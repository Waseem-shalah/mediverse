<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'patient') {
    // Only logged-in patients can view this page
    header("Location: login.php");
    exit;
}

$patient_id   = (int)$_SESSION["user_id"];
$appointments = [];
$error        = '';

/* ---------------- Filters (from GET) ---------------- */
$doctorLike = trim($_GET['doctor'] ?? '');      // partial doctor name match
$specKey    = $_GET['spec'] ?? '';              // ''=all, 'null'=no spec, number=specialization id
$dateEq     = trim($_GET['date'] ?? '');        // exact date filter (YYYY-MM-DD)
$timeEq     = trim($_GET['time'] ?? '');        // exact time filter (HH:MM)
$statusEq   = trim($_GET['status'] ?? '');      // scheduled|completed|canceled

/* Build specialization options based on THIS patient's appointments */
$specOptions = [];
$optSql = "
  SELECT d.specialization_id AS id,
         COALESCE(sp.name, 'Unspecified') AS name
  FROM appointments a
  JOIN users d              ON d.id = a.doctor_id
  LEFT JOIN specializations sp ON sp.id = d.specialization_id
  WHERE a.patient_id = ?
  GROUP BY d.specialization_id, name
  ORDER BY name
";
if ($opt = $conn->prepare($optSql)) {
    $opt->bind_param("i", $patient_id);
    $opt->execute();
    $optRes = $opt->get_result();
    while ($r = $optRes->fetch_assoc()) {
        $specOptions[] = [
            'id'   => ($r['id'] === null ? 'null' : (string)(int)$r['id']),
            'name' => $r['name']
        ];
    }
    $opt->close();
}

/* ---------------- Main query with optional filters ---------------- */
$sql = "SELECT 
          a.*,
          d.name AS doctor_name,
          d.location AS doctor_location,
          d.specialization_id,
          COALESCE(sp.name, 'Unspecified') AS specialization_name,
          s.date AS slot_date,
          s.time AS slot_time
        FROM appointments a
        JOIN users d              ON a.doctor_id = d.id
        LEFT JOIN specializations sp ON sp.id = d.specialization_id
        LEFT JOIN slots s         ON a.slot_id = s.id
        WHERE a.patient_id = ?";

$types  = "i";
$params = [$patient_id];

/* Doctor name LIKE */
if ($doctorLike !== '') {
    $sql    .= " AND d.name LIKE ? ";
    $types  .= "s";
    $params[] = "%{$doctorLike}%";
}

/* Specialization filter */
if ($specKey !== '') {
    if ($specKey === 'null') {
        $sql .= " AND d.specialization_id IS NULL ";
    } elseif (ctype_digit($specKey) && (int)$specKey > 0) {
        $sql    .= " AND d.specialization_id = ? ";
        $types  .= "i";
        $params[] = (int)$specKey;
    }
}

/* Date filter — use slot date if present, otherwise appointment_datetime */
if ($dateEq !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEq)) {
    $sql    .= " AND ((s.date IS NOT NULL AND s.date = ?) OR (s.date IS NULL AND DATE(a.appointment_datetime) = ?))";
    $types  .= "ss";
    $params[] = $dateEq;
    $params[] = $dateEq;
}

/* Time filter — same fallback approach (slot time vs appointment_datetime) */
if ($timeEq !== '' && preg_match('/^\d{2}:\d{2}$/', $timeEq)) {
    $sql    .= " AND ((s.time IS NOT NULL AND s.time = ?) OR (s.time IS NULL AND TIME(a.appointment_datetime) = ?))";
    $types  .= "ss";
    $params[] = $timeEq . ':00'; // normalize to HH:MM:SS
    $params[] = $timeEq . ':00';
}

/* Status filter */
if ($statusEq !== '' && in_array($statusEq, ['scheduled','completed','canceled'], true)) {
    $sql    .= " AND a.status = ? ";
    $types  .= "s";
    $params[] = $statusEq;
}

/* Newest first (by date/time with slot fallback) */
$sql .= " ORDER BY 
            COALESCE(s.date, DATE(a.appointment_datetime)) DESC,
            COALESCE(s.time, TIME(a.appointment_datetime)) DESC";

/* Execute prepared statement with dynamic bindings */
if ($stmt = $conn->prepare($sql)) {
    $bind = [];
    $bind[] = & $types;
    foreach ($params as $k => $v) { $bind[] = & $params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $bind);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
    } else {
        $error = "Error loading appointments: " . $stmt->error;
    }
    $stmt->close();
} else {
    $error = "Error preparing statement: " . $conn->error;
}

