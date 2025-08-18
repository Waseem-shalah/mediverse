<?php
// process_application.php
// Admin endpoint to approve/reject a doctor application, optionally syncing specialization
// and emailing the applicant about the decision.

session_start();
require_once 'config.php';

// PHPMailer (for email notifications)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';

// --- AuthZ: only admins can process applications ---
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

// --- Inputs: application id + action (approve|reject) ---
$app_id = (int)($_POST['app_id'] ?? 0);
$action = $_POST['action'] ?? '';
if (!$app_id || !in_array($action, ['approve','reject'], true)) {
    header("Location: admin/doctor_applications.php");
    exit;
}

// --- Load target application (we only allow processing PENDING apps) ---
$stmt = $conn->prepare("
    SELECT email, name, status, specialization_id
    FROM doctor_applications
    WHERE id = ?
");
if (!$stmt) {
    die("SQL prepare error (load application): " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app || $app['status'] !== 'pending') {
    header("Location: admin/doctor_applications.php");
    exit;
}

$newStatus = ($action === 'approve') ? 'approved' : 'rejected';

// --- Persist new status on the application record ---
$upd = $conn->prepare("
    UPDATE doctor_applications
    SET status = ?
    WHERE id = ?
");
if (!$upd) {
    die("SQL prepare error (update application): " . htmlspecialchars($conn->error));
}
$upd->bind_param("si", $newStatus, $app_id);
$upd->execute();
$upd->close();

/**
 * If approved, try to update an existing doctor user’s specialization immediately.
 * If the doctor user doesn’t exist yet, skip — the specialization from the application
 * will be used later during doctor onboarding (complete_registration.php).
 */
if ($newStatus === 'approved') {
    $findUser = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'doctor' LIMIT 1");
    if (!$findUser) {
        die("SQL prepare error (find user): " . htmlspecialchars($conn->error));
    }
    $findUser->bind_param("s", $app['email']);
    $findUser->execute();
    $uRes = $findUser->get_result();
    $userRow = $uRes->fetch_assoc();
    $findUser->close();

    if ($userRow && isset($userRow['id'])) {
        $doctor_user_id = (int)$userRow['id'];
        $spec_id = (int)$app['specialization_id'];

        $updUser = $conn->prepare("UPDATE users SET specialization_id = ? WHERE id = ?");
        if (!$updUser) {
            die("SQL prepare error (update user specialization): " . htmlspecialchars($conn->error));
        }
        $updUser->bind_param("ii", $spec_id, $doctor_user_id);
        $updUser->execute();
        $updUser->close();
    }
}

// --- Helper: base URL for links in the email (handles subfolder /Doctors deployment) ---
function app_root_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/\\'); // e.g., /Doctors
    // If script path ends with /Doctors, treat its parent as the app root
    $root   = preg_replace('~/Doctors/?$~', '', $dir);
    return $scheme . '://' . $host . $root;
}

// --- Compose email content based on outcome ---
if ($newStatus === 'approved') {
    $subject = 'Your MediVerse Application Has Been Approved';
    $link    = app_root_url() . '/Doctors/complete_registration.php?app_id=' . urlencode((string)$app_id);

    $nameEsc = htmlspecialchars($app['name'], ENT_QUOTES, 'UTF-8');
    $linkEsc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $bodyHtml = "
      <p>Hello {$nameEsc},</p>
      <p>Congratulations! Your application has been approved.</p>
      <p style=\"text-align:center;\">
        <a href=\"{$linkEsc}\" style=\"
          display:inline-block;
          padding:12px 24px;
          font-size:16px;
          color:#fff;
          background:#28a745;
          text-decoration:none;
          border-radius:6px;
        \">Complete Registration</a>
      </p>
      <p>Thanks,<br>The MediVerse Team</p>
    ";
    $bodyAlt = "Hello {$app['name']},\n\n"
             . "Congratulations! Your application has been approved.\n"
             . "Complete registration here: {$link}\n\n"
             . "Thanks,\nThe MediVerse Team";
} else {
    $subject = 'Your MediVerse Application Has Been Declined';

    $nameEsc = htmlspecialchars($app['name'], ENT_QUOTES, 'UTF-8');
    $bodyHtml = "
      <p>Hello {$nameEsc},</p>
      <p>We’re sorry to inform you that your application was declined.</p>
      <p>Regards,<br>The MediVerse Team</p>
    ";
    $bodyAlt = "Hello {$app['name']},\n\n"
             . "We’re sorry to inform you that your application was declined.\n\n"
             . "Regards,\nThe MediVerse Team";
}

// --- Send notification email (best-effort; failure is logged but does not block flow) ---
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mediverse259@gmail.com';
    $mail->Password   = 'yrecnfqylehxregz'; // TODO: move to env var / secrets manager
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('mediverse259@gmail.com', 'MediVerse Admin');
    $mail->addAddress($app['email'], $app['name']);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $bodyHtml;
    $mail->AltBody = $bodyAlt;

    $mail->send();
} catch (Exception $e) {
    // Log but don’t expose SMTP details to the admin UI
    error_log("Mail error ({$app['email']}): {$mail->ErrorInfo}");
}

// --- Done: back to applications list with a small flag ---
header("Location: admin/doctor_applications.php?app_processed=1");
exit;
