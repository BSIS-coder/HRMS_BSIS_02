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

$approval_id = isset($_GET['approval_id']) ? intval($_GET['approval_id']) : null;
if (!$approval_id) {
    header('Location: approval_dashboard.php'); exit;
}

// ── Handle approve/reject POST from this page ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid CSRF token!";
    } else {
        $action  = $_POST['action']  ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        $aid     = intval($_POST['approval_id'] ?? 0);

        if (in_array($action, ['approve', 'reject']) && in_array($role, ['accounting', 'mayor'])) {
            $req = $conn->prepare("SELECT * FROM payroll_approval_requests WHERE approval_id = ?");
            $req->execute([$aid]);
            $request = $req->fetch(PDO::FETCH_ASSOC);

            $ok = true;
            if ($role === 'mayor'      && $request['status'] !== 'Accounting_Approved') { $error_message = "Accounting must approve first."; $ok = false; }
            if ($role === 'accounting' && $request['status'] !== 'Pending')             { $error_message = "This request is no longer pending."; $ok = false; }

            if ($ok) {
                $act = ($action === 'approve') ? 'Approved' : 'Rejected';
                $ins = $conn->prepare("INSERT INTO payroll_approval_actions (approval_id, approver_role, approver_user_id, action, remarks) VALUES (?,?,?,?,?)");
                $ins->execute([$aid, $role, $user_id, $act, $remarks]);

                if ($action === 'reject') {
                    $new_status = 'Rejected';
                } elseif ($role === 'accounting') {
                    $new_status = 'Accounting_Approved';
                } else {
                    $new_status = 'Fully_Approved';
                    $conn->prepare("UPDATE payroll_transactions SET status='Paid' WHERE payroll_cycle_id=? AND status='Processed'")->execute([$request['payroll_cycle_id']]);
                }
                $conn->prepare("UPDATE payroll_approval_requests SET status=? WHERE approval_id=?")->execute([$new_status, $aid]);
                $success_message = ($action === 'approve') ? "Payroll approved successfully! Transactions marked as Paid." : "Payroll request rejected.";
            }
        }
    }
}

