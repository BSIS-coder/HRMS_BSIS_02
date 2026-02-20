<?php
require_once 'dp.php';

try {
    $today = date('Y-m-d');
    echo "Today: $today\n";

    // Test the exact query from fetch_attendance_overview.php
    $stmt = $conn->query("
        SELECT
            ep.employee_id,
            COALESCE(pi.first_name, 'Unknown') as first_name,
            COALESCE(pi.last_name, 'Employee') as last_name,
            a.attendance_date,
            a.clock_in,
            a.clock_out,
            a.status
        FROM employee_profiles ep
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN attendance a ON ep.employee_id = a.employee_id
        WHERE ep.employee_id IN (6, 15)
        ORDER BY pi.first_name, pi.last_name
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Query results for employees 6 and 15:\n";
    foreach($results as $row) {
        echo "Employee: {$row['employee_id']}, Name: {$row['first_name']} {$row['last_name']}, Date: {$row['attendance_date']}, Clock In: '{$row['clock_in']}', Clock Out: '{$row['clock_out']}', Status: {$row['status']}\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
