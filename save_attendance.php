<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include database connection
require_once 'dp.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$employeeName = trim($_POST['employeeName'] ?? '');
$attendanceDate = $_POST['attendanceDate'] ?? '';
$checkInTime = $_POST['checkInTime'] ?? '';
$checkOutTime = $_POST['checkOutTime'] ?? '';
$status = $_POST['attendanceStatus'] ?? '';
$overtimeHours = floatval($_POST['overtimeHours'] ?? 0);
$lateMinutes = floatval($_POST['lateMinutes'] ?? 0);

// Validate required fields
if (empty($employeeName) || empty($attendanceDate) || empty($checkInTime) || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Find employee by name (assuming employeeName is full name)
try {
    $nameParts = explode(' ', $employeeName, 2);
    $firstName = $nameParts[0] ?? '';
    $lastName = $nameParts[1] ?? '';

    $stmt = $conn->prepare("
        SELECT ep.employee_id
        FROM employee_profiles ep
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        WHERE pi.first_name = ? AND pi.last_name = ?
        LIMIT 1
    ");
    $stmt->execute([$firstName, $lastName]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }

    $employeeId = $employee['employee_id'];

    // Build full datetime strings for clock_in and clock_out
    $clockInDt = null;
    $clockOutDt = null;
    if (!empty($attendanceDate) && !empty($checkInTime)) {
        // Ensure seconds are present in time input
        $checkInTimeWithSeconds = preg_match('/^\d{2}:\d{2}:\d{2}$/', $checkInTime) ? $checkInTime : ($checkInTime . ':00');
        $clockInDt = $attendanceDate . ' ' . $checkInTimeWithSeconds;
    }
    if (!empty($attendanceDate) && !empty($checkOutTime)) {
        $checkOutTimeWithSeconds = preg_match('/^\d{2}:\d{2}:\d{2}$/', $checkOutTime) ? $checkOutTime : ($checkOutTime . ':00');
        $clockOutDt = $attendanceDate . ' ' . $checkOutTimeWithSeconds;
    }

    // Calculate working hours if both datetimes are available
    $workingHours = 0;
    if (!empty($clockInDt) && !empty($clockOutDt)) {
        $checkInTs = strtotime($clockInDt);
        $checkOutTs = strtotime($clockOutDt);
        $workingHours = ($checkOutTs - $checkInTs) / 3600; // hours
        $workingHours = max(0, $workingHours); // ensure non-negative
    }

    // Insert or update attendance
    $stmt = $conn->prepare("
        INSERT INTO attendance (employee_id, attendance_date, clock_in, clock_out, working_hours, status, overtime_hours, late_minutes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        clock_in = VALUES(clock_in),
        clock_out = VALUES(clock_out),
        working_hours = VALUES(working_hours),
        status = VALUES(status),
        overtime_hours = VALUES(overtime_hours),
        late_minutes = VALUES(late_minutes)
    ");

    $success = $stmt->execute([
        $employeeId,
        $attendanceDate,
        $clockInDt,
        $clockOutDt,
        $workingHours,
        ucfirst($status),
        $overtimeHours,
        $lateMinutes
    ]);

    if ($success) {
        // Determine if it was an insert or update
        $affectedRows = $stmt->rowCount();
        $action = ($affectedRows == 1) ? 'Attendance recorded' : 'Attendance updated';

        // Fetch the attendance_id for logging
        $stmtId = $conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $stmtId->execute([$employeeId, $attendanceDate]);
        $attendanceRecord = $stmtId->fetch(PDO::FETCH_ASSOC);
        $attendanceId = $attendanceRecord ? $attendanceRecord['attendance_id'] : null;

        error_log("save_attendance: $action for employee $employeeId on $attendanceDate, attendance_id: $attendanceId");

        // Log the activity (commented out to prevent fatal if undefined)
        /*
        $details = [
            'employee_id' => $employeeId,
            'attendance_date' => $attendanceDate,
            'clock_in' => $checkInTime,
            'clock_out' => $checkOutTime,
            'working_hours' => $workingHours,
            'status' => ucfirst($status),
            'overtime_hours' => $overtimeHours,
            'late_minutes' => $lateMinutes
        ];
        logActivity($action, 'attendance', $attendanceId, $details);
        */

        echo json_encode(['success' => true, 'message' => 'Attendance saved successfully']);
    } else {
        error_log("save_attendance: Execute failed for employee $employeeId on $attendanceDate");
        echo json_encode(['success' => false, 'message' => 'Failed to save attendance']);
    }

} catch (PDOException $e) {
    error_log("Error saving attendance: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