// ── Fetch approval request details ───────────────────────────────────────────
try {
    $stmt = $conn->prepare("SELECT ar.*, pc.cycle_name, pc.pay_period_start, pc.pay_period_end, pc.pay_date,
                                   CONCAT(COALESCE(pi.first_name,''),' ',COALESCE(pi.last_name,'')) as submitted_by_name,
                                   (SELECT action   FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_action,
                                   (SELECT remarks  FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_remarks,
                                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='accounting' ORDER BY acted_at DESC LIMIT 1) as acct_date,
                                   (SELECT CONCAT(COALESCE(pi2.first_name,''),' ',COALESCE(pi2.last_name,'')) FROM payroll_approval_actions paa2 LEFT JOIN users u2 ON paa2.approver_user_id=u2.user_id LEFT JOIN employee_profiles ep2 ON u2.employee_id=ep2.employee_id LEFT JOIN personal_information pi2 ON ep2.personal_info_id=pi2.personal_info_id WHERE paa2.approval_id=ar.approval_id AND paa2.approver_role='accounting' ORDER BY paa2.acted_at DESC LIMIT 1) as acct_by,
                                   (SELECT action   FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor' ORDER BY acted_at DESC LIMIT 1) as mayor_action,
                                   (SELECT acted_at FROM payroll_approval_actions WHERE approval_id=ar.approval_id AND approver_role='mayor' ORDER BY acted_at DESC LIMIT 1) as mayor_date,
                                   (SELECT CONCAT(COALESCE(pi3.first_name,''),' ',COALESCE(pi3.last_name,'')) FROM payroll_approval_actions paa3 LEFT JOIN users u3 ON paa3.approver_user_id=u3.user_id LEFT JOIN employee_profiles ep3 ON u3.employee_id=ep3.employee_id LEFT JOIN personal_information pi3 ON ep3.personal_info_id=pi3.personal_info_id WHERE paa3.approval_id=ar.approval_id AND paa3.approver_role='mayor' ORDER BY paa3.acted_at DESC LIMIT 1) as mayor_by
                            FROM payroll_approval_requests ar
                            JOIN payroll_cycles pc ON ar.payroll_cycle_id = pc.payroll_cycle_id
                            LEFT JOIN users u ON ar.requested_by = u.user_id
                            LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id
                            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                            WHERE ar.approval_id = ?");
    $stmt->execute([$approval_id]);
    $approval = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$approval) {
        header('Location: approval_dashboard.php'); exit;
    }
    $cycle_id = $approval['payroll_cycle_id'];

} catch (PDOException $e) {
    die("Error loading approval: " . $e->getMessage());
}

// ── Fetch payroll transactions for this cycle ─────────────────────────────────
try {
    $sql = "SELECT
                pt.gross_pay, pt.tax_deductions, pt.statutory_deductions, pt.other_deductions, pt.net_pay,
                ep.employee_number,
                pi.first_name, pi.last_name,
                jr.title AS position,
                
               
                d.department_name,
                COALESCE(ss.basic_salary, ep.current_salary, 0) AS monthly_basic,
                COALESCE(ss.allowances, 0) AS monthly_allowance,
                (COALESCE(ss.basic_salary, ep.current_salary, 0) + COALESCE(ss.allowances, 0)) AS monthly_rate,

                -- GSIS premium (employee share, no loans)
                COALESCE((SELECT SUM(sd.deduction_amount) FROM statutory_deductions sd
                          WHERE sd.employee_id = ep.employee_id
                            AND LOWER(sd.deduction_type) LIKE '%gsis%'
                            AND LOWER(sd.deduction_type) NOT LIKE '%loan%'
                            AND LOWER(sd.deduction_type) NOT LIKE '%policy%'
                            AND LOWER(sd.deduction_type) NOT LIKE '%emergency%'
                          LIMIT 1), 0) AS gsis_monthly,

                -- PhilHealth
                COALESCE((SELECT SUM(sd.deduction_amount) FROM statutory_deductions sd
                          WHERE sd.employee_id = ep.employee_id
                            AND LOWER(sd.deduction_type) LIKE '%philhealth%'
                          LIMIT 1), 0) AS philhealth_monthly,

                -- Pag-IBIG premium
                COALESCE((SELECT SUM(sd.deduction_amount) FROM statutory_deductions sd
                          WHERE sd.employee_id = ep.employee_id
                            AND (LOWER(sd.deduction_type) LIKE '%pag-ibig%' OR LOWER(sd.deduction_type) LIKE '%pagibig%' OR LOWER(sd.deduction_type) LIKE '%hdmf%')
                            AND LOWER(sd.deduction_type) NOT LIKE '%loan%'
                          LIMIT 1), 0) AS pagibig_monthly,

                -- GSIS loans
                COALESCE((SELECT SUM(sd.deduction_amount) FROM statutory_deductions sd
                          WHERE sd.employee_id = ep.employee_id
                            AND LOWER(sd.deduction_type) LIKE '%gsis%'
                            AND (LOWER(sd.deduction_type) LIKE '%loan%' OR LOWER(sd.deduction_type) LIKE '%policy%' OR LOWER(sd.deduction_type) LIKE '%emergency%')
                         ), 0) AS gsis_loan,

                -- Pag-IBIG loans
                COALESCE((SELECT SUM(sd.deduction_amount) FROM statutory_deductions sd
                          WHERE sd.employee_id = ep.employee_id
                            AND (LOWER(sd.deduction_type) LIKE '%pag-ibig%' OR LOWER(sd.deduction_type) LIKE '%pagibig%' OR LOWER(sd.deduction_type) LIKE '%hdmf%')
                            AND LOWER(sd.deduction_type) LIKE '%loan%'
                         ), 0) AS pagibig_loan

            FROM payroll_transactions pt
            INNER JOIN employee_profiles ep ON pt.employee_id = ep.employee_id
            LEFT  JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT  JOIN job_roles jr            ON ep.job_role_id      = jr.job_role_id
            LEFT  JOIN departments d           ON jr.department       = d.department_name
            LEFT  JOIN salary_structures ss    ON ep.employee_id      = ss.employee_id
            WHERE pt.payroll_cycle_id = ? AND pt.status != 'Cancelled'
            ORDER BY d.department_name, pi.last_name, pi.first_name";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$cycle_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Determine if half-month cycle
    $start_dt = new DateTime($approval['pay_period_start']);
    $end_dt   = new DateTime($approval['pay_period_end']);
    $day_diff = $end_dt->diff($start_dt)->days;
    $name_lower = strtolower($approval['cycle_name']);
    $is_half = ($day_diff <= 16 || strpos($name_lower, 'half') !== false || strpos($name_lower, '1st') !== false || strpos($name_lower, '2nd') !== false);
    $divisor = $is_half ? 2 : 1;

    // Post-process each row
    foreach ($transactions as &$row) {
        $total = $row['monthly_basic'] + $row['monthly_allowance'];
        $br    = $total > 0 ? $row['monthly_basic'] / $total : 1;
        $ar    = $total > 0 ? $row['monthly_allowance'] / $total : 0;

        $row['basic_earned']     = round($row['gross_pay'] * $br, 2);
        $row['allowance_earned'] = round($row['gross_pay'] * $ar, 2);
        $row['gsis']             = round($row['gsis_monthly'] / $divisor, 2);
        $row['philhealth']       = round($row['philhealth_monthly'] / $divisor, 2);
        $row['pagibig']          = round($row['pagibig_monthly'] / $divisor, 2);
        $row['wtax']             = round($row['tax_deductions'], 2);
        $row['gsis_loan_period'] = round($row['gsis_loan'], 2);
        $row['pagibig_loan_period'] = round($row['pagibig_loan'], 2);
        $row['total_ded']        = round($row['gsis'] + $row['philhealth'] + $row['pagibig'] + $row['wtax'] + $row['gsis_loan_period'] + $row['pagibig_loan_period'], 2);
    }
    unset($row);

    // Compute grand totals
    $t_gross    = array_sum(array_column($transactions, 'gross_pay'));
    $t_basic    = array_sum(array_column($transactions, 'basic_earned'));
    $t_allow    = array_sum(array_column($transactions, 'allowance_earned'));
    $t_gsis     = array_sum(array_column($transactions, 'gsis'));
    $t_ph       = array_sum(array_column($transactions, 'philhealth'));
    $t_pi       = array_sum(array_column($transactions, 'pagibig'));
    $t_tax      = array_sum(array_column($transactions, 'wtax'));
    $t_gsis_ln  = array_sum(array_column($transactions, 'gsis_loan_period'));
    $t_pi_ln    = array_sum(array_column($transactions, 'pagibig_loan_period'));
    $t_ded      = array_sum(array_column($transactions, 'total_ded'));
    $t_net      = array_sum(array_column($transactions, 'net_pay'));
    $emp_count  = count($transactions);

} catch (PDOException $e) {
    die("Error loading transactions: " . $e->getMessage());
}

// ── Format period string ──────────────────────────────────────────────────────
$period_str = date('F j', strtotime($approval['pay_period_start']));
if (date('Y-m', strtotime($approval['pay_period_start'])) === date('Y-m', strtotime($approval['pay_period_end']))) {
    $period_str .= '–' . date('j, Y', strtotime($approval['pay_period_end']));
} else {
    $period_str .= ' – ' . date('F j, Y', strtotime($approval['pay_period_end']));
}

// ── Can current user act on this? ─────────────────────────────────────────────
$can_act = (
    ($role === 'accounting' && $approval['status'] === 'Pending') ||
    ($role === 'mayor'      && $approval['status'] === 'Accounting_Approved')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Summary – <?php echo htmlspecialchars($approval['cycle_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ── Screen ── */
        body {
            background: #e8e0d5;
            margin: 0;
            padding: 24px 16px 40px;
            font-family: 'Source Sans 3', sans-serif;
            color: #1a1008;
        }

        /* ── Action toolbar (hidden on print) ── */
        .screen-toolbar {
            max-width: 960px;
            margin: 0 auto 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .toolbar-left { display: flex; align-items: center; gap: 10px; }
        .toolbar-left a {
            color: #8B0000;
            font-size: .82rem;
            font-weight: 600;
            text-decoration: none;
            display: flex; align-items: center; gap: 5px;
        }
        .toolbar-left a:hover { text-decoration: underline; }
        .toolbar-title { font-size: .875rem; color: #555; font-weight: 600; }
        .toolbar-right { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn-toolbar {
            border: none; border-radius: 6px;
            padding: 8px 16px; font-size: .82rem; font-weight: 700;
            cursor: pointer; font-family: 'Source Sans 3', sans-serif;
            display: inline-flex; align-items: center; gap: 6px;
            transition: opacity .15s;
        }
        .btn-toolbar:hover { opacity: .85; }
        .btn-print   { background: #2c1810; color: #fff; }
        .btn-approve { background: #2e7d32; color: #fff; }
        .btn-reject  { background: #8B0000; color: #fff; }
        .btn-back    { background: #f0ebe3; color: #5a3010; border: 1px solid #c8b89a; }

        /* ── Alert ── */
        .alert-bar {
            max-width: 960px; margin: 0 auto 14px;
            padding: 10px 16px; border-radius: 6px;
            font-size: .875rem; font-weight: 600;
            display: flex; align-items: center; gap: 8px;
        }
        .alert-success { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        .alert-danger  { background: #ffebee; color: #8B0000; border: 1px solid #ef9a9a; }

        /* ── Document ── */
        .document {
            max-width: 960px;
            margin: 0 auto;
            background: #fffdf8;
            border: 1px solid #ccc;
            box-shadow: 0 4px 30px rgba(0,0,0,.18);
            padding: 48px 56px 40px;
            position: relative;
        }
        .document::before {
            content: 'FOR APPROVAL';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%,-50%) rotate(-35deg);
            font-size: 5.5rem; font-weight: 900;
            color: rgba(139,0,0,.035);
            white-space: nowrap; pointer-events: none;
            font-family: 'EB Garamond', serif; letter-spacing: .2em;
        }

        /* ── Doc header ── */
        .doc-header {
            text-align: center;
            border-bottom: 3px double #8B0000;
            padding-bottom: 16px;
            margin-bottom: 18px;
        }
        .republic  { font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #8B0000; margin-bottom: 1px; }
        .province  { font-size: .78rem; color: #666; margin-bottom: 2px; }
        .lgu-name  { font-family: 'EB Garamond', serif; font-size: 1.5rem; font-weight: 700; margin: 0 0 2px; }
        .doc-title { font-family: 'EB Garamond', serif; font-size: 1.05rem; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; margin-top: 10px; }
        .doc-sub   { font-size: .78rem; color: #777; margin-top: 3px; }

        /* ── Meta bar ── */
        .meta-bar {
            display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;
            background: #f5ede0; border-left: 4px solid #8B0000;
            padding: 11px 16px; border-radius: 0 6px 6px 0; margin-bottom: 18px;
            font-size: .8rem;
        }
        .meta-label { color: #999; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }
        .meta-value { font-weight: 700; color: #1a1008; font-size: .875rem; }

        /* ── Stat boxes ── */
        .stat-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 18px; }
        .stat-box { border: 1px solid #ddd; border-radius: 6px; padding: 12px 14px; text-align: center; background: #fff; }
        .stat-box.hl { background: #8B0000; border-color: #8B0000; color: #fff; }
        .s-val { font-family: 'EB Garamond', serif; font-size: 1.35rem; font-weight: 700; line-height: 1; margin-bottom: 4px; }
        .s-lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: #999; font-weight: 600; }
        .stat-box.hl .s-lbl { color: rgba(255,255,255,.75); }

        /* ── Approval status trail ── */
        .approval-note {
            background: #fff8f0; border: 1px solid #e0c8a0; border-radius: 6px;
            padding: 10px 14px; font-size: .78rem; color: #5a3010; margin-bottom: 12px;
        }
        .approval-note strong { color: #8B0000; }
        .trail-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .trail-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px;
            font-size: .75rem; font-weight: 700;
        }
        .chip-done    { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        .chip-pending { background: #fff8e1; color: #e65100; border: 1px solid #ffcc80; }
        .chip-reject  { background: #ffebee; color: #8B0000; border: 1px solid #ef9a9a; }
        .chip-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
        .chip-done .chip-dot    { background: #43a047; }
        .chip-pending .chip-dot { background: #ffa000; }
        .chip-reject .chip-dot  { background: #e53935; }

        /* ── Section title ── */
        .sec-title {
            font-family: 'EB Garamond', serif; font-size: .95rem; font-weight: 700;
            color: #8B0000; border-bottom: 1.5px solid #8B0000; padding-bottom: 4px;
            margin: 18px 0 10px; text-transform: uppercase; letter-spacing: .06em;
        }

        /* ── Payroll table ── */
        .payroll-table { width: 100%; border-collapse: collapse; font-size: .72rem; margin-bottom: 18px; }
        .payroll-table thead tr.grp th {
            padding: 5px 6px; font-size: .65rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: .05em; text-align: center;
            border: 1px solid #1a0e08;
        }
        .grp-info   { background: #4a148c; color: #fff; }
        .grp-earn   { background: #1565c0; color: #fff; }
        .grp-ded    { background: #b71c1c; color: #fff; }
        .grp-loan   { background: #e65100; color: #fff; }
        .grp-net    { background: #1b5e20; color: #fff; }
        .payroll-table thead tr.sub th {
            padding: 5px 6px; font-size: .65rem; font-weight: 700;
            border: 1px solid #ccc; white-space: nowrap;
        }
        .sub-info  { background: #ede7f6; color: #4a148c; }
        .sub-earn  { background: #e3f2fd; color: #1565c0; text-align: right; }
        .sub-ded   { background: #ffebee; color: #b71c1c; text-align: right; }
        .sub-loan  { background: #fff3e0; color: #e65100; text-align: right; }
        .sub-net   { background: #e8f5e9; color: #1b5e20; text-align: right; }
        .payroll-table tbody td {
            padding: 5px 7px; border: 1px solid #e0d8cc;
            color: #1a1008; vertical-align: middle; font-size: .72rem;
        }
        .payroll-table tbody tr:nth-child(even) td { background: #faf6f0; }
        .td-r  { text-align: right; }
        .td-c  { text-align: center; }
        .td-ded { color: #8B0000; }
        .td-net { font-weight: 700; color: #1b5e20; }
        .dept-row td { background: #2c1810 !important; color: #f5ede0 !important; font-weight: 700; font-size: .7rem; letter-spacing: .04em; padding: 4px 8px; }
        .totals-row td { background: #f5ede0 !important; font-weight: 800; color: #8B0000 !important; border-top: 2px solid #8B0000; }

        /* ── Remittance table ── */
        .remit-table { max-width: 480px; border-collapse: collapse; font-size: .8rem; margin-bottom: 18px; }
        .remit-table th { background: #2c1810; color: #fff; padding: 7px 10px; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; border: 1px solid #1a0e08; }
        .remit-table td { padding: 6px 10px; border: 1px solid #e0d8cc; }
        .remit-cat td { background: #f0e8da !important; font-weight: 700; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #5a3010; }
        .remit-total td { background: #8B0000 !important; color: #fff !important; font-weight: 800; border-color: #6a0000; }

        /* ── Signature ── */
        .sig-section { margin-top: 28px; border-top: 2px solid #2c1810; padding-top: 22px; }
        .sig-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 24px; }
        .sig-block { text-align: center; }
        .sig-line { border-bottom: 1.5px solid #1a1008; height: 44px; margin-bottom: 6px; position: relative; }
        .sig-block.certified .sig-line::after {
            content: '✓ CERTIFIED'; position: absolute; bottom: 6px; left: 50%;
            transform: translateX(-50%); font-size: .62rem; font-weight: 800;
            color: #1b5e20; letter-spacing: .08em; white-space: nowrap;
            border: 1.5px solid #1b5e20; padding: 1px 5px; border-radius: 3px;
        }
        .sig-block.mayor-signed .sig-line::after {
            content: '✓ APPROVED'; position: absolute; bottom: 6px; left: 50%;
            transform: translateX(-50%); font-size: .62rem; font-weight: 800;
            color: #8B0000; letter-spacing: .08em; white-space: nowrap;
            border: 1.5px solid #8B0000; padding: 1px 5px; border-radius: 3px;
        }
        .sig-name  { font-weight: 700; font-size: .82rem; }
        .sig-title { font-size: .7rem; color: #888; margin-top: 2px; }
        .sig-date  { font-size: .68rem; color: #aaa; margin-top: 2px; }

        /* ── Footer ── */
        .doc-footer {
            margin-top: 18px; padding-top: 10px; border-top: 1px solid #ddd;
            display: flex; justify-content: space-between;
            font-size: .65rem; color: #bbb;
        }

        /* ── Remarks modal ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.5); z-index: 999;
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #fff; border-radius: 10px; padding: 28px;
            width: 420px; max-width: 95vw;
            box-shadow: 0 10px 40px rgba(0,0,0,.25);
            font-family: 'Source Sans 3', sans-serif;
        }
        .modal-box h5 { margin: 0 0 6px; font-size: 1rem; font-weight: 700; }
        .modal-box p  { font-size: .85rem; color: #666; margin-bottom: 16px; }
        .modal-box textarea {
            width: 100%; border: 1.5px solid #ddd; border-radius: 6px;
            padding: 10px 12px; font-size: .875rem; font-family: inherit;
            resize: vertical; min-height: 90px;
            box-sizing: border-box;
        }
        .modal-box textarea:focus { outline: none; border-color: #8B0000; }
        .modal-footer-btns { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }
        .modal-cancel { background: #f3f4f6; color: #333; border: none; border-radius: 6px; padding: 8px 16px; font-size: .82rem; font-weight: 600; cursor: pointer; }
        .modal-confirm-approve { background: #2e7d32; color: #fff; border: none; border-radius: 6px; padding: 8px 18px; font-size: .82rem; font-weight: 700; cursor: pointer; }
        .modal-confirm-reject  { background: #8B0000; color: #fff; border: none; border-radius: 6px; padding: 8px 18px; font-size: .82rem; font-weight: 700; cursor: pointer; }

        /* ── Print ── */
        @media print {
            body { background: white; padding: 0; }
            .screen-toolbar, .alert-bar, .modal-overlay { display: none !important; }
            .document { box-shadow: none; border: none; padding: 16px 24px; max-width: 100%; }
            .payroll-table { font-size: .62rem; }
            .payroll-table tbody td, .payroll-table thead th { padding: 3px 4px !important; }
            .grp-info, .grp-earn, .grp-ded, .grp-loan, .grp-net,
            .sub-info, .sub-earn, .sub-ded, .sub-loan, .sub-net,
            .dept-row td, .totals-row td, .remit-total td, .remit-cat td,
            .stat-box.hl { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sig-section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<!-- Screen toolbar -->
<div class="screen-toolbar">
    <div class="toolbar-left">
        <a href="approval_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <span style="color:#ccc;">|</span>
        <span class="toolbar-title">Payroll Summary — <?php echo htmlspecialchars($approval['cycle_name']); ?></span>
    </div>
    <div class="toolbar-right">
        <button class="btn-toolbar btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print / Save PDF
        </button>
        <?php if ($can_act): ?>
            <button class="btn-toolbar btn-approve" onclick="openModal('approve')">
                <i class="fas fa-check"></i> Approve
            </button>
            <button class="btn-toolbar btn-reject" onclick="openModal('reject')">
                <i class="fas fa-times"></i> Reject
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Alerts -->
<?php if (isset($success_message)): ?>
    <div class="alert-bar alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        <a href="approval_dashboard.php" style="margin-left:auto; color:inherit; font-weight:700;">← Back to Dashboard</a>
    </div>
<?php endif; ?>
<?php if (isset($error_message)): ?>
    <div class="alert-bar alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- ══ DOCUMENT ══ -->
<div class="document">

    <!-- Header -->
    <div class="doc-header">
        <div class="republic">Republic of the Philippines</div>
        <div class="province"><!-- EDIT: Province Name Here --></div>
        <div class="lgu-name">Municipality of <!-- EDIT: Municipality Name Here --></div>
        <div class="doc-title">Payroll Summary Report</div>
        <div class="doc-sub">For Review and Approval by the Honorable Municipal Mayor</div>
    </div>

    <!-- Meta bar -->
    <div class="meta-bar">
        <div>
            <div class="meta-label">Payroll Cycle</div>
            <div class="meta-value"><?php echo htmlspecialchars($approval['cycle_name']); ?></div>
        </div>
        <div>
            <div class="meta-label">Pay Period</div>
            <div class="meta-value"><?php echo $period_str; ?></div>
        </div>
        <div>
            <div class="meta-label">Date Released</div>
            <div class="meta-value">
                <?php echo !empty($approval['pay_date']) ? date('F j, Y', strtotime($approval['pay_date'])) : '—'; ?>
            </div>
        </div>
        <div>
            <div class="meta-label">Generated</div>
            <div class="meta-value"><?php echo date('F j, Y \a\t g:i A'); ?></div>
        </div>
    </div>

    <!-- Stat boxes -->
    <div class="stat-row">
        <div class="stat-box">
            <div class="s-val"><?php echo $emp_count; ?></div>
            <div class="s-lbl">Employees</div>
        </div>
        <div class="stat-box">
            <div class="s-val">₱<?php echo number_format($t_gross, 0); ?></div>
            <div class="s-lbl">Total Gross Pay</div>
        </div>
        <div class="stat-box">
            <div class="s-val">₱<?php echo number_format($t_ded, 0); ?></div>
            <div class="s-lbl">Total Deductions</div>
        </div>
        <div class="stat-box hl">
            <div class="s-val">₱<?php echo number_format($t_net, 0); ?></div>
            <div class="s-lbl">Total Net Pay</div>
        </div>
    </div>

    <!-- Approval trail -->
    <div class="approval-note">
        <strong>Approval Status:</strong>
        <?php if ($approval['status'] === 'Fully_Approved'): ?>
            This payroll has been fully approved by the Municipal Mayor and is cleared for disbursement.
        <?php elseif ($approval['status'] === 'Accounting_Approved'): ?>
            This payroll has been certified by the Municipal Accountant and is now submitted to the Office of the Municipal Mayor for final authorization.
        <?php elseif ($approval['status'] === 'Rejected'): ?>
            This payroll request has been rejected. Please review and resubmit.
        <?php else: ?>
            This payroll is pending review by the Municipal Accountant.
        <?php endif; ?>
    </div>
    <div class="trail-row">
        <!-- Step 1: HR -->
        <span class="trail-chip chip-done">
            <span class="chip-dot"></span>
            HR Submitted — <?php echo date('M j, Y', strtotime($approval['requested_at'])); ?>
            <?php if (trim($approval['submitted_by_name'])): ?>
                &nbsp;·&nbsp; <?php echo htmlspecialchars(trim($approval['submitted_by_name'])); ?>
            <?php endif; ?>
        </span>
        <!-- Step 2: Accounting -->
        <?php if ($approval['acct_action'] === 'Approved'): ?>
            <span class="trail-chip chip-done">
                <span class="chip-dot"></span>
                Accounting Certified — <?php echo date('M j, Y', strtotime($approval['acct_date'])); ?>
                <?php if (trim($approval['acct_by'])): ?>&nbsp;·&nbsp; <?php echo htmlspecialchars(trim($approval['acct_by'])); ?><?php endif; ?>
            </span>
        <?php elseif ($approval['acct_action'] === 'Rejected'): ?>
            <span class="trail-chip chip-reject">
                <span class="chip-dot"></span>
                Accounting Rejected — <?php echo date('M j, Y', strtotime($approval['acct_date'])); ?>
            </span>
        <?php else: ?>
            <span class="trail-chip chip-pending">
                <span class="chip-dot"></span>
                Accounting Review — Pending
            </span>
        <?php endif; ?>
        <!-- Step 3: Mayor -->
        <?php if ($approval['mayor_action'] === 'Approved'): ?>
            <span class="trail-chip chip-done">
                <span class="chip-dot"></span>
                Mayor Approved — <?php echo date('M j, Y', strtotime($approval['mayor_date'])); ?>
                <?php if (trim($approval['mayor_by'])): ?>&nbsp;·&nbsp; <?php echo htmlspecialchars(trim($approval['mayor_by'])); ?><?php endif; ?>
            </span>
        <?php elseif ($approval['mayor_action'] === 'Rejected'): ?>
            <span class="trail-chip chip-reject">
                <span class="chip-dot"></span>
                Mayor Rejected — <?php echo date('M j, Y', strtotime($approval['mayor_date'])); ?>
            </span>
        <?php else: ?>
            <span class="trail-chip chip-pending">
                <span class="chip-dot"></span>
                Mayor's Approval — Pending
            </span>
        <?php endif; ?>
    </div>

    <!-- SECTION I: General Payroll -->
    <div class="sec-title">I. General Payroll Sheet</div>
    <?php if (!empty($transactions)): ?>
    <table class="payroll-table">
        <thead>
            <tr class="grp">
                <th class="grp-info" colspan="5">Employee Information</th>
                <th class="grp-earn" colspan="3">Earnings</th>
                <th class="grp-ded"  colspan="4">Mandatory Deductions</th>
                <th class="grp-loan" colspan="2">Loans</th>
                <th class="grp-net"  colspan="2">Summary</th>
            </tr>
            <tr class="sub">
                <th class="sub-info">Item No.</th>
                <th class="sub-info">Emp #</th>
                <th class="sub-info">Name</th>
                <th class="sub-info">Position</th>
                <th class="sub-info td-c">SG–Step</th>
                <th class="sub-earn">Monthly Rate</th>
                <th class="sub-earn">Basic Pay</th>
                <th class="sub-earn">Allowances</th>
                <th class="sub-ded">GSIS</th>
                <th class="sub-ded">PhilHealth</th>
                <th class="sub-ded">Pag-IBIG</th>
                <th class="sub-ded">W/Tax</th>
                <th class="sub-loan">GSIS Loan</th>
                <th class="sub-loan">Pag-IBIG Loan</th>
                <th class="sub-net">Total Ded.</th>
                <th class="sub-net">Net Pay</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $prev_dept = null;
        foreach ($transactions as $row):
            if ($row['department_name'] !== $prev_dept):
                $prev_dept = $row['department_name'];
        ?>
        <tr class="dept-row">
            <td colspan="16"><?php echo htmlspecialchars($row['department_name'] ?? 'Unassigned Department'); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td class="td-c"><?php echo htmlspecialchars($row['item_number']); ?></td>
            <td><?php echo htmlspecialchars($row['employee_number']); ?></td>
            <td><?php echo htmlspecialchars(trim(($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? ''))); ?></td>
            <td><?php echo htmlspecialchars($row['position'] ?? 'N/A'); ?></td>
            <td class="td-c"><?php echo $row['salary_grade'] ? 'SG-' . $row['salary_grade'] . ($row['salary_step'] ? '/' . $row['salary_step'] : '') : '—'; ?></td>
            <td class="td-r">₱<?php echo number_format($row['monthly_rate'], 2); ?></td>
            <td class="td-r">₱<?php echo number_format($row['basic_earned'], 2); ?></td>
            <td class="td-r">₱<?php echo number_format($row['allowance_earned'], 2); ?></td>
            <td class="td-r td-ded"><?php echo number_format($row['gsis'], 2); ?></td>
            <td class="td-r td-ded"><?php echo number_format($row['philhealth'], 2); ?></td>
            <td class="td-r td-ded"><?php echo number_format($row['pagibig'], 2); ?></td>
            <td class="td-r td-ded"><?php echo number_format($row['wtax'], 2); ?></td>
            <td class="td-r td-ded"><?php echo number_format($row['gsis_loan_period'], 2); ?></td>
            <td class="td-r td-ded"><?php echo number_format($row['pagibig_loan_period'], 2); ?></td>
            <td class="td-r td-ded"><strong><?php echo number_format($row['total_ded'], 2); ?></strong></td>
            <td class="td-r td-net">₱<?php echo number_format($row['net_pay'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="totals-row">
            <td colspan="5" class="td-r" style="padding-right:10px;">GRAND TOTALS (<?php echo $emp_count; ?> employees):</td>
            <td class="td-r">₱<?php echo number_format($t_gross, 2); ?></td>
            <td class="td-r">₱<?php echo number_format($t_basic, 2); ?></td>
            <td class="td-r">₱<?php echo number_format($t_allow, 2); ?></td>
            <td class="td-r"><?php echo number_format($t_gsis, 2); ?></td>
            <td class="td-r"><?php echo number_format($t_ph, 2); ?></td>
            <td class="td-r"><?php echo number_format($t_pi, 2); ?></td>
            <td class="td-r"><?php echo number_format($t_tax, 2); ?></td>
            <td class="td-r"><?php echo number_format($t_gsis_ln, 2); ?></td>
            <td class="td-r"><?php echo number_format($t_pi_ln, 2); ?></td>
            <td class="td-r">₱<?php echo number_format($t_ded, 2); ?></td>
            <td class="td-r">₱<?php echo number_format($t_net, 2); ?></td>
        </tr>
        </tbody>
    </table>
    <?php else: ?>
        <p style="color:#999; font-size:.85rem;">No payroll transactions found for this cycle.</p>
    <?php endif; ?>

    <!-- SECTION II: Remittance -->
    <div class="sec-title">II. Remittance Summary</div>
    <table class="remit-table">
        <thead>
            <tr>
                <th style="width:60%;">Remittance Type</th>
                <th style="text-align:right;">Amount to Remit</th>
            </tr>
        </thead>
        <tbody>
            <tr class="remit-cat"><td colspan="2">Premiums</td></tr>
            <tr><td>GSIS Personal Share</td><td style="text-align:right;">₱<?php echo number_format($t_gsis, 2); ?></td></tr>
            <tr><td>PhilHealth Contributions</td><td style="text-align:right;">₱<?php echo number_format($t_ph, 2); ?></td></tr>
            <tr><td>Pag-IBIG (HDMF) Contributions</td><td style="text-align:right;">₱<?php echo number_format($t_pi, 2); ?></td></tr>
            <tr class="remit-cat"><td colspan="2">Taxes</td></tr>
            <tr><td>Withholding Tax (BIR)</td><td style="text-align:right;">₱<?php echo number_format($t_tax, 2); ?></td></tr>
            <tr class="remit-cat"><td colspan="2">Loans</td></tr>
            <tr><td>GSIS Loans</td><td style="text-align:right;">₱<?php echo number_format($t_gsis_ln, 2); ?></td></tr>
            <tr><td>Pag-IBIG (HDMF) Loans</td><td style="text-align:right;">₱<?php echo number_format($t_pi_ln, 2); ?></td></tr>
            <tr class="remit-total">
                <td><strong>GRAND TOTAL</strong></td>
                <td style="text-align:right;"><strong>₱<?php echo number_format($t_ded, 2); ?></strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Signature block -->
    <div class="sig-section">
        <div class="sig-grid">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo htmlspecialchars(strtoupper($_SESSION['username'] ?? 'Payroll Officer')); ?></div>
                <div class="sig-title">Prepared By: Payroll Officer</div>
                <div class="sig-date">Date: <?php echo date('F j, Y'); ?></div>
            </div>
            <div class="sig-block <?php echo $approval['acct_action'] === 'Approved' ? 'certified' : ''; ?>">
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo $approval['acct_by'] ? htmlspecialchars(strtoupper(trim($approval['acct_by']))) : 'Municipal Accountant'; ?></div>
                <div class="sig-title">Certified Correct: Municipal Accountant</div>
                <div class="sig-date">Date: <?php echo $approval['acct_date'] ? date('F j, Y', strtotime($approval['acct_date'])) : '____________________'; ?></div>
            </div>
            <div class="sig-block <?php echo $approval['mayor_action'] === 'Approved' ? 'mayor-signed' : ''; ?>">
                <div class="sig-line"></div>
                <div class="sig-name"><?php echo $approval['mayor_by'] ? htmlspecialchars(strtoupper(trim($approval['mayor_by']))) : 'Municipal Mayor'; ?></div>
                <div class="sig-title">Approved By: Municipal Mayor</div>
                <div class="sig-date">Date: <?php echo $approval['mayor_date'] ? date('F j, Y', strtotime($approval['mayor_date'])) : '____________________'; ?></div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="doc-footer">
        <span>Municipality of <!-- EDIT: Municipality Name --> &nbsp;|&nbsp; HR Management System</span>
        <span>Generated: <?php echo date('F j, Y \a\t g:i A'); ?> &nbsp;|&nbsp; Approval ID: #<?php echo $approval_id; ?></span>
    </div>

</div><!-- /document -->

<!-- Approve/Reject Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-box">
        <h5 id="modalTitle">Approve Payroll</h5>
        <p id="modalDesc">You are about to approve this payroll cycle.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="approval_id" value="<?php echo $approval_id; ?>">
            <input type="hidden" name="action" id="modalAction" value="approve">
            <textarea name="remarks" id="modalRemarks" placeholder="Add remarks (optional for approval, required for rejection)..."></textarea>
            <div class="modal-footer-btns">
                <button type="button" class="modal-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="modal-confirm-approve" id="modalConfirmBtn">
                    <i class="fas fa-check"></i> Confirm Approve
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(action) {
    document.getElementById('modalAction').value = action;
    document.getElementById('modalRemarks').value = '';
    const btn = document.getElementById('modalConfirmBtn');
    if (action === 'approve') {
        document.getElementById('modalTitle').textContent = 'Approve Payroll';
        document.getElementById('modalDesc').textContent  = 'You are about to approve this payroll. Once approved, all transactions will be marked as Paid.';
        btn.className   = 'modal-confirm-approve';
        btn.innerHTML   = '<i class="fas fa-check"></i> Confirm Approve';
    } else {
        document.getElementById('modalTitle').textContent = 'Reject Payroll';
        document.getElementById('modalDesc').textContent  = 'You are about to reject this payroll. Please provide a reason in the remarks field.';
        btn.className   = 'modal-confirm-reject';
        btn.innerHTML   = '<i class="fas fa-times"></i> Confirm Reject';
    }
    document.getElementById('actionModal').classList.add('open');
}
function closeModal() {
    document.getElementById('actionModal').classList.remove('open');
}
document.getElementById('actionModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>