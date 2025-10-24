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
require_once 'db.php';

// Database connection
$pdo = connectToDatabase(
    dbname: 'CC_HR',
);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new knowledge transfer
                try {
                    $stmt = $pdo->prepare("INSERT INTO knowledge_transfers (exit_id, employee_id, handover_details, start_date, completion_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $completion_date = !empty($_POST['completion_date']) ? $_POST['completion_date'] : null;
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['handover_details'],
                        $_POST['start_date'],
                        $completion_date,
                        $_POST['status'],
                        $_POST['notes']
                    ]);
                    $message = "Knowledge transfer record added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding knowledge transfer: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update knowledge transfer
                try {
                    $stmt = $pdo->prepare("UPDATE knowledge_transfers SET exit_id=?, employee_id=?, handover_details=?, start_date=?, completion_date=?, status=?, notes=? WHERE transfer_id=?");
                    $completion_date = !empty($_POST['completion_date']) ? $_POST['completion_date'] : null;
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['handover_details'],
                        $_POST['start_date'],
                        $completion_date,
                        $_POST['status'],
                        $_POST['notes'],
                        $_POST['transfer_id']
                    ]);
                    $message = "Knowledge transfer record updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating knowledge transfer: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete knowledge transfer
                try {
                    $stmt = $pdo->prepare("DELETE FROM knowledge_transfers WHERE transfer_id=?");
                    $stmt->execute([$_POST['transfer_id']]);
                    $message = "Knowledge transfer record deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting knowledge transfer: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch knowledge transfers with related data
$stmt = $pdo->query("
    SELECT 
        kt.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        jr.title as job_title,
        jr.department
    FROM knowledge_transfers kt
    LEFT JOIN employee_profiles ep ON kt.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY kt.transfer_id DESC
");
$transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        jr.title as job_title,
        jr.department
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exit IDs for dropdown (assuming you have an exits table)
$stmt = $pdo->query("SELECT DISTINCT exit_id FROM knowledge_transfers ORDER BY exit_id");
$exitIds = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Transfer Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Custom styles for knowledge transfer page */
        :root {
            --primary-blue: #E91E63;        /* Changed to match system pink */
            --primary-blue-light: #F06292;  /* Changed to lighter pink */
            --primary-blue-dark: #C2185B;   /* Changed to darker pink */
            --primary-blue-lighter: #F8BBD0;/* Changed to very light pink */
            --primary-blue-pale: #FCE4EC;   /* Changed to palest pink */
        }

        .section-title {
            color: var(--primary-blue);
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
            background: var(--primary-blue-pale);
        }

        .main-content {
            background: var(--primary-blue-pale);
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
            border-color: var(--primary-blue);
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

        .filter-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-select {
            padding: 8px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            background: white;
            cursor: pointer;
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
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--primary-blue-dark) 100%);
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
            background: linear-gradient(135deg, var(--primary-blue-lighter) 0%, #e9ecef 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-blue-dark);
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--primary-blue-lighter);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .status-not-started {
            background: #f8d7da;
            color: #721c24;
        }

        .handover-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            color: #666;
        }

        .handover-preview:hover {
            color: var(--primary-blue);
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
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
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
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%);
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
            color: var(--primary-blue-dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .form-control textarea {
            min-height: 120px;
            resize: vertical;
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

        .transfer-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid var(--primary-blue);
        }

        .transfer-meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .filter-controls {
                justify-content: center;
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
                <h2 class="section-title">📚 Knowledge Transfer Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by employee name, job title, or handover details...">
                        </div>
                        <div class="filter-controls">
                            <select class="filter-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="Completed">Completed</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Not Started">Not Started</option>
                            </select>
                            <button class="btn btn-primary" onclick="openModal('add')">
                                ➕ Add Knowledge Transfer
                            </button>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="table" id="transfersTable">
                            <thead>
                                <tr>
                                    <th>Transfer ID</th>
                                    <th>Employee</th>
                                    <th>Job Title</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>Completion Date</th>
                                    <th>Handover Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="transfersTableBody">
                                <?php foreach ($transfers as $transfer): ?>
                                <tr data-status="<?= strtolower(str_replace(' ', '-', $transfer['status'])) ?>">
                                    <td><strong>#<?= htmlspecialchars($transfer['transfer_id']) ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($transfer['employee_name']) ?></strong><br>
                                            <small style="color: #666;">Exit ID: <?= htmlspecialchars($transfer['exit_id']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($transfer['job_title']) ?></td>
                                    <td><?= htmlspecialchars($transfer['department']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $transfer['status'])) ?>">
                                            <?= htmlspecialchars($transfer['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $transfer['start_date'] ? date('M d, Y', strtotime($transfer['start_date'])) : 'Not set' ?></td>
                                    <td>
                                        <?= $transfer['completion_date'] ? date('M d, Y', strtotime($transfer['completion_date'])) : '<span style="color: #666;">Pending</span>' ?>
                                    </td>
                                    <td>
                                        <div class="handover-preview" onclick="showHandoverDetails('<?= addslashes($transfer['handover_details']) ?>', '<?= addslashes($transfer['employee_name']) ?>')">
                                            <?= strlen($transfer['handover_details']) > 50 ? htmlspecialchars(substr($transfer['handover_details'], 0, 50)) . '...' : htmlspecialchars($transfer['handover_details']) ?>
                                            <br><small style="color: var(--primary-blue);">Click to view full details</small>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editTransfer(<?= $transfer['transfer_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteTransfer(<?= $transfer['transfer_id'] ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($transfers)): ?>
                        <div class="no-results">
                            <i>📚</i>
                            <h3>No knowledge transfers found</h3>
                            <p>Start by adding your first knowledge transfer record.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Knowledge Transfer Modal -->
    <div id="transferModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Knowledge Transfer</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="transferForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="transfer_id" name="transfer_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="exit_id">Exit ID</label>
                                <input type="number" id="exit_id" name="exit_id" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select employee...</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>">
                                        <?= htmlspecialchars($employee['full_name']) ?> - <?= htmlspecialchars($employee['job_title']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="handover_details">Handover Details</label>
                        <textarea id="handover_details" name="handover_details" class="form-control" rows="6" required placeholder="Describe the knowledge transfer details, procedures, responsibilities, and any important information..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="completion_date">Completion Date</label>
                                <input type="date" id="completion_date" name="completion_date" class="form-control">
                                <small style="color: #666;">Leave empty if not yet completed</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="">Select status...</option>
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="4" placeholder="Any additional notes, comments, or observations..."></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Knowledge Transfer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Handover Details Modal -->
    <div id="handoverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="handoverModalTitle">Handover Details</h2>
                <span class="close" onclick="closeHandoverModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="handoverContent"></div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let transfersData = <?= json_encode($transfers) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);

        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const tableBody = document.getElementById('transfersTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                
                const matchesSearch = text.includes(searchTerm);
                const matchesStatus = !statusFilter || status.includes(statusFilter.replace(' ', '-'));
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        // Modal functions
        function openModal(mode, transferId = null) {
            const modal = document.getElementById('transferModal');
            const form = document.getElementById('transferForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add Knowledge Transfer';
                action.value = 'add';
                form.reset();
                document.getElementById('transfer_id').value = '';
            } else if (mode === 'edit' && transferId) {
                title.textContent = 'Edit Knowledge Transfer';
                action.value = 'update';
                document.getElementById('transfer_id').value = transferId;
                populateEditForm(transferId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('transferModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function showHandoverDetails(details, employeeName) {
            const modal = document.getElementById('handoverModal');
            const title = document.getElementById('handoverModalTitle');
            const content = document.getElementById('handoverContent');
            
            title.textContent = `Handover Details - ${employeeName}`;
            content.innerHTML = `
                <div class="transfer-details">
                    <h4>Knowledge Transfer Documentation</h4>
                    <p style="white-space: pre-line; line-height: 1.6;">${details}</p>
                </div>
            `;
            
            modal.style.display = 'block';
        }

        function closeHandoverModal() {
            document.getElementById('handoverModal').style.display = 'none';
        }

        function populateEditForm(transferId) {
            const transfer = transfersData.find(t => t.transfer_id == transferId);
            if (transfer) {
                document.getElementById('exit_id').value = transfer.exit_id || '';
                document.getElementById('employee_id').value = transfer.employee_id || '';
                document.getElementById('handover_details').value = transfer.handover_details || '';
                document.getElementById('start_date').value = transfer.start_date || '';
                document.getElementById('completion_date').value = transfer.completion_date || '';
                document.getElementById('status').value = transfer.status || '';
                document.getElementById('notes').value = transfer.notes || '';
            }
        }

        function editTransfer(transferId) {
            openModal('edit', transferId);
        }

        function deleteTransfer(transferId) {
            if (confirm('Are you sure you want to delete this knowledge transfer record? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="transfer_id" value="${transferId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const transferModal = document.getElementById('transferModal');
            const handoverModal = document.getElementById('handoverModal');
            if (event.target === transferModal) {
                closeModal();
            } else if (event.target === handoverModal) {
                closeHandoverModal();
            }
        }

        // Form validation
        document.getElementById('transferForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const completionDate = document.getElementById('completion_date').value;
            const status = document.getElementById('status').value;
            
            if (completionDate) {
                const compDate = new Date(completionDate);
                if (compDate < startDate) {
                    e.preventDefault();
                    alert('Completion date cannot be earlier than start date');
                    return;
                }
            }

            if (status === 'Completed' && !completionDate) {
                if (!confirm('Status is marked as Completed but no completion date is set. Continue anyway?')) {
                    e.preventDefault();
                    return;
                }
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

        // Status-based completion date handling
        document.getElementById('status').addEventListener('change', function() {
            const completionDateField = document.getElementById('completion_date');
            const status = this.value;
            
            if (status === 'Completed') {
                completionDateField.setAttribute('required', 'required');
                if (!completionDateField.value) {
                    completionDateField.value = new Date().toISOString().split('T')[0];
                }
            } else {
                completionDateField.removeAttribute('required');
                if (status === 'Not Started') {
                    completionDateField.value = '';
                }
            }
        });

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#transfersTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Progress indicator for in-progress transfers
            updateProgressIndicators();
        });

        function updateProgressIndicators() {
            const inProgressRows = document.querySelectorAll('[data-status="in-progress"]');
            inProgressRows.forEach(row => {
                const statusCell = row.querySelector('.status-badge');
                if (statusCell) {
                    statusCell.style.animation = 'pulse 2s infinite';
                }
            });
        }

        // Export functionality
        function exportTransfers() {
            const table = document.getElementById('transfersTable');
            const rows = [];
            
            // Get headers
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                if (th.textContent.trim() !== 'Actions') {
                    headers.push(th.textContent.trim());
                }
            });
            rows.push(headers.join(','));
            
            // Get visible rows data
            const visibleRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => 
                row.style.display !== 'none'
            );
            
            visibleRows.forEach(row => {
                const cells = [];
                const tds = row.querySelectorAll('td');
                for (let i = 0; i < tds.length - 1; i++) { // Exclude actions column
                    let cellText = tds[i].textContent.trim().replace(/\n/g, ' ').replace(/,/g, ';');
                    cells.push(`"${cellText}"`);
                }
                rows.push(cells.join(','));
            });
            
            // Create and download CSV
            const csv = rows.join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `knowledge_transfers_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Add export button to controls
        document.addEventListener('DOMContentLoaded', function() {
            const controls = document.querySelector('.filter-controls');
            const exportBtn = document.createElement('button');
            exportBtn.className = 'btn';
            exportBtn.style.background = '#17a2b8';
            exportBtn.style.color = 'white';
            exportBtn.innerHTML = '📊 Export CSV';
            exportBtn.onclick = exportTransfers;
            controls.insertBefore(exportBtn, controls.lastElementChild);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N to add new transfer
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                openModal('add');
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                closeModal();
                closeHandoverModal();
            }
        });

        // Real-time status updates
        function updateTransferStatus(transferId, newStatus) {
            fetch('update_transfer_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    transfer_id: transferId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Refresh to show updated status
                } else {
                    alert('Error updating status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status');
            });
        }

        // Add quick status update buttons (you can uncomment this if you want quick status changes)
        /*
        function addQuickStatusButtons() {
            document.querySelectorAll('#transfersTable tbody tr').forEach(row => {
                const statusCell = row.querySelector('.status-badge');
                const transferId = row.querySelector('td:first-child strong').textContent.replace('#', '');
                
                if (statusCell && !statusCell.parentNode.querySelector('.quick-status-btns')) {
                    const quickBtns = document.createElement('div');
                    quickBtns.className = 'quick-status-btns';
                    quickBtns.style.marginTop = '5px';
                    quickBtns.innerHTML = `
                        <button onclick="updateTransferStatus(${transferId}, 'In Progress')" 
                                style="font-size: 10px; padding: 2px 6px; margin: 1px; background: #ffc107; border: none; border-radius: 3px; cursor: pointer;">
                            ⏳
                        </button>
                        <button onclick="updateTransferStatus(${transferId}, 'Completed')" 
                                style="font-size: 10px; padding: 2px 6px; margin: 1px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">
                            ✅
                        </button>
                    `;
                    statusCell.parentNode.appendChild(quickBtns);
                }
            });
        }
        */

        // Performance optimization for large datasets
        function initializeVirtualScrolling() {
            // This would be implemented for very large datasets
            // For now, we'll use pagination instead
            const rowsPerPage = 25;
            let currentPage = 1;
            
            function showPage(page) {
                const tbody = document.getElementById('transfersTableBody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const start = (page - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                
                rows.forEach((row, index) => {
                    if (index >= start && index < end) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            // Add pagination controls if there are many records
            if (transfersData.length > rowsPerPage) {
                const totalPages = Math.ceil(transfersData.length / rowsPerPage);
                const paginationHtml = `
                    <div class="pagination-controls" style="text-align: center; margin-top: 20px;">
                        <button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
                        <span>Page ${currentPage} of ${totalPages}</span>
                        <button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
                    </div>
                `;
                document.querySelector('.table-container').insertAdjacentHTML('afterend', paginationHtml);
                showPage(1);
            }
        }

        function changePage(page) {
            // Implementation for pagination
            currentPage = page;
            showPage(page);
        }
    </script>

    <!-- Additional CSS for animations -->
    <style>
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .quick-status-btns button:hover {
            transform: scale(1.1);
        }

        .pagination-controls {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .pagination-controls button {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 8px 16px;
            margin: 0 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .pagination-controls button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .pagination-controls span {
            font-weight: 600;
            color: var(--primary-blue);
        }

        /* Responsive enhancements */
        @media (max-width: 992px) {
            .handover-preview {
                max-width: 200px;
            }
            
            .btn-small {
                padding: 6px 10px;
                font-size: 12px;
            }
        }

        @media (max-width: 576px) {
            .table th, .table td {
                padding: 8px;
                font-size: 14px;
            }
            
            .modal-content {
                margin: 5% auto;
                width: 95%;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .handover-preview {
                max-width: 150px;
            }
        }

        /* Print styles */
        @media print {
            .controls, .btn, .modal {
                display: none !important;
            }
            
            .main-content {
                background: white !important;
            }
            
            .table-container {
                box-shadow: none !important;
            }
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>