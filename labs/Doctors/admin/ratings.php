<?php
// admin/ratings.php
session_start();
require_once '../config.php';

// ✅ Allow only logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

/* -------------------------------------------------------
   Helper Functions
   ------------------------------------------------------- */

/**
 * Get all doctors who have received ratings
 */
function get_doctors_with_ratings(mysqli $conn): array {
    $sql = "
        SELECT DISTINCT u.id, u.username
        FROM ratings r
        JOIN users u ON r.doctor_id = u.id
        ORDER BY u.username
    ";
    $res = $conn->query($sql);
    $doctors = [];
    while ($row = $res->fetch_assoc()) {
        $doctors[] = $row;
    }
    return $doctors;
}

/**
 * Get overall statistics across the entire clinic
 */
function get_overall_stats(mysqli $conn): array {
    $sql = "
        SELECT 
            COUNT(*)                AS total_reviews,
            ROUND(AVG(rating),2)    AS avg_rating,
            ROUND(AVG(wait_time),2) AS avg_wait_time,
            ROUND(AVG(service),2)   AS avg_service,
            ROUND(AVG(communication),2) AS avg_communication,
            ROUND(AVG(facilities),2)    AS avg_facilities
        FROM ratings
    ";
    $res = $conn->query($sql);
    return $res->fetch_assoc() ?: [];
}

/**
 * Get stats for one specific doctor
 */
function get_doctor_stats(mysqli $conn, int $doctor_id): array {
    $sql = "
        SELECT 
            u.username,
            COUNT(*)                      AS total_reviews,
            ROUND(AVG(r.rating),2)        AS avg_rating,
            ROUND(AVG(r.wait_time),2)     AS avg_wait_time,
            ROUND(AVG(r.service),2)       AS avg_service,
            ROUND(AVG(r.communication),2) AS avg_communication,
            ROUND(AVG(r.facilities),2)    AS avg_facilities
        FROM ratings r
        JOIN users u ON r.doctor_id = u.id
        WHERE r.doctor_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: [];
    $stmt->close();
    return $row;
}

/**
 * Get all individual ratings for a given doctor
 * Includes:
 *  - Doctor's user ID
 *  - Patient's user ID
 *  - Rating details
 *  - Appointment date
 */
function get_ratings_for_doctor(
    mysqli $conn, 
    int $doctor_id, 
    string $filter, 
    int $threshold
): mysqli_result {
    $sql = "
        SELECT 
            r.id AS rating_id,                      -- used for PDF action
            u_doc.user_id AS doctor_user_id,        -- doctor ID shown in table
            u_doc.username AS doctor,
            u_pat.user_id AS patient_user_id,       -- patient ID shown in table
            u_pat.username AS patient, 
            r.rating, 
            r.comment, 
            r.rated_at,
            r.wait_time, r.service, r.communication, r.facilities,
            a.appointment_datetime
        FROM ratings r
        JOIN users u_doc    ON r.doctor_id   = u_doc.id
        JOIN users u_pat    ON r.patient_id  = u_pat.id
        JOIN appointments a ON r.appointment_id = a.id
        WHERE r.doctor_id = ?
    ";

    // ✅ Apply filter logic if needed
    if ($filter === 'high') {
        $sql .= " AND r.rating >= ? ";
    } elseif ($filter === 'low') {
        $sql .= " AND r.rating <= ? ";
    }

    $sql .= " ORDER BY a.appointment_datetime DESC ";

    // ✅ Bind params depending on filter
    if ($filter === 'all') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $doctor_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $doctor_id, $threshold);
    }

    $stmt->execute();
    return $stmt->get_result();
}

/* -------------------------------------------------------
   Input Handling
   ------------------------------------------------------- */

