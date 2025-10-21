<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_cycle':
                createFeedbackCycle($_POST);
                break;
            case 'submit_feedback':
                submitFeedback($_POST);
                break;
            case 'request_feedback':
                requestFeedback($_POST);
                break;
        }
    }
}

// Create a feedback cycle
function createFeedbackCycle($data) {
    global $conn;
    $stmt = mysqli_prepare($conn, "INSERT INTO feedback_cycles (cycle_name, description, start_date, end_date, status, created_by)
                            VALUES (?, ?, ?, ?, 'Active', ?)");
    if (!$stmt) {
        $_SESSION['error_message'] = "Database error: " . mysqli_error($conn);
        header('Location: feedback_360.php');
        exit;
    }
    mysqli_stmt_bind_param($stmt, "ssssi", $data['cycle_name'], $data['description'], $data['start_date'], $data['end_date'], $_SESSION['user_id']);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Feedback cycle created successfully!";
    } else {
        $_SESSION['error_message'] = "Error creating feedback cycle: " . mysqli_stmt_error($stmt);
    }
    mysqli_stmt_close($stmt);

    header('Location: feedback_360.php');
    exit;
}

// Submit feedback
function submitFeedback($data) {
    global $conn;

    $responses_json = json_encode($data['responses']);
    $stmt = mysqli_prepare($conn, "INSERT INTO feedback_responses (request_id, reviewer_id, responses, comments, submitted_at)
                            VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "iiss", $data['request_id'], $_SESSION['user_id'], $responses_json, $data['comments']);

    if (mysqli_stmt_execute($stmt)) {
        $update = mysqli_prepare($conn, "UPDATE feedback_requests SET status = 'Completed' WHERE request_id = ?");
        mysqli_stmt_bind_param($update, "i", $data['request_id']);
        mysqli_stmt_execute($update);
        mysqli_stmt_close($update);
        $_SESSION['success_message'] = "Feedback submitted successfully!";
    } else {
        $_SESSION['error_message'] = "Error submitting feedback: " . mysqli_stmt_error($stmt);
    }

    mysqli_stmt_close($stmt);
    header('Location: feedback_360.php');
    exit;
}

// Request feedback
function requestFeedback($data) {
    global $conn;

    $reviewers = explode(',', $data['reviewers']);
    $stmt = mysqli_prepare($conn, "INSERT INTO feedback_requests (employee_id, reviewer_id, cycle_id, relationship_type, status, created_at)
                            VALUES (?, ?, ?, ?, 'Pending', NOW())");

    foreach ($reviewers as $reviewer_id) {
        $reviewer_id = trim($reviewer_id);
        mysqli_stmt_bind_param($stmt, "iiis", $data['employee_id'], $reviewer_id, $data['cycle_id'], $data['relationship_type']);
        mysqli_stmt_execute($stmt);
    }

    mysqli_stmt_close($stmt);
    $_SESSION['success_message'] = "Feedback requests sent successfully!";
    header('Location: feedback_360.php');
    exit;
}

// Feedback statistics
function getFeedbackStats() {
    global $conn;
    $stats = [];

    $queries = [
        'total_cycles' => "SELECT COUNT(*) AS total FROM feedback_cycles",
        'active_cycles' => "SELECT COUNT(*) AS total FROM feedback_cycles WHERE status = 'Active'",
        'pending_requests' => "SELECT COUNT(*) AS total FROM feedback_requests WHERE status = 'Pending'",
        'completed_feedback' => "SELECT COUNT(*) AS total FROM feedback_responses"
    ];

    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : ['total' => 0];
        $stats[$key] = $row['total'];
    }

    return $stats;
}

// Recent feedback activities
function getRecentFeedbackActivities() {
    global $conn;
    $sql = "SELECT fr.request_id, e.first_name, e.last_name, fr.status, fr.created_at,
                   fc.cycle_name, fr.relationship_type
            FROM feedback_requests fr
            JOIN employee_profiles ep ON fr.employee_id = ep.employee_id
            JOIN personal_information e ON ep.personal_info_id = e.personal_info_id
            JOIN feedback_cycles fc ON fr.cycle_id = fc.cycle_id
            ORDER BY fr.created_at DESC LIMIT 10";
    $result = $conn->query($sql);

    $activities = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    }
    return $activities;
}

