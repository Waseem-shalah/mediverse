<?php
// Doctor/doctor_chat_window.php
// Purpose: Doctor’s chat window with a patient.
// Notes: Only brief human-friendly comments added. No logic/style changes.

session_start();
require_once '../config.php';
require '../navbar_loggedin.php';

// Gate: doctor-only access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    die("Unauthorized access.");
}

$doctor_id = (int)$_SESSION['user_id'];
$chat_id   = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
if ($chat_id <= 0) { die("Chat ID missing."); }

// Fetch chat + patient info for header/info block
$stmt = $conn->prepare("
    SELECT 
        c.status,
        u.name AS patient_name,
        u.gender,
        u.date_of_birth,
        u.location,
        u.id   AS patient_id,
        u.user_id AS user_public_id
    FROM chats c
    JOIN users u ON c.patient_id = u.id
    WHERE c.id = ? AND c.doctor_id = ?
");
$stmt->bind_param("ii", $chat_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { die('Chat not found.'); }

$chat           = $result->fetch_assoc();
$status         = $chat['status'];
$patient_name   = $chat['patient_name'];
$gender         = $chat['gender'];
$dob            = $chat['date_of_birth'];
$location       = $chat['location'];
$patient_id     = (int)$chat['patient_id'];
$user_public_id = (int)$chat['user_public_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Chat with <?= htmlspecialchars($patient_name) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap only; keep same look/feel -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Layout matches patient page style */
        .chat-container {
            max-width: 800px;
            margin: 30px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .chat-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-box {
            height: 450px;
            overflow-y: auto;
            padding: 20px;
            background-color: #f1f3f5;
        }
        .message {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 20px;
            margin-bottom: 12px;
            display: inline-block;
            font-size: 15px;
        }
        /* Doctor view: doctor messages on right (blue), patient messages on left (gray) */
        .sent {
            background-color: #0d6efd;
            color: #fff;
            float: right;
            clear: both;
        }
        .received {
            background-color: #e9ecef;
            color: #000;
            float: left;
            clear: both;
        }
        .chat-footer {
            border-top: 1px solid #dee2e6;
            padding: 15px;
            background: #fff;
        }

        /* Patient info block above messages */
        .patient-info {
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 12px 20px;
        }
        .patient-info .row > div { margin-bottom: 6px; }
        .patient-info .label { color:#6c757d; font-weight:600; }
        .patient-info .pill {
            display:inline-block; background:#eef2ff; color:#4338ca;
            padding:4px 10px; border-radius:999px; font-weight:600; font-size:12px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="chat-container">
        <!-- Header: name + status -->
        <div class="chat-header">
            <h5 class="mb-0">Chat with <?= htmlspecialchars($patient_name) ?></h5>
            <span class="badge bg-<?= $status === 'accepted' ? 'success' : ($status === 'pending' ? 'warning' : 'secondary') ?>">
                <?= ucfirst($status) ?>
            </span>
        </div>

        <!-- Compact patient info (ID + quick details + history link) -->
        <div class="patient-info">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div class="mb-2">
                    <span class="pill">ID: <?= $user_public_id ?></span>
                </div>
                <div class="mb-2">
                    <a href="patient_history.php?patient_id=<?= $patient_id ?>" class="btn btn-sm btn-info">📋 View History</a>
                </div>
            </div>
            <div class="row small">
                <div class="col-md-3"><span class="label">Name:</span> <?= htmlspecialchars($patient_name) ?></div>
                <div class="col-md-3"><span class="label">Gender:</span> <?= htmlspecialchars($gender ?: 'Not specified') ?></div>
                <div class="col-md-3"><span class="label">DOB:</span> <?= htmlspecialchars($dob ?: 'Not provided') ?></div>
                <div class="col-md-3"><span class="label">Location:</span> <?= htmlspecialchars($location ?: 'Not provided') ?></div>
            </div>
        </div>

        <!-- Messages viewport (auto-refreshed) -->
        <div class="chat-box" id="chat-messages">
            <!-- Server-rendered HTML of messages will be injected here -->
        </div>

        <!-- Footer: input + send (only when accepted) and status actions -->
        <div class="chat-footer">
            <?php if ($status === 'accepted'): ?>
                <!-- Send message without page reload -->
                <form id="message-form" method="POST" action="../send_message.php" class="d-flex gap-2">
                    <input type="hidden" name="chat_id" value="<?= $chat_id ?>">
                    <input type="text" name="message" id="message-input" class="form-control" placeholder="Type your message...">
                    <button type="button" class="btn btn-outline-secondary" onclick="startDictation()" title="Voice to Text">🎤</button>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
            <?php else: ?>
                <!-- Read-only when not accepted -->
                <input type="text" class="form-control" placeholder="Chat not available" disabled>
            <?php endif; ?>

            <?php if ($status === 'pending'): ?>
                <!-- Offer accept when pending -->
                <div class="alert alert-info mt-3 mb-0">⏳ Waiting for patient/system to accept…</div>
                <form method="POST" action="update_chat_status.php" class="mt-2">
                    <input type="hidden" name="chat_id" value="<?= $chat_id ?>">
                    <input type="hidden" name="status" value="accepted">
                    <button class="btn btn-success btn-sm">✅ Accept Chat</button>
                </form>
            <?php elseif ($status === 'closed'): ?>
                <!-- Closed notice -->
                <div class="alert alert-danger mt-3 mb-0">🚫 Chat closed.</div>
            <?php else: ?>
                <!-- Allow closing if active -->
                <form method="POST" action="update_chat_status.php" class="mt-3">
                    <input type="hidden" name="chat_id" value="<?= $chat_id ?>">
                    <input type="hidden" name="status" value="closed">
                    <button class="btn btn-outline-danger btn-sm">❌ Close Chat</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Pull latest messages and keep scroll pinned to bottom
function loadMessages() {
    fetch('../fetch_messages.php?chat_id=<?= $chat_id ?>')
        .then(response => response.text())
        .then(data => {
            const box = document.getElementById('chat-messages');
            box.innerHTML = data;
            box.scrollTop = box.scrollHeight; // auto-scroll to newest
        });
}

<?php if ($status === 'accepted'): ?>
// AJAX-submit the message to keep UX snappy
document.getElementById('message-form').addEventListener('submit', function (e) {
    e.preventDefault();
    fetch('../send_message.php', {
        method: 'POST',
        body: new FormData(this)
    }).then(() => {
        document.getElementById('message-input').value = '';
        loadMessages();
    });
});
<?php endif; ?>

// Poll for new messages every 2s (simple long-poll alternative)
setInterval(loadMessages, 2000);
loadMessages();

// Browser speech-to-text (Chrome webkit API) to fill the input
function startDictation() {
    if (!('webkitSpeechRecognition' in window)) {
        alert("Voice recognition is not supported in this browser. Try Google Chrome.");
        return;
    }
    const recognition = new webkitSpeechRecognition();
    recognition.lang = 'en-US';
    recognition.continuous = false;
    recognition.interimResults = false;

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
