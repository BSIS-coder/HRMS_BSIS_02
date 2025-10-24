<?php
// get_employee_competencies.php
header('Content-Type: application/json');
require_once 'db_connect.php'; // MySQLi connection

$employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;

if ($employee_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "
        SELECT 
            ec.employee_id,
            ec.competency_id,
            ec.rating,
            ec.assessment_date,
            ec.comments,
            c.name,
            c.description,
            jr.title AS role
        FROM employee_competencies ec
        JOIN competencies c 
            ON ec.competency_id = c.competency_id
        JOIN employee_profiles ep 
            ON ec.employee_id = ep.employee_id
        LEFT JOIN job_roles jr 
            ON ep.job_role_id = jr.job_role_id
        WHERE ec.employee_id = ?
        ORDER BY ec.assessment_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode($rows);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
