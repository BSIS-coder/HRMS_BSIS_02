<?php
// get_employee_competencies.php
header('Content-Type: application/json');
require_once 'config.php'; // PDO connection

$employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$cycle_id = isset($_GET['cycle_id']) ? (int) $_GET['cycle_id'] : 0;

if ($employee_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // First, get the employee's job_role_id from employee_profiles
    $empSql = "SELECT job_role_id FROM employee_profiles WHERE employee_id = :employee_id";
    $empStmt = $conn->prepare($empSql);
    $empStmt->execute([':employee_id' => $employee_id]);
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
    
    $employee_job_role_id = $employee ? $employee['job_role_id'] : null;
    
    // Get ALL competencies that match the employee's job role OR are available to all roles
    // No need to check employee_competencies table - just return competencies based on job role
    $sql = "
        SELECT
            :employee_id AS employee_id,
            c.competency_id,
            :cycle_id AS cycle_id,
            0 AS rating,
            NULL AS assessment_date,
            '' AS comments,
            c.name,
            c.description,
            c.job_role_id,
            jr.title AS role
        FROM competencies c
        LEFT JOIN job_roles jr ON c.job_role_id = jr.job_role_id
        WHERE c.job_role_id = :emp_job_role_id OR c.job_role_id IS NULL
        ORDER BY c.name
    ";
    
    $params = [
        ':employee_id' => $employee_id,
        ':cycle_id' => $cycle_id,
        ':emp_job_role_id' => $employee_job_role_id ?? 0
    ];
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
