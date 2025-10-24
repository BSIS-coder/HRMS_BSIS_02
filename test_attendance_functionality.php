<?php
require_once 'config.php';

try {
    // Test inserting attendance with overtime and late minutes
    $sql = "INSERT INTO attendance (employee_id, attendance_date, clock_in, clock_out, status, working_hours, overtime_hours, late_minutes, notes)
            VALUES (1, CURDATE(), '08:00:00', '17:30:00', 'Present', 8.50, 1.50, 15.00, 'Test attendance with overtime and late minutes')
            ON DUPLICATE KEY UPDATE
                clock_in = VALUES(clock_in),
                clock_out = VALUES(clock_out),
                status = VALUES(status),
                working_hours = VALUES(working_hours),
                overtime_hours = VALUES(overtime_hours),
                late_minutes = VALUES(late_minutes),
                notes = VALUES(notes)";

    $stmt = $conn->prepare($sql);
    $result = $stmt->execute();

    if ($result) {
        echo "✓ Test attendance record inserted/updated successfully.\n";

        // Test fetching the record
        $fetchSql = "SELECT * FROM attendance WHERE employee_id = 1 AND attendance_date = CURDATE()";
        $fetchStmt = $conn->query($fetchSql);
        $record = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            echo "✓ Attendance record fetched successfully:\n";
            echo "  - Employee ID: " . $record['employee_id'] . "\n";
            echo "  - Date: " . $record['attendance_date'] . "\n";
            echo "  - Overtime Hours: " . $record['overtime_hours'] . "\n";
            echo "  - Late Minutes: " . $record['late_minutes'] . "\n";
            echo "  - Notes: " . $record['notes'] . "\n";
        } else {
            echo "✗ Failed to fetch the inserted record.\n";
        }
    } else {
        echo "✗ Failed to insert/update attendance record.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
