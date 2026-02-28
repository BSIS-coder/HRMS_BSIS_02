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

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit;
}

// Include database connection
require_once 'config.php';

// Use existing connection
$pdo = $conn;

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO salary_grades 
                        (grade_name, grade_level, step_number, monthly_salary, description, effective_date, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['grade_name'],
                        $_POST['grade_level'],
                        $_POST['step_number'],
                        $_POST['monthly_salary'],
                        $_POST['description'],
                        $_POST['effective_date'],
                        isset($_POST['is_active']) ? 1 : 0
                    ]);
                    $message = "Salary grade added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding salary grade: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE salary_grades 
                        SET grade_name=?, grade_level=?, step_number=?, monthly_salary=?, description=?, effective_date=?, is_active=? 
                        WHERE grade_id=?
                    ");
                    $stmt->execute([
                        $_POST['grade_name'],
                        $_POST['grade_level'],
                        $_POST['step_number'],
                        $_POST['monthly_salary'],
                        $_POST['description'],
                        $_POST['effective_date'],
                        isset($_POST['is_active']) ? 1 : 0,
                        $_POST['grade_id']
                    ]);
                    $message = "Salary grade updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating salary grade: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                try {
                    // Check if grade is being used
                    $checkStmt = $pdo->prepare("
                        SELECT COUNT(*) as count FROM employee_profiles 
                        WHERE salary_grade_id = ?
                    ");
                    $checkStmt->execute([$_POST['grade_id']]);
                    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['count'] > 0) {
                        $message = "Cannot delete this salary grade as it is assigned to " . $result['count'] . " employee(s).";
                        $messageType = "error";
                    } else {
                        $deleteStmt = $pdo->prepare("DELETE FROM salary_grades WHERE grade_id=?");
                        $deleteStmt->execute([$_POST['grade_id']]);
                        $message = "Salary grade deleted successfully!";
                        $messageType = "success";
                    }
                } catch (PDOException $e) {
                    $message = "Error deleting salary grade: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch all salary grades
try {
    $stmt = $pdo->query("
        SELECT * FROM salary_grades 
        ORDER BY grade_level ASC, step_number ASC
    ");
    $salaryGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $salaryGrades = [];
    $message = "Error fetching salary grades: " . $e->getMessage();
    $messageType = "error";
}

// Fetch salary grade history
try {
    $stmt = $pdo->query("
        SELECT 
            sgh.*,
            CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
            ep.employee_number,
            sg.grade_name as grade_name,
            sg_prev.grade_name as previous_grade_name,
            CONCAT(u.first_name, ' ', u.last_name) as approved_by_name
        FROM salary_grade_history sgh
        LEFT JOIN employee_profiles ep ON sgh.employee_id = ep.employee_id
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN salary_grades sg ON sgh.salary_grade_id = sg.grade_id
        LEFT JOIN salary_grades sg_prev ON sgh.previous_grade_id = sg_prev.grade_id
        LEFT JOIN users u ON sgh.approved_by = u.user_id
        ORDER BY sgh.created_at DESC
    ");
    $salaryGradeHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $salaryGradeHistory = [];
}

// Fetch employees with salary grades
try {
    $stmt = $pdo->query("
        SELECT 
            ep.employee_id,
            ep.employee_number,
            CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
            jr.title as job_title,
            COALESCE(d.department_name, jr.department) as department_name,
            sg.grade_name,
            sg.grade_level,
            ep.current_salary,
            sg.monthly_salary as grade_salary,
            ep.employment_status
        FROM employee_profiles ep
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_name
        LEFT JOIN salary_grades sg ON ep.salary_grade_id = sg.grade_id
        WHERE ep.employment_status NOT IN ('Terminated', 'Resigned')
        ORDER BY ep.employee_number ASC
    ");
    $employeesWithGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employeesWithGrades = [];
}

// Fetch job roles with highest salary
try {
    $stmt = $pdo->query("
        SELECT 
            jr.job_role_id,
            jr.title as job_title,
            jr.department,
            jr.max_salary,
            jr.min_salary,
            COUNT(ep.employee_id) as total_employees,
            AVG(ep.current_salary) as avg_employee_salary,
            MAX(ep.current_salary) as max_employee_salary,
            GROUP_CONCAT(DISTINCT sg.grade_name) as salary_grades
        FROM job_roles jr
        LEFT JOIN employee_profiles ep ON jr.job_role_id = ep.job_role_id AND ep.employment_status NOT IN ('Terminated', 'Resigned')
        LEFT JOIN salary_grades sg ON ep.salary_grade_id = sg.grade_id
        GROUP BY jr.job_role_id, jr.title, jr.department, jr.max_salary, jr.min_salary
        ORDER BY jr.max_salary DESC
        LIMIT 10
    ");
    $topPayingRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topPayingRoles = [];
}

// Fetch employees with highest salary
try {
    $stmt = $pdo->query("
        SELECT 
            ep.employee_id,
            ep.employee_number,
            CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
            jr.title as job_title,
            COALESCE(d.department_name, jr.department) as department_name,
            sg.grade_name,
            sg.grade_level,
            ep.current_salary,
            sg.monthly_salary as grade_salary,
            ep.hire_date,
            TIMESTAMPDIFF(YEAR, ep.hire_date, CURDATE()) as years_of_service,
            ep.employment_status
        FROM employee_profiles ep
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_name
        LEFT JOIN salary_grades sg ON ep.salary_grade_id = sg.grade_id
        WHERE ep.employment_status NOT IN ('Terminated', 'Resigned')
        ORDER BY ep.current_salary DESC
        LIMIT 10
    ");
    $topEarningEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topEarningEmployees = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Grades Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            font-size: 28px;
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
            margin-bottom: 30px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
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
            max-width: 900px;
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
            font-size: 24px;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
            border: none;
            background: none;
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
            padding: 10px 15px;
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

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
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

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            color: var(--azure-blue);
            border-bottom-color: var(--azure-blue);
        }

        .tab-button:hover {
            color: var(--azure-blue-dark);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .grade-card {
            background: white;
            border-left: 4px solid var(--azure-blue);
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .grade-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .grade-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .grade-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .grade-info-item {
            display: flex;
            gap: 8px;
        }

        .grade-info-label {
            font-weight: 600;
            color: #666;
            min-width: 120px;
        }

        .grade-info-value {
            color: #333;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .controls {
                flex-direction: column;
            }

            .search-box {
                max-width: 100%;
            }

            .table {
                font-size: 14px;
            }

            .table td, .table th {
                padding: 10px;
            }

            .btn-small {
                padding: 6px 12px;
                font-size: 12px;
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
                    <!-- Alert Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Page Title -->
                    <h1 class="section-title">
                        <i class="fas fa-layer-group"></i>
                        Salary Grades Management
                    </h1>

                    <!-- Tabs -->
                    <div class="tabs">
                        <button class="tab-button active" onclick="switchTab('grades')">
                            <i class="fas fa-list"></i> Salary Grades
                        </button>
                        <button class="tab-button" onclick="switchTab('analytics')">
                            <i class="fas fa-chart-bar"></i> Analytics
                        </button>
                        <button class="tab-button" onclick="switchTab('employees')">
                            <i class="fas fa-users"></i> Employees by Grade
                        </button>

                    </div>

                    <!-- Tab 1: Salary Grades -->
                    <div id="grades" class="tab-content active">
                        <div class="controls">
                            <div class="search-box">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="gradesSearchInput" placeholder="Search salary grades...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add')">
                                <i class="fas fa-plus"></i> Add Salary Grade
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="gradesTable">
                                <thead>
                                    <tr>
                                        <th>Grade Name</th>
                                        <th>Grade Level</th>
                                        <th>Step</th>
                                        <th>Monthly Salary</th>
                                        <th>Annual Salary</th>
                                        <th>Effective Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($salaryGrades)): ?>
                                        <?php foreach ($salaryGrades as $grade): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($grade['grade_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($grade['grade_level']); ?></td>
                                                <td><?php echo htmlspecialchars($grade['step_number']); ?></td>
                                                <td>₱<?php echo number_format($grade['monthly_salary'], 2); ?></td>
                                                <td>₱<?php echo number_format($grade['annual_salary'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($grade['effective_date']); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $grade['is_active'] == 1 ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $grade['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-success btn-small" onclick="editGrade(<?php echo $grade['grade_id']; ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn btn-danger btn-small" onclick="deleteGrade(<?php echo $grade['grade_id']; ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="no-data">
                                                <i class="fas fa-inbox"></i>
                                                <p>No salary grades found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 2: Employees by Grade -->
                    <div id="employees" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="employeesSearchInput" placeholder="Search employees...">
                            </div>
                        </div>

                        <div class="table-container">
                            <table class="table" id="employeesTable">
                                <thead>
                                    <tr>
                                        <th>Employee #</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Department</th>
                                        <th>Salary Grade</th>
                                        <th>Current Salary</th>
                                        <th>Grade Salary</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($employeesWithGrades)): ?>
                                        <?php foreach ($employeesWithGrades as $emp): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($emp['employee_number']); ?></td>
                                                <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($emp['job_title'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($emp['grade_name'] ?? 'Not Assigned'); ?></td>
                                                <td>₱<?php echo number_format($emp['current_salary'], 2); ?></td>
                                                <td>₱<?php echo number_format($emp['grade_salary'] ?? 0, 2); ?></td>
                                                <td><?php echo htmlspecialchars($emp['employment_status']); ?></td>
                                                <td>
                                                    <a href="employee_profile.php?view=<?php echo $emp['employee_id']; ?>" class="btn btn-primary btn-small" title="View Profile">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="no-data">
                                                <i class="fas fa-inbox"></i>
                                                <p>No employees found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 3: Salary Grade History -->
                    <div id="history" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="historySearchInput" placeholder="Search history...">
                            </div>
                        </div>

                        <div class="table-container">
                            <table class="table" id="historyTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Employee #</th>
                                        <th>New Grade</th>
                                        <th>Previous Grade</th>
                                        <th>Reason</th>
                                        <th>Effective Date</th>
                                        <th>Approved By</th>
                                        <th>Date Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($salaryGradeHistory)): ?>
                                        <?php foreach ($salaryGradeHistory as $history): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($history['employee_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($history['employee_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($history['grade_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($history['previous_grade_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($history['reason'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($history['effective_date']); ?></td>
                                                <td><?php echo htmlspecialchars($history['approved_by_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($history['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="no-data">
                                                <i class="fas fa-inbox"></i>
                                                <p>No salary grade history found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 4: Analytics -->
                    <div id="analytics" class="tab-content">
                        <!-- Top Paying Job Roles Section -->
                        <h3 class="section-title" style="margin-top: 30px;">
                            <i class="fas fa-briefcase"></i>
                            Top 10 Paying Job Roles
                        </h3>
                        <div class="table-container">
                            <table class="table" id="rolesAnalyticsTable">
                                <thead>
                                    <tr>
                                        <th>Job Role</th>
                                        <th>Department</th>
                                        <th>Min Salary</th>
                                        <th>Max Salary</th>
                                        <th>Salary Grades Used</th>
                                        <th>Employees Count</th>
                                        <th>Avg Employee Salary</th>
                                        <th>Max Employee Salary</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($topPayingRoles)): ?>
                                        <?php foreach ($topPayingRoles as $role): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($role['job_title']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($role['department']); ?></td>
                                                <td>₱<?php echo number_format($role['min_salary'], 2); ?></td>
                                                <td><span class="status-badge" style="background: #cfe2ff; color: #084298;">₱<?php echo number_format($role['max_salary'], 2); ?></span></td>
                                                <td>
                                                    <?php if (!empty($role['salary_grades'])): ?>
                                                        <small style="background: #e7f3ff; padding: 3px 8px; border-radius: 3px; display: inline-block;">
                                                            <?php echo htmlspecialchars(str_replace(',', ', ', $role['salary_grades'])); ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <em style="color: #999;">Not assigned</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $role['total_employees'] ?? 0; ?></td>
                                                <td>₱<?php echo number_format($role['avg_employee_salary'] ?? 0, 2); ?></td>
                                                <td>₱<?php echo number_format($role['max_employee_salary'] ?? 0, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="no-data">
                                                <i class="fas fa-inbox"></i>
                                                <p>No job role data found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Top Earning Employees Section -->
                        <h3 class="section-title" style="margin-top: 30px;">
                            <i class="fas fa-user-tie"></i>
                            Top 10 Earning Employees
                        </h3>
                        <div class="table-container">
                            <table class="table" id="employeesAnalyticsTable">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Employee</th>
                                        <th>Employee #</th>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Salary Grade</th>
                                        <th>Current Salary</th>
                                        <th>Hire Date</th>
                                        <th>Years of Service</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($topEarningEmployees)): ?>
                                        <?php $rank = 1; foreach ($topEarningEmployees as $employee): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($rank == 1): ?>
                                                        <span class="status-badge" style="background: #ffd700; color: #000; font-weight: bold;">🥇 #1</span>
                                                    <?php elseif ($rank == 2): ?>
                                                        <span class="status-badge" style="background: #c0c0c0; color: #000; font-weight: bold;">🥈 #2</span>
                                                    <?php elseif ($rank == 3): ?>
                                                        <span class="status-badge" style="background: #cd7f32; color: #fff; font-weight: bold;">🥉 #3</span>
                                                    <?php else: ?>
                                                        <span style="font-weight: bold;">#<?php echo $rank; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($employee['full_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($employee['employee_number']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['job_title']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['department_name']); ?></td>
                                                <td><span class="status-badge" style="background: #fff3cd; color: #664d03;">
                                                    <?php echo htmlspecialchars($employee['grade_name'] ?? 'Not Assigned'); ?>
                                                    <?php if (!empty($employee['grade_level'])): ?>(Level <?php echo $employee['grade_level']; ?>)<?php endif; ?>
                                                </span></td>
                                                <td><span class="status-badge" style="background: #d1e7dd; color: #0f5132; font-weight: bold;">₱<?php echo number_format($employee['current_salary'], 2); ?></span></td>
                                                <td><?php echo htmlspecialchars($employee['hire_date']); ?></td>
                                                <td><?php echo $employee['years_of_service'] ?? 0; ?> years</td>
                                            </tr>
                                            <?php $rank++; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="no-data">
                                                <i class="fas fa-inbox"></i>
                                                <p>No employee data found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Salary Statistics Cards -->
                        <h3 class="section-title" style="margin-top: 30px;">
                            <i class="fas fa-chart-pie"></i>
                            Salary Statistics
                        </h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                            <?php 
                            // Calculate total payroll and sum statistics
                            $totalPayroll = 0;
                            $highestSalary = 0;
                            $lowestSalary = PHP_INT_MAX;
                            $employeeCount = 0;
                            
                            foreach ($employeesWithGrades as $emp) {
                                if (!empty($emp['current_salary'])) {
                                    $totalPayroll += $emp['current_salary'];
                                    $highestSalary = max($highestSalary, $emp['current_salary']);
                                    $lowestSalary = min($lowestSalary, $emp['current_salary']);
                                    $employeeCount++;
                                }
                            }
                            $averageSalary = $employeeCount > 0 ? $totalPayroll / $employeeCount : 0;
                            ?>
                            <div class="grade-card" style="border-left-color: #287ef3;">
                                <div class="grade-name" style="color: #287ef3; margin-bottom: 10px;">Total Monthly Payroll</div>
                                <div style="font-size: 28px; font-weight: bold; color: #287ef3;">₱<?php echo number_format($totalPayroll, 2); ?></div>
                                <div style="color: #999; font-size: 14px; margin-top: 5px;"><?php echo $employeeCount; ?> Active Employees</div>
                            </div>
                            <div class="grade-card" style="border-left-color: #28a745;">
                                <div class="grade-name" style="color: #28a745; margin-bottom: 10px;">Average Salary</div>
                                <div style="font-size: 28px; font-weight: bold; color: #28a745;">₱<?php echo number_format($averageSalary, 2); ?></div>
                                <div style="color: #999; font-size: 14px; margin-top: 5px;">Per Employee</div>
                            </div>
                            <div class="grade-card" style="border-left-color: #ffc107;">
                                <div class="grade-name" style="color: #f39c12; margin-bottom: 10px;">Highest Salary</div>
                                <div style="font-size: 28px; font-weight: bold; color: #f39c12;">₱<?php echo number_format($highestSalary, 2); ?></div>
                                <div style="color: #999; font-size: 14px; margin-top: 5px;">Maximum in Organization</div>
                            </div>
                            <div class="grade-card" style="border-left-color: #6c757d;">
                                <div class="grade-name" style="color: #6c757d; margin-bottom: 10px;">Lowest Salary</div>
                                <div style="font-size: 28px; font-weight: bold; color: #6c757d;">₱<?php echo number_format($lowestSalary === PHP_INT_MAX ? 0 : $lowestSalary, 2); ?></div>
                                <div style="color: #999; font-size: 14px; margin-top: 5px;">Minimum in Organization</div>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- Add/Edit Salary Grade Modal -->
    <div id="gradeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Salary Grade</h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="gradeForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="grade_id" name="grade_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="grade_name">Grade Name <span style="color: red;">*</span></label>
                                <input type="text" class="form-control" id="grade_name" name="grade_name" required placeholder="e.g., SG-1, SG-15">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="grade_level">Grade Level <span style="color: red;">*</span></label>
                                <input type="number" class="form-control" id="grade_level" name="grade_level" required min="1" placeholder="e.g., 1">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="step_number">Step Number <span style="color: red;">*</span></label>
                                <input type="number" class="form-control" id="step_number" name="step_number" required min="1" value="1" placeholder="e.g., 1">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="monthly_salary">Monthly Salary <span style="color: red;">*</span></label>
                                <input type="number" class="form-control" id="monthly_salary" name="monthly_salary" required min="0" step="0.01" placeholder="e.g., 13000.00">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="effective_date">Effective Date <span style="color: red;">*</span></label>
                        <input type="date" class="form-control" id="effective_date" name="effective_date" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Enter grade description..."></textarea>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" checked>
                        <label for="is_active" style="margin-bottom: 0;">Active</label>
                    </div>

                    <div style="margin-top: 25px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button type="button" class="btn btn-danger" onclick="closeModal()" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>Confirm Delete</h2>
                <button class="close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this salary grade?</p>
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" id="deleteGradeId" name="grade_id">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-danger" style="flex: 1;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <button type="button" class="btn btn-primary" onclick="closeDeleteModal()" style="flex: 1;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let salaryGradesData = <?= json_encode($salaryGrades) ?>;
        let employeesData = <?= json_encode($employeesWithGrades) ?>;
        let historyData = <?= json_encode($salaryGradeHistory) ?>;

        // Switch tabs
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(el => el.classList.remove('active'));

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.closest('.tab-button').classList.add('active');
        }

        // Modal functions
        function openModal(mode, gradeId = null) {
            document.getElementById('gradeForm').reset();
            document.getElementById('action').value = mode;
            
            if (mode === 'add') {
                document.getElementById('modalTitle').textContent = 'Add Salary Grade';
                document.getElementById('grade_id').value = '';
                document.getElementById('step_number').value = '1';
                document.getElementById('is_active').checked = true;
            } else if (mode === 'edit') {
                const grade = salaryGradesData.find(g => g.grade_id == gradeId);
                if (grade) {
                    document.getElementById('modalTitle').textContent = 'Edit Salary Grade';
                    document.getElementById('grade_id').value = grade.grade_id;
                    document.getElementById('grade_name').value = grade.grade_name;
                    document.getElementById('grade_level').value = grade.grade_level;
                    document.getElementById('step_number').value = grade.step_number;
                    document.getElementById('monthly_salary').value = grade.monthly_salary;
                    document.getElementById('effective_date').value = grade.effective_date;
                    document.getElementById('description').value = grade.description || '';
                    document.getElementById('is_active').checked = grade.is_active == 1;
                }
            }
            
            document.getElementById('gradeModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('gradeModal').style.display = 'none';
        }

        function editGrade(gradeId) {
            openModal('edit', gradeId);
        }

        function deleteGrade(gradeId) {
            document.getElementById('deleteGradeId').value = gradeId;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Search functions
        document.getElementById('gradesSearchInput').addEventListener('input', function() {
            filterTable('gradesTable', this.value);
        });

        document.getElementById('employeesSearchInput').addEventListener('input', function() {
            filterTable('employeesTable', this.value);
        });

        document.getElementById('historySearchInput').addEventListener('input', function() {
            filterTable('historyTable', this.value);
        });

        function filterTable(tableId, searchValue) {
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            Array.from(rows).forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue.toLowerCase()) ? '' : 'none';
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const gradeModal = document.getElementById('gradeModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == gradeModal) {
                gradeModal.style.display = 'none';
            }
            if (event.target == deleteModal) {
                deleteModal.style.display = 'none';
            }
        }

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Set today's date as default for effective_date
        document.getElementById('effective_date').valueAsDate = new Date();
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
