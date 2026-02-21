<?php
$servername = getenv('DB_HOST') ?? "localhost";   // usually "localhost"
$username   = getenv('DB_USER') ?? "root";        // your MySQL username
$password   = getenv('DB_PASS') ?? "";            // your MySQL password ("" if none)
$dbname     = getenv('DB_NAME') ?? "hr_system";     // your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
