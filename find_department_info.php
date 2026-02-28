<?php
// find_department_info.php
// Script to find where department information is stored

require_once 'config.php';

echo "Checking all tables in the database...\n\n";

// Get all tables
$result = $conn->query('SHOW TABLES');
$tables = $result->fetchAll(PDO::FETCH_ASSOC);

$foundTables = [];

foreach ($tables as $table) {
    $tableName = reset($table);
    echo "Table: " . $tableName . "\n";
    
    // Check if this table has employee_id and department_id
    try {
        $stmt = $conn->query('DESCRIBE ' . $tableName);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasEmployeeId = false;
        $hasDepartmentId = false;
        $hasJobRoleId = false;
        
        foreach ($columns as $col) {
            if ($col['Field'] === 'employee_id') {
                $hasEmployeeId = true;
            }
            if ($col['Field'] === 'department_id') {
                $hasDepartmentId = true;
            }
            if ($col['Field'] === 'job_role_id') {
                $hasJobRoleId = true;
            }
        }
        
        if ($hasEmployeeId && $hasDepartmentId) {
            echo "  ✓ Has both employee_id and department_id\n";
            $foundTables[] = $tableName;
        } elseif ($hasEmployeeId && $hasJobRoleId) {
            echo "  ✓ Has employee_id and job_role_id (may link to department)\n";
            $foundTables[] = $tableName;
        } elseif ($hasEmployeeId) {
            echo "  - Has employee_id\n";
        } elseif ($hasDepartmentId) {
            echo "  - Has department_id\n";
        }
    } catch (PDOException $e) {
        echo "  - Error checking columns\n";
    }
    echo "\n";
}

echo "Tables that might contain department information:\n";
foreach ($foundTables as $table) {
    echo "  - " . $table . "\n";
}

// Now check the job_roles table since it might have department information
echo "\nChecking job_roles table structure...\n";
try {
    $stmt = $conn->query('DESCRIBE job_roles');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "job_roles table columns:\n";
    foreach ($columns as $col) {
        echo "  " . $col['Field'] . ' (' . $col['Type'] . ') - ' . ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    // Check if job_roles has department_id
    $hasDeptId = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'department_id') {
            $hasDeptId = true;
            break;
        }
    }
    
    if ($hasDeptId) {
        echo "\n✓ job_roles table has department_id column!\n";
        
        // Test the JOIN
        echo "\nTesting employee -> job_role -> department JOIN...\n";
        $testSql = "SELECT ep.employee_id, ep.employee_number, ep.work_email,
                          pi.first_name, pi.last_name, jr.title as job_title,
                          d.department_name
                   FROM employee_profiles ep
                   LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                   LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                   LEFT JOIN departments d ON jr.department_id = d.department_id
                   WHERE ep.employee_id = 2";
        
        $stmt = $conn->prepare($testSql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "✓ JOIN successful!\n";
            echo "  Employee: " . $result['first_name'] . " " . $result['last_name'] . "\n";
            echo "  Job Title: " . $result['job_title'] . "\n";
            echo "  Department: " . ($result['department_name'] ?? 'N/A') . "\n";
        } else {
            echo "✗ JOIN failed\n";
        }
    } else {
        echo "\n✗ job_roles table does not have department_id column\n";
    }
} catch (PDOException $e) {
    echo "Error checking job_roles table: " . $e->getMessage() . "\n";
}
?>