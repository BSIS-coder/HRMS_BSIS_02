<?php
require_once 'config.php';

try {
    // Insert a test record with '00:00:00' for clock_in
    $sql = "INSERT INTO attendance (employee_id, attendance_date, clock_in, clock_out, status) VALUES (2, CURDATE(), '00:00:00', '00:00:00', 'Present') ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in), clock_out = VALUES(clock_out)";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute();
    echo $result ? 'Test record with 00:00:00 inserted.' : 'Failed to insert test record.';

    // Fetch and display how it would be shown
    $fetchSql = "SELECT * FROM attendance WHERE employee_id = 2 AND attendance_date = CURDATE()";
    $fetchStmt = $conn->query($fetchSql);
    $record = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        echo "\n\nTesting display logic:\n";
        echo "Raw clock_in: " . $record['clock_in'] . "\n";
        echo "Raw clock_out: " . $record['clock_out'] . "\n";

        // Test employee_attendance.php display logic
        $clockInDisplay = ($record['clock_in'] && $record['clock_in'] !== '00:00:00' && $record['clock_in'] !== '0000-00-00 00:00:00') ? date('h:i A', strtotime($record['clock_in'])) : 'Not Recorded';
        $clockOutDisplay = ($record['clock_out'] && $record['clock_out'] !== '00:00:00' && $record['clock_out'] !== '0000-00-00 00:00:00') ? date('h:i A', strtotime($record['clock_out'])) : 'Not Recorded';

        echo "Clock In Display: " . $clockInDisplay . "\n";
        echo "Clock Out Display: " . $clockOutDisplay . "\n";

        // Test history table display logic
        $clockInHistory = ($record['clock_in'] && $record['clock_in'] !== '00:00:00' && $record['clock_in'] !== '0000-00-00 00:00:00') ? date('h:i A', strtotime($record['clock_in'])) : '-';
        $clockOutHistory = ($record['clock_out'] && $record['clock_out'] !== '00:00:00' && $record['clock_out'] !== '0000-00-00 00:00:00') ? date('h:i A', strtotime($record['clock_out'])) : '-';

        echo "History Clock In: " . $clockInHistory . "\n";
        echo "History Clock Out: " . $clockOutHistory . "\n";
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
