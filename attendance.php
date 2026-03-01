<?php
/**
 * ATTENDANCE MANAGEMENT PAGE
 * 
 * Applicable Philippine Republic Acts:
 * - RA 6727 (Implementing Rules and Regulations of the Wage Order)
 *   - Covers mandatory 8-hour work day compliance
 *   - Overtime regulations and compensation
 *   - Standard working hours (08:00 AM start time as implemented)
 * 
 * - RA 8799 (Securities Regulation Code of the Philippines)
 *   - Section 117.2: Employee benefits provisions
 *   - Work-related benefits and compensation
 * 
 * - RA 10173 (Data Privacy Act of 2012) - APPLIES TO ALL PAGES
 *   - This page processes employee Personal Information (names, attendance times)
 *   - Data must be collected only for lawful purposes (attendance tracking)
 *   - Employee consent required or lawful basis must exist
 *   - Implement security controls to protect attendance data
 *   - Only authorized HR/admin personnel should access this data
 *   - Employees have right to access and correct their attendance records
 *   - Report any data breaches to NPC (National Privacy Commission)
 * 
 * Compliance Note: Late minutes are tracked to ensure accurate overtime
 * calculation and adherence to wage order requirements.
 * All attendance data is personal information and must be handled
 * in accordance with RA 10173 data privacy obligations.
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
    <title>Attendance - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .attendance-card {
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
        }
        
        .attendance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-medium);
        }
        
        .attendance-status {
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Attendance Management</h2>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-calendar-check mr-2"></i>Attendance Overview</h5>
                                <button class="btn btn-outline-primary btn-sm" id="refreshBtn">
                                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Date</th>
                                                <th>Hours Worked</th>
                                                <th>Overtime Hours</th>
                                                <th>Late Minutes</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="attendanceTableBody">
                                            <!-- Attendance data will be loaded here via AJAX -->
                                        </tbody>
                                        <script>
                                            // Update times when data is loaded
                                            function loadAttendanceData() {
                                                console.log('Loading attendance data...');
                                                $.ajax({
                                                    url: 'fetch_attendance_overview.php',
                                                    type: 'GET',
                                                    success: function(data) {
                                                        console.log('Attendance data loaded successfully:', data);
                                                        $('#attendanceTableBody').html(data);
                                                    },
                                                    error: function(xhr, status, error) {
                                                        console.error('AJAX Error:', {status: xhr.status, error: error});
                                                        var errorMsg = 'Error loading attendance data.';
                                                        if (xhr.status === 401) {
                                                            errorMsg = 'Unauthorized access. Please log in as admin/HR.';
                                                        } else if (xhr.status === 500) {
                                                            errorMsg = 'Server error. Check PHP logs.';
                                                        }
                                                        $('#attendanceTableBody').html('<tr><td colspan="6" class="text-center text-danger">' + errorMsg + '</td></tr>');
                                                    }
                                                });
                                            }
                                        </script>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Attendance Statistics</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get attendance statistics
                                $totalEmployees = 0;
                                $totalPresent = 0;
                                $totalAbsent = 0;

                                try {
                                    // Get total employees matching the attendance overview filter
                                    // Use LEFT JOIN instead of subquery to be less restrictive
                                    $stmt = $conn->query("SELECT COUNT(DISTINCT ep.employee_id) as count
                                        FROM employee_profiles ep
                                        LEFT JOIN (
                                            SELECT employee_id, MAX(history_id) as max_history_id
                                            FROM employment_history
                                            GROUP BY employee_id
                                        ) eh_max ON ep.employee_id = eh_max.employee_id
                                        LEFT JOIN employment_history eh ON eh_max.employee_id = eh.employee_id
                                            AND eh_max.max_history_id = eh.history_id
                                        WHERE (eh.employment_status = 'Active' OR eh.employment_status IS NULL)");
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $totalEmployees = $result['count'] ?? 0;

                                    // If no employees found, try simpler query
                                    if ($totalEmployees == 0) {
                                        $stmt = $conn->query("SELECT COUNT(*) as count FROM employee_profiles");
                                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                        $totalEmployees = $result['count'] ?? 0;
                                    }

                                    // Get today's attendance statistics for the same filtered employees
                                    $today = date('Y-m-d');
                                    $stmt = $conn->query("SELECT
                                        SUM(CASE WHEN (a.status = 'Present' OR (a.status IS NULL AND a.clock_in IS NOT NULL)) THEN 1 ELSE 0 END) as present_count,
                                        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                                        FROM employee_profiles ep
                                        LEFT JOIN attendance a ON ep.employee_id = a.employee_id AND a.attendance_date = '$today'
                                        LEFT JOIN (
                                            SELECT employee_id, MAX(history_id) as max_history_id
                                            FROM employment_history
                                            GROUP BY employee_id
                                        ) eh_max ON ep.employee_id = eh_max.employee_id
                                        LEFT JOIN employment_history eh ON eh_max.employee_id = eh.employee_id
                                            AND eh_max.max_history_id = eh.history_id
                                        WHERE (eh.employment_status = 'Active' OR eh.employment_status IS NULL)");
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $totalPresent = $result['present_count'] ?? 0;
                                    $totalAbsent = $result['absent_count'] ?? 0;

                                    // If no results, try simpler query
                                    if ($totalPresent == 0 && $totalAbsent == 0 && $totalEmployees > 0) {
                                        $stmt = $conn->query("SELECT
                                            SUM(CASE WHEN (a.status = 'Present' OR (a.status IS NULL AND a.clock_in IS NOT NULL)) THEN 1 ELSE 0 END) as present_count,
                                            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                                            FROM employee_profiles ep
                                            LEFT JOIN attendance a ON ep.employee_id = a.employee_id AND a.attendance_date = '$today'");
                                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                        $totalPresent = $result['present_count'] ?? 0;
                                        $totalAbsent = $result['absent_count'] ?? 0;
                                    }

                                    error_log("Attendance stats: Total employees $totalEmployees, Present $totalPresent, Absent $totalAbsent");

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
                                        <small class="text-muted">Present</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-danger"><?php echo $totalAbsent; ?></h4>
                                        <small class="text-muted">Absent</small>
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
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Attendance Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <strong>Note:</strong> Ensure to record attendance accurately for payroll processing.
                                </div>
                                <div class="alert alert-warning">
                                    <strong>Warning:</strong> Absent employees may require follow-up.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Attendance Modal -->
    <div class="modal fade" id="addAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="addAttendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAttendanceModalLabel">Add New Attendance</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="attendanceForm">
                        <div class="form-group">
                            <label for="employeeName">Employee Name</label>
                            <input type="text" class="form-control" id="employeeName" name="employeeName" placeholder="Enter employee name" required>
                        </div>
                        <div class="form-group">
                            <label for="attendanceDate">Date</label>
                            <input type="date" class="form-control" id="attendanceDate" name="attendanceDate" required>
                        </div>
                        <div class="form-group">
                            <label for="attendanceStatus">Status</label>
                            <select class="form-control" id="attendanceStatus" name="attendanceStatus" required>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                                <option value="early">Early Departure</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="overtimeHours">Overtime Hours</label>
                            <input type="number" step="0.01" class="form-control" id="overtimeHours" name="overtimeHours" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="lateMinutes">Late Minutes</label>
                            <input type="number" step="0.01" class="form-control" id="lateMinutes" name="lateMinutes" placeholder="0.00">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveAttendanceBtn">Save Attendance</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Function to load attendance data
            function loadAttendanceData() {
                console.log('Loading attendance data...');
                $.ajax({
                    url: 'fetch_attendance_overview.php',
                    type: 'GET',
                    success: function(data) {
                        console.log('Attendance data loaded successfully:', data);
                        $('#attendanceTableBody').html(data);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {status: xhr.status, error: error});
                        var errorMsg = 'Error loading attendance data.';
                        if (xhr.status === 401) {
                            errorMsg = 'Unauthorized access. Please log in as admin/HR.';
                        } else if (xhr.status === 500) {
                            errorMsg = 'Server error. Check PHP logs.';
                        }
                        $('#attendanceTableBody').html('<tr><td colspan="6" class="text-center text-danger">' + errorMsg + '</td></tr>');
                    }
                });
            }

            // Load attendance data on page load
            loadAttendanceData();

            // Auto-refresh every 30 seconds
            setInterval(function() {
                console.log('Auto-refreshing attendance data...');
                loadAttendanceData();
            }, 30000);

            // Handle refresh button click
            $('#refreshBtn').on('click', function() {
                loadAttendanceData();
            });

            // Handle form submission
            $('#saveAttendanceBtn').on('click', function() {
                var formData = new FormData(document.getElementById('attendanceForm'));

                $.ajax({
                    url: 'save_attendance.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        var result = JSON.parse(response);
                        if (result.success) {
                            alert('Attendance saved successfully!');
                            $('#addAttendanceModal').modal('hide');
                            loadAttendanceData(); // Refresh the table data instead of reloading the page
                        } else {
                            alert('Error: ' + result.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Save attendance error:', {status: xhr.status, error: error});
                        alert('An error occurred while saving attendance.');
                    }
                });
            });
        });
    </script>
</body>
</html>
