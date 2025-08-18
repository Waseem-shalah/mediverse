<?php
// get_available_dates.php
session_start();
require_once 'config.php';

// Return JSON always
header('Content-Type: application/json; charset=utf-8');

// Basic auth guard (adjust if you allow guests to browse)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['doctor_id']) || !ctype_digit($_GET['doctor_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid doctor_id']);
    exit;
}

$doctor_id = (int)$_GET['doctor_id'];

/**
 * A date is available iff it has at least one slot that is NOT booked
 * (i.e., there is no non-canceled appointment bound to that slot).
 *
 * We LEFT JOIN appointments on slot_id with the condition a.status <> 'canceled';
 * if a.id IS NULL => no non-canceled appointment, so the slot is free.
 */
$sql = "
    SELECT DISTINCT s.date
      FROM slots s
 LEFT JOIN appointments a
        ON a.slot_id = s.id
       AND a.status <> 'canceled'
     WHERE s.doctor_id = ?
       AND s.date >= CURDATE()
       AND a.id IS NULL
  ORDER BY s.date ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: '.$conn->error]);
    exit;
}
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$res = $stmt->get_result();

$dates = [];
while ($row = $res->fetch_assoc()) {
    // format as YYYY-MM-DD (already DATE in DB)
    $dates[] = $row['date'];
}

echo json_encode($dates);
