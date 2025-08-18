<?php
// Patient profile edit page (patient-only).
// Shows current stats and lets the user update username, height/weight (BMI recalculated server-side),
// password (with strength/match hints), and profile picture.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/navbar_loggedin.php';

// --- AuthZ: patients only ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    die("Unauthorized access.");
}
$patient_id = (int)$_SESSION['user_id'];

// --- Load current user fields used on the form ---
$stmt = $conn->prepare("
    SELECT username,
           COALESCE(height_cm, 0)    AS height_cm,
           COALESCE(weight_kg, 0)    AS weight_kg,
           COALESCE(bmi, 0)          AS bmi,
           COALESCE(profile_image,'') AS profile_image,
           COALESCE(email,'')        AS email
    FROM users
    WHERE id = ? AND role = 'patient'
    LIMIT 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { die("Patient not found."); }

// --- CSRF token for the submit to patient_edit_profile_process.php ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// --- Small helper to render one-shot flash messages ---
function flash($key) {
    if (!empty($_SESSION[$key])) {
        echo '<div class="alert '.($key==='success'?'alert-success':'alert-danger').' mt-3" role="alert">'
           . htmlspecialchars($_SESSION[$key], ENT_QUOTES, 'UTF-8') . '</div>';
        unset($_SESSION[$key]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Edit Profile — Patient</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { margin:0; padding:0; }
    .page-wrap { max-width: 900px; }
    @media (min-width: 992px) { .align-right { margin-left: auto; } }
    .avatar{ width:96px; height:96px; border-radius:50%; object-fit:cover; border:1px solid #e2e8f0; background:#f8fafc }
    .form-label { font-weight: 600; }
    .hint{ color:#64748b; font-size:.9rem }
  </style>
</head>
<body>
<div class="container page-wrap align-right py-4">
  <h3 class="mb-3">Edit Profile</h3>
  <?php flash('success'); flash('error'); ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <!-- Note: enctype required for file uploads -->
      <form action="patient_edit_profile_process.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Username -->
        <div class="mb-3">
          <label class="form-label text-end d-block" for="username">Username</label>
          <input type="text" class="form-control" id="username" name="username"
                 value="<?= htmlspecialchars($user['username']) ?>" maxlength="50" required>
        </div>

        <!-- Height / Weight (BMI shown as read-only; recomputed server-side on save) -->
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label text-end d-block" for="height_cm">Height (cm)</label>
            <input type="number" step="0.01" min="50" max="250" class="form-control" id="height_cm" name="height_cm"
                   value="<?= htmlspecialchars($user['height_cm']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label text-end d-block" for="weight_kg">Weight (kg)</label>
            <input type="number" step="0.01" min="10" max="400" class="form-control" id="weight_kg" name="weight_kg"
                   value="<?= htmlspecialchars($user['weight_kg']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label text-end d-block">Current BMI</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($user['bmi']) ?>" disabled>
            <div class="hint mt-1">BMI will be recalculated when you save.</div>
          </div>
        </div>

        <!-- Passwords (optional: user can leave blank if they don't want to change) -->
        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <label for="current_password" class="form-label text-end d-block">Current Password</label>
            <div class="input-group">
              <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter current password">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('current_password', this)">Show</button>
            </div>
            <div class="hint mt-1">Leave blank if you’re not changing your password.</div>
          </div>

          <div class="col-md-4">
            <label for="new_password" class="form-label text-end d-block">New Password</label>
            <div class="input-group">
              <input type="password" id="new_password" name="new_password" class="form-control" placeholder="At least 8 characters">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('new_password', this)">Show</button>
            </div>

            <!-- Simple strength meter (client-side hint only) -->
            <div class="mt-2">
              <div class="progress" style="height:8px;">
                <div id="pwdStrengthBar" class="progress-bar" role="progressbar" style="width:0%"></div>
              </div>
              <div id="pwdStrengthText" class="hint mt-1">Strength: —</div>
              <div class="hint">Must include upper & lower case, number, and symbol. Must differ from your current password.</div>
            </div>
          </div>

          <div class="col-md-4">
            <label for="confirm_password" class="form-label text-end d-block">Confirm New Password</label>
            <div class="input-group">
              <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Retype new password">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('confirm_password', this)">Show</button>
            </div>
            <div id="matchNote" class="hint mt-1"></div>
          </div>
        </div>

        <!-- Profile Image (preview on left, file input on right) -->
        <div class="row g-3 mt-3 align-items-center">
          <div class="col-auto">
            <img class="avatar"
                 src="<?= $user['profile_image'] ? htmlspecialchars($user['profile_image']) : 'https://via.placeholder.com/96?text=PT' ?>"
                 alt="Profile">
          </div>
          <div class="col">
            <label class="form-label text-end d-block">Profile Picture</label>
            <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp" class="form-control">
            <div class="hint mt-1">Accepted: JPG/PNG/WEBP. Max 2 MB.</div>
          </div>
        </div>

        <div class="d-flex gap-2 mt-4 justify-content-end">
          <a class="btn btn-outline-secondary" href="patient_dashboard.php">Cancel</a>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Minimal JS helpers -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password visibility for a single input
function togglePwd(id, btn){
  const el = document.getElementById(id);
  if (!el) return;
  const isPwd = el.type === 'password';
  el.type = isPwd ? 'text' : 'password';
  btn.textContent = isPwd ? 'Hide' : 'Show';
}
</script>

<!-- Client-side strength + match hints (UX only; server must enforce rules) -->
<script>
(function(){
  const newPwd     = document.getElementById('new_password');
  const confirmPwd = document.getElementById('confirm_password');
  const bar        = document.getElementById('pwdStrengthBar');
  const txt        = document.getElementById('pwdStrengthText');
  const matchNote  = document.getElementById('matchNote');

  // Simple heuristic: length + variety buckets -> % + label
  function strength(p){
    let score = 0;
    if (!p) return {score:0, percent:0, label:'', cls:'bg-danger'};

    if (p.length >= 8) score++;
    if (/[a-z]/.test(p)) score++;
    if (/[A-Z]/.test(p)) score++;
    if (/\d/.test(p)) score++;
    if (/[^A-Za-z0-9]/.test(p)) score++;

    const percent = (score / 5) * 100;
    const label   = (score <= 2) ? 'Weak' : (score === 3 ? 'Medium' : 'Strong');
    const cls     = (score <= 2) ? 'bg-danger' : (score === 3 ? 'bg-warning' : 'bg-success');

    return {score, percent, label, cls};
  }

  function update(){
    const s = strength(newPwd.value);
    bar.style.width = s.percent + '%';
    bar.className = 'progress-bar ' + s.cls;
    txt.textContent = s.label ? ('Strength: ' + s.label) : 'Strength: —';

    matchNote.textContent = (confirmPwd.value && newPwd.value === confirmPwd.value)
      ? 'Passwords match ✅' : (confirmPwd.value ? 'Passwords do not match ❌' : '');
  }

  if (newPwd) newPwd.addEventListener('input', update);
  if (confirmPwd) confirmPwd.addEventListener('input', update);
})();
</script>
</body>
</html>
