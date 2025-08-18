<?php
session_start();
require_once 'config.php';

// If we arrive here via POST from register.php, validate Step 1.
// If GET (direct), require that Step 1 is already valid in session.

function bounce_step1(string $msg, array $post = []): void {
    if (!empty($post)) {
        $_SESSION['reg_data'] = $post; // repopulate Step 1 form
    }
    $_SESSION['register_error'] = $msg;
    header("Location: register.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = $_POST;

    // --- Sanitize & validate Step 1 fields ---
    $name         = trim($raw['name']      ?? '');
    $username     = trim($raw['username']  ?? '');
    $email        = trim($raw['email']     ?? '');
    $phone        = trim($raw['phone']     ?? '');
    $pwdPlain     =        $raw['password']  ?? '';
    $pwdConfirm   =        $raw['password2'] ?? ''; // <-- capture confirm
    $user_id_raw  =        $raw['user_id']   ?? '';

    if ($name === '' || $username === '' || $email === '' || $pwdPlain === '' || $user_id_raw === '') {
        bounce_step1('Please fill in all required fields.', $raw);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        bounce_step1('Invalid email format.', $raw);
    }
    if (!preg_match('/^\d{9,10}$/', $user_id_raw)) {
        bounce_step1('Invalid ID: must be 9 digits.', $raw);
    }
    $user_id = (int)preg_replace('/\D/', '', $user_id_raw);

    // Strong password enforcement (same rule as final step)
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/', $pwdPlain)) {
        bounce_step1('Password too weak: use at least 8 chars and include uppercase, lowercase, number, and symbol.', $raw);
    }

    // NEW: ensure confirm exists and matches
    if ($pwdConfirm === '' || $pwdPlain !== $pwdConfirm) {
        bounce_step1('Passwords do not match.', $raw);
    }

    // ---- Field-specific uniqueness checks ----
    $dupes = [];

    // Username
    if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1")) {
        $stmt->bind_param("s", $username);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) $dupes[] = 'Username already in use';
        $stmt->close();
    } else {
        bounce_step1('Database error (username check).', $raw);
    }

    // Email
    if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1")) {
        $stmt->bind_param("s", $email);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) $dupes[] = 'Email already in use';
        $stmt->close();
    } else {
        bounce_step1('Database error (email check).', $raw);
    }

    // ID (user_id) – app-level uniqueness
    if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1")) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) $dupes[] = 'ID number already in use';
        $stmt->close();
    } else {
        bounce_step1('Database error (ID check).', $raw);
    }

    // Phone 
    if ($phone !== '') {
        if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE phone = ? LIMIT 1")) {
            $stmt->bind_param("s", $phone);
            $stmt->execute(); $stmt->store_result();
            if ($stmt->num_rows > 0) $dupes[] = 'Phone number already in use';
            $stmt->close();
        } else {
            bounce_step1('Database error (phone check).', $raw);
        }
    }

    if (!empty($dupes)) {
        bounce_step1(implode('. ', $dupes) . '.', $raw);
    }

    // All good -> store sanitized Step 1 data and mark started
    $_SESSION['reg_data'] = [
        'name'      => $name,
        'username'  => $username,
        'email'     => $email,
        'phone'     => $phone,
        'user_id'   => $user_id_raw, // keep original string; final step re-parses
        'password'  => $pwdPlain,
        'password2' => $pwdConfirm,   // <-- store confirm for final compare
    ];
    $_SESSION['registration_started'] = true;

} else {
    // GET: ensure previous step is valid & present
    if (empty($_SESSION['registration_started']) || empty($_SESSION['reg_data'])) {
        header("Location: register.php");
        exit();
    }
}

$old2 = $_SESSION['reg_step2'] ?? []; // repopulate on step2 errors
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register Details | MediVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script>
    function updateBMI() {
      const w = parseFloat(document.getElementById("weight").value);
      const h = parseFloat(document.getElementById("height").value);
      if (w > 0 && h > 0) {
        document.getElementById("bmi").value = (w / Math.pow(h/100, 2)).toFixed(2);
      } else {
        document.getElementById("bmi").value = "";
      }
    }
    document.addEventListener('DOMContentLoaded', () => {
      const dob = document.getElementById('dob');

      // Max = today - 15 years
      const today = new Date();
      const cutoff = new Date(today.getFullYear() - 15, today.getMonth(), today.getDate());
      const yyyy = cutoff.getFullYear();
      const mm = String(cutoff.getMonth() + 1).padStart(2, '0');
      const dd = String(cutoff.getDate()).padStart(2, '0');

      dob.max = `${yyyy}-${mm}-${dd}`;
      dob.min = '1900-01-01';
    });
  </script>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-5">
  <h2 class="mb-4 text-center">Step 2: Health & Location Info</h2>

  <?php if (!empty($_SESSION['register_error2'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['register_error2']) ?></div>
    <?php unset($_SESSION['register_error2']); ?>
  <?php endif; ?>

  <form action="register_process.php" method="post" class="w-50 mx-auto" novalidate>
    <div class="mb-3">
      <label class="form-label">Gender</label>
      <select name="gender" class="form-select" required>
        <option value="">-- Select --</option>
        <option value="male"   <?= (($old2['gender'] ?? '')==='male')?'selected':'' ?>>Male</option>
        <option value="female" <?= (($old2['gender'] ?? '')==='female')?'selected':'' ?>>Female</option>
        <option value="other"  <?= (($old2['gender'] ?? '')==='other')?'selected':'' ?>>Other</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Height (cm)</label>
      <input type="number" name="height_cm" id="height" class="form-control"
             min="50" max="250" step="1" oninput="updateBMI()" required
             value="<?= htmlspecialchars($old2['height_cm'] ?? '') ?>">
      <div class="form-text">50–250 cm</div>
    </div>

    <div class="mb-3">
      <label class="form-label">Weight (kg)</label>
      <input type="number" name="weight_kg" id="weight" class="form-control"
             min="20" max="400" step="0.1" oninput="updateBMI()" required
             value="<?= htmlspecialchars($old2['weight_kg'] ?? '') ?>">
      <div class="form-text">20–400 kg</div>
    </div>

    <div class="mb-3">
      <label class="form-label">BMI</label>
      <input type="text" name="bmi" id="bmi" class="form-control" readonly
             value="<?= htmlspecialchars($old2['bmi'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Date of Birth</label>
      <input type="date" name="dob" id="dob" class="form-control" required
             value="<?= htmlspecialchars($old2['dob'] ?? '') ?>"
             max="<?= date('Y-m-d', strtotime('-15 years')) ?>"
             min="1900-01-01">
    </div>

    <div class="mb-3">
      <label class="form-label">Location</label>
      <input type="text" name="location" class="form-control" required
             value="<?= htmlspecialchars($old2['location'] ?? '') ?>">
    </div>

    <button type="submit" class="btn btn-success w-100">Finish Registration</button>
  </form>
</div>
</body>
</html>
