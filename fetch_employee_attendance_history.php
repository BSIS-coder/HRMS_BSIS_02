<?php
session_start();

// Check if the user is logged in and is an employee
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'employee') {
    http_response_code(401);
    exit;
}

// Include database connection
require_once 'dp.php';

// Get employee information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$employee_id = null; // Initialize to prevent undefined variable errors

// Get employee_id from users table
try {
    $stmt = $conn->prepare("SELECT employee_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_employee = $stmt->fetch(PDO::FETCH_ASSOC);
    $employee_id = $user_employee['employee_id'] ?? null;
} catch (PDOException $e) {
    $employee_id = null;
}

if (!$employee_id) {
    echo '<tr><td colspan="5" class="text-center">No attendance records found</td></tr>';
    exit;
}

// Get attendance history
function getAttendanceHistory($employee_id, $limit = 30) {
    global $conn;
    try {
        $sql = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

$attendanceHistory = getAttendanceHistory($employee_id);

if (empty($attendanceHistory)) {
    echo '<tr><td colspan="5" class="text-center">No attendance records found</td></tr>';
} else {
    foreach ($attendanceHistory as $record) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars(date('M d, Y', strtotime($record['attendance_date']))) . '</td>';
        echo '<td>' . (($record['clock_in'] && $record['clock_in'] !== '00:00:00' && $record['clock_in'] !== '0000-00-00 00:00:00') ? date('h:i A', strtotime($record['clock_in'])) : '-') . '</td>';
        echo '<td>' . (($record['clock_out'] && $record['clock_out'] !== '00:00:00' && $record['clock_out'] !== '0000-00-00 00:00:00') ? date('h:i A', strtotime($record['clock_out'])) : '-') . '</td>';
        echo '<td>' . ($record['working_hours'] ? $record['working_hours'] . 'h' : '-') . '</td>';
        echo '<td><span class="badge badge-' . strtolower($record['status'] ?? 'secondary') . '">' . htmlspecialchars($record['status'] ?? 'Not Recorded') . '</span></td>';
        echo '</tr>';
    }
}
?>
