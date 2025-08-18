<?php
// Patient dashboard (patient-only). Fetches name/avatar/gender, chooses a default avatar,
// and renders quick links. Comments kept brief and practical.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// --- Load minimal profile info for header/avatar ---
$stmt = $conn->prepare("SELECT name, COALESCE(profile_image, '') AS profile_image, gender FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($name, $profile_image, $gender);
$stmt->fetch();
$stmt->close();

// --- Fallback avatar (gender-aware for female) ---
if ($profile_image === '' || $profile_image === null) {
    $profile_image = ($gender === 'female')
        ? 'uploads/avatars/female-patient-icon-design-free-vector.jpg'
        : 'uploads/avatars/u5_1754817418.png';
}

/**
 * Build a browser URL for a stored path:
 * - Leaves absolute URLs alone
 * - Normalizes relative paths and prefixes the app base path if needed
 * - Appends a cache-busting query string based on filemtime (or time()) */
function buildImgUrl(?string $raw): string {
    $raw = (string)$raw;
    if ($raw === '') return '';

    // Absolute URL? return as-is
    if (preg_match('#^https?://#i', $raw)) return $raw;

    // Strip leading ../ to avoid escaping the web root
    $clean = preg_replace('#^(?:\.\./)+#', '', $raw);

    // Base path of the current script (works in subfolders, e.g., /labs/Doctors)
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    if ($basePath === '/') $basePath = '';

    // Ensure URL starts with the base path exactly once
    $urlPath = '/' . ltrim($clean, '/');
    if ($basePath && strpos($urlPath, $basePath . '/') !== 0) {
        $urlPath = $basePath . $urlPath;
    }

    // Cache buster: use file mtime when possible
    $fs  = realpath(__DIR__ . '/' . ltrim($clean, '/'));
    $qs  = ($fs && is_file($fs)) ? ('?v=' . filemtime($fs)) : ('?t=' . time());

    return $urlPath . $qs;
}

$imgUrl = buildImgUrl($profile_image);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Patient Dashboard | MediVerse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --bg:#ffffff; --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; }
    body{ background:#ffffff; color:var(--ink); }
    .page-wrap{ max-width:1100px; }
    .avatar{
      width:120px; height:120px; border-radius:50%; object-fit:cover;
      border:2px solid var(--line); box-shadow:0 6px 18px rgba(2,6,23,.06);
    }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      background:#eef2ff; color:#3730a3; border-radius:999px;
      padding:.35rem .7rem; font-weight:700; font-size:.9rem;
    }
    .tiles{ display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:16px; margin-top:8px; }
    .tile{
      border:1px solid var(--line); border-radius:14px; background:#fff;
      padding:22px; text-align:center; color:inherit; text-decoration:none;
      transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    }
    .tile:hover{ box-shadow:0 12px 28px rgba(2,6,23,.08); transform: translateY(-2px); border-color:#dbe3ef; }
    .tile h5{ margin-bottom:6px; }
    .tile p{ margin:0; color:var(--muted); }
  </style>
</head>
<body>
<?php include 'navbar_loggedin.php'; ?>

<div class="container page-wrap my-4 text-center">
  <!-- Avatar + greeting -->
  <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Profile Picture" class="avatar mb-3">
  <h2 class="mb-1">Welcome, <?= htmlspecialchars($name) ?> 👋</h2>
  <div><span class="chip">MediVerse • Patient Dashboard</span></div>

  <!-- Quick actions -->
  <div class="tiles">
    <a href="book_appointment.php" class="tile">
      <h5>📅 Book Appointment</h5>
      <p>Schedule a new visit with a doctor</p>
    </a>

    <a href="my_appointments.php" class="tile">
      <h5>📖 My Appointments</h5>
      <p>View your upcoming & past appointments</p>
    </a>

    <a href="medical_reports.php" class="tile">
      <h5>📋 Medical Reports</h5>
      <p>View diagnosis and doctor notes</p>
    </a>

    <a href="prescriptions.php" class="tile">
      <h5>💊 Prescriptions</h5>
      <p>Your prescribed medications</p>
    </a>

    <a href="patient_chats.php" class="tile">
      <h5>💬 Chat with Doctors</h5>
      <p>Ask follow-ups or clarify treatments</p>
    </a>

    <a href="contact_us_loggedin.php" class="tile">
      <h5>📬 Contact us</h5>
      <p>Contact our support team for any problems</p>
    </a>

    <a href="patient_edit_profile.php" class="tile">
      <h5>✏️ Edit profile</h5>
      <p>Edit your personal information</p>
    </a>
  </div>
</div>
</body>
</html>
