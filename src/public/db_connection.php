<?php
// Database connection parameters
$servername = "localhost";
$username = "lms_system";
$password = "7F2U5oH#";
$dbname = "LMS";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
