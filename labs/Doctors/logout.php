<?php
// Start the session (needed to destroy it later)
session_start();

// Destroy all session data (logs the user out completely)
session_destroy();

// Redirect the user back to the login page after logout
header("Location: login.php");
exit(); // Stop script execution to ensure redirect happens immediately
?>
