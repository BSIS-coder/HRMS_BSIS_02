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
$host = getenv('DB_HOST') ?? 'localhost';
$dbname = getenv('DB_NAME') ?? 'CC_HR';
$username = getenv('DB_USER') ?? 'root';
$password = getenv('DB_PASS') ?? '';

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
            case 'add_assignment':
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_career_paths (employee_id, path_id, current_stage, start_date, target_completion_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['path_id'],
                        $_POST['current_stage'],
                        $_POST['start_date'],
                        $_POST['target_completion_date'],
                        $_POST['status'],
                        $_POST['notes']
                    ]);
                    $message = "Employee career path assignment added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding assignment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update_assignment':
                try {
                    $stmt = $pdo->prepare("UPDATE employee_career_paths SET current_stage=?, target_completion_date=?, status=?, notes=? WHERE assignment_id=?");
                    $stmt->execute([
                        $_POST['current_stage'],
                        $_POST['target_completion_date'],
                        $_POST['status'],
                        $_POST['notes'],
                        $_POST['assignment_id']
                    ]);
                    $message = "Employee career path assignment updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating assignment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_assignment':
                try {
                    $stmt = $pdo->prepare("DELETE FROM employee_career_paths WHERE assignment_id=?");
                    $stmt->execute([$_POST['assignment_id']]);
                    $message = "Employee career path assignment deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting assignment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch employee career path assignments with related data
$stmt = $pdo->query("
    SELECT 
        ecp.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        jr.title as job_title,
        jr.department,
        cp.path_name,
        cp.department as path_department,
        cps.stage_name as current_stage_name
    FROM employee_career_paths ecp
    LEFT JOIN employee_profiles ep ON ecp.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN career_paths cp ON ecp.path_id = cp.path_id
    LEFT JOIN career_path_stages cps ON ecp.current_stage = cps.stage_id
    ORDER BY pi.first_name, pi.last_name, cp.path_name
");
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        jr.title as job_title,
        jr.department
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY pi.first_name, pi.last_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch career paths for dropdown
$stmt = $pdo->query("
    SELECT 
        path_id,
        path_name,
        department
    FROM career_paths
    WHERE status = 'Active'
    ORDER BY path_name
");
$careerPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch career path stages for dropdown
$stmt = $pdo->query("
    SELECT 
        stage_id,
        stage_name,
        path_id,
        stage_order
    FROM career_path_stages
    ORDER BY path_id, stage_order
");
$stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assignment statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_career_paths");
$totalAssignments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as active FROM employee_career_paths WHERE status = 'Active'");
$activeAssignments = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

$stmt = $pdo->query("SELECT COUNT(*) as completed FROM employee_career_paths WHERE status = 'Completed'");
$completedAssignments = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT employee_id) as unique_employees FROM employee_career_paths");
$uniqueEmployees = $stmt->fetch(PDO::FETCH_ASSOC)['unique_employees'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Career Paths Management - HR System</title>
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

        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .btn-primary {
            background: var(--azure-blue);
            border-color: var(--azure-blue);
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            font-weight: 600;
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
            background: #cce5ff;
            color: #004085;
        }

        .status-paused {
            background: #fff3cd;
            color: #856404;
        }

        .department-badge {
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .assignment-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-left: 5px solid var(--azure-blue);
        }

        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .assignment-title {
            color: var(--azure-blue-dark);
            font-weight: 600;
            margin: 0;
        }

        .assignment-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #666;
        }

        .meta-item i {
            color: var(--azure-blue);
        }

        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 8px;
            margin-top: 5px;
            overflow: hidden;
        }

        .progress-fill {
            background: var(--azure-blue);
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Employee Career Paths Management</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-route"></i>
                            <h3><?php echo $totalAssignments; ?></h3>
                            <h6>Total Assignments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-play-circle"></i>
                            <h3><?php echo $activeAssignments; ?></h3>
                            <h6>Active Assignments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-check-circle"></i>
                            <h3><?php echo $completedAssignments; ?></h3>
                            <h6>Completed</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-users"></i>
                            <h3><?php echo $uniqueEmployees; ?></h3>
                            <h6>Employees in Paths</h6>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="input-group" style="max-width: 400px;">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control" id="assignmentSearch" placeholder="Search assignments...">
                    </div>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addAssignmentModal">
                        <i class="fas fa-plus"></i> Add Assignment
                    </button>
                </div>

                <!-- Assignments Grid -->
                <div class="row" id="assignmentsGrid">
                    <?php foreach ($assignments as $assignment): ?>
                    <div class="col-md-6 col-lg-4 assignment-item">
                        <div class="assignment-card">
                            <div class="assignment-header">
                                <h5 class="assignment-title"><?php echo htmlspecialchars($assignment['employee_name']); ?></h5>
                                <span class="status-badge status-<?php echo strtolower($assignment['status']); ?>">
                                    <?php echo htmlspecialchars($assignment['status']); ?>
                                </span>
                            </div>
                            
                            <div class="assignment-meta">
                                <div class="meta-item">
                                    <i class="fas fa-route"></i>
                                    <span><?php echo htmlspecialchars($assignment['path_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span class="department-badge"><?php echo htmlspecialchars($assignment['path_department']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-layer-group"></i>
                                    <span><?php echo htmlspecialchars($assignment['current_stage_name'] ?: 'Not Started'); ?></span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($assignment['start_date'])); ?>
                                </small>
                            </div>
                            
                            <?php if ($assignment['target_completion_date']): ?>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Target Completion:</strong> <?php echo date('M d, Y', strtotime($assignment['target_completion_date'])); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($assignment['notes']): ?>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Notes:</strong> <?php echo htmlspecialchars(substr($assignment['notes'], 0, 100)) . (strlen($assignment['notes']) > 100 ? '...' : ''); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        <i class="fas fa-id-card"></i> Assignment ID: <?php echo $assignment['assignment_id']; ?>
                                    </small>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?php echo $assignment['assignment_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(<?php echo $assignment['assignment_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Assignments Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-route"></i> Employee Career Path Assignments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Career Path</th>
                                        <th>Department</th>
                                        <th>Current Stage</th>
                                        <th>Start Date</th>
                                        <th>Target Completion</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($assignment['employee_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($assignment['job_title']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment['path_name']); ?></td>
                                        <td><span class="department-badge"><?php echo htmlspecialchars($assignment['path_department']); ?></span></td>
                                        <td><?php echo htmlspecialchars($assignment['current_stage_name'] ?: 'Not Started'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($assignment['start_date'])); ?></td>
                                        <td>
                                            <?php echo $assignment['target_completion_date'] ? date('M d, Y', strtotime($assignment['target_completion_date'])) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($assignment['status']); ?>">
                                                <?php echo htmlspecialchars($assignment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?php echo $assignment['assignment_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(<?php echo $assignment['assignment_id']; ?>)">
                                                <i class="fas fa-trash"></i>
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
    </div>

    <!-- Add Assignment Modal -->
    <div class="modal fade" id="addAssignmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Career Path Assignment</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_assignment">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Employee *</label>
                                    <select class="form-control" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['employee_id']; ?>">
                                            <?php echo htmlspecialchars($employee['employee_name'] . ' (' . $employee['job_title'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Career Path *</label>
                                    <select class="form-control" name="path_id" required id="pathSelect">
                                        <option value="">Select Career Path</option>
                                        <?php foreach ($careerPaths as $path): ?>
                                        <option value="<?php echo $path['path_id']; ?>">
                                            <?php echo htmlspecialchars($path['path_name'] . ' (' . $path['department'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Current Stage</label>
                                    <select class="form-control" name="current_stage" id="stageSelect">
                                        <option value="">Select Stage</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status *</label>
                                    <select class="form-control" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="Active">Active</option>
                                        <option value="Paused">Paused</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Target Completion Date</label>
                                    <input type="date" class="form-control" name="target_completion_date">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes about the assignment"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success/Error Message Modal -->
    <?php if ($message): ?>
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header <?php echo $messageType === 'success' ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <h5 class="modal-title">
                        <?php echo $messageType === 'success' ? '<i class="fas fa-check-circle"></i> Success' : '<i class="fas fa-exclamation-circle"></i> Error'; ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Show message modal if there's a message
        <?php if ($message): ?>
        $(document).ready(function() {
            $('#messageModal').modal('show');
        });
        <?php endif; ?>

        // Search functionality
        $('#assignmentSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('.assignment-item').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Dynamic stage loading based on selected career path
        $('#pathSelect').change(function() {
            var pathId = $(this).val();
            var stageSelect = $('#stageSelect');
            stageSelect.empty();
            stageSelect.append('<option value="">Select Stage</option>');
            
            if (pathId) {
                <?php 
                $stagesJson = json_encode($stages);
                echo "var stages = $stagesJson;";
                ?>
                
                stages.forEach(function(stage) {
                    if (stage.path_id == pathId) {
                        stageSelect.append('<option value="' + stage.stage_id + '">' + stage.stage_order + '. ' + stage.stage_name + '</option>');
                    }
                });
            }
        });

        // Edit assignment function
        function editAssignment(assignmentId) {
            alert('Edit assignment with ID: ' + assignmentId);
        }

        // Delete assignment function
        function deleteAssignment(assignmentId) {
            if (confirm('Are you sure you want to delete this career path assignment?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_assignment">
                    <input type="hidden" name="assignment_id" value="${assignmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

