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
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new employment history
                try {
                    $stmt = $pdo->prepare("INSERT INTO employment_history 
                        (employee_id, job_title, department_id, employment_type, start_date, end_date, 
                         employment_status, reporting_manager_id, location, base_salary, allowances, 
                         bonuses, salary_adjustments, reason_for_change, promotions_transfers, 
                         duties_responsibilities, performance_evaluations, training_certifications, 
                         contract_details, remarks) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
                    $manager_id = !empty($_POST['reporting_manager_id']) ? $_POST['reporting_manager_id'] : null;
                    $dept_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
                    
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['job_title'],
                        $dept_id,
                        $_POST['employment_type'],
                        $_POST['start_date'],
                        $end_date,
                        $_POST['employment_status'],
                        $manager_id,
                        $_POST['location'],
                        $_POST['base_salary'],
                        $_POST['allowances'] ?: 0,
                        $_POST['bonuses'] ?: 0,
                        $_POST['salary_adjustments'] ?: 0,
                        $_POST['reason_for_change'],
                        $_POST['promotions_transfers'],
                        $_POST['duties_responsibilities'],
                        $_POST['performance_evaluations'],
                        $_POST['training_certifications'],
                        $_POST['contract_details'],
                        $_POST['remarks']
                    ]);
                    $message = "Employment history record added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding employment history: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update employment history
                try {
                    $stmt = $pdo->prepare("UPDATE employment_history SET 
                        employee_id=?, job_title=?, department_id=?, employment_type=?, start_date=?, 
                        end_date=?, employment_status=?, reporting_manager_id=?, location=?, 
                        base_salary=?, allowances=?, bonuses=?, salary_adjustments=?, reason_for_change=?, 
                        promotions_transfers=?, duties_responsibilities=?, performance_evaluations=?, 
                        training_certifications=?, contract_details=?, remarks=? 
                        WHERE history_id=?");
                    
                    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
                    $manager_id = !empty($_POST['reporting_manager_id']) ? $_POST['reporting_manager_id'] : null;
                    $dept_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
                    
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['job_title'],
                        $dept_id,
                        $_POST['employment_type'],
                        $_POST['start_date'],
                        $end_date,
                        $_POST['employment_status'],
                        $manager_id,
                        $_POST['location'],
                        $_POST['base_salary'],
                        $_POST['allowances'] ?: 0,
                        $_POST['bonuses'] ?: 0,
                        $_POST['salary_adjustments'] ?: 0,
                        $_POST['reason_for_change'],
                        $_POST['promotions_transfers'],
                        $_POST['duties_responsibilities'],
                        $_POST['performance_evaluations'],
                        $_POST['training_certifications'],
                        $_POST['contract_details'],
                        $_POST['remarks'],
                        $_POST['history_id']
                    ]);
                    $message = "Employment history updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating employment history: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete employment history
                try {
                    $stmt = $pdo->prepare("DELETE FROM employment_history WHERE history_id=?");
                    $stmt->execute([$_POST['history_id']]);
                    $message = "Employment history record deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting employment history: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch employment history with related data
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
    ORDER BY eh.start_date DESC, eh.history_id DESC
");
$employmentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        ep.employee_number,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name 
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch departments for dropdown
$stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch managers (employees who can be managers)
$managers = $employees; // Same as employees for simplicity
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employment History Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Custom styles for employment history page */
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
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: top;
        }

        .table tbody tr:hover {
            background-color: var(--azure-blue-lighter);
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

        .status-resigned, .status-transferred {
            background: #f8d7da;
            color: #721c24;
        }

        .duration-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
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
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 95%;
            max-width: 900px;
            max-height: 95vh;
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

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        .form-col-3 {
            flex: 0 0 33.333%;
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
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

        .employment-timeline {
            position: relative;
            padding-left: 20px;
        }

        .timeline-item {
            border-left: 3px solid var(--azure-blue-light);
            padding-left: 15px;
            margin-bottom: 10px;
        }

        .timeline-current {
            border-left-color: #28a745;
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

            .modal-content {
                width: 98%;
                margin: 1% auto;
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
                <h2 class="section-title">Employment History Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by employee name, job title, or department...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add Employment History
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="historyTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Job Title</th>
                                    <th>Department</th>
                                    <th>Employment Type</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Salary</th>
                                    <th>Manager</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <?php foreach ($employmentHistory as $history): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($history['employee_name']) ?></strong><br>
                                            <small style="color: #666;">#<?= htmlspecialchars($history['employee_number']) ?></small>
                                        </div>
                                    </td>
                                    <td><strong><?= htmlspecialchars($history['job_title']) ?></strong></td>
                                    <td><?= htmlspecialchars($history['department_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($history['employment_type']) ?></td>
                                    <td>
                                        <div>
                                            <strong><?= date('M d, Y', strtotime($history['start_date'])) ?></strong><br>
                                            <small style="color: #666;">
                                                to <?= $history['end_date'] ? date('M d, Y', strtotime($history['end_date'])) : 'Present' ?>
                                            </small>
                                            <?php if (!$history['end_date']): ?>
                                                <div class="duration-badge">Current Position</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($history['employment_status']) ?>">
                                            <?= htmlspecialchars($history['employment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong>₱<?= number_format($history['base_salary'], 2) ?></strong>
                                        <?php if ($history['allowances'] > 0): ?>
                                            <br><small style="color: #666;">+₱<?= number_format($history['allowances'], 2) ?> allowances</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($history['manager_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editHistory(<?= $history['history_id'] ?>)" title="Edit History">
                                            ✏️
                                        </button>
                                        <button class="btn btn-primary btn-small" onclick="viewDetails(<?= $history['history_id'] ?>)" title="View Details">
                                            👁️
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteHistory(<?= $history['history_id'] ?>)" title="Delete History">
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($employmentHistory)): ?>
                        <div class="no-results">
                            <i class="fas fa-history"></i>
                            <h3>No employment history found</h3>
                            <p>Start by adding the first employment history record.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Employment History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Employment History</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="historyForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="history_id" name="history_id">

                    <!-- Basic Information -->
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee *</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select employee...</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>"><?= htmlspecialchars($employee['full_name']) ?> (#<?= htmlspecialchars($employee['employee_number']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="job_title">Job Title *</label>
                                <input type="text" id="job_title" name="job_title" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="department_id">Department</label>
                                <select id="department_id" name="department_id" class="form-control">
                                    <option value="">Select department...</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employment_type">Employment Type *</label>
                                <select id="employment_type" name="employment_type" class="form-control" required>
                                    <option value="">Select type...</option>
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Intern">Intern</option>
                                    <option value="Casual">Casual</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Period -->
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="form-control">
                                <small style="color: #666;">Leave blank for current position</small>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employment_status">Status *</label>
                                <select id="employment_status" name="employment_status" class="form-control" required>
                                    <option value="">Select status...</option>
                                    <option value="Active">Active</option>
                                    <option value="Resigned">Resigned</option>
                                    <option value="Transferred">Transferred</option>
                                    <option value="Terminated">Terminated</option>
                                    <option value="Retired">Retired</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Compensation -->
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="base_salary">Base Salary (₱) *</label>
                                <input type="number" id="base_salary" name="base_salary" class="form-control" step="0.01" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="allowances">Allowances (₱)</label>
                                <input type="number" id="allowances" name="allowances" class="form-control" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="bonuses">Bonuses (₱)</label>
                                <input type="number" id="bonuses" name="bonuses" class="form-control" step="0.01" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="salary_adjustments">Salary Adjustments (₱)</label>
                                <input type="number" id="salary_adjustments" name="salary_adjustments" class="form-control" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="reporting_manager_id">Reporting Manager</label>
                                <select id="reporting_manager_id" name="reporting_manager_id" class="form-control">
                                    <option value="">Select manager...</option>
                                    <?php foreach ($managers as $manager): ?>
                                    <option value="<?= $manager['employee_id'] ?>"><?= htmlspecialchars($manager['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Location and Details -->
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control" placeholder="e.g., City Hall - 1st Floor">
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="reason_for_change">Reason for Change</label>
                                <input type="text" id="reason_for_change" name="reason_for_change" class="form-control" placeholder="e.g., Promotion, Transfer, New Hire">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="promotions_transfers">Promotions/Transfers</label>
                                <input type="text" id="promotions_transfers" name="promotions_transfers" class="form-control" placeholder="Details of promotions or transfers">
                            </div>
                        </div>
                    </div>

                    <!-- Text Areas -->
                    <div class="form-group">
                        <label for="duties_responsibilities">Duties & Responsibilities</label>
                        <textarea id="duties_responsibilities" name="duties_responsibilities" class="form-control" rows="3" placeholder="Describe key duties and responsibilities..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="performance_evaluations">Performance Evaluations</label>
                                <textarea id="performance_evaluations" name="performance_evaluations" class="form-control" rows="2" placeholder="Performance ratings and evaluations..."></textarea>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="training_certifications">Training & Certifications</label>
                                <textarea id="training_certifications" name="training_certifications" class="form-control" rows="2" placeholder="List relevant training and certifications..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="contract_details">Contract Details</label>
                                <textarea id="contract_details" name="contract_details" class="form-control" rows="2" placeholder="Contract terms and conditions..."></textarea>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="remarks">Remarks</label>
                                <textarea id="remarks" name="remarks" class="form-control" rows="2" placeholder="Additional notes or remarks..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Employment History</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Employment History Details</h2>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let historyData = <?= json_encode($employmentHistory) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('historyTableBody');
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
        function openModal(mode, historyId = null) {
            const modal = document.getElementById('historyModal');
            const form = document.getElementById('historyForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add Employment History';
                action.value = 'add';
                form.reset();
                document.getElementById('history_id').value = '';
                // Set default values
                document.getElementById('allowances').value = '0';
                document.getElementById('bonuses').value = '0';
                document.getElementById('salary_adjustments').value = '0';
            } else if (mode === 'edit' && historyId) {
                title.textContent = 'Edit Employment History';
                action.value = 'update';
                document.getElementById('history_id').value = historyId;
                populateEditForm(historyId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('historyModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function closeDetailsModal() {
            const modal = document.getElementById('detailsModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(historyId) {
            const history = historyData.find(h => h.history_id == historyId);
            if (history) {
                document.getElementById('employee_id').value = history.employee_id || '';
                document.getElementById('job_title').value = history.job_title || '';
                document.getElementById('department_id').value = history.department_id || '';
                document.getElementById('employment_type').value = history.employment_type || '';
                document.getElementById('start_date').value = history.start_date || '';
                document.getElementById('end_date').value = history.end_date || '';
                document.getElementById('employment_status').value = history.employment_status || '';
                document.getElementById('reporting_manager_id').value = history.reporting_manager_id || '';
                document.getElementById('location').value = history.location || '';
                document.getElementById('base_salary').value = history.base_salary || '';
                document.getElementById('allowances').value = history.allowances || '0';
                document.getElementById('bonuses').value = history.bonuses || '0';
                document.getElementById('salary_adjustments').value = history.salary_adjustments || '0';
                document.getElementById('reason_for_change').value = history.reason_for_change || '';
                document.getElementById('promotions_transfers').value = history.promotions_transfers || '';
                document.getElementById('duties_responsibilities').value = history.duties_responsibilities || '';
                document.getElementById('performance_evaluations').value = history.performance_evaluations || '';
                document.getElementById('training_certifications').value = history.training_certifications || '';
                document.getElementById('contract_details').value = history.contract_details || '';
                document.getElementById('remarks').value = history.remarks || '';
            }
        }

        function editHistory(historyId) {
            openModal('edit', historyId);
        }

        function viewDetails(historyId) {
            const history = historyData.find(h => h.history_id == historyId);
            if (!history) return;

            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('detailsContent');
            
            const startDate = new Date(history.start_date).toLocaleDateString('en-US', { 
                year: 'numeric', month: 'long', day: 'numeric' 
            });
            const endDate = history.end_date ? 
                new Date(history.end_date).toLocaleDateString('en-US', { 
                    year: 'numeric', month: 'long', day: 'numeric' 
                }) : 'Present';

            content.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                    <div>
                        <h4 style="color: var(--azure-blue); margin-bottom: 15px;">Basic Information</h4>
                        <p><strong>Employee:</strong> ${history.employee_name || 'N/A'}</p>
                        <p><strong>Employee Number:</strong> #${history.employee_number || 'N/A'}</p>
                        <p><strong>Job Title:</strong> ${history.job_title || 'N/A'}</p>
                        <p><strong>Department:</strong> ${history.department_name || 'N/A'}</p>
                        <p><strong>Employment Type:</strong> ${history.employment_type || 'N/A'}</p>
                        <p><strong>Employment Period:</strong> ${startDate} - ${endDate}</p>
                        <p><strong>Status:</strong> <span class="status-badge status-${(history.employment_status || '').toLowerCase()}">${history.employment_status || 'N/A'}</span></p>
                        <p><strong>Location:</strong> ${history.location || 'N/A'}</p>
                        <p><strong>Reporting Manager:</strong> ${history.manager_name || 'N/A'}</p>
                    </div>
                    <div>
                        <h4 style="color: var(--azure-blue); margin-bottom: 15px;">Compensation Details</h4>
                        <p><strong>Base Salary:</strong> ₱${parseFloat(history.base_salary || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Allowances:</strong> ₱${parseFloat(history.allowances || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Bonuses:</strong> ₱${parseFloat(history.bonuses || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Salary Adjustments:</strong> ₱${parseFloat(history.salary_adjustments || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Total Compensation:</strong> ₱${(parseFloat(history.base_salary || 0) + parseFloat(history.allowances || 0) + parseFloat(history.bonuses || 0) + parseFloat(history.salary_adjustments || 0)).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                        <p><strong>Reason for Change:</strong> ${history.reason_for_change || 'N/A'}</p>
                        <p><strong>Promotions/Transfers:</strong> ${history.promotions_transfers || 'N/A'}</p>
                    </div>
                </div>
                
                ${history.duties_responsibilities ? `
                    <div style="margin-bottom: 20px;">
                        <h4 style="color: var(--azure-blue); margin-bottom: 10px;">Duties & Responsibilities</h4>
                        <p style="background: #f8f9fa; padding: 15px; border-radius: 8px; line-height: 1.6;">${history.duties_responsibilities}</p>
                    </div>
                ` : ''}

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    ${history.performance_evaluations ? `
                        <div>
                            <h4 style="color: var(--azure-blue); margin-bottom: 10px;">Performance Evaluations</h4>
                            <p style="background: #f8f9fa; padding: 15px; border-radius: 8px; line-height: 1.6;">${history.performance_evaluations}</p>
                        </div>
                    ` : ''}
                    
                    ${history.training_certifications ? `
                        <div>
                            <h4 style="color: var(--azure-blue); margin-bottom: 10px;">Training & Certifications</h4>
                            <p style="background: #f8f9fa; padding: 15px; border-radius: 8px; line-height: 1.6;">${history.training_certifications}</p>
                        </div>
                    ` : ''}
                </div>

                ${history.contract_details || history.remarks ? `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                        ${history.contract_details ? `
                            <div>
                                <h4 style="color: var(--azure-blue); margin-bottom: 10px;">Contract Details</h4>
                                <p style="background: #f8f9fa; padding: 15px; border-radius: 8px; line-height: 1.6;">${history.contract_details}</p>
                            </div>
                        ` : ''}
                        
                        ${history.remarks ? `
                            <div>
                                <h4 style="color: var(--azure-blue); margin-bottom: 10px;">Remarks</h4>
                                <p style="background: #f8f9fa; padding: 15px; border-radius: 8px; line-height: 1.6;">${history.remarks}</p>
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
            `;

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function deleteHistory(historyId) {
            if (confirm('Are you sure you want to delete this employment history record? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="history_id" value="${historyId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const historyModal = document.getElementById('historyModal');
            const detailsModal = document.getElementById('detailsModal');
            if (event.target === historyModal) {
                closeModal();
            } else if (event.target === detailsModal) {
                closeDetailsModal();
            }
        }

        // Form validation
        document.getElementById('historyForm').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (endDate && startDate && new Date(endDate) <= new Date(startDate)) {
                e.preventDefault();
                alert('End date must be after start date');
                return;
            }

            const salary = parseFloat(document.getElementById('base_salary').value);
            if (salary <= 0) {
                e.preventDefault();
                alert('Base salary must be greater than 0');
                return;
            }

            // Validate allowances, bonuses, and adjustments are not negative
            const allowances = parseFloat(document.getElementById('allowances').value) || 0;
            const bonuses = parseFloat(document.getElementById('bonuses').value) || 0;
            const adjustments = parseFloat(document.getElementById('salary_adjustments').value) || 0;
            
            if (allowances < 0 || bonuses < 0) {
                e.preventDefault();
                alert('Allowances and bonuses cannot be negative');
                return;
            }
        });

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

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#historyTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Set max date for end_date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('end_date').max = today;
        });

        // Auto-populate employee dropdown change
        document.getElementById('employee_id').addEventListener('change', function() {
            const selectedEmployeeId = this.value;
            if (selectedEmployeeId) {
                // You could add AJAX call here to get employee's current position details
                // For now, we'll just clear the job title to encourage manual entry
            }
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>