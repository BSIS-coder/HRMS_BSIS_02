<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';

$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'complete_task':
            $stmt = $conn->prepare("UPDATE employee_onboarding_tasks SET status = 'Completed', completion_date = NOW() WHERE employee_task_id = ?");
            $stmt->execute([$_POST['task_id']]);
            $success_message = "✅ Task completed!";
            break;
            
        case 'start_onboarding':
            $employee_id = $_POST['employee_id'];
            
            // Create employee onboarding record
            $stmt = $conn->prepare("INSERT INTO employee_onboarding (employee_id, start_date, expected_completion_date, status) VALUES (?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'In Progress')");
            $stmt->execute([$employee_id]);
            $onboarding_id = $conn->lastInsertId();
            
            // Create tasks for this employee
            $conn->exec("INSERT INTO employee_onboarding_tasks (onboarding_id, task_id, due_date, status)
                         SELECT $onboarding_id, ot.task_id, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Not Started'
                         FROM onboarding_tasks ot
                         WHERE ot.task_type = 'employee' OR ot.task_type IS NULL");
            
            $success_message = "🎉 Employee onboarding started!";
            break;
    }
}

// Get employees ready for onboarding (newly hired or promoted)
$employees_ready = $conn->query("SELECT * FROM employees WHERE status = 'Active' 
                                AND employee_id NOT IN (SELECT employee_id FROM employee_onboarding WHERE status IN ('In Progress', 'Completed'))
                                ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get employees currently in onboarding
$employees_onboarding = $conn->query("SELECT e.*, eo.onboarding_id, eo.start_date, eo.expected_completion_date, eo.status as onboarding_status,
                                     COUNT(eot.employee_task_id) as total_tasks,
                                     COUNT(CASE WHEN eot.status = 'Completed' THEN 1 END) as completed_tasks
                                     FROM employees e
                                     JOIN employee_onboarding eo ON e.employee_id = eo.employee_id
                                     LEFT JOIN employee_onboarding_tasks eot ON eo.onboarding_id = eot.onboarding_id
                                     WHERE eo.status = 'In Progress'
                                     GROUP BY e.employee_id, eo.onboarding_id
                                     ORDER BY eo.start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$stats = ['ready' => count($employees_ready), 'in_progress' => count($employees_onboarding)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Onboarding - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2>👥 Employee Onboarding</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['ready']; ?></h3>
                                <p class="stats-label">Ready for Onboarding</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <h3 class="stats-number"><a href="onboarding_tasks.php?type=employee" class="text-decoration-none">Manage</a></h3>
                                <p class="stats-label">Employee Tasks</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ready for Onboarding -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-user-plus"></i> Employees Ready for Onboarding</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($employees_ready) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Position</th>
                                            <th>Hired Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($employees_ready as $employee): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($employee['current_position']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                                                <td>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="start_onboarding">
                                                        <input type="hidden" name="employee_id" value="<?php echo $employee['employee_id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-play"></i> Start Onboarding
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> No employees ready for onboarding.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Employees in Onboarding -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-users"></i> Employees in Onboarding Process</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($employees_onboarding) > 0): ?>
                            <?php foreach($employees_onboarding as $employee): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                                <small class="text-muted"> - <?php echo htmlspecialchars($employee['current_position']); ?></small>
                                            </div>
                                            <div>
                                                <span class="badge badge-info"><?php echo $employee['completed_tasks']; ?>/<?php echo $employee['total_tasks']; ?> Tasks</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $tasks_query = $conn->prepare("SELECT eot.*, ot.task_name, ot.description FROM employee_onboarding_tasks eot 
                                                                      JOIN onboarding_tasks ot ON eot.task_id = ot.task_id 
                                                                      WHERE eot.onboarding_id = ? ORDER BY ot.task_id");
                                        $tasks_query->execute([$employee['onboarding_id']]);
                                        $employee_tasks = $tasks_query->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        <div class="row">
                                            <?php foreach($employee_tasks as $task): ?>
                                                <div class="col-md-4 mb-2">
                                                    <div class="card border-<?php echo $task['status'] == 'Completed' ? 'success' : 'warning'; ?>">
                                                        <div class="card-body p-2">
                                                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($task['task_name']); ?></h6>
                                                            <p class="card-text small"><?php echo htmlspecialchars($task['description']); ?></p>
                                                            <?php if ($task['status'] != 'Completed'): ?>
                                                                <form method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="complete_task">
                                                                    <input type="hidden" name="task_id" value="<?php echo $task['employee_task_id']; ?>">
                                                                    <button type="submit" class="btn btn-success btn-sm">
                                                                        <i class="fas fa-check"></i> Complete
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="badge badge-success">✅ Completed</span>
                                                                <br><small class="text-muted"><?php echo date('M d, Y', strtotime($task['completion_date'])); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <h5><i class="fas fa-info-circle"></i> No Employees in Onboarding</h5>
                                <p>No employees are currently in the onboarding process.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>