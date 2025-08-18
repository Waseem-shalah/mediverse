<?php
session_start();
require_once '../config.php';

// ✅ Only admins can add medicines
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: ../login.php'); 
  exit();
}

// 🔄 Helper: send user back with an error message
function back_with_error($msg) {
  $_SESSION['med_error'] = $msg;
  header('Location: add_medicine.php');
  exit();
}

// 📝 Collect and sanitize form input
$name   = trim($_POST['name']            ?? '');
$gen    = trim($_POST['generic_name']    ?? '');
$form   = trim($_POST['dosage_form']     ?? '');
$str    = trim($_POST['strength']        ?? '');
$is_otc = isset($_POST['is_otc']) ? (int)$_POST['is_otc'] : 0;
$is_rx  = isset($_POST['is_prescription_required']) ? (int)$_POST['is_prescription_required'] : 0;
$specs  = $_POST['specialization_ids']   ?? [];

// 🚨 Basic validation checks
if ($name === '' || $gen === '' || $form === '' || $str === '') {
  back_with_error('Please fill all required fields.');
}
if (!is_array($specs) || count($specs) === 0) {
  back_with_error('Please select at least one specialization.');
}

// ⚠️ Consistency check: can't be both OTC and require Rx
if ($is_otc === 1 && $is_rx === 1) {
  back_with_error('A medicine cannot be OTC and require prescription at the same time.');
}

// 🔒 Start DB transaction (so inserts roll back if something fails)
$conn->begin_transaction();

try {
  // 💊 Insert medicine into `medicines` table
  $stmt = $conn->prepare("
    INSERT INTO medicines
      (name, generic_name, dosage_form, strength, is_otc, created_at, updated_at, is_prescription_required)
    VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
  ");
  if (!$stmt) throw new Exception('Prepare failed: '.$conn->error);

  $stmt->bind_param("ssssii", $name, $gen, $form, $str, $is_otc, $is_rx);
  if (!$stmt->execute()) throw new Exception('Insert failed: '.$stmt->error);

  // 🔑 Get the new medicine's ID
  $med_id = $stmt->insert_id;
  $stmt->close();

  // 🔗 Link medicine to chosen specializations
  $link = $conn->prepare("
    INSERT INTO medicine_specializations (medicine_id, specialization_id) 
    VALUES (?, ?)
  ");
  if (!$link) throw new Exception('Prepare link failed: '.$conn->error);

  foreach ($specs as $sid) {
    $sid = (int)$sid; // make sure it's an integer
    $link->bind_param("ii", $med_id, $sid);
    if (!$link->execute()) throw new Exception('Link insert failed: '.$link->error);
  }
  $link->close();

  // ✅ All good → commit transaction
  $conn->commit();
  $_SESSION['med_success'] = 'Medicine added successfully.';
  header('Location: add_medicine.php');
  exit();

} catch (Exception $e) {
  // ❌ Something went wrong → undo everything
  $conn->rollback();
  back_with_error($e->getMessage());
}
