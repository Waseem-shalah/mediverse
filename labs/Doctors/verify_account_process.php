<?php
session_start();
require_once 'config.php';

// Make sure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$userId = (int)$_SESSION['user_id'];

// Grab the code they submitted (should be 6 digits)
$code = trim($_POST['code'] ?? '');
if (!preg_match('/^\d{6}$/', $code)) {
    header("Location: verify_account.php?err=Invalid+code+format");
    exit();
}

// --- Fetch the stored code for this user ---
$stmt = $conn->prepare("SELECT verification_code, is_activated FROM users WHERE id = ?");
if (!$stmt) {
    header("Location: verify_account.php?err=DB+error");
    exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

// If no user found, something’s wrong
if ($res->num_rows !== 1) {
    $stmt->close();
    header("Location: verify_account.php?err=User+not+found");
    exit();
}
$row = $res->fetch_assoc();
$stmt->close();

// If already activated, skip verification and go to dashboard
if ((int)$row['is_activated'] === 1) {
    header("Location: patient_dashboard.php");
    exit();
}

// Compare submitted code with stored code (safe string compare)
if (!hash_equals((string)$row['verification_code'], $code)) {
    header("Location: verify_account.php?err=Incorrect+code");
    exit();
}

// --- Correct code entered ---
// Mark account as activated, clear code, and mark email as verified
$upd = $conn->prepare("
    UPDATE users
    SET is_activated = 1,
        verification_code = NULL,
        email_verified = 1
    WHERE id = ?
");
if (!$upd) {
    header("Location: verify_account.php?err=DB+error");
    exit();
}
$upd->bind_param("i", $userId);
if (!$upd->execute()) {
    $upd->close();
    header("Location: verify_account.php?err=Could+not+activate");
    exit();
}
$upd->close();

// Success: go to the dashboard
header("Location: patient_dashboard.php");
exit();
