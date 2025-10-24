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
$pdo = connectToDatabase();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new exit interview
                try {
                    $stmt = $pdo->prepare("INSERT INTO exit_interviews (exit_id, employee_id, interview_date, feedback, improvement_suggestions, reason_for_leaving, would_recommend, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['interview_date'],
                        $_POST['feedback'],
                        $_POST['improvement_suggestions'],
                        $_POST['reason_for_leaving'],
                        isset($_POST['would_recommend']) ? 1 : 0,
                        $_POST['status']
                    ]);
                    $message = "Exit interview scheduled successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error scheduling exit interview: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update exit interview
                try {
                    $stmt = $pdo->prepare("UPDATE exit_interviews SET exit_id=?, employee_id=?, interview_date=?, feedback=?, improvement_suggestions=?, reason_for_leaving=?, would_recommend=?, status=? WHERE interview_id=?");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['employee_id'],
                        $_POST['interview_date'],
                        $_POST['feedback'],
                        $_POST['improvement_suggestions'],
                        $_POST['reason_for_leaving'],
                        isset($_POST['would_recommend']) ? 1 : 0,
                        $_POST['status'],
                        $_POST['interview_id']
                    ]);
                    $message = "Exit interview updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating exit interview: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete exit interview
                try {
                    $stmt = $pdo->prepare("DELETE FROM exit_interviews WHERE interview_id=?");
                    $stmt->execute([$_POST['interview_id']]);
                    $message = "Exit interview deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting exit interview: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch exit interviews with related data
$stmt = $pdo->query("
    SELECT 
        ei.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        jr.title as job_title,
        jr.department,
        e.exit_date,
        e.exit_reason
    FROM exit_interviews ei
    LEFT JOIN exits e ON ei.exit_id = e.exit_id
    LEFT JOIN employee_profiles ep ON ei.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY ei.interview_date DESC, ei.interview_id DESC
");
$interviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown
$stmt = $pdo->query("
    SELECT 
        e.exit_id, 
        e.exit_date,
        e.exit_reason,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
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
    WHERE ep.employment_status != 'Terminated'
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        /* Additional custom styles for exit interview page */
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
        }

        .status-scheduled {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .recommendation-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .recommendation-yes {
            background: #d4edda;
            color: #155724;
        }

        .recommendation-no {
            background: #f8d7da;
            color: #721c24;
        }

        .recommendation-na {
            background: #e2e3e5;
            color: #495057;
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
            width: 95%;
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
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

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .interview-details {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 14px;
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
                <h2 class="section-title">Exit Interview Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by employee name, status, or interview date...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            📋 Schedule Exit Interview
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="interviewTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Job Title</th>
                                    <th>Exit Date</th>
                                    <th>Interview Date</th>
                                    <th>Status</th>
                                    <th>Would Recommend</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="interviewTableBody">
                                <?php foreach ($interviews as $interview): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($interview['employee_name']) ?></strong><br>
                                            <small style="color: #666;">ID: <?= htmlspecialchars($interview['employee_number']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <?= htmlspecialchars($interview['job_title']) ?><br>
                                            <small style="color: #666;"><?= htmlspecialchars($interview['department']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= $interview['exit_date'] ? date('M d, Y', strtotime($interview['exit_date'])) : 'N/A' ?></td>
                                    <td><strong><?= date('M d, Y', strtotime($interview['interview_date'])) ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($interview['status']) ?>">
                                            <?= htmlspecialchars($interview['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($interview['status'] == 'Completed'): ?>
                                            <?php if ($interview['would_recommend'] === '1'): ?>
                                                <span class="recommendation-badge recommendation-yes">✅ Yes</span>
                                            <?php elseif ($interview['would_recommend'] === '0'): ?>
                                                <span class="recommendation-badge recommendation-no">❌ No</span>
                                            <?php else: ?>
                                                <span class="recommendation-badge recommendation-na">➖ N/A</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="recommendation-badge recommendation-na">➖ Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editInterview(<?= $interview['interview_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteInterview(<?= $interview['interview_id'] ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($interviews)): ?>
                        <div class="no-results">
                            <i>📋</i>
                            <h3>No exit interviews found</h3>
                            <p>Start by scheduling your first exit interview.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Exit Interview Modal -->
    <div id="interviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Schedule Exit Interview</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="interviewForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="interview_id" name="interview_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="exit_id">Exit Record</label>
                                <select id="exit_id" name="exit_id" class="form-control" required>
                                    <option value="">Select exit record...</option>
                                    <?php foreach ($exits as $exit): ?>
                                    <option value="<?= $exit['exit_id'] ?>" 
                                            data-employee="<?= htmlspecialchars($exit['employee_name']) ?>"
                                            data-exit-date="<?= $exit['exit_date'] ?>"
                                            data-reason="<?= htmlspecialchars($exit['exit_reason']) ?>">
                                        <?= htmlspecialchars($exit['employee_name']) ?> (<?= htmlspecialchars($exit['employee_number']) ?>) - <?= date('M d, Y', strtotime($exit['exit_date'])) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select employee...</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>">
                                        <?= htmlspecialchars($employee['full_name']) ?> (<?= htmlspecialchars($employee['employee_number']) ?>) - <?= htmlspecialchars($employee['job_title']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="interview_date">Interview Date</label>
                                <input type="date" id="interview_date" name="interview_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="Scheduled">Scheduled</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reason_for_leaving">Reason for Leaving</label>
                        <textarea id="reason_for_leaving" name="reason_for_leaving" class="form-control" 
                                  placeholder="Employee's stated reason for leaving the company..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="feedback">General Feedback</label>
                        <textarea id="feedback" name="feedback" class="form-control" 
                                  placeholder="Employee's feedback about their experience, work environment, management, etc..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="improvement_suggestions">Improvement Suggestions</label>
                        <textarea id="improvement_suggestions" name="improvement_suggestions" class="form-control" 
                                  placeholder="Employee's suggestions for company improvement..."></textarea>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="would_recommend" name="would_recommend">
                            <label for="would_recommend">Would recommend this company to others</label>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Interview</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let interviewsData = <?= json_encode($interviews) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('interviewTableBody');
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
        function openModal(mode, interviewId = null) {
            const modal = document.getElementById('interviewModal');
            const form = document.getElementById('interviewForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Schedule Exit Interview';
                action.value = 'add';
                form.reset();
                document.getElementById('interview_id').value = '';
                
                // Set default interview date to tomorrow
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                document.getElementById('interview_date').value = tomorrow.toISOString().split('T')[0];
            } else if (mode === 'edit' && interviewId) {
                title.textContent = 'Edit Exit Interview';
                action.value = 'update';
                document.getElementById('interview_id').value = interviewId;
                populateEditForm(interviewId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('interviewModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(interviewId) {
            const interview = interviewsData.find(int => int.interview_id == interviewId);
            if (interview) {
                document.getElementById('exit_id').value = interview.exit_id || '';
                document.getElementById('employee_id').value = interview.employee_id || '';
                document.getElementById('interview_date').value = interview.interview_date || '';
                document.getElementById('status').value = interview.status || '';
                document.getElementById('reason_for_leaving').value = interview.reason_for_leaving || '';
                document.getElementById('feedback').value = interview.feedback || '';
                document.getElementById('improvement_suggestions').value = interview.improvement_suggestions || '';
                document.getElementById('would_recommend').checked = interview.would_recommend == 1;
            }
        }

        function editInterview(interviewId) {
            openModal('edit', interviewId);
        }

        function deleteInterview(interviewId) {
            if (confirm('Are you sure you want to delete this exit interview? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="interview_id" value="${interviewId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-populate employee when exit record is selected
        document.getElementById('exit_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                // You could auto-populate the employee field here if needed
                // For now, we'll leave both fields independent for flexibility
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('interviewModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('interviewForm').addEventListener('submit', function(e) {
            const interviewDate = new Date(document.getElementById('interview_date').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (interviewDate < today) {
                if (!confirm('The interview date is in the past. Are you sure you want to continue?')) {
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

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#interviewTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.boxShadow = '0 5px 15px rgba(233, 30, 99, 0.2)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = 'none';
                });
            });

            // Add smooth scrolling animation
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Animate buttons on load
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach((btn, index) => {
                btn.style.opacity = '0';
                btn.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    btn.style.transition = 'all 0.5s ease';
                    btn.style.opacity = '1';
                    btn.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Enhanced search with filters
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter')?.value?.toLowerCase() || '';
            const dateFilter = document.getElementById('dateFilter')?.value || '';
            
            const tableBody = document.getElementById('interviewTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                const statusCell = row.cells[4]?.textContent.toLowerCase() || '';
                const dateCell = row.cells[3]?.textContent || '';
                
                let showRow = text.includes(searchTerm);
                
                if (statusFilter && !statusCell.includes(statusFilter)) {
                    showRow = false;
                }
                
                if (dateFilter && !dateCell.includes(dateFilter)) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            }

            // Update results count
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none').length;
            updateResultsCount(visibleRows, rows.length);
        }

        function updateResultsCount(visible, total) {
            let countElement = document.getElementById('resultsCount');
            if (!countElement) {
                countElement = document.createElement('div');
                countElement.id = 'resultsCount';
                countElement.style.cssText = 'margin-top: 10px; color: #666; font-size: 14px;';
                document.querySelector('.table-container').appendChild(countElement);
            }
            countElement.textContent = `Showing ${visible} of ${total} interviews`;
        }

        // Export functionality
        function exportToCSV() {
            const table = document.getElementById('interviewTable');
            const rows = table.querySelectorAll('tr');
            let csv = [];

            for (let i = 0; i < rows.length; i++) {
                if (rows[i].style.display === 'none') continue; // Skip hidden rows
                
                const row = rows[i];
                const cols = row.querySelectorAll('td, th');
                let rowData = [];

                for (let j = 0; j < cols.length - 1; j++) { // Skip actions column
                    let cellData = cols[j].textContent.trim();
                    // Clean up the data
                    cellData = cellData.replace(/\n/g, ' ').replace(/\s+/g, ' ');
                    rowData.push(`"${cellData}"`);
                }
                csv.push(rowData.join(','));
            }

            // Download CSV
            const csvString = csv.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `exit_interviews_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Print functionality
        function printTable() {
            const printWindow = window.open('', '_blank');
            const table = document.getElementById('interviewTable').cloneNode(true);
            
            // Remove action column
            const actionHeaders = table.querySelectorAll('th:last-child, td:last-child');
            actionHeaders.forEach(cell => cell.remove());
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Exit Interviews Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                        .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; }
                        .status-scheduled { background: #fff3cd; }
                        .status-completed { background: #d4edda; }
                        .status-cancelled { background: #f8d7da; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <h1>Exit Interviews Report</h1>
                    <p>Generated on: ${new Date().toLocaleDateString()}</p>
                    ${table.outerHTML}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                closeModal();
            }
            
            // Ctrl/Cmd + N to add new interview
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                openModal('add');
            }
        });

        // Form auto-save (draft functionality)
        let autoSaveTimer;
        function setupAutoSave() {
            const formInputs = document.querySelectorAll('#interviewForm input, #interviewForm textarea, #interviewForm select');
            
            formInputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(saveDraft, 2000); // Save after 2 seconds of inactivity
                });
            });
        }

        function saveDraft() {
            const formData = new FormData(document.getElementById('interviewForm'));
            const draft = {};
            
            for (let [key, value] of formData.entries()) {
                draft[key] = value;
            }
            
            localStorage.setItem('exitInterviewDraft', JSON.stringify(draft));
            
            // Show draft saved indicator
            const indicator = document.createElement('div');
            indicator.textContent = '💾 Draft saved';
            indicator.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 8px 15px; border-radius: 5px; z-index: 1001; font-size: 14px;';
            document.body.appendChild(indicator);
            
            setTimeout(() => {
                indicator.remove();
            }, 2000);
        }

        function loadDraft() {
            const draft = localStorage.getItem('exitInterviewDraft');
            if (draft && confirm('A draft was found. Would you like to load it?')) {
                const draftData = JSON.parse(draft);
                
                Object.keys(draftData).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        if (element.type === 'checkbox') {
                            element.checked = draftData[key] === 'on';
                        } else {
                            element.value = draftData[key];
                        }
                    }
                });
            }
        }

        function clearDraft() {
            localStorage.removeItem('exitInterviewDraft');
        }

        // Enhanced modal with keyboard navigation
        document.addEventListener('DOMContentLoaded', function() {
            setupAutoSave();
            
            // Add keyboard navigation in modal
            document.getElementById('interviewModal').addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    const focusableElements = this.querySelectorAll(
                        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                    );
                    const firstFocusable = focusableElements[0];
                    const lastFocusable = focusableElements[focusableElements.length - 1];

                    if (e.shiftKey) {
                        if (document.activeElement === firstFocusable) {
                            lastFocusable.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastFocusable) {
                            firstFocusable.focus();
                            e.preventDefault();
                        }
                    }
                }
            });
        });

        // Real-time validation
        function validateForm() {
            const form = document.getElementById('interviewForm');
            const submitBtn = form.querySelector('button[type="submit"]');
            let isValid = true;

            // Check required fields
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#dc3545';
                } else {
                    field.style.borderColor = '#28a745';
                }
            });

            // Date validation
            const interviewDate = document.getElementById('interview_date');
            const today = new Date().toISOString().split('T')[0];
            
            if (interviewDate.value && interviewDate.value < today) {
                interviewDate.style.borderColor = '#ffc107';
                interviewDate.title = 'Interview date is in the past';
            }

            submitBtn.disabled = !isValid;
            submitBtn.style.opacity = isValid ? '1' : '0.6';
        }

        // Add event listeners for real-time validation
        document.addEventListener('DOMContentLoaded', function() {
            const formInputs = document.querySelectorAll('#interviewForm input[required], #interviewForm select[required]');
            formInputs.forEach(input => {
                input.addEventListener('blur', validateForm);
                input.addEventListener('input', validateForm);
            });
        });

        // Success animation after form submission
        function showSuccessAnimation() {
            const successDiv = document.createElement('div');
            successDiv.innerHTML = '✅';
            successDiv.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 4rem;
                z-index: 2000;
                animation: successBounce 1s ease;
            `;
            
            const style = document.createElement('style');
            style.textContent = `
                @keyframes successBounce {
                    0% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
                    50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
                    100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(successDiv);
            
            setTimeout(() => {
                successDiv.remove();
                style.remove();
            }, 1000);
        }
    </script>

    <!-- Additional HTML elements that might be missing -->
    <div style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
        <button class="btn btn-secondary" onclick="exportToCSV()" style="margin-right: 10px;" title="Export to CSV">
            📊 Export
        </button>
        <button class="btn btn-secondary" onclick="printTable()" title="Print Report">
            🖨️ Print
        </button>
    </div>

</body>
</html>