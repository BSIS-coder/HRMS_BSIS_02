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
                    $stmt = $pdo->prepare("INSERT INTO knowledge_transfers (exit_id, employee_id, handover_details, start_date, completion_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['handover_details'],
                        $_POST['start_date'] ?: null,
                        $_POST['completion_date'] ?: null,
                        $_POST['status'],
                        $_POST['notes']
                    ]);
                    $_SESSION['message'] = "Knowledge transfer added successfully!";
                    $_SESSION['messageType'] = "success";
                    header("Location: knowledge_transfers.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error adding knowledge transfer: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: knowledge_transfers.php");
                    exit();
                }
                break;
            
            case 'update':
                try {
                    $stmt = $pdo->prepare("UPDATE knowledge_transfers SET exit_id=?, employee_id=?, handover_details=?, start_date=?, completion_date=?, status=?, notes=? WHERE transfer_id=?");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['handover_details'],
                        $_POST['start_date'] ?: null,
                        $_POST['completion_date'] ?: null,
                        $_POST['status'],
                        $_POST['notes'],
                        $_POST['transfer_id']
                    ]);
                    $_SESSION['message'] = "Knowledge transfer updated successfully!";
                    $_SESSION['messageType'] = "success";
                    header("Location: knowledge_transfers.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error updating knowledge transfer: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: knowledge_transfers.php");
                    exit();
                }
                break;
            
            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM knowledge_transfers WHERE transfer_id=?");
                    $stmt->execute([$_POST['transfer_id']]);
                    $_SESSION['message'] = "Knowledge transfer deleted successfully!";
                    $_SESSION['messageType'] = "success";
                    header("Location: knowledge_transfers.php");
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error deleting knowledge transfer: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: knowledge_transfers.php");
                    exit();
                }
                break;
        }
    }
}

