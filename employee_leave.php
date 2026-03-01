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
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch employee_id from users table
try {
    $sql = "SELECT employee_id FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $employee_id = $stmt->fetchColumn();
    if (!$employee_id) {
        $error = "Employee profile not found. Please contact administrator.";
        $employee_id = null;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    $employee_id = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitLeaveRequest'])) {
    if (!$employee_id) {
        $error = "Cannot submit leave request without a valid employee profile.";
    } else {
        // Handle new leave request submission
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
                // Use absolute path and ensure directory exists
                $uploadDir = __DIR__ . '/uploads/leave_documents/';
                if (!file_exists($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        error_log("Failed to create upload directory: $uploadDir");
                        $error = "Failed to create upload directory. Please contact administrator.";
                    }
                }
                
                if (!isset($error)) {
                    $newFileName = uniqid() . '_' . $fileName;
                    $uploadPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($fileTmpName, $uploadPath)) {
                        // Store relative path for database
                        $documentPath = 'uploads/leave_documents/' . $newFileName;
                    } else {
                        error_log("Failed to move uploaded file: $fileName to $uploadPath");
                        $error = "Failed to upload document. Please try again.";
                    }
                }
            } elseif ($_FILES['document']['error'] != 0 && $_FILES['document']['error'] != UPLOAD_ERR_NO_FILE) {
                error_log("File upload error code: " . $_FILES['document']['error']);
                $error = "File upload failed. Please try again.";
            } elseif (!in_array($fileType, $allowedTypes) || $fileSize >= 5000000) {
                $error = "Invalid file type or file too large. Allowed types: PDF, JPEG, PNG. Max size: 5MB.";
            }
        } elseif (isset($_FILES['document']) && $_FILES['document']['error'] != UPLOAD_ERR_NO_FILE && $_FILES['document']['error'] != 0) {
            // Only show error if file was actually attempted to be uploaded
            error_log("File upload error code: " . $_FILES['document']['error']);
            $error = "File upload failed. Please try again.";
        }

        // Validate gender-based leave restrictions
        $genderValidation = validateLeaveRequestByGender($employee_id, $leaveTypeId);
        if (!$genderValidation['valid']) {
            $error = $genderValidation['message'];
        } else {
            try {
                $sql = "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, reason, document_path, status, applied_on)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$employee_id, $leaveTypeId, $startDate, $endDate, $duration, $reason, $documentPath]);
                $leave_id = $conn->lastInsertId();
                error_log("Employee leave: About to log activity for leave_id $leave_id");
                logActivity("Leave Request Submitted", "leave_requests", $leave_id, [
                    'leave_type_id' => $leaveTypeId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_days' => $duration,
                    'reason' => $reason
                ]);

                // If a document was uploaded, add it to document_management
                if ($documentPath) {
                    try {
                        $documentName = "Leave Request Document - " . date('M d, Y', strtotime($startDate)) . " to " . date('M d, Y', strtotime($endDate));
                        $docSql = "INSERT INTO document_management (employee_id, document_type, document_name, file_path, document_status, notes, created_at)
                                   VALUES (?, 'Leave Document', ?, ?, 'Active', ?, NOW())";
                        $docStmt = $conn->prepare($docSql);
                        $docStmt->execute([$employee_id, $documentName, $documentPath, "Leave request document for " . $reason]);
                        error_log("Employee leave: Document added to document_management for leave_id $leave_id");
                    } catch (PDOException $e) {
                        error_log("Employee leave: Error adding document to document_management: " . $e->getMessage());
                        // Don't fail the leave request if document insertion fails
                    }
                }

                $success = "Leave request submitted successfully!";

                // Clear the form data from session to prevent duplicate submission on refresh
                unset($_SESSION['leave_form_submitted']);

                // Redirect to prevent form resubmission on refresh (PRG pattern)
                header('Location: employee_leave.php?success=1');
                exit;

            } catch (PDOException $e) {
                $error = "Error submitting leave request: " . $e->getMessage();
            }
        }
    }
}

// Check for success message from redirect (PRG pattern)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "Leave request submitted successfully!";
}

