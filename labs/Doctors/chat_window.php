<?php
session_start();
require_once 'config.php';
require 'navbar_loggedin.php';

/*
|------------------------------------------------------------------
| Auth guard: only logged-in patients may access this page
|------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    die("Unauthorized access.");
}

$patient_id = $_SESSION['user_id'];

/*
|------------------------------------------------------------------
| Chat ID is required to load a room
|------------------------------------------------------------------
*/
$chat_id = $_GET['chat_id'] ?? null;
if (!$chat_id) {
    die("Chat ID missing.");
}

/*
|------------------------------------------------------------------
| Load chat header info (status + doctor who owns the chat)
| Restrict to this patient so others can't see it
|------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT c.status, c.doctor_id, u.name AS doctor_name 
    FROM chats c 
    JOIN users u ON c.doctor_id = u.id 
    WHERE c.id = ? AND c.patient_id = ?
");
$stmt->bind_param("ii", $chat_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Chat not found.");
}

$chat         = $result->fetch_assoc();
$status       = $chat['status'];        // accepted | pending | closed
$doctor_id    = $chat['doctor_id'];
$doctor_name  = $chat['doctor_name'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat with Dr. <?= htmlspecialchars($doctor_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Simple centered card-like chat container */
        .chat-container {
            max-width: 800px;
            margin: 30px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        /* Header shows doctor name + chat status badge */
        .chat-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        /* Scrollable message area */
        .chat-box {
            height: 450px;
            overflow-y: auto;
            padding: 20px;
            background-color: #f1f3f5;
        }
        /* Base message bubble */
        .message {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 20px;
            margin-bottom: 12px;
            display: inline-block;
            font-size: 15px;
        }
        /* Messages sent by the current user (patient) */
        .sent {
            background-color: #0d6efd;
            color: #fff;
            float: right;
            clear: both;
        }
        /* Messages received from the doctor */
        .received {
            background-color: #e9ecef;
            color: #000;
            float: left;
            clear: both;
        }
        /* Footer contains composer + actions */
        .chat-footer {
            border-top: 1px solid #dee2e6;
            padding: 15px;
            background: #fff;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="chat-container">
        <!-- Top bar: doctor name + status -->
        <div class="chat-header">
            <h5 class="mb-0">Chat with Dr. <?= htmlspecialchars($doctor_name) ?></h5>
            <span class="badge bg-<?= $status === 'accepted' ? 'success' : ($status === 'pending' ? 'warning' : 'secondary') ?>">
                <?= ucfirst($status) ?>
            </span>
        </div>

        <!-- Messages get injected here via fetch_messages.php (AJAX) -->
        <div class="chat-box" id="chat-messages">
            <!-- Messages will be loaded here -->
        </div>

        <!-- Composer: enabled only if chat is accepted -->
        <div class="chat-footer">
            <?php if ($status === 'accepted'): ?>
                <form id="message-form" method="POST" action="send_message.php" class="d-flex gap-2">
                    <!-- Server expects chat_id + message in POST -->
                    <input type="hidden" name="chat_id" value="<?= $chat_id ?>">
                    <input type="text" name="message" id="message-input" class="form-control" placeholder="Type your message...">
                    <!-- Optional voice-to-text for Chrome (webkitSpeechRecognition) -->
                    <button type="button" class="btn btn-outline-secondary" onclick="startDictation()" title="Voice to Text">🎤</button>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
            <?php else: ?>
                <!-- Read-only composer if chat pending/closed -->
                <input type="text" class="form-control" placeholder="Chat not available" disabled>
            <?php endif; ?>

            <!-- Status hints -->
            <?php if ($status === 'pending'): ?>
                <div class="alert alert-info mt-3 mb-0">⏳ Waiting for doctor to accept the chat...</div>
            <?php elseif ($status === 'closed'): ?>
                <div class="alert alert-danger mt-3 mb-0">
                    🚫 Chat closed by doctor.
                    <br>
                    <a href="start_chat.php?doctor_id=<?= $doctor_id ?>" class="btn btn-success btn-sm mt-2">Start New Chat</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
/*
|------------------------------------------------------------------
| Poll messages every 2s and autoscroll to bottom.
| The server returns HTML bubbles (sanitized) per message.
|------------------------------------------------------------------
*/
function loadMessages() {
    fetch('fetch_messages.php?chat_id=<?= $chat_id ?>')
        .then(response => response.text())
        .then(data => {
            const box = document.getElementById('chat-messages');
            box.innerHTML = data;
            box.scrollTop = box.scrollHeight; // always stick to the latest
        });
}

<?php if ($status === 'accepted'): ?>
/*
|------------------------------------------------------------------
| AJAX send (prevents full page reload)
|------------------------------------------------------------------
*/
document.getElementById('message-form').addEventListener('submit', function (e) {
    e.preventDefault();

    // If empty, do nothing (optional UX guard)
    const input = document.getElementById('message-input');
    if (!input.value.trim()) return;

    fetch('send_message.php', {
        method: 'POST',
        body: new FormData(this)
    }).then(() => {
        input.value = '';
        loadMessages();
    });
});
<?php endif; ?>

// Start polling
setInterval(loadMessages, 2000);
loadMessages();

/*
|------------------------------------------------------------------
| Voice-to-Text using webkitSpeechRecognition (Chrome-only)
|------------------------------------------------------------------
*/
function startDictation() {
    if (!('webkitSpeechRecognition' in window)) {
        alert("Voice recognition is not supported in this browser. Try Google Chrome.");
        return;
    }

    const recognition = new webkitSpeechRecognition();
    recognition.lang = 'en-US';
    recognition.continuous = false;     // stop after one phrase
    recognition.interimResults = false; // we only want the final transcript

    recognition.start();

    recognition.onresult = function (event) {
        const transcript = event.results[0][0].transcript;
        document.getElementById('message-input').value += transcript;
    };

    recognition.onerror = function (event) {
        console.error("Speech recognition error:", event.error);
        alert("Could not recognize speech. Please try again.");
    };
}
</script>
</body>
</html>
