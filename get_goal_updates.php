<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has HR role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check user role - only HR and admin can access
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'hr'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Include database connection
require_once 'dp.php';

// Database connection
$host = getenv('DB_HOST') ?? 'localhost';
$dbname = getenv('DB_NAME') ?? 'hr_system';
$username = getenv('DB_USER') ?? 'root';
$password = getenv('DB_PASS') ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$goal_id = $_GET['goal_id'] ?? null;

if (!$goal_id || !is_numeric($goal_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid goal ID']);
    exit;
}

// Get goal details
$stmt = $pdo->prepare("
    SELECT g.*, CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
    FROM goals g
    LEFT JOIN employee_profiles ep ON g.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    WHERE g.goal_id = ?
");
$stmt->execute([$goal_id]);
$goal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$goal) {
    http_response_code(404);
    echo json_encode(['error' => 'Goal not found']);
    exit;
}

// Get goal updates with enhanced details
$stmt = $pdo->prepare("
    SELECT gu.*,
           u.username as updated_by_name,
           CASE 
               WHEN gu.status_after IS NOT NULL THEN 'Status Changed'
               WHEN gu.comments LIKE 'Progress updated%' THEN 'Progress Updated'
               ELSE 'Comment Added'
           END as update_type
    FROM goal_updates gu
    LEFT JOIN users u ON gu.updated_by = u.user_id
    WHERE gu.goal_id = ?
    ORDER BY gu.created_at DESC, gu.update_date DESC
");

try {
    $stmt->execute([$goal_id]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If query fails due to missing columns, try simpler query
    $stmt = $pdo->prepare("
        SELECT gu.*,
               NULL as updated_by_name,
               'Comment Added' as update_type
        FROM goal_updates gu
        WHERE gu.goal_id = ?
        ORDER BY gu.created_at DESC, gu.update_date DESC
    ");
    $stmt->execute([$goal_id]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to format update type
function getUpdateTypeIcon($update_type) {
    switch ($update_type) {
        case 'Status Changed':
            return '<i class="fas fa-sync-alt text-primary"></i>';
        case 'Progress Updated':
            return '<i class="fas fa-chart-line text-success"></i>';
        default:
            return '<i class="fas fa-comment text-info"></i>';
    }
}

// Helper function to format update type badge color
function getUpdateTypeBadgeClass($update_type) {
    switch ($update_type) {
        case 'Status Changed':
            return 'badge-primary';
        case 'Progress Updated':
            return 'badge-success';
        default:
            return 'badge-info';
    }
}

// Return HTML content for the modal
?>
<div class="goal-details mb-4">
    <h5 class="mb-3"><?= htmlspecialchars($goal['title']) ?></h5>
    <div class="row">
        <div class="col-md-6">
            <p><strong>Employee:</strong> <?= htmlspecialchars($goal['employee_name']) ?></p>
            <p><strong>Status:</strong>
                <span class="badge badge-<?= strtolower(str_replace(' ', '-', $goal['status'])) === 'completed' ? 'success' : (strtolower(str_replace(' ', '-', $goal['status'])) === 'in-progress' ? 'primary' : 'secondary') ?>">
                    <?= htmlspecialchars($goal['status']) ?>
                </span>
            </p>
        </div>
        <div class="col-md-6">
            <p><strong>Current Progress:</strong> <strong class="text-<?= $goal['progress'] >= 100 ? 'success' : ($goal['progress'] >= 50 ? 'primary' : 'warning') ?>"><?= $goal['progress'] ?>%</strong></p>
            <p><strong>Period:</strong> <?= date('M d, Y', strtotime($goal['start_date'])) ?> - <?= date('M d, Y', strtotime($goal['end_date'])) ?></p>
        </div>
    </div>
    <p><strong>Description:</strong> <?= htmlspecialchars($goal['description']) ?></p>
    <hr>
</div>

<?php if (!empty($updates)): ?>
<div class="timeline">
    <h5 class="mb-4"><i class="fas fa-history"></i> Update History (<?= count($updates) ?> total)</h5>
    <?php foreach ($updates as $index => $update): ?>
    <div class="timeline-item">
        <div class="bg-light p-3 rounded">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="mb-2">
                        <span class="badge <?= getUpdateTypeBadgeClass($update['update_type']) ?>">
                            <?= $update['update_type'] ?>
                        </span>
                        <?php if ($update['progress'] !== null): ?>
                            <span class="badge badge-secondary">Progress: <?= $update['progress'] ?>%</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($update['status_before'] && $update['status_after']): ?>
                    <p class="mb-2 small"><strong>Status Change:</strong>
                        <span class="text-muted"><?= htmlspecialchars($update['status_before']) ?></span>
                        <i class="fas fa-arrow-right text-muted"></i>
                        <strong><?= htmlspecialchars($update['status_after']) ?></strong>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($update['comments']): ?>
                    <p class="mb-1 text-dark">
                        <?= htmlspecialchars($update['comments']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-end mt-2 pt-2 border-top">
                <div>
                    <small class="text-muted">
                        <i class="far fa-calendar"></i> <?= date('M d, Y \a\t H:i', strtotime($update['created_at'])) ?>
                        <?php if ($update['updated_by_name']): ?>
                            <br><i class="fas fa-user-circle"></i> by <strong><?= htmlspecialchars($update['updated_by_name']) ?></strong>
                        <?php endif; ?>
                    </small>
                </div>
                <div>
                    <?php 
                    // Check if user can delete (admin or creator)
                    $user_id = $_SESSION['user_id'] ?? null;
                    $is_editable = ($_SESSION['role'] === 'admin' || $update['updated_by'] == $user_id);
                    $primary_key_id = $update['goal_update_id'] ?? $update['update_id'] ?? null;
                    
                    if ($is_editable && $primary_key_id): 
                    ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUpdateConfirm(<?= $primary_key_id ?>)">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-4">
    <i class="fas fa-history fa-3x text-muted mb-3"></i>
    <p class="text-muted">No updates recorded for this goal yet.</p>
</div>
<?php endif; ?>
