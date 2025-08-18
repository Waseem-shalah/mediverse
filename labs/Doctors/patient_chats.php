<?php
// Patient-only view to start chats with available doctors.
// Includes simple filters (by specialization and name) and lists doctors as submit buttons.

session_start();
require_once 'config.php';
require 'navbar_loggedin.php';

// ---- Access control: patients only ----
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];

// ==============================
// Filters (from GET)
//   - spec_id: '0' = all, 'null' = General (NULL), digits = specialization id
//   - q: doctor name contains
// ==============================
$spec_key = isset($_GET['spec_id']) ? trim($_GET['spec_id']) : '0';
$q        = trim($_GET['q'] ?? '');

// ==============================
// Build specialization dropdown from ACTIVE doctors only
// (avoids showing empty/disabled categories)
// ==============================
$specs = [];
$specSql = "
    SELECT DISTINCT
        u.specialization_id AS id,
        COALESCE(s.name,'General') AS name
    FROM users u
    LEFT JOIN specializations s ON s.id = u.specialization_id
    WHERE u.role = 'doctor'
      AND COALESCE(u.is_active,1)    = 1
      AND COALESCE(u.user_deleted,0) = 0
      AND COALESCE(u.is_blocked,0)   = 0
    ORDER BY name
";
if ($res = $conn->query($specSql)) {
    while ($row = $res->fetch_assoc()) {
        $specs[] = ['id' => $row['id'], 'name' => (string)$row['name']];
    }
}

// ==============================
// Fetch doctors with filters applied
// ==============================
$sql = "
    SELECT u.id, u.name, COALESCE(s.name,'General') AS spec_name
    FROM users u
    LEFT JOIN specializations s ON s.id = u.specialization_id
    WHERE u.role = 'doctor'
      AND COALESCE(u.is_active,1)    = 1
      AND COALESCE(u.user_deleted,0) = 0
      AND COALESCE(u.is_blocked,0)   = 0
";
$types  = '';
$params = [];

// Filter by specialization key
if ($spec_key !== '0' && $spec_key !== '') {
    if ($spec_key === 'null') {
        $sql .= " AND u.specialization_id IS NULL ";
    } elseif (ctype_digit($spec_key) && (int)$spec_key > 0) {
        $sql    .= " AND u.specialization_id = ? ";
        $types  .= "i";
        $params[] = (int)$spec_key;
    }
}

// Filter by doctor name (LIKE)
if ($q !== '') {
    $sql    .= " AND u.name LIKE ? ";
    $types  .= "s";
    $like    = "%{$q}%";
    $params[] = $like;
}

$sql .= " ORDER BY u.name";

// Prepare & bind (variadic) to avoid SQL injection
$stmt = $conn->prepare($sql);
if (!$stmt) { die("Prepare failed: " . $conn->error); }

if ($types !== '') {
    $bind = [];
    $bind[] = & $types;
    foreach ($params as $k => $v) { $bind[] = & $params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

$stmt->execute();
$doctors = $stmt->get_result();
$count   = $doctors ? $doctors->num_rows : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chats</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { background:#f6f9fc; }
        .page-wrap { max-width: 1100px; }

        /* Streamlined look: remove big container frame; keep a clean card sidebar */
        .chat-container{
          display:block;
          border:0;
          background:transparent;
          border-radius:0;
          overflow:visible;
        }
        .sidebar{
          width:100%;
          border:1px solid #e9ecef;
          background:#fff;
          border-radius:12px;
          padding:16px;
        }

        .spec-badge { font-size:.78rem; background:#eef2ff; color:#3730a3; }
        .list-group-item { border:1px solid #e9ecef; border-radius:12px !important; margin-bottom:10px; }
        .doctor-name { font-weight:600; color:#0f172a; }
        .filters .form-label { font-weight:600; color:#334155; }
    </style>
</head>
<body>
<div class="container page-wrap my-4">

    <!-- Header with results count -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="m-0">💬 Doctor Chats</h3>
        <span class="badge text-primary bg-primary-subtle">
            <?= (int)$count ?> result<?= $count === 1 ? '' : 's' ?>
        </span>
    </div>

    <!-- Filters (specialization + name) -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-3 align-items-end filters" method="get" action="patient_chats.php">
                <div class="col-sm-6 col-md-4">
                    <label class="form-label">Filter by specialization</label>
                    <select name="spec_id" class="form-select">
                        <option value="0" <?= ($spec_key==='0' || $spec_key==='') ? 'selected' : '' ?>>All specializations</option>
                        <?php foreach ($specs as $s):
                            // 'null' represents doctors with no specialization (General)
                            $val = ($s['id'] === null) ? 'null' : (string)(int)$s['id'];
                        ?>
                          <option value="<?= htmlspecialchars($val) ?>" <?= $spec_key === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                          </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-md-4">
                    <label class="form-label">Doctor name</label>
                    <input type="text" name="q" class="form-control" placeholder="e.g. Ahmed"
                           value="<?= htmlspecialchars($q) ?>">
                </div>

                <div class="col-sm-6 col-md-2 d-grid">
                    <button class="btn btn-primary">Apply</button>
                </div>
                <div class="col-sm-6 col-md-2 d-grid">
                    <a class="btn btn-outline-secondary" href="patient_chats.php">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Single-column list of doctors; clicking a row POSTs to start_chat.php -->
    <div class="chat-container">
        <div class="sidebar">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="m-0">Available Doctors</h6>
                <?php if ($spec_key !== '0' || $q !== ''): ?>
                    <span class="badge bg-info text-dark">Filtered</span>
                <?php endif; ?>
            </div>

            <?php if (!$doctors || $doctors->num_rows === 0): ?>
                <div class="alert alert-secondary mt-3 mb-0">No doctors found for this selection.</div>
            <?php else: ?>
                <div class="list-group">
                    <?php while ($doc = $doctors->fetch_assoc()): ?>
                        <!-- Each item is a tiny form so we can POST doctor_id safely -->
                        <form method="post" action="start_chat.php" class="mb-0">
                            <input type="hidden" name="doctor_id" value="<?= (int)$doc['id'] ?>">
                            <button type="submit" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between">
                                    <span class="doctor-name"><?= htmlspecialchars($doc['name']) ?></span>
                                </div>
                                <div class="mt-1">
                                    <span class="badge spec-badge"><?= htmlspecialchars($doc['spec_name']) ?></span>
                                </div>
                            </button>
                        </form>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
