<?php
// doctor_apply.php
session_start();
require_once 'config.php';
require 'navbar.php';

/**
 * CONFIG: Whether the submitted 9-digit ID must already exist in users.user_id
 * - false: ID must NOT exist in users (default — applicant is not yet a user)
 * - true : ID must already exist in users (applicant is a pre-registered user)
 */
$REQUIRE_ID_TO_EXIST_IN_USERS = false;

// Load specialization list for the dropdown
$specs = $conn->query("SELECT id,name FROM specializations ORDER BY name");

// Page state
$errors = [];
$success = '';

// Bindable form values (keep defaults so we can re-fill the form after errors)
$name = $email = $license_number = $phone = $location = $gender = $dob = $message = '';
$spec_id = 0;
$user_id_9 = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Read & normalize inputs
    $name           = trim($_POST['name']           ?? '');
    $email          = trim($_POST['email']          ?? '');
    $user_id_9      = preg_replace('/\D/', '', $_POST['user_id'] ?? ''); // keep only digits
    $license_number = trim($_POST['license_number'] ?? '');              // composed as "<spec_id>-<digits>"
    $phone          = trim($_POST['phone']          ?? '');
    $location       = trim($_POST['location']       ?? '');
    $gender         = $_POST['gender']              ?? '';
    $dob            = $_POST['date_of_birth']       ?? '';
    $spec_id        = (int)($_POST['specialization_id'] ?? 0);
    $message        = trim($_POST['message']        ?? '');

    // Needed for bind_param() (expects integer type)
    $user_id_int = (int)$user_id_9;

    // 2) Basic validation (required + simple formats)
    if (!$name || !$email || !$user_id_9 || !$license_number || !$phone || !$location || !$gender || !$dob || !$spec_id || !$message) {
        $errors[] = 'All fields are required.';
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    }
    if ($gender && !in_array($gender, ['male','female','other'], true)) {
        $errors[] = 'Invalid gender.';
    }
    if (!preg_match('/^\d{9}$/', $user_id_9)) {
        $errors[] = 'ID must be exactly 9 digits.';
    }

    // New: Phone must be 10 digits and start with "05"
    if ($phone && !preg_match('/^05\d{8}$/', $phone)) {
        $errors[] = 'Phone must be 10 digits: 05 followed by 8 digits.';
    }

    // New: Doctor must be at least 25 years old (server-side)
    if ($dob) {
        $dt = DateTime::createFromFormat('Y-m-d', $dob);
        $validFmt = $dt && $dt->format('Y-m-d') === $dob;
        if (!$validFmt) {
            $errors[] = 'Invalid date of birth.';
        } else {
            $age = $dt->diff(new DateTime('today'))->y;
            if ($age < 25) {
                $errors[] = 'You must be at least 25 years old.';
            }
        }
    }

    // 2b) License format: "<spec_id>-<5 or 6 digits>"
    // If digits are present but prefix is wrong/missing, we auto-correct the prefix
    if ($spec_id) {
        $expectedPrefix = (string)$spec_id;
        if (!preg_match('/^' . preg_quote($expectedPrefix, '/') . '-\d{5,6}$/', $license_number)) {
            if (preg_match('/(\d{5,6})$/', $license_number, $m)) {
                $license_number = $expectedPrefix . '-' . $m[1]; // fix the prefix
            } else {
                $errors[] = "License number must match {$expectedPrefix}-xxxxx (5–6 digits).";
            }
        }
    }

    // 3) Uniqueness checks across users and doctor_applications
    if (empty($errors)) {
        // users.email must be unique
        if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1")) {
            $stmt->bind_param("s", $email);
            $stmt->execute(); $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = 'Email is already registered.';
            $stmt->close();
        } else { $errors[] = 'Database error (check email).'; }

        // users.phone must be unique
        if (empty($errors)) {
            if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE phone = ? LIMIT 1")) {
                $stmt->bind_param("s", $phone);
                $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows > 0) $errors[] = 'Phone number is already registered.';
                $stmt->close();
            } else { $errors[] = 'Database error (check phone).'; }
        }

        // users.user_id rule depends on $REQUIRE_ID_TO_EXIST_IN_USERS
        if (empty($errors)) {
            if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1")) {
                $stmt->bind_param("i", $user_id_int);
                $stmt->execute(); $stmt->store_result();
                $id_exists_in_users = ($stmt->num_rows > 0);
                $stmt->close();

                if ($REQUIRE_ID_TO_EXIST_IN_USERS && !$id_exists_in_users) {
                    $errors[] = 'This ID is not registered. Please register the user first.';
                } elseif (!$REQUIRE_ID_TO_EXIST_IN_USERS && $id_exists_in_users) {
                    $errors[] = 'This ID is already in use by a registered user.';
                }
            } else { $errors[] = 'Database error (check ID).'; }
        }

        // users.license_number should be unique as well (if your users table stores it)
        if (empty($errors)) {
            if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE license_number = ? LIMIT 1")) {
                $stmt->bind_param("s", $license_number);
                $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows > 0) $errors[] = 'License number is already registered to a user.';
                $stmt->close();
            } else { $errors[] = 'Database error (check license).'; }
        }

        // doctor_applications: prevent duplicate submissions by email/phone/id/license
        if (empty($errors)) {
            if ($stmt = $conn->prepare("SELECT 1 FROM doctor_applications WHERE email = ? LIMIT 1")) {
                $stmt->bind_param("s", $email);
                $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows > 0) $errors[] = 'An application with this email already exists.';
                $stmt->close();
            }
        }
        if (empty($errors)) {
            if ($stmt = $conn->prepare("SELECT 1 FROM doctor_applications WHERE phone = ? LIMIT 1")) {
                $stmt->bind_param("s", $phone);
                $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows > 0) $errors[] = 'An application with this phone number already exists.';
                $stmt->close();
            }
        }
        if (empty($errors)) {
            if ($stmt = $conn->prepare("SELECT 1 FROM doctor_applications WHERE user_id = ? LIMIT 1")) {
                $stmt->bind_param("i", $user_id_int);
                $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows > 0) $errors[] = 'An application with this ID already exists.';
                $stmt->close();
            }
        }
        if (empty($errors)) {
            if ($stmt = $conn->prepare("SELECT 1 FROM doctor_applications WHERE license_number = ? LIMIT 1")) {
                $stmt->bind_param("s", $license_number);
                $stmt->execute(); $stmt->store_result();
                if ($stmt->num_rows > 0) $errors[] = 'An application with this license number already exists.';
                $stmt->close();
            }
        }
    }

    // 3b) (Optional) Try to sync the 9-digit user_id into users table:
    //   - Prefer the logged-in user (if any), otherwise look up by email.
    //   - Keep uniqueness (don’t overwrite if another user already has that ID).
    if (empty($errors)) {
        $targetUserId = 0;

        if (!empty($_SESSION['user_id'])) {
            $targetUserId = (int)$_SESSION['user_id'];
        } else {
            $find = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            if ($find) {
                $find->bind_param("s", $email);
                $find->execute();
                $res = $find->get_result();
                if ($res && $res->num_rows) {
                    $targetUserId = (int)$res->fetch_assoc()['id'];
                }
                $find->close();
            }
        }

        // If we found a user row to update, ensure no duplicate user_id clash
        if ($targetUserId) {
            $dupe = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? AND id <> ? LIMIT 1");
            $dupe->bind_param("ii", $user_id_int, $targetUserId);
            $dupe->execute(); $dupe->store_result();
            if ($dupe->num_rows) {
                $errors[] = 'This ID is already in use by another account.';
            }
            $dupe->close();

            if (empty($errors)) {
                $upd = $conn->prepare("UPDATE users SET user_id = ? WHERE id = ?");
                $upd->bind_param("ii", $user_id_int, $targetUserId);
                if (!$upd->execute()) {
                    $errors[] = 'Failed to update your ID in the users table.';
                }
                $upd->close();
            }
        }
    }

    // 4) Handle CV upload (PDF/Word) and insert the application row
    if (empty($errors)) {
        if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please upload your CV.';
        } else {
            $ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','doc','docx'], true)) {
                $errors[] = 'CV must be PDF or Word.';
            } else {
                $uploadDir = __DIR__ . '/uploads/cvs/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                // Unique filename to avoid collisions
                $filename = time() . '_' . uniqid('', true) . '.' . $ext;
                $target   = $uploadDir . $filename;

                if (!move_uploaded_file($_FILES['cv']['tmp_name'], $target)) {
                    $errors[] = 'Failed to save CV.';
                } else {
                    // Relative path to store in DB and use later for download/view
                    $cv_db = 'uploads/cvs/' . $filename;

                    // Insert application
                    $stmt = $conn->prepare("
                        INSERT INTO doctor_applications
                          (user_id, name, email, phone, location, gender, date_of_birth,
                           license_number, specialization_id, message, cv_path)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)
                    ");
                    if (!$stmt) {
                        $errors[] = 'Database error (prepare).';
                    } else {
                        // Bind order comment for readability:
                        // i: user_id, s: name, s: email, s: phone, s: location, s: gender,
                        // s: dob, s: license_number, i: specialization_id, s: message, s: cv_path
                        $stmt->bind_param(
                            'isssssssiss',   // types
                            $user_id_int,    // user_id
                            $name,           // name
                            $email,          // email
                            $phone,          // phone
                            $location,       // location
                            $gender,         // gender
                            $dob,            // date_of_birth
                            $license_number, // license_number
                            $spec_id,        // specialization_id
                            $message,        // message
                            $cv_db           // cv_path
                        );

                        if ($stmt->execute()) {
                            $success = 'Application submitted. You’ll be emailed after review.';
                            // Clear form fields for a fresh form
                            $name = $email = $user_id_9 = $license_number = $phone = $location = $gender = $dob = $message = '';
                            $spec_id = 0;
                        } else {
                            error_log("doctor_apply INSERT failed: " . $stmt->error);
                            $errors[] = 'Database error — please try again later.';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Doctor Application | MediVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Page styling (scoped wrapper so it doesn't interfere with navbar containers) */
    body { font-family:'Segoe UI',sans-serif; background:#f6f9fc; margin:0; padding:0; }
    .apply-container {
      max-width:600px;
      margin:60px auto;
      background:#fff;
      padding:30px;
      border-radius:12px;
      box-shadow:0 0 15px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
  <div class="apply-container">
    <h2 class="mb-4">Apply as a Doctor</h2>

    <!-- Server-side feedback -->
    <?php if ($errors): ?>
      <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php elseif ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Application form (server-side validation mirrors client-side hints) -->
    <form method="POST" enctype="multipart/form-data" novalidate>
      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input name="email" type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
      </div>

      <!-- 9-digit national ID -->
      <div class="mb-3">
        <label class="form-label">ID Number (9 digits)</label>
        <input
          name="user_id"
          type="text"
          class="form-control"
          required
          inputmode="numeric"
          pattern="\d{9}"
          minlength="9"
          maxlength="9"
          placeholder="e.g., 012345678"
          value="<?= htmlspecialchars($user_id_9) ?>"
          oninput="this.value=this.value.replace(/\D/g,'').slice(0,9)">
        <div class="form-text">Must be exactly 9 digits.</div>
      </div>

      <!-- Phone: 05 + 8 digits -->
      <div class="mb-3">
        <label class="form-label">Phone Number</label>
        <input
          id="phone"
          name="phone"
          type="tel"
          class="form-control"
          inputmode="numeric"
          maxlength="10"
          pattern="^05\d{8}$"
          title="Phone must be 10 digits: 05 followed by 8 digits."
          placeholder="05XXXXXXXX"
          value="<?= htmlspecialchars($phone) ?>"
          required
        >
        <small id="phoneTip" class="text-muted d-block mt-1"></small>
      </div>

      <div class="mb-3">
        <label class="form-label">Location</label>
        <input name="location" class="form-control" value="<?= htmlspecialchars($location) ?>" required>
      </div>

      <!-- Gender (server validates allowed values) -->
      <div class="mb-3">
        <label class="form-label">Gender</label>
        <select name="gender" class="form-select" required>
          <option value="">-- select --</option>
          <option value="male"   <?= $gender==='male'   ? 'selected':'' ?>>Male</option>
          <option value="female" <?= $gender==='female' ? 'selected':'' ?>>Female</option>
          <option value="other"  <?= $gender==='other'  ? 'selected':'' ?>>Other</option>
        </select>
      </div>

      <!-- Date of birth: client-side min/max supports 25+ age check -->
      <div class="mb-3">
        <label class="form-label">Date of Birth</label>
        <input
          id="dob"
          name="date_of_birth"
          type="date"
          class="form-control"
          value="<?= htmlspecialchars($dob) ?>"
          required
        >
        <small id="dobTip" class="text-muted d-block mt-1">You must be at least 25 years old.</small>
      </div>

      <!-- License number = <specialization-id>-<5–6 digits> -->
      <div class="mb-3">
        <label class="form-label">License Number</label>
        <div class="input-group">
          <span class="input-group-text" id="licPrefix">?-</span>
          <input id="licDigits" type="text" class="form-control"
                 inputmode="numeric" pattern="^\d{5,6}$" minlength="5" maxlength="6"
                 placeholder="enter 5–6 digits"
                 value="<?= htmlspecialchars(preg_match('/^\d+-\d{5,6}$/', $license_number) ? explode('-', $license_number, 2)[1] : '') ?>"
                 required
                 oninput="this.value=this.value.replace(/\D/g,'').slice(0,6)">
        </div>
        <!-- Hidden field holds the composed full value posted to the server -->
        <input type="hidden" name="license_number" id="license_number"
               value="<?= htmlspecialchars($license_number) ?>">
        <div class="form-text" id="licHelp">Format: &lt;specialization-id&gt;-xxxxx (5–6 digits).</div>
      </div>

      <!-- Specialization dropdown (required to construct license prefix) -->
      <div class="mb-3">
        <label class="form-label">Specialization</label>
        <select name="specialization_id" id="specialization_id" class="form-select" required>
          <option value="">-- select --</option>
          <?php while ($s = $specs->fetch_assoc()): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $spec_id==$s['id']?'selected':''?>>
              <?= htmlspecialchars($s['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- Cover letter / motivation -->
      <div class="mb-3">
        <label class="form-label">Cover Letter / Message</label>
        <textarea name="message" rows="5" class="form-control" required><?= htmlspecialchars($message) ?></textarea>
      </div>

      <!-- CV upload (PDF/Word) -->
      <div class="mb-3">
        <label class="form-label">Upload CV</label>
        <input name="cv" type="file" accept=".pdf,.doc,.docx" class="form-control" required>
      </div>

      <button class="btn btn-primary">Submit Application</button>
    </form>
  </div>

  <script>
    // --- License composer: shows "<spec_id>-" prefix & keeps a hidden composed value ---
    (function () {
      const spec   = document.getElementById('specialization_id');
      const prefEl = document.getElementById('licPrefix');
      const digEl  = document.getElementById('licDigits');
      const hidEl  = document.getElementById('license_number');
      const helpEl = document.getElementById('licHelp');

      function updateLicense() {
        const prefix = spec.value || '?';
        prefEl.textContent = prefix + '-';

        const digits = (digEl.value || '').replace(/\D/g, '').slice(0, 6);
        digEl.value = digits;

        // Only set hidden field when a real specialization is selected
        hidEl.value = (spec.value ? (prefix + '-' + digits) : '');

        helpEl.textContent = 'Format: ' + (spec.value || '<specialization-id>') + '-xxxxx (5–6 digits).';
      }

      spec.addEventListener('change', updateLicense);
      digEl.addEventListener('input', updateLicense);
      updateLicense(); // initial paint
    })();

    // --- Client-side helpers for phone and DOB (mirror server validation) ---
    (function () {
      const phone   = document.getElementById('phone');
      const phoneTip= document.getElementById('phoneTip');
      const dob     = document.getElementById('dob');
      const dobTip  = document.getElementById('dobTip');
      const form    = document.querySelector('form[method="POST"]');

      // Clamp DOB: max = today - 25 years (also set a sane min)
      function pad(n){ return (n<10?'0':'') + n; }
      (function initDob() {
        const now = new Date();
        const max = new Date(now.getFullYear() - 25, now.getMonth(), now.getDate());
        const maxStr = max.getFullYear() + '-' + pad(max.getMonth()+1) + '-' + pad(max.getDate());
        dob.max = maxStr;
        dob.min = '1900-01-01';
        if (dob.value && dob.value > dob.max) dob.value = dob.max;
        dob.addEventListener('change', () => {
          if (dob.value && dob.value > dob.max) dob.value = dob.max;
        });
      })();

      // Normalize phone to "05" + 8 digits as user types
      function normalizePhone(v){
        v = (v || '').replace(/\D/g,'');
        if (!v.startsWith('05')) {
          v = '05' + v.replace(/^0+/, '').replace(/^5?/, '');
        }
        return v.slice(0, 10);
      }
      if (!phone.value) phone.value = '05';
      phone.addEventListener('focus', () => { if (!phone.value) phone.value = '05'; });
      phone.addEventListener('input', () => {
        const cur = phone.value;
        const norm = normalizePhone(cur);
        if (cur !== norm) phone.value = norm;
        const ok = /^05\d{8}$/.test(phone.value);
        phoneTip.className = 'd-block mt-1 ' + (ok ? 'text-muted' : 'text-danger');
        phoneTip.textContent = ok ? '' : 'Phone must be 10 digits: 05 followed by 8 digits.';
      });

      // Final enforcement on submit (prevents subtle browser quirks)
      form.addEventListener('submit', function(e){
        // Phone check
        phone.value = normalizePhone(phone.value);
        if (!/^05\d{8}$/.test(phone.value)) {
          e.preventDefault();
          phone.focus();
          phoneTip.className = 'text-danger d-block mt-1';
          phoneTip.textContent = 'Phone must be 10 digits: 05 followed by 8 digits.';
          return;
        }
        // DOB check
        if (!dob.value || (dob.max && dob.value > dob.max)) {
          e.preventDefault();
          dob.focus();
          dobTip.className = 'text-danger d-block mt-1';
          dobTip.textContent = 'You must be at least 25 years old.';
          if (dob.max) dob.value = dob.max;
          return;
        } else {
          dobTip.className = 'text-muted d-block mt-1';
          dobTip.textContent = 'You must be at least 25 years old.';
        }
      });
    })();
  </script>
</body>
</html>
