<?php
// get_available_slots.php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Basic auth guard (adjust if you allow guests to browse)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['doctor_id'], $_GET['date'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing doctor_id or date']);
    exit;
}

$doctor_id = (int)$_GET['doctor_id'];
$date      = $_GET['date'];

// Very light validation (expects YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

/**
 * A slot is available if there is NO non-canceled appointment bound to it.
 * Same LEFT JOIN trick as above.
 */
$sql = "
    SELECT s.id, s.time
      FROM slots s
 LEFT JOIN appointments a
        ON a.slot_id = s.id
       AND a.status <> 'canceled'
     WHERE s.doctor_id = ?
       AND s.date = ?
       AND a.id IS NULL
  ORDER BY s.time ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: '.$conn->error]);
    exit;
}
$stmt->bind_param('is', $doctor_id, $date);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
    $out[] = [
        'id'   => (int)$row['id'],
        'time' => substr($row['time'], 0, 5) // HH:MM
    ];
}

echo json_encode($out);
