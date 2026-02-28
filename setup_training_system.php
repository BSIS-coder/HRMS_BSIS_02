<?php
/**
 * Setup Training System Script
 * Run this file to create the training management tables and insert sample data
 */

session_start();

// Check if user is logged in as admin/HR
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'dp.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_training'])) {
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // 1. Create training_courses table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS training_courses (
                course_id INT AUTO_INCREMENT PRIMARY KEY,
                course_name VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(100),
                duration INT COMMENT 'Duration in hours',
                cost DECIMAL(10,2) DEFAULT 0,
                status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // 2. Create trainers table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS trainers (
                trainer_id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255),
                phone VARCHAR(50),
                specialization VARCHAR(255),
                bio TEXT,
                is_internal TINYINT(1) DEFAULT 1 COMMENT '1 = internal employee, 0 = external',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // 3. Create training_sessions table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS training_sessions (
                session_id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                trainer_id INT,
                session_name VARCHAR(255),
                start_date DATE NOT NULL,
                end_date DATE,
                location VARCHAR(255),
                capacity INT DEFAULT 0,
                cost_per_participant DECIMAL(10,2) DEFAULT 0,
                status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_course (course_id),
                INDEX idx_trainer (trainer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // 4. Create training_enrollments table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS training_enrollments (
                enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                employee_id INT NOT NULL,
                enrollment_date DATE DEFAULT (CURRENT_DATE),
                status ENUM('Enrolled', 'Completed', 'Cancelled', 'No Show') DEFAULT 'Enrolled',
                completion_date DATE,
                score DECIMAL(5,2) COMMENT 'Assessment score 0-100',
                feedback TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee (employee_id),
                INDEX idx_session (session_id),
                UNIQUE KEY unique_enrollment (session_id, employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // 5. Create certifications table
        $conn->exec("
            CREATE TABLE IF NOT EXISTS certifications (
                certification_id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                certification_name VARCHAR(255) NOT NULL,
                issuing_organization VARCHAR(255),
                certification_number VARCHAR(100),
                category VARCHAR(100),
                proficiency_level VARCHAR(50),
                assessment_score DECIMAL(5,2),
                issue_date DATE,
                expiry_date DATE,
                status ENUM('Active', 'Expired', 'Pending', 'Revoked') DEFAULT 'Active',
                training_hours INT DEFAULT 0,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee (employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // Insert sample training courses
        $courses = [
            [1, 'Workplace Safety Training', 'Comprehensive workplace safety and emergency procedures training', 'Safety', 8, 500, 'Active'],
            [2, 'Customer Service Excellence', 'Enhancing customer interaction and satisfaction skills', 'Soft Skills', 16, 750, 'Active'],
            [3, 'Leadership Development Program', 'Developing effective leadership and management skills', 'Leadership', 40, 2000, 'Active'],
            [4, 'Technical Skills Workshop', 'Technical competencies and job-specific skills training', 'Technical', 24, 1200, 'Active'],
            [5, 'Compliance and Ethics Training', 'Company policies, compliance, and ethical guidelines', 'Compliance', 4, 300, 'Active'],
            [6, 'Communication Skills', 'Effective workplace communication and presentation skills', 'Soft Skills', 12, 600, 'Active'],
            [7, 'Time Management', 'Managing time effectively and productivity improvement', 'Soft Skills', 8, 400, 'Active'],
            [8, 'IT Security Awareness', 'Cybersecurity best practices and data protection', 'IT', 6, 350, 'Active']
        ];
        
        $courseStmt = $conn->prepare("
            INSERT IGNORE INTO training_courses (course_id, course_name, description, category, duration, cost, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($courses as $course) {
            $courseStmt->execute($course);
        }
        
        // Insert sample trainers
        $trainers = [
            [1, 'John', 'Smith', 'john.smith@municipality.gov.ph', '091234567890', 'Leadership & Management', 1],
            [2, 'Maria', 'Garcia', 'maria.garcia@municipality.gov.ph', '091234567891', 'Customer Service', 1],
            [3, 'Robert', 'Chen', 'robert.chen@municipality.gov.ph', '091234567892', 'Technical Training', 1],
            [4, 'Sarah', 'Johnson', 'sarah.johnson@municipality.gov.ph', '091234567893', 'Compliance & Safety', 1],
            [5, 'External', 'Trainer', 'trainer@external.com', '099999999999', 'Specialized Skills', 0]
        ];
        
        $trainerStmt = $conn->prepare("
            INSERT IGNORE INTO trainers (trainer_id, first_name, last_name, email, phone, specialization, is_internal) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($trainers as $trainer) {
            $trainerStmt->execute($trainer);
        }
        
        // Insert sample training sessions
        $sessions = [
            [1, 1, 4, 'Safety Training Q1 2026', '2026-02-15', '2026-02-16', 'Conference Room A', 30, 'Completed'],
            [2, 2, 2, 'Customer Service Batch 1', '2026-03-01', '2026-03-02', 'Training Room B', 25, 'Completed'],
            [3, 3, 1, 'Leadership Program 2026', '2026-01-15', '2026-02-28', 'Executive Suite', 15, 'Completed'],
            [4, 4, 3, 'Technical Workshop - Excel', '2026-04-10', '2026-04-11', 'IT Lab', 20, 'In Progress'],
            [5, 5, 4, 'Compliance Training Q1', '2026-01-10', '2026-01-10', 'Main Auditorium', 50, 'Completed'],
            [6, 6, 2, 'Communication Skills Batch 1', '2026-05-15', '2026-05-16', 'Training Room A', 25, 'Scheduled'],
            [7, 7, 1, 'Time Management Seminar', '2026-06-01', '2026-06-01', 'Conference Room B', 30, 'Scheduled'],
            [8, 8, 3, 'IT Security Awareness', '2026-04-20', '2026-04-20', 'IT Lab', 25, 'Scheduled']
        ];
        
        $sessionStmt = $conn->prepare("
            INSERT IGNORE INTO training_sessions (session_id, course_id, trainer_id, session_name, start_date, end_date, location, capacity, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($sessions as $session) {
            $sessionStmt->execute($session);
        }
        
        $conn->commit();
        $success = true;
        $message = 'Training system setup completed successfully! Tables created and sample data inserted.';
        
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $message = 'Error: ' . $e->getMessage();
    }
}

// Check which tables already exist
$tablesStatus = [];
try {
    $tablesStatus['training_courses'] = $conn->query("SELECT 1 FROM training_courses LIMIT 1")->fetch() !== false;
} catch (PDOException $e) {
    $tablesStatus['training_courses'] = false;
}

try {
    $tablesStatus['trainers'] = $conn->query("SELECT 1 FROM trainers LIMIT 1")->fetch() !== false;
} catch (PDOException $e) {
    $tablesStatus['trainers'] = false;
}

try {
    $tablesStatus['training_sessions'] = $conn->query("SELECT 1 FROM training_sessions LIMIT 1")->fetch() !== false;
} catch (PDOException $e) {
    $tablesStatus['training_sessions'] = false;
}

try {
    $tablesStatus['training_enrollments'] = $conn->query("SELECT 1 FROM training_enrollments LIMIT 1")->fetch() !== false;
} catch (PDOException $e) {
    $tablesStatus['training_enrollments'] = false;
}

try {
    $tablesStatus['certifications'] = $conn->query("SELECT 1 FROM certifications LIMIT 1")->fetch() !== false;
} catch (PDOException $e) {
    $tablesStatus['certifications'] = false;
}

$allTablesExist = $tablesStatus['training_courses'] && $tablesStatus['trainers'] && 
                  $tablesStatus['training_sessions'] && $tablesStatus['training_enrollments'] && 
                  $tablesStatus['certifications'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Training System - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #E91E63;
            --primary-light: #F06292;
            --primary-dark: #C2185B;
            --primary-pale: #FCE4EC;
        }
        body { background: var(--primary-pale); }
        .setup-container {
            max-width: 700px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .setup-header h1 { margin: 0; font-size: 1.8rem; }
        .setup-body { padding: 30px; }
        .table-status {
            margin-bottom: 20px;
        }
        .table-status .table-name {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .status-exists { color: #28a745; }
        .status-missing { color: #dc3545; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-container">
            <div class="setup-header">
                <h1><i class="fas fa-cogs"></i> Training System Setup</h1>
                <p class="mb-0">Configure training management tables and sample data</p>
            </div>
            
            <div class="setup-body">
                <?php if ($message): ?>
                    <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?> mb-4">
                        <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <h5><i class="fas fa-database"></i> Table Status</h5>
                <div class="table-status">
                    <div class="table-name">
                        <span>training_courses</span>
                        <span class="<?php echo $tablesStatus['training_courses'] ? 'status-exists' : 'status-missing'; ?>">
                            <i class="fas fa-<?php echo $tablesStatus['training_courses'] ? 'check-circle' : 'times-circle'; ?>"></i>
                            <?php echo $tablesStatus['training_courses'] ? 'Exists' : 'Missing'; ?>
                        </span>
                    </div>
                    <div class="table-name">
                        <span>trainers</span>
                        <span class="<?php echo $tablesStatus['trainers'] ? 'status-exists' : 'status-missing'; ?>">
                            <i class="fas fa-<?php echo $tablesStatus['trainers'] ? 'check-circle' : 'times-circle'; ?>"></i>
                            <?php echo $tablesStatus['trainers'] ? 'Exists' : 'Missing'; ?>
                        </span>
                    </div>
                    <div class="table-name">
                        <span>training_sessions</span>
                        <span class="<?php echo $tablesStatus['training_sessions'] ? 'status-exists' : 'status-missing'; ?>">
                            <i class="fas fa-<?php echo $tablesStatus['training_sessions'] ? 'check-circle' : 'times-circle'; ?>"></i>
                            <?php echo $tablesStatus['training_sessions'] ? 'Exists' : 'Missing'; ?>
                        </span>
                    </div>
                    <div class="table-name">
                        <span>training_enrollments</span>
                        <span class="<?php echo $tablesStatus['training_enrollments'] ? 'status-exists' : 'status-missing'; ?>">
                            <i class="fas fa-<?php echo $tablesStatus['training_enrollments'] ? 'check-circle' : 'times-circle'; ?>"></i>
                            <?php echo $tablesStatus['training_enrollments'] ? 'Exists' : 'Missing'; ?>
                        </span>
                    </div>
                    <div class="table-name">
                        <span>certifications</span>
                        <span class="<?php echo $tablesStatus['certifications'] ? 'status-exists' : 'status-missing'; ?>">
                            <i class="fas fa-<?php echo $tablesStatus['certifications'] ? 'check-circle' : 'times-circle'; ?>"></i>
                            <?php echo $tablesStatus['certifications'] ? 'Exists' : 'Missing'; ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!$allTablesExist): ?>
                    <form method="POST">
                        <button type="submit" name="setup_training" class="btn btn-primary btn-lg btn-block">
                            <i class="fas fa-play"></i> Setup Training System
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Training system is already set up!
                    </div>
                    <a href="employee_evaluation_form.php" class="btn btn-primary btn-block">
                        <i class="fas fa-arrow-left"></i> Go to Employee Evaluation Form
                    </a>
                <?php endif; ?>
                
                <hr>
                
                <h5><i class="fas fa-info-circle"></i> Next Steps</h5>
                <ol>
                    <li>After setting up the training tables, go to <strong>Employee Competencies</strong> page</li>
                    <li>Select an employee and assign competencies for the review cycle</li>
                    <li>Once competencies are assigned, they will appear in the evaluation report</li>
                </ol>
                
                <a href="employee_competencies.php" class="btn btn-outline-secondary btn-block mt-3">
                    <i class="fas fa-tasks"></i> Go to Employee Competencies Page
                </a>
            </div>
        </div>
    </div>
</body>
</html>
