<?php
// Doctor/edit_profile_process.php
// Purpose: Process the "Edit Profile" form for doctors.
// Actions: CSRF check, validate username, optional password change, optional avatar upload,
//          update DB, and (if password changed) send a confirmation email.
// NOTE: Logic unchanged — only concise, human-friendly comments added.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config.php';

// Keep PHP errors hidden from users; still log them server-side
ini_set('display_errors', 0);
error_reporting(E_ALL);

// PHPMailer (installed via Composer)
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * back(): Redirect back to edit page with a flash message.
 * $ok=true  -> sets $_SESSION['success']
 * $ok=false -> sets $_SESSION['error']
 */
function back($ok = false, $msg = '') {
    if ($ok) $_SESSION['success'] = $msg ?: "Updated successfully.";
    if (!$ok) $_SESSION['error']   = $msg ?: "Update failed.";
    header("Location: edit_profile.php"); exit();
}

// Gate: only logged-in doctors can submit this
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') { die("Unauthorized access."); }
$doctor_id = (int)$_SESSION['user_id'];

// CSRF protection: validate token from form vs. session
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    back(false, "Security token mismatch. Please try again.");
}

// Read and normalize inputs
$username         = trim($_POST['username'] ?? '');
$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Basic username validation
if ($username === '' || strlen($username) > 50) {
    back(false, "Invalid username.");
}

// Load current user record (to check password, email, existing avatar, etc.)
$stmt = $conn->prepare("
    SELECT id, username, email, password, COALESCE(profile_image,'') AS profile_image
    FROM users
    WHERE id = ? AND role = 'doctor'
    LIMIT 1
");
if (!$stmt) back(false, "Prepare failed (load user): ".$conn->error);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) back(false, "Doctor not found in users table.");

// Enforce username uniqueness (except for this same user)
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
if (!$stmt) back(false, "Prepare failed (unique username): ".$conn->error);
$stmt->bind_param("si", $username, $doctor_id);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($exists) back(false, "Username is already taken.");

// Determine if the user intends to change password
$want_pwd_change = ($current_password !== '' || $new_password !== '' || $confirm_password !== '');
if ($want_pwd_change) {
    // Require all password fields
    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        back(false, "To change password, fill all password fields.");
    }
    // Verify current password matches DB
    if (!password_verify($current_password, (string)($user['password'] ?? ''))) {
        back(false, "Current password is incorrect.");
    }
    // Disallow reusing the same password
    if (password_verify($new_password, (string)($user['password'] ?? ''))) {
        back(false, "New password must be different from your current password.");
    }
    // Basic complexity rules
    if (strlen($new_password) < 8
        || !preg_match('/[A-Z]/', $new_password)
        || !preg_match('/[a-z]/', $new_password)
        || !preg_match('/\d/', $new_password)
        || !preg_match('/[^A-Za-z0-9]/', $new_password)) {
        back(false, "Password must be at least 8 characters and include upper, lower, number, and symbol.");
    }
    // Must match confirmation
    if ($new_password !== $confirm_password) {
        back(false, "New password and confirmation do not match.");
    }
    // Optional: discourage using username or email local part as password
    $emailLocal = strtok((string)$user['email'], '@') ?: '';
    if (strcasecmp($new_password, $username) === 0 || strcasecmp($new_password, $emailLocal) === 0) {
        back(false, "Choose a password different from your username/email.");
    }
}

// Handle profile image upload if provided (size, type, and safe path)
$profile_path = $user['profile_image'];
if (!empty($_FILES['profile_image']['name'])) {
    // Basic upload checks
    if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) back(false, "Image upload failed (code ".$_FILES['profile_image']['error'].").");
    if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) back(false, "Image too large (max 2 MB).");

    // Validate MIME -> extension
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($_FILES['profile_image']['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) back(false, "Invalid image format. Use JPG/PNG/WEBP.");
    $ext = $allowed[$mime];

    // Compute destinations (project-root/uploads/avatars/...)
    $baseDir = realpath(__DIR__ . '/..');
    $relDir  = 'uploads/avatars';
    $destDir = $baseDir . DIRECTORY_SEPARATOR . $relDir;
    if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }

    // Unique filename per user + time
    $filename    = 'u' . $doctor_id . '_' . time() . '.' . $ext;
    $destPathAbs = $destDir . DIRECTORY_SEPARATOR . $filename;
    $destPathRel = $relDir . '/' . $filename;

    // Move uploaded file to destination
    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $destPathAbs)) back(false, "Failed to save uploaded image.");

    // Clean up old image (only if it’s within the avatars folder)
    if ($profile_path && strpos($profile_path, 'uploads/avatars/') === 0) {
        $oldAbs = $baseDir . DIRECTORY_SEPARATOR . $profile_path;
        if (is_file($oldAbs)) { @unlink($oldAbs); }
    }

    // Persist new relative path
    $profile_path = $destPathRel;
}

// Prepare UPDATE query (with/without password change)
if ($want_pwd_change) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $sql  = "UPDATE users SET username = ?, profile_image = ?, password = ? WHERE id = ? AND role = 'doctor' LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) back(false, "Prepare failed (update with pwd): ".$conn->error);
    $stmt->bind_param("sssi", $username, $profile_path, $hashed, $doctor_id);
} else {
    $sql  = "UPDATE users SET username = ?, profile_image = ? WHERE id = ? AND role = 'doctor' LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) back(false, "Prepare failed (update no pwd): ".$conn->error);
    $stmt->bind_param("ssi", $username, $profile_path, $doctor_id);
}

// Execute update and capture result
if (!$stmt->execute()) {
    $err = $stmt->error; $stmt->close();
    back(false, "Update failed to execute: ".$err);
}
$affected = $stmt->affected_rows;
$stmt->close();

// If password changed and update touched a row, send a courtesy email (non-blocking)
if ($want_pwd_change && $affected === 1) {
    $doctorEmail = trim((string)($user['email'] ?? ''));
    if ($doctorEmail !== '') {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'mediverse259@gmail.com';   // your sender
            $mail->Password   = 'yrecnfqylehxregz';         // app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('mediverse259@gmail.com', 'MediVerse Security');
            $mail->addAddress($doctorEmail);

            // Build a support URL based on current host (fallback-safe)
            $host = isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '';
            $supportUrl = $host . '/labs/Doctors/contact.php';

            // Basic HTML + plain-text body
            $mail->isHTML(true);
            $mail->Subject = 'Your MediVerse password was updated';
            $mail->Body    = '<p>Hello Doctor,</p><p>Your account password was <strong>successfully updated</strong>.</p><p>If you didn’t make this change, please contact support:</p><p><a href="'.htmlspecialchars($supportUrl,ENT_QUOTES).'">Get Support</a></p>';
            $mail->AltBody = "Your MediVerse password was updated.\nIf this wasn’t you, contact support: $supportUrl";

            $mail->send();
        } catch (Exception $e) {
            // Swallow email errors (do not block profile updates), but log for admins
            error_log("Password change email failed: " . $e->getMessage());
        }
    }
}

// Final user feedback (flash) and redirect
if ($want_pwd_change) {
    ($affected === 1)
        ? back(true, "Profile + password updated. A confirmation email was sent.")
        : back(true, "Saved. (No row reported changed; values may be identical.)");
} else {
    ($affected === 1)
        ? back(true, "Profile updated.")
        : back(true, "Saved. (No row reported changed; values may be identical.)");
}
