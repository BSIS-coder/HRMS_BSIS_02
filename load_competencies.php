<?php
include 'db_connect.php';

$role_id = $_GET['role_id'] ?? '';
$department = $_GET['department'] ?? '';

$conditions = [];
$params = [];
$types = '';

if ($role_id) {
    $conditions[] = "c.job_role_id = ?";
    $params[] = $role_id;
    $types .= 'i';
}

if ($department) {
    $conditions[] = "r.department = ?";
    $params[] = $department;
    $types .= 's';
}

$whereClause = '';
if (!empty($conditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$query = "SELECT c.competency_id, c.name, c.description, r.title AS role
          FROM competencies c
          LEFT JOIN job_roles r ON c.job_role_id = r.job_role_id
          $whereClause
          ORDER BY c.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>
