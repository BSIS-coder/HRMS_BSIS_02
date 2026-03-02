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

// Get messages from session and clear them
$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

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
                    $documentUrl = '';
                    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/exit_documents/';
                        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                        $fileName = time() . '_' . basename($_FILES['document_file']['name']);
                        $targetPath = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $targetPath)) $documentUrl = $targetPath;
                    }
                    $stmt_exit = $pdo->prepare("SELECT employee_id FROM exits WHERE exit_id = ?");
                    $stmt_exit->execute([$_POST['exit_id']]);
                    $exit_data = $stmt_exit->fetch(PDO::FETCH_ASSOC);
                    $stmt = $pdo->prepare("INSERT INTO exit_documents (exit_id, employee_id, document_type, document_name, document_url, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$_POST['exit_id'], $exit_data['employee_id'], $_POST['document_type'], $_POST['document_name'], $documentUrl, $_POST['notes']]);
                    $_SESSION['message'] = "Exit document added successfully!";
                    $_SESSION['message_type'] = "success";
                    header("Location: exit_documents.php"); exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error adding document: " . $e->getMessage();
                    $_SESSION['message_type'] = "error";
                    header("Location: exit_documents.php"); exit;
                }
                break;
            
            case 'update':
                try {
                    $documentUrl = $_POST['existing_document_url'];
                    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/exit_documents/';
                        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                        if (!empty($_POST['existing_document_url']) && file_exists($_POST['existing_document_url'])) unlink($_POST['existing_document_url']);
                        $fileName = time() . '_' . basename($_FILES['document_file']['name']);
                        $targetPath = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $targetPath)) $documentUrl = $targetPath;
                    }
                    $stmt_exit = $pdo->prepare("SELECT employee_id FROM exits WHERE exit_id = ?");
                    $stmt_exit->execute([$_POST['exit_id']]);
                    $exit_data = $stmt_exit->fetch(PDO::FETCH_ASSOC);
                    $stmt = $pdo->prepare("UPDATE exit_documents SET exit_id=?, employee_id=?, document_type=?, document_name=?, document_url=?, notes=? WHERE document_id=?");
                    $stmt->execute([$_POST['exit_id'], $exit_data['employee_id'], $_POST['document_type'], $_POST['document_name'], $documentUrl, $_POST['notes'], $_POST['document_id']]);
                    $_SESSION['message'] = "Exit document updated successfully!";
                    $_SESSION['message_type'] = "success";
                    header("Location: exit_documents.php"); exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error updating document: " . $e->getMessage();
                    $_SESSION['message_type'] = "error";
                    header("Location: exit_documents.php"); exit;
                }
                break;
            
            case 'delete':
                try {
                    $stmt = $pdo->prepare("SELECT document_url FROM exit_documents WHERE document_id=?");
                    $stmt->execute([$_POST['document_id']]);
                    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($doc && !empty($doc['document_url']) && file_exists($doc['document_url'])) unlink($doc['document_url']);
                    $stmt = $pdo->prepare("DELETE FROM exit_documents WHERE document_id=?");
                    $stmt->execute([$_POST['document_id']]);
                    $_SESSION['message'] = "Exit document deleted successfully!";
                    $_SESSION['message_type'] = "success";
                    header("Location: exit_documents.php"); exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error deleting document: " . $e->getMessage();
                    $_SESSION['message_type'] = "error";
                    header("Location: exit_documents.php"); exit;
                }
                break;
        }
    }
}

