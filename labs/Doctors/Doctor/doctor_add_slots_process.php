<?php
// doctor_add_slots_process.php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

/* ---- Auth (adjust to your app rules) ---- */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']); exit;
}
$role = $_SESSION['role'] ?? '';
$doctor_id = (int)$_SESSION['user_id'];

/* If admins can add slots for any doctor, uncomment: */
// if ($role === 'admin' && isset($_POST['doctor_id']) && ctype_digit($_POST['doctor_id'])) {
//     $doctor_id = (int)$_POST['doctor_id'];
// }
if (!in_array($role, ['doctor','admin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']); exit;
}

/* ---- Input ---- */
$date = trim($_POST['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid date (YYYY-MM-DD)']); exit;
}

$times = [];
if (isset($_POST['times']) && is_array($_POST['times'])) {
    $times = $_POST['times'];
} elseif (isset($_POST['time'])) {
    $times = [$_POST['time']];
}
if (!$times) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No time(s) provided']); exit;
}

/* ---- Normalize to HH:MM:00 and dedupe in PHP ---- */
$norm = [];
foreach ($times as $t) {
    $t = trim((string)$t);
    if ($t === '') continue;

    $ts = strtotime($t);
    if ($ts === false) continue;

    $norm[] = date('H:i:00', $ts);
}
$norm = array_values(array_unique($norm));
if (!$norm) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No valid times after normalization']); exit;
}

/* 
   ---- Duplicate-safe insert WITHOUT DB schema changes ----
   We LOCK the slots table for a very short time, then:
   - check if (doctor_id,date,time) exists
   - insert only if missing
   This prevents race-condition duplicates even without a UNIQUE index.
*/
$inserted = 0; $skipped = 0;

try {
    // Exclusive lock over the slots table
    if (!$conn->query("LOCK TABLES slots WRITE")) {
        throw new Exception('Could not lock table: ' . $conn->error);
    }

    // Prepare queries once
    $check = $conn->prepare("SELECT id FROM slots WHERE doctor_id = ? AND date = ? AND time = ? LIMIT 1");
    if (!$check) throw new Exception('Prepare check failed: ' . $conn->error);

    $ins = $conn->prepare("INSERT INTO slots (doctor_id, date, time) VALUES (?, ?, ?)");
    if (!$ins) throw new Exception('Prepare insert failed: ' . $conn->error);

    foreach ($norm as $t) {
        $check->bind_param('iss', $doctor_id, $date, $t);
        if (!$check->execute()) throw new Exception('Check execute failed: ' . $check->error);
        $r = $check->get_result();
        if ($r && $r->num_rows > 0) {
            $skipped++;
            continue; // already exists -> skip
        }

        $ins->bind_param('iss', $doctor_id, $date, $t);
        if (!$ins->execute()) throw new Exception('Insert failed: ' . $ins->error);
        $inserted++;
    }

    $check->close();
    $ins->close();

} catch (Exception $e) {
    // Ensure we unlock if an error happens
    $conn->query("UNLOCK TABLES");
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

// Always unlock
$conn->query("UNLOCK TABLES");

// Done
echo json_encode([
    'ok'       => true,
    'date'     => $date,
    'times'    => $norm,
    'inserted' => $inserted,
    'skipped'  => $skipped
]);
