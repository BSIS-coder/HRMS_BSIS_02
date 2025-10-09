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
                                <button class="btn btn-primary" data-toggle="modal" data-target="#addAttendanceModal">
                                    <i class="fas fa-plus mr-2"></i>Add Attendance
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Date</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Hours Worked</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            try {
                                                // Get employees with their attendance data
                                                $stmt = $conn->query("
                                                    SELECT 
                                                        ep.employee_id,
                                                        pi.first_name,
                                                        pi.last_name,
                                                        ep.employee_number,
                                                        jr.department,
                                                        a.attendance_date,
                                                        a.clock_in,
                                                        a.clock_out,
                                                        a.working_hours,
                                                        a.status
                                                    FROM employee_profiles ep
                                                    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                                                    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                                                    LEFT JOIN attendance a ON ep.employee_id = a.employee_id 
                                                        AND a.attendance_date = CURDATE()
                                                    WHERE ep.employment_status IN ('Full-time', 'Part-time')
                                                    ORDER BY pi.first_name, pi.last_name
                                                    LIMIT 10
                                                ");
                                                $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                if (empty($employees)) {
                                                    echo '<tr><td colspan="7" class="text-center">No employee records found.</td></tr>';
                                                } else {
                                                    foreach ($employees as $employee) {
                                                        $fullName = htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']);
                                                        $department = htmlspecialchars($employee['department'] ?? 'N/A');
                                                        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($fullName) . "&background=E91E63&color=fff&size=35";
                                                        
                                                        // Determine status and styling
                                                        $status = $employee['status'] ?? 'Not Recorded';
                                                        $statusClass = 'badge-secondary';
                                                        $clockIn = $employee['clock_in'] ? date('h:i A', strtotime($employee['clock_in'])) : '-';
                                                        $clockOut = $employee['clock_out'] ? date('h:i A', strtotime($employee['clock_out'])) : '-';
                                                        $hours = $employee['working_hours'] ? $employee['working_hours'] . ' hours' : '0 hours';
                                                        
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
                                                            <td>{$clockIn}</td>
                                                            <td>{$clockOut}</td>
                                                            <td>{$hours}</td>
                                                            <td><span class='attendance-status badge {$statusClass}'>{$status}</span></td>
                                                            <td>
                                                                <button class='btn btn-sm btn-outline-primary mr-2'>
                                                                    <i class='fas fa-edit'></i>
                                                                </button>
                                                                <button class='btn btn-sm btn-outline-danger'>
                                                                    <i class='fas fa-trash'></i>
                                                                </button>
                                                            </td>
                                                        </tr>";
                                                    }
                                                }
                                            } catch (PDOException $e) {
                                                echo '<tr><td colspan="7" class="text-center text-danger">Error loading attendance data: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                                            }
                                            ?>
                                        </tbody>
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
                                    // Get total employees
                                    $stmt = $conn->query("SELECT COUNT(*) as count FROM employee_profiles WHERE employment_status IN ('Full-time', 'Part-time')");
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $totalEmployees = $result['count'];

                                    // Get today's attendance statistics
                                    $today = date('Y-m-d');
                                    $stmt = $conn->query("SELECT
                                        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                                        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                                        FROM attendance WHERE attendance_date = '$today'");
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    $totalPresent = $result['present_count'] ?? 0;
                                    $totalAbsent = $result['absent_count'] ?? 0;

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
                    <form>
                        <div class="form-group">
                            <label for="employeeName">Employee Name</label>
                            <input type="text" class="form-control" id="employeeName" placeholder="Enter employee name">
                        </div>
                        <div class="form-group">
                            <label for="attendanceDate">Date</label>
                            <input type="date" class="form-control" id="attendanceDate">
                        </div>
                        <div class="form-group">
                            <label for="checkInTime">Check In Time</label>
                            <input type="time" class="form-control" id="checkInTime">
                        </div>
                        <div class="form-group">
                            <label for="checkOutTime">Check Out Time</label>
                            <input type="time" class="form-control" id="checkOutTime">
                        </div>
                        <div class="form-group">
                            <label for="attendanceStatus">Status</label>
                            <select class="form-control" id="attendanceStatus">
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                                <option value="early">Early Departure</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">Save Attendance</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
