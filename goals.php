<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config.php';

$user_id = $_SESSION['user_id'];

// Get employee_id from users table
try {
    $stmt = $conn->prepare("SELECT employee_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $employee_id = $user['employee_id'] ?? null;
    $is_employee = $employee_id !== null;
} catch (PDOException $e) {
    die("Error fetching employee profile: " . $e->getMessage());
}

// Handle new goal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_goal') {
    if (!$is_employee) {
        $error = "Only employees can add goals.";
    } else {
        $title = trim($_POST['goal_name']);
        $description = trim($_POST['goal_description']);
        $start_date = date('Y-m-d'); // Current date as start date
        $end_date = $_POST['due_date'];
        $status = $_POST['goal_status'];
        $progress = 0; // New goals start at 0%
        $weight = 100; // Default weight

        if ($title && $description && $end_date && $status) {
            try {
                $insert = $conn->prepare("INSERT INTO goals (employee_id, title, description, start_date, end_date, status, progress, weight, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $insert->execute([$employee_id, $title, $description, $start_date, $end_date, $status, $progress, $weight]);
                header("Location: goals.php");
                exit;
            } catch (PDOException $e) {
                $error = "Error adding goal: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    }
}

// Fetch goals for employee
$goals = [];
if ($is_employee) {
    try {
        $stmt = $conn->prepare("SELECT * FROM goals WHERE employee_id = ? ORDER BY created_at DESC");
        $stmt->execute([$employee_id]);
        $goals = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Error fetching goals: " . $e->getMessage());
    }
}

// Fetch goal updates for timeline
$goal_updates = [];
if ($is_employee) {
    try {
        $stmt = $conn->prepare("SELECT gu.*, g.title FROM goal_updates gu JOIN goals g ON gu.goal_id = g.goal_id WHERE g.employee_id = ? ORDER BY gu.update_date DESC LIMIT 10");
        $stmt->execute([$employee_id]);
        $goal_updates = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Error fetching goal updates: " . $e->getMessage());
    }
}

// Calculate statistics
$total_goals = count($goals);
$completed_goals = 0;
$in_progress_goals = 0;
$pending_goals = 0;
$total_progress = 0.0;

foreach ($goals as $goal) {
    $status = strtolower($goal['status']);
    if ($status === 'completed') {
        $completed_goals++;
    } elseif ($status === 'in progress') {
        $in_progress_goals++;
    } elseif ($status === 'not started' || $status === 'pending') {
        $pending_goals++;
    }
    $total_progress += floatval($goal['progress']);
}

$average_progress = $total_goals > 0 ? round($total_progress / $total_goals, 1) : 0;
$success_rate = $total_goals > 0 ? round(($completed_goals / $total_goals) * 100, 1) : 0;

// Get employee name for display
$employee_name = 'N/A';
if ($is_employee) {
    try {
        $stmt = $conn->prepare("SELECT first_name, last_name FROM employee_profiles WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch();
        $employee_name = $employee ? $employee['first_name'] . ' ' . $employee['last_name'] : 'Unknown';
    } catch (PDOException $e) {
        $employee_name = 'Unknown';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Goals - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Custom styles for goals page */
        .goal-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .goal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .goal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .goal-progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
        }

        .goal-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.6s ease;
        }

        .goal-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-completed { background-color: #d4edda; color: #155724; }
        .status-in-progress { background-color: #fff3cd; color: #856404; }
        .status-pending { background-color: #f8d7da; color: #721c24; }

        .goal-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .goal-card:hover .goal-actions {
            opacity: 1;
        }

        .add-goal-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .add-goal-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .goal-stats {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .goal-timeline {
            position: relative;
            padding-left: 30px;
        }

        .goal-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title mb-0">
                        <i class="fas fa-bullseye mr-3"></i>
                        Employee Goals 
                    </h2>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <button class="btn btn-outline-secondary">
                            <i class="fas fa-sort mr-2"></i>Sort
                        </button>
                    </div>
                </div>

                <?php if (!$is_employee): ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle"></i> Goals are only available for employees. Please contact HR if you believe this is an error.
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Goals Statistics -->
                <div class="goal-stats">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $completed_goals; ?></h3>
                            <p>Completed Goals</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $in_progress_goals; ?></h3>
                            <p>In Progress Goals</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $pending_goals; ?></h3>
                            <p>Pending Goals</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $average_progress; ?>%</h3>
                            <p>Average Progress</p>
                        </div>
                    </div>
                </div>

                <!-- Goals Grid -->
                <div class="row">
                    <?php foreach ($goals as $goal): ?>
                        <?php
                            $statusClass = 'status-pending';
                            $statusText = 'Pending';
                            $progressPercent = 0;
                            $statusLower = strtolower($goal['status']);
                            if ($statusLower === 'completed') {
                                $statusClass = 'status-completed';
                                $statusText = 'Completed';
                                $progressPercent = 100;
                            } elseif ($statusLower === 'in progress') {
                                $statusClass = 'status-in-progress';
                                $statusText = 'In Progress';
                                $progressPercent = 50;
                            } elseif ($statusLower === 'pending' || $statusLower === 'not started') {
                                $statusClass = 'status-pending';
                                $statusText = 'Pending';
                                $progressPercent = 0;
                            }
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card goal-card h-100">
                                <div class="goal-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($goal['title']); ?></h5>
                                            <small>Department: N/A</small>
                                        </div>
                                        <span class="goal-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($goal['description'])); ?></p>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small>Progress</small>
                                            <small><?php echo $progressPercent; ?>%</small>
                                        </div>
                                        <div class="goal-progress">
                                            <div class="goal-progress-bar" style="width: <?php echo $progressPercent; ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Due Date</small>
                                            <strong><?php echo date('M d, Y', strtotime($goal['end_date'])); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Owner</small>
                                            <strong><?php echo htmlspecialchars($employee_name); ?></strong>
                                        </div>
                                    </div>

                                    <div class="goal-actions text-center">
                                        <button class="btn btn-sm btn-outline-primary mr-2" title="Edit Goal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success mr-2" title="Mark as Completed">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" title="Delete Goal">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Goals Timeline -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history mr-2"></i>
                            Recent Goal Activities
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="goal-timeline">
                            <?php if (count($goal_updates) > 0): ?>
                                <?php foreach ($goal_updates as $update): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="mb-1">Goal "<?php echo htmlspecialchars($update['title']); ?>" updated</h6>
                                                <p class="text-muted mb-0">Progress increased to <?php echo htmlspecialchars($update['progress']); ?>%</p>
                                            </div>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($update['update_date'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No recent goal activities.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Goal Button -->
    <?php if ($is_employee): ?>
    <button class="btn btn-primary add-goal-btn" data-toggle="tooltip" title="Add New Goal" onclick="showAddGoalModal()">
        <i class="fas fa-plus"></i>
    </button>
    <?php endif; ?>

    <!-- Add Goal Modal -->
    <div class="modal fade" id="goalModal" tabindex="-1" role="dialog" aria-labelledby="goalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="goalModalLabel">Add New Goal</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="goalForm" method="POST" action="goals.php">
                    <input type="hidden" name="action" value="add_goal">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="goal_name">Goal Name</label>
                            <input type="text" class="form-control" id="goal_name" name="goal_name" required>
                        </div>

                        <div class="form-group">
                            <label for="goal_description">Description</label>
                            <textarea class="form-control" id="goal_description" name="goal_description" rows="3" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="due_date">Due Date</label>
                                    <input type="date" class="form-control" id="due_date" name="due_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="goal_status">Status</label>
                                    <select class="form-control" id="goal_status" name="goal_status" required>
                                        <option value="pending">Pending</option>
                                        <option value="in progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Goal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        var isEmployee = <?php echo $is_employee ? 'true' : 'false'; ?>;

        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });

        function showAddGoalModal() {
            if (!isEmployee) {
                alert("Only employees can add goals.");
                return;
            }
            $('#goalModal').modal('show');
        }
    </script>
</body>
</html>
