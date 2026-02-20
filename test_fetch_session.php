<?php
// Simulate session
session_start();
$_SESSION['loggedin'] = true;
$_SESSION['role'] = 'admin';

// Include the fetch file
require_once 'fetch_attendance_overview.php';
?>
