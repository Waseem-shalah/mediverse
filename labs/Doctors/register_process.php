<?php
session_start();
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/vendor/autoload.php';

/**
 * Redirect back to step 1 or 2 with a friendly error.
 * If $keepStep2Post is true, we stash POST so the form can be repopulated.
 */
function fail_to($step, $msg, $keepStep2Post = false) {
    if ($keepStep2Post) {
        $_SESSION['reg_step2'] = $_POST;
    }
    if ($step === 1) $_SESSION['register_error']  = $msg;
    if ($step === 2) $_SESSION['register_error2'] = $msg;
    header("Location: " . ($step === 1 ? "register.php" : "register_more.php"));
    exit();
}

/** Build absolute base URL for links in emails (http/https aware). */
function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/\\');
    return $scheme . '://' . $host . $path;
}

/**
 * Send a simple verification email with a 6-digit code.
 * Returns true on success (email sent), false otherwise.
 */
function sendVerificationEmail(string $toEmail, string $toName, string $code): bool {
    $verifyLink = base_url() . '/verify_account.php';
    $year = date('Y');
    $html = <<<HTML
<!doctype html>
<html>
  <head><meta charset="utf-8"><meta name="color-scheme" content="light only"></head>
  <body style="margin:0;background:#f6f9fc;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f9fc;padding:32px 0;">
      <tr>
        <td align="center">
          <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:16px;box-shadow:0 6px 24px rgba(18,38,63,.08);padding:32px">
            <tr><td style="text-align:center;"><div style="font-size:24px;font-weight:700;color:#111827;">MediVerse</div></td></tr>
            <tr><td style="height:24px"></td></tr>
            <tr><td style="font-size:16px;color:#111827;line-height:1.6">Hi {$toName},<br><br>Use the code below to verify your account:</td></tr>
            <tr><td style="height:16px"></td></tr>
            <tr><td align="center"><div style="display:inline-block;background:#111827;color:#fff;border-radius:12px;padding:14px 22px;font-size:28px;letter-spacing:6px;font-weight:700">{$code}</div></td></tr>
            <tr><td style="height:16px"></td></tr>
            <tr><td align="center"><a href="{$verifyLink}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;border-radius:10px;padding:12px 18px">Open verification page</a></td></tr>
          </table>
          <div style="color:#9ca3af;font-size:12px;margin-top:16px">© {$year} MediVerse</div>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

    $plain = "Your verification code is: {$code}\nOpen: {$verifyLink}";

    $mail = new PHPMailer(true);
    try {
        // SMTP config (consider moving creds to env vars)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mediverse259@gmail.com';
        $mail->Password   = 'yrecnfqylehxregz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('mediverse259@gmail.com', 'MediVerse');
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Verify your MediVerse account';
        $mail->Body    = $html;
        $mail->AltBody = $plain;

        return $mail->send();
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

// Must have Step 1 data in session (set by register_more.php before showing Step 2)
if (!isset($_SESSION['registration_started']) || empty($_SESSION['reg_data'])) {
    fail_to(1, 'Session expired. Please start again.');
}

$step1 = $_SESSION['reg_data'];
$step2 = $_POST;

/* ---------------------- Re-validate Step 1 ---------------------- */
$name     = trim($step1['name'] ?? '');
$username = trim($step1['username'] ?? '');
$email    = trim($step1['email'] ?? '');
$phone    = trim($step1['phone'] ?? '');
$pwdPlain = $step1['password'] ?? '';
$pw2      = $step1['password2'] ?? '';

// Confirm password match (defense-in-depth; also validated client-side)
if ($pw2 === '' || $pw2 !== $pwdPlain) {
    fail_to(1, 'Passwords do not match.');
}

$role = 'patient';

// Normalize and validate national ID
$user_id_raw = $step1['user_id'] ?? '';
$user_id     = (int)preg_replace('/\D/', '', $user_id_raw);
if (!preg_match('/^\d{9,10}$/', $user_id_raw)) {
    fail_to(1, 'Invalid ID: must be exactly 9 or 10 digits.');
}

// Required fields + email format + password strength
if ($name === '' || $username === '' || $email === '' || $pwdPlain === '') {
    fail_to(1, 'Missing required fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail_to(1, 'Invalid email.');
}
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/', $pwdPlain)) {
    fail_to(1, 'Password too weak: use at least 8 chars, include uppercase, lowercase, number, and symbol.');
}

$password = password_hash($pwdPlain, PASSWORD_DEFAULT);

/* ---------------------- Validate Step 2 ---------------------- */
$gender    = $step2['gender'] ?? '';
$height    = $step2['height_cm'] ?? '';
$weight    = $step2['weight_kg'] ?? '';
$dob       = $step2['dob'] ?? '';           // min-age check can be added here if needed
$location  = trim($step2['location'] ?? '');

if (!in_array($gender, ['male','female','other'], true)) {
    fail_to(2, 'Invalid gender.', true);
}
$height = filter_var($height, FILTER_VALIDATE_INT, ['options'=>['min_range'=>50,'max_range'=>250]]);
$weight = filter_var($weight, FILTER_VALIDATE_FLOAT);
if ($height === false || $weight === false || $weight < 20 || $weight > 400) {
    fail_to(2, 'Invalid height/weight.', true);
}

// DOB must be a real date and not in the future (client enforces picker bounds)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || strtotime($dob) === false || strtotime($dob) > time()) {
    fail_to(2, 'Invalid date of birth.', true);
}

if ($location === '') {
    fail_to(2, 'Location is required.', true);
}

$bmi = round($weight / pow($height/100, 2), 2);

/* -------------- Uniqueness checks (race-condition safe) -------------- */
$dupes = [];

// Username
if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1")) {
    $stmt->bind_param("s", $username);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) $dupes[] = 'Username already in use';
    $stmt->close();
} else {
    fail_to(1, 'Database error (username check).');
}

