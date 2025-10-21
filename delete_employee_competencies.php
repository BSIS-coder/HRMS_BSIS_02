<?php
// delete_employee_competencies.php
header('Content-Type: application/json');
require_once 'dp.php'; // database connection

$employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
$cycle_id = isset($_POST['cycle_id']) ? (int) $_POST['cycle_id'] : 0;

if ($employee_id <= 0 || $cycle_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee or cycle ID']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM employee_competencies WHERE employee_id = :employee_id AND cycle_id = :cycle_id");
    $stmt->execute([':employee_id' => $employee_id, ':cycle_id' => $cycle_id]);

    echo json_encode(['success' => true, 'message' => 'All competencies deleted successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
