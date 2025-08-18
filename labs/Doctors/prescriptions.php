<?php
// My Prescriptions (patient view) — shows issued/used prescriptions with filters and one-time “open prescription” flow

session_start();
require_once 'config.php';
require 'navbar_loggedin.php';

// -------- AuthN/AuthZ: only logged-in patients --------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}
$patient_id = (int)$_SESSION['user_id'];

// -------- CSRF token for one-time open action --------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_token'];

// -------- Flash messages (one-shot) --------
$flash_ok  = $_SESSION['flash_ok']  ?? '';
$flash_err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

// -------- Read filter inputs (GET) --------
$qMedicine = trim($_GET['q'] ?? '');
$qDoctor   = trim($_GET['doctor'] ?? '');
$qForm     = trim($_GET['form'] ?? '');
$qDiag     = trim($_GET['diag'] ?? '');
$qRx       = trim($_GET['rx'] ?? '');
$qFrom     = trim($_GET['from'] ?? '');
$qTo       = trim($_GET['to'] ?? '');
$qStatus   = trim($_GET['status'] ?? ''); // ISSUED | USED

// -------- Build dosage form dropdown from this patient’s history --------
$forms = [];
$optSql = "
    SELECT DISTINCT NULLIF(TRIM(COALESCE(m.dosage_form,'')), '') AS form
    FROM prescribed_medicines pm
    JOIN medical_reports mr ON pm.report_id = mr.id
    JOIN medicines m        ON pm.medicine_id = m.id
    WHERE pm.patient_id = ?
    ORDER BY form
";
$optStmt = $conn->prepare($optSql);
$optStmt->bind_param("i", $patient_id);
$optStmt->execute();
$optRes = $optStmt->get_result();
while ($r = $optRes->fetch_assoc()) {
    if (!empty($r['form'])) $forms[] = $r['form'];
}
$optStmt->close();

// -------- Main query with dynamic filters (prepared) --------
$sql = "
    SELECT 
        pm.id                       AS pm_id,
        m.name                      AS medicine_name,
        COALESCE(m.dosage_form,'')  AS dosage_form,
        COALESCE(m.strength,'')     AS strength,
        m.is_prescription_required,
        pm.pills_per_day,
        pm.duration_days,
        pm.used_status,
        pm.used_at,
        mr.diagnosis,
        mr.created_at,
        d.name                      AS doctor_name
    FROM prescribed_medicines pm
    JOIN medical_reports mr ON pm.report_id = mr.id
    JOIN users d            ON mr.doctor_id = d.id
    JOIN medicines m        ON pm.medicine_id = m.id
    WHERE pm.patient_id = ?
";
$types  = "i";
$params = [$patient_id];

// Optional filters (LIKE/equals/date ranges)
if ($qMedicine !== '') {
    $sql    .= " AND (m.name LIKE ? OR m.strength LIKE ?) ";
    $types  .= "ss";
    $like    = "%{$qMedicine}%";
    $params[] = $like;
    $params[] = $like;
}
if ($qDoctor !== '') {
    $sql    .= " AND d.name LIKE ? ";
    $types  .= "s";
    $params[] = "%{$qDoctor}%";
}
if ($qForm !== '') {
    $sql    .= " AND COALESCE(m.dosage_form,'') = ? ";
    $types  .= "s";
    $params[] = $qForm;
}
if ($qDiag !== '') {
    $sql    .= " AND mr.diagnosis LIKE ? ";
    $types  .= "s";
    $params[] = "%{$qDiag}%";
}
if ($qRx === 'rx') {
    $sql .= " AND m.is_prescription_required = 1 ";
} elseif ($qRx === 'otc') {
    $sql .= " AND m.is_prescription_required = 0 ";
}
if ($qStatus !== '' && in_array($qStatus, ['ISSUED','USED'], true)) {
    $sql   .= " AND pm.used_status = ? ";
    $types .= "s";
    $params[] = $qStatus;
}

// Date range (YYYY-MM-DD)
$validDate = fn($v) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
if ($qFrom !== '' && $validDate($qFrom)) {
    $sql    .= " AND DATE(mr.created_at) >= ? ";
    $types  .= "s";
    $params[] = $qFrom;
}
if ($qTo !== '' && $validDate($qTo)) {
    $sql    .= " AND DATE(mr.created_at) <= ? ";
    $types  .= "s";
    $params[] = $qTo;
}

$sql .= " ORDER BY mr.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) { die("Prepare failed: " . $conn->error); }

// Bind variable number of params
$bind = [];
$bind[] = & $types;
foreach ($params as $k => $v) { $bind[] = & $params[$k]; }
call_user_func_array([$stmt, 'bind_param'], $bind);

$stmt->execute();
$result = $stmt->get_result();
$count  = $result ? $result->num_rows : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Prescriptions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
/* Stretch table; keep cells single-line for compactness */
.table { table-layout: auto; width: 100%; }
.table th, .table td { white-space: nowrap; }

