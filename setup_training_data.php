<?php
require_once 'dp.php';

try {
    // Create training_courses table
    $conn->exec("CREATE TABLE IF NOT EXISTS training_courses (
        course_id INT AUTO_INCREMENT PRIMARY KEY,
        course_name VARCHAR(255) NOT NULL,
        description TEXT,
        category VARCHAR(100),
        duration INT COMMENT 'Duration in hours',
        cost DECIMAL(10,2) DEFAULT 0,
        status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table training_courses created\n";

    // Create trainers table
    $conn->exec("CREATE TABLE IF NOT EXISTS trainers (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table trainers created\n";

    // Create training_sessions table
    $conn->exec("CREATE TABLE IF NOT EXISTS training_sessions (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table training_sessions created\n";

    // Create training_enrollments table
    $conn->exec("CREATE TABLE IF NOT EXISTS training_enrollments (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table training_enrollments created\n";

    // Create certifications table
    $conn->exec("CREATE TABLE IF NOT EXISTS certifications (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table certifications created\n";

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

    $stmt = $conn->prepare("INSERT IGNORE INTO training_courses (course_id, course_name, description, category, duration, cost, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($courses as $course) {
        $stmt->execute($course);
    }
    echo "Sample training courses inserted\n";

    // Insert sample trainers
    $trainers = [
        [1, 'John', 'Smith', 'john.smith@company.com', '091234567890', 'Leadership & Management', 1],
        [2, 'Maria', 'Garcia', 'maria.garcia@company.com', '091234567891', 'Customer Service', 1],
        [3, 'Robert', 'Chen', 'robert.chen@company.com', '091234567892', 'Technical Training', 1],
        [4, 'Sarah', 'Johnson', 'sarah.johnson@company.com', '091234567893', 'Compliance & Safety', 1],
        [5, 'External', 'Trainer', 'trainer@external.com', '099999999999', 'Specialized Skills', 0]
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO trainers (trainer_id, first_name, last_name, email, phone, specialization, is_internal) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($trainers as $trainer) {
        $stmt->execute($trainer);
    }
    echo "Sample trainers inserted\n";

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

    $stmt = $conn->prepare("INSERT IGNORE INTO training_sessions (session_id, course_id, trainer_id, session_name, start_date, end_date, location, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($sessions as $session) {
        $stmt->execute($session);
    }
    echo "Sample training sessions inserted\n";

    // Check if employee with ID 1 exists
    $stmt = $conn->query("SELECT employee_id FROM employee_profiles LIMIT 1");
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    $employee_id = $employee ? $employee['employee_id'] : 1;

    // Insert sample training enrollments for employee
    $enrollments = [
        [$employee_id, 1, '2026-02-10', 'Completed', 95, '2026-02-16'],
        [$employee_id, 2, '2026-02-25', 'Completed', 88, '2026-03-02'],
        [$employee_id, 3, '2026-01-10', 'Completed', 92, '2026-02-28'],
        [$employee_id, 4, '2026-04-05', 'In Progress', NULL, NULL]
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO training_enrollments (employee_id, session_id, enrollment_date, status, score, completion_date) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($enrollments as $enrollment) {
        $stmt->execute($enrollment);
    }
    echo "Sample training enrollments inserted\n";

    // Insert sample certifications for employee
    $certifications = [
        [$employee_id, 'Certified ScrumMaster', 'Scrum Alliance', 'CSM-12345', 'Project Management', 'Intermediate', 85, '2023-06-15', '2026-06-15', 'Active', 16, 'Scrum Master certification for agile project management'],
        [$employee_id, 'AWS Certified Solutions Architect', 'Amazon Web Services', 'AWS-67890', 'Cloud Computing', 'Advanced', 92, '2023-09-20', '2026-09-20', 'Active', 40, 'Certification for designing distributed systems on AWS'],
        [$employee_id, 'PMP Certification', 'Project Management Institute', 'PMP-54321', 'Project Management', 'Expert', 88, '2022-11-10', '2025-11-10', 'Active', 35, 'Project Management Professional certification'],
        [$employee_id, 'Microsoft Certified: Azure Fundamentals', 'Microsoft', 'AZ-900-11111', 'Cloud Computing', 'Beginner', 78, '2023-12-05', '2026-12-05', 'Active', 8, 'Fundamental knowledge of cloud services and Azure platform']
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO certifications (employee_id, certification_name, issuing_organization, certification_number, category, proficiency_level, assessment_score, issue_date, expiry_date, status, training_hours, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($certifications as $cert) {
        $stmt->execute($cert);
    }
    echo "Sample certifications inserted\n";

    echo "\n=== Training Data Setup Complete! ===\n";
    echo "Now you can view the Training Records in evaluation_training_report.php\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
