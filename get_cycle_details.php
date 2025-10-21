<?php
header('Content-Type: application/json');
require_once 'dp.php';

try {
    $cycle_id = filter_input(INPUT_GET, 'cycle_id', FILTER_VALIDATE_INT);

    if (!$cycle_id) {
        echo json_encode(['error' => 'Invalid cycle_id']);
        exit;
    }

    // ✅ Use the correct table name (performance_review_cycles)
    $stmt = $conn->prepare("
        SELECT cycle_name, start_date, end_date 
        FROM performance_review_cycles 
        WHERE cycle_id = :cycle_id
    ");
    $stmt->execute([':cycle_id' => $cycle_id]);
    $cycle = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cycle) {
        echo json_encode($cycle);
    } else {
        echo json_encode(['error' => 'Cycle not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
