<?php
// assign_competency.php
header('Content-Type: application/json');
require_once 'config.php'; // uses $conn (PDO)

try {
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
    $cycle_id = filter_input(INPUT_POST, 'cycle_id', FILTER_VALIDATE_INT);
    $assessment_date = $_POST['assessment_date'] ?? date('Y-m-d');

    // ✅ Basic validation
    if (!$employee_id || !$cycle_id) {
        echo json_encode(['success' => false, 'message' => 'Missing employee_id or cycle_id.']);
        exit;
    }

    // Parse competencies from JSON string (sent by frontend) or from individual arrays (legacy support)
    $competencies = [];
    if (isset($_POST['competencies'])) {
        $competencies = json_decode($_POST['competencies'], true);
        if (!is_array($competencies)) {
            echo json_encode(['success' => false, 'message' => 'Invalid competencies data format.']);
            exit;
        }
    } else {
        // Legacy support: also accept separate arrays
        $competency_ids = $_POST['competency_ids'] ?? [];
        $ratings = $_POST['ratings'] ?? [];
        $notes = $_POST['notes'] ?? [];

        if (!is_array($competency_ids) || !is_array($ratings) || !is_array($notes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
            exit;
        }

        if (count($competency_ids) !== count($ratings) || count($ratings) !== count($notes)) {
            echo json_encode(['success' => false, 'message' => 'Mismatched array lengths.']);
            exit;
        }

        // Convert to competency objects format
        for ($i = 0; $i < count($competency_ids); $i++) {
            $competencies[] = [
                'competency_id' => $competency_ids[$i],
                'rating' => $ratings[$i],
                'comments' => $notes[$i] ?? ''
            ];
        }
    }

    if (count($competencies) === 0) {
        echo json_encode(['success' => false, 'message' => 'No competencies provided.']);
        exit;
    }

    $conn->beginTransaction();

    $sql = "INSERT INTO employee_competencies
            (employee_id, competency_id, cycle_id, rating, assessment_date, comments)
            VALUES (:employee_id, :competency_id, :cycle_id, :rating, :assessment_date, :comments)
            ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                comments = VALUES(comments),
                updated_at = CURRENT_TIMESTAMP";

    $stmt = $conn->prepare($sql);

    $inserted = 0;
    foreach ($competencies as $comp) {
        $competency_id = filter_var($comp['competency_id'], FILTER_VALIDATE_INT);
        $rating = filter_var($comp['rating'], FILTER_VALIDATE_INT);
        $comments = trim($comp['comments'] ?? '');

        if (!$competency_id || !$rating) continue; // Skip if no rating selected

        if ($rating < 1 || $rating > 5) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Rating must be between 1–5.']);
            exit;
        }

        $stmt->execute([
            ':employee_id'     => $employee_id,
            ':competency_id'   => $competency_id,
            ':cycle_id'        => $cycle_id,
            ':rating'          => $rating,
            ':assessment_date' => $assessment_date,
            ':comments'        => $comments
        ]);

        $inserted++;
    }

    $conn->commit();

    if ($inserted > 0) {
        echo json_encode(['success' => true, 'message' => "$inserted competencies assigned or updated successfully."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No valid competencies to save.']);
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $msg = $e->getCode() === '23000'
        ? 'Invalid foreign key or duplicate record.'
        : 'Database error: ' . $e->getMessage();
    echo json_encode(['success' => false, 'message' => $msg]);
}
