<?php
header('Content-Type: application/json');
include __DIR__ . '/db_connect.php';

if (!isset($_GET['employee_id'])) {
    echo json_encode(['error' => 'Employee ID is required']);
    exit;
}

$employee_id = intval($_GET['employee_id']);

// Fetch employee details along with job role
$stmt = $conn->prepare("
    SELECT ep.employee_id,
           CONCAT(pi.first_name, ' ', pi.last_name) AS name,
           jr.title AS job_role,
           ep.job_role_id
    FROM employee_profiles ep
    JOIN personal_information pi 
        ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr 
        ON ep.job_role_id = jr.job_role_id
    WHERE ep.employee_id = ?
");

if (!$stmt) {
    echo json_encode(['error' => $conn->error]);
    exit;
}

$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

// If job_role_id is null, return it as 0 for JS handling
if ($employee && $employee['job_role_id'] === null) {
    $employee['job_role_id'] = 0;
    $employee['job_role'] = 'No role assigned';
}

echo json_encode($employee ?: ['error' => 'Employee not found']);

$stmt->close();
$conn->close();
