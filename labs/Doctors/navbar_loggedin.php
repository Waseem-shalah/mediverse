<?php
// navbar_loggedin.php — role-aware sticky navbar (patient/doctor/admin)

/* Session + access gate */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'], $_SESSION['name'], $_SESSION['role'])) {
    // Hard redirect to login if not authenticated
    header("Location: /labs/Doctors/login.php");
    exit();
}

/* Basic identity used in the header */
$name    = trim((string)$_SESSION['name']);
$role    = $_SESSION['role'];                         // 'patient' | 'doctor' | 'admin'
$initial = strtoupper(mb_substr($name ?: 'U', 0, 1, 'UTF-8')); // avatar initial

/* App base paths (absolute) so links work from any subfolder */
$APP_BASE   = '/labs/Doctors';
$APP_DOCTOR = $APP_BASE . '/Doctor';
$APP_ADMIN  = $APP_BASE . '/admin';

$logo_src    = $APP_BASE . '/assets/images/logo.png';
$logout_href = $APP_BASE . '/logout.php';

/* Brand click-through: send users to the right “home” */
$brandHref =
  $role === 'admin'   ? ($APP_ADMIN  . '/index.php') :
  ($role === 'doctor' ? ($APP_DOCTOR . '/dashboard.php')
                      : ($APP_BASE   . '/patient_dashboard.php'));
