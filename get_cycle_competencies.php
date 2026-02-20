<?php
// get_cycle_competencies.php
header('Content-Type: application/json');
require_once 'dp.php'; // database connection

$cycle_id = isset($_GET['cycle_id']) ? (int) $_GET['cycle_id'] : 0;
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';

if ($cycle_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No valid cycle selected',
        'cycle_id_received' => $_GET['cycle_id'] ?? null
    ]);
    exit;
}

try {
    $deptCondition = !empty($department) ? " AND d.department_name = :department" : "";

    if ($status === 'all') {
        // Query for pending reviews
        $sql_pending = "
            SELECT
                ec.employee_id,
                CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
                d.department_name AS department,
                jr.title AS role,
                ROUND(AVG(ec.rating), 2) AS avg_rating,
                COUNT(ec.competency_id) AS competencies_assessed,
                MAX(ec.assessment_date) AS last_assessment_date
            FROM employee_competencies ec
            JOIN employee_profiles ep ON ec.employee_id = ep.employee_id
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE ec.cycle_id = :cycle_id{$deptCondition}
              AND NOT EXISTS (
                  SELECT 1 FROM performance_reviews pr
                  WHERE pr.employee_id = ec.employee_id
                    AND pr.cycle_id = ec.cycle_id
                    AND pr.status = 'Finalized'
              )
            GROUP BY ec.employee_id, pi.first_name, pi.last_name, d.department_name, jr.title
            ORDER BY last_assessment_date DESC
        ";

        $stmt = $conn->prepare($sql_pending);
        $stmt->bindParam(':cycle_id', $cycle_id, PDO::PARAM_INT);
        if (!empty($department)) {
            $stmt->bindParam(':department', $department, PDO::PARAM_STR);
        }
        $stmt->execute();
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Query for completed reviews
        $sql_completed = "
            SELECT
                pr.employee_id,
                CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
                d.department_name AS department,
                jr.title AS role,
                pr.overall_rating AS avg_rating,
                COUNT(ec.competency_id) AS competencies_assessed,
                pr.review_date AS last_assessment_date
            FROM performance_reviews pr
            JOIN employee_profiles ep ON pr.employee_id = ep.employee_id
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            LEFT JOIN employee_competencies ec
                ON ec.employee_id = pr.employee_id AND ec.cycle_id = pr.cycle_id
            WHERE pr.cycle_id = :cycle_id{$deptCondition}
              AND pr.status = 'Finalized'
            GROUP BY pr.employee_id, pi.first_name, pi.last_name, d.department_name, jr.title,
                     pr.overall_rating, pr.review_date
            ORDER BY pr.review_date DESC
        ";

        $stmt = $conn->prepare($sql_completed);
        $stmt->bindParam(':cycle_id', $cycle_id, PDO::PARAM_INT);
        if (!empty($department)) {
            $stmt->bindParam(':department', $department, PDO::PARAM_STR);
        }
        $stmt->execute();
        $completed = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Merge and sort by last_assessment_date DESC
        $rows = array_merge($pending, $completed);
        usort($rows, function($a, $b) {
            return strtotime($b['last_assessment_date'] ?? '1970-01-01') <=> strtotime($a['last_assessment_date'] ?? '1970-01-01');
        });
    } elseif ($status === 'pending') {
        // For 'pending', only from employee_competencies, exclude finalized
        $sql = "
            SELECT
                ec.employee_id,
                CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
                d.department_name AS department,
                jr.title AS role,
                ROUND(AVG(ec.rating), 2) AS avg_rating,
                COUNT(ec.competency_id) AS competencies_assessed,
                MAX(ec.assessment_date) AS last_assessment_date
            FROM employee_competencies ec
            JOIN employee_profiles ep ON ec.employee_id = ep.employee_id
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE ec.cycle_id = :cycle_id{$deptCondition}
              AND NOT EXISTS (
                  SELECT 1 FROM performance_reviews pr
                  WHERE pr.employee_id = ec.employee_id
                    AND pr.cycle_id = ec.cycle_id
                    AND pr.status = 'Finalized'
              )
            GROUP BY ec.employee_id, pi.first_name, pi.last_name, d.department_name, jr.title
            ORDER BY last_assessment_date DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':cycle_id', $cycle_id, PDO::PARAM_INT);
        if (!empty($department)) {
            $stmt->bindParam(':department', $department, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Default to pending if status not recognized
        $sql = "
            SELECT
                ec.employee_id,
                CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
                d.department_name AS department,
                jr.title AS role,
                ROUND(AVG(ec.rating), 2) AS avg_rating,
                COUNT(ec.competency_id) AS competencies_assessed,
                MAX(ec.assessment_date) AS last_assessment_date
            FROM employee_competencies ec
            JOIN employee_profiles ep ON ec.employee_id = ep.employee_id
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE ec.cycle_id = :cycle_id{$deptCondition}
              AND NOT EXISTS (
                  SELECT 1 FROM performance_reviews pr
                  WHERE pr.employee_id = ec.employee_id
                    AND pr.cycle_id = ec.cycle_id
                    AND pr.status = 'Finalized'
              )
            GROUP BY ec.employee_id, pi.first_name, pi.last_name, d.department_name, jr.title
            ORDER BY last_assessment_date DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':cycle_id', $cycle_id, PDO::PARAM_INT);
        if (!empty($department)) {
            $stmt->bindParam(':department', $department, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ Convert avg_rating to float to prevent JS .toFixed() error
    foreach ($rows as &$r) {
        $r['avg_rating'] = isset($r['avg_rating']) ? (float)$r['avg_rating'] : 0;
    }

    echo json_encode(['success' => true, 'competencies' => $rows]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