body { background:#f6f9fc; }
.page-wrap { max-width: 1100px; }
.card { border: 1px solid #e9ecef; }
.filters .form-label { font-weight: 600; color: #334155; }
.table thead th { background:#f8fafc; }
.empty { color:#64748b; padding:48px 16px; }
.badge-otc { background:#16a34a; }
.badge-rx  { background:#0d6efd; }
.badge-status { font-weight:600; }
.status-issued { background:#fef9c3; color:#854d0e; }
.status-used   { background:#dcfce7; color:#166534; }
    </style>
</head>
<body>
<div class="container page-wrap my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="m-0">💊 My Prescriptions</h2>
        <span class="badge bg-primary-subtle text-primary fs-6">
            <i class="bi bi-capsule me-1"></i><?= (int)$count ?> result<?= $count===1?'':'s' ?>
        </span>
    </div>

    <?php if ($flash_ok): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash_ok) ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flash_err) ?></div>
    <?php endif; ?>

    <!-- Filters panel -->
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center">
            <i class="bi bi-funnel me-2"></i> Filters
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 filters">
                <div class="col-md-4">
                    <label class="form-label">Medicine / Strength</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-capsule"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="e.g. Amoxicillin 500mg"
                               value="<?= htmlspecialchars($qMedicine) ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Doctor</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="doctor" class="form-control" placeholder="Doctor name"
                               value="<?= htmlspecialchars($qDoctor) ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Diagnosis contains</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-heart-pulse"></i></span>
                        <input type="text" name="diag" class="form-control" placeholder="e.g. sinusitis"
                               value="<?= htmlspecialchars($qDiag) ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Dosage form</label>
                    <select name="form" class="form-select">
                        <option value="">Any</option>
                        <?php foreach ($forms as $f): ?>
                            <option value="<?= htmlspecialchars($f) ?>" <?= $qForm===$f?'selected':'' ?>>
                                <?= htmlspecialchars($f) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="rx" class="form-select">
                        <option value="">All</option>
                        <option value="rx"  <?= $qRx==='rx' ? 'selected' : '' ?>>Prescription (Rx)</option>
                        <option value="otc" <?= $qRx==='otc'? 'selected' : '' ?>>Over the Counter (OTC)</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($qFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($qTo) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="ISSUED"  <?= $qStatus==='ISSUED' ? 'selected' : '' ?>>Issued</option>
                        <option value="USED"    <?= $qStatus==='USED'   ? 'selected' : '' ?>>Used</option>
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

    <?php if ($count === 0): ?>
        <!-- Empty state -->
        <div class="card">
            <div class="card-body text-center empty">
                <i class="bi bi-clipboard-x fs-2 mb-2 d-block"></i>
                No prescriptions match your filters.
            </div>
        </div>
    <?php else: ?>
        <!-- Results -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Form</th>
                            <th>Strength</th>
                            <th>Prescribed By</th>
                            <th>Diagnosis</th>
                            <th>Times a Day</th>
                            <th>Duration</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th style="min-width:280px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()):
                            // Per-row derived values (status badges, search query, etc.)
                            $name     = $row['medicine_name'] ?? '';
                            $form     = $row['dosage_form'] ?? '';
                            $strength = $row['strength'] ?? '';
                            $queryStr = trim($name . ' ' . $strength . ' ' . $form);
                            $isRx     = (int)$row['is_prescription_required'] === 1;

                            $status   = $row['used_status'] ?? 'ISSUED';
                            $usedAt   = $row['used_at'] ? date("Y-m-d H:i", strtotime($row['used_at'])) : null;

                            $badgeClass = 'status-issued';
                            $badgeText  = 'Issued';
                            if ($status === 'USED') { $badgeClass = 'status-used'; $badgeText = 'Used'; }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($name) ?></td>
                            <td><?= htmlspecialchars($form !== '' ? $form : '—') ?></td>
                            <td><?= htmlspecialchars($strength !== '' ? $strength : '—') ?></td>
                            <td><?= htmlspecialchars($row['doctor_name']) ?></td>
                            <td><?= htmlspecialchars($row['diagnosis']) ?></td>
                            <td><?= (int)$row['pills_per_day'] ?></td>
                            <td><?= (int)$row['duration_days'] ?> days</td>
                            <td><?= htmlspecialchars(date("Y-m-d", strtotime($row['created_at']))) ?></td>
                            <td>
                                <?php if ($isRx): ?>
                                    <span class="badge badge-rx">Rx</span>
                                <?php else: ?>
                                    <span class="badge badge-otc">OTC</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-status <?= $badgeClass ?>"><?= $badgeText ?></span>
                                <?php if ($status === 'USED' && $usedAt): ?>
                                    <div class="small text-muted">at <?= htmlspecialchars($usedAt) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- One-time printable prescription (marks USED) -->
                                <?php if ($isRx): ?>
                                    <?php if ($status === 'ISSUED'): ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary me-1 rx-open-btn"
                                                data-pm-id="<?= (int)$row['pm_id'] ?>">
                                            <i class="bi bi-file-earmark-plus"></i> Prescription (one-time)
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary me-1" disabled>
                                            <i class="bi bi-file-earmark-plus"></i> Prescription
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Convenience search (external) -->
                                <a class="btn btn-sm btn-outline-info"
                                   target="_blank"
                                   href="https://www.google.com/search?q=buy+<?= urlencode($queryStr) ?>">
                                    <i class="bi bi-search"></i> Search
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Confirmation modal: submits to script that marks USED and opens printable in new tab -->
<div class="modal fade" id="rxOpenModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="prescription_use_and_open.php" class="modal-content" target="_blank">
      <div class="modal-header">
        <h5 class="modal-title text-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>One-time access confirmation
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">
          If you continue, this prescription will be marked as <strong>USED</strong>.
          <u>You won’t be able to open it again</u>. Proceed?
        </p>
        <input type="hidden" name="pm_id" id="rxPmId">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
        <div class="form-text">A new tab will open with your printable prescription.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Yes, open & mark USED</button>
      </div>
    </form>
  </div>
</div>

<!-- Minimal JS: open modal and inject pm_id -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.rx-open-btn').forEach(btn => {
    btn.addEventListener('click', function(){
        const pmId = this.getAttribute('data-pm-id');
        document.getElementById('rxPmId').value = pmId;
        const modal = new bootstrap.Modal(document.getElementById('rxOpenModal'));
        modal.show();
    });
});
</script>
</body>
</html>
