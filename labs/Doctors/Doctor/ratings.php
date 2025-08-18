<?php
// doctor/ratings.php

// Start session and check if the logged-in user is a doctor
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php"); // Redirect non-doctors to login
    exit();
}
require_once '../config.php'; // Database connection

// Get the logged-in doctor’s name for the page header
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($name);
$stmt->fetch();
$stmt->close();

/* 
   Fetch all ratings that belong to this doctor.
   We join with patients (users table) to get patient info,
   and with appointments to get appointment date.
*/
$rs = $conn->prepare("
  SELECT 
    u.user_id                  AS patient_user_id,   -- patient’s public ID
    u.username                 AS patient,           -- patient username
    r.rating,                                       -- overall rating
    r.wait_time, r.service, r.communication, r.facilities, -- sub-ratings
    r.comment,                                     -- patient’s comment
    r.rated_at,                                    -- rating date/time
    a.appointment_datetime                         -- appointment date
  FROM ratings r
  JOIN users u        ON r.patient_id = u.id
  JOIN appointments a ON r.appointment_id = a.id
  WHERE r.doctor_id = ?
  ORDER BY a.appointment_datetime DESC
");
$rs->bind_param("i", $_SESSION['user_id']);
$rs->execute();
$ratings = $rs->get_result();
$rs->close();

/* 
   Calculate average scores (overall and subcategories)
   and total number of ratings for this doctor.
*/
$avgQ = $conn->prepare("
  SELECT 
    ROUND(AVG(rating),2)        AS avg_rating,
    COUNT(*)                    AS cnt,
    ROUND(AVG(wait_time),1)     AS avg_wait,
    ROUND(AVG(service),1)       AS avg_service,
    ROUND(AVG(communication),1) AS avg_comm,
    ROUND(AVG(facilities),1)    AS avg_fac
  FROM ratings
  WHERE doctor_id = ?
");
$avgQ->bind_param("i", $_SESSION['user_id']);
$avgQ->execute();
$avg = $avgQ->get_result()->fetch_assoc();
$avgQ->close();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>My Ratings | MediVerse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Page styling */
    body{ background:#f6f9fc; }
    .page-wrap{ max-width: 1150px; }
    .chip{
      display:inline-flex; align-items:center; gap:.4rem;
      background:#eef2ff; color:#3730a3; border-radius:999px; padding:.3rem .7rem; font-weight:700; font-size:.9rem;
    }
    .subchip{ background:#f1f5f9; color:#0f172a; }
    .table thead th{ background:#f8fafc; border-bottom:1px solid #e9ecef; }
    .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); }
    .star{ color:#f1c40f; }
    .filters .form-label{ font-weight:600; color:#334155; }
    .comment-cell{ max-width:340px; }
    .empty{ padding:42px 16px; color:#64748b; }
  </style>
</head>
<body>
  <?php include '../navbar_loggedin.php'; ?> <!-- Top navigation bar -->

  <div class="container page-wrap py-4">

    <!-- Page header: Doctor’s name + number of ratings -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
      <div class="d-flex align-items-center gap-2">
        <h3 class="m-0">⭐ My Patient Ratings — Dr. <?= htmlspecialchars($name) ?></h3>
        <span class="chip">
          <i class="bi bi-people"></i>
          <?= (int)($avg['cnt'] ?? 0) ?> total
        </span>
      </div>
    </div>

    <!-- Show averages if there are ratings -->
    <?php if (($avg['cnt'] ?? 0) > 0): ?>
      <div class="card card-shadow mb-4">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="chip">
              <i class="bi bi-star-fill"></i>
              Overall: <?= htmlspecialchars($avg['avg_rating']) ?>/5
            </span>
            <span class="chip subchip"><i class="bi bi-hourglass-split"></i> Wait: <?= htmlspecialchars($avg['avg_wait']) ?>/5</span>
            <span class="chip subchip"><i class="bi bi-hand-thumbs-up"></i> Service: <?= htmlspecialchars($avg['avg_service']) ?>/5</span>
            <span class="chip subchip"><i class="bi bi-chat-dots"></i> Communication: <?= htmlspecialchars($avg['avg_comm']) ?>/5</span>
            <span class="chip subchip"><i class="bi bi-building"></i> Facilities: <?= htmlspecialchars($avg['avg_fac']) ?>/5</span>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Filters section (works client-side with JavaScript) -->
    <div class="card card-shadow mb-3">
      <div class="card-header">
        <i class="bi bi-funnel me-2"></i>Filters
      </div>
      <div class="card-body">
        <form class="row g-3 filters" onsubmit="return false;">
          <!-- Search by patient -->
          <div class="col-sm-6 col-md-4 col-lg-3">
            <label class="form-label">Patient (name or ID)</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input type="text" id="fPatient" class="form-control" placeholder="e.g. user123 or Ahmed">
            </div>
          </div>
          <!-- Filter by minimum rating -->
          <div class="col-sm-6 col-md-4 col-lg-3">
            <label class="form-label">Min rating</label>
            <select id="fMinRating" class="form-select">
              <option value="">Any</option>
              <option value="5">5 ★</option>
              <option value="4">4 ★+</option>
              <option value="3">3 ★+</option>
              <option value="2">2 ★+</option>
              <option value="1">1 ★+</option>
            </select>
          </div>
          <!-- Filter by date range -->
          <div class="col-sm-6 col-md-4 col-lg-3">
            <label class="form-label">Rated from</label>
            <input type="date" id="fFrom" class="form-control">
          </div>
          <div class="col-sm-6 col-md-4 col-lg-3">
            <label class="form-label">Rated to</label>
            <input type="date" id="fTo" class="form-control">
          </div>
          <!-- Buttons -->
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" id="btnApply"><i class="bi bi-search me-1"></i>Apply</button>
            <button class="btn btn-outline-secondary" id="btnReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Ratings table -->
    <?php if ($ratings->num_rows === 0): ?>
      <!-- If no ratings yet -->
      <div class="card card-shadow">
        <div class="card-body text-center empty">
          <div class="display-6 mb-2">🙌</div>
          No ratings yet.
        </div>
      </div>
    <?php else: ?>
      <div class="card card-shadow">
        <div class="table-responsive">
          <table id="ratingsTable" class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th style="min-width:110px;">Patient ID</th>
                <th>Patient</th>
                <th class="text-center">Overall</th>
                <th class="text-center">Wait</th>
                <th class="text-center">Service</th>
                <th class="text-center">Communication</th>
                <th class="text-center">Facilities</th>
                <th class="comment-cell">Comment</th>
                <th style="min-width:140px;">Rated At</th>
                <th style="min-width:120px;">Appt. Date</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = $ratings->fetch_assoc()): ?>
                <?php
                  // Format dates nicely
                  $ratedAt = $r['rated_at'] ? date('Y-m-d H:i', strtotime($r['rated_at'])) : '';
                  $apptAt  = $r['appointment_datetime'] ? date('Y-m-d', strtotime($r['appointment_datetime'])) : '';
                ?>
                <tr>
                  <td data-patientid="<?= htmlspecialchars($r['patient_user_id']) ?>"><?= htmlspecialchars($r['patient_user_id']) ?></td>
                  <td data-patient="<?= htmlspecialchars($r['patient']) ?>">
                    <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($r['patient']) ?>
                  </td>
                  <td class="text-center" data-rating="<?= (float)$r['rating'] ?>">
                    <?= htmlspecialchars($r['rating']) ?> <span class="star">★</span>
                  </td>
                  <td class="text-center"><?= (int)$r['wait_time'] ?>/5</td>
                  <td class="text-center"><?= (int)$r['service'] ?>/5</td>
                  <td class="text-center"><?= (int)$r['communication'] ?>/5</td>
                  <td class="text-center"><?= (int)$r['facilities'] ?>/5</td>
                  <td class="comment-cell"><?= nl2br(htmlspecialchars($r['comment'] ?: '—')) ?></td>
                  <td data-ratedat="<?= htmlspecialchars(substr($ratedAt,0,10)) ?>"><?= htmlspecialchars($ratedAt) ?></td>
                  <td><?= htmlspecialchars($apptAt) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  </div>

  <script>
    // Filtering logic (runs entirely client-side)
    (function(){
      const fPatient   = document.getElementById('fPatient');
      const fMinRating = document.getElementById('fMinRating');
      const fFrom      = document.getElementById('fFrom');
      const fTo        = document.getElementById('fTo');
      const btnApply   = document.getElementById('btnApply');
      const btnReset   = document.getElementById('btnReset');
      const tbody      = document.querySelector('#ratingsTable tbody');

      // Helper: parse YYYY-MM-DD into a Date object
      function parseDate(s){
        if(!s) return null;
        const [y,m,d] = s.split('-').map(Number);
        if(!y || !m || !d) return null;
        return new Date(y, m-1, d);
      }

      // Apply filters to the table rows
      function applyFilters(){
        if(!tbody) return;

        const q = (fPatient.value || '').trim().toLowerCase(); // patient name/id search
        const min = parseFloat(fMinRating.value || '0') || 0; // minimum rating
        const from = parseDate(fFrom.value); // from date
        const to   = parseDate(fTo.value);   // to date

        for(const tr of tbody.rows){
          const id  = (tr.querySelector('td[data-patientid]')?.dataset.patientid || '').toLowerCase();
          const nm  = (tr.querySelector('td[data-patient]')?.dataset.patient || '').toLowerCase();
          const rt  = parseFloat(tr.querySelector('td[data-rating]')?.dataset.rating || '0');
          const dstr= tr.querySelector('td[data-ratedat]')?.dataset.ratedat || '';
          const d   = parseDate(dstr);

          let ok = true;

          // Check patient ID or name
          if(q){
            ok = id.includes(q) || nm.includes(q);
          }
          // Check min rating
          if(ok && min > 0){
            ok = rt >= min;
          }
          // Check from date
          if(ok && from){
            ok = d && d >= from;
          }
          // Check to date (inclusive)
          if(ok && to){
            ok = d && d <= new Date(to.getFullYear(), to.getMonth(), to.getDate(), 23,59,59);
          }

          // Show or hide row
          tr.style.display = ok ? '' : 'none';
        }
      }

      // Reset all filters
      function resetFilters(){
        if (fPatient) fPatient.value = '';
        if (fMinRating) fMinRating.value = '';
        if (fFrom) fFrom.value = '';
        if (fTo) fTo.value = '';
        applyFilters();
      }

      // Hook up buttons
      if (btnApply) btnApply.addEventListener('click', applyFilters);
      if (btnReset) btnReset.addEventListener('click', resetFilters);

      // Auto-apply on input change
      [fPatient, fMinRating, fFrom, fTo].forEach(el=>{
        if (el) el.addEventListener('change', applyFilters);
        if (el && el.tagName === 'INPUT') el.addEventListener('input', applyFilters);
      });
    })();
  </script>
</body>
</html>
