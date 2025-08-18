<!-- booking_success.php -->
<?php
session_start();

// Only logged-in users should access this page
// If the user isn't logged in, send them back to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Confirmed - MediVerse</title>

    <!-- Main stylesheet (optional, if you have one) -->
    <link rel="stylesheet" href="styles.css"> 

    <style>
        /* Page styling: center the success box on a soft background */
        body {
            background-color: #f4f9f9;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        /* Container box for success message */
        .success-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }

        /* Title style */
        .success-container h1 {
            color: #4CAF50;
            font-size: 28px;
            margin-bottom: 10px;
        }

        /* Message paragraph style */
        .success-container p {
            font-size: 18px;
            color: #333;
        }

        /* Dashboard button style */
        .success-container a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background-color: #4CAF50;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        /* Hover effect for the dashboard button */
        .success-container a:hover {
            background-color: #43a047;
        }
    </style>
</head>
<body>
    <!-- Success message box -->
    <div class="success-container">
        <h1>✅ Appointment Booked Successfully!</h1>
        <p>Your appointment has been confirmed. You’ll receive an email if reminders are enabled.</p>
        
        <!-- Button to send patient back to their dashboard -->
        <a href="patient_dashboard.php">Go to Dashboard</a>
    </div>
</body>
</html>
