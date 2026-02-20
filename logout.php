<?php
// Initialize the session
session_start();

// Log the logout
$username = $_SESSION['username'] ?? 'Unknown';
$user_id = $_SESSION['user_id'] ?? 'Unknown';
$session_id = session_id();
error_log("User logout: username=$username, user_id=$user_id, session=$session_id, IP={$_SERVER['REMOTE_ADDR']}");

// Unset all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("location: login.php");
exit;
?> 