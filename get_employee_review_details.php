<?php
session_start();

// Require authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'dp.php'; // database connection

$employee_id = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
$cycle_id = filter_input(INPUT_GET, 'cycle_id', FILTER_VALIDATE_INT);

if (!$employee_id || !$cycle_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Fetch employee details
    $empStmt = $conn->prepare("
        SELECT pi.first_name, pi.last_name, d.department_name as dept, jr.title as role
        FROM employee_profiles ep
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_name
        WHERE ep.employee_id = :id
    ");
    $empStmt->execute([':id' => $employee_id]);
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }

    // Fetch competencies for the cycle
    $compStmt = $conn->prepare("
        SELECT c.competency_id, c.name, ec.rating, ec.comments
        FROM competencies c
        LEFT JOIN employee_competencies ec ON ec.competency_id = c.competency_id AND ec.employee_id = :emp_id AND ec.cycle_id = :cycle_id
        ORDER BY c.name
    ");
    $compStmt->execute([':emp_id' => $employee_id, ':cycle_id' => $cycle_id]);
    $competencies = $compStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate average rating
    $ratings = array_filter(array_column($competencies, 'rating'), function($r) { return $r !== null; });
    $avg_rating = count($ratings) > 0 ? array_sum($ratings) / count($ratings) : null;

    $review = [
        'employee_name' => $employee['first_name'] . ' ' . $employee['last_name'],
        'dept' => $employee['dept'],
        'role' => $employee['role'],
        'avg_rating' => $avg_rating,
        'competencies' => $competencies,
        'manager_comments' => '' // Placeholder, can be added later if needed
    ];

    echo json_encode(['success' => true, 'review' => $review]);
} catch (Exception $e) {
    error_log('get_employee_review_details error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
