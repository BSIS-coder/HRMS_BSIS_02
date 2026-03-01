<?php
/**
 * ATTENDANCE SUMMARY PAGE
 * 
 * Applicable Philippine Republic Acts:
 * - RA 6727 (Implementing Rules and Regulations of the Wage Order)
 *   - Establishes 8-hour work day standard (clock-in baseline 08:00 AM)
 *   - On-time vs. Late tracking for wage compliance
 *   - Attendance records as basis for overtime and compensation calculation
 *   - Absent = no compensation (minus payment if unpaid leave)
 *   - Late minutes tracked for potential salary deductions
 * 
 * - RA 10173 (Data Privacy Act of 2012) - APPLIES TO ALL PAGES
 *   - Attendance summary contains PERSONAL INFORMATION
 *   - Aggregate attendance data reveals employee work patterns and productivity
 *   - Restrict summary access to authorized supervisory/HR personnel only
 *   - Do not share attendance percentages with unauthorized viewers
 *   - Protect employee identity in attendance reports
 *   - Maintain confidentiality of late arrival/absence information
 *   - Implement access controls limiting visibility to direct supervisors/HR
 *   - Keep audit trail of who accessed attendance summaries
 *   - Present/Absent percentages cannot be shared publicly
 *   - Ensure employee consent before using data for disciplinary action
 * 
 * Compliance Note: On-time baseline is set at 08:00 AM per wage order.
 * Attendance data is critical for:
 * - Validating 8-hour work day compliance
 * - Calculating overtime compensation
 * - Determining leave deductions
 * - Monitoring excessive late arrivals
 * 
 * Present/Absent percentages help identify patterns that may indicate
 * labor law violations or need for HR intervention.
 * All attendance summary data is personal information protected under RA 10173.
 */

