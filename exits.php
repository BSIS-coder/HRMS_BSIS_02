<?php
// DEBUG (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection and helper functions
require_once 'dp.php';

// Database connection
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $host = getenv('DB_HOST') ?? 'localhost';
$dbname = getenv('DB_NAME') ?? 'hr_system';
$username = getenv('DB_USER') ?? 'root';
$password = getenv('DB_PASS') ?? '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// ─────────────────────────────────────────────
//  Notification helper — logs to exit_notifications
//  Creates the table automatically if missing.
// ─────────────────────────────────────────────
function ensureNotificationsTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS exit_notifications (
            notification_id   INT AUTO_INCREMENT PRIMARY KEY,
            exit_id           INT            NOT NULL,
            recipient_type    VARCHAR(50)    NOT NULL COMMENT 'employee|supervisor|IT|Finance|Admin|department',
            recipient_label   VARCHAR(255)   NOT NULL,
            subject           VARCHAR(255)   NOT NULL,
            message           TEXT           NOT NULL,
            sent_by           VARCHAR(100)   DEFAULT NULL,
            sent_at           DATETIME       DEFAULT CURRENT_TIMESTAMP,
            status            ENUM('sent','failed','simulated') DEFAULT 'simulated'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

function logNotification(PDO $pdo, int $exitId, string $recipientType, string $recipientLabel, string $subject, string $message): void {
    ensureNotificationsTable($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO exit_notifications (exit_id, recipient_type, recipient_label, subject, message, status)
        VALUES (?, ?, ?, ?, ?, 'simulated')
    ");
    $stmt->execute([$exitId, $recipientType, $recipientLabel, $subject, $message]);
}

// Get messages from session
$message     = $_SESSION['message']      ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// ─────────────────────────────────────────────
//  AJAX handlers
// ─────────────────────────────────────────────
if (isset($_POST['ajax']) && $_POST['ajax'] === 'true') {
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']) {

            // ── Add exit record ──────────────────────────
            case 'add':
                $stmt = $pdo->prepare("
                    INSERT INTO exits (employee_id, exit_type, exit_reason, notice_date, exit_date, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['employee_id'],
                    $_POST['exit_type'],
                    $_POST['exit_reason'],
                    $_POST['notice_date'],
                    $_POST['exit_date'],
                    $_POST['status']
                ]);
                $newId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    SELECT e.*,
                           CONCAT(pi.first_name,' ',pi.last_name) AS employee_name,
                           ep.employee_number,
                           jr.title      AS job_title,
                           jr.department
                    FROM exits e
                    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
                    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                    WHERE e.exit_id = ?
                ");
                $stmt->execute([$newId]);
                $newRecord = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $newRecord]);
                exit;

            // ── Delete exit record ───────────────────────
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM exits WHERE exit_id = ?");
                $stmt->execute([$_POST['exit_id']]);
                echo json_encode(['success' => true, 'exit_id' => $_POST['exit_id']]);
                exit;

            // ── Change status ────────────────────────────
            case 'change_status':
                $stmt = $pdo->prepare("UPDATE exits SET status=? WHERE exit_id=?");
                $stmt->execute([$_POST['new_status'], $_POST['exit_id']]);
                echo json_encode([
                    'success'    => true,
                    'message'    => 'Status updated successfully',
                    'new_status' => $_POST['new_status']
                ]);
                exit;

            // ── Send acknowledgment to employee ──────────
            case 'send_acknowledgment':
                $exitId    = (int) $_POST['exit_id'];
                $subject   = trim($_POST['ack_subject']);
                $msgBody   = trim($_POST['ack_message']);
                $recipient = trim($_POST['recipient_label'] ?? 'Employee');

                // ── Resolve the employee_id tied to this exit ──
                $stmtEmp = $pdo->prepare("
                    SELECT ep.employee_id
                    FROM exits e
                    JOIN employee_profiles ep ON e.employee_id = ep.employee_id
                    WHERE e.exit_id = ?
                    LIMIT 1
                ");
                $stmtEmp->execute([$exitId]);
                $empRow = $stmtEmp->fetch(PDO::FETCH_ASSOC);

                if (!$empRow) {
                    echo json_encode(['success' => false, 'message' => 'Employee not found for this exit record.']);
                    exit;
                }
                $targetEmpId = $empRow['employee_id'];

                // ── Ensure employee_inbox table exists ──
                $pdo->exec("
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

                // ── Write to employee inbox ──
                $pdo->prepare("
                    INSERT INTO employee_inbox (employee_id, exit_id, sender_label, subject, message)
                    VALUES (?, ?, 'HR Department', ?, ?)
                ")->execute([$targetEmpId, $exitId, $subject, $msgBody]);

                // ── Also log to exit_notifications for HR audit trail ──
                logNotification($pdo, $exitId, 'employee', $recipient, $subject, $msgBody);

                // ── Mark ack_sent on exits row ──
                $pdo->prepare("UPDATE exits SET ack_sent=1 WHERE exit_id=?")->execute([$exitId]);

                echo json_encode(['success' => true, 'message' => 'Acknowledgment delivered to employee inbox.']);
                exit;

            // ── Notify department / stakeholders ─────────
            case 'notify_stakeholders':
                $exitId      = (int) $_POST['exit_id'];
                $employeeName = trim($_POST['employee_name'] ?? 'the employee');
                $exitDate    = trim($_POST['exit_date']     ?? '');
                $exitType    = trim($_POST['exit_type']     ?? '');
                $recipients  = json_decode($_POST['recipients'] ?? '[]', true);

                $templateMap = [
                    'department' => [
                        'label'   => $_POST['department'] ?? 'Department',
                        'subject' => "Team Notification: Exit of $employeeName",
                        'body'    => "Dear Team,\n\nThis is to inform you that $employeeName has submitted a $exitType request with an exit date of $exitDate.\n\nPlease make the necessary arrangements for workload transition.\n\nBest regards,\nHR Department"
                    ],
                    'supervisor' => [
                        'label'   => 'Supervisor',
                        'subject' => "Action Required: Exit Request – $employeeName",
                        'body'    => "Dear Supervisor,\n\n$employeeName has filed a $exitType. Exit date: $exitDate.\n\nPlease ensure:\n• Handover tasks are assigned\n• Performance clearance is signed\n• Final evaluation is submitted\n\nKindly coordinate with HR.\n\nBest regards,\nHR Department"
                    ],
                    'IT' => [
                        'label'   => 'IT Department',
                        'subject' => "IT Clearance Required: $employeeName (Exit $exitDate)",
                        'body'    => "Dear IT Team,\n\nPlease initiate offboarding procedures for $employeeName who will be leaving on $exitDate.\n\nAction items:\n• Revoke system/email access on exit date\n• Retrieve company devices & assets\n• Transfer or archive data as per policy\n• Disable VPN / remote access\n\nCoordinate with HR for scheduling.\n\nBest regards,\nHR Department"
                    ],
                    'Finance' => [
                        'label'   => 'Finance Department',
                        'subject' => "Final Pay Processing: $employeeName",
                        'body'    => "Dear Finance Team,\n\nPlease prepare the final pay computation for $employeeName (Exit date: $exitDate).\n\nItems to process:\n• Last salary & prorated leaves\n• Deductions / outstanding loans\n• 13th month pay (if applicable)\n• Separation pay (if applicable)\n\nPlease coordinate with HR for the clearance form.\n\nBest regards,\nHR Department"
                    ],
                    'Admin' => [
                        'label'   => 'Admin / Facilities',
                        'subject' => "Asset Retrieval Notice: $employeeName",
                        'body'    => "Dear Admin Team,\n\nKindly coordinate the return and inventory of assets assigned to $employeeName before or on $exitDate.\n\nAction items:\n• Office keys / access cards\n• ID badges\n• Uniforms / equipment\n• Parking slots / lockers\n\nPlease ensure the clearance form is signed.\n\nBest regards,\nHR Department"
                    ],
                ];

                $logged = [];
                foreach ($recipients as $type) {
                    if (isset($templateMap[$type])) {
                        $t = $templateMap[$type];
                        logNotification($pdo, $exitId, $type, $t['label'], $t['subject'], $t['body']);
                        $logged[] = $type;
                    }
                }

                echo json_encode(['success' => true, 'notified' => $logged]);
                exit;

            // ── Fetch notification history ────────────────
            case 'get_notifications':
                $exitId = (int) $_POST['exit_id'];
                ensureNotificationsTable($pdo);
                $stmt = $pdo->prepare("
                    SELECT * FROM exit_notifications WHERE exit_id = ? ORDER BY sent_at DESC
                ");
                $stmt->execute([$exitId]);
                $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $notifs]);
                exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ─────────────────────────────────────────────
//  Non-AJAX POST (fallback / legacy)
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !isset($_POST['ajax'])) {
    try {
        switch ($_POST['action']) {
            case 'add':
                $stmt = $pdo->prepare("INSERT INTO exits (employee_id, exit_type, exit_reason, notice_date, exit_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['employee_id'], $_POST['exit_type'], $_POST['exit_reason'], $_POST['notice_date'], $_POST['exit_date'], $_POST['status']]);
                $message = "Exit record added successfully!"; $messageType = "success"; break;
            case 'update':
                $stmt = $pdo->prepare("UPDATE exits SET employee_id=?, exit_type=?, exit_reason=?, notice_date=?, exit_date=?, status=? WHERE exit_id=?");
                $stmt->execute([$_POST['employee_id'], $_POST['exit_type'], $_POST['exit_reason'], $_POST['notice_date'], $_POST['exit_date'], $_POST['status'], $_POST['exit_id']]);
                $message = "Exit record updated successfully!"; $messageType = "success"; break;
            case 'delete':
                $pdo->prepare("DELETE FROM exits WHERE exit_id=?")->execute([$_POST['exit_id']]);
                $message = "Exit record deleted successfully!"; $messageType = "success"; break;
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage(); $messageType = "danger";
    }
}

// ─────────────────────────────────────────────
//  Fetch data for page render
// ─────────────────────────────────────────────
// Add ack_sent column if it doesn't exist yet (safe to run every time)
try {
    $pdo->exec("ALTER TABLE exits ADD COLUMN ack_sent TINYINT(1) DEFAULT 0");
} catch (PDOException $e) { /* column already exists, ignore */ }

$exits = $pdo->query("
    SELECT e.*,
           CONCAT(pi.first_name,' ',pi.last_name) AS employee_name,
           ep.employee_number,
           jr.title      AS job_title,
           jr.department
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY e.exit_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$employees = $pdo->query("
    SELECT ep.employee_id, ep.employee_number,
           CONCAT(pi.first_name,' ',pi.last_name) AS full_name
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    WHERE ep.employment_status != 'Terminated'
    ORDER BY pi.first_name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Exit Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
    :root {
        --azure-blue: #E91E63;
        --azure-blue-light: #F06292;
        --azure-blue-dark: #C2185B;
        --azure-blue-lighter: #F8BBD0;
        --azure-blue-pale: #FCE4EC;
    }
    .section-title { color: var(--azure-blue); margin-bottom: 30px; font-weight: 600; }
    .container-fluid { padding: 0; }
    .row { margin-right: 0; margin-left: 0; }
    body { background: var(--azure-blue-pale); }
    .main-content { background: var(--azure-blue-pale); padding: 20px; }
    .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
    .search-box { position: relative; flex: 1; max-width: 400px; }
    .search-box input { width: 100%; padding: 12px 15px 12px 45px; border: 2px solid #e0e0e0; border-radius: 25px; font-size: 16px; transition: all 0.3s ease; }
    .search-box input:focus { border-color: var(--azure-blue); outline: none; box-shadow: 0 0 10px rgba(233,30,99,.3); }
    .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #666; }
    .btn { padding: 12px 25px; border: none; border-radius: 25px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
    .btn-primary { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(233,30,99,.4); }
    .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
    .btn-danger  { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; }
    .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #333; }
    .btn-info    { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; }
    .btn-small   { padding: 7px 13px; font-size: 13px; margin: 2px; }
    .table-container { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,.08); }
    .table { width: 100%; border-collapse: collapse; }
    .table th { background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%); padding: 15px; text-align: left; font-weight: 600; color: var(--azure-blue-dark); border-bottom: 2px solid #dee2e6; }
    .table td { padding: 12px 15px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
    .table tbody tr:hover { background-color: var(--azure-blue-lighter); transition: all 0.2s ease; }
    .status-badge { padding: 4px 11px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #e2e3e5; color: #343a40; display: inline-block; }
    .status-badge.status-pending              { background:#ffc107; color:#000; }
    .status-badge.status-approved             { background:#28a745; color:#fff; }
    .status-badge.status-rejected             { background:#dc3545; color:#fff; }
    .status-badge.status-processing           { background:#17a2b8; color:#fff; }
    .status-badge.status-completed            { background:#6c757d; color:#fff; }
    .status-badge.status-cancelled            { background:#6c757d; color:#fff; }
    .status-badge.status-withdrawn            { background:#6c757d; color:#fff; }
    .status-badge.status-under\.review        { background:#007bff; color:#fff; }
    .status-badge.status-request\.revision    { background:#fd7e14; color:#fff; }
    .status-badge.status-clearance\.ongoing   { background:#20c997; color:#fff; }
    .status-badge.status-exit\.interview\.scheduled { background:#6610f2; color:#fff; }
    .status-badge.status-on\.hold             { background:#e83e8c; color:#fff; }

    /* Notification pills */
    .notif-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; margin: 2px; }
    .notif-pill.ack  { background:#d4edda; color:#155724; }
    .notif-pill.dept { background:#cce5ff; color:#004085; }
    .notif-pill.it   { background:#d1ecf1; color:#0c5460; }
    .notif-pill.fin  { background:#fff3cd; color:#856404; }
    .notif-pill.adm  { background:#f8d7da; color:#721c24; }
    .notif-pill.sup  { background:#e2d9f3; color:#432874; }

    .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.5); backdrop-filter:blur(5px); }
    .modal-content { background:white; margin:4% auto; padding:0; border-radius:15px; width:90%; max-width:620px; max-height:92vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,.3); animation:slideIn .3s ease; }
    @keyframes slideIn { from{transform:translateY(-50px);opacity:0} to{transform:translateY(0);opacity:1} }
    .modal-header { background:linear-gradient(135deg,var(--azure-blue) 0%,var(--azure-blue-light) 100%); color:white; padding:18px 28px; border-radius:15px 15px 0 0; }
    .modal-header h2 { margin:0; font-size:1.25rem; }
    .close { float:right; font-size:26px; font-weight:bold; cursor:pointer; color:white; opacity:.7; }
    .close:hover { opacity:1; }
    .modal-body { padding:28px; }
    .form-group { margin-bottom:18px; }
    .form-group label { display:block; margin-bottom:7px; font-weight:600; color:var(--azure-blue-dark); }
    .form-control { width:100%; padding:9px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:15px; transition:all .3s; box-sizing:border-box; }
    .form-control:focus { border-color:var(--azure-blue); outline:none; box-shadow:0 0 8px rgba(233,30,99,.25); }
    .form-row { display:flex; gap:18px; }
    .form-col { flex:1; }
    .alert { padding:13px 16px; margin-bottom:18px; border:1px solid transparent; border-radius:8px; }
    .alert-success { color:#155724; background:#d4edda; border-color:#c3e6cb; }
    .alert-danger  { color:#721c24; background:#f8d7da; border-color:#f5c6cb; }
    .required { color:#dc3545; }
    .actions-cell { min-width:200px; }

    /* Stakeholder checkboxes */
    .stakeholder-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:8px; }
    .stakeholder-option { display:flex; align-items:center; gap:10px; padding:12px 14px; border:2px solid #e0e0e0; border-radius:10px; cursor:pointer; transition:all .25s; background:#fafafa; }
    .stakeholder-option:hover { border-color:var(--azure-blue); background:var(--azure-blue-pale); }
    .stakeholder-option input[type=checkbox] { width:18px; height:18px; accent-color:var(--azure-blue); cursor:pointer; }
    .stakeholder-option.checked { border-color:var(--azure-blue); background:var(--azure-blue-pale); }
    .stakeholder-icon { font-size:1.4rem; }
    .stakeholder-label { font-weight:600; font-size:14px; color:#333; }
    .stakeholder-sub { font-size:11px; color:#777; }

    /* Notification log table */
    .notif-log-table { width:100%; border-collapse:collapse; font-size:13px; }
    .notif-log-table th { background:var(--azure-blue-lighter); padding:8px 10px; text-align:left; font-weight:600; color:var(--azure-blue-dark); }
    .notif-log-table td { padding:8px 10px; border-bottom:1px solid #f0f0f0; vertical-align:top; }
    .badge-type { display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:700; text-transform:uppercase; }
    .badge-employee  { background:#d4edda; color:#155724; }
    .badge-supervisor{ background:#e2d9f3; color:#432874; }
    .badge-IT        { background:#d1ecf1; color:#0c5460; }
    .badge-Finance   { background:#fff3cd; color:#856404; }
    .badge-Admin     { background:#f8d7da; color:#721c24; }
    .badge-department{ background:#cce5ff; color:#004085; }

    /* Action button group */
    .action-group { display:flex; flex-wrap:wrap; gap:4px; }

    @media(max-width:768px){
        .controls{flex-direction:column;align-items:stretch}
        .search-box{max-width:none}
        .form-row{flex-direction:column}
        .table-container{overflow-x:auto}
        .stakeholder-grid{grid-template-columns:1fr}
    }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <h2 class="section-title"><i class="fas fa-door-open mr-2"></i>Employee Exit Management</h2>
            <div class="content">
                <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>

                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchInput" placeholder="Search exits...">
                    </div>
                </div>

                <div class="table-container">
                    <table class="table" id="exitsTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Exit Type</th>
                                <th>Notice Date</th>
                                <th>Exit Date</th>
                                <th>Status</th>
                                <th>Notifications</th>
                                <th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exits as $exit): 
                                $ackSent = !empty($exit['ack_sent']);
                                $sc = strtolower(str_replace(' ', '.', $exit['status']));
                            ?>
                            <tr data-exit-id="<?= $exit['exit_id'] ?>"
                                data-status="<?= $sc ?>"
                                data-employee="<?= htmlspecialchars($exit['employee_name']) ?>"
                                data-type="<?= htmlspecialchars($exit['exit_type']) ?>">
                                <td>
                                    <strong><?= htmlspecialchars($exit['employee_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($exit['employee_number']) ?></small><br>
                                    <small class="text-muted"><?= htmlspecialchars($exit['department'] ?? '') ?></small>
                                </td>
                                <td><?= htmlspecialchars($exit['exit_type']) ?></td>
                                <td><?= date('M d, Y', strtotime($exit['notice_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($exit['exit_date'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $sc ?>">
                                        <?= htmlspecialchars($exit['status']) ?>
                                    </span>
                                </td>
                                <td id="notif-pills-<?= $exit['exit_id'] ?>">
                                    <?php if ($ackSent): ?>
                                        <span class="notif-pill ack" title="Acknowledgment sent to employee">✉ Acknowledged</span>
                                    <?php else: ?>
                                        <small class="text-muted">No notifications yet</small>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <div class="action-group">
                                        <button class="btn btn-primary btn-small"
                                            onclick="openStatusChangeModal(<?= $exit['exit_id'] ?>, '<?= htmlspecialchars($exit['status'], ENT_QUOTES) ?>')"
                                            title="Change Status">🔄 Status</button>

                                        <button class="btn btn-success btn-small"
                                            onclick="openAckModal(<?= $exit['exit_id'] ?>, '<?= htmlspecialchars($exit['employee_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($exit['exit_type'], ENT_QUOTES) ?>', '<?= date('M d, Y', strtotime($exit['exit_date'])) ?>')"
                                            title="Send Acknowledgment to Employee">✉ Acknowledge</button>

                                        <button class="btn btn-warning btn-small"
                                            onclick="openNotifyModal(<?= $exit['exit_id'] ?>, '<?= htmlspecialchars($exit['employee_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($exit['exit_type'], ENT_QUOTES) ?>', '<?= date('M d, Y', strtotime($exit['exit_date'])) ?>', '<?= htmlspecialchars($exit['department'] ?? '', ENT_QUOTES) ?>')"
                                            title="Notify Stakeholders">📢 Notify</button>

                                        <button class="btn btn-info btn-small"
                                            onclick="viewDetails(<?= $exit['exit_id'] ?>)"
                                            title="View Details">👁 Details</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     STATUS CHANGE MODAL
═══════════════════════════════════════════════════════ -->
<div id="statusChangeModal" class="modal">
    <div class="modal-content" style="max-width:520px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#6610f2,#6f42c1)">
            <h2>Update Exit Status</h2>
            <span class="close" onclick="closeStatusChangeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="statusChangeForm">
                <input type="hidden" id="status_change_exit_id" name="exit_id">
                <div class="form-group">
                    <label>Current Status</label>
                    <input type="text" id="current_status_display" class="form-control" readonly style="background:#f8f9fa;font-weight:600;">
                </div>
                <div class="form-group">
                    <label for="new_status">New Status <span class="required">*</span></label>
                    <select id="new_status" name="new_status" class="form-control" required>
                        <option value="">Select new status...</option>
                        <option>Pending</option><option>Under Review</option><option>Request Revision</option>
                        <option>Approved</option><option>Rejected</option><option>Processing</option>
                        <option>Clearance Ongoing</option><option>Exit Interview Scheduled</option>
                        <option>On Hold</option><option>Withdrawn</option><option>Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status_remarks">Remarks / Comments</label>
                    <textarea id="status_remarks" name="remarks" class="form-control" rows="3" placeholder="Optional notes..."></textarea>
                </div>
                <div style="text-align:center;margin-top:24px;">
                    <button type="submit" class="btn btn-success" style="margin-right:10px;">✓ Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeStatusChangeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     ACKNOWLEDGMENT MODAL
═══════════════════════════════════════════════════════ -->
<div id="ackModal" class="modal">
    <div class="modal-content" style="max-width:580px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#28a745,#20c997)">
            <h2>✉ Send Acknowledgment to Employee</h2>
            <span class="close" onclick="closeAckModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p style="color:#555;margin-bottom:18px;">
                This acknowledgment will be logged and sent to the employee confirming that their exit request has been received and is being processed.
            </p>
            <form id="ackForm">
                <input type="hidden" id="ack_exit_id"       name="exit_id">
                <input type="hidden" id="ack_recipient"     name="recipient_label">
                <div class="form-group">
                    <label>To (Employee)</label>
                    <input type="text" id="ack_to_display" class="form-control" readonly style="background:#f8f9fa;font-weight:600;">
                </div>
                <div class="form-group">
                    <label for="ack_subject">Subject <span class="required">*</span></label>
                    <input type="text" id="ack_subject" name="ack_subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="ack_message">Message <span class="required">*</span></label>
                    <textarea id="ack_message" name="ack_message" class="form-control" rows="7" required></textarea>
                </div>
                <div style="text-align:center;margin-top:22px;">
                    <button type="submit" class="btn btn-success" style="margin-right:10px;">📨 Send Acknowledgment</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAckModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     NOTIFY STAKEHOLDERS MODAL
═══════════════════════════════════════════════════════ -->
<div id="notifyModal" class="modal">
    <div class="modal-content" style="max-width:640px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#ff6f00,#ffa726)">
            <h2>📢 Notify Concerned Parties</h2>
            <span class="close" onclick="closeNotifyModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="notify_exit_id">
            <input type="hidden" id="notify_employee_name">
            <input type="hidden" id="notify_exit_type">
            <input type="hidden" id="notify_exit_date">
            <input type="hidden" id="notify_department">

            <div style="background:#fff8e1;border-left:4px solid #ffa726;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <strong>Notifying for:</strong> <span id="notify_emp_display" style="color:var(--azure-blue);font-weight:700;"></span><br>
                <small id="notify_detail_display" style="color:#666;"></small>
            </div>

            <p style="font-weight:600;color:#444;margin-bottom:10px;">Select recipients to notify:</p>

            <div class="stakeholder-grid" id="stakeholderGrid">
                <label class="stakeholder-option" onclick="toggleStakeholder(this)">
                    <input type="checkbox" name="recipients" value="department">
                    <span class="stakeholder-icon">🏢</span>
                    <div>
                        <div class="stakeholder-label">Department / Team</div>
                        <div class="stakeholder-sub">Inform the employee's team about the upcoming exit</div>
                    </div>
                </label>
                <label class="stakeholder-option" onclick="toggleStakeholder(this)">
                    <input type="checkbox" name="recipients" value="supervisor">
                    <span class="stakeholder-icon">👤</span>
                    <div>
                        <div class="stakeholder-label">Supervisor / Manager</div>
                        <div class="stakeholder-sub">Handover, performance clearance & final evaluation</div>
                    </div>
                </label>
                <label class="stakeholder-option" onclick="toggleStakeholder(this)">
                    <input type="checkbox" name="recipients" value="IT">
                    <span class="stakeholder-icon">💻</span>
                    <div>
                        <div class="stakeholder-label">IT Department</div>
                        <div class="stakeholder-sub">Access revocation, device retrieval, data archiving</div>
                    </div>
                </label>
                <label class="stakeholder-option" onclick="toggleStakeholder(this)">
                    <input type="checkbox" name="recipients" value="Finance">
                    <span class="stakeholder-icon">💰</span>
                    <div>
                        <div class="stakeholder-label">Finance Department</div>
                        <div class="stakeholder-sub">Final pay, deductions, separation pay computation</div>
                    </div>
                </label>
                <label class="stakeholder-option" onclick="toggleStakeholder(this)">
                    <input type="checkbox" name="recipients" value="Admin">
                    <span class="stakeholder-icon">🗂️</span>
                    <div>
                        <div class="stakeholder-label">Admin / Facilities</div>
                        <div class="stakeholder-sub">Asset retrieval, ID badges, keys, equipment</div>
                    </div>
                </label>
            </div>

            <div style="margin-top:18px;display:flex;gap:10px;align-items:center;">
                <button type="button" class="btn btn-secondary btn-small" onclick="selectAllStakeholders()">☑ Select All</button>
                <button type="button" class="btn btn-secondary btn-small" onclick="clearAllStakeholders()">☐ Clear All</button>
                <small style="color:#888;">Auto-generated messages will be created for each selected group.</small>
            </div>

            <!-- Preview section -->
            <div id="previewSection" style="display:none;margin-top:22px;">
                <hr>
                <p style="font-weight:600;color:#444;">Preview of notifications to be sent:</p>
                <div id="previewList"></div>
            </div>

            <div style="text-align:center;margin-top:24px;">
                <button type="button" class="btn btn-warning" style="margin-right:10px;color:#333;" onclick="previewNotifications()">👁 Preview Messages</button>
                <button type="button" class="btn btn-primary" onclick="sendNotifications()">📢 Send Notifications</button>
                <button type="button" class="btn btn-secondary" style="margin-left:10px;" onclick="closeNotifyModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     VIEW DETAILS MODAL
═══════════════════════════════════════════════════════ -->
<div id="detailsModal" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h2>Exit Request Details</h2>
            <span class="close" onclick="closeDetailsModal()">&times;</span>
        </div>
        <div class="modal-body" id="detailsContent"></div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     NOTIFICATION LOG MODAL
═══════════════════════════════════════════════════════ -->
<div id="notifLogModal" class="modal">
    <div class="modal-content" style="max-width:750px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#343a40,#495057)">
            <h2>📋 Notification History</h2>
            <span class="close" onclick="closeNotifLogModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="notifLogContent">Loading...</div>
            <div style="text-align:center;margin-top:20px;">
                <button class="btn btn-secondary" onclick="closeNotifLogModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     DELETE CONFIRMATION MODAL
═══════════════════════════════════════════════════════ -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#dc3545,#c82333)">
            <h2>Confirm Delete</h2>
            <span class="close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="modal-body" style="text-align:center;">
            <p style="font-size:1.05em;margin-bottom:20px;">Are you sure you want to delete this exit record?</p>
            <button class="btn btn-danger" id="confirmDelete" style="margin-right:10px;">Yes, Delete</button>
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
// ─────────────────────────────────────────────
//  Data bootstrap from PHP
// ─────────────────────────────────────────────
let exitsData = <?= json_encode($exits) ?>;

// ─────────────────────────────────────────────
//  Search
// ─────────────────────────────────────────────
document.getElementById('searchInput').addEventListener('keyup', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#exitsTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

// ─────────────────────────────────────────────
//  Utility
// ─────────────────────────────────────────────
function formatDate(ds) {
    if (!ds) return 'N/A';
    const d = new Date(ds);
    const m = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return `${m[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
}

function showAlert(msg, type) {
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    div.innerHTML = msg;
    const c = document.querySelector('.content');
    c.insertBefore(div, c.firstChild);
    setTimeout(() => div.remove(), 6000);
}

function postAjax(body) {
    body.append('ajax', 'true');
    return fetch('exits.php', { method:'POST', body });
}

// ─────────────────────────────────────────────
//  STATUS CHANGE
// ─────────────────────────────────────────────
function openStatusChangeModal(exitId, currentStatus) {
    document.getElementById('status_change_exit_id').value = exitId;
    document.getElementById('current_status_display').value = currentStatus;
    document.getElementById('new_status').value = '';
    document.getElementById('status_remarks').value = '';
    document.getElementById('statusChangeModal').style.display = 'block';
}
function closeStatusChangeModal() { document.getElementById('statusChangeModal').style.display = 'none'; }

document.getElementById('statusChangeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'change_status');
    postAjax(fd).then(r => r.json()).then(data => {
        if (data.success) {
            const exitId  = fd.get('exit_id');
            const newStat = fd.get('new_status');
            const row     = document.querySelector(`tr[data-exit-id="${exitId}"]`);
            if (row) {
                const badge = row.querySelector('.status-badge');
                const sc    = newStat.toLowerCase().replace(/ /g, '.');
                badge.className = `status-badge status-${sc}`;
                badge.textContent = newStat;
                row.setAttribute('data-status', sc);
            }
            const idx = exitsData.findIndex(e => e.exit_id == exitId);
            if (idx !== -1) exitsData[idx].status = newStat;
            closeStatusChangeModal();
            showAlert('✅ Status updated to: <strong>' + newStat + '</strong>', 'success');
        } else {
            showAlert('❌ Error: ' + data.message, 'danger');
        }
    });
});

// ─────────────────────────────────────────────
//  ACKNOWLEDGMENT
// ─────────────────────────────────────────────
function openAckModal(exitId, empName, exitType, exitDate) {
    document.getElementById('ack_exit_id').value     = exitId;
    document.getElementById('ack_recipient').value   = empName;
    document.getElementById('ack_to_display').value  = empName;
    document.getElementById('ack_subject').value     = `Acknowledgment: Your ${exitType} Request Has Been Received`;
    document.getElementById('ack_message').value     =
`Dear ${empName},

We would like to acknowledge that we have received your ${exitType} request with an exit date of ${exitDate}.

Your request is currently being reviewed by the HR Department. We will keep you informed of any updates or next steps.

During this process, please ensure that you:
• Complete all pending tasks and handover responsibilities.
• Coordinate with your supervisor for a smooth transition.
• Return any company property on or before your exit date.
• Complete the clearance process as required.

If you have any concerns or questions, please do not hesitate to reach out to the HR Department.

Thank you for your service and contributions to the company.

Best regards,
HR Department`;
    document.getElementById('ackModal').style.display = 'block';
}
function closeAckModal() { document.getElementById('ackModal').style.display = 'none'; }

document.getElementById('ackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'send_acknowledgment');
    postAjax(fd).then(r => r.json()).then(data => {
        if (data.success) {
            const exitId = fd.get('exit_id');
            const cell = document.getElementById(`notif-pills-${exitId}`);
            if (cell && !cell.querySelector('.ack')) {
                const existing = cell.querySelector('small');
                if (existing) existing.remove();
                const pill = document.createElement('span');
                pill.className = 'notif-pill ack';
                pill.title = 'Acknowledgment sent to employee';
                pill.textContent = '✉ Acknowledged';
                cell.appendChild(pill);
            }
            const idx = exitsData.findIndex(e => e.exit_id == exitId);
            if (idx !== -1) exitsData[idx].ack_sent = 1;
            closeAckModal();
            showAlert('✅ Acknowledgment has been logged and sent to <strong>' + fd.get('recipient_label') + '</strong>.', 'success');
        } else {
            showAlert('❌ Error: ' + data.message, 'danger');
        }
    });
});

// ─────────────────────────────────────────────
//  NOTIFY STAKEHOLDERS
// ─────────────────────────────────────────────
function openNotifyModal(exitId, empName, exitType, exitDate, department) {
    document.getElementById('notify_exit_id').value       = exitId;
    document.getElementById('notify_employee_name').value = empName;
    document.getElementById('notify_exit_type').value     = exitType;
    document.getElementById('notify_exit_date').value     = exitDate;
    document.getElementById('notify_department').value    = department;
    document.getElementById('notify_emp_display').textContent   = empName;
    document.getElementById('notify_detail_display').textContent = `${exitType} • Exit Date: ${exitDate}` + (department ? ` • Dept: ${department}` : '');
    // Clear previous selections
    document.querySelectorAll('#stakeholderGrid input[type=checkbox]').forEach(cb => {
        cb.checked = false;
        cb.closest('.stakeholder-option').classList.remove('checked');
    });
    document.getElementById('previewSection').style.display = 'none';
    document.getElementById('previewList').innerHTML = '';
    document.getElementById('notifyModal').style.display = 'block';
}
function closeNotifyModal() { document.getElementById('notifyModal').style.display = 'none'; }

function toggleStakeholder(label) {
    setTimeout(() => {
        const cb = label.querySelector('input[type=checkbox]');
        label.classList.toggle('checked', cb.checked);
    }, 0);
}
function selectAllStakeholders() {
    document.querySelectorAll('#stakeholderGrid input[type=checkbox]').forEach(cb => {
        cb.checked = true;
        cb.closest('.stakeholder-option').classList.add('checked');
    });
}
function clearAllStakeholders() {
    document.querySelectorAll('#stakeholderGrid input[type=checkbox]').forEach(cb => {
        cb.checked = false;
        cb.closest('.stakeholder-option').classList.remove('checked');
    });
}

const templateMessages = {
    department: (name, type, date, dept) => ({
        label: dept || 'Department',
        subject: `Team Notification: Exit of ${name}`,
        body: `Dear Team,\n\nThis is to inform you that ${name} has submitted a ${type} request with an exit date of ${date}.\n\nPlease make the necessary arrangements for workload transition.\n\nBest regards,\nHR Department`
    }),
    supervisor: (name, type, date) => ({
        label: 'Supervisor / Manager',
        subject: `Action Required: Exit Request – ${name}`,
        body: `Dear Supervisor,\n\n${name} has filed a ${type}. Exit date: ${date}.\n\nPlease ensure:\n• Handover tasks are assigned\n• Performance clearance is signed\n• Final evaluation is submitted\n\nCoordinate with HR.\n\nBest regards,\nHR Department`
    }),
    IT: (name, type, date) => ({
        label: 'IT Department',
        subject: `IT Clearance Required: ${name} (Exit ${date})`,
        body: `Dear IT Team,\n\nPlease initiate offboarding for ${name} who will be leaving on ${date}.\n\nAction items:\n• Revoke system/email access on exit date\n• Retrieve company devices & assets\n• Transfer or archive data as per policy\n• Disable VPN / remote access\n\nBest regards,\nHR Department`
    }),
    Finance: (name, type, date) => ({
        label: 'Finance Department',
        subject: `Final Pay Processing: ${name}`,
        body: `Dear Finance Team,\n\nPlease prepare the final pay computation for ${name} (Exit date: ${date}).\n\nItems to process:\n• Last salary & prorated leaves\n• Deductions / outstanding loans\n• 13th month pay (if applicable)\n• Separation pay (if applicable)\n\nBest regards,\nHR Department`
    }),
    Admin: (name, type, date) => ({
        label: 'Admin / Facilities',
        subject: `Asset Retrieval Notice: ${name}`,
        body: `Dear Admin Team,\n\nPlease coordinate the return of assets assigned to ${name} on or before ${date}.\n\nAction items:\n• Office keys / access cards\n• ID badges\n• Uniforms / equipment\n• Parking slots / lockers\n\nBest regards,\nHR Department`
    }),
};

function getSelectedRecipients() {
    const cbs = document.querySelectorAll('#stakeholderGrid input[type=checkbox]:checked');
    return Array.from(cbs).map(cb => cb.value);
}

function previewNotifications() {
    const recipients = getSelectedRecipients();
    if (!recipients.length) { showAlert('Please select at least one recipient.', 'danger'); return; }

    const name = document.getElementById('notify_employee_name').value;
    const type = document.getElementById('notify_exit_type').value;
    const date = document.getElementById('notify_exit_date').value;
    const dept = document.getElementById('notify_department').value;

    const list = document.getElementById('previewList');
    list.innerHTML = '';

    recipients.forEach(r => {
        const tpl = templateMessages[r](name, type, date, dept);
        const card = document.createElement('div');
        card.style.cssText = 'border:1px solid #e0e0e0;border-radius:10px;padding:14px;margin-bottom:12px;background:#fafafa;';
        card.innerHTML = `
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span class="badge-type badge-${r}">${r === 'department' ? 'Department' : r}</span>
                <strong style="font-size:14px;">${tpl.label}</strong>
            </div>
            <div style="font-size:13px;color:#555;margin-bottom:4px;"><strong>Subject:</strong> ${tpl.subject}</div>
            <div style="font-size:12px;color:#777;white-space:pre-line;background:#fff;border:1px solid #eee;padding:10px;border-radius:6px;max-height:120px;overflow-y:auto;">${tpl.body}</div>
        `;
        list.appendChild(card);
    });

    document.getElementById('previewSection').style.display = 'block';
}

function sendNotifications() {
    const recipients = getSelectedRecipients();
    if (!recipients.length) { showAlert('Please select at least one recipient.', 'danger'); return; }

    const exitId  = document.getElementById('notify_exit_id').value;
    const name    = document.getElementById('notify_employee_name').value;
    const type    = document.getElementById('notify_exit_type').value;
    const date    = document.getElementById('notify_exit_date').value;
    const dept    = document.getElementById('notify_department').value;

    const fd = new FormData();
    fd.append('action', 'notify_stakeholders');
    fd.append('exit_id', exitId);
    fd.append('employee_name', name);
    fd.append('exit_type', type);
    fd.append('exit_date', date);
    fd.append('department', dept);
    fd.append('recipients', JSON.stringify(recipients));

    postAjax(fd).then(r => r.json()).then(data => {
        if (data.success) {
            // Add pills to table row
            const cell = document.getElementById(`notif-pills-${exitId}`);
            const small = cell ? cell.querySelector('small') : null;
            if (small) small.remove();

            const pillMap = { department:'dept', supervisor:'sup', IT:'it', Finance:'fin', Admin:'adm' };
            const labelMap = { department:'🏢 Dept', supervisor:'👤 Supervisor', IT:'💻 IT', Finance:'💰 Finance', Admin:'🗂 Admin' };

            data.notified.forEach(r => {
                if (cell && !cell.querySelector(`.${pillMap[r]}`)) {
                    const pill = document.createElement('span');
                    pill.className = `notif-pill ${pillMap[r]}`;
                    pill.title = `${r} has been notified`;
                    pill.textContent = labelMap[r] || r;
                    cell.appendChild(pill);
                }
            });

            closeNotifyModal();
            showAlert(`✅ Notifications sent to: <strong>${data.notified.join(', ')}</strong>`, 'success');
        } else {
            showAlert('❌ Error: ' + data.message, 'danger');
        }
    });
}

// ─────────────────────────────────────────────
//  VIEW DETAILS
// ─────────────────────────────────────────────
function viewDetails(exitId) {
    const exit = exitsData.find(e => e.exit_id == exitId);
    if (!exit) return;

    const sc = exit.status.toLowerCase().replace(/ /g,'.');
    document.getElementById('detailsContent').innerHTML = `
        <div>
            <h3 style="color:var(--azure-blue);margin-bottom:12px;font-size:1rem;">👤 Employee Information</h3>
            <table style="width:100%;font-size:14px;margin-bottom:20px;">
                <tr><td style="padding:5px 0;font-weight:600;width:140px;">Name</td><td>${exit.employee_name}</td></tr>
                <tr><td style="padding:5px 0;font-weight:600;">Employee #</td><td>${exit.employee_number}</td></tr>
                <tr><td style="padding:5px 0;font-weight:600;">Department</td><td>${exit.department || 'N/A'}</td></tr>
                <tr><td style="padding:5px 0;font-weight:600;">Job Title</td><td>${exit.job_title || 'N/A'}</td></tr>
            </table>

            <h3 style="color:var(--azure-blue);margin-bottom:12px;font-size:1rem;">📋 Exit Details</h3>
            <table style="width:100%;font-size:14px;margin-bottom:20px;">
                <tr><td style="padding:5px 0;font-weight:600;width:140px;">Exit Type</td><td>${exit.exit_type}</td></tr>
                <tr><td style="padding:5px 0;font-weight:600;">Notice Date</td><td>${formatDate(exit.notice_date)}</td></tr>
                <tr><td style="padding:5px 0;font-weight:600;">Exit Date</td><td>${formatDate(exit.exit_date)}</td></tr>
                <tr><td style="padding:5px 0;font-weight:600;">Status</td><td><span class="status-badge status-${sc}">${exit.status}</span></td></tr>
            </table>

            <h3 style="color:var(--azure-blue);margin-bottom:10px;font-size:1rem;">📝 Exit Reason</h3>
            <div style="background:#f8f9fa;padding:14px;border-radius:8px;border-left:4px solid var(--azure-blue);font-size:14px;margin-bottom:20px;">
                ${exit.exit_reason}
            </div>

            <h3 style="color:var(--azure-blue);margin-bottom:10px;font-size:1rem;">📣 Notifications Sent</h3>
            <div id="detailNotifLog" style="font-size:13px;color:#777;">Loading...</div>

            <div style="text-align:center;margin-top:24px;">
                <button class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    `;

    document.getElementById('detailsModal').style.display = 'block';

    // Load notification log
    const fd = new FormData();
    fd.append('action', 'get_notifications');
    fd.append('exit_id', exitId);
    postAjax(fd).then(r => r.json()).then(data => {
        const container = document.getElementById('detailNotifLog');
        if (!container) return;
        if (!data.success || !data.data.length) {
            container.textContent = 'No notifications sent yet.';
            return;
        }
        let html = '<table class="notif-log-table"><thead><tr><th>Type</th><th>Recipient</th><th>Subject</th><th>Sent At</th></tr></thead><tbody>';
        data.data.forEach(n => {
            html += `<tr>
                <td><span class="badge-type badge-${n.recipient_type}">${n.recipient_type}</span></td>
                <td>${n.recipient_label}</td>
                <td>${n.subject}</td>
                <td>${n.sent_at}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    });
}
function closeDetailsModal() { document.getElementById('detailsModal').style.display = 'none'; }

// ─────────────────────────────────────────────
//  NOTIFICATION LOG MODAL (standalone)
// ─────────────────────────────────────────────
function viewNotifLog(exitId) {
    document.getElementById('notifLogModal').style.display = 'block';
    document.getElementById('notifLogContent').innerHTML = 'Loading...';
    const fd = new FormData();
    fd.append('action', 'get_notifications');
    fd.append('exit_id', exitId);
    postAjax(fd).then(r => r.json()).then(data => {
        if (!data.success || !data.data.length) {
            document.getElementById('notifLogContent').innerHTML = '<p style="text-align:center;color:#999;">No notifications recorded for this exit.</p>';
            return;
        }
        let html = '<table class="notif-log-table"><thead><tr><th>Type</th><th>Recipient</th><th>Subject</th><th>Message</th><th>Sent At</th></tr></thead><tbody>';
        data.data.forEach(n => {
            html += `<tr>
                <td><span class="badge-type badge-${n.recipient_type}">${n.recipient_type}</span></td>
                <td>${n.recipient_label}</td>
                <td>${n.subject}</td>
                <td style="max-width:200px;white-space:pre-wrap;font-size:12px;color:#666;">${n.message.substring(0,100)}${n.message.length > 100 ? '…' : ''}</td>
                <td style="white-space:nowrap;">${n.sent_at}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        document.getElementById('notifLogContent').innerHTML = html;
    });
}
function closeNotifLogModal() { document.getElementById('notifLogModal').style.display = 'none'; }

// ─────────────────────────────────────────────
//  DELETE
// ─────────────────────────────────────────────
function deleteExit(exitId) {
    document.getElementById('deleteModal').style.display = 'block';
    document.getElementById('confirmDelete').onclick = function() {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('exit_id', exitId);
        postAjax(fd).then(r => r.json()).then(data => {
            if (data.success) {
                document.querySelector(`tr[data-exit-id="${exitId}"]`)?.remove();
                exitsData = exitsData.filter(e => e.exit_id != exitId);
                closeDeleteModal();
                showAlert('✅ Exit record deleted.', 'success');
            } else {
                showAlert('❌ Error: ' + data.message, 'danger');
            }
        });
    };
}
function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }

// ─────────────────────────────────────────────
//  Close modals on outside click
// ─────────────────────────────────────────────
window.onclick = function(e) {
    ['statusChangeModal','ackModal','notifyModal','detailsModal','deleteModal','notifLogModal']
        .forEach(id => { if (e.target === document.getElementById(id)) document.getElementById(id).style.display = 'none'; });
};

// Prevent form resubmission on refresh
if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
</script>
</body>
</html>