<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php'); exit;
}

$allowed_roles = ['admin', 'hr', 'accounting', 'mayor'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: index.php'); exit;
}

require_once 'config.php';
require_once 'dp.php';

$role    = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// ── Handle approve / reject POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid CSRF token!";
    } else {
        $action      = $_POST['action']      ?? '';
        $approval_id = intval($_POST['approval_id'] ?? 0);
        $remarks     = trim($_POST['remarks'] ?? '');

        if (in_array($action, ['approve', 'reject']) && in_array($role, ['accounting', 'mayor'])) {
            $req = $conn->prepare("SELECT * FROM payroll_approval_requests WHERE approval_id = ?");
            $req->execute([$approval_id]);
            $request = $req->fetch(PDO::FETCH_ASSOC);

            $ok = true;
            if ($role === 'mayor'      && $request['status'] !== 'Accounting_Approved') { $error_message = "Accounting must approve first."; $ok = false; }
            if ($role === 'accounting' && $request['status'] !== 'Pending')             { $error_message = "This request is no longer pending."; $ok = false; }

            if ($ok) {
                $act = ($action === 'approve') ? 'Approved' : 'Rejected';
                $ins = $conn->prepare("INSERT INTO payroll_approval_actions (approval_id, approver_role, approver_user_id, action, remarks) VALUES (?,?,?,?,?)");
                $ins->execute([$approval_id, $role, $user_id, $act, $remarks]);

                if ($action === 'reject') {
                    $new_status = 'Rejected';
                } elseif ($role === 'accounting') {
                    $new_status = 'Accounting_Approved';
                } else {
                    $new_status = 'Fully_Approved';
                    $conn->prepare("UPDATE payroll_transactions SET status='Paid' WHERE payroll_cycle_id=? AND status='Processed'")->execute([$request['payroll_cycle_id']]);
                }
                $conn->prepare("UPDATE payroll_approval_requests SET status=? WHERE approval_id=?")->execute([$new_status, $approval_id]);
                $success_message = ($action === 'approve') ? "Payroll approved successfully!" : "Payroll request rejected.";
            }
        }
    }
}

// ── Fetch stats ───────────────────────────────────────────────────────────────
try {
    // Pending for accounting
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Pending'");
    $pending_accounting = $s->fetchColumn();

    // Pending for mayor
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Accounting_Approved'");
    $pending_mayor = $s->fetchColumn();

    // Fully approved this month
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Fully_Approved' AND MONTH(requested_at)=MONTH(NOW()) AND YEAR(requested_at)=YEAR(NOW())");
    $approved_month = $s->fetchColumn();

    // Rejected total
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Rejected'");
    $total_rejected = $s->fetchColumn();

    // Total net pay approved this month
    $s = $conn->query("SELECT COALESCE(SUM(total_net),0) FROM payroll_approval_requests WHERE status='Fully_Approved' AND MONTH(requested_at)=MONTH(NOW()) AND YEAR(requested_at)=YEAR(NOW())");
    $total_net_approved = $s->fetchColumn();

} catch (PDOException $e) {
    $pending_accounting = $pending_mayor = $approved_month = $total_rejected = $total_net_approved = 0;
}

