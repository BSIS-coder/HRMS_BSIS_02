<?php
// get_cycle_competencies.php
header('Content-Type: application/json');
require_once 'dp.php'; // PDO connection

$cycle_id = isset($_GET['cycle_id']) ? (int) $_GET['cycle_id'] : 0;

if ($cycle_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'No valid cycle selected', 'cycle_id_received' => $_GET['cycle_id'] ?? null]);
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
            CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
            d.department_name AS department,
            jr.title AS role,
            c.name AS competency_name,
            c.description AS competency_description
        FROM employee_competencies ec
        JOIN employee_profiles ep ON ec.employee_id = ep.employee_id
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        JOIN competencies c ON ec.competency_id = c.competency_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_name
        WHERE ec.cycle_id = :cycle_id
        ORDER BY ec.assessment_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':cycle_id' => $cycle_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'competencies' => $rows]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
