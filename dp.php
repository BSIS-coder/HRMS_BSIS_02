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
        // Get all employees with their leave balances grouped by employee, including gender info
        // Fixed: Use MAX to get single value per leave type (in case of duplicates), fallback to default_days from leave_types
        $sql = "SELECT 
                    ep.employee_id,
                    pi.first_name, 
                    pi.last_name,
                    pi.gender,
                    ep.employee_number,
                    COALESCE(
                        (SELECT d.department_name 
                         FROM job_roles jr 
                         LEFT JOIN departments d ON jr.department = d.department_name 
                         WHERE jr.job_role_id = ep.job_role_id 
                         LIMIT 1),
                        (SELECT jr.department 
                         FROM job_roles jr 
                         WHERE jr.job_role_id = ep.job_role_id 
                         LIMIT 1),
                        'N/A'
                    ) as department_name,
                    COALESCE(
                        (SELECT lb.leaves_remaining 
                         FROM leave_balances lb 
                         JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id 
                         WHERE lb.employee_id = ep.employee_id 
                         AND lb.year = YEAR(CURDATE()) 
                         AND lt.leave_type_name = 'Vacation Leave' 
                         LIMIT 1),
                        (SELECT default_days FROM leave_types WHERE leave_type_name = 'Vacation Leave' LIMIT 1),
                        0
                    ) as vacation_leave,
                    COALESCE(
                        (SELECT lb.leaves_remaining 
                         FROM leave_balances lb 
                         JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id 
                         WHERE lb.employee_id = ep.employee_id 
                         AND lb.year = YEAR(CURDATE()) 
                         AND lt.leave_type_name = 'Sick Leave' 
                         LIMIT 1),
                        (SELECT default_days FROM leave_types WHERE leave_type_name = 'Sick Leave' LIMIT 1),
                        0
                    ) as sick_leave,
                    COALESCE(
                        CASE WHEN pi.gender = 'Female' THEN
                            (SELECT lb.leaves_remaining 
                             FROM leave_balances lb 
                             JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id 
                             WHERE lb.employee_id = ep.employee_id 
                             AND lb.year = YEAR(CURDATE()) 
                             AND lt.leave_type_name = 'Maternity Leave' 
                             LIMIT 1)
                        ELSE 0 END,
                        CASE WHEN pi.gender = 'Female' THEN
                            (SELECT default_days FROM leave_types WHERE leave_type_name = 'Maternity Leave' LIMIT 1)
                        ELSE 0 END,
                        0
                    ) as maternity_leave,
                    COALESCE(
                        CASE WHEN pi.gender = 'Male' THEN
                            (SELECT lb.leaves_remaining 
                             FROM leave_balances lb 
                             JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id 
                             WHERE lb.employee_id = ep.employee_id 
                             AND lb.year = YEAR(CURDATE()) 
                             AND lt.leave_type_name = 'Paternity Leave' 
                             LIMIT 1)
                        ELSE 0 END,
                        CASE WHEN pi.gender = 'Male' THEN
                            (SELECT default_days FROM leave_types WHERE leave_type_name = 'Paternity Leave' LIMIT 1)
                        ELSE 0 END,
                        0
                    ) as paternity_leave,
                    (
                        COALESCE(
                            (SELECT lb.leaves_remaining 
                             FROM leave_balances lb 
                             JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id 
                             WHERE lb.employee_id = ep.employee_id 
                             AND lb.year = YEAR(CURDATE()) 
                             AND lt.leave_type_name = 'Vacation Leave' 
                             LIMIT 1),
                            (SELECT default_days FROM leave_types WHERE leave_type_name = 'Vacation Leave' LIMIT 1),
                            0
                        ) +
                        COALESCE(
                            (SELECT lb.leaves_remaining 
                             FROM leave_balances lb 
                             JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id 
                             WHERE lb.employee_id = ep.employee_id 
                             AND lb.year = YEAR(CURDATE()) 
                             AND lt.leave_type_name = 'Sick Leave' 
                             LIMIT 1),
                            (SELECT default_days FROM leave_types WHERE leave_type_name = 'Sick Leave' LIMIT 1),
                            0
                        ) +
                        CASE WHEN pi.gender = 'Female' THEN
                            COALESCE(
                                (SELECT lb.leaves_remaining 
                                 FROM leave_balances lb 
                                 JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id 
                                 WHERE lb.employee_id = ep.employee_id 
                                 AND lb.year = YEAR(CURDATE()) 
                                 AND lt.leave_type_name = 'Maternity Leave' 
                                 LIMIT 1),
                                (SELECT default_days FROM leave_types WHERE leave_type_name = 'Maternity Leave' LIMIT 1),
                                0
                            )
                        ELSE 0 END +
                        CASE WHEN pi.gender = 'Male' THEN
                            COALESCE(
                                (SELECT lb.leaves_remaining 
                                 FROM leave_balances lb 
                                 JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id 
                                 WHERE lb.employee_id = ep.employee_id 
                                 AND lb.year = YEAR(CURDATE()) 
                                 AND lt.leave_type_name = 'Paternity Leave' 
                                 LIMIT 1),
                                (SELECT default_days FROM leave_types WHERE leave_type_name = 'Paternity Leave' LIMIT 1),
                                0
                            )
                        ELSE 0 END
                    ) as total_balance
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                ORDER BY pi.first_name, pi.last_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getLeaveBalances error: " . $e->getMessage());
        return [];
    }
}

