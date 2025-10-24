<?php
require_once 'dp.php';

try {
    // Insert employee profile first
    $stmt = $conn->prepare("INSERT INTO employee_profiles (personal_info_id, job_role_id, employee_number, hire_date, employment_status, current_salary, work_email, work_phone, location, remote_work) VALUES (NULL, NULL, ?, '2023-01-01', 'Full-time', 50000.00, ?, NULL, 'City Hall', 0) ON DUPLICATE KEY UPDATE employee_number=employee_number");
    $stmt->execute(['EMP001', 'john.doe@municipality.gov.ph']);

    // Get employee_id
    $employee_id = $conn->lastInsertId();
    if (!$employee_id) {
        $stmt = $conn->prepare("SELECT employee_id FROM employee_profiles WHERE employee_number = ?");
        $stmt->execute(['EMP001']);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        $employee_id = $emp['employee_id'];
    }

    // Insert user account
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, employee_id, is_active) VALUES (?, ?, ?, 'employee', ?, 1) ON DUPLICATE KEY UPDATE username=username");
    $stmt->execute(['test_employee', password_hash('password123', PASSWORD_DEFAULT), 'john.doe@municipality.gov.ph', $employee_id]);

    echo "Sample data loaded successfully. User: test_employee, Password: password123\n";
} catch (PDOException $e) {
    echo "Error loading sample data: " . $e->getMessage() . "\n";
}
?>
