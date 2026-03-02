<?php
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

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // FIX: Sanitize and default all numeric fields to prevent empty-string DB errors
                    $exit_id                 = intval($_POST['exit_id']);
                    $employee_id             = intval($_POST['employee_id']);
                    $last_working_day        = $_POST['last_working_day'];
                    $final_salary            = floatval($_POST['final_salary'] ?? 0);
                    $severance_pay           = floatval($_POST['severance_pay'] ?? 0);
                    $unused_leave_payout     = floatval($_POST['unused_leave_payout'] ?? 0);
                    $deductions              = floatval($_POST['deductions'] ?? 0);
                    $final_settlement_amount = floatval($_POST['final_settlement_amount'] ?? 0);
                    $payment_date            = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;
                    // FIX: payment_method was always empty string — default to 'Bank Transfer' if blank
                    $payment_method          = !empty($_POST['payment_method']) ? $_POST['payment_method'] : 'Bank Transfer';
                    $status                  = !empty($_POST['status']) ? $_POST['status'] : 'Pending';
                    $notes                   = $_POST['notes'] ?? '';

                    // FIX: Re-compute final_settlement_amount server-side as safety net
                    // in case JS didn't fire before form submission
                    $computed_net = $final_salary + $severance_pay + $unused_leave_payout - $deductions;
                    // Use server-computed value if the client-sent value is 0 but components are non-zero
                    if ($final_settlement_amount == 0 && $computed_net != 0) {
                        $final_settlement_amount = $computed_net;
                    }

                    $stmt = $pdo->prepare("INSERT INTO settlements (exit_id, employee_id, last_working_day, final_salary, severance_pay, unused_leave_payout, deductions, final_settlement_amount, payment_date, payment_method, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $exit_id,
                        $employee_id,
                        $last_working_day,
                        $final_salary,
                        $severance_pay,
                        $unused_leave_payout,
                        $deductions,
                        $final_settlement_amount,
                        $payment_date,
                        $payment_method,
                        $status,
                        $notes
                    ]);
                    $_SESSION['message'] = "Settlement added successfully!";
                    $_SESSION['messageType'] = "success";
                    header("Location: settlements.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error adding settlement: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: settlements.php");
                    exit();
                }
                break;

            case 'update_status':
                try {
                    $processed_date = null;
                    if ($_POST['status'] === 'Completed') {
                        $processed_date = date('Y-m-d');
                    }
                    $stmt = $pdo->prepare("UPDATE settlements SET status=?, processed_date=? WHERE settlement_id=?");
                    $stmt->execute([$_POST['status'], $processed_date, $_POST['settlement_id']]);
                    $_SESSION['message'] = "Settlement status updated successfully!";
                    $_SESSION['messageType'] = "success";
                    header("Location: settlements.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error updating status: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: settlements.php");
                    exit();
                }
                break;

            case 'view_details':
                try {
                    $stmt = $pdo->prepare("INSERT INTO settlement_access_logs (settlement_id, user_id, accessed_at) VALUES (?, ?, NOW())");
                    $stmt->execute([$_POST['settlement_id'], $_SESSION['user_id'] ?? 'unknown']);
                } catch (PDOException $e) {}
                break;
        }
    }
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// Fetch settlements
$stmt = $pdo->query("
    SELECT s.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number, ep.work_email, ep.current_salary, ep.hire_date,
        jr.title as job_title, jr.department,
        e.exit_type, e.exit_date
    FROM settlements s
    LEFT JOIN employee_profiles ep ON s.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN exits e ON s.exit_id = e.exit_id
    ORDER BY s.settlement_id DESC
");
$settlements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown
$stmt = $pdo->query("
    SELECT e.exit_id, e.employee_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number, ep.current_salary, ep.hire_date,
        e.exit_type, e.exit_date
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    WHERE e.exit_id NOT IN (SELECT exit_id FROM settlements)
    ORDER BY e.exit_date DESC
");
$availableExits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees
$stmt = $pdo->query("
    SELECT ep.employee_id, CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number, ep.current_salary, ep.hire_date
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settlement Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --primary: #E91E63;
            --primary-light: #F06292;
            --primary-dark: #C2185B;
            --primary-lighter: #F8BBD0;
            --primary-pale: #FCE4EC;
            --success: #00C853;
            --success-pale: #E8F5E9;
            --warning: #FF6F00;
            --warning-pale: #FFF8E1;
            --info: #0288D1;
            --info-pale: #E1F5FE;
            --danger: #D32F2F;
            --danger-pale: #FFEBEE;
            --dark: #1A1A2E;
            --mid: #374151;
            --muted: #6B7280;
            --border: #E5E7EB;
            --surface: #FFFFFF;
            --bg: #FDF2F5;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--dark);
        }

        .container-fluid { padding: 0; }
        .row { margin: 0; }

        .main-content {
            padding: 28px 32px;
            background: var(--bg);
            min-height: 100vh;
        }

        /* ── Page Header ── */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header-left h2 {
            font-size: 26px;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 4px 0;
        }

        .page-header-left p {
            color: var(--muted);
            font-size: 14px;
            margin: 0;
        }

        /* ── Summary Cards ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--surface);
            border-radius: 14px;
            padding: 20px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(233,30,99,0.10);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }

        .stat-card.pink::before { background: var(--primary); }
        .stat-card.green::before { background: var(--success); }
        .stat-card.orange::before { background: var(--warning); }
        .stat-card.blue::before { background: var(--info); }

        .stat-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .stat-card.pink .stat-icon { background: var(--primary-pale); }
        .stat-card.green .stat-icon { background: var(--success-pale); }
        .stat-card.orange .stat-icon { background: var(--warning-pale); }
        .stat-card.blue .stat-icon { background: var(--info-pale); }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--dark);
            font-family: 'DM Mono', monospace;
        }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* ── Controls ── */
        .controls {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 220px;
            max-width: 380px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            background: var(--surface);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(233,30,99,0.10);
        }

        .search-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
        }

        /* ── Buttons ── */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(233,30,99,0.30); color: white; }

        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #00A846; transform: translateY(-1px); color: white; }

        .btn-info { background: var(--info); color: white; }
        .btn-info:hover { background: #0277BD; transform: translateY(-1px); color: white; }

        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #E65100; transform: translateY(-1px); color: white; }

        .btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--mid); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-pale); }

        .btn-ghost { background: transparent; color: var(--muted); padding: 8px 12px; }
        .btn-ghost:hover { background: var(--bg); color: var(--dark); }

        .btn-sm { padding: 7px 13px; font-size: 13px; border-radius: 8px; }

        /* ── Table ── */
        .table-card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table th {
            background: #FAFAFA;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .table td {
            padding: 14px 16px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
        }

        .table tbody tr:last-child td { border-bottom: none; }

        .table tbody tr:hover { background: #FAFAFA; }

        .emp-info strong { display: block; font-size: 14px; color: var(--dark); }
        .emp-info small { color: var(--muted); font-size: 12px; }

        /* ── Status Badges ── */
        .badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: inline-block;
        }

        .badge-pending { background: var(--warning-pale); color: var(--warning); }
        .badge-processing { background: var(--info-pale); color: var(--info); }
        .badge-completed { background: var(--success-pale); color: #1B5E20; }

        .amount-hidden {
            font-family: 'DM Mono', monospace;
            background: #F3F4F6;
            color: transparent;
            text-shadow: 0 0 8px rgba(0,0,0,0.4);
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 13px;
            user-select: none;
            cursor: not-allowed;
            letter-spacing: 2px;
        }

        /* ── Modals ── */
        .modal-overlay {
            display: none;
            position: fixed;
            z-index: 1000;
            inset: 0;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(4px);
            align-items: flex-start;
            justify-content: center;
            padding: 24px 16px;
            overflow-y: auto;
        }

        .modal-overlay.active { display: flex; }

        .modal-box {
            background: var(--surface);
            border-radius: 18px;
            width: 100%;
            max-width: 760px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0,0,0,0.20);
            animation: modalIn 0.25s ease;
            margin: auto;
        }

        .modal-box.wide { max-width: 960px; }
        .modal-box.narrow { max-width: 500px; }

        @keyframes modalIn {
            from { transform: translateY(-20px) scale(0.98); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .modal-head {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-head h3 { margin: 0; font-size: 18px; font-weight: 700; }
        .modal-head p { margin: 4px 0 0; font-size: 13px; opacity: 0.85; }

        .modal-close {
            background: rgba(255,255,255,0.20);
            border: none;
            color: white;
            width: 32px; height: 32px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            transition: background 0.2s;
            display: flex; align-items: center; justify-content: center;
        }

        .modal-close:hover { background: rgba(255,255,255,0.35); }

        .modal-body { padding: 28px; }

        /* ── Tabs (inside Final Pay modal) ── */
        .tab-bar {
            display: flex;
            gap: 4px;
            background: #F3F4F6;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 24px;
        }

        .tab-btn {
            flex: 1;
            padding: 9px 14px;
            border: none;
            background: transparent;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .tab-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* ── Form Styles ── */
        .form-section {
            background: #FAFAFA;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .form-section-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        .form-grid.cols-1 { grid-template-columns: 1fr; }

        .form-group { margin-bottom: 0; }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--mid);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-group label .req { color: var(--primary); margin-left: 2px; }

        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            background: white;
            color: var(--dark);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(233,30,99,0.10);
        }

        .form-control:read-only, .form-control[readonly] {
            background: #F9FAFB;
            color: var(--muted);
            cursor: not-allowed;
        }

        .form-control.computed {
            background: var(--primary-pale);
            color: var(--primary-dark);
            font-weight: 600;
            font-family: 'DM Mono', monospace;
            cursor: default;
        }

        /* ── Calculation Summary ── */
        .calc-summary {
            background: linear-gradient(135deg, var(--dark) 0%, #2D3748 100%);
            border-radius: 14px;
            padding: 22px;
            color: white;
            margin-top: 20px;
        }

        .calc-summary-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
            margin-bottom: 16px;
        }

        .calc-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            font-size: 14px;
        }

        .calc-line:last-child { border-bottom: none; }

        .calc-line.subtotal {
            border-top: 1px solid rgba(255,255,255,0.2);
            margin-top: 8px;
            padding-top: 14px;
            font-weight: 600;
            color: #86EFAC;
        }

        .calc-line.deduction .calc-amt { color: #FCA5A5; }

        .calc-line.total {
            border-top: 2px solid rgba(255,255,255,0.3);
            margin-top: 10px;
            padding-top: 16px;
            font-size: 18px;
            font-weight: 700;
        }

        .calc-line.total .calc-amt { color: #86EFAC; font-family: 'DM Mono', monospace; }

        .calc-label { opacity: 0.85; }
        .calc-amt { font-family: 'DM Mono', monospace; font-weight: 600; }

        /* ── Leave Monetization Calculator ── */
        .leave-breakdown {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 16px;
        }

        .leave-breakdown-header {
            background: #F8FAFC;
            padding: 12px 16px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 8px;
            border-bottom: 1px solid var(--border);
        }

        .leave-breakdown-row {
            padding: 12px 16px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 8px;
            align-items: center;
            border-bottom: 1px solid #F3F4F6;
            font-size: 14px;
            transition: background 0.15s;
        }

        .leave-breakdown-row:last-child { border-bottom: none; }
        .leave-breakdown-row:hover { background: var(--primary-pale); }

        .leave-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }

        .leave-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .leave-payout-total {
            background: linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%);
            color: white;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ── Detail View ── */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .detail-item {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }

        .detail-item:nth-child(odd) { border-right: 1px solid var(--border); }

        .detail-item-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .detail-item-value {
            font-size: 14px;
            color: var(--dark);
            font-weight: 500;
        }

        .detail-item-value.mono {
            font-family: 'DM Mono', monospace;
            font-size: 15px;
            color: var(--primary-dark);
            font-weight: 700;
        }

        .sensitive-section {
            background: linear-gradient(135deg, #FFF8E1 0%, #FFFDE7 100%);
            border: 1.5px solid #FFD54F;
            border-radius: 12px;
            padding: 14px 18px;
            margin: 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sensitive-section-icon { font-size: 22px; }

        .sensitive-section-text strong { display: block; color: #E65100; font-size: 13px; }
        .sensitive-section-text span { color: #BF360C; font-size: 12px; }

        .fin-breakdown-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .fin-breakdown-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            border-bottom: 1px solid #F3F4F6;
            font-size: 14px;
        }

        .fin-breakdown-row:last-child { border-bottom: none; }
        .fin-breakdown-row.total-row {
            background: var(--primary-pale);
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .fin-breakdown-row .amount { font-family: 'DM Mono', monospace; font-weight: 600; }
        .fin-breakdown-row .amount.positive { color: #1B5E20; }
        .fin-breakdown-row .amount.negative { color: var(--danger); }
        .fin-breakdown-row .amount.total { color: var(--primary-dark); font-size: 17px; }

        /* ── Alert ── */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success { background: var(--success-pale); color: #1B5E20; border: 1px solid #A5D6A7; }
        .alert-error { background: var(--danger-pale); color: var(--danger); border: 1px solid #EF9A9A; }

        .info-banner {
            background: linear-gradient(135deg, var(--primary-pale) 0%, #FDE8F0 100%);
            border: 1px solid var(--primary-lighter);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .info-banner-icon { font-size: 20px; flex-shrink: 0; margin-top: 1px; }
        .info-banner-text strong { display: block; color: var(--primary-dark); font-size: 13px; margin-bottom: 2px; }
        .info-banner-text span { color: var(--mid); font-size: 13px; }

        .no-data {
            text-align: center;
            padding: 60px 30px;
            color: var(--muted);
        }

        .no-data-icon { font-size: 48px; margin-bottom: 12px; }
        .no-data h3 { font-size: 18px; color: var(--mid); margin-bottom: 6px; }
        .no-data p { font-size: 14px; }

        /* Divider */
        .modal-divider {
            height: 1px;
            background: var(--border);
            margin: 20px 0;
        }

        .action-bar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            margin-top: 20px;
        }

        /* Tooltip-style helper text */
        .field-hint {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* Employee info card (in modal) */
        .emp-card {
            background: linear-gradient(135deg, var(--primary-pale) 0%, #FDE8F0 100%);
            border: 1px solid var(--primary-lighter);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .emp-card-avatar {
            width: 44px; height: 44px;
            background: var(--primary);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .emp-card-info strong { display: block; font-size: 16px; color: var(--dark); }
        .emp-card-info span { font-size: 13px; color: var(--muted); }

        .emp-card-salary {
            margin-left: auto;
            text-align: right;
        }

        .emp-card-salary strong { display: block; font-size: 18px; font-family: 'DM Mono', monospace; color: var(--primary-dark); }
        .emp-card-salary span { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; }

        /* Progress ring for leave balance */
        .leave-progress {
            position: relative;
            width: 52px; height: 52px;
            flex-shrink: 0;
        }

        .leave-progress svg {
            transform: rotate(-90deg);
        }

        .leave-progress-label {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
        }

        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .form-grid.cols-3 { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
            .detail-item:nth-child(odd) { border-right: none; }
            .main-content { padding: 16px; }
            .leave-breakdown-header, .leave-breakdown-row {
                grid-template-columns: 2fr 1fr 1fr;
            }
            .leave-breakdown-header span:last-child,
            .leave-breakdown-row span:last-child { display: none; }
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 32px;
            right: 32px;
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(233,30,99,0.40);
            transition: all 0.3s ease;
            z-index: 999;
            border: none;
        }

        .fab:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 12px 32px rgba(233,30,99,0.50);
        }

        .fab:active {
            transform: scale(0.95);
        }

        .fab-tooltip {
            position: absolute;
            right: 76px;
            background: var(--dark);
            color: white;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }

        .fab:hover .fab-tooltip {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .fab {
                bottom: 20px;
                right: 20px;
                width: 56px;
                height: 56px;
                font-size: 20px;
            }
        }

        /* FIX: Validation error highlight */
        .form-control.is-invalid {
            border-color: var(--danger) !important;
            box-shadow: 0 0 0 3px rgba(211,47,47,0.10) !important;
        }

        .validation-msg {
            background: var(--danger-pale);
            border: 1px solid #EF9A9A;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 13px;
            color: var(--danger);
            font-weight: 500;
            display: none;
        }

        .validation-msg.show { display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left">
                    <h2>💼 Settlement Management</h2>
                    <p>Manage final pay computations, salary & benefits, and leave monetization</p>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button class="btn btn-outline" onclick="openFinalPayCalculator()">🧮 Final Pay Calculator</button>
                    <button class="btn btn-primary" onclick="openAddModal()" style="font-size:15px; padding:12px 24px;">
                        <i class="fas fa-plus-circle"></i> New Settlement
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= $messageType === 'success' ? '✅' : '❌' ?>
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Summary Stats -->
            <div class="stats-row">
                <?php
                    $total = count($settlements);
                    $pending = count(array_filter($settlements, fn($s) => $s['status'] === 'Pending'));
                    $processing = count(array_filter($settlements, fn($s) => $s['status'] === 'Processing'));
                    $completed = count(array_filter($settlements, fn($s) => $s['status'] === 'Completed'));
                    $totalPayout = array_sum(array_column($settlements, 'final_settlement_amount'));
                ?>
                <div class="stat-card pink">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value"><?= $total ?></div>
                    <div class="stat-label">Total Settlements</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-value"><?= $pending ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-icon">🔄</div>
                    <div class="stat-value"><?= $processing ?></div>
                    <div class="stat-label">Processing</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?= $completed ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <!-- Info Banner -->
            <div class="info-banner">
                <div class="info-banner-icon">🔒</div>
                <div class="info-banner-text">
                    <strong>Sensitive Data Protection Active</strong>
                    <span>Final settlement amounts are hidden in the list view. Click "View Details" to access financial information. All access is securely logged.</span>
                </div>
            </div>

            <!-- Controls -->
            <div class="controls">
                <div class="search-box">
                    <span class="search-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchInput" placeholder="Search by employee name or number…">
                </div>
                <select id="statusFilter" class="form-control" style="width:auto; padding: 10px 14px; border-radius:10px; border: 1.5px solid var(--border);">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Processing">Processing</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>

            <!-- Table -->
            <div class="table-card">
                <table class="table" id="settlementTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Exit Type</th>
                            <th>Last Working Day</th>
                            <th>Final Amount</th>
                            <th>Payment Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="settlementTableBody">
                        <?php foreach ($settlements as $s): ?>
                        <tr data-status="<?= $s['status'] ?>">
                            <td><strong style="font-family:'DM Mono',monospace;color:var(--muted);">#<?= $s['settlement_id'] ?></strong></td>
                            <td>
                                <div class="emp-info">
                                    <strong><?= htmlspecialchars($s['employee_name']) ?></strong>
                                    <small><?= htmlspecialchars($s['employee_number']) ?></small>
                                </div>
                            </td>
                            <td><small><?= htmlspecialchars($s['department'] ?? '—') ?></small></td>
                            <td><small><?= htmlspecialchars($s['exit_type']) ?></small></td>
                            <td><small><?= date('M d, Y', strtotime($s['last_working_day'])) ?></small></td>
                            <td><span class="amount-hidden">₱•••••••</span></td>
                            <td><small><?= $s['payment_date'] ? date('M d, Y', strtotime($s['payment_date'])) : '—' ?></small></td>
                            <td><span class="badge badge-<?= strtolower($s['status']) ?>"><?= $s['status'] ?></span></td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="viewSettlementDetails(<?= $s['settlement_id'] ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="updateStatus(<?= $s['settlement_id'] ?>, '<?= $s['status'] ?>')">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($settlements)): ?>
                <div class="no-data">
                    <div class="no-data-icon">💼</div>
                    <h3>No settlements yet</h3>
                    <p>Add your first settlement to get started.</p>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- end main-content -->
    </div>
</div>

<!-- Floating Action Button -->
<button class="fab" onclick="openAddModal()" title="Add New Settlement">
    <span class="fab-tooltip">Add New Settlement</span>
    <i class="fas fa-plus"></i>
</button>

<!-- ══════════════════════════════════════════════
     MODAL: ADD SETTLEMENT
══════════════════════════════════════════════ -->
<div id="addModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <h3>➕ New Settlement</h3>
                <p>Create a final pay settlement for a departing employee</p>
            </div>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <div class="modal-body">
            <!-- FIX: Added onsubmit handler to force-recalc and validate before submit -->
            <form id="addForm" method="POST" action="settlements.php" onsubmit="return handleAddSubmit(event)">
                <input type="hidden" name="action" value="add">
                <input type="hidden" id="add_employee_id" name="employee_id">
                <input type="hidden" id="add_final_settlement_amount" name="final_settlement_amount" value="0">
                <input type="hidden" name="status" value="Pending">
                <!-- FIX: payment_method is now a visible dropdown so it always has a value -->

                <!-- Validation message box -->
                <div class="validation-msg" id="addValidationMsg">
                    <span>⚠️</span><span id="addValidationText"></span>
                </div>

                <!-- Exit Selection -->
                <div class="form-section">
                    <div class="form-section-title">📋 Exit Record</div>
                    <div class="form-group">
                        <label>Exit Record <span class="req">*</span></label>
                        <select id="add_exit_id" name="exit_id" class="form-control" required onchange="onExitSelect()">
                            <option value="">Choose an exit record…</option>
                            <?php foreach ($availableExits as $ex): ?>
                            <option value="<?= $ex['exit_id'] ?>"
                                data-eid="<?= $ex['employee_id'] ?>"
                                data-edate="<?= $ex['exit_date'] ?>"
                                data-salary="<?= $ex['current_salary'] ?>"
                                data-hire="<?= $ex['hire_date'] ?>">
                                <?= htmlspecialchars($ex['employee_name']) ?> — <?= $ex['exit_type'] ?> (<?= date('M d, Y', strtotime($ex['exit_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Employee Info Card (populated dynamically) -->
                <div id="addEmpCard" class="emp-card" style="display:none;"></div>

                <!-- Dates -->
                <div class="form-section">
                    <div class="form-section-title">📅 Dates</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Last Working Day <span class="req">*</span></label>
                            <input type="date" id="add_last_working_day" name="last_working_day" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Payment Date</label>
                            <input type="date" id="add_payment_date" name="payment_date" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Salary & Benefits -->
                <div class="form-section">
                    <div class="form-section-title">💵 Salary &amp; Benefits</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Final Salary (₱) <span class="req">*</span></label>
                            <input type="number" id="add_final_salary" name="final_salary" class="form-control" step="0.01" min="0" required oninput="recalcAdd()">
                            <div class="field-hint">Pro-rated salary for the last month if applicable</div>
                        </div>
                        <div class="form-group">
                            <label>13th Month Pay (₱)</label>
                            <input type="number" id="add_13th_month" class="form-control" step="0.01" min="0" value="0" oninput="recalcAdd()">
                            <div class="field-hint">Pro-rated 13th month pay</div>
                        </div>
                        <div class="form-group">
                            <label>Severance Pay (₱)</label>
                            <input type="number" id="add_severance" name="severance_pay" class="form-control" step="0.01" min="0" value="0" oninput="recalcAdd()">
                            <div class="field-hint">Based on years of service &amp; exit type</div>
                        </div>
                        <div class="form-group">
                            <label>Other Benefits (₱)</label>
                            <input type="number" id="add_other_benefits" class="form-control" step="0.01" min="0" value="0" oninput="recalcAdd()">
                            <div class="field-hint">Rice subsidy, allowances, etc.</div>
                        </div>
                    </div>
                </div>

                <!-- Leave Monetization -->
                <div class="form-section">
                    <div class="form-section-title">🌿 Leave Monetization</div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Unused VL Days</label>
                            <input type="number" id="add_vl_days" class="form-control" step="0.5" min="0" value="0" oninput="calcLeave()">
                        </div>
                        <div class="form-group">
                            <label>Unused SL Days</label>
                            <input type="number" id="add_sl_days" class="form-control" step="0.5" min="0" value="0" oninput="calcLeave()">
                        </div>
                        <div class="form-group">
                            <label>Other Leave Days</label>
                            <input type="number" id="add_other_leave" class="form-control" step="0.5" min="0" value="0" oninput="calcLeave()">
                        </div>
                        <div class="form-group">
                            <label>Daily Rate (₱)</label>
                            <input type="number" id="add_daily_rate" class="form-control" step="0.01" min="0" value="0" oninput="calcLeave()">
                            <div class="field-hint">Monthly ÷ 26 working days</div>
                        </div>
                        <div class="form-group">
                            <label>Total Leave Payout (₱)</label>
                            <input type="number" id="add_leave_total" class="form-control computed" readonly>
                        </div>
                        <div class="form-group" style="display:flex; align-items:flex-end;">
                            <!-- FIX: applyLeave now auto-applies; button still works as manual trigger -->
                            <button type="button" class="btn btn-outline btn-sm" onclick="applyLeave()" style="width:100%">
                                ✅ Apply to Settlement
                            </button>
                        </div>
                    </div>
                    <!-- FIX: This hidden field is now kept in sync automatically by calcLeave() -->
                    <input type="hidden" id="add_unused_leave_payout" name="unused_leave_payout" value="0">
                </div>

                <!-- Deductions -->
                <div class="form-section">
                    <div class="form-section-title">🔻 Deductions</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>SSS / GSIS (₱)</label>
                            <input type="number" id="add_ded_sss" class="form-control" step="0.01" min="0" value="0" oninput="recalcAdd()">
                        </div>
                        <div class="form-group">
                            <label>PhilHealth (₱)</label>
                            <input type="number" id="add_ded_ph" class="form-control" step="0.01" min="0" value="0" oninput="recalcAdd()">
                        </div>
                        <div class="form-group">
                            <label>Pag-IBIG (₱)</label>
                            <input type="number" id="add_ded_pi" class="form-control" step="0.01" min="0" value="0" oninput="recalcAdd()">
                        </div>
                        <div class="form-group">
                            <label>Withholding Tax (₱)</label>
                            <input type="number" id="add_ded_tax" class="form-control" step="0.01" min="0" value="0" oninput="recalcAdd()">
                        </div>
                        <div class="form-group">
                            <label>Loans / Advances (₱)</label>
                            <input type="number" id="add_ded_loans" class="form-control" step="0.01" min="0" value="0" oninput="recalcAdd()">
                        </div>
                        <div class="form-group">
                            <label>Other Deductions (₱)</label>
                            <input type="number" id="add_ded_other" class="form-control" step="0.01" min="0" value="0" oninput="recalcAdd()">
                        </div>
                    </div>
                    <!-- FIX: Total deductions synced on every recalc -->
                    <input type="hidden" id="add_deductions" name="deductions" value="0">
                </div>

                <!-- FIX: Payment Method is now a visible field (was always blank before) -->
                <div class="form-section">
                    <div class="form-section-title">💳 Payment Details</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Payment Method <span class="req">*</span></label>
                            <select name="payment_method" class="form-control" required>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="GCash">GCash</option>
                                <option value="Maya">Maya</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <!-- spacer -->
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-section">
                    <div class="form-section-title">📝 Notes</div>
                    <div class="form-group">
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes or remarks…"></textarea>
                    </div>
                </div>

                <!-- Calculation Summary -->
                <div class="calc-summary" id="addCalcSummary">
                    <div class="calc-summary-title">💡 Settlement Calculation Preview</div>
                    <div class="calc-line"><span class="calc-label">Final Salary</span><span class="calc-amt" id="cs_salary">₱0.00</span></div>
                    <div class="calc-line"><span class="calc-label">13th Month Pay</span><span class="calc-amt" id="cs_13th">₱0.00</span></div>
                    <div class="calc-line"><span class="calc-label">Severance Pay</span><span class="calc-amt" id="cs_severance">₱0.00</span></div>
                    <div class="calc-line"><span class="calc-label">Leave Payout</span><span class="calc-amt" id="cs_leave">₱0.00</span></div>
                    <div class="calc-line"><span class="calc-label">Other Benefits</span><span class="calc-amt" id="cs_benefits">₱0.00</span></div>
                    <div class="calc-line subtotal"><span class="calc-label">Gross Settlement</span><span class="calc-amt" id="cs_gross">₱0.00</span></div>
                    <div class="calc-line deduction"><span class="calc-label">Total Deductions</span><span class="calc-amt" id="cs_deductions">-₱0.00</span></div>
                    <div class="calc-line total"><span class="calc-label">NET FINAL PAY</span><span class="calc-amt" id="cs_total">₱0.00</span></div>
                </div>

                <div class="action-bar">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" id="saveSettlementBtn" class="btn btn-success">💾 Save Settlement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: FINAL PAY CALCULATOR (standalone)
══════════════════════════════════════════════ -->
<div id="calcModal" class="modal-overlay">
    <div class="modal-box wide">
        <div class="modal-head">
            <div>
                <h3>🧮 Final Pay Calculator</h3>
                <p>Compute employee final pay with salary, benefits &amp; leave monetization</p>
            </div>
            <button class="modal-close" onclick="closeModal('calcModal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="tab-bar">
                <button class="tab-btn active" onclick="switchTab('tab-salary')" id="btn-tab-salary">💵 Salary &amp; Benefits</button>
                <button class="tab-btn" onclick="switchTab('tab-leave')" id="btn-tab-leave">🌿 Leave Monetization</button>
                <button class="tab-btn" onclick="switchTab('tab-summary')" id="btn-tab-summary">📊 Summary</button>
            </div>

            <!-- TAB: Salary & Benefits -->
            <div id="tab-salary" class="tab-pane active">
                <div class="form-section">
                    <div class="form-section-title">👤 Employee Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Employee</label>
                            <select id="calc_emp" class="form-control" onchange="onCalcEmpSelect()">
                                <option value="">Select employee…</option>
                                <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['employee_id'] ?>"
                                    data-salary="<?= $e['current_salary'] ?>"
                                    data-hire="<?= $e['hire_date'] ?>">
                                    <?= htmlspecialchars($e['employee_name']) ?> (<?= $e['employee_number'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Last Working Date</label>
                            <input type="date" id="calc_last_day" class="form-control" onchange="calcSeverance()">
                        </div>
                        <div class="form-group">
                            <label>Monthly Basic Salary (₱)</label>
                            <input type="number" id="calc_monthly_salary" class="form-control" step="0.01" min="0" value="0" oninput="onSalaryInput(); recalcFP()">
                        </div>
                        <div class="form-group">
                            <label>Exit Reason</label>
                            <select id="calc_exit_reason" class="form-control" onchange="calcSeverance()">
                                <option value="Resignation">Resignation</option>
                                <option value="End of Contract">End of Contract</option>
                                <option value="Retirement">Retirement</option>
                                <option value="Retrenchment">Retrenchment</option>
                                <option value="Redundancy">Redundancy</option>
                                <option value="Disease">Disease</option>
                                <option value="Termination for Cause">Termination for Cause</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">📦 Benefits Computation</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Working Days in Last Month</label>
                            <input type="number" id="calc_worked_days" class="form-control" step="0.5" min="0" max="26" value="26" oninput="recalcFP()">
                            <div class="field-hint">Standard: 26 days/month</div>
                        </div>
                        <div class="form-group">
                            <label>Pro-Rated Final Salary (₱)</label>
                            <input type="number" id="calc_prorated_salary" class="form-control computed" readonly>
                            <div class="field-hint">Auto-computed from worked days</div>
                        </div>
                        <div class="form-group">
                            <label>13th Month Pay (₱)</label>
                            <input type="number" id="calc_13th" class="form-control computed" readonly>
                            <div class="field-hint">Pro-rated based on months worked this year</div>
                        </div>
                        <div class="form-group">
                            <label>Override 13th Month</label>
                            <input type="number" id="calc_13th_override" class="form-control" step="0.01" min="0" placeholder="Leave blank to auto" oninput="recalcFP()">
                        </div>
                        <div class="form-group">
                            <label>Severance Pay (₱)</label>
                            <input type="number" id="calc_severance_val" class="form-control computed" readonly>
                            <div id="calc_severance_note" class="field-hint">Select exit reason to compute</div>
                        </div>
                        <div class="form-group">
                            <label>Override Severance</label>
                            <input type="number" id="calc_severance_override" class="form-control" step="0.01" min="0" placeholder="Leave blank to auto" oninput="recalcFP()">
                        </div>
                        <div class="form-group">
                            <label>Other Allowances / Benefits (₱)</label>
                            <input type="number" id="calc_other_benefits" class="form-control" step="0.01" min="0" value="0" oninput="recalcFP()">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">🔻 Government &amp; Other Deductions</div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>SSS / GSIS (₱)</label>
                            <input type="number" id="calc_ded_sss" class="form-control" step="0.01" min="0" value="0" oninput="recalcFP()">
                        </div>
                        <div class="form-group">
                            <label>PhilHealth (₱)</label>
                            <input type="number" id="calc_ded_ph" class="form-control" step="0.01" min="0" value="0" oninput="recalcFP()">
                        </div>
                        <div class="form-group">
                            <label>Pag-IBIG (₱)</label>
                            <input type="number" id="calc_ded_pi" class="form-control" step="0.01" min="0" value="0" oninput="recalcFP()">
                        </div>
                        <div class="form-group">
                            <label>Withholding Tax (₱)</label>
                            <input type="number" id="calc_ded_tax" class="form-control" step="0.01" min="0" value="0" oninput="recalcFP()">
                        </div>
                        <div class="form-group">
                            <label>Loans / Advances (₱)</label>
                            <input type="number" id="calc_ded_loans" class="form-control" step="0.01" min="0" value="0" oninput="recalcFP()">
                        </div>
                        <div class="form-group">
                            <label>Other Deductions (₱)</label>
                            <input type="number" id="calc_ded_other" class="form-control" step="0.01" min="0" value="0" oninput="recalcFP()">
                        </div>
                    </div>
                </div>

                <div class="action-bar">
                    <button class="btn btn-primary" onclick="switchTab('tab-leave')">Next: Leave Monetization →</button>
                </div>
            </div>

            <!-- TAB: Leave Monetization -->
            <div id="tab-leave" class="tab-pane">
                <div class="form-section">
                    <div class="form-section-title">🌿 Leave Balance &amp; Monetization</div>
                    <p style="font-size:13px; color:var(--muted); margin-bottom:16px;">
                        Under Philippine labor law, employees are entitled to monetize their unused leave credits upon separation.
                        SIL (Service Incentive Leave) and other contractual leave may be converted to cash.
                    </p>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Daily Rate (₱)</label>
                            <input type="number" id="calc_daily_rate" class="form-control computed" readonly>
                            <div class="field-hint">Auto: Monthly ÷ 26 days</div>
                        </div>
                        <div class="form-group">
                            <label>Reason for Leaving</label>
                            <input type="text" id="calc_leave_reason_display" class="form-control" readonly placeholder="Set in Salary tab">
                        </div>
                        <div class="form-group">
                            <label>SIL Monetizable?</label>
                            <select id="calc_sil_eligible" class="form-control" onchange="recalcLeave()">
                                <option value="1">Yes — All unused leave</option>
                                <option value="0">No — Not eligible</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">📋 Leave Credits Input</div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Vacation Leave (VL) Days</label>
                            <input type="number" id="calc_vl" class="form-control" step="0.5" min="0" value="0" oninput="recalcLeave()">
                        </div>
                        <div class="form-group">
                            <label>Sick Leave (SL) Days</label>
                            <input type="number" id="calc_sl" class="form-control" step="0.5" min="0" value="0" oninput="recalcLeave()">
                        </div>
                        <div class="form-group">
                            <label>Other Leave Days</label>
                            <input type="number" id="calc_other_leave" class="form-control" step="0.5" min="0" value="0" oninput="recalcLeave()">
                        </div>
                        <div class="form-group">
                            <label>VL Monetization Rate</label>
                            <select id="calc_vl_rate" class="form-control" onchange="recalcLeave()">
                                <option value="1">100% (Full)</option>
                                <option value="0.5">50% (Half)</option>
                                <option value="0">0% (Not monetized)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>SL Monetization Rate</label>
                            <select id="calc_sl_rate" class="form-control" onchange="recalcLeave()">
                                <option value="1">100% (Full)</option>
                                <option value="0.5">50% (Half)</option>
                                <option value="0">0% (Not monetized)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Other Leave Rate</label>
                            <select id="calc_other_leave_rate" class="form-control" onchange="recalcLeave()">
                                <option value="1">100% (Full)</option>
                                <option value="0.5">50% (Half)</option>
                                <option value="0">0% (Not monetized)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Leave Breakdown Table -->
                <div class="leave-breakdown" id="leaveBreakdownTable">
                    <div class="leave-breakdown-header">
                        <span>Leave Type</span>
                        <span>Days</span>
                        <span>Rate</span>
                        <span>Amount</span>
                    </div>
                    <div class="leave-breakdown-row" id="lb_vl">
                        <div class="leave-type-badge"><span class="leave-dot" style="background:#E91E63;"></span> Vacation Leave (VL)</div>
                        <span id="lb_vl_days">0</span>
                        <span id="lb_vl_rate">100%</span>
                        <span id="lb_vl_amt" style="font-family:'DM Mono',monospace; font-weight:600; color:#1B5E20;">₱0.00</span>
                    </div>
                    <div class="leave-breakdown-row" id="lb_sl">
                        <div class="leave-type-badge"><span class="leave-dot" style="background:#0288D1;"></span> Sick Leave (SL)</div>
                        <span id="lb_sl_days">0</span>
                        <span id="lb_sl_rate">100%</span>
                        <span id="lb_sl_amt" style="font-family:'DM Mono',monospace; font-weight:600; color:#1B5E20;">₱0.00</span>
                    </div>
                    <div class="leave-breakdown-row">
                        <div class="leave-type-badge"><span class="leave-dot" style="background:#FF6F00;"></span> Other Leave</div>
                        <span id="lb_oth_days">0</span>
                        <span id="lb_oth_rate">100%</span>
                        <span id="lb_oth_amt" style="font-family:'DM Mono',monospace; font-weight:600; color:#1B5E20;">₱0.00</span>
                    </div>
                    <div class="leave-payout-total">
                        <strong>Total Leave Payout</strong>
                        <strong id="lb_total" style="font-family:'DM Mono',monospace; font-size:18px;">₱0.00</strong>
                    </div>
                </div>

                <div class="action-bar">
                    <button class="btn btn-outline" onclick="switchTab('tab-salary')">← Back</button>
                    <button class="btn btn-primary" onclick="switchTab('tab-summary')">View Summary →</button>
                </div>
            </div>

            <!-- TAB: Summary -->
            <div id="tab-summary" class="tab-pane">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                    <div>
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--muted); margin-bottom:8px;">Employee</div>
                        <div id="sum_emp_name" style="font-size:16px; font-weight:700; color:var(--dark);">—</div>
                        <div id="sum_exit_type" style="font-size:13px; color:var(--muted); margin-top:2px;">—</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--muted); margin-bottom:8px;">Net Final Pay</div>
                        <div id="sum_net_total" style="font-size:28px; font-weight:700; font-family:'DM Mono',monospace; color:var(--primary-dark);">₱0.00</div>
                    </div>
                </div>

                <div class="fin-breakdown-card">
                    <div style="padding:12px 18px; background:#FAFAFA; border-bottom:1px solid var(--border); font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--muted);">Earnings</div>
                    <div class="fin-breakdown-row"><span>Pro-Rated Final Salary</span><span class="amount positive" id="sum_salary">₱0.00</span></div>
                    <div class="fin-breakdown-row"><span>13th Month Pay</span><span class="amount positive" id="sum_13th">₱0.00</span></div>
                    <div class="fin-breakdown-row"><span>Severance Pay</span><span class="amount positive" id="sum_severance">₱0.00</span></div>
                    <div class="fin-breakdown-row"><span>Leave Monetization</span><span class="amount positive" id="sum_leave">₱0.00</span></div>
                    <div class="fin-breakdown-row"><span>Other Benefits</span><span class="amount positive" id="sum_benefits">₱0.00</span></div>
                    <div class="fin-breakdown-row" style="background:#F0FDF4; font-weight:600;">
                        <span>Gross Settlement</span><span class="amount positive" id="sum_gross">₱0.00</span>
                    </div>

                    <div style="padding:12px 18px; background:#FAFAFA; border-bottom:1px solid var(--border); border-top: 1px solid var(--border); font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--muted);">Deductions</div>
                    <div class="fin-breakdown-row"><span>SSS / GSIS</span><span class="amount negative" id="sum_sss">₱0.00</span></div>
                    <div class="fin-breakdown-row"><span>PhilHealth</span><span class="amount negative" id="sum_ph">₱0.00</span></div>
                    <div class="fin-breakdown-row"><span>Pag-IBIG</span><span class="amount negative" id="sum_pi">₱0.00</span></div>
                    <div class="fin-breakdown-row"><span>Withholding Tax</span><span class="amount negative" id="sum_tax">₱0.00</span></div>
                    <div class="fin-breakdown-row"><span>Loans / Advances</span><span class="amount negative" id="sum_loans">₱0.00</span></div>
                    <div class="fin-breakdown-row"><span>Other Deductions</span><span class="amount negative" id="sum_ded_other">₱0.00</span></div>
                    <div class="fin-breakdown-row" style="background:#FFF5F5; font-weight:600; color:var(--danger);">
                        <span>Total Deductions</span><span class="amount negative" id="sum_total_ded">₱0.00</span>
                    </div>

                    <div class="fin-breakdown-row total-row">
                        <span>🏆 NET FINAL PAY</span>
                        <span class="amount total" id="sum_net">₱0.00</span>
                    </div>
                </div>

                <div class="action-bar">
                    <button class="btn btn-outline" onclick="switchTab('tab-leave')">← Back</button>
                    <button class="btn btn-ghost" onclick="printFinalPay()">🖨️ Print</button>
                    <button class="btn btn-success" onclick="closeModal('calcModal')">✅ Done</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: UPDATE STATUS
══════════════════════════════════════════════ -->
<div id="statusModal" class="modal-overlay">
    <div class="modal-box narrow">
        <div class="modal-head">
            <div><h3>🔄 Update Status</h3></div>
            <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" id="status_settlement_id" name="settlement_id">
                <div class="form-group" style="margin-bottom:20px;">
                    <label>New Status <span class="req">*</span></label>
                    <select id="new_status" name="status" class="form-control" required>
                        <option value="Pending">⏳ Pending</option>
                        <option value="Processing">🔄 Processing</option>
                        <option value="Completed">✅ Completed</option>
                    </select>
                </div>
                <div class="action-bar">
                    <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: VIEW DETAILS
══════════════════════════════════════════════ -->
<div id="detailsModal" class="modal-overlay">
    <div class="modal-box wide">
        <div class="modal-head">
            <div>
                <h3>🔒 Settlement Details</h3>
                <p>Secure view — access is logged</p>
            </div>
            <button class="modal-close" onclick="closeModal('detailsModal')">✕</button>
        </div>
        <div class="modal-body" id="detailsModalBody"></div>
    </div>
</div>

<script>
    const settlementsData = <?= json_encode($settlements) ?>;
    const employeesData = <?= json_encode($employees) ?>;

    // ── Helpers ──────────────────────────────────────────────────────────────
    const fmt = (n) => '₱' + parseFloat(n || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    const gv  = (id) => parseFloat(document.getElementById(id)?.value) || 0;
    const sv  = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };

    // ── Modal helpers ─────────────────────────────────────────────────────────
    function openModal(id)  { document.getElementById(id).classList.add('active');    document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); document.body.style.overflow = 'auto';   }
    function openAddModal()          { openModal('addModal'); }
    function openFinalPayCalculator(){ openModal('calcModal'); }

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
    });

    // ── Tab switching ─────────────────────────────────────────────────────────
    function switchTab(tabId) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        document.getElementById('btn-' + tabId).classList.add('active');
        if (tabId === 'tab-summary') buildSummary();
        if (tabId === 'tab-leave') {
            document.getElementById('calc_leave_reason_display').value =
                document.getElementById('calc_exit_reason').value;
        }
    }

    // ── Search + Filter ───────────────────────────────────────────────────────
    function filterTable() {
        const q  = document.getElementById('searchInput').value.toLowerCase();
        const sf = document.getElementById('statusFilter').value;
        document.querySelectorAll('#settlementTableBody tr').forEach(row => {
            const txt    = row.textContent.toLowerCase();
            const status = row.getAttribute('data-status');
            row.style.display = ((!q || txt.includes(q)) && (!sf || status === sf)) ? '' : 'none';
        });
    }
    document.getElementById('searchInput').addEventListener('input', filterTable);
    document.getElementById('statusFilter').addEventListener('change', filterTable);

    // ── ADD MODAL ─────────────────────────────────────────────────────────────
    function onExitSelect() {
        const sel = document.getElementById('add_exit_id');
        const opt = sel.options[sel.selectedIndex];
        if (!opt.value) { document.getElementById('addEmpCard').style.display = 'none'; return; }

        const eid    = opt.getAttribute('data-eid');
        const salary = parseFloat(opt.getAttribute('data-salary')) || 0;
        const hire   = opt.getAttribute('data-hire');

        document.getElementById('add_employee_id').value = eid;
        document.getElementById('add_last_working_day').value = opt.getAttribute('data-edate');
        sv('add_final_salary', salary.toFixed(2));
        sv('add_daily_rate',   (salary / 26).toFixed(2));

        // Employee card
        const card = document.getElementById('addEmpCard');
        card.innerHTML = `
            <div class="emp-card-avatar">${opt.text.charAt(0)}</div>
            <div class="emp-card-info">
                <strong>${opt.text.split(' — ')[0]}</strong>
                <span>Hired: ${hire ? new Date(hire).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—'}</span>
            </div>
            <div class="emp-card-salary">
                <strong>${fmt(salary)}</strong>
                <span>Monthly Salary</span>
            </div>`;
        card.style.display = 'flex';
        calcLeave(); // internally calls recalcAdd()
    }

    // calcLeave computes leave total and syncs the hidden field.
    // recalcAdd() is always triggered separately by oninput handlers or handleAddSubmit.
    function calcLeave() {
        const daily = gv('add_daily_rate');
        const total = (gv('add_vl_days') + gv('add_sl_days') + gv('add_other_leave')) * daily;
        sv('add_leave_total', total.toFixed(2));
        sv('add_unused_leave_payout', total.toFixed(2));
        recalcAdd();
    }

    // Manual "Apply" button — keeps UX intact while also being redundant safety net
    function applyLeave() {
        sv('add_unused_leave_payout', gv('add_leave_total').toFixed(2));
        recalcAdd();
        // Visual feedback
        const btn = event.target.closest('button');
        const orig = btn.textContent;
        btn.textContent = '✔ Applied!';
        btn.style.background = 'var(--success)';
        btn.style.color = 'white';
        setTimeout(() => { btn.textContent = orig; btn.style.background = ''; btn.style.color = ''; }, 1500);
    }

    function recalcAdd() {
        const salary   = gv('add_final_salary');
        const thirteenth = gv('add_13th_month');
        const severance  = gv('add_severance');
        const leave      = gv('add_unused_leave_payout');
        const benefits   = gv('add_other_benefits');
        const gross      = salary + thirteenth + severance + leave + benefits;

        const ded = gv('add_ded_sss') + gv('add_ded_ph') + gv('add_ded_pi') +
                    gv('add_ded_tax') + gv('add_ded_loans') + gv('add_ded_other');

        // FIX: Always update both hidden fields so the submitted POST data is correct
        sv('add_deductions', ded.toFixed(2));
        sv('add_final_settlement_amount', (gross - ded).toFixed(2));

        // Update summary display
        document.getElementById('cs_salary').textContent     = fmt(salary);
        document.getElementById('cs_13th').textContent       = fmt(thirteenth);
        document.getElementById('cs_severance').textContent  = fmt(severance);
        document.getElementById('cs_leave').textContent      = fmt(leave);
        document.getElementById('cs_benefits').textContent   = fmt(benefits);
        document.getElementById('cs_gross').textContent      = fmt(gross);
        document.getElementById('cs_deductions').textContent = '-' + fmt(ded);
        document.getElementById('cs_total').textContent      = fmt(gross - ded);
    }

    // ── FIX: Form submit handler — force recalc, validate, then allow submit ──
    function handleAddSubmit(e) {
        // Force recalculate everything fresh before reading hidden field values
        calcLeave(); // this internally calls recalcAdd() at the end

        const msgBox  = document.getElementById('addValidationMsg');
        const msgText = document.getElementById('addValidationText');

        // Validate required fields
        if (!document.getElementById('add_exit_id').value) {
            showValidationError('Please select an Exit Record before saving.');
            return false;
        }
        if (!document.getElementById('add_employee_id').value) {
            showValidationError('Employee ID is missing. Please re-select the Exit Record.');
            return false;
        }
        if (!document.getElementById('add_last_working_day').value) {
            showValidationError('Please enter the Last Working Day.');
            return false;
        }
        if (gv('add_final_salary') <= 0) {
            showValidationError('Final Salary must be greater than zero.');
            return false;
        }

        msgBox.classList.remove('show');

        // Use setTimeout(0) so the browser captures the submit FIRST,
        // then we update the button visually — never disable before submit fires
        const btn = document.getElementById('saveSettlementBtn');
        setTimeout(() => {
            btn.textContent = '⏳ Saving…';
            btn.style.opacity = '0.7';
            btn.style.pointerEvents = 'none';
        }, 0);

        return true; // Allow native form submit
    }

    function showValidationError(msg) {
        const box  = document.getElementById('addValidationMsg');
        document.getElementById('addValidationText').textContent = msg;
        box.classList.add('show');
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ── FINAL PAY CALCULATOR ─────────────────────────────────────────────────
    function onCalcEmpSelect() {
        const sel    = document.getElementById('calc_emp');
        const opt    = sel.options[sel.selectedIndex];
        if (!opt.value) return;
        const salary = parseFloat(opt.getAttribute('data-salary')) || 0;
        sv('calc_monthly_salary', salary.toFixed(2));
        if (!document.getElementById('calc_last_day').value) {
            document.getElementById('calc_last_day').value = new Date().toISOString().split('T')[0];
        }
        onSalaryInput();
        calcSeverance();
        recalcFP();
    }

    function onSalaryInput() {
        const monthly = gv('calc_monthly_salary');
        sv('calc_daily_rate', (monthly / 26).toFixed(2));
        recalcLeave();
        recalcFP();
    }

    function calcSeverance() {
        const monthly    = gv('calc_monthly_salary');
        const exitReason = document.getElementById('calc_exit_reason').value;
        const lastDay    = document.getElementById('calc_last_day').value;
        const sel        = document.getElementById('calc_emp');
        const opt        = sel.options[sel.selectedIndex];
        const hire       = opt?.getAttribute('data-hire');

        let years = 0;
        if (hire && lastDay) {
            years = (new Date(lastDay) - new Date(hire)) / (1000 * 60 * 60 * 24 * 365.25);
        }

        let severance = 0, note = '';
        const half = monthly * 0.5;

        if (['Retrenchment','Redundancy'].includes(exitReason)) {
            severance = Math.max(half * Math.ceil(years), half);
            note = `½ month × ${Math.ceil(years)} year(s) (Retrenchment/Redundancy)`;
        } else if (exitReason === 'Disease') {
            severance = Math.max(monthly * Math.ceil(years), monthly);
            note = `1 month × ${Math.ceil(years)} year(s) (Disease)`;
        } else if (exitReason === 'Retirement') {
            severance = Math.max(half * Math.ceil(years), half);
            note = `½ month × ${Math.ceil(years)} year(s) (Retirement)`;
        } else {
            severance = 0;
            note = exitReason === 'Termination for Cause'
                ? 'No separation pay for termination for cause'
                : 'No statutory severance for resignation / end of contract';
        }

        sv('calc_severance_val', severance.toFixed(2));
        const noteEl = document.getElementById('calc_severance_note');
        if (noteEl) noteEl.textContent = note;

        // Pro-rated 13th month
        const workedMonths = lastDay ? Math.min(12, new Date(lastDay).getMonth() + 1) : 0;
        sv('calc_13th', ((monthly / 12) * workedMonths).toFixed(2));
        recalcFP();
    }

    function recalcFP() {
        const monthly    = gv('calc_monthly_salary');
        const workedDays = gv('calc_worked_days');
        const prorated   = (monthly / 26) * workedDays;
        sv('calc_prorated_salary', prorated.toFixed(2));

        const salary13  = gv('calc_13th_override') || gv('calc_13th');
        const severance = gv('calc_severance_override') || gv('calc_severance_val');
        const leave     = gv('calc_leave_payout_total') || 0;
        const benefits  = gv('calc_other_benefits');
        const gross     = prorated + salary13 + severance + leave + benefits;
        const ded       = gv('calc_ded_sss') + gv('calc_ded_ph') + gv('calc_ded_pi') +
                          gv('calc_ded_tax') + gv('calc_ded_loans') + gv('calc_ded_other');
        sv('calc_net_total', (gross - ded).toFixed(2));
    }

    function recalcLeave() {
        const daily    = gv('calc_daily_rate');
        const vl       = gv('calc_vl');
        const sl       = gv('calc_sl');
        const oth      = gv('calc_other_leave');
        const vlRate   = gv('calc_vl_rate');
        const slRate   = gv('calc_sl_rate');
        const othRate  = gv('calc_other_leave_rate');
        const eligible = document.getElementById('calc_sil_eligible').value === '1';

        const vlAmt  = eligible ? vl  * daily * vlRate  : 0;
        const slAmt  = eligible ? sl  * daily * slRate  : 0;
        const othAmt = eligible ? oth * daily * othRate : 0;
        const total  = vlAmt + slAmt + othAmt;

        const rateLabel = (r) => r == 1 ? '100%' : r == 0.5 ? '50%' : '0%';

        document.getElementById('lb_vl_days').textContent  = vl;
        document.getElementById('lb_sl_days').textContent  = sl;
        document.getElementById('lb_oth_days').textContent = oth;
        document.getElementById('lb_vl_rate').textContent  = eligible ? rateLabel(vlRate)  : 'N/A';
        document.getElementById('lb_sl_rate').textContent  = eligible ? rateLabel(slRate)  : 'N/A';
        document.getElementById('lb_oth_rate').textContent = eligible ? rateLabel(othRate) : 'N/A';
        document.getElementById('lb_vl_amt').textContent   = fmt(vlAmt);
        document.getElementById('lb_sl_amt').textContent   = fmt(slAmt);
        document.getElementById('lb_oth_amt').textContent  = fmt(othAmt);
        document.getElementById('lb_total').textContent    = fmt(total);

        sv('calc_leave_payout_total', total.toFixed(2));
        recalcFP();
    }

    function buildSummary() {
        const sel      = document.getElementById('calc_emp');
        const opt      = sel.options[sel.selectedIndex];
        const empName  = opt.value ? opt.text.split(' (')[0] : '—';
        const exitType = document.getElementById('calc_exit_reason').value;

        const prorated = gv('calc_prorated_salary');
        const s13      = gv('calc_13th_override') || gv('calc_13th');
        const sev      = gv('calc_severance_override') || gv('calc_severance_val');
        const leave    = gv('calc_leave_payout_total') || 0;
        const benefits = gv('calc_other_benefits');
        const gross    = prorated + s13 + sev + leave + benefits;
        const sss      = gv('calc_ded_sss');
        const ph       = gv('calc_ded_ph');
        const pi       = gv('calc_ded_pi');
        const tax      = gv('calc_ded_tax');
        const loans    = gv('calc_ded_loans');
        const dedOther = gv('calc_ded_other');
        const totalDed = sss + ph + pi + tax + loans + dedOther;
        const net      = gross - totalDed;

        document.getElementById('sum_emp_name').textContent  = empName;
        document.getElementById('sum_exit_type').textContent = exitType;
        document.getElementById('sum_salary').textContent    = fmt(prorated);
        document.getElementById('sum_13th').textContent      = fmt(s13);
        document.getElementById('sum_severance').textContent = fmt(sev);
        document.getElementById('sum_leave').textContent     = fmt(leave);
        document.getElementById('sum_benefits').textContent  = fmt(benefits);
        document.getElementById('sum_gross').textContent     = fmt(gross);
        document.getElementById('sum_sss').textContent       = fmt(sss);
        document.getElementById('sum_ph').textContent        = fmt(ph);
        document.getElementById('sum_pi').textContent        = fmt(pi);
        document.getElementById('sum_tax').textContent       = fmt(tax);
        document.getElementById('sum_loans').textContent     = fmt(loans);
        document.getElementById('sum_ded_other').textContent = fmt(dedOther);
        document.getElementById('sum_total_ded').textContent = fmt(totalDed);
        document.getElementById('sum_net').textContent       = fmt(net);
        document.getElementById('sum_net_total').textContent = fmt(net);
    }

    // ── STATUS MODAL ──────────────────────────────────────────────────────────
    function updateStatus(id, cur) {
        document.getElementById('status_settlement_id').value = id;
        document.getElementById('new_status').value = cur;
        openModal('statusModal');
    }

    // ── VIEW DETAILS ─────────────────────────────────────────────────────────
    function viewSettlementDetails(id) {
        const s = settlementsData.find(x => x.settlement_id == id);
        if (!s) return;

        const statusClass = {
            Pending: 'badge-pending',
            Processing: 'badge-processing',
            Completed: 'badge-completed'
        };

        document.getElementById('detailsModalBody').innerHTML = `
            <div class="emp-card" style="margin-bottom:20px;">
                <div class="emp-card-avatar">${s.employee_name.charAt(0)}</div>
                <div class="emp-card-info">
                    <strong>${s.employee_name}</strong>
                    <span>${s.employee_number} · ${s.job_title || '—'} · ${s.department || '—'}</span>
                </div>
                <div style="margin-left:auto;">
                    <span class="badge ${statusClass[s.status] || 'badge-pending'}">${s.status}</span>
                </div>
            </div>

            <div class="sensitive-section">
                <div class="sensitive-section-icon">⚠️</div>
                <div class="sensitive-section-text">
                    <strong>CONFIDENTIAL — SETTLEMENT FINANCIAL DATA</strong>
                    <span>Access to this information is logged. Accessed: ${new Date().toLocaleString()}</span>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <div>
                    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:12px;">Employee &amp; Exit Details</div>
                    <div class="fin-breakdown-card">
                        <div class="fin-breakdown-row"><span>Employee #</span><span>${s.employee_number}</span></div>
                        <div class="fin-breakdown-row"><span>Email</span><span style="font-size:13px;">${s.work_email || '—'}</span></div>
                        <div class="fin-breakdown-row"><span>Exit Type</span><span><strong>${s.exit_type}</strong></span></div>
                        <div class="fin-breakdown-row"><span>Exit Date</span><span>${s.exit_date ? new Date(s.exit_date).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—'}</span></div>
                        <div class="fin-breakdown-row"><span>Last Working Day</span><span>${new Date(s.last_working_day).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'})}</span></div>
                        <div class="fin-breakdown-row"><span>Payment Date</span><span>${s.payment_date ? new Date(s.payment_date).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '<em>Not scheduled</em>'}</span></div>
                        <div class="fin-breakdown-row"><span>Payment Method</span><span>${s.payment_method || 'Not specified'}</span></div>
                    </div>
                </div>
                <div>
                    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:12px;">Settlement Breakdown</div>
                    <div class="fin-breakdown-card">
                        <div class="fin-breakdown-row"><span>Final Salary</span><span class="amount positive">${fmt(s.final_salary)}</span></div>
                        <div class="fin-breakdown-row"><span>Severance Pay</span><span class="amount positive">${fmt(s.severance_pay)}</span></div>
                        <div class="fin-breakdown-row"><span>Leave Payout</span><span class="amount positive">${fmt(s.unused_leave_payout)}</span></div>
                        <div class="fin-breakdown-row"><span>Deductions</span><span class="amount negative">-${fmt(s.deductions)}</span></div>
                        <div class="fin-breakdown-row total-row">
                            <span>NET FINAL PAY</span>
                            <span class="amount total">${fmt(s.final_settlement_amount)}</span>
                        </div>
                    </div>
                </div>
            </div>

            ${s.notes ? `<div style="margin-top:20px;">
                <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:8px;">Notes</div>
                <div style="background:#F9FAFB;border:1px solid var(--border);border-radius:10px;padding:14px 16px;font-size:14px;white-space:pre-wrap;">${s.notes}</div>
            </div>` : ''}

            <div class="action-bar">
                <button class="btn btn-outline" onclick="closeModal('detailsModal')">Close</button>
                <button class="btn btn-ghost" onclick="printDetails(${id})">🖨️ Print</button>
            </div>`;

        // Log access
        const fd = new FormData();
        fd.append('action', 'view_details');
        fd.append('settlement_id', id);
        fetch(window.location.href, { method: 'POST', body: fd }).catch(() => {});

        openModal('detailsModal');
    }

    // ── Print Final Pay ───────────────────────────────────────────────────────
    function printFinalPay() {
        const sel      = document.getElementById('calc_emp');
        const opt      = sel.options[sel.selectedIndex];
        const empName  = opt.value ? opt.text.split(' (')[0] : '—';
        const exitType = document.getElementById('calc_exit_reason').value;
        const lastDay  = document.getElementById('calc_last_day').value;

        const prorated = gv('calc_prorated_salary');
        const s13      = gv('calc_13th_override') || gv('calc_13th');
        const sev      = gv('calc_severance_override') || gv('calc_severance_val');
        const leave    = gv('calc_leave_payout_total') || 0;
        const benefits = gv('calc_other_benefits');
        const gross    = prorated + s13 + sev + leave + benefits;
        const sss      = gv('calc_ded_sss');
        const ph       = gv('calc_ded_ph');
        const pi       = gv('calc_ded_pi');
        const tax      = gv('calc_ded_tax');
        const loans    = gv('calc_ded_loans');
        const dedOther = gv('calc_ded_other');
        const totalDed = sss + ph + pi + tax + loans + dedOther;
        const net      = gross - totalDed;

        const win = window.open('', '', 'width=800,height=900');
        win.document.write(`<!DOCTYPE html><html><head><title>Final Pay — ${empName}</title>
        <style>
            body{font-family:'Segoe UI',sans-serif;margin:0;padding:32px;color:#1A1A2E;}
            .header{border-bottom:3px solid #E91E63;padding-bottom:16px;margin-bottom:24px;display:flex;justify-content:space-between;}
            .header h2{margin:0;color:#E91E63;font-size:22px;} .header p{margin:4px 0 0;font-size:13px;color:#6B7280;}
            table{width:100%;border-collapse:collapse;margin-bottom:24px;}
            th{background:#F9FAFB;padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#6B7280;border-bottom:1px solid #E5E7EB;}
            td{padding:10px 14px;border-bottom:1px solid #F3F4F6;font-size:14px;}
            .amt{font-family:'Courier New',monospace;font-weight:600;text-align:right;}
            .positive{color:#1B5E20;} .negative{color:#D32F2F;}
            .total-row td{background:#FCE4EC;font-weight:700;font-size:16px;color:#C2185B;}
            .section-head td{background:#F9FAFB;font-weight:700;color:#6B7280;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;}
            .footer{font-size:11px;color:#9CA3AF;margin-top:32px;border-top:1px solid #E5E7EB;padding-top:12px;display:flex;justify-content:space-between;}
        </style></head><body>
        <div class="header">
            <div><h2>FINAL PAY COMPUTATION</h2><p>Settlement Report · Confidential</p></div>
            <div style="text-align:right;font-size:13px;color:#6B7280;">
                <strong>${empName}</strong><br>Exit: ${exitType}<br>
                Last Day: ${lastDay ? new Date(lastDay).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}) : '—'}
            </div>
        </div>
        <table>
            <tr class="section-head"><td colspan="2">Earnings</td></tr>
            <tr><td>Pro-Rated Final Salary</td><td class="amt positive">${fmt(prorated)}</td></tr>
            <tr><td>13th Month Pay</td><td class="amt positive">${fmt(s13)}</td></tr>
            <tr><td>Severance Pay</td><td class="amt positive">${fmt(sev)}</td></tr>
            <tr><td>Leave Monetization</td><td class="amt positive">${fmt(leave)}</td></tr>
            <tr><td>Other Benefits</td><td class="amt positive">${fmt(benefits)}</td></tr>
            <tr style="background:#F0FDF4;font-weight:600;"><td>Gross Settlement</td><td class="amt positive">${fmt(gross)}</td></tr>
            <tr class="section-head"><td colspan="2">Deductions</td></tr>
            <tr><td>SSS / GSIS</td><td class="amt negative">( ${fmt(sss)} )</td></tr>
            <tr><td>PhilHealth</td><td class="amt negative">( ${fmt(ph)} )</td></tr>
            <tr><td>Pag-IBIG</td><td class="amt negative">( ${fmt(pi)} )</td></tr>
            <tr><td>Withholding Tax</td><td class="amt negative">( ${fmt(tax)} )</td></tr>
            <tr><td>Loans / Advances</td><td class="amt negative">( ${fmt(loans)} )</td></tr>
            <tr><td>Other Deductions</td><td class="amt negative">( ${fmt(dedOther)} )</td></tr>
            <tr style="background:#FFF5F5;font-weight:600;color:#D32F2F;"><td>Total Deductions</td><td class="amt negative">( ${fmt(totalDed)} )</td></tr>
            <tr class="total-row"><td>🏆 NET FINAL PAY</td><td class="amt">${fmt(net)}</td></tr>
        </table>
        <div class="footer">
            <span>This is a CONFIDENTIAL document. For HR use only.</span>
            <span>Printed: ${new Date().toLocaleString()}</span>
        </div></body></html>`);
        win.document.close();
        setTimeout(() => win.print(), 300);
    }

    function printDetails(id) {
        const s = settlementsData.find(x => x.settlement_id == id);
        if (!s) return;
        const win = window.open('', '', 'width=800,height=900');
        win.document.write(`<!DOCTYPE html><html><head><title>Settlement #${s.settlement_id}</title>
        <style>body{font-family:'Segoe UI',sans-serif;margin:32px;color:#1A1A2E;}
        h2{color:#E91E63;margin:0 0 4px;}.sub{color:#6B7280;font-size:13px;}
        .divider{border:none;border-top:2px solid #E91E63;margin:20px 0;}
        .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F3F4F6;font-size:14px;}
        .row strong{color:#C2185B;font-family:'Courier New',monospace;}
        .total{background:#FCE4EC;padding:12px;border-radius:8px;display:flex;justify-content:space-between;font-weight:700;font-size:16px;color:#C2185B;margin-top:12px;}
        .footer{font-size:11px;color:#9CA3AF;margin-top:32px;}</style></head>
        <body>
        <h2>SETTLEMENT REPORT #${s.settlement_id}</h2>
        <p class="sub">${s.employee_name} · ${s.employee_number} · Printed ${new Date().toLocaleString()}</p>
        <hr class="divider">
        <div class="row"><span>Exit Type</span><span>${s.exit_type}</span></div>
        <div class="row"><span>Last Working Day</span><span>${new Date(s.last_working_day).toLocaleDateString()}</span></div>
        <div class="row"><span>Final Salary</span><strong>${fmt(s.final_salary)}</strong></div>
        <div class="row"><span>Severance Pay</span><strong>${fmt(s.severance_pay)}</strong></div>
        <div class="row"><span>Leave Payout</span><strong>${fmt(s.unused_leave_payout)}</strong></div>
        <div class="row"><span>Deductions</span><strong style="color:#D32F2F;">-${fmt(s.deductions)}</strong></div>
        <div class="total"><span>NET FINAL PAY</span><span>${fmt(s.final_settlement_amount)}</span></div>
        <p class="footer">CONFIDENTIAL — For HR use only. Status: ${s.status}</p>
        </body></html>`);
        win.document.close();
        setTimeout(() => win.print(), 300);
    }

    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            a.style.transition = 'opacity 0.5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        });
    }, 5000);
</script>
</body>
</html>