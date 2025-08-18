<?php
session_start();
require_once '../config.php';

// --- Access control: only admin can view this page ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

/* ---------- Helper functions ---------- */

// Format a license number so that it includes the specialization prefix
// Example: if specialization_id=12 and license=34567 -> "12-34567"
function format_license_with_spec_prefix(?string $raw, $specId): string {
    $raw = trim((string)$raw);
    $specId = (int)$specId;

    // If license looks like a plain number (5–6 digits), prepend spec_id
    if (preg_match('/\b(\d{5,6})\b$/', $raw, $m)) {
        return $specId . '-' . $m[1];
    }
    // If license already has a prefix (e.g. "7-34567"), replace prefix with spec_id
    if (preg_match('/^\d+-(\d{5,6})$/', $raw, $m)) {
        return $specId . '-' . $m[1];
    }
    return $raw; // Otherwise return raw input
}

// Validate a date string in format YYYY-MM-DD
function valid_date($s){ return ($s && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s)) ? $s : ''; }

/* ---------- Read filters from GET parameters ---------- */
$q        = trim($_GET['q'] ?? '');                  // search string (name/email/phone/license)
$spec_id  = ctype_digit($_GET['spec_id'] ?? '') ? (int)$_GET['spec_id'] : 0;
$status   = trim($_GET['status'] ?? '');             // pending/approved/rejected
$gender   = trim($_GET['gender'] ?? '');             // male/female/other
$from     = valid_date($_GET['from'] ?? '');         // start date
$to       = valid_date($_GET['to'] ?? '');           // end date

/* ---------- Fetch specializations for filter dropdown ---------- */
$specs = $conn->query("SELECT id, name FROM specializations ORDER BY name");
if ($specs === false) die("SQL error (specs): ".htmlspecialchars($conn->error));

/* ---------- Build SQL query with filters ---------- */
$sql = "
  SELECT
    da.id, da.name, da.email, da.phone, da.location, da.gender, da.date_of_birth,
    da.license_number, da.specialization_id, COALESCE(s.name,'—') AS spec_name,
    da.message, da.cv_path, da.status, da.created_at
  FROM doctor_applications da
  LEFT JOIN specializations s ON s.id = da.specialization_id
  WHERE 1=1
";

$types = '';
$params = [];

// Search query: check multiple fields
if ($q !== '') {
    $sql   .= " AND (da.name LIKE ? OR da.email LIKE ? OR da.phone LIKE ? OR da.license_number LIKE ?) ";
    $like   = "%{$q}%";
    $types .= "ssss";
    array_push($params, $like, $like, $like, $like);
}

// Specialization filter
if ($spec_id > 0) {
    $sql   .= " AND da.specialization_id = ? ";
    $types .= "i";
    $params[] = $spec_id;
}

// Status filter
if ($status !== '' && in_array($status, ['pending','approved','rejected'], true)) {
    $sql   .= " AND da.status = ? ";
    $types .= "s";
    $params[] = $status;
}

// Gender filter
if ($gender !== '' && in_array($gender, ['male','female','other'], true)) {
    $sql   .= " AND da.gender = ? ";
    $types .= "s";
    $params[] = $gender;
}

// Date filters
if ($from) {
    $sql   .= " AND DATE(da.created_at) >= ? ";
    $types .= "s";
    $params[] = $from;
}
if ($to) {
    $sql   .= " AND DATE(da.created_at) <= ? ";
    $types .= "s";
    $params[] = $to;
}

$sql .= " ORDER BY da.created_at DESC";

// Prepare statement safely
$stmt = $conn->prepare($sql);
if (!$stmt) die("SQL error (prepare): ".htmlspecialchars($conn->error));

