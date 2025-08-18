<?php
// Doctor/edit_report.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config.php';

// ---- Access control: only logged-in doctors can reach this page ----
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

// ---- Read the appointment ID from query string ----
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
if ($appointment_id <= 0) {
    // If appointment id is missing or invalid, go back to appointments list
    header("Location: view_appointments.php");
    exit();
}

/* ---------- Helper functions (DB fetchers) ---------- */

/**
 * Fetch basic appointment data along with patient name.
 * Returns: ['id','patient_id','doctor_id','patient_name'] or null
 */
function fetch_appointment(mysqli $conn, int $appointment_id) {
    $stmt = $conn->prepare("
        SELECT a.id, a.patient_id, a.doctor_id, u.name AS patient_name
        FROM appointments a
        JOIN users u ON u.id = a.patient_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Fetch the latest medical report for this appointment (if any).
 * Returns: ['id','appointment_id','patient_id','doctor_id','diagnosis','description','created_at'] or null
 */
function fetch_existing_report(mysqli $conn, int $appointment_id) {
    $stmt = $conn->prepare("
        SELECT id, appointment_id, patient_id, doctor_id, diagnosis, description, created_at
        FROM medical_reports
        WHERE appointment_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Fetch existing prescribed medicines for a given report id.
 * Each item includes medicine info and dosage details.
 */
function fetch_existing_prescriptions(mysqli $conn, int $report_id): array {
    $items = [];
    $stmt = $conn->prepare("
        SELECT pm.medicine_id, pm.pills_per_day, pm.duration_days,
               m.name, COALESCE(m.dosage_form,'') AS dosage_form, COALESCE(m.strength,'') AS strength
        FROM prescribed_medicines pm
        LEFT JOIN medicines m ON m.id = pm.medicine_id
        WHERE pm.report_id = ?
    ");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $items[] = $row; }
    $stmt->close();
    return $items;
}

/**
 * Fetch the medicines catalog filtered by the doctor's specialization.
 * Supports two possible junction table names (plural/singular) to match your DB.
 */
function fetch_meds_for_specialization(mysqli $conn, int $spec_id): array {
    $sql = "
        SELECT m.id, m.name, COALESCE(m.dosage_form,'') AS dosage_form, COALESCE(m.strength,'') AS strength
        FROM medicines m
        JOIN medicine_specializations ms ON ms.medicine_id = m.id
        WHERE ms.specialization_id = ?
        ORDER BY m.name, m.dosage_form, m.strength
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Fallback if your DB uses singular table name "medicine_specialization"
        $sql = "
            SELECT m.id, m.name, COALESCE(m.dosage_form,'') AS dosage_form, COALESCE(m.strength,'') AS strength
            FROM medicines m
            JOIN medicine_specialization ms ON ms.medicine_id = m.id
            WHERE ms.specialization_id = ?
            ORDER BY m.name, m.dosage_form, m.strength
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { die("Prepare failed (meds): ".$conn->error); }
    }
    $stmt->bind_param("i", $spec_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    return $rows;
}

/* ---------- Gatekeeping & data bootstrapping ---------- */

// 1) Ensure the appointment exists and belongs to the logged-in doctor
$appt = fetch_appointment($conn, $appointment_id);
if (!$appt || (int)$appt['doctor_id'] !== (int)$_SESSION['user_id']) {
    // If not owned, bounce back with an error flag
    header("Location: view_appointments.php?report=error");
    exit();
}

// 2) Fetch doctor's specialization (used to filter medicine catalog)
$doctor_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT specialization_id FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$specRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$specialization_id = (int)($specRow['specialization_id'] ?? 0);
if ($specialization_id <= 0) { die("Your profile has no specialization set."); }

// 3) Make sure there is an existing report; otherwise redirect to the write page
$report = fetch_existing_report($conn, $appointment_id);
if (!$report) {
    header("Location: write_report.php?appointment_id=".$appointment_id);
    exit();
}

/* ---------- POST: handle "Save Changes & Resend" ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We replace the old report with a new one (via save_report.php).
    // To keep data consistent, we first delete the old prescriptions & report
    // in a single transaction.
    $conn->begin_transaction();
    try {
        // Delete all prescribed medicines tied to this appointment's report(s)
        $delPm = $conn->prepare("
            DELETE pm FROM prescribed_medicines pm
            JOIN medical_reports mr ON mr.id = pm.report_id
            WHERE mr.appointment_id = ?
        ");
        $delPm->bind_param("i", $appointment_id);
        $delPm->execute(); 
        $delPm->close();

        // Delete the report record(s) for this appointment
        $delR = $conn->prepare("DELETE FROM medical_reports WHERE appointment_id = ?");
        $delR->bind_param("i", $appointment_id);
        $delR->execute(); 
        $delR->close();

        // If both deletions succeeded, commit
        $conn->commit();
    } catch (Throwable $e) {
        // On error, rollback and return user to list with an error
        $conn->rollback();
        header("Location: view_appointments.php?report=error");
        exit();
    }

    // Now forward the (possibly updated) POST data to the existing saver.
    // save_report.php will create a fresh report + prescriptions based on POST.
    $_POST['appointment_id'] = $appointment_id;
    $_POST['patient_id']     = $appt['patient_id'];
    require __DIR__ . '/save_report.php';
    exit();
}

/* ---------- GET: render the edit form ---------- */

// Load current prescriptions to prefill rows
$existing_presc = fetch_existing_prescriptions($conn, (int)$report['id']);

// Load the medicines catalog filtered by specialization
$catalog = fetch_meds_for_specialization($conn, $specialization_id);

// Build a structure for the front-end to populate dependent selects:
// GROUPS[name] = {
//   forms: [ ... ],
//   strengthsByForm: { form => [ ...strengths ] },
//   idByCombo: { "form||strength" => medicine_id }
// }
// NAMES = [ list of distinct medicine names ]
$GROUPS = []; 
$NAMES  = [];
foreach ($catalog as $row) {
    $name = $row['name']; 
    $form = trim((string)$row['dosage_form']); 
    $str  = trim((string)$row['strength']); 
    $id   = (int)$row['id'];

    if (!isset($GROUPS[$name])) {
        $GROUPS[$name] = ['forms'=>[], 'strengthsByForm'=>[], 'idByCombo'=>[]]; 
        $NAMES[]       = $name;
    }

    if ($form !== '' && !in_array($form, $GROUPS[$name]['forms'], true)) {
        $GROUPS[$name]['forms'][] = $form;
    }

    if (!isset($GROUPS[$name]['strengthsByForm'][$form])) {
        $GROUPS[$name]['strengthsByForm'][$form] = [];
    }

    if ($str !== '' && !in_array($str, $GROUPS[$name]['strengthsByForm'][$form], true)) {
        $GROUPS[$name]['strengthsByForm'][$form][] = $str;
    }

    // Map (form, strength) -> medicine id for quick lookup
    $GROUPS[$name]['idByCombo'][$form.'||'.$str] = $id;
}

// Escape text fields for safe display in HTML inputs/textareas
$diagnosis   = htmlspecialchars($report['diagnosis'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$description = htmlspecialchars($report['description'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Include the logged-in navbar (kept as-is)
include '../navbar_loggedin.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Edit Report | MediVerse</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  /* Simple layout tweaks for the dynamic medicine rows */
  .medicine-row{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
  .medicine-row .form-select,.medicine-row .form-control{max-width:220px}
  .badge-warn{background:#fff3cd;color:#664d03;border:1px solid #ffe69c}
</style>
</head>
<body>
<div class="container mt-4">
  <!-- Header with appointment & patient info -->
  <h3>
    Edit Report – Appointment #<?= (int)$appointment_id ?> 
    (Patient: <?= htmlspecialchars($appt['patient_name']) ?>)
  </h3>

  <div class="card mt-3">
    <div class="card-body">
      <!-- The same page handles POST to update the report -->
      <form method="POST" action="">
        <!-- Hidden identifiers to keep context -->
        <input type="hidden" name="appointment_id" value="<?= (int)$appointment_id ?>">
        <input type="hidden" name="patient_id" value="<?= (int)$appt['patient_id'] ?>">

        <!-- Diagnosis (single line) -->
        <div class="mb-3">
          <label class="form-label">Diagnosis</label>
          <input type="text" name="diagnosis" class="form-control" required value="<?= $diagnosis ?>">
        </div>

        <!-- Description (multi line) -->
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="5" required><?= $description ?></textarea>
        </div>

        <!-- Dynamic list of prescribed medicines -->
        <div class="mb-2">
          <label class="form-label">Prescribed Medicines</label>

          <div id="medList">
            <?php if (!empty($existing_presc)): foreach ($existing_presc as $row):
                  // Preload current values from DB
                  $curId   = (int)($row['medicine_id']??0); 
                  $curName = (string)($row['name']??'');
                  $curForm = (string)($row['dosage_form']??''); 
                  $curStr  = (string)($row['strength']??'');

                  // If the current medicine name does not exist in specialization catalog,
                  // we mark it as "legacy" to keep it selectable in the UI.
                  $legacy  = !array_key_exists($curName,$GROUPS); 
            ?>
              <!-- One editable medicine row -->
              <div class="medicine-row" data-row
                   data-cur-name="<?= htmlspecialchars($curName,ENT_QUOTES) ?>"
                   data-cur-form="<?= htmlspecialchars($curForm,ENT_QUOTES) ?>"
                   data-cur-str="<?= htmlspecialchars($curStr,ENT_QUOTES) ?>"
                   data-cur-id="<?= (int)$curId ?>" data-legacy="<?= $legacy?'1':'0' ?>">

                <!-- Cascading selects: Name -> Form -> Strength -->
                <select class="form-select med-name" required></select>
                <select class="form-select med-form" required></select>
                <select class="form-select med-strength" required></select>

                <!-- Hidden fields to submit selected combo back to PHP -->
                <input type="hidden" name="medicine_ids[]" class="med-id" value="<?= (int)$curId ?>">
                <input type="hidden" name="med_names[]" class="med-name-hidden" value="">
                <input type="hidden" name="med_forms[]" class="med-form-hidden" value="">
                <input type="hidden" name="med_strengths[]" class="med-strength-hidden" value="">

                <!-- Dosage details -->
                <input type="number" name="pills_per_day[]" class="form-control" placeholder="Pills/day" min="1" value="<?= (int)$row['pills_per_day'] ?>" required>
                <input type="number" name="days[]" class="form-control" placeholder="Days" min="1" value="<?= (int)$row['duration_days'] ?>" required>

                <!-- Remove this row (front-end only; not submitted) -->
                <button type="button" class="btn btn-outline-danger" onclick="removeRow(this)">Remove</button>

                <!-- If legacy, visually warn the editor but still allow keeping it -->
                <?php if ($legacy): ?>
                  <span class="badge badge-warn">Legacy item (not in your specialization list)</span>
                <?php endif; ?>
              </div>
            <?php endforeach; else: ?>
              <!-- If there were no existing prescriptions, start with an empty row -->
              <div class="medicine-row" data-row data-cur-name="" data-cur-form="" data-cur-str="" data-cur-id="0" data-legacy="0">
                <select class="form-select med-name" required></select>
                <select class="form-select med-form" required></select>
                <select class="form-select med-strength" required></select>

                <input type="hidden" name="medicine_ids[]" class="med-id" value="0">
                <input type="hidden" name="med_names[]" class="med-name-hidden" value="">
                <input type="hidden" name="med_forms[]" class="med-form-hidden" value="">
                <input type="hidden" name="med_strengths[]" class="med-strength-hidden" value="">

                <input type="number" name="pills_per_day[]" class="form-control" placeholder="Pills/day" min="1" required>
                <input type="number" name="days[]" class="form-control" placeholder="Days" min="1" required>

                <button type="button" class="btn btn-outline-danger" onclick="removeRow(this)">Remove</button>
              </div>
            <?php endif; ?>
          </div>

          <!-- Add new blank medicine row -->
          <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addRow()">+ Add Medicine</button>
        </div>

        <!-- Actions -->
        <div class="mt-4">
          <a href="view_appointments.php" class="btn btn-secondary">Cancel</a>
          <!-- Submitting will delete+recreate the report and re-send (as in your existing flow) -->
          <button type="submit" class="btn btn-warning">Save Changes & Resend</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ------------------------
// Front-end dynamic logic
// ------------------------

// GROUPS & NAMES were prepared in PHP above to drive the dependent selects
const GROUPS = <?= json_encode($GROUPS, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const NAMES  = <?= json_encode($NAMES,  JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

/**
 * Remove a medicine row from the DOM (does not affect server until submit).
 */
function removeRow(btn){
  const row = btn.closest('.medicine-row'); 
  if(row) row.remove();
}

/**
 * Helper: Populate a <select> with given array of values.
 * Optionally, provide `selected` and a formatter `f` to display text.
 */
function setOptions(sel, arr, selected='', f=null){
  sel.innerHTML='';
  arr.forEach(v=>{
    const o=document.createElement('option');
    o.value=v;
    o.textContent = f ? f(v) : v;
    if(v===selected) o.selected=true;
    sel.appendChild(o);
  });
}

/**
 * Sync hidden inputs with the currently selected visible values
 * so PHP receives the final chosen name/form/strength.
 */
function updateHidden(row){
  row.querySelector('.med-name-hidden').value     = row.querySelector('.med-name').value || '';
  row.querySelector('.med-form-hidden').value     = row.querySelector('.med-form').value || '';
  row.querySelector('.med-strength-hidden').value = row.querySelector('.med-strength').value || '';
}

/**
 * Determine the medicine_id that matches (name, form, strength).
 * If no match, default to 0 (e.g., legacy or unmatched combination).
 */
function updateMedId(row){
  const name = row.querySelector('.med-name').value;
  const form = row.querySelector('.med-form').value;
  const str  = row.querySelector('.med-strength').value;

  let id = 0;
  if (GROUPS[name] && GROUPS[name]['idByCombo'][form+'||'+str] !== undefined){
    id = GROUPS[name]['idByCombo'][form+'||'+str];
  }
  row.querySelector('.med-id').value = id;
}

/**
 * When the user changes the medicine name:
 * - Populate available forms for that name
 * - Then trigger form change to populate strengths
 */
function onNameChange(row, preserve=false){
  const nameSel = row.querySelector('.med-name');
  const formSel = row.querySelector('.med-form');
  const strSel  = row.querySelector('.med-strength');
  const name    = nameSel.value;

  // If name isn't in catalog (legacy), lock form/strength to existing values
  if (!GROUPS[name]) {
    const curForm = row.dataset.curForm || '';
    const curStr  = row.dataset.curStr  || '';
    setOptions(formSel, [curForm], curForm);
    setOptions(strSel,  [curStr],  curStr);
    updateMedId(row);
    updateHidden(row);
    return;
  }

  // Otherwise, load forms for the chosen name
  const forms = GROUPS[name]['forms'] || [];
  let toForm  = forms[0] || '';

  // If preserving preloaded value (when initializing), try to keep it
  if (preserve){
    const cur = row.dataset.curForm || '';
    if (forms.includes(cur)) toForm = cur;
  }

  setOptions(formSel, forms, toForm);
  onFormChange(row, preserve); // cascade to strengths
}

/**
 * When the user changes the form:
 * - Populate strengths allowed for that (name, form) combo
 */
function onFormChange(row, preserve=false){
  const name = row.querySelector('.med-name').value;
  const form = row.querySelector('.med-form').value;

  const strengths = (GROUPS[name] && GROUPS[name]['strengthsByForm'][form])
    ? GROUPS[name]['strengthsByForm'][form] : [];

  let toStr = strengths[0] || '';

  // Preserve initial preloaded strength if valid
  if (preserve){
    const cur = row.dataset.curStr || '';
    if (strengths.includes(cur)) toStr = cur;
  }

  setOptions(row.querySelector('.med-strength'), strengths, toStr);

  // After changing form/strength, update the hidden id + fields
  updateMedId(row);
  updateHidden(row);
}

/** Strength change only affects mapping + hidden fields */
function onStrengthChange(row){
  updateMedId(row);
  updateHidden(row);
}

/**
 * Initialize a medicine row:
 * - Build Name/Form/Strength selects
 * - Wire up change handlers
 * - Try to preserve preloaded values (legacy supported)
 */
function initRow(row){
  const nameSel = row.querySelector('.med-name');
  const formSel = row.querySelector('.med-form');
  const strSel  = row.querySelector('.med-strength');

  const legacy  = row.dataset.legacy === '1';
  const curName = row.dataset.curName || '';
  const curForm = row.dataset.curForm || '';
  const curStr  = row.dataset.curStr  || '';

  // Prepare available names; if legacy, prepend the unknown name so it appears
  let names = [...NAMES];
  if (legacy && curName && !names.includes(curName)){
    names.unshift(curName);
  }

  // Populate name select; default to current or first
  setOptions(nameSel, names, curName || names[0]);

  // Hook change listeners
  nameSel.onchange = () => onNameChange(row, false);
  formSel.onchange = () => onFormChange(row, false);
  strSel.onchange  = () => onStrengthChange(row);

  // First-time cascade; preserve DB values when possible
  onNameChange(row, true);
  if (curForm) formSel.value = curForm;
  if (curStr)  strSel.value  = curStr;
  onFormChange(row, true);
}

/**
 * Add a brand-new, empty medicine row to the list.
 * Keeps your original innerHTML unchanged (including your "dont touch..." line).
 */
function addRow(){
  const medList = document.getElementById('medList');

  // Create the container div for the new row
  const div = document.createElement('div');
  div.className = 'medicine-row';
  div.setAttribute('data-row','');
  div.dataset.curName = '';
  div.dataset.curForm = '';
  div.dataset.curStr  = '';
  div.dataset.curId   = '0';
  div.dataset.legacy  = '0';

  // Insert your exact original row HTML (unchanged)
  div.innerHTML = `<select class="form-select med-name" required></select>
  <select class="form-select med-form" required></select>
  <select class="form-select med-strength" required></select>
  <input type="hidden" name="medicine_ids[]" class="med-id" value="0">
  <input type="hidden" name="med_names[]" class="med-name-hidden" value="">
  <input type="hidden" name="med_forms[]" class="med-form-hidden" value="">
  <input type="hidden" name="med_strengths[]" class="med-strength-hidden" value="">
  <input type="number" name="pills_per_day[]" class="form-control" placeholder="Pills/day" min="1" required>
  <input type="number" name="days[]" class="form-control" placeholder="Days" min="1" required>

dont touch anything else
  <button type="button" class="btn btn-outline-danger" onclick="removeRow(this)">Remove</button>`;

  medList.appendChild(div);

  // Initialize the new row’s selects based on GROUPS/NAMES
  initRow(div);

  // Ensure hidden fields are consistent
  updateMedId(div);
  updateHidden(div);
}

// Initialize all pre-rendered rows on page load
document.querySelectorAll('.medicine-row').forEach(initRow);
</script>
</body>
</html>
