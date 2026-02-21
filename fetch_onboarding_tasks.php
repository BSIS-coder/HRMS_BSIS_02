<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

require_once 'db_connect.php';

$onboarding_id = $_GET['onboarding_id'] ?? 0;

if (!$onboarding_id) {
    echo '<div class="alert alert-warning">Invalid onboarding ID</div>';
    exit;
}

// Fetch tasks for this applicant
$stmt = $conn->prepare("SELECT eot.*, ot.task_name, ot.description, ot.task_type
                        FROM employee_onboarding_tasks eot
                        JOIN onboarding_tasks ot ON eot.task_id = ot.task_id
                        WHERE eot.onboarding_id = ?
                        ORDER BY ot.task_name");
$stmt->bind_param('i', $onboarding_id);
$stmt->execute();
$result = $stmt->get_result();
$tasks = $result->fetch_all(MYSQLI_ASSOC);
?>

<?php if (!empty($tasks)): ?>
    <div class="task-list">
        <?php foreach ($tasks as $task): 
            $status_color = $task['status'] === 'Completed' ? 'success' : 
                          ($task['status'] === 'Failed' ? 'danger' : 
                          ($task['status'] === 'In Progress' ? 'warning' : 'secondary'));
        ?>
            <div class="card mb-3 border-<?php echo $status_color; ?>">
                <div class="card-body">
                    <div class="row align-items-start">
                        <div class="col-md-8">
                            <h6 class="mb-1">
                                <?php echo htmlspecialchars($task['task_name']); ?>
                            </h6>
                            <p class="text-muted small mb-2">
                                <?php echo htmlspecialchars($task['description']); ?>
                            </p>
                            <div>
                                <span class="badge badge-<?php echo $status_color; ?>">
                                    <?php echo htmlspecialchars($task['status']); ?>
                                </span>
                                <span class="badge badge-light ml-2">
                                    <?php echo htmlspecialchars($task['task_type']); ?>
                                </span>
                            </div>
                            <?php if ($task['completion_date']): ?>
                                <small class="text-muted d-block mt-2">
                                    Completed: <?php echo date('M d, Y', strtotime($task['completion_date'])); ?>
                                </small>
                            <?php endif; ?>
                            <?php if ($task['due_date']): ?>
                                <small class="text-muted d-block">
                                    Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                </small>
                            <?php endif; ?>
                            <?php if ($task['notes']): ?>
                                <div class="mt-2 p-2 bg-light border rounded">
                                    <strong>Notes:</strong>
                                    <p class="mb-0 small"><?php echo htmlspecialchars($task['notes']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <?php if ($task['status'] === 'Not Started' || $task['status'] === 'In Progress'): ?>
                                <div class="btn-group-vertical w-100">
                                    <form method="POST" action="onboarding.php" class="mb-2">
                                        <input type="hidden" name="action" value="complete_task">
                                        <input type="hidden" name="progress_id" value="<?php echo $task['employee_task_id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="fas fa-check mr-1"></i>Complete
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="failTaskFromModal(<?php echo $task['employee_task_id']; ?>)">
                                        <i class="fas fa-times mr-1"></i>Mark as Failed
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0 p-2 small">
                                    <i class="fas fa-info-circle"></i> Task cannot be modified
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-warning text-center">
        <i class="fas fa-inbox fa-2x mb-2"></i>
        <p class="mb-0">No tasks assigned yet</p>
    </div>
<?php endif; ?>

<script>
function failTaskFromModal(progressId) {
    $('#failProgressId').val(progressId);
    $('#failTaskModal').modal('show');
}
</script>
