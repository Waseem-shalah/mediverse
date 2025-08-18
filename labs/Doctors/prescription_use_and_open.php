<?php
// prescription_use_and_open.php
// One-time “open prescription”: marks a patient's prescription as USED atomically
// and redirects to the printable page. Uses CSRF + row locking to prevent re-use.

session_start();
require_once 'config.php';

// --- AuthZ: only logged-in patients ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(403);
    exit('Forbidden');
}

$patient_id = (int)$_SESSION['user_id'];
$pm_id      = isset($_POST['pm_id']) ? (int)$_POST['pm_id'] : 0;
$csrf       = $_POST['csrf'] ?? '';

// --- Basic input/CSRF checks ---
if ($pm_id <= 0) {
    http_response_code(400);
    exit('Bad request');
}
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(400);
    exit('Invalid CSRF token');
}

// --- Start a R/W transaction so we can lock, check, and update atomically ---
$conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

try {
    // Lock this prescription row to block concurrent opens
    $q = $conn->prepare("
        SELECT used_status
        FROM prescribed_medicines
        WHERE id = ? AND patient_id = ?
        FOR UPDATE
    ");
    $q->bind_param("ii", $pm_id, $patient_id);
    $q->execute();
    $res = $q->get_result();
    if ($res->num_rows !== 1) {
        throw new Exception('Prescription item not found.');
    }
    $row = $res->fetch_assoc();

    // Must be ISSUED to allow a first-time open
    if ($row['used_status'] !== 'ISSUED') {
        throw new Exception('This prescription has already been used.');
    }

    // Mark as USED (idempotent via WHERE used_status='ISSUED')
    $u = $conn->prepare("
        UPDATE prescribed_medicines
        SET used_status = 'USED', used_at = NOW()
        WHERE id = ? AND patient_id = ? AND used_status = 'ISSUED'
    ");
    $u->bind_param("ii", $pm_id, $patient_id);
    $u->execute();

    // If no row was updated, someone else won the race
    if ($u->affected_rows !== 1) {
        throw new Exception('Unable to mark as used (already used).');
    }

    // All good — persist the change
    $conn->commit();

    // Send user to the printable page (opens in the modal’s target=_blank)
    header("Location: generate_prescription.php?pm_id=" . $pm_id);
    exit();

} catch (Throwable $e) {
    // Roll back and show a friendly flash message on the list page
    $conn->rollback();
    $_SESSION['flash_err'] = $e->getMessage();
    header("Location: prescriptions.php");
    exit();
}
