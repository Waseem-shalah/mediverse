<?php
session_start();
require_once 'config.php';
include 'navbar_loggedin.php';

// Guard: only logged-in patients can see their reports
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

// Accept report id from ?report_id or ?id, ensure it’s numeric
$report_id_raw = $_GET['report_id'] ?? $_GET['id'] ?? null;
if (!$report_id_raw || !ctype_digit((string)$report_id_raw)) {
    die("Missing report ID.");
}
$report_id  = (int)$report_id_raw;
$patient_id = (int)$_SESSION['user_id'];

/* ---- Load the report + related doctor/patient info (scoped to this patient) ---- */
$stmt = $conn->prepare("
    SELECT 
        r.id,
        r.diagnosis,
        r.description,
        r.created_at,
        d.name  AS doctor_name,
        d.user_id AS doctor_public_id,
        d.license_number AS doctor_license,
        p.name AS patient_name,
        p.user_id AS patient_public_id,
        p.gender, p.date_of_birth, p.height_cm, p.weight_kg, p.bmi, p.location,
        a.appointment_datetime
    FROM medical_reports r
    JOIN users d ON r.doctor_id = d.id
    JOIN users p ON r.patient_id = p.id
    LEFT JOIN appointments a ON r.appointment_id = a.id
    WHERE r.id = ? AND r.patient_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $report_id, $patient_id);
$stmt->execute();
$report_res = $stmt->get_result();
if ($report_res->num_rows === 0) {
    $stmt->close();
    die("Report not found.");
}
$report = $report_res->fetch_assoc();
$stmt->close();

/* ---- Helpers: detect optional columns so we can safely COALESCE them ---- */
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
$pm_has_form     = col_exists($conn, 'prescribed_medicines', 'dosage_form');
$pm_has_strength = col_exists($conn, 'prescribed_medicines', 'strength');

// Build expressions that fall back to medicines table if pm.* is missing/null
$formExpr     = $pm_has_form     ? "COALESCE(pm.dosage_form, m.dosage_form)" : "m.dosage_form";
$strengthExpr = $pm_has_strength ? "COALESCE(pm.strength, m.strength)"       : "m.strength";

/* ---- Fetch prescribed medicines for this report ---- */
$med_sql = "
    SELECT 
        m.name,
        COALESCE($formExpr,'')     AS dosage_form,
        COALESCE($strengthExpr,'') AS strength,
        pm.pills_per_day,
        pm.duration_days
    FROM prescribed_medicines pm
    JOIN medicines m ON pm.medicine_id = m.id
    WHERE pm.report_id = ?
    ORDER BY m.name
";
$stmt2 = $conn->prepare($med_sql);
$stmt2->bind_param("i", $report_id);
$stmt2->execute();
$meds_result = $stmt2->get_result();

/* ---- Format dates for display ---- */
$created_at    = $report['created_at'] ? date("Y-m-d H:i", strtotime($report['created_at'])) : '—';
$appt_datetime = $report['appointment_datetime'] ? date("Y-m-d H:i", strtotime($report['appointment_datetime'])) : '—';

/* ---- Branding / assets ---- */
$host    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$brand   = 'MediVerse';
$logoSrc = 'assets/images/logo.png'; // relative path for the patient UI

/* ---- Lightweight “digital signature” fingerprint for the doctor ---- */
$signature_id = strtoupper(substr(hash('sha256',
    ($report['doctor_public_id'] ?? '') . '|' . $report['id'] . '|' . ($report['created_at'] ?? '')
), 0, 10));
$signed_when  = $created_at ?: date('Y-m-d H:i');

