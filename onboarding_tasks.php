<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_task':
                try {
                    $task_name = $_POST['task_name'] ?? '';
                    $description = $_POST['task_description'] ?? '';
                    $task_type = $_POST['task_type'] ?? 'Administrative';
                    $department_id = $_POST['department_id'] ?? null;
                    
                    // If department is empty, set to NULL
                    if (empty($department_id)) {
                        $department_id = null;
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO onboarding_tasks (task_name, description, task_type, department_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('sssi', $task_name, $description, $task_type, $department_id);
                    $stmt->execute();
                    
                    $message = "✅ Task created successfully!";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "❌ Error adding task: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_task':
                try {
                    $stmt = $conn->prepare("DELETE FROM onboarding_tasks WHERE task_id = ?");
                    $stmt->bind_param('i', $_POST['task_id']);
                    $stmt->execute();
                    
                    $message = "✅ Task deleted successfully!";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "❌ Error deleting task: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch all tasks
$result = $conn->query("SELECT ot.*, d.department_name 
                       FROM onboarding_tasks ot 
                       LEFT JOIN departments d ON ot.department_id = d.department_id 
                       ORDER BY 
                           CASE WHEN ot.department_id IS NOT NULL THEN 0 ELSE 1 END,
                           d.department_name ASC,
                           ot.task_name ASC");
$all_tasks = $result->fetch_all(MYSQLI_ASSOC);

// Separate into department-specific and general tasks
$dept_tasks = [];
$general_tasks = [];
foreach ($all_tasks as $task) {
    if ($task['department_id'] !== null) {
        $dept_tasks[] = $task;
    } else {
        $general_tasks[] = $task;
    }
}

// Fetch all departments
$result = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding Task Management - HR System</title>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title">⚙️ Onboarding Task Management</h2>
                    <div>
                        <a href="onboarding.php" class="btn btn-success ml-2">
                            <i class="fas fa-arrow-left"></i> Back to Onboarding
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Add New Task Card -->
                <div class="card mb-4" style="border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                    <div class="card-header bg-success text-white" style="border-radius: 15px 15px 0 0;">
                        <h5 class="mb-0"><i class="fas fa-plus-circle mr-2"></i>Create New Onboarding Task</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_task">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="task_name"><strong>Task Name:</strong></label>
                                        <input type="text" id="task_name" name="task_name" class="form-control" placeholder="e.g., Complete HR Orientation" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="task_type"><strong>Task Type:</strong></label>
                                        <select id="task_type" name="task_type" class="form-control">
                                            <option value="Administrative">Administrative</option>
                                            <option value="Training">Training</option>
                                            <option value="Technical">Technical</option>
                                            <option value="Security">Security</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="department_id"><strong>Department (Optional):</strong></label>
                                        <select id="department_id" name="department_id" class="form-control">
                                            <option value="">-- General Task (All Departments) --</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['department_id']; ?>">
                                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">Leave empty to apply to all new hires</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="task_description"><strong>Description:</strong></label>
                                        <input type="text" id="task_description" name="task_description" class="form-control" placeholder="Brief description of the task" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success btn-lg" style="width: 100%;">
                                <i class="fas fa-save mr-2"></i>Create Task
                            </button>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <!-- Department-Specific Tasks -->
                    <div class="col-lg-6">
                        <div class="card" style="border-radius: 15px; box-shadow: 0 5px 20px rgba(233, 30, 99, 0.1); border-left: 5px solid #E91E63;">
                            <div class="card-header" style="background: linear-gradient(135deg, #E91E63 0%, #F06292 100%); border-radius: 15px 15px 0 0; color: white;">
                                <h5 class="mb-0"><i class="fas fa-sitemap mr-2"></i>Department-Specific Tasks</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($dept_tasks)): ?>
                                    <div class="list-group">
                                        <?php foreach ($dept_tasks as $task): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">
                                                            <?php echo htmlspecialchars($task['task_name']); ?>
                                                            <span class="badge badge-primary ml-2"><?php echo htmlspecialchars($task['task_type']); ?></span>
                                                        </h6>
                                                        <p class="mb-1 text-muted small"><?php echo htmlspecialchars($task['description']); ?></p>
                                                        <small class="text-info"><i class="fas fa-building"></i> <?php echo htmlspecialchars($task['department_name']); ?></small>
                                                    </div>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this task?');">
                                                        <input type="hidden" name="action" value="delete_task">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm ml-2">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>No department-specific tasks yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- General Tasks -->
                    <div class="col-lg-6">
                        <div class="card" style="border-radius: 15px; box-shadow: 0 5px 20px rgba(76, 175, 80, 0.1); border-left: 5px solid #4CAF50;">
                            <div class="card-header" style="background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 100%); border-radius: 15px 15px 0 0; color: white;">
                                <h5 class="mb-0"><i class="fas fa-globe mr-2"></i>General Tasks (All Departments)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($general_tasks)): ?>
                                    <div class="list-group">
                                        <?php foreach ($general_tasks as $task): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">
                                                            <?php echo htmlspecialchars($task['task_name']); ?>
                                                            <span class="badge badge-success ml-2"><?php echo htmlspecialchars($task['task_type']); ?></span>
                                                        </h6>
                                                        <p class="mb-1 text-muted small"><?php echo htmlspecialchars($task['description']); ?></p>
                                                        <small class="text-success"><i class="fas fa-check-circle"></i> Applies to all departments</small>
                                                    </div>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this task?');">
                                                        <input type="hidden" name="action" value="delete_task">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm ml-2">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>No general tasks yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle"></i> <strong>Information:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Department-specific tasks will be assigned only to applicants from that department</li>
                        <li>General tasks will be assigned to all new hires regardless of department</li>
                        <li>Tasks are automatically assigned when you use the "Assign" button on the Applicant Onboarding page</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>