<?php
session_start();
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';

/**
 * Build the absolute base URL for the current script.
 * Works on localhost and production, with http/https detection.
 */
function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/\\');
    return $scheme . '://' . $host . $path;
}

/**
 * Send a verification email containing a 6-digit code and a link
 * back to the verification page. Returns true on success.
 */
function sendVerificationEmail(string $toEmail, string $toName, string $code): bool {
    $verifyLink = base_url() . '/verify_account.php'; // where user types the code
    $year = date('Y');

    // HTML version (nicely formatted email)
    $html = <<<HTML
<!doctype html>
<html>
  <head><meta charset="utf-8"><meta name="color-scheme" content="light only"></head>
  <body style="margin:0;background:#f6f9fc;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f9fc;padding:32px 0;">
      <tr>
        <td align="center">
          <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:16px;box-shadow:0 6px 24px rgba(18,38,63,.08);padding:32px">
            <tr>
              <td style="text-align:center;">
                <div style="font-size:24px;font-weight:700;letter-spacing:.3px;color:#111827;">MediVerse</div>
                <div style="margin-top:8px;color:#6b7280">Here’s your new verification code</div>
              </td>
            </tr>
            <tr><td style="height:24px"></td></tr>
            <tr>
              <td style="font-size:16px;color:#111827;line-height:1.6">
                Hi {$toName},<br><br>
                Use the code below to verify your account:
              </td>
            </tr>
            <tr><td style="height:16px"></td></tr>
            <tr>
              <td align="center">
                <div style="display:inline-block;background:#111827;color:#ffffff;border-radius:12px;padding:14px 22px;font-size:28px;letter-spacing:6px;font-weight:700">
                  {$code}
                </div>
              </td>
            </tr>
            <tr><td style="height:16px"></td></tr>
            <tr>
              <td align="center">
                <a href="{$verifyLink}"
                   style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;padding:12px 18px">
                  Open verification page
                </a>
              </td>
            </tr>
          </table>
          <div style="color:#9ca3af;font-size:12px;margin-top:16px">© {$year} MediVerse</div>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    // Plain-text fallback for clients that don't render HTML
    $plain = "Your verification code is: {$code}\nOpen: {$verifyLink}";

    // Configure and send via PHPMailer (Gmail SMTP)
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mediverse259@gmail.com'; // consider moving to env vars
        $mail->Password   = 'yrecnfqylehxregz';       // consider using an app password env var
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('mediverse259@gmail.com', 'MediVerse');
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your new MediVerse verification code';
        $mail->Body    = $html;
        $mail->AltBody = $plain;

        return $mail->send();
    } catch (Exception $e) {
        // Log the specific reason; return false so caller can decide what to show
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ---- Controller: only logged-in users can request a new code ----
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$userId = (int)$_SESSION['user_id'];

// Load recipient email/name and activation status
$stmt = $conn->prepare("SELECT email, name, is_activated FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    header("Location: verify_account.php?err=User+not+found");
    exit();
}
$u = $res->fetch_assoc();
$stmt->close();

// If already activated, no need to resend → send them to dashboard
if ((int)$u['is_activated'] === 1) {
    header("Location: patient_dashboard.php");
    exit();
}

// Generate a fresh 6-digit code and store it for this user
$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$upd  = $conn->prepare("UPDATE users SET verification_code = ? WHERE id = ?");
$upd->bind_param("si", $code, $userId);
$upd->execute();
$upd->close();

// Fire off the email (ignore return here, but you could branch on false if you want)
sendVerificationEmail($u['email'], $u['name'] ?: $u['email'], $code);

// Redirect back to the verification page with a friendly note
header("Location: verify_account.php?ok=Code+resent");
exit();
