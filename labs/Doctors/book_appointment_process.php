<?php
session_start();
require_once 'config.php';
date_default_timezone_set('Asia/Jerusalem'); // Set timezone for correct local datetime

// 🔒 Only logged-in patients can book appointments
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(403);
    exit('Unauthorized.');
}

// Collect inputs safely
$patientId = (int)($_SESSION['user_id'] ?? 0);
$doctorId  = (int)($_POST['doctor_id'] ?? 0);
$slotId    = (int)($_POST['time'] ?? 0); // slot chosen from radio button
$reason    = trim($_POST['reason'] ?? '');

// Basic validation – doctor and slot must exist
if (!$doctorId || !$slotId) {
    http_response_code(400);
    exit('Missing required fields.');
}

/**
 * 🛠 Helper function: 
 * Tries to build a proper datetime (Y-m-d H:i:s) from a slot row.
 * Your slots table might have different column names, so we check many.
 */
function compose_datetime_from_slot_row(array $slot): ?string {
    // 1) Direct full datetime fields
    foreach (['slot_datetime','start_datetime','starts_at','start_at','datetime','date_time','appointment_datetime'] as $c) {
        if (!empty($slot[$c]) && strtoupper($slot[$c]) !== '0000-00-00 00:00:00') {
            $dt = date_create(trim($slot[$c]));
            if ($dt) return $dt->format('Y-m-d H:i:s');
        }
    }

    // 2) Combine separate date + time fields
    $dateCols = ['slot_date','date','day'];
    $timeCols = ['slot_time','time','start_time','hour','start'];
    $d = null; $t = null;
    foreach ($dateCols as $c) if (!empty($slot[$c])) { $d = trim($slot[$c]); break; }
    foreach ($timeCols as $c) if (!empty($slot[$c])) { $t = trim($slot[$c]); break; }

    if ($d && $t) {
        $dt = date_create("$d $t");
        if ($dt) return $dt->format('Y-m-d H:i:s');
    }

    // 3) If only time exists, assume today
    if ($t && !$d) {
        $d = date('Y-m-d');
        $dt = date_create("$d $t");
        if ($dt) return $dt->format('Y-m-d H:i:s');
    }
    return null; // Couldn’t parse
}

// Build the full URL for "View My Appointments"
function view_appointments_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    return $host ? ($scheme.'://'.$host.'/labs/Doctors/my_appointments.php') : 'my_appointments.php';
}

// Begin SQL transaction (saves us from partial inserts if something fails)
$conn->begin_transaction();

