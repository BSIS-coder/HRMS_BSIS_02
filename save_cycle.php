<?php
header('Content-Type: application/json'); // Always return JSON
require_once 'config.php'; // database connection

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "success" => false,
            "message" => "Invalid request method: " . $_SERVER['REQUEST_METHOD']
        ]);
        exit;
    }

    // Sanitize input
    $cycleName = trim($_POST['cycleName'] ?? '');
    $startDate = trim($_POST['startDate'] ?? '');
    $endDate   = trim($_POST['endDate'] ?? '');

    if ($cycleName === '' || $startDate === '' || $endDate === '') {
        echo json_encode([
            "success" => false,
            "message" => "All fields are required."
        ]);
        exit;
    }

    // Insert into DB
    $stmt = $conn->prepare("
        INSERT INTO performance_review_cycles (cycle_name, start_date, end_date, status, created_at) 
        VALUES (:cycle_name, :start_date, :end_date, 'Active', NOW())
    ");
    $stmt->execute([
        ':cycle_name' => $cycleName,
        ':start_date' => $startDate,
        ':end_date'   => $endDate
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Cycle added successfully."
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}
