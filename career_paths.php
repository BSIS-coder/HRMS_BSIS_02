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
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
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
                <ul class="nav nav-tabs mb-4" id="careerTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="paths-tab" data-toggle="tab" href="#paths" role="tab">
                            <i class="fas fa-road"></i> Career Paths
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="stages-tab" data-toggle="tab" href="#stages" role="tab">
                            <i class="fas fa-route"></i> Career Stages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="assignments-tab" data-toggle="tab" href="#assignments" role="tab">
                            <i class="fas fa-user-check"></i> Employee Assignments
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="careerTabsContent">
                    <!-- Career Paths Tab -->
                    <div class="tab-pane fade show active" id="paths" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="pathSearch" placeholder="Search career paths...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addCareerPathModal">
                                <i class="fas fa-plus"></i> Add Career Path
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-road"></i> Career Paths</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Path Name</th>
                                                <th>Department</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($careerPaths as $path): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($path['path_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($path['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars(substr($path['description'], 0, 50)) . (strlen($path['description']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editCareerPath(<?php echo $path['path_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCareerPath(<?php echo $path['path_id']; ?>)">
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

                    <!-- Career Stages Tab -->
                    <div class="tab-pane fade" id="stages" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="stageSearch" placeholder="Search career stages...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addCareerStageModal">
                                <i class="fas fa-plus"></i> Add Career Stage
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-route"></i> Career Path Stages</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
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
                                            <?php foreach ($careerStages as $stage): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($stage['path_name']); ?></strong></td>
                                                <td><span class="stage-badge">Stage <?php echo $stage['stage_order']; ?></span></td>
                                                <td><?php echo htmlspecialchars($stage['job_role_title']); ?></td>
                                                <td><?php echo $stage['minimum_time_in_role']; ?> months</td>
                                                <td><?php echo htmlspecialchars(substr($stage['required_skills'], 0, 30)) . (strlen($stage['required_skills']) > 30 ? '...' : ''); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editCareerStage(<?php echo $stage['stage_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
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

                    <!-- Employee Assignments Tab -->
                    <div class="tab-pane fade" id="assignments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="assignmentSearch" placeholder="Search assignments...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#assignEmployeeModal">
                                <i class="fas fa-plus"></i> Assign Employee
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user-check"></i> Employee Career Path Assignments</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
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
                                            <?php foreach ($employeePaths as $assignment): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($assignment['path_name']); ?></td>
                                                <td><span class="stage-badge">Stage <?php echo $assignment['stage_order']; ?></span></td>
                                                <td><?php echo htmlspecialchars($assignment['current_role']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($assignment['start_date'])); ?></td>
                                                <td><?php echo $assignment['target_completion_date'] ? date('M d, Y', strtotime($assignment['target_completion_date'])) : 'N/A'; ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($assignment['status']); ?>">
                                                        <?php echo htmlspecialchars($assignment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?php echo $assignment['employee_path_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
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
        </div>
    </div>

    <!-- Add Career Path Modal -->
    <div class="modal fade" id="addCareerPathModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Career Path</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_career_path">
                        <div class="form-group">
                            <label>Path Name *</label>
                            <input type="text" class="form-control" name="path_name" required>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Description of the career path"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Career Path</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Career Stage Modal -->
    <div class="modal fade" id="addCareerStageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Career Stage</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_career_stage">
                        <div class="form-group">
                            <label>Career Path *</label>
                            <select class="form-control" name="path_id" required>
                                <option value="">Select Career Path</option>
                                <?php foreach ($careerPaths as $path): ?>
                                <option value="<?php echo $path['path_id']; ?>">
                                    <?php echo htmlspecialchars($path['path_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Job Role *</label>
                            <select class="form-control" name="job_role_id" required>
                                <option value="">Select Job Role</option>
                                <?php foreach ($jobRoles as $role): ?>
                                <option value="<?php echo $role['job_role_id']; ?>">
                                    <?php echo htmlspecialchars($role['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Stage Order *</label>
                                    <input type="number" class="form-control" name="stage_order" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Min Time in Role (Months) *</label>
                                    <input type="number" class="form-control" name="minimum_time_in_role" min="1" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Required Skills</label>
                            <textarea class="form-control" name="required_skills" rows="2" placeholder="Skills required for this stage"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Required Experience</label>
                            <textarea class="form-control" name="required_experience" rows="2" placeholder="Experience requirements"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Employee Modal -->
    <div class="modal fade" id="assignEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Assign Employee to Career Path</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_employee_path">
                        <div class="form-group">
                            <label>Employee *</label>
                            <select class="form-control" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['employee_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Career Path *</label>
                            <select class="form-control" name="path_id" id="pathSelect" required>
                                <option value="">Select Career Path</option>
                                <?php foreach ($careerPaths as $path): ?>
                                <option value="<?php echo $path['path_id']; ?>">
                                    <?php echo htmlspecialchars($path['path_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Current Stage *</label>
                            <select class="form-control" name="current_stage_id" id="stageSelect" required>
                                <option value="">Select Career Path first</option>
                            </select>
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
                            <label>Status *</label>
                            <select class="form-control" name="status" required>
                                <option value="Active">Active</option>
                                <option value="On Hold">On Hold</option>
                                <option value="Completed">Completed</option>
                                <option value="Abandoned">Abandoned</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Employee</button>
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
        $('#pathSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#paths table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        $('#stageSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#stages table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        $('#assignmentSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#assignments table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Dynamic stage loading based on career path selection
        $('#pathSelect').change(function() {
            var pathId = $(this).val();
            var stageSelect = $('#stageSelect');
            
            stageSelect.html('<option value="">Loading stages...</option>');
            
            if (pathId) {
                // Filter stages for the selected path
                var stages = <?php echo json_encode($careerStages); ?>;
                var filteredStages = stages.filter(function(stage) {
                    return stage.path_id == pathId;
                });
                
                stageSelect.html('<option value="">Select Stage</option>');
                filteredStages.forEach(function(stage) {
                    stageSelect.append('<option value="' + stage.stage_id + '">Stage ' + stage.stage_order + ' - ' + stage.job_role_title + '</option>');
                });
            } else {
                stageSelect.html('<option value="">Select Career Path first</option>');
            }
        });

        // Edit functions
        function editCareerPath(pathId) {
            alert('Edit career path with ID: ' + pathId);
        }

        function editCareerStage(stageId) {
            alert('Edit career stage with ID: ' + stageId);
        }

        function editAssignment(assignmentId) {
            alert('Edit assignment with ID: ' + assignmentId);
        }

        // Delete career path function
        function deleteCareerPath(pathId) {
            if (confirm('Are you sure you want to delete this career path?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_career_path">
                    <input type="hidden" name="path_id" value="${pathId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

</html>
