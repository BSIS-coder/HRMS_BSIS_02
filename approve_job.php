<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if user has HR or Admin role
if (!in_array($_SESSION['role'], ['hr', 'admin'])) {
    echo json_encode(['error' => 'Only HR and Admin can approve jobs']);
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $jobOpeningId = $_POST['job_opening_id'] ?? null;
    
    if (!$action || !$jobOpeningId) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }
    
    try {
        // Check if job exists and is pending approval
        $stmt = $conn->prepare("SELECT * FROM job_openings WHERE job_opening_id = ? AND ai_generated = TRUE AND approval_status = 'Pending'");
        $stmt->execute([$jobOpeningId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            echo json_encode(['error' => 'Job not found or already processed']);
            exit;
        }
        
        if ($action === 'approve') {
            // Check department vacancy limit before approving
            $stmt = $conn->prepare("
                SELECT d.vacancy_limit, d.department_name,
                       COALESCE(SUM(CASE WHEN jo.status = 'Open' THEN jo.vacancy_count ELSE 0 END), 0) as current_vacancies
                FROM departments d
                LEFT JOIN job_openings jo ON d.department_id = jo.department_id
                WHERE d.department_id = ?
                GROUP BY d.department_id
            ");
            $stmt->execute([$job['department_id']]);
            $deptInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($deptInfo && $deptInfo['vacancy_limit'] !== null) {
                $newTotal = $deptInfo['current_vacancies'] + $job['vacancy_count'];
                if ($newTotal > $deptInfo['vacancy_limit']) {
                    echo json_encode([
                        'error' => '⚠️ Cannot approve: This would exceed the vacancy limit for ' . $deptInfo['department_name'] . 
                                   '. Current: ' . $deptInfo['current_vacancies'] . 
                                   ', Limit: ' . $deptInfo['vacancy_limit'] . 
                                   ', Requested: ' . $job['vacancy_count']
                    ]);
                    exit;
                }
            }
            
            // Approve and auto-publish the job
            $stmt = $conn->prepare("
                UPDATE job_openings 
                SET approval_status = 'Approved',
                    status = 'Open',
                    approved_by = ?,
                    approved_at = NOW(),
                    rejection_reason = NULL
                WHERE job_opening_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $jobOpeningId]);
            
            echo json_encode([
                'success' => true,
                'message' => '✅ Job opening approved and published!'
            ]);
            
        } elseif ($action === 'reject') {
            $rejectionReason = $_POST['rejection_reason'] ?? 'No reason provided';
            
            // Reject the job
            $stmt = $conn->prepare("
                UPDATE job_openings 
                SET approval_status = 'Rejected',
                    approved_by = ?,
                    approved_at = NOW(),
                    rejection_reason = ?
                WHERE job_opening_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $rejectionReason, $jobOpeningId]);
            
            echo json_encode([
                'success' => true,
                'message' => '❌ Job opening rejected.'
            ]);
            
        } elseif ($action === 'update') {
            // Update job content before approval
            $title = $_POST['title'] ?? null;
            $description = $_POST['description'] ?? null;
            $requirements = $_POST['requirements'] ?? null;
            $responsibilities = $_POST['responsibilities'] ?? null;
            
            if (!$title || !$description || !$requirements || !$responsibilities) {
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }
            
            $stmt = $conn->prepare("
                UPDATE job_openings 
                SET title = ?,
                    description = ?,
                    requirements = ?,
                    responsibilities = ?
                WHERE job_opening_id = ?
            ");
            $stmt->execute([$title, $description, $requirements, $responsibilities, $jobOpeningId]);
            
            echo json_encode([
                'success' => true,
                'message' => '✏️ Job opening updated successfully!'
            ]);
            
        } else {
            echo json_encode(['error' => 'Invalid action']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
