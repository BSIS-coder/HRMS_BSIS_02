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
            $stmt->execute([$employee_id, $leaveTypeId, $startDate, $endDate, $duration, $reason, $documentPath]);
            $leave_id = $conn->lastInsertId();
            error_log("Employee index: About to log activity for leave_id $leave_id");
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
                    error_log("Employee index: Document added to document_management for leave_id $leave_id");
                } catch (PDOException $e) {
                    error_log("Employee index: Error adding document to document_management: " . $e->getMessage());
                    // Don't fail the leave request if document insertion fails
                }
            }

            $success = "Leave request submitted successfully!";
            unset($_SESSION['leave_form_submitted']);
            header('Location: employee_index.php?success=1');
            exit;
        } catch (PDOException $e) {
            $error = "Error submitting leave request: " . $e->getMessage();
        }
    }
}

// Check for success message from redirect (PRG pattern)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "Leave request submitted successfully!";
}

// Fetch leave types for the form
if (function_exists('getLeaveTypesForEmployee') && $employee_id) {
    $leaveTypes = getLeaveTypesForEmployee($employee_id);
} elseif (function_exists('getLeaveTypes')) {
    $leaveTypes = getLeaveTypes();
} else {
    $leaveTypes = [];
}

// L&D widget: upcoming training and training needs count
$upcomingTraining = [];
$trainingNeedsCount = 0;
if ($employee_id) {
    try {
        $stmt = $conn->prepare("
            SELECT te.enrollment_id, te.status, ts.session_name, ts.start_date, tc.course_name
            FROM training_enrollments te
            JOIN training_sessions ts ON te.session_id = ts.session_id
            JOIN training_courses tc ON ts.course_id = tc.course_id
            WHERE te.employee_id = ? AND te.status != 'Completed'
            ORDER BY ts.start_date ASC
            LIMIT 5
        ");
        $stmt->execute([$employee_id]);
        $upcomingTraining = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM training_needs_assessment WHERE employee_id = ? AND status IN ('Identified', 'In Progress')");
        $stmt->execute([$employee_id]);
        $trainingNeedsCount = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        // ignore
    }
}

// Debug information
$current_role = $_SESSION['role'] ?? 'none';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for employee index page */
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .container-fluid {
            padding: 0;
        }
        
        .row {
            margin-right: 0;
            margin-left: 0;
        }
        
        .employee-welcome {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .employee-welcome h2 {
            margin: 0;
            font-weight: 700;
        }
        
        .employee-welcome p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .quick-actions {
            margin-bottom: 30px;
        }
        
        .quick-action-btn {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #495057;
            display: block;
            height: 100%;
        }
        
        .quick-action-btn:hover {
            border-color: #E91E63;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(233, 30, 99, 0.15);
            text-decoration: none;
            color: #E91E63;
        }
        
        .quick-action-btn i {
            font-size: 2.5rem;
            color: #E91E63;
            margin-bottom: 15px;
        }
        
        .quick-action-btn h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }
        
        .info-card h5 {
            color: #E91E63;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .info-value {
            color: #495057;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .leave-request-btn {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .leave-request-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.3);
            color: white;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .document-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            background: #E91E63;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
        }
        
        .document-info h6 {
            margin: 0;
            color: #495057;
        }
        
        .document-info small {
            color: #6c757d;
        }
        
        .profile-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: #E91E63;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 20px;
        }

        /* Form Styling */
        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }

        .form-control, .form-control-file {
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #fafbfc;
        }

        .form-control:focus, .form-control-file:focus {
            border-color: #E91E63;
            box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.15);
            background-color: white;
        }

        .form-control::placeholder {
            color: #adb5bd;
            font-size: 0.9rem;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23495057' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 35px;
        }

        textarea.form-control {
            resize: vertical;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .modal-header {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 20px 25px;
        }

        .modal-header .modal-title {
            color: white;
            font-weight: 700;
            font-size: 1.3rem;
        }

        .modal-header .close {
            color: white;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        .modal-footer {
            border-top: 1px solid #e0e0e0;
            padding: 15px 25px;
        }

        .btn {
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            border: none;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.3);
            color: white;
        }

        .row .col-md-6 .form-group {
            margin-bottom: 0;
        }

        .modal-body .row {
            margin-left: -10px;
            margin-right: -10px;
        }

        .modal-body .col-md-6 {
            padding-left: 10px;
            padding-right: 10px;
        }

        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'employee_sidebar.php'; ?>
            <div class="main-content">
                <!-- Employee Welcome Section -->
                <div class="employee-welcome">
                    <h2><i class="fas fa-user-circle mr-3"></i>Welcome, <?php echo ucfirst(explode('.', $username)[0]); ?>!</h2>
                    <p>Here's your personal HR dashboard with all the information you need</p>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h4 class="section-title">Quick Actions</h4>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="my_profile.php" class="quick-action-btn">
                                <i class="fas fa-user-edit"></i>
                                <h5>Update Profile</h5>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="#" class="quick-action-btn" data-toggle="modal" data-target="#leaveRequestModal">
                                <i class="fas fa-calendar-plus"></i>
                                <h5>Request Leave</h5>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="my_document.php" class="quick-action-btn">
                                <i class="fas fa-file-alt"></i>
                                <h5>Documents</h5>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="#" class="quick-action-btn">
                                <i class="fas fa-chart-line"></i>
                                <h5>Performance</h5>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-4">
                        <div class="profile-section">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <h5 class="text-center mb-3">Personal Information</h5>
                            <div class="info-item">
                                <span class="info-label">Employee ID:</span>
                                <span class="info-value"><?php echo $employee_id; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Username:</span>
                                <span class="info-value"><?php echo $username; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status:</span>
                                <span class="status-badge status-active">Active</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Department:</span>
                                <span class="info-value">IT Department</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Position:</span>
                                <span class="info-value">Software Developer</span>
                            </div>
                        </div>
                    </div>

                    <!-- Leave Information -->
                    <div class="col-md-4">
                        <div class="info-card">
                            <h5><i class="fas fa-calendar-alt mr-2"></i>Leave Information</h5>
                            <div class="info-item">
                                <span class="info-label">Annual Leave Balance:</span>
                                <span class="info-value">15 days</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Sick Leave Balance:</span>
                                <span class="info-value">10 days</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Pending Requests:</span>
                                <span class="info-value">2</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Leave Date:</span>
                                <span class="info-value">Aug 15, 2023</span>
                            </div>
                            <div class="text-center mt-3">
                                <button class="leave-request-btn" data-toggle="modal" data-target="#leaveRequestModal">
                                    <i class="fas fa-plus mr-2"></i>Request Leave
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Payroll Information -->
                    <div class="col-md-4">
                        <div class="info-card">
                            <h5><i class="fas fa-money-bill-wave mr-2"></i>Payroll Information</h5>
                            <div class="info-item">
                                <span class="info-label">Basic Salary:</span>
                                <span class="info-value">$4,500</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Allowances:</span>
                                <span class="info-value">$500</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Next Payroll:</span>
                                <span class="info-value">Aug 25, 2023</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Bank Account:</span>
                                <span class="info-value">****1234</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Documents -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5><i class="fas fa-file-alt mr-2"></i>Recent Documents</h5>
                            <div class="document-item">
                                <div class="document-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="document-info">
                                    <h6>Employment Contract</h6>
                                    <small>Updated: Aug 20, 2023</small>
                                </div>
                            </div>
                            <div class="document-item">
                                <div class="document-icon">
                                    <i class="fas fa-file-word"></i>
                                </div>
                                <div class="document-info">
                                    <h6>Performance Review</h6>
                                    <small>Updated: Aug 15, 2023</small>
                                </div>
                            </div>
                            <div class="document-item">
                                <div class="document-icon">
                                    <i class="fas fa-file-image"></i>
                                </div>
                                <div class="document-info">
                                    <h6>ID Badge</h6>
                                    <small>Updated: Aug 10, 2023</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Learning & Development -->
                    <div class="col-md-6">
                        <div class="info-card">
                            <h5><i class="fas fa-graduation-cap mr-2"></i>Learning & Development</h5>
                            <?php if (!empty($upcomingTraining)): ?>
                                <?php foreach (array_slice($upcomingTraining, 0, 4) as $t): ?>
                                    <div class="info-item">
                                        <span class="info-label"><?php echo htmlspecialchars($t['course_name']); ?></span>
                                        <span class="info-value"><?php echo $t['start_date'] ? date('M d, Y', strtotime($t['start_date'])) : '—'; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="info-item">
                                    <span class="info-label">Upcoming training</span>
                                    <span class="info-value text-muted">None scheduled</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($trainingNeedsCount > 0): ?>
                                <div class="info-item">
                                    <span class="info-label">Identified training needs</span>
                                    <span class="info-value"><?php echo $trainingNeedsCount; ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="text-center mt-3">
                                <a href="my_training.php" class="btn btn-sm btn-outline-primary">View all my training</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Overview -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="info-card">
                            <h5><i class="fas fa-chart-line mr-2"></i>Performance Overview</h5>
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <h3 class="text-success">4.2/5</h3>
                                    <p class="text-muted">Overall Rating</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h3 class="text-primary">87%</h3>
                                    <p class="text-muted">Goal Achievement</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h3 class="text-info">95%</h3>
                                    <p class="text-muted">Attendance</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h3 class="text-warning">3</h3>
                                    <p class="text-muted">Projects Completed</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Request Modal -->
    <div class="modal fade" id="leaveRequestModal" tabindex="-1" role="dialog" aria-labelledby="leaveRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveRequestModalLabel">Request Leave</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="employee_index.php" enctype="multipart/form-data">
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
