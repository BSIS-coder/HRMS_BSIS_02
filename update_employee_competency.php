<?php
// update_employee_competency.php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $employee_id     = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
    $competency_id   = filter_input(INPUT_POST, 'competency_id', FILTER_VALIDATE_INT);
    $assessment_date = $_POST['assessment_date'] ?? null;
    $rating          = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    $comments        = trim($_POST['comments'] ?? '');

    if (!$employee_id || !$competency_id || !$assessment_date || !$rating) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    $sql = "UPDATE employee_competencies
            SET rating = :rating, comments = :comments, updated_at = CURRENT_TIMESTAMP
            WHERE employee_id = :employee_id
              AND competency_id = :competency_id
              AND assessment_date = :assessment_date";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':rating'           => $rating,
        ':comments'         => $comments,
        ':employee_id'      => $employee_id,
        ':competency_id'    => $competency_id,
        ':assessment_date'  => $assessment_date
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
