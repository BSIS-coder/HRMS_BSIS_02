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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $stmt_exit = $pdo->prepare("SELECT employee_id FROM exits WHERE exit_id = ?");
                    $stmt_exit->execute([$_POST['exit_id']]);
                    $exit_data = $stmt_exit->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("INSERT INTO exit_interviews (exit_id, employee_id, interview_date, feedback, improvement_suggestions, reason_for_leaving, would_recommend, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $exit_data['employee_id'],
                        $_POST['interview_date'],
                        $_POST['feedback'],
                        $_POST['improvement_suggestions'],
                        $_POST['reason_for_leaving'],
                        isset($_POST['would_recommend']) ? 1 : 0,
                        $_POST['status']
                    ]);
                    $_SESSION['message'] = "Exit interview added successfully!";
                    $_SESSION['messageType'] = "success";
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error adding interview: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                }
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
            
            case 'update':
                try {
                    $stmt_exit = $pdo->prepare("SELECT employee_id FROM exits WHERE exit_id = ?");
                    $stmt_exit->execute([$_POST['exit_id']]);
                    $exit_data = $stmt_exit->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("UPDATE exit_interviews SET exit_id=?, employee_id=?, interview_date=?, feedback=?, improvement_suggestions=?, reason_for_leaving=?, would_recommend=?, status=? WHERE interview_id=?");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $exit_data['employee_id'],
                        $_POST['interview_date'],
                        $_POST['feedback'],
                        $_POST['improvement_suggestions'],
                        $_POST['reason_for_leaving'],
                        isset($_POST['would_recommend']) ? 1 : 0,
                        $_POST['status'],
                        $_POST['interview_id']
                    ]);
                    $_SESSION['message'] = "Exit interview updated successfully!";
                    $_SESSION['messageType'] = "success";
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error updating interview: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                }
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                break;

            case 'conduct':
                // Save structured interview Q&A + scoring
                try {
                    $stmt_exit = $pdo->prepare("SELECT employee_id FROM exits WHERE exit_id = ?");
                    $stmt_exit->execute([$_POST['exit_id']]);
                    $exit_data = $stmt_exit->fetch(PDO::FETCH_ASSOC);

                    // Gather all structured answers into JSON
                    $structured_data = json_encode([
                        'interview_type'        => $_POST['interview_type'] ?? '',
                        'conducted_by'          => $_POST['conducted_by'] ?? '',
                        'duration_minutes'      => $_POST['duration_minutes'] ?? '',
                        'overall_satisfaction'  => $_POST['overall_satisfaction'] ?? '',
                        'management_rating'     => $_POST['management_rating'] ?? '',
                        'culture_rating'        => $_POST['culture_rating'] ?? '',
                        'growth_rating'         => $_POST['growth_rating'] ?? '',
                        'compensation_rating'   => $_POST['compensation_rating'] ?? '',
                        'qa_highlights'         => $_POST['qa_highlights'] ?? '',
                        'action_items'          => $_POST['action_items'] ?? '',
                        'rehire_eligible'       => $_POST['rehire_eligible'] ?? 'No',
                    ]);

                    $stmt = $pdo->prepare("INSERT INTO exit_interviews (exit_id, employee_id, interview_date, feedback, improvement_suggestions, reason_for_leaving, would_recommend, status, structured_data) VALUES (?, ?, ?, ?, ?, ?, ?, 'Completed', ?) ON DUPLICATE KEY UPDATE structured_data=VALUES(structured_data), status='Completed'");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $exit_data['employee_id'],
                        $_POST['interview_date'],
                        $_POST['feedback'] ?? '',
                        $_POST['improvement_suggestions'] ?? '',
                        $_POST['reason_for_leaving'] ?? '',
                        isset($_POST['would_recommend']) ? 1 : 0,
                        $structured_data
                    ]);
                    $_SESSION['message'] = "Exit interview conducted and recorded successfully!";
                    $_SESSION['messageType'] = "success";
                } catch (PDOException $e) {
                    // If structured_data column doesn't exist, fall back to basic insert
                    try {
                        $stmt = $pdo->prepare("INSERT INTO exit_interviews (exit_id, employee_id, interview_date, feedback, improvement_suggestions, reason_for_leaving, would_recommend, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Completed')");
                        $stmt->execute([
                            $_POST['exit_id'],
                            $exit_data['employee_id'],
                            $_POST['interview_date'],
                            $_POST['feedback'] ?? '',
                            $_POST['improvement_suggestions'] ?? '',
                            $_POST['reason_for_leaving'] ?? '',
                            isset($_POST['would_recommend']) ? 1 : 0,
                        ]);
                        $_SESSION['message'] = "Exit interview conducted and recorded successfully!";
                        $_SESSION['messageType'] = "success";
                    } catch (PDOException $e2) {
                        $_SESSION['message'] = "Error: " . $e2->getMessage();
                        $_SESSION['messageType'] = "error";
                    }
                }
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
            
            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM exit_interviews WHERE interview_id=?");
                    $stmt->execute([$_POST['interview_id']]);
                    $_SESSION['message'] = "Exit interview deleted successfully!";
                    $_SESSION['messageType'] = "success";
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error deleting interview: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                }
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
        }
    }
}

