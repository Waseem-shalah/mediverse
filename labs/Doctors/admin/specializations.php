<?php
session_start();
require_once '../config.php';

// ✅ Security check: Only allow logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

// ✅ Handle adding or deleting specializations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adding a new specialization
    if (isset($_POST['new_spec'])) {
        $name = trim($_POST['new_spec']);
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO specializations (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
        }
        header("Location: specializations.php"); // redirect to refresh list
        exit;
    }

    // Deleting a specialization
    if (isset($_POST['delete_spec_id'])) {
        $sid = (int)$_POST['delete_spec_id'];
        $stmt = $conn->prepare("DELETE FROM specializations WHERE id = ?");
        $stmt->bind_param("i", $sid);
        $stmt->execute();
        header("Location: specializations.php"); // redirect to refresh list
        exit;
    }
}

// ✅ Fetch all current specializations
$specsQ = $conn->query("SELECT id, name FROM specializations ORDER BY name");
$specs  = [];
while ($row = $specsQ->fetch_assoc()) { 
    $specs[] = $row; 
}
$count  = count($specs); // total specializations
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Specializations | MediVerse Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- ✅ Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* ✅ Custom styling for cards, chips, avatars, etc. */
    :root{ --bg:#ffffff; --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; }
    body{ background:#ffffff !important; color:var(--ink); }

    .page-wrap{ max-width: 1100px; }
    .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); border:1px solid #e9ecef; }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      background:#eef2ff; color:#3730a3; border-radius:999px;
      padding:.35rem .7rem; font-weight:700; font-size:.9rem;
    }
    .count-badge{ background:#e0e7ff; color:#3730a3; font-weight:700; }
    .filters .form-label{ font-weight:600; color:#334155 }
    .list-group-item{ display:flex; align-items:center; justify-content:space-between; }
    .spec-left{ display:flex; align-items:center; gap:.75rem; }
    .avatar{
      width:36px; height:36px; border-radius:50%;
      display:inline-flex; align-items:center; justify-content:center;
      background:#eef2ff; color:#3730a3; font-weight:800;
    }
    .muted{ color:var(--muted); }
  </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap my-4">
  <!-- ✅ Page Header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
      <h3 class="mb-0">🩺 Doctor Specializations</h3>
      <span class="chip"><i class="bi bi-clipboard2-pulse me-1"></i>MediVerse</span>
    </div>
  </div>

  <div class="row g-4">
    <!-- ✅ Left: All Specializations List -->
    <div class="col-lg-7">
      <div class="card card-shadow">
        <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-list-ul me-1"></i> <strong>All Specializations</strong>
          </div>
          <span class="badge count-badge"><?= (int)$count ?> total</span>
        </div>
        <div class="card-body">
          <!-- ✅ Filter/Search Box -->
          <div class="filters mb-3">
            <label class="form-label" for="filterInput">Search</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input id="filterInput" type="text" class="form-control" placeholder="Type to filter specializations…">
            </div>
            <small class="muted">Filtering is client-side; it does not change the database.</small>
          </div>

          <!-- ✅ Display Specializations -->
          <?php if ($count === 0): ?>
            <div class="text-center py-4 muted">
              <i class="bi bi-clipboard-x fs-3 d-block mb-2"></i>
              No specializations yet.
            </div>
          <?php else: ?>
            <ul class="list-group" id="specList">
              <?php foreach ($specs as $s): ?>
              <?php $initial = mb_strtoupper(mb_substr($s['name'],0,1)); ?>
              <li class="list-group-item" data-name="<?= htmlspecialchars(mb_strtolower($s['name'])) ?>">
                <!-- Left side: Initial + Name -->
                <div class="spec-left">
                  <div class="avatar"><?= htmlspecialchars($initial) ?></div>
                  <div class="fw-semibold"><?= htmlspecialchars($s['name']) ?></div>
                </div>
                <!-- Right side: Delete button -->
                <form method="POST" class="m-0" onsubmit="return confirm('Delete specialization &quot;<?= htmlspecialchars($s['name']) ?>&quot;?');">
                  <input type="hidden" name="delete_spec_id" value="<?= (int)$s['id'] ?>">
                  <button class="btn btn-sm btn-danger">
                    <i class="bi bi-trash me-1"></i>Delete
                  </button>
                </form>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ✅ Right: Add New Specialization Form -->
    <div class="col-lg-5">
      <div class="card card-shadow">
        <div class="card-header bg-white">
          <strong><i class="bi bi-plus-circle me-1"></i>Add New Specialization</strong>
        </div>
        <div class="card-body">
          <form method="POST" novalidate>
            <div class="mb-3">
              <label for="new_spec" class="form-label">Specialization Name</label>
              <input type="text" class="form-control" id="new_spec" name="new_spec" required placeholder="e.g. Cardiology">
              <div class="form-text">Add a distinct, descriptive name.</div>
            </div>
            <button class="btn btn-success w-100">
              <i class="bi bi-check2-circle me-1"></i>Add
            </button>
          </form>
        </div>
      </div>
      <div class="text-muted small mt-2">
        Tip: Deleting a specialization here removes it from the master list. Make sure no doctors rely on it.
      </div>
    </div>
  </div>
</div>

<script>
  // ✅ Simple client-side search filter
  const input = document.getElementById('filterInput');
  const list  = document.getElementById('specList');
  if (input && list) {
    input.addEventListener('input', () => {
      const q = (input.value || '').toLowerCase().trim();
      list.querySelectorAll('li.list-group-item').forEach(li => {
        const name = li.getAttribute('data-name') || '';
        li.style.display = (!q || name.includes(q)) ? '' : 'none';
      });
    });
  }
</script>
</body>
</html>
