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
    $cycleType = trim($_POST['cycleType'] ?? 'monthly');
    $startDate = trim($_POST['startDate'] ?? '');
    $endDate   = trim($_POST['endDate'] ?? '');

    // Validate cycle type
    $valid_cycle_types = ['weekly', 'monthly', 'quarterly', 'semi-annual', 'annual'];
    if (!in_array($cycleType, $valid_cycle_types)) {
        $cycleType = 'monthly'; // Default
    }

    if ($cycleName === '' || $startDate === '' || $endDate === '') {
        echo json_encode([
            "success" => false,
            "message" => "All fields are required."
        ]);
        exit;
    }

    // Determine status based on dates with exact ENUM values
    $today = new DateTime();
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    if ($today < $start) {
        $status = 'Upcoming';
    } elseif ($today > $end) {
        $status = 'Completed';
    } else {
        $status = 'In Progress';
    }
    
    // Check if review_frequency column exists, if not add it
    $checkColumn = $conn->query("SHOW COLUMNS FROM performance_review_cycles LIKE 'review_frequency'");
    if ($checkColumn->rowCount() === 0) {
        $conn->exec("ALTER TABLE performance_review_cycles ADD COLUMN review_frequency ENUM('weekly', 'monthly', 'quarterly', 'semi-annual', 'annual') DEFAULT 'monthly'");
    }

    // Insert into DB with explicit ENUM handling
    $stmt = $conn->prepare("
        INSERT INTO performance_review_cycles (cycle_name, review_frequency, start_date, end_date, status, created_at) 
        VALUES (:cycle_name, :review_frequency, :start_date, :end_date, :status, NOW())
    ");
    
    // Ensure status is exactly one of the ENUM values
    $valid_statuses = ['Upcoming', 'In Progress', 'Completed'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'Upcoming'; // Default fallback
    }
    
    $stmt->execute([
        ':cycle_name' => $cycleName,
        ':review_frequency' => $cycleType,
        ':start_date' => $startDate,
        ':end_date'   => $endDate,
        ':status'     => $status
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