// Bind parameters if any
if ($types !== '') {
    $bind = [];
    $bind[] = & $types;
    foreach ($params as $k => $v) { $bind[] = & $params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

$stmt->execute();
$res = $stmt->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Doctor Applications | MediVerse Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Simple custom styles -->
  <style>
    :root{ --bg:#ffffff; --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; }
    body{ background:#ffffff; color:var(--ink); }
    .page-wrap{ max-width: 1200px; }
    .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); border:1px solid #e9ecef; }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      background:#eef2ff; color:#3730a3; border-radius:999px;
      padding:.35rem .7rem; font-weight:700; font-size:.9rem;
    }
    .filters .form-label{ font-weight:600; color:#334155 }
    .table thead th{ background:#f8fafc; }
    .count-badge{ background:#e0e7ff; color:#3730a3; font-weight:700; }
    .msg-cell{ max-width:420px; white-space:pre-wrap }
  </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap my-4">
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
      <h3 class="mb-0">📑 Doctor Applications</h3>
      <span class="chip"><i class="bi bi-clipboard2-pulse me-1"></i>MediVerse</span>
    </div>
    <!-- Display count of results -->
    <span class="badge count-badge"><?= count($rows) ?> result<?= count($rows)===1?'':'s' ?></span>
  </div>

  <!-- Filters card -->
  <div class="card card-shadow mb-3">
    <div class="card-header bg-white">
      <strong><i class="bi bi-funnel me-1"></i>Filters</strong>
    </div>
    <div class="card-body">
      <form method="get" class="row g-3 filters">
        <!-- Search box -->
        <div class="col-md-4">
          <label class="form-label">Search (name/email/phone/license)</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="e.g. John, 05..., 12-34567">
          </div>
        </div>

        <!-- Dropdown: specialization -->
        <div class="col-md-3">
          <label class="form-label">Specialization</label>
          <select name="spec_id" class="form-select">
            <option value="0">All</option>
            <?php while ($s = $specs->fetch_assoc()): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $spec_id===(int)$s['id']?'selected':'' ?>>
                <?= htmlspecialchars($s['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Dropdown: status -->
        <div class="col-md-2">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">All</option>
            <option value="pending"  <?= $status==='pending'?'selected':'' ?>>Pending</option>
            <option value="approved" <?= $status==='approved'?'selected':'' ?>>Approved</option>
            <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Rejected</option>
          </select>
        </div>

        <!-- Dropdown: gender -->
        <div class="col-md-3">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select">
            <option value="">All</option>
            <option value="male"   <?= $gender==='male'?'selected':'' ?>>Male</option>
            <option value="female" <?= $gender==='female'?'selected':'' ?>>Female</option>
            <option value="other"  <?= $gender==='other'?'selected':'' ?>>Other</option>
          </select>
        </div>

        <!-- Date filters -->
        <div class="col-md-2">
          <label class="form-label">From</label>
          <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">To</label>
          <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
        </div>

        <!-- Buttons -->
        <div class="col-md-3 d-grid align-self-end">
          <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Apply</button>
        </div>
        <div class="col-md-3 d-grid align-self-end">
          <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- Results table -->
  <div class="card card-shadow">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th style="min-width:180px;">Applicant</th>
            <th style="min-width:160px;">Contact</th>
            <th>Location</th>
            <th style="width:120px;">Gender</th>
            <th style="min-width:130px;">DOB</th>
            <th style="min-width:150px;">License #</th>
            <th style="min-width:160px;">Specialization</th>
            <th style="min-width:140px;">Applied On</th>
            <th style="min-width:120px;">Status</th>
            <th style="min-width:220px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <!-- No results -->
            <tr><td colspan="11" class="text-center text-muted py-4"><i class="bi bi-inbox fs-4 d-block mb-2"></i>No applications found.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $app): ?>
              <?php $license_display = format_license_with_spec_prefix($app['license_number'], $app['specialization_id']); ?>
              <tr>
                <td class="text-muted">#<?= (int)$app['id'] ?></td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($app['name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($app['email']) ?></div>
                </td>
                <td>
                  <div><?= htmlspecialchars($app['phone']) ?></div>
                </td>
                <td><?= htmlspecialchars($app['location']) ?></td>
                <td><?= htmlspecialchars(ucfirst($app['gender'])) ?></td>
                <td><?= htmlspecialchars($app['date_of_birth']) ?></td>
                <td><?= htmlspecialchars($license_display) ?></td>
                <td><?= htmlspecialchars($app['spec_name']) ?></td>
                <td><?= htmlspecialchars(date('Y-m-d', strtotime($app['created_at']))) ?></td>
                <td>
                  <!-- Status badge with color -->
                  <span class="badge bg-<?= $app['status']=='pending'?'secondary':($app['status']=='approved'?'success':'danger') ?>">
                    <?= htmlspecialchars(ucfirst($app['status'])) ?>
                  </span>
                </td>
                <td class="d-flex gap-2 flex-wrap">
                  <!-- CV link -->
                  <?php if (!empty($app['cv_path'])): ?>
                    <a href="../<?= htmlspecialchars($app['cv_path']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                      <i class="bi bi-file-earmark-text me-1"></i>CV
                    </a>
                  <?php endif; ?>

                  <!-- Approve / Reject buttons if pending -->
                  <?php if ($app['status'] === 'pending'): ?>
                    <form method="POST" action="../process_application.php" class="m-0 d-inline">
                      <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                      <button name="action" value="approve" class="btn btn-sm btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Approve
                      </button>
                      <button name="action" value="reject" class="btn btn-sm btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Reject
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted small">No actions</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
