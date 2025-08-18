<?php
session_start();
require_once 'config.php';

// ✅ Only allow patients to access this page
if ($_SESSION['role'] !== 'patient') exit("Unauthorized");

// Get the current chat ID from the request
$chat_id = $_GET['chat_id'] ?? null;
$patient_id = $_SESSION['user_id'];

// ✅ Make sure the chat belongs to this patient
$stmt = $conn->prepare("SELECT status FROM chats WHERE id=? AND patient_id=?");
$stmt->bind_param("ii", $chat_id, $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$chat = $result->fetch_assoc();
if (!$chat) exit("Chat not found");
$status = $chat['status'];

// ✅ Fetch all messages for this chat, with sender names
$msgs = $conn->prepare("
    SELECT m.message, m.sent_at, u.name, m.sender_id
    FROM messages m 
    JOIN users u ON u.id = m.sender_id 
    WHERE m.chat_id = ? 
    ORDER BY m.sent_at ASC
");
$msgs->bind_param("i", $chat_id);
$msgs->execute();
$messages = $msgs->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!-- Chat Header -->
<h3>Chat with Doctor</h3>

<!-- Chat Messages Box -->
<div style="border:1px solid #ccc; padding:10px; max-height:300px; overflow-y:scroll;">
    <?php foreach ($messages as $msg): ?>
        <!-- Display each message with sender name and timestamp -->
        <p>
            <strong><?= htmlspecialchars($msg['name']) ?>:</strong> 
            <?= htmlspecialchars($msg['message']) ?> 
            <small><?= $msg['sent_at'] ?></small>
        </p>
    <?php endforeach; ?>
</div>

<!-- ✅ Show different options depending on chat status -->
<?php if ($status === 'active'): ?>
    <!-- If chat is active, allow patient to send a message -->
    <form method="POST" action="send_message.php">
        <input type="hidden" name="chat_id" value="<?= $chat_id ?>">
        <textarea name="message" required></textarea><br>
        <button type="submit">Send</button>
    </form>

<?php elseif ($status === 'pending'): ?>
    <!-- If doctor hasn’t accepted yet -->
    <p>⏳ Waiting for the doctor to accept...</p>

<?php else: ?>
    <!-- If chat was closed -->
    <p>❌ Chat is closed. 
        <a href="start_chat.php?doctor_id=<?= $_GET['doctor_id'] ?>">Start New Chat</a>
    </p>
<?php endif; ?>
