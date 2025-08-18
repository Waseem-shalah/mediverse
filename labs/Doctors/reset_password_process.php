<?php
require 'config.php'; // DB connection ($conn)

// Handle only POST requests (form submission)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Token from the reset link (usually sent as a hidden input)
    $token = $_POST['token'];

    // Hash the new password before storing it (never store plain text)
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    // 1) Find the user with this reset token that hasn't expired yet
    $stmt = $conn->prepare("
        SELECT id 
        FROM users 
        WHERE reset_token = ? 
          AND token_expiry > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    // If we found exactly one user, proceed to update the password
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        // 2) Update the password and clear the token so the link can’t be reused
        $stmt = $conn->prepare("
            UPDATE users 
            SET password = ?, reset_token = NULL, token_expiry = NULL 
            WHERE id = ?
        ");
        $stmt->bind_param("si", $new_password, $user_id);
        $stmt->execute();

        // Simple confirmation
        echo "Your password has been reset. You can now <a href='login.php'>login</a>.";
    } else {
        // Token is invalid or expired
        echo "Invalid or expired token.";
    }
}
?>
