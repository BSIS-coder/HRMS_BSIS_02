<?php
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_GET['job_role_id'])) {
    echo json_encode(['error' => 'Job Role ID is required']);
    exit;
}

$job_role_id = intval($_GET['job_role_id']);

try {
    $sql = "SELECT competency_id, name
            FROM competencies
            WHERE job_role_id = ?
            ORDER BY name";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$job_role_id]);
    $competencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($competencies);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
