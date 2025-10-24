<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';
// Include employee status functions
require_once 'employee_status_functions.php';

// Get current user ID
$user_id = $_SESSION['user_id'];

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
            
            // Get employee_id for this leave request
            $empStmt = $conn->prepare("SELECT employee_id FROM leave_requests WHERE leave_id = ?");
            $empStmt->execute([$requestId]);
            $employee_id = $empStmt->fetchColumn();
            
            $sql = "UPDATE leave_requests SET status = 'Approved' WHERE leave_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$requestId]);
            
            // Update employee status based on leave approval
            if ($employee_id) {
                handleLeaveStatusChange($employee_id, 'Approved');
            }

            // Update shift status based on leave approval
            require_once 'shift_status_functions.php';
            updateShiftStatusOnLeaveApproval($requestId);
            
            error_log("Leave requests: About to log approve for request $requestId");
            error_log("Logging activity: Leave request #$requestId approved by user ID $user_id");
            logActivity("Leave request #$requestId approved by user ID $user_id", "leave_requests", $requestId);
            // Redirect to prevent form resubmission
            header("Location: leave_requests.php");
            exit;
        } elseif (isset($_POST['rejectRequest'])) {
            $requestId = $_POST['requestId'];
            
            // Get employee_id for this leave request
            $empStmt = $conn->prepare("SELECT employee_id FROM leave_requests WHERE leave_id = ?");
            $empStmt->execute([$requestId]);
            $employee_id = $empStmt->fetchColumn();
            
            $sql = "UPDATE leave_requests SET status = 'Rejected' WHERE leave_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$requestId]);
            
            // Update employee status based on leave rejection
            if ($employee_id) {
                handleLeaveStatusChange($employee_id, 'Rejected');
            }
            
            error_log("Leave requests: About to log reject for request $requestId");
            error_log("Logging activity: Leave request #$requestId rejected by user ID $user_id");
            logActivity("Leave request #$requestId rejected by user ID $user_id", "leave_requests", $requestId);
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

        $documentPath = null;
        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
            $fileName = $_FILES['document']['name'];
            $fileTmpName = $_FILES['document']['tmp_name'];
            $fileSize = $_FILES['document']['size'];
            $fileType = $_FILES['document']['type'];

            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (in_array($fileType, $allowedTypes) && $fileSize < 5000000) {
                $newFileName = uniqid() . '_' . $fileName;
                $uploadPath = 'uploads/leave_documents/' . $newFileName;
                if (move_uploaded_file($fileTmpName, $uploadPath)) {
                    $documentPath = $uploadPath;
                }
            }
        }

        try {
            $sql = "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, reason, document_path, status, applied_on)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$employeeId, $leaveTypeId, $startDate, $endDate, $duration, $reason, $documentPath]);
            error_log("Logging activity: New leave request submitted by employee ID $employeeId");
            logActivity("New leave request submitted by employee ID $employeeId", "leave_requests");
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
                                                <th>Document</th>
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
<<<<<<< HEAD
                                                <td><?php if ($request['document_path']): ?><a href="#" class="btn btn-sm btn-info view-document" data-path="<?php echo htmlspecialchars($request['document_path']); ?>" data-type="<?php echo strtolower(pathinfo($request['document_path'], PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'image'; ?>"><i class="fas fa-eye mr-1"></i>View</a><?php endif; ?></td>
=======
                                                <td><?php if ($request['document_path']): ?>
                                                    <button class="btn btn-sm btn-outline-primary mr-1" onclick="viewDocument('<?php echo htmlspecialchars($request['document_path']); ?>', '<?php echo htmlspecialchars($request['employee_name']); ?>', '<?php echo htmlspecialchars($request['leave_type_name']); ?>', '<?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?>')"><i class="fas fa-eye"></i> View</button>
                                                    <a href="<?php echo htmlspecialchars($request['document_path']); ?>" download class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i> Download</a>
                                                <?php endif; ?></td>
>>>>>>> 48f0fd909401d87fc7e82ce488dfd81cd0dfc6fa
                                                <td>
                                                    <?php if ($request['status'] == 'Pending'): ?>
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
                                                    <?php endif; ?>
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
                                <div class="timeline" id="recent-activity-timeline">
                                    <?php
                                    $recentActivities = getRecentAuditLogs(5);
                                    foreach ($recentActivities as $activity) {
                                        $timeAgo = humanTiming(strtotime($activity['created_at']));
                                        $username = htmlspecialchars($activity['username'] ?? 'Unknown User');
                                        $action = htmlspecialchars($activity['action']);
                                        echo '<div class="timeline-item">';
                                        echo "<small class=\"text-muted\">$timeAgo ago</small>";
                                        echo "<p class=\"mb-0\">$username: $action</p>";
                                        echo '</div>';
                                    }

                                    function humanTiming($time)
                                    {
                                        $time = time() - $time; // to get the time since that moment
                                        $time = ($time < 1) ? 1 : $time;
                                        $tokens = array(
                                            31536000 => 'year',
                                            2592000 => 'month',
                                            604800 => 'week',
                                            86400 => 'day',
                                            3600 => 'hour',
                                            60 => 'minute',
                                            1 => 'second'
                                        );
                                        foreach ($tokens as $unit => $text) {
                                            if ($time < $unit) continue;
                                            $numberOfUnits = floor($time / $unit);
                                            return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '');
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Viewer Modal -->
    <div id="documentViewerModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="documentViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentViewerModalLabel">Document Viewer</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="documentViewerContent">
                        <!-- Document content will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <a id="downloadDocumentBtn" href="#" target="_blank" class="btn btn-primary">Download Document</a>
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
                    <form method="POST" action="leave_requests.php" enctype="multipart/form-data">
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
                        <div class="form-group">
                            <label for="document">Document (optional)</label>
                            <input type="file" class="form-control" id="document" name="document" accept=".pdf,.jpg,.png">
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

    <!-- Document Viewer Modal -->
    <div class="modal fade" id="documentViewerModal" tabindex="-1" role="dialog" aria-labelledby="documentViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentViewerModalLabel">Document Viewer</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="documentViewerContent">
                        <!-- Document content will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        function fetchRecentActivity() {
            $.ajax({
                url: 'fetch_recent_activity.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    var timeline = $('#recent-activity-timeline');
                    timeline.empty();
                    data.forEach(function(activity) {
                        var timeAgo = activity.time_ago;
                        var username = $('<div>').text(activity.username).html() || 'Unknown User';
                        var action = $('<div>').text(activity.action).html();
                        var item = $('<div>').addClass('timeline-item');
                        item.append('<small class="text-muted">' + timeAgo + ' ago</small>');
                        item.append('<p class="mb-0">' + username + ': ' + action + '</p>');
                        timeline.append(item);
                    });
                },
                error: function() {
                    console.error('Failed to fetch recent activity');
                }
            });
        }

        function viewDocument(documentPath, employeeName, leaveType, dates) {
            var fileExtension = documentPath.split('.').pop().toLowerCase();
            var content = '';

            // Update modal title
            $('#documentViewerModalLabel').text('Document Viewer - ' + employeeName + ' (' + leaveType + ' - ' + dates + ')');

            // Set download link
            $('#downloadDocumentBtn').attr('href', documentPath);

            if (fileExtension === 'pdf') {
                content = '<iframe src="view_document.php?file=' + encodeURIComponent(documentPath) + '" width="100%" height="600px" style="border: none;"></iframe>';
            } else if (fileExtension === 'jpg' || fileExtension === 'jpeg' || fileExtension === 'png') {
                content = '<img src="view_document.php?file=' + encodeURIComponent(documentPath) + '" class="img-fluid" alt="Document Image" style="max-width: 100%; max-height: 600px;">';
            } else {
                content = '<div class="alert alert-warning">Unsupported file type. <a href="' + documentPath + '" target="_blank">Click here to download and view the file</a></div>';
            }

            $('#documentViewerContent').html(content);
            $('#documentViewerModal').modal('show');
        }

        $(document).ready(function() {
            fetchRecentActivity();
            setInterval(fetchRecentActivity, 30000); // Refresh every 30 seconds

            // Hide action buttons immediately after clicking to prevent multiple submissions
            $('button[name="approveRequest"], button[name="rejectRequest"]').on('click', function() {
                $(this).closest('td').find('button').hide();
            });

            // Document viewer functionality
            $('.view-document').on('click', function(e) {
                e.preventDefault();
                var documentPath = $(this).data('path');
                var documentType = $(this).data('type');

                $('#documentViewerContent').empty();

                if (documentType === 'pdf') {
                    $('#documentViewerContent').html('<iframe src="' + documentPath + '" width="100%" height="600px" style="border: none;"></iframe>');
                } else if (documentType === 'image') {
                    $('#documentViewerContent').html('<img src="' + documentPath + '" class="img-fluid" alt="Document">');
                }

                $('#documentViewerModal').modal('show');
            });
        });
    </script>
</body>
</html>
