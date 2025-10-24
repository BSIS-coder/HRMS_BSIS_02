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

// Include database connection
require_once 'config.php';

// Use the global database connection
$pdo = $conn;

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_career_path':
                try {
                    $stmt = $pdo->prepare("INSERT INTO career_paths (path_name, description, department_id) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $_POST['path_name'],
                        $_POST['description'],
                        $_POST['department_id']
                    ]);
                    $message = "Career path added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding career path: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'edit_career_path':
                try {
                    $stmt = $pdo->prepare("UPDATE career_paths SET path_name=?, description=?, department_id=? WHERE path_id=?");
                    $stmt->execute([
                        $_POST['path_name'],
                        $_POST['description'],
                        $_POST['department_id'],
                        $_POST['path_id']
                    ]);
                    $message = "Career path updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating career path: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'add_career_stage':
                try {
                    $stmt = $pdo->prepare("INSERT INTO career_path_stages (path_id, job_role_id, stage_order, minimum_time_in_role, required_skills, required_experience) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['path_id'],
                        $_POST['job_role_id'],
                        $_POST['stage_order'],
                        $_POST['minimum_time_in_role'],
                        $_POST['required_skills'],
                        $_POST['required_experience']
                    ]);
                    $message = "Career stage added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding career stage: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'edit_career_stage':
                try {
                    $stmt = $pdo->prepare("UPDATE career_path_stages SET path_id=?, job_role_id=?, stage_order=?, minimum_time_in_role=?, required_skills=?, required_experience=? WHERE stage_id=?");
                    $stmt->execute([
                        $_POST['path_id'],
                        $_POST['job_role_id'],
                        $_POST['stage_order'],
                        $_POST['minimum_time_in_role'],
                        $_POST['required_skills'],
                        $_POST['required_experience'],
                        $_POST['stage_id']
                    ]);
                    $message = "Career stage updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating career stage: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'assign_employee_path':
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_career_paths (employee_id, path_id, current_stage_id, start_date, target_completion_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['path_id'],
                        $_POST['current_stage_id'],
                        $_POST['start_date'],
                        $_POST['target_completion_date'],
                        $_POST['status']
                    ]);
                    $message = "Employee assigned to career path successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error assigning employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'edit_assignment':
                try {
                    $stmt = $pdo->prepare("UPDATE employee_career_paths SET employee_id=?, path_id=?, current_stage_id=?, start_date=?, target_completion_date=?, status=? WHERE employee_path_id=?");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['path_id'],
                        $_POST['current_stage_id'],
                        $_POST['start_date'],
                        $_POST['target_completion_date'],
                        $_POST['status'],
                        $_POST['employee_path_id']
                    ]);
                    $message = "Employee assignment updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating assignment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_career_path':
                try {
                    $stmt = $pdo->prepare("DELETE FROM career_paths WHERE path_id=?");
                    $stmt->execute([$_POST['path_id']]);
                    $message = "Career path deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting career path: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_career_stage':
                try {
                    $stmt = $pdo->prepare("DELETE FROM career_path_stages WHERE stage_id=?");
                    $stmt->execute([$_POST['stage_id']]);
                    $message = "Career stage deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting career stage: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_assignment':
                try {
                    $stmt = $pdo->prepare("DELETE FROM employee_career_paths WHERE employee_path_id=?");
                    $stmt->execute([$_POST['employee_path_id']]);
                    $message = "Employee assignment deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting assignment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch career paths
try {
    $stmt = $pdo->query("
        SELECT cp.*, d.department_name 
        FROM career_paths cp 
        LEFT JOIN departments d ON cp.department_id = d.department_id 
        ORDER BY cp.path_name
    ");
    $careerPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $careerPaths = [];
    $message = "Error fetching career paths: " . $e->getMessage();
    $messageType = "error";
}

// Fetch career path stages
try {
    $stmt = $pdo->query("
        SELECT cps.*, cp.path_name, jr.title as job_role_title 
        FROM career_path_stages cps 
        JOIN career_paths cp ON cps.path_id = cp.path_id 
        JOIN job_roles jr ON cps.job_role_id = jr.job_role_id 
        ORDER BY cp.path_name, cps.stage_order
    ");
    $careerStages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $careerStages = [];
}

// Fetch employee career paths
try {
    $stmt = $pdo->query("
        SELECT ecp.*, e.first_name, e.last_name, cp.path_name, cps.stage_order, jr.title as current_role
        FROM employee_career_paths ecp 
        JOIN employee_profiles ep ON ecp.employee_id = ep.employee_id 
        JOIN personal_information e ON ep.personal_info_id = e.personal_info_id 
        JOIN career_paths cp ON ecp.path_id = cp.path_id 
        JOIN career_path_stages cps ON ecp.current_stage_id = cps.stage_id 
        JOIN job_roles jr ON cps.job_role_id = jr.job_role_id 
        ORDER BY e.last_name, cp.path_name
    ");
    $employeePaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employeePaths = [];
}

// Fetch departments for dropdowns
try {
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// Fetch job roles for dropdowns
try {
    $stmt = $pdo->query("SELECT * FROM job_roles ORDER BY title");
    $jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $jobRoles = [];
}

// Fetch employees for dropdowns
try {
    $stmt = $pdo->query("
        SELECT ep.employee_id, pi.first_name, pi.last_name 
        FROM employee_profiles ep 
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id 
        ORDER BY pi.last_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM career_paths");
    $totalPaths = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM career_path_stages");
    $totalStages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_career_paths WHERE status = 'Active'");
    $activeAssignments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_career_paths WHERE status = 'Completed'");
    $completedPaths = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $totalPaths = 0;
    $totalStages = 0;
    $activeAssignments = 0;
    $completedPaths = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Development Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Career Development Management Styles */
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

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .stats-card i {
            font-size: 3rem;
            color: var(--azure-blue);
            margin-bottom: 15px;
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

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-paused, .status-on-hold {
            background: #fff3cd;
            color: #856404;
        }

        .status-abandoned {
            background: #f8d7da;
            color: #721c24;
        }

        .stage-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
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
            padding: 12px 15px;
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

        .tab-navigation {
            display: flex;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .tab-button {
            flex: 1;
            padding: 20px;
            background: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            color: #666;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .tab-button.active {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .tab-button:hover:not(.active) {
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

            .tab-button {
                padding: 15px 10px;
                font-size: 14px;
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
                <h2 class="section-title">Career Development Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-road"></i>
                            <h3><?php echo $totalPaths; ?></h3>
                            <h6>Career Paths</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-route"></i>
                            <h3><?php echo $totalStages; ?></h3>
                            <h6>Career Stages</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-user-check"></i>
                            <h3><?php echo $activeAssignments; ?></h3>
                            <h6>Active Assignments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-trophy"></i>
                            <h3><?php echo $completedPaths; ?></h3>
                            <h6>Completed Paths</h6>
                        </div>
                    </div>
                </div>

                    <!-- Tab Navigation -->
                    <div class="tab-navigation">
                        <button class="tab-button active" onclick="showTab('paths')">
                            <i class="fas fa-road"></i>
                            Career Paths
                        </button>
                        <button class="tab-button" onclick="showTab('stages')">
                            <i class="fas fa-route"></i>
                            Career Stages
                        </button>
                        <button class="tab-button" onclick="showTab('assignments')">
                            <i class="fas fa-user-check"></i>
                            Employee Assignments
                        </button>
                    </div>

                    <!-- Career Paths Tab -->
                    <div id="paths-tab" class="tab-content active">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="pathSearch" placeholder="Search career paths by name or department...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add_career_path')">
                                ➕ Add New Career Path
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="pathTable">
                                <thead>
                                    <tr>
                                        <th>Path Name</th>
                                        <th>Department</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="pathTableBody">
                                    <?php foreach ($careerPaths as $path): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($path['path_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($path['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(substr($path['description'], 0, 50)) . (strlen($path['description']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editCareerPath(<?php echo $path['path_id']; ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteCareerPath(<?php echo $path['path_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($careerPaths)): ?>
                            <div class="no-results">
                                <i class="fas fa-road"></i>
                                <h3>No career paths found</h3>
                                <p>Start by adding your first career path.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Career Stages Tab -->
                    <div id="stages-tab" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="stageSearch" placeholder="Search career stages...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add_career_stage')">
                                ➕ Add Career Stage
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="stageTable">
                                <thead>
                                    <tr>
                                        <th>Career Path</th>
                                        <th>Stage Order</th>
                                        <th>Job Role</th>
                                        <th>Min Time (Months)</th>
                                        <th>Required Skills</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="stageTableBody">
                                    <?php foreach ($careerStages as $stage): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($stage['path_name']); ?></strong></td>
                                        <td><span class="stage-badge">Stage <?php echo $stage['stage_order']; ?></span></td>
                                        <td><?php echo htmlspecialchars($stage['job_role_title']); ?></td>
                                        <td><?php echo $stage['minimum_time_in_role']; ?> months</td>
                                        <td><?php echo htmlspecialchars(substr($stage['required_skills'], 0, 30)) . (strlen($stage['required_skills']) > 30 ? '...' : ''); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editCareerStage(<?php echo $stage['stage_id']; ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteCareerStage(<?php echo $stage['stage_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($careerStages)): ?>
                            <div class="no-results">
                                <i class="fas fa-route"></i>
                                <h3>No career stages found</h3>
                                <p>Start by adding your first career stage.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Employee Assignments Tab -->
                    <div id="assignments-tab" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="assignmentSearch" placeholder="Search employee assignments...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('assign_employee_path')">
                                ➕ Assign Employee
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="assignmentTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Career Path</th>
                                        <th>Current Stage</th>
                                        <th>Current Role</th>
                                        <th>Start Date</th>
                                        <th>Target Completion</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="assignmentTableBody">
                                    <?php foreach ($employeePaths as $assignment): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($assignment['path_name']); ?></td>
                                        <td><span class="stage-badge">Stage <?php echo $assignment['stage_order']; ?></span></td>
                                        <td><?php echo htmlspecialchars($assignment['current_role']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($assignment['start_date'])); ?></td>
                                        <td><?php echo $assignment['target_completion_date'] ? date('M d, Y', strtotime($assignment['target_completion_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $assignment['status'])); ?>">
                                                <?php echo htmlspecialchars($assignment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editAssignment(<?php echo $assignment['employee_path_id']; ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteAssignment(<?php echo $assignment['employee_path_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($employeePaths)): ?>
                            <div class="no-results">
                                <i class="fas fa-user-check"></i>
                                <h3>No employee assignments found</h3>
                                <p>Start by assigning your first employee to a career path.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Career Path Modal -->
    <div id="careerPathModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="careerPathModalTitle">Add New Career Path</h2>
                <span class="close" onclick="closeModal('careerPath')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="careerPathForm" method="POST">
                    <input type="hidden" id="careerPath_action" name="action" value="add_career_path">
                    <input type="hidden" id="careerPath_id" name="path_id">

                    <div class="form-group">
                        <label for="path_name">Path Name *</label>
                        <input type="text" id="path_name" name="path_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id" class="form-control">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Description of the career path"></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('careerPath')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Career Path</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Career Stage Modal -->
    <div id="careerStageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="careerStageModalTitle">Add New Career Stage</h2>
                <span class="close" onclick="closeModal('careerStage')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="careerStageForm" method="POST">
                    <input type="hidden" id="careerStage_action" name="action" value="add_career_stage">
                    <input type="hidden" id="careerStage_id" name="stage_id">

                    <div class="form-group">
                        <label for="stage_path_id">Career Path *</label>
                        <select id="stage_path_id" name="path_id" class="form-control" required>
                            <option value="">Select Career Path</option>
                            <?php foreach ($careerPaths as $path): ?>
                            <option value="<?php echo $path['path_id']; ?>">
                                <?php echo htmlspecialchars($path['path_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="job_role_id">Job Role *</label>
                        <select id="job_role_id" name="job_role_id" class="form-control" required>
                            <option value="">Select Job Role</option>
                            <?php foreach ($jobRoles as $role): ?>
                            <option value="<?php echo $role['job_role_id']; ?>">
                                <?php echo htmlspecialchars($role['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="stage_order">Stage Order *</label>
                                <input type="number" id="stage_order" name="stage_order" class="form-control" min="1" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="minimum_time_in_role">Min Time in Role (Months) *</label>
                                <input type="number" id="minimum_time_in_role" name="minimum_time_in_role" class="form-control" min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="required_skills">Required Skills</label>
                        <textarea id="required_skills" name="required_skills" class="form-control" rows="2" placeholder="Skills required for this stage"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="required_experience">Required Experience</label>
                        <textarea id="required_experience" name="required_experience" class="form-control" rows="2" placeholder="Experience requirements"></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('careerStage')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Career Stage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Employee Assignment Modal -->
    <div id="employeeAssignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="employeeAssignmentModalTitle">Assign Employee to Career Path</h2>
                <span class="close" onclick="closeModal('employeeAssignment')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="employeeAssignmentForm" method="POST">
                    <input type="hidden" id="employeeAssignment_action" name="action" value="assign_employee_path">
                    <input type="hidden" id="employeeAssignment_id" name="employee_path_id">

                    <div class="form-group">
                        <label for="assignment_employee_id">Employee *</label>
                        <select id="assignment_employee_id" name="employee_id" class="form-control" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['employee_id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assignment_path_id">Career Path *</label>
                        <select id="assignment_path_id" name="path_id" class="form-control" required onchange="loadStages()">
                            <option value="">Select Career Path</option>
                            <?php foreach ($careerPaths as $path): ?>
                            <option value="<?php echo $path['path_id']; ?>">
                                <?php echo htmlspecialchars($path['path_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assignment_stage_id">Current Stage *</label>
                        <select id="assignment_stage_id" name="current_stage_id" class="form-control" required>
                            <option value="">Select Career Path first</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="target_completion_date">Target Completion Date</label>
                                <input type="date" id="target_completion_date" name="target_completion_date" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="assignment_status">Status *</label>
                        <select id="assignment_status" name="status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Completed">Completed</option>
                            <option value="Abandoned">Abandoned</option>
                        </select>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('employeeAssignment')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let careerPathsData = <?= json_encode($careerPaths) ?>;
        let careerStagesData = <?= json_encode($careerStages) ?>;
        let employeePathsData = <?= json_encode($employeePaths) ?>;
        let departmentsData = <?= json_encode($departments) ?>;
        let jobRolesData = <?= json_encode($jobRoles) ?>;
        let employeesData = <?= json_encode($employees) ?>;

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }

        // Search functionality
        document.getElementById('pathSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('pathTableBody');
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

        document.getElementById('stageSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('stageTableBody');
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

        document.getElementById('assignmentSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('assignmentTableBody');
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
        function openModal(mode, id = null) {
            if (mode === 'add_career_path') {
                const modal = document.getElementById('careerPathModal');
                const form = document.getElementById('careerPathForm');
                const title = document.getElementById('careerPathModalTitle');
                const action = document.getElementById('careerPath_action');

                title.textContent = 'Add New Career Path';
                action.value = 'add_career_path';
                form.reset();
                document.getElementById('careerPath_id').value = '';
                modal.style.display = 'block';
                
            } else if (mode === 'edit_career_path' && id) {
                const modal = document.getElementById('careerPathModal');
                const title = document.getElementById('careerPathModalTitle');
                const action = document.getElementById('careerPath_action');

                title.textContent = 'Edit Career Path';
                action.value = 'edit_career_path';
                document.getElementById('careerPath_id').value = id;
                populateCareerPathForm(id);
                modal.style.display = 'block';
                
            } else if (mode === 'add_career_stage') {
                const modal = document.getElementById('careerStageModal');
                const form = document.getElementById('careerStageForm');
                const title = document.getElementById('careerStageModalTitle');
                const action = document.getElementById('careerStage_action');

                title.textContent = 'Add New Career Stage';
                action.value = 'add_career_stage';
                form.reset();
                document.getElementById('careerStage_id').value = '';
                modal.style.display = 'block';
                
            } else if (mode === 'edit_career_stage' && id) {
                const modal = document.getElementById('careerStageModal');
                const title = document.getElementById('careerStageModalTitle');
                const action = document.getElementById('careerStage_action');

                title.textContent = 'Edit Career Stage';
                action.value = 'edit_career_stage';
                document.getElementById('careerStage_id').value = id;
                populateCareerStageForm(id);
                modal.style.display = 'block';
                
            } else if (mode === 'assign_employee_path') {
                const modal = document.getElementById('employeeAssignmentModal');
                const form = document.getElementById('employeeAssignmentForm');
                const title = document.getElementById('employeeAssignmentModalTitle');
                const action = document.getElementById('employeeAssignment_action');

                title.textContent = 'Assign Employee to Career Path';
                action.value = 'assign_employee_path';
                form.reset();
                document.getElementById('employeeAssignment_id').value = '';
                document.getElementById('start_date').value = new Date().toISOString().split('T')[0];
                modal.style.display = 'block';
                
            } else if (mode === 'edit_assignment' && id) {
                const modal = document.getElementById('employeeAssignmentModal');
                const title = document.getElementById('employeeAssignmentModalTitle');
                const action = document.getElementById('employeeAssignment_action');

                title.textContent = 'Edit Employee Assignment';
                action.value = 'edit_assignment';
                document.getElementById('employeeAssignment_id').value = id;
                populateAssignmentForm(id);
                modal.style.display = 'block';
            }

            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalType) {
            const modal = document.getElementById(modalType + 'Modal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateCareerPathForm(pathId) {
            const path = careerPathsData.find(p => p.path_id == pathId);
            if (path) {
                document.getElementById('path_name').value = path.path_name || '';
                document.getElementById('department_id').value = path.department_id || '';
                document.getElementById('description').value = path.description || '';
            }
        }

        function populateCareerStageForm(stageId) {
            const stage = careerStagesData.find(s => s.stage_id == stageId);
            if (stage) {
                document.getElementById('stage_path_id').value = stage.path_id || '';
                document.getElementById('job_role_id').value = stage.job_role_id || '';
                document.getElementById('stage_order').value = stage.stage_order || '';
                document.getElementById('minimum_time_in_role').value = stage.minimum_time_in_role || '';
                document.getElementById('required_skills').value = stage.required_skills || '';
                document.getElementById('required_experience').value = stage.required_experience || '';
            }
        }

        function populateAssignmentForm(assignmentId) {
            const assignment = employeePathsData.find(a => a.employee_path_id == assignmentId);
            if (assignment) {
                document.getElementById('assignment_employee_id').value = assignment.employee_id || '';
                document.getElementById('assignment_path_id').value = assignment.path_id || '';
                document.getElementById('assignment_stage_id').value = assignment.current_stage_id || '';
                document.getElementById('start_date').value = assignment.start_date || '';
                document.getElementById('target_completion_date').value = assignment.target_completion_date || '';
                document.getElementById('assignment_status').value = assignment.status || '';
                loadStages(); // Load stages for the selected path
            }
        }

        function editCareerPath(pathId) {
            openModal('edit_career_path', pathId);
        }

        function editCareerStage(stageId) {
            openModal('edit_career_stage', stageId);
        }

        function editAssignment(assignmentId) {
            openModal('edit_assignment', assignmentId);
        }

        function deleteCareerPath(pathId) {
            if (confirm('Are you sure you want to delete this career path? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_career_path">
                    <input type="hidden" name="path_id" value="${pathId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteCareerStage(stageId) {
            if (confirm('Are you sure you want to delete this career stage? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_career_stage">
                    <input type="hidden" name="stage_id" value="${stageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteAssignment(assignmentId) {
            if (confirm('Are you sure you want to delete this employee assignment? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_assignment">
                    <input type="hidden" name="employee_path_id" value="${assignmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Load stages based on career path selection
        function loadStages() {
            const pathId = document.getElementById('assignment_path_id').value;
            const stageSelect = document.getElementById('assignment_stage_id');
            
            stageSelect.innerHTML = '<option value="">Loading stages...</option>';
            
            if (pathId) {
                // Filter stages for the selected path
                const filteredStages = careerStagesData.filter(function(stage) {
                    return stage.path_id == pathId;
                });
                
                stageSelect.innerHTML = '<option value="">Select Stage</option>';
                filteredStages.forEach(function(stage) {
                    stageSelect.innerHTML += '<option value="' + stage.stage_id + '">Stage ' + stage.stage_order + ' - ' + stage.job_role_title + '</option>';
                });
            } else {
                stageSelect.innerHTML = '<option value="">Select Career Path first</option>';
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const careerPathModal = document.getElementById('careerPathModal');
            const careerStageModal = document.getElementById('careerStageModal');
            const employeeAssignmentModal = document.getElementById('employeeAssignmentModal');
            
            if (event.target === careerPathModal) {
                closeModal('careerPath');
            } else if (event.target === careerStageModal) {
                closeModal('careerStage');
            } else if (event.target === employeeAssignmentModal) {
                closeModal('employeeAssignment');
            }
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
            const tableRows = document.querySelectorAll('.table tbody tr');
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
