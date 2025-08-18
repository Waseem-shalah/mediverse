<?php
session_start();
require_once 'config.php';

// --- Security check: only logged-in users can send messages ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    exit("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$message = trim($_POST['message'] ?? '');
$chat_id = $_POST['chat_id'] ?? null;

// Must have both chat_id and a non-empty message
if (!$chat_id || empty($message)) {
    http_response_code(400); // Bad request
    exit("Missing chat ID or empty message.");
}

// --- Optional safety check: confirm user is part of this chat ---
$stmt = $conn->prepare("
    SELECT * FROM chats 
    WHERE id = ? AND (patient_id = ? OR doctor_id = ?)
");
$stmt->bind_param("iii", $chat_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// If no rows, user tried to send to a chat they don’t belong to
if ($result->num_rows === 0) {
    http_response_code(403); // Forbidden
    exit("You are not part of this chat.");
}
$stmt->close();

// --- Insert the new message into the messages table ---
$sent_at = date("Y-m-d H:i:s"); // Timestamp for when message was sent
$seen = 0;                      // Default: not seen by recipient

$stmt = $conn->prepare("
    INSERT INTO messages (chat_id, sender_id, message, sent_at, seen) 
    VALUES (?, ?, ?, ?, ?)
");
if (!$stmt) {
    http_response_code(500); // Server error
    exit("Prepare failed: " . $conn->error);
}

$stmt->bind_param("iissi", $chat_id, $user_id, $message, $sent_at, $seen);
$stmt->execute();
$stmt->close();

// Everything went fine
http_response_code(200);
echo "Message sent.";
?>
