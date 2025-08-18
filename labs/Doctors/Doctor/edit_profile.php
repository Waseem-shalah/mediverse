<?php
// Doctor/edit_profile.php
// Purpose: Simple edit profile page for doctors (username, password change, avatar upload).
// Notes: Keeps existing logic; adds brief human-friendly comments only.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config.php';
require '../navbar_loggedin.php';

// --- Gate: only doctors can access this page ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') { die("Unauthorized access."); }

$doctor_id = (int)$_SESSION['user_id'];

// --- Load current doctor's basic profile (username/email/avatar path) ---
$stmt = $conn->prepare("
    SELECT username, email, COALESCE(profile_image, '') AS profile_image
    FROM users
    WHERE id = ? AND role = 'doctor'
    LIMIT 1
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { die("Doctor not found."); }

// --- CSRF token to protect form submission ---
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

/**
 * flash(): show one-time success/error messages (set in process script)
 */
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
  <title>Edit Profile — Doctor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Minimal layout polish */
    body { margin:0; padding:0; }
    .page-wrap { max-width: 900px; }
    @media (min-width: 992px) { .align-right { margin-left: auto; } }
    .avatar{ width:96px; height:96px; border-radius:50%; object-fit:cover; border:1px solid #e2e8f0; background:#f8fafc }
    .form-label { font-weight: 600; }
    .hint{ color:#64748b; font-size:.9rem }
    .password-meter-label{ font-size:.9rem }
  </style>
</head>
<body>
<div class="container page-wrap align-right py-4">
  <h3 class="mb-3">Edit Profile</h3>
  <?php flash('success'); flash('error'); ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <!-- Submit to server-side handler that performs validation & update -->
      <form action="edit_profile_process.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <!-- Username (can be changed) -->
        <div class="mb-3">
          <label for="username" class="form-label text-end d-block">Username</label>
          <input type="text" id="username" name="username" class="form-control"
                 value="<?= htmlspecialchars($user['username']) ?>" maxlength="50" required>
        </div>

        <!-- Password section: optional change (current + new + confirm) -->
        <div class="row g-3">
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

            <!-- Visual strength meter (client-side hint only) -->
            <div class="mt-3">
              <div class="d-flex justify-content-between align-items-center">
                <span class="password-meter-label">Strength:</span>
                <span id="strengthText" class="password-meter-label fw-semibold">Too weak</span>
              </div>
              <div class="progress" style="height:8px">
                <div id="strengthBar" class="progress-bar" role="progressbar" style="width:0%"></div>
              </div>
              <div class="hint mt-1">Use upper & lower case, numbers, and a symbol. Must differ from your current password.</div>
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

        <!-- Profile image preview + upload (server validates size/type) -->
        <div class="row g-3 mt-3 align-items-center">
          <div class="col-auto">
            <img class="avatar"
                 src="<?= $user['profile_image'] ? '../' . htmlspecialchars($user['profile_image']) : 'https://via.placeholder.com/96?text=DR' ?>"
                 alt="Profile">
          </div>
          <div class="col">
            <label class="form-label text-end d-block">Profile Picture</label>
            <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp" class="form-control">
            <div class="hint mt-1">Accepted: JPG/PNG/WEBP. Max 2 MB.</div>
          </div>
        </div>

        <!-- Actions -->
        <div class="d-flex gap-2 mt-4 justify-content-end">
          <a class="btn btn-outline-secondary" href="dashboard.php">Cancel</a>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS (no custom deps) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// togglePwd(): show/hide any password field for convenience
function togglePwd(id, btn){
  const el = document.getElementById(id);
  if (!el) return;
  const isPwd = el.type === 'password';
  el.type = isPwd ? 'text' : 'password';
  btn.textContent = isPwd ? 'Hide' : 'Show';
}

// Lightweight password strength + match UI (client-side guidance only)
(function(){
  const pw   = document.getElementById('new_password');
  const cpw  = document.getElementById('confirm_password');
  const bar  = document.getElementById('strengthBar');
  const text = document.getElementById('strengthText');
  const matchNote = document.getElementById('matchNote');

  // Heuristic score (0–100). Server must still enforce rules.
  function scorePassword(p) {
    let s = 0;
    if (!p) return 0;
    if (p.length >= 8)  s += 20;
    if (p.length >= 12) s += 15;
    if (p.length >= 16) s += 15;
    if (/[a-z]/.test(p)) s += 15;
    if (/[A-Z]/.test(p)) s += 15;
    if (/\d/.test(p))    s += 10;
    if (/[^A-Za-z0-9]/.test(p)) s += 15;
    if (/(.)\1{2,}/.test(p)) s -= 10; // penalize very long repeats
    return Math.max(0, Math.min(100, s));
  }

  // Update progress bar + label
  function setBar(v){
    bar.style.width = v + '%';
    let label='Weak', cls='bg-danger';
    if (v>=80){ label='Strong'; cls='bg-success'; }
    else if (v>=60){ label='Good'; cls='bg-info'; }
    else if (v>=40){ label='Fair'; cls='bg-warning'; }
    bar.className = 'progress-bar ' + cls;
    text.textContent = label;
  }

  // Show match note for confirmation field
  function checkMatch(){
    if (!cpw.value) { matchNote.textContent=''; return; }
    matchNote.textContent = (pw.value === cpw.value) ? 'Passwords match ✅' : 'Passwords do not match ❌';
  }

  pw.addEventListener('input', ()=> setBar(scorePassword(pw.value)));
  cpw.addEventListener('input', checkMatch);
})();
</script>
</body>
</html>
