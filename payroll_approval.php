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

$role     = $_SESSION['role'];
$user_id  = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid CSRF token!";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'submit_for_approval' && in_array($role, ['admin', 'hr'])) {
            $cycle_id = $_POST['cycle_id'];
            $stmt = $conn->prepare("SELECT COUNT(*) as emp_count, SUM(gross_pay) as total_gross, SUM(net_pay) as total_net
                                    FROM payroll_transactions WHERE payroll_cycle_id = ? AND status = 'Processed'");
            $stmt->execute([$cycle_id]);
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);

            $chk = $conn->prepare("SELECT approval_id FROM payroll_approval_requests WHERE payroll_cycle_id = ? AND status NOT IN ('Rejected')");
            $chk->execute([$cycle_id]);
            if ($chk->fetch()) {
                $error_message = "An approval request already exists for this cycle.";
            } else {
                $ins = $conn->prepare("INSERT INTO payroll_approval_requests (payroll_cycle_id, requested_by, total_gross, total_net, total_employees, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                $ins->execute([$cycle_id, $user_id, $totals['total_gross'], $totals['total_net'], $totals['emp_count']]);
                $success_message = "Payroll submitted for approval successfully!";
            }
        }

        if (in_array($action, ['approve', 'reject']) && in_array($role, ['admin', 'accounting', 'mayor'])) {
            $approval_id = intval($_POST['approval_id']);
            $remarks     = $_POST['remarks'] ?? '';

            $req = $conn->prepare("SELECT * FROM payroll_approval_requests WHERE approval_id = ?");
            $req->execute([$approval_id]);
            $request = $req->fetch(PDO::FETCH_ASSOC);

            // Check permissions based on role and current status
            $can_approve = false;
            if ($role === 'admin') {
                $can_approve = true;
            } elseif ($role === 'accounting' && $request['status'] === 'Pending') {
                $can_approve = true;
            } elseif ($role === 'mayor' && $request['status'] === 'Accounting_Approved') {
                $can_approve = true;
            }

            if (!$can_approve) {
                if ($role === 'mayor') {
                    $error_message = "Accounting must approve before Mayor review.";
                } elseif ($role === 'accounting') {
                    $error_message = "This request is not pending accounting review.";
                } else {
                    $error_message = "You don't have permission to approve this request.";
                }
            } else {
                $act = ($action === 'approve') ? 'Approved' : 'Rejected';

                $ins = $conn->prepare("INSERT INTO payroll_approval_actions (approval_id, approver_role, approver_user_id, action, remarks) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$approval_id, $role, $user_id, $act, $remarks]);

                if ($action === 'reject') {
                    $new_status = 'Rejected';
                } elseif ($role === 'accounting') {
                    $new_status = 'Accounting_Approved';
                } else {
                    $new_status = 'Fully_Approved';
                    $conn->prepare("UPDATE payroll_transactions SET status = 'Paid' WHERE payroll_cycle_id = ? AND status = 'Processed'")->execute([$request['payroll_cycle_id']]);
                }

                $conn->prepare("UPDATE payroll_approval_requests SET status = ? WHERE approval_id = ?")->execute([$new_status, $approval_id]);
                $success_message = ($action === 'approve') ? "Payroll approved successfully!" : "Payroll request has been rejected.";
            }
        }
    }
}

