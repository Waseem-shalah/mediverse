<?php
// complete_registration.php
// ----------------------------------------------------
// This page is used by doctors whose application was
// approved, to finish creating their login account.
// ----------------------------------------------------

session_start();
require_once 'config.php';

// Get the application ID from the URL (link from email/admin approval)
$app_id = (int)($_GET['app_id'] ?? 0);
if (!$app_id) {
    die("Invalid link.");
}

// ----------------------------------------------------
// 1) Fetch the approved application details
// ----------------------------------------------------
$stmt = $conn->prepare("
    SELECT name,
           email,
           license_number,
           specialization_id,
           phone,
           location,
           gender,
           date_of_birth,
           user_id
      FROM doctor_applications
     WHERE id = ? AND status = 'approved'
     LIMIT 1
");
if (!$stmt) {
    die("SQL prepare error (load application): " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If the application doesn’t exist or isn’t approved, stop here
if (!$app) {
    die("Application not approved or not found.");
}

// This will hold any error messages for display
$errors = [];

// ----------------------------------------------------
// 2) If the doctor submits the form (username + password)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']  ?? '');
    $pw1      = $_POST['password']      ?? '';
    $pw2      = $_POST['password2']     ?? '';

    // Basic validation: nothing empty, and passwords must match
    if ($username === '' || $pw1 === '' || $pw2 === '' || $pw1 !== $pw2) {
        $errors[] = 'All fields are required and passwords must match.';
    }

    // Ensure the national ID in the application looks valid (9 digits)
    if (!preg_match('/^\d{9}$/', (string)$app['user_id'])) {
        $errors[] = 'Invalid ID number in application.';
    }

    // Check password strength: at least 8 chars, upper, lower, digit, symbol
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/', $pw1)) {
        $errors[] = 'Password too weak: use at least 8 characters with uppercase, lowercase, number, and symbol.';
    }

    // ----------------------------------------------------
    // 3) Check uniqueness: username, email, ID, license number
    // ----------------------------------------------------
    if (empty($errors)) {
        // Check username
        if ($chk = $conn->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1")) {
            $chk->bind_param("s", $username);
            $chk->execute(); $chk->store_result();
            if ($chk->num_rows > 0) $errors[] = 'Username already in use.';
            $chk->close();
        } else {
            $errors[] = 'Database error (username check).';
        }

        // Check email
        if (empty($errors) && ($chk = $conn->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1"))) {
            $chk->bind_param("s", $app['email']);
            $chk->execute(); $chk->store_result();
            if ($chk->num_rows > 0) $errors[] = 'Email already in use.';
            $chk->close();
        }

        // Check national ID
        if (empty($errors) && ($chk = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1"))) {
            $chk->bind_param("s", $app['user_id']);
            $chk->execute(); $chk->store_result();
            if ($chk->num_rows > 0) $errors[] = 'ID number already in use.';
            $chk->close();
        }

        // Check license number
        if (empty($errors) && ($chk = $conn->prepare("SELECT 1 FROM users WHERE license_number = ? LIMIT 1"))) {
            $chk->bind_param("s", $app['license_number']);
            $chk->execute(); $chk->store_result();
            if ($chk->num_rows > 0) $errors[] = 'License number already in use.';
            $chk->close();
        }
    }

    // ----------------------------------------------------
    // 4) If everything is OK, insert into users table
    // ----------------------------------------------------
    if (empty($errors)) {
        $hash = password_hash($pw1, PASSWORD_DEFAULT);

        $insU = $conn->prepare("
            INSERT INTO users
              (username, name, email, phone, location, gender, date_of_birth,
               license_number, password, role, specialization_id, user_id, is_activated, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'doctor', ?, ?, 1, NOW())
        ");
        if (!$insU) {
            $errors[] = 'Database error (prepare insert).';
        } else {
            $insU->bind_param(
                "sssssssssis",
                $username,
                $app['name'],
                $app['email'],
                $app['phone'],
                $app['location'],
                $app['gender'],
                $app['date_of_birth'],
                $app['license_number'],
                $hash,
                $app['specialization_id'],
                $app['user_id']
            );

            if ($insU->execute()) {
                $insU->close();

                // Mark application as completed (so it can’t be reused)
                $upd = $conn->prepare("UPDATE doctor_applications SET status='completed' WHERE id = ?");
                if ($upd) {
                    $upd->bind_param("i", $app_id);
                    $upd->execute();
                    $upd->close();
                }

                // Redirect to login after success
                header("Location: login.php?registered=1");
                exit;
            } else {
                // Handle duplicate-key errors gracefully
                if ($insU->errno == 1062) {
                    $e = strtolower($insU->error);
                    if (strpos($e, 'username') !== false) {
                        $errors[] = 'Username already in use.';
                    } elseif (strpos($e, 'email') !== false) {
                        $errors[] = 'Email already in use.';
                    } elseif (strpos($e, 'user_id') !== false) {
                        $errors[] = 'ID number already in use.';
                    } elseif (strpos($e, 'license_number') !== false) {
                        $errors[] = 'License number already in use.';
                    } else {
                        $errors[] = 'Duplicate value. Please use different details.';
                    }
                } else {
                    $errors[] = 'Registration error. Please try again.';
                }
                $insU->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Complete Registration | MediVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family:'Segoe UI',sans-serif; background:#f6f9fc; }
    .container { max-width:500px; margin:60px auto; background:#fff; padding:30px; border-radius:8px;
                 box-shadow:0 0 15px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
  <div class="container">
    <h3>Complete Your Signup</h3>

    <!-- Display validation errors if any -->
    <?php if ($errors): ?>
      <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <!-- Signup form -->
    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input name="username" class="form-control" required>
      </div>

      <!-- Password field with show/hide toggle -->
      <div class="mb-2">
        <label class="form-label" for="password">Password</label>
        <div class="input-group">
          <input name="password" id="password" type="password" class="form-control" required autocomplete="new-password">
          <button type="button" class="btn btn-outline-secondary" id="togglePw"
                  aria-label="Show password" aria-pressed="false">Show</button>
        </div>
      </div>

      <!-- Confirm password field with show/hide toggle -->
      <div class="mb-2">
        <label class="form-label" for="password2">Confirm Password</label>
        <div class="input-group">
          <input name="password2" id="password2" type="password" class="form-control" required autocomplete="new-password">
          <button type="button" class="btn btn-outline-secondary" id="togglePw2"
                  aria-label="Show confirm password" aria-pressed="false">Show</button>
        </div>
        <small id="matchTip" class="text-muted d-block mt-1"></small>
      </div>

      <!-- Password strength progress bar -->
      <div class="mb-3">
        <div class="progress" role="progressbar" aria-label="Password strength">
          <div id="pwBar" class="progress-bar" style="width:0%"></div>
        </div>
        <small id="pwTip" class="text-muted d-block mt-1">
          Use at least 8 characters with uppercase, lowercase, number, and symbol.
        </small>
      </div>

      <button class="btn btn-success w-100">Register</button>
    </form>
  </div>

  <!-- Client-side scripts: show/hide password, strength meter, live validation -->
  <script>
    (function () {
      function hookToggle(inputId, btnId) {
        const inp = document.getElementById(inputId);
        const btn = document.getElementById(btnId);
        if (!inp || !btn) return;
        btn.addEventListener('click', function () {
          const show = inp.type === 'password';
          inp.type = show ? 'text' : 'password';
          btn.textContent = show ? 'Hide' : 'Show';
          btn.setAttribute('aria-pressed', show ? 'true' : 'false');
          inp.focus({ preventScroll: true });
        });
      }
      hookToggle('password',  'togglePw');
      hookToggle('password2', 'togglePw2');
    })();

    (function () {
      const pw   = document.getElementById('password');
      const pw2  = document.getElementById('password2');
      const bar  = document.getElementById('pwBar');
      const tip  = document.getElementById('pwTip');
      const mtip = document.getElementById('matchTip');
      const form = document.querySelector('form');

      // Score password strength (basic scoring function)
      function score(p) {
        if (!p) return 0;
        let s = 0;
        if (p.length >= 8)  s++;
        if (p.length >= 12) s++;
        if (/[a-z]/.test(p)) s++;
        if (/[A-Z]/.test(p)) s++;
        if (/\d/.test(p))    s++;
        if (/[^A-Za-z0-9]/.test(p)) s++;
        if (!/(.)\1{2,}/.test(p)) s++;
        return Math.min(s, 7);
      }

      // Update strength bar as user types
      function updateBar() {
        const pct = Math.round((score(pw.value) / 7) * 100);
        bar.style.width = pct + '%';
        bar.className = 'progress-bar';
        if (pct < 45) {
          bar.classList.add('bg-danger'); bar.textContent = 'Weak';
        } else if (pct < 75) {
          bar.classList.add('bg-warning'); bar.textContent = 'Medium';
        } else {
          bar.classList.add('bg-success'); bar.textContent = 'Strong';
        }
        updateMatch();
      }

      // Check if confirm password matches
      function updateMatch() {
        const match = pw.value.length > 0 && pw.value === pw2.value;
        if (pw2.value.length === 0) {
          mtip.className = 'text-muted d-block mt-1'; mtip.textContent = '';
        } else if (match) {
          mtip.className = 'text-success d-block mt-1'; mtip.textContent = 'Passwords match.';
        } else {
          mtip.className = 'text-danger d-block mt-1'; mtip.textContent = 'Passwords do not match.';
        }
      }

      pw.addEventListener('input', updateBar);
      pw2.addEventListener('input', updateMatch);
      updateBar();

      // Block submission if password is weak or doesn’t match
      form.addEventListener('submit', function (e) {
        const strong = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/.test(pw.value);
        const matches = pw.value === pw2.value;
        if (!strong) {
          e.preventDefault();
          tip.classList.remove('text-muted');
          tip.classList.add('text-danger');
          tip.textContent = 'Password too weak: use at least 8 chars, include uppercase, lowercase, number, and symbol.';
          pw.focus();
          return;
        }
        if (!matches) {
          e.preventDefault();
          mtip.className = 'text-danger d-block mt-1';
          mtip.textContent = 'Passwords do not match.';
          pw2.focus();
        }
      });
    })();
  </script>
</body>
</html>
