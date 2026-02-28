<?php
require_once 'dp.php';

try {
    // Insert sample trainers
    $trainers = [
        ['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john.smith@company.com', 'phone' => '091234567890', 'specialization' => 'Leadership & Management'],
        ['first_name' => 'Maria', 'last_name' => 'Garcia', 'email' => 'maria.garcia@company.com', 'phone' => '091234567891', 'specialization' => 'Customer Service'],
        ['first_name' => 'Robert', 'last_name' => 'Chen', 'email' => 'robert.chen@company.com', 'phone' => '091234567892', 'specialization' => 'Technical Training'],
        ['first_name' => 'Sarah', 'last_name' => 'Johnson', 'email' => 'sarah.johnson@company.com', 'phone' => '091234567893', 'specialization' => 'Compliance & Safety'],
        ['first_name' => 'Michael', 'last_name' => 'Brown', 'email' => 'michael.brown@external.com', 'phone' => '099999999999', 'specialization' => 'Project Management']
    ];

    foreach ($trainers as $trainer) {
        $stmt = $conn->prepare("INSERT IGNORE INTO trainers (first_name, last_name, email, phone, specialization, is_internal) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$trainer['first_name'], $trainer['last_name'], $trainer['email'], $trainer['phone'], $trainer['specialization'], $trainer['email'] == 'michael.brown@external.com' ? 0 : 1]);
    }
    echo "Sample trainers inserted\n";

    // Insert sample training courses
    $courses = [
        ['course_name' => 'Advanced Leadership Skills', 'description' => 'Develop advanced leadership and team management skills', 'category' => 'Management', 'duration' => 16, 'cost' => 2500.00, 'status' => 'Active'],
        ['course_name' => 'Customer Service Excellence', 'description' => 'Master customer interaction and satisfaction techniques', 'category' => 'Soft Skills', 'duration' => 12, 'cost' => 1800.00, 'status' => 'Active'],
        ['course_name' => 'Project Management Professional', 'description' => 'Comprehensive project management training', 'category' => 'Management', 'duration' => 40, 'cost' => 5000.00, 'status' => 'Active'],
        ['course_name' => 'Data Analysis with Excel', 'description' => 'Advanced Excel techniques for data analysis', 'category' => 'Technical', 'duration' => 8, 'cost' => 1200.00, 'status' => 'Active'],
        ['course_name' => 'Cybersecurity Awareness', 'description' => 'Essential cybersecurity practices and data protection', 'category' => 'IT', 'duration' => 6, 'cost' => 800.00, 'status' => 'Active'],
        ['course_name' => 'Communication Skills Workshop', 'description' => 'Effective workplace communication and presentation', 'category' => 'Soft Skills', 'duration' => 10, 'cost' => 1500.00, 'status' => 'Active'],
        ['course_name' => 'Time Management Mastery', 'description' => 'Strategies for effective time management and productivity', 'category' => 'Soft Skills', 'duration' => 8, 'cost' => 1000.00, 'status' => 'Active'],
        ['course_name' => 'Compliance and Ethics', 'description' => 'Understanding compliance requirements and ethical standards', 'category' => 'Compliance', 'duration' => 4, 'cost' => 600.00, 'status' => 'Active']
    ];

    $courseIds = [];
    foreach ($courses as $course) {
        $stmt = $conn->prepare("INSERT IGNORE INTO training_courses (course_name, description, category, duration, cost, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$course['course_name'], $course['description'], $course['category'], $course['duration'], $course['cost'], $course['status']]);
        $courseIds[] = $conn->lastInsertId();
    }
    echo "Sample training courses inserted\n";

    // Insert sample training sessions
    $sessions = [
        ['course_id' => $courseIds[0], 'trainer_id' => 1, 'session_name' => 'Leadership Development Q1 2024', 'start_date' => '2024-01-15', 'end_date' => '2024-01-17', 'location' => 'Conference Room A', 'capacity' => 20, 'status' => 'Completed'],
        ['course_id' => $courseIds[1], 'trainer_id' => 2, 'session_name' => 'Customer Service Excellence Batch 1', 'start_date' => '2024-02-05', 'end_date' => '2024-02-06', 'location' => 'Training Room B', 'capacity' => 25, 'status' => 'Completed'],
        ['course_id' => $courseIds[2], 'trainer_id' => 5, 'session_name' => 'PMP Certification Program', 'start_date' => '2024-03-01', 'end_date' => '2024-04-15', 'location' => 'Online', 'capacity' => 15, 'status' => 'Completed'],
        ['course_id' => $courseIds[3], 'trainer_id' => 3, 'session_name' => 'Excel Data Analysis Workshop', 'start_date' => '2024-04-10', 'end_date' => '2024-04-10', 'location' => 'Computer Lab', 'capacity' => 20, 'status' => 'In Progress'],
        ['course_id' => $courseIds[4], 'trainer_id' => 3, 'session_name' => 'Cybersecurity Awareness 2024', 'start_date' => '2024-05-20', 'end_date' => '2024-05-20', 'location' => 'IT Lab', 'capacity' => 30, 'status' => 'Scheduled'],
        ['course_id' => $courseIds[5], 'trainer_id' => 2, 'session_name' => 'Communication Skills Workshop', 'start_date' => '2024-06-15', 'end_date' => '2024-06-16', 'location' => 'Training Room A', 'capacity' => 25, 'status' => 'Scheduled'],
        ['course_id' => $courseIds[6], 'trainer_id' => 1, 'session_name' => 'Time Management Seminar', 'start_date' => '2024-07-01', 'end_date' => '2024-07-01', 'location' => 'Conference Room B', 'capacity' => 30, 'status' => 'Scheduled'],
        ['course_id' => $courseIds[7], 'trainer_id' => 4, 'session_name' => 'Compliance Training Q2', 'start_date' => '2024-04-15', 'end_date' => '2024-04-15', 'location' => 'Main Auditorium', 'capacity' => 50, 'status' => 'Completed']
    ];

    $sessionIds = [];
    foreach ($sessions as $session) {
        $stmt = $conn->prepare("INSERT IGNORE INTO training_sessions (course_id, trainer_id, session_name, start_date, end_date, location, capacity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$session['course_id'], $session['trainer_id'], $session['session_name'], $session['start_date'], $session['end_date'], $session['location'], $session['capacity'], $session['status']]);
        $sessionIds[] = $conn->lastInsertId();
    }
    echo "Sample training sessions inserted\n";

    // Get existing employee IDs
    $stmt = $conn->query("SELECT employee_id FROM employee_profiles LIMIT 5");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $employeeIds = array_column($employees, 'employee_id');

    if (empty($employeeIds)) {
        // If no employees, create some sample ones
        $sampleEmployees = [
            ['personal_info_id' => NULL, 'job_role_id' => NULL, 'employee_number' => 'EMP001', 'hire_date' => '2023-01-01', 'employment_status' => 'Full-time', 'current_salary' => 50000.00, 'work_email' => 'john.doe@municipality.gov.ph', 'work_phone' => NULL, 'location' => 'City Hall', 'remote_work' => 0],
            ['personal_info_id' => NULL, 'job_role_id' => NULL, 'employee_number' => 'EMP002', 'hire_date' => '2023-02-01', 'employment_status' => 'Full-time', 'current_salary' => 45000.00, 'work_email' => 'jane.smith@municipality.gov.ph', 'work_phone' => NULL, 'location' => 'City Hall', 'remote_work' => 0],
            ['personal_info_id' => NULL, 'job_role_id' => NULL, 'employee_number' => 'EMP003', 'hire_date' => '2023-03-01', 'employment_status' => 'Full-time', 'current_salary' => 55000.00, 'work_email' => 'bob.johnson@municipality.gov.ph', 'work_phone' => NULL, 'location' => 'City Hall', 'remote_work' => 0]
        ];

        foreach ($sampleEmployees as $emp) {
            $stmt = $conn->prepare("INSERT IGNORE INTO employee_profiles (personal_info_id, job_role_id, employee_number, hire_date, employment_status, current_salary, work_email, work_phone, location, remote_work) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array_values($emp));
        }

        $employeeIds = [1, 2, 3]; // Assuming auto-increment starts from 1
    }

    // Insert sample training enrollments
    $enrollments = [
        ['employee_id' => $employeeIds[0], 'session_id' => $sessionIds[0], 'enrollment_date' => '2024-01-10', 'status' => 'Completed', 'score' => 95, 'completion_date' => '2024-01-17'],
        ['employee_id' => $employeeIds[0], 'session_id' => $sessionIds[1], 'enrollment_date' => '2024-02-01', 'status' => 'Completed', 'score' => 88, 'completion_date' => '2024-02-06'],
        ['employee_id' => $employeeIds[0], 'session_id' => $sessionIds[2], 'enrollment_date' => '2024-03-01', 'status' => 'Completed', 'score' => 92, 'completion_date' => '2024-04-15'],
        ['employee_id' => $employeeIds[0], 'session_id' => $sessionIds[3], 'enrollment_date' => '2024-04-05', 'status' => 'In Progress', 'score' => NULL, 'completion_date' => NULL],
        ['employee_id' => $employeeIds[1], 'session_id' => $sessionIds[1], 'enrollment_date' => '2024-02-01', 'status' => 'Completed', 'score' => 90, 'completion_date' => '2024-02-06'],
        ['employee_id' => $employeeIds[1], 'session_id' => $sessionIds[4], 'enrollment_date' => '2024-05-15', 'status' => 'Enrolled', 'score' => NULL, 'completion_date' => NULL],
        ['employee_id' => $employeeIds[2], 'session_id' => $sessionIds[0], 'enrollment_date' => '2024-01-10', 'status' => 'Completed', 'score' => 87, 'completion_date' => '2024-01-17'],
        ['employee_id' => $employeeIds[2], 'session_id' => $sessionIds[5], 'enrollment_date' => '2024-06-10', 'status' => 'Enrolled', 'score' => NULL, 'completion_date' => NULL]
    ];

    foreach ($enrollments as $enrollment) {
        $stmt = $conn->prepare("INSERT IGNORE INTO training_enrollments (employee_id, session_id, enrollment_date, status, score, completion_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute($enrollment);
    }
    echo "Sample training enrollments inserted\n";

    // Insert sample certifications
    $certifications = [
        ['employee_id' => $employeeIds[0], 'certification_name' => 'Certified ScrumMaster', 'issuing_organization' => 'Scrum Alliance', 'certification_number' => 'CSM-12345', 'category' => 'Project Management', 'proficiency_level' => 'Intermediate', 'assessment_score' => 85, 'issue_date' => '2023-06-15', 'expiry_date' => '2026-06-15', 'status' => 'Active', 'training_hours' => 16, 'description' => 'Professional certification in Scrum Master practices'],
        ['employee_id' => $employeeIds[0], 'certification_name' => 'AWS Certified Solutions Architect', 'issuing_organization' => 'Amazon Web Services', 'certification_number' => 'AWS-67890', 'category' => 'Cloud Computing', 'proficiency_level' => 'Advanced', 'assessment_score' => 92, 'issue_date' => '2023-09-20', 'expiry_date' => '2026-09-20', 'status' => 'Active', 'training_hours' => 40, 'description' => 'Certification for designing distributed systems on AWS'],
        ['employee_id' => $employeeIds[0], 'certification_name' => 'Project Management Professional', 'issuing_organization' => 'Project Management Institute', 'certification_number' => 'PMP-54321', 'category' => 'Project Management', 'proficiency_level' => 'Expert', 'assessment_score' => 88, 'issue_date' => '2022-11-10', 'expiry_date' => '2025-11-10', 'status' => 'Active', 'training_hours' => 35, 'description' => 'Global standard for project management professionals'],
        ['employee_id' => $employeeIds[1], 'certification_name' => 'Microsoft Certified: Azure Fundamentals', 'issuing_organization' => 'Microsoft', 'certification_number' => 'AZ-900-11111', 'category' => 'Cloud Computing', 'proficiency_level' => 'Beginner', 'assessment_score' => 78, 'issue_date' => '2023-12-05', 'expiry_date' => '2026-12-05', 'status' => 'Active', 'training_hours' => 8, 'description' => 'Fundamental knowledge of cloud services and Azure platform'],
        ['employee_id' => $employeeIds[1], 'certification_name' => 'Certified Customer Service Professional', 'issuing_organization' => 'Customer Service Institute', 'certification_number' => 'CCSP-22222', 'category' => 'Customer Service', 'proficiency_level' => 'Intermediate', 'assessment_score' => 82, 'issue_date' => '2023-08-15', 'expiry_date' => '2026-08-15', 'status' => 'Active', 'training_hours' => 20, 'description' => 'Professional certification in customer service excellence'],
        ['employee_id' => $employeeIds[2], 'certification_name' => 'Certified Information Systems Security Professional', 'issuing_organization' => 'ISC²', 'certification_number' => 'CISSP-33333', 'category' => 'Information Security', 'proficiency_level' => 'Advanced', 'assessment_score' => 90, 'issue_date' => '2023-03-10', 'expiry_date' => '2026-03-10', 'status' => 'Active', 'training_hours' => 120, 'description' => 'Gold standard for information security professionals'],
        ['employee_id' => $employeeIds[2], 'certification_name' => 'Six Sigma Green Belt', 'issuing_organization' => 'American Society for Quality', 'certification_number' => 'SSGB-44444', 'category' => 'Quality Management', 'proficiency_level' => 'Intermediate', 'assessment_score' => 85, 'issue_date' => '2023-07-20', 'expiry_date' => '2026-07-20', 'status' => 'Active', 'training_hours' => 32, 'description' => 'Certification in Six Sigma quality management methodology']
    ];

    foreach ($certifications as $cert) {
        $stmt = $conn->prepare("INSERT IGNORE INTO certifications (employee_id, certification_name, issuing_organization, certification_number, category, proficiency_level, assessment_score, issue_date, expiry_date, status, training_hours, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($cert);
    }
    echo "Sample certifications inserted\n";

    echo "\n=== Realistic Training Data Created Successfully! ===\n";
    echo "You can now view populated training records in evaluation_training_report.php\n";
    echo "Expected results for an employee:\n";
    echo "- Total Trainings: 4\n";
    echo "- Completed: 3\n";
    echo "- In Progress: 1\n";
    echo "- Avg Score: ~92%\n";
    echo "- Certifications: 3-4\n";
    echo "- Active Certs: 3-4\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
