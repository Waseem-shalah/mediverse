<?php
// admin/export_rating_print.php
session_start();
require_once '../config.php';
require '../navbar_loggedin.php'; // ✅ keep same navigation/header

// ✅ Security: only admins allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

// -------------------- Input --------------------
$rating_id = isset($_GET['rating_id']) && ctype_digit($_GET['rating_id']) ? (int)$_GET['rating_id'] : 0;
if ($rating_id <= 0) die("Invalid rating ID.");

// -------------------- Fetch One Rating --------------------
// Join ratings, doctor, patient, and appointment info
$sql = "
  SELECT 
    r.id,
    r.rating,
    r.comment,
    r.rated_at,
    r.wait_time, r.service, r.communication, r.facilities,
    u_doc.username AS doctor,
    u_pat.username AS patient,
    a.appointment_datetime
  FROM ratings r
  JOIN users u_doc    ON r.doctor_id = u_doc.id
  JOIN users u_pat    ON r.patient_id = u_pat.id
  JOIN appointments a ON r.appointment_id = a.id
  WHERE r.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $rating_id);
$stmt->execute();
$res = $stmt->get_result();
$R = $res->fetch_assoc();
$stmt->close();

if (!$R) die("Rating not found.");

// ✅ Safe HTML helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>MediVerse — Rating #<?= (int)$R['id'] ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  /* ===========================
     Scoped styles under .rp 
     so they don’t leak to navbar
     =========================== */
  .rp{
    --bg:#f7f7fb; --card:#ffffff; --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb;
    --brand:#4f46e5; --brand-2:#14b8a6; --chip:#eef2ff; --chip-text:#3730a3;
    color:var(--ink);
    font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Noto Sans","Apple Color Emoji","Segoe UI Emoji";
    background:var(--bg);
  }
  .rp .rp-wrap{max-width:1000px;margin:28px auto;padding:0 16px;}
  .rp .rp-topbar{
    background:linear-gradient(100deg,var(--brand),var(--brand-2));
    color:#fff;border-radius:16px;padding:18px 20px;
    box-shadow:0 10px 30px rgba(79,70,229,.25);
    display:flex;align-items:center;justify-content:space-between;gap:12px;
  }
  .rp .rp-title{font-weight:700;letter-spacing:.3px}
  .rp .rp-meta{font-size:14px;opacity:.95}
  .rp .rp-chip{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);color:#fff;border-radius:999px;padding:6px 10px;font-size:12px;}
  .rp .rp-btns{display:flex;gap:8px}
  .rp .rp-btn{
    background:#fff;color:var(--brand);border:1px solid #fff;border-radius:10px;padding:8px 12px;font-weight:600;
    box-shadow:0 4px 14px rgba(0,0,0,.08);text-decoration:none;display:inline-flex;gap:8px;align-items:center;cursor:pointer;
  }
  .rp .rp-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px;margin-top:20px;}
  .rp .rp-card{
    background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px;
    box-shadow:0 6px 18px rgba(17,24,39,.06);
  }
  .rp .rp-span6{grid-column:span 6}
  .rp .rp-span12{grid-column:span 12}
  .rp h2{margin:0 0 10px 0;font-size:18px}
  .rp table{width:100%;border-collapse:collapse;overflow:hidden;border-radius:12px;border:1px solid var(--line);}
  .rp th,.rp td{padding:10px 12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
  .rp th{background:#f9fafb;font-weight:700;color:#111827}
  .rp tr:last-child td{border-bottom:none}
  .rp .rp-kv{width:100%}
  .rp .rp-kv th{width:30%;white-space:nowrap}
  .rp .rp-badge{display:inline-block;background:var(--chip);color:var(--chip-text);border:1px solid #c7d2fe;border-radius:999px;padding:4px 10px;font-weight:700}
  .rp .rp-comment{white-space:pre-wrap}
  .rp .rp-footer{margin:22px 0 0;text-align:center;color:var(--muted);font-size:13px}

  /* ✅ Print only this report section */
  @media print{
    .rp .rp-btns{display:none}
    body *{visibility:hidden}
    .rp, .rp *{visibility:visible}
    .rp{position:relative}
  }

  /* ✅ Responsive adjustments */
  @media (max-width:720px){
    .rp .rp-span6{grid-column:span 12}
  }
</style>
</head>
<body>
  <!-- ===========================
       SCOPED REPORT CONTAINER
       =========================== -->
  <div class="rp">
    <div class="rp-wrap">
      <!-- Header bar with title, generated timestamp, and print button -->
      <div class="rp-topbar">
        <div>
          <div class="rp-title">MediVerse — Single Rating Report</div>
          <div class="rp-meta">Generated: <?= date('Y-m-d H:i') ?></div>
        </div>
        <div class="rp-btns">
          <span class="rp-chip">Rating ID #<?= (int)$R['id'] ?></span>
          <button class="rp-btn" onclick="window.print()">
            <span>🖨</span> Print / Save PDF
          </button>
        </div>
      </div>

      <!-- Two-column grid: overview + scores -->
      <div class="rp-grid">
        <!-- Left: Overview info -->
        <div class="rp-card rp-span6">
          <h2>Overview</h2>
          <table class="rp-kv">
            <tr><th>Doctor</th><td><?= h($R['doctor']) ?></td></tr>
            <tr><th>Patient</th><td><?= h($R['patient']) ?></td></tr>
            <tr><th>Appointment</th><td><?= h(date('Y-m-d', strtotime($R['appointment_datetime']))) ?></td></tr>
            <tr><th>Rated At</th><td><?= h(date('Y-m-d H:i', strtotime($R['rated_at']))) ?></td></tr>
          </table>
        </div>

        <!-- Right: Score breakdown -->
        <div class="rp-card rp-span6">
          <h2>Scores</h2>
          <table>
            <tr>
              <th>Wait Time</th><th>Service</th><th>Communication</th><th>Facilities</th><th>Overall</th>
            </tr>
            <tr>
              <td><span class="rp-badge"><?= h($R['wait_time']) ?></span></td>
              <td><span class="rp-badge"><?= h($R['service']) ?></span></td>
              <td><span class="rp-badge"><?= h($R['communication']) ?></span></td>
              <td><span class="rp-badge"><?= h($R['facilities']) ?></span></td>
              <td><span class="rp-badge"><?= h($R['rating']) ?></span></td>
            </tr>
          </table>
        </div>

        <!-- Full-width card for comment -->
        <div class="rp-card rp-span12">
          <h2>Patient Comment</h2>
          <div class="rp-comment"><?= nl2br(h($R['comment'] ?: '—')) ?></div>
        </div>
      </div>

      <!-- Footer note -->
      <div class="rp-footer">MediVerse • Confidential — For internal use</div>
    </div>
  </div>
  <!-- END .rp -->
</body>
</html>