/* Small helper for bootstrap badge color */
function statusBadgeClass(string $s): string {
    return match ($s) {
        'completed' => 'success',
        'canceled'  => 'danger',
        default     => 'warning'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Appointments - MediVerse</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icons for nicer UI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f6f9fc; }
        .page-wrap { max-width: 1100px; }
        .card { border: 1px solid #e9ecef; }
        .table thead th { background-color: #f8fafc; }
        .filters .form-label { font-weight: 600; color: #334155; }
        .empty-state { padding: 48px 16px; color: #64748b; }
        .btn-gradient-blue { background: linear-gradient(135deg,#0d6efd,#3f8cff); color:#fff; }
        .btn-gradient-green{ background: linear-gradient(135deg,#16a34a,#22c55e); color:#fff; }
    </style>
</head>
<body>

<?php include "navbar_loggedin.php"; ?>

<div class="container page-wrap my-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="m-0">My Appointments</h2>
        <span class="badge bg-primary-subtle text-primary fs-6">
            <i class="bi bi-calendar2-check me-1"></i><?= count($appointments) ?> result<?= count($appointments)===1?'':'s' ?>
        </span>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center">
            <i class="bi bi-funnel me-2"></i> Filters
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 filters">
                <div class="col-md-4">
                    <label class="form-label">Doctor name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="doctor" class="form-control" placeholder="e.g. Ahmed"
                               value="<?= htmlspecialchars($doctorLike) ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Specialization</label>
                    <select name="spec" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($specOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt['id']) ?>"
                                <?= ($specKey !== '' && $specKey === $opt['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateEq) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Time</label>
                    <input type="time" name="time" class="form-control" value="<?= htmlspecialchars($timeEq) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="scheduled" <?= $statusEq==='scheduled'?'selected':''; ?>>Scheduled</option>
                        <option value="completed" <?= $statusEq==='completed'?'selected':''; ?>>Completed</option>
                        <option value="canceled"  <?= $statusEq==='canceled'?'selected':'';  ?>>Canceled</option>
                    </select>
                </div>

                <div class="col-md-3 d-grid">
                    <label class="form-label opacity-0">Search</label>
                    <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Search</button>
                </div>
                <div class="col-md-3 d-grid">
                    <label class="form-label opacity-0">Reset</label>
                    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
        <!-- Backend error loading the table -->
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php elseif (empty($appointments)): ?>
        <!-- No matches for current filters -->
        <div class="card">
            <div class="card-body text-center empty-state">
                <i class="bi bi-clipboard2-heart fs-2 mb-2 d-block"></i>
                No appointments match your filters.
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Booked On</th>
                            <th style="min-width:220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($appointments as $appt): ?>
                        <?php
                            // Display-friendly date/time with slot fallback
                            $dispDate = !empty($appt["slot_date"])
                                ? $appt["slot_date"]
                                : date("Y-m-d", strtotime($appt["appointment_datetime"]));
                            $dispTime = !empty($appt["slot_time"])
                                ? $appt["slot_time"]
                                : date("H:i:s", strtotime($appt["appointment_datetime"]));
                            $badge = statusBadgeClass($appt["status"]);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($appt["doctor_name"]) ?></td>
                            <td><?= htmlspecialchars($appt["specialization_name"]) ?></td>
                            <td><?= htmlspecialchars($appt["doctor_location"]) ?></td>
                            <td><?= htmlspecialchars($dispDate) ?></td>
                            <td><?= htmlspecialchars(substr($dispTime,0,5)) ?></td>
                            <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($appt["status"]) ?></span></td>
                            <td><?= htmlspecialchars(date("Y-m-d", strtotime($appt["created_at"]))) ?></td>
                            <td class="actions-cell">
                                <?php if ($appt["status"] !== 'completed'): ?>
                                    <!-- Allow cancel while not yet completed -->
                                    <a href="cancel_appointment.php?id=<?= (int)$appt['id'] ?>"
                                       class="btn btn-sm btn-outline-danger me-1"
                                       onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                       <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                <?php endif; ?>

                                <?php if ($appt["status"] === 'completed'):
                                    /* If there is a report, show the quick link */
                                    $chk = $conn->prepare("SELECT id FROM medical_reports WHERE appointment_id = ?");
                                    $chk->bind_param("i", $appt['id']);
                                    $chk->execute();
                                    $rres = $chk->get_result();
                                    if ($rres->num_rows === 1):
                                        $rid = (int)$rres->fetch_assoc()['id']; ?>
                                        <a href="report_detail.php?report_id=<?= $rid ?>"
                                           class="btn btn-sm btn-outline-primary me-1" title="View your medical report">
                                           <i class="bi bi-file-earmark-text"></i> Report
                                        </a>
                                    <?php 
                                    endif;
                                    $chk->close();

                                    /* If not rated yet, let the patient rate this visit */
                                    $chk2 = $conn->prepare("SELECT 1 FROM ratings WHERE appointment_id = ?");
                                    $chk2->bind_param("i", $appt['id']);
                                    $chk2->execute();
                                    $already = $chk2->get_result()->num_rows > 0;
                                    $chk2->close();

                                    if (!$already): ?>
                                        <a href="rate_doctor.php?appointment_id=<?= (int)$appt['id'] ?>"
                                           class="btn btn-sm btn-outline-success" title="Rate your doctor">
                                           <i class="bi bi-star"></i> Rate
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Rated</span>
                                    <?php endif; 
                                endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Simple flash messages from booking/cancel flows -->
    <?php if (isset($_GET["success"])): ?>
        <div class="alert alert-success mt-3"><i class="bi bi-check-circle me-2"></i>Appointment booked successfully!</div>
    <?php elseif (isset($_GET["cancelled"])): ?>
        <div class="alert alert-success mt-3"><i class="bi bi-check-circle me-2"></i>Appointment cancelled successfully.</div>
    <?php elseif (isset($_GET["error"])): ?>
        <div class="alert alert-danger mt-3"><i class="bi bi-exclamation-triangle me-2"></i>Error: Could not cancel the appointment.</div>
    <?php endif; ?>
</div>

</body>
</html>
