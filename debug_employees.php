<?php
// Debug file to check employee data
header('Content-Type: application/json');
require_once 'config.php';

try {
    // Check total employees
    $total = $conn->query("SELECT COUNT(*) as total FROM employee_profiles")->fetch(PDO::FETCH_ASSOC);
    echo "Total employees: " . $total['total'] . "\n";
    
    // Check employment status distribution
    $statuses = $conn->query("SELECT employment_status, COUNT(*) as cnt FROM employee_profiles GROUP BY employment_status")->fetchAll(PDO::FETCH_ALL);
    echo "Employment statuses:\n";
    print_r($statuses);
    
    // Check personal_info_id values
    $personalInfo = $conn->query("SELECT employee_id, personal_info_id, employee_number, employment_status FROM employee_profiles LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nSample employee records:\n";
    print_r($personalInfo);
    
    // Check if personal_information table has data
    $piTotal = $conn->query("SELECT COUNT(*) as total FROM personal_information")->fetch(PDO::FETCH_ASSOC);
    echo "\nTotal personal_information records: " . $piTotal['total'] . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
