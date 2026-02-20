<?php
// get_employees.php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $sql = "
        SELECT
            ep.employee_id,
            pi.personal_info_id,
            pi.first_name,
            pi.last_name,
            ep.job_role_id,
            jr.title AS job_role
        FROM employee_profiles ep
        INNER JOIN personal_information pi
            ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr
            ON ep.job_role_id = jr.job_role_id
        ORDER BY pi.last_name, pi.first_name
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($employees);
} catch (PDOException $e) {
    echo json_encode(["error" => true, "message" => $e->getMessage()]);
}