try {
    // ✅ Step 1: Get the slot info
    $q = $conn->prepare("SELECT * FROM `slots` WHERE `id` = ? AND `doctor_id` = ? LIMIT 1");
    if (!$q) throw new RuntimeException('Prepare failed [slot lookup]: '.$conn->error);
    $q->bind_param("ii", $slotId, $doctorId);
    $q->execute();
    $slot = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$slot) throw new RuntimeException('Slot not found for this doctor.');

    // ✅ Step 2: Convert slot row into a real datetime
    $dt = compose_datetime_from_slot_row($slot);
    if (!$dt) throw new RuntimeException('Slot datetime missing/invalid.');

    // ✅ Step 3: Make sure the slot isn’t already taken
    $q = $conn->prepare("SELECT `id` FROM `appointments` WHERE `slot_id` = ? LIMIT 1");
    $q->bind_param("i", $slotId);
    $q->execute();
    if ($q->get_result()->num_rows > 0) { 
        $q->close(); 
        throw new RuntimeException('This slot is already booked.'); 
    }
    $q->close();

    // ✅ Step 4: Insert the appointment
    $ins = $conn->prepare("
        INSERT INTO `appointments`
          (`patient_id`,`doctor_id`,`appointment_datetime`,`status`,
           `visit_duration_minutes`,`follow_up_required`,`reason`,`slot_id`,`created_at`)
        VALUES (?,?,?,'scheduled',30,0,?,?,NOW())
    ");
    $ins->bind_param("iissi", $patientId, $doctorId, $dt, $reason, $slotId);
    $ins->execute();
    $appointmentId = (int)$conn->insert_id;
    $ins->close();

    // Commit everything to DB
    $conn->commit();

    // ✅ Step 5: Fetch info for confirmation email
    $infoSql = "
      SELECT 
        a.id,
        a.appointment_datetime,
        COALESCE(a.visit_duration_minutes, 30) AS duration_minutes,
        p.name  AS patient_name,
        p.email AS patient_email,
        d.name  AS doctor_name
      FROM appointments a
      JOIN users p ON p.id = a.patient_id
      JOIN users d ON d.id = a.doctor_id
      WHERE a.id = ?
      LIMIT 1
    ";
    $q = $conn->prepare($infoSql);
    $q->bind_param("i", $appointmentId);
    $q->execute();
    $inf = $q->get_result()->fetch_assoc();
    $q->close();

    // ✅ Step 6: Send email confirmation (if patient has email)
    if ($inf && !empty($inf['patient_email'])) {
        $tz       = new DateTimeZone('Asia/Jerusalem');
        $startLoc = new DateTime($inf['appointment_datetime'], $tz);
        $endLoc   = (clone $startLoc)->modify('+' . (int)$inf['duration_minutes'] . ' minutes');
        $startUtc = (clone $startLoc)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $endUtc   = (clone $endLoc)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');

        $viewURL = view_appointments_url();
        $summary = 'Appointment with Dr. ' . $inf['doctor_name'];
        $gcal    = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
                    . '&text=' . rawurlencode($summary)
                    . '&dates=' . $startUtc . '/' . $endUtc
                    . '&details=' . rawurlencode('MediVerse appointment');

        $whenTxt = $startLoc->format('l, F j, Y \a\t H:i');
        $pn = htmlspecialchars($inf['patient_name'] ?? '');
        $dn = htmlspecialchars($inf['doctor_name'] ?? '');

        // Build professional HTML email
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;color:#111827">'
              . '<h2 style="margin:0 0 10px">Appointment Confirmed</h2>'
              . '<p>Hi ' . $pn . ', your appointment with <b>Dr. ' . $dn
              . '</b> is confirmed for <b>' . $whenTxt . '</b>.</p>'
              . '<p>'
              . '<a href="' . $viewURL . '" target="_blank" style="padding:10px 14px;border-radius:8px;background:#0d6efd;color:#fff;text-decoration:none;font-weight:600;margin-right:8px">View My Appointments</a>'
              . '<a href="' . $gcal . '" target="_blank" style="padding:10px 14px;border-radius:8px;background:#f1f3f5;color:#0d6efd;border:1px solid #d0d7de;text-decoration:none;font-weight:600">Add to Google Calendar</a>'
              . '</p>'
              . '</div>';

        $subject = 'Your appointment is confirmed – Dr. ' . $inf['doctor_name'];

        // Try PHPMailer, fallback to PHP mail()
        @require_once __DIR__ . '/vendor/autoload.php';
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->Port       = 587;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAuth   = true;
                $mail->Username   = 'mediverse259@gmail.com';
                $mail->Password   = 'yrecnfqylehxregz'; // app password
                $mail->setFrom('mediverse259@gmail.com', 'MediVerse');
                $mail->addAddress($inf['patient_email'], $inf['patient_name']);
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body    = $html;
                $mail->AltBody = strip_tags('View: ' . $viewURL . ' | Calendar: ' . $gcal);
                $mail->send();
            } catch (\Throwable $e) {
                // fallback
                @mail($inf['patient_email'], $subject, $html, "Content-Type: text/html; charset=UTF-8\r\n");
            }
        } else {
            @mail($inf['patient_email'], $subject, $html, "Content-Type: text/html; charset=UTF-8\r\n");
        }
    }

    // ✅ Step 7: Redirect patient to success page
    header("Location: booking_success.php");
    exit();

} catch (\Throwable $e) {
    // Roll back if anything failed
    $conn->rollback();
    http_response_code(400);
    exit('Booking failed: ' . $e->getMessage());
}
