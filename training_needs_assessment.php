<?php
// Training Needs are managed in Skills & Assessment (skill_matrix.php).
// Redirect so there is one combined place for skills and training needs.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}
header("Location: skill_matrix.php?tab=needs");
exit;
