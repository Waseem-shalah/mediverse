<?php
// contact_us_loggedin.php
// This page allows logged-in users to contact support without having to re-enter their name/email.

session_start();
require_once 'config.php';

// ✅ Only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Show the logged-in navbar
require 'navbar_loggedin.php';

$user_id = (int)$_SESSION['user_id'];

// ✅ Always fetch fresh name/email from DB (session may be outdated)
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fallback to session if DB is missing values
$name  = $u['name']  ?? ($_SESSION['name'] ?? '');
$email = $u['email'] ?? '';

$success = '';
$error   = '';
$subject = '';
$message = '';

/**
 * Helper function:
 * Check if the `contact_messages` table has a `user_id` column.
 * (Some projects may have an older schema without it.)
 */
function cm_has_user_id(mysqli $conn): bool {
    $q = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'contact_messages'
          AND COLUMN_NAME = 'user_id'
        LIMIT 1
    ");
    if (!$q) return false;
    $q->execute();
    $has = $q->get_result()->num_rows === 1;
    $q->close();
    return $has;
}

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Do NOT trust posted name/email → always use DB values
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validation
    if ($subject === '' || $message === '') {
        $error = 'Please fill in both Subject and Message.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Your account email appears invalid. Please update your profile.';
    } else {
        // Check schema version: with or without user_id column
        $hasUserId = cm_has_user_id($conn);

        if ($hasUserId) {
            // Insert with user_id
            $ins = $conn->prepare("
                INSERT INTO contact_messages
                  (user_id, name, email, subject, message)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->bind_param("issss", $user_id, $name, $email, $subject, $message);
        } else {
            // Legacy: insert without user_id
            $ins = $conn->prepare("
                INSERT INTO contact_messages
                  (name, email, subject, message)
                VALUES (?, ?, ?, ?)
            ");
            $ins->bind_param("ssss", $name, $email, $subject, $message);
        }

        if (!$ins) {
            $error = 'Could not prepare save. Please try again later.';
        } else {
            $ins->execute();
            if ($ins->errno) {
                $error = 'Could not save your message. Please try again later.';
            } else {
                $success = 'Thanks! Your message was sent to support.';
                // Clear form after successful send
                $subject = $message = '';
            }
            $ins->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Support | MediVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family:'Segoe UI',sans-serif; background:#f6f9fc; margin:0; padding:0; }
    .contact-container {
      max-width: 720px; margin: 32px auto; background: #fff; padding: 28px;
      border-radius: 14px; box-shadow: 0 10px 30px rgba(2,6,23,.08);
      border: 1px solid rgba(2,6,23,.06);
    }
    .contact-container h2 { font-weight:800; color:#0f172a; letter-spacing:.2px; }
    .form-label { font-weight:600; color:#334155; }
    .form-control[readonly] { background:#f8fafc; }
  </style>
</head>
<body>

<div class="contact-container">
  <h2>Contact Support</h2>

  <!-- Show error or success messages -->
  <?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success mt-3"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Contact form -->
  <form method="POST" novalidate>
    <div class="row g-3">
      <!-- Name and Email shown but locked -->
      <div class="col-md-6">
        <label for="name" class="form-label">Your Name</label>
        <input id="name" type="text" class="form-control" value="<?= htmlspecialchars($name) ?>" readonly>
      </div>
      <div class="col-md-6">
        <label for="email" class="form-label">Your Email</label>
        <input id="email" type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" readonly>
      </div>
    </div>

    <div class="mt-3">
      <label for="subject" class="form-label">Subject</label>
      <input id="subject" name="subject" type="text" class="form-control" required
             value="<?= htmlspecialchars($subject) ?>">
    </div>

    <div class="mt-3">
      <label for="message" class="form-label">Message</label>
      <textarea id="message" name="message" rows="5" class="form-control" required><?= htmlspecialchars($message) ?></textarea>
    </div>

    <div class="text-center mt-4">
      <button type="submit" class="btn btn-primary px-4">Send Message</button>
    </div>
  </form>
</div>

</body>
</html>
