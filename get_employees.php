<?php
// get_employees.php
header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $sql = "
        SELECT 
            ep.employee_id,
            pi.personal_info_id,
            pi.first_name,
            pi.last_name
        FROM employee_profiles ep
        INNER JOIN personal_information pi 
            ON ep.personal_info_id = pi.personal_info_id
        ORDER BY pi.last_name, pi.first_name
    ";

    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode(["error" => true, "message" => $conn->error]);
        exit;
    }

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    echo json_encode($employees);
} catch (Throwable $e) {
    echo json_encode(["error" => true, "message" => $e->getMessage()]);
}

$conn->close();
