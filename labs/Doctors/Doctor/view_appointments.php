<?php
session_start();
require_once '../config.php';

// ✅ Check if the user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

// ✅ If doctor clicked "Mark as Done" on an appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_done'])) {
    $aid = (int)$_POST['mark_done']; // appointment ID
    // Update appointment status to completed
    $upd = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
    $upd->bind_param("i", $aid);
    $upd->execute();
    // Redirect back with success flag
    header("Location: view_appointments.php?done=$aid");
    exit();
}

// ✅ Handle search filters
$search     = trim($_GET['q'] ?? ''); // patient name or ID
$filterDate = trim($_GET['d'] ?? ''); // specific date YYYY-MM-DD

// ✅ Build base query: get doctor’s appointments, join patient info, and slots if exist
$sql = "SELECT a.id, 
               a.patient_id, 
               u.user_id AS patient_user_id,
               u.name AS patient_name, 
               COALESCE(s.date, DATE(a.appointment_datetime)) AS date,
               COALESCE(s.time, TIME(a.appointment_datetime)) AS time,
               a.status
        FROM appointments a
        JOIN users u       ON a.patient_id = u.id
        LEFT JOIN slots s  ON a.slot_id     = s.id
        WHERE a.doctor_id = ?";

// ✅ Initial bind params: doctor_id
$types  = "i";
$params = [$_SESSION['user_id']];

// ✅ Apply search filter if patient name or ID entered
if ($search !== '') {
    $sql   .= " AND (u.name LIKE ?";
    $types .= "s";
    $params[] = "%{$search}%";

    // If search looks like a number, also check against IDs
    if (ctype_digit($search)) {
        $sql   .= " OR u.user_id = ? OR u.id = ? OR a.patient_id = ?";
        $types .= "iii";
        $num = (int)$search;
        $params[] = $num; // users.user_id
        $params[] = $num; // users.id
        $params[] = $num; // appointments.patient_id
    }
    $sql .= ")";
}

// ✅ Apply date filter if valid format
if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $sql    .= " AND ((s.date IS NOT NULL AND s.date = ?) OR (s.date IS NULL AND DATE(a.appointment_datetime) = ?))";
    $types  .= "ss";
    $params[] = $filterDate;
    $params[] = $filterDate;
}

// ✅ Order results by date & time
$sql .= " ORDER BY date, time";

// ✅ Prepare SQL safely
$stmt = $conn->prepare($sql);
if (!$stmt) { die("Query error: " . $conn->error); }

