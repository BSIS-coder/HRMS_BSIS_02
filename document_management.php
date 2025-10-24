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
$host = 'localhost';
$dbname = 'hr_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create uploads directory if it doesn't exist
$uploadsDir = 'uploads/documents/';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Handle file upload for new document
                $filePath = '';
                if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handleFileUpload($_FILES['document_file']);
                    if ($uploadResult['success']) {
                        $filePath = $uploadResult['path'];
                    } else {
                        $message = "Error uploading file: " . $uploadResult['error'];
                        $messageType = "error";
                        break;
                    }
                } else {
                    // Use manual file path if no file uploaded
                    $filePath = $_POST['file_path'] ?? '';
                }

                // Add new document
                try {
                    $stmt = $pdo->prepare("INSERT INTO document_management (employee_id, document_type, document_name, file_path, expiry_date, document_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['document_type'],
                        $_POST['document_name'],
                        $filePath,
                        $_POST['expiry_date'] ?: null,
                        $_POST['document_status'],
                        $_POST['notes']
                    ]);
                    $message = "Document added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding document: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Handle file upload for document update
                $filePath = $_POST['current_file_path']; // Keep current file path by default
                if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handleFileUpload($_FILES['document_file']);
                    if ($uploadResult['success']) {
                        // Delete old file if it exists and is in uploads directory
                        if ($filePath && strpos($filePath, 'uploads/') === 0 && file_exists($filePath)) {
                            unlink($filePath);
                        }
                        $filePath = $uploadResult['path'];
                    } else {
                        $message = "Error uploading file: " . $uploadResult['error'];
                        $messageType = "error";
                        break;
                    }
                } elseif (isset($_POST['file_path']) && $_POST['file_path'] !== $filePath) {
                    // Use manual file path if provided and different from current
                    $filePath = $_POST['file_path'];
                }

                // Update document
                try {
                    $stmt = $pdo->prepare("UPDATE document_management SET employee_id=?, document_type=?, document_name=?, file_path=?, expiry_date=?, document_status=?, notes=? WHERE document_id=?");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['document_type'],
                        $_POST['document_name'],
                        $filePath,
                        $_POST['expiry_date'] ?: null,
                        $_POST['document_status'],
                        $_POST['notes'],
                        $_POST['document_id']
                    ]);
                    $message = "Document updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating document: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete document record only (do not delete physical file)
                try {
                    // Get file path before deleting record
                    $stmt = $pdo->prepare("SELECT file_path FROM document_management WHERE document_id=?");
                    $stmt->execute([$_POST['document_id']]);
                    $document = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Delete the database record
                    $stmt = $pdo->prepare("DELETE FROM document_management WHERE document_id=?");
                    $stmt->execute([$_POST['document_id']]);
                    
                    // Note: Per requirement, do not delete the physical file from the server
                    
                    $message = "Document deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting document: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Function to handle file upload
function handleFileUpload($file) {
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'text/plain'
    ];
    
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    // Check file size
    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'error' => 'File size exceeds 10MB limit'];
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }
    
    // Check file extension
    $pathInfo = pathinfo($file['name']);
    $extension = strtolower($pathInfo['extension']);
    
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'error' => 'File extension not allowed'];
    }
    
    // Generate unique filename
    $originalName = $pathInfo['filename'];
    $timestamp = date('Y-m-d_H-i-s');
    $uniqueId = uniqid();
    $newFileName = $originalName . '_' . $timestamp . '_' . $uniqueId . '.' . $extension;
    
    // Create directory structure
    $uploadsDir = 'uploads/documents/';
    $yearMonth = date('Y/m');
    $fullUploadDir = $uploadsDir . $yearMonth . '/';
    
    if (!file_exists($fullUploadDir)) {
        mkdir($fullUploadDir, 0755, true);
    }
    
    $targetPath = $fullUploadDir . $newFileName;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath, 'original_name' => $file['name']];
    } else {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
}

