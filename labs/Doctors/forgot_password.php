<?php
session_start();

/**
 * Pull one-time flash message (if any) set by forgot_password_process.php.
 * - $_SESSION['message']      → the text to show
 * - $_SESSION['message_type'] → Bootstrap alert type (success | danger | warning | info)
 * After reading, we unset so it doesn’t persist on refresh/next view.
 */
$msg  = $_SESSION['message'] ?? null;
$type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);

/**
 * Preserve the last-typed email on validation error, so the user
 * doesn’t have to retype it. Clear it once we’ve read it.
 */
$old_email = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Forgot Password | MediVerse</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS only, keeps the page lightweight -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php
// Public navbar (non-auth) so users can navigate elsewhere if needed
include 'navbar.php';
?>

<div class="container mt-5" style="max-width:720px;">
  <h2 class="text-center mb-4">Reset Your Password</h2>

  <?php if ($msg): ?>
    <!-- Flash alert (auto-dismissable). $type maps to Bootstrap alert-* classes -->
    <div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!--
    Main form:
    - Posts to forgot_password_process.php, which:
        * validates the email
        * generates a temp password (or token, depending on implementation)
        * emails the user
        * sets a flash message + redirects back here
    - We re-fill the email input with $old_email on errors for better UX.
  -->
  <form action="forgot_password_process.php" method="post" class="mx-auto">
    <div class="mb-3">
      <label class="form-label">Enter your email</label>
      <input
        type="email"
        name="email"
        class="form-control"
        required
        value="<?= htmlspecialchars($old_email) ?>"
        autocomplete="email"
      >
    </div>

    <button type="submit" class="btn btn-warning w-100">
      Send Temporary Password
    </button>
  </form>
</div>

<!-- Bootstrap JS bundle (for dismissing alerts, etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
