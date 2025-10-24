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
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Training Sessions Management</h2>
                
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

                <!-- Controls -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="input-group" style="max-width: 400px;">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control" id="sessionSearch" placeholder="Search sessions...">
                    </div>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addSessionModal">
                        <i class="fas fa-plus"></i> Add Session
                    </button>
                </div>

                <!-- Sessions Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Training Sessions List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
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
                                <tbody>
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
                                            <span class="status-badge status-<?php echo strtolower($session['status']); ?>">
                                                <?php echo htmlspecialchars($session['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editSession(<?php echo $session['session_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSession(<?php echo $session['session_id']; ?>)">
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
        </div>
    </div>

    <!-- Add Session Modal -->
    <div class="modal fade" id="addSessionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Training Session</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_session">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Session Name *</label>
                                    <input type="text" class="form-control" name="session_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Course *</label>
                                    <select class="form-control" name="course_id" required>
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Trainer *</label>
                                    <select class="form-control" name="trainer_id" required>
                                        <option value="">Select Trainer</option>
                                        <?php foreach ($trainers as $trainer): ?>
                                        <option value="<?php echo $trainer['trainer_id']; ?>">
                                            <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Location *</label>
                                    <input type="text" class="form-control" name="location" required>
                                </div>
                            </div>
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
                                    <label>End Date *</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Capacity *</label>
                                    <input type="number" class="form-control" name="capacity" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Cost per Participant</label>
                                    <input type="number" class="form-control" name="cost_per_participant" min="0" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="Scheduled">Scheduled</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Session Modal -->
    <div class="modal fade" id="editSessionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Training Session</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_session">
                        <input type="hidden" name="session_id" id="edit_session_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Session Name *</label>
                                    <input type="text" class="form-control" name="session_name" id="edit_session_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Course *</label>
                                    <select class="form-control" name="course_id" id="edit_course_id" required>
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Trainer *</label>
                                    <select class="form-control" name="trainer_id" id="edit_trainer_id" required>
                                        <option value="">Select Trainer</option>
                                        <?php foreach ($trainers as $trainer): ?>
                                        <option value="<?php echo $trainer['trainer_id']; ?>">
                                            <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Location *</label>
                                    <input type="text" class="form-control" name="location" id="edit_location" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>End Date *</label>
                                    <input type="date" class="form-control" name="end_date" id="edit_end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Capacity *</label>
                                    <input type="number" class="form-control" name="capacity" id="edit_capacity" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Cost per Participant</label>
                                    <input type="number" class="form-control" name="cost_per_participant" id="edit_cost_per_participant" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status" id="edit_status">
                                <option value="Scheduled">Scheduled</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Update Session</button>
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
        $('#sessionSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Edit session function
        function editSession(sessionId) {
            // Fetch session data and populate the edit modal
            $.ajax({
                url: 'get_session.php',
                type: 'GET',
                data: { session_id: sessionId },
                dataType: 'json',
                success: function(session) {
                    $('#edit_session_id').val(session.session_id);
                    $('#edit_session_name').val(session.session_name);
                    $('#edit_course_id').val(session.course_id);
                    $('#edit_trainer_id').val(session.trainer_id);
                    $('#edit_start_date').val(session.start_date);
                    $('#edit_end_date').val(session.end_date);
                    $('#edit_location').val(session.location);
                    $('#edit_capacity').val(session.capacity);
                    $('#edit_cost_per_participant').val(session.cost_per_participant);
                    $('#edit_status').val(session.status);
                    
                    $('#editSessionModal').modal('show');
                },
                error: function() {
                    alert('Error fetching session data');
                }
            });
        }

        // Delete session function
        function deleteSession(sessionId) {
            if (confirm('Are you sure you want to delete this training session?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_session">
                    <input type="hidden" name="session_id" value="${sessionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
