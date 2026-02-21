<?php
header('Content-Type: application/json');
require_once 'dp.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $cycle_id = isset($_POST['cycle_id']) ? (int) $_POST['cycle_id'] : 0;
    if ($cycle_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid cycle ID']);
        exit;
    }

    // Check current cycle status
    $check = $conn->prepare("SELECT status FROM performance_review_cycles WHERE cycle_id = :cycle_id");
    $check->execute([':cycle_id' => $cycle_id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Cycle not found']);
        exit;
    }
    $currentStatus = $row['status'] ?? '';
    if (strtolower($currentStatus) === 'completed' || strtolower($currentStatus) === 'closed') {
        echo json_encode(['success' => false, 'message' => 'Cycle already finalized']);
        exit;
    }

    $conn->beginTransaction();

    // Find employees that have competencies for this cycle but no performance_reviews entry yet
    $sel = $conn->prepare(
        "SELECT ep.employee_id, CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name, jr.title AS role, AVG(ec.rating) AS avg_rating
         FROM employee_competencies ec
         INNER JOIN employee_profiles ep ON ec.employee_id = ep.employee_id
         INNER JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
         LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
         LEFT JOIN performance_reviews pr ON ep.employee_id = pr.employee_id AND ec.cycle_id = pr.cycle_id
         WHERE ec.cycle_id = :cycle_id AND pr.employee_id IS NULL
         GROUP BY ep.employee_id"
    );
    $sel->execute([':cycle_id' => $cycle_id]);
    $toInsert = $sel->fetchAll(PDO::FETCH_ASSOC);

    $insertStmt = $conn->prepare(
        "INSERT INTO performance_reviews (employee_id, cycle_id, review_date, overall_rating, strengths, areas_of_improvement, comments, status)
         VALUES (:employee_id, :cycle_id, CURDATE(), :overall_rating, :strengths, :areas_of_improvement, :comments, :status)"
    );

    $inserted = 0;
    foreach ($toInsert as $emp) {
        // If avg_rating is null, skip
        $avg = isset($emp['avg_rating']) ? (float) $emp['avg_rating'] : null;
        if ($avg === null) continue;

        $insertStmt->execute([
            ':employee_id' => $emp['employee_id'],
            ':cycle_id' => $cycle_id,
            ':overall_rating' => $avg,
            ':strengths' => 'N/A',
            ':areas_of_improvement' => 'N/A',
            ':comments' => "Auto-finalized for {$emp['employee_name']} ({$emp['role']})",
            ':status' => 'Finalized'
        ]);
        $inserted++;
    }

    // Update cycle status to Completed
    $upd = $conn->prepare("UPDATE performance_review_cycles SET status = 'Completed', updated_at = NOW() WHERE cycle_id = :cycle_id");
    $upd->execute([':cycle_id' => $cycle_id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Cycle finalized successfully. Inserted {$inserted} missing review(s).",
        'inserted' => $inserted
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error finalizing cycle: ' . $e->getMessage()]);
}

?>
