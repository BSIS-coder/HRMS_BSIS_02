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
            
            case 'edit_trainer':
                try {
                    $stmt = $pdo->prepare("UPDATE trainers SET first_name=?, last_name=?, email=?, phone=?, specialization=?, bio=?, is_internal=? WHERE trainer_id=?");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['specialization'],
                        $_POST['bio'],
                        isset($_POST['is_internal']) ? 1 : 0,
                        $_POST['trainer_id']
                    ]);
                    $message = "Trainer updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating trainer: " . $e->getMessage();
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
            
            case 'edit_enrollment':
                try {
                    $stmt = $pdo->prepare("UPDATE training_enrollments SET session_id=?, employee_id=?, enrollment_date=?, status=?, completion_date=?, score=?, feedback=? WHERE enrollment_id=?");
                    $stmt->execute([
                        $_POST['session_id'],
                        $_POST['employee_id'],
                        $_POST['enrollment_date'],
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

            case 'delete_trainer':
                try {
                    $stmt = $pdo->prepare("DELETE FROM trainers WHERE trainer_id=?");
                    $stmt->execute([$_POST['trainer_id']]);
                    $message = "Trainer deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting trainer: " . $e->getMessage();
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
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Enhanced custom styles for training enrollments page */
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
            margin-bottom: 30px;
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

        .status-enrolled {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #cce5ff;
            color: #0056b3;
        }

        .status-waitlisted {
            background: #fff3cd;
            color: #856404;
        }

        .status-dropped {
            background: #f8d7da;
            color: #721c24;
        }

        .status-failed {
            background: #f5c6cb;
            color: #721c24;
        }

        .trainer-badge {
            background: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
        }

        .score-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
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
            max-width: 600px;
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
            padding: 10px 15px;
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

        .tab-navigation {
            display: flex;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .tab-button {
            flex: 1;
            padding: 20px;
            background: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            color: #666;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .tab-button.active {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .tab-button:hover:not(.active) {
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

            .tab-button {
                padding: 15px 10px;
                font-size: 14px;
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
                <h2 class="section-title"></h2>
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

                    <!-- Tab Navigation -->
                    <div class="tab-navigation">
                        <button class="tab-button active" onclick="showTab('enrollments')">
                            <i class="fas fa-user-plus"></i>
                            Training Enrollments
                        </button>
                        <button class="tab-button" onclick="showTab('trainers')">
                            <i class="fas fa-chalkboard-teacher"></i>
                            Trainers
                        </button>
                    </div>

                    <!-- Enrollments Tab -->
                    <div id="enrollments-tab" class="tab-content active">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="enrollmentSearchInput" placeholder="Search enrollments by employee, course, or trainer...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add_enrollment')">
                                ➕ Add New Enrollment
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="enrollmentTable">
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
                                <tbody id="enrollmentTableBody">
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
                                            <button class="btn btn-warning btn-small" onclick="editEnrollment(<?php echo $enrollment['enrollment_id']; ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteEnrollment(<?php echo $enrollment['enrollment_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($enrollments)): ?>
                            <div class="no-results">
                                <i class="fas fa-user-plus"></i>
                                <h3>No enrollments found</h3>
                                <p>Start by adding your first training enrollment.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Trainers Tab -->
                    <div id="trainers-tab" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="trainerSearchInput" placeholder="Search trainers by name, email, or specialization...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add_trainer')">
                                ➕ Add New Trainer
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="trainerTable">
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
                                <tbody id="trainerTableBody">
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
                                            <button class="btn btn-warning btn-small" onclick="editTrainer(<?php echo $trainer['trainer_id']; ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteTrainer(<?php echo $trainer['trainer_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($trainers)): ?>
                            <div class="no-results">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <h3>No trainers found</h3>
                                <p>Start by adding your first trainer.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Enrollment Modal -->
    <div id="enrollmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="enrollmentModalTitle">Add New Enrollment</h2>
                <span class="close" onclick="closeModal('enrollment')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="enrollmentForm" method="POST">
                    <input type="hidden" id="enrollment_action" name="action" value="add_enrollment">
                    <input type="hidden" id="enrollment_id" name="enrollment_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee *</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="session_id">Training Session *</label>
                                <select id="session_id" name="session_id" class="form-control" required>
                                    <option value="">Select Session</option>
                                    <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['session_id']; ?>">
                                        <?php echo htmlspecialchars($session['course_name'] . ' - ' . $session['session_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="enrollment_date">Enrollment Date *</label>
                                <input type="date" id="enrollment_date" name="enrollment_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="enrollment_status">Status *</label>
                                <select id="enrollment_status" name="status" class="form-control" required>
                                    <option value="Enrolled">Enrolled</option>
                                    <option value="Waitlisted">Waitlisted</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Dropped">Dropped</option>
                                    <option value="Failed">Failed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="completion_date">Completion Date</label>
                                <input type="date" id="completion_date" name="completion_date" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="score">Score (%)</label>
                                <input type="number" id="score" name="score" class="form-control" min="0" max="100" step="0.1">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="feedback">Feedback</label>
                        <textarea id="feedback" name="feedback" class="form-control" rows="3" placeholder="Training feedback and comments"></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('enrollment')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Enrollment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Trainer Modal -->
    <div id="trainerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="trainerModalTitle">Add New Trainer</h2>
                <span class="close" onclick="closeModal('trainer')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="trainerForm" method="POST">
                    <input type="hidden" id="trainer_action" name="action" value="add_trainer">
                    <input type="hidden" id="trainer_id" name="trainer_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="trainer_first_name">First Name *</label>
                                <input type="text" id="trainer_first_name" name="first_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="trainer_last_name">Last Name *</label>
                                <input type="text" id="trainer_last_name" name="last_name" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="trainer_email">Email *</label>
                                <input type="email" id="trainer_email" name="email" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="trainer_phone">Phone</label>
                                <input type="text" id="trainer_phone" name="phone" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="specialization">Specialization</label>
                        <input type="text" id="specialization" name="specialization" class="form-control" placeholder="e.g., Leadership, Technical Skills, etc.">
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" class="form-control" rows="3" placeholder="Brief biography and experience"></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="is_internal" name="is_internal" checked> Internal Trainer
                        </label>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('trainer')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Trainer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let enrollmentsData = <?= json_encode($enrollments) ?>;
        let trainersData = <?= json_encode($trainers) ?>;
        let sessionsData = <?= json_encode($sessions) ?>;
        let employeesData = <?= json_encode($employees) ?>;

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }

        // Search functionality
        document.getElementById('enrollmentSearchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('enrollmentTableBody');
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

        document.getElementById('trainerSearchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('trainerTableBody');
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
        function openModal(mode, id = null) {
            if (mode === 'add_enrollment') {
                const modal = document.getElementById('enrollmentModal');
                const form = document.getElementById('enrollmentForm');
                const title = document.getElementById('enrollmentModalTitle');
                const action = document.getElementById('enrollment_action');

                title.textContent = 'Add New Enrollment';
                action.value = 'add_enrollment';
                form.reset();
                document.getElementById('enrollment_id').value = '';
                document.getElementById('enrollment_date').value = new Date().toISOString().split('T')[0];
                modal.style.display = 'block';
                
            } else if (mode === 'edit_enrollment' && id) {
                const modal = document.getElementById('enrollmentModal');
                const title = document.getElementById('enrollmentModalTitle');
                const action = document.getElementById('enrollment_action');

                title.textContent = 'Edit Enrollment';
                action.value = 'edit_enrollment';
                document.getElementById('enrollment_id').value = id;
                populateEnrollmentForm(id);
                modal.style.display = 'block';
                
            } else if (mode === 'add_trainer') {
                const modal = document.getElementById('trainerModal');
                const form = document.getElementById('trainerForm');
                const title = document.getElementById('trainerModalTitle');
                const action = document.getElementById('trainer_action');

                title.textContent = 'Add New Trainer';
                action.value = 'add_trainer';
                form.reset();
                document.getElementById('trainer_id').value = '';
                document.getElementById('is_internal').checked = true;
                modal.style.display = 'block';
                
            } else if (mode === 'edit_trainer' && id) {
                const modal = document.getElementById('trainerModal');
                const title = document.getElementById('trainerModalTitle');
                const action = document.getElementById('trainer_action');

                title.textContent = 'Edit Trainer';
                action.value = 'edit_trainer';
                document.getElementById('trainer_id').value = id;
                populateTrainerForm(id);
                modal.style.display = 'block';
            }

            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalType) {
            const modal = document.getElementById(modalType + 'Modal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEnrollmentForm(enrollmentId) {
            const enrollment = enrollmentsData.find(e => e.enrollment_id == enrollmentId);
            if (enrollment) {
                document.getElementById('employee_id').value = enrollment.employee_id || '';
                document.getElementById('session_id').value = enrollment.session_id || '';
                document.getElementById('enrollment_date').value = enrollment.enrollment_date || '';
                document.getElementById('enrollment_status').value = enrollment.status || '';
                document.getElementById('completion_date').value = enrollment.completion_date || '';
                document.getElementById('score').value = enrollment.score || '';
                document.getElementById('feedback').value = enrollment.feedback || '';
            }
        }

        function populateTrainerForm(trainerId) {
            const trainer = trainersData.find(t => t.trainer_id == trainerId);
            if (trainer) {
                document.getElementById('trainer_first_name').value = trainer.first_name || '';
                document.getElementById('trainer_last_name').value = trainer.last_name || '';
                document.getElementById('trainer_email').value = trainer.email || '';
                document.getElementById('trainer_phone').value = trainer.phone || '';
                document.getElementById('specialization').value = trainer.specialization || '';
                document.getElementById('bio').value = trainer.bio || '';
                document.getElementById('is_internal').checked = trainer.is_internal == 1;
            }
        }

        function editEnrollment(enrollmentId) {
            openModal('edit_enrollment', enrollmentId);
        }

        function editTrainer(trainerId) {
            openModal('edit_trainer', trainerId);
        }

        function deleteEnrollment(enrollmentId) {
            if (confirm('Are you sure you want to delete this enrollment? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_enrollment">
                    <input type="hidden" name="enrollment_id" value="${enrollmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteTrainer(trainerId) {
            if (confirm('Are you sure you want to delete this trainer? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_trainer">
                    <input type="hidden" name="trainer_id" value="${trainerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const enrollmentModal = document.getElementById('enrollmentModal');
            const trainerModal = document.getElementById('trainerModal');
            
            if (event.target === enrollmentModal) {
                closeModal('enrollment');
            } else if (event.target === trainerModal) {
                closeModal('trainer');
            }
        }

        // Form validation
        document.getElementById('enrollmentForm').addEventListener('submit', function(e) {
            const score = document.getElementById('score').value;
            if (score && (score < 0 || score > 100)) {
                e.preventDefault();
                alert('Score must be between 0 and 100');
                return;
            }
        });

        document.getElementById('trainerForm').addEventListener('submit', function(e) {
            const email = document.getElementById('trainer_email').value;
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
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

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Set today's date as default for enrollment date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('enrollment_date').value = today;
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
