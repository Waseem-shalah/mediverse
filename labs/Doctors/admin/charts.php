<?php
session_start();
require_once '../config.php';

// ✅ Security: only allow logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

/**
 * ✅ Small helper: run a query and stop with a clear error if it fails.
 * Returns an array of associative rows.
 */
function fetch_all_or_die(mysqli $conn, string $sql, string $label) : array {
    $res = $conn->query($sql);
    if ($res === false) {
        die("SQL error in {$label}: " . htmlspecialchars($conn->error));
    }
    return $res->fetch_all(MYSQLI_ASSOC);
}

/* -----------------------------------------------------------
   Chart 1: User Registrations (non-admins) over time
   ----------------------------------------------------------- */
$userRegs = fetch_all_or_die(
    $conn,
    "
    SELECT DATE(created_at) AS date, COUNT(*) AS cnt
    FROM users
    WHERE role <> 'admin'
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
    ",
    'user registrations'
);

/* -----------------------------------------------------------
   Chart 2: Top Specializations by Appointment Count
   (how many appointments tied to each specialization)
   ----------------------------------------------------------- */
$specDemand = fetch_all_or_die(
    $conn,
    "
    SELECT s.name, COUNT(a.id) AS cnt
    FROM appointments a
    JOIN users d            ON a.doctor_id = d.id
    JOIN specializations s  ON d.specialization_id = s.id
    GROUP BY s.id, s.name
    ORDER BY cnt DESC
    ",
    'specialization demand'
);

/* -----------------------------------------------------------
   Chart 3: Doctor Ratings (average per doctor)
   ----------------------------------------------------------- */
$ratings = fetch_all_or_die(
    $conn,
    "
    SELECT u.username, ROUND(AVG(r.rating), 2) AS avg_rating
    FROM ratings r
    JOIN users u ON r.doctor_id = u.id
    GROUP BY u.id, u.username
    ORDER BY avg_rating DESC
    ",
    'doctor ratings'
);

/* -----------------------------------------------------------
   NEW — Chart 4: Count of Doctors in Each Specialization
   (how many doctors belong to each specialization)
   ----------------------------------------------------------- */
