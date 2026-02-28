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

    // Insert sample training courses - using correct columns from hr_system.sql
    $courses = [
        ['course_name' => 'Advanced Leadership Skills', 'description' => 'Develop advanced leadership and team management skills', 'category' => 'Management', 'delivery_method' => 'Workshop', 'duration' => 16, 'max_participants' => 20, 'prerequisites' => 'None', 'status' => 'Active'],
        ['course_name' => 'Customer Service Excellence', 'description' => 'Master customer interaction and satisfaction techniques', 'category' => 'Soft Skills', 'delivery_method' => 'Classroom Training', 'duration' => 12, 'max_participants' => 25, 'prerequisites' => 'None', 'status' => 'Active'],
        ['course_name' => 'Project Management Professional', 'description' => 'Comprehensive project management training', 'category' => 'Management', 'delivery_method' => 'Blended Learning', 'duration' => 40, 'max_participants' => 15, 'prerequisites' => 'Basic PM knowledge', 'status' => 'Active'],
        ['course_name' => 'Data Analysis with Excel', 'description' => 'Advanced Excel techniques for data analysis', 'category' => 'Technical', 'delivery_method' => 'Workshop', 'duration' => 8, 'max_participants' => 20, 'prerequisites' => 'Basic Excel', 'status' => 'Active'],
        ['course_name' => 'Cybersecurity Awareness', 'description' => 'Essential cybersecurity practices and data protection', 'category' => 'IT', 'delivery_method' => 'Online Learning', 'duration' => 6, 'max_participants' => 30, 'prerequisites' => 'None', 'status' => 'Active'],
        ['course_name' => 'Communication Skills Workshop', 'description' => 'Effective workplace communication and presentation', 'category' => 'Soft Skills', 'delivery_method' => 'Seminar', 'duration' => 10, 'max_participants' => 25, 'prerequisites' => 'None', 'status' => 'Active'],
        ['course_name' => 'Time Management Mastery', 'description' => 'Strategies for effective time management and productivity', 'category' => 'Soft Skills', 'delivery_method' => 'Workshop', 'duration' => 8, 'max_participants' => 30, 'prerequisites' => 'None', 'status' => 'Active'],
        ['course_name' => 'Compliance and Ethics', 'description' => 'Understanding compliance requirements and ethical standards', 'category' => 'Compliance', 'delivery_method' => 'Classroom Training', 'duration' => 4, 'max_participants' => 50, 'prerequisites' => 'None', 'status' => 'Active']
    ];

    $courseIds = [];
    foreach ($courses as $course) {
        $stmt = $conn->prepare("INSERT IGNORE INTO training_courses (course_name, description, category, delivery_method, duration, max_participants, prerequisites, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$course['course_name'], $course['description'], $course['category'], $course['delivery_method'], $course['duration'], $course['max_participants'], $course['prerequisites'], $course['status']]);
        $courseIds[] = $conn->lastInsertId();
    }
    echo "Sample training courses inserted\n";

    // Insert sample training sessions
    $sessions = [
        ['course_id' => $courseIds[0], 'trainer_id' => 1, 'session_name' => 'Leadership Development Q1 2024', 'start_date' => '2024-01-15 09:00:00', 'end_date' => '2024-01-17 17:00:00', 'location' => 'Conference Room A', 'capacity' => 20, 'cost_per_participant' => 2500.00, 'status' => 'Completed'],
        ['course_id' => $courseIds[1], 'trainer_id' => 2, 'session_name' => 'Customer Service Excellence Batch 1', 'start_date' => '2024-02-05 09:00:00', 'end_date' => '2024-02-06 17:00:00', 'location' => 'Training Room B', 'capacity' => 25, 'cost_per_participant' => 1800.00, 'status' => 'Completed'],
        ['course_id' => $courseIds[2], 'trainer_id' => 5, 'session_name' => 'PMP Certification Program', 'start_date' => '2024-03-01 09:00:00', 'end_date' => '2024-04-15 17:00:00', 'location' => 'Online', 'capacity' => 15, 'cost_per_participant' => 5000.00, 'status' => 'Completed'],
        ['course_id' => $courseIds[3], 'trainer_id' => 3, 'session_name' => 'Excel Data Analysis Workshop', 'start_date' => '2024-04-10 09:00:00', 'end_date' => '2024-04-10 17:00:00', 'location' => 'Computer Lab', 'capacity' => 20, 'cost_per_participant' => 1200.00, 'status' => 'In Progress'],
        ['course_id' => $courseIds[4], 'trainer_id' => 3, 'session_name' => 'Cybersecurity Awareness 2024', 'start_date' => '2024-05-20 09:00:00', 'end_date' => '2024-05-20 17:00:00', 'location' => 'IT Lab', 'capacity' => 30, 'cost_per_participant' => 800.00, 'status' => 'Scheduled'],
        ['course_id' => $courseIds[5], 'trainer_id' => 2, 'session_name' => 'Communication Skills Workshop', 'start_date' => '2024-06-15 09:00:00', 'end_date' => '2024-06-16 17:00:00', 'location' => 'Training Room A', 'capacity' => 25, 'cost_per_participant' => 1500.00, 'status' => 'Scheduled'],
        ['course_id' => $courseIds[6], 'trainer_id' => 1, 'session_name' => 'Time Management Seminar', 'start_date' => '2024-07-01 09:00:00', 'end_date' => '2024-07-01 17:00:00', 'location' => 'Conference Room B', 'capacity' => 30, 'cost_per_participant' => 1000.00, 'status' => 'Scheduled'],
        ['course_id' => $courseIds[7], 'trainer_id' => 4, 'session_name' => 'Compliance Training Q2', 'start_date' => '2024-04-15 09:00:00', 'end_date' => '2024-04-15 13:00:00', 'location' => 'Main Auditorium', 'capacity' => 50, 'cost_per_participant' => 600.00, 'status' => 'Completed']
    ];

    $sessionIds = [];
    foreach ($sessions as $session) {
        $stmt = $conn->prepare("INSERT IGNORE INTO training_sessions (course_id, trainer_id, session_name, start_date, end_date, location, capacity, cost_per_participant, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$session['course_id'], $session['trainer_id'], $session['session_name'], $session['start_date'], $session['end_date'], $session['location'], $session['capacity'], $session['cost_per_participant'], $session['status']]);
        $sessionIds[] = $conn->lastInsertId();
    }
    echo "Sample training sessions inserted\n";

    // Get existing employee IDs
    $stmt = $conn->query("SELECT employee_id FROM employee_profiles LIMIT 5");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $employeeIds = array_column($employees, 'employee_id');

    if (empty($employeeIds)) {
        $employeeIds = [1, 2, 3]; // Fallback
    }

    // Insert sample training enrollments - using correct columns (no score column in some cases)
    $enrollments = [
        [$employeeIds[0], $sessionIds[0], '2024-01-10 10:00:00', 'Completed', 95, '2024-01-17'],
        [$employeeIds[0], $sessionIds[1], '2024-02-01 10:00:00', 'Completed', 88, '2024-02-06'],
        [$employeeIds[0], $sessionIds[2], '2024-03-01 10:00:00', 'Completed', 92, '2024-04-15'],
        [$employeeIds[0], $sessionIds[3], '2024-04-05 10:00:00', 'Enrolled', NULL, NULL],
        [$employeeIds[1], $sessionIds[1], '2024-02-01 10:00:00', 'Completed', 90, '2024-02-06'],
        [$employeeIds[1], $sessionIds[4], '2024-05-15 10:00:00', 'Enrolled', NULL, NULL],
        [$employeeIds[2], $sessionIds[0], '2024-01-10 10:00:00', 'Completed', 87, '2024-01-17'],
        [$employeeIds[2], $sessionIds[5], '2024-06-10 10:00:00', 'Enrolled', NULL, NULL]
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO training_enrollments (employee_id, session_id, enrollment_date, status, score, completion_date) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($enrollments as $enrollment) {
        $stmt->execute($enrollment);
    }
    echo "Sample training enrollments inserted\n";

    // Insert sample certifications - using correct columns from hr_system.sql
    $certifications = [
        ['employee_id' => $employeeIds[0], 'certification_name' => 'Certified ScrumMaster', 'issuing_organization' => 'Scrum Alliance', 'certification_number' => 'CSM-12345', 'category' => 'Project Management', 'proficiency_level' => 'Intermediate', 'assessment_score' => 85, 'issue_date' => '2023-06-15', 'expiry_date' => '2026-06-15', 'assessed_date' => '2023-06-15', 'status' => 'Active', 'verification_status' => 'Verified', 'cost' => 1500.00, 'training_hours' => 16],
        ['employee_id' => $employeeIds[0], 'certification_name' => 'AWS Certified Solutions Architect', 'issuing_organization' => 'Amazon Web Services', 'certification_number' => 'AWS-67890', 'category' => 'Cloud Computing', 'proficiency_level' => 'Advanced', 'assessment_score' => 92, 'issue_date' => '2023-09-20', 'expiry_date' => '2026-09-20', 'assessed_date' => '2023-09-20', 'status' => 'Active', 'verification_status' => 'Verified', 'cost' => 3000.00, 'training_hours' => 40],
        ['employee_id' => $employeeIds[0], 'certification_name' => 'Project Management Professional', 'issuing_organization' => 'Project Management Institute', 'certification_number' => 'PMP-54321', 'category' => 'Project Management', 'proficiency_level' => 'Expert', 'assessment_score' => 88, 'issue_date' => '2022-11-10', 'expiry_date' => '2025-11-10', 'assessed_date' => '2022-11-10', 'status' => 'Active', 'verification_status' => 'Verified', 'cost' => 5000.00, 'training_hours' => 35],
        ['employee_id' => $employeeIds[1], 'certification_name' => 'Microsoft Certified: Azure Fundamentals', 'issuing_organization' => 'Microsoft', 'certification_number' => 'AZ-900-11111', 'category' => 'Cloud Computing', 'proficiency_level' => 'Beginner', 'assessment_score' => 78, 'issue_date' => '2023-12-05', 'expiry_date' => '2026-12-05', 'assessed_date' => '2023-12-05', 'status' => 'Active', 'verification_status' => 'Verified', 'cost' => 990.00, 'training_hours' => 8],
        ['employee_id' => $employeeIds[1], 'certification_name' => 'Certified Customer Service Professional', 'issuing_organization' => 'Customer Service Institute', 'certification_number' => 'CCSP-22222', 'category' => 'Customer Service', 'proficiency_level' => 'Intermediate', 'assessment_score' => 82, 'issue_date' => '2023-08-15', 'expiry_date' => '2026-08-15', 'assessed_date' => '2023-08-15', 'status' => 'Active', 'verification_status' => 'Verified', 'cost' => 1200.00, 'training_hours' => 20],
        ['employee_id' => $employeeIds[2], 'certification_name' => 'Certified Information Systems Security Professional', 'issuing_organization' => 'ISC²', 'certification_number' => 'CISSP-33333', 'category' => 'Information Security', 'proficiency_level' => 'Advanced', 'assessment_score' => 90, 'issue_date' => '2023-03-10', 'expiry_date' => '2026-03-10', 'assessed_date' => '2023-03-10', 'status' => 'Active', 'verification_status' => 'Verified', 'cost' => 7000.00, 'training_hours' => 120],
        ['employee_id' => $employeeIds[2], 'certification_name' => 'Six Sigma Green Belt', 'issuing_organization' => 'American Society for Quality', 'certification_number' => 'SSGB-44444', 'category' => 'Quality Management', 'proficiency_level' => 'Intermediate', 'assessment_score' => 85, 'issue_date' => '2023-07-20', 'expiry_date' => '2026-07-20', 'assessed_date' => '2023-07-20', 'status' => 'Active', 'verification_status' => 'Verified', 'cost' => 2500.00, 'training_hours' => 32]
    ];

    foreach ($certifications as $cert) {
        $stmt = $conn->prepare("INSERT IGNORE INTO certifications (employee_id, certification_name, issuing_organization, certification_number, category, proficiency_level, assessment_score, issue_date, expiry_date, assessed_date, status, verification_status, cost, training_hours) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($cert);
    }
    echo "Sample certifications inserted\n";

    echo "\n=== Training Data Created Successfully! ===\n";
    echo "Now you can view populated training records in evaluation_training_report.php\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