// Employee list
function getEmployeesWithDetails() {
    global $conn;
    $sql = "SELECT ep.employee_id, pi.first_name, pi.last_name, jr.title, d.department_name
            FROM employee_profiles ep
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE ep.employment_status = 'Full-time'
            ORDER BY pi.first_name, pi.last_name";
    $result = $conn->query($sql);

    $employees = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
    return $employees;
}

// Feedback cycles
function getFeedbackCycles() {
    global $conn;
    $sql = "SELECT * FROM feedback_cycles ORDER BY created_at DESC";
    $result = $conn->query($sql);

    $cycles = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cycles[] = $row;
        }
    }
    return $cycles;
}

// Pending feedback requests
function getPendingFeedbackRequests() {
    global $conn;
    $sql = "SELECT fr.request_id, fr.employee_id, fr.relationship_type, fc.cycle_name,
                   pi.first_name, pi.last_name, fr.created_at
            FROM feedback_requests fr
            JOIN feedback_cycles fc ON fr.cycle_id = fc.cycle_id
            JOIN employee_profiles ep ON fr.employee_id = ep.employee_id
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            WHERE fr.reviewer_id = ? AND fr.status = 'Pending'
            ORDER BY fr.created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return []; // Return empty array if prepare fails
    }
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();

    return $requests;
}

// Aggregated feedback for employee
function getTotalFeedback($employee_id) {
    global $conn;
    $sql = "SELECT fr.responses, fr.comments, fr.submitted_at, fc.cycle_name,
                   pi.first_name, pi.last_name, freq.relationship_type
            FROM feedback_responses fr
            JOIN feedback_requests freq ON fr.request_id = freq.request_id
            JOIN feedback_cycles fc ON freq.cycle_id = fc.cycle_id
            JOIN employee_profiles ep ON freq.employee_id = ep.employee_id
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            WHERE freq.employee_id = ?
            ORDER BY fr.submitted_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $feedbacks = [];
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
    $stmt->close();

    // Aggregate responses
    $aggregated = [
        'leadership' => [], 'communication' => [], 'teamwork' => [],
        'problem_solving' => [], 'work_quality' => [],
        'comments' => [], 'reviewers' => []
    ];

    foreach ($feedbacks as $feedback) {
        $responses = json_decode($feedback['responses'], true);
        if ($responses) {
            foreach ($responses as $key => $value) {
                if (isset($aggregated[$key])) {
                    $aggregated[$key][] = (int)$value;
                }
            }
        }

        if (!empty($feedback['comments'])) {
            $aggregated['comments'][] = [
                'comment' => $feedback['comments'],
                'reviewer' => $feedback['first_name'] . ' ' . $feedback['last_name'],
                'relationship' => $feedback['relationship_type'],
                'cycle' => $feedback['cycle_name'],
                'date' => $feedback['submitted_at']
            ];
        }

        $aggregated['reviewers'][] = [
            'name' => $feedback['first_name'] . ' ' . $feedback['last_name'],
            'relationship' => $feedback['relationship_type'],
            'cycle' => $feedback['cycle_name']
        ];
    }

    // Calculate averages
    $averages = [];
    foreach (['leadership', 'communication', 'teamwork', 'problem_solving', 'work_quality'] as $key) {
        $averages[$key] = !empty($aggregated[$key])
            ? round(array_sum($aggregated[$key]) / count($aggregated[$key]), 1)
            : 0;
    }

    return [
        'averages' => $averages,
        'comments' => $aggregated['comments'],
        'reviewers' => $aggregated['reviewers'],
        'total_feedbacks' => count($feedbacks)
    ];
}

