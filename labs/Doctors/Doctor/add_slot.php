<?php
// Doctor/add_slot.php
// Purpose: Let a doctor create 30-minute availability slots within a chosen time range.
// Now duplicate-safe: will NOT insert a slot that already exists for (doctor_id, date, time).

session_start();
require_once '../config.php';

// Gate: only logged-in doctors
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];
$created_count = 0;
$skipped_count = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Raw inputs from form
    $date       = $_POST['date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time   = $_POST['end_time'] ?? '';

    // Quick format checks for date/time (YYYY-MM-DD / HH:MM)
    $date_ok = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    $time_ok = preg_match('/^\d{2}:\d{2}$/', $start_time) && preg_match('/^\d{2}:\d{2}$/', $end_time);

    if (!$date_ok || !$time_ok) {
        $error = "Invalid date or time.";
    } else {
        // Server “now” anchor (timezone comes from config.php)
        $nowTs = time();
        $today = date('Y-m-d');

        $start = strtotime("$date $start_time");
        $end   = strtotime("$date $end_time");

        // Validation rules: not in past, start<end, and if today then after current time
        if ($date < $today) {
            $error = "Date can’t be in the past.";
        } elseif ($date === $today && ($start < $nowTs || $end <= $nowTs)) {
            $error = "Start and end times must be after the current time.";
        } elseif ($start >= $end) {
            $error = "Start time must be earlier than end time.";
        } else {
            // Build all desired 30-minute slot starts that fully fit in [start, end)
            $desiredTimes = [];
            for ($t = $start; $t + 1800 <= $end; $t += 1800) {
                if ($t < $nowTs) continue; // skip past starts
                // Normalize to HH:MM:00 to be consistent for comparisons/inserts
                $desiredTimes[] = date("H:i:00", $t);
            }

            if (empty($desiredTimes)) {
                $error = "No 30-minute slots fit in the selected time range.";
            } else {
                // Fetch existing times for this doctor & date once, to skip duplicates
                $existing = [];
                if ($stmtEx = $conn->prepare("SELECT TIME_FORMAT(`time`, '%H:%i:00') AS t FROM slots WHERE doctor_id = ? AND date = ?")) {
                    $stmtEx->bind_param("is", $doctor_id, $date);
                    $stmtEx->execute();
                    $resEx = $stmtEx->get_result();
                    while ($row = $resEx->fetch_assoc()) {
                        $existing[$row['t']] = true;
                    }
                    $stmtEx->close();
                } else {
                    $error = "Failed to check existing slots: " . htmlspecialchars($conn->error);
                }

                if (!isset($error)) {
                    // Insert only times that are not already present
                    $stmtIns = $conn->prepare("INSERT INTO slots (doctor_id, date, time) VALUES (?, ?, ?)");
                    if (!$stmtIns) {
                        $error = "Failed to prepare insert: " . htmlspecialchars($conn->error);
                    } else {
                        $success = true;
                        foreach ($desiredTimes as $slot_time) {
                            if (isset($existing[$slot_time])) {
                                // Duplicate — skip
                                $skipped_count++;
                                continue;
                            }
                            $stmtIns->bind_param("iss", $doctor_id, $date, $slot_time);
                            if (!$stmtIns->execute()) {
                                $success = false;
                                $error   = "Failed to add one or more slots.";
                                break;
                            }
                            $created_count++;
                            // Track as existing so we don't try again in the same request
                            $existing[$slot_time] = true;
                        }
                        $stmtIns->close();

                        // Post-insert messaging
                        if ($success && $created_count === 0 && $skipped_count > 0) {
                            $error = "All selected times already exist. No new slots were created.";
                            unset($success);
                        } elseif ($success && $created_count === 0) {
                            $error = "No 30-minute slots fit in the selected time range.";
                            unset($success);
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Add Slot | MediVerse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap + Icons for quick layout -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Light UI styling */
    body{background:#f6f9fc;}
    .page-wrap{max-width:860px;}
    .card-ux{border:1px solid #e9ecef;border-radius:14px;box-shadow:0 8px 22px rgba(16,24,40,.06);}
    .hero{display:flex;gap:12px;align-items:center}
    .hero .icon{width:44px;height:44px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:#eaf4ff;color:#0d6efd}
    .subtle{color:#64748b}
    .hint{color:#64748b;font-size:.92rem}
    .divider{height:1px;background:#eef2f7;margin:12px 0}
    .form-icon .input-group-text{background:#f8fafc}
  </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap py-4">
  <!-- Title + explainer -->
  <div class="hero mb-3">
    <div class="icon"><i class="bi bi-calendar-plus fs-5"></i></div>
    <div>
      <h3 class="m-0">Add Available Slots</h3>
      <div class="subtle">Slots are created automatically in <strong>30-minute</strong> intervals.</div>
    </div>
  </div>

  <!-- Flash-like alerts (success or error) -->
  <?php if (isset($success) && $success): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i>
      <div>
        Slots added successfully.
        <strong><?= (int)$created_count ?></strong> slot<?= $created_count===1?'':'s' ?> created<?= $skipped_count ? ", <strong>$skipped_count</strong> skipped (already existed)" : '' ?>.
      </div>
    </div>
  <?php elseif (isset($error)): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <div><?= htmlspecialchars($error) ?></div>
    </div>
  <?php endif; ?>

  <!-- Main form -->
  <div class="card card-ux">
    <div class="card-body">
      <form method="post" class="row g-3" autocomplete="off">
        <!-- Date picker -->
        <div class="col-12 col-md-4">
          <label for="date" class="form-label">Date</label>
          <div class="input-group form-icon">
            <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
            <input
              type="date"
              name="date"
              id="date"
              class="form-control"
              required
              min="<?= date('Y-m-d') ?>"
              value="<?= isset($date) ? htmlspecialchars($date) : '' ?>"
            >
          </div>
          <div class="form-text hint">Cannot be in the past.</div>
        </div>

        <!-- Start time -->
        <div class="col-12 col-md-4">
          <label for="start_time" class="form-label">Start time</label>
          <div class="input-group form-icon">
            <span class="input-group-text"><i class="bi bi-clock"></i></span>
            <input
              type="time"
              name="start_time"
              id="start_time"
              class="form-control"
              required
              value="<?= isset($start_time) ? htmlspecialchars($start_time) : '' ?>"
            >
          </div>
          <div class="form-text hint">If today, can’t be earlier than now.</div>
        </div>

        <!-- End time -->
        <div class="col-12 col-md-4">
          <label for="end_time" class="form-label">End time</label>
          <div class="input-group form-icon">
            <span class="input-group-text"><i class="bi bi-clock-history"></i></span>
            <input
              type="time"
              name="end_time"
              id="end_time"
              class="form-control"
              required
              value="<?= isset($end_time) ? htmlspecialchars($end_time) : '' ?>"
            >
          </div>
          <div class="form-text hint">Must be at least <strong>30 minutes</strong> after start.</div>
        </div>

        <div class="divider"></div>

        <!-- Submit / Back -->
        <div class="col-12 d-flex gap-2">
          <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-short me-1"></i>Back
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-magic me-1"></i>Generate Slots
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Helpful note -->
  <div class="mt-3 hint">
    <i class="bi bi-info-circle me-1"></i>
    Slots are created at: <em>Start, Start+30, Start+60, …</em> and only if each slot fully fits before End.
  </div>
</div>

<script>
// Client-side helpers: prevent selecting past times today and nudge end >= start+30
(function(){
  const dateEl  = document.getElementById('date');
  const startEl = document.getElementById('start_time');
  const endEl   = document.getElementById('end_time');

  function pad(n){ return (n < 10 ? '0' : '') + n; }

  // Round given Date to next 30-minute boundary
  function roundToNext30(d){
    const mins = d.getMinutes();
    const add  = mins % 30 === 0 ? 0 : (30 - (mins % 30));
    d.setMinutes(mins + add, 0, 0);
    return d;
  }

  // If fields are empty, prefill with sensible defaults (today/next half-hour)
  function setDefaultsIfEmpty(){
    const todayStr = new Date().toISOString().slice(0,10);
    if (!dateEl.value) dateEl.value = todayStr;

    if (!startEl.value || !endEl.value) {
      let base = new Date();
      if (dateEl.value !== todayStr) { base.setHours(9,0,0,0); } // 09:00 for future days
      base = roundToNext30(base);
      const startHH = pad(base.getHours()) + ':' + pad(base.getMinutes());
      base.setMinutes(base.getMinutes() + 60); // default range 1 hour
      const endHH = pad(base.getHours()) + ':' + pad(base.getMinutes());
      if (!startEl.value) startEl.value = startHH;
      if (!endEl.value)   endEl.value   = endHH;
    }
  }

  // For today: enforce min times >= now; otherwise allow 00:00
  function setMinTimesForDate() {
    const todayStr = new Date().toISOString().slice(0,10);
    const selectedDate = dateEl.value;

    if (selectedDate === todayStr) {
      const now = new Date();
      const hh   = pad(now.getHours());
      const mm   = pad(now.getMinutes());
      const hhmm = `${hh}:${mm}`;
      startEl.min = hhmm;
      endEl.min   = hhmm;

      if (startEl.value && startEl.value < startEl.min) startEl.value = startEl.min;
      if (endEl.value && endEl.value < endEl.min)       endEl.value   = endEl.min;
    } else {
      startEl.min = '00:00';
      endEl.min   = '00:00';
    }
    bumpEndMin();
  }

  // Keep end >= start + 30 minutes
  function bumpEndMin() {
    if (!startEl.value) return;
    const [h, m] = startEl.value.split(':').map(Number);
    const d = new Date();
    d.setHours(h, m, 0, 0);
    d.setMinutes(d.getMinutes() + 30); // +30 minutes

    const hhmm = pad(d.getHours()) + ':' + pad(d.getMinutes());
    const todayStr = new Date().toISOString().slice(0,10);
    endEl.min = (dateEl.value === todayStr)
      ? (hhmm > endEl.min ? hhmm : endEl.min) // later of "now" or "start+30"
      : hhmm;

    if (endEl.value && endEl.value < endEl.min) endEl.value = endEl.min;
  }

  // Init defaults and constraints
  setDefaultsIfEmpty();
  setMinTimesForDate();

  // React to user changes
  dateEl.addEventListener('change', setMinTimesForDate);
  startEl.addEventListener('change', bumpEndMin);
})();
</script>
</body>
</html>
