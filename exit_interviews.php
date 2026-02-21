<?php
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

// Database connection
$host = 'localhost';
$dbname = 'hr_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new exit interview
                try {
                    $stmt = $pdo->prepare("INSERT INTO exit_interviews (exit_id, employee_id, interview_date, feedback, improvement_suggestions, reason_for_leaving, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['interview_date'],
                        $_POST['feedback'],
                        $_POST['improvement_suggestions'],
                        $_POST['reason_for_leaving'],
                        $_POST['status']
                    ]);
                    $_SESSION['message'] = "Exit interview added successfully!";
                    $_SESSION['messageType'] = "success";
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error adding interview: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                }
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
            
            case 'update':
                // Update exit interview
                try {
                    $stmt = $pdo->prepare("UPDATE exit_interviews SET exit_id=?, employee_id=?, interview_date=?, feedback=?, improvement_suggestions=?, reason_for_leaving=?, status=? WHERE interview_id=?");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['interview_date'],
                        $_POST['feedback'],
                        $_POST['improvement_suggestions'],
                        $_POST['reason_for_leaving'],
                        $_POST['status'],
                        $_POST['interview_id']
                    ]);
                    $_SESSION['message'] = "Exit interview updated successfully!";
                    $_SESSION['messageType'] = "success";
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error updating interview: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                }
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
            
            case 'delete':
                // Delete exit interview
                try {
                    $stmt = $pdo->prepare("DELETE FROM exit_interviews WHERE interview_id=?");
                    $stmt->execute([$_POST['interview_id']]);
                    $_SESSION['message'] = "Exit interview deleted successfully!";
                    $_SESSION['messageType'] = "success";
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error deleting interview: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                }
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
        }
    }
}

