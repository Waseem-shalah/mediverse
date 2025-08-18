<?php
session_start();

// --- Retrieve possible error messages set during login_process.php ---
$error         = $_SESSION['login_error']        ?? ''; // Error text (if any)
$register_link = !empty($_SESSION['show_register_link']); // Flag: should we show "register" link?
$is_html       = !empty($_SESSION['login_error_is_html']); // Flag: is error safe HTML?

// --- Clear them immediately so they don't show again on page refresh ---
unset($_SESSION['login_error'], $_SESSION['show_register_link'], $_SESSION['login_error_is_html']);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Login | MediVerse</title>
  <!-- Bootstrap CSS for styling -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?> <!-- Top navigation bar -->

<div class="container mt-5">
  <h2 class="mb-4 text-center">Login to MediVerse</h2>

  <!-- If there’s an error message, show it -->
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger text-center">
      <?php if ($is_html): ?>
        <?= $error // Show as raw HTML (useful if error contains links) ?>
      <?php else: ?>
        <?= htmlspecialchars($error) // Safe plain text ?>
      <?php endif; ?>

      <!-- Optional "register" link if login suggested it -->
      <?php if ($register_link): ?>
        <br><a href="register.php" class="text-decoration-underline">Click here to register.</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Login Form -->
  <form action="login_process.php" method="post" class="w-50 mx-auto" novalidate>
    <!-- Email or Username field -->
    <div class="mb-3">
      <label class="form-label" for="username">Email or Username</label>
      <input type="text" id="username" name="username" class="form-control" required>
    </div>

    <!-- Password field with "Show/Hide" toggle -->
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <div class="input-group">
        <input type="password" id="password" name="password" class="form-control" required>
        <button type="button"
                class="btn btn-outline-secondary"
                id="togglePw"
                aria-label="Show password"
                aria-pressed="false">Show</button>
      </div>
    </div>

    <!-- Login button -->
    <button type="submit" class="btn btn-primary w-100">Login</button>

    <!-- Forgot password link -->
    <div class="text-end mt-2">
      <a href="forgot_password.php">Forgot Password?</a>
    </div>
  </form>
</div>

<script>
  // --- Show/Hide Password Toggle ---
  (function () {
    const pw  = document.getElementById('password');
    const btn = document.getElementById('togglePw');

    btn.addEventListener('click', function () {
      const show = pw.type === 'password';
      pw.type = show ? 'text' : 'password'; // Switch input type
      btn.textContent = show ? 'Hide' : 'Show'; // Update button text
      btn.setAttribute('aria-pressed', show ? 'true' : 'false'); // Accessibility
    });
  })();
</script>
</body>
</html>
