<?php
$servername = "localhost";   // usually "localhost"
$username   = "root";        // your MySQL username
$password   = "";            // your MySQL password ("" if none)
$dbname     = "hr_system";     // your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
