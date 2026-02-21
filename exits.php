<?php
// DEBUG (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection and helper functions
require_once 'dp.php';

// Database connection (use existing $pdo from dp.php if available)
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $host = getenv('DB_HOST') ?? 'localhost';
    $dbname = getenv('DB_NAME') ?? 'hr_system';
    $username = getenv('DB_USER') ?? 'root';
    $password = getenv('DB_PASS') ?? '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Get messages from session and clear them
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add':
                // Add new employee
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_profiles (personal_info_id, job_role_id, employee_number, hire_date, employment_status, current_salary, work_email, work_phone, location, remote_work) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['job_role_id'],
                        $_POST['employee_number'],
                        $_POST['hire_date'],
                        $_POST['employment_status'],
                        $_POST['current_salary'],
                        $_POST['work_email'],
                        $_POST['work_phone'],
                        $_POST['location'],
                        isset($_POST['remote_work']) ? 1 : 0
                    ]);
                    $message = "Employee profile added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update employee
                try {
                    $stmt = $pdo->prepare("UPDATE employee_profiles SET personal_info_id=?, job_role_id=?, employee_number=?, hire_date=?, employment_status=?, current_salary=?, work_email=?, work_phone=?, location=?, remote_work=? WHERE employee_id=?");
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['job_role_id'],
                        $_POST['employee_number'],
                        $_POST['hire_date'],
                        $_POST['employment_status'],
                        $_POST['current_salary'],
                        $_POST['work_email'],
                        $_POST['work_phone'],
                        $_POST['location'],
                        isset($_POST['remote_work']) ? 1 : 0,
                        $_POST['employee_id']
                    ]);
                    $message = "Employee profile updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete employee
                try {
                    $stmt = $pdo->prepare("DELETE FROM employee_profiles WHERE employee_id=?");
                    $stmt->execute([$_POST['employee_id']]);
                    $message = "Employee profile deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $messageType = "danger";
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "danger";
    }
}

// Handle AJAX requests
if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO exits (employee_id, exit_type, exit_reason, notice_date, exit_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['employee_id'],
                    $_POST['exit_type'],
                    $_POST['exit_reason'],
                    $_POST['notice_date'],
                    $_POST['exit_date'],
                    $_POST['status']
                ]);
                $newId = $pdo->lastInsertId();
                
                // Fetch the newly added record with employee details
                $stmt = $pdo->prepare("
                    SELECT e.*, 
                           CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
                           ep.employee_number
                    FROM exits e
                    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
                    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    WHERE e.exit_id = ?
                ");
                $stmt->execute([$newId]);
                $newRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $newRecord]);
                exit;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM exits WHERE exit_id = ?");
                $stmt->execute([$_POST['exit_id']]);
                echo json_encode(['success' => true, 'exit_id' => $_POST['exit_id']]);
                exit;
            
            case 'change_status':
                $stmt = $pdo->prepare("UPDATE exits SET status=? WHERE exit_id=?");
                $stmt->execute([$_POST['new_status'], $_POST['exit_id']]);
                
                // You can optionally log the remarks to an audit table here
                // For now, we'll just return success with the new status
                echo json_encode([
                    'success' => true, 
                    'message' => 'Status updated successfully',
                    'new_status' => $_POST['new_status']
                ]);
                exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch employees with related data
