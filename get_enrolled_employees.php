<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if user has permission (only admin and hr can view enrolled employees)
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
    echo json_encode(['error' => 'Insufficient permissions']);
    exit;
}

// Include database connection
require_once 'config.php';

// Check if plan_id is provided
if (!isset($_POST['plan_id']) || empty($_POST['plan_id'])) {
    echo json_encode(['error' => 'Plan ID is required']);
    exit;
}

$plan_id = $_POST['plan_id'];

try {
    // Fetch enrolled employees for the specified benefit plan
    $sql = "SELECT
                eb.employee_id,
                eb.enrollment_date,
                eb.benefit_amount,
                eb.status,
                pi.first_name,
                pi.last_name,
                jr.title as position,
                d.department_name as department
            FROM employee_benefits eb
            JOIN employee_profiles ep ON eb.employee_id = ep.employee_id
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE eb.benefit_plan_id = ? AND eb.status = 'Active'
            ORDER BY pi.first_name, pi.last_name";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$plan_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the data as JSON
    echo json_encode([
        'success' => true,
        'employees' => $employees,
        'total_count' => count($employees)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
