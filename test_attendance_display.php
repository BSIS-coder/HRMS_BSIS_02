<?php
session_start();
require_once 'dp.php';

// Test script to check attendance data display
$today = date('Y-m-d');
echo "<h2>Testing Attendance Display for Date: {$today}</h2>";

// Check what's in the database
echo "<h3>1. Direct Database Query:</h3>";
$stmt = $conn->query("SELECT employee_id, attendance_date, clock_in, clock_out, status FROM attendance WHERE attendance_date = '{$today}'");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
echo "Found " . count($records) . " records for today:\n";
foreach ($records as $rec) {
    echo "Employee ID: {$rec['employee_id']}\n";
    echo "  Date: {$rec['attendance_date']}\n";
    echo "  Clock In: " . var_export($rec['clock_in'], true) . " (type: " . gettype($rec['clock_in']) . ")\n";
    echo "  Clock Out: " . var_export($rec['clock_out'], true) . " (type: " . gettype($rec['clock_out']) . ")\n";
    echo "  Status: {$rec['status']}\n";
    echo "\n";
}
echo "</pre>";

// Test the formatting logic
echo "<h3>2. Formatting Test:</h3>";
echo "<pre>";
foreach ($records as $rec) {
    $clockInRaw = $rec['clock_in'];
    $clockOutRaw = $rec['clock_out'];
    
    echo "Employee {$rec['employee_id']}:\n";
    
    // Test clock_in
    $clockIn = '-';
    if (isset($clockInRaw) && $clockInRaw !== null && $clockInRaw !== '') {
        $clockInTrimmed = trim($clockInRaw);
        if ($clockInTrimmed !== '00:00:00' && 
            $clockInTrimmed !== '0000-00-00 00:00:00' && 
            strpos($clockInTrimmed, '0000-00-00') === false &&
            strlen($clockInTrimmed) > 0) {
            try {
                $clockInDateTime = new DateTime($clockInTrimmed);
                $clockIn = $clockInDateTime->format('h:i A');
                echo "  Clock In: '{$clockInTrimmed}' -> '{$clockIn}' ✓\n";
            } catch (Exception $e) {
                $timestamp = @strtotime($clockInTrimmed);
                if ($timestamp !== false && $timestamp > 0) {
                    $clockIn = date('h:i A', $timestamp);
                    echo "  Clock In: '{$clockInTrimmed}' -> '{$clockIn}' (via strtotime) ✓\n";
                } else {
                    echo "  Clock In: FAILED - " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "  Clock In: Skipped (invalid date pattern)\n";
        }
    } else {
        echo "  Clock In: NULL/empty\n";
    }
    
    // Test clock_out
    $clockOut = '-';
    if (isset($clockOutRaw) && $clockOutRaw !== null && $clockOutRaw !== '') {
        $clockOutTrimmed = trim($clockOutRaw);
        if ($clockOutTrimmed !== '00:00:00' && 
            $clockOutTrimmed !== '0000-00-00 00:00:00' && 
            strpos($clockOutTrimmed, '0000-00-00') === false &&
            strlen($clockOutTrimmed) > 0) {
            try {
                $clockOutDateTime = new DateTime($clockOutTrimmed);
                $clockOut = $clockOutDateTime->format('h:i A');
                echo "  Clock Out: '{$clockOutTrimmed}' -> '{$clockOut}' ✓\n";
            } catch (Exception $e) {
                $timestamp = @strtotime($clockOutTrimmed);
                if ($timestamp !== false && $timestamp > 0) {
                    $clockOut = date('h:i A', $timestamp);
                    echo "  Clock Out: '{$clockOutTrimmed}' -> '{$clockOut}' (via strtotime) ✓\n";
                } else {
                    echo "  Clock Out: FAILED - " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "  Clock Out: Skipped (invalid date pattern)\n";
        }
    } else {
        echo "  Clock Out: NULL/empty\n";
    }
    echo "\n";
}
echo "</pre>";

// Test the full query
echo "<h3>3. Full Query Test (same as fetch_attendance_overview.php):</h3>";
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
        AND a.attendance_date = '{$today}'
    ORDER BY pi.first_name, pi.last_name
    LIMIT 10
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
echo "Found " . count($employees) . " employees:\n";
foreach ($employees as $emp) {
    echo "Employee {$emp['employee_id']}: {$emp['first_name']} {$emp['last_name']}\n";
    echo "  Clock In: " . var_export($emp['clock_in'], true) . "\n";
    echo "  Clock Out: " . var_export($emp['clock_out'], true) . "\n";
    echo "\n";
}
echo "</pre>";
?>
