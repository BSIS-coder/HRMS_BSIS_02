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

$assignedResources = [];
$allResources = [];
if ($employee_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT lr.resource_id, lr.resource_name, lr.resource_type, lr.description, lr.resource_url, lr.author, lr.duration,
                   er.assigned_date, er.due_date
            FROM employee_resources er
            JOIN learning_resources lr ON er.resource_id = lr.resource_id
            WHERE er.employee_id = ?
            ORDER BY er.assigned_date DESC
        ");
        $stmt->execute([$employee_id]);
        $assignedResources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT resource_id, resource_name, resource_type, description, resource_url, author, duration FROM learning_resources ORDER BY resource_name");
        $allResources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error loading resources.";
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Resources - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title { color: #E91E63; margin-bottom: 20px; font-weight: 600; }
        .main-content { flex: 1; padding: 20px; background: #FCE4EC; }
        .info-card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); border: 1px solid #e9ecef; }
        .info-card h5 { color: #E91E63; margin-bottom: 20px; font-weight: 600; }
        .resource-item { padding: 15px; border-bottom: 1px solid #f0f0f0; }
        .resource-item:last-child { border-bottom: none; }
        .resource-type { font-size: 0.85rem; color: #6c757d; }
        .no-results { text-align: center; padding: 40px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'employee_sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-book-open mr-2"></i>Learning Resources</h2>
                <p class="text-muted mb-4">Browse learning materials and resources assigned to you.</p>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($employee_id): ?>
                    <?php if (!empty($assignedResources)): ?>
                        <div class="info-card">
                            <h5><i class="fas fa-tasks mr-2"></i>Assigned to Me</h5>
                            <?php foreach ($assignedResources as $r): ?>
                                <div class="resource-item">
                                    <strong><?php echo htmlspecialchars($r['resource_name']); ?></strong>
                                    <span class="resource-type"> — <?php echo htmlspecialchars($r['resource_type']); ?></span>
                                    <?php if (!empty($r['description'])): ?>
                                        <p class="mb-1 mt-1 small text-muted"><?php echo htmlspecialchars(substr($r['description'], 0, 150)); ?><?php echo strlen($r['description']) > 150 ? '...' : ''; ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($r['resource_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($r['resource_url']); ?>" target="_blank" rel="noopener noreferrer" class="small">Open resource</a>
                                    <?php endif; ?>
                                    <?php if (!empty($r['assigned_date'])): ?>
                                        <span class="small text-muted">Assigned: <?php echo date('M d, Y', strtotime($r['assigned_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="info-card">
                        <h5><i class="fas fa-book mr-2"></i>All Resources</h5>
                        <?php if (empty($allResources)): ?>
                            <div class="no-results">No learning resources available yet.</div>
                        <?php else: ?>
                            <?php foreach ($allResources as $r): ?>
                                <div class="resource-item">
                                    <strong><?php echo htmlspecialchars($r['resource_name']); ?></strong>
                                    <span class="resource-type"> — <?php echo htmlspecialchars($r['resource_type']); ?></span>
                                    <?php if (!empty($r['description'])): ?>
                                        <p class="mb-1 mt-1 small text-muted"><?php echo htmlspecialchars(substr($r['description'], 0, 150)); ?><?php echo strlen($r['description']) > 150 ? '...' : ''; ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($r['resource_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($r['resource_url']); ?>" target="_blank" rel="noopener noreferrer" class="small">Open resource</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
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