session_start();
// Restrict access for employees
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] === 'employee') {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Summary - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .summary-card {
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
        }
        
        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-medium);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Attendance Summary</h2>
                
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calendar-check mr-2"></i>Attendance Overview</h5>
                            </div>
                            <div class="card-body">
                <?php
                // Get total employees
                $totalEmployees = 0;
                $totalPresent = 0;
                $totalAbsent = 0;

                try {
                    // Get total active employees using LEFT JOIN instead of restrictive subquery
                    $stmt = $conn->query("
                        SELECT COUNT(DISTINCT ep.employee_id) as count
                        FROM employee_profiles ep
                        LEFT JOIN (
                            SELECT employee_id, MAX(history_id) as max_history_id
                            FROM employment_history
                            GROUP BY employee_id
                        ) eh_max ON ep.employee_id = eh_max.employee_id
                        LEFT JOIN employment_history eh ON eh_max.employee_id = eh.employee_id
                            AND eh_max.max_history_id = eh.history_id
                        WHERE (eh.employment_status = 'Active' OR eh.employment_status IS NULL)
                    ");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $totalEmployees = $result['count'] ?? 0;

                    // If no employees found, try simpler query
                    if ($totalEmployees == 0) {
                        $stmt = $conn->query("SELECT COUNT(*) as count FROM employee_profiles");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $totalEmployees = $result['count'] ?? 0;
                    }

                    // Get today's attendance summary (if available) for active employees only
                    $today = date('Y-m-d');
                    $stmt = $conn->query("
                        SELECT COUNT(DISTINCT a.employee_id) as present
                        FROM attendance a
                        JOIN employee_profiles ep ON a.employee_id = ep.employee_id
                        LEFT JOIN (
                            SELECT employee_id, MAX(history_id) as max_history_id
                            FROM employment_history
                            GROUP BY employee_id
                        ) eh_max ON ep.employee_id = eh_max.employee_id
                        LEFT JOIN employment_history eh ON eh_max.employee_id = eh.employee_id
                            AND eh_max.max_history_id = eh.history_id
                        WHERE a.attendance_date = '$today'
                        AND (a.status = 'Present' OR (a.status IS NULL AND a.clock_in IS NOT NULL))
                        AND (eh.employment_status = 'Active' OR eh.employment_status IS NULL)
                    ");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $totalPresent = $result['present'] ?? 0;

                    // If no results, try simpler query
                    if ($totalPresent == 0 && $totalEmployees > 0) {
                        $stmt = $conn->query("
                            SELECT COUNT(DISTINCT employee_id) as present
                            FROM attendance
                            WHERE attendance_date = '$today'
                            AND (status = 'Present' OR (status IS NULL AND clock_in IS NOT NULL))
                        ");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $totalPresent = $result['present'] ?? 0;
                    }

                    $totalAbsent = $totalEmployees - $totalPresent;

                } catch (PDOException $e) {
                    error_log("Error fetching attendance stats: " . $e->getMessage());
                }

                $presentPercentage = $totalEmployees > 0 ? round(($totalPresent / $totalEmployees) * 100) : 0;
                $absentPercentage = 100 - $presentPercentage;
                ?>

                <?php
                // Get on-time and late attendance counts
                $totalOnTime = 0;
                $totalLate = 0;

                try {
                    // Get on-time attendance (clock_in <= 08:00:00)
                    $stmt = $conn->query("
                        SELECT COUNT(DISTINCT a.employee_id) as on_time
                        FROM attendance a
                        JOIN employee_profiles ep ON a.employee_id = ep.employee_id
                        LEFT JOIN (
                            SELECT employee_id, MAX(history_id) as max_history_id
                            FROM employment_history
                            GROUP BY employee_id
                        ) eh_max ON ep.employee_id = eh_max.employee_id
                        LEFT JOIN employment_history eh ON eh_max.employee_id = eh.employee_id
                            AND eh_max.max_history_id = eh.history_id
                        WHERE a.attendance_date = '$today'
                        AND (a.status = 'Present' OR (a.status IS NULL AND a.clock_in IS NOT NULL))
                        AND TIME(a.clock_in) <= '08:00:00'
                        AND (eh.employment_status = 'Active' OR eh.employment_status IS NULL)
                    ");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $totalOnTime = $result['on_time'] ?? 0;

                    // If no results, try simpler query
                    if ($totalOnTime == 0 && $totalPresent > 0) {
                        $stmt = $conn->query("
                            SELECT COUNT(DISTINCT employee_id) as on_time
                            FROM attendance
                            WHERE attendance_date = '$today'
                            AND (status = 'Present' OR (status IS NULL AND clock_in IS NOT NULL))
                            AND TIME(clock_in) <= '08:00:00'
                        ");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $totalOnTime = $result['on_time'] ?? 0;
                    }

                    // Get late attendance (clock_in > 08:00:00)
                    $stmt = $conn->query("
                        SELECT COUNT(DISTINCT a.employee_id) as late
                        FROM attendance a
                        JOIN employee_profiles ep ON a.employee_id = ep.employee_id
                        LEFT JOIN (
                            SELECT employee_id, MAX(history_id) as max_history_id
                            FROM employment_history
                            GROUP BY employee_id
                        ) eh_max ON ep.employee_id = eh_max.employee_id
                        LEFT JOIN employment_history eh ON eh_max.employee_id = eh.employee_id
                            AND eh_max.max_history_id = eh.history_id
                        WHERE a.attendance_date = '$today'
                        AND (a.status = 'Present' OR (a.status IS NULL AND a.clock_in IS NOT NULL))
                        AND TIME(a.clock_in) > '08:00:00'
                        AND (eh.employment_status = 'Active' OR eh.employment_status IS NULL)
                    ");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $totalLate = $result['late'] ?? 0;

                    // If no results, try simpler query
                    if ($totalLate == 0 && $totalPresent > 0) {
                        $stmt = $conn->query("
                            SELECT COUNT(DISTINCT employee_id) as late
                            FROM attendance
                            WHERE attendance_date = '$today'
                            AND (status = 'Present' OR (status IS NULL AND clock_in IS NOT NULL))
                            AND TIME(clock_in) > '08:00:00'
                        ");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $totalLate = $result['late'] ?? 0;
                    }

                } catch (PDOException $e) {
                    error_log("Error fetching on-time/late stats: " . $e->getMessage());
                }

                $onTimePercentage = $totalPresent > 0 ? round(($totalOnTime / $totalPresent) * 100) : 0;
                $latePercentage = $totalPresent > 0 ? round(($totalLate / $totalPresent) * 100) : 0;
                ?>

                <div class="row text-center mb-4">
                    <div class="col-2">
                        <h4 class="text-primary"><?php echo $totalEmployees; ?></h4>
                        <small class="text-muted">Total Employees</small>
                    </div>
                    <div class="col-2">
                        <h4 class="text-success"><?php echo $totalPresent; ?></h4>
                        <small class="text-muted">Present Today</small>
                    </div>
                    <div class="col-2">
                        <h4 class="text-danger"><?php echo $totalAbsent; ?></h4>
                        <small class="text-muted">Absent Today</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-info"><?php echo $totalOnTime; ?></h4>
                        <small class="text-muted">On-Time Arrivals</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-warning"><?php echo $totalLate; ?></h4>
                        <small class="text-muted">Late Arrivals</small>
                    </div>
                </div>
                <div class="progress mb-2">
                    <div class="progress-bar bg-success" style="width: <?php echo $presentPercentage; ?>%">Present (<?php echo $presentPercentage; ?>%)</div>
                </div>
                <div class="progress mb-2">
                    <div class="progress-bar bg-info" style="width: <?php echo $onTimePercentage; ?>%">On-Time (<?php echo $onTimePercentage; ?>% of Present)</div>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-warning" style="width: <?php echo $latePercentage; ?>%">Late (<?php echo $latePercentage; ?>% of Present)</div>
                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card summary-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie mr-2"></i>Attendance Distribution</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Calculate overall attendance rate from summary data
                                $overallAttendanceRate = 0;
                                $absenteeismRate = 0;

                                try {
                                    // Get overall attendance statistics from summary table
                                    $stmt = $conn->query("
                                        SELECT
                                            SUM(total_present) as total_present_days,
                                            SUM(total_present + total_absent) as total_working_days
                                        FROM attendance_summary
                                        WHERE month = MONTH(CURRENT_DATE()) AND year = YEAR(CURRENT_DATE())
                                    ");
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                                    if ($result['total_working_days'] > 0) {
                                        $overallAttendanceRate = round(($result['total_present_days'] / $result['total_working_days']) * 100);
                                        $absenteeismRate = 100 - $overallAttendanceRate;
                                    }
                                } catch (PDOException $e) {
                                    error_log("Error calculating attendance rate: " . $e->getMessage());
                                }
                                ?>

                                <div class="row text-center mb-4">
                                    <div class="col-6">
                                        <h4 class="text-success"><?php echo $overallAttendanceRate; ?>%</h4>
                                        <small class="text-muted">Overall Attendance Rate</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-danger"><?php echo $absenteeismRate; ?>%</h4>
                                        <small class="text-muted">Absenteeism Rate</small>
                                    </div>
                                </div>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-success" style="width: <?php echo $overallAttendanceRate; ?>%">Attendance Rate</div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-danger" style="width: <?php echo $absenteeismRate; ?>%">Absenteeism Rate</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card summary-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Attendance Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <strong>Note:</strong> Ensure to monitor attendance for payroll processing.
                                </div>
                                <div class="alert alert-warning">
                                    <strong>Warning:</strong> Follow up with absent employees as needed.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance by Department -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card summary-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-building mr-2"></i>Attendance by Department (This Month)</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $deptAttendance = [];
                                try {
                                    $stmt = $conn->query("
                                        SELECT d.department_name,
                                            COUNT(DISTINCT eh.employee_id) as total_emp,
                                            COALESCE(SUM(asum.total_present), 0) as total_present,
                                            COALESCE(SUM(asum.total_absent), 0) as total_absent,
                                            COALESCE(SUM(asum.total_late), 0) as total_late,
                                            COALESCE(SUM(asum.total_leave), 0) as total_leave
                                        FROM departments d
                                        LEFT JOIN employment_history eh ON eh.department_id = d.department_id
                                            AND eh.history_id = (SELECT MAX(history_id) FROM employment_history e2 WHERE e2.employee_id = eh.employee_id)
                                            AND (eh.employment_status = 'Active' OR eh.employment_status IS NULL)
                                        LEFT JOIN attendance_summary asum ON eh.employee_id = asum.employee_id
                                            AND asum.month = MONTH(CURRENT_DATE()) AND asum.year = YEAR(CURRENT_DATE())
                                        GROUP BY d.department_id, d.department_name
                                        ORDER BY total_emp DESC
                                    ");
                                    $deptAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) {
                                    error_log("Error fetching dept attendance: " . $e->getMessage());
                                }
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Department</th>
                                                <th class="text-center">Employees</th>
                                                <th class="text-center">Present</th>
                                                <th class="text-center">Absent</th>
                                                <th class="text-center">Late Days</th>
                                                <th class="text-center">On Leave</th>
                                                <th class="text-center">Att. Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($deptAttendance as $row): 
                                                $tot = ($row['total_present'] ?? 0) + ($row['total_absent'] ?? 0);
                                                $rate = $tot > 0 ? round((($row['total_present'] ?? 0) / $tot) * 100) : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                                <td class="text-center"><?php echo (int)($row['total_emp'] ?? 0); ?></td>
                                                <td class="text-center text-success"><?php echo (int)($row['total_present'] ?? 0); ?></td>
                                                <td class="text-center text-danger"><?php echo (int)($row['total_absent'] ?? 0); ?></td>
                                                <td class="text-center text-warning"><?php echo (int)($row['total_late'] ?? 0); ?></td>
                                                <td class="text-center text-info"><?php echo (int)($row['total_leave'] ?? 0); ?></td>
                                                <td class="text-center"><strong><?php echo $rate; ?>%</strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($deptAttendance)): ?>
                                            <tr><td colspan="7" class="text-center text-muted">No department data available.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overtime & Working Hours / Late Arrivals / Status Breakdown -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card summary-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-clock mr-2"></i>Monthly Hours Summary</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $hoursSummary = ['working' => 0, 'overtime' => 0];
                                try {
                                    $stmt = $conn->query("
                                        SELECT COALESCE(SUM(total_working_hours), 0) as total_working,
                                               COALESCE(SUM(total_overtime_hours), 0) as total_overtime
                                        FROM attendance_summary
                                        WHERE month = MONTH(CURRENT_DATE()) AND year = YEAR(CURRENT_DATE())
                                    ");
                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $hoursSummary['working'] = $row['total_working'] ?? 0;
                                    $hoursSummary['overtime'] = $row['total_overtime'] ?? 0;
                                } catch (PDOException $e) { error_log($e->getMessage()); }
                                ?>
                                <div class="text-center">
                                    <h4 class="text-primary"><?php echo number_format($hoursSummary['working'], 1); ?></h4>
                                    <small class="text-muted">Total Working Hours</small>
                                </div>
                                <hr>
                                <div class="text-center">
                                    <h4 class="text-info"><?php echo number_format($hoursSummary['overtime'], 1); ?></h4>
                                    <small class="text-muted">Total Overtime Hours</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card summary-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle mr-2"></i>Top Late Arrivals (This Month)</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php
                                $topLate = [];
                                try {
                                    $stmt = $conn->query("
                                        SELECT CONCAT(pi.first_name, ' ', pi.last_name) as name, asum.total_late
                                        FROM attendance_summary asum
                                        JOIN employee_profiles ep ON asum.employee_id = ep.employee_id
                                        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                                        WHERE asum.month = MONTH(CURRENT_DATE()) AND asum.year = YEAR(CURRENT_DATE())
                                        AND asum.total_late > 0
                                        ORDER BY asum.total_late DESC
                                        LIMIT 5
                                    ");
                                    $topLate = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) { error_log($e->getMessage()); }
                                ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($topLate as $r): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($r['name'] ?? 'N/A'); ?>
                                        <span class="badge badge-warning"><?php echo (int)($r['total_late'] ?? 0); ?> late</span>
                                    </li>
                                    <?php endforeach; ?>
                                    <?php if (empty($topLate)): ?>
                                    <li class="list-group-item text-muted">No late arrivals this month.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card summary-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Status Breakdown (This Month)</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $statusBreakdown = [];
                                try {
                                    $stmt = $conn->query("
                                        SELECT status, COUNT(*) as cnt
                                        FROM attendance
                                        WHERE MONTH(attendance_date) = MONTH(CURRENT_DATE()) AND YEAR(attendance_date) = YEAR(CURRENT_DATE())
                                        GROUP BY status
                                    ");
                                    $statusBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) { error_log($e->getMessage()); }
                                $statusLabels = ['Present' => 'success', 'Absent' => 'danger', 'Late' => 'warning', 'Half Day' => 'info', 'On Leave' => 'secondary'];
                                ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($statusBreakdown as $s): 
                                        $badge = $statusLabels[$s['status']] ?? 'secondary';
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($s['status']); ?>
                                        <span class="badge badge-<?php echo $badge; ?>"><?php echo (int)$s['cnt']; ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                    <?php if (empty($statusBreakdown)): ?>
                                    <li class="list-group-item text-muted">No attendance records this month.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trend -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card summary-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-line mr-2"></i>Attendance Trend (Last 6 Months)</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $monthlyTrend = [];
                                try {
                                    $stmt = $conn->query("
                                        SELECT year, month,
                                            SUM(total_present) as present,
                                            SUM(total_absent) as absent,
                                            SUM(total_present + total_absent) as total
                                        FROM attendance_summary
                                        GROUP BY year, month
                                        ORDER BY year DESC, month DESC
                                        LIMIT 6
                                    ");
                                    $monthlyTrend = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
                                } catch (PDOException $e) {
                                    error_log("Error fetching monthly trend: " . $e->getMessage());
                                }
                                $monthNames = ['', 'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Month</th>
                                                <th class="text-center">Present</th>
                                                <th class="text-center">Absent</th>
                                                <th class="text-center">Total Days</th>
                                                <th class="text-center">Attendance Rate</th>
                                                <th>Trend</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($monthlyTrend as $m): 
                                                $tot = (int)($m['total'] ?? 0);
                                                $rate = $tot > 0 ? round((($m['present'] ?? 0) / $tot) * 100) : 0;
                                                $barW = min(100, max(0, $rate));
                                            ?>
                                            <tr>
                                                <td><?php echo $monthNames[(int)($m['month'] ?? 0)] . ' ' . ($m['year'] ?? ''); ?></td>
                                                <td class="text-center"><?php echo (int)($m['present'] ?? 0); ?></td>
                                                <td class="text-center"><?php echo (int)($m['absent'] ?? 0); ?></td>
                                                <td class="text-center"><?php echo $tot; ?></td>
                                                <td class="text-center"><strong><?php echo $rate; ?>%</strong></td>
                                                <td>
                                                    <div class="progress" style="height: 8px; min-width: 80px;">
                                                        <div class="progress-bar bg-success" style="width: <?php echo $barW; ?>%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($monthlyTrend)): ?>
                                            <tr><td colspan="6" class="text-center text-muted">No trend data available.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
