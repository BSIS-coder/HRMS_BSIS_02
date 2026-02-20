<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$cycle_id = $_POST['cycle_id'] ?? null;

if (!$cycle_id) {
    echo json_encode(["success" => false, "message" => "Cycle ID is required"]);
    exit;
}

try {
    $sql = "DELETE FROM performance_review_cycles WHERE cycle_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$cycle_id]);

    echo json_encode(["success" => true, "message" => "Cycle deleted successfully"]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
