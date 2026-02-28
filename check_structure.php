<?php
// check_structure.php
// Script to check table structure

require_once 'config.php';

echo "Checking personal_information table structure...\n";

try {
    // Get table structure
    $stmt = $conn->query('DESCRIBE personal_information');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in personal_information table:\n";
    foreach ($columns as $col) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ") - " . ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    echo "\nChecking employee_profiles table structure...\n";
    
    $stmt2 = $conn->query('DESCRIBE employee_profiles');
    $columns2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in employee_profiles table:\n";
    foreach ($columns2 as $col) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ") - " . ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    echo "\nTesting JOIN with correct column names...\n";
    
    // Test with correct column names
    $testSql = "SELECT ep.employee_id, ep.employee_number, ep.work_email, 
                       pi.first_name, pi.last_name, pi.phone_number as personal_phone,
                       jr.title as job_title
                FROM employee_profiles ep
                LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                WHERE ep.employee_id = 2";
    
    $stmt3 = $conn->prepare($testSql);
    $stmt3->execute();
    $result = $stmt3->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✓ Test successful! Employee data retrieved:\n";
        echo "  Employee ID: " . $result['employee_id'] . "\n";
        echo "  Name: " . $result['first_name'] . " " . $result['last_name'] . "\n";
        echo "  Work Email: " . $result['work_email'] . "\n";
        echo "  Personal Phone: " . $result['personal_phone'] . "\n";
        echo "  Job Title: " . $result['job_title'] . "\n";
    } else {
        echo "✗ Test failed - no data retrieved\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>