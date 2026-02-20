<?php
require_once 'dp.php';

try {
    $today = date('Y-m-d');
    echo "Today: $today\n";

    // Direct query for attendance records
    $stmt = $conn->prepare('SELECT * FROM attendance WHERE attendance_date = ?');
    $stmt->execute([$today]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Direct attendance records for today:\n";
    foreach($records as $record) {
        echo "ID: {$record['attendance_id']}, Employee: {$record['employee_id']}, Date: {$record['attendance_date']}, Clock In: '{$record['clock_in']}', Clock Out: '{$record['clock_out']}', Status: {$record['status']}\n";
    }

    if (empty($records)) {
        echo "No attendance records found for today.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
