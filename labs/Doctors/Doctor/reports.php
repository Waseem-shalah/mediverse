<?php
// doctor/reports.php — list all reports authored by the logged-in doctor
session_start();
require_once '../config.php';

// --- Access control: only logged-in doctors can view this page ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php"); exit();
}

$doctor_id = (int)$_SESSION['user_id'];

// --- Helper function to validate "YYYY-MM-DD" date format ---
function valid_date($s){ 
    return ($s && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s)) ? $s : ''; 
}

// --- Capture filter inputs from query string ---
$q          = trim($_GET['q'] ?? '');                               // Free-text search
$patient_id = ctype_digit($_GET['patient_id'] ?? '') ? (int)$_GET['patient_id'] : 0;  // Patient filter
$from       = valid_date($_GET['from'] ?? '');                      // From date
$to         = valid_date($_GET['to'] ?? '');                        // To date

// --- Fetch patient list for dropdown (only patients with reports by this doctor) ---
$pt = $conn->prepare("
  SELECT DISTINCT u.id, u.user_id, u.name
  FROM medical_reports r
  JOIN users u ON u.id = r.patient_id
  WHERE r.doctor_id = ?
  ORDER BY u.name
");
$pt->bind_param("i", $doctor_id);
$pt->execute();
$patients = $pt->get_result();
$pt->close();

// --- Build main query to fetch reports ---
$sql = "
  SELECT r.id, r.appointment_id, r.patient_id, r.diagnosis, r.description, r.created_at,
         u.user_id AS patient_public_id, u.name AS patient_name,
         a.appointment_datetime AS appt_dt,
         COUNT(pm.id) AS meds_cnt
  FROM medical_reports r
  JOIN users u ON u.id = r.patient_id
  LEFT JOIN appointments a ON a.id = r.appointment_id
  LEFT JOIN prescribed_medicines pm ON pm.report_id = r.id
  WHERE r.doctor_id = ?
";
$types = "i";
$params = [$doctor_id];

// --- Apply filters dynamically ---
if ($patient_id > 0) { 
    $sql .= " AND r.patient_id = ?"; 
    $types .= "i"; 
    $params[] = $patient_id; 
}
if ($q !== '') { 
    $sql .= " AND (u.name LIKE CONCAT('%',?,'%') OR r.diagnosis LIKE CONCAT('%',?,'%'))";
    $types .= "ss"; 
    $params[] = $q; 
    $params[] = $q; 
}
if ($from) { 
    $sql .= " AND DATE(r.created_at) >= ?"; 
    $types .= "s"; 
    $params[] = $from; 
}
if ($to) { 
    $sql .= " AND DATE(r.created_at) <= ?"; 
    $types .= "s"; 
    $params[] = $to; 
}

// --- Group and order results ---
$sql .= " GROUP BY r.id ORDER BY r.created_at DESC";

// --- Execute prepared query ---
$st = $conn->prepare($sql);
$st->bind_param($types, ...$params);
$st->execute();
$reports = $st->get_result();
$st->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Reports | MediVerse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Page styling */
    body{ background:#f6f9fc; }
    .page-wrap{ max-width: 1200px; }
    .chip{ display:inline-flex; align-items:center; gap:.45rem; background:#eef2ff; color:#3730a3;
           border-radius:999px; padding:.35rem .7rem; font-weight:700; font-size:.9rem; }
    .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); border:1px solid #e9ecef; }
    .pill{display:inline-block;padding:.25rem .5rem;border:1px solid #e5e7eb;background:#f8fafc;
          border-radius:999px;font-size:.8rem;color:#334155}
    .nowrap{white-space:nowrap}
    .muted{color:#6b7280}
    .filters .form-label{ font-weight:600; color:#334155; }
    .table thead th{ background:#f8fafc; }
  </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap my-4">

  <!-- Header with report count -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
      <h3 class="mb-0">📝 My Medical Reports</h3>
      <span class="chip"><i class="bi bi-file-medical me-1"></i><?= (int)$reports->num_rows ?> total</span>
    </div>
  </div>

  <!-- Filters form -->
  <form class="card card-shadow mb-3" method="get" autocomplete="off">
    <div class="card-body">
      <div class="row g-3 filters">
        <!-- Patient dropdown -->
        <div class="col-md-4">
          <label class="form-label">Patient</label>
          <select name="patient_id" class="form-select">
            <option value="0">All patients</option>
            <?php
            // Loop through patient list
            $patients->data_seek(0);
            while($p=$patients->fetch_assoc()): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $patient_id===(int)$p['id']?'selected':'' ?>>
                <?= htmlspecialchars($p['name']) ?> (ID <?= htmlspecialchars($p['user_id']) ?>)
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <!-- From / To date filters -->
        <div class="col-md-2">
          <label class="form-label">From</label>
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">To</label>
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control">
        </div>
        <!-- Text search -->
        <div class="col-md-4">
          <label class="form-label">Search (name/diagnosis)</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="e.g. flu, John">
          </div>
        </div>
      </div>
      <!-- Apply/Reset buttons -->
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-filter-circle me-1"></i>Apply</button>
        <a class="btn btn-outline-secondary" href="reports.php"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
      </div>
    </div>
  </form>

  <!-- If no reports, show empty state -->
  <?php if ($reports->num_rows === 0): ?>
    <div class="card card-shadow">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-clipboard2-x" style="font-size:2rem;"></i>
        <div class="mt-2">No reports found.</div>
      </div>
    </div>

  <!-- Otherwise, show reports table -->
  <?php else: ?>
    <div class="card card-shadow">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="min-width:70px;">ID</th>
              <th style="min-width:240px;">Patient</th>
              <th>Diagnosis</th>
              <th class="text-center" style="min-width:90px;">Meds</th>
              <th class="nowrap" style="min-width:160px;">Created</th>
              <th class="nowrap" style="min-width:160px;">Appt</th>
              <th class="text-center" style="min-width:160px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($r=$reports->fetch_assoc()): ?>
              <?php
                $rid   = (int)$r['id'];
                $diag  = trim((string)$r['diagnosis']);
                if (mb_strlen($diag)>80) $diag = mb_substr($diag,0,77).'…';

                // Build PDF path for this report
                $pdfFs  = __DIR__ . "/../uploads/reports/report_{$rid}.pdf";
                $pdfOk  = is_file($pdfFs); // check if PDF exists
                $pdfUrl = "../uploads/reports/report_{$rid}.pdf";
              ?>
              <tr>
                <td class="nowrap">#<?= $rid ?></td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($r['patient_name']) ?></div>
                  <div class="muted small">ID <?= htmlspecialchars((string)$r['patient_public_id']) ?></div>
                </td>
                <td><?= htmlspecialchars($diag ?: '—') ?></td>
                <td class="text-center">
                  <span class="pill"><?= (int)$r['meds_cnt'] ?> item<?= ((int)$r['meds_cnt']===1?'':'s') ?></span>
                </td>
                <td class="nowrap"><?= htmlspecialchars($r['created_at']) ?></td>
                <td class="nowrap"><?= $r['appt_dt'] ? htmlspecialchars($r['appt_dt']) : '—' ?></td>
                <td class="text-center nowrap">
                  <!-- View report preview -->
                  <a class="btn btn-sm btn-outline-primary" href="report_preview.php?report_id=<?= $rid ?>">
                    <i class="bi bi-eye me-1"></i>View
                  </a>
                  <!-- PDF link (enabled only if file exists) -->
                  <?php if ($pdfOk): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank">
                      <i class="bi bi-filetype-pdf me-1"></i>PDF
                    </a>
                  <?php else: ?>
                    <span class="btn btn-sm btn-outline-secondary disabled" title="PDF not found">
                      <i class="bi bi-filetype-pdf me-1"></i>PDF
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