// Fetch documents with employee information
$stmt = $pdo->query("
    SELECT 
        dm.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        pi.first_name,
        pi.last_name,
        ep.employee_number,
        jr.title as job_title,
        jr.department,
        CASE 
            WHEN dm.expiry_date IS NOT NULL AND dm.expiry_date < CURDATE() THEN 'Expired'
            WHEN dm.expiry_date IS NOT NULL AND dm.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
            ELSE 'Current'
        END as expiry_status
    FROM document_management dm
    LEFT JOIN employee_profiles ep ON dm.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY dm.created_at DESC
");
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        CONCAT(pi.first_name, ' ', pi.last_name, ' (', ep.employee_number, ')') as display_name
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Document types
$documentTypes = [
    'Contract', 'Appointment', 'Certificate', 'License', 'Training', 
    'Resume', 'ID Copy', 'Medical Certificate', 'Clearance', 'Other'
];

// Document statuses
$documentStatuses = ['Active', 'Inactive', 'Expired', 'Pending'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for document management page */
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .container-fluid {
            padding: 0;
        }
        
        .row {
            margin-right: 0;
            margin-left: 0;
        }

        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .controls-left {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .filter-select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            background: white;
            min-width: 150px;
        }

        .filter-select:focus {
            border-color: var(--azure-blue);
            outline: none;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
            margin: 0 3px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stats-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stats-card .label {
            color: #666;
            font-size: 1.1rem;
        }

        .stats-card.total .number { color: var(--azure-blue); }
        .stats-card.expiring .number { color: #ffc107; }
        .stats-card.expired .number { color: #dc3545; }
        .stats-card.active .number { color: #28a745; }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--azure-blue-lighter);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .document-type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .type-contract { background: #e3f2fd; color: #1565c0; }
        .type-certificate { background: #f3e5f5; color: #7b1fa2; }
        .type-license { background: #e8f5e8; color: #2e7d32; }
        .type-training { background: #fff3e0; color: #ef6c00; }
        .type-appointment { background: #fce4ec; color: #c2185b; }
        .type-resume { background: #f1f8e9; color: #558b2f; }
        .type-other { background: #f5f5f5; color: #424242; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }

        .expiry-badge {
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .expiry-current { background: #d4edda; color: #155724; }
        .expiry-expiring { background: #fff3cd; color: #856404; }
        .expiry-expired { background: #f8d7da; color: #721c24; }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 95vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
        }

        .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .form-control {
            width: 100%;
            padding: 6px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-results {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        /* File Upload Styles */
        .file-upload-section {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .file-upload-section.dragover {
            border-color: var(--azure-blue);
            background: var(--azure-blue-pale);
        }

        .file-upload-input {
            display: none;
        }

        .file-upload-label {
            display: inline-block;
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 10px 0;
        }

        .file-upload-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        }

        .file-info {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            display: none;
        }

        .file-info.show {
            display: block;
        }

        .file-info .file-name {
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .file-info .file-size {
            color: #666;
            font-size: 14px;
        }

        .manual-path-toggle {
            margin-top: 15px;
        }

        .manual-path-toggle a {
            color: var(--azure-blue);
            text-decoration: none;
            font-size: 14px;
        }

        .manual-path-toggle a:hover {
            text-decoration: underline;
        }

        .manual-path-section {
            display: none;
            margin-top: 15px;
        }

        .manual-path-section.show {
            display: block;
        }

        .supported-formats {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }

        .download-link {
            color: var(--azure-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .download-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .controls-left {
                flex-direction: column;
            }

            .search-box {
                max-width: none;
            }

            .form-row {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Document Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="stats-cards">
                        <?php
                        $totalDocs = count($documents);
                        $expiring = count(array_filter($documents, function($doc) { return $doc['expiry_status'] === 'Expiring Soon'; }));
                        $expired = count(array_filter($documents, function($doc) { return $doc['expiry_status'] === 'Expired'; }));
                        $active = count(array_filter($documents, function($doc) { return $doc['document_status'] === 'Active'; }));
                        ?>
                        <div class="stats-card total">
                            <div class="number"><?= $totalDocs ?></div>
                            <div class="label">Total Documents</div>
                        </div>
                        <div class="stats-card active">
                            <div class="number"><?= $active ?></div>
                            <div class="label">Active Documents</div>
                        </div>
                        <div class="stats-card expiring">
                            <div class="number"><?= $expiring ?></div>
                            <div class="label">Expiring Soon</div>
                        </div>
                        <div class="stats-card expired">
                            <div class="number"><?= $expired ?></div>
                            <div class="label">Expired Documents</div>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div class="controls">
                        <div class="controls-left">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="searchInput" placeholder="Search documents by name, employee, or type...">
                            </div>
                            <select id="typeFilter" class="filter-select">
                                <option value="">All Types</option>
                                <?php foreach ($documentTypes as $type): ?>
                                    <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="statusFilter" class="filter-select">
                                <option value="">All Status</option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Expired">Expired</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            📄 Add New Document
                        </button>
                    </div>

                    <!-- Documents Table -->
                    <div class="table-container">
                        <table class="table" id="documentsTable">
                            <thead>
                                <tr>
                                    <th>Document Name</th>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Expiry Date</th>
                                    <th>Expiry Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="documentsTableBody">
                                <?php foreach ($documents as $document): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <?php if ($document['file_path'] && file_exists($document['file_path'])): ?>
                                                <a href="<?= htmlspecialchars($document['file_path']) ?>" class="download-link" target="_blank">
                                                    <strong><?= htmlspecialchars($document['document_name']) ?></strong>
                                                </a>
                                            <?php else: ?>
                                                <strong><?= htmlspecialchars($document['document_name']) ?></strong>
                                            <?php endif; ?>
                                            <?php if ($document['notes']): ?>
                                                <br><small style="color: #666;"><?= htmlspecialchars(substr($document['notes'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($document['employee_name'] ?? 'N/A') ?></strong><br>
                                            <small style="color: #666;">
                                                <?= htmlspecialchars($document['employee_number'] ?? '') ?> | 
                                                <?= htmlspecialchars($document['job_title'] ?? '') ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="document-type-badge type-<?= strtolower($document['document_type']) ?>">
                                            <?= htmlspecialchars($document['document_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($document['document_status']) ?>">
                                            <?= htmlspecialchars($document['document_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $document['expiry_date'] ? date('M d, Y', strtotime($document['expiry_date'])) : '<em>No Expiry</em>' ?>
                                    </td>
                                    <td>
                                        <span class="expiry-badge expiry-<?= strtolower(str_replace(' ', '', $document['expiry_status'])) ?>">
                                            <?= $document['expiry_status'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($document['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-info btn-small" onclick="viewDocument(<?= $document['document_id'] ?>)" title="View Details">
                                            👁️ View
                                        </button>
                                        <button class="btn btn-warning btn-small" onclick="editDocument(<?= $document['document_id'] ?>)" title="Edit">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteDocument(<?= $document['document_id'] ?>)" title="Delete">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($documents)): ?>
                        <div class="no-results">
                            <i>📄</i>
                            <h3>No documents found</h3>
                            <p>Start by adding your first document.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Document Modal -->
    <div id="documentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Document</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="documentForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="document_id" name="document_id">
                    <input type="hidden" id="current_file_path" name="current_file_path">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select employee...</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>"><?= htmlspecialchars($employee['display_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="document_type">Document Type</label>
                                <select id="document_type" name="document_type" class="form-control" required>
                                    <option value="">Select type...</option>
                                    <?php foreach ($documentTypes as $type): ?>
                                    <option value="<?= $type ?>"><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="document_name">Document Name</label>
                        <input type="text" id="document_name" name="document_name" class="form-control" required 
                               placeholder="e.g., Employment Contract - John Doe">
                    </div>

                    <!-- File Upload Section -->
                    <div class="form-group">
                        <label>Document File</label>
                        <div class="file-upload-section" id="fileUploadSection">
                            <div class="upload-icon">📁</div>
                            <p><strong>Drag and drop your file here</strong></p>
                            <p>or</p>
                            <label for="document_file" class="file-upload-label">
                                📂 Choose File from Computer
                            </label>
                            <input type="file" id="document_file" name="document_file" class="file-upload-input" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.txt">
                            
                            <div class="file-info" id="fileInfo">
                                <div class="file-name" id="fileName"></div>
                                <div class="file-size" id="fileSize"></div>
                                <button type="button" class="btn btn-danger btn-small" id="removeFile" style="margin-top: 10px;">Remove File</button>
                            </div>
                            
                            <div class="supported-formats">
                                Supported formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, TXT (Max: 10MB)
                            </div>
                            
                            <div class="manual-path-toggle">
                                <a href="#" id="toggleManualPath">Or enter file path manually</a>
                            </div>
                        </div>
                        
                        <div class="manual-path-section" id="manualPathSection">
                            <label for="file_path">Manual File Path</label>
                            <input type="text" id="file_path" name="file_path" class="form-control" 
                                   placeholder="/documents/contracts/john_doe_contract.pdf">
                            <small class="text-muted">Use this option if the file is already on the server or external location</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date (Optional)</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="document_status">Status</label>
                                <select id="document_status" name="document_status" class="form-control" required>
                                    <?php foreach ($documentStatuses as $status): ?>
                                    <option value="<?= $status ?>" <?= $status === 'Active' ? 'selected' : '' ?>><?= $status ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="1" 
                                  placeholder="Additional notes about this document..."></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Document Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Document Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let documentsData = <?= json_encode($documents) ?>;

        // File upload functionality
        const fileUploadSection = document.getElementById('fileUploadSection');
        const fileInput = document.getElementById('document_file');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeFileBtn = document.getElementById('removeFile');
        const toggleManualPath = document.getElementById('toggleManualPath');
        const manualPathSection = document.getElementById('manualPathSection');

        // Drag and drop functionality
        fileUploadSection.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadSection.classList.add('dragover');
        });

        fileUploadSection.addEventListener('dragleave', (e) => {
            e.preventDefault();
            fileUploadSection.classList.remove('dragover');
        });

        fileUploadSection.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadSection.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        // File input change event
        fileInput.addEventListener('change', handleFileSelect);

        // Remove file button
        removeFileBtn.addEventListener('click', () => {
            fileInput.value = '';
            fileInfo.classList.remove('show');
            document.getElementById('file_path').value = '';
        });

        // Toggle manual path
        toggleManualPath.addEventListener('click', (e) => {
            e.preventDefault();
            const isVisible = manualPathSection.classList.contains('show');
            
            if (isVisible) {
                manualPathSection.classList.remove('show');
                toggleManualPath.textContent = 'Or enter file path manually';
            } else {
                manualPathSection.classList.add('show');
                toggleManualPath.textContent = 'Hide manual path input';
            }
        });

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                // Validate file size (10MB)
                if (file.size > 10 * 1024 * 1024) {
                    alert('File size exceeds 10MB limit. Please choose a smaller file.');
                    fileInput.value = '';
                    return;
                }

                // Validate file type
                const allowedTypes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'text/plain'
                ];

                if (!allowedTypes.includes(file.type)) {
                    alert('File type not allowed. Please choose a PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF, or TXT file.');
                    fileInput.value = '';
                    return;
                }

                // Show file info
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.classList.add('show');

                // Clear manual path if file is selected
                document.getElementById('file_path').value = '';
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Search and filter functionality
        document.getElementById('searchInput').addEventListener('input', filterDocuments);
        document.getElementById('typeFilter').addEventListener('change', filterDocuments);
        document.getElementById('statusFilter').addEventListener('change', filterDocuments);

        function filterDocuments() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            const tableBody = document.getElementById('documentsTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                const typeCell = row.cells[2].textContent.trim();
                const statusCell = row.cells[3].textContent.trim();
                
                let showRow = true;
                
                // Search filter
                if (searchTerm && !text.includes(searchTerm)) {
                    showRow = false;
                }
                
                // Type filter
                if (typeFilter && typeCell !== typeFilter) {
                    showRow = false;
                }
                
                // Status filter
                if (statusFilter && statusCell !== statusFilter) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            }
        }

        // Modal functions
        function openModal(mode, documentId = null) {
            const modal = document.getElementById('documentModal');
            const form = document.getElementById('documentForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Document';
                action.value = 'add';
                form.reset();
                document.getElementById('document_id').value = '';
                document.getElementById('current_file_path').value = '';
                fileInfo.classList.remove('show');
                manualPathSection.classList.remove('show');
                toggleManualPath.textContent = 'Or enter file path manually';
            } else if (mode === 'edit' && documentId) {
                title.textContent = 'Edit Document';
                action.value = 'update';
                document.getElementById('document_id').value = documentId;
                populateEditForm(documentId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('documentModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(documentId) {
            const selectedDocument = documentsData.find(doc => doc.document_id == documentId);
            if (selectedDocument) {
                document.getElementById('employee_id').value = selectedDocument.employee_id || '';
                document.getElementById('document_type').value = selectedDocument.document_type || '';
                document.getElementById('document_name').value = selectedDocument.document_name || '';
                document.getElementById('file_path').value = selectedDocument.file_path || '';
                document.getElementById('current_file_path').value = selectedDocument.file_path || '';
                document.getElementById('expiry_date').value = selectedDocument.expiry_date || '';
                document.getElementById('document_status').value = selectedDocument.document_status || '';
                document.getElementById('notes').value = selectedDocument.notes || '';
                
                // Show current file info if exists
                if (selectedDocument.file_path) {
                    const pathParts = selectedDocument.file_path.split('/');
                    const currentFileName = pathParts[pathParts.length - 1];
                    fileName.textContent = currentFileName + ' (current file)';
                    fileSize.textContent = 'Existing file';
                    fileInfo.classList.add('show');
                }
            }
        }

        function viewDocument(documentId) {
            const selectedDocument = documentsData.find(doc => doc.document_id == documentId);
            if (selectedDocument) {
                const modal = document.getElementById('viewModal');
                const modalBody = document.getElementById('viewModalBody');
                
                modalBody.innerHTML = `
                    <div style="display: grid; gap: 20px;">
                        <div class="form-row">
                            <div class="form-col">
                                <strong>Document Name:</strong><br>
                                ${selectedDocument.document_name || 'N/A'}
                            </div>
                            <div class="form-col">
                                <strong>Type:</strong><br>
                                <span class="document-type-badge type-${selectedDocument.document_type.toLowerCase()}">${selectedDocument.document_type}</span>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <strong>Employee:</strong><br>
                                ${selectedDocument.employee_name || 'N/A'}<br>
                                <small style="color: #666;">${selectedDocument.employee_number || ''} | ${selectedDocument.job_title || ''}</small>
                            </div>
                            <div class="form-col">
                                <strong>Status:</strong><br>
                                <span class="status-badge status-${selectedDocument.document_status.toLowerCase()}">${selectedDocument.document_status}</span>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <strong>File:</strong><br>
                                ${selectedDocument.file_path ? 
                                    `<a href="${selectedDocument.file_path}" class="download-link" target="_blank">📄 ${selectedDocument.file_path.split('/').pop()}</a>` : 
                                    'No file attached'
                                }
                            </div>
                            <div class="form-col">
                                <strong>Expiry Date:</strong><br>
                                ${selectedDocument.expiry_date ? new Date(selectedDocument.expiry_date).toLocaleDateString() : 'No Expiry'}<br>
                                <span class="expiry-badge expiry-${selectedDocument.expiry_status.toLowerCase().replace(' ', '')}">${selectedDocument.expiry_status}</span>
                            </div>
                        </div>
                        
                        <div>
                            <strong>Notes:</strong><br>
                            ${selectedDocument.notes || 'No notes available'}
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <strong>Created:</strong><br>
                                ${new Date(selectedDocument.created_at).toLocaleDateString()}
                            </div>
                            <div class="form-col">
                                <strong>Last Updated:</strong><br>
                                ${selectedDocument.updated_at ? new Date(selectedDocument.updated_at).toLocaleDateString() : 'Never'}
                            </div>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        ${selectedDocument.file_path && selectedDocument.file_path.indexOf('uploads/') === 0 ? 
                            `<a href="${selectedDocument.file_path}" class="btn btn-info" target="_blank" download>📥 Download File</a>` : ''
                        }
                        <button class="btn btn-warning" onclick="closeViewModal(); editDocument(${documentId})">
                            ✏️ Edit Document
                        </button>
                        <button class="btn" style="background: #6c757d; color: white; margin-left: 10px;" onclick="closeViewModal()">
                            Close
                        </button>
                    </div>
                `;
                
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function editDocument(documentId) {
            openModal('edit', documentId);
        }

        function deleteDocument(documentId) {
            const selectedDocument = documentsData.find(doc => doc.document_id == documentId);
            const fileName = selectedDocument && selectedDocument.file_path ? selectedDocument.file_path.split('/').pop() : 'this document';
            
            if (confirm(`Are you sure you want to delete "${fileName}"? This action cannot be undone and will also delete the physical file if it exists.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="document_id" value="${documentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const documentModal = document.getElementById('documentModal');
            const viewModal = document.getElementById('viewModal');
            
            if (event.target === documentModal) {
                closeModal();
            }
            
            if (event.target === viewModal) {
                closeViewModal();
            }
        }

        // Form validation
        document.getElementById('documentForm').addEventListener('submit', function(e) {
            const documentName = document.getElementById('document_name').value.trim();
            const fileInput = document.getElementById('document_file');
            const filePath = document.getElementById('file_path').value.trim();
            const action = document.getElementById('action').value;
            
            if (!documentName) {
                e.preventDefault();
                alert('Document name is required');
                return;
            }
            
            // For new documents, require either file upload or manual path
            if (action === 'add') {
                if (!fileInput.files.length && !filePath) {
                    e.preventDefault();
                    alert('Please either upload a file or provide a file path');
                    return;
                }
            }
            
            // Validate manual file path format if provided
            if (filePath && !filePath.startsWith('/') && !filePath.startsWith('http')) {
                e.preventDefault();
                alert('Please enter a valid file path (e.g., /documents/contracts/file.pdf or http://example.com/file.pdf)');
                return;
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#documentsTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Add hover effects to stats cards
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach(card => {
                card.addEventListener('click', function() {
                    const cardType = this.classList.contains('expiring') ? 'expiring' : 
                                   this.classList.contains('expired') ? 'expired' :
                                   this.classList.contains('active') ? 'active' : '';
                    
                    if (cardType === 'expiring') {
                        document.getElementById('searchInput').value = '';
                        document.getElementById('typeFilter').value = '';
                        document.getElementById('statusFilter').value = '';
                        filterExpiringDocuments('Expiring Soon');
                    } else if (cardType === 'expired') {
                        document.getElementById('searchInput').value = '';
                        document.getElementById('typeFilter').value = '';
                        document.getElementById('statusFilter').value = '';
                        filterExpiringDocuments('Expired');
                    } else if (cardType === 'active') {
                        document.getElementById('searchInput').value = '';
                        document.getElementById('typeFilter').value = '';
                        document.getElementById('statusFilter').value = 'Active';
                        filterDocuments();
                    }
                });
            });

            // Check for expiring documents and show notifications
            const expiringDocs = documentsData.filter(doc => doc.expiry_status === 'Expiring Soon');
            const expiredDocs = documentsData.filter(doc => doc.expiry_status === 'Expired');
            
            if (expiringDocs.length > 0 || expiredDocs.length > 0) {
                setTimeout(() => {
                    let message = '';
                    if (expiredDocs.length > 0) {
                        message += `⚠️ You have ${expiredDocs.length} expired document(s). `;
                    }
                    if (expiringDocs.length > 0) {
                        message += `📅 You have ${expiringDocs.length} document(s) expiring soon.`;
                    }
                    
                    if (message && confirm(message + '\n\nWould you like to view these documents?')) {
                        if (expiredDocs.length > 0) {
                            filterExpiringDocuments('Expired');
                        } else {
                            filterExpiringDocuments('Expiring Soon');
                        }
                    }
                }, 1000);
            }
        });

        function filterExpiringDocuments(status) {
            const tableBody = document.getElementById('documentsTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const expiryStatusCell = row.cells[5].textContent.trim();
                
                if (expiryStatusCell === status) {
                    row.style.display = '';
                    row.style.backgroundColor = status === 'Expired' ? '#f8d7da' : '#fff3cd';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + N = New Document
            if (e.altKey && e.key === 'n') {
                e.preventDefault();
                openModal('add');
            }
            
            // Escape = Close modals
            if (e.key === 'Escape') {
                closeModal();
                closeViewModal();
            }
            
            // Alt + S = Focus search
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });
    </script>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>