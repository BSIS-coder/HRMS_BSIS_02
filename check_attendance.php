<?php
require_once 'dp.php';

try {
    $stmt = $conn->query('SELECT * FROM attendance ORDER BY attendance_date DESC LIMIT 10');
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Recent attendance records:\n";
    foreach($records as $record) {
        echo "ID: " . $record['attendance_id'] . ", Employee: " . $record['employee_id'] . ", Date: " . $record['attendance_date'] . ", Clock In: " . $record['clock_in'] . ", Clock Out: " . $record['clock_out'] . ", Status: " . $record['status'] . "\n";
    }

    // Check today's records specifically
    $today = date('Y-m-d');
    echo "\nToday's attendance records for date: $today\n";
    $stmt2 = $conn->prepare('SELECT * FROM attendance WHERE attendance_date = ?');
    $stmt2->execute([$today]);
    $todayRecords = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    foreach($todayRecords as $record) {
        echo "ID: " . $record['attendance_id'] . ", Employee: " . $record['employee_id'] . ", Date: " . $record['attendance_date'] . ", Clock In: " . $record['clock_in'] . ", Clock Out: " . $record['clock_out'] . ", Status: " . $record['status'] . "\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
