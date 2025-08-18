<?php
session_start();
require_once 'config.php'; // Database connection

// --- Security check: make sure user is logged in ---
if (!isset($_SESSION['user_id'])) {
    exit; // Stop script if not logged in
}

// --- Get the chat_id from the URL query string ---
$chat_id = $_GET['chat_id'] ?? null;

// If no chat_id is provided, stop and show an error
if (!$chat_id) {
    exit("Missing chat ID.");
}

// --- SQL query: fetch all messages for this chat ---
// We also join with the users table to get the sender’s name and role
$query = "
    SELECT m.*, u.name, u.role 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.chat_id = ? 
    ORDER BY m.sent_at ASC
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    // If query preparation failed, show DB error (developer-friendly)
    die("Prepare failed: " . $conn->error);
}

// Bind the chat_id safely (avoids SQL injection)
$stmt->bind_param("i", $chat_id);
$stmt->execute();
$result = $stmt->get_result();

// --- Loop through all messages and display them ---
while ($row = $result->fetch_assoc()) {
    // Decide CSS class based on whether current user sent it
    $class = ($row['sender_id'] == $_SESSION['user_id']) ? 'sent' : 'received';

    // Output each message block
    echo '<div class="message ' . $class . '">';
    // Show sender name (HTML-escaped for safety)
    echo '<strong>' . htmlspecialchars($row['name']) . ':</strong> ';
    // Show the actual message text
    echo htmlspecialchars($row['message']);
    // Show timestamp in small text
    echo '<br><small>' . $row['sent_at'] . '</small>';
    echo '</div>';
}
?>
