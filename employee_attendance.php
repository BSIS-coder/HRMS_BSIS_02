<?php
session_start();

// Check if the user is logged in and is an employee, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';

// Get employee information
$employee_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clock_in'])) {
        // Clock in
        try {
            $today = date('Y-m-d');
            $now = date('H:i:s');
            $sql = "INSERT INTO attendance (employee_id, attendance_date, clock_in, status) VALUES (?, ?, ?, 'Present')
                    ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in), status = 'Present'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$employee_id, $today, $now]);
            $success = "Clocked in successfully at " . date('h:i A');
        } catch (PDOException $e) {
            $error = "Error clocking in: " . $e->getMessage();
        }
    } elseif (isset($_POST['clock_out'])) {
        // Clock out
        try {
            $today = date('Y-m-d');
            $now = date('H:i:s');
            $sql = "UPDATE attendance SET clock_out = ?, working_hours = TIMESTAMPDIFF(HOUR, clock_in, ?) WHERE employee_id = ? AND attendance_date = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$now, $now, $employee_id, $today]);
            $success = "Clocked out successfully at " . date('h:i A');
        } catch (PDOException $e) {
            $error = "Error clocking out: " . $e->getMessage();
        }
    }
    // Redirect to prevent form resubmission
    header("Location: employee_attendance.php");
    exit;
}

// Get today's attendance
function getTodayAttendance($employee_id) {
    global $conn;
    try {
        $today = date('Y-m-d');
        $sql = "SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id, $today]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
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

$todayAttendance = getTodayAttendance($employee_id);
$attendanceHistory = getAttendanceHistory($employee_id);

// Calculate attendance statistics
$totalDays = count($attendanceHistory);
$presentDays = 0;
$totalHours = 0;

foreach ($attendanceHistory as $record) {
    if ($record['status'] === 'Present') {
        $presentDays++;
    }
    if ($record['working_hours']) {
        $totalHours += $record['working_hours'];
    }
}

$attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;
$avgHours = $presentDays > 0 ? round($totalHours / $presentDays, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Employee Portal</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="employee_style.css">
    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }

        .clock-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .clock-time {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .clock-date {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .clock-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 0 10px;
        }

        .clock-btn:hover {
            background: white;
            color: #667eea;
            transform: translateY(-2px);
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .attendance-record {
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
            margin-bottom: 10px;
        }

        .attendance-record:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-medium);
        }

        .status-present {
            background-color: #d4edda;
            color: #155724;
        }

        .status-absent {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-late {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body class="employee-page">
    <div class="container-fluid">
        <?php include 'employee_navigation.php'; ?>
        <div class="row">
            <?php include 'employee_sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-clock mr-2"></i>Attendance Management</h2>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Clock In/Out Section -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="clock-card">
                            <div class="clock-time" id="current-time"><?php echo date('h:i:s A'); ?></div>
                            <div class="clock-date"><?php echo date('l, F j, Y'); ?></div>
                            <div class="mb-3">
                                <?php if ($todayAttendance && $todayAttendance['clock_in']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-sign-in-alt mr-2"></i>
                                        Clocked In: <?php echo date('h:i A', strtotime($todayAttendance['clock_in'])); ?>
                                    </div>
                                    <?php if ($todayAttendance['clock_out']): ?>
                                        <div>
                                            <i class="fas fa-sign-out-alt mr-2"></i>
                                            Clocked Out: <?php echo date('h:i A', strtotime($todayAttendance['clock_out'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <button type="submit" name="clock_out" class="clock-btn">
                                                <i class="fas fa-sign-out-alt mr-2"></i>Clock Out
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <button type="submit" name="clock_in" class="clock-btn">
                                            <i class="fas fa-sign-in-alt mr-2"></i>Clock In
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h5 class="text-center mb-3">Attendance Rate</h5>
                            <div class="stats-value text-center"><?php echo $attendanceRate; ?>%</div>
                            <small class="text-muted text-center d-block"><?php echo $presentDays; ?>/<?php echo $totalDays; ?> days</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h5 class="text-center mb-3">Average Hours</h5>
                            <div class="stats-value text-center"><?php echo $avgHours; ?>h</div>
                            <small class="text-muted text-center d-block">Per working day</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h5 class="text-center mb-3">Total Hours</h5>
                            <div class="stats-value text-center"><?php echo round($totalHours, 1); ?>h</div>
                            <small class="text-muted text-center d-block">This month</small>
                        </div>
                    </div>
                </div>

                <!-- Attendance History -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history mr-2"></i>Attendance History</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($attendanceHistory)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No attendance records found</h5>
                                        <p class="text-muted">Your attendance history will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Clock In</th>
                                                    <th>Clock Out</th>
                                                    <th>Hours Worked</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($attendanceHistory as $record): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($record['attendance_date']))); ?></td>
                                                    <td><?php echo $record['clock_in'] ? date('h:i A', strtotime($record['clock_in'])) : '-'; ?></td>
                                                    <td><?php echo $record['clock_out'] ? date('h:i A', strtotime($record['clock_out'])) : '-'; ?></td>
                                                    <td><?php echo $record['working_hours'] ? $record['working_hours'] . 'h' : '-'; ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo strtolower($record['status'] ?? 'secondary'); ?>">
                                                            <?php echo htmlspecialchars($record['status'] ?? 'Not Recorded'); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
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
    <script>
        // Update current time every second
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }

        setInterval(updateTime, 1000);
        updateTime(); // Initial call
    </script>
</body>
</html>
