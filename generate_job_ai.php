<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'config.php';
require_once 'ai_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobRoleId = $_POST['job_role_id'] ?? null;
    $departmentId = $_POST['department_id'] ?? null;
    $vacancyCount = $_POST['vacancy_count'] ?? 1;
    
    if (!$jobRoleId || !$departmentId) {
        echo json_encode(['error' => 'Job role and department are required']);
        exit;
    }
    
    // Validate vacancy count
    if ($vacancyCount < 1) {
        echo json_encode(['error' => 'Vacancy count must be at least 1']);
        exit;
    }
    
    try {
        // Get job role details
        $stmt = $conn->prepare("SELECT title, description FROM job_roles WHERE job_role_id = ?");
        $stmt->execute([$jobRoleId]);
        $jobRole = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$jobRole) {
            echo json_encode(['error' => 'Job role not found']);
            exit;
        }
        
        // Get department details
        $stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = ?");
        $stmt->execute([$departmentId]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$department) {
            echo json_encode(['error' => 'Department not found']);
            exit;
        }
        
        // Generate job description using AI (AI generates EVERYTHING including title)
        $aiResult = generateJobWithAI(
            $jobRole['title'],
            $jobRole['description'],
            $department['department_name'],
            'Full-time', // Default employment type
            null,
            null
        );
        
        if (isset($aiResult['error'])) {
            echo json_encode(['error' => $aiResult['error']]);
            exit;
        }
        
        $jobData = $aiResult['data'];
        
        // Insert job opening with AI-generated content
        $stmt = $conn->prepare("
            INSERT INTO job_openings (
                job_role_id, 
                department_id, 
                title, 
                description, 
                requirements, 
                responsibilities, 
                location, 
                employment_type, 
                experience_level,
                education_requirements,
                salary_range_min, 
                salary_range_max, 
                vacancy_count, 
                posting_date, 
                status,
                ai_generated,
                approval_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Draft', TRUE, 'Pending')
        ");
        
        $stmt->execute([
            $jobRoleId,
            $departmentId,
            $jobData['title'],
            $jobData['description'],
            $jobData['requirements'],
            $jobData['responsibilities'],
            'Municipal Office', // Default location
            'Full-time', // Default employment type
            $jobData['experience_level'] ?? null,
            $jobData['education_requirements'] ?? null,
            null, // salary_min
            null, // salary_max
            $vacancyCount // Use the vacancy count from form
        ]);
        
        $jobOpeningId = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => '🤖 Job opening generated successfully! Waiting for approval.',
            'job_opening_id' => $jobOpeningId,
            'data' => $jobData
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
