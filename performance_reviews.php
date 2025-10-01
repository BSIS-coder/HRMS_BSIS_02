<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'db.php';

// Get user role
$user_role = $_SESSION['role'] ?? 'employee';
$user_id = $_SESSION['user_id'] ?? 0;

// Function to get performance review details
function getPerformanceReviewDetails($review_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, prc.cycle_name FROM performance_reviews pr JOIN employees e ON pr.employee_id = e.employee_id JOIN performance_review_cycles prc ON pr.cycle_id = prc.cycle_id WHERE pr.review_id = ?");
    $stmt->execute([$review_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch pending reviews
$pending_reviews = [];
if ($user_role == 'manager' || $user_role == 'hr') {
    $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, prc.cycle_name FROM performance_reviews pr JOIN employees e ON pr.employee_id = e.employee_id JOIN performance_review_cycles prc ON pr.cycle_id = prc.cycle_id WHERE pr.status = 'pending'");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT pr.*, prc.cycle_name FROM performance_reviews pr JOIN performance_review_cycles prc ON pr.cycle_id = prc.cycle_id WHERE pr.employee_id = ? AND pr.status = 'pending'");
    $stmt->execute([$user_id]);
}
$pending_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch completed reviews
$completed_reviews = [];
if ($user_role == 'manager' || $user_role == 'hr') {
    $stmt = $conn->prepare("SELECT pr.*, e.first_name, e.last_name, prc.cycle_name FROM performance_reviews pr JOIN employees e ON pr.employee_id = e.employee_id JOIN performance_review_cycles prc ON pr.cycle_id = prc.cycle_id WHERE pr.status = 'completed'");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT pr.*, prc.cycle_name FROM performance_reviews pr JOIN performance_review_cycles prc ON pr.cycle_id = prc.cycle_id WHERE pr.employee_id = ? AND pr.status = 'completed'");
    $stmt->execute([$user_id]);
}
$completed_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance Reviews</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">

    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }

        .container {
            max-width: 1150px;
            margin-left: 265px;
            padding-top: 5rem;
        }

        .nav-tabs .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }

        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- Navigation -->
    <?php include 'navigation.php'; ?>

    <div class="row">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="container">
            <h1 class="section-title">Performance Reviews</h1>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="reviewTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">Pending Reviews</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">Completed Reviews</button>
                </li>
            </ul>

            <div class="tab-content" id="reviewTabsContent">
                <!-- Pending Reviews Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Employee</th>
                                    <th>Cycle</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pending_reviews)): ?>
                                    <?php foreach ($pending_reviews as $review): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(($user_role == 'manager' || $user_role == 'hr') ? $review['first_name'] . ' ' . $review['last_name'] : 'You'); ?></td>
                                            <td><?php echo htmlspecialchars($review['cycle_name']); ?></td>
                                            <td><?php echo htmlspecialchars($review['status']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary">View</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No pending reviews</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Completed Reviews Tab -->
                <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Employee</th>
                                    <th>Cycle</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($completed_reviews)): ?>
                                    <?php foreach ($completed_reviews as $review): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(($user_role == 'manager' || $user_role == 'hr') ? $review['first_name'] . ' ' . $review['last_name'] : 'You'); ?></td>
                                            <td><?php echo htmlspecialchars($review['cycle_name']); ?></td>
                                            <td><?php echo htmlspecialchars($review['status']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info">View Details</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No completed reviews</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- End .container -->
    </div><!-- End .row -->
</div><!-- End .container-fluid -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
