<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    die("Unauthorized access.");
}

$doctor_id = (int)$_SESSION['user_id'];
$chat_id   = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;

if ($chat_id <= 0) {
    die("Chat ID is required.");
}

// Make sure the chat belongs to this doctor
$stmt = $conn->prepare("
    SELECT id 
    FROM chats 
    WHERE id = ? AND doctor_id = ?
");
$stmt->bind_param("ii", $chat_id, $doctor_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $stmt->close();
    die("Chat not found or unauthorized.");
}
$stmt->close();

// Reopen the chat
$update = $conn->prepare("UPDATE chats SET status = 'open' WHERE id = ?");
$update->bind_param("i", $chat_id);
if ($update->execute()) {
    $update->close();
    header("Location: view_chat.php?chat_id=" . urlencode($chat_id));
    exit();
} else {
    $update->close();
    die("Failed to reopen chat.");
}