function getLeaveTypeTotals() {
    global $conn;
    try {
        $sql = "SELECT lt.leave_type_name,
                       COALESCE(SUM(lb.total_leaves), 0) as total_allocated,
                       COALESCE(SUM(lb.leaves_taken), 0) as total_taken,
                       COALESCE(SUM(lb.leaves_remaining), 0) as total_remaining,
                       CASE 
                           WHEN SUM(lb.total_leaves) > 0 
                           THEN ROUND((SUM(lb.leaves_taken) / SUM(lb.total_leaves)) * 100, 2)
                           ELSE 0
                       END as utilization_percentage
                FROM leave_types lt
                LEFT JOIN leave_balances lb ON lt.leave_type_id = lb.leave_type_id AND lb.year = YEAR(CURDATE())
                GROUP BY lt.leave_type_id, lt.leave_type_name
                ORDER BY lt.leave_type_name";
        $stmt = $conn->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("getLeaveTypeTotals: Fetched " . count($results) . " leave types");
        if (count($results) > 0) {
            error_log("getLeaveTypeTotals: First result - " . json_encode($results[0]));
        }
        return $results;
    } catch (PDOException $e) {
        error_log("getLeaveTypeTotals error: " . $e->getMessage());
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
        $sql = "SELECT 
                    pi.first_name, 
                    pi.last_name,
                    pi.gender,
                    COALESCE(SUM(CASE WHEN lt.leave_type_name = 'Vacation Leave' THEN lb.leaves_remaining ELSE 0 END), 0) as vacation_leave,
                    COALESCE(SUM(CASE WHEN lt.leave_type_name = 'Sick Leave' THEN lb.leaves_remaining ELSE 0 END), 0) as sick_leave,
                    COALESCE(SUM(lb.leaves_remaining), 0) as total_balance
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN leave_balances lb ON ep.employee_id = lb.employee_id AND lb.year = YEAR(CURDATE())
                LEFT JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                GROUP BY ep.employee_id, pi.first_name, pi.last_name, pi.gender
                HAVING (vacation_leave < 5 OR sick_leave < 5 OR total_balance < 10)
                ORDER BY total_balance ASC";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getLowBalanceAlerts error: " . $e->getMessage());
        return [];
    }
}

// Shifts Functions
function getShifts() {
    global $conn;
    try {
        $sql = "SELECT DISTINCT * FROM shifts ORDER BY shift_name";
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

        $sql = "SELECT ep.employee_id,
                       CASE
                           WHEN EXISTS (
                               SELECT 1 FROM leave_requests lr
                               WHERE lr.employee_id = ep.employee_id
                               AND lr.status = 'Approved'
                               AND CURDATE() BETWEEN lr.start_date AND lr.end_date
                           ) THEN 'On Leave'
                           ELSE 'Active'
                       END as status,
                       pi.first_name, pi.last_name, d.department_name,
                       es.employee_shift_id, es.shift_id, es.assigned_date, es.is_overtime,
                       s.shift_name
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN employee_shifts es ON ep.employee_id = es.employee_id
                LEFT JOIN shifts s ON es.shift_id = s.shift_id
                LEFT JOIN departments d ON ep.job_role_id IN (
                    SELECT job_role_id FROM job_roles WHERE department = d.department_name
                )
                WHERE ep.employee_id IN (
                    SELECT employee_id FROM employment_history
                    WHERE history_id IN (
                        SELECT MAX(history_id) FROM employment_history GROUP BY employee_id
                    ) AND employment_status = 'Active'
                )
                ORDER BY pi.first_name, pi.last_name";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function addEmployeeShift($employeeId, $shiftId, $assignedDate, $isOvertime) {
    global $conn;
    try {
        $sql = "INSERT INTO employee_shifts (employee_id, shift_id, assigned_date, is_overtime, status) VALUES (?, ?, ?, ?, 'Active')";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$employeeId, $shiftId, $assignedDate, $isOvertime]);
    } catch (PDOException $e) {
        return false;
    }
}