?>
<style>
  /* --- Design tokens + layout --- */
  :root{
    --bg: rgba(255,255,255,.65);
    --bg-dark: rgba(17,24,39,.65);
    --fg: #0f172a;
    --fg-subtle:#475569;
    --ring: rgba(59,130,246,.35);
  }
  .mv-navbar{position:sticky;top:0;z-index:1000;} /* stays visible on scroll */
  .mv-nav{
    backdrop-filter:saturate(160%) blur(12px);
    -webkit-backdrop-filter:saturate(160%) blur(12px);
    background:linear-gradient(90deg,#60a5fa20,#22d3ee20),var(--bg);
    border-bottom:1px solid rgba(2,6,23,.08);
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 16px;gap:12px;
    font-family:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif;
  }
  .mv-brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--fg)}
  .mv-logo{height:28px;width:auto;border-radius:6px;box-shadow:0 2px 8px rgba(2,6,23,.08)}
  .mv-title{font-weight:800;font-size:20px;letter-spacing:.2px}

  .mv-right{display:flex;align-items:center;gap:10px}
  .mv-links{display:flex;align-items:center;gap:8px;flex-wrap:wrap}

  /* “Chip” style links for primary navigation */
  .mv-chip{
    display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;
    background:linear-gradient(180deg,#e0f2fe,#e0f2fe00);color:#0c4a6e;border:1px solid #bae6fd;
    text-decoration:none;font-weight:600;font-size:14px;white-space:nowrap;
    transition:transform .12s ease,box-shadow .2s ease;
  }
  .mv-chip:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(2,6,23,.08)}
  .mv-chip.logout{background:linear-gradient(180deg,#fee2e2,#fee2e200);color:#7f1d1d;border-color:#fecaca}

  /* Profile capsule (name/role + initial) */
  .mv-profile{display:flex;align-items:center;gap:10px;padding:6px 10px;border-radius:999px;border:1px solid rgba(2,6,23,.08);background:rgba(255,255,255,.55)}
  .mv-avatar{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:14px;background:linear-gradient(135deg,#38bdf8,#6366f1);color:#fff;box-shadow:0 2px 10px rgba(2,6,23,.15)}
  .mv-name{font-weight:700;color:var(--fg);font-size:14px}
  .mv-role{color:var(--fg-subtle);font-size:12px}
  .ic{font-size:16px}

  /* Mobile: checkbox-driven menu toggle (no JS needed) */
  .mv-toggle{display:none}
  .mv-menu-btn{display:none;border:1px solid rgba(2,6,23,.1);background:#fff;border-radius:10px;padding:8px 10px;font-weight:700}
  @media (max-width:960px){
    .mv-menu-btn{display:inline-flex;gap:8px;align-items:center}
    .mv-right{flex-wrap:wrap}
    .mv-links{display:none;width:100%;padding:8px 0;border-top:1px dashed rgba(2,6,23,.12);margin-top:8px}
    .mv-toggle:checked ~ .mv-right .mv-links{display:flex}
  }

  /* Accessible focus rings */
  .mv-chip:focus-visible,.mv-menu-btn:focus-visible,.mv-brand:focus-visible{outline:3px solid var(--ring);outline-offset:2px}

  /* Dark mode polish */
  @media (prefers-color-scheme:dark){
    :root{--bg:var(--bg-dark);--fg:#e5e7eb;--fg-subtle:#94a3b8}
    .mv-nav{border-bottom-color:rgba(255,255,255,.08)}
    .mv-profile{background:rgba(17,24,39,.45);border-color:rgba(255,255,255,.08)}
    .mv-chip{background:linear-gradient(180deg,#0b3b50,#0b3b5000);color:#e0f2fe;border-color:#164e63}
    .mv-chip.logout{background:linear-gradient(180deg,#4c1d1d,#4c1d1d00);color:#fecaca;border-color:#7f1d1d}
  }
</style>

<div class="mv-navbar">
  <nav class="mv-nav">
    <!-- Brand goes to role-specific home -->
    <a class="mv-brand" href="<?= htmlspecialchars($brandHref) ?>">
      <img class="mv-logo" src="<?= htmlspecialchars($logo_src) ?>" alt="MediVerse logo">
      <span class="mv-title">MediVerse</span>
    </a>

    <!-- Mobile toggle control for the nav links -->
    <input id="mvNavToggle" class="mv-toggle" type="checkbox" aria-label="Toggle menu">
    <label class="mv-menu-btn" for="mvNavToggle" aria-controls="mvLinks">☰ Menu</label>

    <div class="mv-right">
      <!-- Primary navigation chips; vary by role -->
      <div id="mvLinks" class="mv-links" aria-label="Primary navigation">
        <?php if ($role === 'admin'): ?>
          <a class="mv-chip" href="<?= $APP_ADMIN ?>/users.php"><span class="ic">👥</span> Manage users</a>
          <a class="mv-chip" href="<?= $APP_ADMIN ?>/specializations.php"><span class="ic">🧬</span> Specializations</a>
          <a class="mv-chip" href="<?= $APP_ADMIN ?>/add_medicine.php"><span class="ic">➕</span> Add medicine</a>
          <a class="mv-chip" href="<?= $APP_ADMIN ?>/charts.php"><span class="ic">📈</span> Analytics</a>
          <a class="mv-chip" href="<?= $APP_ADMIN ?>/ratings.php"><span class="ic">⭐</span> Ratings</a>
          <a class="mv-chip" href="<?= $APP_ADMIN ?>/contact_messages.php"><span class="ic">📬</span> Messages</a>
          <a class="mv-chip" href="<?= $APP_ADMIN ?>/doctor_applications.php"><span class="ic">🧾</span> Applications</a>
          <a class="mv-chip logout" href="<?= $logout_href ?>"><span class="ic">🚪</span> Logout</a>

        <?php elseif ($role === 'doctor'): ?>
          <a class="mv-chip" href="<?= $APP_DOCTOR ?>/view_appointments.php"><span class="ic">📅</span> My Appointments</a>
          <a class="mv-chip" href="<?= $APP_DOCTOR ?>/add_slot.php"><span class="ic">➕</span> Add Slot</a>
          <a class="mv-chip" href="<?= $APP_DOCTOR ?>/view_slots.php"><span class="ic">🛠️</span> Manage Slots</a>
          <a class="mv-chip" href="<?= $APP_DOCTOR ?>/ratings.php"><span class="ic">⭐</span> Ratings</a>
          <a class="mv-chip" href="<?= $APP_DOCTOR ?>/doctor_chats.php"><span class="ic">💬</span> Chats</a>
          <a class="mv-chip" href="<?= $APP_DOCTOR ?>/reports.php"><span class="ic">📝</span> Reports</a>
          <a class="mv-chip" href="<?= $APP_BASE ?>/contact_us_loggedin.php"><span class="ic">📬</span> Contact us</a>
          <a class="mv-chip" href="<?= $APP_DOCTOR ?>/edit_profile.php"><span class="ic">⚙️</span> Edit Profile</a>
          <a class="mv-chip logout" href="<?= $logout_href ?>"><span class="ic">🚪</span> Logout</a>

        <?php else: /* patient */ ?>
          <a class="mv-chip" href="<?= $APP_BASE ?>/book_appointment.php"><span class="ic">🗓️</span> Book</a>
          <a class="mv-chip" href="<?= $APP_BASE ?>/my_appointments.php"><span class="ic">📅</span> My Appointments</a>
          <a class="mv-chip" href="<?= $APP_BASE ?>/medical_reports.php"><span class="ic">📝</span> Reports</a>
          <a class="mv-chip" href="<?= $APP_BASE ?>/prescriptions.php"><span class="ic">💊</span> Prescriptions</a>
          <a class="mv-chip" href="<?= $APP_BASE ?>/patient_chats.php"><span class="ic">💬</span> Chat</a>
          <a class="mv-chip" href="<?= $APP_BASE ?>/contact_us_loggedin.php"><span class="ic">📬</span> Contact us</a>
          <a class="mv-chip" href="<?= $APP_BASE ?>/patient_edit_profile.php"><span class="ic">⚙️</span> Edit Profile</a>
          <a class="mv-chip logout" href="<?= $logout_href ?>"><span class="ic">🚪</span> Logout</a>
        <?php endif; ?>
      </div>

      <!-- Compact profile (shows current name + role) -->
      <div class="mv-profile" aria-label="Account">
        <div class="mv-avatar" aria-hidden="true"><?= htmlspecialchars($initial) ?></div>
        <div>
          <div class="mv-name"><?= htmlspecialchars($name ?: 'User') ?></div>
          <div class="mv-role"><?= htmlspecialchars(ucfirst($role)) ?></div>
        </div>
      </div>
    </div>
  </nav>
</div>
