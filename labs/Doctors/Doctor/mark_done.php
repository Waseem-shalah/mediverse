<?php
// mark_done.php
session_start();
require_once '../config.php';

// ✅ Make sure only logged-in doctors can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

// ✅ Get the appointment ID either from POST or GET, default to 0 if missing
$appointment_id = (int)($_POST['appointment_id'] ?? $_GET['appointment_id'] ?? 0);
// ✅ Logged-in doctor's ID
$doctor_id      = (int)$_SESSION['user_id'];

// ✅ If no valid appointment ID was given, stop execution
if (!$appointment_id) { 
    die('Invalid appointment.'); 
}

// ✅ Mark the appointment as "done", but only if it belongs to this doctor
$stmt = $conn->prepare("UPDATE appointments SET status = 'done' WHERE id = ? AND doctor_id = ?");
$stmt->bind_param("ii", $appointment_id, $doctor_id);
$stmt->execute();
$stmt->close();

// ✅ Redirect back to the doctor’s appointments list
header("Location: view_appointments.php");
exit;
