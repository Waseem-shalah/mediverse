<?php
// Process “Edit Profile” for patients: validates inputs, updates DB, handles optional
// password change + avatar upload, and sends a security email on password change.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';

// In production keep errors off (still log them server-side)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// PHPMailer (for password-change notification)
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Small helper to redirect back with a flash message
function back($ok = false, $msg = '') {
    if ($ok) $_SESSION['success'] = $msg ?: "Updated successfully.";
    if (!$ok) $_SESSION['error']   = $msg ?: "Update failed.";
    header("Location: patient_edit_profile.php"); exit();
}

// AuthZ: only logged-in patient can update their profile
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    die("Unauthorized access.");
}
$patient_id = (int)$_SESSION['user_id'];

// CSRF protection (compare token from form with one in session)
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    back(false, "Security token mismatch. Please try again.");
}

// Pull inputs (trim text fields)
$username         = trim((string)($_POST['username'] ?? ''));
$height_cm        = trim((string)($_POST['height_cm'] ?? ''));
$weight_kg        = trim((string)($_POST['weight_kg'] ?? ''));
$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Basic username validation + uniqueness (excluding current user)
if ($username === '' || strlen($username) > 50) {
    back(false, "Invalid username.");
}
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
$stmt->bind_param("si", $username, $patient_id);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($exists) back(false, "Username is already taken.");

// Height/weight validation (numeric + sane ranges)
if ($height_cm === '' || $weight_kg === '') back(false, "Height and Weight are required.");
if (!is_numeric($height_cm) || !is_numeric($weight_kg)) back(false, "Height and Weight must be numeric.");
$h = (float)$height_cm; $w = (float)$weight_kg;
if ($h < 50 || $h > 250 || $w < 10 || $w > 400) back(false, "Height/Weight out of range.");

// Compute BMI (server is source of truth, UI is just a hint)
$bmi = null; $hm = $h / 100.0; if ($hm > 0) $bmi = round($w / ($hm * $hm), 2);

// Load current user’s password hash, existing avatar, and email (for notifications)
$stmt = $conn->prepare("SELECT password, COALESCE(profile_image,'') AS profile_image, COALESCE(email,'') AS email FROM users WHERE id = ? AND role='patient' LIMIT 1");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) back(false, "Patient not found.");

// Decide whether a password change is requested (any field filled triggers full validation)
$want_pwd_change = ($current_password !== '' || $new_password !== '' || $confirm_password !== '');
if ($want_pwd_change) {
    // All three fields required
    if ($current_password === '' || $new_password === '' || $confirm_password === '')
        back(false, "To change password, fill all password fields.");

    // Verify current password
    if (!password_verify($current_password, (string)($user['password'] ?? '')))
        back(false, "Current password is incorrect.");

    // Don’t allow reusing the current password
    if (password_verify($new_password, (string)($user['password'] ?? '')))
        back(false, "New password must be different from your current password.");

    // Complexity: >=8 and contain upper/lower/digit/symbol
    if (strlen($new_password) < 8
        || !preg_match('/[A-Z]/', $new_password)
        || !preg_match('/[a-z]/', $new_password)
        || !preg_match('/\d/', $new_password)
        || !preg_match('/[^A-Za-z0-9]/', $new_password)) {
        back(false, "Password must be at least 8 characters and include upper, lower, number, and symbol.");
    }

    // Confirm match
    if ($new_password !== $confirm_password)
        back(false, "New password and confirmation do not match.");

    // Extra: discourage password equal to username/email local-part
    $emailLocal = strtok((string)$user['email'], '@') ?: '';
    if (strcasecmp($new_password, $username) === 0 || strcasecmp($new_password, $emailLocal) === 0) {
        back(false, "Choose a password different from your username/email.");
    }
}

// Optional profile image upload & replacement (size/type checks + simple storage)
$profile_path = $user['profile_image'];
if (!empty($_FILES['profile_image']['name'])) {
    if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) back(false, "Image upload failed (code ".$_FILES['profile_image']['error'].").");
    if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) back(false, "Image too large (max 2 MB).");

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['profile_image']['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) back(false, "Invalid image format. Use JPG/PNG/WEBP.");
    $ext = $allowed[$mime];

    $baseDir = realpath(__DIR__);
    $relDir  = 'uploads/avatars';
    $destDir = $baseDir . DIRECTORY_SEPARATOR . $relDir;
    if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }

    $filename    = 'u' . $patient_id . '_' . time() . '.' . $ext;
    $destPathAbs = $destDir . DIRECTORY_SEPARATOR . $filename;
    $destPathRel = $relDir . '/' . $filename;

    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $destPathAbs)) back(false, "Failed to save uploaded image.");

    // Clean up prior custom avatar (if stored under uploads/avatars/)
    if ($profile_path && strpos($profile_path, 'uploads/avatars/') === 0) {
        $oldAbs = $baseDir . DIRECTORY_SEPARATOR . $profile_path;
        if (is_file($oldAbs)) { @unlink($oldAbs); }
    }
    $profile_path = $destPathRel;
}

// Build and execute the UPDATE query (with/without password column)
if ($want_pwd_change) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $sql = "UPDATE users
            SET username = ?, height_cm = ?, weight_kg = ?, bmi = ?, profile_image = ?, password = ?
            WHERE id = ? AND role = 'patient'
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdddssi", $username, $h, $w, $bmi, $profile_path, $hashed, $patient_id);
} else {
    $sql = "UPDATE users
            SET username = ?, height_cm = ?, weight_kg = ?, bmi = ?, profile_image = ?
            WHERE id = ? AND role = 'patient'
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdddsi", $username, $h, $w, $bmi, $profile_path, $patient_id);
}

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    back(false, "Update failed to execute: ".$err);
}
$affected = $stmt->affected_rows; // May be 0 when data didn’t actually change
$stmt->close();

// If password changed, send a security notice email (best-effort; non-fatal)
if ($want_pwd_change && $affected === 1) {
    $patientEmail = trim((string)($user['email'] ?? ''));
    if ($patientEmail !== '') {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'mediverse259@gmail.com';
            $mail->Password   = 'yrecnfqylehxregz'; // TODO: move to env/secret manager
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('mediverse259@gmail.com', 'MediVerse Security');
            $mail->addAddress($patientEmail);

            $host = isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '';
            $supportUrl = $host . '/labs/Doctors/contact.php';

            $mail->isHTML(true);
            $mail->Subject = 'Your MediVerse password was updated';
            $mail->Body    = '<p>Hello,</p><p>Your MediVerse account password was <strong>successfully updated</strong>.</p><p>If this wasn’t you, please contact support:</p><p><a href="'.htmlspecialchars($supportUrl,ENT_QUOTES).'">Get Support</a></p>';
            $mail->AltBody = "Your MediVerse password was updated.\nIf this wasn’t you, contact support: $supportUrl";

            $mail->send();
        } catch (Exception $e) {
            error_log("Patient password-change email failed: " . $e->getMessage());
        }
    }
}

// Final redirect with a friendly message. Note: 0 rows affected can be legit (no changes).
if ($want_pwd_change) {
    back(true, $affected === 1 ? "Profile + password updated. A confirmation email was sent." : "Saved. (No row reported changed; values may be identical.)");
} else {
    back(true, $affected === 1 ? "Profile updated." : "Saved. (No row reported changed; values may be identical.)");
}
