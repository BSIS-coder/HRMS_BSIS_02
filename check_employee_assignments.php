<?php
// check_employee_assignments.php
// Script to check employee assignments and get department info

require_once 'config.php';

echo "Checking employee_assignments table...\n";
try {
    $stmt = $conn->query('DESCRIBE employee_assignments');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "employee_assignments table columns:\n";
    foreach ($columns as $col) {
        echo "  " . $col['Field'] . ' (' . $col['Type'] . ') - ' . ($col['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    // Test getting department for employee 2
    echo "\nTesting employee_assignments for employee 2...\n";
    $assignStmt = $conn->prepare('SELECT * FROM employee_assignments WHERE employee_id = ? ORDER BY assignment_date DESC LIMIT 1');
    $assignStmt->bindParam(1, 2, PDO::PARAM_INT);
    $assignStmt->execute();
    $assignment = $assignStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assignment) {
        echo "✓ Found assignment:\n";
        echo "  Assignment ID: " . $assignment['assignment_id'] . "\n";
        echo "  Department ID: " . $assignment['department_id'] . "\n";
        echo "  Assignment Date: " . $assignment['assignment_date'] . "\n";
        
        // Get department name
        $deptStmt = $conn->prepare('SELECT department_name FROM departments WHERE department_id = ?');
        $deptStmt->bindParam(1, $assignment['department_id'], PDO::PARAM_INT);
        $deptStmt->execute();
        $dept = $deptStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dept) {
            echo "  Department Name: " . $dept['department_name'] . "\n";
        }
    } else {
        echo "✗ No assignment found for employee 2\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Also check the job_roles table department column
echo "\nChecking job_roles department column for employee 2...\n";
$roleStmt = $conn->prepare('SELECT jr.* FROM employee_profiles ep 
                          LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id 
                          WHERE ep.employee_id = ?');
$roleStmt->bindParam(1, 2, PDO::PARAM_INT);
$roleStmt->execute();
$role = $roleStmt->fetch(PDO::FETCH_ASSOC);

if ($role) {
    echo "✓ Found job role:\n";
    echo "  Job Role ID: " . $role['job_role_id'] . "\n";
    echo "  Title: " . $role['title'] . "\n";
    echo "  Department: " . $role['department'] . "\n";
} else {
    echo "✗ No job role found for employee 2\n";
}

// Test the complete JOIN with department information
echo "\nTesting complete JOIN with department information...\n";
$testSql = "SELECT ep.employee_id, ep.employee_number, ep.work_email,
                  pi.first_name, pi.last_name, pi.phone_number as phone,
                  jr.title as job_title, jr.department as job_department,
                  d.department_name as assigned_department
           FROM employee_profiles ep
           LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
           LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
           LEFT JOIN employee_assignments ea ON ep.employee_id = ea.employee_id
           LEFT JOIN departments d ON ea.department_id = d.department_id
           WHERE ep.employee_id = 2";

$stmt = $conn->prepare($testSql);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✓ Complete JOIN successful!\n";
    echo "  Employee: " . $result['first_name'] . " " . $result['last_name'] . "\n";
    echo "  Job Title: " . $result['job_title'] . "\n";
    echo "  Job Department: " . ($result['job_department'] ?? 'N/A') . "\n";
    echo "  Assigned Department: " . ($result['assigned_department'] ?? 'N/A') . "\n";
} else {
    echo "✗ Complete JOIN failed\n";
}
?>