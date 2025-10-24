<?php
require_once 'config.php';

function getTotalEmployees() {
    global $conn;
    try {
        $sql = "SELECT COUNT(*) as total_employees FROM employee_profiles";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_employees'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getTotalDepartments() {
    global $conn;
    try {
        $sql = "SELECT COUNT(*) as total_departments FROM departments";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_departments'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getActiveJobOpenings() {
    global $conn;
    try {
        $sql = "SELECT COUNT(*) as active_job_openings FROM job_openings WHERE status = 'Open'";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['active_job_openings'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getUpcomingTrainingSessions() {
    global $conn;
    try {
        $sql = "SELECT COUNT(*) as upcoming_training_sessions FROM training_sessions WHERE status = 'Scheduled' AND start_date >= CURDATE()";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['upcoming_training_sessions'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getRecentActivities() {
    global $conn;
    try {
        $sql = "SELECT a.action, u.username, a.created_at FROM audit_logs a JOIN users u ON a.user_id = u.user_id ORDER BY a.created_at DESC LIMIT 5";
        $stmt = $conn->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("getRecentActivities: fetched " . count($result) . " activities");
        return $result;
    } catch (PDOException $e) {
        error_log("getRecentActivities: error - " . $e->getMessage());
        return [];
    }
}

// Leave Balances Functions
function getLeaveBalances() {
    global $conn;
    try {
        $sql = "SELECT lb.*, pi.first_name, pi.last_name, d.department_name,
                       (lb.vacation_leave + lb.sick_leave + lb.maternity_leave + lb.paternity_leave) as total_balance
                FROM leave_balances lb
                JOIN employee_profiles ep ON lb.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN departments d ON ep.job_role_id IN (
                    SELECT job_role_id FROM job_roles WHERE department = d.department_name
                )
                WHERE lb.year = YEAR(CURDATE())
                ORDER BY pi.first_name, pi.last_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getLeaveTypeTotals() {
    global $conn;
    try {
        $sql = "SELECT lt.leave_type_name,
                       SUM(lb.total_leaves) as total_allocated,
                       SUM(lb.leaves_taken) as total_taken,
                       SUM(lb.leaves_remaining) as total_remaining,
                       ROUND((SUM(lb.leaves_taken) / SUM(lb.total_leaves)) * 100, 2) as utilization_percentage
                FROM leave_types lt
                LEFT JOIN leave_balances lb ON lt.leave_type_id = lb.leave_type_id AND lb.year = YEAR(CURDATE())
                GROUP BY lt.leave_type_id, lt.leave_type_name
                ORDER BY lt.leave_type_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getLeaveUtilizationTrend() {
    global $conn;
    try {
        // This is a simplified trend - in a real system, you'd have historical data
        $sql = "SELECT MONTH(CURDATE()) as current_month,
                       YEAR(CURDATE()) as current_year,
                       SUM(leaves_taken) as current_taken,
                       (SELECT SUM(leaves_taken) FROM leave_balances WHERE year = YEAR(CURDATE()) - 1 AND MONTH(created_at) = MONTH(CURDATE())) as previous_year_taken
                FROM leave_balances
                WHERE year = YEAR(CURDATE())";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['current_month' => date('m'), 'current_year' => date('Y'), 'current_taken' => 0, 'previous_year_taken' => 0];
    } catch (PDOException $e) {
        return ['current_month' => date('m'), 'current_year' => date('Y'), 'current_taken' => 0, 'previous_year_taken' => 0];
    }
}

function getLowBalanceAlerts() {
    global $conn;
    try {
        $sql = "SELECT pi.first_name, pi.last_name, lb.vacation_leave, lb.sick_leave,
                       (lb.vacation_leave + lb.sick_leave + lb.maternity_leave + lb.paternity_leave) as total_balance
                FROM leave_balances lb
                JOIN employee_profiles ep ON lb.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                WHERE lb.year = YEAR(CURDATE())
                AND (lb.vacation_leave < 5 OR lb.sick_leave < 5 OR (lb.vacation_leave + lb.sick_leave + lb.maternity_leave + lb.paternity_leave) < 10)
                ORDER BY total_balance ASC";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Shifts Functions
function getShifts() {
    global $conn;
    try {
        $sql = "SELECT * FROM shifts ORDER BY shift_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Employee Shifts Functions
function getEmployeeShifts() {
    global $conn;
    try {
        $sql = "SELECT es.*, s.shift_name, pi.first_name, pi.last_name, d.department_name
                FROM employee_shifts es
                JOIN shifts s ON es.shift_id = s.shift_id
                JOIN employee_profiles ep ON es.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN departments d ON ep.job_role_id IN (
                    SELECT job_role_id FROM job_roles WHERE department = d.department_name
                )
                ORDER BY es.assigned_date DESC, pi.first_name, pi.last_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function addEmployeeShift($employeeId, $shiftId, $assignedDate, $isOvertime) {
    global $conn;
    try {
        $sql = "INSERT INTO employee_shifts (employee_id, shift_id, assigned_date, is_overtime) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$employeeId, $shiftId, $assignedDate, $isOvertime]);
    } catch (PDOException $e) {
        return false;
    }
}

function updateEmployeeShift($employeeShiftId, $shiftId, $assignedDate, $isOvertime) {
    global $conn;
    try {
        $sql = "UPDATE employee_shifts SET shift_id = ?, assigned_date = ?, is_overtime = ? WHERE employee_shift_id = ?";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$shiftId, $assignedDate, $isOvertime, $employeeShiftId]);
    } catch (PDOException $e) {
        return false;
    }
}

function deleteEmployeeShift($employeeShiftId) {
    global $conn;
    try {
        $sql = "DELETE FROM employee_shifts WHERE employee_shift_id = ?";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$employeeShiftId]);
    } catch (PDOException $e) {
        return false;
    }
}

// Additional helper functions
function getEmployees() {
    global $conn;
    try {
        $sql = "SELECT ep.employee_id, pi.first_name, pi.last_name, ep.employee_number
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                ORDER BY pi.first_name, pi.last_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getLeaveTypes() {
    global $conn;
    try {
        $sql = "SELECT * FROM leave_types ORDER BY leave_type_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Function to log user activity
function logActivity($action, $table_name, $record_id = null, $details = '') {
    global $conn;

    error_log("logActivity called: $action for table $table_name, record $record_id");
    error_log("logActivity: conn is " . (isset($conn) ? "set" : "not set"));
    error_log("logActivity: user_id in session is " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "not set"));

    if (isset($conn) && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $user_id,
                $action,
                $table_name,
                $record_id,
                null,
                json_encode($details),
                $ip_address,
                $user_agent
            ]);
            error_log("logActivity: Successfully executed INSERT, result: " . ($result ? "true" : "false"));
            error_log("logActivity: Last insert ID: " . $conn->lastInsertId());
        } catch (PDOException $e) {
            // Log error silently
            error_log("Error logging activity: " . $e->getMessage());
        }
    } else {
        error_log("logActivity: conn or user_id not set");
    }
}

function getRecentAuditLogs($limit = 5) {
    global $conn;
    $sql = "SELECT a.action, u.username, a.created_at
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.user_id
            ORDER BY a.created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$limit]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("getRecentAuditLogs: fetched " . count($result) . " activities");
    return $result;
}
?>
