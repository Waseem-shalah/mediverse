<?php
session_start();
require_once 'config.php';

// Require a logged-in user (adjust as needed)
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php'); exit();
}

// 1) Specializations that actually have at least one doctor
$specializations = $conn->query("
  SELECT s.id, s.name
  FROM specializations s
  WHERE EXISTS (
    SELECT 1
    FROM users u
    WHERE u.specialization_id = s.id
      AND u.role = 'doctor'
  )
  ORDER BY s.name
");

// 2) All doctors (filtered client-side by specialization)
$doctors = $conn->query("
  SELECT u.id, u.name, COALESCE(u.location, '') AS location, u.specialization_id
  FROM users u
  WHERE u.role = 'doctor'
  ORDER BY u.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Book Appointment | MediVerse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <style>
    body{ background:#f6f9fc; }
    .page-wrap{ max-width: 960px; }
    .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); border:1px solid #e9ecef; }
    .label-strong{ font-weight:600; color:#334155; }
    .muted{ color:#6b7280; }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      background:#eef2ff; color:#3730a3;
      border-radius:999px; padding:.35rem .7rem; font-weight:700; font-size:.9rem;
    }
    .spinner{ display:inline-block; width:1rem; height:1rem; border:.15em solid #cbd5e1; border-top-color:#2563eb; border-radius:50%; animation:spin .8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
<?php include 'navbar_loggedin.php'; ?>

<div class="container page-wrap my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
      <h3 class="mb-0">🗓️ Book an Appointment</h3>
      <span class="chip"><i class="bi bi-clipboard2-pulse me-1"></i>MediVerse</span>
    </div>
  </div>

  <div class="card card-shadow">
    <div class="card-body">
      <form action="book_appointment_process.php" method="post" class="row g-3" autocomplete="off">
        <!-- Specialization -->
        <div class="col-md-6">
          <label for="specialization" class="form-label label-strong">Specialization</label>
          <select id="specialization" name="specialization_id" class="form-select" required>
            <option value="">— Select —</option>
            <?php while ($s = $specializations->fetch_assoc()): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endwhile; ?>
          </select>
          <div class="form-text muted">Choose a specialty to narrow the doctors list.</div>
        </div>

        <!-- Doctor -->
        <div class="col-md-6">
          <label for="doctor" class="form-label label-strong">Doctor</label>
          <select name="doctor_id" id="doctor" class="form-select" required disabled>
            <option value="">— Select Doctor —</option>
            <?php while ($d = $doctors->fetch_assoc()): ?>
              <option value="<?= (int)$d['id'] ?>" data-spec="<?= (int)$d['specialization_id'] ?>">
                <?= htmlspecialchars($d['name']) ?><?= $d['location'] ? ' — ' . htmlspecialchars($d['location']) : '' ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Date -->
        <div class="col-md-4">
          <label for="date" class="form-label label-strong">Date</label>
          <input
            type="text"
            name="date"
            id="date"
            class="form-control"
            required
            placeholder="Select a date"
            autocomplete="off"
          >
          <div class="form-text muted" id="date-hint">Pick a date after choosing a doctor.</div>
        </div>

        <!-- Slots -->
        <div class="col-md-8">
          <label for="time-slot" class="form-label label-strong">Available Time</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-clock"></i></span>
            <select name="time" id="time-slot" class="form-select" required disabled>
              <option value="">Select specialization, doctor & date</option>
            </select>
          </div>
          <div class="form-text muted" id="slot-hint">Pick a date after choosing a doctor.</div>
        </div>

        <div class="col-12 d-flex gap-2 justify-content-end pt-2">
          <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Book Appointment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const specSelect   = document.getElementById('specialization');
  const doctorSelect = document.getElementById('doctor');
  const dateInput    = document.getElementById('date');
  const dateHint     = document.getElementById('date-hint');
  const slotBox      = document.getElementById('time-slot');
  const slotHint     = document.getElementById('slot-hint');

  let enabledDates = []; // array of 'YYYY-MM-DD' strings returned by the server

  // Flatpickr (use local formatting only — NO toISOString!)
  const fp = flatpickr(dateInput, {
    dateFormat: 'Y-m-d',
    disableMobile: true,
    allowInput: false,
    minDate: 'today',
    enable: [() => false], // disabled until doctor is selected
    onOpen: function() {
      if (doctorSelect.disabled || !doctorSelect.value) {
        dateInput.blur(); // block opening before doctor selection
      }
    },
    onChange: function() {
      loadSlots(); // when a valid date is picked
    }
  });

  // Events
  specSelect.addEventListener('change', filterDoctors);
  doctorSelect.addEventListener('change', handleDoctorChange);
  dateInput.addEventListener('change', loadSlots);

  function resetSlots(placeholder = 'Select specialization, doctor & date') {
    slotBox.innerHTML = `<option value="">${placeholder}</option>`;
    slotBox.disabled = true;
  }

  function filterDoctors() {
    const specId = specSelect.value || '';
    let visibleCount = 0;

    doctorSelect.querySelectorAll('option').forEach((opt, idx) => {
      if (idx === 0) { opt.hidden = false; return; }
      const optSpec = opt.getAttribute('data-spec') || '';
      if (!specId) {
        opt.hidden = true;
      } else {
        opt.hidden = (optSpec !== specId);
        if (!opt.hidden) visibleCount++;
      }
    });

    doctorSelect.value = '';
    doctorSelect.disabled = !(specId && visibleCount > 0);

    // Reset date & slots and disable all dates
    enabledDates = [];
    fp.clear();
    fp.set('enable', [() => false]);
    dateHint.textContent = 'Pick a date after choosing a doctor.';
    resetSlots();
    slotHint.textContent = 'Pick a date after choosing a doctor.';
  }

  function showLoadingSlots() {
    slotBox.disabled = true;
    slotBox.innerHTML = `<option value="">Loading slots…</option>`;
    slotHint.innerHTML = `<span class="spinner" aria-hidden="true"></span> Fetching available times`;
  }

  // When doctor changes, fetch allowed dates and enable them locally (NO UTC conversion!)
  function handleDoctorChange() {
    const doctorId = doctorSelect.value;

    enabledDates = [];
    fp.clear();
    resetSlots();

    if (!doctorId) {
      fp.set('enable', [() => false]);
      dateHint.textContent = 'Pick a date after choosing a doctor.';
      slotHint.textContent = 'Pick a date after choosing a doctor.';
      return;
    }

    dateHint.innerHTML = `<span class="spinner" aria-hidden="true"></span> Loading available dates`;

    fetch(`get_available_dates.php?doctor_id=${encodeURIComponent(doctorId)}`, { credentials: 'same-origin' })
      .then(async (r) => {
        if (!r.ok) {
          const txt = await r.text();
          throw new Error(`HTTP ${r.status}: ${txt.slice(0,200)}`);
        }
        const ct = r.headers.get('content-type') || '';
        const body = await r.text();
        if (!ct.includes('application/json')) {
          throw new Error(`Unexpected content-type: ${ct}. First bytes: ${body.slice(0,200)}`);
        }
        return JSON.parse(body);
      })
      .then(dates => {
        if (!Array.isArray(dates) || dates.length === 0) {
          enabledDates = [];
          fp.set('enable', [() => false]);
          dateHint.textContent = 'No available dates for this doctor. Try another doctor.';
          return;
        }
        enabledDates = dates;
        // Use Flatpickr's local formatter to compare dates, not toISOString()
        fp.set('enable', [function(dateObj){
          const localIso = fp.formatDate(dateObj, 'Y-m-d');
          return enabledDates.includes(localIso);
        }]);
        dateHint.textContent = 'Choose a date with available times.';
      })
      .catch((err) => {
        enabledDates = [];
        fp.set('enable', [() => false]);
        dateHint.textContent = `Could not load dates: ${err.message}`;
      });
  }

  // Load slots for the selected doctor & date (use the local formatted date)
  function loadSlots() {
    const doctorId = doctorSelect.value;

    // Prefer the selectedDates array to avoid any textbox quirks
    let picked = '';
    if (fp.selectedDates && fp.selectedDates.length) {
      picked = fp.formatDate(fp.selectedDates[0], 'Y-m-d');
    } else {
      picked = dateInput.value || '';
    }

    if (!doctorId) { resetSlots('Select a doctor first'); slotHint.textContent = 'Pick a date after choosing a doctor.'; return; }
    if (!picked)   { resetSlots('Select a date');         slotHint.textContent = 'Pick a date after choosing a doctor.'; return; }

    // Validate picked date is one of the enabled ones (local string)
    if (enabledDates.length && !enabledDates.includes(picked)) {
      resetSlots('Select an enabled date');
      slotHint.textContent = 'That date isn’t available. Please choose a highlighted date.';
      fp.clear();
      return;
    }

    showLoadingSlots();

    fetch(`get_available_slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(picked)}`, { credentials: 'same-origin' })
      .then(async (r) => {
        if (!r.ok) {
          const txt = await r.text();
          throw new Error(`HTTP ${r.status}: ${txt.slice(0,200)}`);
        }
        const ct = r.headers.get('content-type') || '';
        const body = await r.text();
        if (!ct.includes('application/json')) {
          throw new Error(`Unexpected content-type: ${ct}. First bytes: ${body.slice(0,200)}`);
        }
        return JSON.parse(body);
      })
      .then(data => {
        slotBox.innerHTML = '';
        if (!Array.isArray(data) || data.length === 0) {
          slotBox.innerHTML = '<option value="">No available slots</option>';
          slotBox.disabled = true;
          slotHint.textContent = 'No times left for this date. Try another day.';
          return;
        }
        data.forEach(slot => {
          const opt = document.createElement('option');
          opt.value       = slot.id;     // slot_id
          opt.textContent = slot.time;   // HH:MM
          slotBox.appendChild(opt);
        });
        slotBox.disabled = false;
        slotHint.textContent = 'Choose an available time.';
      })
      .catch((err) => {
        resetSlots('Error loading slots');
        slotHint.textContent = `Could not load slots: ${err.message}`;
      });
  }

  // Init
  (function init() {
    doctorSelect.disabled = true;
    doctorSelect.querySelectorAll('option').forEach((opt, idx) => {
      if (idx === 0) { opt.hidden = false; } else { opt.hidden = true; }
    });
    fp.set('enable', [() => false]); // disable all dates initially
    dateHint.textContent = 'Pick a date after choosing a doctor.';
    resetSlots();
  })();
});
</script>
</body>
</html>
