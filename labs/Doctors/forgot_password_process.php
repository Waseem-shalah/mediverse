<?php
session_start();
require_once 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * This controller handles the “Forgot Password” form POST.
 * Flow:
 *  1) Validate email input format
 *  2) Verify the email exists in the users table
 *  3) Generate a secure, time-limited reset token and store it with expiry
 *  4) Email a reset link to the user
 *  5) Set a flash message and redirect back to forgot_password.php
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 1) Input + basic validation ---------------------------------------
    $email = trim($_POST['email'] ?? '');
    // Keep last-typed email in session so the form can repopulate on error
    $_SESSION['old_email'] = $email;

    // Validate email format early to avoid unnecessary DB work
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Please enter a valid email address.";
        $_SESSION['message_type'] = "danger";
        header("Location: forgot_password.php");
        exit();
    }

    // --- 2) Check that the email exists ------------------------------------
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        // Fallback: generic server error so we don't leak details
        $_SESSION['message'] = "Server error. Please try again.";
        $_SESSION['message_type'] = "danger";
        header("Location: forgot_password.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        // Email not found → friendly message (don’t reveal whether an account exists in high-security apps)
        $stmt->close();
        $_SESSION['message'] = "Email not found. Please check and try again.";
        $_SESSION['message_type'] = "danger";
        header("Location: forgot_password.php");
        exit();
    }
    $stmt->close();

    // --- 3) Generate token + expiry and persist ----------------------------
    // 32 hex chars (128 bits of entropy) is plenty for a one-time reset link
    $token = bin2hex(random_bytes(16));

    // Build a reset link for the user to click
    // NOTE: Consider deriving the base URL dynamically and using HTTPS in production.
    $resetLink = "http://localhost/labs/Doctors/reset_password.php?token=$token";

    // Token expires in 1 hour (server time)
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Save token + expiry to the user row
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
    if (!$stmt) {
        $_SESSION['message'] = "Server error. Please try again.";
        $_SESSION['message_type'] = "danger";
        header("Location: forgot_password.php");
        exit();
    }
    $stmt->bind_param("sss", $token, $expiry, $email);
    $stmt->execute();
    $stmt->close();

    // --- 4) Send the reset email -------------------------------------------
    $mail = new PHPMailer(true);
    try {
        // SMTP transport
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        /**
         * SECURITY NOTE:
         * These credentials should be stored in environment variables or a secrets
         * manager, not hard-coded. Example:
         *   $mail->Username = getenv('SMTP_USER');
         *   $mail->Password = getenv('SMTP_PASS');
         */
        $mail->Username   = 'mediverse259@gmail.com';
        $mail->Password   = 'yrecnfqylehxregz'; // Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('mediverse259@gmail.com', 'MediVerse');
        $mail->addAddress($email);

        // Compose the email (HTML + plaintext)
        $mail->isHTML(true);
        $mail->Subject = 'Reset your MediVerse password';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color:#333'>
                <h2>Password Reset Request</h2>
                <p>We received a request to reset your password for your MediVerse account.</p>
                <p>Click the button below to set a new password (link expires in 1 hour):</p>
                <p style='margin:20px 0'>
                    <a href='$resetLink' style='display:inline-block;background:#0d6efd;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-weight:600'>
                        Reset Password
                    </a>
                </p>
                <p>If the button above doesn't work, copy and paste this link into your browser:</p>
                <p><a href='$resetLink'>$resetLink</a></p>
                <hr style='border:none;border-top:1px solid #eee;margin:24px 0'>
                <p style='font-size:12px;color:#666'>If you didn't request this, you can safely ignore this email.</p>
            </div>
        ";
        $mail->AltBody = "Password Reset Request\n\nOpen this link to reset your password:\n$resetLink\n\nIf you didn't request this, ignore this email.";

        $mail->send();

        // Success flash message (shown on forgot_password.php)
        $_SESSION['message'] = "We emailed you a password reset link. Please check your inbox.";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        // Gracefully handle email failure (do not expose internal errors in production)
        $_SESSION['message'] = "Email could not be sent. Error: {$mail->ErrorInfo}";
        $_SESSION['message_type'] = "danger";
    }

    // --- 5) Redirect back to the form with a flash message -----------------
    header("Location: forgot_password.php");
    exit();
}
?>
