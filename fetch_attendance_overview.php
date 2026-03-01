<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and has admin/hr role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr')) {
    http_response_code(401);
    exit;
}

// Include database connection
require_once 'dp.php';

try {
    // Get today's date for the query
    $today = date('Y-m-d');
    error_log("fetch_attendance_overview: Looking for attendance on date: {$today}");
    
    // First, let's check what attendance records exist for today
    $checkStmt = $conn->query("SELECT employee_id, attendance_date, clock_in, clock_out, status FROM attendance WHERE attendance_date = '{$today}'");
    $todayRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("fetch_attendance_overview: Found " . count($todayRecords) . " attendance records for today");
    foreach ($todayRecords as $rec) {
        error_log("  - Employee {$rec['employee_id']}: clock_in='{$rec['clock_in']}', clock_out='{$rec['clock_out']}'");
    }
    
    // Get employees with their attendance data
    $stmt = $conn->query("
        SELECT
            ep.employee_id,
            COALESCE(pi.first_name, 'Unknown') as first_name,
            COALESCE(pi.last_name, 'Employee') as last_name,
            ep.employee_number,
            COALESCE(jr.department, 'N/A') as department,
            a.attendance_date,
            a.clock_in,
            a.clock_out,
            a.working_hours,
            a.status,
            a.overtime_hours,
            CASE WHEN a.clock_in IS NOT NULL AND TIME(a.clock_in) > '08:00:00' THEN TIMESTAMPDIFF(MINUTE, '08:00:00', TIME(a.clock_in)) ELSE 0 END as late_minutes
        FROM employee_profiles ep
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN attendance a ON ep.employee_id = a.employee_id
            AND a.attendance_date = '{$today}'
        ORDER BY pi.first_name, pi.last_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("fetch_attendance_overview: Query executed, fetched " . count($employees) . " employees");

    if (empty($employees)) {
        // Debug: Check total employees
        $debugStmt = $conn->query("SELECT COUNT(*) as total FROM employee_profiles WHERE employment_status IN ('Full-time', 'Part-time')");
        $debugResult = $debugStmt->fetch(PDO::FETCH_ASSOC);
        $totalEmployees = $debugResult['total'] ?? 0;
        error_log("fetch_attendance_overview: No employees fetched, but total active employees: $totalEmployees");
        echo '<tr><td colspan="6" class="text-center">No employee records found. (Debug: ' . $totalEmployees . ' active employees in DB)</td></tr>';
    } else {
        // Debug: Log what we're getting
        error_log("fetch_attendance_overview: Processing " . count($employees) . " employees");
        foreach ($employees as $emp) {
            error_log("Employee: " . $emp['employee_id'] . " - Clock In: '" . var_export($emp['clock_in'], true) . "' - Clock Out: '" . var_export($emp['clock_out'], true) . "' - Type: " . gettype($emp['clock_in']));
        }
        foreach ($employees as $employee) {
            $fullName = htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']);
            $department = htmlspecialchars($employee['department']);
            $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($fullName) . "&background=E91E63&color=fff&size=35";

            // Determine status and styling
            $status = $employee['status'] ?? ($employee['clock_in'] && $employee['clock_in'] !== '00:00:00' ? 'Present' : 'Not Recorded');
            $statusClass = 'badge-secondary';
            
            // Format clock in time
            $clockIn = '-';
            if (isset($employee['clock_in']) && $employee['clock_in'] !== null) {
                $clockInValue = trim($employee['clock_in']);
                // Skip empty strings and MySQL zero dates
                if ($clockInValue !== '' && 
                    $clockInValue !== '00:00:00' && 
                    $clockInValue !== '0000-00-00 00:00:00' && 
                    strpos($clockInValue, '0000-00-00') === false) {
                    $timestamp = strtotime($clockInValue);
                    if ($timestamp !== false && $timestamp > 0) {
                        $clockIn = date('h:i A', $timestamp);
                    }
                }
            }
            
            // Format clock out time
            $clockOut = '-';
            if (isset($employee['clock_out']) && $employee['clock_out'] !== null) {
                $clockOutValue = trim($employee['clock_out']);
                // Skip empty strings and MySQL zero dates
                if ($clockOutValue !== '' && 
                    $clockOutValue !== '00:00:00' && 
                    $clockOutValue !== '0000-00-00 00:00:00' && 
                    strpos($clockOutValue, '0000-00-00') === false) {
                    $timestamp = strtotime($clockOutValue);
                    if ($timestamp !== false && $timestamp > 0) {
                        $clockOut = date('h:i A', $timestamp);
                    }
                }
            }
            $hours = $employee['working_hours'] ? $employee['working_hours'] . ' hours' : '0 hours';
            $overtime = $employee['overtime_hours'] ? $employee['overtime_hours'] . ' hours' : '0 hours';
            $late = $employee['late_minutes'] ? $employee['late_minutes'] . ' mins' : '0 mins';

            if ($status == 'Present') {
                $statusClass = 'badge-success';
            } elseif ($status == 'Absent') {
                $statusClass = 'badge-danger';
            } elseif ($status == 'Late') {
                $statusClass = 'badge-warning';
            }

            echo "<tr>
                <td>
                    <div class='d-flex align-items-center'>
                        <img src='{$avatarUrl}' alt='Profile' class='profile-image mr-2'>
                        <div>
                            <h6 class='mb-0'>{$fullName}</h6>
                            <small class='text-muted'>{$department}</small>
                        </div>
                    </div>
                </td>
                <td>" . date('Y-m-d') . "</td>
                <td>{$hours}</td>
                <td>{$overtime}</td>
                <td>{$late}</td>
                <td><span class='attendance-status badge {$statusClass}'>{$status}</span></td>
            </tr>";
        }
    }
} catch (PDOException $e) {
    error_log("fetch_attendance_overview: PDO error - " . $e->getMessage());
    echo '<tr><td colspan="6" class="text-center text-danger">Error loading attendance data: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
}

// Debug mode: Output JSON if ?debug=1
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    header('Content-Type: application/json');
    echo json_encode($employees ?? []);
    exit;
}
?>
