<?php
header('Content-Type: application/json');
require_once 'dp.php';

try {
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
    $cycle_id = filter_input(INPUT_POST, 'cycle_id', FILTER_VALIDATE_INT);
    $competencies = json_decode($_POST['competencies'] ?? '[]', true);

    if (!$employee_id || !$cycle_id || !is_array($competencies)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    $conn->beginTransaction();

    // Delete existing competencies for this employee and cycle
    $deleteSql = "DELETE FROM employee_competencies WHERE employee_id = :employee_id AND cycle_id = :cycle_id";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->execute([':employee_id' => $employee_id, ':cycle_id' => $cycle_id]);

    // Insert new competencies
    foreach ($competencies as $comp) {
        $competency_id = (int) $comp['competency_id'];
        $rating = (int) $comp['rating'];
        $comments = trim($comp['comments'] ?? '');

        $insertSql = "INSERT INTO employee_competencies (employee_id, competency_id, cycle_id, rating, assessment_date, comments, updated_at)
                      VALUES (:employee_id, :competency_id, :cycle_id, :rating, CURDATE(), :comments, CURRENT_TIMESTAMP)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->execute([
            ':employee_id' => $employee_id,
            ':competency_id' => $competency_id,
            ':cycle_id' => $cycle_id,
            ':rating' => $rating,
            ':comments' => $comments
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
