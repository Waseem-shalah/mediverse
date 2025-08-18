<?php
// contact.php
require_once "config.php"; // Load database connection

// Variables to hold success/error messages
$success = '';
$error   = '';

// Variables to repopulate form fields if validation fails
$name    = '';
$email   = '';
$subject = '';
$message = '';

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input values
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Basic validation checks
    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $error = 'Please fill in all fields.'; // User left something empty
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.'; // Invalid email format
    } else {
        // Prepare SQL query to insert contact form data
        $ins = $conn->prepare("
            INSERT INTO contact_messages (name, email, subject, message)
            VALUES (?, ?, ?, ?)
        ");

        if ($ins) {
            // Bind user input to query safely
            $ins->bind_param("ssss", $name, $email, $subject, $message);

            // Try to execute insert
            if ($ins->execute()) {
                // Success: show thank-you message
                $success = 'Thank you! Your message has been received by our support team and will be reviewed shortly.';
                
                // Reset fields so form clears
                $name = $email = $subject = $message = '';
            } else {
                // Something went wrong when saving
                $error = 'Could not save your message. Please try again later.';
            }
            $ins->close();
        } else {
            // Database prepare failed
            $error = 'Server error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Us | MediVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Segoe UI', system-ui, -apple-system, Roboto, sans-serif; background:#f6f9fc; }
    .contact-wrap { max-width: 760px; margin: 32px auto 60px; }
    .card { border: 1px solid rgba(2,6,23,.06); box-shadow: 0 12px 30px rgba(2,6,23,.06); }
  </style>
</head>
<body>

  <?php require 'navbar.php'; ?> <!-- Include site navbar -->

  <div class="container contact-wrap">
    <h2 class="mb-3 fw-bold">Contact Us</h2>

    <!-- Show error message if validation fails -->
    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Show success message after sending -->
    <?php if ($success): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Contact form -->
    <div class="card">
      <div class="card-body">
        <form method="POST" novalidate>
          <!-- Name field -->
          <div class="mb-3">
            <label for="name" class="form-label">Your Name</label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?= htmlspecialchars($name) ?>" required>
          </div>

          <!-- Email field -->
          <div class="mb-3">
            <label for="email" class="form-label">Your Email</label>
            <input type="email" id="email" name="email" class="form-control" 
                   value="<?= htmlspecialchars($email) ?>" required>
          </div>

          <!-- Subject field -->
          <div class="mb-3">
            <label for="subject" class="form-label">Subject</label>
            <input type="text" id="subject" name="subject" class="form-control" 
                   value="<?= htmlspecialchars($subject) ?>" required>
          </div>

          <!-- Message field -->
          <div class="mb-3">
            <label for="message" class="form-label">Message</label>
            <textarea id="message" name="message" class="form-control" rows="5" required><?= htmlspecialchars($message) ?></textarea>
          </div>

          <!-- Submit button -->
          <div class="text-center">
            <button type="submit" class="btn btn-primary px-4">Send Message</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
