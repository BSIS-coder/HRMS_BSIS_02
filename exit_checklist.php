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

    require_once 'dp.php';

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

    $message = '';
    $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {

            case 'add':
                try {
                    $stmt = $pdo->prepare("INSERT INTO exit_checklist 
                        (exit_id, item_name, description, responsible_department, status, completed_date, notes, item_type, serial_number, sticker_type, approval_status, approved_by, approved_date, remarks) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['exit_id'], $_POST['item_name'], $_POST['description'],
                        $_POST['responsible_department'], $_POST['status'],
                        !empty($_POST['completed_date']) ? $_POST['completed_date'] : null,
                        $_POST['notes'], $_POST['item_type'] ?? 'Other',
                        $_POST['serial_number'] ?? '', $_POST['sticker_type'] ?? '',
                        $_POST['approval_status'] ?? 'Pending', $_POST['approved_by'] ?? null,
                        null, $_POST['remarks'] ?? ''
                    ]);
                    $message = "Exit checklist item added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding checklist item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'update':
                try {
                    $approved_date = null;
                    $clearance_status = $_POST['clearance_status'] ?? 'Pending';
                    $clearance_date = null;
                    $cleared_by = null;
                    if ($_POST['approval_status'] === 'Approved' && !empty($_POST['approved_by'])) {
                        $approved_date = date('Y-m-d');
                    }
                    if ($_POST['status'] === 'Completed' && $_POST['approval_status'] === 'Approved') {
                        $clearance_status = 'Cleared';
                        $clearance_date = date('Y-m-d');
                        $cleared_by = $_POST['approved_by'] ?? $_SESSION['username'] ?? 'System';
                    }
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET exit_id=?, item_name=?, description=?, responsible_department=?, status=?, completed_date=?, notes=?, item_type=?, serial_number=?, sticker_type=?, approval_status=?, approved_by=?, approved_date=?, remarks=?, clearance_status=?, clearance_date=?, cleared_by=? WHERE checklist_id=?");
                    $stmt->execute([
                        $_POST['exit_id'], $_POST['item_name'], $_POST['description'],
                        $_POST['responsible_department'], $_POST['status'],
                        !empty($_POST['completed_date']) ? $_POST['completed_date'] : null,
                        $_POST['notes'], $_POST['item_type'] ?? 'Other',
                        $_POST['serial_number'] ?? '', $_POST['sticker_type'] ?? '',
                        $_POST['approval_status'] ?? 'Pending', $_POST['approved_by'] ?? null,
                        $approved_date, $_POST['remarks'] ?? '',
                        $clearance_status, $clearance_date, $cleared_by,
                        $_POST['checklist_id']
                    ]);
                    $message = "Exit checklist item updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating checklist item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM exit_checklist WHERE checklist_id=?");
                    $stmt->execute([$_POST['checklist_id']]);
                    $message = "Exit checklist item deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting checklist item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'bulk_update':
                try {
                    $pdo->beginTransaction();
                    if (isset($_POST['checklist_items']) && is_array($_POST['checklist_items'])) {
                        foreach ($_POST['checklist_items'] as $item_id => $item_data) {
                            $stmt = $pdo->prepare("UPDATE exit_checklist SET status=?, completed_date=?, notes=? WHERE checklist_id=?");
                            $completed_date = ($item_data['status'] === 'Completed' && empty($item_data['completed_date']))
                                ? date('Y-m-d') : (!empty($item_data['completed_date']) ? $item_data['completed_date'] : null);
                            $stmt->execute([$item_data['status'], $completed_date, $item_data['notes'] ?? '', $item_id]);
                        }
                    }
                    $pdo->commit();
                    $message = "Checklist items updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Error updating checklist items: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'approve':
                try {
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET approval_status=?, approved_by=?, approved_date=? WHERE checklist_id=?");
                    $stmt->execute(['Approved', $_POST['approved_by'] ?? $_SESSION['user_id'] ?? 'System', date('Y-m-d'), $_POST['checklist_id']]);
                    $message = "Checklist item approved successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error approving item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'reject':
                try {
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET approval_status=?, remarks=? WHERE checklist_id=?");
                    $stmt->execute(['Rejected', $_POST['rejection_remarks'] ?? '', $_POST['checklist_id']]);
                    $message = "Checklist item rejected.";
                    $messageType = "error";
                } catch (PDOException $e) {
                    $message = "Error rejecting item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            /* ── NEW FEATURE ACTIONS ── */

            case 'deactivate_system_access':
                try {
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET status='Completed', completed_date=?, notes=CONCAT(IFNULL(notes,''),' | System access deactivated on ".date('Y-m-d')." by ".($_SESSION['username']??'HR')."') WHERE checklist_id=?");
                    $stmt->execute([date('Y-m-d'), $_POST['checklist_id']]);
                    $message = "System access marked as deactivated.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'disable_email_account':
                try {
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET status='Completed', completed_date=?, notes=CONCAT(IFNULL(notes,''),' | Email/account disabled on ".date('Y-m-d')." by ".($_SESSION['username']??'IT')."') WHERE checklist_id=?");
                    $stmt->execute([date('Y-m-d'), $_POST['checklist_id']]);
                    $message = "Email & system account marked as disabled.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'dept_signoff':
                try {
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET approval_status='Approved', approved_by=?, approved_date=?, remarks=CONCAT(IFNULL(remarks,''),' | Dept sign-off: ".addslashes($_POST['signoff_dept']??'')." on ".date('Y-m-d')."') WHERE checklist_id=?");
                    $stmt->execute([$_POST['signoff_by'] ?? 'Department Head', date('Y-m-d'), $_POST['checklist_id']]);
                    $message = "Departmental sign-off recorded.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'mark_asset_returned':
                try {
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET status='Completed', completed_date=?, approval_status='Approved', approved_by=?, approved_date=?, notes=CONCAT(IFNULL(notes,''),' | Asset returned & verified on ".date('Y-m-d')."') WHERE checklist_id=?");
                    $stmt->execute([date('Y-m-d'), $_POST['received_by'] ?? 'IT/Facilities', date('Y-m-d'), $_POST['checklist_id']]);
                    $message = "Company asset marked as returned and verified.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'process_clearance':
                try {
                    // Full clearance: mark all linked items as cleared
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET clearance_status='Cleared', clearance_date=?, cleared_by=? WHERE exit_id=? AND status='Completed' AND approval_status='Approved'");
                    $stmt->execute([date('Y-m-d'), $_POST['cleared_by'] ?? $_SESSION['username'] ?? 'HR', $_POST['exit_id']]);
                    $affected = $stmt->rowCount();
                    $message = "Clearance processed for $affected eligible item(s).";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error processing clearance: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch checklist items
$stmt = $pdo->query("
    SELECT ec.*, e.employee_id, e.exit_date, e.exit_type,
           CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
           ep.employee_number
    FROM exit_checklist ec
    LEFT JOIN exits e ON ec.exit_id = e.exit_id
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY ec.created_at DESC
");
$checklistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown - exclude those already in checklist
$stmt = $pdo->query("
    SELECT e.exit_id, e.exit_date, e.exit_type,
           CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
           ep.employee_number
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    WHERE e.exit_id NOT IN (SELECT DISTINCT exit_id FROM exit_checklist)
    ORDER BY e.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch ALL exits (including those with checklist items) for edit mode
$stmt = $pdo->query("
    SELECT e.exit_id, e.exit_date, e.exit_type,
           CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
           ep.employee_number
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY e.exit_date DESC
");
$allExits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$departments = ['HR','IT','Finance','Security','Operations','Facilities','Legal','Marketing','Sales','Management'];

// Compute per-exit clearance summary
$exitSummary = [];
foreach ($checklistItems as $item) {
    $eid = $item['exit_id'];
    if (!isset($exitSummary[$eid])) {
        $exitSummary[$eid] = ['total'=>0,'completed'=>0,'approved'=>0,'cleared'=>0,'name'=>$item['employee_name'],'emp_no'=>$item['employee_number'],'exit_date'=>$item['exit_date']];
    }
    $exitSummary[$eid]['total']++;
    if ($item['status'] === 'Completed') $exitSummary[$eid]['completed']++;
    if ($item['approval_status'] === 'Approved') $exitSummary[$eid]['approved']++;
    if (($item['clearance_status'] ?? '') === 'Cleared') $exitSummary[$eid]['cleared']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exit Checklist & Clearance – HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --pink: #E91E63;
            --pink-light: #F06292;
            --pink-dark: #C2185B;
            --pink-lighter: #F8BBD0;
            --pink-pale: #FCE4EC;
        }
        body { background: var(--pink-pale); }
        .main-content { background: var(--pink-pale); padding: 20px; }
        .container-fluid { padding: 0; }
        .row { margin: 0; }
        .section-title { color: var(--pink); font-weight: 700; margin-bottom: 24px; }

        /* ── Tabs ── */
        .tab-bar { display: flex; gap: 6px; margin-bottom: 22px; flex-wrap: wrap; }
        .tab-btn {
            padding: 10px 20px; border: 2px solid #ddd; border-radius: 25px;
            background: white; font-weight: 600; cursor: pointer; font-size: 14px;
            transition: all .25s;
        }
        .tab-btn.active, .tab-btn:hover {
            background: var(--pink); color: white; border-color: var(--pink);
            box-shadow: 0 4px 12px rgba(233,30,99,.35);
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── Controls ── */
        .controls { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
        .search-box { position:relative; flex:1; max-width:380px; }
        .search-box input { width:100%; padding:11px 14px 11px 42px; border:2px solid #e0e0e0; border-radius:25px; font-size:15px; transition:.3s; }
        .search-box input:focus { border-color:var(--pink); outline:none; box-shadow:0 0 8px rgba(233,30,99,.25); }
        .search-icon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#888; }

        /* ── Buttons ── */
        .btn { padding:10px 22px; border:none; border-radius:25px; font-size:14px; font-weight:600; cursor:pointer; transition:.25s; display:inline-block; text-decoration:none; }
        .btn:hover { transform:translateY(-2px); }
        .btn:disabled { opacity:0.5; cursor:not-allowed; transform:none !important; }
        .btn-primary  { background:linear-gradient(135deg,var(--pink),var(--pink-light)); color:#fff; }
        .btn-success  { background:linear-gradient(135deg,#28a745,#20c997); color:#fff; }
        .btn-danger   { background:linear-gradient(135deg,#dc3545,#c82333); color:#fff; }
        .btn-warning  { background:linear-gradient(135deg,#ffc107,#e0a800); color:#fff; }
        .btn-info     { background:linear-gradient(135deg,#17a2b8,#138496); color:#fff; }
        .btn-purple   { background:linear-gradient(135deg,#6f42c1,#563d7c); color:#fff; }
        .btn-teal     { background:linear-gradient(135deg,#20c997,#17a589); color:#fff; }
        .btn-sm { padding:6px 14px; font-size:12px; }

        /* ── Stats ── */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:16px; margin-bottom:22px; }
        .stat-card { background:#fff; border-radius:12px; padding:18px; display:flex; align-items:center; gap:14px; box-shadow:0 2px 10px rgba(0,0,0,.08); transition:.3s; }
        .stat-card:hover { transform:translateY(-4px); }
        .stat-icon { font-size:1.6rem; padding:10px; border-radius:8px; }
        .stat-number { font-size:1.9rem; font-weight:700; background:linear-gradient(135deg,var(--pink),var(--pink-dark)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .stat-label { font-size:.8rem; color:#666; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }

        /* ── Table ── */
        .table-wrap { background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,.08); }
        table { width:100%; border-collapse:collapse; }
        thead th { background:linear-gradient(135deg,var(--pink-lighter),#e9ecef); padding:14px 12px; font-weight:700; color:var(--pink-dark); border-bottom:2px solid #dee2e6; font-size:13px; }
        tbody td { padding:12px; border-bottom:1px solid #f1f1f1; vertical-align:middle; font-size:13px; }
        tbody tr:hover { background:var(--pink-pale); }

        /* ── Badges ── */
        .badge-pill { padding:4px 11px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; display:inline-block; }
        .b-pending   { background:#fff3cd; color:#856404; }
        .b-completed { background:#d4edda; color:#155724; }
        .b-na        { background:#d1ecf1; color:#0c5460; }
        .b-approved  { background:#d4edda; color:#155724; }
        .b-rejected  { background:#f8d7da; color:#721c24; }
        .b-cleared   { background:#d4edda; color:#155724; }
        .b-notcleared{ background:#fff3cd; color:#856404; }
        .b-physical  { background:#cfe2ff; color:#084298; }
        .b-document  { background:#e2e3e5; color:#383d41; }
        .b-access    { background:#d1ecf1; color:#0c5460; }
        .b-financial { background:#f8d7da; color:#721c24; }
        .b-other     { background:#e7d4f5; color:#6f42c1; }

        /* ── Modal ── */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.52); backdrop-filter:blur(4px); z-index:1050; }
        .modal-box { background:#fff; margin:4% auto; border-radius:16px; width:92%; max-width:860px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 50px rgba(0,0,0,.3); animation:slideIn .3s ease; }
        .modal-box.sm { max-width:500px; }
        @keyframes slideIn { from{transform:translateY(-40px);opacity:0} to{transform:translateY(0);opacity:1} }
        .modal-head { background:linear-gradient(135deg,var(--pink),var(--pink-light)); color:#fff; padding:18px 26px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center; }
        .modal-head h3 { margin:0; font-size:1.1rem; }
        .modal-close { font-size:24px; cursor:pointer; opacity:.8; background:none; border:none; color:#fff; line-height:1; }
        .modal-close:hover { opacity:1; }
        .modal-body { padding:26px; }

        /* ── Forms ── */
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-grid.cols-1 { grid-template-columns:1fr; }
        .fg { display:flex; flex-direction:column; gap:5px; }
        .fg label { font-weight:600; color:var(--pink-dark); font-size:13px; }
        .fg input, .fg select, .fg textarea { padding:9px 12px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; transition:.25s; width:100%; }
        .fg input:focus, .fg select:focus, .fg textarea:focus { border-color:var(--pink); outline:none; box-shadow:0 0 8px rgba(233,30,99,.2); }
        .section-box { background:#f8f9fa; border-left:4px solid var(--pink); border-radius:8px; padding:16px; margin:14px 0; }
        .section-box.yellow { border-color:#ffc107; background:#fffdf0; }
        .section-box.green  { border-color:#28a745; background:#f0fff4; }
        .section-box.blue   { border-color:#17a2b8; background:#f0fafd; }
        .section-box.purple { border-color:#6f42c1; background:#f5f0ff; }
        .section-box h5 { margin:0 0 12px; font-size:.9rem; font-weight:700; }

        /* ── Progress ── */
        .progress-wrap { background:#e9ecef; border-radius:25px; height:18px; overflow:hidden; margin:16px 0; }
        .progress-fill { background:linear-gradient(135deg,var(--pink),var(--pink-light)); height:100%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; font-weight:700; transition:width .5s ease; min-width:32px; }

        /* ── Clearance Card ── */
        .clearance-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,.08); margin-bottom:14px; border-left:5px solid #ddd; }
        .clearance-card.all-clear { border-color:#28a745; }
        .clearance-card.partial { border-color:#ffc107; }
        .clearance-card.none { border-color:#dc3545; }
        .clearance-card h5 { margin:0 0 6px; }
        .clearance-mini-progress { background:#e9ecef; border-radius:10px; height:10px; overflow:hidden; margin:8px 0; }
        .clearance-mini-fill { background:linear-gradient(135deg,var(--pink),var(--pink-light)); height:100%; border-radius:10px; transition:width .4s; }

        /* ── System Access Panel ── */
        .access-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
        .access-card { background:#fff; border-radius:12px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,.08); text-align:center; border-top:4px solid #ddd; }
        .access-card.email { border-color:#EA4335; }
        .access-card.vpn   { border-color:#4285F4; }
        .access-card.erp   { border-color:#FBBC05; }
        .access-card.ad    { border-color:#34A853; }
        .access-card.badge { border-color:#9C27B0; }
        .access-card.cloud { border-color:#00BCD4; }
        .access-card-icon { font-size:2rem; margin-bottom:10px; }
        .access-card-title { font-weight:700; margin-bottom:6px; font-size:.95rem; }
        .access-card-desc  { font-size:.75rem; color:#666; margin-bottom:12px; }

        /* ── Alert ── */
        .alert { padding:14px 18px; border-radius:8px; margin-bottom:18px; font-weight:500; }
        .alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .alert-error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

        .no-data { text-align:center; padding:50px; color:#888; }
        .no-data i { font-size:3.5rem; margin-bottom:16px; display:block; color:#ddd; }

        /* ── Action Buttons group ── */
        .action-group { display:flex; flex-wrap:wrap; gap:4px; }

        @media(max-width:768px) {
            .form-grid { grid-template-columns:1fr; }
            .tab-bar { gap:4px; }
            .tab-btn { padding:8px 14px; font-size:13px; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <h2 class="section-title"><i class="fas fa-clipboard-check"></i> Exit Checklist &amp; Clearance Management</h2>

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- ── TAB BAR ── -->
            <div class="tab-bar">
                <button class="tab-btn active" onclick="switchTab('tab-checklist',this)">📋 Checklist Items</button>
                <button class="tab-btn" onclick="switchTab('tab-clearance',this)">🔓 Clearance Processing</button>
                <button class="tab-btn" onclick="switchTab('tab-assets',this)">📦 Asset Returns</button>
                <button class="tab-btn" onclick="switchTab('tab-signoffs',this)">✍️ Dept. Sign-offs</button>
                <button class="tab-btn" onclick="switchTab('tab-access',this)">🔐 System Access</button>
            </div>

            <!-- ══════════════════════════════════════════
                 TAB 1 – CHECKLIST ITEMS (original + enhanced)
            ══════════════════════════════════════════ -->
            <div id="tab-checklist" class="tab-panel active">

                <?php if (empty($exits)): ?>
                <div class="alert" style="background:#fff3cd;border-left:4px solid #ffc107;color:#856404;margin-bottom:20px">
                    <strong>ℹ️ Notice:</strong> All employees with exit records already have checklist items assigned. You can edit existing items or wait for new exit records to be created.
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <?php
                $totalItems     = count($checklistItems);
                $completedItems = count(array_filter($checklistItems, fn($i)=>$i['status']==='Completed'));
                $pendingItems   = count(array_filter($checklistItems, fn($i)=>$i['status']==='Pending'));
                $clearedItems   = count(array_filter($checklistItems, fn($i)=>($i['clearance_status']??'')  ==='Cleared'));
                ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-icon" style="background:var(--pink-pale);color:var(--pink)">📋</span>
                        <div><div class="stat-number"><?= $totalItems ?></div><div class="stat-label">Total Items</div></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon" style="background:#d4edda;color:#28a745">✅</span>
                        <div><div class="stat-number" style="background:linear-gradient(135deg,#28a745,#20c997);-webkit-background-clip:text;-webkit-text-fill-color:transparent"><?= $completedItems ?></div><div class="stat-label">Completed</div></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon" style="background:#fff3cd;color:#ffc107">⏳</span>
                        <div><div class="stat-number" style="background:linear-gradient(135deg,#ffc107,#e0a800);-webkit-background-clip:text;-webkit-text-fill-color:transparent"><?= $pendingItems ?></div><div class="stat-label">Pending</div></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon" style="background:#d1ecf1;color:#17a2b8">🔓</span>
                        <div><div class="stat-number" style="background:linear-gradient(135deg,#17a2b8,#138496);-webkit-background-clip:text;-webkit-text-fill-color:transparent"><?= $clearedItems ?></div><div class="stat-label">Cleared</div></div>
                    </div>
                </div>

                <!-- Progress -->
                <?php if ($totalItems > 0):
                    $pct = round(($completedItems / $totalItems) * 100); ?>
                <div class="progress-wrap">
                    <div class="progress-fill" style="width:0%" data-target="<?= $pct ?>%">
                        <?= $completedItems ?>/<?= $totalItems ?> (<?= $pct ?>%)
                    </div>
                </div>
                <?php endif; ?>

                <!-- Controls -->
                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchInput" placeholder="Search employee, item, department…">
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn btn-info" onclick="openBulkModal()">📋 Bulk Update</button>
                        <button class="btn btn-primary" onclick="openItemModal('add')" <?= empty($exits) ? 'disabled title="No available employees to add"' : '' ?>>➕ Add Item</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Item &amp; Type</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Approval</th>
                                <th>Clearance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="checklistBody">
                        <?php foreach ($checklistItems as $item): ?>
                        <tr data-id="<?= $item['checklist_id'] ?>">
                            <td>
                                <strong><?= htmlspecialchars($item['employee_name']) ?></strong><br>
                                <small style="color:#888">#<?= htmlspecialchars($item['employee_number']) ?> · <?= date('M d, Y', strtotime($item['exit_date'])) ?></small>
                            </td>
                            <td>
                                <span class="badge-pill b-<?= strtolower(str_replace([' ','/'],['-','-'],$item['item_type'])) ?>"><?= htmlspecialchars($item['item_type']) ?></span>
                                <strong style="display:block;margin-top:3px"><?= htmlspecialchars($item['item_name']) ?></strong>
                                <?php if ($item['item_type']==='Physical' && ($item['serial_number']||$item['sticker_type'])): ?>
                                <small style="color:#666">
                                    <?= $item['serial_number'] ? 'SN: '.htmlspecialchars($item['serial_number']) : '' ?>
                                    <?= $item['sticker_type'] ? ' · Tag: '.htmlspecialchars($item['sticker_type']) : '' ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['responsible_department']) ?></td>
                            <td><span class="badge-pill b-<?= strtolower(str_replace([' ','-'],['',''],$item['status'])) ?>"><?= htmlspecialchars($item['status']) ?></span></td>
                            <td>
                                <span class="badge-pill b-<?= strtolower($item['approval_status']) ?>"><?= htmlspecialchars($item['approval_status']) ?></span>
                                <?php if ($item['approval_status']==='Approved' && $item['approved_by']): ?>
                                <div style="font-size:11px;color:#666;margin-top:3px">✓ <?= htmlspecialchars($item['approved_by']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $isCleared = ($item['status']==='Completed' && $item['approval_status']==='Approved'); ?>
                                <span class="badge-pill <?= $isCleared ? 'b-cleared' : 'b-notcleared' ?>">
                                    <?= $isCleared ? '✓ CLEARED' : '⏳ PENDING' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <button class="btn btn-info btn-sm" onclick="viewItem(<?= $item['checklist_id'] ?>)">👁️</button>
                                    <button class="btn btn-warning btn-sm" onclick="openItemModal('edit',<?= $item['checklist_id'] ?>)">✏️</button>
                                    <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $item['checklist_id'] ?>)">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($checklistItems)): ?>
                    <div class="no-data"><i>📋</i><h4>No checklist items yet</h4><p>Add items using the button above.</p></div>
                    <?php endif; ?>
                </div>
            </div><!-- /tab-checklist -->


            <!-- ══════════════════════════════════════════
                 TAB 2 – CLEARANCE PROCESSING
            ══════════════════════════════════════════ -->
            <div id="tab-clearance" class="tab-panel">
                <h4 style="color:var(--pink-dark);margin-bottom:16px">🔓 Employee Clearance Overview</h4>
                <p style="color:#666;font-size:14px">An employee is fully cleared when <strong>all</strong> checklist items are Completed + Approved. Use the "Process Clearance" button to batch-mark eligible items.</p>

                <?php if (empty($exitSummary)): ?>
                <div class="no-data"><i>🔓</i><p>No exit records found.</p></div>
                <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
                    <?php foreach ($exitSummary as $eid => $es):
                        $ratio = $es['total'] > 0 ? round(($es['cleared'] / $es['total'])*100) : 0;
                        $cls = $ratio===100 ? 'all-clear' : ($ratio>0 ? 'partial' : 'none');
                    ?>
                    <div class="clearance-card <?= $cls ?>">
                        <h5><?= htmlspecialchars($es['name']) ?> <small style="color:#888">#<?= htmlspecialchars($es['emp_no']) ?></small></h5>
                        <small style="color:#666">Exit: <?= date('M d, Y', strtotime($es['exit_date'])) ?></small>
                        <div class="clearance-mini-progress">
                            <div class="clearance-mini-fill" style="width:<?= $ratio ?>%"></div>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:12px;margin-bottom:12px">
                            <span>📋 Total: <strong><?= $es['total'] ?></strong></span>
                            <span>✅ Done: <strong><?= $es['completed'] ?></strong></span>
                            <span>✓ Approved: <strong><?= $es['approved'] ?></strong></span>
                            <span>🔓 Cleared: <strong><?= $es['cleared'] ?></strong></span>
                        </div>
                        <?php if ($ratio === 100): ?>
                        <span class="badge-pill b-cleared" style="font-size:13px">🎉 FULLY CLEARED</span>
                        <?php else: ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="process_clearance">
                            <input type="hidden" name="exit_id" value="<?= $eid ?>">
                            <input type="hidden" name="cleared_by" value="<?= htmlspecialchars($_SESSION['username'] ?? 'HR') ?>">
                            <button type="submit" class="btn btn-success btn-sm">🔓 Process Clearance (<?= $es['approved'] - $es['cleared'] ?> eligible)</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div><!-- /tab-clearance -->


            <!-- ══════════════════════════════════════════
                 TAB 3 – ASSET RETURNS
            ══════════════════════════════════════════ -->
            <div id="tab-assets" class="tab-panel">
                <h4 style="color:var(--pink-dark);margin-bottom:16px">📦 Company Asset Returns</h4>

                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="assetSearch" placeholder="Search assets…">
                    </div>
                    <button class="btn btn-primary" onclick="openItemModal('add','','Physical')" <?= empty($exits) ? 'disabled title="No available employees to add"' : '' ?>>➕ Add Asset Item</button>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Asset</th>
                                <th>Serial / Tag</th>
                                <th>Department</th>
                                <th>Return Status</th>
                                <th>Condition Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="assetBody">
                        <?php foreach ($checklistItems as $item):
                            if ($item['item_type'] !== 'Physical') continue; ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['employee_name']) ?></strong><br>
                                <small style="color:#888">#<?= htmlspecialchars($item['employee_number']) ?></small>
                            </td>
                            <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                            <td>
                                <?= $item['serial_number'] ? '<small>SN: <strong>'.htmlspecialchars($item['serial_number']).'</strong></small><br>' : '' ?>
                                <?= $item['sticker_type'] ? '<small>Tag: <strong>'.htmlspecialchars($item['sticker_type']).'</strong></small>' : '<small style="color:#aaa">—</small>' ?>
                            </td>
                            <td><?= htmlspecialchars($item['responsible_department']) ?></td>
                            <td>
                                <span class="badge-pill b-<?= strtolower(str_replace([' ','-'],['',''],$item['status'])) ?>"><?= htmlspecialchars($item['status']) ?></span>
                                <?php if ($item['status']==='Completed'): ?>
                                <div style="font-size:11px;color:#28a745;margin-top:2px">📅 <?= $item['completed_date'] ? date('M d, Y', strtotime($item['completed_date'])) : 'Returned' ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:160px;font-size:12px;color:#555"><?= htmlspecialchars($item['notes'] ?: '—') ?></td>
                            <td>
                                <?php if ($item['status'] !== 'Completed'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="mark_asset_returned">
                                    <input type="hidden" name="checklist_id" value="<?= $item['checklist_id'] ?>">
                                    <input type="hidden" name="received_by" value="IT/Facilities">
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Mark this asset as returned?')">
                                        📦 Mark Returned
                                    </button>
                                </form>
                                <?php else: ?>
                                <span style="color:#28a745;font-size:12px;font-weight:600">✓ Returned</span>
                                <?php endif; ?>
                                <button class="btn btn-warning btn-sm" onclick="openItemModal('edit',<?= $item['checklist_id'] ?>)">✏️</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty(array_filter($checklistItems, fn($i)=>$i['item_type']==='Physical'))): ?>
                    <div class="no-data"><i>📦</i><p>No physical asset items found.</p></div>
                    <?php endif; ?>
                </div>

                <!-- Asset Return Checklist Template -->
                <div class="section-box blue" style="margin-top:22px">
                    <h5 style="color:#0c5460">📋 Standard Asset Return Checklist</h5>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;font-size:13px">
                        <?php
                        $assetTemplates = [
                            ['💻','Laptop / Computer'],['📱','Mobile Phone'],['⌨️','Keyboard & Mouse'],
                            ['🖥️','Monitor'],['🔑','Office Keys'],['💳','Access Card / ID Badge'],
                            ['🎧','Headset / Peripherals'],['🖨️','Printer (if assigned)'],['📡','WiFi Dongle / Router'],
                            ['🔒','Security Token / USB'],['🚗','Company Vehicle Key'],['📋','Documents / Files']
                        ];
                        foreach ($assetTemplates as [$icon,$name]): ?>
                        <div style="background:#fff;border:1px solid #bee5eb;border-radius:6px;padding:8px 10px;display:flex;align-items:center;gap:8px">
                            <span><?= $icon ?></span><span><?= $name ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div><!-- /tab-assets -->


            <!-- ══════════════════════════════════════════
                 TAB 4 – DEPARTMENTAL SIGN-OFFS
            ══════════════════════════════════════════ -->
            <div id="tab-signoffs" class="tab-panel">
                <h4 style="color:var(--pink-dark);margin-bottom:16px">✍️ Departmental Sign-off Tracker</h4>
                <p style="color:#666;font-size:14px">Each department must sign off on their respective checklist items before full clearance is granted.</p>

                <?php
                // Group items by employee + department
                $signoffMap = [];
                foreach ($checklistItems as $item) {
                    $key = $item['exit_id'].'|'.$item['responsible_department'];
                    if (!isset($signoffMap[$key])) {
                        $signoffMap[$key] = [
                            'employee' => $item['employee_name'],
                            'emp_no'   => $item['employee_number'],
                            'dept'     => $item['responsible_department'],
                            'exit_id'  => $item['exit_id'],
                            'items'    => []
                        ];
                    }
                    $signoffMap[$key]['items'][] = $item;
                }
                ?>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Items (Total)</th>
                                <th>Completed</th>
                                <th>Approved</th>
                                <th>Sign-off Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($signoffMap as $key => $sg):
                            $total = count($sg['items']);
                            $done  = count(array_filter($sg['items'], fn($i)=>$i['status']==='Completed'));
                            $appr  = count(array_filter($sg['items'], fn($i)=>$i['approval_status']==='Approved'));
                            $allOk = ($done===$total && $appr===$total);
                            $firstItem = $sg['items'][0];
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sg['employee']) ?></strong><br><small style="color:#888">#<?= htmlspecialchars($sg['emp_no']) ?></small></td>
                            <td><span class="badge-pill" style="background:#e7d4f5;color:#6f42c1"><?= htmlspecialchars($sg['dept']) ?></span></td>
                            <td><strong><?= $total ?></strong></td>
                            <td><?= $done === $total ? '<span style="color:#28a745;font-weight:700">'.$done.'/'.$total.' ✓</span>' : '<span style="color:#ffc107">'.$done.'/'.$total.'</span>' ?></td>
                            <td><?= $appr === $total ? '<span style="color:#28a745;font-weight:700">'.$appr.'/'.$total.' ✓</span>' : '<span style="color:#dc3545">'.$appr.'/'.$total.'</span>' ?></td>
                            <td>
                                <?php if ($allOk): ?>
                                <span class="badge-pill b-approved">✓ SIGNED OFF</span>
                                <?php elseif ($done > 0): ?>
                                <span class="badge-pill b-pending">⏳ IN PROGRESS</span>
                                <?php else: ?>
                                <span class="badge-pill b-rejected">✗ NOT STARTED</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$allOk): ?>
                                <button class="btn btn-purple btn-sm"
                                    onclick="openSignoffModal(<?= $firstItem['checklist_id'] ?>, '<?= addslashes($sg['dept']) ?>', '<?= addslashes($sg['employee']) ?>')">
                                    ✍️ Sign Off
                                </button>
                                <?php else: ?>
                                <span style="color:#28a745;font-size:12px;font-weight:600">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($signoffMap)): ?>
                    <div class="no-data"><i>✍️</i><p>No sign-off data available.</p></div>
                    <?php endif; ?>
                </div>

                <!-- Sign-off Progress by Department -->
                <div class="section-box" style="margin-top:22px">
                    <h5>📊 Department Sign-off Summary</h5>
                    <?php
                    $deptStats = [];
                    foreach ($checklistItems as $item) {
                        $d = $item['responsible_department'];
                        if (!isset($deptStats[$d])) $deptStats[$d] = ['total'=>0,'approved'=>0];
                        $deptStats[$d]['total']++;
                        if ($item['approval_status']==='Approved') $deptStats[$d]['approved']++;
                    }
                    foreach ($deptStats as $dept => $ds):
                        $dp = $ds['total']>0 ? round(($ds['approved']/$ds['total'])*100) : 0;
                    ?>
                    <div style="margin-bottom:10px">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px">
                            <strong><?= htmlspecialchars($dept) ?></strong>
                            <span><?= $ds['approved'] ?>/<?= $ds['total'] ?> (<?= $dp ?>%)</span>
                        </div>
                        <div class="progress-wrap" style="margin:0">
                            <div class="progress-fill" style="width:<?= $dp ?>%;min-width:0"><?= $dp>10?$dp.'%':'' ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div><!-- /tab-signoffs -->


            <!-- ══════════════════════════════════════════
                 TAB 5 – SYSTEM ACCESS DEACTIVATION
            ══════════════════════════════════════════ -->
            <div id="tab-access" class="tab-panel">
                <h4 style="color:var(--pink-dark);margin-bottom:6px">🔐 System Access &amp; Account Deactivation</h4>
                <p style="color:#666;font-size:14px;margin-bottom:20px">Track and action the deactivation of all system accounts, email, and access credentials for departing employees.</p>

                <!-- Access Type Cards -->
                <div class="access-grid" style="margin-bottom:24px">
                    <div class="access-card email">
                        <div class="access-card-icon">📧</div>
                        <div class="access-card-title">Email Account</div>
                        <div class="access-card-desc">Disable corporate email, set auto-reply, and forward critical emails</div>
                    </div>
                    <div class="access-card vpn">
                        <div class="access-card-icon">🔒</div>
                        <div class="access-card-title">VPN Access</div>
                        <div class="access-card-desc">Revoke remote access credentials and VPN certificates</div>
                    </div>
                    <div class="access-card erp">
                        <div class="access-card-icon">🖥️</div>
                        <div class="access-card-title">ERP / Business Systems</div>
                        <div class="access-card-desc">Deactivate accounts in ERP, CRM, and other business tools</div>
                    </div>
                    <div class="access-card ad">
                        <div class="access-card-icon">👤</div>
                        <div class="access-card-title">Active Directory</div>
                        <div class="access-card-desc">Disable AD account, remove from security groups</div>
                    </div>
                    <div class="access-card badge">
                        <div class="access-card-icon">💳</div>
                        <div class="access-card-title">Physical Access / Badge</div>
                        <div class="access-card-desc">Deactivate door access badges and biometric records</div>
                    </div>
                    <div class="access-card cloud">
                        <div class="access-card-icon">☁️</div>
                        <div class="access-card-title">Cloud Services</div>
                        <div class="access-card-desc">Revoke access to cloud platforms (Google Workspace, M365, etc.)</div>
                    </div>
                </div>

                <!-- Access Checklist Table -->
                <?php
                $accessItems = array_filter($checklistItems, fn($i)=>in_array($i['item_type'],['Access','Document']) || 
                    in_array(strtolower($i['item_name']),['email','vpn','active directory','system access','erp','crm','badge']) ||
                    stripos($i['item_name'],'access')!==false || stripos($i['item_name'],'email')!==false ||
                    stripos($i['item_name'],'account')!==false || stripos($i['item_name'],'password')!==false ||
                    $i['responsible_department']==='IT'
                );
                ?>

                <div class="controls" style="margin-bottom:14px">
                    <h5 style="margin:0;color:var(--pink-dark)">IT / Access Checklist Items</h5>
                    <div style="display:flex;gap:8px">
                        <button class="btn btn-teal" onclick="openItemModal('add','','Access')" <?= empty($exits) ? 'disabled title="No available employees to add"' : '' ?>>🔐 Add Access Item</button>
                        <button class="btn btn-danger" onclick="openBulkDeactivateModal()">⚡ Bulk Deactivate</button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>System / Access Type</th>
                                <th>Responsible</th>
                                <th>Status</th>
                                <th>Approval</th>
                                <th>Deactivation Actions</th>
                            </tr>
                        </thead>
                        <tbody id="accessBody">
                        <?php foreach ($checklistItems as $item):
                            if ($item['responsible_department'] !== 'IT' &&
                                $item['item_type'] !== 'Access' &&
                                stripos($item['item_name'],'email')===false &&
                                stripos($item['item_name'],'access')===false &&
                                stripos($item['item_name'],'account')===false &&
                                stripos($item['item_name'],'vpn')===false &&
                                stripos($item['item_name'],'password')===false) continue;
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['employee_name']) ?></strong><br><small style="color:#888">#<?= htmlspecialchars($item['employee_number']) ?></small></td>
                            <td>
                                <span class="badge-pill b-access"><?= htmlspecialchars($item['item_type']) ?></span>
                                <strong style="display:block;margin-top:3px"><?= htmlspecialchars($item['item_name']) ?></strong>
                                <?php if ($item['description']): ?>
                                <small style="color:#666"><?= htmlspecialchars(substr($item['description'],0,60)) ?><?= strlen($item['description'])>60?'…':'' ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['responsible_department']) ?></td>
                            <td><span class="badge-pill b-<?= strtolower(str_replace([' ','-'],['',''],$item['status'])) ?>"><?= htmlspecialchars($item['status']) ?></span></td>
                            <td><span class="badge-pill b-<?= strtolower($item['approval_status']) ?>"><?= htmlspecialchars($item['approval_status']) ?></span></td>
                            <td>
                                <div class="action-group">
                                    <?php if ($item['status'] !== 'Completed'): ?>
                                    <!-- Disable Email -->
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="disable_email_account">
                                        <input type="hidden" name="checklist_id" value="<?= $item['checklist_id'] ?>">
                                        <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#EA4335,#c0392b);color:#fff" title="Disable Email/Account" onclick="return confirm('Disable email &amp; system account for this item?')">
                                            📧 Disable Email
                                        </button>
                                    </form>
                                    <!-- Deactivate System Access -->
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="deactivate_system_access">
                                        <input type="hidden" name="checklist_id" value="<?= $item['checklist_id'] ?>">
                                        <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#6f42c1,#563d7c);color:#fff" title="Deactivate System Access" onclick="return confirm('Mark system access as deactivated?')">
                                            🔐 Deactivate
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span style="color:#28a745;font-weight:700;font-size:12px">✓ Deactivated</span>
                                    <?php endif; ?>
                                    <button class="btn btn-warning btn-sm" onclick="openItemModal('edit',<?= $item['checklist_id'] ?>)">✏️</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty(array_filter($checklistItems, fn($i)=>$i['responsible_department']==='IT'||$i['item_type']==='Access'))): ?>
                    <div class="no-data"><i>🔐</i><p>No access/IT items found. Add items with "IT" department or "Access" type.</p></div>
                    <?php endif; ?>
                </div>

                <!-- Deactivation Checklist -->
                <div class="section-box purple" style="margin-top:22px">
                    <h5>⚡ Standard IT Offboarding Checklist</h5>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px">
                        <?php
                        $itChecks = [
                            ['📧','Disable corporate email account','Prevents unauthorized access'],
                            ['🔁','Set email auto-reply / OOO message','Notify senders of departure'],
                            ['📂','Transfer email data to manager','Preserve business communications'],
                            ['👤','Disable Active Directory account','Block network login'],
                            ['🔒','Revoke VPN credentials','Remove remote access'],
                            ['☁️','Revoke Microsoft 365 / Google Workspace','Cloud productivity access'],
                            ['🛡️','Remove from security groups','Limit residual permissions'],
                            ['🔑','Reset shared passwords known to employee','Security hygiene'],
                            ['💾','Backup user profile data','Preserve business files'],
                            ['🖥️','Deactivate ERP/CRM access','Business system security'],
                            ['📱','Remote wipe or unenroll mobile device','MDM compliance'],
                            ['🎫','Revoke software licenses','License cost management'],
                        ];
                        foreach ($itChecks as [$icon, $title, $desc]): ?>
                        <div style="background:#fff;border:1px solid #d1b3ff;border-radius:8px;padding:10px 12px">
                            <div style="font-weight:700;font-size:13px;margin-bottom:3px"><?= $icon ?> <?= $title ?></div>
                            <div style="font-size:11px;color:#888"><?= $desc ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div><!-- /tab-access -->

        </div><!-- /main-content -->
    </div>
</div>


<!-- ══════════════════ MODAL: Add / Edit Item ══════════════════ -->
<div id="itemModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="itemModalTitle">Add Checklist Item</h3>
            <button class="modal-close" onclick="closeItemModal()">×</button>
        </div>
        <div class="modal-body">
            <form id="itemForm" method="POST">
                <input type="hidden" id="f_action" name="action" value="add">
                <input type="hidden" id="f_checklist_id" name="checklist_id">

                <div class="form-grid">
                    <div class="fg" style="grid-column:span 2">
                        <label>Employee Exit</label>
                        <select id="f_exit_id" name="exit_id" required>
                            <option value="">Select employee exit…</option>
                            <!-- For add mode: only show available exits -->
                            <?php foreach ($exits as $e): ?>
                            <option value="<?= $e['exit_id'] ?>" data-mode="add"><?= htmlspecialchars($e['employee_name']) ?> (#<?= htmlspecialchars($e['employee_number']) ?>)</option>
                            <?php endforeach; ?>
                            <!-- For edit mode: show all exits -->
                            <?php foreach ($allExits as $e): ?>
                            <option value="<?= $e['exit_id'] ?>" data-mode="edit" style="display:none"><?= htmlspecialchars($e['employee_name']) ?> (#<?= htmlspecialchars($e['employee_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($exits)): ?>
                        <small style="color:#dc3545;font-weight:600">⚠️ All employees with exit records already have checklist items. No new entries can be added.</small>
                        <?php endif; ?>
                    </div>
                    <div class="fg">
                        <label>Item Type</label>
                        <select id="f_item_type" name="item_type" required onchange="togglePhysical()">
                            <option value="Physical">📦 Physical Item</option>
                            <option value="Document">📄 Document</option>
                            <option value="Access">🔐 Access / Permission</option>
                            <option value="Financial">💰 Financial</option>
                            <option value="Other">🔹 Other</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Item Name</label>
                        <input type="text" id="f_item_name" name="item_name" required placeholder="e.g. Company Laptop">
                    </div>
                </div>

                <!-- Physical Details -->
                <div id="physicalSection" class="section-box blue" style="display:none">
                    <h5 style="color:#0c5460">📦 Physical Item Details</h5>
                    <div class="form-grid">
                        <div class="fg">
                            <label>Serial Number</label>
                            <input type="text" id="f_serial_number" name="serial_number" placeholder="SN123456789">
                        </div>
                        <div class="fg">
                            <label>Asset Sticker / Tag</label>
                            <input type="text" id="f_sticker_type" name="sticker_type" placeholder="AST-2024-001">
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="fg">
                        <label>Responsible Department</label>
                        <select id="f_dept" name="responsible_department" required>
                            <option value="">Select department…</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Status</label>
                        <select id="f_status" name="status" required>
                            <option value="Pending">⏳ Pending</option>
                            <option value="Completed">✅ Completed</option>
                            <option value="Not Applicable">🚫 Not Applicable</option>
                        </select>
                    </div>
                </div>

                <div class="fg" style="margin-bottom:14px">
                    <label>Description</label>
                    <textarea id="f_description" name="description" rows="2" placeholder="Detailed description…"></textarea>
                </div>

                <!-- Approval -->
                <div class="section-box">
                    <h5 style="color:var(--pink-dark)">✓ Approval &amp; Clearance</h5>
                    <div class="form-grid">
                        <div class="fg">
                            <label>Approval Status</label>
                            <select id="f_approval_status" name="approval_status" required>
                                <option value="Pending">⏳ Pending</option>
                                <option value="Approved">✅ Approved</option>
                                <option value="Rejected">❌ Rejected</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label>Approved By</label>
                            <input type="text" id="f_approved_by" name="approved_by" placeholder="Approver name or ID">
                        </div>
                        <div class="fg">
                            <label>Completion Date</label>
                            <input type="date" id="f_completed_date" name="completed_date">
                        </div>
                    </div>
                </div>

                <!-- Remarks -->
                <div class="section-box yellow">
                    <h5 style="color:#856404">📝 Remarks &amp; Notes</h5>
                    <div class="fg" style="margin-bottom:10px">
                        <label>Remarks</label>
                        <textarea id="f_remarks" name="remarks" rows="2" placeholder="Any exceptions or special notes…"></textarea>
                    </div>
                    <div class="fg">
                        <label>Internal Notes</label>
                        <textarea id="f_notes" name="notes" rows="2" placeholder="Internal reference notes…"></textarea>
                    </div>
                </div>

                <div style="text-align:center;margin-top:22px;display:flex;justify-content:center;gap:10px">
                    <button type="button" class="btn" style="background:#6c757d;color:#fff" onclick="closeItemModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">💾 Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════ MODAL: View Item ══════════════════ -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Checklist Item Details</h3>
            <button class="modal-close" onclick="closeViewModal()">×</button>
        </div>
        <div class="modal-body" id="viewContent"></div>
    </div>
</div>

<!-- ══════════════════ MODAL: Dept Sign-off ══════════════════ -->
<div id="signoffModal" class="modal-overlay">
    <div class="modal-box sm">
        <div class="modal-head">
            <h3>✍️ Departmental Sign-off</h3>
            <button class="modal-close" onclick="document.getElementById('signoffModal').style.display='none'">×</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="dept_signoff">
                <input type="hidden" name="checklist_id" id="so_checklist_id">
                <div class="fg" style="margin-bottom:14px">
                    <label>Employee</label>
                    <input type="text" id="so_employee" readonly style="background:#f8f9fa">
                </div>
                <div class="fg" style="margin-bottom:14px">
                    <label>Department</label>
                    <input type="text" id="so_dept" name="signoff_dept" readonly style="background:#f8f9fa">
                </div>
                <div class="fg" style="margin-bottom:14px">
                    <label>Signed Off By (Name / ID)</label>
                    <input type="text" name="signoff_by" required placeholder="Department head name or ID">
                </div>
                <div class="fg" style="margin-bottom:18px">
                    <label>Sign-off Remarks</label>
                    <textarea name="remarks" rows="3" placeholder="Any remarks or conditions…"></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:center">
                    <button type="button" class="btn" style="background:#6c757d;color:#fff" onclick="document.getElementById('signoffModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-purple">✍️ Record Sign-off</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════ MODAL: Bulk Deactivate ══════════════════ -->
<div id="bulkDeactivateModal" class="modal-overlay">
    <div class="modal-box sm">
        <div class="modal-head">
            <h3>⚡ Bulk System Access Deactivation</h3>
            <button class="modal-close" onclick="document.getElementById('bulkDeactivateModal').style.display='none'">×</button>
        </div>
        <div class="modal-body">
            <div class="section-box" style="margin-bottom:16px">
                <h5>Select which system access to mark as deactivated for ALL pending IT items:</h5>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;cursor:pointer">
                    <input type="checkbox" id="ba_email" checked> 📧 Email Accounts
                </label>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;cursor:pointer">
                    <input type="checkbox" id="ba_vpn" checked> 🔒 VPN Access
                </label>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;cursor:pointer">
                    <input type="checkbox" id="ba_ad" checked> 👤 Active Directory
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" id="ba_erp"> 🖥️ ERP / Business Systems
                </label>
            </div>
            <div class="fg" style="margin-bottom:16px">
                <label>Authorized By</label>
                <input type="text" id="ba_authorized" placeholder="IT Manager name or ID">
            </div>
            <div style="background:#fff3cd;padding:10px;border-radius:6px;font-size:13px;margin-bottom:16px">
                ⚠️ This will mark all matching pending IT items as Completed. This action is logged.
            </div>
            <div style="display:flex;gap:10px;justify-content:center">
                <button class="btn" style="background:#6c757d;color:#fff" onclick="document.getElementById('bulkDeactivateModal').style.display='none'">Cancel</button>
                <button class="btn btn-danger" onclick="executeBulkDeactivate()">⚡ Execute Deactivation</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirm -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-box sm">
        <div class="modal-head" style="background:linear-gradient(135deg,#dc3545,#c82333)">
            <h3>🗑️ Confirm Delete</h3>
            <button class="modal-close" onclick="document.getElementById('deleteModal').style.display='none'">×</button>
        </div>
        <div class="modal-body" style="text-align:center">
            <p style="font-size:16px;margin-bottom:20px">Are you sure you want to delete this checklist item? This cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="checklist_id" id="del_id">
                <button type="button" class="btn" style="background:#6c757d;color:#fff;margin-right:10px" onclick="document.getElementById('deleteModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-danger">🗑️ Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
const DATA = <?= json_encode(array_values($checklistItems)) ?>;

/* ── Tab Switching ── */
function switchTab(id, btn) {
    document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

/* ── Progress bar animation ── */
document.addEventListener('DOMContentLoaded', ()=>{
    document.querySelectorAll('.progress-fill[data-target]').forEach(el=>{
        const t = el.dataset.target;
        el.style.width='0%';
        setTimeout(()=>el.style.width=t, 400);
    });
    document.querySelectorAll('.clearance-mini-fill').forEach(el=>{
        const w = el.style.width;
        el.style.width='0%';
        setTimeout(()=>el.style.width=w, 500);
    });
});

/* ── Search ── */
document.getElementById('searchInput').addEventListener('input', function(){
    const q = this.value.toLowerCase();
    document.querySelectorAll('#checklistBody tr').forEach(r=>{
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
document.getElementById('assetSearch')?.addEventListener('input', function(){
    const q = this.value.toLowerCase();
    document.querySelectorAll('#assetBody tr').forEach(r=>{
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

/* ── Item Modal ── */
function openItemModal(mode, id='', defaultType='') {
    const modal = document.getElementById('itemModal');
    const form  = document.getElementById('itemForm');
    const exitSelect = document.getElementById('f_exit_id');
    
    if (mode==='add') {
        document.getElementById('itemModalTitle').textContent = 'Add Checklist Item';
        document.getElementById('f_action').value = 'add';
        form.reset();
        document.getElementById('f_checklist_id').value = '';
        if (defaultType) document.getElementById('f_item_type').value = defaultType;
        
        // Show only available exits (data-mode="add")
        Array.from(exitSelect.options).forEach(opt => {
            if (opt.dataset.mode === 'add') {
                opt.style.display = '';
            } else if (opt.dataset.mode === 'edit') {
                opt.style.display = 'none';
            }
        });
        
        togglePhysical();
    } else {
        document.getElementById('itemModalTitle').textContent = 'Edit Checklist Item';
        document.getElementById('f_action').value = 'update';
        document.getElementById('f_checklist_id').value = id;
        
        // Show all exits for edit mode (data-mode="edit")
        Array.from(exitSelect.options).forEach(opt => {
            if (opt.dataset.mode === 'edit') {
                opt.style.display = '';
            } else if (opt.dataset.mode === 'add') {
                opt.style.display = 'none';
            }
        });
        
        const item = DATA.find(x=>x.checklist_id==id);
        if (item) {
            document.getElementById('f_exit_id').value = item.exit_id || '';
            document.getElementById('f_item_type').value = item.item_type || 'Other';
            document.getElementById('f_item_name').value = item.item_name || '';
            document.getElementById('f_serial_number').value = item.serial_number || '';
            document.getElementById('f_sticker_type').value = item.sticker_type || '';
            document.getElementById('f_description').value = item.description || '';
            document.getElementById('f_dept').value = item.responsible_department || '';
            document.getElementById('f_status').value = item.status || '';
            document.getElementById('f_completed_date').value = item.completed_date || '';
            document.getElementById('f_approval_status').value = item.approval_status || 'Pending';
            document.getElementById('f_approved_by').value = item.approved_by || '';
            document.getElementById('f_remarks').value = item.remarks || '';
            document.getElementById('f_notes').value = item.notes || '';
            togglePhysical();
        }
    }
    modal.style.display = 'block';
}
function closeItemModal() { document.getElementById('itemModal').style.display='none'; }

function togglePhysical() {
    const t = document.getElementById('f_item_type').value;
    document.getElementById('physicalSection').style.display = t==='Physical' ? 'block' : 'none';
}

/* ── View Modal ── */
function viewItem(id) {
    const item = DATA.find(x=>x.checklist_id==id);
    if (!item) return;
    const isCleared = item.status==='Completed' && item.approval_status==='Approved';
    const html = `
        <h4 style="color:var(--pink)">${item.item_name}</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:14px 0;font-size:14px">
            <div><strong>Employee:</strong><br>${item.employee_name} (#${item.employee_number})</div>
            <div><strong>Department:</strong><br>${item.responsible_department}</div>
            <div><strong>Item Type:</strong><br><span class="badge-pill b-${item.item_type.toLowerCase()}">${item.item_type}</span></div>
            <div><strong>Status:</strong><br><span class="badge-pill b-${item.status.toLowerCase().replace(/ /g,'')}">${item.status}</span></div>
            ${item.serial_number?`<div><strong>Serial Number:</strong><br>${item.serial_number}</div>`:''}
            ${item.sticker_type?`<div><strong>Asset Tag:</strong><br>${item.sticker_type}</div>`:''}
        </div>
        <div style="margin:12px 0;padding:12px;background:#f8f9fa;border-left:4px solid var(--pink);border-radius:6px;font-size:14px">
            <strong>Approval:</strong> <span class="badge-pill b-${item.approval_status.toLowerCase()}">${item.approval_status}</span>
            ${item.approved_by?`<br><small>By: ${item.approved_by}${item.approved_date?' on '+new Date(item.approved_date).toLocaleDateString():''}</small>`:''}
        </div>
        <div style="margin:12px 0;padding:12px;background:${isCleared?'#d4edda':'#fff3cd'};border-radius:6px;font-size:14px;font-weight:700;color:${isCleared?'#155724':'#856404'}">
            ${isCleared?'✓ CLEARED':'⏳ PENDING CLEARANCE'}
        </div>
        ${item.description?`<div style="margin:10px 0;font-size:14px"><strong>Description:</strong><br>${item.description}</div>`:''}
        ${item.remarks?`<div style="background:#fffdf0;border-left:4px solid #ffc107;padding:10px;border-radius:4px;font-size:13px;margin:10px 0"><strong>Remarks:</strong> ${item.remarks}</div>`:''}
        ${item.notes?`<div style="background:#f0f0f0;padding:10px;border-radius:6px;font-size:13px;margin:10px 0"><strong>Notes:</strong> ${item.notes}</div>`:''}
        <div style="text-align:center;margin-top:20px;display:flex;justify-content:center;gap:10px">
            <button class="btn btn-warning" onclick="openItemModal('edit',${id});closeViewModal()">✏️ Edit</button>
            <button class="btn" style="background:#6c757d;color:#fff" onclick="closeViewModal()">Close</button>
        </div>`;
    document.getElementById('viewContent').innerHTML = html;
    document.getElementById('viewModal').style.display = 'block';
}
function closeViewModal() { document.getElementById('viewModal').style.display='none'; }

/* ── Delete ── */
function confirmDelete(id) {
    document.getElementById('del_id').value = id;
    document.getElementById('deleteModal').style.display = 'block';
}

/* ── Sign-off Modal ── */
function openSignoffModal(id, dept, employee) {
    document.getElementById('so_checklist_id').value = id;
    document.getElementById('so_dept').value = dept;
    document.getElementById('so_employee').value = employee;
    document.getElementById('signoffModal').style.display = 'block';
}

/* ── Bulk Deactivate ── */
function openBulkDeactivateModal() {
    document.getElementById('bulkDeactivateModal').style.display = 'block';
}
function executeBulkDeactivate() {
    const authorized = document.getElementById('ba_authorized').value.trim();
    if (!authorized) { alert('Please enter the authorizing person name/ID.'); return; }
    // Collect all pending IT items from DATA and submit one by one via hidden forms
    const itItems = DATA.filter(x=> (x.responsible_department==='IT' || x.item_type==='Access') && x.status!=='Completed');
    if (!itItems.length) { alert('No pending IT/Access items found.'); return; }
    if (!confirm(`This will mark ${itItems.length} item(s) as deactivated. Proceed?`)) return;
    // Submit first item and chain would need server support; simplify: submit a bulk form
    let formHtml = '<form method="POST" id="bulkDeactivateForm">';
    formHtml += '<input type="hidden" name="action" value="bulk_update">';
    itItems.forEach(item=>{
        formHtml += `<input type="hidden" name="checklist_items[${item.checklist_id}][status]" value="Completed">`;
        formHtml += `<input type="hidden" name="checklist_items[${item.checklist_id}][completed_date]" value="${new Date().toISOString().split('T')[0]}">`;
        formHtml += `<input type="hidden" name="checklist_items[${item.checklist_id}][notes]" value="Bulk deactivated by ${authorized}">`;
    });
    formHtml += '</form>';
    document.body.insertAdjacentHTML('beforeend', formHtml);
    document.getElementById('bulkDeactivateForm').submit();
}

/* ── Bulk Update Modal (original) ── */
function openBulkModal() {
    alert('Bulk update feature: select multiple rows and update status in a batch. (Extend this as needed.)');
}

/* ── Status auto-complete date ── */
document.getElementById('f_status')?.addEventListener('change', function(){
    const df = document.getElementById('f_completed_date');
    if (this.value==='Completed' && !df.value) df.value = new Date().toISOString().split('T')[0];
    else if (this.value!=='Completed') df.value='';
});

/* ── Close modals on backdrop click ── */
document.querySelectorAll('.modal-overlay').forEach(m=>{
    m.addEventListener('click', e=>{ if(e.target===m) m.style.display='none'; });
});

/* ── ESC key ── */
document.addEventListener('keydown', e=>{
    if (e.key==='Escape') document.querySelectorAll('.modal-overlay').forEach(m=>m.style.display='none');
    if ((e.ctrlKey||e.metaKey)&&e.key==='n') { e.preventDefault(); openItemModal('add'); }
});

/* ── Auto-hide alerts ── */
setTimeout(()=>{
    document.querySelectorAll('.alert').forEach(a=>{
        a.style.transition='opacity .5s';
        a.style.opacity='0';
        setTimeout(()=>a.remove(), 500);
    });
}, 5000);
</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>