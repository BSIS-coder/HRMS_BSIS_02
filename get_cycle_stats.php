<?php
header('Content-Type: application/json');
require_once 'dp.php';

$cycle_id = isset($_GET['cycle_id']) ? (int) $_GET['cycle_id'] : 0;

if ($cycle_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cycle ID']);
    exit;
}

try {
    // Count completed reviews (those in performance_reviews table)
    $sql_completed = "SELECT COUNT(DISTINCT employee_id) as completed FROM performance_reviews WHERE cycle_id = :cycle_id";
    $stmt_completed = $conn->prepare($sql_completed);
    $stmt_completed->execute([':cycle_id' => $cycle_id]);
    $completed = $stmt_completed->fetch(PDO::FETCH_ASSOC)['completed'];

    // Count pending reviews (those in employee_competencies but not in performance_reviews)
    $sql_pending = "
        SELECT COUNT(DISTINCT ec.employee_id) as pending
        FROM employee_competencies ec
        LEFT JOIN performance_reviews pr ON ec.employee_id = pr.employee_id AND ec.cycle_id = pr.cycle_id
        WHERE ec.cycle_id = :cycle_id AND pr.employee_id IS NULL
    ";
    $stmt_pending = $conn->prepare($sql_pending);
    $stmt_pending->execute([':cycle_id' => $cycle_id]);
    $pending = $stmt_pending->fetch(PDO::FETCH_ASSOC)['pending'];

    echo json_encode([
        'success' => true,
        'completed_reviews' => $completed,
        'pending_reviews' => $pending
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