// Check for flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// Fetch knowledge transfers with related data
$stmt = $pdo->query("
    SELECT 
        kt.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        jr.title as job_title,
        jr.department,
        e.exit_date,
        e.exit_type,
        CONCAT(pi_exit.first_name, ' ', pi_exit.last_name) as exiting_employee_name
    FROM knowledge_transfers kt
    LEFT JOIN employee_profiles ep ON kt.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN exits e ON kt.exit_id = e.exit_id
    LEFT JOIN employee_profiles ep_exit ON e.employee_id = ep_exit.employee_id
    LEFT JOIN personal_information pi_exit ON ep_exit.personal_info_id = pi_exit.personal_info_id
    ORDER BY kt.transfer_id DESC
");
$transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown
$stmt = $pdo->query("
    SELECT 
        e.exit_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        e.exit_date,
        e.exit_type
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY e.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        ep.employee_number,
        jr.title as job_title
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    WHERE ep.employment_status = 'Full-time' OR ep.employment_status = 'Part-time'
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        /* Additional custom styles for knowledge transfer page */
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

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-not-started {
            background: #f8d7da;
            color: #721c24;
        }

        .status-in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-na {
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
            margin: 3% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
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

        textarea.form-control {
            min-height: 100px;
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

        .info-label {
            font-size: 12px;
            color: #666;
            display: block;
            margin-top: 2px;
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
                <h2 class="section-title">📚 Knowledge Transfer Management</h2>
                <div class="content">
                    <?php if (isset($message)): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by employee name, status, or exit type...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add Knowledge Transfer
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="transferTable">
                            <thead>
                                <tr>
                                    <th>Transfer ID</th>
                                    <th>Exiting Employee</th>
                                    <th>Receiving Employee</th>
                                    <th>Exit Type</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>Completion Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="transferTableBody">
                                <?php foreach ($transfers as $transfer): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($transfer['transfer_id']) ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($transfer['exiting_employee_name']) ?></strong><br>
                                            <small class="info-label">Exit: <?= date('M d, Y', strtotime($transfer['exit_date'])) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($transfer['employee_name']) ?></strong><br>
                                            <small class="info-label"><?= htmlspecialchars($transfer['job_title']) ?> - <?= htmlspecialchars($transfer['department']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($transfer['exit_type']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $transfer['status'])) ?>">
                                            <?= htmlspecialchars($transfer['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $transfer['start_date'] ? date('M d, Y', strtotime($transfer['start_date'])) : 'Not set' ?></td>
                                    <td><?= $transfer['completion_date'] ? date('M d, Y', strtotime($transfer['completion_date'])) : 'Not set' ?></td>
                                    <td>
                                        <button class="btn btn-info btn-small" onclick="viewTransfer(<?= $transfer['transfer_id'] ?>)">
                                            👁️ View
                                        </button>
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
                                <label for="exit_id">Exit Record *</label>
                                <select id="exit_id" name="exit_id" class="form-control" required>
                                    <option value="">Select exit...</option>
                                    <?php foreach ($exits as $exit): ?>
                                    <option value="<?= $exit['exit_id'] ?>">
                                        <?= htmlspecialchars($exit['employee_name']) ?> - <?= htmlspecialchars($exit['exit_type']) ?> (<?= date('M d, Y', strtotime($exit['exit_date'])) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Receiving Employee *</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select employee...</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>">
                                        <?= htmlspecialchars($emp['full_name']) ?> - <?= htmlspecialchars($emp['job_title']) ?> (<?= htmlspecialchars($emp['employee_number']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="handover_details">Handover Details</label>
                        <textarea id="handover_details" name="handover_details" class="form-control" placeholder="Describe the knowledge, responsibilities, and tasks being transferred..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="completion_date">Completion Date</label>
                                <input type="date" id="completion_date" name="completion_date" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="N/A">N/A</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea id="notes" name="notes" class="form-control" placeholder="Any additional information or observations..."></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Knowledge Transfer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Knowledge Transfer Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📚 Knowledge Transfer Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let transfersData = <?= json_encode($transfers) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('transferTableBody');
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

        function populateEditForm(transferId) {
            const transfer = transfersData.find(t => t.transfer_id == transferId);
            if (transfer) {
                document.getElementById('exit_id').value = transfer.exit_id || '';
                document.getElementById('employee_id').value = transfer.employee_id || '';
                document.getElementById('handover_details').value = transfer.handover_details || '';
                document.getElementById('start_date').value = transfer.start_date || '';
                document.getElementById('completion_date').value = transfer.completion_date || '';
                document.getElementById('status').value = transfer.status || 'Not Started';
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

        function viewTransfer(transferId) {
            const transfer = transfersData.find(t => t.transfer_id == transferId);
            if (!transfer) return;

            const viewModalBody = document.getElementById('viewModalBody');
            const statusClass = 'status-' + transfer.status.toLowerCase().replace(' ', '-');
            
            viewModalBody.innerHTML = `
                <div style="padding: 20px;">
                    <div style="margin-bottom: 25px;">
                        <h4 style="color: var(--azure-blue); margin-bottom: 15px;">Transfer Information</h4>
                        <p><strong>Transfer ID:</strong> #${transfer.transfer_id}</p>
                        <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${transfer.status}</span></p>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <h4 style="color: var(--azure-blue); margin-bottom: 15px;">Employees</h4>
                        <p><strong>Exiting Employee:</strong> ${transfer.exiting_employee_name}</p>
                        <p><strong>Exit Date:</strong> ${new Date(transfer.exit_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        <p><strong>Exit Type:</strong> ${transfer.exit_type}</p>
                        <hr>
                        <p><strong>Receiving Employee:</strong> ${transfer.employee_name}</p>
                        <p><strong>Job Title:</strong> ${transfer.job_title}</p>
                        <p><strong>Department:</strong> ${transfer.department}</p>
                        <p><strong>Employee Number:</strong> ${transfer.employee_number}</p>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <h4 style="color: var(--azure-blue); margin-bottom: 15px;">Timeline</h4>
                        <p><strong>Start Date:</strong> ${transfer.start_date ? new Date(transfer.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Not set'}</p>
                        <p><strong>Completion Date:</strong> ${transfer.completion_date ? new Date(transfer.completion_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Not set'}</p>
                    </div>

                    ${transfer.handover_details ? `
                    <div style="margin-bottom: 25px;">
                        <h4 style="color: var(--azure-blue); margin-bottom: 15px;">Handover Details</h4>
                        <p style="white-space: pre-wrap; background: #f8f9fa; padding: 15px; border-radius: 8px;">${transfer.handover_details}</p>
                    </div>
                    ` : ''}

                    ${transfer.notes ? `
                    <div style="margin-bottom: 25px;">
                        <h4 style="color: var(--azure-blue); margin-bottom: 15px;">Additional Notes</h4>
                        <p style="white-space: pre-wrap; background: #f8f9fa; padding: 15px; border-radius: 8px;">${transfer.notes}</p>
                    </div>
                    ` : ''}

                    <div style="margin-bottom: 15px;">
                        <h4 style="color: var(--azure-blue); margin-bottom: 15px;">Record Information</h4>
                        <p><strong>Created:</strong> ${new Date(transfer.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                        <p><strong>Last Updated:</strong> ${new Date(transfer.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button class="btn btn-primary" onclick="closeViewModal()">Close</button>
                    </div>
                </div>
            `;

            document.getElementById('viewModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const transferModal = document.getElementById('transferModal');
            const viewModal = document.getElementById('viewModal');
            
            if (event.target === transferModal) {
                closeModal();
            } else if (event.target === viewModal) {
                closeViewModal();
            }
        }

        // Form validation
        document.getElementById('transferForm').addEventListener('submit', function(e) {
            if (this.submitted) {
                e.preventDefault();
                return;
            }
            
            const startDate = document.getElementById('start_date').value;
            const completionDate = document.getElementById('completion_date').value;

            if (startDate && completionDate && new Date(completionDate) < new Date(startDate)) {
                e.preventDefault();
                alert('Completion date cannot be earlier than start date');
                return;
            }

            // Disable submit button and mark form as submitted
            this.submitted = true;
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
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

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#transferTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Auto-set today's date as default for start date when adding new transfer
            const startDateInput = document.getElementById('start_date');
            if (startDateInput && !startDateInput.value) {
                const today = new Date().toISOString().split('T')[0];
                startDateInput.setAttribute('max', '2099-12-31');
            }

            // Set completion date min to start date
            document.getElementById('start_date').addEventListener('change', function() {
                const completionDateInput = document.getElementById('completion_date');
                completionDateInput.setAttribute('min', this.value);
            });
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
