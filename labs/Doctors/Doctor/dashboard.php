<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';

// Get doctor name, profile image, and gender
$stmt = $conn->prepare("SELECT name, COALESCE(profile_image, '') AS profile_image, gender FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($name, $profile_image, $gender);
$stmt->fetch();
$stmt->close();

/**
 * Build a URL for the profile image that works from /Doctor/,
 * and add a cache-busting query string so new uploads show immediately.
 */
function buildProfileImgUrl(?string $raw): string {
    $raw = (string)$raw;
    if ($raw === '') return '';

    if (preg_match('#^https?://#i', $raw)) {
        return $raw;
    }

    if (isset($raw[0]) && $raw[0] === '/') {
        $url = $raw;
        $fs  = !empty($_SERVER['DOCUMENT_ROOT'])
                ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $url
                : null;
        $qs  = ($fs && is_file($fs)) ? ('?v=' . filemtime($fs)) : ('?t=' . time());
        return $url . $qs;
    }

    $urlFromDoctor = '../' . ltrim($raw, '/');
    $fs = realpath(__DIR__ . '/../' . ltrim($raw, '/'));
    $qs = ($fs && is_file($fs)) ? ('?v=' . filemtime($fs)) : ('?t=' . time());
    return $urlFromDoctor . $qs;
}

// Compute the URL or fall back to gender-based default
$imgUrl = buildProfileImgUrl($profile_image);
if ($imgUrl === '') {
    $imgUrl = ($gender === 'female')
        ? '../uploads/avatars/3107162.jpg'
        : '../uploads/avatars/u3_1754816161.jpg';
    $defaultFs = realpath(__DIR__ . '/../' . ltrim(str_replace('../', '', $imgUrl), '/'));
    $imgUrl   .= ($defaultFs && is_file($defaultFs)) ? ('?v=' . filemtime($defaultFs)) : '';
}

// Fetch this doctor’s ratings (unchanged; kept as in your file)
$rs = $conn->prepare("
  SELECT 
    r.id,
    u.username           AS patient,
    r.rating,
    a.appointment_datetime
  FROM ratings r
  JOIN users u          ON r.patient_id     = u.id
  JOIN appointments a   ON r.appointment_id = a.id
  WHERE r.doctor_id = ?
  ORDER BY a.appointment_datetime DESC
");
$rs->bind_param("i", $_SESSION['user_id']);
$rs->execute();
$ratingsList = $rs->get_result();
$rs->close();

$emoji = ($gender === 'female') ? '👩‍⚕️' : '👨‍⚕️';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Doctor Dashboard | MediVerse</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      :root{ --bg:#ffffff; --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; }
      body{ background:#ffffff; color:var(--ink); }
      .page-wrap{ max-width:1100px; }
      .avatar{
        width:120px; height:120px; border-radius:50%; object-fit:cover;
        border:2px solid #e5e7eb; box-shadow:0 6px 18px rgba(2,6,23,.06);
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
  <?php include '../navbar_loggedin.php'; ?>

  <div class="container page-wrap my-4 text-center">
    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Profile Picture" class="avatar mb-3">
    <h2 class="mb-1">Welcome, Dr. <?= htmlspecialchars($name) ?> <?= $emoji ?></h2>
    <div><span class="chip">MediVerse • Doctor Dashboard</span></div>

    <div class="tiles">
      <a href="view_appointments.php" class="tile">
        <h5>📖 View Appointments</h5>
        <p>See who booked a slot with you</p>
      </a>

      <a href="add_slot.php" class="tile">
        <h5>➕ Add Slots</h5>
        <p>Add your availability</p>
      </a>

      <a href="view_slots.php" class="tile">
        <h5>🕓 Manage Slots</h5>
        <p>Add or delete available appointment slots</p>
      </a>

      <a href="ratings.php" class="tile">
        <h5>⭐ My Ratings</h5>
        <p>See feedback from your patients</p>
      </a>

      <a href="doctor_chats.php" class="tile">
        <h5>💬 Chat with Patients</h5>
        <p>Answer follow-ups or clarify treatments</p>
      </a>

      <a href="reports.php" class="tile">
        <h5>📝 Reports</h5>
        <p>Look at the medical reports you wrote</p>
      </a>

      <a href="../contact_us_loggedin.php" class="tile">
        <h5>📬 Contact us</h5>
        <p>Contact our support team for any problems</p>
      </a>

      <a href="edit_profile.php" class="tile">
        <h5>✏️ Edit profile</h5>
        <p>Edit your personal information</p>
      </a>
    </div>
  </div>
</body>
</html>
