<?php
// Always return JSON
header('Content-Type: application/json');
require_once 'dp.php';

try {
    // Fetch all cycles ordered by newest first
    $sql = "
        SELECT cycle_id, cycle_name, start_date, end_date, status
        FROM performance_review_cycles
        ORDER BY created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "cycles" => $cycles
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
    exit;
}
