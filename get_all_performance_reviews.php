<?php
session_start();

// Require authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'config.php';

$cycle_id = $_GET['cycle_id'] ?? null;
$department = $_GET['department'] ?? null;
$status = $_GET['status'] ?? null;
$search = $_GET['search'] ?? null;

try {
    $params = [];
    
    // Main query to get all performance reviews using employee_profiles (no personal_information table)
    $query = "
        SELECT 
            pr.employee_id,
            pr.cycle_id,
            CASE 
                WHEN ep.work_email IS NOT NULL AND ep.work_email != '' THEN 
                    TRIM(REPLACE(REPLACE(REPLACE(REPLACE(ep.work_email, '@municipality.gov.ph', ''), '.', ' '), '_', ' '), '  ', ' '))
                ELSE ep.employee_number
            END AS employee_name,
            jr.department AS department,
            jr.title AS role,
            pc.cycle_name,
            pc.start_date,
            pc.end_date,
            pr.overall_rating AS avg_rating,
            pr.review_date AS last_assessment_date,
            pr.comments AS manager_comments,
            pr.status,
            COUNT(ec.competency_id) AS competencies_assessed
        FROM performance_reviews pr
        INNER JOIN employee_profiles ep ON pr.employee_id = ep.employee_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN performance_review_cycles pc ON pr.cycle_id = pc.cycle_id
        LEFT JOIN employee_competencies ec 
            ON ec.employee_id = pr.employee_id AND ec.cycle_id = pr.cycle_id
        WHERE 1=1
    ";

    // Apply filters
    if (!empty($cycle_id)) {
        $query .= " AND pr.cycle_id = :cycle_id";
        $params[':cycle_id'] = $cycle_id;
    }

    if (!empty($department)) {
        $query .= " AND jr.department = :department";
        $params[':department'] = $department;
    }

    if (!empty($status)) {
        $query .= " AND pr.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($search)) {
        $query .= " AND (ep.work_email LIKE :search OR ep.employee_number LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $query .= "
        GROUP BY pr.review_id, pr.employee_id, pr.cycle_id, ep.employee_number, ep.work_email, 
                 jr.department, jr.title, pc.cycle_name, pc.start_date, pc.end_date,
                 pr.overall_rating, pr.review_date, pr.comments, pr.status
        ORDER BY pr.review_date DESC, pr.employee_id
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no reviews found, try alternative query - get reviews from employee_competencies if performance_reviews table is empty
    if (empty($reviews)) {
        $altQuery = "
            SELECT 
                ec.employee_id,
                ec.cycle_id,
                CASE 
                    WHEN ep.work_email IS NOT NULL AND ep.work_email != '' THEN 
                        TRIM(REPLACE(REPLACE(REPLACE(REPLACE(ep.work_email, '@municipality.gov.ph', ''), '.', ' '), '_', ' '), '  ', ' '))
                    ELSE ep.employee_number
                END AS employee_name,
                jr.department AS department,
                jr.title AS role,
                pc.cycle_name,
                pc.start_date,
                pc.end_date,
                ROUND(AVG(ec.rating), 2) AS avg_rating,
MAX(ec.assessment_date) AS last_assessment_date,
                '' AS manager_comments,
                CASE 
                    WHEN MAX(ec.rating) > 0 THEN 'Finalized'
                    ELSE 'Draft'
                END AS status,
                COUNT(ec.competency_id) AS competencies_assessed
            FROM employee_competencies ec
            INNER JOIN employee_profiles ep ON ec.employee_id = ep.employee_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN performance_review_cycles pc ON ec.cycle_id = pc.cycle_id
            WHERE ec.rating > 0
            GROUP BY ec.employee_id, ec.cycle_id, ep.employee_number, ep.work_email, 
                     jr.department, jr.title, pc.cycle_name, pc.start_date, pc.end_date
            ORDER BY last_assessment_date DESC
        ";
        
        $stmt = $conn->query($altQuery);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'reviews' => $reviews]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
