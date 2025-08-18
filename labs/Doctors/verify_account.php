<?php
// Start session and make sure the user is logged in.
// If not, redirect them back to login.
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Your Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap CSS for styling -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Card styling for centered, nice look */
    .card { max-width: 440px; margin: 8vh auto; border-radius:16px }
    /* Input styling for the code box */
    .code-input { letter-spacing: 6px; text-align: center; font-size: 1.25rem; }
  </style>
</head>
<body class="bg-light">
  <!-- Show navbar if logged in -->
  <?php @include "navbar_loggedin.php"; ?>

  <div class="card shadow">
    <div class="card-body">
      <h4 class="mb-2">Verify your account</h4>
      <p class="text-muted">
        We emailed you a 6-digit code. Enter it below to activate your account.
      </p>

      <!-- Display error or success messages passed as query params -->
      <?php if (!empty($_GET['err'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_GET['err']) ?></div>
      <?php elseif (!empty($_GET['ok'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['ok']) ?></div>
      <?php endif; ?>

      <!-- Verification form -->
      <form method="POST" action="verify_account_process.php" class="mt-3">
        <div class="mb-3">
          <label for="code" class="form-label">Verification code</label>
          <!-- Only allows exactly 6 digits -->
          <input id="code" name="code" class="form-control code-input"
                 maxlength="6" pattern="\d{6}" placeholder="••••••" required>
          <div class="form-text">Exactly 6 digits.</div>
        </div>

        <!-- Buttons: verify or request resend -->
        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="submit">Verify</button>
          <a href="resend_code.php" class="btn btn-outline-secondary">Resend code</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