// Fetch exit documents with related data
$stmt = $pdo->query("
    SELECT 
        ed.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        e.exit_date,
        e.exit_type
    FROM exit_documents ed
    LEFT JOIN employee_profiles ep ON ed.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN exits e ON ed.exit_id = e.exit_id
    ORDER BY ed.uploaded_date DESC
");
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown
$stmt = $pdo->query("
    SELECT e.exit_id, e.exit_type, e.exit_date, CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY e.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown
$stmt = $pdo->query("
    SELECT ep.employee_id, ep.employee_number, CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
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
    <title>Exit Documents Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose2024">
    <style>
        :root {
            --pink: #E91E63;
            --pink-light: #F06292;
            --pink-dark: #C2185B;
            --pink-lighter: #F8BBD0;
            --pink-pale: #FCE4EC;
            --teal: #00BCD4;
            --teal-dark: #0097A7;
            --violet: #7C3AED;
            --amber: #F59E0B;
            --emerald: #10B981;
            --slate: #475569;
        }

        body { background: var(--pink-pale); font-family: 'Segoe UI', sans-serif; }
        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }

        .main-content { background: var(--pink-pale); padding: 24px; }

        .page-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }
        .page-header-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, var(--pink), var(--pink-light));
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(233,30,99,0.3);
        }
        .section-title { color: var(--pink); font-weight: 700; font-size: 1.6rem; margin: 0; }
        .section-subtitle { color: #888; font-size: 0.85rem; margin: 0; }

        /* ─── Quick-Action Cards ─── */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .qa-card {
            background: white;
            border-radius: 14px;
            padding: 20px 18px;
            cursor: pointer;
            transition: all 0.25s ease;
            border: 2px solid transparent;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex; flex-direction: column; gap: 10px;
            position: relative; overflow: hidden;
        }
        .qa-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: var(--card-color);
        }
        .qa-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 28px rgba(0,0,0,0.12);
            border-color: var(--card-color);
        }
        .qa-icon {
            width: 42px; height: 42px;
            background: var(--card-bg);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .qa-label { font-weight: 700; font-size: 0.9rem; color: #2d3748; }
        .qa-desc { font-size: 0.75rem; color: #718096; line-height: 1.4; }

        .qa-pink  { --card-color: #E91E63; --card-bg: #FCE4EC; }
        .qa-teal  { --card-color: #00BCD4; --card-bg: #E0F7FA; }
        .qa-violet{ --card-color: #7C3AED; --card-bg: #EDE9FE; }
        .qa-amber { --card-color: #F59E0B; --card-bg: #FEF3C7; }

        /* ─── Controls ─── */
        .controls {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
        }
        .search-box { position: relative; flex: 1; max-width: 400px; }
        .search-box input {
            width: 100%; padding: 11px 15px 11px 42px;
            border: 2px solid #e0e0e0; border-radius: 25px; font-size: 15px;
            transition: all 0.3s;
        }
        .search-box input:focus { border-color: var(--pink); outline: none; box-shadow: 0 0 10px rgba(233,30,99,0.2); }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #999; }

        .filter-tabs {
            display: flex; gap: 8px; flex-wrap: wrap;
        }
        .filter-tab {
            padding: 7px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;
            border: 2px solid #e0e0e0; background: white; cursor: pointer;
            transition: all 0.2s; color: #666;
        }
        .filter-tab.active, .filter-tab:hover {
            background: var(--pink); color: white; border-color: var(--pink);
        }

        /* ─── Buttons ─── */
        .btn {
            padding: 10px 22px; border: none; border-radius: 25px;
            font-size: 15px; font-weight: 600; cursor: pointer;
            transition: all 0.25s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary { background: linear-gradient(135deg, var(--pink), var(--pink-light)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(233,30,99,0.4); }
        .btn-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .btn-danger  { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
        .btn-warning { background: linear-gradient(135deg, #ffc107, #e0a800); color: #333; }
        .btn-info    { background: linear-gradient(135deg, #17a2b8, #138496); color: white; }
        .btn-violet  { background: linear-gradient(135deg, #7C3AED, #9F7AEA); color: white; }
        .btn-teal    { background: linear-gradient(135deg, #00BCD4, #0097A7); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn:hover:not(.btn-primary) { transform: translateY(-1px); filter: brightness(1.08); }

        /* ─── Table ─── */
        .table-container {
            background: white; border-radius: 14px;
            overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.07);
        }
        .table { width: 100%; border-collapse: collapse; }
        .table th {
            background: linear-gradient(135deg, var(--pink-lighter), #f1f5f9);
            padding: 14px 16px; text-align: left; font-weight: 700;
            color: var(--pink-dark); border-bottom: 2px solid #e9ecef; font-size: 13px;
        }
        .table td { padding: 13px 16px; border-bottom: 1px solid #f5f5f5; vertical-align: middle; font-size: 14px; }
        .table tbody tr:hover { background: #FFF5F7; }

        .badge-type {
            padding: 4px 11px; border-radius: 20px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .type-clearance         { background: #E0F2FE; color: #0369A1; }
        .type-resignation       { background: #FEE2E2; color: #B91C1C; }
        .type-final-pay         { background: #DCFCE7; color: #15803D; }
        .type-nda               { background: #FEF9C3; color: #854D0E; }
        .type-certificate       { background: #EDE9FE; color: #6D28D9; }
        .type-exit-interview    { background: #E0F7FA; color: #0097A7; }
        .type-acknowledgment    { background: #FFF3E0; color: #E65100; }
        .type-clearance-form    { background: #E8F5E9; color: #2E7D32; }
        .type-kt-documentation  { background: #F3E5F5; color: #6A1B9A; }
        .type-sign-off          { background: #E3F2FD; color: #1565C0; }
        .type-other             { background: #F3F4F6; color: #374151; }

        .actions-cell { display: flex; gap: 5px; flex-wrap: wrap; }

        /* ─── Modal ─── */
        .modal {
            display: none; position: fixed; z-index: 1050;
            left: 0; top: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white; margin: 3% auto; padding: 0;
            border-radius: 18px; width: 92%; max-width: 680px;
            max-height: 92vh; overflow-y: auto;
            box-shadow: 0 24px 50px rgba(0,0,0,0.25);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn { from { transform: translateY(-40px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header {
            padding: 22px 28px; border-radius: 18px 18px 0 0;
            display: flex; align-items: center; gap: 14px;
        }
        .modal-header.pink   { background: linear-gradient(135deg, var(--pink), var(--pink-light)); }
        .modal-header.teal   { background: linear-gradient(135deg, var(--teal-dark), var(--teal)); }
        .modal-header.violet { background: linear-gradient(135deg, #5B21B6, #7C3AED); }
        .modal-header.amber  { background: linear-gradient(135deg, #D97706, var(--amber)); }
        .modal-header.emerald{ background: linear-gradient(135deg, #059669, var(--emerald)); }
        .modal-header h3 { color: white; margin: 0; font-size: 1.2rem; font-weight: 700; }
        .modal-header-icon { font-size: 22px; }
        .close-btn { margin-left: auto; background: rgba(255,255,255,0.25); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .close-btn:hover { background: rgba(255,255,255,0.45); }

        .modal-body { padding: 28px; }

        .form-section {
            background: #f8fafc; border-radius: 10px;
            padding: 18px; margin-bottom: 20px; border-left: 4px solid var(--pink);
        }
        .form-section-title { font-size: 13px; font-weight: 700; color: var(--pink-dark); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 13px; }
        .form-control {
            width: 100%; padding: 9px 14px;
            border: 2px solid #e5e7eb; border-radius: 8px;
            font-size: 14px; transition: all 0.3s; color: #1f2937;
        }
        .form-control:focus { border-color: var(--pink); outline: none; box-shadow: 0 0 0 3px rgba(233,30,99,0.1); }
        .form-row { display: flex; gap: 16px; }
        .form-col { flex: 1; }

        /* Clearance-form specific */
        .clearance-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .clearance-item {
            background: white; border: 2px solid #e5e7eb;
            border-radius: 8px; padding: 12px;
            display: flex; align-items: center; gap: 10px; cursor: pointer;
            transition: all 0.2s;
        }
        .clearance-item:hover, .clearance-item.selected { border-color: var(--emerald); background: #F0FDF4; }
        .clearance-check { width: 18px; height: 18px; border-radius: 4px; border: 2px solid #d1d5db; display: flex; align-items: center; justify-content: center; font-size: 11px; transition: all 0.2s; }
        .clearance-item.selected .clearance-check { background: var(--emerald); border-color: var(--emerald); color: white; }
        .clearance-label { font-size: 13px; font-weight: 600; color: #374151; }
        .clearance-dept { font-size: 11px; color: #9CA3AF; }

        /* KT grid */
        .kt-topics { display: flex; flex-direction: column; gap: 8px; }
        .kt-item {
            background: white; border: 2px solid #e5e7eb; border-radius: 8px;
            padding: 10px 14px; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; gap: 10px;
        }
        .kt-item:hover, .kt-item.selected { border-color: var(--violet); background: #FAF5FF; }
        .kt-item.selected .kt-check { background: var(--violet); border-color: var(--violet); color: white; }
        .kt-check { width: 18px; height: 18px; border-radius: 4px; border: 2px solid #d1d5db; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
        .kt-tag { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #EDE9FE; color: #6D28D9; margin-left: auto; }

        /* Sign-off steps */
        .signoff-steps { display: flex; flex-direction: column; gap: 8px; }
        .signoff-step {
            background: white; border: 2px solid #e5e7eb; border-radius: 8px;
            padding: 12px 14px; display: flex; align-items: center; gap: 12px;
        }
        .step-num { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #1565C0, #1E88E5); color: white; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .step-info { flex: 1; }
        .step-name { font-size: 13px; font-weight: 600; color: #1f2937; }
        .step-dept { font-size: 11px; color: #6B7280; }
        .step-status { font-size: 11px; font-weight: 600; }
        .step-status.pending { color: #D97706; }
        .step-status.approved{ color: #059669; }
        .step-toggle { padding: 4px 10px; font-size: 11px; border-radius: 12px; border: none; cursor: pointer; font-weight: 600; }
        .step-toggle.pending  { background: #FEF3C7; color: #D97706; }
        .step-toggle.approved { background: #D1FAE5; color: #059669; }

        /* Progress bar */
        .progress-track { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-top: 4px; }
        .progress-fill  { height: 100%; background: linear-gradient(90deg, var(--pink), var(--pink-light)); border-radius: 3px; transition: width 0.4s ease; }

        /* File upload */
        .file-upload-zone {
            border: 2px dashed #d1d5db; border-radius: 10px;
            padding: 24px; text-align: center; cursor: pointer;
            transition: all 0.2s; color: #9CA3AF;
        }
        .file-upload-zone:hover, .file-upload-zone.dragover { border-color: var(--pink); background: #FFF5F7; color: var(--pink); }
        .file-upload-zone input { display: none; }
        .file-upload-zone .upload-icon { font-size: 28px; margin-bottom: 8px; }
        .file-upload-zone .upload-text { font-size: 14px; font-weight: 600; }
        .file-upload-zone .upload-sub  { font-size: 12px; margin-top: 4px; }
        .file-chosen { margin-top: 10px; padding: 8px 12px; background: #F0FDF4; border-radius: 6px; font-size: 13px; color: #059669; font-weight: 600; display: none; }

        .alert { padding: 14px 18px; margin-bottom: 20px; border-radius: 10px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error   { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }

        .no-results { text-align: center; padding: 60px 20px; color: #9CA3AF; }
        .no-results .no-icon { font-size: 3.5rem; margin-bottom: 14px; }

        .modal-footer { padding: 18px 28px; border-top: 1px solid #f3f4f6; display: flex; justify-content: flex-end; gap: 10px; }

        .doc-count { font-size: 12px; color: #9CA3AF; margin-left: 6px; font-weight: 500; }

        @media (max-width: 768px) {
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .clearance-grid { grid-template-columns: 1fr; }
            .form-row { flex-direction: column; }
            .actions-cell { flex-direction: column; }
        }
        @media (max-width: 480px) {
            .quick-actions { grid-template-columns: 1fr; }
        }
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
                <div class="page-header-icon">📁</div>
                <div>
                    <h2 class="section-title">Exit Documents Management</h2>
                    <p class="section-subtitle">Manage offboarding paperwork, clearances & knowledge transfer</p>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= $messageType === 'success' ? '✅' : '❌' ?>
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- Quick Action Cards -->
            <div class="quick-actions">
                <div class="qa-card qa-pink" onclick="openModal('add')">
                    <div class="qa-icon">📄</div>
                    <div class="qa-label">Upload Document</div>
                    <div class="qa-desc">Add any exit-related document to an employee's record</div>
                </div>
                <div class="qa-card qa-teal" onclick="openAcknowledgmentModal()">
                    <div class="qa-icon">✍️</div>
                    <div class="qa-label">Acknowledgment Letter</div>
                    <div class="qa-desc">Generate & record signed acknowledgment of exit terms</div>
                </div>
                <div class="qa-card qa-amber" onclick="openClearanceModal()">
                    <div class="qa-icon">✅</div>
                    <div class="qa-label">Clearance Form</div>
                    <div class="qa-desc">Track multi-department clearance sign-offs</div>
                </div>
                <div class="qa-card qa-violet" onclick="openKTModal()">
                    <div class="qa-icon">🧠</div>
                    <div class="qa-label">KT Documentation</div>
                    <div class="qa-desc">Knowledge transfer checklist & handover notes</div>
                </div>
                <!-- Sign-off is also accessible via the 5th card -->
                <div class="qa-card" style="--card-color:#1565C0;--card-bg:#EFF6FF;" onclick="openSignOffModal()">
                    <div class="qa-icon">🖊️</div>
                    <div class="qa-label">Sign-off Form</div>
                    <div class="qa-desc">Multi-step sequential sign-off workflow tracker</div>
                </div>
            </div>

            <!-- Table Controls -->
            <div class="controls">
                <div class="search-box">
                    <span class="search-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchInput" placeholder="Search by employee, type, or document name…">
                </div>
                <div class="filter-tabs">
                    <span class="filter-tab active" data-type="all">All <span class="doc-count">(<?= count($documents) ?>)</span></span>
                    <span class="filter-tab" data-type="Acknowledgment">Acknowledgment</span>
                    <span class="filter-tab" data-type="Clearance Form">Clearance</span>
                    <span class="filter-tab" data-type="KT Documentation">KT Docs</span>
                    <span class="filter-tab" data-type="Sign-off">Sign-off</span>
                </div>
            </div>

            <!-- Documents Table -->
            <div class="table-container">
                <table class="table" id="documentsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Document Type</th>
                            <th>Document Name</th>
                            <th>Exit Type</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="documentsTableBody">
                        <?php foreach ($documents as $doc): ?>
                        <?php
                            $typeKey = strtolower(str_replace([' ', '/'], ['-', '-'], $doc['document_type']));
                        ?>
                        <tr data-type="<?= htmlspecialchars($doc['document_type']) ?>">
                            <td><strong>#<?= htmlspecialchars($doc['document_id']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($doc['employee_name']) ?></strong><br>
                                <small style="color:#9CA3AF">👤 <?= htmlspecialchars($doc['employee_number']) ?></small>
                            </td>
                            <td>
                                <span class="badge-type type-<?= $typeKey ?>">
                                    <?= htmlspecialchars($doc['document_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($doc['document_name']) ?></td>
                            <td><?= htmlspecialchars($doc['exit_type']) ?></td>
                            <td><?= date('M d, Y', strtotime($doc['uploaded_date'])) ?></td>
                            <td>
                                <div class="actions-cell">
                                    <?php if (!empty($doc['document_url']) && file_exists($doc['document_url'])): ?>
                                    <a href="<?= htmlspecialchars($doc['document_url']) ?>" class="btn btn-info btn-sm" target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-info btn-sm" onclick="viewDocumentDetails(<?= $doc['document_id'] ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-warning btn-sm" onclick="editDocument(<?= $doc['document_id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteDocument(<?= $doc['document_id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($documents)): ?>
                <div class="no-results">
                    <div class="no-icon">📂</div>
                    <h4>No exit documents yet</h4>
                    <p>Use the cards above to start creating offboarding documents.</p>
                </div>
                <?php endif; ?>
            </div>
        </div><!-- /main-content -->
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL 1 — Add / Edit Document (existing, enhanced)
══════════════════════════════════════════════════ -->
<div id="documentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header pink">
            <span class="modal-header-icon">📄</span>
            <h3 id="modalTitle">Add New Exit Document</h3>
            <button class="close-btn" onclick="closeModal('documentModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="documentForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="action"               name="action" value="add">
                <input type="hidden" id="document_id"          name="document_id">
                <input type="hidden" id="existing_document_url" name="existing_document_url">

                <div class="form-section">
                    <div class="form-section-title">Exit Record</div>
                    <div class="form-group">
                        <label>Exit Record</label>
                        <select id="exit_id" name="exit_id" class="form-control" required>
                            <option value="">Select exit record…</option>
                            <?php foreach ($exits as $exit): ?>
                            <option value="<?= $exit['exit_id'] ?>">
                                <?= htmlspecialchars($exit['employee_name']) ?> — <?= htmlspecialchars($exit['exit_type']) ?> (<?= date('M d, Y', strtotime($exit['exit_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Document Details</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Document Type</label>
                                <select id="document_type" name="document_type" class="form-control" required>
                                    <option value="">Select type…</option>
                                    <optgroup label="Standard Documents">
                                        <option value="Clearance">Clearance</option>
                                        <option value="Resignation">Resignation Letter</option>
                                        <option value="Final Pay">Final Pay Slip</option>
                                        <option value="NDA">Non-Disclosure Agreement</option>
                                        <option value="Certificate">Certificate of Employment</option>
                                        <option value="Exit Interview">Exit Interview Form</option>
                                    </optgroup>
                                    <optgroup label="Offboarding Workflows">
                                        <option value="Acknowledgment">Acknowledgment Letter</option>
                                        <option value="Clearance Form">Clearance Form</option>
                                        <option value="KT Documentation">KT Documentation</option>
                                        <option value="Sign-off">Sign-off Form</option>
                                    </optgroup>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Document Name</label>
                                <input type="text" id="document_name" name="document_name" class="form-control" required placeholder="e.g., Final Clearance Form">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">File & Notes</div>
                    <div class="form-group">
                        <label>Upload File</label>
                        <div class="file-upload-zone" id="dropZone" onclick="document.getElementById('document_file').click()">
                            <div class="upload-icon">📎</div>
                            <div class="upload-text">Click to choose or drag & drop</div>
                            <div class="upload-sub">PDF, DOC, DOCX, JPG, PNG accepted</div>
                            <input type="file" id="document_file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                        <div class="file-chosen" id="fileChosen"></div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Additional notes…"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#e5e7eb;color:#374151;" onclick="closeModal('documentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL 2 — Acknowledgment Letter
══════════════════════════════════════════════════ -->
<div id="acknowledgmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header teal">
            <span class="modal-header-icon">✍️</span>
            <h3>Acknowledgment Letter</h3>
            <button class="close-btn" onclick="closeModal('acknowledgmentModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="acknowledgmentForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="document_type" value="Acknowledgment">

                <div class="form-section">
                    <div class="form-section-title">Employee & Exit Record</div>
                    <div class="form-group">
                        <label>Select Exit Record</label>
                        <select name="exit_id" class="form-control" required>
                            <option value="">Select exit record…</option>
                            <?php foreach ($exits as $exit): ?>
                            <option value="<?= $exit['exit_id'] ?>">
                                <?= htmlspecialchars($exit['employee_name']) ?> — <?= htmlspecialchars($exit['exit_type']) ?> (<?= date('M d, Y', strtotime($exit['exit_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section" style="border-left-color: var(--teal);">
                    <div class="form-section-title" style="color:var(--teal-dark)">Acknowledgment Details</div>
                    <div class="form-group">
                        <label>Letter Title / Reference</label>
                        <input type="text" name="document_name" class="form-control" required placeholder="e.g., Acknowledgment of Resignation — March 2025">
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Employee's Last Day</label>
                                <input type="date" id="ack_last_day" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Notice Period (days)</label>
                                <input type="number" id="ack_notice" class="form-control" placeholder="e.g., 30" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Items Acknowledged</label>
                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
                            <?php
                            $ackItems = [
                                ['id'=>'ack_resignation','label'=>'Resignation / Exit accepted'],
                                ['id'=>'ack_nda','label'=>'NDA obligations confirmed'],
                                ['id'=>'ack_ip','label'=>'Intellectual property returned'],
                                ['id'=>'ack_benefits','label'=>'Benefits cessation date noted'],
                                ['id'=>'ack_final_pay','label'=>'Final pay computation acknowledged'],
                                ['id'=>'ack_reference','label'=>'Reference letter policy explained'],
                            ];
                            foreach($ackItems as $item): ?>
                            <label style="display:flex;align-items:center;gap:10px;font-weight:500;cursor:pointer;font-size:14px;">
                                <input type="checkbox" id="<?= $item['id'] ?>" style="width:16px;height:16px;accent-color:var(--teal);"> <?= $item['label'] ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Additional Remarks</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Any specific conditions, remarks, or agreements…"></textarea>
                    </div>
                </div>

                <div class="form-section" style="border-left-color:#9CA3AF;">
                    <div class="form-section-title" style="color:#6B7280;">Attach Signed Copy (Optional)</div>
                    <div class="file-upload-zone" onclick="document.getElementById('ack_file').click()">
                        <div class="upload-icon">📎</div>
                        <div class="upload-text">Upload signed acknowledgment</div>
                        <div class="upload-sub">PDF, DOC, DOCX, JPG, PNG</div>
                        <input type="file" id="ack_file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="showFileName(this,'ack_chosen')">
                    </div>
                    <div class="file-chosen" id="ack_chosen"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#e5e7eb;color:#374151;" onclick="closeModal('acknowledgmentModal')">Cancel</button>
                    <button type="button" class="btn btn-teal" onclick="previewAcknowledgment()">👁 Preview Letter</button>
                    <button type="submit" form="acknowledgmentForm" class="btn" style="background:linear-gradient(135deg,var(--teal-dark),var(--teal));color:white;">💾 Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL 3 — Clearance Form
══════════════════════════════════════════════════ -->
<div id="clearanceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header amber">
            <span class="modal-header-icon">✅</span>
            <h3>Clearance Form</h3>
            <button class="close-btn" onclick="closeModal('clearanceModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="clearanceForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="document_type" value="Clearance Form">

                <div class="form-section">
                    <div class="form-section-title">Exit Record</div>
                    <div class="form-group">
                        <label>Select Exit Record</label>
                        <select name="exit_id" class="form-control" required>
                            <option value="">Select exit record…</option>
                            <?php foreach ($exits as $exit): ?>
                            <option value="<?= $exit['exit_id'] ?>">
                                <?= htmlspecialchars($exit['employee_name']) ?> — <?= htmlspecialchars($exit['exit_type']) ?> (<?= date('M d, Y', strtotime($exit['exit_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Clearance Form Title</label>
                        <input type="text" name="document_name" class="form-control" required placeholder="e.g., Employee Clearance — John Doe">
                    </div>
                </div>

                <div class="form-section" style="border-left-color:var(--emerald);">
                    <div class="form-section-title" style="color:#065F46;">Department Clearances</div>
                    <p style="font-size:13px;color:#6B7280;margin-bottom:12px;">Select all departments that have granted clearance:</p>
                    <div class="clearance-grid" id="clearanceGrid">
                        <?php
                        $depts = [
                            ['icon'=>'💻','name'=>'IT Department','desc'=>'Equipment, accounts, access'],
                            ['icon'=>'📚','name'=>'Library','desc'=>'Books, resources returned'],
                            ['icon'=>'💰','name'=>'Finance','desc'=>'Loans, allowances settled'],
                            ['icon'=>'🏠','name'=>'Administration','desc'=>'Office keys, ID, parking'],
                            ['icon'=>'👥','name'=>'HR Department','desc'=>'Files, contracts, exit forms'],
                            ['icon'=>'📦','name'=>'Warehouse','desc'=>'Assets, tools returned'],
                            ['icon'=>'🔒','name'=>'Security','desc'=>'Access cards, badges'],
                            ['icon'=>'🏥','name'=>'Medical/Health','desc'=>'Medical clearance issued'],
                            ['icon'=>'📊','name'=>'Operations','desc'=>'Projects, tasks handed over'],
                            ['icon'=>'⚖️','name'=>'Legal','desc'=>'NDA, compliance confirmed'],
                        ];
                        foreach($depts as $d): ?>
                        <div class="clearance-item" onclick="toggleClearance(this)">
                            <div class="clearance-check">✓</div>
                            <div>
                                <div class="clearance-label"><?= $d['icon'] ?> <?= $d['name'] ?></div>
                                <div class="clearance-dept"><?= $d['desc'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:12px;">
                        <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Clearance Progress</label>
                        <div class="progress-track"><div class="progress-fill" id="clearanceProgress" style="width:0%"></div></div>
                        <div style="font-size:12px;color:#9CA3AF;margin-top:4px;" id="clearanceCount">0 of <?= count($depts) ?> departments cleared</div>
                    </div>
                </div>

                <div class="form-section" style="border-left-color:#9CA3AF;">
                    <div class="form-section-title" style="color:#6B7280">Remarks & Attachment</div>
                    <div class="form-group">
                        <label>Clearance Notes / Pending Items</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Note any pending items or conditions…"></textarea>
                    </div>
                    <div class="file-upload-zone" onclick="document.getElementById('cl_file').click()">
                        <div class="upload-icon">📎</div>
                        <div class="upload-text">Attach signed clearance document</div>
                        <div class="upload-sub">PDF, DOC, JPG, PNG</div>
                        <input type="file" id="cl_file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="showFileName(this,'cl_chosen')">
                    </div>
                    <div class="file-chosen" id="cl_chosen"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#e5e7eb;color:#374151;" onclick="closeModal('clearanceModal')">Cancel</button>
                    <button type="submit" class="btn" style="background:linear-gradient(135deg,#D97706,var(--amber));color:white;">💾 Save Clearance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL 4 — KT Documentation
══════════════════════════════════════════════════ -->
<div id="ktModal" class="modal">
    <div class="modal-content">
        <div class="modal-header violet">
            <span class="modal-header-icon">🧠</span>
            <h3>KT Documentation</h3>
            <button class="close-btn" onclick="closeModal('ktModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="ktForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="document_type" value="KT Documentation">

                <div class="form-section">
                    <div class="form-section-title">Exit Record & KT Details</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Exit Record</label>
                                <select name="exit_id" class="form-control" required>
                                    <option value="">Select exit record…</option>
                                    <?php foreach ($exits as $exit): ?>
                                    <option value="<?= $exit['exit_id'] ?>">
                                        <?= htmlspecialchars($exit['employee_name']) ?> — <?= htmlspecialchars($exit['exit_type']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>KT Document Title</label>
                                <input type="text" name="document_name" class="form-control" required placeholder="e.g., KT Handover — Jane Smith">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>KT Recipient (Successor)</label>
                                <select class="form-control" id="kt_recipient">
                                    <option value="">Select employee…</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>">
                                        <?= htmlspecialchars($emp['employee_name']) ?> (<?= htmlspecialchars($emp['employee_number']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>KT Target Completion Date</label>
                                <input type="date" id="kt_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section" style="border-left-color:var(--violet);">
                    <div class="form-section-title" style="color:#5B21B6;">KT Checklist Topics</div>
                    <p style="font-size:13px;color:#6B7280;margin-bottom:12px;">Mark topics that have been covered in handover sessions:</p>
                    <div class="kt-topics" id="ktTopics">
                        <?php
                        $ktItems = [
                            ['label'=>'Ongoing Projects & Status','tag'=>'Critical'],
                            ['label'=>'Key Stakeholder Contacts','tag'=>'Critical'],
                            ['label'=>'System Access & Credentials','tag'=>'Critical'],
                            ['label'=>'SOPs & Process Documentation','tag'=>'Important'],
                            ['label'=>'Pending Tasks & Deadlines','tag'=>'Important'],
                            ['label'=>'Vendor / Client Relationships','tag'=>'Important'],
                            ['label'=>'Tools, Software & Workflows','tag'=>'Operational'],
                            ['label'=>'File & Folder Structure','tag'=>'Operational'],
                            ['label'=>'Team Introductions Done','tag'=>'Operational'],
                            ['label'=>'Training Sessions Completed','tag'=>'Training'],
                            ['label'=>'Documentation Reviewed Together','tag'=>'Training'],
                            ['label'=>'Q&A Sessions Conducted','tag'=>'Training'],
                        ];
                        $tagColors = ['Critical'=>'#FEE2E2 #B91C1C','Important'=>'#FEF3C7 #92400E','Operational'=>'#EDE9FE #5B21B6','Training'=>'#DBEAFE #1D4ED8'];
                        foreach($ktItems as $item):
                            $tc = explode(' ', $tagColors[$item['tag']]);
                        ?>
                        <div class="kt-item" onclick="toggleKT(this)">
                            <div class="kt-check">✓</div>
                            <span style="font-size:14px;font-weight:600;color:#374151;"><?= $item['label'] ?></span>
                            <span class="kt-tag" style="background:<?= $tc[0] ?>;color:<?= $tc[1] ?>;"><?= $item['tag'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:12px;">
                        <div class="progress-track"><div class="progress-fill" id="ktProgress" style="width:0%;background:linear-gradient(90deg,#7C3AED,#9F7AEA);"></div></div>
                        <div style="font-size:12px;color:#9CA3AF;margin-top:4px;" id="ktCount">0 of <?= count($ktItems) ?> topics covered</div>
                    </div>
                </div>

                <div class="form-section" style="border-left-color:#9CA3AF;">
                    <div class="form-section-title" style="color:#6B7280;">Handover Notes & Attachments</div>
                    <div class="form-group">
                        <label>Detailed Handover Notes</label>
                        <textarea name="notes" class="form-control" rows="4" placeholder="Key context, warnings, tips for the successor…"></textarea>
                    </div>
                    <div class="file-upload-zone" onclick="document.getElementById('kt_file').click()">
                        <div class="upload-icon">📎</div>
                        <div class="upload-text">Attach KT document / handover report</div>
                        <div class="upload-sub">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</div>
                        <input type="file" id="kt_file" name="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png" onchange="showFileName(this,'kt_chosen')">
                    </div>
                    <div class="file-chosen" id="kt_chosen"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#e5e7eb;color:#374151;" onclick="closeModal('ktModal')">Cancel</button>
                    <button type="submit" class="btn btn-violet">💾 Save KT Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL 5 — Sign-off Form
══════════════════════════════════════════════════ -->
<div id="signoffModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" style="background:linear-gradient(135deg,#1565C0,#1E88E5);">
            <span class="modal-header-icon">🖊️</span>
            <h3>Sign-off Form</h3>
            <button class="close-btn" onclick="closeModal('signoffModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="signoffForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="document_type" value="Sign-off">

                <div class="form-section">
                    <div class="form-section-title">Exit Record</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Exit Record</label>
                                <select name="exit_id" class="form-control" required>
                                    <option value="">Select exit record…</option>
                                    <?php foreach ($exits as $exit): ?>
                                    <option value="<?= $exit['exit_id'] ?>">
                                        <?= htmlspecialchars($exit['employee_name']) ?> — <?= htmlspecialchars($exit['exit_type']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Sign-off Form Title</label>
                                <input type="text" name="document_name" class="form-control" required placeholder="e.g., Exit Sign-off — John Doe">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section" style="border-left-color:#1565C0;">
                    <div class="form-section-title" style="color:#1565C0;">Sign-off Workflow Steps</div>
                    <p style="font-size:13px;color:#6B7280;margin-bottom:12px;">Toggle each step as it gets signed off:</p>
                    <div class="signoff-steps" id="signoffSteps">
                        <?php
                        $steps = [
                            ['dept'=>'Employee',         'desc'=>'Employee submits resignation / exit notice'],
                            ['dept'=>'Direct Manager',   'desc'=>'Manager approves exit & sets last day'],
                            ['dept'=>'HR Department',    'desc'=>'HR acknowledges & initiates offboarding'],
                            ['dept'=>'IT Department',    'desc'=>'Revokes system access, collects equipment'],
                            ['dept'=>'Finance',          'desc'=>'Confirms no outstanding dues'],
                            ['dept'=>'Administration',   'desc'=>'Collects keys, ID badge, parking pass'],
                            ['dept'=>'HR (Final)',       'desc'=>'Issues COE & final clearance certificate'],
                        ];
                        foreach($steps as $i => $step): ?>
                        <div class="signoff-step" id="step_<?= $i ?>">
                            <div class="step-num"><?= $i+1 ?></div>
                            <div class="step-info">
                                <div class="step-name"><?= $step['dept'] ?></div>
                                <div class="step-dept"><?= $step['desc'] ?></div>
                            </div>
                            <button type="button" class="step-toggle pending" id="stoggle_<?= $i ?>" onclick="toggleStep(<?= $i ?>)">
                                Pending
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:12px;">
                        <div class="progress-track"><div class="progress-fill" id="signoffProgress" style="width:0%;background:linear-gradient(90deg,#1565C0,#42A5F5);"></div></div>
                        <div style="font-size:12px;color:#9CA3AF;margin-top:4px;" id="signoffCount">0 of <?= count($steps) ?> steps completed</div>
                    </div>
                </div>

                <div class="form-section" style="border-left-color:#9CA3AF;">
                    <div class="form-section-title" style="color:#6B7280">Notes & Attachment</div>
                    <div class="form-group">
                        <label>Sign-off Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Remarks, conditions, exceptions…"></textarea>
                    </div>
                    <div class="file-upload-zone" onclick="document.getElementById('so_file').click()">
                        <div class="upload-icon">📎</div>
                        <div class="upload-text">Attach signed sign-off form</div>
                        <div class="upload-sub">PDF, DOC, JPG, PNG</div>
                        <input type="file" id="so_file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.png" onchange="showFileName(this,'so_chosen')">
                    </div>
                    <div class="file-chosen" id="so_chosen"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#e5e7eb;color:#374151;" onclick="closeModal('signoffModal')">Cancel</button>
                    <button type="submit" class="btn" style="background:linear-gradient(135deg,#1565C0,#1E88E5);color:white;">💾 Save Sign-off</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL 6 — View Document Details
══════════════════════════════════════════════════ -->
<div id="viewDocModal" class="modal">
    <div class="modal-content" style="max-width:580px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#17a2b8,#138496);">
            <span class="modal-header-icon">👁</span>
            <h3>Document Details</h3>
            <button class="close-btn" onclick="closeModal('viewDocModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-section" style="border-left-color:#17a2b8;">
                <div class="form-section-title" style="color:#138496;">Document Information</div>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr style="border-bottom:1px solid #f1f1f1;">
                        <td style="padding:10px 8px;font-weight:600;color:#6B7280;width:40%;">Document ID</td>
                        <td style="padding:10px 8px;color:#1f2937;" id="vd_id"></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f1f1;">
                        <td style="padding:10px 8px;font-weight:600;color:#6B7280;">Employee</td>
                        <td style="padding:10px 8px;color:#1f2937;" id="vd_employee"></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f1f1;">
                        <td style="padding:10px 8px;font-weight:600;color:#6B7280;">Document Type</td>
                        <td style="padding:10px 8px;" id="vd_type"></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f1f1;">
                        <td style="padding:10px 8px;font-weight:600;color:#6B7280;">Document Name</td>
                        <td style="padding:10px 8px;color:#1f2937;" id="vd_name"></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f1f1;">
                        <td style="padding:10px 8px;font-weight:600;color:#6B7280;">Exit Type</td>
                        <td style="padding:10px 8px;color:#1f2937;" id="vd_exit_type"></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f1f1;">
                        <td style="padding:10px 8px;font-weight:600;color:#6B7280;">Exit Date</td>
                        <td style="padding:10px 8px;color:#1f2937;" id="vd_exit_date"></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f1f1;">
                        <td style="padding:10px 8px;font-weight:600;color:#6B7280;">Uploaded Date</td>
                        <td style="padding:10px 8px;color:#1f2937;" id="vd_uploaded"></td>
                    </tr>
                    <tr>
                        <td style="padding:10px 8px;font-weight:600;color:#6B7280;">Notes</td>
                        <td style="padding:10px 8px;color:#1f2937;" id="vd_notes"></td>
                    </tr>
                </table>
            </div>
            <div id="vd_no_file" style="background:#FEF9C3;border-radius:8px;padding:12px 16px;font-size:13px;color:#854D0E;display:flex;align-items:center;gap:8px;">
                ⚠️ No file attachment found for this document.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" style="background:#e5e7eb;color:#374151;" onclick="closeModal('viewDocModal')">Close</button>
            <button class="btn btn-warning" id="vd_edit_btn">
                <i class="fas fa-edit"></i> Edit
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     MODAL 7 — Acknowledgment Preview
══════════════════════════════════════════════════ -->
<div id="previewModal" class="modal">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#0097A7,#00BCD4);">
            <span class="modal-header-icon">👁</span>
            <h3>Acknowledgment Letter Preview</h3>
            <button class="close-btn" onclick="closeModal('previewModal')">×</button>
        </div>
        <div class="modal-body" id="previewContent">
            <!-- generated by JS -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" style="background:#e5e7eb;color:#374151;" onclick="closeModal('previewModal')">Close</button>
            <button type="button" class="btn btn-teal" onclick="window.print()">🖨 Print</button>
        </div>
    </div>
</div>

<script>
/* ──────────────────────────────
   DATA
─────────────────────────────── */
let documentsData = <?= json_encode($documents) ?>;
const totalSteps = 7;
let stepStates = new Array(totalSteps).fill(false);
const totalClearance = 10;
let clearanceCount = 0;
const totalKT = 12;
let ktCount = 0;

/* ──────────────────────────────
   MODAL HELPERS
─────────────────────────────── */
function openModal(mode, documentId = null) {
    const modal = document.getElementById('documentModal');
    const title = document.getElementById('modalTitle');
    const action = document.getElementById('action');
    document.getElementById('documentForm').reset();
    if (mode === 'add') {
        title.textContent = 'Add New Exit Document';
        action.value = 'add';
        document.getElementById('document_id').value = '';
        document.getElementById('existing_document_url').value = '';
        document.getElementById('fileChosen').style.display = 'none';
    } else if (mode === 'edit' && documentId) {
        title.textContent = 'Edit Exit Document';
        action.value = 'update';
        document.getElementById('document_id').value = documentId;
        populateEditForm(documentId);
    }
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function openAcknowledgmentModal() { document.getElementById('acknowledgmentModal').style.display='block'; document.body.style.overflow='hidden'; }
function openClearanceModal()     { document.getElementById('clearanceModal').style.display='block'; document.body.style.overflow='hidden'; }
function openKTModal()            { document.getElementById('ktModal').style.display='block'; document.body.style.overflow='hidden'; }
function openSignOffModal()       { document.getElementById('signoffModal').style.display='block'; document.body.style.overflow='hidden'; }

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = 'auto';
}

window.onclick = e => {
    ['documentModal','acknowledgmentModal','clearanceModal','ktModal','signoffModal','viewDocModal','previewModal'].forEach(id => {
        if (e.target === document.getElementById(id)) closeModal(id);
    });
};

/* ──────────────────────────────
   FILE UPLOAD
─────────────────────────────── */
document.getElementById('document_file').addEventListener('change', function() {
    const el = document.getElementById('fileChosen');
    if (this.files[0]) {
        const fileName = this.files[0].name;
        const fileExt = fileName.split('.').pop().toUpperCase();
        el.textContent = '📎 ' + fileExt + ' File';
        el.style.display = 'block';
    }
});
function showFileName(input, displayId) {
    const el = document.getElementById(displayId);
    if (input.files[0]) {
        const fileName = input.files[0].name;
        const fileExt = fileName.split('.').pop().toUpperCase();
        el.textContent = '📎 ' + fileExt + ' File';
        el.style.display = 'block';
    }
}

/* ──────────────────────────────
   SEARCH & FILTER
─────────────────────────────── */
document.getElementById('searchInput').addEventListener('input', function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#documentsTableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});

document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const type = this.dataset.type;
        document.querySelectorAll('#documentsTableBody tr').forEach(row => {
            row.style.display = (type === 'all' || row.dataset.type === type) ? '' : 'none';
        });
    });
});

/* ──────────────────────────────
   EDIT / DELETE
─────────────────────────────── */
function populateEditForm(id) {
    const doc = documentsData.find(d => d.document_id == id);
    if (!doc) return;
    document.getElementById('exit_id').value = doc.exit_id || '';
    document.getElementById('document_type').value = doc.document_type || '';
    document.getElementById('document_name').value = doc.document_name || '';
    document.getElementById('notes').value = doc.notes || '';
    document.getElementById('existing_document_url').value = doc.document_url || '';
    if (doc.document_url) {
        const fileName = doc.document_url.split('/').pop();
        const fileExt = fileName.split('.').pop().toUpperCase();
        document.getElementById('fileChosen').textContent = '📎 ' + fileExt + ' File';
        document.getElementById('fileChosen').style.display = 'block';
    } else {
        document.getElementById('fileChosen').style.display = 'none';
    }
}
function editDocument(id) { openModal('edit', id); }
function deleteDocument(id) {
    if (confirm("Delete this document? This action cannot be undone.")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="document_id" value="${id}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

/* ──────────────────────────────
   CLEARANCE TOGGLES
─────────────────────────────── */
function toggleClearance(el) {
    el.classList.toggle('selected');
    clearanceCount = document.querySelectorAll('.clearance-item.selected').length;
    const pct = (clearanceCount / totalClearance) * 100;
    document.getElementById('clearanceProgress').style.width = pct + '%';
    document.getElementById('clearanceCount').textContent = clearanceCount + ' of ' + totalClearance + ' departments cleared';
}

/* ──────────────────────────────
   KT TOGGLES
─────────────────────────────── */
function toggleKT(el) {
    el.classList.toggle('selected');
    ktCount = document.querySelectorAll('.kt-item.selected').length;
    const pct = (ktCount / totalKT) * 100;
    document.getElementById('ktProgress').style.width = pct + '%';
    document.getElementById('ktCount').textContent = ktCount + ' of ' + totalKT + ' topics covered';
}

/* ──────────────────────────────
   SIGN-OFF TOGGLES
─────────────────────────────── */
function toggleStep(i) {
    stepStates[i] = !stepStates[i];
    const btn = document.getElementById('stoggle_' + i);
    if (stepStates[i]) {
        btn.textContent = '✓ Approved';
        btn.className = 'step-toggle approved';
    } else {
        btn.textContent = 'Pending';
        btn.className = 'step-toggle pending';
    }
    const done = stepStates.filter(Boolean).length;
    document.getElementById('signoffProgress').style.width = (done / totalSteps * 100) + '%';
    document.getElementById('signoffCount').textContent = done + ' of ' + totalSteps + ' steps completed';
}

/* ──────────────────────────────
   ACKNOWLEDGMENT PREVIEW
─────────────────────────────── */
function previewAcknowledgment() {
    const lastDay = document.getElementById('ack_last_day').value;
    const notice  = document.getElementById('ack_notice').value;
    const items   = ['Resignation / Exit accepted','NDA obligations confirmed','Intellectual property returned','Benefits cessation date noted','Final pay computation acknowledged','Reference letter policy explained'];
    const ids     = ['ack_resignation','ack_nda','ack_ip','ack_benefits','ack_final_pay','ack_reference'];
    const checked = ids.filter(id => document.getElementById(id).checked);

    const html = `
    <div style="font-family:'Georgia',serif;padding:20px;background:white;border:1px solid #e5e7eb;border-radius:8px;">
        <div style="text-align:center;border-bottom:2px solid #00BCD4;padding-bottom:16px;margin-bottom:20px;">
            <div style="font-size:22px;font-weight:700;color:#0097A7;">ACKNOWLEDGMENT LETTER</div>
            <div style="font-size:12px;color:#9CA3AF;margin-top:4px;">Generated on ${new Date().toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})}</div>
        </div>
        <p style="margin-bottom:14px;">This letter serves as formal acknowledgment of the employee's exit from the organization.</p>
        ${lastDay ? `<p><strong>Last Day of Employment:</strong> ${new Date(lastDay).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'})}</p>` : ''}
        ${notice  ? `<p><strong>Notice Period:</strong> ${notice} days</p>` : ''}
        ${checked.length > 0 ? `
        <p style="margin-top:16px;font-weight:600;">The following have been formally acknowledged:</p>
        <ul style="margin-top:8px;">
            ${ids.filter(id=>document.getElementById(id).checked).map((id,i) => `<li style="margin-bottom:6px;">${items[ids.indexOf(id)]}</li>`).join('')}
        </ul>` : ''}
        <div style="margin-top:40px;display:grid;grid-template-columns:1fr 1fr;gap:30px;">
            <div style="border-top:1px solid #374151;padding-top:8px;text-align:center;font-size:13px;color:#6B7280;">Employee Signature & Date</div>
            <div style="border-top:1px solid #374151;padding-top:8px;text-align:center;font-size:13px;color:#6B7280;">HR Representative Signature & Date</div>
        </div>
    </div>`;

    document.getElementById('previewContent').innerHTML = html;
    document.getElementById('previewModal').style.display = 'block';
}

/* ──────────────────────────────
   PRINT CERTIFICATE
─────────────────────────────── */
function printCertificate(employeeId, exitId) {
    const w = window.open(`generate_exit_certificate.php?employee_id=${employeeId}&exit_id=${exitId}`, '', 'width=900,height=700');
    w.onload = () => { w.focus(); w.print(); w.onafterprint = () => w.close(); };
}

/* ──────────────────────────────
   VIEW DOCUMENT DETAILS
─────────────────────────────── */
function viewDocumentDetails(documentId) {
    const doc = documentsData.find(d => d.document_id == documentId);
    if (!doc) return;

    document.getElementById('vd_id').textContent        = '#' + doc.document_id;
    document.getElementById('vd_employee').textContent  = (doc.employee_name || '—') + (doc.employee_number ? ' (' + doc.employee_number + ')' : '');
    document.getElementById('vd_name').textContent      = doc.document_name || '—';
    document.getElementById('vd_exit_type').textContent = doc.exit_type || '—';
    document.getElementById('vd_notes').textContent     = doc.notes || '—';

    const fmtDate = str => str ? new Date(str).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}) : '—';
    document.getElementById('vd_exit_date').textContent = fmtDate(doc.exit_date);
    document.getElementById('vd_uploaded').textContent  = fmtDate(doc.uploaded_date);

    const typeKey = (doc.document_type || '').toLowerCase().replace(/[\s\/]/g, '-');
    document.getElementById('vd_type').innerHTML = '<span class="badge-type type-' + typeKey + '">' + (doc.document_type || '—') + '</span>';

    document.getElementById('vd_no_file').style.display = doc.document_url ? 'none' : 'flex';
    document.getElementById('vd_edit_btn').onclick = () => { closeModal('viewDocModal'); editDocument(documentId); };

    document.getElementById('viewDocModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

/* ──────────────────────────────
   MISC
─────────────────────────────── */
if (document.querySelector('.alert')) window.scrollTo({ top: 0, behavior: 'smooth' });
if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>