<?php
// download_report.php
session_start();
require_once 'config.php';

// --- Access control: only logged-in patients can download their reports ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    http_response_code(403);
    exit('Unauthorized.');
}

$patient_id = (int)$_SESSION['user_id'];
$report_id  = 0;

// --- Accept report ID from POST first (form submit), then GET (direct link) ---
if (isset($_POST['report_id']) && ctype_digit((string)$_POST['report_id'])) {
    $report_id = (int)$_POST['report_id'];
} elseif (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
    $report_id = (int)$_GET['id'];
}

// --- Basic validation of the report identifier ---
if ($report_id <= 0) {
    http_response_code(400);
    exit('Invalid report ID.');
}

/* --------------------------------------------------------------------------
   Load the report that belongs to the current patient, along with:
   - Doctor & patient public IDs (safe to show)
   - Vital stats and demographics
   - Linked appointment time (if any)
-------------------------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT 
        r.id, r.diagnosis, r.description, r.created_at,
        d.name AS doctor_name, d.user_id AS doctor_public_id, d.license_number AS doctor_license,
        p.name AS patient_name, p.user_id AS patient_public_id,
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
$repRes = $stmt->get_result();

// --- Not found or not owned by this patient ---
if ($repRes->num_rows !== 1) {
    http_response_code(404);
    exit('Report not found.');
}
$rep = $repRes->fetch_assoc();
$stmt->close();

/* --------------------------------------------------------------------------
   Medicines section
   - Some installs may store dosage_form/strength on prescribed_medicines
     as overrides; others only on medicines.
   - col_exists() checks the schema to decide which column to read.
-------------------------------------------------------------------------- */
function col_exists(mysqli $c, string $t, string $col): bool {
    $q = $c->prepare("
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1
    ");
    $q->bind_param("ss", $t, $col);
    $q->execute();
    $ok = (bool)$q->get_result()->num_rows;
    $q->close();
    return $ok;
}

$pm_has_form     = col_exists($conn, 'prescribed_medicines', 'dosage_form');
$pm_has_strength = col_exists($conn, 'prescribed_medicines', 'strength');

// Prefer pm.* overrides if columns exist; fall back to medicines.*
$formExpr     = $pm_has_form     ? "COALESCE(pm.dosage_form, m.dosage_form)" : "m.dosage_form";
$strengthExpr = $pm_has_strength ? "COALESCE(pm.strength, m.strength)"       : "m.strength";

$med_sql = "
    SELECT 
        m.name,
        COALESCE($formExpr,'')     AS dosage_form,
        COALESCE($strengthExpr,'') AS strength,
        pm.pills_per_day, pm.duration_days
    FROM prescribed_medicines pm
    JOIN medicines m ON pm.medicine_id = m.id
    WHERE pm.report_id = ?
    ORDER BY m.name
";
$mst = $conn->prepare($med_sql);
$mst->bind_param("i", $report_id);
$mst->execute();
$meds = $mst->get_result();

/* --------------------------------------------------------------------------
   Branding assets
   - If the logo file exists locally, embed it as a data URI so dompdf can
     render it without remote fetches.
-------------------------------------------------------------------------- */
$brand    = 'MediVerse';
$logoFs   = __DIR__ . '/assets/images/logo.png';
$logoData = '';
if (is_file($logoFs)) {
    $mime     = function_exists('mime_content_type') ? mime_content_type($logoFs) : 'image/png';
    $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoFs));
}

/* --------------------------------------------------------------------------
   Signature metadata
   - signature_id is a short hash tied to doctor_public_id + report_id + created_at
   - signed_when shows the issuance time that appears on the PDF
-------------------------------------------------------------------------- */
$created_at    = $rep['created_at'] ? date("Y-m-d H:i", strtotime($rep['created_at'])) : '—';
$appt_datetime = $rep['appointment_datetime'] ? date("Y-m-d H:i", strtotime($rep['appointment_datetime'])) : '—';
$signature_id  = strtoupper(substr(hash('sha256',
    ($rep['doctor_public_id'] ?? '') . '|' . $rep['id'] . '|' . ($rep['created_at'] ?? '')
), 0, 10));
$signed_when   = $created_at ?: date('Y-m-d H:i');

