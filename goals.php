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

// Check if user is an employee (only employees can access goals)
$user_role = $_SESSION['role'] ?? 'user';
if ($user_role !== 'employee') {
    header("Location: index.php");
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

// Get current user info
$user_id = $_SESSION['user_id'] ?? null;
$employee_id = null;

// Get employee_id from users table
if ($user_id) {
    $stmt = $pdo->prepare("SELECT employee_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $employee_id = $user['employee_id'] ?? null;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new goal
                try {
                    $stmt = $pdo->prepare("INSERT INTO goals (employee_id, title, description, start_date, end_date, status, progress, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $employee_id,
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['status'],
                        $_POST['progress'] ?? 0,
                        $_POST['weight'] ?? 100
                    ]);
                    $message = "Goal added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding goal: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'update':
                // Update goal
                try {
                    $stmt = $pdo->prepare("UPDATE goals SET title=?, description=?, start_date=?, end_date=?, status=?, progress=?, weight=? WHERE goal_id=? AND employee_id=?");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['status'],
                        $_POST['progress'] ?? 0,
                        $_POST['weight'] ?? 100,
                        $_POST['goal_id'],
                        $employee_id
                    ]);
                    $message = "Goal updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating goal: " . $e->getMessage();
                    $messageType = "error";
                }
                break;



            case 'delete':
                // Delete goal
                try {
                    $stmt = $pdo->prepare("DELETE FROM goals WHERE goal_id=? AND employee_id=?");
                    $stmt->execute([$_POST['goal_id'], $employee_id]);
                    $message = "Goal deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting goal: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch goals for current employee
$goals = [];
$goalUpdates = [];

if ($employee_id) {
    // Fetch goals
    $stmt = $pdo->prepare("
        SELECT g.*,
               (SELECT comments FROM goal_updates WHERE goal_id = g.goal_id ORDER BY update_date DESC LIMIT 1) as last_comment,
               (SELECT update_date FROM goal_updates WHERE goal_id = g.goal_id ORDER BY update_date DESC LIMIT 1) as last_update
        FROM goals g
        WHERE g.employee_id = ?
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$employee_id]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch goal updates for progress history
    $stmt = $pdo->prepare("
        SELECT gu.*, g.title as goal_title
        FROM goal_updates gu
        JOIN goals g ON gu.goal_id = g.goal_id
        WHERE g.employee_id = ?
        ORDER BY gu.update_date DESC, gu.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$employee_id]);
    $goalUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate goal statistics
$totalGoals = count($goals);
$completedGoals = count(array_filter($goals, function($goal) { return $goal['status'] === 'Completed'; }));
$inProgressGoals = count(array_filter($goals, function($goal) { return $goal['status'] === 'In Progress'; }));
$averageProgress = $totalGoals > 0 ? array_sum(array_column($goals, 'progress')) / $totalGoals : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Goals - HR System</title>
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

        .progress-bar {
            height: 8px;
            border-radius: 4px;
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
            <?php include 'employee_sidebar.php'; ?>
            <div class="col-md-10 main-content">
                <h2 class="section-title"><i class="fas fa-bullseye"></i> My Goals</h2>

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
                                    <h6 class="text-muted mb-1">Avg Progress</h6>
                                    <div class="stats-number text-info"><?= number_format($averageProgress, 1) ?>%</div>
                                </div>
                                <i class="fas fa-chart-line fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="btn-group" role="group">
                        <button class="btn btn-outline-primary active" onclick="filterGoals('all')">All</button>
                        <button class="btn btn-outline-primary" onclick="filterGoals('in-progress')">In Progress</button>
                        <button class="btn btn-outline-success" onclick="filterGoals('completed')">Completed</button>
                        <button class="btn btn-outline-warning" onclick="filterGoals('not-started')">Not Started</button>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('add')">
                        <i class="fas fa-plus"></i> Add New Goal
                    </button>
                </div>

                <!-- Goals List -->
                <div id="goalsContainer">
                    <?php foreach ($goals as $goal): ?>
                    <div class="goal-card" data-status="<?= strtolower(str_replace(' ', '-', $goal['status'])) ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <h5 class="mb-2"><?= htmlspecialchars($goal['title']) ?></h5>
                                <p class="text-muted mb-2"><?= htmlspecialchars($goal['description']) ?></p>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="goal-status status-<?= strtolower(str_replace(' ', '-', $goal['status'])) ?> mr-3">
                                        <?= htmlspecialchars($goal['status']) ?>
                                    </span>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?= date('M d, Y', strtotime($goal['start_date'])) ?> - <?= date('M d, Y', strtotime($goal['end_date'])) ?>
                                    </small>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-warning" onclick="editGoal(<?= $goal['goal_id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteGoal(<?= $goal['goal_id'] ?>)">
                                    <i class="fas fa-trash"></i>
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

                        <?php if ($goal['last_update']): ?>
                        <div class="text-muted">
                            <small>Last updated: <?= date('M d, Y', strtotime($goal['last_update'])) ?>
                            <?php if ($goal['last_comment']): ?>
                                - <?= htmlspecialchars(substr($goal['last_comment'], 0, 50)) ?><?php if (strlen($goal['last_comment']) > 50): ?>...<?php endif; ?>
                            <?php endif; ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($goals)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bullseye fa-4x text-muted mb-3"></i>
                        <h4>No goals yet</h4>
                        <p class="text-muted">Start by setting your first goal to track your progress and achievements.</p>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            <i class="fas fa-plus"></i> Create Your First Goal
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Updates -->
                <?php if (!empty($goalUpdates)): ?>
                <div class="mt-5">
                    <h4 class="mb-3"><i class="fas fa-history"></i> Recent Progress Updates</h4>
                    <div class="timeline">
                        <?php foreach ($goalUpdates as $update): ?>
                        <div class="timeline-item">
                            <div class="bg-light p-3 rounded">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($update['goal_title']) ?></h6>
                                        <p class="mb-1 text-muted small">
                                            Progress updated to <?= $update['progress'] ?>%
                                            <?php if ($update['comments']): ?>
                                                - <?= htmlspecialchars($update['comments']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <small class="text-muted">
                                        <?= date('M d, Y', strtotime($update['update_date'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Goal Modal -->
    <div id="goalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 id="modalTitle">Add New Goal</h4>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="goalForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="goal_id" name="goal_id">

                    <div class="form-group">
                        <label for="title">Goal Title *</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date">End Date *</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">Status *</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="Not Started">Not Started</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="progress">Progress (%)</label>
                                <input type="number" id="progress" name="progress" class="form-control" min="0" max="100" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="weight">Weight (Importance)</label>
                        <input type="number" id="weight" name="weight" class="form-control" min="1" max="100" value="100">
                        <small class="form-text text-muted">Higher weight means more important goal (1-100)</small>
                    </div>

                    <div class="text-right">
                        <button type="button" class="btn btn-secondary mr-2" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Goal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Progress Update Modal -->
    <div id="progressModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Update Progress</h4>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="progressForm" method="POST">
                    <input type="hidden" name="action" value="update_progress">
                    <input type="hidden" id="progress_goal_id" name="goal_id">

                    <div class="form-group">
                        <label for="progress_value">Progress (%)</label>
                        <input type="number" id="progress_value" name="progress" class="form-control" min="0" max="100" required>
                    </div>

                    <div class="form-group">
                        <label for="progress_comments">Comments (Optional)</label>
                        <textarea id="progress_comments" name="comments" class="form-control" rows="3" placeholder="Describe what you've accomplished..."></textarea>
                    </div>

                    <div class="text-right">
                        <button type="button" class="btn btn-secondary mr-2" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Progress</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let goalsData = <?= json_encode($goals) ?>;

        function openModal(mode, goalId = null) {
            const modal = document.getElementById('goalModal');
            const form = document.getElementById('goalForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Goal';
                action.value = 'add';
                form.reset();
                document.getElementById('goal_id').value = '';
            } else if (mode === 'edit' && goalId) {
                title.textContent = 'Edit Goal';
                action.value = 'update';
                document.getElementById('goal_id').value = goalId;
                populateEditForm(goalId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('goalModal').style.display = 'none';
            document.getElementById('progressModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(goalId) {
            const goal = goalsData.find(g => g.goal_id == goalId);
            if (goal) {
                document.getElementById('title').value = goal.title || '';
                document.getElementById('description').value = goal.description || '';
                document.getElementById('start_date').value = goal.start_date || '';
                document.getElementById('end_date').value = goal.end_date || '';
                document.getElementById('status').value = goal.status || '';
                document.getElementById('progress').value = goal.progress || 0;
                document.getElementById('weight').value = goal.weight || 100;
            }
        }

        function editGoal(goalId) {
            openModal('edit', goalId);
        }

        function requestProgressUpdate(goalId) {
            if (confirm('Are you sure you want to request a progress update from HR?')) {
                // Here you could add AJAX call to notify HR
                alert('Progress update request sent to HR. They will review and update your goal progress.');
            }
        }

        function deleteGoal(goalId) {
            if (confirm('Are you sure you want to delete this goal? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="goal_id" value="${goalId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function filterGoals(status) {
            const cards = document.querySelectorAll('.goal-card');
            const buttons = document.querySelectorAll('.btn-group .btn');

            // Update button states
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            cards.forEach(card => {
                if (status === 'all') {
                    card.style.display = 'block';
                } else {
                    const cardStatus = card.getAttribute('data-status');
                    card.style.display = cardStatus === status ? 'block' : 'none';
                }
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const goalModal = document.getElementById('goalModal');
            const progressModal = document.getElementById('progressModal');
            if (event.target === goalModal) {
                closeModal();
            }
            if (event.target === progressModal) {
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

        // Set default start date to today
        document.getElementById('start_date').valueAsDate = new Date();
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
