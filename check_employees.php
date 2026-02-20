<?php
require_once 'dp.php';

try {
    $stmt = $conn->query('SELECT COUNT(*) as count FROM employee_profiles');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo 'Total employees: ' . $result['count'] . "\n";

    // Check if there are employees with personal info
    $stmt2 = $conn->query('SELECT ep.employee_id, pi.first_name, pi.last_name FROM employee_profiles ep LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id LIMIT 5');
    $employees = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample employees:\n";
    foreach($employees as $emp) {
        echo "ID: " . $emp['employee_id'] . ", Name: " . ($emp['first_name'] ?? 'Unknown') . " " . ($emp['last_name'] ?? 'Employee') . "\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