// Get message from session
$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    // Clear the message after displaying
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// Fetch exit interviews with related data
$stmt = $pdo->query("
    SELECT 
        ei.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        ep.employee_number,
        ep.work_email,
        jr.title as job_title,
        jr.department,
        ex.exit_date,
        ex.exit_type,
        ep.hire_date
    FROM exit_interviews ei
    LEFT JOIN employee_profiles ep ON ei.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN exits ex ON ei.exit_id = ex.exit_id
    ORDER BY ei.interview_date DESC
");
$interviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown
$stmt = $pdo->query("
    SELECT 
        ex.exit_id, 
        ex.employee_id,
        ex.exit_date,
        ex.exit_type,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
    FROM exits ex
    LEFT JOIN employee_profiles ep ON ex.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY ex.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exit Interview Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for exit interviews page */
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

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
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
        }

        .status-scheduled {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

        /* Certificate Styles */
        .certificate-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px;
            background: white;
            border: 15px solid;
            border-image: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 50%, var(--azure-blue-dark) 100%) 1;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            position: relative;
        }

        .certificate-container::before,
        .certificate-container::after {
            content: '';
            position: absolute;
            width: 80px;
            height: 80px;
            border: 3px solid var(--azure-blue);
        }

        .certificate-container::before {
            top: 20px;
            left: 20px;
            border-right: none;
            border-bottom: none;
        }

        .certificate-container::after {
            bottom: 20px;
            right: 20px;
            border-left: none;
            border-top: none;
        }

        .certificate-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--azure-blue-lighter);
        }

        .certificate-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.3);
        }

        .certificate-title {
            font-size: 36px;
            font-weight: 700;
            color: var(--azure-blue-dark);
            margin: 20px 0 10px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .certificate-subtitle {
            font-size: 18px;
            color: #666;
            font-style: italic;
        }

        .certificate-body {
            padding: 30px 20px;
            line-height: 1.8;
        }

        .certificate-text {
            font-size: 16px;
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }

        .employee-details {
            background: var(--azure-blue-pale);
            padding: 30px;
            border-radius: 10px;
            margin: 30px 0;
            border-left: 5px solid var(--azure-blue);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--azure-blue-lighter);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--azure-blue-dark);
            min-width: 180px;
        }

        .detail-value {
            color: #333;
            text-align: right;
            flex: 1;
        }

        .certificate-footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-around;
            padding-top: 30px;
            border-top: 2px solid var(--azure-blue-lighter);
        }

        .signature-block {
            text-align: center;
            min-width: 200px;
        }

        .signature-line {
            border-top: 2px solid #333;
            margin: 60px 0 10px;
            padding-top: 10px;
        }

        .signature-name {
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .signature-title {
            font-size: 14px;
            color: #666;
            font-style: italic;
        }

        .certificate-seal {
            position: absolute;
            bottom: 40px;
            left: 40px;
            width: 100px;
            height: 100px;
            border: 3px solid var(--azure-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            font-size: 12px;
            text-align: center;
            font-weight: 600;
            color: var(--azure-blue-dark);
            transform: rotate(-15deg);
        }

        .certificate-date {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #666;
        }

        /* IMPROVED PRINT STYLES */
        @media print {
            /* Hide everything except the certificate */
            body * {
                visibility: hidden;
            }
            
            #printCertificate,
            #printCertificate * {
                visibility: visible;
            }
            
            /* Hide non-printable elements */
            .no-print {
                display: none !important;
            }
            
            /* Position certificate for printing */
            #printCertificate {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            
            /* Reset page setup for optimal printing */
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            
            /* Ensure certificate fits on one page */
            .certificate-container {
                max-width: 100%;
                width: 100%;
                margin: 0;
                padding: 20px;
                page-break-inside: avoid;
                box-sizing: border-box;
                border-width: 8px;
                box-shadow: none !important;
            }
            
            /* Adjust font sizes for print */
            .certificate-title {
                font-size: 24px;
                letter-spacing: 2px;
            }
            
            .certificate-subtitle {
                font-size: 14px;
            }
            
            .certificate-text {
                font-size: 13px;
                line-height: 1.6;
            }
            
            .detail-row {
                padding: 8px 0;
                font-size: 13px;
            }
            
            .employee-details {
                padding: 15px;
                margin: 15px 0;
            }
            
            .detail-label {
                font-size: 13px;
                min-width: 150px;
            }
            
            .detail-value {
                font-size: 13px;
            }
            
            /* Adjust logo size */
            .certificate-logo {
                width: 70px;
                height: 70px;
                font-size: 32px;
                margin-bottom: 15px;
            }
            
            /* Adjust decorative corners */
            .certificate-container::before,
            .certificate-container::after {
                width: 50px;
                height: 50px;
                border-width: 2px;
            }
            
            .certificate-container::before {
                top: 10px;
                left: 10px;
            }
            
            .certificate-container::after {
                bottom: 10px;
                right: 10px;
            }
            
            /* Adjust body padding */
            .certificate-body {
                padding: 15px 10px;
            }
            
            /* Adjust header */
            .certificate-header {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            
            /* Adjust footer spacing */
            .certificate-footer {
                margin-top: 25px;
                padding-top: 15px;
            }
            
            .signature-line {
                margin: 30px 0 5px;
            }
            
            .signature-name {
                font-size: 13px;
            }
            
            .signature-title {
                font-size: 12px;
            }
            
            /* Adjust seal position and size */
            .certificate-seal {
                bottom: 20px;
                left: 20px;
                width: 70px;
                height: 70px;
                font-size: 9px;
                border-width: 2px;
            }
            
            .certificate-date {
                margin-top: 20px;
                font-size: 12px;
            }
            
            /* Ensure colors print correctly */
            .certificate-container,
            .certificate-logo,
            .employee-details {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color-adjust: exact;
            }
            
            /* Remove shadows for cleaner print */
            .certificate-logo {
                box-shadow: none !important;
            }
        }

        /* Additional responsive adjustments for smaller screens */
        @media screen and (max-width: 768px) {
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

            .certificate-container {
                padding: 20px;
                border-width: 8px;
            }

            .certificate-title {
                font-size: 24px;
            }

            .certificate-footer {
                flex-direction: column;
                gap: 40px;
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
                <h2 class="section-title">Exit Interview Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by employee name, status...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add New Interview
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="interviewTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Job Title</th>
                                    <th>Interview Date</th>
                                    <th>Exit Date</th>
                                    <th>Exit Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="interviewTableBody">
                                <?php foreach ($interviews as $interview): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($interview['full_name']) ?></strong><br>
                                            <small style="color: #666;">#<?= htmlspecialchars($interview['employee_number']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($interview['job_title']) ?><br>
                                        <small style="color: #666;"><?= htmlspecialchars($interview['department']) ?></small>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($interview['interview_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($interview['exit_date'])) ?></td>
                                    <td><?= htmlspecialchars($interview['exit_type']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($interview['status']) ?>">
                                            <?= htmlspecialchars($interview['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editInterview(<?= $interview['interview_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($interviews)): ?>
                        <div class="no-results">
                            <i>📋</i>
                            <h3>No exit interviews found</h3>
                            <p>Start by adding your first exit interview.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Interview Modal -->
    <div id="interviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Exit Interview</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="interviewForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="interview_id" name="interview_id">

                    <div class="form-group">
                        <label for="exit_id">Exit Record</label>
                        <select id="exit_id" name="exit_id" class="form-control" required>
                            <option value="">Select exit record...</option>
                            <?php foreach ($exits as $exit): ?>
                            <option value="<?= $exit['exit_id'] ?>" data-employee-id="<?= $exit['employee_id'] ?>"><?= htmlspecialchars($exit['employee_name']) ?> - <?= date('M d, Y', strtotime($exit['exit_date'])) ?> (<?= htmlspecialchars($exit['exit_type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" id="employee_id" name="employee_id">

                    <div class="form-group">
                        <label for="interview_date">Interview Date</label>
                        <input type="date" id="interview_date" name="interview_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="feedback">Feedback</label>
                        <textarea id="feedback" name="feedback" class="form-control" placeholder=" Nothing's here yet..." readonly style="background-color: #f5f5f5; cursor: not-allowed;"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="improvement_suggestions">Improvement Suggestions</label>
                        <textarea id="improvement_suggestions" name="improvement_suggestions" class="form-control" placeholder="Nothing's here yet..." readonly style="background-color: #f5f5f5; cursor: not-allowed;"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="reason_for_leaving">Reason for Leaving</label>
                        <textarea id="reason_for_leaving" name="reason_for_leaving" class="form-control" placeholder="Nothing's here yet..." readonly style="background-color: #f5f5f5; cursor: not-allowed;"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div style="text-align: right;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Certificate Print Section -->
    <div id="printCertificate" style="display: none;">
        <div class="certificate-container">
            <div class="certificate-header">
                <div class="certificate-logo">🏢</div>
                <h1 class="certificate-title">Exit Certificate</h1>
                <p class="certificate-subtitle">Official Record of Employment Exit</p>
            </div>

            <div class="certificate-body">
                <p class="certificate-text">
                    This is to certify that <strong id="certEmployeeName">[Employee Name]</strong> has formally completed the exit process with our organization. 
                    The company acknowledges their service and extends best wishes for their future endeavors.
                </p>

                <div class="employee-details">
                    <div class="detail-row"><span class="detail-label">Employee Name:</span><span class="detail-value" id="certName"></span></div>
                    <div class="detail-row"><span class="detail-label">Employee Number:</span><span class="detail-value" id="certNumber"></span></div>
                    <div class="detail-row"><span class="detail-label">Job Title:</span><span class="detail-value" id="certJobTitle"></span></div>
                    <div class="detail-row"><span class="detail-label">Department:</span><span class="detail-value" id="certDepartment"></span></div>
                    <div class="detail-row"><span class="detail-label">Exit Type:</span><span class="detail-value" id="certExitType"></span></div>
                    <div class="detail-row"><span class="detail-label">Exit Date:</span><span class="detail-value" id="certExitDate"></span></div>
                    <div class="detail-row"><span class="detail-label">Interview Date:</span><span class="detail-value" id="certInterviewDate"></span></div>
                </div>

                <div class="certificate-date">
                    Issued on <span id="certIssuedDate"><?= date('F d, Y') ?></span>
                </div>
            </div>

            <div class="certificate-footer">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">_______________________</div>
                    <div class="signature-title">HR Manager</div>
                </div>

                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-name">_______________________</div>
                    <div class="signature-title">Authorized Signature</div>
                </div>
            </div>

            <div class="certificate-seal">
                OFFICIAL<br>COMPANY<br>SEAL
            </div>
        </div>
    </div>

    <script>
        // Modal controls
        const modal = document.getElementById('interviewModal');
        const form = document.getElementById('interviewForm');

        // Auto-populate employee_id when exit record is selected
        document.getElementById('exit_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const employeeId = selectedOption.getAttribute('data-employee-id');
            document.getElementById('employee_id').value = employeeId || '';
        });

        function openModal(action, data = null) {
            document.getElementById('action').value = action;
            document.getElementById('modalTitle').innerText = action === 'add' ? 'Add New Exit Interview' : 'Edit Exit Interview';
            form.reset();

            if (data) {
                // Set form values from data
                for (let key in data) {
                    const element = document.getElementById(key);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = data[key] == 1;
                        } else {
                            element.value = data[key];
                        }
                    }
                }
            }

            modal.style.display = 'block';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) closeModal();
        }

        // Search filter
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const value = this.value.toLowerCase();
            document.querySelectorAll('#interviewTableBody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
            });
        });

        // Edit interview function
        function editInterview(id) {
            const interview = <?= json_encode($interviews) ?>.find(i => i.interview_id == id);
            if (interview) {
                openModal('update', {
                    interview_id: interview.interview_id,
                    employee_id: interview.employee_id,
                    exit_id: interview.exit_id,
                    interview_date: interview.interview_date,
                    feedback: interview.feedback,
                    improvement_suggestions: interview.improvement_suggestions,
                    reason_for_leaving: interview.reason_for_leaving,
                    status: interview.status
                });
            }
        }
    </script>
</body>
</html>
