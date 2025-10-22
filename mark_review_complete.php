<?php
header('Content-Type: application/json');
require_once 'dp.php';

try {
    $employee_id = $_POST['employee_id'] ?? null;
    $cycle_id = $_POST['cycle_id'] ?? null;

    if (!$employee_id || !$cycle_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        exit;
    }

    // Fetch review summary from joined tables
    $stmt = $conn->prepare("
        SELECT 
            ep.employee_id,
            CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
            jr.title AS role,
            jr.department AS department,
            AVG(ec.rating) AS avg_rating
        FROM employee_competencies ec
        INNER JOIN employee_profiles ep ON ec.employee_id = ep.employee_id
        INNER JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        INNER JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        WHERE ec.employee_id = :employee_id AND ec.cycle_id = :cycle_id
        GROUP BY ep.employee_id
    ");
    $stmt->execute(['employee_id' => $employee_id, 'cycle_id' => $cycle_id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'No review data found for this employee.']);
        exit;
    }

    // Insert summary into performance_reviews table
    $insert = $conn->prepare("
        INSERT INTO performance_reviews 
            (employee_id, cycle_id, review_date, overall_rating, strengths, areas_of_improvement, comments, status)
        VALUES 
            (:employee_id, :cycle_id, CURDATE(), :overall_rating, :strengths, :areas_of_improvement, :comments, :status)
    ");
    $insert->execute([
        ':employee_id' => $review['employee_id'],
        ':cycle_id' => $cycle_id,
        ':overall_rating' => $review['avg_rating'] ?? 0,
        ':strengths' => 'N/A', // you can change or auto-generate later
        ':areas_of_improvement' => 'N/A',
        ':comments' => "Auto-finalized for {$review['employee_name']} ({$review['role']})",
        ':status' => 'Finalized'
    ]);

    echo json_encode(['success' => true, 'message' => 'Review successfully marked as complete and saved to performance_reviews.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
