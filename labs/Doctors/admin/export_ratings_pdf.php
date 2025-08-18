<?php
// admin/export_ratings_print.php
session_start();
require_once '../config.php';
require '../navbar_loggedin.php'; // ✅ keeps the same header/nav styling site-wide

// ✅ Security: only logged-in admins can view/print this report
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

// ✅ tiny helper for safe HTML output
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// -------------------- Inputs & Defaults --------------------
// Filter context comes from query string; provide safe defaults
$doctor_id = isset($_GET['doctor_id']) && ctype_digit($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$filter    = $_GET['filter'] ?? 'all';           // all | high | low
$threshold = isset($_GET['threshold']) && ctype_digit($_GET['threshold']) ? (int)$_GET['threshold'] : 0;

// Sensible default thresholds if not provided
if ($filter === 'high' && $threshold === 0) $threshold = 4;
if ($filter === 'low'  && $threshold === 0) $threshold = 2;

// -------------------- Doctor Display Name --------------------
// If a specific doctor is targeted, show their username; else "All Doctors"
$docName = 'All Doctors';
if ($doctor_id > 0) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $docName = $row['username'];
    $stmt->close();
}

// -------------------- Summary KPIs (top cards) --------------------
// Build WHERE clause only when a specific doctor is requested
$statsWhere = $doctor_id > 0 ? " WHERE r.doctor_id = ? " : "";
$statsSql = "
  SELECT 
    COUNT(*)                       AS total_reviews,
    ROUND(AVG(r.rating),2)         AS avg_rating,
    ROUND(AVG(r.wait_time),2)      AS avg_wait_time,
    ROUND(AVG(r.service),2)        AS avg_service,
    ROUND(AVG(r.communication),2)  AS avg_communication,
    ROUND(AVG(r.facilities),2)     AS avg_facilities
  FROM ratings r
  $statsWhere
