<?php
require_once 'dp.php';

try {
    // Get the test employee ID
    $stmt = $conn->prepare("SELECT employee_id FROM users WHERE username = ?");
    $stmt->execute(['test_employee']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo "Test employee not found. Please run load_sample_data.php first.\n";
        exit;
    }
    $employee_id = $user['employee_id'];

    // Simulate clock in
    $today = date('Y-m-d');
    $clockInTime = date('H:i:s'); // Current time
    $sql = "INSERT INTO attendance (employee_id, attendance_date, clock_in, status) VALUES (?, ?, ?, 'Present')
            ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in), status = 'Present'";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$employee_id, $today, $clockInTime]);

    echo "Clocked in at: $clockInTime\n";

    // Wait a moment
    sleep(2);

    // Simulate clock out
    $clockOutTime = date('H:i:s'); // Current time after wait
    $sql = "UPDATE attendance SET clock_out = ?, working_hours = TIMESTAMPDIFF(HOUR, clock_in, ?) WHERE employee_id = ? AND attendance_date = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$clockOutTime, $clockOutTime, $employee_id, $today]);

    echo "Clocked out at: $clockOutTime\n";

    // Now test the display logic
    $stmt = $conn->prepare("SELECT clock_in, clock_out FROM attendance WHERE employee_id = ? AND attendance_date = ?");
    $stmt->execute([$employee_id, $today]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        $clockInDisplay = ($record['clock_in'] && $record['clock_in'] !== '00:00:00' && $record['clock_in'] !== '0000-00-00 00:00:00') ? date('h:i A', strtotime($record['clock_in'])) : '-';
        $clockOutDisplay = ($record['clock_out'] && $record['clock_out'] !== '00:00:00' && $record['clock_out'] !== '0000-00-00 00:00:00') ? date('h:i A', strtotime($record['clock_out'])) : '-';

        echo "\nDisplay Test:\n";
        echo "Raw clock_in: " . $record['clock_in'] . "\n";
        echo "Raw clock_out: " . $record['clock_out'] . "\n";
        echo "Clock In Display: $clockInDisplay\n";
        echo "Clock Out Display: $clockOutDisplay\n";

        // Check if times are correct (not 12:00 AM)
        $clockInHour = date('H', strtotime($record['clock_in']));
        $clockOutHour = date('H', strtotime($record['clock_out']));

        if ($clockInHour != '00' && $clockOutHour != '00') {
            echo "\nSUCCESS: Times are displayed correctly (not 12:00 AM)\n";
        } else {
            echo "\nERROR: Times are showing as 12:00 AM\n";
        }
    } else {
        echo "No attendance record found.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
