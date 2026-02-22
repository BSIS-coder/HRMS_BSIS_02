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

$mySkills = [];
if ($employee_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT es.employee_skill_id, es.proficiency_level, es.assessed_date, es.notes,
                   s.skill_name, s.category
            FROM employee_skills es
            JOIN skill_matrix s ON es.skill_id = s.skill_id
            WHERE es.employee_id = ?
            ORDER BY s.category, s.skill_name
        ");
        $stmt->execute([$employee_id]);
        $mySkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error loading skills.";
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Skills - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title { color: #E91E63; margin-bottom: 20px; font-weight: 600; }
        .main-content { flex: 1; padding: 20px; background: #FCE4EC; }
        .info-card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); border: 1px solid #e9ecef; }
        .info-card h5 { color: #E91E63; margin-bottom: 20px; font-weight: 600; }
        .table th { background: #F8BBD0; color: #C2185B; }
        .proficiency-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .proficiency-beginner { background: #e2e3e5; color: #383d41; }
        .proficiency-intermediate { background: #d1ecf1; color: #0c5460; }
        .proficiency-advanced { background: #d4edda; color: #155724; }
        .proficiency-expert { background: #cce5ff; color: #004085; }
        .no-results { text-align: center; padding: 40px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'employee_sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title"><i class="fas fa-user-cog mr-2"></i>My Skills</h2>
                <p class="text-muted mb-4">Your assessed skills and proficiency levels. Contact HR to update.</p>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($employee_id): ?>
                    <div class="info-card">
                        <?php if (empty($mySkills)): ?>
                            <div class="no-results">No skills on record yet. Skills are added by HR based on assessments and training.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Skill</th>
                                            <th>Category</th>
                                            <th>Proficiency</th>
                                            <th>Assessed Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mySkills as $s): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($s['skill_name']); ?></td>
                                                <td><?php echo htmlspecialchars($s['category']); ?></td>
                                                <td><span class="proficiency-badge proficiency-<?php echo strtolower($s['proficiency_level'] ?? 'beginner'); ?>"><?php echo htmlspecialchars($s['proficiency_level'] ?? '—'); ?></span></td>
                                                <td><?php echo $s['assessed_date'] ? date('M d, Y', strtotime($s['assessed_date'])) : '—'; ?></td>
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
