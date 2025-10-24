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
            case 'add_session':
                try {
                    $stmt = $pdo->prepare("INSERT INTO training_sessions (course_id, trainer_id, session_name, start_date, end_date, location, capacity, cost_per_participant, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['course_id'],
                        $_POST['trainer_id'],
                        $_POST['session_name'],
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['location'],
                        $_POST['capacity'],
                        $_POST['cost_per_participant'],
                        $_POST['status']
                    ]);
                    $message = "Training session added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding session: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'edit_session':
                try {
                    $stmt = $pdo->prepare("UPDATE training_sessions SET course_id=?, trainer_id=?, session_name=?, start_date=?, end_date=?, location=?, capacity=?, cost_per_participant=?, status=? WHERE session_id=?");
                    $stmt->execute([
                        $_POST['course_id'],
                        $_POST['trainer_id'],
                        $_POST['session_name'],
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['location'],
                        $_POST['capacity'],
                        $_POST['cost_per_participant'],
                        $_POST['status'],
                        $_POST['session_id']
                    ]);
                    $message = "Training session updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating session: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_session':
                try {
                    $stmt = $pdo->prepare("DELETE FROM training_sessions WHERE session_id=?");
                    $stmt->execute([$_POST['session_id']]);
                    $message = "Training session deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting session: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch training sessions with related data
try {
    $stmt = $pdo->query("
        SELECT ts.*, tc.course_name, t.first_name, t.last_name 
        FROM training_sessions ts
        LEFT JOIN training_courses tc ON ts.course_id = tc.course_id
        LEFT JOIN trainers t ON ts.trainer_id = t.trainer_id
        ORDER BY ts.start_date DESC
    ");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sessions = [];
    $message = "Error fetching sessions: " . $e->getMessage();
    $messageType = "error";
}

// Fetch courses for dropdown
try {
    $stmt = $pdo->query("SELECT course_id, course_name FROM training_courses WHERE status = 'Active' ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $courses = [];
}

// Fetch trainers for dropdown
try {
    $stmt = $pdo->query("SELECT trainer_id, first_name, last_name FROM trainers ORDER BY first_name, last_name");
    $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $trainers = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_sessions");
    $totalSessions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as upcoming FROM training_sessions WHERE start_date >= CURDATE()");
    $upcomingSessions = $stmt->fetch(PDO::FETCH_ASSOC)['upcoming'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as ongoing FROM training_sessions WHERE start_date <= CURDATE() AND end_date >= CURDATE()");
    $ongoingSessions = $stmt->fetch(PDO::FETCH_ASSOC)['ongoing'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as completed FROM training_sessions WHERE end_date < CURDATE()");
    $completedSessions = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
} catch (PDOException $e) {
    $totalSessions = 0;
    $upcomingSessions = 0;
    $ongoingSessions = 0;
    $completedSessions = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Sessions Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for training sessions page */
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
        
        .container-fluid {
            padding: 0;
        }
        
        .row {
            margin-right: 0;
            margin-left: 0;
        }

        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
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
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
            margin: 0 3px;
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

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--azure-blue-lighter);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-in.progress {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .status-completed {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
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
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
        }

        .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
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

        .no-results {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .form-row {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }

            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Training Sessions Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-calendar-alt"></i>
                                <h3><?php echo $totalSessions; ?></h3>
                                <h6>Total Sessions</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-clock"></i>
                                <h3><?php echo $upcomingSessions; ?></h3>
                                <h6>Upcoming</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-play-circle"></i>
                                <h3><?php echo $ongoingSessions; ?></h3>
                                <h6>Ongoing</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-check-circle"></i>
                                <h3><?php echo $completedSessions; ?></h3>
                                <h6>Completed</h6>
                            </div>
                        </div>
                    </div>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search sessions by name, course, or trainer...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add New Session
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="sessionTable">
                            <thead>
                                <tr>
                                    <th>Session Name</th>
                                    <th>Course</th>
                                    <th>Trainer</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Location</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sessionTableBody">
                                <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($session['session_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($session['course_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(($session['first_name'] ?? '') . ' ' . ($session['last_name'] ?? '')); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($session['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($session['end_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($session['location']); ?></td>
                                    <td><?php echo $session['capacity']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $session['status'])); ?>">
                                            <?php echo htmlspecialchars($session['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editSession(<?php echo $session['session_id']; ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteSession(<?php echo $session['session_id']; ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($sessions)): ?>
                        <div class="no-results">
                            <i class="fas fa-calendar-alt"></i>
                            <h3>No sessions found</h3>
                            <p>Start by adding your first training session.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Session Modal -->
    <div id="sessionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Session</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="sessionForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add_session">
                    <input type="hidden" id="session_id" name="session_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="session_name">Session Name *</label>
                                <input type="text" id="session_name" name="session_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="course_id">Course *</label>
                                <select id="course_id" name="course_id" class="form-control" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['course_id']; ?>">
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="trainer_id">Trainer *</label>
                                <select id="trainer_id" name="trainer_id" class="form-control" required>
                                    <option value="">Select Trainer</option>
                                    <?php foreach ($trainers as $trainer): ?>
                                    <option value="<?php echo $trainer['trainer_id']; ?>">
                                        <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="location">Location *</label>
                                <input type="text" id="location" name="location" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="start_date">Start Date *</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="end_date">End Date *</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="capacity">Capacity *</label>
                                <input type="number" id="capacity" name="capacity" class="form-control" min="1" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="cost_per_participant">Cost per Participant</label>
                                <input type="number" id="cost_per_participant" name="cost_per_participant" class="form-control" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="Scheduled">Scheduled</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let sessionsData = <?= json_encode($sessions) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('sessionTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Modal functions
        function openModal(mode, sessionId = null) {
            const modal = document.getElementById('sessionModal');
            const form = document.getElementById('sessionForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Session';
                action.value = 'add_session';
                form.reset();
                document.getElementById('session_id').value = '';
            } else if (mode === 'edit' && sessionId) {
                title.textContent = 'Edit Session';
                action.value = 'edit_session';
                document.getElementById('session_id').value = sessionId;
                populateEditForm(sessionId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('sessionModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(sessionId) {
            const session = sessionsData.find(s => s.session_id == sessionId);
            if (session) {
                document.getElementById('session_name').value = session.session_name || '';
                document.getElementById('course_id').value = session.course_id || '';
                document.getElementById('trainer_id').value = session.trainer_id || '';
                document.getElementById('start_date').value = session.start_date || '';
                document.getElementById('end_date').value = session.end_date || '';
                document.getElementById('location').value = session.location || '';
                document.getElementById('capacity').value = session.capacity || '';
                document.getElementById('cost_per_participant').value = session.cost_per_participant || '';
                document.getElementById('status').value = session.status || '';
            }
        }

        function editSession(sessionId) {
            openModal('edit', sessionId);
        }

        function deleteSession(sessionId) {
            if (confirm('Are you sure you want to delete this training session? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_session">
                    <input type="hidden" name="session_id" value="${sessionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('sessionModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('sessionForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (endDate < startDate) {
                e.preventDefault();
                alert('End date must be after start date');
                return;
            }

            const capacity = document.getElementById('capacity').value;
            if (capacity <= 0) {
                e.preventDefault();
                alert('Capacity must be greater than 0');
                return;
            }
        });

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

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#sessionTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });

    </script>

    <!-- Bootstrap JS + Dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
