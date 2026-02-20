<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

$candidate_onboarding_id = $_GET['candidate_onboarding_id'] ?? 0;

if (!$candidate_onboarding_id) {
    echo '<div class="alert alert-warning">Invalid onboarding ID</div>';
    exit;
}

// Fetch tasks for this candidate onboarding
$query = $conn->prepare("
    SELECT 
        cot.candidate_task_id,
        cot.due_date,
        cot.status,
        cot.completion_date,
        cot.notes,
        ot.task_id,
        ot.task_name,
        ot.description
    FROM candidate_onboarding_tasks cot
    JOIN onboarding_tasks ot ON cot.task_id = ot.task_id
    WHERE cot.candidate_onboarding_id = ?
    ORDER BY cot.due_date ASC
");
$query->bind_param('i', $candidate_onboarding_id);
$query->execute();
$tasks = $query->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($tasks)):
?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No tasks assigned yet. Please assign tasks from the Actions column.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <thead class="table-light">
                <tr>
                    <th><i class="fas fa-tasks"></i> Task</th>
                    <th><i class="fas fa-calendar"></i> Due</th>
                    <th><i class="fas fa-check"></i> Status</th>
                    <th style="width: 150px;"><i class="fas fa-cogs"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): 
                    $due_date = strtotime($task['due_date']);
                    $today = strtotime(date('Y-m-d'));
                    $is_overdue = $due_date < $today && $task['status'] !== 'Completed';
                ?>
                <tr class="<?php echo $task['status'] === 'Completed' ? 'table-success' : ($is_overdue ? 'table-danger' : ''); ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($task['task_name']); ?></strong>
                        <?php if ($task['description']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 50)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $is_overdue ? 'danger' : 'info'; ?>">
                            <?php echo date('M d', $due_date); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        if ($task['status'] === 'Completed') {
                            echo '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Done</span>';
                        } elseif ($task['status'] === 'In Progress') {
                            echo '<span class="badge badge-primary"><i class="fas fa-spinner"></i> In Progress</span>';
                        } elseif ($task['status'] === 'Cancelled') {
                            echo '<span class="badge badge-secondary"><i class="fas fa-ban"></i> Cancelled</span>';
                        } else {
                            echo '<span class="badge badge-warning"><i class="fas fa-hourglass-start"></i> Pending</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($task['status'] === 'Not Started' || $task['status'] === 'In Progress'): ?>
                            <div class="btn-group btn-group-sm" role="group">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="complete_task">
                                    <input type="hidden" name="progress_id" value="<?php echo $task['candidate_task_id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm" title="Mark as completed">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-danger btn-sm" onclick="openFailModal(<?php echo $task['candidate_task_id']; ?>)" title="Cancel task">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </div>
                        <?php elseif ($task['status'] === 'Completed'): ?>
                            <span class="badge badge-success">✅ Completed</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Task Summary -->
    <hr class="my-3">
    <div class="row mt-2">
        <?php 
        $completed = count(array_filter($tasks, fn($t) => $t['status'] === 'Completed'));
        $total = count($tasks);
        $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
        ?>
        <div class="col-md-12">
            <h6 class="mb-2"><strong>Overall Progress</strong></h6>
            <div class="progress" style="height: 25px;">
                <div class="progress-bar progress-bar-striped <?php echo $progress == 100 ? 'bg-success' : 'bg-info'; ?> progress-bar-animated" role="progressbar" style="width: <?php echo $progress; ?>%">
                    <strong><?php echo $completed; ?>/<?php echo $total; ?> — <?php echo $progress; ?>%</strong>
                </div>
            </div>
            <small class="text-muted mt-1">
                <?php if ($progress == 100): ?>
                    ✅ All tasks completed! Candidate is ready for next stage.
                <?php else: ?>
                    📋 <?php echo ($total - $completed); ?> task(s) remaining
                <?php endif; ?>
            </small>
        </div>
    </div>
<?php endif; ?>

<script>
// Prevent form submission and reload page
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        // Allow normal form submission
        // The form will post to onboarding.php and the page will reload
    });
});
</script>


