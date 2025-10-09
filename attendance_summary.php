<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
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
                    // Get total employees
                    $stmt = $conn->query("SELECT COUNT(*) as count FROM employee_profiles WHERE employment_status IN ('Full-time', 'Part-time')");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $totalEmployees = $result['count'];

                    // Get today's attendance summary (if available)
                    $today = date('Y-m-d');
                    $stmt = $conn->query("SELECT COUNT(*) as present FROM attendance WHERE attendance_date = '$today' AND status = 'Present'");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $totalPresent = $result['present'];

                    $totalAbsent = $totalEmployees - $totalPresent;

                } catch (PDOException $e) {
                    error_log("Error fetching attendance stats: " . $e->getMessage());
                }

                $presentPercentage = $totalEmployees > 0 ? round(($totalPresent / $totalEmployees) * 100) : 0;
                $absentPercentage = 100 - $presentPercentage;
                ?>

                <div class="row text-center mb-4">
                    <div class="col-4">
                        <h4 class="text-primary"><?php echo $totalEmployees; ?></h4>
                        <small class="text-muted">Total Employees</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-success"><?php echo $totalPresent; ?></h4>
                        <small class="text-muted">Present Today</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-danger"><?php echo $totalAbsent; ?></h4>
                        <small class="text-muted">Absent Today</small>
                    </div>
                </div>
                <div class="progress mb-2">
                    <div class="progress-bar bg-success" style="width: <?php echo $presentPercentage; ?>%">Present (<?php echo $presentPercentage; ?>%)</div>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-danger" style="width: <?php echo $absentPercentage; ?>%">Absent (<?php echo $absentPercentage; ?>%)</div>
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