// Fetch all approval requests
try {
    $sql = "SELECT ar.*, pc.cycle_name, pc.pay_period_start, pc.pay_period_end,
                   CONCAT(COALESCE(pi.first_name,''), ' ', COALESCE(pi.last_name,'')) as requested_by_name,
                   (SELECT action   FROM payroll_approval_actions WHERE approval_id = ar.approval_id AND approver_role = 'accounting' ORDER BY acted_at DESC LIMIT 1) as acct_action,
                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id = ar.approval_id AND approver_role = 'accounting' ORDER BY acted_at DESC LIMIT 1) as acct_remarks,
                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id = ar.approval_id AND approver_role = 'accounting' ORDER BY acted_at DESC LIMIT 1) as acct_date,
                   (SELECT action   FROM payroll_approval_actions WHERE approval_id = ar.approval_id AND approver_role = 'mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_action,
                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id = ar.approval_id AND approver_role = 'mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_remarks,
                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id = ar.approval_id AND approver_role = 'mayor'      ORDER BY acted_at DESC LIMIT 1) as mayor_date,
                   (SELECT action   FROM payroll_approval_actions WHERE approval_id = ar.approval_id AND approver_role = 'admin'      ORDER BY acted_at DESC LIMIT 1) as admin_action,
                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id = ar.approval_id AND approver_role = 'admin'      ORDER BY acted_at DESC LIMIT 1) as admin_remarks,
                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id = ar.approval_id AND approver_role = 'admin'      ORDER BY acted_at DESC LIMIT 1) as admin_date
            FROM payroll_approval_requests ar
            JOIN payroll_cycles pc ON ar.payroll_cycle_id = pc.payroll_cycle_id
            LEFT JOIN users u ON ar.requested_by = u.user_id
            LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            ORDER BY ar.requested_at DESC";
    $requests = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requests = [];
    $error_message = "Error fetching approval requests: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Approval Requests - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        /* ── Identical base styles from payroll_transactions.php ── */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f5f5;
            margin: 0;
            padding: 0;
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
        .table th {
            border-top: none;
            color: #E91E63;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
            color: #333;
            border-color: rgba(128,0,0,0.1);
        }
        .btn-primary { background-color: #E91E63; border-color: #E91E63; }
        .btn-primary:hover { background-color: #be0945; border-color: #be0945; }
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
        .section-title { color: #E91E63; margin-bottom: 25px; font-weight: 600; }
        .form-control:focus {
            border-color: #E91E63;
            box-shadow: 0 0 0 0.2rem rgba(233,30,99,0.25);
        }
        .table-sm th, .table-sm td { padding: 0.5rem; font-size: 0.9rem; }

        /* ── Workflow banner (fits the card style) ── */
        .workflow-banner {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(128,0,0,0.05);
            padding: 14px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0;
        }
        .wf-step { display: flex; align-items: center; gap: 8px; }
        .wf-icon {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; flex-shrink: 0;
        }
        .wf-icon.s1 { background: #e3f2fd; color: #1565c0; }
        .wf-icon.s2 { background: #fff8e1; color: #f57f17; }
        .wf-icon.s3 { background: #fce4ec; color: #E91E63; }
        .wf-icon.s4 { background: #e8f5e9; color: #2e7d32; }
        .wf-label   { font-size: .8125rem; font-weight: 600; color: #333; line-height: 1.1; }
        .wf-sub     { font-size: .7rem; color: #999; }
        .wf-arrow   { color: #ccc; font-size: .7rem; padding: 0 12px; }

        /* ── Approval progress pills ── */
        .progress-wrap { display: flex; flex-direction: column; gap: 4px; }
        .p-step {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: .78rem;
            font-weight: 500;
            padding: 3px 8px;
            border-radius: 4px;
            width: fit-content;
        }
        .p-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .p-pending  { background: #f5f5f5; color: #666; }
        .p-pending  .p-dot { background: #bbb; }
        .p-approved { background: #e8f5e9; color: #256029; }
        .p-approved .p-dot { background: #43a047; }
        .p-rejected { background: #ffebee; color: #b71c1c; }
        .p-rejected .p-dot { background: #e53935; }
        .p-waiting  { background: #fff8e1; color: #bf360c; }
        .p-waiting  .p-dot { background: #ffa000; }
        .p-role { opacity: .65; margin-right: 2px; }

        /* ── Status badges (mirrors existing badge-* pattern) ── */
        .badge { font-size: .8rem; padding: 5px 10px; font-weight: 600; border-radius: 4px; }
        .badge-pending             { background-color: #ffc107; color: #212529; }
        .badge-accounting_approved { background-color: #17a2b8; color: #fff; }
        .badge-fully_approved      { background-color: #28a745; color: #fff; }
        .badge-rejected            { background-color: #dc3545; color: #fff; }

        .btn-action-col { display: flex; flex-direction: column; gap: 4px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>
    <div class="row">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <h2 class="section-title">
                <i class="fas fa-clipboard-check mr-2"></i>Payroll Approval Requests
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

            <!-- Workflow Banner -->
            <div class="workflow-banner">
                <div class="wf-step">
                    <div class="wf-icon s1"><i class="fas fa-calculator"></i></div>
                    <div>
                        <div class="wf-label">HR Calculates</div>
                        <div class="wf-sub">Computes payroll</div>
                    </div>
                </div>
                <div class="wf-arrow"><i class="fas fa-chevron-right"></i></div>
                <div class="wf-step">
                    <div class="wf-icon s2"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div>
                        <div class="wf-label">Accounting Reviews</div>
                        <div class="wf-sub">Validates figures</div>
                    </div>
                </div>
                <div class="wf-arrow"><i class="fas fa-chevron-right"></i></div>
                <div class="wf-step">
                    <div class="wf-icon s3"><i class="fas fa-stamp"></i></div>
                    <div>
                        <div class="wf-label">Mayor Approves</div>
                        <div class="wf-sub">Final authorization</div>
                    </div>
                </div>
                <div class="wf-arrow"><i class="fas fa-chevron-right"></i></div>
                <div class="wf-step">
                    <div class="wf-icon s4"><i class="fas fa-money-bill-wave"></i></div>
                    <div>
                        <div class="wf-label">Payroll Released</div>
                        <div class="wf-sub">Salaries disbursed</div>
                    </div>
                </div>
            </div>

            <!-- Main Table Card -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-list mr-2"></i>
                        Approval Requests (<?php echo count($requests); ?> records)
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Payroll Cycle</th>
                                    <th>Pay Period</th>
                                    <th>Employees</th>
                                    <th>Total Gross</th>
                                    <th>Total Net</th>
                                    <th>Submitted</th>
                                    <th>Approval Progress</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($requests)): ?>
                                <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($req['cycle_name']); ?></strong></td>

                                    <td>
                                        <small>
                                            <?php echo date('M d', strtotime($req['pay_period_start'])); ?> –
                                            <?php echo date('M d, Y', strtotime($req['pay_period_end'])); ?>
                                        </small>
                                    </td>

                                    <td><?php echo $req['total_employees']; ?></td>

                                    <td>₱<?php echo number_format($req['total_gross'], 2); ?></td>

                                    <td><strong>₱<?php echo number_format($req['total_net'], 2); ?></strong></td>

                                    <td><small><?php echo date('M d, Y', strtotime($req['requested_at'])); ?></small></td>

                                    <!-- Approval Progress -->
                                    <td>
                                        <?php
                                        if ($req['acct_action'] === 'Approved')      { $acls = 'p-approved'; $albl = 'Approved'; }
                                        elseif ($req['acct_action'] === 'Rejected')  { $acls = 'p-rejected'; $albl = 'Rejected'; }
                                        else                                          { $acls = 'p-pending';  $albl = 'Pending';  }

                                        if ($req['mayor_action'] === 'Approved')     { $mcls = 'p-approved'; $mlbl = 'Approved'; }
                                        elseif ($req['mayor_action'] === 'Rejected') { $mcls = 'p-rejected'; $mlbl = 'Rejected'; }
                                        elseif ($req['acct_action'] === 'Approved')  { $mcls = 'p-waiting';  $mlbl = 'Awaiting'; }
                                        else                                          { $mcls = 'p-pending';  $mlbl = 'Pending';  }
                                        ?>
                                        <div class="progress-wrap">
                                            <span class="p-step <?php echo $acls; ?>">
                                                <span class="p-dot"></span>
                                                <span class="p-role">Accounting:</span><?php echo $albl; ?>
                                            </span>
                                            <span class="p-step <?php echo $mcls; ?>">
                                                <span class="p-dot"></span>
                                                <span class="p-role">Mayor:</span><?php echo $mlbl; ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td>
                                        <?php
                                        $badges = [
                                            'Pending'             => ['pending',             'Pending'],
                                            'Accounting_Approved' => ['accounting_approved',  'Acctg. Approved'],
                                            'Fully_Approved'      => ['fully_approved',        'Fully Approved'],
                                            'Rejected'            => ['rejected',              'Rejected'],
                                        ];
                                        [$bc, $lbl] = $badges[$req['status']] ?? ['pending', $req['status']];
                                        ?>
                                        <span class="badge badge-<?php echo $bc; ?>"><?php echo $lbl; ?></span>
                                    </td>

                                    <!-- Actions -->
                                    <td>
                                        <?php
                                        $can_act = in_array($role, ['admin', 'accounting', 'mayor']) && 
                                            (($role === 'accounting' && $req['status'] === 'Pending') ||
                                            ($role === 'mayor'      && $req['status'] === 'Accounting_Approved') ||
                                            ($role === 'admin'));
                                        ?>
                                        <div class="btn-action-col">
                                            <!-- Always show View Summary button -->
                                            <a href="payroll_summary.php?approval_id=<?php echo $req['approval_id']; ?>" class="btn btn-sm btn-info" title="View Summary" style="margin-bottom: 3px;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <?php if ($can_act): ?>
                                                <!-- Direct Approve Form -->
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Approve this payroll request?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="approval_id" value="<?php echo $req['approval_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Approve" style="width: 100%;">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <!-- Direct Reject Form -->
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Reject this payroll request?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="approval_id" value="<?php echo $req['approval_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Reject" style="width: 100%;">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            <?php elseif ($req['status'] === 'Fully_Approved'): ?>
                                                <span class="text-success" style="font-size:.82rem; font-weight:600;">
                                                    <i class="fas fa-check-double mr-1"></i>Released
                                                </span>
                                            <?php elseif ($req['status'] === 'Rejected'): ?>
                                                <span class="text-danger" style="font-size:.82rem; font-weight:600;">
                                                    <i class="fas fa-ban mr-1"></i>Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:.75rem;">Pending...</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block" style="color: #ddd;"></i>
                                        No approval requests found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

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
                    <input type="hidden" name="action" id="mod alAction">
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