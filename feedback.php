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

// Current user role and employee id (only employees can submit feedback)
$current_role = $_SESSION['role'] ?? null;
$current_user_id = $_SESSION['user_id'] ?? null;
$current_employee_id = $_SESSION['employee_id'] ?? null;
if ($current_role === 'employee' && $current_user_id && $current_employee_id === null) {
    try {
        $stmt = $pdo->prepare("SELECT employee_id FROM users WHERE user_id = ? AND role = 'employee'");
        $stmt->execute([$current_user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['employee_id'] !== null) {
            $current_employee_id = (int) $row['employee_id'];
            $_SESSION['employee_id'] = $current_employee_id;
        }
    } catch (PDOException $e) {
        // ignore
    }
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_feedback':
                if ($current_role !== 'employee') {
                    $message = "Only employees can submit feedback.";
                    $messageType = "error";
                    break;
                }
                if ($current_employee_id === null) {
                    $message = "Your account is not linked to an employee profile. Contact HR.";
                    $messageType = "error";
                    break;
                }
                try {
                    $stmt = $pdo->prepare("INSERT INTO training_feedback (employee_id, feedback_type, session_id, trainer_id, course_id, overall_rating, content_rating, instructor_rating, what_worked_well, what_could_improve, additional_comments, would_recommend, met_expectations, feedback_date, is_anonymous) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $current_employee_id,
                        $_POST['feedback_type'],
                        $_POST['session_id'] ?: NULL,
                        $_POST['trainer_id'] ?: NULL,
                        $_POST['course_id'] ?: NULL,
                        $_POST['overall_rating'],
                        $_POST['content_rating'] ?: NULL,
                        $_POST['instructor_rating'] ?: NULL,
                        $_POST['what_worked_well'],
                        $_POST['what_could_improve'],
                        $_POST['additional_comments'],
                        isset($_POST['would_recommend']) ? 1 : 0,
                        isset($_POST['met_expectations']) ? 1 : 0,
                        $_POST['feedback_date'],
                        isset($_POST['is_anonymous']) ? 1 : 0
                    ]);
                    $message = "Training feedback submitted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding feedback: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'edit_feedback':
                if ($current_role === 'employee') {
                    $message = "You are not allowed to edit feedback.";
                    $messageType = "error";
                    break;
                }
                try {
                    $stmt = $pdo->prepare("UPDATE training_feedback SET employee_id=?, feedback_type=?, session_id=?, trainer_id=?, course_id=?, overall_rating=?, content_rating=?, instructor_rating=?, what_worked_well=?, what_could_improve=?, additional_comments=?, would_recommend=?, met_expectations=?, feedback_date=?, is_anonymous=? WHERE feedback_id=?");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['feedback_type'],
                        $_POST['session_id'] ?: NULL,
                        $_POST['trainer_id'] ?: NULL,
                        $_POST['course_id'] ?: NULL,
                        $_POST['overall_rating'],
                        $_POST['content_rating'] ?: NULL,
                        $_POST['instructor_rating'] ?: NULL,
                        $_POST['what_worked_well'],
                        $_POST['what_could_improve'],
                        $_POST['additional_comments'],
                        isset($_POST['would_recommend']) ? 1 : 0,
                        isset($_POST['met_expectations']) ? 1 : 0,
                        $_POST['feedback_date'],
                        isset($_POST['is_anonymous']) ? 1 : 0,
                        $_POST['feedback_id']
                    ]);
                    $message = "Training feedback updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating feedback: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete_feedback':
                if ($current_role === 'employee') {
                    $message = "You are not allowed to delete feedback.";
                    $messageType = "error";
                    break;
                }
                try {
                    $stmt = $pdo->prepare("DELETE FROM training_feedback WHERE feedback_id=?");
                    $stmt->execute([$_POST['feedback_id']]);
                    $message = "Training feedback deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting feedback: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch feedback with related data (employees see only their own)
$feedback_sql = "
    SELECT tf.*,
           CASE WHEN tf.is_anonymous = 1 THEN 'Anonymous'
                ELSE CONCAT('Employee ID: ', tf.employee_id) END as employee_name,
           ts.session_name,
           tc.course_name,
           CONCAT(t.first_name, ' ', t.last_name) as trainer_name
    FROM training_feedback tf
    LEFT JOIN training_sessions ts ON tf.session_id = ts.session_id
    LEFT JOIN training_courses tc ON tf.course_id = tc.course_id
    LEFT JOIN trainers t ON tf.trainer_id = t.trainer_id
";
if ($current_role === 'employee') {
    if ($current_employee_id !== null) {
        $feedback_sql .= " WHERE tf.employee_id = ? ";
    } else {
        $feedback_sql .= " WHERE 1 = 0 "; // no linked profile: show nothing
    }
}
$feedback_sql .= " ORDER BY tf.feedback_date DESC";

try {
    $stmt = $pdo->prepare($feedback_sql);
    if ($current_role === 'employee' && $current_employee_id !== null) {
        $stmt->execute([$current_employee_id]);
    } else {
        $stmt->execute();
    }
    $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback = [];
    $message = "Error fetching feedback: " . $e->getMessage();
    $messageType = "error";
}

// Fetch training sessions for dropdown
try {
    $stmt = $pdo->query("SELECT session_id, session_name FROM training_sessions ORDER BY session_name");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sessions = [];
}

// Fetch trainers for dropdown
try {
    $stmt = $pdo->query("SELECT trainer_id, first_name, last_name FROM trainers ORDER BY first_name, last_name");
    $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $trainers = [];
}

// Fetch courses for dropdown
try {
    $stmt = $pdo->query("SELECT course_id, course_name FROM training_courses ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $courses = [];
}

// Get statistics (employees see only their own stats)
try {
    if ($current_role === 'employee' && $current_employee_id !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM training_feedback WHERE employee_id = ?");
        $stmt->execute([$current_employee_id]);
        $totalFeedback = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $pdo->prepare("SELECT AVG(overall_rating) as avg_rating FROM training_feedback WHERE employee_id = ? AND overall_rating IS NOT NULL");
        $stmt->execute([$current_employee_id]);
        $avgRating = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?: 0, 1);
        $stmt = $pdo->prepare("SELECT COUNT(*) as positive FROM training_feedback WHERE employee_id = ? AND would_recommend = 1");
        $stmt->execute([$current_employee_id]);
        $positiveRecommendations = $stmt->fetch(PDO::FETCH_ASSOC)['positive'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as anonymous FROM training_feedback WHERE employee_id = ? AND is_anonymous = 1");
        $stmt->execute([$current_employee_id]);
        $anonymousFeedback = $stmt->fetch(PDO::FETCH_ASSOC)['anonymous'];
    } elseif ($current_role === 'employee') {
        $totalFeedback = 0;
        $avgRating = 0;
        $positiveRecommendations = 0;
        $anonymousFeedback = 0;
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_feedback");
        $totalFeedback = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $pdo->query("SELECT AVG(overall_rating) as avg_rating FROM training_feedback WHERE overall_rating IS NOT NULL");
        $avgRating = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_rating'] ?: 0, 1);
        $stmt = $pdo->query("SELECT COUNT(*) as positive FROM training_feedback WHERE would_recommend = 1");
        $positiveRecommendations = $stmt->fetch(PDO::FETCH_ASSOC)['positive'];
        $stmt = $pdo->query("SELECT COUNT(*) as anonymous FROM training_feedback WHERE is_anonymous = 1");
        $anonymousFeedback = $stmt->fetch(PDO::FETCH_ASSOC)['anonymous'];
    }
} catch (PDOException $e) {
    $totalFeedback = 0;
    $avgRating = 0;
    $positiveRecommendations = 0;
    $anonymousFeedback = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Feedback Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for feedback page */
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

        .rating-stars {
            color: #ffc107;
            font-size: 18px;
        }

        .feedback-type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-training-session {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-trainer {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .type-course {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .type-learning-resource {
            background: #fff3e0;
            color: #f57c00;
        }

        .recommendation-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .recommend-yes {
            background: #d4edda;
            color: #155724;
        }

        .recommend-no {
            background: #f8d7da;
            color: #721c24;
        }

        .modal {
            position: fixed;
            z-index: 1060;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
        }

        .modal-backdrop {
            z-index: 1050;
        }

        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            max-height: 95vh;
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
            padding: 6px 15px;
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

        .form-col-3 {
            flex: 0 0 30%;
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input[type="checkbox"] {
            transform: scale(1.2);
            accent-color: var(--azure-blue);
        }

        .rating-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .rating-group select {
            width: 80px;
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

        .feedback-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-style: italic;
            color: #666;
            max-height: 100px;
            overflow: hidden;
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

            .checkbox-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body class="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') ? 'employee-page' : ''; ?>">
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') { include 'employee_sidebar.php'; } else { include 'sidebar.php'; } ?>
            <div class="main-content">
                <h2 class="section-title"><?php echo ($current_role === 'employee') ? 'Training Feedback' : 'Training Feedback Management'; ?></h2>
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
                                <i class="fas fa-comments"></i>
                                <h3><?php echo $totalFeedback; ?></h3>
                                <h6>Total Feedback</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-star"></i>
                                <h3><?php echo $avgRating; ?></h3>
                                <h6>Avg Rating</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-thumbs-up"></i>
                                <h3><?php echo $positiveRecommendations; ?></h3>
                                <h6>Recommendations</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-user-secret"></i>
                                <h3><?php echo $anonymousFeedback; ?></h3>
                                <h6>Anonymous</h6>
                            </div>
                        </div>
                    </div>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search feedback by employee, type, or content...">
                        </div>
                        <?php if ($current_role === 'employee' && $current_employee_id !== null): ?>
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addFeedbackModal">
                                <i class="fas fa-plus"></i> Submit feedback
                            </button>
                        <?php else: ?>
                            <div class="text-muted" style="align-self:center;">
                                <i class="fas fa-info-circle"></i> Viewing all feedback (read-only)
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-container">
                        <table class="table" id="feedbackTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Related To</th>
                                    <th>Overall Rating</th>
                                    <th>Date</th>
                                    <th>Recommend</th>
                                    <th>Preview</th>
                                    <!-- Actions column removed for view-only admin -->
                                </tr>
                            </thead>
                            <tbody id="feedbackTableBody">
                                <?php foreach ($feedback as $fb): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($fb['employee_name']); ?></strong>
                                        <?php if ($fb['is_anonymous']): ?>
                                            <i class="fas fa-user-secret" title="Anonymous Feedback" style="color: #666; margin-left: 5px;"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="feedback-type-badge type-<?php echo strtolower(str_replace([' ', '_'], '-', $fb['feedback_type'])); ?>">
                                            <?php echo htmlspecialchars($fb['feedback_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($fb['session_name']) {
                                            echo '<small>Session:</small> ' . htmlspecialchars($fb['session_name']);
                                        } elseif ($fb['trainer_name']) {
                                            echo '<small>Trainer:</small> ' . htmlspecialchars($fb['trainer_name']);
                                        } elseif ($fb['course_name']) {
                                            echo '<small>Course:</small> ' . htmlspecialchars($fb['course_name']);
                                        } else {
                                            echo '<small>General</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($fb['overall_rating']): ?>
                                            <span class="rating-stars">
                                                <?php 
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $fb['overall_rating'] ? '★' : '☆';
                                                }
                                                ?>
                                            </span>
                                            <small>(<?php echo $fb['overall_rating']; ?>/5)</small>
                                        <?php else: ?>
                                            <small class="text-muted">Not rated</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($fb['feedback_date'])); ?></td>
                                    <td>
                                        <span class="recommendation-badge recommend-<?php echo $fb['would_recommend'] ? 'yes' : 'no'; ?>">
                                            <?php echo $fb['would_recommend'] ? 'Yes' : 'No'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($fb['what_worked_well']): ?>
                                            <div class="feedback-preview">
                                                <?php echo htmlspecialchars(substr($fb['what_worked_well'], 0, 100)) . (strlen($fb['what_worked_well']) > 100 ? '...' : ''); ?>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted">No preview</small>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Actions removed for view-only admin -->
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($feedback)): ?>
                        <div class="no-results">
                            <i class="fas fa-comments"></i>
                            <h3>No feedback found</h3>
                            <p>Start by adding your first training feedback.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($current_role === 'employee' && $current_employee_id !== null): ?>
    <!-- Submit feedback modal (employees only) -->
    <div class="modal fade" id="addFeedbackModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white;">
                    <h5 class="modal-title">Submit training feedback</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="post" action="feedback.php">
                    <input type="hidden" name="action" value="add_feedback">
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Feedback type <span class="text-danger">*</span></label>
                                <select name="feedback_type" class="form-control" required>
                                    <option value="Training Session">Training Session</option>
                                    <option value="Learning Resource">Learning Resource</option>
                                    <option value="Trainer">Trainer</option>
                                    <option value="Course">Course</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Feedback date <span class="text-danger">*</span></label>
                                <input type="date" name="feedback_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Session</label>
                                <select name="session_id" class="form-control">
                                    <option value="">— Select —</option>
                                    <?php foreach ($sessions as $s): ?>
                                        <option value="<?php echo (int)$s['session_id']; ?>"><?php echo htmlspecialchars($s['session_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Course</label>
                                <select name="course_id" class="form-control">
                                    <option value="">— Select —</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?php echo (int)$c['course_id']; ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Trainer</label>
                                <select name="trainer_id" class="form-control">
                                    <option value="">— Select —</option>
                                    <?php foreach ($trainers as $t): ?>
                                        <option value="<?php echo (int)$t['trainer_id']; ?>"><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Overall rating (1–5) <span class="text-danger">*</span></label>
                                <select name="overall_rating" class="form-control" required>
                                    <?php for ($i = 1; $i <= 5; $i++) echo "<option value=\"$i\">$i</option>"; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Content rating (1–5)</label>
                                <select name="content_rating" class="form-control">
                                    <option value="">—</option>
                                    <?php for ($i = 1; $i <= 5; $i++) echo "<option value=\"$i\">$i</option>"; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Instructor rating (1–5)</label>
                                <select name="instructor_rating" class="form-control">
                                    <option value="">—</option>
                                    <?php for ($i = 1; $i <= 5; $i++) echo "<option value=\"$i\">$i</option>"; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>What worked well</label>
                            <textarea name="what_worked_well" class="form-control" rows="2" placeholder="Optional"></textarea>
                        </div>
                        <div class="form-group">
                            <label>What could improve</label>
                            <textarea name="what_could_improve" class="form-control" rows="2" placeholder="Optional"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Additional comments</label>
                            <textarea name="additional_comments" class="form-control" rows="2" placeholder="Optional"></textarea>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <label class="checkbox-item">
                                    <input type="checkbox" name="would_recommend" value="1"> Would recommend
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="met_expectations" value="1"> Met expectations
                                </label>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="is_anonymous" value="1"> Submit anonymously
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Minimal JS for admin read-only feedback page
        // Search functionality
        (function() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const tableBody = document.getElementById('feedbackTableBody');
                if (!tableBody) return;
                const rows = tableBody.getElementsByTagName('tr');

                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                }
            });

            // Auto-hide alerts
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() { alert.remove(); }, 500);
                });
            }, 5000);

            // Add small hover effect to table rows
            document.addEventListener('DOMContentLoaded', function() {
                const tableRows = document.querySelectorAll('#feedbackTable tbody tr');
                tableRows.forEach(row => {
                    row.addEventListener('mouseenter', function() { this.style.transform = 'scale(1.02)'; });
                    row.addEventListener('mouseleave', function() { this.style.transform = 'scale(1)'; });
                });
            });
        })();
    </script>

    <!-- Bootstrap JS + Dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>