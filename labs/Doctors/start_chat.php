<?php
session_start();
require_once 'config.php';

// Only logged-in patients can start or reopen a chat
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    die("Unauthorized access.");
}

$patient_id = $_SESSION['user_id'];
// Doctor ID can come from either POST or GET
$doctor_id = $_POST['doctor_id'] ?? $_GET['doctor_id'] ?? null;

if (!$doctor_id) {
    die("Doctor ID missing.");
}

// --- Check if a chat already exists between this patient and doctor ---
$stmt = $conn->prepare("SELECT id, status FROM chats WHERE patient_id = ? AND doctor_id = ?");
if (!$stmt) {
    die("Prepare failed (SELECT): " . $conn->error);
}
$stmt->bind_param("ii", $patient_id, $doctor_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Chat exists → fetch its id and status
    $stmt->bind_result($chat_id, $status);
    $stmt->fetch();
    $stmt->close();

    // If the chat was closed, reopen it by setting status back to "pending"
    if ($status === 'closed') {
        $new_status = 'pending';
        $update_stmt = $conn->prepare("
            UPDATE chats 
            SET status = ?, created_at = NOW() 
            WHERE id = ?
        ");
        if (!$update_stmt) {
            die("Prepare failed (UPDATE): " . $conn->error);
        }
        $update_stmt->bind_param("si", $new_status, $chat_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
} else {
    // No chat yet → create a new one
    $stmt->close();

    $status = 'pending';
    $created_at = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("
        INSERT INTO chats (patient_id, doctor_id, status, created_at) 
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) {
        die("Prepare failed (INSERT): " . $conn->error);
    }
    $stmt->bind_param("iiss", $patient_id, $doctor_id, $status, $created_at);
    $stmt->execute();
    $chat_id = $stmt->insert_id; // Get new chat ID
    $stmt->close();
}

// Finally → redirect user straight into the chat window
header("Location: chat_window.php?chat_id=$chat_id");
exit();
?>
