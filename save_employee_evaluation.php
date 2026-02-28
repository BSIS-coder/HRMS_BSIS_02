<?php
// save_employee_evaluation.php
header('Content-Type: application/json');
require_once 'config.php'; // PDO connection

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Try FormData as fallback
    $employee_id = isset($_POST['employee_id']) ? (int) $_POST['employee_id'] : 0;
    $cycle_id = isset($_POST['cycle_id']) ? (int) $_POST['cycle_id'] : 0;
    $evaluations = isset($_POST['evaluations']) ? json_decode($_POST['evaluations'], true) : [];
    $additional_comments = isset($_POST['additional_comments']) ? $_POST['additional_comments'] : '';
} else {
    $employee_id = isset($input['employee_id']) ? (int) $input['employee_id'] : 0;
    $cycle_id = isset($input['cycle_id']) ? (int) $input['cycle_id'] : 0;
    $evaluations = isset($input['evaluations']) ? $input['evaluations'] : [];
    $additional_comments = isset($input['additional_comments']) ? $input['additional_comments'] : '';
}

// Debug: Log received values
error_log("Received employee_id: " . var_export($employee_id, true) . ", cycle_id: " . var_export($cycle_id, true));

// Validate - ensure they are positive integers
if (empty($employee_id) || $employee_id <= 0 || empty($cycle_id) || $cycle_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee or cycle. Employee ID: ' . $employee_id . ', Cycle ID: ' . $cycle_id]);
    exit;
}

if (empty($evaluations)) {
    echo json_encode(['success' => false, 'message' => 'No evaluations provided']);
    exit;
}

try {
    $conn->beginTransaction();
    
    $currentDate = date('Y-m-d');
    
    foreach ($evaluations as $eval) {
        $competency_id = isset($eval['competency_id']) ? (int) $eval['competency_id'] : 0;
        $rating = isset($eval['rating']) ? (int) $eval['rating'] : 0;
        $comment = isset($eval['comment']) ? $eval['comment'] : '';
        
        if ($competency_id <= 0 || $rating <= 0) {
            continue;
        }
        
        // Check if evaluation already exists for this employee/cycle/competency
        // Use assessment_date to check for existing records (composite primary key includes assessment_date)
        $checkSql = "SELECT employee_id, competency_id, assessment_date 
                     FROM employee_competencies 
                     WHERE employee_id = :employee_id 
                     AND cycle_id = :cycle_id 
                     AND competency_id = :competency_id
                     ORDER BY assessment_date DESC
                     LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([
            ':employee_id' => $employee_id,
            ':cycle_id' => $cycle_id,
            ':competency_id' => $competency_id
        ]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing evaluation (use today's date to match or create new record)
            $updateSql = "UPDATE employee_competencies 
                         SET rating = :rating, 
                             comments = :comments, 
                             assessment_date = :assessment_date,
                             updated_at = NOW()
                         WHERE employee_id = :employee_id 
                         AND cycle_id = :cycle_id 
                         AND competency_id = :competency_id
                         AND assessment_date = :existing_date";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([
                ':rating' => $rating,
                ':comments' => $comment,
                ':assessment_date' => $currentDate,
                ':employee_id' => $employee_id,
                ':cycle_id' => $cycle_id,
                ':competency_id' => $competency_id,
                ':existing_date' => $existing['assessment_date']
            ]);
            
            // If no rows updated (date mismatch), insert new record
            if ($updateStmt->rowCount() === 0) {
                $insertSql = "INSERT INTO employee_competencies 
                             (employee_id, competency_id, cycle_id, rating, comments, assessment_date) 
                             VALUES (:employee_id, :competency_id, :cycle_id, :rating, :comments, :assessment_date)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->execute([
                    ':employee_id' => $employee_id,
                    ':competency_id' => $competency_id,
                    ':cycle_id' => $cycle_id,
                    ':rating' => $rating,
                    ':comments' => $comment,
                    ':assessment_date' => $currentDate
                ]);
            }
        } else {
            // Insert new evaluation
            $insertSql = "INSERT INTO employee_competencies 
                         (employee_id, competency_id, cycle_id, rating, comments, assessment_date) 
                         VALUES (:employee_id, :competency_id, :cycle_id, :rating, :comments, :assessment_date)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->execute([
                ':employee_id' => $employee_id,
                ':competency_id' => $competency_id,
                ':cycle_id' => $cycle_id,
                ':rating' => $rating,
                ':comments' => $comment,
                ':assessment_date' => $currentDate
            ]);
        }
    }
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Evaluation saved successfully']);
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
