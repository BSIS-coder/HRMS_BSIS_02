<?php
// get_employee_competencies.php
header('Content-Type: application/json');
require_once 'config.php'; // PDO connection

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
            ec.cycle_id,
            ec.rating,
            ec.assessment_date,
            ec.comments,
            c.name,
            c.description,
            jr.title AS role
        FROM employee_competencies ec
        JOIN competencies c ON ec.competency_id = c.competency_id
        JOIN employee_profiles ep ON ec.employee_id = ep.employee_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        WHERE ec.employee_id = :employee_id
        ORDER BY ec.assessment_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':employee_id' => $employee_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
