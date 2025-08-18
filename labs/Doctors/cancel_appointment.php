<?php
session_start();
require_once "config.php";

/**
 * Only signed-in patients can cancel.
 * If not logged in as a patient, send them to login.
 */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== 'patient') {
    header("Location: login.php");
    exit();
}

/**
 * Expect an appointment id in the query string (?id=123).
 * If present, verify ownership, then delete; otherwise redirect with error.
 */
if (isset($_GET["id"])) {
    $appointment_id = intval($_GET["id"]);         // sanitize to int
    $patient_id     = $_SESSION["user_id"];

    // 1) Verify the appointment belongs to this patient
    $check = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND patient_id = ?");
    $check->bind_param("ii", $appointment_id, $patient_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 1) {
        // 2) Delete the appointment (hard delete)
        //    NOTE: This permanently removes the row.
        //    Consider using a soft cancel (status='canceled') instead.
        $delete = $conn->prepare("DELETE FROM appointments WHERE id = ?");
        $delete->bind_param("i", $appointment_id);

        if ($delete->execute()) {
            // Success → show a success banner on the listing page
            header("Location: my_appointments.php?cancelled=1");
            exit();
        } else {
            // DB error while deleting
            header("Location: my_appointments.php?error=deletion");
            exit();
        }
    } else {
        // Appointment not found or does not belong to this user
        header("Location: my_appointments.php?error=unauthorized");
        exit();
    }
}

// If we get here, the id was missing or invalid
header("Location: my_appointments.php?error=invalid");
exit();
