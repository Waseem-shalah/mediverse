<?php
session_start();
require 'config.php'; // DB connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    /**
     * Helper function:
     * Saves an error into the session and sends user back to login page.
     */
    function back_with_error($msg, $show_register_link = false, $is_html = false) {
        $_SESSION['login_error']        = $msg;
        $_SESSION['show_register_link'] = $show_register_link ? 1 : 0;
        $_SESSION['login_error_is_html']= $is_html ? 1 : 0;
        header("Location: login.php");
        exit();
    }

    // --- Basic validation: both fields must be filled ---
    if (trim($username) === '' || trim($password) === '') {
        back_with_error("Please fill in both fields.");
    }

    // --- Detect if input is an email or a username ---
    $input   = trim($username);
    $isEmail = filter_var($input, FILTER_VALIDATE_EMAIL) !== false;

    // --- Prepare query based on input type ---
    if ($isEmail) {
        $stmt = $conn->prepare("
            SELECT id, name, username, password, role,
                   COALESCE(is_active, 1)    AS is_active,
                   COALESCE(user_deleted, 0) AS user_deleted,
                   COALESCE(is_activated, 0) AS is_activated,
                   block_reason,
                   deleted_reason
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT id, name, username, password, role,
                   COALESCE(is_active, 1)    AS is_active,
                   COALESCE(user_deleted, 0) AS user_deleted,
                   COALESCE(is_activated, 0) AS is_activated,
                   block_reason,
                   deleted_reason
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
    }

    if (!$stmt) {
        back_with_error("Server error. Please try again later.");
    }

    $stmt->bind_param("s", $input);
    $stmt->execute();
    $res = $stmt->get_result();

    // --- User not found ---
    if (!$res || $res->num_rows !== 1) {
        $stmt->close();
        $conn->close();
        back_with_error("User not found.", true); // also show register link
    }

    $user = $res->fetch_assoc();
    $stmt->close();

    // --- Step 1: Check if account is deleted ---
    if ((int)$user['user_deleted'] === 1) {
        $reason = trim((string)($user['deleted_reason'] ?? ''));
        $msg = "Your account has been <strong>deleted</strong>."
             . ($reason !== '' ? " Reason: <em>" . htmlspecialchars($reason, ENT_QUOTES) . "</em>." : "")
             . " <a href='contact.php'>Contact Support</a>";
        back_with_error($msg, false, true);
    }

    // --- Step 2: Check if account is blocked/inactive ---
    if ((int)$user['is_active'] === 0) {
        $reason = trim((string)($user['block_reason'] ?? ''));
        $msg = "Your account is <strong>blocked</strong>."
             . ($reason !== '' ? " Reason: <em>" . htmlspecialchars($reason, ENT_QUOTES) . "</em>." : "")
             . " <a href='contact.php'>Contact Support</a>";
        back_with_error($msg, false, true);
    }

    // --- Step 3: Verify password ---
    if (!password_verify($password, $user['password'])) {
        $conn->close();
        back_with_error("Invalid password");
    }

    // --- Step 4: If not activated, redirect to verification page ---
    if ((int)$user['is_activated'] !== 1) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];

        header("Location: verify_account.php?ok=Please+enter+the+code+we+emailed+you");
        $conn->close();
        exit();
    }

    // --- Step 5: Successful login → set session and redirect by role ---
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];

    if ($user['role'] === 'admin') {
        header("Location: admin/index.php");
    } elseif ($user['role'] === 'doctor') {
        header("Location: Doctor/dashboard.php");
    } else {
        header("Location: patient_dashboard.php");
    }

    $conn->close();
    exit();
}
?>