// Fetch leave balances for the employee
function getEmployeeLeaveBalances($employee_id) {
    global $conn;
    try {
        $sql = "SELECT lb.*, lt.leave_type_name
                FROM leave_balances lb
                JOIN leave_types lt ON lb.leave_type_id = lt.leave_type_id
                WHERE lb.employee_id = ? AND lb.year = YEAR(CURDATE())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Fetch leave requests for the employee
function getEmployeeLeaveRequests($employee_id) {
    global $conn;
    try {
        $sql = "SELECT lr.*, lt.leave_type_name
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                WHERE lr.employee_id = ?
                ORDER BY lr.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

if ($employee_id) {
    $leaveBalances = getEmployeeLeaveBalances($employee_id);
    $leaveRequests = getEmployeeLeaveRequests($employee_id);
} else {
    $leaveBalances = [];
    $leaveRequests = [];
}
if ($employee_id && function_exists('getLeaveTypesForEmployee')) {
    $leaveTypes = getLeaveTypesForEmployee($employee_id);
} elseif (function_exists('getLeaveTypes')) {
    $leaveTypes = getLeaveTypes();
} else {
    $leaveTypes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Employee Portal</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="employee_style.css">
    <style>
        /* Fix topnav visibility for this page only */
        .top-navbar {
            margin-left: 250px !important;
            width: calc(100% - 250px) !important;
            position: fixed !important;
            top: 0;
            left: 0;
            z-index: 1020;
        }
        .main-content {
            margin-top: 90px;
            margin-left: 250px;
            padding: 30px 40px 40px 40px;
        }
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }

        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .balance-card h4 {
            margin-bottom: 15px;
            font-weight: 600;
        }

        .balance-value {
            font-size: 2rem;
            font-weight: 700;
        }

        .request-card {
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
            margin-bottom: 15px;
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

        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-approved {
            background-color: #28a745;
            color: white;
        }

        .badge-rejected {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body class="employee-page">
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'employee_sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-calendar-alt mr-2"></i>Leave Management</h2>

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

                <!-- Leave Balances -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h4 class="mb-3">Leave Balances</h4>
                        <div class="row">
                            <?php foreach ($leaveBalances as $balance): ?>
                            <div class="col-md-3 mb-3">
                                <div class="balance-card">
                                    <h4><?php echo htmlspecialchars($balance['leave_type_name']); ?></h4>
                                    <div class="balance-value"><?php echo htmlspecialchars($balance['leaves_remaining']); ?> days</div>
                                    <small>Used: <?php echo htmlspecialchars($balance['leaves_taken']); ?> | Total: <?php echo htmlspecialchars($balance['total_leaves']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Request New Leave -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-plus-circle mr-2"></i>Request New Leave</h5>
                                <button class="btn btn-primary" data-toggle="modal" data-target="#newLeaveRequestModal">
                                    <i class="fas fa-plus mr-2"></i>New Request
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leave Request History -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history mr-2"></i>Leave Request History</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($leaveRequests)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No leave requests found</h5>
                                        <p class="text-muted">You haven't submitted any leave requests yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Leave Type</th>
                                                    <th>Dates</th>
                                                    <th>Duration</th>
                                                    <th>Reason</th>
                                                    <th>Status</th>
                                                    <th>Document</th>
                                                    <th>Applied On</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($leaveRequests as $request): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($request['leave_type_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['start_date']) . ' - ' . htmlspecialchars($request['end_date']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['total_days']); ?> days</td>
                                                    <td><?php echo htmlspecialchars($request['reason']); ?></td>
                                                    <td><span class="status-badge badge-<?php echo strtolower($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                                    <td><?php if ($request['document_path']): ?>
                                                        <button class="btn btn-sm btn-outline-primary mr-1" onclick="viewDocument('<?php echo htmlspecialchars($request['document_path']); ?>', '<?php echo htmlspecialchars($request['leave_type_name']); ?>', '<?php echo htmlspecialchars($request['start_date']); ?> to <?php echo htmlspecialchars($request['end_date']); ?>')"><i class="fas fa-eye"></i> View</button>
                                                        <a href="<?php echo htmlspecialchars($request['document_path']); ?>" download class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i> Download</a>
                                                    <?php endif; ?></td>
                                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($request['applied_on']))); ?></td>
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

    <!-- New Leave Request Modal -->
    <div class="modal fade" id="newLeaveRequestModal" tabindex="-1" role="dialog" aria-labelledby="newLeaveRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newLeaveRequestModalLabel">New Leave Request</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="employee_leave.php" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="leaveTypeId">Leave Type</label>
                            <select class="form-control" id="leaveTypeId" name="leaveTypeId" required>
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

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        function viewDocument(documentPath, leaveType, dates) {
            var fileExtension = documentPath.split('.').pop().toLowerCase();
            var content = '';

            // Update modal title
            $('#documentViewerModalLabel').text('Document Viewer - ' + leaveType + ' (' + dates + ')');

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
    </script>
</body>
</html>
