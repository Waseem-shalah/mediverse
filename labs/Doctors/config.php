<?php
$host = "localhost";
$user = "root";
$pass = ""; // update if needed
$db = "doctors";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Local time for PHP (handles DST automatically)
date_default_timezone_set('Asia/Jerusalem');

// Match MySQL session to PHP's current offset (no tz tables needed)
if (isset($conn) && $conn instanceof mysqli) {
    $conn->query("SET time_zone = '" . date('P') . "'"); // e.g. +03:00 or +02:00
}

?>