// ── Fetch requests queue based on role ────────────────────────────────────────
try {
    // For accounting: show Pending
    // For mayor:      show Accounting_Approved
    // For admin/hr:   show all
    if ($role === 'accounting') {
        $filter_status = "ar.status = 'Pending'";
    } elseif ($role === 'mayor') {
        $filter_status = "ar.status = 'Accounting_Approved'";
    } else {
        $filter_status = "ar.status IN ('Pending','Accounting_Approved','Fully_Approved','Rejected')";
    }

    $sql = "SELECT ar.*, pc.cycle_name, pc.pay_period_start, pc.pay_period_end,
                   CONCAT(COALESCE(pi.first_name,''), ' ', COALESCE(pi.last_name,'')) as submitted_by,
                   (SELECT action   FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_action,
                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_remarks,
                   (SELECT CONCAT(COALESCE(pi2.first_name,''),' ',COALESCE(pi2.last_name,'')) FROM payroll_approval_actions paa2 LEFT JOIN users u2 ON paa2.approver_user_id=u2.user_id LEFT JOIN employee_profiles ep2 ON u2.employee_id=ep2.employee_id LEFT JOIN personal_information pi2 ON ep2.personal_info_id=pi2.personal_info_id WHERE paa2.approval_id=ar.approval_id AND paa2.approver_role='accounting' ORDER BY paa2.acted_at DESC LIMIT 1) as acct_by,
                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_date,
                   (SELECT action   FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_action,
                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_remarks,
                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_date
            FROM payroll_approval_requests ar
            JOIN payroll_cycles pc ON ar.payroll_cycle_id = pc.payroll_cycle_id
            LEFT JOIN users u ON ar.requested_by = u.user_id
            LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            WHERE $filter_status
            ORDER BY ar.requested_at DESC";

    $requests = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requests = [];
    $error_message = "Error loading requests: " . $e->getMessage();
}

// ── Recent activity (last 10 actions) ────────────────────────────────────────
try {
    $act_sql = "SELECT paa.*, paa.approver_role, paa.action, paa.remarks, paa.acted_at,
                       pc.cycle_name,
                       CONCAT(COALESCE(pi.first_name,''),' ',COALESCE(pi.last_name,'')) as approver_name
                FROM payroll_approval_actions paa
                JOIN payroll_approval_requests ar ON paa.approval_id = ar.approval_id
                JOIN payroll_cycles pc ON ar.payroll_cycle_id = pc.payroll_cycle_id
                LEFT JOIN users u ON paa.approver_user_id = u.user_id
                LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id
                LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                ORDER BY paa.acted_at DESC LIMIT 10";
    $activity = $conn->query($act_sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activity = [];
}

// Role display label
$role_labels = ['accounting' => 'Accounting Officer', 'mayor' => 'Mayor', 'admin' => 'Administrator', 'hr' => 'HR Officer'];
$role_label  = $role_labels[$role] ?? ucfirst($role);

// What the current user needs to action
$my_queue_count = ($role === 'accounting') ? $pending_accounting : (($role === 'mayor') ? $pending_mayor : ($pending_accounting + $pending_mayor));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Dashboard - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        /* ── Base (identical to payroll_transactions.php) ── */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f5f5;
            margin: 0; padding: 0;
        }
        .sidebar {
            height: 100vh;
            background-color: #E91E63;
            color: #fff;
            padding-top: 20px;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #fff #E91E63;
            z-index: 1030;
        }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: #E91E63; }
        .sidebar::-webkit-scrollbar-thumb { background-color: #fff; border-radius: 3px; }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            margin-bottom: 5px;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover { background-color: rgba(255,255,255,0.1); color: #fff; }
        .sidebar .nav-link.active { background-color: #fff; color: #E91E63; }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content {
            margin-left: 250px;
            padding: 90px 20px 20px;
            transition: margin-left 0.3s;
            width: calc(100% - 250px);
        }
        .top-navbar {
            background: #fff;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(128,0,0,0.1);
            position: fixed;
            top: 0; right: 0; left: 250px;
            z-index: 1020;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            border: none;
            border-radius: 8px;
            overflow: hidden;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(128,0,0,0.1);
            padding: 15px 20px;
            font-weight: bold;
            color: #E91E63;
        }
        .card-header i { color: #E91E63; }
        .card-body { padding: 20px; }
        .table th { border-top: none; color: #E91E63; font-weight: 600; }
        .table td { vertical-align: middle; color: #333; border-color: rgba(128,0,0,0.1); }
        .btn-primary { background-color: #E91E63; border-color: #E91E63; }
        .btn-primary:hover { background-color: #be0945; border-color: #be0945; }
        .section-title { color: #E91E63; margin-bottom: 25px; font-weight: 600; }
        .form-control:focus { border-color: #E91E63; box-shadow: 0 0 0 0.2rem rgba(233,30,99,0.25); }
        .table-sm th, .table-sm td { padding: 0.5rem; font-size: 0.875rem; }

        /* ── Welcome banner ── */
        .welcome-banner {
            background: linear-gradient(135deg, #E91E63 0%, #ad1457 100%);
            border-radius: 8px;
            padding: 22px 28px;
            margin-bottom: 24px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .welcome-banner .wb-left h4 { margin: 0 0 4px; font-weight: 700; font-size: 1.15rem; }
        .welcome-banner .wb-left p  { margin: 0; font-size: .875rem; opacity: .88; }
        .wb-badge {
            background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.35);
            border-radius: 6px;
            padding: 8px 16px;
            text-align: center;
        }
        .wb-badge .wb-num  { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .wb-badge .wb-text { font-size: .72rem; opacity: .85; margin-top: 2px; }

        /* ── Stat cards ── */
        .stat-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width:1100px) { .stat-cards { grid-template-columns: repeat(2,1fr); } }
        @media (max-width:600px)  { .stat-cards { grid-template-columns: 1fr; } }
        .stat-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-left: 4px solid transparent;
        }
        .stat-card.sc-pink   { border-left-color: #E91E63; }
        .stat-card.sc-blue   { border-left-color: #17a2b8; }
        .stat-card.sc-green  { border-left-color: #28a745; }
        .stat-card.sc-red    { border-left-color: #dc3545; }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .sc-pink  .stat-icon { background: #fce4ec; color: #E91E63; }
        .sc-blue  .stat-icon { background: #e3f6f9; color: #17a2b8; }
        .sc-green .stat-icon { background: #e8f5e9; color: #28a745; }
        .sc-red   .stat-icon { background: #ffebee; color: #dc3545; }
        .stat-info .stat-num   { font-size: 1.5rem; font-weight: 700; color: #1a1a2e; line-height: 1; }
        .stat-info .stat-label { font-size: .78rem; color: #888; margin-top: 3px; }

        /* ── Queue cards (for accounting/mayor action items) ── */
        .queue-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            margin-bottom: 14px;
            overflow: hidden;
            border-left: 4px solid #E91E63;
            transition: box-shadow .2s;
        }
        .queue-card:hover { box-shadow: 0 4px 12px rgba(233,30,99,0.12); }
        .queue-card.qc-blue  { border-left-color: #17a2b8; }
        .queue-card.qc-green { border-left-color: #28a745; }
        .queue-card.qc-red   { border-left-color: #dc3545; }
        .qc-body {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .qc-info .qc-title  { font-weight: 700; font-size: .9375rem; color: #1a1a2e; margin-bottom: 3px; }
        .qc-info .qc-period { font-size: .78rem; color: #888; }
        .qc-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .qc-meta-item { text-align: center; }
        .qc-meta-item .qm-val { font-weight: 700; font-size: .9375rem; color: #1a1a2e; }
        .qc-meta-item .qm-lbl { font-size: .72rem; color: #aaa; }
        .qc-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

        /* ── Progress pills ── */
        .p-step {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: .75rem; font-weight: 600;
            padding: 3px 8px; border-radius: 4px; white-space: nowrap;
        }
        .p-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .p-pending  { background: #f5f5f5; color: #666; }
        .p-pending  .p-dot { background: #bbb; }
        .p-approved { background: #e8f5e9; color: #256029; }
        .p-approved .p-dot { background: #43a047; }
        .p-rejected { background: #ffebee; color: #b71c1c; }
        .p-rejected .p-dot { background: #e53935; }
        .p-waiting  { background: #fff8e1; color: #bf360c; }
        .p-waiting  .p-dot { background: #ffa000; }

        /* ── Status badges ── */
        .badge { font-size: .8rem; padding: 5px 10px; font-weight: 600; border-radius: 4px; }
        .badge-pending             { background-color: #ffc107; color: #212529; }
        .badge-accounting_approved { background-color: #17a2b8; color: #fff; }
        .badge-fully_approved      { background-color: #28a745; color: #fff; }
        .badge-rejected            { background-color: #dc3545; color: #fff; }

        /* ── Activity feed ── */
        .activity-feed { display: flex; flex-direction: column; gap: 0; }
        .activity-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(128,0,0,0.06);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-dot {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; flex-shrink: 0; margin-top: 2px;
        }
        .ad-approved { background: #e8f5e9; color: #28a745; }
        .ad-rejected { background: #ffebee; color: #dc3545; }
        .activity-content .ac-title { font-size: .875rem; font-weight: 600; color: #333; margin-bottom: 2px; }
        .activity-content .ac-sub   { font-size: .78rem; color: #888; }
        .activity-content .ac-remark { font-size: .78rem; color: #555; font-style: italic; margin-top: 3px; background: #f9f9f9; padding: 3px 7px; border-radius: 4px; }

        /* ── Role tag ── */
        .role-tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: .75rem; font-weight: 600;
        }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 40px 20px; color: #aaa; }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; color: #ddd; display: block; }
        .empty-state p { font-size: .875rem; margin: 0; }

        /* ── Two-col layout ── */
        .dash-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
        @media (max-width: 1000px) { .dash-grid { grid-template-columns: 1fr; } }

        .btn-action-col { display: flex; flex-direction: column; gap: 4px; }

        /* Urgency pulse for items needing action */
        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0 rgba(233,30,99,0.35); }
            70%  { box-shadow: 0 0 0 8px rgba(233,30,99,0); }
            100% { box-shadow: 0 0 0 0 rgba(233,30,99,0); }
        }
        .needs-action { animation: pulse-ring 2s infinite; }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>
    <div class="row">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">

            <!-- Page title -->
            <h2 class="section-title">
                <i class="fas fa-tachometer-alt mr-2"></i>Approval Dashboard
            </h2>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="wb-left">
                    <h4>
                        <i class="fas fa-user-circle mr-2"></i>
                        Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </h4>
                    <p>
                        <span class="role-tag"><i class="fas fa-id-badge"></i> <?php echo $role_label; ?></span>
                        &nbsp;&nbsp;<?php echo date('l, F d, Y'); ?>
                    </p>
                </div>
                <?php if ($my_queue_count > 0): ?>
                    <div class="wb-badge needs-action">
                        <div class="wb-num"><?php echo $my_queue_count; ?></div>
                        <div class="wb-text">
                            <?php echo $my_queue_count === 1 ? 'Request' : 'Requests'; ?><br>
                            awaiting your action
                        </div>
                    </div>
                <?php else: ?>
                    <div class="wb-badge">
                        <div class="wb-num"><i class="fas fa-check" style="font-size:1.2rem;"></i></div>
                        <div class="wb-text">All clear!<br>Nothing pending</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stat Cards -->
            <div class="stat-cards">
                <div class="stat-card sc-pink">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $pending_accounting; ?></div>
                        <div class="stat-label">Pending Accounting Review</div>
                    </div>
                </div>
                <div class="stat-card sc-blue">
                    <div class="stat-icon"><i class="fas fa-stamp"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $pending_mayor; ?></div>
                        <div class="stat-label">Awaiting Mayor Approval</div>
                    </div>
                </div>
                <div class="stat-card sc-green">
                    <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $approved_month; ?></div>
                        <div class="stat-label">Fully Approved This Month</div>
                    </div>
                </div>
                <div class="stat-card sc-red">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $total_rejected; ?></div>
                        <div class="stat-label">Total Rejected</div>
                    </div>
                </div>
            </div>

            <!-- Two-column layout: Queue + Activity -->
            <div class="dash-grid">

                <!-- LEFT: Action Queue -->
                <div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-inbox mr-2"></i>
                                <?php if ($role === 'accounting'): ?>
                                    Requests Pending Your Review
                                <?php elseif ($role === 'mayor'): ?>
                                    Requests Awaiting Your Approval
                                <?php else: ?>
                                    All Approval Requests
                                <?php endif; ?>
                                <span class="badge badge-<?php echo ($my_queue_count > 0) ? 'pending' : 'fully_approved'; ?> ml-2">
                                    <?php echo count($requests); ?>
                                </span>
                            </span>
                            <a href="payroll_approval.php" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;">
                                <i class="fas fa-external-link-alt mr-1"></i>Full List
                            </a>
                        </div>
                        <div class="card-body" style="padding: 16px 20px;">

                            <?php if (empty($requests)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color:#28a745;"></i>
                                    <p>
                                        <?php if ($role === 'accounting'): ?>
                                            No payroll requests pending your review.
                                        <?php elseif ($role === 'mayor'): ?>
                                            No approved requests awaiting your authorization.
                                        <?php else: ?>
                                            No requests found.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($requests as $req):
                                    $can_act = (
                                        ($role === 'accounting' && $req['status'] === 'Pending') ||
                                        ($role === 'mayor'      && $req['status'] === 'Accounting_Approved')
                                    );

                                    $badges = [
                                        'Pending'             => ['pending',             'Pending'],
                                        'Accounting_Approved' => ['accounting_approved',  'Acctg. Approved'],
                                        'Fully_Approved'      => ['fully_approved',        'Fully Approved'],
                                        'Rejected'            => ['rejected',              'Rejected'],
                                    ];
                                    [$bc, $bl] = $badges[$req['status']] ?? ['pending', $req['status']];

                                    $card_color = '';
                                    if ($req['status'] === 'Accounting_Approved') $card_color = 'qc-blue';
                                    if ($req['status'] === 'Fully_Approved')      $card_color = 'qc-green';
                                    if ($req['status'] === 'Rejected')            $card_color = 'qc-red';
                                ?>
                                <div class="queue-card <?php echo $card_color; ?>">
                                    <div class="qc-body">
                                        <!-- Info -->
                                        <div class="qc-info">
                                            <div class="qc-title">
                                                <?php echo htmlspecialchars($req['cycle_name']); ?>
                                                <span class="badge badge-<?php echo $bc; ?> ml-1" style="font-size:.72rem;"><?php echo $bl; ?></span>
                                            </div>
                                            <div class="qc-period">
                                                <i class="fas fa-calendar-alt mr-1" style="color:#ccc;"></i>
                                                <?php echo date('M d', strtotime($req['pay_period_start'])); ?> –
                                                <?php echo date('M d, Y', strtotime($req['pay_period_end'])); ?>
                                                &nbsp;|&nbsp;
                                                <i class="fas fa-user-clock mr-1" style="color:#ccc;"></i>
                                                Submitted <?php echo date('M d', strtotime($req['requested_at'])); ?>
                                                <?php if ($req['submitted_by']): ?>
                                                    by <?php echo htmlspecialchars(trim($req['submitted_by'])); ?>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Progress pills -->
                                            <div class="mt-2 d-flex gap-1" style="gap:6px; display:flex; flex-wrap:wrap;">
                                                <?php
                                                if ($req['acct_action'] === 'Approved')      { $ac = 'p-approved'; $al = 'Acctg: Approved'; }
                                                elseif ($req['acct_action'] === 'Rejected')  { $ac = 'p-rejected'; $al = 'Acctg: Rejected'; }
                                                else                                          { $ac = 'p-pending';  $al = 'Acctg: Pending';  }

                                                if ($req['mayor_action'] === 'Approved')     { $mc = 'p-approved'; $ml = 'Mayor: Approved'; }
                                                elseif ($req['mayor_action'] === 'Rejected') { $mc = 'p-rejected'; $ml = 'Mayor: Rejected'; }
                                                elseif ($req['acct_action'] === 'Approved')  { $mc = 'p-waiting';  $ml = 'Mayor: Awaiting'; }
                                                else                                          { $mc = 'p-pending';  $ml = 'Mayor: Pending';  }
                                                ?>
                                                <span class="p-step <?php echo $ac; ?>"><span class="p-dot"></span><?php echo $al; ?></span>
                                                <span class="p-step <?php echo $mc; ?>"><span class="p-dot"></span><?php echo $ml; ?></span>
                                            </div>
                                        </div>

                                        <!-- Amounts -->
                                        <div class="qc-meta">
                                            <div class="qc-meta-item">
                                                <div class="qm-val"><?php echo $req['total_employees']; ?></div>
                                                <div class="qm-lbl">Employees</div>
                                            </div>
                                            <div class="qc-meta-item">
                                                <div class="qm-val">₱<?php echo number_format($req['total_gross'], 0); ?></div>
                                                <div class="qm-lbl">Gross Pay</div>
                                            </div>
                                            <div class="qc-meta-item">
                                                <div class="qm-val" style="color:#E91E63;">₱<?php echo number_format($req['total_net'], 0); ?></div>
                                                <div class="qm-lbl">Net Pay</div>
                                            </div>
                                        </div>

                                        <!-- Action buttons -->
                                        <div class="qc-actions">
                                            <div class="btn-action-col">
                                                <!-- View Summary always visible -->
                                                <a href="payroll_summary.php?approval_id=<?php echo $req['approval_id']; ?>"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-file-alt mr-1"></i>View Summary
                                                </a>
                                                <?php if ($can_act): ?>
                                                    <button class="btn btn-sm btn-success"
                                                            onclick="showModal(<?php echo $req['approval_id']; ?>, '<?php echo htmlspecialchars(addslashes($req['cycle_name'])); ?>', 'approve')">
                                                        <i class="fas fa-check mr-1"></i>Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger"
                                                            onclick="showModal(<?php echo $req['approval_id']; ?>, '<?php echo htmlspecialchars(addslashes($req['cycle_name'])); ?>', 'reject')">
                                                        <i class="fas fa-times mr-1"></i>Reject
                                                    </button>
                                                <?php elseif ($req['status'] === 'Fully_Approved'): ?>
                                                    <span class="text-success" style="font-size:.82rem; font-weight:700; display:block; text-align:center; margin-top:2px;">
                                                        <i class="fas fa-check-double mr-1"></i>Released
                                                    </span>
                                                <?php elseif ($req['status'] === 'Rejected'): ?>
                                                    <span class="text-danger" style="font-size:.82rem; font-weight:700; display:block; text-align:center; margin-top:2px;">
                                                        <i class="fas fa-ban mr-1"></i>Rejected
                                                    </span>
                                                <?php elseif ($req['status'] === 'Accounting_Approved' && $role !== 'mayor'): ?>
                                                    <span class="text-info" style="font-size:.82rem; font-weight:600; display:block; text-align:center; margin-top:2px;">
                                                        <i class="fas fa-arrow-right mr-1"></i>With Mayor
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <!-- RIGHT: Recent Activity -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history mr-2"></i>Recent Activity
                        </div>
                        <div class="card-body" style="padding: 8px 20px;">
                            <?php if (empty($activity)): ?>
                                <div class="empty-state" style="padding:30px 10px;">
                                    <i class="fas fa-clock"></i>
                                    <p>No activity yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="activity-feed">
                                    <?php foreach ($activity as $act): ?>
                                    <div class="activity-item">
                                        <div class="activity-dot <?php echo $act['action'] === 'Approved' ? 'ad-approved' : 'ad-rejected'; ?>">
                                            <i class="fas fa-<?php echo $act['action'] === 'Approved' ? 'check' : 'times'; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="ac-title">
                                                <?php echo htmlspecialchars($act['cycle_name']); ?>
                                                <span class="badge badge-<?php echo $act['action'] === 'Approved' ? 'fully_approved' : 'rejected'; ?>" style="font-size:.68rem; padding:2px 7px;">
                                                    <?php echo $act['action']; ?>
                                                </span>
                                            </div>
                                            <div class="ac-sub">
                                                <?php echo ucfirst($act['approver_role']); ?>
                                                <?php if (trim($act['approver_name'])): ?>
                                                    &middot; <?php echo htmlspecialchars(trim($act['approver_name'])); ?>
                                                <?php endif; ?>
                                                &middot; <?php echo date('M d, g:i A', strtotime($act['acted_at'])); ?>
                                            </div>
                                            <?php if ($act['remarks']): ?>
                                                <div class="ac-remark">"<?php echo htmlspecialchars($act['remarks']); ?>"</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-link mr-2"></i>Quick Links
                        </div>
                        <div class="card-body" style="padding:12px 16px;">
                            <a href="payroll_approval.php" class="btn btn-sm btn-primary btn-block mb-2">
                                <i class="fas fa-clipboard-check mr-2"></i>All Approval Requests
                            </a>
                            <a href="payroll_transactions.php" class="btn btn-sm btn-outline-secondary btn-block mb-2">
                                <i class="fas fa-exchange-alt mr-2"></i>Payroll Transactions
                            </a>
                            <a href="payroll_cycles.php" class="btn btn-sm btn-outline-secondary btn-block">
                                <i class="fas fa-calendar-alt mr-2"></i>Payroll Cycles
                            </a>
                        </div>
                    </div>
                </div>

            </div><!-- /dash-grid -->

        </div><!-- /main-content -->
    </div>
</div>

<!-- Approve / Reject Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Approval Action</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="modalAction">
                    <input type="hidden" name="approval_id" id="modalApprovalId">
                    <p id="modalDesc" class="text-muted" style="font-size:.875rem; margin-bottom:16px;"></p>
                    <div class="form-group mb-0">
                        <label style="font-weight:600; font-size:.875rem; color:#333;">Remarks</label>
                        <textarea name="remarks" id="modalRemarks" class="form-control" rows="3"
                                  placeholder="Add your remarks here..."></textarea>
                        <small class="form-text text-muted">Optional for approval. Required when rejecting.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-sm" id="modalSubmitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
function showModal(id, cycleName, action) {
    document.getElementById('modalApprovalId').value = id;
    document.getElementById('modalAction').value     = action;
    document.getElementById('modalRemarks').value    = '';

    const btn   = document.getElementById('modalSubmitBtn');
    const title = document.getElementById('modalTitle');
    const desc  = document.getElementById('modalDesc');

    if (action === 'approve') {
        title.textContent = 'Approve Payroll';
        desc.innerHTML    = 'You are about to <strong>approve</strong> payroll for: <strong>' + cycleName + '</strong>';
        btn.className     = 'btn btn-success btn-sm';
        btn.innerHTML     = '<i class="fas fa-check mr-1"></i>Approve';
    } else {
        title.textContent = 'Reject Payroll';
        desc.innerHTML    = 'You are about to <strong>reject</strong> payroll for: <strong>' + cycleName + '</strong>.<br><small class="text-danger">Please provide a reason below.</small>';
        btn.className     = 'btn btn-danger btn-sm';
        btn.innerHTML     = '<i class="fas fa-times mr-1"></i>Reject';
    }

    $('#approvalModal').modal('show');
}
</script>

</body>
</html><?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php'); exit;
}

$allowed_roles = ['admin', 'hr', 'accounting', 'mayor'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: index.php'); exit;
}

require_once 'config.php';
require_once 'dp.php';

$role    = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// ── Handle approve / reject POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid CSRF token!";
    } else {
        $action      = $_POST['action']      ?? '';
        $approval_id = intval($_POST['approval_id'] ?? 0);
        $remarks     = trim($_POST['remarks'] ?? '');

        if (in_array($action, ['approve', 'reject']) && in_array($role, ['accounting', 'mayor'])) {
            $req = $conn->prepare("SELECT * FROM payroll_approval_requests WHERE approval_id = ?");
            $req->execute([$approval_id]);
            $request = $req->fetch(PDO::FETCH_ASSOC);

            $ok = true;
            if ($role === 'mayor'      && $request['status'] !== 'Accounting_Approved') { $error_message = "Accounting must approve first."; $ok = false; }
            if ($role === 'accounting' && $request['status'] !== 'Pending')             { $error_message = "This request is no longer pending."; $ok = false; }

            if ($ok) {
                $act = ($action === 'approve') ? 'Approved' : 'Rejected';
                $ins = $conn->prepare("INSERT INTO payroll_approval_actions (approval_id, approver_role, approver_user_id, action, remarks) VALUES (?,?,?,?,?)");
                $ins->execute([$approval_id, $role, $user_id, $act, $remarks]);

                if ($action === 'reject') {
                    $new_status = 'Rejected';
                } elseif ($role === 'accounting') {
                    $new_status = 'Accounting_Approved';
                } else {
                    $new_status = 'Fully_Approved';
                    $conn->prepare("UPDATE payroll_transactions SET status='Paid' WHERE payroll_cycle_id=? AND status='Processed'")->execute([$request['payroll_cycle_id']]);
                }
                $conn->prepare("UPDATE payroll_approval_requests SET status=? WHERE approval_id=?")->execute([$new_status, $approval_id]);
                $success_message = ($action === 'approve') ? "Payroll approved successfully!" : "Payroll request rejected.";
            }
        }
    }
}

// ── Fetch stats ───────────────────────────────────────────────────────────────
try {
    // Pending for accounting
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Pending'");
    $pending_accounting = $s->fetchColumn();

    // Pending for mayor
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Accounting_Approved'");
    $pending_mayor = $s->fetchColumn();

    // Fully approved this month
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Fully_Approved' AND MONTH(requested_at)=MONTH(NOW()) AND YEAR(requested_at)=YEAR(NOW())");
    $approved_month = $s->fetchColumn();

    // Rejected total
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Rejected'");
    $total_rejected = $s->fetchColumn();

    // Total net pay approved this month
    $s = $conn->query("SELECT COALESCE(SUM(total_net),0) FROM payroll_approval_requests WHERE status='Fully_Approved' AND MONTH(requested_at)=MONTH(NOW()) AND YEAR(requested_at)=YEAR(NOW())");
    $total_net_approved = $s->fetchColumn();

} catch (PDOException $e) {
    $pending_accounting = $pending_mayor = $approved_month = $total_rejected = $total_net_approved = 0;
}

// ── Fetch requests queue based on role ────────────────────────────────────────
try {
    // For accounting: show Pending
    // For mayor:      show Accounting_Approved
    // For admin/hr:   show all
    if ($role === 'accounting') {
        $filter_status = "ar.status = 'Pending'";
    } elseif ($role === 'mayor') {
        $filter_status = "ar.status = 'Accounting_Approved'";
    } else {
        $filter_status = "ar.status IN ('Pending','Accounting_Approved','Fully_Approved','Rejected')";
    }

    $sql = "SELECT ar.*, pc.cycle_name, pc.pay_period_start, pc.pay_period_end,
                   CONCAT(COALESCE(pi.first_name,''), ' ', COALESCE(pi.last_name,'')) as submitted_by,
                   (SELECT action   FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_action,
                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_remarks,
                   (SELECT CONCAT(COALESCE(pi2.first_name,''),' ',COALESCE(pi2.last_name,'')) FROM payroll_approval_actions paa2 LEFT JOIN users u2 ON paa2.approver_user_id=u2.user_id LEFT JOIN employee_profiles ep2 ON u2.employee_id=ep2.employee_id LEFT JOIN personal_information pi2 ON ep2.personal_info_id=pi2.personal_info_id WHERE paa2.approval_id=ar.approval_id AND paa2.approver_role='accounting' ORDER BY paa2.acted_at DESC LIMIT 1) as acct_by,
                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_date,
                   (SELECT action   FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_action,
                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_remarks,
                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_date
            FROM payroll_approval_requests ar
            JOIN payroll_cycles pc ON ar.payroll_cycle_id = pc.payroll_cycle_id
            LEFT JOIN users u ON ar.requested_by = u.user_id
            LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            WHERE $filter_status
            ORDER BY ar.requested_at DESC";

    $requests = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requests = [];
    $error_message = "Error loading requests: " . $e->getMessage();
}

// ── Recent activity (last 10 actions) ────────────────────────────────────────
try {
    $act_sql = "SELECT paa.*, paa.approver_role, paa.action, paa.remarks, paa.acted_at,
                       pc.cycle_name,
                       CONCAT(COALESCE(pi.first_name,''),' ',COALESCE(pi.last_name,'')) as approver_name
                FROM payroll_approval_actions paa
                JOIN payroll_approval_requests ar ON paa.approval_id = ar.approval_id
                JOIN payroll_cycles pc ON ar.payroll_cycle_id = pc.payroll_cycle_id
                LEFT JOIN users u ON paa.approver_user_id = u.user_id
                LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id
                LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                ORDER BY paa.acted_at DESC LIMIT 10";
    $activity = $conn->query($act_sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activity = [];
}

// Role display label
$role_labels = ['accounting' => 'Accounting Officer', 'mayor' => 'Mayor', 'admin' => 'Administrator', 'hr' => 'HR Officer'];
$role_label  = $role_labels[$role] ?? ucfirst($role);

// What the current user needs to action
$my_queue_count = ($role === 'accounting') ? $pending_accounting : (($role === 'mayor') ? $pending_mayor : ($pending_accounting + $pending_mayor));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Dashboard - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        /* ── Base (identical to payroll_transactions.php) ── */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f5f5;
            margin: 0; padding: 0;
        }
        .sidebar {
            height: 100vh;
            background-color: #E91E63;
            color: #fff;
            padding-top: 20px;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #fff #E91E63;
            z-index: 1030;
        }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: #E91E63; }
        .sidebar::-webkit-scrollbar-thumb { background-color: #fff; border-radius: 3px; }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            margin-bottom: 5px;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover { background-color: rgba(255,255,255,0.1); color: #fff; }
        .sidebar .nav-link.active { background-color: #fff; color: #E91E63; }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content {
            margin-left: 250px;
            padding: 90px 20px 20px;
            transition: margin-left 0.3s;
            width: calc(100% - 250px);
        }
        .top-navbar {
            background: #fff;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(128,0,0,0.1);
            position: fixed;
            top: 0; right: 0; left: 250px;
            z-index: 1020;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            border: none;
            border-radius: 8px;
            overflow: hidden;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(128,0,0,0.1);
            padding: 15px 20px;
            font-weight: bold;
            color: #E91E63;
        }
        .card-header i { color: #E91E63; }
        .card-body { padding: 20px; }
        .table th { border-top: none; color: #E91E63; font-weight: 600; }
        .table td { vertical-align: middle; color: #333; border-color: rgba(128,0,0,0.1); }
        .btn-primary { background-color: #E91E63; border-color: #E91E63; }
        .btn-primary:hover { background-color: #be0945; border-color: #be0945; }
        .section-title { color: #E91E63; margin-bottom: 25px; font-weight: 600; }
        .form-control:focus { border-color: #E91E63; box-shadow: 0 0 0 0.2rem rgba(233,30,99,0.25); }
        .table-sm th, .table-sm td { padding: 0.5rem; font-size: 0.875rem; }

        /* ── Welcome banner ── */
        .welcome-banner {
            background: linear-gradient(135deg, #E91E63 0%, #ad1457 100%);
            border-radius: 8px;
            padding: 22px 28px;
            margin-bottom: 24px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .welcome-banner .wb-left h4 { margin: 0 0 4px; font-weight: 700; font-size: 1.15rem; }
        .welcome-banner .wb-left p  { margin: 0; font-size: .875rem; opacity: .88; }
        .wb-badge {
            background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.35);
            border-radius: 6px;
            padding: 8px 16px;
            text-align: center;
        }
        .wb-badge .wb-num  { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .wb-badge .wb-text { font-size: .72rem; opacity: .85; margin-top: 2px; }

        /* ── Stat cards ── */
        .stat-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width:1100px) { .stat-cards { grid-template-columns: repeat(2,1fr); } }
        @media (max-width:600px)  { .stat-cards { grid-template-columns: 1fr; } }
        .stat-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-left: 4px solid transparent;
        }
        .stat-card.sc-pink   { border-left-color: #E91E63; }
        .stat-card.sc-blue   { border-left-color: #17a2b8; }
        .stat-card.sc-green  { border-left-color: #28a745; }
        .stat-card.sc-red    { border-left-color: #dc3545; }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .sc-pink  .stat-icon { background: #fce4ec; color: #E91E63; }
        .sc-blue  .stat-icon { background: #e3f6f9; color: #17a2b8; }
        .sc-green .stat-icon { background: #e8f5e9; color: #28a745; }
        .sc-red   .stat-icon { background: #ffebee; color: #dc3545; }
        .stat-info .stat-num   { font-size: 1.5rem; font-weight: 700; color: #1a1a2e; line-height: 1; }
        .stat-info .stat-label { font-size: .78rem; color: #888; margin-top: 3px; }

        /* ── Queue cards (for accounting/mayor action items) ── */
        .queue-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            margin-bottom: 14px;
            overflow: hidden;
            border-left: 4px solid #E91E63;
            transition: box-shadow .2s;
        }
        .queue-card:hover { box-shadow: 0 4px 12px rgba(233,30,99,0.12); }
        .queue-card.qc-blue  { border-left-color: #17a2b8; }
        .queue-card.qc-green { border-left-color: #28a745; }
        .queue-card.qc-red   { border-left-color: #dc3545; }
        .qc-body {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .qc-info .qc-title  { font-weight: 700; font-size: .9375rem; color: #1a1a2e; margin-bottom: 3px; }
        .qc-info .qc-period { font-size: .78rem; color: #888; }
        .qc-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .qc-meta-item { text-align: center; }
        .qc-meta-item .qm-val { font-weight: 700; font-size: .9375rem; color: #1a1a2e; }
        .qc-meta-item .qm-lbl { font-size: .72rem; color: #aaa; }
        .qc-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

        /* ── Progress pills ── */
        .p-step {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: .75rem; font-weight: 600;
            padding: 3px 8px; border-radius: 4px; white-space: nowrap;
        }
        .p-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .p-pending  { background: #f5f5f5; color: #666; }
        .p-pending  .p-dot { background: #bbb; }
        .p-approved { background: #e8f5e9; color: #256029; }
        .p-approved .p-dot { background: #43a047; }
        .p-rejected { background: #ffebee; color: #b71c1c; }
        .p-rejected .p-dot { background: #e53935; }
        .p-waiting  { background: #fff8e1; color: #bf360c; }
        .p-waiting  .p-dot { background: #ffa000; }

        /* ── Status badges ── */
        .badge { font-size: .8rem; padding: 5px 10px; font-weight: 600; border-radius: 4px; }
        .badge-pending             { background-color: #ffc107; color: #212529; }
        .badge-accounting_approved { background-color: #17a2b8; color: #fff; }
        .badge-fully_approved      { background-color: #28a745; color: #fff; }
        .badge-rejected            { background-color: #dc3545; color: #fff; }

        /* ── Activity feed ── */
        .activity-feed { display: flex; flex-direction: column; gap: 0; }
        .activity-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(128,0,0,0.06);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-dot {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; flex-shrink: 0; margin-top: 2px;
        }
        .ad-approved { background: #e8f5e9; color: #28a745; }
        .ad-rejected { background: #ffebee; color: #dc3545; }
        .activity-content .ac-title { font-size: .875rem; font-weight: 600; color: #333; margin-bottom: 2px; }
        .activity-content .ac-sub   { font-size: .78rem; color: #888; }
        .activity-content .ac-remark { font-size: .78rem; color: #555; font-style: italic; margin-top: 3px; background: #f9f9f9; padding: 3px 7px; border-radius: 4px; }

        /* ── Role tag ── */
        .role-tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: .75rem; font-weight: 600;
        }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 40px 20px; color: #aaa; }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; color: #ddd; display: block; }
        .empty-state p { font-size: .875rem; margin: 0; }

        /* ── Two-col layout ── */
        .dash-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
        @media (max-width: 1000px) { .dash-grid { grid-template-columns: 1fr; } }

        .btn-action-col { display: flex; flex-direction: column; gap: 4px; }

        /* Urgency pulse for items needing action */
        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0 rgba(233,30,99,0.35); }
            70%  { box-shadow: 0 0 0 8px rgba(233,30,99,0); }
            100% { box-shadow: 0 0 0 0 rgba(233,30,99,0); }
        }
        .needs-action { animation: pulse-ring 2s infinite; }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>
    <div class="row">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">

            <!-- Page title -->
            <h2 class="section-title">
                <i class="fas fa-tachometer-alt mr-2"></i>Approval Dashboard
            </h2>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="wb-left">
                    <h4>
                        <i class="fas fa-user-circle mr-2"></i>
                        Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </h4>
                    <p>
                        <span class="role-tag"><i class="fas fa-id-badge"></i> <?php echo $role_label; ?></span>
                        &nbsp;&nbsp;<?php echo date('l, F d, Y'); ?>
                    </p>
                </div>
                <?php if ($my_queue_count > 0): ?>
                    <div class="wb-badge needs-action">
                        <div class="wb-num"><?php echo $my_queue_count; ?></div>
                        <div class="wb-text">
                            <?php echo $my_queue_count === 1 ? 'Request' : 'Requests'; ?><br>
                            awaiting your action
                        </div>
                    </div>
                <?php else: ?>
                    <div class="wb-badge">
                        <div class="wb-num"><i class="fas fa-check" style="font-size:1.2rem;"></i></div>
                        <div class="wb-text">All clear!<br>Nothing pending</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stat Cards -->
            <div class="stat-cards">
                <div class="stat-card sc-pink">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $pending_accounting; ?></div>
                        <div class="stat-label">Pending Accounting Review</div>
                    </div>
                </div>
                <div class="stat-card sc-blue">
                    <div class="stat-icon"><i class="fas fa-stamp"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $pending_mayor; ?></div>
                        <div class="stat-label">Awaiting Mayor Approval</div>
                    </div>
                </div>
                <div class="stat-card sc-green">
                    <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $approved_month; ?></div>
                        <div class="stat-label">Fully Approved This Month</div>
                    </div>
                </div>
                <div class="stat-card sc-red">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $total_rejected; ?></div>
                        <div class="stat-label">Total Rejected</div>
                    </div>
                </div>
            </div>

            <!-- Two-column layout: Queue + Activity -->
            <div class="dash-grid">

                <!-- LEFT: Action Queue -->
                <div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-inbox mr-2"></i>
                                <?php if ($role === 'accounting'): ?>
                                    Requests Pending Your Review
                                <?php elseif ($role === 'mayor'): ?>
                                    Requests Awaiting Your Approval
                                <?php else: ?>
                                    All Approval Requests
                                <?php endif; ?>
                                <span class="badge badge-<?php echo ($my_queue_count > 0) ? 'pending' : 'fully_approved'; ?> ml-2">
                                    <?php echo count($requests); ?>
                                </span>
                            </span>
                            <a href="payroll_approval.php" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;">
                                <i class="fas fa-external-link-alt mr-1"></i>Full List
                            </a>
                        </div>
                        <div class="card-body" style="padding: 16px 20px;">

                            <?php if (empty($requests)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color:#28a745;"></i>
                                    <p>
                                        <?php if ($role === 'accounting'): ?>
                                            No payroll requests pending your review.
                                        <?php elseif ($role === 'mayor'): ?>
                                            No approved requests awaiting your authorization.
                                        <?php else: ?>
                                            No requests found.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($requests as $req):
                                    $can_act = (
                                        ($role === 'accounting' && $req['status'] === 'Pending') ||
                                        ($role === 'mayor'      && $req['status'] === 'Accounting_Approved')
                                    );

                                    $badges = [
                                        'Pending'             => ['pending',             'Pending'],
                                        'Accounting_Approved' => ['accounting_approved',  'Acctg. Approved'],
                                        'Fully_Approved'      => ['fully_approved',        'Fully Approved'],
                                        'Rejected'            => ['rejected',              'Rejected'],
                                    ];
                                    [$bc, $bl] = $badges[$req['status']] ?? ['pending', $req['status']];

                                    $card_color = '';
                                    if ($req['status'] === 'Accounting_Approved') $card_color = 'qc-blue';
                                    if ($req['status'] === 'Fully_Approved')      $card_color = 'qc-green';
                                    if ($req['status'] === 'Rejected')            $card_color = 'qc-red';
                                ?>
                                <div class="queue-card <?php echo $card_color; ?>">
                                    <div class="qc-body">
                                        <!-- Info -->
                                        <div class="qc-info">
                                            <div class="qc-title">
                                                <?php echo htmlspecialchars($req['cycle_name']); ?>
                                                <span class="badge badge-<?php echo $bc; ?> ml-1" style="font-size:.72rem;"><?php echo $bl; ?></span>
                                            </div>
                                            <div class="qc-period">
                                                <i class="fas fa-calendar-alt mr-1" style="color:#ccc;"></i>
                                                <?php echo date('M d', strtotime($req['pay_period_start'])); ?> –
                                                <?php echo date('M d, Y', strtotime($req['pay_period_end'])); ?>
                                                &nbsp;|&nbsp;
                                                <i class="fas fa-user-clock mr-1" style="color:#ccc;"></i>
                                                Submitted <?php echo date('M d', strtotime($req['requested_at'])); ?>
                                                <?php if ($req['submitted_by']): ?>
                                                    by <?php echo htmlspecialchars(trim($req['submitted_by'])); ?>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Progress pills -->
                                            <div class="mt-2 d-flex gap-1" style="gap:6px; display:flex; flex-wrap:wrap;">
                                                <?php
                                                if ($req['acct_action'] === 'Approved')      { $ac = 'p-approved'; $al = 'Acctg: Approved'; }
                                                elseif ($req['acct_action'] === 'Rejected')  { $ac = 'p-rejected'; $al = 'Acctg: Rejected'; }
                                                else                                          { $ac = 'p-pending';  $al = 'Acctg: Pending';  }

                                                if ($req['mayor_action'] === 'Approved')     { $mc = 'p-approved'; $ml = 'Mayor: Approved'; }
                                                elseif ($req['mayor_action'] === 'Rejected') { $mc = 'p-rejected'; $ml = 'Mayor: Rejected'; }
                                                elseif ($req['acct_action'] === 'Approved')  { $mc = 'p-waiting';  $ml = 'Mayor: Awaiting'; }
                                                else                                          { $mc = 'p-pending';  $ml = 'Mayor: Pending';  }
                                                ?>
                                                <span class="p-step <?php echo $ac; ?>"><span class="p-dot"></span><?php echo $al; ?></span>
                                                <span class="p-step <?php echo $mc; ?>"><span class="p-dot"></span><?php echo $ml; ?></span>
                                            </div>
                                        </div>

                                        <!-- Amounts -->
                                        <div class="qc-meta">
                                            <div class="qc-meta-item">
                                                <div class="qm-val"><?php echo $req['total_employees']; ?></div>
                                                <div class="qm-lbl">Employees</div>
                                            </div>
                                            <div class="qc-meta-item">
                                                <div class="qm-val">₱<?php echo number_format($req['total_gross'], 0); ?></div>
                                                <div class="qm-lbl">Gross Pay</div>
                                            </div>
                                            <div class="qc-meta-item">
                                                <div class="qm-val" style="color:#E91E63;">₱<?php echo number_format($req['total_net'], 0); ?></div>
                                                <div class="qm-lbl">Net Pay</div>
                                            </div>
                                        </div>

                                        <!-- Action buttons -->
                                        <div class="qc-actions">
                                            <div class="btn-action-col">
                                                <!-- View Summary always visible -->
                                                <a href="payroll_summary.php?approval_id=<?php echo $req['approval_id']; ?>"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-file-alt mr-1"></i>View Summary
                                                </a>
                                                <?php if ($can_act): ?>
                                                    <button class="btn btn-sm btn-success"
                                                            onclick="showModal(<?php echo $req['approval_id']; ?>, '<?php echo htmlspecialchars(addslashes($req['cycle_name'])); ?>', 'approve')">
                                                        <i class="fas fa-check mr-1"></i>Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger"
                                                            onclick="showModal(<?php echo $req['approval_id']; ?>, '<?php echo htmlspecialchars(addslashes($req['cycle_name'])); ?>', 'reject')">
                                                        <i class="fas fa-times mr-1"></i>Reject
                                                    </button>
                                                <?php elseif ($req['status'] === 'Fully_Approved'): ?>
                                                    <span class="text-success" style="font-size:.82rem; font-weight:700; display:block; text-align:center; margin-top:2px;">
                                                        <i class="fas fa-check-double mr-1"></i>Released
                                                    </span>
                                                <?php elseif ($req['status'] === 'Rejected'): ?>
                                                    <span class="text-danger" style="font-size:.82rem; font-weight:700; display:block; text-align:center; margin-top:2px;">
                                                        <i class="fas fa-ban mr-1"></i>Rejected
                                                    </span>
                                                <?php elseif ($req['status'] === 'Accounting_Approved' && $role !== 'mayor'): ?>
                                                    <span class="text-info" style="font-size:.82rem; font-weight:600; display:block; text-align:center; margin-top:2px;">
                                                        <i class="fas fa-arrow-right mr-1"></i>With Mayor
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <!-- RIGHT: Recent Activity -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history mr-2"></i>Recent Activity
                        </div>
                        <div class="card-body" style="padding: 8px 20px;">
                            <?php if (empty($activity)): ?>
                                <div class="empty-state" style="padding:30px 10px;">
                                    <i class="fas fa-clock"></i>
                                    <p>No activity yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="activity-feed">
                                    <?php foreach ($activity as $act): ?>
                                    <div class="activity-item">
                                        <div class="activity-dot <?php echo $act['action'] === 'Approved' ? 'ad-approved' : 'ad-rejected'; ?>">
                                            <i class="fas fa-<?php echo $act['action'] === 'Approved' ? 'check' : 'times'; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="ac-title">
                                                <?php echo htmlspecialchars($act['cycle_name']); ?>
                                                <span class="badge badge-<?php echo $act['action'] === 'Approved' ? 'fully_approved' : 'rejected'; ?>" style="font-size:.68rem; padding:2px 7px;">
                                                    <?php echo $act['action']; ?>
                                                </span>
                                            </div>
                                            <div class="ac-sub">
                                                <?php echo ucfirst($act['approver_role']); ?>
                                                <?php if (trim($act['approver_name'])): ?>
                                                    &middot; <?php echo htmlspecialchars(trim($act['approver_name'])); ?>
                                                <?php endif; ?>
                                                &middot; <?php echo date('M d, g:i A', strtotime($act['acted_at'])); ?>
                                            </div>
                                            <?php if ($act['remarks']): ?>
                                                <div class="ac-remark">"<?php echo htmlspecialchars($act['remarks']); ?>"</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-link mr-2"></i>Quick Links
                        </div>
                        <div class="card-body" style="padding:12px 16px;">
                            <a href="payroll_approval.php" class="btn btn-sm btn-primary btn-block mb-2">
                                <i class="fas fa-clipboard-check mr-2"></i>All Approval Requests
                            </a>
                            <a href="payroll_transactions.php" class="btn btn-sm btn-outline-secondary btn-block mb-2">
                                <i class="fas fa-exchange-alt mr-2"></i>Payroll Transactions
                            </a>
                            <a href="payroll_cycles.php" class="btn btn-sm btn-outline-secondary btn-block">
                                <i class="fas fa-calendar-alt mr-2"></i>Payroll Cycles
                            </a>
                        </div>
                    </div>
                </div>

            </div><!-- /dash-grid -->

        </div><!-- /main-content -->
    </div>
</div>

<!-- Approve / Reject Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Approval Action</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="modalAction">
                    <input type="hidden" name="approval_id" id="modalApprovalId">
                    <p id="modalDesc" class="text-muted" style="font-size:.875rem; margin-bottom:16px;"></p>
                    <div class="form-group mb-0">
                        <label style="font-weight:600; font-size:.875rem; color:#333;">Remarks</label>
                        <textarea name="remarks" id="modalRemarks" class="form-control" rows="3"
                                  placeholder="Add your remarks here..."></textarea>
                        <small class="form-text text-muted">Optional for approval. Required when rejecting.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-sm" id="modalSubmitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
function showModal(id, cycleName, action) {
    document.getElementById('modalApprovalId').value = id;
    document.getElementById('modalAction').value     = action;
    document.getElementById('modalRemarks').value    = '';

    const btn   = document.getElementById('modalSubmitBtn');
    const title = document.getElementById('modalTitle');
    const desc  = document.getElementById('modalDesc');

    if (action === 'approve') {
        title.textContent = 'Approve Payroll';
        desc.innerHTML    = 'You are about to <strong>approve</strong> payroll for: <strong>' + cycleName + '</strong>';
        btn.className     = 'btn btn-success btn-sm';
        btn.innerHTML     = '<i class="fas fa-check mr-1"></i>Approve';
    } else {
        title.textContent = 'Reject Payroll';
        desc.innerHTML    = 'You are about to <strong>reject</strong> payroll for: <strong>' + cycleName + '</strong>.<br><small class="text-danger">Please provide a reason below.</small>';
        btn.className     = 'btn btn-danger btn-sm';
        btn.innerHTML     = '<i class="fas fa-times mr-1"></i>Reject';
    }

    $('#approvalModal').modal('show');
}
</script>

</body>
</html><?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php'); exit;
}

$allowed_roles = ['admin', 'hr', 'accounting', 'mayor'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: index.php'); exit;
}

require_once 'config.php';
require_once 'dp.php';

$role    = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// ── Handle approve / reject POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid CSRF token!";
    } else {
        $action      = $_POST['action']      ?? '';
        $approval_id = intval($_POST['approval_id'] ?? 0);
        $remarks     = trim($_POST['remarks'] ?? '');

        if (in_array($action, ['approve', 'reject']) && in_array($role, ['accounting', 'mayor'])) {
            $req = $conn->prepare("SELECT * FROM payroll_approval_requests WHERE approval_id = ?");
            $req->execute([$approval_id]);
            $request = $req->fetch(PDO::FETCH_ASSOC);

            $ok = true;
            if ($role === 'mayor'      && $request['status'] !== 'Accounting_Approved') { $error_message = "Accounting must approve first."; $ok = false; }
            if ($role === 'accounting' && $request['status'] !== 'Pending')             { $error_message = "This request is no longer pending."; $ok = false; }

            if ($ok) {
                $act = ($action === 'approve') ? 'Approved' : 'Rejected';
                $ins = $conn->prepare("INSERT INTO payroll_approval_actions (approval_id, approver_role, approver_user_id, action, remarks) VALUES (?,?,?,?,?)");
                $ins->execute([$approval_id, $role, $user_id, $act, $remarks]);

                if ($action === 'reject') {
                    $new_status = 'Rejected';
                } elseif ($role === 'accounting') {
                    $new_status = 'Accounting_Approved';
                } else {
                    $new_status = 'Fully_Approved';
                    $conn->prepare("UPDATE payroll_transactions SET status='Paid' WHERE payroll_cycle_id=? AND status='Processed'")->execute([$request['payroll_cycle_id']]);
                }
                $conn->prepare("UPDATE payroll_approval_requests SET status=? WHERE approval_id=?")->execute([$new_status, $approval_id]);
                $success_message = ($action === 'approve') ? "Payroll approved successfully!" : "Payroll request rejected.";
            }
        }
    }
}

// ── Fetch stats ───────────────────────────────────────────────────────────────
try {
    // Pending for accounting
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Pending'");
    $pending_accounting = $s->fetchColumn();

    // Pending for mayor
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Accounting_Approved'");
    $pending_mayor = $s->fetchColumn();

    // Fully approved this month
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Fully_Approved' AND MONTH(requested_at)=MONTH(NOW()) AND YEAR(requested_at)=YEAR(NOW())");
    $approved_month = $s->fetchColumn();

    // Rejected total
    $s = $conn->query("SELECT COUNT(*) FROM payroll_approval_requests WHERE status='Rejected'");
    $total_rejected = $s->fetchColumn();

    // Total net pay approved this month
    $s = $conn->query("SELECT COALESCE(SUM(total_net),0) FROM payroll_approval_requests WHERE status='Fully_Approved' AND MONTH(requested_at)=MONTH(NOW()) AND YEAR(requested_at)=YEAR(NOW())");
    $total_net_approved = $s->fetchColumn();

} catch (PDOException $e) {
    $pending_accounting = $pending_mayor = $approved_month = $total_rejected = $total_net_approved = 0;
}

// ── Fetch requests queue based on role ────────────────────────────────────────
try {
    // For accounting: show Pending
    // For mayor:      show Accounting_Approved
    // For admin/hr:   show all
    if ($role === 'accounting') {
        $filter_status = "ar.status = 'Pending'";
    } elseif ($role === 'mayor') {
        $filter_status = "ar.status = 'Accounting_Approved'";
    } else {
        $filter_status = "ar.status IN ('Pending','Accounting_Approved','Fully_Approved','Rejected')";
    }

    $sql = "SELECT ar.*, pc.cycle_name, pc.pay_period_start, pc.pay_period_end,
                   CONCAT(COALESCE(pi.first_name,''), ' ', COALESCE(pi.last_name,'')) as submitted_by,
                   (SELECT action   FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_action,
                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_remarks,
                   (SELECT CONCAT(COALESCE(pi2.first_name,''),' ',COALESCE(pi2.last_name,'')) FROM payroll_approval_actions paa2 LEFT JOIN users u2 ON paa2.approver_user_id=u2.user_id LEFT JOIN employee_profiles ep2 ON u2.employee_id=ep2.employee_id LEFT JOIN personal_information pi2 ON ep2.personal_info_id=pi2.personal_info_id WHERE paa2.approval_id=ar.approval_id AND paa2.approver_role='accounting' ORDER BY paa2.acted_at DESC LIMIT 1) as acct_by,
                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_date,
                   (SELECT action   FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_action,
                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_remarks,
                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_date
            FROM payroll_approval_requests ar
            JOIN payroll_cycles pc ON ar.payroll_cycle_id = pc.payroll_cycle_id
            LEFT JOIN users u ON ar.requested_by = u.user_id
            LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            WHERE $filter_status
            ORDER BY ar.requested_at DESC";

    $requests = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requests = [];
    $error_message = "Error loading requests: " . $e->getMessage();
}

// ── Recent activity (last 10 actions) ────────────────────────────────────────
try {
    $act_sql = "SELECT paa.*, paa.approver_role, paa.action, paa.remarks, paa.acted_at,
                       pc.cycle_name,
                       CONCAT(COALESCE(pi.first_name,''),' ',COALESCE(pi.last_name,'')) as approver_name
                FROM payroll_approval_actions paa
                JOIN payroll_approval_requests ar ON paa.approval_id = ar.approval_id
                JOIN payroll_cycles pc ON ar.payroll_cycle_id = pc.payroll_cycle_id
                LEFT JOIN users u ON paa.approver_user_id = u.user_id
                LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id
                LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                ORDER BY paa.acted_at DESC LIMIT 10";
    $activity = $conn->query($act_sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activity = [];
}

// Role display label
$role_labels = ['accounting' => 'Accounting Officer', 'mayor' => 'Mayor', 'admin' => 'Administrator', 'hr' => 'HR Officer'];
$role_label  = $role_labels[$role] ?? ucfirst($role);

// What the current user needs to action
$my_queue_count = ($role === 'accounting') ? $pending_accounting : (($role === 'mayor') ? $pending_mayor : ($pending_accounting + $pending_mayor));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Dashboard - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        /* ── Base (identical to payroll_transactions.php) ── */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f5f5;
            margin: 0; padding: 0;
        }
        .sidebar {
            height: 100vh;
            background-color: #E91E63;
            color: #fff;
            padding-top: 20px;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #fff #E91E63;
            z-index: 1030;
        }
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: #E91E63; }
        .sidebar::-webkit-scrollbar-thumb { background-color: #fff; border-radius: 3px; }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            margin-bottom: 5px;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover { background-color: rgba(255,255,255,0.1); color: #fff; }
        .sidebar .nav-link.active { background-color: #fff; color: #E91E63; }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content {
            margin-left: 250px;
            padding: 90px 20px 20px;
            transition: margin-left 0.3s;
            width: calc(100% - 250px);
        }
        .top-navbar {
            background: #fff;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(128,0,0,0.1);
            position: fixed;
            top: 0; right: 0; left: 250px;
            z-index: 1020;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            border: none;
            border-radius: 8px;
            overflow: hidden;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(128,0,0,0.1);
            padding: 15px 20px;
            font-weight: bold;
            color: #E91E63;
        }
        .card-header i { color: #E91E63; }
        .card-body { padding: 20px; }
        .table th { border-top: none; color: #E91E63; font-weight: 600; }
        .table td { vertical-align: middle; color: #333; border-color: rgba(128,0,0,0.1); }
        .btn-primary { background-color: #E91E63; border-color: #E91E63; }
        .btn-primary:hover { background-color: #be0945; border-color: #be0945; }
        .section-title { color: #E91E63; margin-bottom: 25px; font-weight: 600; }
        .form-control:focus { border-color: #E91E63; box-shadow: 0 0 0 0.2rem rgba(233,30,99,0.25); }
        .table-sm th, .table-sm td { padding: 0.5rem; font-size: 0.875rem; }

        /* ── Welcome banner ── */
        .welcome-banner {
            background: linear-gradient(135deg, #E91E63 0%, #ad1457 100%);
            border-radius: 8px;
            padding: 22px 28px;
            margin-bottom: 24px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .welcome-banner .wb-left h4 { margin: 0 0 4px; font-weight: 700; font-size: 1.15rem; }
        .welcome-banner .wb-left p  { margin: 0; font-size: .875rem; opacity: .88; }
        .wb-badge {
            background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.35);
            border-radius: 6px;
            padding: 8px 16px;
            text-align: center;
        }
        .wb-badge .wb-num  { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .wb-badge .wb-text { font-size: .72rem; opacity: .85; margin-top: 2px; }

        /* ── Stat cards ── */
        .stat-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width:1100px) { .stat-cards { grid-template-columns: repeat(2,1fr); } }
        @media (max-width:600px)  { .stat-cards { grid-template-columns: 1fr; } }
        .stat-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-left: 4px solid transparent;
        }
        .stat-card.sc-pink   { border-left-color: #E91E63; }
        .stat-card.sc-blue   { border-left-color: #17a2b8; }
        .stat-card.sc-green  { border-left-color: #28a745; }
        .stat-card.sc-red    { border-left-color: #dc3545; }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .sc-pink  .stat-icon { background: #fce4ec; color: #E91E63; }
        .sc-blue  .stat-icon { background: #e3f6f9; color: #17a2b8; }
        .sc-green .stat-icon { background: #e8f5e9; color: #28a745; }
        .sc-red   .stat-icon { background: #ffebee; color: #dc3545; }
        .stat-info .stat-num   { font-size: 1.5rem; font-weight: 700; color: #1a1a2e; line-height: 1; }
        .stat-info .stat-label { font-size: .78rem; color: #888; margin-top: 3px; }

        /* ── Queue cards (for accounting/mayor action items) ── */
        .queue-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            margin-bottom: 14px;
            overflow: hidden;
            border-left: 4px solid #E91E63;
            transition: box-shadow .2s;
        }
        .queue-card:hover { box-shadow: 0 4px 12px rgba(233,30,99,0.12); }
        .queue-card.qc-blue  { border-left-color: #17a2b8; }
        .queue-card.qc-green { border-left-color: #28a745; }
        .queue-card.qc-red   { border-left-color: #dc3545; }
        .qc-body {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .qc-info .qc-title  { font-weight: 700; font-size: .9375rem; color: #1a1a2e; margin-bottom: 3px; }
        .qc-info .qc-period { font-size: .78rem; color: #888; }
        .qc-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .qc-meta-item { text-align: center; }
        .qc-meta-item .qm-val { font-weight: 700; font-size: .9375rem; color: #1a1a2e; }
        .qc-meta-item .qm-lbl { font-size: .72rem; color: #aaa; }
        .qc-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

        /* ── Progress pills ── */
        .p-step {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: .75rem; font-weight: 600;
            padding: 3px 8px; border-radius: 4px; white-space: nowrap;
        }
        .p-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .p-pending  { background: #f5f5f5; color: #666; }
        .p-pending  .p-dot { background: #bbb; }
        .p-approved { background: #e8f5e9; color: #256029; }
        .p-approved .p-dot { background: #43a047; }
        .p-rejected { background: #ffebee; color: #b71c1c; }
        .p-rejected .p-dot { background: #e53935; }
        .p-waiting  { background: #fff8e1; color: #bf360c; }
        .p-waiting  .p-dot { background: #ffa000; }

        /* ── Status badges ── */
        .badge { font-size: .8rem; padding: 5px 10px; font-weight: 600; border-radius: 4px; }
        .badge-pending             { background-color: #ffc107; color: #212529; }
        .badge-accounting_approved { background-color: #17a2b8; color: #fff; }
        .badge-fully_approved      { background-color: #28a745; color: #fff; }
        .badge-rejected            { background-color: #dc3545; color: #fff; }

        /* ── Activity feed ── */
        .activity-feed { display: flex; flex-direction: column; gap: 0; }
        .activity-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(128,0,0,0.06);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-dot {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; flex-shrink: 0; margin-top: 2px;
        }
        .ad-approved { background: #e8f5e9; color: #28a745; }
        .ad-rejected { background: #ffebee; color: #dc3545; }
        .activity-content .ac-title { font-size: .875rem; font-weight: 600; color: #333; margin-bottom: 2px; }
        .activity-content .ac-sub   { font-size: .78rem; color: #888; }
        .activity-content .ac-remark { font-size: .78rem; color: #555; font-style: italic; margin-top: 3px; background: #f9f9f9; padding: 3px 7px; border-radius: 4px; }

        /* ── Role tag ── */
        .role-tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: .75rem; font-weight: 600;
        }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 40px 20px; color: #aaa; }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; color: #ddd; display: block; }
        .empty-state p { font-size: .875rem; margin: 0; }

        /* ── Two-col layout ── */
        .dash-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
        @media (max-width: 1000px) { .dash-grid { grid-template-columns: 1fr; } }

        .btn-action-col { display: flex; flex-direction: column; gap: 4px; }

        /* Urgency pulse for items needing action */
        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0 rgba(233,30,99,0.35); }
            70%  { box-shadow: 0 0 0 8px rgba(233,30,99,0); }
            100% { box-shadow: 0 0 0 0 rgba(233,30,99,0); }
        }
        .needs-action { animation: pulse-ring 2s infinite; }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>
    <div class="row">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">

            <!-- Page title -->
            <h2 class="section-title">
                <i class="fas fa-tachometer-alt mr-2"></i>Approval Dashboard
            </h2>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="wb-left">
                    <h4>
                        <i class="fas fa-user-circle mr-2"></i>
                        Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </h4>
                    <p>
                        <span class="role-tag"><i class="fas fa-id-badge"></i> <?php echo $role_label; ?></span>
                        &nbsp;&nbsp;<?php echo date('l, F d, Y'); ?>
                    </p>
                </div>
                <?php if ($my_queue_count > 0): ?>
                    <div class="wb-badge needs-action">
                        <div class="wb-num"><?php echo $my_queue_count; ?></div>
                        <div class="wb-text">
                            <?php echo $my_queue_count === 1 ? 'Request' : 'Requests'; ?><br>
                            awaiting your action
                        </div>
                    </div>
                <?php else: ?>
                    <div class="wb-badge">
                        <div class="wb-num"><i class="fas fa-check" style="font-size:1.2rem;"></i></div>
                        <div class="wb-text">All clear!<br>Nothing pending</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stat Cards -->
            <div class="stat-cards">
                <div class="stat-card sc-pink">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $pending_accounting; ?></div>
                        <div class="stat-label">Pending Accounting Review</div>
                    </div>
                </div>
                <div class="stat-card sc-blue">
                    <div class="stat-icon"><i class="fas fa-stamp"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $pending_mayor; ?></div>
                        <div class="stat-label">Awaiting Mayor Approval</div>
                    </div>
                </div>
                <div class="stat-card sc-green">
                    <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $approved_month; ?></div>
                        <div class="stat-label">Fully Approved This Month</div>
                    </div>
                </div>
                <div class="stat-card sc-red">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div class="stat-info">
                        <div class="stat-num"><?php echo $total_rejected; ?></div>
                        <div class="stat-label">Total Rejected</div>
                    </div>
                </div>
            </div>

            <!-- Two-column layout: Queue + Activity -->
            <div class="dash-grid">

                <!-- LEFT: Action Queue -->
                <div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-inbox mr-2"></i>
                                <?php if ($role === 'accounting'): ?>
                                    Requests Pending Your Review
                                <?php elseif ($role === 'mayor'): ?>
                                    Requests Awaiting Your Approval
                                <?php else: ?>
                                    All Approval Requests
                                <?php endif; ?>
                                <span class="badge badge-<?php echo ($my_queue_count > 0) ? 'pending' : 'fully_approved'; ?> ml-2">
                                    <?php echo count($requests); ?>
                                </span>
                            </span>
                            <a href="payroll_approval.php" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;">
                                <i class="fas fa-external-link-alt mr-1"></i>Full List
                            </a>
                        </div>
                        <div class="card-body" style="padding: 16px 20px;">

                            <?php if (empty($requests)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color:#28a745;"></i>
                                    <p>
                                        <?php if ($role === 'accounting'): ?>
                                            No payroll requests pending your review.
                                        <?php elseif ($role === 'mayor'): ?>
                                            No approved requests awaiting your authorization.
                                        <?php else: ?>
                                            No requests found.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($requests as $req):
                                    $can_act = (
                                        ($role === 'accounting' && $req['status'] === 'Pending') ||
                                        ($role === 'mayor'      && $req['status'] === 'Accounting_Approved')
                                    );

                                    $badges = [
                                        'Pending'             => ['pending',             'Pending'],
                                        'Accounting_Approved' => ['accounting_approved',  'Acctg. Approved'],
                                        'Fully_Approved'      => ['fully_approved',        'Fully Approved'],
                                        'Rejected'            => ['rejected',              'Rejected'],
                                    ];
                                    [$bc, $bl] = $badges[$req['status']] ?? ['pending', $req['status']];

                                    $card_color = '';
                                    if ($req['status'] === 'Accounting_Approved') $card_color = 'qc-blue';
                                    if ($req['status'] === 'Fully_Approved')      $card_color = 'qc-green';
                                    if ($req['status'] === 'Rejected')            $card_color = 'qc-red';
                                ?>
                                <div class="queue-card <?php echo $card_color; ?>">
                                    <div class="qc-body">
                                        <!-- Info -->
                                        <div class="qc-info">
                                            <div class="qc-title">
                                                <?php echo htmlspecialchars($req['cycle_name']); ?>
                                                <span class="badge badge-<?php echo $bc; ?> ml-1" style="font-size:.72rem;"><?php echo $bl; ?></span>
                                            </div>
                                            <div class="qc-period">
                                                <i class="fas fa-calendar-alt mr-1" style="color:#ccc;"></i>
                                                <?php echo date('M d', strtotime($req['pay_period_start'])); ?> –
                                                <?php echo date('M d, Y', strtotime($req['pay_period_end'])); ?>
                                                &nbsp;|&nbsp;
                                                <i class="fas fa-user-clock mr-1" style="color:#ccc;"></i>
                                                Submitted <?php echo date('M d', strtotime($req['requested_at'])); ?>
                                                <?php if ($req['submitted_by']): ?>
                                                    by <?php echo htmlspecialchars(trim($req['submitted_by'])); ?>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Progress pills -->
                                            <div class="mt-2 d-flex gap-1" style="gap:6px; display:flex; flex-wrap:wrap;">
                                                <?php
                                                if ($req['acct_action'] === 'Approved')      { $ac = 'p-approved'; $al = 'Acctg: Approved'; }
                                                elseif ($req['acct_action'] === 'Rejected')  { $ac = 'p-rejected'; $al = 'Acctg: Rejected'; }
                                                else                                          { $ac = 'p-pending';  $al = 'Acctg: Pending';  }

                                                if ($req['mayor_action'] === 'Approved')     { $mc = 'p-approved'; $ml = 'Mayor: Approved'; }
                                                elseif ($req['mayor_action'] === 'Rejected') { $mc = 'p-rejected'; $ml = 'Mayor: Rejected'; }
                                                elseif ($req['acct_action'] === 'Approved')  { $mc = 'p-waiting';  $ml = 'Mayor: Awaiting'; }
                                                else                                          { $mc = 'p-pending';  $ml = 'Mayor: Pending';  }
                                                ?>
                                                <span class="p-step <?php echo $ac; ?>"><span class="p-dot"></span><?php echo $al; ?></span>
                                                <span class="p-step <?php echo $mc; ?>"><span class="p-dot"></span><?php echo $ml; ?></span>
                                            </div>
                                        </div>

                                        <!-- Amounts -->
                                        <div class="qc-meta">
                                            <div class="qc-meta-item">
                                                <div class="qm-val"><?php echo $req['total_employees']; ?></div>
                                                <div class="qm-lbl">Employees</div>
                                            </div>
                                            <div class="qc-meta-item">
                                                <div class="qm-val">₱<?php echo number_format($req['total_gross'], 0); ?></div>
                                                <div class="qm-lbl">Gross Pay</div>
                                            </div>
                                            <div class="qc-meta-item">
                                                <div class="qm-val" style="color:#E91E63;">₱<?php echo number_format($req['total_net'], 0); ?></div>
                                                <div class="qm-lbl">Net Pay</div>
                                            </div>
                                        </div>

                                        <!-- Action buttons -->
                                        <div class="qc-actions">
                                            <div class="btn-action-col">
                                                <!-- View Summary always visible -->
                                                <a href="payroll_summary.php?approval_id=<?php echo $req['approval_id']; ?>"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-file-alt mr-1"></i>View Summary
                                                </a>
                                                <?php if ($can_act): ?>
                                                    <button class="btn btn-sm btn-success"
                                                            onclick="showModal(<?php echo $req['approval_id']; ?>, '<?php echo htmlspecialchars(addslashes($req['cycle_name'])); ?>', 'approve')">
                                                        <i class="fas fa-check mr-1"></i>Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger"
                                                            onclick="showModal(<?php echo $req['approval_id']; ?>, '<?php echo htmlspecialchars(addslashes($req['cycle_name'])); ?>', 'reject')">
                                                        <i class="fas fa-times mr-1"></i>Reject
                                                    </button>
                                                <?php elseif ($req['status'] === 'Fully_Approved'): ?>
                                                    <span class="text-success" style="font-size:.82rem; font-weight:700; display:block; text-align:center; margin-top:2px;">
                                                        <i class="fas fa-check-double mr-1"></i>Released
                                                    </span>
                                                <?php elseif ($req['status'] === 'Rejected'): ?>
                                                    <span class="text-danger" style="font-size:.82rem; font-weight:700; display:block; text-align:center; margin-top:2px;">
                                                        <i class="fas fa-ban mr-1"></i>Rejected
                                                    </span>
                                                <?php elseif ($req['status'] === 'Accounting_Approved' && $role !== 'mayor'): ?>
                                                    <span class="text-info" style="font-size:.82rem; font-weight:600; display:block; text-align:center; margin-top:2px;">
                                                        <i class="fas fa-arrow-right mr-1"></i>With Mayor
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <!-- RIGHT: Recent Activity -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history mr-2"></i>Recent Activity
                        </div>
                        <div class="card-body" style="padding: 8px 20px;">
                            <?php if (empty($activity)): ?>
                                <div class="empty-state" style="padding:30px 10px;">
                                    <i class="fas fa-clock"></i>
                                    <p>No activity yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="activity-feed">
                                    <?php foreach ($activity as $act): ?>
                                    <div class="activity-item">
                                        <div class="activity-dot <?php echo $act['action'] === 'Approved' ? 'ad-approved' : 'ad-rejected'; ?>">
                                            <i class="fas fa-<?php echo $act['action'] === 'Approved' ? 'check' : 'times'; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="ac-title">
                                                <?php echo htmlspecialchars($act['cycle_name']); ?>
                                                <span class="badge badge-<?php echo $act['action'] === 'Approved' ? 'fully_approved' : 'rejected'; ?>" style="font-size:.68rem; padding:2px 7px;">
                                                    <?php echo $act['action']; ?>
                                                </span>
                                            </div>
                                            <div class="ac-sub">
                                                <?php echo ucfirst($act['approver_role']); ?>
                                                <?php if (trim($act['approver_name'])): ?>
                                                    &middot; <?php echo htmlspecialchars(trim($act['approver_name'])); ?>
                                                <?php endif; ?>
                                                &middot; <?php echo date('M d, g:i A', strtotime($act['acted_at'])); ?>
                                            </div>
                                            <?php if ($act['remarks']): ?>
                                                <div class="ac-remark">"<?php echo htmlspecialchars($act['remarks']); ?>"</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-link mr-2"></i>Quick Links
                        </div>
                        <div class="card-body" style="padding:12px 16px;">
                            <a href="payroll_approval.php" class="btn btn-sm btn-primary btn-block mb-2">
                                <i class="fas fa-clipboard-check mr-2"></i>All Approval Requests
                            </a>
                            <a href="payroll_transactions.php" class="btn btn-sm btn-outline-secondary btn-block mb-2">
                                <i class="fas fa-exchange-alt mr-2"></i>Payroll Transactions
                            </a>
                            <a href="payroll_cycles.php" class="btn btn-sm btn-outline-secondary btn-block">
                                <i class="fas fa-calendar-alt mr-2"></i>Payroll Cycles
                            </a>
                        </div>
                    </div>
                </div>

            </div><!-- /dash-grid -->

        </div><!-- /main-content -->
    </div>
</div>

<!-- Approve / Reject Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Approval Action</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="modalAction">
                    <input type="hidden" name="approval_id" id="modalApprovalId">
                    <p id="modalDesc" class="text-muted" style="font-size:.875rem; margin-bottom:16px;"></p>
                    <div class="form-group mb-0">
                        <label style="font-weight:600; font-size:.875rem; color:#333;">Remarks</label>
                        <textarea name="remarks" id="modalRemarks" class="form-control" rows="3"
                                  placeholder="Add your remarks here..."></textarea>
                        <small class="form-text text-muted">Optional for approval. Required when rejecting.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-sm" id="modalSubmitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
function showModal(id, cycleName, action) {
    document.getElementById('modalApprovalId').value = id;
    document.getElementById('modalAction').value     = action;
    document.getElementById('modalRemarks').value    = '';

    const btn   = document.getElementById('modalSubmitBtn');
    const title = document.getElementById('modalTitle');
    const desc  = document.getElementById('modalDesc');

    if (action === 'approve') {
        title.textContent = 'Approve Payroll';
        desc.innerHTML    = 'You are about to <strong>approve</strong> payroll for: <strong>' + cycleName + '</strong>';
        btn.className     = 'btn btn-success btn-sm';
        btn.innerHTML     = '<i class="fas fa-check mr-1"></i>Approve';
    } else {
        title.textContent = 'Reject Payroll';
        desc.innerHTML    = 'You are about to <strong>reject</strong> payroll for: <strong>' + cycleName + '</strong>.<br><small class="text-danger">Please provide a reason below.</small>';
        btn.className     = 'btn btn-danger btn-sm';
        btn.innerHTML     = '<i class="fas fa-times mr-1"></i>Reject';
    }

    $('#approvalModal').modal('show');
}
</script>

</body>
</html>