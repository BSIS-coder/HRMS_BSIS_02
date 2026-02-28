<?php
// simple_fix.php
// Simple script to fix the database relationship

require_once 'config.php';

echo "Starting database fix...\n";

try {
    // Add foreign key constraint
    echo "Adding foreign key constraint...\n";
    $sql = "ALTER TABLE employee_profiles 
            ADD CONSTRAINT fk_employee_personal_info 
            FOREIGN KEY (personal_info_id) 
            REFERENCES personal_information(personal_info_id) 
            ON DELETE SET NULL";
    
    try {
        $conn->exec($sql);
        echo "✓ Foreign key constraint added successfully\n";
    } catch (PDOException $e) {
        echo "⚠ Warning: " . $e->getMessage() . "\n";
    }
    
    // Test the fix
    echo "\nTesting the fix...\n";
    
    $testSql = "SELECT ep.employee_id, ep.employee_number, ep.work_email, 
                       pi.first_name, pi.last_name, pi.email as personal_email,
                       jr.title as job_title
                FROM employee_profiles ep
                LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                WHERE ep.employee_id = 2";
    
    $stmt = $conn->prepare($testSql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✓ Test successful! Employee data retrieved:\n";
        echo "  Employee ID: " . $result['employee_id'] . "\n";
        echo "  Name: " . $result['first_name'] . " " . $result['last_name'] . "\n";
        echo "  Email: " . $result['work_email'] . "\n";
        echo "  Job Title: " . $result['job_title'] . "\n";
    } else {
        echo "✗ Test failed - no data retrieved\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>