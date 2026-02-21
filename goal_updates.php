<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has HR role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Check user role - only HR and admin can access
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'hr'])) {
    header("Location: unauthorized.php");
    exit;
}

// Include database connection and helper functions
require_once 'dp.php';

// Database connection
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

// Handle form submissions
$message = '';
$messageType = '';

// Helper function to add goal update with full tracking
function addGoalUpdate($pdo, $goal_id, $progress, $comments, $user_id = null, $status_change = null) {
    try {
        // First, try with full columns. If it fails, fall back to basic columns
        $current_status = null;
        
        if ($status_change) {
            $stmt_status = $pdo->prepare("SELECT status FROM goals WHERE goal_id = ?");
            $stmt_status->execute([$goal_id]);
            $current_status = $stmt_status->fetchColumn();
        }
        
        $status_before = $current_status;
        $status_after = $status_change ?? null;
        $updated_by = $user_id ?? $_SESSION['user_id'] ?? null;
        
        // Try to insert with all columns
        try {
            $stmt = $pdo->prepare("
                INSERT INTO goal_updates (
                    goal_id,
                    update_date,
                    progress,
                    comments,
                    updated_by,
                    status_before,
                    status_after
                ) VALUES (?, CURDATE(), ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $goal_id,
                $progress,
                $comments,
                $updated_by,
                $status_before,
                $status_after
            ]);
        } catch (PDOException $e) {
            // If that fails, try without the new columns
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $stmt = $pdo->prepare("
                    INSERT INTO goal_updates (
                        goal_id,
                        update_date,
                        progress,
                        comments
                    ) VALUES (?, CURDATE(), ?, ?)
                ");
                
                $stmt->execute([
                    $goal_id,
                    $progress,
                    $comments
                ]);
            } else {
                throw $e;
            }
        }
        
        return true;
    } catch (PDOException $e) {
        throw new Exception("Error adding update: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_comment':
                // Add comment to goal update with tracking
                try {
                    $goal_id = $_POST['goal_id'];
                    $comment = $_POST['comment'];
                    
                    // Get current progress
                    $stmt = $pdo->prepare("SELECT progress FROM goals WHERE goal_id = ?");
                    $stmt->execute([$goal_id]);
                    $progress = $stmt->fetchColumn();
                    
                    addGoalUpdate($pdo, $goal_id, $progress, $comment);
                    
                    $message = "Comment added successfully!";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error adding comment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'update_goal_status':
                // Update goal status with tracking
                try {
                    $goal_id = $_POST['goal_id'];
                    $new_status = $_POST['status'];
                    
                    // Get current progress
                    $stmt = $pdo->prepare("SELECT progress FROM goals WHERE goal_id = ?");
                    $stmt->execute([$goal_id]);
                    $progress = $stmt->fetchColumn();
                    
                    // Update status in goals table
                    $stmt = $pdo->prepare("UPDATE goals SET status = ? WHERE goal_id = ?");
                    $stmt->execute([$new_status, $goal_id]);
                    
                    // Track the status change
                    addGoalUpdate($pdo, $goal_id, $progress, "Status changed to: " . $new_status, null, $new_status);
                    
                    $message = "Goal status updated successfully!";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error updating goal status: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'update_progress':
                // Update goal progress
                try {
                    $goal_id = $_POST['goal_id'];
                    $new_progress = $_POST['progress'];
                    $comment = $_POST['comment'] ?? '';
                    
                    // Update progress in goals table
                    $stmt = $pdo->prepare("UPDATE goals SET progress = ? WHERE goal_id = ?");
                    $stmt->execute([$new_progress, $goal_id]);
                    
                    // Track the progress change
                    $update_comment = "Progress updated to: " . $new_progress . "%";
                    if ($comment) {
                        $update_comment .= " - " . $comment;
                    }
                    
                    addGoalUpdate($pdo, $goal_id, $new_progress, $update_comment);
                    
                    $message = "Goal progress updated successfully!";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error updating progress: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete_update':
                // Delete an update (only admins or the person who created it)
                try {
                    $update_id = $_POST['update_id'];
                    
                    // Try to get updated_by, handling both old and new column names
                    try {
                        $stmt = $pdo->prepare("SELECT updated_by FROM goal_updates WHERE goal_update_id = ?");
                        $stmt->execute([$update_id]);
                        $update_creator = $stmt->fetchColumn();
                    } catch (PDOException $e) {
                        // If goal_update_id doesn't exist, try update_id
                        $stmt = $pdo->prepare("SELECT updated_by FROM goal_updates WHERE update_id = ?");
                        $stmt->execute([$update_id]);
                        $update_creator = $stmt->fetchColumn();
                    }
                    
                    // Verify authorization
                    $user_id = $_SESSION['user_id'] ?? null;
                    if ($_SESSION['role'] !== 'admin' && $update_creator != $user_id) {
                        throw new Exception("You don't have permission to delete this update");
                    }
                    
                    // Try to delete using goal_update_id first, then update_id
                    try {
                        $stmt = $pdo->prepare("DELETE FROM goal_updates WHERE goal_update_id = ?");
                        $stmt->execute([$update_id]);
                    } catch (PDOException $e) {
                        $stmt = $pdo->prepare("DELETE FROM goal_updates WHERE update_id = ?");
                        $stmt->execute([$update_id]);
                    }
                    
                    $message = "Update deleted successfully!";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error deleting update: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Get filter parameters
$employee_filter = $_GET['employee'] ?? '';
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Build query for goals with filters
$query = "
    SELECT
        g.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        pi.first_name,
        pi.last_name,
        jr.title as job_title,
        jr.department,
        d.department_name,
        (SELECT COUNT(*) FROM goal_updates WHERE goal_id = g.goal_id) as update_count,
        (SELECT update_date FROM goal_updates WHERE goal_id = g.goal_id ORDER BY update_date DESC LIMIT 1) as last_update_date,
        (SELECT comments FROM goal_updates WHERE goal_id = g.goal_id ORDER BY update_date DESC LIMIT 1) as last_comment
    FROM goals g
    LEFT JOIN employee_profiles ep ON g.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN departments d ON jr.department = d.department_name
    WHERE 1=1
";

$params = [];

if ($employee_filter) {
    $query .= " AND (CONCAT(pi.first_name, ' ', pi.last_name) LIKE ? OR ep.employee_number LIKE ?)";
    $params[] = "%$employee_filter%";
    $params[] = "%$employee_filter%";
}

if ($status_filter) {
    $query .= " AND g.status = ?";
    $params[] = $status_filter;
}

if ($department_filter) {
    $query .= " AND d.department_name = ?";
    $params[] = $department_filter;
}

$query .= " ORDER BY g.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter dropdown
$stmt = $pdo->query("SELECT DISTINCT department_name FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get goal statuses for filter
$statuses = ['Not Started', 'In Progress', 'Completed', 'Cancelled'];

// Calculate statistics
$totalGoals = count($goals);
$completedGoals = count(array_filter($goals, function($goal) { return $goal['status'] === 'Completed'; }));
$inProgressGoals = count(array_filter($goals, function($goal) { return $goal['status'] === 'In Progress'; }));
$overdueGoals = count(array_filter($goals, function($goal) {
    return $goal['status'] !== 'Completed' && $goal['end_date'] < date('Y-m-d');
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Updates Management - HR System</title>
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

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--azure-blue);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--azure-blue);
        }

        .goal-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .goal-card:hover {
            transform: translateY(-2px);
        }

        .goal-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-not-started { background: #e9ecef; color: #6c757d; }
        .status-in-progress { background: #cce5ff; color: #0066cc; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .progress-bar {
            height: 8px;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
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
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }

        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }

        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        }

        .modal-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group label {
            font-weight: 600;
            color: var(--azure-blue-dark);
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.2);
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

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            margin-bottom: 20px;
            position: relative;
        }

        .timeline-item:before {
            content: '';
            position: absolute;
            left: -35px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--azure-blue);
        }

        .timeline-item:after {
            content: '';
            position: absolute;
            left: -31px;
            top: 15px;
            width: 2px;
            height: calc(100% + 10px);
            background: #e9ecef;
        }

        .timeline-item:last-child:after {
            display: none;
        }

        .overdue {
            border-left: 4px solid #dc3545 !important;
        }

        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 15px;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-10 main-content">
                <h2 class="section-title"><i class="fas fa-tasks"></i> Goal Updates Management</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Goals</h6>
                                    <div class="stats-number"><?= $totalGoals ?></div>
                                </div>
                                <i class="fas fa-target fa-2x text-muted"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Completed</h6>
                                    <div class="stats-number text-success"><?= $completedGoals ?></div>
                                </div>
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">In Progress</h6>
                                    <div class="stats-number text-primary"><?= $inProgressGoals ?></div>
                                </div>
                                <i class="fas fa-clock fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Overdue</h6>
                                    <div class="stats-number text-danger"><?= $overdueGoals ?></div>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="employee">Employee</label>
                                <input type="text" id="employee" name="employee" class="form-control"
                                       value="<?= htmlspecialchars($employee_filter) ?>"
                                       placeholder="Search by name or ID">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status ?>" <?= $status_filter === $status ? 'selected' : '' ?>>
                                        <?= $status ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="department">Department</label>
                                <select id="department" name="department" class="form-control">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_name'] ?>" <?= $department_filter === $dept['department_name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary mr-2">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="goal_updates.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Goals List -->
                <div id="goalsContainer">
                    <?php foreach ($goals as $goal): ?>
                    <div class="goal-card <?= ($goal['status'] !== 'Completed' && $goal['end_date'] < date('Y-m-d')) ? 'overdue' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <h5 class="mb-2">
                                    <?= htmlspecialchars($goal['title']) ?>
                                    <small class="text-muted">(ID: <?= $goal['goal_id'] ?>)</small>
                                </h5>
                                <p class="text-muted mb-2"><?= htmlspecialchars($goal['description']) ?></p>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="goal-status status-<?= strtolower(str_replace(' ', '-', $goal['status'])) ?> mr-3">
                                        <?= htmlspecialchars($goal['status']) ?>
                                    </span>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($goal['employee_name']) ?> |
                                        <i class="fas fa-building"></i> <?= htmlspecialchars($goal['department']) ?> |
                                        <i class="fas fa-calendar-alt"></i>
                                        <?= date('M d, Y', strtotime($goal['start_date'])) ?> - <?= date('M d, Y', strtotime($goal['end_date'])) ?>
                                    </small>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-info" onclick="viewUpdates(<?= $goal['goal_id'] ?>)">
                                    <i class="fas fa-history"></i> Updates (<?= $goal['update_count'] ?>)
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="updateProgress(<?= $goal['goal_id'] ?>, <?= $goal['progress'] ?>)">
                                    <i class="fas fa-chart-line"></i> Progress
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="addComment(<?= $goal['goal_id'] ?>)">
                                    <i class="fas fa-comment"></i> Comment
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="updateStatus(<?= $goal['goal_id'] ?>, '<?= $goal['status'] ?>')">
                                    <i class="fas fa-edit"></i> Status
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">Progress</small>
                                <small class="text-muted"><?= $goal['progress'] ?>%</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-<?= $goal['progress'] >= 100 ? 'success' : ($goal['progress'] >= 50 ? 'primary' : 'warning') ?>"
                                     style="width: <?= $goal['progress'] ?>%"></div>
                            </div>
                        </div>

                        <?php if ($goal['last_update_date']): ?>
                        <div class="text-muted">
                            <small>Last updated: <?= date('M d, Y', strtotime($goal['last_update_date'])) ?>
                            <?php if ($goal['last_comment']): ?>
                                - <?= htmlspecialchars(substr($goal['last_comment'], 0, 50)) ?><?php if (strlen($goal['last_comment']) > 50): ?>...<?php endif; ?>
                            <?php endif; ?>
                            </small>
                        </div>
                        <?php endif; ?>

                        <?php if ($goal['status'] !== 'Completed' && $goal['end_date'] < date('Y-m-d')): ?>
                        <div class="mt-2">
                            <span class="badge badge-danger">Overdue</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($goals)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-3"></i>
                        <h4>No goals found</h4>
                        <p class="text-muted">Try adjusting your filters or check back later for new goals.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Comment Modal -->
    <div id="commentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Add Comment</h4>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="commentForm" method="POST">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" id="comment_goal_id" name="goal_id">

                    <div class="form-group">
                        <label for="comment">Comment</label>
                        <textarea id="comment" name="comment" class="form-control" rows="4" required
                                  placeholder="Add your feedback or notes about this goal..."></textarea>
                    </div>

                    <div class="text-right">
                        <button type="button" class="btn btn-secondary mr-2" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Comment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Update Goal Status</h4>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="statusForm" method="POST">
                    <input type="hidden" name="action" value="update_goal_status">
                    <input type="hidden" id="status_goal_id" name="goal_id">

                    <div class="form-group">
                        <label for="goal_status">Status</label>
                        <select id="goal_status" name="status" class="form-control" required>
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="text-right">
                        <button type="button" class="btn btn-secondary mr-2" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Progress Update Modal -->
    <div id="progressModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Update Goal Progress</h4>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="progressForm" method="POST">
                    <input type="hidden" name="action" value="update_progress">
                    <input type="hidden" id="progress_goal_id" name="goal_id">

                    <div class="form-group">
                        <label for="goal_progress">Progress (%)</label>
                        <input type="range" id="goal_progress" name="progress" class="form-control" 
                               min="0" max="100" value="50" oninput="updateProgressValue(this.value)">
                        <div class="mt-2">
                            <span id="progressValue">50</span>%
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="progress_comment">Comment (Optional)</label>
                        <textarea id="progress_comment" name="comment" class="form-control" rows="3"
                                  placeholder="Add notes about this progress update..."></textarea>
                    </div>

                    <div class="text-right">
                        <button type="button" class="btn btn-secondary mr-2" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Progress</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Updates History Modal -->
    <div id="updatesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Goal Updates History</h4>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="updatesContent">
                    <!-- Updates will be loaded here via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function addComment(goalId) {
            const modal = document.getElementById('commentModal');
            document.getElementById('comment_goal_id').value = goalId;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function updateStatus(goalId, currentStatus) {
            const modal = document.getElementById('statusModal');
            document.getElementById('status_goal_id').value = goalId;
            document.getElementById('goal_status').value = currentStatus;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function updateProgress(goalId, currentProgress) {
            const modal = document.getElementById('progressModal');
            document.getElementById('progress_goal_id').value = goalId;
            document.getElementById('goal_progress').value = currentProgress;
            document.getElementById('progressValue').textContent = currentProgress;
            document.getElementById('progress_comment').value = '';
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function updateProgressValue(value) {
            document.getElementById('progressValue').textContent = value;
        }

        function deleteUpdate(updateId, goalId) {
            if (confirm('Are you sure you want to delete this update? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_update"><input type="hidden" name="update_id" value="' + updateId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteUpdateConfirm(updateId) {
            if (confirm('Are you sure you want to delete this update? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_update"><input type="hidden" name="update_id" value="' + updateId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewUpdates(goalId) {
            const modal = document.getElementById('updatesModal');
            const content = document.getElementById('updatesContent');

            // Load updates via AJAX
            fetch(`get_goal_updates.php?goal_id=${goalId}`)
                .then(response => response.text())
                .then(data => {
                    content.innerHTML = data;
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                })
                .catch(error => {
                    content.innerHTML = '<p class="text-danger">Error loading updates: ' + error.message + '</p>';
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                });
        }

        function closeModal() {
            document.getElementById('commentModal').style.display = 'none';
            document.getElementById('statusModal').style.display = 'none';
            document.getElementById('progressModal').style.display = 'none';
            document.getElementById('updatesModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const commentModal = document.getElementById('commentModal');
            const statusModal = document.getElementById('statusModal');
            const progressModal = document.getElementById('progressModal');
            const updatesModal = document.getElementById('updatesModal');

            if (event.target === commentModal || event.target === statusModal || 
                event.target === progressModal || event.target === updatesModal) {
                closeModal();
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
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
