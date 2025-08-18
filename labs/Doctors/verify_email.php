<?php
// Start a session if it isn't already running (used for flash messages).
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Load DB connection ($conn) and other config.
require_once __DIR__ . '/config.php';

// Read verification params from the link.
// `token` proves the request came from the email, `u` is the user id.
$token = $_GET['token'] ?? '';
$uid   = isset($_GET['u']) ? (int)$_GET['u'] : 0;

// Quick sanity check: must have both a non-empty token and a valid user id.
if ($token === '' || $uid <= 0) {
    $_SESSION['login_error'] = "Invalid verification link.";
    header("Location: login.php"); exit();
}

// Try to find a user with this id+token whose record we can verify.
// We also fetch current verification status and token expiry time.
$stmt = $conn->prepare("
    SELECT id, email_verified, verify_expires
    FROM users
    WHERE id = ? AND verify_token = ?
    LIMIT 1
");
$stmt->bind_param("is", $uid, $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If no match, the link is invalid (wrong/used token or wrong user).
if (!$user) {
    $_SESSION['login_error'] = "Verification link is invalid.";
    header("Location: login.php"); exit();
}

// If a token expiry is set, make sure it hasn't passed yet.
if ($user['verify_expires'] !== null && strtotime($user['verify_expires']) < time()) {
    $_SESSION['login_error'] = "Verification link has expired. Please request a new one.";
    header("Location: login.php"); exit();
}

// If the email is already verified, let the user know and send them to sign in.
if ((int)$user['email_verified'] === 1) {
    $_SESSION['login_notice'] = "Your email is already verified. You can sign in.";
    header("Location: login.php"); exit();
}

// Mark the email as verified and clear the token+expiry so the link can't be reused.
$stmt = $conn->prepare("
    UPDATE users
    SET email_verified = 1,
        verify_token = NULL,
        verify_expires = NULL
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$stmt->close();

// Success message and redirect to login.
$_SESSION['login_notice'] = "Email verified successfully. You can now sign in.";
header("Location: login.php"); exit();
