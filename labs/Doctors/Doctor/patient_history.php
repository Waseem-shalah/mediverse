<?php
session_start();
require_once '../config.php';
require '../navbar_loggedin.php';

// ✅ Only allow doctors to access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php"); 
    exit();
}

// ✅ Get patient_id from the URL (e.g., medical_history.php?patient_id=5)
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if ($patient_id <= 0) die("Missing patient ID.");

/* ---------- Helper functions to detect DB structure ---------- */
// Check if a table exists in the current database
function table_exists(mysqli $c, string $t): bool {
    $q = $c->prepare("SELECT 1 FROM information_schema.TABLES 
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? LIMIT 1");
    $q->bind_param("s",$t); 
    $q->execute(); 
    $ok=(bool)$q->get_result()->num_rows; 
    $q->close(); 
    return $ok;
}

// Check if a column exists in a given table
function col_exists(mysqli $c, string $t, string $col): bool {
    $q = $c->prepare("SELECT 1 FROM information_schema.COLUMNS 
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
    $q->bind_param("ss",$t,$col); 
    $q->execute(); 
    $ok=(bool)$q->get_result()->num_rows; 
    $q->close(); 
    return $ok;
}

// Choose the right column expression if available, else return NULL
function pick_expr(mysqli $c, string $t, array $cands, string $alias): string {
    foreach ($cands as $col) 
        if (col_exists($c,$t,$col)) return "r.$col AS $alias";
    return "NULL AS $alias";
}

/* ---------- Detect reports table (different naming in projects) ---------- */
$rep_tbl = table_exists($conn,'reports') ? 'reports' :
           (table_exists($conn,'medical_reports') ? 'medical_reports' : '');
if ($rep_tbl === '') die("No reports table found.");

/* ---------- Detect columns for ordering & extra data ---------- */
$has_created = col_exists($conn,$rep_tbl,'created_at');
$has_appt_dt = col_exists($conn,'appointments','appointment_datetime');

// Medicines / prescribed_medicines optional columns
$has_pm_strength = col_exists($conn, 'prescribed_medicines', 'strength');
$has_pm_form     = col_exists($conn, 'prescribed_medicines', 'dosage_form');
$has_m_strength  = col_exists($conn, 'medicines', 'strength');
$has_m_form      = col_exists($conn, 'medicines', 'dosage_form');

// Choose how to show appointment date & ordering
$date_select = $has_appt_dt ? "a.appointment_datetime AS appt_dt" : "NULL AS appt_dt";
$order_by    = $has_created ? "ORDER BY r.created_at DESC" : "ORDER BY appt_dt DESC, r.id DESC";

/* ---------- Build SELECT fields dynamically ---------- */
$selects = [
  "r.id",
  pick_expr($conn,$rep_tbl,['diagnosis'],'diagnosis'),
  pick_expr($conn,$rep_tbl,['description','notes','report_text'],'description'),
  pick_expr($conn,$rep_tbl,['height','height_cm'],'height'),
  pick_expr($conn,$rep_tbl,['weight','weight_kg'],'weight'),
  pick_expr($conn,$rep_tbl,['bmi'],'bmi'),
  $date_select,
  "du.name AS doctor_name",
];

/* ---------- Main query to fetch patient’s medical history ---------- */
$sql = "
  SELECT
    ".implode(",\n    ", $selects)."
  FROM `$rep_tbl` r
  JOIN appointments a ON a.id = r.appointment_id
  JOIN users du       ON du.id = r.doctor_id
  WHERE r.patient_id = ?
  $order_by
";
$stmt = $conn->prepare($sql);
if (!$stmt) die('SQL prepare failed: '.htmlspecialchars($conn->error));
$stmt->bind_param("i",$patient_id);
$stmt->execute();
$result = $stmt->get_result();

/* ---------- Prepare query to fetch prescribed medicines for each report ---------- */
$dosage_form_expr = $has_pm_form
    ? "pm.dosage_form AS dosage_form"
    : ($has_m_form ? "m.dosage_form AS dosage_form" : "NULL AS dosage_form");

$strength_expr = $has_pm_strength
    ? "pm.strength AS strength"
    : ($has_m_strength ? "m.strength AS strength" : "NULL AS strength");

$med_sql = "
  SELECT 
      m.name,
      $dosage_form_expr,
      $strength_expr,
      pm.pills_per_day, 
      pm.duration_days
  FROM prescribed_medicines pm
  JOIN medicines m ON m.id = pm.medicine_id
  WHERE pm.report_id = ?
";
$med_q = $conn->prepare($med_sql);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Medical History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <h2>📜 Medical History</h2>

  <?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <?php
        // ✅ If BMI not in DB, calculate from height & weight
        $bmi = $row['bmi'];
        if (($bmi === null || $bmi === '') && !empty($row['height']) && !empty($row['weight'])) {
            $h = (float)$row['height'];
            if ($h > 0) $bmi = round(((float)$row['weight']) / pow($h/100, 2), 2);
        }

        // ✅ Format appointment date
        $when = '-';
        if (!empty($row['appt_dt'])) {
            $ts = strtotime($row['appt_dt']);
            $when = $ts ? date('Y-m-d H:i', $ts) : htmlspecialchars($row['appt_dt']);
        }
      ?>

      <!-- ✅ Each medical report in a card -->
      <div class="card mb-3">
        <div class="card-body">
          <p><strong>Date:</strong> <?= $when ?></p>
          <p><strong>Doctor:</strong> <?= htmlspecialchars($row['doctor_name'] ?? '-') ?></p>
          <p><strong>Diagnosis:</strong> <?= htmlspecialchars($row['diagnosis'] ?? '-') ?></p>
          <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($row['description'] ?? '-')) ?></p>

          <?php if (!empty($row['height'])): ?>
            <p><strong>Height:</strong> <?= htmlspecialchars($row['height']) ?> cm</p>
          <?php endif; ?>
          <?php if (!empty($row['weight'])): ?>
            <p><strong>Weight:</strong> <?= htmlspecialchars($row['weight']) ?> kg</p>
          <?php endif; ?>
          <?php if (!empty($bmi)): ?>
            <p><strong>BMI:</strong> <?= htmlspecialchars($bmi) ?></p>
          <?php endif; ?>

          <hr>
          <strong>Prescribed Medicines:</strong>
          <ul class="mb-0">
            <?php
              if ($med_q) {
                $rid = (int)$row['id'];
                $med_q->bind_param("i",$rid);
                $med_q->execute();
                $meds = $med_q->get_result();

                // ✅ List medicines or show "None"
                if ($meds->num_rows === 0) {
                    echo "<li>None</li>";
                } else {
                    while ($med = $meds->fetch_assoc()) {
                        $name     = htmlspecialchars($med['name'] ?? '—');
                        $form     = htmlspecialchars($med['dosage_form'] ?? '');
                        $strength = htmlspecialchars($med['strength'] ?? '');
                        $ppd      = (int)($med['pills_per_day'] ?? 0);
                        $days     = (int)($med['duration_days'] ?? 0);

                        $extra = [];
                        if ($form !== '')     $extra[] = $form;
                        if ($strength !== '') $extra[] = $strength;
                        $meta = count($extra) ? ' — ' . implode(' · ', $extra) : '';

                        echo "<li>{$name}{$meta} — {$ppd} Times a day for {$days} days</li>";
                    }
                }
              } else {
                echo "<li>Error loading medicines.</li>";
              }
            ?>
          </ul>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <p>No medical history found.</p>
  <?php endif; ?>
</div>
</body>
</html>
