<?php
session_start();
require_once 'config.php';
include 'navbar_loggedin.php';

// ✅ Only logged-in patients can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];

/* ---------- 1. Handle filter inputs from GET ---------- */
$doctorLike = trim($_GET['doctor'] ?? ''); // filter by doctor name
$specKey    = $_GET['spec'] ?? '';         // specialization filter (empty = all)
$dateEq     = trim($_GET['date'] ?? '');   // exact date filter (YYYY-MM-DD)

/* ---------- 2. Build specialization filter options ---------- */
// Only show specializations that this patient actually has reports for
$specOptions = [];
$optSql = "
  SELECT u.specialization_id AS id,
         COALESCE(sp.name, 'Unspecified') AS name
  FROM medical_reports mr
  JOIN users u              ON u.id = mr.doctor_id
  LEFT JOIN specializations sp ON sp.id = u.specialization_id
  WHERE mr.patient_id = ?
  GROUP BY u.specialization_id, name
  ORDER BY name
";
$optStmt = $conn->prepare($optSql);
$optStmt->bind_param("i", $patient_id);
$optStmt->execute();
$optRes = $optStmt->get_result();

// Store specialization dropdown options
while ($r = $optRes->fetch_assoc()) {
    $id = $r['id'];
    $specOptions[] = [
        'id'   => ($id === null ? 'null' : (string)(int)$id),
        'name' => $r['name'],
    ];
}
$optStmt->close();

/* ---------- 3. Main query with optional filters ---------- */
$sql = "
    SELECT mr.id,
           mr.diagnosis,
           mr.created_at,
           u.name AS doctor_name,
           COALESCE(sp.name, 'Unspecified') AS specialization_name
    FROM medical_reports mr
    JOIN users u              ON u.id = mr.doctor_id
    LEFT JOIN specializations sp ON sp.id = u.specialization_id
    WHERE mr.patient_id = ?
";
$types  = "i";              // first param is always patient_id
$params = [$patient_id];

// Apply doctor name filter if filled
if ($doctorLike !== '') {
    $sql    .= " AND u.name LIKE ? ";
    $types  .= "s";
    $params[] = "%{$doctorLike}%";
}

// Apply specialization filter if selected
if ($specKey !== '') {
    if ($specKey === 'null') {
        $sql .= " AND u.specialization_id IS NULL ";
    } elseif (ctype_digit($specKey) && (int)$specKey > 0) {
        $sql    .= " AND u.specialization_id = ? ";
        $types  .= "i";
        $params[] = (int)$specKey;
    }
}

// Apply date filter if a valid date was entered
if ($dateEq !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEq)) {
    $sql    .= " AND DATE(mr.created_at) = ? ";
    $types  .= "s";
    $params[] = $dateEq;
}

$sql .= " ORDER BY mr.created_at DESC"; // newest reports first

// Prepare final query
$query = $conn->prepare($sql);
if (!$query) {
    die("Query error: " . $conn->error);
}

// Bind params dynamically
$bind = [];
$bind[] = & $types;
foreach ($params as $k => $v) { $bind[] = & $params[$k]; }
call_user_func_array([$query, 'bind_param'], $bind);

$query->execute();
$result = $query->get_result();
$count  = $result ? $result->num_rows : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>My Medical Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background:#f6f9fc; }
        .page-wrap { max-width: 1100px; }
        .card { border:1px solid #e9ecef; }
        .filters .form-label { font-weight:600; color:#334155; }
        .table thead th { background:#f8fafc; }
        .empty { color:#64748b; padding:48px 16px; }
        .specialization-badge { background:#eef2ff; color:#3730a3; }
        .btn-icon { display:inline-flex; align-items:center; gap:.35rem; }
    </style>
</head>
<body>
<div class="container page-wrap my-4">

    <!-- Page header with results count -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="m-0">📄 My Medical Reports</h2>
        <span class="badge bg-primary-subtle text-primary fs-6">
            <i class="bi bi-file-earmark-text me-1"></i><?= (int)$count ?> result<?= $count===1?'':'s' ?>
        </span>
    </div>

    <!-- Filter section -->
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center">
            <i class="bi bi-funnel me-2"></i> Filters
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 filters">
                <!-- Doctor name filter -->
                <div class="col-md-4">
                    <label class="form-label">Doctor name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="doctor" class="form-control" placeholder="e.g. Ahmed"
                               value="<?= htmlspecialchars($doctorLike) ?>">
                    </div>
                </div>

                <!-- Specialization filter -->
                <div class="col-md-4">
                    <label class="form-label">Specialization</label>
                    <select name="spec" class="form-select">
                        <option value="">All specializations</option>
                        <?php foreach ($specOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt['id']) ?>"
                              <?= ($specKey !== '' && $specKey === $opt['id']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($opt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date filter -->
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateEq) ?>">
                </div>

                <!-- Buttons -->
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

    <!-- Reports list -->
    <?php if ($result && $result->num_rows): ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width:120px;">Date</th>
                            <th>Diagnosis</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th style="min-width:180px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php $dateDisp = date('Y-m-d H:i', strtotime($row['created_at'])); ?>
                        <tr>
                            <td><?= htmlspecialchars($dateDisp) ?></td>
                            <td><?= htmlspecialchars($row['diagnosis']) ?></td>
                            <td><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($row['doctor_name']) ?></td>
                            <td><span class="badge specialization-badge"><?= htmlspecialchars($row['specialization_name']) ?></span></td>
                            <td>
                                <!-- View report -->
                                <a href="report_detail.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon me-1">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <!-- Download as PDF -->
                                <form action="download_report.php" method="post" class="d-inline">
                                    <input type="hidden" name="report_id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success btn-icon">
                                        <i class="bi bi-download"></i> PDF
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <!-- If no results -->
        <div class="card">
            <div class="card-body text-center empty">
                <i class="bi bi-clipboard-x fs-2 mb-2 d-block"></i>
                No reports found for the selected filters.
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
