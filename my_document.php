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

// Include database connection
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

// Get current user's employee_id from the users table
$stmt = $pdo->prepare("
    SELECT employee_id 
    FROM users 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$employee_id = $user['employee_id'];

// Fetch documents specific to the logged-in employee with enhanced query
$stmt = $pdo->prepare("
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
            WHEN dm.expiry_date IS NOT NULL AND dm.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 'Expiring Later'
            ELSE 'Current'
        END as expiry_status,
        DATEDIFF(dm.expiry_date, CURDATE()) as days_until_expiry,
        CASE 
            WHEN dm.file_path IS NOT NULL AND dm.file_path != '' THEN 
                SUBSTRING_INDEX(dm.file_path, '.', -1)
            ELSE NULL
        END as file_extension
    FROM document_management dm
    LEFT JOIN employee_profiles ep ON dm.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    WHERE dm.employee_id = ?
    ORDER BY dm.created_at DESC
");
$stmt->execute([$employee_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enhanced document types with icons
$documentTypes = [
    'Contract' => '📋',
    'Appointment' => '✉️',
    'Certificate' => '🏆',
    'License' => '🆔',
    'Training' => '🎓',
    'Resume' => '📄',
    'ID Copy' => '🪪',
    'Medical Certificate' => '🏥',
    'Clearance' => '✅',
    'Other' => '📁'
];

// Get file size helper function
function formatFileSize($filePath) {
    if (!file_exists($filePath)) return 'N/A';
    $bytes = filesize($filePath);
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Handle AJAX requests for document actions
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_document_details':
            $doc_id = $_POST['document_id'];
            $stmt = $pdo->prepare("SELECT * FROM document_management WHERE document_id = ? AND employee_id = ?");
            $stmt->execute([$doc_id, $employee_id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($document) {
                $document['file_size'] = $document['file_path'] ? formatFileSize($document['file_path']) : 'N/A';
                echo json_encode(['success' => true, 'document' => $document]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Document not found']);
            }
            exit;
            
        case 'mark_as_viewed':
            $doc_id = $_POST['document_id'];
            $stmt = $pdo->prepare("UPDATE document_management SET last_accessed = NOW() WHERE document_id = ? AND employee_id = ?");
            if ($stmt->execute([$doc_id, $employee_id])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=enhanced">
    <style>
        :root {
            --primary-color: #E91E63;
            --primary-light: #F06292;
            --primary-dark: #C2185B;
            --primary-lighter: #F8BBD0;
            --primary-pale: #FCE4EC;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --border-radius: 8px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--primary-pale) 0%, #fff 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            background: transparent;
            padding: 30px;
            min-height: 100vh;
        }

        .section-title {
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title::before {
            content: '📚';
            font-size: 2.5rem;
        }

        /* Enhanced Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stats-card {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border-left: 4px solid;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stats-card.total { border-left-color: var(--info-color); }
        .stats-card.active { border-left-color: var(--success-color); }
        .stats-card.expiring { border-left-color: var(--warning-color); }
        .stats-card.expired { border-left-color: var(--danger-color); }

        .stats-card .number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stats-card .label {
            font-size: 1rem;
            color: #666;
            font-weight: 500;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1));
            transform: rotate(45deg);
            transition: all 0.3s ease;
        }

        .stats-card:hover::before {
            top: -100%;
            right: -100%;
        }

        /* Enhanced Controls */
        .controls {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .search-container {
            position: relative;
            flex: 1;
            min-width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 18px;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            background: white;
            font-size: 14px;
            min-width: 150px;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        .view-toggle {
            display: flex;
            gap: 5px;
        }

        .view-btn {
            padding: 10px 15px;
            border: 2px solid var(--primary-color);
            background: transparent;
            color: var(--primary-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-btn.active,
        .view-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Enhanced Table */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            font-size: 14px;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 20px 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: var(--primary-pale);
            transform: scale(1.01);
        }

        .table td {
            padding: 18px 15px;
            vertical-align: middle;
            border-color: #f1f3f4;
        }

        /* Enhanced Badges */
        .document-type-badge,
        .status-badge,
        .expiry-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .document-type-badge {
            background: var(--primary-lighter);
            color: var(--primary-dark);
        }

        .status-badge.status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .expiry-badge.expiry-current {
            background: #d4edda;
            color: #155724;
        }

        .expiry-badge.expiry-expiringsoon {
            background: #fff3cd;
            color: #856404;
            animation: pulse 2s infinite;
        }

        .expiry-badge.expiry-expired {
            background: #f8d7da;
            color: #721c24;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        /* Enhanced Buttons */
        .btn-enhanced {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin: 2px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view {
            background: var(--info-color);
            color: white;
        }

        .btn-download {
            background: var(--success-color);
            color: white;
        }

        .btn-enhanced:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Card View */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .document-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .document-card-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-lighter), white);
            border-bottom: 1px solid #e9ecef;
        }

        .document-card-title {
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .document-card-body {
            padding: 20px;
        }

        .document-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 13px;
        }

        .info-value {
            font-weight: 500;
            color: #333;
        }

        /* Enhanced Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-weight: 700;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close:hover {
            transform: scale(1.1);
        }

        .modal-body {
            padding: 30px;
            max-height: 60vh;
            overflow-y: auto;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-results h3 {
            color: var(--primary-dark);
            margin-bottom: 10px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }

            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-container {
                min-width: auto;
            }

            .filter-group {
                justify-content: stretch;
            }

            .filter-select {
                flex: 1;
                min-width: auto;
            }

            .table-container {
                overflow-x: auto;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .file-icon {
            font-size: 1.2em;
            margin-right: 8px;
        }

        .document-name-link {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .document-name-link:hover {
            color: var(--primary-color);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'employee_navigation.php'; ?>
        <div class="row">
            <?php include 'employee_sidebar.php'; ?>
            <div class="main-content col-md-10">
                <h2 class="section-title">My Documents</h2>
                
                <!-- Enhanced Document Statistics -->
                <div class="stats-cards">
                    <?php
                    $totalDocs = count($documents);
                    $expiring = count(array_filter($documents, function($doc) { 
                        return $doc['expiry_status'] === 'Expiring Soon'; 
                    }));
                    $expired = count(array_filter($documents, function($doc) { 
                        return $doc['expiry_status'] === 'Expired'; 
                    }));
                    $active = count(array_filter($documents, function($doc) { 
                        return $doc['document_status'] === 'Active'; 
                    }));
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

                <!-- Enhanced Search and Filter Controls -->
                <div class="controls">
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search documents by name, type, or description...">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                    
                    <div class="filter-group">
                        <select id="typeFilter" class="filter-select">
                            <option value="">All Types</option>
                            <?php foreach ($documentTypes as $type => $icon): ?>
                                <option value="<?= $type ?>"><?= $icon ?> <?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select id="statusFilter" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Active">✅ Active</option>
                            <option value="Inactive">❌ Inactive</option>
                            <option value="Expired">⚠️ Expired</option>
                        </select>

                        <select id="expiryFilter" class="filter-select">
                            <option value="">All Expiry Status</option>
                            <option value="Current">✅ Current</option>
                            <option value="Expiring Soon">⚠️ Expiring Soon</option>
                            <option value="Expired">❌ Expired</option>
                        </select>
                    </div>

                    <div class="view-toggle">
                        <button class="view-btn active" id="tableViewBtn" onclick="switchView('table')">
                            <i class="fas fa-list"></i> Table
                        </button>
                        <button class="view-btn" id="cardViewBtn" onclick="switchView('card')">
                            <i class="fas fa-th-large"></i> Cards
                        </button>
                    </div>
                </div>

                <!-- Table View -->
                <div class="table-container" id="tableView">
                    <table class="table" id="documentsTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-file-alt"></i> Document Name</th>
                                <th><i class="fas fa-tag"></i> Type</th>
                                <th><i class="fas fa-circle"></i> Status</th>
                                <th><i class="fas fa-calendar"></i> Expiry Date</th>
                                <th><i class="fas fa-clock"></i> Expiry Status</th>
                                <th><i class="fas fa-calendar-plus"></i> Created</th>
                                <th><i class="fas fa-file-download"></i> Size</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                            <tr data-document='<?= json_encode($document) ?>'>
                                <td>
                                    <?php 
                                    $fileIcon = '';
                                    if ($document['file_extension']) {
                                        switch(strtolower($document['file_extension'])) {
                                            case 'pdf': $fileIcon = '<i class="fas fa-file-pdf text-danger"></i>'; break;
                                            case 'doc':
                                            case 'docx': $fileIcon = '<i class="fas fa-file-word text-primary"></i>'; break;
                                            case 'xls':
                                            case 'xlsx': $fileIcon = '<i class="fas fa-file-excel text-success"></i>'; break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png': $fileIcon = '<i class="fas fa-file-image text-info"></i>'; break;
                                            default: $fileIcon = '<i class="fas fa-file text-secondary"></i>';
                                        }
                                    }
                                    ?>
                                    <?php if ($document['file_path'] && file_exists($document['file_path'])): ?>
                                        <a href="<?= htmlspecialchars($document['file_path']) ?>" 
                                           class="document-name-link" target="_blank">
                                            <?= $fileIcon ?>
                                            <span><?= htmlspecialchars($document['document_name']) ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="document-name-link">
                                            <?= $fileIcon ?>
                                            <span><?= htmlspecialchars($document['document_name']) ?></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="document-type-badge">
                                        <?= $documentTypes[$document['document_type']] ?? '📁' ?>
                                        <?= htmlspecialchars($document['document_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($document['document_status']) ?>">
                                        <?= $document['document_status'] === 'Active' ? '✅' : '❌' ?>
                                        <?= htmlspecialchars($document['document_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($document['expiry_date']): ?>
                                        <span title="<?= date('F j, Y', strtotime($document['expiry_date'])) ?>">
                                            <?= date('M d, Y', strtotime($document['expiry_date'])) ?>
                                        </span>
                                        <?php if ($document['days_until_expiry'] > 0): ?>
                                            <small class="text-muted d-block">(<?= $document['days_until_expiry'] ?> days)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No Expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="expiry-badge expiry-<?= strtolower(str_replace(' ', '', $document['expiry_status'])) ?>">
                                        <?php
                                        switch($document['expiry_status']) {
                                            case 'Current': echo '✅'; break;
                                            case 'Expiring Soon': echo '⚠️'; break;
                                            case 'Expired': echo '❌'; break;
                                            default: echo '📅';
                                        }
                                        ?>
                                        <?= $document['expiry_status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span title="<?= date('F j, Y g:i A', strtotime($document['created_at'])) ?>">
                                        <?= date('M d, Y', strtotime($document['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted">
                                        <?= $document['file_path'] ? formatFileSize($document['file_path']) : 'N/A' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-enhanced btn-view" 
                                            onclick="viewDocumentDetails(<?= $document['document_id'] ?>)" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($document['file_path'] && file_exists($document['file_path'])): ?>
                                        <a href="<?= htmlspecialchars($document['file_path']) ?>" 
                                           class="btn btn-success btn-small" 
                                           download title="Download">
                                            📥 Download
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($documents)): ?>
                        <div class="no-results">
                            <i>📄</i>
                            <h3>No documents found</h3>
                            <p>You don't have any documents yet. Contact HR if you need assistance.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            // Initialize documents data
            const documentsData = <?= json_encode($documents) ?>;

            // Search and filter functionality
            function filterDocuments() {
                const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                const typeFilter = document.getElementById('typeFilter').value;
                const statusFilter = document.getElementById('statusFilter').value;
                
                const rows = document.querySelectorAll('#documentsTable tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const type = row.querySelector('.document-type-badge').textContent;
                    const status = row.querySelector('.status-badge').textContent;
                    
                    const matchesSearch = !searchTerm || text.includes(searchTerm);
                    const matchesType = !typeFilter || type.includes(typeFilter);
                    const matchesStatus = !statusFilter || status.includes(statusFilter);
                    
                    row.style.display = matchesSearch && matchesType && matchesStatus ? '' : 'none';
                });
            }

            // View document details
            function viewDocument(documentId) {
                const document = documentsData.find(doc => doc.document_id == documentId);
                if (document) {
                    const modal = document.getElementById('viewModal');
                    const modalBody = document.getElementById('viewModalBody');
                    
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h4>${document.document_name}</h4>
                            <div class="detail-row">
                                <div class="detail-label">Type:</div>
                                <div class="detail-value">
                                    <span class="document-type-badge type-${document.document_type.toLowerCase()}">
                                        ${document.document_type}
                                    </span>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status:</div>
                                <div class="detail-value">
                                    <span class="status-badge status-${document.document_status.toLowerCase()}">
                                        ${document.document_status}
                                    </span>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Expiry Date:</div>
                                <div class="detail-value">
                                    ${document.expiry_date ? new Date(document.expiry_date).toLocaleDateString() : 'No Expiry'}
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Created:</div>
                                <div class="detail-value">
                                    ${new Date(document.created_at).toLocaleDateString()}
                                </div>
                            </div>
                            ${document.notes ? `
                                <div class="detail-row">
                                    <div class="detail-label">Notes:</div>
                                    <div class="detail-value">${document.notes}</div>
                                </div>
                            ` : ''}
                            ${document.file_path ? `
                                <div style="margin-top: 20px; text-align: center;">
                                    <a href="${document.file_path}" class="btn btn-success" download>
                                        📥 Download Document
                                    </a>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    
                    modal.style.display = 'block';
                }
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                if (event.target == document.getElementById('viewModal')) {
                    closeViewModal();
                }
            }

            function closeViewModal() {
                document.getElementById('viewModal').style.display = 'none';
            }

            // Add event listeners
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('searchInput').addEventListener('input', filterDocuments);
                document.getElementById('typeFilter').addEventListener('change', filterDocuments);
                document.getElementById('statusFilter').addEventListener('change', filterDocuments);
            });
        </script>

        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    </div>
</body>
</html>