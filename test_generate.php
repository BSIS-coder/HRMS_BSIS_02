<?php
session_start();
header('Content-Type: application/json');

// Simulate logged in user for testing
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = 1;

require_once 'config.php';
require_once 'ai_config.php';

echo json_encode([
    'step' => 'Starting test',
    'session' => $_SESSION,
    'post' => $_POST
]);

// Test with hardcoded values
$jobRoleId = 1;
$departmentId = 1;
$vacancyCount = 1;

try {
    // Get job role details
    $stmt = $conn->prepare("SELECT title, description FROM job_roles WHERE job_role_id = ?");
    $stmt->execute([$jobRoleId]);
    $jobRole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'step' => 'Got job role',
        'jobRole' => $jobRole
    ]);
    
    if (!$jobRole) {
        echo json_encode(['error' => 'Job role not found']);
        exit;
    }
    
    // Get department details
    $stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = ?");
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'step' => 'Got department',
        'department' => $department
    ]);
    
    if (!$department) {
        echo json_encode(['error' => 'Department not found']);
        exit;
    }
    
    // Test AI function
    echo json_encode(['step' => 'Calling AI function']);
    
    $aiResult = generateJobWithAI(
        $jobRole['title'],
        $jobRole['description'],
        $department['department_name'],
        'Full-time',
        null,
        null
    );
    
    echo json_encode([
        'step' => 'AI function returned',
        'aiResult' => $aiResult
    ]);
    
    if (!isset($aiResult['success']) || !$aiResult['success']) {
        echo json_encode(['error' => 'AI generation failed', 'result' => $aiResult]);
        exit;
    }
    
    $jobData = $aiResult['data'];
    
    echo json_encode([
        'step' => 'About to insert',
        'jobData' => $jobData
    ]);
    
    // Try the INSERT
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
            created_by,
            screening_level,
            approval_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Draft', TRUE, ?, 'Moderate', 'Pending')
    ");
    
    $result = $stmt->execute([
        $jobRoleId,
        $departmentId,
        $jobData['title'],
        $jobData['description'],
        $jobData['requirements'],
        $jobData['responsibilities'],
        'Municipal Office',
        'Full-time',
        $jobData['experience_level'] ?? null,
        $jobData['education_requirements'] ?? null,
        null,
        null,
        $vacancyCount,
        $_SESSION['user_id']
    ]);
    
    $jobOpeningId = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Test successful!',
        'job_opening_id' => $jobOpeningId,
        'insert_result' => $result
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'General error',
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
