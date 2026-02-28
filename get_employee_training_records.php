<?php
// get_employee_training_records.php
// API to fetch employee training records and certifications

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include database connection
require_once 'dp.php';

$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit;
}

try {
    // First check if tables exist
    $trainingTablesExist = true;
    try {
        $conn->query("SELECT 1 FROM training_enrollments LIMIT 1");
    } catch (PDOException $e) {
        $trainingTablesExist = false;
    }
    
    // Check if personal_information table exists
    $personalInfoExists = true;
    try {
        $conn->query("SELECT 1 FROM personal_information LIMIT 1");
    } catch (PDOException $e) {
        $personalInfoExists = false;
    }
    
    $enrollments = [];
    $certifications = [];
    $employee = null;
    
    if ($trainingTablesExist) {
        // Fetch training enrollments
        $enrollments_sql = "
            SELECT 
                te.enrollment_id,
                te.session_id,
                te.employee_id,
                te.enrollment_date,
                te.status as enrollment_status,
                te.completion_date,
                te.score,
                te.feedback,
                tc.course_name,
                tc.category as course_category,
                tc.duration,
                ts.session_name,
                ts.start_date,
                ts.end_date,
                ts.location,
                CONCAT(t.first_name, ' ', t.last_name) as trainer_name
            FROM training_enrollments te
            LEFT JOIN training_sessions ts ON te.session_id = ts.session_id
            LEFT JOIN training_courses tc ON ts.course_id = tc.course_id
            LEFT JOIN trainers t ON ts.trainer_id = t.trainer_id
            WHERE te.employee_id = ?
            ORDER BY ts.start_date DESC
        ";
        
        $stmt = $conn->prepare($enrollments_sql);
        $stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
        $stmt->execute();
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch certifications - without notes column
        $certifications_sql = "
            SELECT 
                c.certification_id,
                c.certification_name,
                c.issuing_organization,
                c.certification_number,
                c.category,
                c.proficiency_level,
                c.assessment_score,
                c.issue_date,
                c.expiry_date,
                c.status as certification_status,
                c.training_hours,
                c.description
            FROM certifications c
            WHERE c.employee_id = ?
            ORDER BY c.issue_date DESC
        ";
        
        $stmt2 = $conn->prepare($certifications_sql);
        $stmt2->bindParam(1, $employee_id, PDO::PARAM_INT);
        $stmt2->execute();
        $certifications = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Fetch employee info - handle both cases (with or without personal_information table)
    if ($personalInfoExists) {
        // Check if email column exists in personal_information
        $emailColumnExists = true;
        try {
            $conn->query("SELECT email FROM personal_information LIMIT 1");
        } catch (PDOException $e) {
            $emailColumnExists = false;
        }
        
        if ($emailColumnExists) {
            $employee_sql = "
                SELECT 
                    ep.employee_id,
                    ep.employee_number,
                    pi.first_name,
                    pi.last_name,
                    pi.email,
                    jr.title as job_title,
                    d.department_name
                FROM employee_profiles ep
                LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                LEFT JOIN departments d ON jr.department = d.department_name
                WHERE ep.employee_id = ?
            ";
        } else {
            $employee_sql = "
                SELECT 
                    ep.employee_id,
                    ep.employee_number,
                    pi.first_name,
                    pi.last_name,
                    jr.title as job_title,
                    d.department_name
                FROM employee_profiles ep
                LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                LEFT JOIN departments d ON jr.department = d.department_name
                WHERE ep.employee_id = ?
            ";
        }
    } else {
        // Fallback without personal_information table
        $employee_sql = "
            SELECT 
                ep.employee_id,
                ep.employee_number,
                ep.work_email,
                jr.title as job_title,
                d.department_name
            FROM employee_profiles ep
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE ep.employee_id = ?
        ";
    }
    
    $stmt3 = $conn->prepare($employee_sql);
    $stmt3->bindParam(1, $employee_id, PDO::PARAM_INT);
    $stmt3->execute();
    $employee = $stmt3->fetch(PDO::FETCH_ASSOC);
    
    // Calculate training statistics
    $total_trainings = count($enrollments);
    $completed_trainings = count(array_filter($enrollments, function($e) {
        return $e['enrollment_status'] === 'Completed';
    }));
    $in_progress_trainings = count(array_filter($enrollments, function($e) {
        return $e['enrollment_status'] === 'Enrolled';
    }));
    $total_certifications = count($certifications);
    $active_certifications = count(array_filter($certifications, function($c) {
        return $c['certification_status'] === 'Active';
    }));
    
    // Calculate average score
    $scores = array_filter(array_map(function($e) {
        return $e['score'];
    }, $enrollments));
    $avg_score = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
    
    $training_stats = [
        'total_trainings' => $total_trainings,
        'completed_trainings' => $completed_trainings,
        'in_progress_trainings' => $in_progress_trainings,
        'total_certifications' => $total_certifications,
        'active_certifications' => $active_certifications,
        'average_score' => $avg_score
    ];
    
    echo json_encode([
        'success' => true,
        'employee' => $employee,
        'enrollments' => $enrollments,
        'certifications' => $certifications,
        'training_stats' => $training_stats
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching training records: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching training records: ' . $e->getMessage()
    ]);
}
