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
            case 'add_enrollment':
                try {
                    $stmt = $pdo->prepare("INSERT INTO training_enrollments (session_id, employee_id, enrollment_date, status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['session_id'],
                        $_POST['employee_id'],
                        $_POST['enrollment_date'],
                        $_POST['status']
                    ]);
                    $message = "Enrollment added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding enrollment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'add_trainer':
                try {
                    $stmt = $pdo->prepare("INSERT INTO trainers (first_name, last_name, email, phone, specialization, bio, is_internal) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['specialization'],
                        $_POST['bio'],
                        isset($_POST['is_internal']) ? 1 : 0
                    ]);
                    $message = "Trainer added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding trainer: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update_enrollment':
                try {
                    $stmt = $pdo->prepare("UPDATE training_enrollments SET status = ?, completion_date = ?, score = ?, feedback = ? WHERE enrollment_id = ?");
                    $stmt->execute([
                        $_POST['status'],
                        $_POST['completion_date'] ?: null,
                        $_POST['score'] ?: null,
                        $_POST['feedback'],
                        $_POST['enrollment_id']
                    ]);
                    $message = "Enrollment updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating enrollment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_enrollment':
                try {
                    $stmt = $pdo->prepare("DELETE FROM training_enrollments WHERE enrollment_id=?");
                    $stmt->execute([$_POST['enrollment_id']]);
                    $message = "Enrollment deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting enrollment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch enrollments with details
try {
    $stmt = $pdo->query("
        SELECT te.*, e.first_name, e.last_name, ts.session_name, tc.course_name, t.first_name as trainer_first, t.last_name as trainer_last
        FROM training_enrollments te 
        JOIN employee_profiles ep ON te.employee_id = ep.employee_id 
        JOIN personal_information e ON ep.personal_info_id = e.personal_info_id 
        JOIN training_sessions ts ON te.session_id = ts.session_id 
        JOIN training_courses tc ON ts.course_id = tc.course_id 
        JOIN trainers t ON ts.trainer_id = t.trainer_id 
        ORDER BY te.enrollment_date DESC
    ");
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $enrollments = [];
    $message = "Error fetching enrollments: " . $e->getMessage();
    $messageType = "error";
}

// Fetch trainers
try {
    $stmt = $pdo->query("SELECT * FROM trainers ORDER BY last_name, first_name");
    $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $trainers = [];
}

// Fetch training sessions for dropdowns
try {
    $stmt = $pdo->query("
        SELECT ts.*, tc.course_name, t.first_name as trainer_first, t.last_name as trainer_last
        FROM training_sessions ts 
        JOIN training_courses tc ON ts.course_id = tc.course_id 
        JOIN trainers t ON ts.trainer_id = t.trainer_id 
        WHERE ts.status = 'Scheduled' OR ts.status = 'In Progress'
        ORDER BY ts.start_date
    ");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sessions = [];
}

// Fetch employees for dropdowns
try {
    $stmt = $pdo->query("
        SELECT ep.employee_id, pi.first_name, pi.last_name 
        FROM employee_profiles ep 
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id 
        ORDER BY pi.last_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_enrollments");
    $totalEnrollments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_enrollments WHERE status = 'Enrolled'");
    $activeEnrollments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_enrollments WHERE status = 'Completed'");
    $completedEnrollments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM trainers");
    $totalTrainers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $totalEnrollments = 0;
    $activeEnrollments = 0;
    $completedEnrollments = 0;
    $totalTrainers = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollments & Tracking Management - HR System</title>
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
                <h2 class="section-title">Enrollments & Tracking Management</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-user-plus"></i>
                            <h3><?php echo $totalEnrollments; ?></h3>
                            <h6>Total Enrollments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-clock"></i>
                            <h3><?php echo $activeEnrollments; ?></h3>
                            <h6>Active Enrollments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-check-circle"></i>
                            <h3><?php echo $completedEnrollments; ?></h3>
                            <h6>Completed</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <h3><?php echo $totalTrainers; ?></h3>
                            <h6>Total Trainers</h6>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="enrollmentTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="enrollments-tab" data-toggle="tab" href="#enrollments" role="tab">
                            <i class="fas fa-user-plus"></i> Training Enrollments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="trainers-tab" data-toggle="tab" href="#trainers" role="tab">
                            <i class="fas fa-chalkboard-teacher"></i> Trainers
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="enrollmentTabsContent">
                    <!-- Enrollments Tab -->
                    <div class="tab-pane fade show active" id="enrollments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="enrollmentSearch" placeholder="Search enrollments...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addEnrollmentModal">
                                <i class="fas fa-plus"></i> Add Enrollment
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user-plus"></i> Training Enrollments</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Course</th>
                                                <th>Session</th>
                                                <th>Trainer</th>
                                                <th>Enrollment Date</th>
                                                <th>Status</th>
                                                <th>Score</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($enrollments as $enrollment): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($enrollment['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($enrollment['session_name']); ?></td>
                                                <td>
                                                    <span class="trainer-badge">
                                                        <?php echo htmlspecialchars($enrollment['trainer_first'] . ' ' . $enrollment['trainer_last']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($enrollment['status']); ?>">
                                                        <?php echo htmlspecialchars($enrollment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($enrollment['score']): ?>
                                                    <span class="score-badge"><?php echo $enrollment['score']; ?>%</span>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editEnrollment(<?php echo $enrollment['enrollment_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteEnrollment(<?php echo $enrollment['enrollment_id']; ?>)">
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

                    <!-- Trainers Tab -->
                    <div class="tab-pane fade" id="trainers" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="trainerSearch" placeholder="Search trainers...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addTrainerModal">
                                <i class="fas fa-plus"></i> Add Trainer
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Trainers</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Specialization</th>
                                                <th>Type</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($trainers as $trainer): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($trainer['email']); ?></td>
                                                <td><?php echo htmlspecialchars($trainer['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($trainer['specialization']); ?></td>
                                                <td>
                                                    <span class="trainer-badge">
                                                        <?php echo $trainer['is_internal'] ? 'Internal' : 'External'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editTrainer(<?php echo $trainer['trainer_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
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
        </div>
    </div>

    <!-- Add Enrollment Modal -->
    <div class="modal fade" id="addEnrollmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Training Enrollment</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_enrollment">
                        <div class="form-group">
                            <label>Employee *</label>
                            <select class="form-control" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['employee_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Training Session *</label>
                            <select class="form-control" name="session_id" required>
                                <option value="">Select Session</option>
                                <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo $session['session_id']; ?>">
                                    <?php echo htmlspecialchars($session['course_name'] . ' - ' . $session['session_name'] . ' (' . $session['trainer_first'] . ' ' . $session['trainer_last'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Enrollment Date *</label>
                                    <input type="date" class="form-control" name="enrollment_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status *</label>
                                    <select class="form-control" name="status" required>
                                        <option value="Enrolled">Enrolled</option>
                                        <option value="Waitlisted">Waitlisted</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Dropped">Dropped</option>
                                        <option value="Failed">Failed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Enrollment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Trainer Modal -->
    <div class="modal fade" id="addTrainerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Trainer</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_trainer">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" class="form-control" name="phone">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Specialization</label>
                            <input type="text" class="form-control" name="specialization" placeholder="e.g., Leadership, Technical Skills, etc.">
                        </div>
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea class="form-control" name="bio" rows="3" placeholder="Brief biography and experience"></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_internal" id="isInternal" checked>
                            <label class="form-check-label" for="isInternal">Internal Trainer</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Trainer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Enrollment Modal -->
    <div class="modal fade" id="editEnrollmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Update Enrollment</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_enrollment">
                        <input type="hidden" name="enrollment_id" id="editEnrollmentId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status *</label>
                                    <select class="form-control" name="status" required>
                                        <option value="Enrolled">Enrolled</option>
                                        <option value="Waitlisted">Waitlisted</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Dropped">Dropped</option>
                                        <option value="Failed">Failed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Completion Date</label>
                                    <input type="date" class="form-control" name="completion_date">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Score (%)</label>
                                    <input type="number" class="form-control" name="score" min="0" max="100" step="0.1">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Feedback</label>
                            <textarea class="form-control" name="feedback" rows="3" placeholder="Training feedback and comments"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Enrollment</button>
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
        $('#enrollmentSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#enrollments table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        $('#trainerSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#trainers table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Edit functions
        function editEnrollment(enrollmentId) {
            $('#editEnrollmentId').val(enrollmentId);
            $('#editEnrollmentModal').modal('show');
        }

        function editTrainer(trainerId) {
            alert('Edit trainer with ID: ' + trainerId);
        }

        // Delete enrollment function
        function deleteEnrollment(enrollmentId) {
            if (confirm('Are you sure you want to delete this enrollment?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_enrollment">
                    <input type="hidden" name="enrollment_id" value="${enrollmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