// Email
if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1")) {
    $stmt->bind_param("s", $email);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) $dupes[] = 'Email already in use';
    $stmt->close();
} else {
    fail_to(1, 'Database error (email check).');
}

// ID
if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ? LIMIT 1")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) $dupes[] = 'ID number already in use';
    $stmt->close();
} else {
    fail_to(1, 'Database error (ID check).');
}

// Phone (optional)
if ($phone !== '') {
    if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE phone = ? LIMIT 1")) {
        $stmt->bind_param("s", $phone);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) $dupes[] = 'Phone number already in use';
        $stmt->close();
    } else {
        fail_to(1, 'Database error (phone check).');
    }
}

if (!empty($dupes)) {
    fail_to(1, implode('. ', $dupes) . '.');
}

/* ---------------------- Create the user ---------------------- */
$sql = "INSERT INTO users
        (name, username, email, phone, password, role, user_id, gender, height_cm, weight_kg, bmi, date_of_birth, location, is_activated)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
$ins = $conn->prepare($sql);
if (!$ins) { fail_to(2, 'DB error (prepare insert): '.$conn->error, true); }

$ins->bind_param(
    "ssssssisiddss",
    $name, $username, $email, $phone, $password, $role,
    $user_id, $gender, $height, $weight, $bmi, $dob, $location
);

if (!$ins->execute()) {
    $msg = 'Registration failed: '.$ins->error;
    $ins->close();
    fail_to(2, $msg, true);
}
$newUserId = $ins->insert_id;
$ins->close();

/* ---------------------- Create 6-digit verification code ---------------------- */
$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$upd = $conn->prepare("UPDATE users SET verification_code = ?, email_verified = 0 WHERE id = ?");
if (!$upd) { fail_to(2, 'DB error (prepare update code): '.$conn->error, true); }
$upd->bind_param("si", $code, $newUserId);
if (!$upd->execute()) {
    $msg = 'Registration failed (code save): '.$upd->error;
    $upd->close();
    fail_to(2, $msg, true);
}
$upd->close();

/* ---------------------- Email the code (non-fatal if it fails) ---------------------- */
sendVerificationEmail($email, $name ?: $email, $code);

/* ---------------------- Log in and redirect to verification ---------------------- */
$_SESSION['user_id']       = $newUserId;
$_SESSION['role']          = $role;
$_SESSION['temp_password'] = false;

// Clear wizard state
unset($_SESSION['registration_started'], $_SESSION['reg_data'], $_SESSION['reg_step2']);

header("Location: verify_account.php");
exit();
