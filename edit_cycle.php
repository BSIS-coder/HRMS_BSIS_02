<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$cycle_id = $_POST['cycle_id'] ?? null;
$cycle_name = $_POST['cycle_name'] ?? null;
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;

if (!$cycle_id || !$cycle_name || !$start_date || !$end_date) {
    echo json_encode(["success" => false, "message" => "All fields are required"]);
    exit;
}

// Step 2: Determine status automatically
$today = date('Y-m-d');
if ($today < $start_date) {
    $status = 'Upcoming';
} elseif ($today >= $start_date && $today <= $end_date) {
    $status = 'In Progress';
} else {
    $status = 'Completed';
}

try {
    $sql = "UPDATE performance_review_cycles 
            SET cycle_name = ?, start_date = ?, end_date = ?, status = ? 
            WHERE cycle_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$cycle_name, $start_date, $end_date, $status, $cycle_id]);

    echo json_encode(["success" => true, "message" => "Cycle updated successfully"]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
