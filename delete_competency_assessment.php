<?php
session_start();

// Require authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'config.php'; // database connection

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$employee_id = $_POST['employee_id'] ?? null;
$competency_id = $_POST['competency_id'] ?? null;
$cycle_id = $_POST['cycle_id'] ?? null;

if (!$employee_id || !is_numeric($employee_id) || !$competency_id || !is_numeric($competency_id) || !$cycle_id || !is_numeric($cycle_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Check if the assessment exists
    $stmt = $conn->prepare("SELECT employee_id FROM employee_competencies WHERE employee_id = ? AND competency_id = ? AND cycle_id = ?");
    $stmt->execute([$employee_id, $competency_id, $cycle_id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Assessment not found']);
        exit;
    }

    // Delete the assessment
    $stmt = $conn->prepare("DELETE FROM employee_competencies WHERE employee_id = ? AND competency_id = ? AND cycle_id = ?");
    $stmt->execute([$employee_id, $competency_id, $cycle_id]);

    echo json_encode(['success' => true, 'message' => 'Competency assessment deleted successfully']);
} catch (Exception $e) {
    error_log('Delete competency assessment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete assessment']);
}
?>
