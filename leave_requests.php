<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';

// Fetch leave requests from the database
function getLeaveRequests() {
    global $conn;
    $sql = "SELECT lr.*, et.leave_type_name, CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
            FROM leave_requests lr
            JOIN leave_types et ON lr.leave_type_id = et.leave_type_id
            JOIN employee_profiles ep ON lr.employee_id = ep.employee_id
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            ORDER BY lr.created_at DESC";
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approveRequest'])) {
        $requestId = $_POST['requestId'];
        $sql = "UPDATE leave_requests SET status = 'Approved' WHERE leave_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$requestId]);
        // Redirect to prevent form resubmission
        header("Location: leave_requests.php");
        exit;
    } elseif (isset($_POST['rejectRequest'])) {
        $requestId = $_POST['requestId'];
        $sql = "UPDATE leave_requests SET status = 'Rejected' WHERE leave_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$requestId]);
        // Redirect to prevent form resubmission
        header("Location: leave_requests.php");
        exit;
    } elseif (isset($_POST['submitLeaveRequest'])) {
        // Handle new leave request submission
        $employeeId = $_POST['employeeId'];
        $leaveTypeId = $_POST['leaveTypeId'];
        $startDate = $_POST['startDate'];
        $endDate = $_POST['endDate'];
        $reason = $_POST['reason'];

        // Calculate duration in days
        $duration = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1;

        try {
            $sql = "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, reason, status, applied_on)
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$employeeId, $leaveTypeId, $startDate, $endDate, $duration, $reason]);
            // Redirect to refresh the page
            header("Location: leave_requests.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error submitting leave request: " . $e->getMessage();
        }
    }
}

$leaveRequests = getLeaveRequests();
$leaveTypes = getLeaveTypes();
$employees = getEmployees();

// Calculate statistics
$totalRequests = count($leaveRequests);
$approvedRequests = 0;
$pendingRequests = 0;
$rejectedRequests = 0;

foreach ($leaveRequests as $request) {
    switch ($request['status']) {
        case 'Approved':
            $approvedRequests++;
            break;
        case 'Pending':
            $pendingRequests++;
            break;
        case 'Rejected':
            $rejectedRequests++;
            break;
    }
}

$approvedPercentage = $totalRequests > 0 ? ($approvedRequests / $totalRequests) * 100 : 0;
$pendingPercentage = $totalRequests > 0 ? ($pendingRequests / $totalRequests) * 100 : 0;
$rejectedPercentage = $totalRequests > 0 ? ($rejectedRequests / $totalRequests) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .request-card {
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
        }
        
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-medium);
        }
        
        .status-badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-light);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 2px solid white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Leave Requests Management</h2>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-paper-plane mr-2"></i>Leave Requests</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Leave Type</th>
                                                <th>Dates</th>
                                                <th>Duration</th>
                                                <th>Reason</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($leaveRequests as $request): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                                                <td><?php echo htmlspecialchars($request['leave_type_name']); ?></td>
                                                <td><?php echo htmlspecialchars($request['start_date']) . ' - ' . htmlspecialchars($request['end_date']); ?></td>
                                                <td><?php echo htmlspecialchars($request['total_days']); ?></td>
                                                <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                                <td><span class="status-badge badge-<?php echo strtolower($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                                <td>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="requestId" value="<?php echo $request['leave_id']; ?>">
                                                        <button type="submit" name="approveRequest" class="btn btn-sm btn-outline-success mr-2">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="requestId" value="<?php echo $request['leave_id']; ?>">
                                                        <button type="submit" name="rejectRequest" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie mr-2"></i>Request Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-4">
                                    <div class="col-3">
                                        <div class="stats-box">
                                            <h3 class="text-primary"><?php echo $totalRequests; ?></h3>
                                            <small class="text-muted">Total Requests</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="stats-box">
                                            <h3 class="text-success"><?php echo $approvedRequests; ?></h3>
                                            <small class="text-muted">Approved</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="stats-box">
                                            <h3 class="text-warning"><?php echo $pendingRequests; ?></h3>
                                            <small class="text-muted">Pending</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="stats-box">
                                            <h3 class="text-danger"><?php echo $rejectedRequests; ?></h3>
                                            <small class="text-muted">Rejected</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-success" style="width: <?php echo $approvedPercentage; ?>%">Approved (<?php echo round($approvedPercentage); ?>%)</div>
                                </div>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $pendingPercentage; ?>%">Pending (<?php echo round($pendingPercentage); ?>%)</div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-danger" style="width: <?php echo $rejectedPercentage; ?>%">Rejected (<?php echo round($rejectedPercentage); ?>%)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history mr-2"></i>Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <small class="text-muted">2 hours ago</small>
                                        <p class="mb-0">John Doe submitted vacation request</p>
                                    </div>
                                    <div class="timeline-item">
                                        <small class="text-muted">4 hours ago</small>
                                        <p class="mb-0">Jane Smith's sick leave was approved</p>
                                    </div>
                                    <div class="timeline-item">
                                        <small class="text-muted">6 hours ago</small>
                                        <p class="mb-0">Mike Johnson's emergency leave was rejected</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Request Modal -->
    <div class="modal fade" id="newRequestModal" tabindex="-1" role="dialog" aria-labelledby="newRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newRequestModalLabel">New Leave Request</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="leave_requests.php">
                        <div class="form-group">
                            <label for="employeeId">Employee</label>
                            <select class="form-control" id="employeeId" name="employeeId" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="leaveType">Leave Type</label>
                            <select class="form-control" id="leaveType" name="leaveTypeId" required>
                                <option value="">Select leave type</option>
                                <?php foreach ($leaveTypes as $leaveType): ?>
                                    <option value="<?php echo $leaveType['leave_type_id']; ?>"><?php echo htmlspecialchars($leaveType['leave_type_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="startDate">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="endDate">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reason">Reason</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Enter reason for leave" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" name="submitLeaveRequest" class="btn btn-primary">Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
