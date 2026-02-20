<?php
session_start();

// Require authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'dp.php'; // database connection

header('Content-Type: application/json');

try {
    $employee_id = $_POST['employee_id'] ?? null;
    $cycle_id = $_POST['cycle_id'] ?? null;
    $competencies_json = $_POST['competencies'] ?? null;

    if (!$employee_id || !$cycle_id || !$competencies_json) {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        exit;
    }

    // Decode the competencies JSON
    $competencies = json_decode($competencies_json, true);
    
    if (!is_array($competencies) || count($competencies) === 0) {
        echo json_encode(['success' => false, 'message' => 'No competencies to save']);
        exit;
    }

    $updated_count = 0;
    $today = date('Y-m-d');

    foreach ($competencies as $comp) {
        $competency_id = $comp['competency_id'] ?? null;
        $rating = $comp['rating'] ?? null;
        $comments = $comp['comments'] ?? '';

        if (!$competency_id) {
            continue;
        }

        // Check if a record exists for this employee, competency, and cycle
        $checkStmt = $conn->prepare("
            SELECT employee_id FROM employee_competencies 
            WHERE employee_id = :employee_id 
            AND competency_id = :competency_id 
            AND cycle_id = :cycle_id
            LIMIT 1
        ");
        $checkStmt->execute([
            ':employee_id' => $employee_id,
            ':competency_id' => $competency_id,
            ':cycle_id' => $cycle_id
        ]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing record
            $updateStmt = $conn->prepare("
                UPDATE employee_competencies 
                SET rating = :rating, 
                    comments = :comments, 
                    assessment_date = :assessment_date,
                    updated_at = NOW()
                WHERE employee_id = :employee_id 
                AND competency_id = :competency_id 
                AND cycle_id = :cycle_id
            ");
            $updateStmt->execute([
                ':rating' => $rating ?: null,
                ':comments' => $comments,
                ':assessment_date' => $today,
                ':employee_id' => $employee_id,
                ':competency_id' => $competency_id,
                ':cycle_id' => $cycle_id
            ]);
        } else {
            // Insert new record
            $insertStmt = $conn->prepare("
                INSERT INTO employee_competencies 
                (employee_id, competency_id, cycle_id, rating, comments, assessment_date, created_at, updated_at)
                VALUES 
                (:employee_id, :competency_id, :cycle_id, :rating, :comments, :assessment_date, NOW(), NOW())
            ");
            $insertStmt->execute([
                ':employee_id' => $employee_id,
                ':competency_id' => $competency_id,
                ':cycle_id' => $cycle_id,
                ':rating' => $rating ?: null,
                ':comments' => $comments,
                ':assessment_date' => $today
            ]);
        }

        $updated_count++;
    }

    if ($updated_count > 0) {
        echo json_encode(['success' => true, 'message' => "Successfully updated $updated_count competency(ies)"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No records were updated']);
    }

} catch (Exception $e) {
    error_log('update_employee_evaluation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
