<?php
include 'db_connect.php';
header('Content-Type: application/json');

$name = trim($_POST['competency_name'] ?? '');
$description = $_POST['description'] ?? '';
$job_role_id = $_POST['job_role_id'] ?? '';
$category = $_POST['category'] ?? 'Technical';

// Validate name is not empty
if (empty($name)) {
    echo json_encode(["success" => false, "message" => "Competency name is required."]);
    exit;
}

try {
    // Check if competency with same name already exists
    $checkStmt = $conn->prepare("SELECT competency_id FROM competencies WHERE LOWER(name) = LOWER(?)");
    $checkStmt->bind_param("s", $name);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "A competency with this name already exists."]);
        exit;
    }
    
    // Insert new competency
    $stmt = $conn->prepare("INSERT INTO competencies (name, description, job_role_id, category) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $description, $job_role_id, $category);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Competency added successfully."]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
