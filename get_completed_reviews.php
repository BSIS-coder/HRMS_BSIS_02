<?php
session_start();

// Require authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'dp.php';

$cycle_id = $_GET['cycle_id'] ?? null;
$department = $_GET['department'] ?? null;

if (!$cycle_id) {
    echo json_encode(['success' => false, 'message' => 'Cycle ID required']);
    exit;
}

try {
    $params = [':cycle_id' => $cycle_id];

    // ✅ Using JOIN + GROUP BY for accurate competency counts
    $query = "
        SELECT
            pr.employee_id,
            CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
            jr.department AS department,
            jr.title AS role,
            pr.overall_rating AS avg_rating,
            pr.review_date AS last_assessment_date,
            pr.comments AS manager_comments,
            pr.status,
            COUNT(ec.competency_id) AS competencies_assessed
        FROM performance_reviews pr
        INNER JOIN employee_profiles ep ON pr.employee_id = ep.employee_id
        INNER JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN employee_competencies ec 
            ON ec.employee_id = pr.employee_id AND ec.cycle_id = pr.cycle_id
        WHERE pr.cycle_id = :cycle_id
          AND pr.status = 'Finalized'
    ";

    if (!empty($department)) {
        $query .= " AND jr.department = :department";
        $params[':department'] = $department;
    }

    $query .= "
        GROUP BY pr.employee_id, pi.first_name, pi.last_name, jr.department, jr.title,
                 pr.overall_rating, pr.review_date, pr.comments, pr.status
        ORDER BY pr.review_date DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'reviews' => $reviews]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
