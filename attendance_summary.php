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
                
                <!-- Compliance Information -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-info-circle mr-2"></i>Applicable Philippine Laws & Data Privacy Notice</h5>
                            <hr>
                            <strong>Philippine Republic Acts:</strong>
                            <ul class="mb-2">
                                <li><strong>RA 6727</strong> - Wage Order: 8-hour work day baseline (08:00 AM). On-time vs. Late tracking for wage compliance and overtime calculation.</li>
                                <li><strong>RA 10173</strong> - Data Privacy Act: <strong>Attendance data is PERSONAL INFORMATION</strong></li>
                            </ul>
                            <strong>Data Privacy Notice:</strong>
                            <ul class="mb-2">
                                <li>Attendance summary shows aggregate employee attendance patterns - access restricted to authorized HR/supervisory personnel</li>
                                <li>Individual employee attendance percentages are confidential - cannot be shared publicly</li>
                                <li>Late arrival information is protected personal data subject to confidentiality requirements</li>
                                <li>All access to this summary is logged and audited for security and compliance</li>
                                <li>Use attendance data only for legitimate HR purposes (payroll, compliance, performance management)</li>
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                <h2 class="section-title">Attendance Summary</h2>
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
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
