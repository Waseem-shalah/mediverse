<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor_id   = (int)$_SESSION['user_id'];
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Your doctor';

/* ── PHPMailer setup ─────────────────────────────────────────────────── */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/autoload.php';

/* Build app-root URL (parent of /Doctor) for absolute links in emails */
function app_root_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/\\'); // e.g. /Doctor
    $root   = preg_replace('~/Doctor/?$~', '', $dir);                // remove /Doctor
    return $scheme . '://' . $host . $root;
}

function sendApologyEmail(string $toEmail, string $toName, string $doctorName, string $date, string $time, string $bookUrl): bool {
    // Sanitize for HTML
    $toNameSafe     = htmlspecialchars($toName ?: 'Patient', ENT_QUOTES, 'UTF-8');
    $doctorNameSafe = htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8');
    $dateSafe       = htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
    $timeSafe       = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');
    $bookUrlSafe    = htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8');

    $subject = "Appointment Cancellation - Dr. {$doctorName} ( {$date}) at {$time}";

    $html = <<<HTML
<!doctype html>
<html>
  <body style="margin:0;padding:0;background:#f6f9fc;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f9fc;padding:24px 0;">
      <tr>
        <td align="center">
          <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:14px;box-shadow:0 6px 20px rgba(18,38,63,.08);padding:28px">
            <tr>
              <td style="text-align:center;">
                <div style="font-size:22px;font-weight:800;color:#111827;letter-spacing:.2px">MediVerse</div>
                <div style="margin-top:6px;color:#6b7280">Appointment Update</div>
              </td>
            </tr>

            <tr><td style="height:18px"></td></tr>

            <tr>
              <td style="font-size:16px;line-height:1.6;color:#111827">
                Hi {$toNameSafe},<br><br>
                We’re sorry — your appointment on <strong>{$dateSafe} at {$timeSafe}</strong> with <strong>Dr. {$doctorNameSafe}</strong> has been <strong>canceled</strong> due to a schedule change.
              </td>
            </tr>

            <tr><td style="height:14px"></td></tr>

            <tr>
              <td style="font-size:15px;line-height:1.6;color:#374151">
                Please choose another time that works for you:
              </td>
            </tr>

            <tr><td style="height:16px"></td></tr>

            <tr>
              <td align="center">
                <a href="{$bookUrlSafe}"
                   style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:600;border-radius:10px;padding:12px 20px">
                  Book a New Appointment
                </a>
              </td>
            </tr>

            <tr><td style="height:18px"></td></tr>

            <tr>
              <td style="font-size:14px;line-height:1.6;color:#6b7280">
                If you have any questions, just reply to this email and we’ll be happy to help.
              </td>
            </tr>
          </table>
          <div style="color:#9ca3af;font-size:12px;margin-top:12px">© <?=date('Y')?> MediVerse</div>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    $plain = "Hi {$toName},\n\nYour appointment on {$date} at {$time} with Dr. {$doctorName} has been canceled.\n\nBook a new appointment: {$bookUrl}\n\n— MediVerse";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mediverse259@gmail.com';
        $mail->Password   = 'yrecnfqylehxregz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('mediverse259@gmail.com', 'MediVerse');
        $mail->addAddress($toEmail, $toName ?: $toEmail);
        $mail->addReplyTo('mediverse259@gmail.com', 'MediVerse Support');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $plain;

        return $mail->send();
    } catch (Exception $e) {
        error_log('Apology mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

/* keep current filter (if any) */
$selectedDate = $_GET['date'] ?? '';
if ($selectedDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = '';
}

/* Delete slot if requested */
if (isset($_GET['delete'])) {
    $slot_id = (int)$_GET['delete'];

    $conn->begin_transaction();

    // 1) Fetch the slot (ensure it's this doctor's)
    $slotStmt = $conn->prepare("SELECT date, time FROM slots WHERE id = ? AND doctor_id = ? LIMIT 1");
    $slotStmt->bind_param("ii", $slot_id, $doctor_id);
    $slotStmt->execute();
    $slotRes = $slotStmt->get_result();
    $slot    = $slotRes ? $slotRes->fetch_assoc() : null;
    $slotStmt->close();

    if ($slot) {
        $date = $slot['date'];
        $time = $slot['time'];

        // 2) Find any NON-canceled appointment on this slot (match by doctor + date/time)
        $apptStmt = $conn->prepare("
            SELECT a.id, a.patient_id, u.email, u.name
            FROM appointments a
            JOIN users u ON u.id = a.patient_id
            WHERE a.doctor_id = ?
              AND DATE(a.appointment_datetime) = ?
              AND TIME(a.appointment_datetime) = ?
              AND a.status <> 'canceled'
            LIMIT 1
            FOR UPDATE
        ");
        $apptStmt->bind_param("iss", $doctor_id, $date, $time);
        $apptStmt->execute();
        $appt = $apptStmt->get_result()->fetch_assoc();
        $apptStmt->close();

        if ($appt) {
            // 3) Cancel the appointment
            $cancel = $conn->prepare("UPDATE appointments SET status = 'canceled' WHERE id = ? AND doctor_id = ?");
            $cancel->bind_param("ii", $appt['id'], $doctor_id);
            $cancel->execute();
            $cancel->close();
        }

        // 4) Delete the slot
        $del = $conn->prepare("DELETE FROM slots WHERE id = ? AND doctor_id = ?");
        $del->bind_param("ii", $slot_id, $doctor_id);
        $del->execute();
        $del->close();

        $conn->commit();

        // 5) Email the patient (if any)
        if (!empty($appt) && !empty($appt['email'])) {
            $bookLink = app_root_url() . '/book_appointment.php';
            sendApologyEmail(
                $appt['email'],
                $appt['name'] ?: 'Patient',
                $doctor_name,
                $date,
                $time,
                $bookLink
            );
        }
    } else {
        $conn->rollback();
    }

    // redirect back keeping the filter
    header("Location: view_slots.php" . ($selectedDate ? "?date=" . urlencode($selectedDate) : ""));
    exit();
}

/* Auto-cleanup: delete past slots with NO active appointment */
$cleanup = $conn->prepare("
    DELETE s FROM slots s
    LEFT JOIN appointments a
      ON a.doctor_id = s.doctor_id
     AND DATE(a.appointment_datetime) = s.date
     AND TIME(a.appointment_datetime) = s.time
     AND a.status NOT IN ('canceled','cancelled')
    WHERE s.doctor_id = ?
      AND TIMESTAMP(s.date, s.time) < NOW()
      AND a.id IS NULL
");
$cleanup->bind_param("i", $doctor_id);
$cleanup->execute();
$cleanup->close();

/* Fetch slots (optionally filtered by date) */
if ($selectedDate) {
    $stmt = $conn->prepare("SELECT id, date, time FROM slots WHERE doctor_id = ? AND date = ? ORDER BY date, time");
    $stmt->bind_param("is", $doctor_id, $selectedDate);
} else {
    $stmt = $conn->prepare("SELECT id, date, time FROM slots WHERE doctor_id = ? ORDER BY date, time");
    $stmt->bind_param("i", $doctor_id);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Slots | MediVerse</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body{ background:#f6f9fc; }
        .page-wrap{ max-width: 1100px; }
        .toolbar-card .card-header{
            background:#fff;
            border-bottom:1px solid #e9ecef;
            font-weight:600;
        }
        .chip{
            display:inline-flex; align-items:center; gap:.4rem;
            background:#eef2ff; color:#3730a3; border-radius:999px; padding:.25rem .6rem; font-weight:600; font-size:.9rem;
        }
        .table thead th{ background:#f8fafc; border-bottom:1px solid #e9ecef; }
        .empty{
            padding:48px 16px; color:#64748b;
        }
        .card-shadow{ box-shadow:0 8px 24px rgba(15,23,42,.06); }
        .btn-icon{ display:inline-flex; align-items:center; gap:.4rem; }
    </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap py-4">

    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <h2 class="m-0">🗓️ My Available Slots</h2>
            <span class="chip">
                <i class="bi bi-clock-history"></i>
                <?= (int)$result->num_rows ?> slot<?= $result->num_rows === 1 ? '' : 's' ?>
            </span>
        </div>
        <a href="add_slot.php" class="btn btn-primary btn-icon">
            <i class="bi bi-plus-circle"></i> Add Slots
        </a>
    </div>

    <!-- Toolbar / Filters -->
    <div class="card toolbar-card card-shadow mb-3">
        <div class="card-header">
            <i class="bi bi-funnel me-2"></i>Filter
        </div>
        <div class="card-body">
            <form class="row g-3 align-items-end" method="get" action="view_slots.php">
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <label for="date" class="form-label">Date</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                        <input type="date" id="date" name="date" class="form-control"
                               value="<?= htmlspecialchars($selectedDate) ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i>Apply
                    </button>
                    <?php if ($selectedDate): ?>
                        <a class="btn btn-outline-secondary ms-1" href="view_slots.php">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($selectedDate): ?>
                <div class="mt-3">
                    <span class="chip"><i class="bi bi-filter"></i> Date: <?= htmlspecialchars($selectedDate) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results -->
    <?php if ($result->num_rows > 0): ?>
        <div class="card card-shadow">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Date</th>
                            <th style="width: 20%;">Time</th>
                            <th style="width: 1px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($row['date']) ?></td>
                            <td>
                                <span class="badge text-bg-light border">
                                    <i class="bi bi-clock me-1"></i><?= htmlspecialchars($row['time']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="view_slots.php?delete=<?= (int)$row['id'] ?><?= $selectedDate ? '&date='.urlencode($selectedDate) : '' ?>"
                                   class="btn btn-outline-danger btn-sm"
                                   onclick="return confirm('Delete this slot? This will cancel any existing appointment and email the patient.');">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card card-shadow">
            <div class="card-body text-center empty">
                <div class="display-6 mb-2">🗓️</div>
                <div class="mb-2">No <?= $selectedDate ? 'slots on <strong>'.htmlspecialchars($selectedDate).'</strong>' : 'available slots' ?>.</div>
                <div class="text-muted mb-3">Create some 30-minute slots so patients can book you.</div>
                <a href="add_slot.php" class="btn btn-primary btn-icon"><i class="bi bi-plus-circle"></i> Add Slots</a>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