";
$stmt = $conn->prepare($statsSql);
if ($doctor_id > 0) $stmt->bind_param("i", $doctor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc() ?: [
  // Fallback zeros so UI renders cleanly even with no data
  'total_reviews'=>0,'avg_rating'=>0,'avg_wait_time'=>0,'avg_service'=>0,'avg_communication'=>0,'avg_facilities'=>0
];
$stmt->close();

// -------------------- Detailed Ratings List --------------------
// Base query joins ratings + doctor/patient usernames + appointment date
$sql = "
  SELECT 
    r.id, 
    u_doc.username AS doctor, 
    u_pat.username AS patient, 
    r.rating, 
    r.comment, 
    r.rated_at,
    r.wait_time, r.service, r.communication, r.facilities,
    a.appointment_datetime
  FROM ratings r
  JOIN users u_doc   ON r.doctor_id = u_doc.id
  JOIN users u_pat   ON r.patient_id = u_pat.id
  JOIN appointments a ON r.appointment_id = a.id
  WHERE 1=1
";
$types = ""; $params = [];

// Dynamically add filters (doctor and/or min/max rating)
if ($doctor_id > 0) { $sql .= " AND r.doctor_id = ? "; $types .= "i"; $params[] = $doctor_id; }
if ($filter === 'high') { $sql .= " AND r.rating >= ? "; $types .= "i"; $params[] = $threshold; }
elseif ($filter === 'low') { $sql .= " AND r.rating <= ? "; $types .= "i"; $params[] = $threshold; }

// Most recent appointments first
$sql .= " ORDER BY a.appointment_datetime DESC";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$ratingsRes = $stmt->get_result();

// -------------------- UI Labels for Filter Chips --------------------
$filterText = 'All ratings';
if ($filter === 'high') $filterText = "High ratings (≥ $threshold)";
if ($filter === 'low')  $filterText = "Low ratings (≤ $threshold)";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MediVerse — Ratings Report</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  /* ✅ Print-friendly, clean report styling (works on screen & paper) */
  :root{
    --bg:#f7f7fb; --card:#ffffff; --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb;
    --brand:#4f46e5; --brand-2:#14b8a6; --chip:#eef2ff; --chip-text:#3730a3;
    --ok:#16a34a; --warn:#ea580c; --bad:#dc2626;
  }
  *{box-sizing:border-box}
  body{margin:0; background:var(--bg); color:var(--ink); font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
  .wrap{max-width:1200px; margin:28px auto; padding:0 16px}
  .topbar{
    background:linear-gradient(100deg,var(--brand),var(--brand-2));
    color:#fff; border-radius:16px; padding:18px 20px; box-shadow:0 10px 30px rgba(79,70,229,.25);
    display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
  }
  .title{font-weight:800; letter-spacing:.3px}
  .meta{font-size:14px; opacity:.95}
  .chips{display:flex; gap:8px; flex-wrap:wrap}
  .chip{background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.35); color:#fff; border-radius:999px; padding:6px 10px; font-size:12px}
  .btns{display:flex; gap:8px}
  .btn{
    background:#fff; color:var(--brand); border:1px solid #fff; border-radius:10px; padding:8px 12px; font-weight:700;
    box-shadow:0 4px 14px rgba(0,0,0,.08); text-decoration:none; display:inline-flex; gap:8px; align-items:center;
  }

  .grid{display:grid; grid-template-columns:repeat(12,1fr); gap:16px; margin-top:20px}
  .card{
    background:var(--card); border:1px solid var(--line); border-radius:14px; padding:16px;
    box-shadow:0 6px 18px rgba(17,24,39,.06);
  }
  .span3{grid-column:span 3} .span4{grid-column:span 4} .span6{grid-column:span 6} .span12{grid-column:span 12}
  h2{margin:0 0 10px 0; font-size:18px}

  .kpi{display:flex; align-items:center; justify-content:space-between; padding:12px; border:1px solid var(--line); border-radius:12px}
  .kpi .label{color:var(--muted); font-size:12px}
  .kpi .value{font-size:22px; font-weight:800}

  table.table{width:100%; border-collapse:separate; border-spacing:0; margin-top:8px; border:1px solid var(--line); border-radius:12px; overflow:hidden}
  .table thead th{background:#f9fafb; font-weight:800; color:#111827; padding:10px 12px; border-bottom:1px solid var(--line); text-align:left}
  .table tbody td{padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top}
  .table tbody tr:last-child td{border-bottom:none}
  .badge{display:inline-block; background:var(--chip); color:var(--chip-text); border:1px solid #c7d2fe; border-radius:999px; padding:3px 9px; font-weight:700; font-size:12px}
  .rating-ok{background:#ecfdf5; color:#065f46; border-color:#a7f3d0}
  .rating-warn{background:#fff7ed; color:#9a3412; border-color:#fed7aa}
  .rating-bad{background:#fef2f2; color:#991b1b; border-color:#fecaca}
  .comment{white-space:pre-wrap; max-width:520px}

  .footer{margin:22px 0 0; text-align:center; color:var(--muted); font-size:13px}

  @media print{
    .btns{display:none}           /* Hide buttons when printing */
    body{background:#fff}         /* Paper white */
    .topbar,.card{box-shadow:none}
    a[href]:after{content:""}     /* Avoid long URLs after links on print */
    /* If you prefer landscape:
    @page{size: A4 landscape; margin:12mm}
    */
  }
  @media (max-width:900px){ .span6{grid-column:span 12} .span4{grid-column:span 6} .span3{grid-column:span 6} }
  @media (max-width:600px){ .span4,.span3{grid-column:span 12} }
</style>
</head>
<body>
  <div class="wrap">
    <!-- ---------- Report Header / Chips / Print Button ---------- -->
    <div class="topbar">
      <div>
        <div class="title">MediVerse — Ratings Report</div>
        <div class="meta">Generated: <?= h(date('Y-m-d H:i')) ?></div>
      </div>
      <div class="chips">
        <span class="chip">Doctor: <?= h($docName) ?></span>
        <span class="chip"><?= h($filterText) ?></span>
        <span class="chip">Total: <?= (int)$stats['total_reviews'] ?></span>
      </div>
      <div class="btns">
        <button class="btn" onclick="window.print()"><span>🖨</span> Print / Save PDF</button>
      </div>
    </div>

    <!-- ---------- KPI Cards (summary at a glance) ---------- -->
    <div class="grid">
      <div class="card span3">
        <div class="kpi"><div><div class="label">Average Rating</div><div class="value"><?= h($stats['avg_rating']) ?></div></div></div>
      </div>
      <div class="card span3">
        <div class="kpi"><div><div class="label">Avg Wait</div><div class="value"><?= h($stats['avg_wait_time']) ?></div></div></div>
      </div>
      <div class="card span3">
        <div class="kpi"><div><div class="label">Avg Service</div><div class="value"><?= h($stats['avg_service']) ?></div></div></div>
      </div>
      <div class="card span3">
        <div class="kpi"><div><div class="label">Avg Communication</div><div class="value"><?= h($stats['avg_communication']) ?></div></div></div>
      </div>
      <div class="card span3">
        <div class="kpi"><div><div class="label">Avg Facilities</div><div class="value"><?= h($stats['avg_facilities']) ?></div></div></div>
      </div>
      <div class="card span3">
        <div class="kpi"><div><div class="label">Total Reviews</div><div class="value"><?= (int)$stats['total_reviews'] ?></div></div></div>
      </div>
    </div>

    <!-- ---------- Detailed Ratings Table ---------- -->
    <div class="card span12" style="margin-top:16px">
      <h2>All Ratings</h2>
      <table class="table">
        <thead>
          <tr>
            <th style="width:70px">ID</th>
            <th style="width:140px">Patient</th>
            <th style="width:110px">Appt. Date</th>
            <th style="width:90px">Wait</th>
            <th style="width:100px">Service</th>
            <th style="width:140px">Communication</th>
            <th style="width:110px">Facilities</th>
            <th style="width:90px">Rating</th>
            <th>Comment</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($ratingsRes->num_rows): ?>
          <?php while ($r = $ratingsRes->fetch_assoc()): 
            // Pre-format some cells for compact, readable print
            $appt = $r['appointment_datetime'] ? date('Y-m-d', strtotime($r['appointment_datetime'])) : '';
            $rating = (float)$r['rating'];
            $ratingClass = $rating >= 4 ? 'rating-ok' : ($rating >= 3 ? 'rating-warn' : 'rating-bad');
          ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= h($r['patient']) ?></td>
              <td><?= h($appt) ?></td>
              <td><span class="badge"><?= h($r['wait_time']) ?></span></td>
              <td><span class="badge"><?= h($r['service']) ?></span></td>
              <td><span class="badge"><?= h($r['communication']) ?></span></td>
              <td><span class="badge"><?= h($r['facilities']) ?></span></td>
              <td><span class="badge <?= $ratingClass ?>"><?= h($r['rating']) ?></span></td>
              <td class="comment"><?= nl2br(h($r['comment'] ?: '—')) ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="9" style="text-align:center; color:var(--muted)">No ratings found for this selection.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ---------- Footer note ---------- -->
    <div class="footer">MediVerse • Confidential — For internal use</div>
  </div>
</body>
</html>
