<?php
require_once 'dp.php';

try {
    // Sample trainers
    $trainers = [
        ['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john.smith@training.com', 'phone' => '123-456-7890', 'specialization' => 'Leadership'],
        ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane.doe@training.com', 'phone' => '123-456-7891', 'specialization' => 'Technical Skills'],
        ['first_name' => 'Bob', 'last_name' => 'Johnson', 'email' => 'bob.johnson@training.com', 'phone' => '123-456-7892', 'specialization' => 'Project Management']
    ];

    foreach ($trainers as $trainer) {
        $stmt = $conn->prepare("INSERT INTO trainers (first_name, last_name, email, phone, specialization) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE first_name=first_name");
        $stmt->execute([$trainer['first_name'], $trainer['last_name'], $trainer['email'], $trainer['phone'], $trainer['specialization']]);
    }

    // Sample training courses
    $courses = [
        ['course_name' => 'Leadership Development', 'category' => 'Management', 'description' => 'Develop leadership skills', 'duration' => 16, 'prerequisites' => 'None'],
        ['course_name' => 'Advanced PHP Programming', 'category' => 'Technical', 'description' => 'Master PHP development', 'duration' => 24, 'prerequisites' => 'Basic PHP'],
        ['course_name' => 'Project Management Fundamentals', 'category' => 'Management', 'description' => 'Learn project management basics', 'duration' => 12, 'prerequisites' => 'None'],
        ['course_name' => 'Data Analysis with Excel', 'category' => 'Technical', 'description' => 'Analyze data using Excel', 'duration' => 8, 'prerequisites' => 'Basic Excel']
    ];

    $courseIds = [];
    foreach ($courses as $course) {
        $stmt = $conn->prepare("INSERT INTO training_courses (course_name, category, description, duration, prerequisites) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE course_name=course_name");
        $stmt->execute([$course['course_name'], $course['category'], $course['description'], $course['duration'], $course['prerequisites']]);
        $courseIds[] = $conn->lastInsertId();
    }

    // Sample training sessions
    $sessions = [
        ['course_id' => $courseIds[0], 'session_name' => 'Leadership Workshop Q1', 'start_date' => '2024-01-15', 'end_date' => '2024-01-16', 'location' => 'Conference Room A', 'max_participants' => 20, 'trainer_id' => 1],
        ['course_id' => $courseIds[1], 'session_name' => 'PHP Advanced Training', 'start_date' => '2024-02-01', 'end_date' => '2024-02-03', 'location' => 'Training Room B', 'max_participants' => 15, 'trainer_id' => 2],
        ['course_id' => $courseIds[2], 'session_name' => 'PM Fundamentals', 'start_date' => '2024-03-10', 'end_date' => '2024-03-11', 'location' => 'Online', 'max_participants' => 25, 'trainer_id' => 3],
        ['course_id' => $courseIds[3], 'session_name' => 'Excel Data Analysis', 'start_date' => '2024-04-05', 'end_date' => '2024-04-05', 'location' => 'Computer Lab', 'max_participants' => 20, 'trainer_id' => 2]
    ];

    $sessionIds = [];
    foreach ($sessions as $session) {
        $stmt = $conn->prepare("INSERT INTO training_sessions (course_id, session_name, start_date, end_date, location, max_participants, trainer_id) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE session_name=session_name");
        $stmt->execute([$session['course_id'], $session['session_name'], $session['start_date'], $session['end_date'], $session['location'], $session['max_participants'], $session['trainer_id']]);
        $sessionIds[] = $conn->lastInsertId();
    }

    // Sample training enrollments for employee_id = 1 (assuming EMP001)
    $enrollments = [
        ['employee_id' => 1, 'session_id' => $sessionIds[0], 'enrollment_date' => '2024-01-10', 'status' => 'Completed', 'score' => 95],
        ['employee_id' => 1, 'session_id' => $sessionIds[1], 'enrollment_date' => '2024-01-25', 'status' => 'Completed', 'score' => 88],
        ['employee_id' => 1, 'session_id' => $sessionIds[2], 'enrollment_date' => '2024-03-01', 'status' => 'Enrolled', 'score' => null],
        ['employee_id' => 1, 'session_id' => $sessionIds[3], 'enrollment_date' => '2024-03-20', 'status' => 'Completed', 'score' => 92]
    ];

    foreach ($enrollments as $enrollment) {
        $stmt = $conn->prepare("INSERT INTO training_enrollments (employee_id, session_id, enrollment_date, status, score) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=status");
        $stmt->execute([$enrollment['employee_id'], $enrollment['session_id'], $enrollment['enrollment_date'], $enrollment['status'], $enrollment['score']]);
    }

    // Sample certifications for employee_id = 1
    $certifications = [
        ['employee_id' => 1, 'certification_name' => 'Certified ScrumMaster', 'issuing_organization' => 'Scrum Alliance', 'certification_number' => 'CSM-12345', 'category' => 'Project Management', 'proficiency_level' => 'Intermediate', 'assessment_score' => 85, 'issue_date' => '2023-06-15', 'expiry_date' => '2026-06-15', 'status' => 'Active', 'training_hours' => 16, 'description' => 'Scrum Master certification for agile project management'],
        ['employee_id' => 1, 'certification_name' => 'AWS Certified Solutions Architect', 'issuing_organization' => 'Amazon Web Services', 'certification_number' => 'AWS-67890', 'category' => 'Cloud Computing', 'proficiency_level' => 'Advanced', 'assessment_score' => 92, 'issue_date' => '2023-09-20', 'expiry_date' => '2026-09-20', 'status' => 'Active', 'training_hours' => 40, 'description' => 'Certification for designing distributed systems on AWS'],
        ['employee_id' => 1, 'certification_name' => 'PMP Certification', 'issuing_organization' => 'Project Management Institute', 'certification_number' => 'PMP-54321', 'category' => 'Project Management', 'proficiency_level' => 'Expert', 'assessment_score' => 88, 'issue_date' => '2022-11-10', 'expiry_date' => '2025-11-10', 'status' => 'Active', 'training_hours' => 35, 'description' => 'Project Management Professional certification'],
        ['employee_id' => 1, 'certification_name' => 'Microsoft Certified: Azure Fundamentals', 'issuing_organization' => 'Microsoft', 'certification_number' => 'AZ-900-11111', 'category' => 'Cloud Computing', 'proficiency_level' => 'Beginner', 'assessment_score' => 78, 'issue_date' => '2023-12-05', 'expiry_date' => '2026-12-05', 'status' => 'Active', 'training_hours' => 8, 'description' => 'Fundamental knowledge of cloud services and Azure platform']
    ];

    foreach ($certifications as $cert) {
        $stmt = $conn->prepare("INSERT INTO certifications (employee_id, certification_name, issuing_organization, certification_number, category, proficiency_level, assessment_score, issue_date, expiry_date, status, training_hours, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE certification_name=certification_name");
        $stmt->execute([$cert['employee_id'], $cert['certification_name'], $cert['issuing_organization'], $cert['certification_number'], $cert['category'], $cert['proficiency_level'], $cert['assessment_score'], $cert['issue_date'], $cert['expiry_date'], $cert['status'], $cert['training_hours'], $cert['description']]);
    }

    echo "Training sample data created successfully!\n";
    echo "Run the evaluation_training_report.php to see the populated data.\n";

} catch (PDOException $e) {
    echo "Error creating training sample data: " . $e->getMessage() . "\n";
}
?>
