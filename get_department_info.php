<?php
// get_department_info.php
// Script to get department information from both sources

require_once 'config.php';

$employee_id = 2;

echo "Getting department information for employee $employee_id...\n\n";

// Method 1: From employee_assignments table
echo "Method 1: Checking employee_assignments table...\n";
try {
    $assignStmt = $conn->prepare('SELECT * FROM employee_assignments WHERE employee_id = ? ORDER BY assigned_date DESC LIMIT 1');
    $assignStmt->bindParam(1, $employee_id, PDO::PARAM_INT);
    $assignStmt->execute();
    $assignment = $assignStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assignment && $assignment['department_id']) {
        $deptStmt = $conn->prepare('SELECT department_name FROM departments WHERE department_id = ?');
        $deptStmt->bindParam(1, $assignment['department_id'], PDO::PARAM_INT);
        $deptStmt->execute();
        $dept = $deptStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dept) {
            echo "✓ Found department from assignments: " . $dept['department_name'] . "\n";
            $assignedDepartment = $dept['department_name'];
        } else {
            echo "✗ Department not found in departments table\n";
            $assignedDepartment = null;
        }
    } else {
        echo "✗ No department found in assignments\n";
        $assignedDepartment = null;
    }
} catch (PDOException $e) {
    echo "Error checking assignments: " . $e->getMessage() . "\n";
    $assignedDepartment = null;
}

// Method 2: From job_roles table
echo "\nMethod 2: Checking job_roles table...\n";
try {
    $roleStmt = $conn->prepare('SELECT jr.* FROM employee_profiles ep 
                              LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id 
                              WHERE ep.employee_id = ?');
    $roleStmt->bindParam(1, $employee_id, PDO::PARAM_INT);
    $roleStmt->execute();
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($role && $role['department']) {
        echo "✓ Found department from job role: " . $role['department'] . "\n";
        $jobDepartment = $role['department'];
    } else {
        echo "✗ No department found in job role\n";
        $jobDepartment = null;
    }
} catch (PDOException $e) {
    echo "Error checking job roles: " . $e->getMessage() . "\n";
    $jobDepartment = null;
}

// Determine final department
$finalDepartment = null;
if ($assignedDepartment) {
    $finalDepartment = $assignedDepartment;
    echo "\n✓ Using assigned department: $finalDepartment\n";
} elseif ($jobDepartment) {
    $finalDepartment = $jobDepartment;
    echo "\n✓ Using job role department: $finalDepartment\n";
} else {
    echo "\n✗ No department information found\n";
}

// Test the complete query with department
echo "\nTesting complete query with department information...\n";
$testSql = "SELECT ep.employee_id, ep.employee_number, ep.work_email,
                  pi.first_name, pi.last_name, pi.phone_number as phone,
                  jr.title as job_title, jr.department as job_department,
                  d.department_name as assigned_department
           FROM employee_profiles ep
           LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
           LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
           LEFT JOIN employee_assignments ea ON ep.employee_id = ea.employee_id
           LEFT JOIN departments d ON ea.department_id = d.department_id
           WHERE ep.employee_id = ?";

$stmt = $conn->prepare($testSql);
$stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "✓ Complete query successful!\n";
    echo "  Employee: " . $result['first_name'] . " " . $result['last_name'] . "\n";
    echo "  Job Title: " . $result['job_title'] . "\n";
    echo "  Job Department: " . ($result['job_department'] ?? 'N/A') . "\n";
    echo "  Assigned Department: " . ($result['assigned_department'] ?? 'N/A') . "\n";
    
    // Determine which department to use
    $displayDepartment = $result['assigned_department'] ?? $result['job_department'] ?? 'N/A';
    echo "  Final Department: " . $displayDepartment . "\n";
} else {
    echo "✗ Complete query failed\n";
}
?>