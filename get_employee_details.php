<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
require_once 'config.php';

// Get employee_id from request parameters (supports both GET and POST)
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : (isset($_POST['employee_id']) ? $_POST['employee_id'] : null);

// Validate employee_id
if ($employee_id) {
    try {
        $sql = "SELECT
            ep.employee_id,
            CONCAT(pi.first_name, ' ', pi.last_name) AS name,
            jr.title AS job_role,
            ep.job_role_id
        FROM employee_profiles ep
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        WHERE ep.employee_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee) {
            header('Content-Type: application/json');
            echo json_encode($employee);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Employee not found']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request']);
}
?>
