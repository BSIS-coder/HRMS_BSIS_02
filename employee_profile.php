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
                // Add new employee
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_profiles (personal_info_id, job_role_id, employee_number, hire_date, employment_status, current_salary, work_email, work_phone, location, remote_work) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['job_role_id'],
                        $_POST['employee_number'],
                        $_POST['hire_date'],
                        $_POST['employment_status'],
                        $_POST['current_salary'],
                        $_POST['work_email'],
                        $_POST['work_phone'],
                        $_POST['location'],
                        isset($_POST['remote_work']) ? 1 : 0
                    ]);
                    $message = "Employee profile added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update employee
                try {
                    $stmt = $pdo->prepare("UPDATE employee_profiles SET personal_info_id=?, job_role_id=?, employee_number=?, hire_date=?, employment_status=?, current_salary=?, work_email=?, work_phone=?, location=?, remote_work=? WHERE employee_id=?");
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['job_role_id'],
                        $_POST['employee_number'],
                        $_POST['hire_date'],
                        $_POST['employment_status'],
                        $_POST['current_salary'],
                        $_POST['work_email'],
                        $_POST['work_phone'],
                        $_POST['location'],
                        isset($_POST['remote_work']) ? 1 : 0,
                        $_POST['employee_id']
                    ]);
                    $message = "Employee profile updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete employee
                try {
                    $stmt = $pdo->prepare("DELETE FROM employee_profiles WHERE employee_id=?");
                    $stmt->execute([$_POST['employee_id']]);
                    $message = "Employee profile deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting employee: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch employees with related data
$stmt = $pdo->query("
    SELECT 
        ep.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        pi.first_name,
        pi.last_name,
        pi.phone_number,
        jr.title as job_title,
        jr.department
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY ep.employee_id DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch personal information for dropdown
$stmt = $pdo->query("SELECT personal_info_id, CONCAT(first_name, ' ', last_name) as full_name FROM personal_information ORDER BY first_name");
$personalInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch job roles for dropdown
$stmt = $pdo->query("SELECT job_role_id, title, department FROM job_roles ORDER BY title");
$jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for employee profile page */
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

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
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

        .loading {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--azure-blue);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <h2 class="section-title">Employee Profile Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="searchInput" placeholder="Search employees by name, email, or employee number...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add')">
                                ➕ Add New Employee
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="employeeTable">
                                <thead>
                                    <tr>
                                        <th>Employee #</th>
                                        <th>Name</th>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Email</th>
                                        <th>Salary</th>
                                        <th>Status</th>
                                        <th>Hire Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="employeeTableBody">
                                    <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($employee['employee_number']) ?></strong></td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($employee['full_name']) ?></strong><br>
                                                <small style="color: #666;">📞 <?= htmlspecialchars($employee['phone_number']) ?></small>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($employee['job_title']) ?></td>
                                        <td><?= htmlspecialchars($employee['department']) ?></td>
                                        <td><?= htmlspecialchars($employee['work_email']) ?></td>
                                        <td><strong>₱<?= number_format($employee['current_salary'], 2) ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($employee['employment_status']) === 'full-time' ? 'active' : 'inactive' ?>">
                                                <?= htmlspecialchars($employee['employment_status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($employee['hire_date'])) ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editEmployee(<?= $employee['employee_id'] ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteEmployee(<?= $employee['employee_id'] ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($employees)): ?>
                            <div class="no-results">
                                <i>👥</i>
                                <h3>No employees found</h3>
                                <p>Start by adding your first employee profile.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Employee</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="employeeForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="employee_id" name="employee_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="personal_info_id">Personal Information</label>
                                <select id="personal_info_id" name="personal_info_id" class="form-control" required>
                                    <option value="">Select person...</option>
                                    <?php foreach ($personalInfo as $person): ?>
                                    <option value="<?= $person['personal_info_id'] ?>"><?= htmlspecialchars($person['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="job_role_id">Job Role</label>
                                <select id="job_role_id" name="job_role_id" class="form-control" required>
                                    <option value="">Select job role...</option>
                                    <?php foreach ($jobRoles as $role): ?>
                                    <option value="<?= $role['job_role_id'] ?>"><?= htmlspecialchars($role['title']) ?> (<?= htmlspecialchars($role['department']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_number">Employee Number</label>
                                <input type="text" id="employee_number" name="employee_number" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="hire_date">Hire Date</label>
                                <input type="date" id="hire_date" name="hire_date" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employment_status">Employment Status</label>
                                <select id="employment_status" name="employment_status" class="form-control" required>
                                    <option value="">Select status...</option>
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Intern">Intern</option>
                                    <option value="Terminated">Terminated</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="current_salary">Current Salary (₱)</label>
                                <input type="number" id="current_salary" name="current_salary" class="form-control" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="work_email">Work Email</label>
                                <input type="email" id="work_email" name="work_email" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="work_phone">Work Phone</label>
                                <input type="tel" id="work_phone" name="work_phone" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control">
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="remote_work" name="remote_work">
                            <label for="remote_work">Remote Work Enabled</label>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let employeesData = <?= json_encode($employees) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('employeeTableBody');
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
        function openModal(mode, employeeId = null) {
            const modal = document.getElementById('employeeModal');
            const form = document.getElementById('employeeForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Employee';
                action.value = 'add';
                form.reset();
                document.getElementById('employee_id').value = '';
            } else if (mode === 'edit' && employeeId) {
                title.textContent = 'Edit Employee';
                action.value = 'update';
                document.getElementById('employee_id').value = employeeId;
                populateEditForm(employeeId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('employeeModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(employeeId) {
            // This would typically fetch data via AJAX
            // For now, we'll use the existing data
            const employee = employeesData.find(emp => emp.employee_id == employeeId);
            if (employee) {
                document.getElementById('personal_info_id').value = employee.personal_info_id || '';
                document.getElementById('job_role_id').value = employee.job_role_id || '';
                document.getElementById('employee_number').value = employee.employee_number || '';
                document.getElementById('hire_date').value = employee.hire_date || '';
                document.getElementById('employment_status').value = employee.employment_status || '';
                document.getElementById('current_salary').value = employee.current_salary || '';
                document.getElementById('work_email').value = employee.work_email || '';
                document.getElementById('work_phone').value = employee.work_phone || '';
                document.getElementById('location').value = employee.location || '';
                document.getElementById('remote_work').checked = employee.remote_work == 1;
            }
        }

        function editEmployee(employeeId) {
            openModal('edit', employeeId);
        }

        function deleteEmployee(employeeId) {
            if (confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="employee_id" value="${employeeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('employeeModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('employeeForm').addEventListener('submit', function(e) {
            const salary = document.getElementById('current_salary').value;
            if (salary <= 0) {
                e.preventDefault();
                alert('Salary must be greater than 0');
                return;
            }

            const email = document.getElementById('work_email').value;
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

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
            const tableRows = document.querySelectorAll('#employeeTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });


        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>