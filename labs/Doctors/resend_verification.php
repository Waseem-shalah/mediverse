<?php
// Start session if not already started (used for flash messages)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/config.php';       // DB connection ($conn)
require_once __DIR__ . '/vendor/autoload.php'; // Composer autoload for PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Expect a user id in the query string (u=?). If missing, send to login.
$uid = isset($_GET['u']) ? (int)$_GET['u'] : 0;
if ($uid <= 0) { header("Location: login.php"); exit(); }

// Fetch the user; only resend if not yet verified
$stmt = $conn->prepare("SELECT email, email_verified FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { header("Location: login.php"); exit(); }
if ((int)$user['email_verified'] === 1) {
    // Already verified → no need to resend
    $_SESSION['login_notice'] = "Email is already verified. Please sign in.";
    header("Location: login.php"); exit();
}

// Create a fresh token and 24h expiry so old links can’t be reused
$verifyToken  = bin2hex(random_bytes(32));
$verifyExpiry = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');

$stmt = $conn->prepare("UPDATE users SET verify_token = ?, verify_expires = ? WHERE id = ?");
$stmt->bind_param("ssi", $verifyToken, $verifyExpiry, $uid);
$stmt->execute();
$stmt->close();

// Build a full verification URL (works on localhost and prod)
$host = isset($_SERVER['HTTP_HOST']) ? ('http://' . $_SERVER['HTTP_HOST']) : '';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$verifyUrl = $host . $base . "/verify_email.php?token=" . urlencode($verifyToken) . "&u=" . $uid;

try {
    // Configure and send the email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mediverse259@gmail.com';      // Tip: move to env/secret config
    $mail->Password   = 'yrecnfqylehxregz';            // Tip: use app password via env var
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS on port 587
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('mediverse259@gmail.com', 'MediVerse');
    $mail->addAddress($user['email']);

    $mail->isHTML(true);
    $mail->Subject = 'Verify your MediVerse email';
    // Simple HTML body with a button-style link
    $mail->Body = '
      <p>Click the button to verify your email:</p>
      <p><a href="'.htmlspecialchars($verifyUrl, ENT_QUOTES).'" style="display:inline-block;padding:12px 20px;background:#0ea5e9;color:#fff;text-decoration:none;border-radius:10px">Verify my email</a></p>
      <p>This link expires in 24 hours.</p>';
    // Plain-text fallback
    $mail->AltBody = "Verify your email:\n$verifyUrl\n\nThis link expires in 24 hours.";

    $mail->send();
    $_SESSION['login_notice'] = "Verification email resent. Please check your inbox.";
} catch (Exception $e) {
    // Log the exact error for admins; show a generic message to the user
    error_log("Resend verify failed: ".$e->getMessage());
    $_SESSION['login_error'] = "Could not send verification email. Please try again later.";
}

// Always redirect back to login afterward
header("Location: login.php"); exit();
