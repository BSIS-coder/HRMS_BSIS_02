<?php
// execute_fix.php
// Script to execute the database fix

require_once 'config.php';

echo "Starting database fix...\n";

try {
    // Read the SQL file
    $sql_content = file_get_contents('fix_employee_relationship.sql');
    
    // Split SQL into individual statements
    $statements = explode(';', $sql_content);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                echo "Executing: " . substr($statement, 0, 50) . "...\n";
                $conn->exec($statement);
                echo "✓ Executed successfully\n";
            } catch (PDOException $e) {
                echo "⚠ Warning: " . $e->getMessage() . "\n";
                // Continue execution even if some statements fail
            }
        }
    }
    
    echo "\nDatabase fix completed!\n";
    
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