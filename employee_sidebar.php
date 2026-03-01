<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$user_role    = $_SESSION['role'] ?? 'user';
$username     = $_SESSION['username'] ?? 'User';
$employee_id  = $_SESSION['user_id'] ?? null;

function isActiveMenu($page_name) {
    global $current_page;
    return $current_page === $page_name ? 'active' : '';
}

if (!$is_logged_in || $user_role !== 'employee') {
    return;
}

// ── Shared DB connection ──────────────────────────────
try {
    $pdo_sb = new PDO("mysql:host=localhost;dbname=hr_system", 'root', '');
    $pdo_sb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    $pdo_sb = null;
}

// ── Resolve real employee_id from username ────────────
$resolved_emp_id = $employee_id;
$parts     = explode('.', $username);
$firstName = $parts[0] ?? '';
$lastName  = $parts[1] ?? '';

if ($pdo_sb && $firstName && $lastName) {
    try {
        $stmtResolve = $pdo_sb->prepare("
            SELECT ep.employee_id
            FROM employee_profiles ep
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            WHERE LOWER(pi.first_name) = LOWER(?) AND LOWER(pi.last_name) = LOWER(?)
            LIMIT 1
        ");
        $stmtResolve->execute([$firstName, $lastName]);
        $row = $stmtResolve->fetch(PDO::FETCH_ASSOC);
        if ($row) $resolved_emp_id = $row['employee_id'];
    } catch (Exception $e) {}
}

// ── Unread inbox count ────────────────────────────────
$unreadInboxCount = 0;
if ($pdo_sb && $resolved_emp_id) {
    try {
        $pdo_sb->exec("
            CREATE TABLE IF NOT EXISTS employee_inbox (
                inbox_id     INT AUTO_INCREMENT PRIMARY KEY,
                employee_id  INT          NOT NULL,
                exit_id      INT          NULL,
                sender_label VARCHAR(100) NOT NULL DEFAULT 'HR Department',
                subject      VARCHAR(255) NOT NULL,
                message      TEXT         NOT NULL,
                is_read      TINYINT(1)   NOT NULL DEFAULT 0,
                created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $stmtInbox = $pdo_sb->prepare("
            SELECT COUNT(*) FROM employee_inbox
            WHERE employee_id = ? AND is_read = 0
        ");
        $stmtInbox->execute([$resolved_emp_id]);
        $unreadInboxCount = (int) $stmtInbox->fetchColumn();
    } catch (Exception $e) {}
}

// ── Pending survey check (preserved from original) ───
$hasPendingSurvey = false;
if ($pdo_sb && $resolved_emp_id) {
    try {
        $pdo_sb->exec("CREATE TABLE IF NOT EXISTS survey_notifications (
            notif_id INT AUTO_INCREMENT PRIMARY KEY,
            exit_id INT NOT NULL UNIQUE,
            sent_by_user_id INT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (exit_id)
        )");
        $stmtManual = $pdo_sb->prepare("
            SELECT COUNT(*) FROM survey_notifications sn
            JOIN exits ex ON sn.exit_id = ex.exit_id
            WHERE ex.employee_id = ?
              AND ex.exit_id NOT IN (
                  SELECT exit_id FROM post_exit_surveys WHERE exit_id IS NOT NULL
              )
        ");
        $stmtManual->execute([$resolved_emp_id]);
        $hasPendingSurvey = $stmtManual->fetchColumn() > 0;
    } catch (Exception $e) {}
}
?>

<!-- Employee Sidebar -->
<div class="sidebar">
    <div class="user-profile-section mb-4">
        <div class="text-center">
            <div class="user-avatar mb-2">
                <i class="fas fa-user-circle fa-3x" style="color:#fff;"></i>
            </div>
            <h6 class="text-white mb-1"><?php echo htmlspecialchars($username); ?></h6>
            <small class="text-light">Employee</small>
        </div>
    </div>

    <!-- ── Bell notification row ── -->
    <?php if ($unreadInboxCount > 0): ?>
    <div class="inbox-bell-row">
        <a href="employee_inbox.php" class="inbox-bell-link">
            <span class="bell-wrapper has-unread">
                <i class="fas fa-bell"></i>
                <span class="bell-badge" id="inboxBellCount"><?= $unreadInboxCount ?></span>
            </span>
            <span class="bell-text">You have <strong><?= $unreadInboxCount ?></strong> new message<?= $unreadInboxCount > 1 ? 's' : '' ?></span>
        </a>
    </div>
    <?php endif; ?>
    <!-- ──────────────────────────── -->

    <h4 class="text-center mb-4" style="color:#fff;">Employee Portal</h4>

    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_index.php'); ?>" href="employee_index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('my_profile.php'); ?>" href="my_profile.php">
                <i class="fas fa-user"></i> My Profile
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('my_document.php'); ?>" href="my_document.php">
                <i class="fas fa-file-alt"></i> My Documents
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_leave.php'); ?>" href="employee_leave.php">
                <i class="fas fa-calendar-alt"></i> Leave Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('payslips.php'); ?>" href="payslips.php">
                <i class="fas fa-receipt"></i> My Payslips
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('performance_reviews.php'); ?>" href="performance_reviews.php">
                <i class="fas fa-chart-line"></i> Performance Reviews
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('training_enrollments.php'); ?>" href="training_enrollments.php">
                <i class="fas fa-graduation-cap"></i> My Training
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('goals.php'); ?>" href="goals.php">
                <i class="fas fa-bullseye"></i> My Goals
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_skills.php'); ?>" href="employee_skills.php">
                <i class="fas fa-user-cog"></i> My Skills
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('career_paths.php'); ?>" href="career_paths.php">
                <i class="fas fa-road"></i> Career Path
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_resources.php'); ?>" href="employee_resources.php">
                <i class="fas fa-book-open"></i> Learning Resources
            </a>
        </li>

        <!-- ── Inbox with badge ── -->
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_inbox.php'); ?>" href="employee_inbox.php"
               style="position:relative;">
                <i class="fas fa-inbox"></i> My Inbox
                <?php if ($unreadInboxCount > 0): ?>
                <span class="nav-inbox-badge"><?= $unreadInboxCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <!-- ──────────────────── -->

        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_settings.php'); ?>" href="employee_settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('file_resignation.php'); ?>" href="file_resignation.php">
                <i class="fas fa-sign-out-alt"></i> File Resignation
            </a>
        </li>

        <!-- ── My Survey ── -->
        <?php if ($hasPendingSurvey): ?>
        <li class="nav-item survey-nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_survey_form.php'); ?> survey-nav-link"
               href="employee_survey_form.php">
                <i class="fas fa-clipboard-list"></i>
                My Survey
                <span class="survey-nav-badge">Pending</span>
            </a>
        </li>
        <?php else: ?>
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_survey_form.php'); ?>"
               href="employee_survey_form.php">
                <i class="fas fa-clipboard-list"></i> My Survey
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item mt-4">
            <a class="nav-link text-danger" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>

<style>
.sidebar {
    background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%) !important;
    min-height: 100vh; padding: 20px 0;
    width: 250px; position: fixed;
    left: 0; top: 0; z-index: 1000;
}
.user-profile-section { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sidebar .nav-link {
    transition: all .3s; border-radius: 5px;
    margin: 2px 5px; color: rgba(255,255,255,0.9);
    padding: 12px 15px; display: flex; align-items: center;
}
.sidebar .nav-link:hover { background-color:rgba(255,255,255,0.15); transform:translateX(5px); color:#fff; text-decoration:none; }
.sidebar .nav-link.active { background-color:rgba(255,255,255,0.2); color:#fff; font-weight:600; border-left:4px solid #fff; }
.sidebar .nav-link i { margin-right:10px; width:20px; text-align:center; }

/* ── Bell notification row ── */
.inbox-bell-row {
    margin: 0 10px 14px;
    border-radius: 10px;
    overflow: hidden;
    animation: bellRowPulse 2.5s ease-in-out infinite;
}
@keyframes bellRowPulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(255,255,255,.25); }
    50%      { box-shadow: 0 0 0 5px rgba(255,255,255,.06); }
}
.inbox-bell-link {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 14px;
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.3);
    border-radius: 10px;
    text-decoration: none !important; color: #fff !important;
    font-size: 13px;
    transition: background .25s;
}
.inbox-bell-link:hover { background: rgba(255,255,255,.25); }
.bell-wrapper { position: relative; font-size: 20px; }
.bell-wrapper.has-unread i { animation: bellShake 1.2s ease-in-out infinite; }
@keyframes bellShake {
    0%,100% { transform: rotate(0); }
    15%      { transform: rotate(12deg); }
    30%      { transform: rotate(-10deg); }
    45%      { transform: rotate(8deg); }
    60%      { transform: rotate(-6deg); }
    75%      { transform: rotate(4deg); }
}
.bell-badge {
    position: absolute; top: -6px; right: -8px;
    background: #fff; color: #E91E63;
    font-size: 10px; font-weight: 800;
    width: 18px; height: 18px;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    animation: badgePop .4s cubic-bezier(.68,-.55,.27,1.55);
}
@keyframes badgePop { from{transform:scale(0)} to{transform:scale(1)} }
.bell-text { font-size: 12px; line-height: 1.4; }

/* ── Inbox nav badge ── */
.nav-inbox-badge {
    margin-left: auto;
    background: #fff; color: #E91E63;
    font-size: 10px; font-weight: 800;
    padding: 2px 7px; border-radius: 10px;
    min-width: 20px; text-align: center;
}

/* ── Survey ── */
.survey-nav-item { margin-top: 6px; }
.survey-nav-link { background:rgba(255,255,255,0.12)!important; border:1px solid rgba(255,255,255,0.25)!important; border-radius:8px!important; margin:4px 5px!important; animation:surveyNavPulse 2s ease-in-out infinite; }
.survey-nav-link:hover { background:rgba(255,255,255,0.22)!important; }
@keyframes surveyNavPulse { 0%,100%{box-shadow:0 0 0 0 rgba(255,255,255,0.3)} 50%{box-shadow:0 0 0 4px rgba(255,255,255,0.1)} }
.survey-nav-badge { margin-left:auto; background:white; color:#E91E63; font-size:10px; font-weight:800; padding:2px 8px; border-radius:10px; text-transform:uppercase; letter-spacing:.3px; animation:badgeFade 1.5s ease-in-out infinite; }
@keyframes badgeFade { 0%,100%{opacity:1} 50%{opacity:.6} }

.sidebar .nav-link.text-danger { color:#ffebee!important; border-top:1px solid rgba(255,255,255,0.1); margin-top:20px; padding-top:15px; }
.sidebar .nav-link.text-danger:hover { background-color:rgba(255,255,255,0.1); color:#fff!important; }

@media(max-width:768px){
    .sidebar{min-height:auto;padding:15px 0;width:100%;position:relative;}
    .sidebar .nav-link{padding:10px 15px;font-size:.9rem;}
}
body.employee-page .main-content{margin-left:250px!important;width:calc(100% - 250px)!important;}
@media(max-width:768px){body.employee-page .main-content{margin-left:0!important;width:100%!important;}}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!document.body.classList.contains('employee-page')) return;
    const currentPage = '<?php echo $current_page; ?>';
    document.querySelectorAll('.sidebar .nav-link').forEach(item => {
        if (item.href && item.href.includes(currentPage)) item.classList.add('active');
        item.addEventListener('click', function() {
            document.querySelectorAll('.sidebar .nav-link').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
</script>