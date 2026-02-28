<?php
session_start();

// Check authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'dp.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_feedback') {
        try {
            $stmt = $pdo->prepare("INSERT INTO patient_feedback (patient_name, department, feedback_type, rating, comments, submitted_by, feedback_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $_POST['patient_name'],
                $_POST['department'],
                $_POST['feedback_type'],
                $_POST['rating'],
                $_POST['comments'],
                $_SESSION['user_id']
            ]);
            $message = "Feedback submitted successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    } elseif ($action === 'delete_feedback') {
        try {
            $stmt = $pdo->prepare("DELETE FROM patient_feedback WHERE feedback_id = ?");
            $stmt->execute([$_POST['feedback_id']]);
            $message = "Feedback deleted successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch all feedback
try {
    $stmt = $pdo->query("SELECT * FROM patient_feedback ORDER BY feedback_date DESC");
    $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total, AVG(rating) as avg_rating FROM patient_feedback WHERE rating IS NOT NULL");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT department, COUNT(*) as count FROM patient_feedback GROUP BY department");
    $deptStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total' => 0, 'avg_rating' => 0];
    $deptStats = [];
}

// Get departments for dropdown
try {
    $stmt = $pdo->query("SELECT DISTINCT department FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Feedback - HR Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .section-title { color: var(--primary-color); margin-bottom: 30px; font-weight: 600; }
        .container { max-width: 85%; margin-left: 265px; padding-top: 80px; }
        .stat-card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .feedback-card { transition: transform 0.3s; border-radius: 10px; margin-bottom: 15px; }
        .feedback-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .rating-stars { color: #ffc107; font-size: 1.2rem; }
    </style>
</head>
<body>
    <div class="container-fluid"><?php include 'navigation.php'; ?></div>
    <div class="row"><?php include 'sidebar.php'; ?></div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="section-title"><i class="fas fa-heartbeat me-2"></i>Patient Feedback</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                <i class="fas fa-plus me-2"></i>Add Feedback
            </button>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-comments fa-2x mb-2"></i>
                        <h3><?= $stats['total'] ?? 0 ?></h3>
                        <small>Total Feedback</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-star fa-2x mb-2"></i>
                        <h3><?= number_format($stats['avg_rating'] ?? 0, 1) ?></h3>
                        <small>Average Rating</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-smile fa-2x mb-2"></i>
                        <h3><?= count(array_filter($feedback, function($f) { return $f['rating'] >= 4; })) ?></h3>
                        <small>Positive Feedback</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-frown fa-2x mb-2"></i>
                        <h3><?= count(array_filter($feedback, function($f) { return $f['rating'] <= 2; })) ?></h3>
                        <small>Needs Attention</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback List -->
        <div class="row">
            <?php if (empty($feedback)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No feedback yet</h4>
                    <p>Start collecting patient feedback by clicking "Add Feedback"</p>
                </div>
            <?php else: ?>
                <?php foreach ($feedback as $fb): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card feedback-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary"><?= htmlspecialchars($fb['feedback_type']) ?></span>
                                <small class="text-muted"><?= date('M d, Y', strtotime($fb['feedback_date'])) ?></small>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($fb['patient_name']) ?></h5>
                                <p class="text-muted small"><i class="fas fa-building me-1"></i> <?= htmlspecialchars($fb['department']) ?></p>
                                <div class="rating-stars mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $fb['rating']): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <p class="card-text"><?= htmlspecialchars($fb['comments'] ?? 'No comments') ?></p>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-sm btn-danger" onclick="deleteFeedback(<?= $fb['feedback_id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Patient Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_feedback">
                        <div class="mb-3">
                            <label class="form-label">Patient Name</label>
                            <input type="text" name="patient_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['department']) ?>">
                                        <?= htmlspecialchars($dept['department']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Feedback Type</label>
                            <select name="feedback_type" class="form-select" required>
                                <option value="Compliment">Compliment</option>
                                <option value="Complaint">Complaint</option>
                                <option value="Suggestion">Suggestion</option>
                                <option value="Inquiry">Inquiry</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select" required>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Good</option>
                                <option value="3">3 - Average</option>
                                <option value="2">2 - Below Average</option>
                                <option value="1">1 - Poor</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comments</label>
                            <textarea name="comments" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteFeedback(id) {
            if (confirm('Are you sure you want to delete this feedback?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_feedback"><input type="hidden" name="feedback_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