// Doctor to view (if any)
$doctor_id = isset($_GET['doctor_id']) && ctype_digit($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

// Filter type: all, high, low
$filter    = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Threshold for high/low (defaults: high=4, low=2)
$threshold = isset($_GET['threshold']) && ctype_digit($_GET['threshold']) ? (int)$_GET['threshold'] : 0;
if ($filter === 'high' && $threshold === 0) $threshold = 4;
if ($filter === 'low'  && $threshold === 0) $threshold = 2;

/* -------------------------------------------------------
   Fetch Data
   ------------------------------------------------------- */
$doctors = get_doctors_with_ratings($conn);
$overall = get_overall_stats($conn);

$doctorStats = [];
$ratingsRes  = null;

if ($doctor_id > 0) {
    $doctorStats = get_doctor_stats($conn, $doctor_id);
    $ratingsRes  = get_ratings_for_doctor($conn, $doctor_id, $filter, $threshold);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Doctor Ratings | MediVerse Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container my-5">
  <!-- ✅ Page Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
    <h2 class="mb-2">📝 Ratings Dashboard</h2>
    <a class="btn btn-outline-secondary btn-sm" href="ratings.php">Reset</a>
  </div>

  <!-- ✅ Overall Clinic Stats -->
  <div class="card mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
      <span>Overall (Clinic-wide)</span>
      <a class="btn btn-sm btn-outline-primary" href="export_ratings_pdf.php?doctor_id=0&filter=all&threshold=0">⬇️ Download Clinic PDF</a>
    </div>
    <div class="card-body">
      <div class="row g-3 text-center">
        <!-- each column shows one metric -->
        <div class="col-6 col-md-2">
          <div class="p-2 border rounded">
            <div class="small text-muted">Total Reviews</div>
            <div class="fs-5"><?= (int)($overall['total_reviews'] ?? 0) ?></div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 border rounded">
            <div class="small text-muted">Avg Rating</div>
            <div class="fs-5"><?= htmlspecialchars($overall['avg_rating'] ?? '0.00') ?></div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 border rounded">
            <div class="small text-muted">Avg Wait</div>
            <div class="fs-5"><?= htmlspecialchars($overall['avg_wait_time'] ?? '0.00') ?></div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 border rounded">
            <div class="small text-muted">Avg Service</div>
            <div class="fs-5"><?= htmlspecialchars($overall['avg_service'] ?? '0.00') ?></div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 border rounded">
            <div class="small text-muted">Avg Comm.</div>
            <div class="fs-5"><?= htmlspecialchars($overall['avg_communication'] ?? '0.00') ?></div>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 border rounded">
            <div class="small text-muted">Avg Facilities</div>
            <div class="fs-5"><?= htmlspecialchars($overall['avg_facilities'] ?? '0.00') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ✅ Doctor Selection + Filter Form -->
  <form class="card mb-4" method="get" action="ratings.php">
    <div class="card-header fw-bold">View Per Doctor</div>
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <!-- Choose Doctor -->
        <div class="col-md-4">
          <label class="form-label">Doctor</label>
          <select name="doctor_id" class="form-select" required>
            <option value="" disabled <?= $doctor_id ? '' : 'selected' ?>>Choose a doctor…</option>
            <?php foreach ($doctors as $doc): ?>
              <option value="<?= (int)$doc['id'] ?>" <?= $doctor_id == (int)$doc['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($doc['username']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Choose Filter -->
        <div class="col-md-3">
          <label class="form-label">Rating Filter</label>
          <select name="filter" class="form-select">
            <option value="all"  <?= $filter === 'all'  ? 'selected' : '' ?>>All</option>
            <option value="high" <?= $filter === 'high' ? 'selected' : '' ?>>High (≥ threshold)</option>
            <option value="low"  <?= $filter === 'low'  ? 'selected' : '' ?>>Low (≤ threshold)</option>
          </select>
        </div>

        <!-- Choose Threshold -->
        <div class="col-md-3">
          <label class="form-label">Threshold (1–5)</label>
          <input type="number" min="1" max="5" name="threshold" class="form-control" 
                 value="<?= (int)$threshold ?>" placeholder="<?= $filter==='high'?'4':($filter==='low'?'2':'') ?>">
        </div>

        <!-- Apply Button -->
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit">Apply</button>
        </div>
      </div>
    </div>
  </form>

  <?php if ($doctor_id > 0): ?>
    <!-- ✅ Download Doctor-Specific PDF -->
    <div class="d-flex justify-content-end mb-2">
      <?php 
        $qs = http_build_query([
          'doctor_id' => $doctor_id,
          'filter'    => $filter,
          'threshold' => $threshold
        ]);
      ?>
      <a class="btn btn-outline-primary btn-sm"
         href="export_ratings_pdf.php?<?= htmlspecialchars($qs) ?>">
         ⬇️ Download Doctor PDF
      </a>
    </div>

    <!-- ✅ Doctor Summary -->
    <div class="card mb-4">
      <div class="card-header fw-bold">
        Doctor Summary — <?= htmlspecialchars($doctorStats['username'] ?? 'Unknown') ?>
      </div>
      <div class="card-body">
        <div class="row g-3 text-center">
          <!-- stats same as overall but per doctor -->
          <div class="col-6 col-md-2">
            <div class="p-2 border rounded">
              <div class="small text-muted">Total Reviews</div>
              <div class="fs-5"><?= (int)($doctorStats['total_reviews'] ?? 0) ?></div>
            </div>
          </div>
          <div class="col-6 col-md-2">
            <div class="p-2 border rounded">
              <div class="small text-muted">Avg Rating</div>
              <div class="fs-5"><?= htmlspecialchars($doctorStats['avg_rating'] ?? '0.00') ?></div>
            </div>
          </div>
          <div class="col-6 col-md-2">
            <div class="p-2 border rounded">
              <div class="small text-muted">Avg Wait</div>
              <div class="fs-5"><?= htmlspecialchars($doctorStats['avg_wait_time'] ?? '0.00') ?></div>
            </div>
          </div>
          <div class="col-6 col-md-2">
            <div class="p-2 border rounded">
              <div class="small text-muted">Avg Service</div>
              <div class="fs-5"><?= htmlspecialchars($doctorStats['avg_service'] ?? '0.00') ?></div>
            </div>
          </div>
          <div class="col-6 col-md-2">
            <div class="p-2 border rounded">
              <div class="small text-muted">Avg Comm.</div>
              <div class="fs-5"><?= htmlspecialchars($doctorStats['avg_communication'] ?? '0.00') ?></div>
            </div>
          </div>
          <div class="col-6 col-md-2">
            <div class="p-2 border rounded">
              <div class="small text-muted">Avg Facilities</div>
              <div class="fs-5"><?= htmlspecialchars($doctorStats['avg_facilities'] ?? '0.00') ?></div>
            </div>
          </div>
        </div>
        <?php if ($filter !== 'all'): ?>
          <div class="mt-3 small text-muted">
            Showing <?= $filter === 'high' ? 'ratings ≥ ' : 'ratings ≤ ' ?><?= (int)$threshold ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ✅ Ratings Table -->
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Doctor ID</th>
            <th>Doctor</th>
            <th>Patient ID</th>
            <th>Patient</th>
            <th>Wait Time</th>
            <th>Service</th>
            <th>Communication</th>
            <th>Facilities</th>
            <th>Rating</th>
            <th>Comment</th>
            <th>Appt. Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($ratingsRes && $ratingsRes->num_rows): ?>
          <?php while ($r = $ratingsRes->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($r['doctor_user_id']) ?></td>
              <td><?= htmlspecialchars($r['doctor']) ?></td>
              <td><?= htmlspecialchars($r['patient_user_id']) ?></td>
              <td><?= htmlspecialchars($r['patient']) ?></td>
              <td><?= htmlspecialchars($r['wait_time']) ?>/5</td>
              <td><?= htmlspecialchars($r['service']) ?>/5</td>
              <td><?= htmlspecialchars($r['communication']) ?>/5</td>
              <td><?= htmlspecialchars($r['facilities']) ?>/5</td>
              <td><?= htmlspecialchars($r['rating']) ?>/5</td>
              <td><?= htmlspecialchars($r['comment']) ?></td>
              <td><?= htmlspecialchars(date('Y-m-d', strtotime($r['appointment_datetime']))) ?></td>
              <td>
                <!-- PDF export for single rating -->
                <a class="btn btn-sm btn-outline-secondary"
                   href="export_rating_pdf.php?rating_id=<?= (int)$r['rating_id'] ?>">
                  ⬇️ PDF
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="12" class="text-center text-muted">No ratings found for this selection.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="alert alert-info">
      Select a doctor above to view their ratings and apply filters.
    </div>
  <?php endif; ?>
</div>
</body>
</html>