$stats = getFeedbackStats();
$recent_activities = getRecentFeedbackActivities();
$employees = getEmployeesWithDetails();
$cycles = getFeedbackCycles();
$pending_requests = getPendingFeedbackRequests();
?>
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>360-Degree Feedback - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .feedback-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .feedback-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(233, 30, 99, 0.2);
        }

        .feedback-header {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            padding: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
        }

        .progress-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(#E91E63 0% 75%, #f8f9fa 75% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .rating-stars {
            color: #ffc107;
            font-size: 18px;
        }

        .relationship-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-supervisor { background: #e3f2fd; color: #1976d2; }
        .badge-peer { background: #f3e5f5; color: #7b1fa2; }
        .badge-subordinate { background: #e8f5e8; color: #388e3c; }
        .badge-self { background: #fff3e0; color: #f57c00; }

        .feedback-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .question-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .modal-xl {
            max-width: 1200px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">
                    <i class="fas fa-comments mr-2"></i>
                    360-Degree Feedback
                </h2>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card feedback-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-primary text-white">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                                <h6 class="text-muted">Total Cycles</h6>
                                <h3 class="card-title text-primary"><?php echo $stats['total_cycles']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card feedback-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-success text-white">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                                <h6 class="text-muted">Active Cycles</h6>
                                <h3 class="card-title text-success"><?php echo $stats['active_cycles']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card feedback-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-warning text-white">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h6 class="text-muted">Pending Requests</h6>
                                <h3 class="card-title text-warning"><?php echo $stats['pending_requests']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card feedback-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-info text-white">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h6 class="text-muted">Completed Feedback</h6>
                                <h3 class="card-title text-info"><?php echo $stats['completed_feedback']; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <h5 class="mb-0">Quick Actions</h5>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#createCycleModal">
                                            <i class="fas fa-plus mr-2"></i>Create Cycle
                                        </button>
                                        <button class="btn btn-success" data-toggle="modal" data-target="#requestFeedbackModal">
                                            <i class="fas fa-user-plus mr-2"></i>Request Feedback
                                        </button>
                                        <button class="btn btn-warning" data-toggle="modal" data-target="#viewTotalFeedbackModal">
                                            <i class="fas fa-chart-bar mr-2"></i>View Total Feedback
                                        </button>
                                        <button class="btn btn-info" onclick="location.reload()">
                                            <i class="fas fa-sync-alt mr-2"></i>Refresh
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Feedback Requests -->
                <?php if (!empty($pending_requests)): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header feedback-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock mr-2"></i>
                                    Pending Feedback Requests (<?php echo count($pending_requests); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($pending_requests as $request): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-left-primary">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="card-title">
                                                            <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                                        </h6>
                                                        <p class="card-text text-muted mb-2">
                                                            <?php echo htmlspecialchars($request['cycle_name']); ?>
                                                        </p>
                                                        <span class="relationship-badge badge-<?php echo strtolower($request['relationship_type']); ?>">
                                                            <?php echo ucfirst($request['relationship_type']); ?>
                                                        </span>
                                                    </div>
                                                    <button class="btn btn-sm btn-primary" onclick="provideFeedback(<?php echo $request['request_id']; ?>)">
                                                        <i class="fas fa-edit mr-1"></i>Provide Feedback
                                                    </button>
                                                </div>
                                                <small class="text-muted">
                                                    Requested: <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Activities -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header feedback-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history mr-2"></i>
                                    Recent Feedback Activities
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recent_activities)): ?>
                                    <div class="timeline">
                                        <?php foreach ($recent_activities as $activity): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-primary"></div>
                                            <div class="timeline-content">
                                                <h6 class="mb-1">
                                                    Feedback request for <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                </h6>
                                                <p class="text-muted mb-1">
                                                    Cycle: <?php echo htmlspecialchars($activity['cycle_name']); ?> |
                                                    Type: <?php echo ucfirst($activity['relationship_type']); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?> |
                                                    Status: <span class="badge badge-<?php echo $activity['status'] === 'Completed' ? 'success' : 'warning'; ?>">
                                                        <?php echo $activity['status']; ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">No recent feedback activities</h6>
                                        <p class="text-muted">Feedback activities will appear here once they are created.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Active Cycles Summary -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header feedback-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-play-circle mr-2"></i>
                                    Active Cycles
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $active_cycles = array_filter($cycles, function($cycle) {
                                    return $cycle['status'] === 'Active';
                                });
                                ?>

                                <?php if (!empty($active_cycles)): ?>
                                    <?php foreach (array_slice($active_cycles, 0, 3) as $cycle): ?>
                                    <div class="mb-3">
                                        <h6 class="mb-2"><?php echo htmlspecialchars($cycle['cycle_name']); ?></h6>
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-primary" style="width: 75%"></div>
                                        </div>
                                        <small class="text-muted">
                                            Ends: <?php echo date('M d, Y', strtotime($cycle['end_date'])); ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-pause-circle fa-2x text-muted mb-2"></i>
                                        <p class="text-muted small">No active cycles</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Cycle Modal -->
    <div class="modal fade" id="createCycleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header feedback-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus mr-2"></i>Create Feedback Cycle
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_cycle">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cycle_name">Cycle Name *</label>
                                    <input type="text" class="form-control" id="cycle_name" name="cycle_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">Start Date *</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date">End Date *</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="Active">Active</option>
                                        <option value="Draft">Draft</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Cycle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Request Feedback Modal -->
    <div class="modal fade" id="requestFeedbackModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header feedback-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus mr-2"></i>Request Feedback
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="request_feedback">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="employee_id">Employee *</label>
                                    <select class="form-control" id="employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['employee_id']; ?>">
                                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . $employee['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cycle_id">Feedback Cycle *</label>
                                    <select class="form-control" id="cycle_id" name="cycle_id" required>
                                        <option value="">Select Cycle</option>
                                        <?php foreach ($cycles as $cycle): ?>
                                            <option value="<?php echo $cycle['cycle_id']; ?>">
                                                <?php echo htmlspecialchars($cycle['cycle_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="relationship_type">Relationship Type *</label>
                                    <select class="form-control" id="relationship_type" name="relationship_type" required>
                                        <option value="supervisor">Supervisor</option>
                                        <option value="peer">Peer</option>
                                        <option value="subordinate">Subordinate</option>
                                        <option value="self">Self</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="reviewers">Select Reviewers *</label>
                                    <input type="text" class="form-control" id="reviewers" name="reviewers"
                                           placeholder="Enter employee IDs separated by commas" required>
                                    <small class="form-text text-muted">Enter employee IDs separated by commas (e.g., 1,2,3)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Send Requests</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Provide Feedback Modal -->
    <div class="modal fade" id="provideFeedbackModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header feedback-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit mr-2"></i>Provide Feedback
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST" id="feedbackForm">
                    <div class="modal-body" id="feedbackModalBody">
                        <!-- Dynamic content will be loaded here -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Total Feedback Modal -->
    <div class="modal fade" id="viewTotalFeedbackModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header feedback-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-bar mr-2"></i>View Total Feedback
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="select_employee">Select Employee</label>
                                <select class="form-control" id="select_employee">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['employee_id']; ?>">
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . $employee['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div id="totalFeedbackContent">
                        <div class="text-center py-4">
                            <i class="fas fa-user-check fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Select an employee to view their total feedback</h6>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
    function provideFeedback(requestId) {
        // Load feedback form content
        $('#feedbackModalBody').html(`
            <input type="hidden" name="action" value="submit_feedback">
            <input type="hidden" name="request_id" value="${requestId}">

            <div class="feedback-form">
                <h6 class="mb-3">Please provide your feedback by rating the following competencies:</h6>

                <div class="question-item">
                    <label class="form-label">Leadership Skills</label>
                    <div class="rating-stars mb-2">
                        <input type="radio" name="responses[leadership]" value="1" id="leadership1">
                        <label for="leadership1">★</label>
                        <input type="radio" name="responses[leadership]" value="2" id="leadership2">
                        <label for="leadership2">★</label>
                        <input type="radio" name="responses[leadership]" value="3" id="leadership3">
                        <label for="leadership3">★</label>
                        <input type="radio" name="responses[leadership]" value="4" id="leadership4">
                        <label for="leadership4">★</label>
                        <input type="radio" name="responses[leadership]" value="5" id="leadership5" checked>
                        <label for="leadership5">★</label>
                    </div>
                </div>

                <div class="question-item">
                    <label class="form-label">Communication Skills</label>
                    <div class="rating-stars mb-2">
                        <input type="radio" name="responses[communication]" value="1" id="comm1">
                        <label for="comm1">★</label>
                        <input type="radio" name="responses[communication]" value="2" id="comm2">
                        <label for="comm2">★</label>
                        <input type="radio" name="responses[communication]" value="3" id="comm3">
                        <label for="comm3">★</label>
                        <input type="radio" name="responses[communication]" value="4" id="comm4">
                        <label for="comm4">★</label>
                        <input type="radio" name="responses[communication]" value="5" id="comm5" checked>
                        <label for="comm5">★</label>
                    </div>
                </div>

                <div class="question-item">
                    <label class="form-label">Teamwork</label>
                    <div class="rating-stars mb-2">
                        <input type="radio" name="responses[teamwork]" value="1" id="team1">
                        <label for="team1">★</label>
                        <input type="radio" name="responses[teamwork]" value="2" id="team2">
                        <label for="team2">★</label>
                        <input type="radio" name="responses[teamwork]" value="3" id="team3">
                        <label for="team3">★</label>
                        <input type="radio" name="responses[teamwork]" value="4" id="team4">
                        <label for="team4">★</label>
                        <input type="radio" name="responses[teamwork]" value="5" id="team5" checked>
                        <label for="team5">★</label>
                    </div>
                </div>

                <div class="question-item">
                    <label class="form-label">Problem Solving</label>
                    <div class="rating-stars mb-2">
                        <input type="radio" name="responses[problem_solving]" value="1" id="ps1">
                        <label for="ps1">★</label>
                        <input type="radio" name="responses[problem_solving]" value="2" id="ps2">
                        <label for="ps2">★</label>
                        <input type="radio" name="responses[problem_solving]" value="3" id="ps3">
                        <label for="ps3">★</label>
                        <input type="radio" name="responses[problem_solving]" value="4" id="ps4">
                        <label for="ps4">★</label>
                        <input type="radio" name="responses[problem_solving]" value="5" id="ps5" checked>
                        <label for="ps5">★</label>
                    </div>
                </div>

                <div class="question-item">
                    <label class="form-label">Work Quality</label>
                    <div class="rating-stars mb-2">
                        <input type="radio" name="responses[work_quality]" value="1" id="wq1">
                        <label for="wq1">★</label>
                        <input type="radio" name="responses[work_quality]" value="2" id="wq2">
                        <label for="wq2">★</label>
                        <input type="radio" name="responses[work_quality]" value="3" id="wq3">
                        <label for="wq3">★</label>
                        <input type="radio" name="responses[work_quality]" value="4" id="wq4">
                        <label for="wq4">★</label>
                        <input type="radio" name="responses[work_quality]" value="5" id="wq5" checked>
                        <label for="wq5">★</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="comments">Additional Comments</label>
                    <textarea class="form-control" id="comments" name="comments" rows="4"
                              placeholder="Please provide any additional feedback or specific examples..."></textarea>
                </div>
            </div>
        `);

        $('#provideFeedbackModal').modal('show');
    }
    // Enhanced star rating functionality
    $(document).on('change', 'input[type="radio"]', function() {
        const name = $(this).attr('name');
        const value = $(this).val();

        // Update visual feedback for rating
        $(`input[name="${name}"]`).each(function() {
            const label = $(this).next('label');
            if ($(this).val() <= value) {
                label.css('color', '#ffc107');
            } else {
                label.css('color', '#ddd');
            }
        });
    });

    // Initialize star ratings on modal show
    $('#provideFeedbackModal').on('shown.bs.modal', function() {
        $('input[type="radio"]:checked').trigger('change');
    });

    // Form validation
    $('#feedbackForm').on('submit', function(e) {
        const ratings = $('input[type="radio"]:checked');
        if (ratings.length < 5) {
            e.preventDefault();
            alert('Please provide ratings for all competencies.');
            return false;
        }
    });

    // Auto-refresh functionality
    setInterval(function() {
        // You can add AJAX call here to refresh pending requests count
        console.log('Checking for new feedback requests...');
    }, 30000);

    // Handle employee selection for total feedback view
    $('#select_employee').on('change', function() {
        const employeeId = $(this).val();
        if (employeeId) {
            loadTotalFeedback(employeeId);
        } else {
            $('#totalFeedbackContent').html(`
                <div class="text-center py-4">
                    <i class="fas fa-user-check fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Select an employee to view their total feedback</h6>
                </div>
            `);
        }
    });

    function loadTotalFeedback(employeeId) {
        // Show loading
        $('#totalFeedbackContent').html(`
            <div class="text-center py-4">
                <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                <h6 class="text-muted">Loading feedback data...</h6>
            </div>
        `);

        // Simulate AJAX call (replace with actual AJAX when backend is ready)
        setTimeout(() => {
            // This would be replaced with actual AJAX call to get feedback data
            const feedbackData = getTotalFeedback(employeeId); // This function is already defined in PHP

            if (feedbackData && feedbackData.total_feedbacks > 0) {
                displayTotalFeedback(feedbackData);
            } else {
                $('#totalFeedbackContent').html(`
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">No feedback data available</h6>
                        <p class="text-muted">This employee hasn't received any feedback yet.</p>
                    </div>
                `);
            }
        }, 1000);
    }

    function displayTotalFeedback(data) {
        let html = `
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line mr-2"></i>
                                Feedback Summary (${data.total_feedbacks} responses)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Average Ratings</h6>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Leadership</span>
                                            <span class="badge badge-primary">${data.averages.leadership}/5</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-primary" style="width: ${(data.averages.leadership / 5) * 100}%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Communication</span>
                                            <span class="badge badge-success">${data.averages.communication}/5</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" style="width: ${(data.averages.communication / 5) * 100}%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Teamwork</span>
                                            <span class="badge badge-info">${data.averages.teamwork}/5</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-info" style="width: ${(data.averages.teamwork / 5) * 100}%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Problem Solving</span>
                                            <span class="badge badge-warning">${data.averages.problem_solving}/5</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-warning" style="width: ${(data.averages.problem_solving / 5) * 100}%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span>Work Quality</span>
                                            <span class="badge badge-danger">${data.averages.work_quality}/5</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-danger" style="width: ${(data.averages.work_quality / 5) * 100}%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-3">Feedback Sources</h6>
                                    <div class="list-group">
        `;

        // Group reviewers by relationship type
        const reviewersByType = {};
        data.reviewers.forEach(reviewer => {
            if (!reviewersByType[reviewer.relationship]) {
                reviewersByType[reviewer.relationship] = [];
            }
            reviewersByType[reviewer.relationship].push(reviewer);
        });

        // Build reviewers list
        for (const [type, reviewers] of Object.entries(reviewersByType)) {
            html += `<li class="list-group-item">
                <strong>${type.charAt(0).toUpperCase() + type.slice(1)} (${reviewers.length})</strong>
                <ul class="mb-0 mt-1">`;
            reviewers.forEach(reviewer => {
                html += `<li class="small">${reviewer.name} - ${reviewer.cycle}</li>`;
            });
            html += `</ul></li>`;
        }

        html += `
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-comments mr-2"></i>
                                    Feedback Comments
                                </h5>
                            </div>
                            <div class="card-body">
        `;

        if (data.comments.length > 0) {
            data.comments.forEach(comment => {
                html += `
                                <div class="mb-3 p-3 border-left-primary">
                                    <p class="mb-1"><strong>${comment.reviewer}</strong> (${comment.relationship}) - ${comment.cycle}</p>
                                    <p class="mb-1">${comment.comment}</p>
                                    <small class="text-muted">${new Date(comment.date).toLocaleDateString()}</small>
                                </div>
                `;
            });
        } else {
            html += `<p class="text-muted">No comments available.</p>`;
        }

        html += `
                            </div>
                        </div>
                    </div>
                </div>
        `;

        $('#totalFeedbackContent').html(html);
    }

    </script>

    <style>
    /* Timeline Styles */
    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
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

    .timeline-marker {
        position: absolute;
        left: -22px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #fff;
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #E91E63;
    }

    /* Star Rating Styles */
    .rating-stars {
        display: inline-block;
    }

    .rating-stars input[type="radio"] {
        display: none;
    }

    .rating-stars label {
        float: right;
        cursor: pointer;
        color: #ddd;
        transition: color 0.3s;
    }

    .rating-stars label:before {
        content: '★';
        font-size: 24px;
    }

    .rating-stars input[type="radio"]:checked ~ label {
        color: #ffc107;
    }

    /* Enhanced Modal Styles */
    .modal-content {
        border: none;
        border-radius: 15px;
        overflow: hidden;
    }

    .modal-header {
        border-bottom: none;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .timeline {
            padding-left: 20px;
        }

        .timeline-marker {
            left: -17px;
        }

        .btn-group {
            flex-direction: column;
            width: 100%;
        }

        .btn-group .btn {
            margin-bottom: 5px;
        }
    }
    </style>
</body>
</html>