// ✅ Bind parameters dynamically
if (!empty($params)) {
    $bind = [];
    $bind[] = & $types;
    foreach ($params as $k => $v) { $bind[] = & $params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

$stmt->execute();
$result = $stmt->get_result();
$count  = $result ? $result->num_rows : 0;
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>View Appointments | MediVerse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Basic styling */
    body{ background:#f6f9fc; }
    .page-wrap{ max-width: 1200px; }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      background:#eef2ff; color:#3730a3;
      border-radius:999px; padding:.35rem .7rem; font-weight:700; font-size:.9rem;
    }
    .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); border:1px solid #e9ecef; }
    .filters .form-label{ font-weight:600; color:#334155; }
    .table thead th{ background:#f8fafc; }
    .status-badge{ font-weight:600; }
    .status-scheduled{ background:#e0f2fe; color:#075985; }  /* info */
    .status-completed{ background:#dcfce7; color:#166534; }  /* success */
    .status-canceled{ background:#fee2e2; color:#991b1b; }   /* danger */
  </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap my-4">

  <!-- ✅ Page Header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
      <h3 class="mb-0">📖 Your Appointments</h3>
      <span class="chip"><i class="bi bi-calendar2-week me-1"></i><?= (int)$count ?> result<?= $count===1?'':'s' ?></span>
    </div>
  </div>

  <!-- ✅ Success/Flash messages -->
  <?php if (isset($_GET['done'])): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>
      Appointment #<?= (int)$_GET['done'] ?> marked as completed.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['report'])): ?>
    <?php if ($_GET['report'] === 'sent'): ?>
      <div class="alert alert-success"><i class="bi bi-envelope-check me-1"></i>
        Report for appointment #<?= (int)($_GET['aid'] ?? 0) ?> was saved and emailed to the patient.
      </div>
    <?php elseif ($_GET['report'] === 'nosend'): ?>
      <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>
        Report for appointment #<?= (int)($_GET['aid'] ?? 0) ?> was saved, but email could not be sent.
      </div>
    <?php elseif ($_GET['report'] === 'error'): ?>
      <div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i>
        An error occurred while saving the report.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (isset($_GET['success']) && $_GET['success'] === 'report_saved'): ?>
    <div class="alert alert-success"><i class="bi bi-check2-circle me-1"></i>
      ✅ Medical report has been saved successfully.
    </div>
  <?php endif; ?>

  <!-- ✅ Filters Form -->
  <form method="get" class="card card-shadow mb-3" autocomplete="off">
    <div class="card-body">
      <div class="row g-3 align-items-end filters">
        <!-- Search by name or ID -->
        <div class="col-md-6">
          <label class="form-label">Patient name or ID</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
            <input type="text" name="q" class="form-control" placeholder="e.g. John or 12345"
                   value="<?= htmlspecialchars($search) ?>">
          </div>
        </div>
        <!-- Filter by date -->
        <div class="col-md-3">
          <label class="form-label">Date</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
            <input type="date" name="d" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
          </div>
        </div>
        <!-- Apply & Reset -->
        <div class="col-md-3 d-grid">
          <label class="form-label opacity-0">Apply</label>
          <div class="d-flex gap-2">
            <button class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Search</button>
            <a href="view_appointments.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- ✅ Results Table -->
  <?php if ($count === 0): ?>
    <!-- If no appointments -->
    <div class="card card-shadow">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-clipboard2-x" style="font-size:2rem;"></i>
        <div class="mt-2">No appointments found for the selected filters.</div>
      </div>
    </div>
  <?php else: ?>
    <!-- Table of appointments -->
    <div class="card card-shadow">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Patient ID</th>
              <th>Patient</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($a = $result->fetch_assoc()): ?>
            <?php
              // Badge color depending on status
              $status = strtolower((string)$a['status']);
              $badgeClass = 'status-badge status-scheduled';
              if ($status === 'completed') $badgeClass = 'status-badge status-completed';
              if ($status === 'canceled' || $status === 'cancelled') $badgeClass = 'status-badge status-canceled';
            ?>
            <tr>
              <td>#<?= (int)$a['id'] ?></td>
              <td><?= htmlspecialchars((string)$a['patient_user_id']) ?></td>
              <td><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($a['patient_name']) ?></td>
              <td><?= htmlspecialchars($a['date']) ?></td>
              <td><?= htmlspecialchars($a['time']) ?></td>
              <td><span class="badge <?= $badgeClass ?>"><?= ucfirst($a['status']) ?></span></td>
              <td>
                <!-- ✅ Mark as Done button -->
                <?php if ($a['status'] !== 'completed'): ?>
                  <form method="POST" class="d-inline">
                    <button 
                      name="mark_done"
                      value="<?= (int)$a['id'] ?>"
                      class="btn btn-sm btn-success"
                      onclick="return confirm('Mark appointment #<?= (int)$a['id'] ?> as completed?');">
                      <i class="bi bi-check2-circle me-1"></i>Mark as Done
                    </button>
                  </form>
                <?php else: ?>
                  <span class="badge bg-secondary"><i class="bi bi-check2 me-1"></i>Completed</span>
                <?php endif; ?>

                <?php
                  // ✅ Check if a medical report exists for this appointment
                  $chk = $conn->prepare("SELECT 1 FROM medical_reports WHERE appointment_id = ?");
                  $chk->bind_param("i", $a['id']);
                  $chk->execute();
                  $hasReport = $chk->get_result()->num_rows > 0;
                  $chk->close();
                ?>

                <!-- ✅ Show "Write Report" or "Edit Report" -->
                <?php if ($hasReport): ?>
                  <span class="badge bg-info text-dark ms-2">Report Written</span>
                  <a href="edit_report.php?appointment_id=<?= (int)$a['id'] ?>"
                     class="btn btn-sm btn-warning ms-2">
                    <i class="bi bi-pencil-square me-1"></i>Edit Report
                  </a>
                <?php else: ?>
                  <a href="write_report.php?appointment_id=<?= (int)$a['id'] ?>"
                     class="btn btn-sm btn-outline-primary ms-2">
                    <i class="bi bi-file-earmark-plus me-1"></i>Write Report
                  </a>
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
