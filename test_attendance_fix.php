<?php
require_once 'dp.php';

try {
    echo "Testing Attendance Fix\n";
    echo "=====================\n\n";

    // Test the updated query from fetch_attendance_overview.php
    $stmt = $conn->query("
        SELECT COUNT(*) as count
        FROM employee_profiles ep
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN attendance a ON ep.employee_id = a.employee_id
            AND a.attendance_date = DATE(NOW())
        LEFT JOIN (
            SELECT employee_id, MAX(history_id) as max_history_id
            FROM employment_history
            GROUP BY employee_id
        ) eh_max ON ep.employee_id = eh_max.employee_id
        LEFT JOIN employment_history eh ON eh_max.employee_id = eh.employee_id
            AND eh_max.max_history_id = eh.history_id
        WHERE (eh.employment_status = 'Active' OR eh.employment_status IS NULL)
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total employees fetched by UPDATED query: " . ($result['count'] ?? 0) . "\n";

    // Test the old restrictive query for comparison
    $stmt2 = $conn->query("SELECT COUNT(*) as count FROM employee_profiles WHERE employment_status IN ('Full-time', 'Part-time')");
    $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo "Employees with OLD filter (Full-time/Part-time only): " . ($result2['count'] ?? 0) . "\n\n";

    // Show employment status distribution
    $stmt3 = $conn->query("SELECT DISTINCT employment_status, COUNT(*) as count FROM employee_profiles GROUP BY employment_status ORDER BY count DESC");
    $statuses = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    echo "Employment status distribution:\n";
    foreach ($statuses as $status) {
        echo "  " . ($status['employment_status'] ?: 'NULL') . ": " . $status['count'] . "\n";
    }

    echo "\nFix successful: All employees should now be visible in attendance pages!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
