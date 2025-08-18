<?php
session_start();
require_once 'config.php';

require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Admin-only: block non-admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

/* ---------- Build a clean base URL for redirects ---------- */
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseURL  = $scheme . '://' . $host . $basePath;

// Optional "return" parameter (keeps the admin in context after reply)
$returnRaw = $_GET['return'] ?? 'admin/contact_messages.php';
$returnRel = ltrim($returnRaw, '/');
$messagesURLNoQuery = $baseURL . '/' . $returnRel;

/* ---------- Validate message id ---------- */
$id = $_GET['id'] ?? '';
if (!ctype_digit($id)) {
    die("Invalid message ID.");
}

/* ---------- Load the message to reply to ---------- */
$stmt = $conn->prepare("
  SELECT id, name, email, subject, message, status, reply
    FROM contact_messages
   WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$msg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$msg) {
    die("Message not found.");
}

$error = '';

/* ---------- Handle reply submission ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply_text = trim($_POST['reply'] ?? '');
    if ($reply_text === '') {
        $error = 'Reply cannot be empty.';
    } else {
        // 1) Save the reply and mark as replied (with timestamp)
        $upd = $conn->prepare("
          UPDATE contact_messages
             SET reply = ?, status = 'replied', replied_at = NOW()
           WHERE id = ?
        ");
        $upd->bind_param("si", $reply_text, $id);
        $upd->execute();

        if ($upd->errno) {
            $error = 'Failed to save reply: ' . $upd->error;
        } else {
            // 2) Email the user a formatted response
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'mediverse259@gmail.com';
                $mail->Password   = 'yrecnfqylehxregz'; // Consider env var / app password
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('mediverse259@gmail.com', 'MediVerse Support');
                $mail->addAddress($msg['email'], $msg['name']);

                // Tag with ticket number for context
                $subject = 'Re: ' . ($msg['subject'] ?: 'Your Message') . ' [Ticket #' . $msg['id'] . ']';
                $mail->isHTML(true);
                $mail->Subject = $subject;

                // Escape user-provided content for HTML email
                $safeName    = htmlspecialchars($msg['name'], ENT_QUOTES, 'UTF-8');
                $safeReply   = nl2br(htmlspecialchars($reply_text, ENT_QUOTES, 'UTF-8'));
                $safeSubject = htmlspecialchars($msg['subject'], ENT_QUOTES, 'UTF-8');
                $safeMessage = nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8'));

                // Simple branded HTML layout + plaintext fallback
                $mail->Body = "
                <div style='font-family: Arial, sans-serif; background:#f5f7fb; padding:20px;'>
                  <div style='max-width:600px; margin:auto; background:white; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.1);'>
                    <div style='background:#4f46e5; color:white; padding:16px;'>
                      <h2 style='margin:0;'>MediVerse Support</h2>
                    </div>
                    <div style='padding:20px;'>
                      <p>Dear {$safeName},</p>
                      <p>Thank you for contacting us regarding: <strong>{$safeSubject}</strong></p>
                      <p><em>Your original message:</em></p>
                      <blockquote style='border-left:4px solid #ddd; margin:10px 0; padding-left:10px; color:#555;'>{$safeMessage}</blockquote>
                      <p><em>Our reply:</em></p>
                      <div style='background:#eef2ff; border-left:4px solid #4f46e5; padding:10px;'>{$safeReply}</div>
                      <p style='margin-top:20px;'>Best regards,<br>MediVerse Support Team</p>
                    </div>
                  </div>
                </div>";

                $mail->AltBody = "Dear {$msg['name']},\n\nYour message: {$msg['message']}\n\nOur reply:\n{$reply_text}\n\nMediVerse Support";

                $mail->send();
            } catch (Exception $e) {
                // Optional: log the $e->getMessage() if you want visibility
            }

            // 3) Redirect back to the list, with a small success flag
            $redir = $messagesURLNoQuery . (strpos($messagesURLNoQuery, '?') !== false ? '&' : '?') . 'replied=1';
            header("Location: $redir", true, 302);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reply to Message #<?= htmlspecialchars($msg['id']) ?> | MediVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family:'Segoe UI',sans-serif; background:#f6f9fc; }
    .container { max-width:600px; margin:60px auto; background:#fff;
      padding:30px; border-radius:12px; box-shadow:0 0 15px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
  <?php include 'navbar_loggedin.php'; ?>

  <div class="container">
    <h2>Reply to #<?= htmlspecialchars($msg['id']) ?></h2>
    <p><strong>From:</strong> <?= htmlspecialchars($msg['name']) ?>
      &lt;<?= htmlspecialchars($msg['email']) ?>&gt;</p>
    <p><strong>Subject:</strong> <?= htmlspecialchars($msg['subject']) ?></p>
    <hr>
    <p><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
    <hr>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Keep the previous reply (if any) in the textarea for context -->
    <form method="POST">
      <div class="mb-3">
        <label for="reply" class="form-label">Your Reply</label>
        <textarea id="reply" name="reply" rows="6" class="form-control" required><?= htmlspecialchars($msg['reply'] ?? '') ?></textarea>
      </div>
      <button class="btn btn-primary">Send & Mark Replied</button>
      <a class="btn btn-secondary ms-2" href="<?= htmlspecialchars($messagesURLNoQuery) ?>">Cancel</a>
    </form>
  </div>
</body>
</html>