// Get message from session
$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// Fetch exit interviews with related data
$stmt = $pdo->query("
    SELECT 
        ei.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        ep.employee_number,
        ep.work_email,
        jr.title as job_title,
        jr.department,
        ex.exit_date,
        ex.exit_type,
        ep.hire_date
    FROM exit_interviews ei
    LEFT JOIN employee_profiles ep ON ei.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN exits ex ON ei.exit_id = ex.exit_id
    ORDER BY ei.interview_date DESC
");
$interviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown
$stmt = $pdo->query("
    SELECT 
        ex.exit_id, 
        ex.exit_date,
        ex.exit_type,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
    FROM exits ex
    LEFT JOIN employee_profiles ep ON ex.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY ex.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Analytics
$totalInterviews = count($interviews);
$completedCount  = count(array_filter($interviews, fn($i) => $i['status'] === 'Completed'));
$scheduledCount  = count(array_filter($interviews, fn($i) => $i['status'] === 'Scheduled'));
$recommendCount  = count(array_filter($interviews, fn($i) => $i['would_recommend'] == 1));
$recommendPct    = $totalInterviews > 0 ? round(($recommendCount / $totalInterviews) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exit Interview Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --pink:       #E91E63;
            --pink-light: #F06292;
            --pink-dark:  #C2185B;
            --pink-pale:  #FCE4EC;
            --pink-soft:  #F8BBD0;
        }

        body { background: var(--pink-pale); }

        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }

        .main-content { background: var(--pink-pale); padding: 20px; }

        .section-title {
            color: var(--pink);
            margin-bottom: 8px;
            font-weight: 700;
            font-size: 1.6rem;
        }

        /* ── Stat Cards ────────────────────────────────────── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 20px 18px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(233,30,99,.08);
            border-top: 4px solid var(--pink);
            transition: transform .2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .stat-num {
            font-size: 2rem;
            font-weight: 800;
            color: var(--pink-dark);
            line-height: 1;
        }
        .stat-card .stat-label {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .stat-card.green { border-top-color: #28a745; }
        .stat-card.green .stat-num { color: #1a7a31; }
        .stat-card.amber { border-top-color: #ffc107; }
        .stat-card.amber .stat-num { color: #b38600; }
        .stat-card.teal  { border-top-color: #17a2b8; }
        .stat-card.teal  .stat-num { color: #0e6c7e; }

        /* ── Controls bar ──────────────────────────────────── */
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .search-box { position: relative; flex: 1; max-width: 380px; }
        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 42px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 15px;
            transition: all .3s;
        }
        .search-box input:focus {
            border-color: var(--pink);
            outline: none;
            box-shadow: 0 0 10px rgba(233,30,99,.2);
        }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #999; }

        .btn-group-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        /* ── Buttons ───────────────────────────────────────── */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .25s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary  { background: linear-gradient(135deg,var(--pink),var(--pink-light)); color:#fff; }
        .btn-primary:hover  { transform:translateY(-2px); box-shadow:0 6px 16px rgba(233,30,99,.35); }
        .btn-success  { background: linear-gradient(135deg,#28a745,#20c997); color:#fff; }
        .btn-success:hover  { transform:translateY(-2px); box-shadow:0 6px 16px rgba(40,167,69,.3); }
        .btn-danger   { background: linear-gradient(135deg,#dc3545,#c82333); color:#fff; }
        .btn-danger:hover   { transform:translateY(-2px); }
        .btn-warning  { background: linear-gradient(135deg,#ffc107,#e0a800); color:#fff; }
        .btn-warning:hover  { transform:translateY(-2px); }
        .btn-info     { background: linear-gradient(135deg,#17a2b8,#138496); color:#fff; }
        .btn-info:hover     { transform:translateY(-2px); }
        .btn-violet   { background: linear-gradient(135deg,#7c3aed,#a855f7); color:#fff; }
        .btn-violet:hover   { transform:translateY(-2px); box-shadow:0 6px 16px rgba(124,58,237,.3); }
        .btn-teal     { background: linear-gradient(135deg,#0d9488,#14b8a6); color:#fff; }
        .btn-teal:hover     { transform:translateY(-2px); }
        .btn-sm { padding: 6px 14px; font-size: 13px; }
        .btn-secondary { background:#6c757d; color:#fff; }

        /* ── Table ─────────────────────────────────────────── */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,.07);
        }
        .table { width: 100%; border-collapse: collapse; }
        .table th {
            background: linear-gradient(135deg, var(--pink-soft), #f0f0f0);
            padding: 14px 15px;
            text-align: left;
            font-weight: 700;
            color: var(--pink-dark);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .4px;
            border-bottom: 2px solid #dee2e6;
        }
        .table td { padding: 13px 15px; border-bottom: 1px solid #f5f5f5; vertical-align: middle; font-size: 14px; }
        .table tbody tr:hover { background: var(--pink-pale); }

        .status-badge {
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .status-scheduled  { background:#fff3cd; color:#856404; }
        .status-completed  { background:#d4edda; color:#155724; }
        .status-cancelled  { background:#f8d7da; color:#721c24; }

        .action-btns { display: flex; gap: 4px; flex-wrap: wrap; }

        /* ── Tabs ──────────────────────────────────────────── */
        .tab-nav {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 22px;
        }
        .tab-btn {
            padding: 10px 18px;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 600;
            color: #888;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all .2s;
        }
        .tab-btn.active { color: var(--pink); border-bottom-color: var(--pink); }
        .tab-btn:hover:not(.active) { color: var(--pink-dark); }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* ── Modals ────────────────────────────────────────── */
        .modal {
            display: none; position: fixed; z-index: 1050;
            left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,.5); backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white; margin: 3% auto;
            border-radius: 16px; width: 92%; max-width: 780px;
            max-height: 92vh; overflow-y: auto;
            box-shadow: 0 24px 48px rgba(0,0,0,.25);
            animation: popIn .25s ease;
        }
        @keyframes popIn {
            from { transform:scale(.94) translateY(-20px); opacity:0; }
            to   { transform:scale(1)   translateY(0);    opacity:1; }
        }
        .modal-header {
            padding: 20px 28px;
            border-radius: 16px 16px 0 0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-header.pink   { background: linear-gradient(135deg,var(--pink),var(--pink-light)); }
        .modal-header.violet { background: linear-gradient(135deg,#7c3aed,#a855f7); }
        .modal-header.teal   { background: linear-gradient(135deg,#0d9488,#14b8a6); }
        .modal-header.amber  { background: linear-gradient(135deg,#f59e0b,#fbbf24); }
        .modal-header h2 { margin:0; color:#fff; font-size:1.2rem; }
        .modal-header .close-btn { background:none; border:none; color:rgba(255,255,255,.8); font-size:22px; cursor:pointer; }
        .modal-header .close-btn:hover { color:#fff; }
        .modal-body { padding: 28px; }

        /* ── Form helpers ──────────────────────────────────── */
        .form-group { margin-bottom: 18px; }
        .form-group label { display:block; margin-bottom:6px; font-weight:600; color:#444; font-size:13px; }
        .form-control {
            width: 100%; padding: 9px 14px;
            border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: 14px; transition: all .25s; box-sizing: border-box;
        }
        .form-control:focus { border-color: var(--pink); outline:none; box-shadow:0 0 8px rgba(233,30,99,.2); }
        textarea.form-control { min-height: 90px; resize: vertical; }
        .form-row { display: flex; gap: 16px; }
        .form-col { flex: 1; }

        /* Star rating */
        .star-rating { display:flex; gap:6px; margin-top:6px; }
        .star-rating input { display:none; }
        .star-rating label {
            font-size: 26px; cursor:pointer; color:#ddd;
            transition: color .15s;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color:#ffc107; }
        .star-rating { flex-direction: row-reverse; justify-content: flex-end; }

        /* Rating section grid */
        .ratings-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

        /* Progress bar */
        .progress-bar-wrap { background:#f0f0f0; border-radius:99px; height:8px; margin-top:8px; }
        .progress-bar-fill { height:8px; border-radius:99px; background: linear-gradient(90deg,var(--pink),var(--pink-light)); transition: width .6s; }

        /* Reason chips */
        .reason-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
        .reason-chip {
            padding: 6px 14px; border-radius: 99px;
            border: 2px solid #e0e0e0; background: white;
            font-size: 13px; cursor:pointer; font-weight:600; color:#555;
            transition: all .2s;
        }
        .reason-chip.selected { border-color: var(--pink); background: var(--pink-pale); color: var(--pink-dark); }

        /* Alerts */
        .alert { padding:14px 18px; margin-bottom:18px; border-radius:10px; font-weight:500; }
        .alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .alert-error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

        /* Feedback detail panel */
        .feedback-panel {
            background: white; border-radius: 12px;
            padding: 20px; margin-bottom: 14px;
            border-left: 4px solid var(--pink);
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
        }
        .feedback-panel h5 { color: var(--pink-dark); margin: 0 0 8px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
        .feedback-panel p  { margin:0; color:#444; font-size:14px; line-height:1.6; }

        /* Certificate */
        .certificate-container {
            max-width: 860px; margin: 0 auto; padding: 40px;
            background: white;
            border: 12px solid var(--pink-soft);
            outline: 3px solid var(--pink);
            outline-offset: -18px;
            box-shadow: 0 8px 32px rgba(0,0,0,.1);
            position: relative;
        }
        .cert-header { text-align:center; margin-bottom:30px; padding-bottom:20px; border-bottom:2px solid var(--pink-soft); }
        .cert-logo {
            width:90px; height:90px; margin:0 auto 14px;
            background: linear-gradient(135deg,var(--pink),var(--pink-light));
            border-radius:50%; display:flex; align-items:center; justify-content:center;
            font-size:40px; color:#fff;
        }
        .cert-title { font-size:30px; font-weight:800; color:var(--pink-dark); text-transform:uppercase; letter-spacing:3px; }
        .cert-sub   { color:#888; font-style:italic; margin-top:6px; }
        .cert-body  { padding: 20px 0; }
        .cert-text  { font-size:15px; color:#333; text-align:center; margin-bottom:24px; line-height:1.7; }
        .cert-details { background:var(--pink-pale); padding:24px; border-radius:10px; border-left:5px solid var(--pink); }
        .cert-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--pink-soft); font-size:14px; }
        .cert-row:last-child { border:none; }
        .cert-row span:first-child { font-weight:700; color:var(--pink-dark); }
        .cert-footer { margin-top:40px; display:flex; justify-content:space-around; border-top:2px solid var(--pink-soft); padding-top:24px; }
        .sig-block { text-align:center; min-width:180px; }
        .sig-line  { border-top:2px solid #333; margin:50px 0 8px; }
        .sig-name  { font-weight:700; color:var(--pink-dark); font-size:14px; }
        .sig-role  { font-size:12px; color:#888; font-style:italic; }

        /* Print */
        @media print {
            body * { visibility: hidden; }
            #printCertificate, #printCertificate * { visibility: visible; }
            #printCertificate { position:absolute; left:0; top:0; width:100%; }
            .no-print { display:none !important; }
            @page { size:A4 portrait; margin:10mm; }
            .certificate-container { max-width:100%; margin:0; padding:20px; box-shadow:none; }
        }

        @media (max-width:768px) {
            .form-row  { flex-direction:column; }
            .ratings-grid { grid-template-columns:1fr; }
            .stats-row { grid-template-columns:1fr 1fr; }
        }

        .no-results { text-align:center; padding:60px 20px; color:#aaa; }
        .no-results .icon { font-size:4rem; display:block; margin-bottom:14px; }

        /* Section divider in conduct modal */
        .section-heading {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--pink);
            margin: 22px 0 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-heading::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--pink-soft);
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">

            <h2 class="section-title">🗂 Exit Interview Management</h2>
            <p style="color:#888; margin-bottom:20px; font-size:14px;">Manage exit interviews, gather employee feedback, and record reasons for leaving.</p>

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= $messageType === 'success' ? '✅ ' : '⚠️ ' ?><?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-num"><?= $totalInterviews ?></div>
                    <div class="stat-label">Total Interviews</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-num"><?= $completedCount ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-num"><?= $scheduledCount ?></div>
                    <div class="stat-label">Scheduled</div>
                </div>
                <div class="stat-card teal">
                    <div class="stat-num"><?= $recommendPct ?>%</div>
                    <div class="stat-label">Would Recommend</div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-nav no-print">
                <button class="tab-btn active" onclick="switchTab('interviews')">📋 Interviews</button>
                <button class="tab-btn" onclick="switchTab('feedback')">💬 Feedback Summary</button>
                <button class="tab-btn" onclick="switchTab('reasons')">📊 Reasons for Leaving</button>
            </div>

            <!-- Tab: Interviews -->
            <div id="tab-interviews" class="tab-pane active">
                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchInput" placeholder="Search employee, status, exit type…">
                    </div>
                    <div class="btn-group-actions">
                        <button class="btn btn-violet" onclick="openConductModal()">🎤 Conduct Interview</button>
                        <button class="btn btn-primary" onclick="openModal('add')">➕ Add Interview</button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table" id="interviewTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Job Title</th>
                                <th>Interview Date</th>
                                <th>Exit Date</th>
                                <th>Exit Type</th>
                                <th>Status</th>
                                <th>Recommend</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="interviewTableBody">
                        <?php foreach ($interviews as $interview): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($interview['full_name']) ?></strong><br>
                                <small style="color:#888;">#<?= htmlspecialchars($interview['employee_number']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($interview['job_title']) ?><br>
                                <small style="color:#888;"><?= htmlspecialchars($interview['department']) ?></small>
                            </td>
                            <td><?= date('M d, Y', strtotime($interview['interview_date'])) ?></td>
                            <td><?= date('M d, Y', strtotime($interview['exit_date'])) ?></td>
                            <td><?= htmlspecialchars($interview['exit_type']) ?></td>
                            <td><span class="status-badge status-<?= strtolower($interview['status']) ?>"><?= htmlspecialchars($interview['status']) ?></span></td>
                            <td><?= $interview['would_recommend'] ? '✅ Yes' : '❌ No' ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-teal btn-sm" onclick="viewFeedback(<?= $interview['interview_id'] ?>)" title="View Feedback">👁 Feedback</button>
                                    <button class="btn btn-info btn-sm" onclick="printCertificate(<?= $interview['interview_id'] ?>)" title="Print Certificate">🖨️</button>
                                    <button class="btn btn-warning btn-sm" onclick="editInterview(<?= $interview['interview_id'] ?>)" title="Edit">✏️</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteInterview(<?= $interview['interview_id'] ?>)" title="Delete">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($interviews)): ?>
                    <div class="no-results">
                        <span class="icon">📋</span>
                        <h4>No exit interviews found</h4>
                        <p>Click <strong>Conduct Interview</strong> or <strong>Add Interview</strong> to get started.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: Feedback Summary -->
            <div id="tab-feedback" class="tab-pane">
                <div style="max-width:820px;">
                    <h4 style="color:var(--pink-dark); margin-bottom:18px;">💬 Employee Feedback Overview</h4>
                    <?php if (empty($interviews)): ?>
                        <p style="color:#aaa;">No feedback recorded yet.</p>
                    <?php else: ?>
                        <?php foreach ($interviews as $interview): if (empty($interview['feedback'])) continue; ?>
                        <div class="feedback-panel">
                            <h5><?= htmlspecialchars($interview['full_name']) ?> — <?= htmlspecialchars($interview['job_title']) ?>
                                <span style="float:right; font-size:12px; color:#aaa; font-weight:400;"><?= date('M d, Y', strtotime($interview['interview_date'])) ?></span>
                            </h5>
                            <p><?= nl2br(htmlspecialchars($interview['feedback'])) ?></p>
                            <?php if (!empty($interview['improvement_suggestions'])): ?>
                                <div style="margin-top:10px; padding:10px 14px; background:var(--pink-pale); border-radius:8px; font-size:13px; color:#555;">
                                    💡 <strong>Suggestions:</strong> <?= nl2br(htmlspecialchars($interview['improvement_suggestions'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div style="text-align:right; margin-top:16px;">
                        <button class="btn btn-primary btn-sm" onclick="openFeedbackModal()">📝 Gather New Feedback</button>
                    </div>
                </div>
            </div>

            <!-- Tab: Reasons for Leaving -->
            <div id="tab-reasons" class="tab-pane">
                <h4 style="color:var(--pink-dark); margin-bottom:18px;">📊 Reasons for Leaving</h4>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; max-width:900px;">
                    <?php
                    $reasonCounts = [];
                    foreach ($interviews as $i) {
                        $r = trim($i['reason_for_leaving'] ?? '');
                        if ($r === '') continue;
                        // Normalize to first ~40 chars as a key if free text
                        $key = strlen($r) > 50 ? substr($r, 0, 47).'…' : $r;
                        $reasonCounts[$key] = ($reasonCounts[$key] ?? 0) + 1;
                    }
                    arsort($reasonCounts);
                    $maxCount = max(array_values($reasonCounts) ?: [1]);
                    $colors = ['var(--pink)','#7c3aed','#0d9488','#f59e0b','#dc2626','#2563eb'];
                    $ci = 0;
                    foreach ($reasonCounts as $reason => $count):
                        $pct = round(($count / $totalInterviews) * 100);
                        $barW = round(($count / $maxCount) * 100);
                        $col = $colors[$ci++ % count($colors)];
                    ?>
                    <div style="background:white; border-radius:12px; padding:18px; box-shadow:0 2px 10px rgba(0,0,0,.06);">
                        <div style="font-size:13px; font-weight:700; color:#333; margin-bottom:6px;"><?= htmlspecialchars($reason) ?></div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="flex:1; background:#f0f0f0; border-radius:99px; height:10px;">
                                <div style="width:<?= $barW ?>%; height:10px; border-radius:99px; background:<?= $col ?>;"></div>
                            </div>
                            <span style="font-weight:800; color:<?= $col ?>; min-width:32px; font-size:14px;"><?= $count ?></span>
                        </div>
                        <div style="font-size:11px; color:#aaa; margin-top:4px;"><?= $pct ?>% of total exits</div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($reasonCounts)): ?>
                        <div style="grid-column:1/-1; color:#aaa; text-align:center; padding:40px;">No reasons recorded yet.</div>
                    <?php endif; ?>
                </div>

                <div style="margin-top:20px;">
                    <button class="btn btn-primary btn-sm" onclick="openReasonModal()">📝 Record New Reason</button>
                </div>
            </div>

        </div><!-- /main-content -->
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL 1: Add / Edit Interview
════════════════════════════════════════════════════════════ -->
<div id="interviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header pink">
            <h2 id="modalTitle">Add New Exit Interview</h2>
            <button class="close-btn" onclick="closeModal('interviewModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="interviewForm" method="POST">
                <input type="hidden" id="action" name="action" value="add">
                <input type="hidden" id="interview_id" name="interview_id">

                <div class="form-group">
                    <label>Exit Record *</label>
                    <select id="exit_id" name="exit_id" class="form-control" required>
                        <option value="">Select exit record…</option>
                        <?php foreach ($exits as $exit): ?>
                        <option value="<?= $exit['exit_id'] ?>"><?= htmlspecialchars($exit['employee_name']) ?> — <?= date('M d, Y', strtotime($exit['exit_date'])) ?> (<?= htmlspecialchars($exit['exit_type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label>Interview Date *</label>
                        <input type="date" id="interview_date" name="interview_date" class="form-control" required>
                    </div>
                    <div class="form-col form-group">
                        <label>Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Reason for Leaving</label>
                    <textarea id="reason_for_leaving" name="reason_for_leaving" class="form-control" placeholder="Enter reason for leaving…"></textarea>
                </div>

                <div class="form-group">
                    <label>Feedback</label>
                    <textarea id="feedback" name="feedback" class="form-control" placeholder="General feedback from the employee…"></textarea>
                </div>

                <div class="form-group">
                    <label>Improvement Suggestions</label>
                    <textarea id="improvement_suggestions" name="improvement_suggestions" class="form-control" placeholder="Suggestions the employee made…"></textarea>
                </div>

                <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" id="would_recommend" name="would_recommend" value="1">
                    <label for="would_recommend" style="margin:0; font-weight:600;">Would recommend the company to others</label>
                </div>

                <div style="text-align:right; margin-top:10px; display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('interviewModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">💾 Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL 2: Conduct Interview (structured wizard)
════════════════════════════════════════════════════════════ -->
<div id="conductModal" class="modal">
    <div class="modal-content" style="max-width:860px;">
        <div class="modal-header violet">
            <h2>🎤 Conduct Exit Interview</h2>
            <button class="close-btn" onclick="closeModal('conductModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="conduct">

                <!-- Step 1: Basic Info -->
                <div class="section-heading">1 — Interview Setup</div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label>Exit Record *</label>
                        <select name="exit_id" class="form-control" required>
                            <option value="">Select exit record…</option>
                            <?php foreach ($exits as $exit): ?>
                            <option value="<?= $exit['exit_id'] ?>"><?= htmlspecialchars($exit['employee_name']) ?> — <?= date('M d, Y', strtotime($exit['exit_date'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-col form-group">
                        <label>Interview Date *</label>
                        <input type="date" name="interview_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col form-group">
                        <label>Interview Type</label>
                        <select name="interview_type" class="form-control">
                            <option value="In-Person">In-Person</option>
                            <option value="Video Call">Video Call</option>
                            <option value="Phone">Phone</option>
                            <option value="Written Form">Written Form</option>
                        </select>
                    </div>
                    <div class="form-col form-group">
                        <label>Conducted By</label>
                        <input type="text" name="conducted_by" class="form-control" placeholder="HR Manager / Interviewer name">
                    </div>
                    <div class="form-col form-group">
                        <label>Duration (minutes)</label>
                        <input type="number" name="duration_minutes" class="form-control" placeholder="e.g. 30" min="5" max="180">
                    </div>
                </div>

                <!-- Step 2: Ratings -->
                <div class="section-heading">2 — Satisfaction Ratings</div>
                <div class="ratings-grid">
                    <div class="form-group">
                        <label>Overall Satisfaction</label>
                        <div class="star-rating" id="star-overall">
                            <?php for ($s=5;$s>=1;$s--): ?>
                            <input type="radio" id="overall<?=$s?>" name="overall_satisfaction" value="<?=$s?>">
                            <label for="overall<?=$s?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Management</label>
                        <div class="star-rating">
                            <?php for ($s=5;$s>=1;$s--): ?>
                            <input type="radio" id="mgmt<?=$s?>" name="management_rating" value="<?=$s?>">
                            <label for="mgmt<?=$s?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Work Culture</label>
                        <div class="star-rating">
                            <?php for ($s=5;$s>=1;$s--): ?>
                            <input type="radio" id="cult<?=$s?>" name="culture_rating" value="<?=$s?>">
                            <label for="cult<?=$s?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Growth Opportunities</label>
                        <div class="star-rating">
                            <?php for ($s=5;$s>=1;$s--): ?>
                            <input type="radio" id="grow<?=$s?>" name="growth_rating" value="<?=$s?>">
                            <label for="grow<?=$s?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Compensation & Benefits</label>
                        <div class="star-rating">
                            <?php for ($s=5;$s>=1;$s--): ?>
                            <input type="radio" id="comp<?=$s?>" name="compensation_rating" value="<?=$s?>">
                            <label for="comp<?=$s?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Eligible for Rehire?</label>
                        <select name="rehire_eligible" class="form-control">
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                            <option value="Conditional">Conditional</option>
                        </select>
                    </div>
                </div>

                <!-- Step 3: Reason for Leaving -->
                <div class="section-heading">3 — Reason for Leaving</div>
                <div class="form-group">
                    <label>Select primary reason(s):</label>
                    <div class="reason-chips" id="reasonChips">
                        <?php
                        $reasonOptions = [
                            'Better Opportunity','Higher Compensation','Work-Life Balance',
                            'Relocation','Career Change','Management Issues','Company Culture',
                            'Lack of Growth','Personal Reasons','Retirement','Contract Ended','Other'
                        ];
                        foreach ($reasonOptions as $ro): ?>
                        <div class="reason-chip" onclick="toggleChip(this,'reason_for_leaving')"><?= $ro ?></div>
                        <?php endforeach; ?>
                    </div>
                    <textarea name="reason_for_leaving" id="reason_for_leaving_conduct" class="form-control" style="margin-top:12px;" placeholder="Additional details about the reason for leaving…"></textarea>
                </div>

                <!-- Step 4: Feedback -->
                <div class="section-heading">4 — Feedback & Suggestions</div>
                <div class="form-group">
                    <label>Employee Feedback / General Comments</label>
                    <textarea name="feedback" class="form-control" rows="4" placeholder="Record what the employee shared about their experience…"></textarea>
                </div>
                <div class="form-group">
                    <label>Improvement Suggestions</label>
                    <textarea name="improvement_suggestions" class="form-control" rows="3" placeholder="What would they improve? Processes, culture, management, tools…"></textarea>
                </div>
                <div class="form-group">
                    <label>Key Q&A Highlights</label>
                    <textarea name="qa_highlights" class="form-control" rows="3" placeholder="Notable quotes or highlights from the interview…"></textarea>
                </div>
                <div class="form-group">
                    <label>Action Items / Follow-ups</label>
                    <textarea name="action_items" class="form-control" rows="2" placeholder="Any actions to take based on this interview…"></textarea>
                </div>

                <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="would_recommend" value="1" id="conduct_recommend">
                    <label for="conduct_recommend" style="margin:0; font-weight:600;">Employee would recommend this company to others</label>
                </div>

                <div style="text-align:right; margin-top:14px; display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('conductModal')">Cancel</button>
                    <button type="submit" class="btn btn-violet">✅ Complete Interview</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL 3: View Feedback
════════════════════════════════════════════════════════════ -->
<div id="feedbackModal" class="modal">
    <div class="modal-content" style="max-width:640px;">
        <div class="modal-header teal">
            <h2>💬 Employee Feedback Details</h2>
            <button class="close-btn" onclick="closeModal('feedbackModal')">&times;</button>
        </div>
        <div class="modal-body" id="feedbackModalBody">
            <!-- Populated by JS -->
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL 4: Gather Feedback (quick)
════════════════════════════════════════════════════════════ -->
<div id="gatherFeedbackModal" class="modal">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header teal">
            <h2>📝 Gather Feedback</h2>
            <button class="close-btn" onclick="closeModal('gatherFeedbackModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <div class="form-group">
                    <label>Select Interview Record *</label>
                    <select name="exit_id" class="form-control" id="gf_exit_id" required onchange="syncInterviewId(this)">
                        <option value="">Select…</option>
                        <?php foreach ($interviews as $iv): ?>
                        <option value="<?= $iv['exit_id'] ?>" data-iid="<?= $iv['interview_id'] ?>"><?= htmlspecialchars($iv['full_name']) ?> — <?= date('M d, Y', strtotime($iv['interview_date'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="interview_id" id="gf_interview_id">
                    <input type="hidden" name="interview_date" id="gf_interview_date" value="<?= date('Y-m-d') ?>">
                    <input type="hidden" name="status" value="Completed">
                    <input type="hidden" name="reason_for_leaving" value="">
                    <input type="hidden" name="improvement_suggestions" value="">
                </div>
                <div class="form-group">
                    <label>Feedback / Comments *</label>
                    <textarea name="feedback" class="form-control" rows="5" placeholder="Record the employee's feedback…" required></textarea>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="would_recommend" value="1" id="gf_recommend">
                    <label for="gf_recommend" style="margin:0; font-weight:600;">Would recommend the company</label>
                </div>
                <div style="text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('gatherFeedbackModal')">Cancel</button>
                    <button type="submit" class="btn btn-teal">💾 Save Feedback</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL 5: Record Reason for Leaving (quick)
════════════════════════════════════════════════════════════ -->
<div id="reasonModal" class="modal">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header amber">
            <h2>📊 Record Reason for Leaving</h2>
            <button class="close-btn" onclick="closeModal('reasonModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <div class="form-group">
                    <label>Select Interview Record *</label>
                    <select name="exit_id" class="form-control" id="rm_exit_id" required onchange="syncReasonInterviewId(this)">
                        <option value="">Select…</option>
                        <?php foreach ($interviews as $iv): ?>
                        <option value="<?= $iv['exit_id'] ?>" data-iid="<?= $iv['interview_id'] ?>"><?= htmlspecialchars($iv['full_name']) ?> — <?= date('M d, Y', strtotime($iv['interview_date'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="interview_id" id="rm_interview_id">
                    <input type="hidden" name="interview_date" id="rm_interview_date" value="<?= date('Y-m-d') ?>">
                    <input type="hidden" name="status" value="Completed">
                    <input type="hidden" name="feedback" value="">
                    <input type="hidden" name="improvement_suggestions" value="">
                </div>
                <div class="form-group">
                    <label>Select primary reason(s):</label>
                    <div class="reason-chips" id="rmChips">
                        <?php foreach ($reasonOptions as $ro): ?>
                        <div class="reason-chip" onclick="toggleChip(this,'reason_for_leaving_rm')"><?= $ro ?></div>
                        <?php endforeach; ?>
                    </div>
                    <textarea name="reason_for_leaving" id="reason_for_leaving_rm" class="form-control" style="margin-top:12px;" placeholder="Describe the reason in more detail…"></textarea>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="would_recommend" value="1" id="rm_recommend">
                    <label for="rm_recommend" style="margin:0; font-weight:600;">Would recommend the company</label>
                </div>
                <div style="text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reasonModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">💾 Save Reason</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     Certificate Print Section
════════════════════════════════════════════════════════════ -->
<div id="printCertificate" style="display:none;">
    <div class="certificate-container">
        <div class="cert-header">
            <div class="cert-logo">🏢</div>
            <div class="cert-title">Exit Certificate</div>
            <div class="cert-sub">Official Record of Employment Exit</div>
        </div>
        <div class="cert-body">
            <p class="cert-text">
                This is to certify that <strong id="certEmployeeName">[Employee Name]</strong> has formally completed 
                the exit process with our organization. The company acknowledges their service and extends 
                best wishes for their future endeavors.
            </p>
            <div class="cert-details">
                <div class="cert-row"><span>Employee Name</span><span id="certName"></span></div>
                <div class="cert-row"><span>Employee Number</span><span id="certNumber"></span></div>
                <div class="cert-row"><span>Job Title</span><span id="certJobTitle"></span></div>
                <div class="cert-row"><span>Department</span><span id="certDepartment"></span></div>
                <div class="cert-row"><span>Exit Type</span><span id="certExitType"></span></div>
                <div class="cert-row"><span>Exit Date</span><span id="certExitDate"></span></div>
                <div class="cert-row"><span>Interview Date</span><span id="certInterviewDate"></span></div>
            </div>
            <div style="text-align:center; margin-top:20px; color:#888; font-size:13px;">
                Issued on <?= date('F d, Y') ?>
            </div>
        </div>
        <div class="cert-footer">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-name">_______________________</div>
                <div class="sig-role">HR Manager</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-name">_______________________</div>
                <div class="sig-role">Authorized Signature</div>
            </div>
        </div>
    </div>
</div>

<script>
const allInterviews = <?= json_encode($interviews) ?>;

/* ── Tabs ────────────────────────────────────────────────── */
function switchTab(id) {
    document.querySelectorAll('.tab-btn').forEach((b,i) => {
        const tabs = ['interviews','feedback','reasons'];
        b.classList.toggle('active', tabs[i] === id);
    });
    document.querySelectorAll('.tab-pane').forEach(p => {
        p.classList.toggle('active', p.id === 'tab-'+id);
    });
}

/* ── Modals ──────────────────────────────────────────────── */
function openModal(action, data = null) {
    const form = document.getElementById('interviewForm');
    document.getElementById('action').value = action;
    document.getElementById('modalTitle').innerText = action === 'add' ? 'Add New Exit Interview' : 'Edit Exit Interview';
    form.reset();
    if (data) {
        for (let key in data) {
            const el = document.getElementById(key);
            if (!el) continue;
            el.type === 'checkbox' ? (el.checked = data[key] == 1) : (el.value = data[key] || '');
        }
    }
    document.getElementById('interviewModal').style.display = 'block';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function openConductModal() { document.getElementById('conductModal').style.display = 'block'; }
function openFeedbackModal() { document.getElementById('gatherFeedbackModal').style.display = 'block'; }
function openReasonModal()   { document.getElementById('reasonModal').style.display = 'block'; }

window.addEventListener('click', e => {
    ['interviewModal','conductModal','feedbackModal','gatherFeedbackModal','reasonModal'].forEach(id => {
        if (e.target === document.getElementById(id)) closeModal(id);
    });
});

/* ── Delete ──────────────────────────────────────────────── */
function deleteInterview(id) {
    if (!confirm("Delete this exit interview? This cannot be undone.")) return;
    const f = document.createElement('form');
    f.method = 'POST';
    f.innerHTML = `<input type="hidden" name="action" value="delete">
                   <input type="hidden" name="interview_id" value="${id}">`;
    document.body.appendChild(f);
    f.submit();
}

/* ── Edit ────────────────────────────────────────────────── */
function editInterview(id) {
    const i = allInterviews.find(x => x.interview_id == id);
    if (i) openModal('update', i);
}

/* ── View Feedback ───────────────────────────────────────── */
function viewFeedback(id) {
    const i = allInterviews.find(x => x.interview_id == id);
    if (!i) return;
    const body = document.getElementById('feedbackModalBody');
    const recommend = i.would_recommend == 1 ? '✅ Yes' : '❌ No';
    body.innerHTML = `
        <div style="margin-bottom:10px;">
            <strong style="font-size:16px;">${i.full_name || '—'}</strong>
            <span style="color:#aaa; font-size:13px; margin-left:10px;">${i.job_title || ''} · ${i.department || ''}</span>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px;">
            <span style="background:var(--pink-pale); color:var(--pink-dark); padding:4px 12px; border-radius:99px; font-size:12px; font-weight:700;">${i.status}</span>
            <span style="background:#f0fdf4; color:#166534; padding:4px 12px; border-radius:99px; font-size:12px; font-weight:700;">Recommend: ${recommend}</span>
        </div>
        <div class="feedback-panel">
            <h5>Reason for Leaving</h5>
            <p>${i.reason_for_leaving ? nl2br(esc(i.reason_for_leaving)) : '<em style="color:#aaa">Not recorded</em>'}</p>
        </div>
        <div class="feedback-panel" style="border-left-color:#7c3aed;">
            <h5 style="color:#7c3aed;">Feedback</h5>
            <p>${i.feedback ? nl2br(esc(i.feedback)) : '<em style="color:#aaa">No feedback recorded</em>'}</p>
        </div>
        <div class="feedback-panel" style="border-left-color:#0d9488;">
            <h5 style="color:#0d9488;">Improvement Suggestions</h5>
            <p>${i.improvement_suggestions ? nl2br(esc(i.improvement_suggestions)) : '<em style="color:#aaa">None recorded</em>'}</p>
        </div>
        <div style="text-align:right; margin-top:14px;">
            <button class="btn btn-secondary btn-sm" onclick="closeModal('feedbackModal')">Close</button>
        </div>
    `;
    document.getElementById('feedbackModal').style.display = 'block';
}

function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
function nl2br(str) { return str.replace(/\n/g, '<br>'); }

/* ── Print Certificate ───────────────────────────────────── */
function printCertificate(id) {
    const row = [...document.querySelectorAll('#interviewTableBody tr')].find(r =>
        r.querySelector('.btn-info') && r.querySelector('.btn-info').getAttribute('onclick').includes(id)
    );
    if (!row) return;
    const cells = row.querySelectorAll('td');
    document.getElementById('certEmployeeName').textContent = cells[0].querySelector('strong').innerText;
    document.getElementById('certName').textContent = cells[0].querySelector('strong').innerText;
    document.getElementById('certNumber').textContent = cells[0].querySelector('small').innerText.replace('#','').trim();
    document.getElementById('certJobTitle').textContent = cells[1].childNodes[0].textContent.trim();
    document.getElementById('certDepartment').textContent = cells[1].querySelector('small').innerText;
    document.getElementById('certInterviewDate').textContent = cells[2].innerText;
    document.getElementById('certExitDate').textContent = cells[3].innerText;
    document.getElementById('certExitType').textContent = cells[4].innerText;

    const cert = document.getElementById('printCertificate');
    cert.style.display = 'block';
    window.print();
    cert.style.display = 'none';
}

/* ── Reason chips ────────────────────────────────────────── */
function toggleChip(el, targetId) {
    el.classList.toggle('selected');
    const target = document.getElementById(targetId);
    const selected = [...el.closest('.reason-chips').querySelectorAll('.selected')].map(c => c.textContent);
    // Preserve any free-text after a line break if user typed something
    const existing = target.value.split('\n\n---\n').pop();
    if (selected.length > 0) {
        target.value = selected.join(', ') + (existing && !selected.some(s => target.value.startsWith(s)) ? '\n\n---\n' + existing : '');
    }
}

/* ── Search ──────────────────────────────────────────────── */
document.getElementById('searchInput').addEventListener('keyup', function() {
    const val = this.value.toLowerCase();
    document.querySelectorAll('#interviewTableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
});

/* ── Feedback modal sync ─────────────────────────────────── */
function syncInterviewId(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('gf_interview_id').value = opt.dataset.iid || '';
}
function syncReasonInterviewId(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('rm_interview_id').value = opt.dataset.iid || '';
}
</script>
</body>
</html>