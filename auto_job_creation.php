<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if user has HR or Admin role
if (!in_array($_SESSION['role'], ['hr', 'admin'])) {
    echo json_encode(['error' => 'Only HR and Admin can manage auto job creation']);
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    
    try {
        if ($action === 'toggle_auto_creation') {
            // Toggle auto job creation setting
            $enabled = $_POST['enabled'] ?? 0;
            
            // Store in a settings table or config file
            // For now, we'll use a simple approach with a config file
            $settings = [
                'auto_job_creation_enabled' => (bool)$enabled,
                'updated_by' => $_SESSION['user_id'],
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            file_put_contents('auto_job_settings.json', json_encode($settings));
            
            echo json_encode([
                'success' => true,
                'message' => $enabled ? 'Auto job creation enabled' : 'Auto job creation disabled'
            ]);
            
        } elseif ($action === 'create_vacancy_job') {
            // Manually trigger job creation for a specific vacancy
            $employeeId = $_POST['employee_id'] ?? null;
            
            if (!$employeeId) {
                echo json_encode(['error' => 'Employee ID required']);
                exit;
            }
            
            // Get employee details
            $stmt = $conn->prepare("
                SELECT ep.*, jr.title, jr.department, jr.job_role_id, d.department_id, d.department_name
                FROM employee_profiles ep
                JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                JOIN departments d ON jr.department = d.department_name
                WHERE ep.employee_id = ?
            ");
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                echo json_encode(['error' => 'Employee not found']);
                exit;
            }
            
            // Check department vacancy limit
            $stmt = $conn->prepare("
                SELECT d.vacancy_limit,
                       COALESCE(SUM(CASE WHEN jo.status = 'Open' THEN jo.vacancy_count ELSE 0 END), 0) as current_vacancies
                FROM departments d
                LEFT JOIN job_openings jo ON d.department_id = jo.department_id
                WHERE d.department_id = ?
                GROUP BY d.department_id
            ");
            $stmt->execute([$employee['department_id']]);
            $deptInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($deptInfo && $deptInfo['vacancy_limit'] !== null) {
                if ($deptInfo['current_vacancies'] >= $deptInfo['vacancy_limit']) {
                    echo json_encode([
                        'error' => 'Cannot create job: Department has reached vacancy limit (' . $deptInfo['vacancy_limit'] . ')'
                    ]);
                    exit;
                }
            }
            
            // Generate job using AI
            require_once 'ai_config.php';
            require_once 'generate_job_ai.php';
            
            $jobData = generateJobWithAI($employee['department_name'], $employee['title']);
            
            if ($jobData['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Job opening created successfully and pending approval',
                    'job_id' => $jobData['job_id']
                ]);
            } else {
                echo json_encode(['error' => $jobData['error']]);
            }
            
        } elseif ($action === 'get_settings') {
            // Get current settings
            if (file_exists('auto_job_settings.json')) {
                $settings = json_decode(file_get_contents('auto_job_settings.json'), true);
                echo json_encode(['success' => true, 'settings' => $settings]);
            } else {
                echo json_encode([
                    'success' => true, 
                    'settings' => ['auto_job_creation_enabled' => false]
                ]);
            }
            
        } else {
            echo json_encode(['error' => 'Invalid action']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
