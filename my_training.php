<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
$pdo = $conn;

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$employee_id = null;

try {
    $stmt = $pdo->prepare("SELECT employee_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $employee_id = $stmt->fetchColumn();
} catch (PDOException $e) {
    $error = "Database error. Please try again.";
}

if (!$employee_id) {
    $error = "Employee profile not found. Please contact administrator.";
}

// Fetch this employee's enrollments: upcoming/in progress and completed
$upcomingEnrollments = [];
$completedEnrollments = [];
if ($employee_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT te.enrollment_id, te.enrollment_date, te.status, te.completion_date, te.score,
                   ts.session_name, ts.start_date, ts.end_date, ts.status as session_status,
                   tc.course_name
            FROM training_enrollments te
            JOIN training_sessions ts ON te.session_id = ts.session_id
            JOIN training_courses tc ON ts.course_id = tc.course_id
            WHERE te.employee_id = ?
            ORDER BY ts.start_date DESC
        ");
        $stmt->execute([$employee_id]);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $today = date('Y-m-d');
        foreach ($all as $row) {
            if (in_array($row['status'], ['Completed']) || ($row['completion_date'] ?? '') !== '') {
                $completedEnrollments[] = $row;
            } else {
                $upcomingEnrollments[] = $row;
            }
        }
    } catch (PDOException $e) {
        $error = "Error loading training data.";
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Training - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title { color: #E91E63; margin-bottom: 20px; font-weight: 600; }
        .main-content { flex: 1; padding: 20px; background: #FCE4EC; }
        .info-card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); border: 1px solid #e9ecef; }
        .info-card h5 { color: #E91E63; margin-bottom: 20px; font-weight: 600; }
        .table th { background: #F8BBD0; color: #C2185B; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .status-enrolled { background: #d1ecf1; color: #0c5460; }
        .status-inprogress { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .no-results { text-align: center; padding: 40px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'employee_sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-graduation-cap mr-2"></i>My Training</h2>
                <p class="text-muted mb-4">View your training enrollments and completion history.</p>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($employee_id): ?>
                    <!-- Upcoming / In progress -->
                    <div class="info-card">
                        <h5><i class="fas fa-calendar-check mr-2"></i>Upcoming & In Progress</h5>
                        <?php if (empty($upcomingEnrollments)): ?>
                            <div class="no-results">You have no upcoming or in-progress training sessions.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Session</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcomingEnrollments as $e): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($e['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($e['session_name']); ?></td>
                                                <td><?php echo $e['start_date'] ? date('M d, Y', strtotime($e['start_date'])) : '—'; ?></td>
                                                <td><?php echo $e['end_date'] ? date('M d, Y', strtotime($e['end_date'])) : '—'; ?></td>
                                                <td><span class="status-badge status-<?php echo strtolower($e['status']); ?>"><?php echo htmlspecialchars($e['status']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Completed -->
                    <div class="info-card">
                        <h5><i class="fas fa-check-circle mr-2"></i>Completed</h5>
                        <?php if (empty($completedEnrollments)): ?>
                            <div class="no-results">No completed training yet.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Session</th>
                                            <th>Completed</th>
                                            <th>Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($completedEnrollments as $e): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($e['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($e['session_name']); ?></td>
                                                <td><?php echo $e['completion_date'] ? date('M d, Y', strtotime($e['completion_date'])) : '—'; ?></td>
                                                <td><?php echo $e['score'] !== null && $e['score'] !== '' ? $e['score'] . '%' : '—'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