/* ---- Return path: go back to where user came from or to the list ---- */
$backUrl = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'medical_reports.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Medical Report — <?= htmlspecialchars($brand) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Cursive font for the signature look -->
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
    <style>
        /* Scoped styles so the global navbar/theme isn’t affected */
        .report-scope{
          --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; --brand:#0ea5e9; --bg:#f6f9fc;
          background:var(--bg); color:var(--ink);
        }
        .report-scope .sheet{
          background:#fff; border:1px solid var(--line);
          border-radius:16px; box-shadow:0 10px 30px rgba(15,23,42,.06);
        }
        .report-scope .brandbar{
          border-bottom:1px solid var(--line);
          padding:18px 22px; display:flex; align-items:center; justify-content:space-between;
        }
        .report-scope .brand-left{ display:flex; align-items:center; gap:12px; }
        .report-scope .brand-name{ font-size:22px; font-weight:800; letter-spacing:.3px; color:var(--brand); }
        .report-scope .brand-sub{ color:var(--muted); font-size:12px; }
        .report-scope .meta{
          display:grid; grid-template-columns:repeat(3,auto); gap:10px 18px; font-size:13px; color:#334155;
        }
        .report-scope .meta .k{ font-weight:600; color:#1f2937; margin-right:6px; }
        .report-scope .section{ padding:22px; border-bottom:1px solid var(--line); }
        .report-scope .section:last-child{ border-bottom:0; }
        .report-scope .title{ font-weight:700; color:#111827; margin-bottom:12px; }
        .report-scope .kv{ display:grid; grid-template-columns:1fr 1fr; gap:8px 24px; }
        .report-scope .kv .k{ font-weight:600; color:#334155; }
        .report-scope .box{ border:1px solid var(--line); border-radius:12px; padding:14px; }
        .report-scope .rx-table thead th{ background:#f8fafc; border-bottom:1px solid var(--line); }
        .report-scope .rx-table tbody td{ vertical-align:middle; }
        .report-scope .foot{ padding:14px 22px; border-top:1px solid var(--line); color:var(--muted); font-size:12px; display:flex; justify-content:space-between; }
        .report-scope .watermark{ position:absolute; inset:0; pointer-events:none; opacity:.05;
          background: radial-gradient(120px 120px at 10% 20%, var(--brand), transparent 70%),
                      radial-gradient(120px 120px at 90% 80%, #3b82f6, transparent 70%);
          border-radius:16px;
        }
        .report-scope .sigline{ height:44px; }
        .report-scope .sigcap{ font-size:12px; color:var(--muted); margin-top:4px; }
        .report-scope .sig-script{ font-family:'Great Vibes', cursive; font-size:34px; line-height:1; color:#111827; }
        .report-scope .sig-meta{ font-size:11px; color:var(--muted); }

        /* Print layout: hide chrome, compact spacing, keep report together */
        @media print{
          @page { size: A4; margin: 10mm; }
          .navbar, .no-print { display:none !important; }

          body * { visibility: hidden !important; }
          .report-scope, .report-scope * { visibility: visible !important; }

          .report-scope .sheet{ position: static; box-shadow:none !important; border:none !important; }
          .report-scope .sheet{ font-size:12px; }
          .report-scope .brand-name{ font-size:16px !important; }
          .report-scope .meta{ font-size:10px !important; }
          .report-scope .section{ padding:12px 16px !important; }
          .report-scope .kv{ gap:6px 16px !important; }
          .report-scope .rx-table th, .report-scope .rx-table td { padding:.35rem .4rem !important; }
          .report-scope .title{ margin-bottom:8px !important; }
          .report-scope .foot{ padding:8px 16px !important; font-size:11px !important; }
          .report-scope .section, .report-scope .rx-table, .report-scope .kv, .report-scope .brandbar { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="report-scope">
  <div class="container my-4">
    <div class="position-relative sheet">
      <div class="watermark"></div>

      <!-- Header / letterhead -->
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
          <div><span class="k">&nbsp;</span></div>
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
        <?php if ($meds_result->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table rx-table align-middle">
              <thead>
                <tr>
                  <th>Medicine</th>
                  <th>Form</th>
                  <th>Strength</th>
                  <th class="text-center">Times a Day</th>
                  <th class="text-center">Duration (Days)</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($med = $meds_result->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($med['name']) ?></td>
                    <td><?= htmlspecialchars($med['dosage_form'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($med['strength'] ?: '—') ?></td>
                    <td class="text-center"><?= (int)$med['pills_per_day'] ?></td>
                    <td class="text-center"><?= (int)$med['duration_days'] ?></td>
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

    <!-- Actions (not printed) -->
    <div class="mt-3 d-flex gap-2 no-print">
        <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-secondary">← Back</a>
    </div>
  </div>
</div>
</body>
</html>
