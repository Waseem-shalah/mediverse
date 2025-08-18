<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    die("Unauthorized access.");
}

$chat_id = $_POST['chat_id'] ?? null;
$new_status = $_POST['status'] ?? null;
$doctor_id = $_SESSION['user_id'];

if (!$chat_id || !in_array($new_status, ['accepted', 'closed'])) {
    die("Invalid input.");
}

// Check if this chat belongs to the doctor
$check = $conn->prepare("SELECT id FROM chats WHERE id = ? AND doctor_id = ?");
$check->bind_param("ii", $chat_id, $doctor_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    die("Chat not found or not authorized.");
}
$check->close();

// Update chat status
$update = $conn->prepare("UPDATE chats SET status = ? WHERE id = ?");
$update->bind_param("si", $new_status, $chat_id);

if ($update->execute()) {
    // Redirect back to chat window
    header("Location: doctor_chat_window.php?chat_id=" . $chat_id);
    exit();
} else {
    echo "Error updating chat status: " . $conn->error;
}

$update->close();
?>
