<?php
session_start();
require_once '../config.php';
include '../navbar_loggedin.php';

// Make sure the user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
if ($appointment_id <= 0) die("Missing appointment ID.");

/* 1) Verify that the appointment exists and belongs to this doctor.
      Also fetch patient details so the doctor can see who the report is for */
$stmt = $conn->prepare("
    SELECT a.appointment_datetime,
           p.id   AS patient_id,
           p.name AS patient_name,
           p.gender, p.date_of_birth, p.height_cm, p.weight_kg, p.bmi, p.location
    FROM appointments a
    JOIN users p ON p.id = a.patient_id
    WHERE a.id = ? AND a.doctor_id = ?
    LIMIT 1
");
if (!$stmt) die("Prepare failed (appointment): ".$conn->error);
$stmt->bind_param("ii", $appointment_id, $doctor_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$appointment) die("Appointment not found or you don't own it.");

$patient_id = (int)$appointment['patient_id'];
$formattedTime = !empty($appointment['appointment_datetime'])
    ? date("Y-m-d H:i", strtotime($appointment['appointment_datetime']))
    : 'N/A';

/* 2) Get the doctor’s specialization so we know what medicines to show */
$stmt = $conn->prepare("SELECT specialization_id FROM users WHERE id = ? LIMIT 1");
if (!$stmt) die("Prepare failed (spec id): ".$conn->error);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$specialization_id = ($stmt->get_result()->fetch_assoc())['specialization_id'] ?? null;
$stmt->close();
if (!$specialization_id) die("Your profile has no specialization set.");

/* 3) Fetch medicines allowed for this specialization.
      We grab name, form, and strength for dropdowns in the form */
$sql = "
    SELECT DISTINCT m.id, m.name, COALESCE(m.dosage_form,'') AS dosage_form, COALESCE(m.strength,'') AS strength
    FROM medicines m
    INNER JOIN medicine_specializations ms ON m.id = ms.medicine_id
    WHERE ms.specialization_id = ?
    ORDER BY m.name, m.dosage_form, m.strength
";
$stmt = $conn->prepare($sql);
// Fallback if the table name is singular instead of plural
if (!$stmt) {
    $sql = "
        SELECT DISTINCT m.id, m.name, COALESCE(m.dosage_form,'') AS dosage_form, COALESCE(m.strength,'') AS strength
        FROM medicines m
        INNER JOIN medicine_specialization ms ON m.id = ms.medicine_id
        WHERE ms.specialization_id = ?
        ORDER BY m.name, m.dosage_form, m.strength
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed (meds): ".$conn->error);
}
$stmt->bind_param("i", $specialization_id);
$stmt->execute();
$res = $stmt->get_result();

// Build arrays of medicines and unique names for the dropdown
$medRows = [];
$uniqueNames = [];
while ($r = $res->fetch_assoc()) {
    $r['id'] = (int)$r['id'];
    $r['name'] = (string)$r['name'];
    $r['dosage_form'] = (string)$r['dosage_form'];
    $r['strength'] = (string)$r['strength'];
    $medRows[] = $r;
    $uniqueNames[$r['name']] = true;
}
$stmt->close();
$names = array_keys($uniqueNames);
sort($names, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Write Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      /* Styling for medicine entry rows and error feedback */
      .medicine-entry + .medicine-entry { border-top: 1px dashed #e5e7eb; padding-top: .75rem; margin-top: .75rem; }
      .is-invalid + .invalid-feedback{ display:block; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">📝 Write Medical Report</h2>

    <!-- Patient Info card -->
    <div class="card mb-4 shadow">
        <div class="card-body">
            <h5>Patient Information</h5>
            <p><strong>Name:</strong> <?= htmlspecialchars($appointment['patient_name']) ?></p>
            <p><strong>Gender:</strong> <?= htmlspecialchars($appointment['gender']) ?></p>
            <p><strong>Birthday:</strong> <?= htmlspecialchars($appointment['date_of_birth']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($appointment['location']) ?></p>
            <p><strong>Height:</strong> <?= htmlspecialchars($appointment['height_cm']) ?> cm</p>
            <p><strong>Weight:</strong> <?= htmlspecialchars($appointment['weight_kg']) ?> kg</p>
            <p><strong>BMI:</strong> <?= htmlspecialchars($appointment['bmi']) ?></p>
            <p><strong>Appointment Time:</strong> <?= htmlspecialchars($formattedTime) ?></p>
        </div>
    </div>

    <!-- Hidden alert box (appears if form is missing fields) -->
    <div id="formAlert" class="alert alert-danger d-none" role="alert"></div>

    <!-- Report Form -->
    <form method="POST" action="save_report.php" id="reportForm" novalidate>
        <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">
        <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

        <!-- Diagnosis input -->
        <div class="mb-3">
            <label class="form-label">Diagnosis</label>
            <input type="text" name="diagnosis" id="diagnosis" class="form-control" required>
            <div class="invalid-feedback">Diagnosis is required.</div>
        </div>

        <!-- Description input -->
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" id="description" class="form-control" rows="5" required></textarea>
            <div class="invalid-feedback">Description is required.</div>
        </div>

        <!-- Medicines section -->
        <div class="mb-2 d-flex align-items-center justify-content-between">
          <label class="form-label mb-0">Prescribed Medicines</label>
          <!-- Button to add more medicines -->
          <button type="button" class="btn btn-secondary btn-sm" onclick="addMedicine()">➕ Add Another Medicine</button>
        </div>

        <div id="medicine-container">
          <!-- One medicine row (doctor can add more) -->
          <div class="medicine-entry row g-2 align-items-end">
            <!-- Medicine name dropdown -->
            <div class="col-md-4">
              <label class="form-label">Medicine</label>
              <select class="form-select med-name" required>
                <option value="">-- Select Name --</option>
                <?php foreach ($names as $nm): ?>
                  <option value="<?= htmlspecialchars($nm) ?>"><?= htmlspecialchars($nm) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select a medicine name.</div>
            </div>

            <!-- Medicine form (tablet, syrup, etc.) -->
            <div class="col-md-3">
              <label class="form-label">Form</label>
              <select class="form-select med-form" required disabled>
                <option value="">-- Select Form --</option>
              </select>
              <div class="invalid-feedback">Please select a dosage form.</div>
            </div>

            <!-- Medicine strength (e.g., 500mg) -->
            <div class="col-md-3">
              <label class="form-label">Strength</label>
              <select class="form-select med-strength" required disabled>
                <option value="">-- Select Strength --</option>
              </select>
              <div class="invalid-feedback">Please select a strength.</div>
            </div>

            <!-- Hidden inputs (store the actual values for the server) -->
            <input type="hidden" name="medicine_ids[]"   class="med-id" required>
            <input type="hidden" name="med_names[]"      class="med-name-hidden">
            <input type="hidden" name="med_forms[]"      class="med-form-hidden">
            <input type="hidden" name="med_strengths[]"  class="med-strength-hidden">

            <!-- Dosage frequency -->
            <div class="col-md-3">
              <label class="form-label">Times a day</label>
              <input type="number" name="pills_per_day[]" class="form-control pills-per-day" placeholder="Times a day" min="1" required>
              <div class="invalid-feedback">Times a day.</div>
            </div>
            <!-- Duration in days -->
            <div class="col-md-3">
              <label class="form-label">Days</label>
              <input type="number" name="days[]" class="form-control days" placeholder="Days" min="1" required>
              <div class="invalid-feedback">Enter number of days.</div>
            </div>
          </div>
        </div>

        <!-- Submit buttons -->
        <button type="submit" class="btn btn-success mt-3">💾 Submit Report</button>
        <a href="patient_history.php?patient_id=<?= (int)$patient_id ?>" class="btn btn-info mt-3">📜 View Medical History</a>
    </form>
</div>

<script>
// MED_ROWS comes from PHP: contains all medicine variations for this specialization
const MED_ROWS = <?= json_encode($medRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

/* Utility functions to fetch forms and strengths for a given medicine */
function unique(arr){ return Array.from(new Set(arr)).filter(v => v !== null && v !== undefined); }
function getFormsForName(name){
  return unique(MED_ROWS.filter(r => r.name === name).map(r => r.dosage_form)).sort((a,b)=>a.localeCompare(b));
}
function getStrengthsFor(name, form){
  return unique(MED_ROWS.filter(r => r.name === name && r.dosage_form === form).map(r => r.strength))
         .sort((a,b)=>a.localeCompare(b, undefined, {numeric:true, sensitivity:'base'}));
}
function getIdFor(name, form, strength){
  const found = MED_ROWS.find(r => r.name === name && r.dosage_form === form && r.strength === strength);
  return found ? found.id : '';
}

/* Function to wire dropdowns so that:
   - Selecting a medicine name enables the form dropdown
   - Selecting a form enables the strength dropdown
   - Selecting strength fills the hidden fields */
function wireRow(row){
  const nameSel = row.querySelector('.med-name');
  const formSel = row.querySelector('.med-form');
  const strSel  = row.querySelector('.med-strength');

  const idInput = row.querySelector('.med-id');
  const nameH   = row.querySelector('.med-name-hidden');
  const formH   = row.querySelector('.med-form-hidden');
  const strH    = row.querySelector('.med-strength-hidden');

  function resetFormSelect(){
    formSel.innerHTML = '<option value="">-- Select Form --</option>';
    formSel.disabled = true;
    formSel.classList.remove('is-invalid');
  }
  function resetStrengthSelect(){
    strSel.innerHTML = '<option value="">-- Select Strength --</option>';
    strSel.disabled = true;
    strSel.classList.remove('is-invalid');
  }
  function clearHidden(){
    idInput.value = '';
    nameH.value = '';
    formH.value = '';
    strH.value = '';
  }

  // When medicine name changes
  nameSel.addEventListener('change', () => {
    const name = nameSel.value;
    resetFormSelect();
    resetStrengthSelect();
    clearHidden();

    if (!name){ nameSel.classList.add('is-invalid'); return; }
    nameSel.classList.remove('is-invalid');

    const forms = getFormsForName(name);
    forms.forEach(f => {
      const opt = document.createElement('option');
      opt.value = f;
      opt.textContent = f || '(unspecified)';
      formSel.appendChild(opt);
    });
    formSel.disabled = forms.length === 0;

    // If only one form exists, auto-select it
    if (forms.length === 1) {
      formSel.value = forms[0];
      formSel.dispatchEvent(new Event('change'));
    }
  });

  // When form changes
  formSel.addEventListener('change', () => {
    const name = nameSel.value;
    const form = formSel.value;
    resetStrengthSelect();
    idInput.value = '';
    formH.value = '';
    strH.value = '';
    if (!form){ formSel.classList.add('is-invalid'); return; }
    formSel.classList.remove('is-invalid');

    const strengths = getStrengthsFor(name, form);
    strengths.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s;
      opt.textContent = s || '(unspecified)';
      strSel.appendChild(opt);
    });
    strSel.disabled = strengths.length === 0;

    // If only one strength exists, auto-select it
    if (strengths.length === 1) {
      strSel.value = strengths[0];
      strSel.dispatchEvent(new Event('change'));
    }
  });

  // When strength changes
  strSel.addEventListener('change', () => {
    const name = nameSel.value;
    const form = formSel.value;
    const strength = strSel.value;

    if (!strength){ strSel.classList.add('is-invalid'); return; }
    const id = getIdFor(name, form, strength);
    if (!id){
      strSel.classList.add('is-invalid');
      clearHidden();
      return;
    }
    strSel.classList.remove('is-invalid');

    // Fill hidden inputs for submission
    idInput.value = id;
    nameH.value = name;
    formH.value = form;
    strH.value = strength;
  });
}

// Wire up the first medicine row
document.querySelectorAll('.medicine-entry').forEach(wireRow);

/* Allow doctor to add more medicine rows */
function addMedicine(){
  const container = document.getElementById('medicine-container');
  const first = container.querySelector('.medicine-entry');
  const clone = first.cloneNode(true);

  // reset controls for the new row
  clone.querySelector('.med-name').value = '';
  const formSel = clone.querySelector('.med-form');
  formSel.innerHTML = '<option value="">-- Select Form --</option>';
  formSel.disabled = true;

  const strSel = clone.querySelector('.med-strength');
  strSel.innerHTML = '<option value="">-- Select Strength --</option>';
  strSel.disabled = true;

  clone.querySelector('.med-id').value = '';
  clone.querySelector('.med-name-hidden').value = '';
  clone.querySelector('.med-form-hidden').value = '';
  clone.querySelector('.med-strength-hidden').value = '';
  clone.querySelectorAll('input[type="number"]').forEach(i => i.value = '');

  container.appendChild(clone);
  wireRow(clone);
}

// Final check before submitting form
document.getElementById('reportForm').addEventListener('submit', (e) => {
  const alertBox = document.getElementById('formAlert');
  alertBox.classList.add('d-none');
  alertBox.textContent = '';

  let ok = true;
  const diag = document.getElementById('diagnosis');
  const desc = document.getElementById('description');

  // Ensure diagnosis and description are filled
  if (!diag.value.trim()){ ok = false; diag.classList.add('is-invalid'); } else { diag.classList.remove('is-invalid'); }
  if (!desc.value.trim()){ ok = false; desc.classList.add('is-invalid'); } else { desc.classList.remove('is-invalid'); }

  // Check each medicine row for completeness
  document.querySelectorAll('.medicine-entry').forEach((row, idx) => {
    const nameSel = row.querySelector('.med-name');
    const formSel = row.querySelector('.med-form');
    const strSel  = row.querySelector('.med-strength');
    const idInput = row.querySelector('.med-id');
    const pills   = row.querySelector('.pills-per-day');
    const days    = row.querySelector('.days');

    if (!nameSel.value) { ok = false; nameSel.classList.add('is-invalid'); }
    if (!formSel.value) { ok = false; formSel.classList.add('is-invalid'); }
    if (!strSel.value)  { ok = false; strSel.classList.add('is-invalid'); }
    if (!idInput.value) { ok = false; strSel.classList.add('is-invalid'); }
    if (!pills.value || Number(pills.value) < 1) { ok = false; pills.classList.add('is-invalid'); } else { pills.classList.remove('is-invalid'); }
    if (!days.value  || Number(days.value)  < 1) { ok = false; days.classList.add('is-invalid'); }  else { days.classList.remove('is-invalid'); }
  });

  // If anything is missing, stop submission and show error
  if (!ok) {
    e.preventDefault();
    alertBox.textContent = 'Please complete all required fields (highlighted below).';
    alertBox.classList.remove('d-none');
    alertBox.scrollIntoView({behavior:'smooth', block:'start'});
  }
});
</script>
</body>
</html>
