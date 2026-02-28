<?php
// test_fix.php
// Test script to verify the database fix

require_once 'config.php';

echo "Testing the database fix...\n\n";

$employee_id = 2;

// Check if employee exists
$checkEmpSql = "SELECT COUNT(*) as cnt FROM employee_profiles WHERE employee_id = ?";
$checkStmt = $conn->prepare($checkEmpSql);
$checkStmt->bindParam(1, $employee_id, PDO::PARAM_INT);
$checkStmt->execute();
$empExists = $checkStmt->fetch(PDO::FETCH_ASSOC);

echo "Employee exists: " . ($empExists['cnt'] > 0 ? 'Yes' : 'No') . " (count: " . $empExists['cnt'] . ")\n";

if ($empExists['cnt'] > 0) {
    // Get employee data with JOIN
    $empSql = "SELECT ep.*, pi.first_name, pi.last_name, pi.phone_number as phone, 
               jr.title as job_title
               FROM employee_profiles ep
               LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
               LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
               WHERE ep.employee_id = ?";
    
    $stmt = $conn->prepare($empSql);
    $stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
    $stmt->execute();
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        echo "✓ Employee data retrieved successfully!\n";
        echo "  Employee ID: " . $employee['employee_id'] . "\n";
        echo "  Name: " . ($employee['first_name'] ?? 'N/A') . " " . ($employee['last_name'] ?? 'N/A') . "\n";
        echo "  Work Email: " . ($employee['work_email'] ?? 'N/A') . "\n";
        echo "  Job Title: " . ($employee['job_title'] ?? 'N/A') . "\n";
        echo "  Personal Info ID: " . ($employee['personal_info_id'] ?? 'N/A') . "\n";
        echo "\n✓ Fix is working correctly!\n";
    } else {
        echo "✗ Employee data retrieval failed\n";
    }
} else {
    echo "✗ Employee not found\n";
}
?>