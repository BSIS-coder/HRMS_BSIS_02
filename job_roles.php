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

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new job role
                try {
                    $stmt = $pdo->prepare("INSERT INTO job_roles (title, description, department, min_salary, max_salary) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['department'],
                        $_POST['min_salary'],
                        $_POST['max_salary']
                    ]);
                    $message = "Job role added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding job role: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update job role
                try {
                    $stmt = $pdo->prepare("UPDATE job_roles SET title=?, description=?, department=?, min_salary=?, max_salary=? WHERE job_role_id=?");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['department'],
                        $_POST['min_salary'],
                        $_POST['max_salary'],
                        $_POST['job_role_id']
                    ]);
                    $message = "Job role updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating job role: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete job role
                try {
                    // First check if job role is being used by any employee
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE job_role_id = ?");
                    $stmt->execute([$_POST['job_role_id']]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        $message = "Cannot delete job role. It is currently assigned to $count employee(s).";
                        $messageType = "error";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM job_roles WHERE job_role_id=?");
                        $stmt->execute([$_POST['job_role_id']]);
                        $message = "Job role deleted successfully!";
                        $messageType = "success";
                    }
                } catch (PDOException $e) {
                    $message = "Error deleting job role: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch all job roles with employee count
$stmt = $pdo->query("
    SELECT 
        jr.*,
        COUNT(ep.employee_id) as employee_count
    FROM job_roles jr
    LEFT JOIN employee_profiles ep ON jr.job_role_id = ep.job_role_id
    GROUP BY jr.job_role_id
    ORDER BY jr.department, jr.title
");
$jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique departments for dropdown
$stmt = $pdo->query("SELECT DISTINCT department FROM job_roles ORDER BY department");
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Role Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for job roles page */
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

        .department-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .dept-prepress {
            background: #e3f2fd;
            color: #1976d2;
        }

        .dept-printing {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .dept-postpress {
            background: #e8f5e8;
            color: #388e3c;
        }

        .salary-range {
            font-size: 14px;
            color: #666;
        }

        .employee-count {
            background: #e9ecef;
            color: #495057;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
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
            max-width: 700px;
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

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
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
                <h2 class="section-title">Job Role Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search job roles by title, department, or description...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add New Job Role
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="jobRoleTable">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Department</th>
                                    <th>Description</th>
                                    <th>Salary Range</th>
                                    <th>Employees</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="jobRoleTableBody">
                                <?php foreach ($jobRoles as $role): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($role['title']) ?></strong><br>
                                            <small style="color: #666;">ID: <?= $role['job_role_id'] ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="department-badge <?= getDepartmentClass($role['department']) ?>">
                                            <?= htmlspecialchars($role['department']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="max-width: 300px;">
                                            <?= htmlspecialchars(substr($role['description'], 0, 100)) ?>
                                            <?php if (strlen($role['description']) > 100): ?>...<?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>₱<?= number_format($role['min_salary'], 0) ?> - ₱<?= number_format($role['max_salary'], 0) ?></strong><br>
                                            <span class="salary-range">Range: ₱<?= number_format($role['max_salary'] - $role['min_salary'], 0) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="employee-count">
                                            <?= $role['employee_count'] ?> <?= $role['employee_count'] == 1 ? 'employee' : 'employees' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editJobRole(<?= $role['job_role_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteJobRole(<?= $role['job_role_id'] ?>, '<?= htmlspecialchars($role['title']) ?>', <?= $role['employee_count'] ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($jobRoles)): ?>
                        <div class="no-results">
                            <i>💼</i>
                            <h3>No job roles found</h3>
                            <p>Start by adding your first job role.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Job Role Modal -->
    <div id="jobRoleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Job Role</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="jobRoleForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="job_role_id" name="job_role_id">

                    <div class="form-group">
                        <label for="title">Job Title *</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="department">Department *</label>
                        <select id="department" name="department" class="form-control" required>
                            <option value="">Select department...</option>
                            <option value="Municipal Treasurer's Office">Municipal Treasurer's Office</option>
                            <option value="Municipal Budget Office">Municipal Budget Office</option>
                            <option value="Municipal Accountant's Office">Municipal Accountant's Office</option>
                            <option value="Municipal Planning & Development Office">Municipal Planning & Development Office</option>
                            <option value="Municipal Engineer's Office">Municipal Engineer's Office</option>
                            <option value="Municipal Civil Registrar's Office">Municipal Civil Registrar's Office</option>
                            <option value="Municipal Health Office">Municipal Health Office</option>
                            <option value="Municipal Social Welfare & Development Office">Municipal Social Welfare & Development Office</option>
                            <option value="Municipal Agriculture Office">Municipal Agriculture Office</option>
                            <option value="Municipal Assessor's Office">Municipal Assessor's Office</option>
                            <option value="Municipal Human Resource & Administrative Office">Municipal Human Resource & Administrative Office</option>
                            <option value="Municipal Disaster Risk Reduction & Management Office">Municipal Disaster Risk Reduction & Management Office</option>
                            <option value="General Services Office">General Services Office</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Job Description</label>
                        <textarea id="description" name="description" class="form-control" placeholder="Describe the job responsibilities and requirements..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="min_salary">Minimum Salary (₱) *</label>
                                <input type="number" id="min_salary" name="min_salary" class="form-control" step="500" min="0" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="max_salary">Maximum Salary (₱) *</label>
                                <input type="number" id="max_salary" name="max_salary" class="form-control" step="500" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Job Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php
function getDepartmentClass($department) {
    switch ($department) {
        case 'Municipal Treasurer\'s Office':
            return 'dept-treasury';
        case 'Municipal Budget Office':
            return 'dept-budget';
        case 'Municipal Accountant\'s Office':
            return 'dept-accounting';
        case 'Municipal Planning & Development Office':
            return 'dept-planning';
        case 'Municipal Engineer\'s Office':
            return 'dept-engineering';
        case 'Municipal Civil Registrar\'s Office':
            return 'dept-civil-registry';
        case 'Municipal Health Office':
            return 'dept-health';
        case 'Municipal Social Welfare & Development Office':
            return 'dept-social-welfare';
        case 'Municipal Agriculture Office':
            return 'dept-agriculture';
        case 'Municipal Assessor\'s Office':
            return 'dept-assessor';
        case 'Municipal Human Resource & Administrative Office':
            return 'dept-hr';
        case 'Municipal Disaster Risk Reduction & Management Office':
            return 'dept-disaster-mgmt';
        case 'General Services Office':
            return 'dept-general-services';
        default:
            return 'dept-default';
    }
}
?>

    <script>
        // Global variables
        let jobRolesData = <?= json_encode($jobRoles) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('jobRoleTableBody');
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
        function openModal(mode, jobRoleId = null) {
            const modal = document.getElementById('jobRoleModal');
            const form = document.getElementById('jobRoleForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Job Role';
                action.value = 'add';
                form.reset();
                document.getElementById('job_role_id').value = '';
            } else if (mode === 'edit' && jobRoleId) {
                title.textContent = 'Edit Job Role';
                action.value = 'update';
                document.getElementById('job_role_id').value = jobRoleId;
                populateEditForm(jobRoleId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('jobRoleModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(jobRoleId) {
            const jobRole = jobRolesData.find(role => role.job_role_id == jobRoleId);
            if (jobRole) {
                document.getElementById('title').value = jobRole.title || '';
                document.getElementById('department').value = jobRole.department || '';
                document.getElementById('description').value = jobRole.description || '';
                document.getElementById('min_salary').value = jobRole.min_salary || '';
                document.getElementById('max_salary').value = jobRole.max_salary || '';
            }
        }

        function editJobRole(jobRoleId) {
            openModal('edit', jobRoleId);
        }

        function deleteJobRole(jobRoleId, title, employeeCount) {
            if (employeeCount > 0) {
                alert(`Cannot delete "${title}" because it is currently assigned to ${employeeCount} employee(s).`);
                return;
            }

            if (confirm(`Are you sure you want to delete the job role "${title}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="job_role_id" value="${jobRoleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('jobRoleModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('jobRoleForm').addEventListener('submit', function(e) {
            const minSalary = parseFloat(document.getElementById('min_salary').value);
            const maxSalary = parseFloat(document.getElementById('max_salary').value);

            if (minSalary <= 0 || maxSalary <= 0) {
                e.preventDefault();
                alert('Salary values must be greater than 0');
                return;
            }

            if (minSalary >= maxSalary) {
                e.preventDefault();
                alert('Maximum salary must be greater than minimum salary');
                return;
            }

            const title = document.getElementById('title').value.trim();
            if (title.length < 2) {
                e.preventDefault();
                alert('Job title must be at least 2 characters long');
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

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#jobRoleTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Real-time salary range calculation
            const minSalaryInput = document.getElementById('min_salary');
            const maxSalaryInput = document.getElementById('max_salary');

            function updateSalaryValidation() {
                const minVal = parseFloat(minSalaryInput.value) || 0;
                const maxVal = parseFloat(maxSalaryInput.value) || 0;

                if (minVal > 0 && maxVal > 0) {
                    if (minVal >= maxVal) {
                        maxSalaryInput.setCustomValidity('Maximum salary must be greater than minimum salary');
                    } else {
                        maxSalaryInput.setCustomValidity('');
                    }
                }
            }

            minSalaryInput.addEventListener('input', updateSalaryValidation);
            maxSalaryInput.addEventListener('input', updateSalaryValidation);
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>