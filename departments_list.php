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
                // Add new department
                try {
                    $stmt = $pdo->prepare("INSERT INTO departments (department_name, description, location) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $_POST['department_name'],
                        $_POST['description'],
                        $_POST['location']
                    ]);
                    $message = "Department added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding department: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update department
                try {
                    $stmt = $pdo->prepare("UPDATE departments SET department_name=?, description=?, location=? WHERE department_id=?");
                    $stmt->execute([
                        $_POST['department_name'],
                        $_POST['description'],
                        $_POST['location'],
                        $_POST['department_id']
                    ]);
                    $message = "Department updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating department: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete department
                try {
                    // Check if department has employees first
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_roles WHERE department = ?");
                    $stmt->execute([$_POST['department_name']]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        $message = "Cannot delete department. There are " . $count . " job roles associated with this department.";
                        $messageType = "error";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id=?");
                        $stmt->execute([$_POST['department_id']]);
                        $message = "Department deleted successfully!";
                        $messageType = "success";
                    }
                } catch (PDOException $e) {
                    $message = "Error deleting department: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch departments with employee count
$stmt = $pdo->query("
    SELECT 
        d.*,
        COUNT(DISTINCT jr.job_role_id) as job_roles_count,
        COUNT(DISTINCT ep.employee_id) as employees_count
    FROM departments d
    LEFT JOIN job_roles jr ON d.department_name = jr.department
    LEFT JOIN employee_profiles ep ON jr.job_role_id = ep.job_role_id
    GROUP BY d.department_id
    ORDER BY d.department_name ASC
");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for department page */
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

        .department-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .department-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .department-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--azure-blue-dark);
            margin-bottom: 10px;
        }

        .department-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .department-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: var(--azure-blue-lighter);
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: #e9ecef;
            border-radius: 15px;
            font-size: 12px;
            color: #495057;
            margin-bottom: 15px;
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

        .view-toggle {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .view-btn {
            padding: 8px 12px;
            border: 2px solid var(--azure-blue);
            background: transparent;
            color: var(--azure-blue);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-btn.active {
            background: var(--azure-blue);
            color: white;
        }

        .card-view {
            display: none;
        }

        .card-view.active {
            display: block;
        }

        .table-view {
            display: block;
        }

        .table-view.active {
            display: block;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .department-stats {
                flex-direction: column;
                gap: 10px;
            }

            .view-toggle {
                margin-left: 0;
                justify-content: center;
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
                <h2 class="section-title">Department Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search departments by name or location...">
                        </div>
                        <div class="view-toggle">
                            <button class="view-btn active" onclick="switchView('table')" id="tableViewBtn">📋 Table</button>
                            <button class="view-btn" onclick="switchView('card')" id="cardViewBtn">📁 Cards</button>
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add New Department
                        </button>
                    </div>

                    <!-- Table View -->
                    <div id="tableView" class="table-view active">
                        <div class="table-container">
                            <table class="table" id="departmentTable">
                                <thead>
                                    <tr>
                                        <th>Department Name</th>
                                        <th>Description</th>
                                        <th>Location</th>
                                        <th>Job Roles</th>
                                        <th>Employees</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="departmentTableBody">
                                    <?php foreach ($departments as $department): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong style="color: var(--azure-blue-dark);"><?= htmlspecialchars($department['department_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="max-width: 300px;">
                                                <?= htmlspecialchars($department['description']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="location-badge">
                                                📍 <?= htmlspecialchars($department['location']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="stat-item">
                                                👔 <?= $department['job_roles_count'] ?> roles
                                            </span>
                                        </td>
                                        <td>
                                            <span class="stat-item">
                                                👥 <?= $department['employees_count'] ?> employees
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editDepartment(<?= $department['department_id'] ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteDepartment(<?= $department['department_id'] ?>, '<?= htmlspecialchars($department['department_name']) ?>')">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($departments)): ?>
                            <div class="no-results">
                                <i>🏢</i>
                                <h3>No departments found</h3>
                                <p>Start by adding your first department.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card View -->
                    <div id="cardView" class="card-view">
                        <div id="departmentCards">
                            <?php foreach ($departments as $department): ?>
                            <div class="department-card">
                                <div class="department-name"><?= htmlspecialchars($department['department_name']) ?></div>
                                <div class="location-badge">
                                    📍 <?= htmlspecialchars($department['location']) ?>
                                </div>
                                <div class="department-description">
                                    <?= htmlspecialchars($department['description']) ?>
                                </div>
                                <div class="department-stats">
                                    <div class="stat-item">
                                        👔 <?= $department['job_roles_count'] ?> Job Roles
                                    </div>
                                    <div class="stat-item">
                                        👥 <?= $department['employees_count'] ?> Employees
                                    </div>
                                </div>
                                <div>
                                    <button class="btn btn-warning btn-small" onclick="editDepartment(<?= $department['department_id'] ?>)">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-small" onclick="deleteDepartment(<?= $department['department_id'] ?>, '<?= htmlspecialchars($department['department_name']) ?>')">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty($departments)): ?>
                        <div class="no-results">
                            <i>🏢</i>
                            <h3>No departments found</h3>
                            <p>Start by adding your first department.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Department Modal -->
    <div id="departmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Department</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="departmentForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="department_id" name="department_id">

                    <div class="form-group">
                        <label for="department_name">Department Name *</label>
                        <input type="text" id="department_name" name="department_name" class="form-control" required placeholder="e.g., Municipal Department">
                    </div>

                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" class="form-control" required placeholder="Describe the department's role and responsibilities..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location" class="form-control" required placeholder="e.g., Floor 1 - City Hall">
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let departmentsData = <?= json_encode($departments) ?>;
        let currentView = 'table';

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            if (currentView === 'table') {
                const tableBody = document.getElementById('departmentTableBody');
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
            } else {
                const cards = document.querySelectorAll('.department-card');
                cards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
        });

        // View switching
        function switchView(view) {
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');
            const tableBtn = document.getElementById('tableViewBtn');
            const cardBtn = document.getElementById('cardViewBtn');

            if (view === 'table') {
                tableView.classList.add('active');
                cardView.classList.remove('active');
                tableBtn.classList.add('active');
                cardBtn.classList.remove('active');
                currentView = 'table';
            } else {
                tableView.classList.remove('active');
                cardView.classList.add('active');
                tableBtn.classList.remove('active');
                cardBtn.classList.add('active');
                currentView = 'card';
            }
        }

        // Modal functions
        function openModal(mode, departmentId = null) {
            const modal = document.getElementById('departmentModal');
            const form = document.getElementById('departmentForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Department';
                action.value = 'add';
                form.reset();
                document.getElementById('department_id').value = '';
            } else if (mode === 'edit' && departmentId) {
                title.textContent = 'Edit Department';
                action.value = 'update';
                document.getElementById('department_id').value = departmentId;
                populateEditForm(departmentId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('departmentModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(departmentId) {
            const department = departmentsData.find(dept => dept.department_id == departmentId);
            if (department) {
                document.getElementById('department_name').value = department.department_name || '';
                document.getElementById('description').value = department.description || '';
                document.getElementById('location').value = department.location || '';
            }
        }

        function editDepartment(departmentId) {
            openModal('edit', departmentId);
        }

        function deleteDepartment(departmentId, departmentName) {
            if (confirm(`Are you sure you want to delete "${departmentName}"? This action cannot be undone and may affect related job roles.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="department_id" value="${departmentId}">
                    <input type="hidden" name="department_name" value="${departmentName}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('departmentModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('departmentForm').addEventListener('submit', function(e) {
            const departmentName = document.getElementById('department_name').value.trim();
            const description = document.getElementById('description').value.trim();
            const location = document.getElementById('location').value.trim();

            if (departmentName.length < 3) {
                e.preventDefault();
                alert('Department name must be at least 3 characters long');
                return;
            }

            if (description.length < 10) {
                e.preventDefault();
                alert('Description must be at least 10 characters long');
                return;
            }

            if (location.length < 5) {
                e.preventDefault();
                alert('Location must be at least 5 characters long');
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
            const tableRows = document.querySelectorAll('#departmentTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Add hover effects to cards
            const cards = document.querySelectorAll('.department-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>