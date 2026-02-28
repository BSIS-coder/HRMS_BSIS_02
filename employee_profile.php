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
require_once 'config.php';

// For backward compatibility with existing code
$pdo = $conn;

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new employee
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_profiles (personal_info_id, job_role_id, salary_grade_id, employee_number, hire_date, employment_status, current_salary, work_email, work_phone, location, remote_work) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['job_role_id'],
                        isset($_POST['salary_grade_id']) && $_POST['salary_grade_id'] != '' ? $_POST['salary_grade_id'] : null,
                        $_POST['employee_number'],
                        $_POST['hire_date'],
                        $_POST['employment_status'],
                        isset($_POST['current_salary']) ? $_POST['current_salary'] : 0,
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
                    $stmt = $pdo->prepare("UPDATE employee_profiles SET personal_info_id=?, job_role_id=?, salary_grade_id=?, employee_number=?, hire_date=?, employment_status=?, current_salary=?, work_email=?, work_phone=?, location=?, remote_work=? WHERE employee_id=?");
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['job_role_id'],
                        isset($_POST['salary_grade_id']) && $_POST['salary_grade_id'] != '' ? $_POST['salary_grade_id'] : null,
                        $_POST['employee_number'],
                        $_POST['hire_date'],
                        $_POST['employment_status'],
                        isset($_POST['current_salary']) ? $_POST['current_salary'] : 0,
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
                // Archive employee profile instead of permanent delete
                try {
                    $pdo->beginTransaction();
                    
                    // Fetch the complete employee record to be archived
                    $fetchStmt = $pdo->prepare("SELECT * FROM employee_profiles WHERE employee_id = ?");
                    $fetchStmt->execute([$_POST['employee_id']]);
                    $recordToArchive = $fetchStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($recordToArchive) {
                        // Get current user ID from session
                        $archived_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                        
                        // Get employee_id (which is the same as record_id in this case)
                        $employeeId = $recordToArchive['employee_id'];
                        
                        // Determine archive reason based on employment status
                        $archiveReason = 'Data Cleanup';
                        $archiveReasonDetails = 'Employee profile deleted by user';
                        
                        if ($recordToArchive['employment_status'] === 'Terminated') {
                            $archiveReason = 'Termination';
                            $archiveReasonDetails = 'Employee profile archived after termination';
                        }
                        
                        // Archive the record
                        $archiveStmt = $pdo->prepare("INSERT INTO archive_storage (
                            source_table, 
                            record_id, 
                            employee_id, 
                            archive_reason, 
                            archive_reason_details, 
                            archived_by, 
                            archived_at, 
                            can_restore, 
                            record_data, 
                            notes
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1, ?, ?)");
                        
                        $archiveStmt->execute([
                            'employee_profiles',
                            $recordToArchive['employee_id'],
                            $employeeId,
                            $archiveReason,
                            $archiveReasonDetails,
                            $archived_by,
                            json_encode($recordToArchive, JSON_PRETTY_PRINT),
                            'Employee profile archived on deletion. Employee Number: ' . ($recordToArchive['employee_number'] ?? 'N/A')
                        ]);
                        
                        // Delete from employee_profiles table
                        $deleteStmt = $pdo->prepare("DELETE FROM employee_profiles WHERE employee_id=?");
                        $deleteStmt->execute([$_POST['employee_id']]);
                        
                        $pdo->commit();
                        $message = "Employee profile archived successfully! You can view it in Archive Storage.";
                        $messageType = "success";
                    } else {
                        $pdo->rollBack();
                        $message = "Error: Employee profile record not found!";
                        $messageType = "error";
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Error archiving employee profile: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
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
        jr.department,
        sg.grade_name,
        sg.grade_level,
        sg.monthly_salary as grade_salary,
        sg.description as salary_grade_description
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN salary_grades sg ON ep.salary_grade_id = sg.grade_id
    ORDER BY ep.employee_id DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch personal information for dropdown
$stmt = $pdo->query("SELECT personal_info_id, CONCAT(first_name, ' ', last_name) as full_name FROM personal_information ORDER BY first_name");
$personalInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch job roles for dropdown
$stmt = $pdo->query("SELECT job_role_id, title, department FROM job_roles ORDER BY title");
$jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch salary grades for dropdown
$stmt = $pdo->query("SELECT grade_id, grade_name, grade_level, monthly_salary FROM salary_grades WHERE is_active = 1 ORDER BY grade_level ASC");
$salaryGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all personal information for viewing
$stmt = $pdo->query("
    SELECT pi.*,
           CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
           TIMESTAMPDIFF(YEAR, pi.date_of_birth, CURDATE()) as age
    FROM personal_information pi
    ORDER BY pi.personal_info_id DESC
");
$allPersonalInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employment history for viewing
$stmt = $pdo->query("
    SELECT 
        eh.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        d.department_name,
        CONCAT(pi2.first_name, ' ', pi2.last_name) as manager_name
    FROM employment_history eh
    LEFT JOIN employee_profiles ep ON eh.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN departments d ON eh.department_id = d.department_id
    LEFT JOIN employee_profiles ep2 ON eh.reporting_manager_id = ep2.employee_id
    LEFT JOIN personal_information pi2 ON ep2.personal_info_id = pi2.personal_info_id
    ORDER BY eh.employee_id, eh.start_date DESC
");
$allEmploymentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all documents for viewing
$stmt = $pdo->query("
    SELECT 
        dm.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        pi.first_name,
        pi.last_name,
        ep.employee_number,
        jr.title as job_title,
        jr.department,
        CASE 
            WHEN dm.expiry_date IS NOT NULL AND dm.expiry_date < CURDATE() THEN 'Expired'
            WHEN dm.expiry_date IS NOT NULL AND dm.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
            ELSE 'Current'
        END as expiry_status
    FROM document_management dm
    LEFT JOIN employee_profiles ep ON dm.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY dm.created_at DESC
");
$allDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for employee profile page */
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

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
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
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        .modal-content-large {
            max-width: 750px;
        }

        .nav-buttons-section {
            background: linear-gradient(135deg, var(--azure-blue-pale) 0%, #f0f0f0 100%);
            padding: 25px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid var(--azure-blue);
        }

        .nav-buttons-section h4 {
            color: var(--azure-blue-dark);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .nav-button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .nav-button-group .btn {
            flex: 1;
            min-width: 140px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .info-section {
            margin-bottom: 20px;
        }

        .info-section h5 {
            color: var(--azure-blue-dark);
            font-weight: 600;
            margin-bottom: 12px;
            border-bottom: 2px solid var(--azure-blue-lighter);
            padding-bottom: 8px;
        }

        .info-row {
            display: flex;
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: 600;
            color: var(--azure-blue-dark);
            min-width: 150px;
        }

        .info-value {
            color: #333;
        }

        /* Personal Information Grid Styling */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-item {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 16px;
            border-radius: 10px;
            border-left: 4px solid var(--azure-blue);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .info-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.15);
        }

        .info-item .info-label {
            font-weight: 700;
            color: var(--azure-blue-dark);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }

        .info-item .info-value {
            font-size: 15px;
            color: #333;
            word-break: break-word;
        }

        .section-divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--azure-blue-lighter), transparent);
            margin: 30px 0;
        }

        .section-header {
            font-size: 18px;
            font-weight: 700;
            color: var(--azure-blue-dark);
            margin: 20px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--azure-blue-lighter);
            display: inline-block;
        }

        .document-type-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
        }

        .expiry-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .expiry-current {
            background: #d4edda;
            color: #155724;
        }

        .expiry-expiring-soon {
            background: #fff3cd;
            color: #856404;
        }

        .expiry-expired {
            background: #f8d7da;
            color: #721c24;
        }

        /* Employment History Styling */
        .history-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid var(--azure-blue);
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .history-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 25px;
        }

        .history-section {
            padding: 20px;
            background: white;
            border-radius: 10px;
            border-top: 3px solid var(--azure-blue-lighter);
        }

        .history-section h4 {
            color: var(--azure-blue);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--azure-blue-lighter);
        }

        .history-item {
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .history-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .history-item strong {
            color: var(--azure-blue-dark);
            display: block;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .history-item p {
            margin: 0;
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }

        .history-full-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--azure-blue);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .history-full-section h4 {
            color: var(--azure-blue-dark);
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 12px 0;
            padding: 0;
            border: none;
        }

        .history-full-section p {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            line-height: 1.7;
            color: #555;
            margin: 0;
            font-size: 14px;
        }

        @media (max-width: 1024px) {
            .history-grid {
                grid-template-columns: 1fr;
            }
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

        .loading {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--azure-blue);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <h2 class="section-title">Employee Profile Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="searchInput" placeholder="Search employees by name, email, or employee number...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add')">
                                ➕ Add New Employee
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="employeeTable">
                                <thead>
                                    <tr>
                                        <th>Employee #</th>
                                        <th>Name</th>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Salary Grade</th>
                                        <th>Hire Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="employeeTableBody">
                                    <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($employee['employee_number']) ?></strong></td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($employee['full_name']) ?></strong><br>
                                                <small style="color: #666;">📞 <?= htmlspecialchars($employee['phone_number']) ?></small>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($employee['job_title']) ?></td>
                                        <td><?= htmlspecialchars($employee['department']) ?></td>
                                        <td><?= htmlspecialchars($employee['work_email']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($employee['employment_status']) === 'full-time' ? 'active' : 'inactive' ?>">
                                                <?= htmlspecialchars($employee['employment_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($employee['grade_name']): ?>
                                                <span style="background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%); padding: 5px 12px; border-radius: 20px; font-weight: 600; color: var(--azure-blue-dark); font-size: 14px;">
                                                    <?= htmlspecialchars($employee['grade_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($employee['hire_date'])) ?></td>
                                        <td>
                                            <button class="btn btn-info btn-small" onclick="viewEmployeeDetails(<?= $employee['employee_id'] ?>)" title="View full profile">
                                                👁️ View
                                            </button>
                                            <button class="btn btn-warning btn-small" onclick="editEmployee(<?= $employee['employee_id'] ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-primary btn-small" onclick="deleteEmployee(<?= $employee['employee_id'] ?>)">
                                                📦 Archive
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($employees)): ?>
                            <div class="no-results">
                                <i>👥</i>
                                <h3>No employees found</h3>
                                <p>Start by adding your first employee profile.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Employee Details Modal -->
    <div id="viewDetailsModal" class="modal">
        <div class="modal-content modal-content-large">
            <div class="modal-header">
                <h2 id="detailsTitle">Employee Details</h2>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Employee</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="employeeForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="employee_id" name="employee_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="personal_info_id">Personal Information</label>
                                <select id="personal_info_id" name="personal_info_id" class="form-control" required>
                                    <option value="">Select person...</option>
                                    <?php foreach ($personalInfo as $person): ?>
                                    <option value="<?= $person['personal_info_id'] ?>"><?= htmlspecialchars($person['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="job_role_id">Job Role</label>
                                <select id="job_role_id" name="job_role_id" class="form-control" required>
                                    <option value="">Select job role...</option>
                                    <?php foreach ($jobRoles as $role): ?>
                                    <option value="<?= $role['job_role_id'] ?>"><?= htmlspecialchars($role['title']) ?> (<?= htmlspecialchars($role['department']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_number">Employee Number</label>
                                <input type="text" id="employee_number" name="employee_number" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="hire_date">Hire Date</label>
                                <input type="date" id="hire_date" name="hire_date" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employment_status">Employment Status</label>
                                <select id="employment_status" name="employment_status" class="form-control" required>
                                    <option value="">Select status...</option>
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Intern">Intern</option>
                                    <option value="Terminated">Terminated</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="salary_grade_id">Salary Grade <span style="color: #999; font-size: 12px;">(Optional)</span></label>
                                <select id="salary_grade_id" name="salary_grade_id" class="form-control" onchange="updateSalaryFromGrade()">
                                    <option value="">Select salary grade...</option>
                                    <?php foreach ($salaryGrades as $grade): ?>
                                    <option value="<?= $grade['grade_id'] ?>" data-salary="<?= $grade['monthly_salary'] ?>"><?= htmlspecialchars($grade['grade_name']) ?> - Level <?= $grade['grade_level'] ?> (₱<?= number_format($grade['monthly_salary'], 2) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="current_salary">Current Salary</label>
                                <input type="number" id="current_salary" name="current_salary" class="form-control" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="work_email">Work Email</label>
                                <input type="email" id="work_email" name="work_email" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="work_phone">Work Phone</label>
                                <input type="tel" id="work_phone" name="work_phone" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control">
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="remote_work" name="remote_work">
                            <label for="remote_work">Remote Work Enabled</label>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let employeesData = <?= json_encode($employees) ?>;
        let personalInfoData = <?= json_encode($allPersonalInfo) ?>;
        let employmentHistoryData = <?= json_encode($allEmploymentHistory) ?>;
        let documentsData = <?= json_encode($allDocuments) ?>;
        let salaryGradesData = <?= json_encode($salaryGrades) ?>;
        let currentViewingEmployeeId = null;
        let currentViewingDocumentId = null;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('employeeTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Modal functions
        function openModal(mode, employeeId = null) {
            const modal = document.getElementById('employeeModal');
            const form = document.getElementById('employeeForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Employee';
                action.value = 'add';
                form.reset();
                document.getElementById('employee_id').value = '';
            } else if (mode === 'edit' && employeeId) {
                title.textContent = 'Edit Employee';
                action.value = 'update';
                document.getElementById('employee_id').value = employeeId;
                populateEditForm(employeeId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('employeeModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function closeDetailsModal() {
            const modal = document.getElementById('viewDetailsModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function viewEmployeeDetails(employeeId) {
            const employee = employeesData.find(emp => emp.employee_id == employeeId);
            if (!employee) return;

            currentViewingEmployeeId = employeeId;
            const modal = document.getElementById('viewDetailsModal');
            const title = document.getElementById('detailsTitle');
            const content = document.getElementById('detailsContent');

            title.textContent = `${employee.full_name} - Employee Profile`;

            const hireDate = new Date(employee.hire_date).toLocaleDateString('en-US', { 
                year: 'numeric', month: 'long', day: 'numeric' 
            });

            content.innerHTML = `
                <div class="info-section">
                    <h5>📋 Basic Information</h5>
                    <div class="info-row">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value">${employee.full_name}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Employee Number:</span>
                        <span class="info-value">${employee.employee_number}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value">${employee.work_email || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value">${employee.work_phone || 'N/A'}</span>
                    </div>
                </div>

                <div class="info-section">
                    <h5>💼 Employment Information</h5>
                    <div class="info-row">
                        <span class="info-label">Job Title:</span>
                        <span class="info-value">${employee.job_title || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Department:</span>
                        <span class="info-value">${employee.department || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value"><span class="status-badge status-${employee.employment_status.toLowerCase() === 'full-time' ? 'active' : 'inactive'}">${employee.employment_status}</span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Hire Date:</span>
                        <span class="info-value">${hireDate}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Location:</span>
                        <span class="info-value">${employee.location || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Remote Work:</span>
                        <span class="info-value">${employee.remote_work == 1 ? '✅ Enabled' : '❌ Not Enabled'}</span>
                    </div>
                </div>

                <div class="info-section">
                    <h5>💰 Salary & Compensation</h5>
                    <div class="info-row">
                        <span class="info-label">Salary Grade:</span>
                        <span class="info-value">${employee.grade_name ? `<span style="background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%); padding: 5px 12px; border-radius: 20px; font-weight: 600; color: var(--azure-blue-dark);">${employee.grade_name} (Level ${employee.grade_level})</span>` : 'Not Assigned'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Current Salary:</span>
                        <span class="info-value">₱ ${parseFloat(employee.current_salary).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    </div>
                    ${employee.grade_salary ? `<div class="info-row">
                        <span class="info-label">Grade Base Salary:</span>
                        <span class="info-value">₱ ${parseFloat(employee.grade_salary).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    </div>` : ''}
                </div>

                <div class="nav-buttons-section">
                    <h4>📁 Related Information</h4>
                    <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Access detailed records for this employee:</p>
                    <div class="nav-button-group">
                        <button onclick="viewPersonalInfo(${employee.personal_info_id})" class="btn btn-info" title="View personal information">
                            👤 Personal Information
                        </button>
                        <button onclick="viewEmploymentHistory(${employee.employee_id})" class="btn btn-success" title="View employment history">
                            📊 Employment History
                        </button>
                        <button onclick="viewDocumentsFromProfile(${employee.employee_id})" class="btn btn-primary" title="View documents">
                            📄 Documents
                        </button>
                    </div>
                </div>
            `;

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function populateEditForm(employeeId) {
            // This would typically fetch data via AJAX
            // For now, we'll use the existing data
            const employee = employeesData.find(emp => emp.employee_id == employeeId);
            if (employee) {
                document.getElementById('personal_info_id').value = employee.personal_info_id || '';
                document.getElementById('job_role_id').value = employee.job_role_id || '';
                document.getElementById('employee_number').value = employee.employee_number || '';
                document.getElementById('hire_date').value = employee.hire_date || '';
                document.getElementById('employment_status').value = employee.employment_status || '';
                document.getElementById('work_email').value = employee.work_email || '';
                document.getElementById('work_phone').value = employee.work_phone || '';
                document.getElementById('location').value = employee.location || '';
                document.getElementById('remote_work').checked = employee.remote_work == 1;
                document.getElementById('salary_grade_id').value = employee.salary_grade_id || '';
                document.getElementById('current_salary').value = employee.current_salary || '';
            }
        }

        // Function to update salary when grade is selected
        function updateSalaryFromGrade() {
            const gradeSelect = document.getElementById('salary_grade_id');
            const salaryInput = document.getElementById('current_salary');
            const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
            
            if (selectedOption && selectedOption.dataset.salary) {
                salaryInput.value = selectedOption.dataset.salary;
            }
        }

        function editEmployee(employeeId) {
            openModal('edit', employeeId);
        }

        function deleteEmployee(employeeId) {
            if (confirm('Are you sure you want to archive this employee profile? The record will be moved to Archive Storage and can be restored later.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="employee_id" value="${employeeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // View Personal Information for employee
        function viewPersonalInfo(personalInfoId) {
            const person = personalInfoData.find(p => p.personal_info_id == personalInfoId);
            if (!person) {
                alert('Personal information not found');
                return;
            }

            const modal = document.getElementById('viewDetailsModal');
            const title = document.getElementById('detailsTitle');
            const content = document.getElementById('detailsContent');

            title.textContent = `${person.full_name} - Personal Information`;

            let html = `
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value">${person.first_name} ${person.last_name}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value">${person.date_of_birth ? new Date(person.date_of_birth).toLocaleDateString() : 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Age</div>
                        <div class="info-value">${person.age} years</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <div class="info-value">${person.gender || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Nationality</div>
                        <div class="info-value">${person.nationality || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value">${person.phone_number || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tax ID</div>
                        <div class="info-value">${person.tax_id || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">GSIS ID</div>
                        <div class="info-value">${person.gsis_id || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Pag-IBIG ID</div>
                        <div class="info-value">${person.pag_ibig_id || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">PhilHealth ID</div>
                        <div class="info-value">${person.philhealth_id || 'N/A'}</div>
                    </div>
                </div>

                <div class="section-divider"></div>
                <div class="section-header">💍 Marital Status</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">${person.marital_status || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status Date</div>
                        <div class="info-value">${person.marital_status_date ? new Date(person.marital_status_date).toLocaleDateString() : 'N/A'}</div>
                    </div>
                </div>

                <div class="section-divider"></div>
                <div class="section-header">🚨 Emergency Contact</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Name</div>
                        <div class="info-value">${person.emergency_contact_name || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Relationship</div>
                        <div class="info-value">${person.emergency_contact_relationship || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value">${person.emergency_contact_phone || 'N/A'}</div>
                    </div>
                </div>

                <div class="section-divider"></div>
                <div class="section-header">🎓 Education</div>
                ${person.highest_education_level ? `
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Highest Attainment</div>
                            <div class="info-value">${person.highest_education_level}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Course/Degree</div>
                            <div class="info-value">${person.field_of_study || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">School/University</div>
                            <div class="info-value">${person.institution_name || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Year Graduated</div>
                            <div class="info-value">${person.graduation_year || 'N/A'}</div>
                        </div>
                    </div>
                ` : '<p>No education information recorded.</p>'}

                ${person.certifications ? `
                    <div class="section-divider"></div>
                    <div class="section-header">🏆 Professional Certifications</div>
                    <div class="info-item">
                        <div style="line-height: 1.8; color: #333;">
                            ${person.certifications.split(',').map(cert => '<div>• ' + cert.trim() + '</div>').join('')}
                        </div>
                    </div>
                ` : ''}

                <div class="nav-buttons-section">
                    <h4>📋 Related Information</h4>
                    <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Access related records for this person:</p>
                    <div class="nav-button-group">
                        <button onclick="viewEmploymentHistory(${currentViewingEmployeeId})" class="btn btn-success" title="View employment history">
                            📊 Employment History
                        </button>
                        <button onclick="viewDocumentsFromProfile(${currentViewingEmployeeId})" class="btn btn-primary" title="View documents">
                            📄 Documents
                        </button>
                        <button onclick="backToEmployeeProfile()" class="btn btn-info" title="Back to employee profile">
                            👨‍💼 Back to Profile
                        </button>
                    </div>
                </div>
            `;

            content.innerHTML = html;
        }

        // View Employment History for employee
        function viewEmploymentHistory(employeeId) {
            const histories = employmentHistoryData.filter(h => h.employee_id == employeeId);
            
            if (histories.length === 0) {
                alert('No employment history found for this employee');
                return;
            }

            const modal = document.getElementById('viewDetailsModal');
            const title = document.getElementById('detailsTitle');
            const content = document.getElementById('detailsContent');

            const firstHistory = histories[0];
            title.textContent = `${firstHistory.employee_name} - Employment History`;

            let html = '';
            
            histories.forEach((history, index) => {
                const startDate = new Date(history.start_date).toLocaleDateString('en-US', { 
                    year: 'numeric', month: 'long', day: 'numeric' 
                });
                const endDate = history.end_date ? 
                    new Date(history.end_date).toLocaleDateString('en-US', { 
                        year: 'numeric', month: 'long', day: 'numeric' 
                    }) : 'Present';

                if (index === 0) {
                    html += `
                        <div class="history-container">
                            <div class="history-grid">
                                <div class="history-section">
                                    <h4>📋 Basic Information</h4>
                                    <div class="history-item"><strong>Employee</strong><p>${history.employee_name || 'N/A'}</p></div>
                                    <div class="history-item"><strong>Employee Number</strong><p>#${history.employee_number || 'N/A'}</p></div>
                                    <div class="history-item"><strong>Job Title</strong><p>${history.job_title || 'N/A'}</p></div>
                                    <div class="history-item"><strong>Salary Grade</strong><p>${history.salary_grade || 'N/A'}</p></div>
                                    <div class="history-item"><strong>Department</strong><p>${history.department_name || 'N/A'}</p></div>
                                    <div class="history-item"><strong>Employment Type</strong><p>${history.employment_type || 'N/A'}</p></div>
                                    <div class="history-item"><strong>Employment Period</strong><p>${startDate} - ${endDate}</p></div>
                                    <div class="history-item"><strong>Status</strong><p><span class="status-badge status-${(history.employment_status || '').toLowerCase()}">${history.employment_status || 'N/A'}</span></p></div>
                                    <div class="history-item"><strong>Promotion Type</strong><p>${history.promotion_type || 'N/A'}</p></div>
                                    <div class="history-item"><strong>Position Sequence</strong><p>#${history.position_sequence || '1'}</p></div>
                                    <div class="history-item"><strong>Current Position</strong><p>${history.is_current_position ? '✓ Yes' : 'No'}</p></div>
                                    <div class="history-item"><strong>Location</strong><p>${history.location || 'N/A'}</p></div>
                                    <div class="history-item"><strong>Reporting Manager</strong><p>${history.manager_name || 'N/A'}</p></div>
                                </div>
                                <div class="history-section">
                                    <h4>💰 Compensation & Salary History</h4>
                                    <div class="history-item"><strong>Base Salary</strong><p>₱${parseFloat(history.base_salary || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p></div>
                                    <div class="history-item"><strong>Previous Salary</strong><p>${history.previous_salary ? '₱' + parseFloat(history.previous_salary).toLocaleString('en-US', {minimumFractionDigits: 2}) : 'N/A'}</p></div>
                                    ${history.salary_increase_amount ? `<div class="history-item"><strong>Salary Increase</strong><p>₱${parseFloat(history.salary_increase_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})} (${parseFloat(history.salary_increase_percentage || 0).toFixed(2)}%)</p></div>` : ''}
                                    <div class="history-item"><strong>Allowances</strong><p>₱${parseFloat(history.allowances || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p></div>
                                    <div class="history-item"><strong>Bonuses</strong><p>₱${parseFloat(history.bonuses || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p></div>
                                    <div class="history-item"><strong>Salary Adjustments</strong><p>₱${parseFloat(history.salary_adjustments || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p></div>
                                    <div class="history-item"><strong>Total Compensation</strong><p style="color: var(--azure-blue); font-weight: 700;">₱${(parseFloat(history.base_salary || 0) + parseFloat(history.allowances || 0) + parseFloat(history.bonuses || 0) + parseFloat(history.salary_adjustments || 0)).toLocaleString('en-US', {minimumFractionDigits: 2})}</p></div>
                                    <div class="history-item"><strong>Salary Effective Date</strong><p>${history.salary_effective_date ? new Date(history.salary_effective_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p></div>
                                    <div class="history-item"><strong>Reason for Change</strong><p>${history.reason_for_change || 'N/A'}</p></div>
                                </div>
                            </div>
                            
                            ${history.duties_responsibilities ? `
                                <div class="history-full-section">
                                    <h4>📝 Duties & Responsibilities</h4>
                                    <p>${history.duties_responsibilities}</p>
                                </div>
                            ` : ''}

                            ${history.performance_evaluations || history.training_certifications ? `
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                    ${history.performance_evaluations ? `
                                        <div class="history-full-section">
                                            <h4>⭐ Performance Evaluations</h4>
                                            <p>${history.performance_evaluations}</p>
                                        </div>
                                    ` : ''}
                                    
                                    ${history.training_certifications ? `
                                        <div class="history-full-section">
                                            <h4>🎓 Training & Certifications</h4>
                                            <p>${history.training_certifications}</p>
                                        </div>
                                    ` : ''}
                                </div>
                            ` : ''}

                            ${history.contract_details || history.remarks ? `
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    ${history.contract_details ? `
                                        <div class="history-full-section">
                                            <h4>📋 Contract Details</h4>
                                            <p>${history.contract_details}</p>
                                        </div>
                                    ` : ''}
                                    
                                    ${history.remarks ? `
                                        <div class="history-full-section">
                                            <h4>📌 Remarks</h4>
                                            <p>${history.remarks}</p>
                                        </div>
                                    ` : ''}
                                </div>
                            ` : ''}
                        </div>
                    `;
                }
            });

            // Get personal_info_id from employee data
            const employee = employeesData.find(e => e.employee_id == employeeId);
            const personalInfoId = employee ? employee.personal_info_id : 0;

            html += `
                <div class="nav-buttons-section">
                    <h4>📋 Related Information</h4>
                    <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Access related records:</p>
                    <div class="nav-button-group">
                        <a href="personal_information.php?personal_info_id=${personalInfoId}" class="btn btn-info" title="View personal information">
                            👤 Personal Information
                        </a>
                        <button onclick="viewDocumentsFromProfile(${employeeId})" class="btn btn-primary" title="View documents">
                            📄 Documents
                        </button>
                        <button onclick="backToEmployeeProfile()" class="btn btn-success" title="Back to employee profile">
                            👨‍💼 Back to Profile
                        </button>
                    </div>
                </div>
            `;

            content.innerHTML = html;
        }

        // View Documents for employee
        function viewDocumentsFromProfile(employeeId) {
            const docs = documentsData.filter(d => d.employee_id == employeeId);
            const modal = document.getElementById('viewDetailsModal');
            const title = document.getElementById('detailsTitle');
            const content = document.getElementById('detailsContent');

            title.textContent = `Employee Documents`;

            if (docs.length === 0) {
                content.innerHTML = `
                    <div style="padding: 30px; text-align: center;">
                        <p style="color: #666; font-size: 16px;">No documents found for this employee.</p>
                        <div class="nav-buttons-section" style="margin-top: 30px;">
                            <h4>📂 Back to Employee</h4>
                            <div class="nav-button-group">
                                <button onclick="viewPersonalInfo(${employeesData.find(e => e.employee_id == employeeId)?.personal_info_id})" class="btn btn-info">👤 Personal Info</button>
                                <button onclick="viewEmploymentHistory(${employeeId})" class="btn btn-success">📊 Employment History</button>
                                <button onclick="backToEmployeeProfile()" class="btn btn-primary">👨‍💼 Back to Profile</button>
                            </div>
                        </div>
                    </div>
                `;
                modal.style.display = 'block';
                return;
            }

            let html = '<div style="padding: 20px;">';
            
            docs.forEach(doc => {
                const createDate = new Date(doc.created_at).toLocaleDateString('en-US', { 
                    year: 'numeric', month: 'short', day: 'numeric' 
                });
                const expiryDate = doc.expiry_date ? 
                    new Date(doc.expiry_date).toLocaleDateString('en-US', { 
                        year: 'numeric', month: 'short', day: 'numeric' 
                    }) : 'No Expiry';

                const typeClass = 'type-' + doc.document_type.toLowerCase().replace(/ /g, '-');
                const statusClass = 'status-' + doc.document_status.toLowerCase();
                const expiryClass = 'expiry-' + doc.expiry_status.toLowerCase().replace(/ /g, '-');

                html += `
                <div style="background: white; padding: 15px; margin-bottom: 15px; border-radius: 8px; border-left: 4px solid var(--azure-blue);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div>
                            <h5 style="color: var(--azure-blue-dark); margin: 0 0 5px 0; font-weight: 600;">${doc.document_name}</h5>
                            <span class="document-type-badge ${typeClass}">${doc.document_type}</span>
                            <span class="status-badge ${statusClass}" style="margin-left: 8px;">${doc.document_status}</span>
                        </div>
                        <span class="expiry-badge expiry-${expiryClass}">${doc.expiry_status}</span>
                    </div>
                    <div style="font-size: 13px; color: #666; margin-top: 10px;">
                        <div><strong>Created:</strong> ${createDate}</div>
                        <div><strong>Expiry:</strong> ${expiryDate}</div>
                        ${doc.file_path ? `<div><strong>File:</strong> <a href="${doc.file_path}" target="_blank" class="download-link">📥 Download</a></div>` : ''}
                    </div>
                </div>
                `;
            });

            const employee = employeesData.find(e => e.employee_id == employeeId);
            const personalInfoId = employee ? employee.personal_info_id : 0;

            html += `
                <div class="nav-buttons-section">
                    <h4>📂 Back to Employee</h4>
                    <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Navigate to related information:</p>
                    <div class="nav-button-group">
                        <button onclick="viewPersonalInfo(${personalInfoId})" class="btn btn-info" title="View personal information">
                            👤 Personal Info
                        </button>
                        <button onclick="viewEmploymentHistory(${employeeId})" class="btn btn-success" title="View employment history">
                            📊 Employment History
                        </button>
                        <button onclick="backToEmployeeProfile()" class="btn btn-primary" title="Back to employee profile">
                            👨‍💼 Back to Profile
                        </button>
                    </div>
                </div>
            </div>
            `;

            content.innerHTML = html;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Back to Employee Profile
        function backToEmployeeProfile() {
            if (currentViewingEmployeeId) {
                viewEmployeeDetails(currentViewingEmployeeId);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const employeeModal = document.getElementById('employeeModal');
            const detailsModal = document.getElementById('viewDetailsModal');
            
            if (event.target === employeeModal) {
                closeModal();
            }
            if (event.target === detailsModal) {
                closeDetailsModal();
            }
        }

        // Form validation
        document.getElementById('employeeForm').addEventListener('submit', function(e) {
            const email = document.getElementById('work_email').value;
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#employeeTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });


        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
