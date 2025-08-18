<?php
session_start();
require_once '../config.php';

// ✅ Security: Only allow logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access.");
}

$admin_id = (int)$_SESSION['user_id'];

// ✅ Fetch admin's display name
// Prefer `name` if set, otherwise fallback to `username`, otherwise "Admin"
$name = 'Admin';
if ($stmt = $conn->prepare("SELECT COALESCE(NULLIF(name,''), username, 'Admin') FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->bind_result($fetchedName);
    if ($stmt->fetch()) $name = $fetchedName;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard | MediVerse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- ✅ Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* ✅ Custom styles for dashboard look & feel */
    :root{ --bg:#ffffff; --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; }
    body{ background:#ffffff; color:var(--ink); }
    .page-wrap{ max-width:1100px; }

    /* Chip badge style */
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      background:#eef2ff; color:#3730a3; border-radius:999px;
      padding:.35rem .7rem; font-weight:700; font-size:.9rem;
    }

    /* Dashboard grid */
    .tiles{ 
      display:grid; 
      grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); 
      gap:16px; 
    }

    /* Individual tile */
    .tile{
      display:flex; align-items:center; gap:12px;
      border:1px solid var(--line); border-radius:12px; padding:18px 16px;
      background:#fff; text-decoration:none; color:inherit;
      transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
    }
    .tile:hover{ 
      box-shadow:0 12px 28px rgba(2,6,23,.08); 
      transform: translateY(-2px); 
      border-color:#dbe3ef; 
    }
    .tile i{ font-size:22px; }
    .tile .meta{ color:var(--muted); font-size:.9rem }
  </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap my-4">
  <!-- ✅ Page header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-1">Welcome, <?= htmlspecialchars($name) ?> 🧑‍💼</h3>
      <span class="chip"><i class="bi bi-shield-lock me-1"></i>Admin Dashboard</span>
    </div>
  </div>

  <!-- ✅ Quick access tiles -->
  <div class="tiles">
    <!-- Manage Users -->
    <a class="tile" href="users.php">
      <i class="bi bi-people text-primary"></i>
      <div>
        <div class="fw-semibold">Manage Users</div>
        <div class="meta">Block / delete / restore accounts</div>
      </div>
    </a>

    <!-- Manage Specializations -->
    <a class="tile" href="specializations.php">
      <i class="bi bi-magic text-warning"></i>
      <div>
        <div class="fw-semibold">Manage Specializations</div>
        <div class="meta">Create or remove doctor specialties</div>
      </div>
    </a>

    <!-- Medicines catalog -->
    <a class="tile" href="add_medicine.php">
      <i class="bi bi-capsule text-success"></i>
      <div>
        <div class="fw-semibold">Add Medicines</div>
        <div class="meta">Maintain the medicines catalog</div>
      </div>
    </a>

    <!-- Charts & Stats -->
    <a class="tile" href="charts.php">
      <i class="bi bi-graph-up-arrow text-info"></i>
      <div>
        <div class="fw-semibold">Charts & Stats</div>
        <div class="meta">Users, demand & ratings analytics</div>
      </div>
    </a>

    <!-- Ratings -->
    <a class="tile" href="ratings.php">
      <i class="bi bi-star-half text-dark"></i>
      <div>
        <div class="fw-semibold">All Ratings</div>
        <div class="meta">Review doctor feedback</div>
      </div>
    </a>

    <!-- Contact messages -->
    <a class="tile" href="contact_messages.php">
      <i class="bi bi-inbox text-secondary"></i>
      <div>
        <div class="fw-semibold">Contact Messages</div>
        <div class="meta">Reply to incoming messages</div>
      </div>
    </a>

    <!-- Doctor applications -->
    <a class="tile" href="doctor_applications.php">
      <i class="bi bi-file-earmark-text text-danger"></i>
      <div>
        <div class="fw-semibold">Doctor Applications</div>
        <div class="meta">Approve or reject applicants</div>
      </div>
    </a>
  </div>
</div>
</body>
</html>