$stmt = $pdo->query("
    SELECT 
        ep.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        pi.first_name,
        pi.last_name,
        pi.phone_number,
        jr.title as job_title,
        jr.department
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY ep.employee_id DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch personal information for dropdown
$stmt = $pdo->query("SELECT personal_info_id, CONCAT(first_name, ' ', last_name) as full_name FROM personal_information ORDER BY first_name");
$personalInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch job roles for dropdown
$stmt = $pdo->query("SELECT job_role_id, title, department FROM job_roles ORDER BY title");
$jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits with related data
$stmt = $pdo->query("
    SELECT 
        e.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        jr.title as job_title,
        jr.department
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY e.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions for exits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO exits (employee_id, exit_type, exit_reason, notice_date, exit_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['employee_id'],
                    $_POST['exit_type'],
                    $_POST['exit_reason'],
                    $_POST['notice_date'],
                    $_POST['exit_date'],
                    $_POST['status']
                ]);
                $message = "Exit record added successfully!";
                $messageType = "success";
                break;
            
            case 'update':
                $stmt = $pdo->prepare("UPDATE exits SET employee_id=?, exit_type=?, exit_reason=?, notice_date=?, exit_date=?, status=? WHERE exit_id=?");
                $stmt->execute([
                    $_POST['employee_id'],
                    $_POST['exit_type'],
                    $_POST['exit_reason'],
                    $_POST['notice_date'],
                    $_POST['exit_date'],
                    $_POST['status'],
                    $_POST['exit_id']
                ]);
                $message = "Exit record updated successfully!";
                $messageType = "success";
                break;
            
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM exits WHERE exit_id=?");
                $stmt->execute([$_POST['exit_id']]);
                $message = "Exit record deleted successfully!";
                $messageType = "success";
                break;
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch active employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        ep.employee_number,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    WHERE ep.employment_status != 'Terminated'
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Exit Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
    /* Copied styling from employee profiles for identical look */
    :root {
        --azure-blue: #E91E63;
        --azure-blue-light: #F06292;
        --azure-blue-dark: #C2185B;
        --azure-blue-lighter: #F8BBD0;
        --azure-blue-pale: #FCE4EC;
    }

    .section-title {
        color: var(--azure-blue);
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

    body {
        background: var(--azure-blue-pale);
    }

    .main-content {
        background: var(--azure-blue-pale);
        padding: 20px;
    }

    .controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .search-box {
        position: relative;
        flex: 1;
        max-width: 400px;
    }

    .search-box input {
        width: 100%;
        padding: 12px 15px 12px 45px;
        border: 2px solid #e0e0e0;
        border-radius: 25px;
        font-size: 16px;
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        border-color: var(--azure-blue);
        outline: none;
        box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
    }

    .search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
    }

    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 25px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%);
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
    }

    .btn-warning {
        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        color: white;
    }

    .btn-small {
        padding: 8px 15px;
        font-size: 14px;
        margin: 0 3px;
    }

    .table-container {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th {
        background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%);
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: var(--azure-blue-dark);
        border-bottom: 2px solid #dee2e6;
    }

    .table td {
        padding: 15px;
        border-bottom: 1px solid #f1f1f1;
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background-color: var(--azure-blue-lighter);
        transform: scale(1.01);
        transition: all 0.2s ease;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        background: #e2e3e5;
        color: #343a40;
    }

    .status-badge.status-pending {
        background: #ffc107;
        color: #000;
    }

    .status-badge.status-approved {
        background: #28a745;
        color: white;
    }

    .status-badge.status-rejected {
        background: #dc3545;
        color: white;
    }

    .status-badge.status-processing {
        background: #17a2b8;
        color: white;
    }

    .status-badge.status-completed {
        background: #6c757d;
        color: white;
    }

    .status-badge.status-cancelled {
        background: #6c757d;
        color: white;
    }

    .status-badge.status-under.review {
        background: #007bff;
        color: white;
    }

    .status-badge.status-request.revision {
        background: #fd7e14;
        color: white;
    }

    .status-badge.status-clearance.ongoing {
        background: #20c997;
        color: white;
    }

    .status-badge.status-exit.interview.scheduled {
        background: #6610f2;
        color: white;
    }

    .status-badge.status-on.hold {
        background: #e83e8c;
        color: white;
    }

    .status-badge.status-withdrawn {
        background: #6c757d;
        color: white;
    }

    .status-action-btn {
        background: linear-gradient(135deg, #6610f2 0%, #6f42c1 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .status-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 16, 242, 0.4);
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 15px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 15px 15px 0 0;
    }

    .modal-header h2 {
        margin: 0;
    }

    .close {
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: white;
        opacity: 0.7;
    }

    .close:hover {
        opacity: 1;
    }

    .modal-body {
        padding: 30px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--azure-blue-dark);
    }

    .form-control {
        width: 100%;
        padding: 6px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    .form-control:focus {
        border-color: var(--azure-blue);
        outline: none;
        box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
    }

    .form-row {
        display: flex;
        gap: 20px;
    }

    .form-col {
        flex: 1;
    }

    .no-results {
        text-align: center;
        padding: 50px;
        color: #666;
    }

    .no-results i {
        font-size: 4rem;
        margin-bottom: 20px;
        color: #ddd;
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }

    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .required {
        color: #dc3545;
    }

    .actions-cell {
        min-width: 240px;
    }

    @media (max-width: 768px) {
        .controls {
            flex-direction: column;
            align-items: stretch;
        }

        .search-box {
            max-width: none;
        }

        .form-row {
            flex-direction: column;
        }

        .table-container {
            overflow-x: auto;
        }

        .content {
            padding: 20px;
        }
    }
    </style>
</head>

<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Employee Exit Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search exits...">
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="table" id="exitsTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Exit Type</th>
                                    <th>Notice Date</th>
                                    <th>Exit Date</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exits as $exit): ?>
                                <tr data-exit-id="<?= $exit['exit_id'] ?>" data-status="<?= strtolower(str_replace(' ', '.', $exit['status'])) ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($exit['employee_name']) ?></strong><br>
                                        <small><?= htmlspecialchars($exit['employee_number']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($exit['exit_type']) ?></td>
                                    <td><?= date('M d, Y', strtotime($exit['notice_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($exit['exit_date'])) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '.', $exit['status'])) ?>">
                                            <?= htmlspecialchars($exit['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($exit['exit_reason']) ?></td>
                                    <td class="actions-cell">
                                        <button class="status-action-btn" 
                                            onclick="openStatusChangeModal(<?= $exit['exit_id'] ?>, '<?= htmlspecialchars($exit['status'], ENT_QUOTES) ?>')"
                                            title="Change Status">
                                            🔄 Change Status
                                        </button>
                                        <button class="btn btn-info btn-small"
                                            onclick="viewDetails(<?= $exit['exit_id'] ?>)"
                                            title="View Details">
                                            👁 View Details
                                        </button>
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

    <!-- Add/Edit Exit Modal -->
    <div id="exitModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Exit Record</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="exitForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="exit_id" name="exit_id">

                    <div class="form-group">
                        <label for="employee_id">Employee</label>
                        <select id="employee_id" name="employee_id" class="form-control" required>
                            <option value="">Select employee...</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?= $employee['employee_id'] ?>">
                                <?= htmlspecialchars($employee['full_name']) ?>
                                (<?= htmlspecialchars($employee['employee_number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="exit_type">Exit Type</label>
                                <select id="exit_type" name="exit_type" class="form-control" required>
                                    <option value="">Select type...</option>
                                    <option value="Resignation">Resignation</option>
                                    <option value="Retirement">Retirement</option>
                                    <option value="Termination">Termination</option>
                                    <option value="End of Contract">End of Contract</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="">Select status...</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Under Review">Under Review</option>
                                    <option value="Request Revision">Request Revision</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                    <option value="Processing">Processing</option>
                                    <option value="Clearance Ongoing">Clearance Ongoing</option>
                                    <option value="Exit Interview Scheduled">Exit Interview Scheduled</option>
                                    <option value="On Hold">On Hold</option>
                                    <option value="Withdrawn">Withdrawn</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="notice_date">Notice Date</label>
                                <input type="date" id="notice_date" name="notice_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="exit_date">Exit Date</label>
                                <input type="date" id="exit_date" name="exit_date" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exit_reason">Exit Reason</label>
                        <textarea id="exit_reason" name="exit_reason" class="form-control" rows="3" required></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Exit Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                <h2>Confirm Delete</h2>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p style="text-align: center; font-size: 1.1em; margin-bottom: 20px;">
                    Are you sure you want to delete this exit record? 
                </p>
                <div style="text-align: center;">
                    <button class="btn btn-danger" id="confirmDelete" style="margin-right: 10px;">Yes</button>
                    <button class="btn btn-secondary" onclick="closeDeleteModal()">No</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div id="statusChangeModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #6610f2 0%, #6f42c1 100%);">
                <h2>Update Exit Request Status</h2>
                <span class="close" onclick="closeStatusChangeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="statusChangeForm">
                    <input type="hidden" id="status_change_exit_id" name="exit_id">
                    
                    <div class="form-group">
                        <label for="current_status_display">Current Status</label>
                        <input type="text" id="current_status_display" class="form-control" readonly style="background: #f8f9fa; font-weight: 600;">
                    </div>

                    <div class="form-group">
                        <label for="new_status">New Status <span class="required">*</span></label>
                        <select id="new_status" name="new_status" class="form-control" required>
                            <option value="">Select new status...</option>
                            <option value="Pending">Pending</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Request Revision">Request Revision</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Processing">Processing</option>
                            <option value="Clearance Ongoing">Clearance Ongoing</option>
                            <option value="Exit Interview Scheduled">Exit Interview Scheduled</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Withdrawn">Withdrawn</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status_remarks">Remarks / Comments</label>
                        <textarea id="status_remarks" name="remarks" class="form-control" rows="4" placeholder="Add any notes, reasons, or comments about this status change..."></textarea>
                        <small style="color: #6c757d; font-size: 12px;">These remarks will be saved with the status change for record keeping.</small>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn btn-success" style="margin-right: 10px;">✓ Update Status</button>
                        <button type="button" class="btn btn-secondary" onclick="closeStatusChangeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2>Exit Request Details</h2>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Details will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Send Feedback to Employee</h2>
                <span class="close" onclick="closeFeedbackModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="feedbackForm">
                    <input type="hidden" id="feedback_exit_id" name="exit_id">
                    <div class="form-group">
                        <label for="feedback_subject">Subject</label>
                        <input type="text" id="feedback_subject" name="subject" class="form-control" placeholder="Enter subject..." required>
                    </div>
                    <div class="form-group">
                        <label for="feedback_message">Message</label>
                        <textarea id="feedback_message" name="message" class="form-control" rows="5" placeholder="Enter your message..." required></textarea>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="margin-right: 10px;">📧 Send Feedback</button>
                        <button type="button" class="btn btn-secondary" onclick="closeFeedbackModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
    // Add this JavaScript at the end of the file
    let exitsData = <?= json_encode($exits) ?>;

    function openModal(mode, exitId = null) {
        const modal = document.getElementById('exitModal');
        const form = document.getElementById('exitForm');
        const title = document.getElementById('modalTitle');
        const action = document.getElementById('action');

        form.reset();
        if (mode === 'add') {
            title.textContent = 'Add New Exit Record';
            action.value = 'add';
            document.getElementById('exit_id').value = '';
        } else if (mode === 'edit') {
            title.textContent = 'Edit Exit Record';
            action.value = 'update';
            document.getElementById('exit_id').value = exitId;
            populateEditForm(exitId);
        }

        modal.style.display = 'block';
    }

    function closeModal() {
        document.getElementById('exitModal').style.display = 'none';
    }

    function populateEditForm(exitId) {
        const exit = exitsData.find(e => e.exit_id == exitId);
        if (exit) {
            document.getElementById('employee_id').value = exit.employee_id;
            document.getElementById('exit_type').value = exit.exit_type;
            document.getElementById('exit_reason').value = exit.exit_reason;
            document.getElementById('notice_date').value = exit.notice_date.split(' ')[0];
            document.getElementById('exit_date').value = exit.exit_date.split(' ')[0];
            document.getElementById('status').value = exit.status;
        }
    }

    function editExit(exitId) {
        openModal('edit', exitId);
    }

    function deleteExit(exitId) {
        // Show delete confirmation modal
        const deleteModal = document.getElementById('deleteModal');
        deleteModal.style.display = 'block';
        
        // Handle delete confirmation
        document.getElementById('confirmDelete').onclick = function() {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('exit_id', exitId);
            formData.append('ajax', 'true');

            fetch('exits.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = document.querySelector(`tr[data-exit-id="${exitId}"]`);
                    if (row) {
                        row.remove();
                    }
                    // Remove from exitsData array
                    exitsData = exitsData.filter(exit => exit.exit_id != exitId);
                    closeDeleteModal();
                    showAlert('Exit record deleted successfully!', 'success');
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Error: ' + error.message, 'error');
            });
        };
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    // Status Change Modal Functions
    function openStatusChangeModal(exitId, currentStatus) {
        document.getElementById('status_change_exit_id').value = exitId;
        document.getElementById('current_status_display').value = currentStatus;
        document.getElementById('new_status').value = '';
        document.getElementById('status_remarks').value = '';
        document.getElementById('statusChangeModal').style.display = 'block';
    }

    function closeStatusChangeModal() {
        document.getElementById('statusChangeModal').style.display = 'none';
    }

    // Handle status change form submission
    document.getElementById('statusChangeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'change_status');
        formData.append('ajax', 'true');
        
        const exitId = formData.get('exit_id');
        const newStatus = formData.get('new_status');
        
        fetch('exits.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the row with new status
                const row = document.querySelector(`tr[data-exit-id="${exitId}"]`);
                if (row) {
                    const statusBadge = row.querySelector('.status-badge');
                    const statusClass = newStatus.toLowerCase().replace(/ /g, '.');
                    statusBadge.className = `status-badge status-${statusClass}`;
                    statusBadge.textContent = newStatus;
                    row.setAttribute('data-status', statusClass);
                }
                
                // Update exitsData array
                const index = exitsData.findIndex(e => e.exit_id == exitId);
                if (index !== -1) {
                    exitsData[index].status = newStatus;
                }
                
                closeStatusChangeModal();
                showAlert('Status updated successfully to: ' + newStatus, 'success');
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error: ' + error.message, 'danger');
        });
    });

    // View Details
    function viewDetails(exitId) {
        const exit = exitsData.find(e => e.exit_id == exitId);
        if (!exit) return;
        
        const detailsContent = document.getElementById('detailsContent');
        detailsContent.innerHTML = `
            <div style="padding: 10px;">
                <div style="margin-bottom: 20px;">
                    <h3 style="color: var(--azure-blue); margin-bottom: 15px;">Employee Information</h3>
                    <p><strong>Name:</strong> ${exit.employee_name}</p>
                    <p><strong>Employee Number:</strong> ${exit.employee_number}</p>
                    <p><strong>Department:</strong> ${exit.department || 'N/A'}</p>
                    <p><strong>Job Title:</strong> ${exit.job_title || 'N/A'}</p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h3 style="color: var(--azure-blue); margin-bottom: 15px;">Exit Details</h3>
                    <p><strong>Exit Type:</strong> ${exit.exit_type}</p>
                    <p><strong>Notice Date:</strong> ${formatDate(exit.notice_date)}</p>
                    <p><strong>Exit Date:</strong> ${formatDate(exit.exit_date)}</p>
                    <p><strong>Status:</strong> <span class="status-badge status-${exit.status.toLowerCase().replace(/ /g, '.')}">${exit.status}</span></p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h3 style="color: var(--azure-blue); margin-bottom: 15px;">Exit Reason</h3>
                    <p style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid var(--azure-blue);">
                        ${exit.exit_reason}
                    </p>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
                </div>
            </div>
        `;
        
        document.getElementById('detailsModal').style.display = 'block';
    }

    function closeDetailsModal() {
        document.getElementById('detailsModal').style.display = 'none';
    }

    // Send Feedback
    function sendFeedback(exitId) {
        document.getElementById('feedback_exit_id').value = exitId;
        document.getElementById('feedbackModal').style.display = 'block';
    }

    function closeFeedbackModal() {
        document.getElementById('feedbackModal').style.display = 'none';
        document.getElementById('feedbackForm').reset();
    }

    // Handle feedback form submission
    document.getElementById('feedbackForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'send_feedback');
        formData.append('ajax', 'true');
        
        fetch('exits.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeFeedbackModal();
                showAlert('Feedback sent successfully!', 'success');
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error: ' + error.message, 'danger');
        });
    });

    // Update form handling
    document.getElementById('exitForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('ajax', 'true');
        const action = formData.get('action');
        const exitId = formData.get('exit_id');
        
        fetch('exits.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (action === 'add') {
                    // Add new row to table
                    const tbody = document.querySelector('#exitsTable tbody');
                    const newRow = createTableRow(data.data);
                    tbody.insertBefore(newRow, tbody.firstChild);
                    
                    // Add to exitsData array
                    exitsData.unshift(data.data);
                    
                    showAlert('Exit record added successfully!', 'success');
                } else if (action === 'update') {
                    // Update existing row in table
                    const row = document.querySelector(`tr[data-exit-id="${exitId}"]`);
                    if (row) {
                        const newRow = createTableRow(data.data);
                        row.replaceWith(newRow);
                    }
                    
                    // Update exitsData array
                    const index = exitsData.findIndex(e => e.exit_id == exitId);
                    if (index !== -1) {
                        exitsData[index] = data.data;
                    }
                    
                    showAlert('Exit record updated successfully!', 'success');
                }
                
                // Close modal
                closeModal();
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error: ' + error.message, 'danger');
        });
    });

    function createTableRow(exit) {
        const row = document.createElement('tr');
        const statusClass = exit.status.toLowerCase().replace(/ /g, '.');
        row.setAttribute('data-exit-id', exit.exit_id);
        row.setAttribute('data-status', statusClass);
        
        row.innerHTML = `
            <td>
                <strong>${exit.employee_name}</strong><br>
                <small>${exit.employee_number}</small>
            </td>
            <td>${exit.exit_type}</td>
            <td>${formatDate(exit.notice_date)}</td>
            <td>${formatDate(exit.exit_date)}</td>
            <td>
                <span class="status-badge status-${statusClass}">
                    ${exit.status}
                </span>
            </td>
            <td>${exit.exit_reason}</td>
            <td class="actions-cell">
                <button class="status-action-btn" 
                    onclick="openStatusChangeModal(${exit.exit_id}, '${exit.status.replace(/'/g, "\\'")}')"
                    title="Change Status">
                    🔄 Change Status
                </button>
                <button class="btn btn-info btn-small" onclick="viewDetails(${exit.exit_id})" title="View Details">
                    👁 View Details
                </button>
            </td>
        `;
        return row;
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    }

    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;
        
        const content = document.querySelector('.content');
        content.insertBefore(alertDiv, content.firstChild);
        
        setTimeout(() => alertDiv.remove(), 5000);
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const exitModal = document.getElementById('exitModal');
        const deleteModal = document.getElementById('deleteModal');
        const statusChangeModal = document.getElementById('statusChangeModal');
        const detailsModal = document.getElementById('detailsModal');
        const feedbackModal = document.getElementById('feedbackModal');
        
        if (event.target === exitModal) {
            closeModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
        if (event.target === statusChangeModal) {
            closeStatusChangeModal();
        }
        if (event.target === detailsModal) {
            closeDetailsModal();
        }
        if (event.target === feedbackModal) {
            closeFeedbackModal();
        }
    }

    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
</body>

</html>