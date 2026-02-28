<?php
header('Content-Type: application/json');
require_once 'config.php';

$competency_id = isset($_POST['competency_id']) ? (int)$_POST['competency_id'] : 0;
$name = isset($_POST['competency_name']) ? trim($_POST['competency_name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$job_role_id = isset($_POST['job_role_id']) && $_POST['job_role_id'] !== '' ? (int)$_POST['job_role_id'] : null;

if ($competency_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid competency ID']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Competency name is required']);
    exit;
}

try {
    $sql = "UPDATE competencies SET name = :name, description = :description, job_role_id = :job_role_id WHERE competency_id = :competency_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':description' => $description,
        ':job_role_id' => $job_role_id,
        ':competency_id' => $competency_id
    ]);

    echo json_encode(['success' => true, 'message' => 'Competency updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating competency: ' . $e->getMessage()]);
}
?>