function updateEmployeeShift($employeeShiftId, $shiftId, $assignedDate, $isOvertime) {
    global $conn;
    try {
        $sql = "UPDATE employee_shifts SET shift_id = ?, assigned_date = ?, is_overtime = ?, status = 'Active' WHERE employee_shift_id = ?";
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

// Function to ensure all employees have leave balance records with gender-based restrictions
function ensureEmployeeLeaveBalances() {
    global $conn;
    try {
        // Get all employees with their gender information who don't have leave balance records for current year
        $sql = "SELECT ep.employee_id, pi.gender, lt.leave_type_id, lt.leave_type_name, lt.default_days
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                CROSS JOIN leave_types lt
                LEFT JOIN leave_balances lb ON ep.employee_id = lb.employee_id 
                    AND lt.leave_type_id = lb.leave_type_id 
                    AND lb.year = YEAR(CURDATE())
                WHERE lb.balance_id IS NULL";
        
        $stmt = $conn->query($sql);
        $missingBalances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $createdCount = 0;
        
        // Insert missing leave balance records with gender restrictions
        foreach ($missingBalances as $balance) {
            $employeeGender = $balance['gender'];
            $leaveTypeName = $balance['leave_type_name'];
            
            // Apply gender-based restrictions
            $shouldCreate = true;
            
            if ($leaveTypeName === 'Maternity Leave' && $employeeGender !== 'Female') {
                $shouldCreate = false;
                error_log("Skipping Maternity Leave for non-female employee: {$balance['employee_id']} (Gender: $employeeGender)");
            }
            
            if ($leaveTypeName === 'Paternity Leave' && $employeeGender !== 'Male') {
                $shouldCreate = false;
                error_log("Skipping Paternity Leave for non-male employee: {$balance['employee_id']} (Gender: $employeeGender)");
            }
            
            if ($leaveTypeName === 'Menstrual Disorder Leave' && $employeeGender !== 'Female') {
                $shouldCreate = false;
                error_log("Skipping Menstrual Disorder Leave for non-female employee: {$balance['employee_id']} (Gender: $employeeGender)");
            }
            
            if ($shouldCreate) {
                $insertSql = "INSERT INTO leave_balances (employee_id, leave_type_id, year, total_leaves, leaves_taken, leaves_pending, leaves_remaining) 
                             VALUES (?, ?, ?, ?, 0, 0, ?)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->execute([
                    $balance['employee_id'],
                    $balance['leave_type_id'],
                    date('Y'),
                    $balance['default_days'],
                    $balance['default_days']
                ]);
                $createdCount++;
            }
        }
        
        return $createdCount;
    } catch (PDOException $e) {
        error_log("ensureEmployeeLeaveBalances error: " . $e->getMessage());
        return 0;
    }
}

// Function to clean up leave balance records that violate gender restrictions
function cleanupGenderViolations() {
    global $conn;
    try {
        // Remove maternity leave records for non-female employees
        $sql = "DELETE lb FROM leave_balances lb
                JOIN employee_profiles ep ON lb.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                WHERE lt.leave_type_name = 'Maternity Leave' AND pi.gender != 'Female'";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $maternityRemoved = $stmt->rowCount();
        
        // Remove paternity leave records for non-male employees
        $sql = "DELETE lb FROM leave_balances lb
                JOIN employee_profiles ep ON lb.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                WHERE lt.leave_type_name = 'Paternity Leave' AND pi.gender != 'Male'";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $paternityRemoved = $stmt->rowCount();
        
        // Remove menstrual disorder leave records for non-female employees
        $sql = "DELETE lb FROM leave_balances lb
                JOIN employee_profiles ep ON lb.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                WHERE lt.leave_type_name = 'Menstrual Disorder Leave' AND pi.gender != 'Female'";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $menstrualRemoved = $stmt->rowCount();
        
        $totalRemoved = $maternityRemoved + $paternityRemoved + $menstrualRemoved;
        
        if ($totalRemoved > 0) {
            error_log("Cleaned up $totalRemoved gender-violating leave balance records ($maternityRemoved maternity, $paternityRemoved paternity, $menstrualRemoved menstrual disorder)");
        }
        
        return $totalRemoved;
    } catch (PDOException $e) {
        error_log("cleanupGenderViolations error: " . $e->getMessage());
        return 0;
    }
}

// Function to validate leave requests based on gender
function validateLeaveRequestByGender($employee_id, $leave_type_id) {
    global $conn;
    try {
        // Get employee gender and leave type name
        $sql = "SELECT pi.gender, lt.leave_type_name
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                JOIN leave_types lt ON lt.leave_type_id = ?
                WHERE ep.employee_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$leave_type_id, $employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return ['valid' => false, 'message' => 'Employee or leave type not found.'];
        }
        
        $employeeGender = $result['gender'];
        $leaveTypeName = $result['leave_type_name'];
        
        // Check gender restrictions
        if ($leaveTypeName === 'Maternity Leave' && $employeeGender !== 'Female') {
            return [
                'valid' => false, 
                'message' => 'Maternity leave is only available for female employees. Your gender is recorded as: ' . $employeeGender
            ];
        }
        
        if ($leaveTypeName === 'Paternity Leave' && $employeeGender !== 'Male') {
            return [
                'valid' => false, 
                'message' => 'Paternity leave is only available for male employees. Your gender is recorded as: ' . $employeeGender
            ];
        }
        
        if ($leaveTypeName === 'Menstrual Disorder Leave' && $employeeGender !== 'Female') {
            return [
                'valid' => false, 
                'message' => 'Menstrual disorder leave is only available for female employees. Your gender is recorded as: ' . $employeeGender
            ];
        }
        
        return ['valid' => true, 'message' => 'Leave request is valid.'];
        
    } catch (PDOException $e) {
        error_log("validateLeaveRequestByGender error: " . $e->getMessage());
        return ['valid' => false, 'message' => 'Error validating leave request. Please contact administrator.'];
    }
}

// Function to get leave types appropriate for an employee's gender
function getLeaveTypesForEmployee($employee_id) {
    global $conn;
    try {
        // Get employee gender
        $sql = "SELECT pi.gender
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                WHERE ep.employee_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return [];
        }

        $employeeGender = $result['gender'];

        // Get all leave types with gender restrictions
        $sql = "SELECT * FROM leave_types ORDER BY leave_type_name";
        $stmt = $conn->query($sql);
        $allLeaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $appropriateLeaveTypes = [];

        foreach ($allLeaveTypes as $leaveType) {
            $leaveTypeName = $leaveType['leave_type_name'];
            $shouldInclude = true;

            // Apply gender restrictions
            if ($leaveTypeName === 'Maternity Leave' && $employeeGender !== 'Female') {
                $shouldInclude = false;
            }

            if ($leaveTypeName === 'Paternity Leave' && $employeeGender !== 'Male') {
                $shouldInclude = false;
            }

            if ($leaveTypeName === 'Menstrual Disorder Leave' && $employeeGender !== 'Female') {
                $shouldInclude = false;
            }

            if ($shouldInclude) {
                $appropriateLeaveTypes[] = $leaveType;
            }
        }

        return $appropriateLeaveTypes;

    } catch (PDOException $e) {
        error_log("getLeaveTypesForEmployee error: " . $e->getMessage());
        return [];
    }
}

