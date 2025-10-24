<?php

session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config.php';

if ($conn === null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    } else {
        die('Database connection failed.');
    }
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'employee';

// Get employee_id from users table
try {
    $stmt = $conn->prepare("SELECT employee_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $employee_id = $user['employee_id'] ?? null;
    // Allow users with roles employee, manager, hr to manage goal updates
    $allowed_roles = ['employee', 'manager', 'hr'];
    $is_employee = in_array($user_role, $allowed_roles);
} catch (PDOException $e) {
    die("Error fetching employee profile: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (!$is_employee) {
        $response['message'] = 'Only employees can manage goal updates.';
        echo json_encode($response);
        exit;
    }

    try {
        switch ($_POST['action']) {
            case 'add_update':
                $goal_id = intval($_POST['goal_id']);
                $update_text = trim($_POST['update_text']);
                $progress = min(100, max(0, intval($_POST['progress'] ?? 0)));
                $status = $_POST['status'] ?? 'in_progress';

                if (!$goal_id || !$update_text) {
                    $response['message'] = 'Goal ID and update text are required.';
                } else {
                    // Verify goal belongs to employee
                    $stmt = $conn->prepare("SELECT goal_id FROM goals WHERE goal_id = ? AND employee_id = ?");
                    $stmt->execute([$goal_id, $employee_id]);
                    if (!$stmt->fetch()) {
                        $response['message'] = 'Goal not found or access denied.';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO goal_updates (goal_id, update_text, progress, status, update_date) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$goal_id, $update_text, $progress, $status]);
                        $response['success'] = true;
                        $response['message'] = 'Goal update added successfully.';
                    }
                }
                break;
            case 'fetch_goals':
                // Fetch goals for the logged-in employee for the add modal dropdown
                $stmt = $conn->prepare("SELECT goal_id, title FROM goals WHERE employee_id = ? ORDER BY created_at DESC");
                $stmt->execute([$employee_id]);
                $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['success'] = true;
                $response['goals'] = $goals;
                break;

            case 'edit_update':
                $update_id = intval($_POST['update_id']);
                $update_text = trim($_POST['update_text']);
                $progress = min(100, max(0, intval($_POST['progress'] ?? 0)));
                $status = $_POST['status'] ?? 'in_progress';

                if (!$update_id || !$update_text) {
                    $response['message'] = 'Update ID and text are required.';
                } else {
                    // Verify update belongs to employee's goal
                    $stmt = $conn->prepare("SELECT gu.update_id FROM goal_updates gu JOIN goals g ON gu.goal_id = g.goal_id WHERE gu.update_id = ? AND g.employee_id = ?");
                    $stmt->execute([$update_id, $employee_id]);
                    if (!$stmt->fetch()) {
                        $response['message'] = 'Update not found or access denied.';
                    } else {
                        $stmt = $conn->prepare("UPDATE goal_updates SET update_text = ?, progress = ?, status = ?, update_date = NOW() WHERE update_id = ?");
                        $stmt->execute([$update_text, $progress, $status, $update_id]);
                        $response['success'] = true;
                        $response['message'] = 'Goal update updated successfully.';
                    }
                }
                break;

            case 'complete_update':
                $update_id = intval($_POST['update_id']);
                // Verify update belongs to employee's goal
                $stmt = $conn->prepare("SELECT gu.update_id FROM goal_updates gu JOIN goals g ON gu.goal_id = g.goal_id WHERE gu.update_id = ? AND g.employee_id = ?");
                $stmt->execute([$update_id, $employee_id]);
                if (!$stmt->fetch()) {
                    $response['message'] = 'Update not found or access denied.';
                } else {
                    $stmt = $conn->prepare("UPDATE goal_updates SET status = 'completed', update_date = NOW() WHERE update_id = ?");
                    $stmt->execute([$update_id]);
                    $response['success'] = true;
                    $response['message'] = 'Goal update marked as completed.';
                }
                break;

            case 'delete_update':
                $update_id = intval($_POST['update_id']);
                // Verify update belongs to employee's goal
                $stmt = $conn->prepare("SELECT gu.update_id FROM goal_updates gu JOIN goals g ON gu.goal_id = g.goal_id WHERE gu.update_id = ? AND g.employee_id = ?");
                $stmt->execute([$update_id, $employee_id]);
                if (!$stmt->fetch()) {
                    $response['message'] = 'Update not found or access denied.';
                } else {
                    $stmt = $conn->prepare("DELETE FROM goal_updates WHERE update_id = ?");
                    $stmt->execute([$update_id]);
                    $response['success'] = true;
                    $response['message'] = 'Goal update deleted successfully.';
                }
                break;

            default:
                $response['message'] = 'Invalid action.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// Fetch goal updates for employee
$goal_updates = [];
if ($is_employee) {
    try {
        $stmt = $conn->prepare("SELECT gu.*, g.title as goal_title FROM goal_updates gu JOIN goals g ON gu.goal_id = g.goal_id WHERE g.employee_id = ? ORDER BY gu.update_date DESC");
        $stmt->execute([$employee_id]);
        $goal_updates = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Error fetching goal updates: " . $e->getMessage());
    }
}

// Calculate statistics
$total_updates = count($goal_updates);
$completed_updates = 0;
$in_progress_updates = 0;
$blocked_updates = 0;

foreach ($goal_updates as $update) {
    $status = strtolower($update['status']);
    if ($status === 'completed') {
        $completed_updates++;
    } elseif ($status === 'in_progress') {
        $in_progress_updates++;
    } elseif ($status === 'blocked') {
        $blocked_updates++;
    }
}

// Get employee name
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

/**
 * Process goal update data without database changes
 * This function validates and formats goal update information
 *
 * @param array $updateData The goal update data to process
 * @return array Processed and validated update data
 */
function processGoalUpdate($updateData) {
    $processed = [];

    // Validate required fields
    if (!isset($updateData['goal_id']) || empty($updateData['goal_id'])) {
        return ['error' => 'Goal ID is required'];
    }

    if (!isset($updateData['update_text']) || empty($updateData['update_text'])) {
        return ['error' => 'Update text is required'];
    }

    // Process and format the data
    $processed['goal_id'] = intval($updateData['goal_id']);
    $processed['update_text'] = trim($updateData['update_text']);
    $processed['progress'] = isset($updateData['progress']) ? min(100, max(0, intval($updateData['progress']))) : 0;
    $processed['status'] = isset($updateData['status']) ? $updateData['status'] : 'in_progress';
    $processed['timestamp'] = date('Y-m-d H:i:s');

    // Additional processing logic without database operations
    $processed['word_count'] = str_word_count($processed['update_text']);
    $processed['is_valid'] = true;

    return $processed;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Updates - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Custom styles for goal updates page */
        .update-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .update-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .update-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .update-content {
            padding: 20px;
        }

        .update-meta {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .update-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-progress { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-blocked { background-color: #f8d7da; color: #721c24; }

        .update-actions {
            opacity: 1;
            transition: opacity 0.3s ease;
        }

        .add-update-btn {
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

        .add-update-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .update-stats {
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

        .update-timeline {
            position: relative;
            padding-left: 30px;
        }

        .update-timeline::before {
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

        .progress-indicator {
            width: 100%;
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-bar-custom {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.6s ease;
        }

        .goal-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .goal-link:hover {
            text-decoration: underline;
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
                        <i class="fas fa-tasks mr-3"></i>
                        Goal Updates 
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

                <!-- Updates Statistics -->
                <div class="update-stats">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $total_updates; ?></h3>
                            <p class="mb-0">Total Updates</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $in_progress_updates; ?></h3>
                            <p class="mb-0">In Progress</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $completed_updates; ?></h3>
                            <p class="mb-0">Completed</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $blocked_updates; ?></h3>
                            <p class="mb-0">Blocked</p>
                        </div>
                    </div>
                </div>

                <!-- Updates Grid -->
                <div class="row">
                    <?php if (empty($goal_updates)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle fa-3x mb-3"></i>
                                <h4>No Goal Updates Found</h4>
                                <p>You haven't added any goal updates yet. Click the + button to add your first update!</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($goal_updates as $update): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card update-card h-100">
                                    <div class="update-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($update['goal_title']); ?> Update</h5>
                                                <small>Goal: <?php echo htmlspecialchars($update['goal_title']); ?></small>
                                            </div>
                                            <span class="update-status status-<?php echo strtolower(str_replace('_', '-', $update['status'])); ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $update['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="update-content">
                                        <div class="update-meta">
                                            <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($employee_name); ?>
                                            <i class="fas fa-calendar ml-3 mr-1"></i> <?php echo date('M j, Y', strtotime($update['update_date'])); ?>
                                        </div>
                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($update['update_text']); ?></p>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>Goal Progress</small>
                                                <small><?php echo $update['progress']; ?>%</small>
                                            </div>
                                            <div class="progress-indicator">
                                                <div class="progress-bar-custom" style="width: <?php echo $update['progress']; ?>%"></div>
                                            </div>
                                        </div>

                                        <div class="row text-center mb-3">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Status</small>
                                                <strong><?php echo ucwords(str_replace('_', ' ', $update['status'])); ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Last Updated</small>
                                                <strong><?php echo date('M j', strtotime($update['update_date'])); ?></strong>
                                            </div>
                                        </div>

                                        <div class="update-actions text-center">
                                            <button class="btn btn-sm btn-outline-primary mr-2 edit-update-btn" data-update-id="<?php echo $update['update_id']; ?>" data-goal-id="<?php echo $update['goal_id']; ?>" data-text="<?php echo htmlspecialchars($update['update_text']); ?>" data-progress="<?php echo $update['progress']; ?>" data-status="<?php echo $update['status']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($update['status'] !== 'completed'): ?>
                                                <button class="btn btn-sm btn-outline-success mr-2 complete-update-btn" data-update-id="<?php echo $update['update_id']; ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger delete-update-btn" data-update-id="<?php echo $update['update_id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Updates Timeline -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history mr-2"></i>
                            Recent Goal Update Activities
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="update-timeline">
                            <?php if (empty($goal_updates)): ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <p>No recent activities to display.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($goal_updates, 0, 10) as $update): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="#" class="goal-link"><?php echo htmlspecialchars($update['goal_title']); ?></a> <?php echo strtolower($update['status']) === 'completed' ? 'completed' : 'updated'; ?>
                                                </h6>
                                                <p class="text-muted mb-0"><?php echo htmlspecialchars(substr($update['update_text'], 0, 100) . (strlen($update['update_text']) > 100 ? '...' : '')); ?></p>
                                            </div>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($update['update_date'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Update Button -->
    <button class="btn btn-primary add-update-btn" data-toggle="tooltip" title="Add New Goal Update">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Edit Update Modal -->
    <div class="modal fade" id="editUpdateModal" tabindex="-1" role="dialog" aria-labelledby="editUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUpdateModalLabel">Edit Goal Update</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editUpdateForm">
                    <div class="modal-body">
                        <input type="hidden" id="editUpdateId" name="update_id">
                        <input type="hidden" id="editGoalId" name="goal_id">
                        <div class="form-group">
                            <label for="editUpdateText">Update Text</label>
                            <textarea class="form-control" id="editUpdateText" name="update_text" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="editProgress">Progress (%)</label>
                            <input type="number" class="form-control" id="editProgress" name="progress" min="0" max="100" required>
                        </div>
                        <div class="form-group">
                            <label for="editStatus">Status</label>
                            <select class="form-control" id="editStatus" name="status" required>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });

        /**
         * Show success message
         * @param {string} message The message to display
         */
        function showSuccess(message) {
            // Simple alert for now, could be replaced with toast notifications
            alert('Success: ' + message);
        }

        /**
         * Show error message
         * @param {string} message The message to display
         */
        function showError(message) {
            alert('Error: ' + message);
        }

        /**
         * Handle edit button click for goal updates
         * @param {Event} event The click event
         */
        function editGoalUpdate(event) {
            event.preventDefault();
            const button = event.target.closest('.edit-update-btn');
            const updateId = button.getAttribute('data-update-id');
            const goalId = button.getAttribute('data-goal-id');
            const updateText = button.getAttribute('data-text');
            const progress = button.getAttribute('data-progress');
            const status = button.getAttribute('data-status');

            $('#editUpdateId').val(updateId);
            $('#editGoalId').val(goalId);
            $('#editUpdateText').val(updateText);
            $('#editProgress').val(progress);
            $('#editStatus').val(status);

            $('#editUpdateModal').modal('show');
        }

        /**
         * Handle complete button click for goal updates
         * @param {Event} event The click event
         */
        function completeGoalUpdate(event) {
            event.preventDefault();
            const button = event.target.closest('.complete-update-btn');
            const updateId = button.getAttribute('data-update-id');

            if (confirm('Mark this goal update as completed?')) {
                $.post('goal_updates.php', {
                    action: 'complete_update',
                    update_id: updateId
                }, function(response) {
                    if (response.success) {
                        showSuccess(response.message);
                        location.reload(); // Refresh to show updated data
                    } else {
                        showError(response.message);
                    }
                }, 'json').fail(function() {
                    showError('Failed to complete update. Please try again.');
                });
            }
        }

        /**
         * Handle delete button click for goal updates
         * @param {Event} event The click event
         */
        function deleteGoalUpdate(event) {
            event.preventDefault();
            const button = event.target.closest('.delete-update-btn');
            const updateId = button.getAttribute('data-update-id');

            if (confirm('Delete this goal update? This action cannot be undone.')) {
                $.post('goal_updates.php', {
                    action: 'delete_update',
                    update_id: updateId
                }, function(response) {
                    if (response.success) {
                        showSuccess(response.message);
                        location.reload(); // Refresh to show updated data
                    } else {
                        showError(response.message);
                    }
                }, 'json').fail(function() {
                    showError('Failed to delete update. Please try again.');
                });
            }
        }

        /**
         * Handle add update button click
         * @param {Event} event The click event
         */
        function addNewGoalUpdate(event) {
            event.preventDefault();
            // Fetch goals for dropdown and show modal
            $.post('goal_updates.php', { action: 'fetch_goals' }, function(response) {
                if (response.success) {
                    const goals = response.goals;
                    const select = $('#addGoalId');
                    select.empty();
                    if (goals.length === 0) {
                        select.append('<option disabled>No goals found</option>');
                    } else {
                        goals.forEach(goal => {
                            select.append(`<option value="${goal.goal_id}">${goal.title}</option>`);
                        });
                    }
                    $('#addUpdateModal').modal('show');
                } else {
                    showError('Failed to fetch goals for adding update.');
                }
            }, 'json').fail(function() {
                showError('Failed to fetch goals for adding update.');
            });
        }

        // Attach event listeners to buttons
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();

            // Edit buttons
            $(document).on('click', '.edit-update-btn', editGoalUpdate);

            // Complete buttons
            $(document).on('click', '.complete-update-btn', completeGoalUpdate);

            // Delete buttons
            $(document).on('click', '.delete-update-btn', deleteGoalUpdate);

            // Add update button
            $('.add-update-btn').on('click', addNewGoalUpdate);

            // Edit form submission
            $('#editUpdateForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'edit_update');

                $.ajax({
                    url: 'goal_updates.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#editUpdateModal').modal('hide');
                            showSuccess(response.message);
                            location.reload(); // Refresh to show updated data
                        } else {
                            showError(response.message);
                        }
                    },
                    error: function() {
                        showError('Failed to update goal update. Please try again.');
                    }
                });
            });
        });
        // Add form submission
$('#addUpdateForm').on('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('action', 'add_update');

    $.ajax({
        url: 'goal_updates.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                $('#addUpdateModal').modal('hide');
                showSuccess(response.message);
                location.reload(); // reload updates list
            } else {
                showError(response.message);
            }
        },
        error: function() {
            showError('Failed to add goal update. Please try again.');
        }
    });
});

    </script>

    <!-- Add Update Modal -->
    <div class="modal fade" id="addUpdateModal" tabindex="-1" role="dialog" aria-labelledby="addUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="addUpdateForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addUpdateModalLabel">Add New Goal Update</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="addGoalId">Select Goal</label>
                            <select class="form-control" id="addGoalId" name="goal_id" required>
                                <option value="" disabled selected>Select a goal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="addUpdateText">Update Text</label>
                            <textarea class="form-control" id="addUpdateText" name="update_text" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="addProgress">Progress (%)</label>
                            <input type="number" class="form-control" id="addProgress" name="progress" min="0" max="100" value="0" required>
                        </div>
                        <div class="form-group">
                            <label for="addStatus">Status</label>
                            <select class="form-control" id="addStatus" name="status" required>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