/* --------------------------------------------------------------------------
   Build the HTML that will be converted to PDF by Dompdf
   - Keep CSS simple and inline so dompdf renders consistently.
-------------------------------------------------------------------------- */
ob_start();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Report #<?= (int)$rep['id'] ?></title>
<style>
  @page { margin: 18mm; }
  body { font-family: DejaVu Sans, Arial, sans-serif; color:#0f172a; font-size:12px; }
  .header { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e5e7eb; padding-bottom:8px; margin-bottom:12px; }
  .brand { display:flex; align-items:center; gap:10px; }
  .brand-name { font-size:18px; font-weight:800; color:#0ea5e9; }
  .meta { text-align:right; font-size:11px; color:#475569; }
  .section { margin: 10px 0 14px; }
  .title { font-size:14px; font-weight:700; margin-bottom:6px; }
  .kv { width:100%; border-collapse:collapse; }
  .kv td { padding:4px 6px; vertical-align:top; }
  .kv .k { width:28%; color:#334155; font-weight:600; }
  .box { border:1px solid #e5e7eb; border-radius:6px; padding:8px; }
  table.rx { width:100%; border-collapse:collapse; }
  table.rx th, table.rx td { border:1px solid #e5e7eb; padding:6px 8px; }
  table.rx th { background:#f8fafc; }
  .sig { margin-top:16px; display:flex; justify-content:space-between; gap:20px; }
  .sig-block { width:48%; }
  .sig-line { border-bottom:1px solid #000; height:28px; }
  .muted { color:#64748b; font-size:11px; }
  .footer { margin-top:10px; border-top:1px solid #e5e7eb; padding-top:6px; font-size:10px; color:#64748b; display:flex; justify-content:space-between; }
</style>
</head>
<body>
  <div class="header">
    <div class="brand">
      <?php if ($logoData): ?>
        <img src="<?= $logoData ?>" alt="logo" width="42" height="42" style="border-radius:8px;">
      <?php endif; ?>
      <div>
        <div class="brand-name"><?= htmlspecialchars($brand) ?></div>
        <div class="muted">Medical Report</div>
      </div>
    </div>
    <div class="meta">
      <div><strong>Report #:</strong> <?= (int)$rep['id'] ?></div>
      <div><strong>Created:</strong> <?= htmlspecialchars($created_at) ?></div>
      <div><strong>Appointment:</strong> <?= htmlspecialchars($appt_datetime) ?></div>
    </div>
  </div>

  <div class="section">
    <div class="title">Patient Information</div>
    <table class="kv">
      <tr><td class="k">Name</td><td><?= htmlspecialchars($rep['patient_name']) ?></td></tr>
      <tr><td class="k">Patient ID</td><td><?= htmlspecialchars((string)$rep['patient_public_id']) ?></td></tr>
      <tr><td class="k">Gender</td><td><?= htmlspecialchars($rep['gender'] ?: '—') ?></td></tr>
      <tr><td class="k">Date of Birth</td><td><?= htmlspecialchars($rep['date_of_birth'] ?: '—') ?></td></tr>
      <tr><td class="k">Location</td><td><?= htmlspecialchars($rep['location'] ?: '—') ?></td></tr>
      <tr><td class="k">Height</td><td><?= htmlspecialchars((string)$rep['height_cm']) ?> cm</td></tr>
      <tr><td class="k">Weight</td><td><?= htmlspecialchars((string)$rep['weight_kg']) ?> kg</td></tr>
      <tr><td class="k">BMI</td><td><?= htmlspecialchars((string)$rep['bmi']) ?></td></tr>
    </table>
  </div>

  <div class="section">
    <div class="title">Report Details</div>
    <table class="kv">
      <tr><td class="k">Doctor</td><td><?= htmlspecialchars($rep['doctor_name']) ?></td></tr>
      <tr><td class="k">Doctor ID</td><td><?= htmlspecialchars((string)$rep['doctor_public_id']) ?></td></tr>
      <tr><td class="k">License #</td><td><?= htmlspecialchars((string)($rep['doctor_license'] ?? '—')) ?></td></tr>
      <tr><td class="k">Diagnosis</td><td><?= htmlspecialchars($rep['diagnosis']) ?></td></tr>
    </table>
    <div class="box" style="margin-top:6px;">
      <?= nl2br(htmlspecialchars($rep['description'])) ?>
    </div>
  </div>

  <div class="section">
    <div class="title">Prescribed Medicines</div>
    <?php if ($meds->num_rows): ?>
      <table class="rx">
        <thead><tr>
          <th>Medicine</th><th>Form</th><th>Strength</th><th>Pills/Day</th><th>Duration (Days)</th>
        </tr></thead>
        <tbody>
        <?php while($m = $meds->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= htmlspecialchars($m['dosage_form'] ?: '—') ?></td>
            <td><?= htmlspecialchars($m['strength'] ?: '—') ?></td>
            <td style="text-align:center;"><?= (int)$m['pills_per_day'] ?></td>
            <td style="text-align:center;"><?= (int)$m['duration_days'] ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="muted">No medicines prescribed.</div>
    <?php endif; ?>
  </div>

  <div class="sig">
    <div class="sig-block">
      <div class="muted">Doctor’s Signature</div>
      <div class="sig-line"></div>
      <div class="muted">Digitally signed • <?= htmlspecialchars($signed_when) ?> • ID: <?= htmlspecialchars($signature_id) ?></div>
    </div>
    <div class="sig-block">
      <div class="muted">Patient’s Signature</div>
      <div class="sig-line"></div>
    </div>
  </div>

  <div class="footer">
    <div>Generated by <?= htmlspecialchars($brand) ?> • For medical use only</div>
    <div><?= htmlspecialchars(date('Y-m-d H:i')) ?></div>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

/* --------------------------------------------------------------------------
   Render the PDF with Dompdf and stream it as a download
   - isRemoteEnabled allows embedded data URIs/images.
   - stream() forces a download with a friendly filename.
-------------------------------------------------------------------------- */
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Download filename (includes report ID)
$fname = 'MediVerse_Report_' . (int)$rep['id'] . '.pdf';
$dompdf->stream($fname, ['Attachment' => true]);
exit;