// Function to get employee hire date
function getEmployeeHireDate($employeeId) {
    global $conn;
    try {
        $sql = "SELECT hire_date FROM employee_profiles WHERE employee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employeeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['hire_date'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

// Comprehensive AI Search Function - Searches across all HR modules
function searchEmployeeComprehensive($searchTerm) {
    global $conn;
    $results = [
        'employee' => [],
        'payroll' => [],
        'performance' => [],
        'leave' => [],
        'exit' => [],
        'recruitment' => [],
        'training' => [],
        'employment_history' => [],
        'documents' => []
    ];
    
    try {
        // Use case-insensitive search with multiple patterns
        $searchPattern = '%' . $searchTerm . '%';
        $searchPatternLower = '%' . strtolower($searchTerm) . '%';
        
        // 1. Search Employee Profiles and Personal Information
        $sql = "SELECT DISTINCT 
                    ep.employee_id,
                    ep.employee_number,
                    ep.hire_date,
                    ep.employment_status,
                    ep.current_salary,
                    ep.work_email,
                    ep.work_phone,
                    pi.first_name,
                    pi.last_name,
                    pi.phone_number,
                    pi.date_of_birth,
                    jr.title as job_title,
                    jr.department as department_name
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                WHERE LOWER(pi.first_name) LIKE ? 
                   OR LOWER(pi.last_name) LIKE ? 
                   OR LOWER(CONCAT(pi.first_name, ' ', pi.last_name)) LIKE ?
                   OR LOWER(ep.employee_number) LIKE ?
                   OR LOWER(ep.work_email) LIKE ?
                   OR LOWER(pi.phone_number) LIKE ?
                ORDER BY pi.first_name, pi.last_name
                LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$searchPatternLower, $searchPatternLower, $searchPatternLower, $searchPatternLower, $searchPatternLower, $searchPatternLower]);
        $results['employee'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log for debugging
        error_log("Search '$searchTerm': Found " . count($results['employee']) . " employees");
        
        // Get employee IDs for further searches
        $employeeIds = array_column($results['employee'], 'employee_id');
        
        if (!empty($employeeIds)) {
            $placeholders = str_repeat('?,', count($employeeIds) - 1) . '?';
            
            // 2. Search Payroll Data
            $sql = "SELECT 
                        pt.payroll_transaction_id,
                        pt.employee_id,
                        pt.payroll_cycle_id,
                        pt.gross_pay as base_salary,
                        (pt.tax_deductions + pt.statutory_deductions + pt.other_deductions) as deductions,
                        pt.net_pay,
                        pt.status,
                        pt.created_at,
                        pt.processed_date,
                        pc.cycle_name,
                        pi.first_name,
                        pi.last_name
                    FROM payroll_transactions pt
                    JOIN employee_profiles ep ON pt.employee_id = ep.employee_id
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    LEFT JOIN payroll_cycles pc ON pt.payroll_cycle_id = pc.payroll_cycle_id
                    WHERE pt.employee_id IN ($placeholders)
                    ORDER BY pt.created_at DESC
                    LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($employeeIds);
            $results['payroll'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Payroll results: " . count($results['payroll']));
            
            // 3. Search Performance Reviews
            $sql = "SELECT 
                        pr.review_id,
                        pr.employee_id,
                        pr.cycle_id as review_cycle_id,
                        pr.overall_rating,
                        pr.status,
                        pr.review_date,
                        pr.comments,
                        pr.strengths,
                        pr.areas_of_improvement,
                        prc.cycle_name,
                        pi.first_name,
                        pi.last_name
                    FROM performance_reviews pr
                    JOIN employee_profiles ep ON pr.employee_id = ep.employee_id
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    LEFT JOIN performance_review_cycles prc ON pr.cycle_id = prc.cycle_id
                    WHERE pr.employee_id IN ($placeholders)
                    ORDER BY pr.review_date DESC
                    LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($employeeIds);
            $results['performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Performance results: " . count($results['performance']));
            
            // 4. Search Leave Records
            $sql = "SELECT 
                        lr.leave_id as leave_request_id,
                        lr.employee_id,
                        lr.leave_type_id,
                        lr.start_date,
                        lr.end_date,
                        lr.total_days as days_requested,
                        lr.reason,
                        lr.status,
                        lr.applied_on as created_at,
                        lr.approved_on,
                        lt.leave_type_name,
                        pi.first_name,
                        pi.last_name
                    FROM leave_requests lr
                    JOIN employee_profiles ep ON lr.employee_id = ep.employee_id
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    LEFT JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                    WHERE lr.employee_id IN ($placeholders)
                    ORDER BY lr.applied_on DESC
                    LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($employeeIds);
            $results['leave'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Leave results: " . count($results['leave']));
            
            // 5. Search Exit Management
            $sql = "SELECT 
                        e.exit_id,
                        e.employee_id,
                        e.exit_type,
                        e.exit_reason,
                        e.notice_date,
                        e.exit_date,
                        e.status,
                        e.created_at,
                        pi.first_name,
                        pi.last_name
                    FROM exits e
                    JOIN employee_profiles ep ON e.employee_id = ep.employee_id
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    WHERE e.employee_id IN ($placeholders)
                    ORDER BY e.created_at DESC
                    LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($employeeIds);
            $results['exit'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 6. Search Training Enrollments
            $sql = "SELECT 
                        te.enrollment_id,
                        te.employee_id,
                        te.session_id,
                        te.enrollment_date,
                        te.status,
                        te.completion_date,
                        ts.session_name,
                        ts.start_date,
                        ts.end_date,
                        tc.course_name,
                        pi.first_name,
                        pi.last_name
                    FROM training_enrollments te
                    JOIN employee_profiles ep ON te.employee_id = ep.employee_id
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    LEFT JOIN training_sessions ts ON te.session_id = ts.session_id
                    LEFT JOIN training_courses tc ON ts.course_id = tc.course_id
                    WHERE te.employee_id IN ($placeholders)
                    ORDER BY te.enrollment_date DESC
                    LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($employeeIds);
            $results['training'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Training results: " . count($results['training']));
            
            // 7. Search Employment History
            $sql = "SELECT 
                        eh.history_id,
                        eh.employee_id,
                        eh.job_title,
                        eh.department_id,
                        eh.employment_type,
                        eh.start_date,
                        eh.end_date,
                        eh.employment_status,
                        eh.reporting_manager_id,
                        eh.location,
                        eh.base_salary,
                        eh.allowances,
                        eh.bonuses,
                        eh.salary_adjustments,
                        eh.reason_for_change,
                        eh.promotions_transfers,
                        eh.created_at,
                        d.department_name,
                        pi.first_name,
                        pi.last_name,
                        manager.first_name as manager_first,
                        manager.last_name as manager_last
                    FROM employment_history eh
                    JOIN employee_profiles ep ON eh.employee_id = ep.employee_id
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    LEFT JOIN departments d ON eh.department_id = d.department_id
                    LEFT JOIN employee_profiles ep_manager ON eh.reporting_manager_id = ep_manager.employee_id
                    LEFT JOIN personal_information manager ON ep_manager.personal_info_id = manager.personal_info_id
                    WHERE eh.employee_id IN ($placeholders)
                    ORDER BY eh.start_date DESC
                    LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($employeeIds);
            $results['employment_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Employment history results: " . count($results['employment_history']));
            
            // 8. Search Document Management
            $sql = "SELECT 
                        dm.document_id,
                        dm.employee_id,
                        dm.document_type,
                        dm.document_name,
                        dm.file_path,
                        dm.upload_date,
                        dm.expiry_date,
                        dm.document_status,
                        dm.notes,
                        dm.created_at,
                        pi.first_name,
                        pi.last_name
                    FROM document_management dm
                    JOIN employee_profiles ep ON dm.employee_id = ep.employee_id
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    WHERE dm.employee_id IN ($placeholders)
                    ORDER BY dm.created_at DESC
                    LIMIT 50";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($employeeIds);
            $results['documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Documents results: " . count($results['documents']));
        } else {
            error_log("No employee IDs found for search: '$searchTerm'");
        }
        
        // 9. Search Recruitment (if employee was a candidate) - Independent search
        $sql = "SELECT 
                    ja.application_id,
                    ja.candidate_id,
                    ja.job_opening_id,
                    ja.application_date,
                    ja.status,
                    ja.interview_date,
                    c.first_name,
                    c.last_name,
                    c.email,
                    jo.title as job_title
                FROM job_applications ja
                JOIN candidates c ON ja.candidate_id = c.candidate_id
                LEFT JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                WHERE (LOWER(c.first_name) LIKE ? 
                   OR LOWER(c.last_name) LIKE ? 
                   OR LOWER(CONCAT(c.first_name, ' ', c.last_name)) LIKE ?
                   OR LOWER(c.email) LIKE ?)
                ORDER BY ja.application_date DESC
                LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$searchPatternLower, $searchPatternLower, $searchPatternLower, $searchPatternLower]);
        $results['recruitment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Recruitment results: " . count($results['recruitment']));
        
        // Log summary
        error_log("Search summary for '$searchTerm': Employee=" . count($results['employee']) . 
                  ", Payroll=" . count($results['payroll']) . 
                  ", Performance=" . count($results['performance']) . 
                  ", Leave=" . count($results['leave']) . 
                  ", Exit=" . count($results['exit']) . 
                  ", Training=" . count($results['training']) . 
                  ", Employment History=" . count($results['employment_history']) . 
                  ", Documents=" . count($results['documents']) . 
                  ", Recruitment=" . count($results['recruitment']));
        
        return $results;
        
    } catch (PDOException $e) {
        error_log("searchEmployeeComprehensive error: " . $e->getMessage());
        return $results;
    }
}
?>
