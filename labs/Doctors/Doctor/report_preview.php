<?php
// Doctor/report_preview.php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
if ($report_id <= 0) {
    die("Missing report ID.");
}

/* -------- fetch report (ensure this doctor owns it) -------- */
$stmt = $conn->prepare("
    SELECT r.id, r.appointment_id, r.patient_id, r.doctor_id,
           r.diagnosis, r.description, r.created_at,
           d.name  AS doctor_name,
           d.user_id AS doctor_public_id,          -- doctor id from users.user_id
           d.license_number AS doctor_license,     -- license number
           p.name AS patient_name,
           p.user_id AS patient_public_id,         -- patient id from users.user_id
           p.gender, p.date_of_birth, p.height_cm, p.weight_kg, p.bmi, p.location,
           a.appointment_datetime
    FROM medical_reports r
    JOIN users d ON r.doctor_id = d.id
    JOIN users p ON r.patient_id = p.id
    LEFT JOIN appointments a ON r.appointment_id = a.id
    WHERE r.id = ? AND r.doctor_id = ?
    LIMIT 1
");
$doc_id = (int)$_SESSION['user_id'];
$stmt->bind_param("ii", $report_id, $doc_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    die("Report not found or you don't have access to it.");
}

/* -------- helpers to check columns -------- */
function col_exists(mysqli $c, string $t, string $col): bool {
    $q = $c->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $q->bind_param("ss", $t, $col);
    $q->execute();
    $ok = (bool)$q->get_result()->num_rows;
    $q->close();
    return $ok;
}

/* detect columns so we can COALESCE safely */
$pm_has_form      = col_exists($conn, 'prescribed_medicines', 'dosage_form');
$pm_has_strength  = col_exists($conn, 'prescribed_medicines', 'strength');
$med_has_form     = col_exists($conn, 'medicines', 'dosage_form');
$med_has_strength = col_exists($conn, 'medicines', 'strength');

/* Build fallback expressions: prefer pm.*, else medicines.* */
$formExpr = $med_has_form ? "m.dosage_form" : "NULL";
if ($pm_has_form) { $formExpr = "COALESCE(pm.dosage_form, $formExpr)"; }
$formExpr .= " AS dosage_form";

$strengthExpr = $med_has_strength ? "m.strength" : "NULL";
if ($pm_has_strength) { $strengthExpr = "COALESCE(pm.strength, $strengthExpr)"; }
$strengthExpr .= " AS strength";

/* -------- fetch prescribed medicines with fallback -------- */
$med_sql = "
    SELECT m.name AS medicine_name,
           pm.pills_per_day, pm.duration_days,
           $formExpr, $strengthExpr
    FROM prescribed_medicines pm
    JOIN medicines m ON pm.medicine_id = m.id
    WHERE pm.report_id = ?
";
$med_stmt = $conn->prepare($med_sql);
$med_stmt->bind_param("i", $report_id);
$med_stmt->execute();
$medicines = $med_stmt->get_result();
$med_stmt->close();

/* dates / meta */
$created_at    = $report['created_at'] ? date("Y-m-d H:i", strtotime($report['created_at'])) : '—';
$appt_datetime = $report['appointment_datetime'] ? date("Y-m-d H:i", strtotime($report['appointment_datetime'])) : '—';

/* brand */
$host  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$brand = 'MediVerse';
$logoSrc = '../assets/images/logo.png'; // <-- your logo

/* auto-generated doctor signature (stable, non-PII) */
$signature_id = strtoupper(substr(hash('sha256',
    ($report['doctor_public_id'] ?? '') . '|' . $report['id'] . '|' . ($report['created_at'] ?? '')
), 0, 10));
$signed_when  = $created_at ?: date('Y-m-d H:i');

/* back url (referrer) */
$backUrl = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'view_appointments.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Medical Report — <?= htmlspecialchars($brand) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- script-like font for signature -->
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
    <style>
        :root{ --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; --brand:#0ea5e9; --bg:#f6f9fc; }
        body{ background:var(--bg); color:var(--ink); }
        .sheet{
          background:#fff; border:1px solid var(--line);
          border-radius:16px; box-shadow:0 10px 30px rgba(15,23,42,.06);
        }
        .brandbar{
          border-bottom:1px solid var(--line);
          padding:18px 22px; display:flex; align-items:center; justify-content:space-between;
        }
        .brand-left{ display:flex; align-items:center; gap:12px; }
        .brand-name{ font-size:22px; font-weight:800; letter-spacing:.3px; color:var(--brand); }
        .brand-sub{ color:var(--muted); font-size:12px; }
        .meta{
          display:grid; grid-template-columns:repeat(3,auto); gap:10px 18px; font-size:13px; color:#334155;
        }
        .meta .k{ font-weight:600; color:#1f2937; margin-right:6px; }
        .section{ padding:22px; border-bottom:1px solid var(--line); }
        .section:last-child{ border-bottom:0; }
        .title{ font-weight:700; color:#111827; margin-bottom:12px; }
        .kv{ display:grid; grid-template-columns:1fr 1fr; gap:8px 24px; }
        .kv .k{ font-weight:600; color:#334155; }
        .box{ border:1px solid var(--line); border-radius:12px; padding:14px; }
        .rx-table thead th{ background:#f8fafc; border-bottom:1px solid var(--line); }
        .rx-table tbody td{ vertical-align:middle; }
        .foot{ padding:14px 22px; border-top:1px solid var(--line); color:var(--muted); font-size:12px; display:flex; justify-content:space-between; }
        .watermark{ position:absolute; inset:0; pointer-events:none; opacity:.05;
          background: radial-gradient(120px 120px at 10% 20%, var(--brand), transparent 70%),
                      radial-gradient(120px 120px at 90% 80%, #3b82f6, transparent 70%);
          border-radius:16px;
        }
        .sigline{ height:44px; }
        .sigcap{ font-size:12px; color:var(--muted); margin-top:4px; }
        .sig-script{ font-family:'Great Vibes', cursive; font-size:34px; line-height:1; color:#111827; }
        .sig-meta{ font-size:11px; color:var(--muted); }

        /* ==== PRINT: only print the report; make it fit one A4 page ==== */
        @media print{
          @page { size: A4; margin: 10mm; }         /* compact margins */
          body{ background:#fff; }
          /* Hide everything except the report sheet */
          body * { visibility: hidden !important; }
          .sheet, .sheet * { visibility: visible !important; }
          .sheet{ position: static; box-shadow:none !important; border:none !important; }
          /* Compact typography/padding to keep to one page if possible */
          .sheet{ font-size:12px; }
          .brand-name{ font-size:16px !important; }
          .meta{ font-size:10px !important; }
          .section{ padding:12px 16px !important; }
          .kv{ gap:6px 16px !important; }
          .rx-table th, .rx-table td { padding:.35rem .4rem !important; }
          .title{ margin-bottom:8px !important; }
          .foot{ padding:8px 16px !important; font-size:11px !important; }
          .section, .rx-table, .kv, .brandbar { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container my-4">
  <div class="position-relative sheet">
    <div class="watermark"></div>

    <!-- Letterhead -->
    <div class="brandbar">
      <div class="brand-left">
        <img src="<?= htmlspecialchars($logoSrc) ?>" alt="MediVerse logo" width="48" height="48" style="border-radius:10px;">
        <div>
          <div class="brand-name"><?= htmlspecialchars($brand) ?></div>
        </div>
      </div>
      <div class="meta">
        <div><span class="k">Report #:</span> <?= (int)$report['id'] ?></div>
        <div><span class="k">Created:</span> <?= htmlspecialchars($created_at) ?></div>
        <div><span class="k">Appointment:</span> <?= htmlspecialchars($appt_datetime) ?></div>
      </div>
    </div>

    <!-- Patient snapshot -->
    <div class="section">
      <div class="title">Patient Information</div>
      <div class="kv">
        <div><span class="k">Name:</span> <?= htmlspecialchars($report['patient_name']) ?></div>
        <div><span class="k">Patient ID:</span> <?= htmlspecialchars((string)$report['patient_public_id']) ?></div>
        <div><span class="k">Gender:</span> <?= htmlspecialchars($report['gender'] ?: '—') ?></div>
        <div><span class="k">Date of Birth:</span> <?= htmlspecialchars($report['date_of_birth'] ?: '—') ?></div>
        <div><span class="k">Location:</span> <?= htmlspecialchars($report['location'] ?: '—') ?></div>
        <div><span class="k">Height:</span> <?= htmlspecialchars((string)$report['height_cm']) ?> cm</div>
        <div><span class="k">Weight:</span> <?= htmlspecialchars((string)$report['weight_kg']) ?> kg</div>
        <div><span class="k">BMI:</span> <?= htmlspecialchars((string)$report['bmi']) ?></div>
      </div>
    </div>

    <!-- Report details -->
    <div class="section">
      <div class="title">Report Details</div>
      <div class="kv mb-2">
        <div><span class="k">Doctor:</span> <?= htmlspecialchars($report['doctor_name']) ?></div>
        <div><span class="k">Doctor ID:</span> <?= htmlspecialchars((string)$report['doctor_public_id']) ?></div>
        <div><span class="k">License #:</span> <?= htmlspecialchars((string)($report['doctor_license'] ?? '—')) ?></div>
      </div>
      <div class="mb-2"><span class="k">Diagnosis:</span> <?= htmlspecialchars($report['diagnosis']) ?></div>
      <div class="box">
        <div class="k mb-1">Description</div>
        <div><?= nl2br(htmlspecialchars($report['description'])) ?></div>
      </div>
    </div>

    <!-- Prescriptions -->
    <div class="section">
      <div class="title">Prescribed Medicines</div>
      <?php if ($medicines->num_rows > 0): ?>
        <div class="table-responsive">
          <table class="table rx-table align-middle">
            <thead>
              <tr>
                <th>Medicine</th>
                <th>Form</th>
                <th>Strength</th>
                <th class="text-center">Pills / Day</th>
                <th class="text-center">Duration (Days)</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($m = $medicines->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($m['medicine_name']) ?></td>
                  <td><?= htmlspecialchars(($m['dosage_form'] ?? '') !== '' ? $m['dosage_form'] : '—') ?></td>
                  <td><?= htmlspecialchars(($m['strength'] ?? '') !== '' ? $m['strength'] : '—') ?></td>
                  <td class="text-center"><?= (int)$m['pills_per_day'] ?></td>
                  <td class="text-center"><?= (int)$m['duration_days'] ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-muted mb-0">No medicines prescribed.</p>
      <?php endif; ?>
    </div>

    <!-- Signatures -->
    <div class="section">
      <div class="row g-4">
        <div class="col-md-6">
          <div class="sig-script"><?= htmlspecialchars($report['doctor_name']) ?></div>
          <div class="sig-meta">
            Digitally signed • <?= htmlspecialchars($signed_when) ?>
            • Signature ID: <?= htmlspecialchars($signature_id) ?>
          </div>
          <div class="sigcap mt-2">Doctor’s Signature</div>
        </div>
        <div class="col-md-6">
          <div class="sigline border-bottom"></div>
          <div class="sigcap">Patient’s Signature</div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="foot">
      <div>Generated by <?= htmlspecialchars($brand) ?> • This report is for medical use only.</div>
      <div><?= htmlspecialchars(date('Y-m-d H:i')) ?></div>
    </div>
  </div>

  <div class="no-print mt-3 d-flex gap-2">
      <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
      <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-secondary">← Back</a>
  </div>
</div>
</body>
</html>
