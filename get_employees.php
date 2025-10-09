<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    $sql = "SELECT personal_info_id, first_name, last_name FROM personal_information ORDER BY last_name, first_name";
    $result = $conn->query($sql);

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    echo json_encode($employees);
} catch (Exception $e) {
    echo json_encode(["error" => true, "message" => $e->getMessage()]);
}