$doctorsBySpec = fetch_all_or_die(
    $conn,
    "
    SELECT s.name, COUNT(u.id) AS cnt
    FROM users u
    JOIN specializations s ON s.id = u.specialization_id
    WHERE u.role = 'doctor'
    GROUP BY s.id, s.name
    ORDER BY cnt DESC
    ",
    'doctors per specialization'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Charts | MediVerse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- ✅ UI libs -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* ✅ Light dashboard look & feel */
    :root{ --bg:#ffffff; --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; }
    body{ background:#ffffff; color:var(--ink); }
    .page-wrap{ max-width: 1200px; }
    .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); border:1px solid #e9ecef; }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      background:#eef2ff; color:#3730a3; border-radius:999px;
      padding:.35rem .7rem; font-weight:700; font-size:.9rem;
    }
    .card h6{ margin:0; font-weight:700; color:#111827 }
    .subtle{ color:var(--muted); }
    .canvas-wrap{ height: 280px; position: relative; }
    .empty{
      display:flex; align-items:center; justify-content:center;
      height: 280px; color:#64748b;
    }
    .card-header{ background:#fff; border-bottom:1px solid var(--line); }
  </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap my-4">
  <!-- ✅ Page header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
      <h3 class="mb-0">📊 Admin Charts & Analytics</h3>
      <span class="chip"><i class="bi bi-clipboard-data me-1"></i>MediVerse</span>
    </div>
  </div>

  <!-- ✅ Row 1: three existing charts -->
  <div class="row g-4">
    <!-- Users over time -->
    <div class="col-md-4">
      <div class="card card-shadow h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6>New Users Over Time</h6>
          <span class="subtle small"><?= count($userRegs) ?> pts</span>
        </div>
        <div class="card-body">
          <?php if (count($userRegs) === 0): ?>
            <div class="empty">No registration data yet.</div>
          <?php else: ?>
            <div class="canvas-wrap"><canvas id="usersChart"></canvas></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Top specializations (by appointments) -->
    <div class="col-md-4">
      <div class="card card-shadow h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6>Top Specializations</h6>
          <span class="subtle small"><?= count($specDemand) ?> rows</span>
        </div>
        <div class="card-body">
          <?php if (count($specDemand) === 0): ?>
            <div class="empty">No appointment data yet.</div>
          <?php else: ?>
            <div class="canvas-wrap"><canvas id="specsChart"></canvas></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Doctor ratings -->
    <div class="col-md-4">
      <div class="card card-shadow h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6>Doctor Ratings</h6>
          <span class="subtle small"><?= count($ratings) ?> doctors</span>
        </div>
        <div class="card-body">
          <?php if (count($ratings) === 0): ?>
            <div class="empty">No ratings available.</div>
          <?php else: ?>
            <div class="canvas-wrap"><canvas id="ratingsChart"></canvas></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ✅ Row 2: NEW chart — Doctors per Specialization -->
  <div class="row g-4 mt-1">
    <div class="col-12">
      <div class="card card-shadow h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6>Doctors per Specialization</h6>
          <span class="subtle small"><?= count($doctorsBySpec) ?> specs</span>
        </div>
        <div class="card-body">
          <?php if (count($doctorsBySpec) === 0): ?>
            <div class="empty">No doctors found.</div>
          <?php else: ?>
            <div class="canvas-wrap"><canvas id="doctorsBySpecChart"></canvas></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // ✅ PHP → JS datasets (encoded safely server-side)
  const userLabels   = <?= json_encode(array_column($userRegs, 'date')) ?>;
  const userData     = <?= json_encode(array_map('intval', array_column($userRegs, 'cnt')))  ?>;

  const specLabels   = <?= json_encode(array_column($specDemand, 'name')) ?>;
  const specData     = <?= json_encode(array_map('intval', array_column($specDemand, 'cnt')))  ?>;

  const ratingLabels = <?= json_encode(array_column($ratings, 'username')) ?>;
  const ratingData   = <?= json_encode(array_map('floatval', array_column($ratings, 'avg_rating'))) ?>;

  // NEW: doctors per specialization
  const doctorsSpecLabels = <?= json_encode(array_column($doctorsBySpec, 'name')) ?>;
  const doctorsSpecData   = <?= json_encode(array_map('intval', array_column($doctorsBySpec, 'cnt')))  ?>;

  // ✅ Helper: soft gradient for line area fill
  function makeGradient(ctx){
    const g = ctx.createLinearGradient(0, 0, 0, 280);
    g.addColorStop(0, 'rgba(13,110,253,0.25)');
    g.addColorStop(1, 'rgba(13,110,253,0.02)');
    return g;
  }

  // Users line chart
  if (userLabels.length) {
    const ctx1 = document.getElementById('usersChart').getContext('2d');
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: userLabels,
        datasets: [{
          label: 'Registrations',
          data: userData,
          fill: true,
          tension: 0.3,
          backgroundColor: makeGradient(ctx1),
          borderColor: 'rgba(13,110,253,1)',
          pointRadius: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: {
          x: { ticks: { autoSkip: true, maxTicksLimit: 6 } },
          y: { beginAtZero: true, precision: 0 }
        }
      }
    });
  }

  // Appointments per specialization (horizontal bar)
  if (specLabels.length) {
    const ctx2 = document.getElementById('specsChart').getContext('2d');
    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: specLabels,
        datasets: [{
          label: 'Appointments',
          data: specData,
          borderWidth: 1
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: {
          x: { beginAtZero: true, precision: 0 }
        }
      }
    });
  }

  // Average doctor rating (bar)
  if (ratingLabels.length) {
    const ctx3 = document.getElementById('ratingsChart').getContext('2d');
    new Chart(ctx3, {
      type: 'bar',
      data: {
        labels: ratingLabels,
        datasets: [{
          label: 'Avg Rating',
          data: ratingData,
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: {
          y: { suggestedMin: 1, suggestedMax: 5, ticks: { stepSize: 1 } }
        }
      }
    });
  }

  // ✅ NEW: Doctors per specialization (horizontal bar for readability)
  if (doctorsSpecLabels.length) {
    const ctx4 = document.getElementById('doctorsBySpecChart').getContext('2d');
    new Chart(ctx4, {
      type: 'bar',
      data: {
        labels: doctorsSpecLabels,
        datasets: [{
          label: 'Doctors',
          data: doctorsSpecData,
          borderWidth: 1
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: {
          x: { beginAtZero: true, precision: 0 }
        }
      }
    });
  }
</script>
</body>
</html>
