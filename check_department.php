<?php
// check_department.php
// Script to check department structure and fix the issue

require_once 'config.php';

echo "Checking employee_profiles table structure...\n";

// Get table structure
$stmt = $conn->query('DESCRIBE employee_profiles');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ') - ' . ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
}

echo "\nChecking if departments table exists...\n";
try {
    $conn->query('SELECT 1 FROM departments LIMIT 1');
    echo "✓ Departments table exists\n";
    
    // Check departments table structure
    $stmt2 = $conn->query('DESCRIBE departments');
    $deptColumns = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Departments table columns:\n";
    foreach ($deptColumns as $col) {
        echo "  " . $col['Field'] . ' (' . $col['Type'] . ') - ' . ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
} catch (PDOException $e) {
    echo "✗ Departments table does not exist\n";
}

echo "\nChecking employee record for ID 2...\n";
$empStmt = $conn->prepare('SELECT * FROM employee_profiles WHERE employee_id = ?');
$empStmt->bindParam(1, 2, PDO::PARAM_INT);
$empStmt->execute();
$emp = $empStmt->fetch(PDO::FETCH_ASSOC);

if ($emp) {
    echo "Employee data:\n";
    foreach ($emp as $key => $value) {
        echo "  " . $key . ': ' . ($value ?? 'NULL') . "\n";
    }
} else {
    echo "Employee not found\n";
}

echo "\nChecking if employee_profiles has department_id column...\n";
$hasDeptId = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'department_id') {
        $hasDeptId = true;
        echo "✓ Found department_id column\n";
        break;
    }
}

if (!$hasDeptId) {
    echo "✗ No department_id column found in employee_profiles\n";
    echo "Need to check how department information is stored...\n";
    
    // Check if there's a separate employee_departments table
    try {
        $conn->query('SELECT 1 FROM employee_departments LIMIT 1');
        echo "✓ Found employee_departments table\n";
    } catch (PDOException $e) {
        echo "✗ No employee_departments table\n";
    }
}
?>