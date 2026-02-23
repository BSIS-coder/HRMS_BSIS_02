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
                // Add new exit document
                try {
                    // Handle file upload
                    $documentUrl = '';
                    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/exit_documents/';
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        $fileName = time() . '_' . basename($_FILES['document_file']['name']);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $targetPath)) {
                            $documentUrl = $targetPath;
                        }
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO exit_documents (exit_id, employee_id, document_type, document_name, document_url, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['document_type'],
                        $_POST['document_name'],
                        $documentUrl,
                        $_POST['notes']
                    ]);
                    $_SESSION['message'] = "Exit document added successfully!";
                    $_SESSION['message_type'] = "success";
                    header("Location: exit_documents.php");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error adding document: " . $e->getMessage();
                    $_SESSION['message_type'] = "error";
                    header("Location: exit_documents.php");
                    exit;
                }
                break;
            
            case 'update':
                // Update exit document
                try {
                    $documentUrl = $_POST['existing_document_url'];
                    
                    // Handle new file upload
                    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/exit_documents/';
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        // Delete old file if exists
                        if (!empty($_POST['existing_document_url']) && file_exists($_POST['existing_document_url'])) {
                            unlink($_POST['existing_document_url']);
                        }
                        
                        $fileName = time() . '_' . basename($_FILES['document_file']['name']);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $targetPath)) {
                            $documentUrl = $targetPath;
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE exit_documents SET exit_id=?, employee_id=?, document_type=?, document_name=?, document_url=?, notes=? WHERE document_id=?");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['document_type'],
                        $_POST['document_name'],
                        $documentUrl,
                        $_POST['notes'],
                        $_POST['document_id']
                    ]);
                    $_SESSION['message'] = "Exit document updated successfully!";
                    $_SESSION['message_type'] = "success";
                    header("Location: exit_documents.php");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error updating document: " . $e->getMessage();
                    $_SESSION['message_type'] = "error";
                    header("Location: exit_documents.php");
                    exit;
                }
                break;
            
            case 'delete':
                // Delete exit document
                try {
                    // Get document info to delete file
                    $stmt = $pdo->prepare("SELECT document_url FROM exit_documents WHERE document_id=?");
                    $stmt->execute([$_POST['document_id']]);
                    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Delete file if exists
                    if ($doc && !empty($doc['document_url']) && file_exists($doc['document_url'])) {
                        unlink($doc['document_url']);
                    }
                    
                    // Delete database record
                    $stmt = $pdo->prepare("DELETE FROM exit_documents WHERE document_id=?");
                    $stmt->execute([$_POST['document_id']]);
                    $_SESSION['message'] = "Exit document deleted successfully!";
                    $_SESSION['message_type'] = "success";
                    header("Location: exit_documents.php");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error deleting document: " . $e->getMessage();
                    $_SESSION['message_type'] = "error";
                    header("Location: exit_documents.php");
                    exit;
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
    SELECT 
        e.exit_id,
        e.employee_id,
        e.exit_type,
        e.exit_date,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY e.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exit Documents Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for exit documents page */
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
        }

        .type-clearance {
            background: #d1ecf1;
            color: #0c5460;
        }

        .type-resignation {
            background: #f8d7da;
            color: #721c24;
        }

        .type-final-pay {
            background: #d4edda;
            color: #155724;
        }

        .type-nda {
            background: #fff3cd;
            color: #856404;
        }

        .type-other {
            background: #e2e3e5;
            color: #383d41;
        }

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
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
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

        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-upload-label {
            display: block;
            padding: 12px 15px;
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            background: var(--azure-blue-light);
            color: white;
        }

        .file-upload-wrapper input[type=file] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-name-display {
            margin-top: 10px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 14px;
            color: #666;
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

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
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

            .content {
                padding: 20px;
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
                <h2 class="section-title">Exit Documents Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search documents by employee name, type, or document name...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add New Document
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="documentsTable">
                            <thead>
                                <tr>
                                    <th>Document ID</th>
                                    <th>Employee</th>
                                    <th>Document Type</th>
                                    <th>Document Name</th>
                                    <th>Exit Type</th>
                                    <th>Uploaded Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="documentsTableBody">
                                <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($doc['document_id']) ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($doc['employee_name']) ?></strong><br>
                                            <small style="color: #666;">👤 <?= htmlspecialchars($doc['employee_number']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="document-type-badge type-<?= strtolower(str_replace(' ', '-', $doc['document_type'])) ?>">
                                            <?= htmlspecialchars($doc['document_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($doc['document_name']) ?></td>
                                    <td><?= htmlspecialchars($doc['exit_type']) ?></td>
                                    <td><?= date('M d, Y', strtotime($doc['uploaded_date'])) ?></td>
                                    <td>
                                        <button class="btn btn-success btn-small"
    onclick="printCertificate(<?= $doc['employee_id'] ?>, <?= $doc['exit_id'] ?>)">
    <i class="fas fa-print"></i> Print
</button>

                                        <?php if (!empty($doc['document_url']) && file_exists($doc['document_url'])): ?>
                                        <a href="<?= htmlspecialchars($doc['document_url']) ?>" class="btn btn-info btn-small" target="_blank">
                                            📄 View
                                        </a>
                                        <?php endif; ?>
                                        <button class="btn btn-warning btn-small" onclick="editDocument(<?= $doc['document_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($documents)): ?>
                        <div class="no-results">
                            <i>📄</i>
                            <h3>No exit documents found</h3>
                            <p>Start by uploading your first exit document.</p>
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
                <h2 id="modalTitle">Add New Exit Document</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="documentForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="document_id" name="document_id">
                    <input type="hidden" id="existing_document_url" name="existing_document_url">

                    <div class="form-group">
                        <label for="exit_id">Exit Record</label>
                        <select id="exit_id" name="exit_id" class="form-control" required>
                            <option value="">Select exit record...</option>
                            <?php foreach ($exits as $exit): ?>
                            <option value="<?= $exit['exit_id'] ?>" data-employee-id="<?= $exit['employee_id'] ?>">
                                <?= htmlspecialchars($exit['employee_name']) ?> - <?= htmlspecialchars($exit['exit_type']) ?> (<?= date('M d, Y', strtotime($exit['exit_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" id="employee_id" name="employee_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="document_type">Document Name</label>
                                <select id="document_type" name="document_type" class="form-control" required>
                                    <option value="">Select type...</option>
                                    <option value="Clearance">Clearance</option>
                                    <option value="Resignation">Resignation Letter</option>
                                    <option value="Final Pay">Final Pay Slip</option>
                                    <option value="NDA">Non-Disclosure Agreement</option>
                                    <option value="Certificate">Certificate of Employment</option>
                                    <option value="Exit Interview">Exit Interview Form</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
<<<<<<< HEAD
                                <label for="document_name">File Extension Type</label>
=======
                                <label for="document_name">File Extension Type
>>>>>>> 13776b824ff02bbf68eecd564fbb3aa1e513a708
                                <select id="document_name" name="document_name" class="form-control" required>
                                    <option value="">Select document format...</option>
                                    <option value="PDF">.PDF</option>
                                    <option value="Docx">.Docx</option>
                                    <option value="xlsx">.xlsx</option>
                                    <option value="ppt">.ppt</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="document_file">Upload Document</label>
                        <div class="file-upload-wrapper">
                            <label for="document_file" class="file-upload-label" id="fileLabel">
                                📎 Choose File
                            </label>
                            <input type="file" id="document_file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                        <div id="fileNameDisplay" class="file-name-display" style="display: none;"></div>
                    </div>

                    <input type="hidden" id="notes" name="notes" value="">

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let documentsData = <?= json_encode($documents) ?>;

        // Auto-populate employee_id when exit record is selected
        document.getElementById('exit_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const employeeId = selectedOption.getAttribute('data-employee-id');
            document.getElementById('employee_id').value = employeeId || '';
        });

        // File upload handling
        document.getElementById('document_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : '';
            const fileLabel = document.getElementById('fileLabel');
            const fileDisplay = document.getElementById('fileNameDisplay');
            
            if (fileName) {
                fileLabel.textContent = '✅ File Selected';
                fileDisplay.textContent = '📎 ' + fileName;
                fileDisplay.style.display = 'block';
            } else {
                fileLabel.textContent = '📎 Choose File';
                fileDisplay.style.display = 'none';
            }
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('documentsTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Modal functions
        function openModal(mode, documentId = null) {
            const modal = document.getElementById('documentModal');
            const form = document.getElementById('documentForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            // Reset form first
            form.reset();
            
            if (mode === 'add') {
                title.textContent = 'Add New Exit Document';
                action.value = 'add';
                document.getElementById('document_id').value = '';
                document.getElementById('existing_document_url').value = '';
                document.getElementById('fileLabel').textContent = '📎 Choose File';
                document.getElementById('fileNameDisplay').style.display = 'none';
            } else if (mode === 'edit' && documentId) {
                title.textContent = 'Edit Exit Document';
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

        function populateEditForm(documentId) {
            const doc = documentsData.find(d => d.document_id == documentId);
            if (doc) {
                document.getElementById('exit_id').value = doc.exit_id || '';
                document.getElementById('employee_id').value = doc.employee_id || '';
                document.getElementById('document_type').value = doc.document_type || '';
                document.getElementById('document_name').value = doc.document_name || '';
                document.getElementById('notes').value = doc.notes || '';
                document.getElementById('existing_document_url').value = doc.document_url || '';
                
                if (doc.document_url) {
                    const fileName = doc.document_url.split('/').pop();
                    document.getElementById('fileLabel').textContent = '✅ Current File';
                    document.getElementById('fileNameDisplay').textContent = '📎 ' + fileName;
                    document.getElementById('fileNameDisplay').style.display = 'block';
                }
            }
        }

        function editDocument(documentId) {
            openModal('edit', documentId);
        }

        function deleteDocument(documentId) {
            if (confirm("Are you sure you want to delete this document? This action cannot be undone.")) {
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
            const modal = document.getElementById('documentModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Smooth scroll to top when alert is shown
        if (document.querySelector('.alert')) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        function printCertificate(employeeId, exitId) {
    const printWindow = window.open(
        `generate_exit_certificate.php?employee_id=${employeeId}&exit_id=${exitId}`,
        '',
        'width=900,height=700'
    );

    printWindow.onload = function() {
        printWindow.focus();
        printWindow.print();

        printWindow.onafterprint = function() {
            printWindow.close();
        };
    };
}

    </script>

    <!-- Bootstrap JS (Optional) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>