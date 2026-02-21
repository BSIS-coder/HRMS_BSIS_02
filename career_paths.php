<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pull flash message from session (Post-Redirect-Get)
$message = '';
$messageType = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Read active tab preference (so we can open a specific tab after redirect)
$activeTab = $_SESSION['active_tab'] ?? 'paths';
unset($_SESSION['active_tab']);

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
                    $_SESSION['flash_message'] = "Career path added successfully!";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['active_tab'] = 'paths';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error adding career path: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: career_paths.php'); exit();
                break;

            case 'update_career_path':
                try {
                    $stmt = $pdo->prepare("UPDATE career_paths SET path_name=?, description=?, department_id=? WHERE path_id=?");
                    $stmt->execute([
                        $_POST['path_name'],
                        $_POST['description'],
                        $_POST['department_id'],
                        $_POST['path_id']
                    ]);
                    $_SESSION['flash_message'] = "Career path updated successfully!";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['active_tab'] = 'paths';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error updating career path: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: career_paths.php'); exit();
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
                    $_SESSION['flash_message'] = "Career stage added successfully!";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['active_tab'] = 'paths';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error adding career stage: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: career_paths.php'); exit();
                break;

            case 'update_career_stage':
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
                    $_SESSION['flash_message'] = "Career stage updated successfully!";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['active_tab'] = 'paths';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error updating career stage: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: career_paths.php'); exit();
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
                    $_SESSION['flash_message'] = "Employee assigned to career path successfully!";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['active_tab'] = 'assignments';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error assigning employee: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: career_paths.php'); exit();
                break;

            case 'update_assignment':
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
                    $_SESSION['flash_message'] = "Assignment updated successfully!";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['active_tab'] = 'assignments';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error updating assignment: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: career_paths.php'); exit();
                break;

            case 'delete_career_path':
                try {
                    $stmt = $pdo->prepare("DELETE FROM career_paths WHERE path_id=?");
                    $stmt->execute([$_POST['path_id']]);
                    $_SESSION['flash_message'] = "Career path deleted successfully!";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['active_tab'] = 'paths';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error deleting career path: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: career_paths.php'); exit();
                break;

            case 'delete_career_stage':
                try {
                    $stmt = $pdo->prepare("DELETE FROM career_path_stages WHERE stage_id=?");
                    $stmt->execute([$_POST['stage_id']]);
                    $_SESSION['flash_message'] = "Career stage deleted successfully!";
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['active_tab'] = 'paths';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error deleting career stage: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: career_paths.php'); exit();
                break;

            case 'delete_assignment':
            case 'delete_employee_assignment':
                try {
                    $id = $_POST['employee_path_id'] ?? $_POST['assignment_id'] ?? null;
                    if ($id) {
                        $stmt = $pdo->prepare("DELETE FROM employee_career_paths WHERE employee_path_id=?");
                        $stmt->execute([$id]);
                        $_SESSION['flash_message'] = "Employee assignment deleted successfully!";
                        $_SESSION['flash_type'] = 'success';
                        $_SESSION['active_tab'] = 'assignments';
                    } else {
                        $_SESSION['flash_message'] = "No assignment id provided.";
                        $_SESSION['flash_type'] = 'error';
                    }
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error deleting assignment: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: career_paths.php'); exit();
                break;

            default:
                // Unknown action - redirect back
                $_SESSION['flash_message'] = 'Unknown action';
                $_SESSION['flash_type'] = 'error';
                header('Location: career_paths.php'); exit();
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

// Fetch employee career paths - get base data first then enrich with names/details
try {
    // First get all assignments - this query MUST work since we know rows exist
    $stmt = $pdo->query("SELECT * FROM employee_career_paths ORDER BY employee_path_id DESC");
    $employeePaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Then enrich with optional related data
    if (!empty($employeePaths)) {
        $employeeIds = array_column($employeePaths, 'employee_id');
        $pathIds = array_column($employeePaths, 'path_id');
        $stageIds = array_column($employeePaths, 'current_stage_id');

        // Get employee names if any exist
        if (!empty($employeeIds)) {
            $nameStmt = $pdo->query("
                SELECT ep.employee_id, pi.first_name, pi.last_name
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                WHERE ep.employee_id IN (" . implode(',', array_map('intval', $employeeIds)) . ")
            ");
            $names = $nameStmt->fetchAll(PDO::FETCH_ASSOC);
            $nameMap = [];
            foreach ($names as $n) {
                $nameMap[$n['employee_id']] = $n['first_name'] . ' ' . $n['last_name'];
            }
        }

        // Get path names if any exist
        if (!empty($pathIds)) {
            $pathStmt = $pdo->query("SELECT path_id, path_name FROM career_paths WHERE path_id IN (" . implode(',', array_map('intval', $pathIds)) . ")");
            $paths = $pathStmt->fetchAll(PDO::FETCH_ASSOC);
            $pathMap = [];
            foreach ($paths as $p) {
                $pathMap[$p['path_id']] = $p['path_name'];
            }
        }

        // Get stage info if any exist
        if (!empty($stageIds)) {
            $stageStmt = $pdo->query("
                SELECT cps.stage_id, cps.stage_order, jr.title as job_role_title
                FROM career_path_stages cps
                LEFT JOIN job_roles jr ON cps.job_role_id = jr.job_role_id
                WHERE cps.stage_id IN (" . implode(',', array_map('intval', $stageIds)) . ")
            ");
            $stages = $stageStmt->fetchAll(PDO::FETCH_ASSOC);
            $stageMap = [];
            foreach ($stages as $s) {
                $stageMap[$s['stage_id']] = ['order' => $s['stage_order'], 'role' => $s['job_role_title']];
            }
        }

        // Enrich employee paths with related data
        foreach ($employeePaths as &$ep) {
            $ep['employee_name'] = $nameMap[$ep['employee_id']] ?? 'Employee #' . $ep['employee_id'];
            $ep['path_name'] = $pathMap[$ep['path_id']] ?? 'Path #' . $ep['path_id'];
            $ep['stage_order'] = isset($stageMap[$ep['current_stage_id']]) ? $stageMap[$ep['current_stage_id']]['order'] : 'N/A';
            $ep['current_role'] = isset($stageMap[$ep['current_stage_id']]) ? $stageMap[$ep['current_stage_id']]['role'] : 'Unknown Role';
        }
        unset($ep); // break reference
    }
} catch (PDOException $e) {
    $employeePaths = [];
}

// Quick debug helper: append a diagnostic message when ?debug=1 is present
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    try {
        $countStmt = $pdo->query("SELECT COUNT(*) as c FROM employee_career_paths");
        $count = (int) $countStmt->fetchColumn();
        if ($count > 0 && empty($employeePaths)) {
            $sample = $pdo->query("SELECT * FROM employee_career_paths LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $message = "Debug: employee_career_paths count={$count}; query returned 0 rows. Sample row: " . json_encode($sample);
            $messageType = 'error';
        } else {
            $message = "Debug: employee_career_paths count={$count}; fetched=" . count($employeePaths);
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = "Debug error reading employee_career_paths: " . $e->getMessage();
        $messageType = 'error';
    }
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
        /* Copied design from skill_matrix.php to match frontend look exactly */
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

        .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: white; }

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

        .status-badge, .proficiency-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }

        .modal-content { background: white; margin: 5% auto; padding: 0; border-radius: 15px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: slideIn 0.3s ease; }

        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; padding: 20px 30px; border-radius: 15px 15px 0 0; }

        .modal-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }

        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--azure-blue-dark); }

        .form-control { width: 100%; padding: 6px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: all 0.3s ease; }

        .form-control:focus { border-color: var(--azure-blue); outline: none; box-shadow: 0 0 10px rgba(233, 30, 99, 0.3); }

        .tab-navigation { display: flex; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 30px; }

        .tab-button { flex: 1; padding: 20px; background: white; border: none; cursor: pointer; font-weight: 600; font-size: 16px; color: #666; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 10px; }

        .tab-button.active { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; }

        .tab-button:hover:not(.active) { background: var(--azure-blue-lighter); color: var(--azure-blue-dark); }

        .tab-content { display: none; }

        .tab-content.active { display: block; }

        @media (max-width: 768px) {
            .controls { flex-direction: column; align-items: stretch; }
            .search-box { max-width: none; }
            .form-row { flex-direction: column; }
            .table-container { overflow-x: auto; }
            .tab-button { padding: 15px 10px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>

        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
            <div style="background: #fffbea; border: 2px solid #ffd54f; color: #6a4300; padding: 12px 20px; margin: 15px; border-radius: 8px;">
                <strong>DEBUG:</strong>
                <?php
                    try {
                        $cnt = $pdo->query("SELECT COUNT(*) FROM employee_career_paths")->fetchColumn();
                        echo 'employee_career_paths count=' . (int)$cnt . '. ';
                        if ($cnt > 0) {
                            $sample = $pdo->query("SELECT * FROM employee_career_paths LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                            echo 'Sample row: ' . htmlspecialchars(json_encode($sample));
                        }
                    } catch (Exception $e) {
                        echo 'Debug query error: ' . htmlspecialchars($e->getMessage());
                    }
                ?>
            </div>
        <?php endif; ?>

        <!-- Flash modal (shows after add/edit/delete) -->
        <div id="flashModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="flashModalTitle">Notification</h2>
                    <span class="close" onclick="closeFlashModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <p id="flashMessageText"><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        </div>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Career Development Management</h2>
                
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

                <!-- Tabs Navigation -->
                <div class="tab-navigation">
                    <button class="tab-button active" data-tab="paths" onclick="showTab('paths')">
                        <i class="fas fa-road"></i> Career Paths
                    </button>
                    <button class="tab-button" data-tab="stages" onclick="showTab('stages')">
                        <i class="fas fa-route"></i> Career Stages
                    </button>
                    <button class="tab-button" data-tab="assignments" onclick="showTab('assignments')">
                        <i class="fas fa-user-check"></i> Employee Assignments
                    </button>
                </div>

                <!-- Tab Content -->
                <div id="careerTabsContent">
                    <!-- Career Paths Tab -->
                    <div id="paths-tab" class="tab-content active">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="pathSearch" placeholder="Search career paths..." onkeyup="searchPaths()">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('careerPath')">
                                ➕ Add Career Path
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="pathsTable">
                                <thead>
                                    <tr>
                                        <th>Path Name</th>
                                        <th>Department</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($careerPaths)): ?>
                                    <tr>
                                        <td colspan="4" class="no-results">
                                            <i class="fas fa-road"></i>
                                            <h3>No career paths found</h3>
                                            <p>Start by adding your first career path.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($careerPaths as $path): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($path['path_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($path['department_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(substr($path['description'], 0, 50)) . (strlen($path['description']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editCareerPath(<?php echo $path['path_id']; ?>, '<?php echo addslashes($path['path_name']); ?>', '<?php echo addslashes($path['description']); ?>', '<?php echo $path['department_id']; ?>')">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteCareerPath(<?php echo $path['path_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Career Stages Tab -->
                    <div id="stages-tab" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="stageSearch" placeholder="Search career stages..." onkeyup="searchStages()">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('careerStage')">
                                ➕ Add Career Stage
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="stagesTable">
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
                                <tbody>
                                    <?php if (empty($careerStages)): ?>
                                    <tr>
                                        <td colspan="6" class="no-results">
                                            <i class="fas fa-route"></i>
                                            <h3>No career stages found</h3>
                                            <p>Start by adding your first career stage.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($careerStages as $stage): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($stage['path_name']); ?></strong></td>
                                        <td><span class="status-badge status-active">Stage <?php echo $stage['stage_order']; ?></span></td>
                                        <td><?php echo htmlspecialchars($stage['job_role_title']); ?></td>
                                        <td><?php echo $stage['minimum_time_in_role']; ?> months</td>
                                        <td><?php echo htmlspecialchars(substr($stage['required_skills'], 0, 30)) . (strlen($stage['required_skills']) > 30 ? '...' : ''); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editCareerStage(<?php echo $stage['stage_id']; ?>, '<?php echo $stage['path_id']; ?>', '<?php echo $stage['job_role_id']; ?>', '<?php echo $stage['stage_order']; ?>', '<?php echo $stage['minimum_time_in_role']; ?>', '<?php echo addslashes($stage['required_skills']); ?>', '<?php echo addslashes($stage['required_experience']); ?>')">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteCareerStage(<?php echo $stage['stage_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Employee Assignments Tab -->
                    <div id="assignments-tab" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="assignmentSearch" placeholder="Search assignments..." onkeyup="searchAssignments()">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('employeeAssignment')">
                                ➕ Assign Employee
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="assignmentsTable">
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
                                <tbody>
                                    <?php if (empty($employeePaths)): ?>
                                    <tr>
                                        <td colspan="8" class="no-results">
                                            <i class="fas fa-user-check"></i>
                                            <h3>No employee assignments found</h3>
                                            <p>Start by assigning an employee to a career path.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($employeePaths as $assignment): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($assignment['employee_name'] ?? ('Employee #' . $assignment['employee_id'])); ?></strong></td>
                                        <td><?php echo htmlspecialchars($assignment['path_name'] ?? '—'); ?></td>
                                        <td><span class="status-badge status-active"><?php echo isset($assignment['stage_order']) ? 'Stage ' . htmlspecialchars($assignment['stage_order']) : 'Stage N/A'; ?></span></td>
                                        <td><?php echo htmlspecialchars($assignment['current_role'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($assignment['start_date'])); ?></td>
                                        <td><?php echo $assignment['target_completion_date'] ? date('M d, Y', strtotime($assignment['target_completion_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($assignment['status']); ?>">
                                                <?php echo htmlspecialchars($assignment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editAssignment(<?php echo $assignment['employee_path_id']; ?>, '<?php echo $assignment['employee_id']; ?>', '<?php echo $assignment['path_id']; ?>', '<?php echo $assignment['current_stage_id']; ?>', '<?php echo $assignment['start_date']; ?>', '<?php echo $assignment['target_completion_date']; ?>', '<?php echo $assignment['status']; ?>')">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteAssignment(<?php echo $assignment['employee_path_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Career Path Modal -->
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

    <!-- Career Stage Modal -->
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

    <!-- Employee Assignment Modal -->
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Tab functionality (matches skill_matrix.php behavior)
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
            var target = document.getElementById(tabName + '-tab');
            if (target) target.classList.add('active');

            // Add active class to clicked tab button (or corresponding data-tab button)
            var btn = document.querySelector('.tab-button[data-tab="' + tabName + '"]');
            if (btn) btn.classList.add('active');
        }

        // Modal functions
        function openModal(type) {
            if (type === 'careerPath') {
                document.getElementById('careerPathModal').style.display = 'block';
                document.getElementById('careerPathModalTitle').textContent = 'Add New Career Path';
                document.getElementById('careerPath_action').value = 'add_career_path';
                document.getElementById('careerPathForm').reset();
            } else if (type === 'careerStage') {
                document.getElementById('careerStageModal').style.display = 'block';
                document.getElementById('careerStageModalTitle').textContent = 'Add New Career Stage';
                document.getElementById('careerStage_action').value = 'add_career_stage';
                document.getElementById('careerStageForm').reset();
            } else if (type === 'employeeAssignment') {
                document.getElementById('employeeAssignmentModal').style.display = 'block';
                document.getElementById('employeeAssignmentModalTitle').textContent = 'Assign Employee to Career Path';
                document.getElementById('employeeAssignment_action').value = 'assign_employee_path';
                document.getElementById('employeeAssignmentForm').reset();
            }
        }

        function closeModal(type) {
            if (type === 'careerPath') {
                document.getElementById('careerPathModal').style.display = 'none';
            } else if (type === 'careerStage') {
                document.getElementById('careerStageModal').style.display = 'none';
            } else if (type === 'employeeAssignment') {
                document.getElementById('employeeAssignmentModal').style.display = 'none';
            }
        }

        // Edit functions
        function editCareerPath(id, name, description, departmentId) {
            document.getElementById('careerPathModal').style.display = 'block';
            document.getElementById('careerPathModalTitle').textContent = 'Edit Career Path';
            document.getElementById('careerPath_action').value = 'update_career_path';
            document.getElementById('careerPath_id').value = id;
            document.getElementById('path_name').value = name;
            document.getElementById('description').value = description;
            document.getElementById('department_id').value = departmentId;
        }

        function editCareerStage(id, pathId, jobRoleId, stageOrder, minTime, skills, experience) {
            document.getElementById('careerStageModal').style.display = 'block';
            document.getElementById('careerStageModalTitle').textContent = 'Edit Career Stage';
            document.getElementById('careerStage_action').value = 'update_career_stage';
            document.getElementById('careerStage_id').value = id;
            document.getElementById('stage_path_id').value = pathId;
            document.getElementById('job_role_id').value = jobRoleId;
            document.getElementById('stage_order').value = stageOrder;
            document.getElementById('minimum_time_in_role').value = minTime;
            document.getElementById('required_skills').value = skills;
            document.getElementById('required_experience').value = experience;
        }

        function editAssignment(id, employeeId, pathId, stageId, startDate, targetDate, status) {
            document.getElementById('employeeAssignmentModal').style.display = 'block';
            document.getElementById('employeeAssignmentModalTitle').textContent = 'Edit Employee Assignment';
            document.getElementById('employeeAssignment_action').value = 'update_assignment';
            document.getElementById('employeeAssignment_id').value = id;
            document.getElementById('assignment_employee_id').value = employeeId;
            document.getElementById('assignment_path_id').value = pathId;
            // Load stages for the selected path then set the stage value
            if (typeof loadStages === 'function') {
                loadStages();
            }
            document.getElementById('assignment_stage_id').value = stageId;
            document.getElementById('start_date').value = startDate;
            document.getElementById('target_completion_date').value = targetDate;
            document.getElementById('assignment_status').value = status;
        }

        // Delete functions
        function deleteCareerPath(id) {
            if (confirm('Are you sure you want to delete this career path?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_career_path"><input type="hidden" name="path_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteCareerStage(id) {
            if (confirm('Are you sure you want to delete this career stage?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_career_stage"><input type="hidden" name="stage_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteAssignment(id) {
            if (confirm('Are you sure you want to delete this assignment?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_assignment"><input type="hidden" name="employee_path_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Search functionality
        function searchPaths() {
            var input = document.getElementById('pathSearch');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('pathsTable');
            var tr = table.getElementsByTagName('tr');

            for (var i = 1; i < tr.length; i++) {
                var td = tr[i].getElementsByTagName('td');
                var found = false;
                for (var j = 0; j < td.length; j++) {
                    if (td[j]) {
                        var txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        function searchStages() {
            var input = document.getElementById('stageSearch');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('stagesTable');
            var tr = table.getElementsByTagName('tr');

            for (var i = 1; i < tr.length; i++) {
                var td = tr[i].getElementsByTagName('td');
                var found = false;
                for (var j = 0; j < td.length; j++) {
                    if (td[j]) {
                        var txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        function searchAssignments() {
            var input = document.getElementById('assignmentSearch');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('assignmentsTable');
            var tr = table.getElementsByTagName('tr');

            for (var i = 1; i < tr.length; i++) {
                var td = tr[i].getElementsByTagName('td');
                var found = false;
                for (var j = 0; j < td.length; j++) {
                    if (td[j]) {
                        var txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        // Load stages based on career path selection
        function loadStages() {
            var pathId = document.getElementById('assignment_path_id').value;
            var stageSelect = document.getElementById('assignment_stage_id');
            
            stageSelect.innerHTML = '<option value="">Loading stages...</option>';
            
            if (pathId) {
                // Filter stages for the selected path
                var stages = <?php echo json_encode($careerStages); ?>;
                var filteredStages = stages.filter(function(stage) {
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
            var modals = ['careerPathModal', 'careerStageModal', 'employeeAssignmentModal', 'flashModal'];
            modals.forEach(function(modalId) {
                var modal = document.getElementById(modalId);
                if (modal && event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Flash popup + activate desired tab after redirects
        document.addEventListener('DOMContentLoaded', function() {
            try {
                var flashMessage = <?php echo json_encode($message); ?>;
                var flashType = <?php echo json_encode($messageType); ?>;
                var activeTab = <?php echo json_encode($activeTab); ?>;

                // Activate requested tab (if function exists)
                if (typeof showTab === 'function' && activeTab) {
                    showTab(activeTab);
                }

                if (flashMessage) {
                    var modal = document.getElementById('flashModal');
                    var textEl = document.getElementById('flashMessageText');
                    if (textEl) textEl.textContent = flashMessage;
                    if (modal) {
                        modal.style.display = 'block';
                        // auto-close after 3.5s
                        setTimeout(function() { closeFlashModal(); }, 3500);
                    } else {
                        // fallback to alert
                        alert(flashMessage);
                    }
                }
            } catch (e) { console.error(e); }
        });

        function closeFlashModal() {
            var modal = document.getElementById('flashModal');
            if (modal) modal.style.display = 'none';
        }
    </script>
</body>
</html>
