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
                try {
                    $pdo->beginTransaction();
                    
                    // Check if creating new person or using existing
                    if ($_POST['creation_type'] === 'new_person') {
                        // Create new personal information first
                        $personalStmt = $pdo->prepare("INSERT INTO personal_information (first_name, last_name, date_of_birth, gender, marital_status, nationality, tax_id, social_security_number, phone_number, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $personalStmt->execute([
                            $_POST['first_name'],
                            $_POST['last_name'],
                            $_POST['date_of_birth'],
                            $_POST['gender'],
                            $_POST['marital_status'],
                            $_POST['nationality'],
                            $_POST['tax_id'],
                            $_POST['social_security_number'],
                            $_POST['phone_number'],
                            $_POST['emergency_contact_name'],
                            $_POST['emergency_contact_relationship'],
                            $_POST['emergency_contact_phone']
                        ]);
                        
                        $personalInfoId = $pdo->lastInsertId();
                        
                        // Create employee profile if job information provided
                        $employeeId = null;
                        if (!empty($_POST['job_role_id'])) {
                            $employeeStmt = $pdo->prepare("INSERT INTO employee_profiles (personal_info_id, job_role_id, employee_number, hire_date, employment_status, current_salary, work_email, work_phone, location, remote_work) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $employeeStmt->execute([
                                $personalInfoId,
                                $_POST['job_role_id'],
                                $_POST['employee_number'],
                                $_POST['hire_date'],
                                $_POST['employment_status'],
                                $_POST['current_salary'],
                                $_POST['work_email'] ?: $_POST['email'], // Use personal email if work email not provided
                                $_POST['work_phone'],
                                $_POST['location'],
                                isset($_POST['remote_work']) ? 1 : 0
                            ]);
                            $employeeId = $pdo->lastInsertId();
                        }
                        
                        // Create user account
                        $userStmt = $pdo->prepare("INSERT INTO users (username, password, email, role, employee_id) VALUES (?, ?, ?, ?, ?)");
                        $userStmt->execute([
                            $_POST['username'],
                            password_hash($_POST['password'], PASSWORD_DEFAULT),
                            $_POST['email'],
                            $_POST['role'],
                            $employeeId
                        ]);
                        
                    } else {
                        // Use existing employee
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, employee_id) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_POST['username'],
                            password_hash($_POST['password'], PASSWORD_DEFAULT),
                            $_POST['email'],
                            $_POST['role'],
                            $_POST['employee_id'] ?: NULL
                        ]);
                    }
                    
                    $pdo->commit();
                    $message = "User created successfully with all related information!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $pdo->rollback();
                    $message = "Error creating user: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                try {
                    $updateSql = "UPDATE users SET username=?, email=?, role=?, employee_id=?, is_active=?";
                    $params = [
                        $_POST['username'],
                        $_POST['email'],
                        $_POST['role'],
                        $_POST['employee_id'] ?: NULL,
                        isset($_POST['is_active']) ? 1 : 0
                    ];
                    
                    // Only update password if provided
                    if (!empty($_POST['password'])) {
                        $updateSql .= ", password=?";
                        $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    }
                    
                    $updateSql .= " WHERE user_id=?";
                    $params[] = $_POST['user_id'];
                    
                    $stmt = $pdo->prepare($updateSql);
                    $stmt->execute($params);
                    $message = "User updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating user: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id=?");
                    $stmt->execute([$_POST['user_id']]);
                    $message = "User deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting user: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch users with additional information
$stmt = $pdo->query("
    SELECT 
        u.*,
        ep.employee_number,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        pi.phone_number,
        pi.first_name,
        pi.last_name,
        DATE(u.created_at) as created_date
    FROM users u
    LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY u.user_id DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown (users without accounts)
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        ep.employee_number,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN users u ON ep.employee_id = u.employee_id
    WHERE u.employee_id IS NULL
    ORDER BY pi.first_name
");
$availableEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch job roles for dropdown
$stmt = $pdo->query("SELECT job_role_id, title, department FROM job_roles ORDER BY title");
$jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate next employee number
$stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(employee_number, 4) AS UNSIGNED)) as max_num FROM employee_profiles WHERE employee_number LIKE 'EMP%'");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$nextEmployeeNumber = 'EMP' . str_pad(($result['max_num'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for user management page */
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
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 95%;
            max-width: 900px;
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

        .creation-type-selector {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        .creation-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .creation-option:hover {
            border-color: var(--azure-blue-light);
            background: var(--azure-blue-pale);
        }

        .creation-option.selected {
            border-color: var(--azure-blue);
            background: var(--azure-blue-lighter);
        }

        .creation-option input[type="radio"] {
            margin: 0;
        }

        .section-divider {
            border-top: 2px solid #e0e0e0;
            margin: 30px 0 20px 0;
            padding-top: 20px;
        }

        .section-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--azure-blue-dark);
            margin-bottom: 20px;
        }

        .form-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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

            .modal-content {
                width: 98%;
                margin: 1% auto;
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
                <h2 class="section-title">User Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search users by username, email, or role...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add New User
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="userTable">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Employee</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody">
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($user['user_id']) ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong><br>
                                            <?php if ($user['phone_number']): ?>
                                            <small style="color: #666;">📞 <?= htmlspecialchars($user['phone_number']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($user['role']) === 'employee' ? 'active' : 'inactive' ?>"
                                              style="background: <?= $user['role'] === 'admin' ? '#dc3545' : ($user['role'] === 'hr' ? '#007bff' : '#28a745') ?>; color: white;">
                                            <?= strtoupper(htmlspecialchars($user['role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['full_name']): ?>
                                            <div>
                                                <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
                                                <small style="color: #666;">#<?= htmlspecialchars($user['employee_number']) ?></small>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">No employee linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $user['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $user['is_active'] ? 'ACTIVE' : 'INACTIVE' ?>
                                        </span>
                                    </td>
                                    <td><?= $user['created_date'] ? date('M d, Y', strtotime($user['created_date'])) : 'Aug 30, 2025' ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editUser(<?= $user['user_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-danger btn-small" onclick="deleteUser(<?= $user['user_id'] ?>)">
                                            🗑️ Delete
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($users)): ?>
                        <div class="no-results">
                            <i>👥</i>
                            <h3>No users found</h3>
                            <p>Start by adding your first user.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New User</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="userForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="user_id" name="user_id">

                    <!-- Creation Type Selector (only for new users) -->
                    <div id="creationTypeSection" class="creation-type-selector">
                        <h3 style="margin-bottom: 20px; color: var(--azure-blue-dark);">How would you like to create this user?</h3>
                        
                        <div class="creation-option" onclick="selectCreationType('existing')">
                            <input type="radio" id="existing_employee" name="creation_type" value="existing" style="pointer-events: none;">
                            <div>
                                <strong>Link to Existing Employee</strong><br>
                                <small>Connect user account to an employee who already has a profile in the system</small>
                            </div>
                        </div>
                        
                        <div class="creation-option" onclick="selectCreationType('new_person')">
                            <input type="radio" id="new_person" name="creation_type" value="new_person" style="pointer-events: none;">
                            <div>
                                <strong>Create Complete New Person</strong><br>
                                <small>Create personal information, employee profile, and user account all together</small>
                            </div>
                        </div>
                    </div>

                    <!-- Basic User Information -->
                    <div class="section-header">👤 User Account Information</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control">
                                <small class="text-muted" id="passwordHelp" style="display: none;">Leave blank to keep current password</small>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="role">Role</label>
                                <select id="role" name="role" class="form-control" required>
                                    <option value="">Select role...</option>
                                    <option value="admin">Admin</option>
                                    <option value="hr">HR</option>
                                    <option value="employee">Employee</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Employee Section -->
                    <div id="existingEmployeeSection" class="form-section">
                        <div class="section-divider">
                            <div class="section-header">🔗 Link to Existing Employee</div>
                        </div>
                        <div class="form-group">
                            <label for="employee_id">Select Employee</label>
                            <select id="employee_id" name="employee_id" class="form-control">
                                <option value="">Select employee...</option>
                                <?php foreach ($availableEmployees as $employee): ?>
                                <option value="<?= $employee['employee_id'] ?>">
                                    <?= htmlspecialchars($employee['full_name']) ?> - #<?= htmlspecialchars($employee['employee_number']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- New Person Section -->
                    <div id="newPersonSection" class="form-section">
                        <!-- Personal Information -->
                        <div class="section-divider">
                            <div class="section-header">📋 Personal Information</div>
                        </div>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender" class="form-control">
                                        <option value="">Select gender...</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="marital_status">Marital Status</label>
                                    <select id="marital_status" name="marital_status" class="form-control">
                                        <option value="">Select status...</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="nationality">Nationality</label>
                                    <input type="text" id="nationality" name="nationality" class="form-control" value="Filipino">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="tax_id">Tax ID</label>
                                    <input type="text" id="tax_id" name="tax_id" class="form-control" placeholder="123-45-6789">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="social_security_number">Social Security Number</label>
                                    <input type="text" id="social_security_number" name="social_security_number" class="form-control" placeholder="123456789">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="phone_number">Phone Number</label>
                                    <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="555-1234">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="emergency_contact_name">Emergency Contact Name</label>
                                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="emergency_contact_relationship">Emergency Contact Relationship</label>
                                    <select id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control">
                                        <option value="">Select relationship...</option>
                                        <option value="Spouse">Spouse</option>
                                        <option value="Father">Father</option>
                                        <option value="Mother">Mother</option>
                                        <option value="Brother">Brother</option>
                                        <option value="Sister">Sister</option>
                                        <option value="Child">Child</option>
                                        <option value="Friend">Friend</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                    <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" placeholder="555-5678">
                                </div>
                            </div>
                        </div>

                        <!-- Employee Information -->
                        <div class="section-divider">
                            <div class="section-header">💼 Employee Information (Optional)</div>
                            <p style="color: #666; font-size: 14px;">Fill out this section if the person will be an employee. Leave blank to create just a user account.</p>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="job_role_id">Job Role</label>
                                    <select id="job_role_id" name="job_role_id" class="form-control">
                                        <option value="">Select job role...</option>
                                        <?php foreach ($jobRoles as $role): ?>
                                        <option value="<?= $role['job_role_id'] ?>"><?= htmlspecialchars($role['title']) ?> (<?= htmlspecialchars($role['department']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="employee_number">Employee Number</label>
                                    <input type="text" id="employee_number" name="employee_number" class="form-control" value="<?= $nextEmployeeNumber ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="hire_date">Hire Date</label>
                                    <input type="date" id="hire_date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="employment_status">Employment Status</label>
                                    <select id="employment_status" name="employment_status" class="form-control">
                                        <option value="">Select status...</option>
                                        <option value="Full-time" selected>Full-time</option>
                                        <option value="Part-time">Part-time</option>
                                        <option value="Contract">Contract</option>
                                        <option value="Intern">Intern</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="current_salary">Current Salary (₱)</label>
                                    <input type="number" id="current_salary" name="current_salary" class="form-control" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="work_email">Work Email (optional)</label>
                                    <input type="email" id="work_email" name="work_email" class="form-control" placeholder="Will use personal email if not provided">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="work_phone">Work Phone</label>
                                    <input type="tel" id="work_phone" name="work_phone" class="form-control" placeholder="555-1234">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="location">Location</label>
                                    <input type="text" id="location" name="location" class="form-control" placeholder="Office location">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="remote_work" name="remote_work">
                                <label for="remote_work">Remote Work Enabled</label>
                            </div>
                        </div>
                    </div>

                    <!-- Account Status -->
                    <div class="section-divider">
                        <div class="section-header">⚙️ Account Settings</div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" checked>
                            <label for="is_active">Account Active</label>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let usersData = <?= json_encode($users) ?>;
        let availableEmployees = <?= json_encode($availableEmployees) ?>;
        let nextEmployeeNumber = '<?= $nextEmployeeNumber ?>';

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('userTableBody');
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

        // Creation type selection
        function selectCreationType(type) {
            // Update radio buttons
            document.getElementById('existing_employee').checked = (type === 'existing');
            document.getElementById('new_person').checked = (type === 'new_person');
            
            // Update visual selection
            document.querySelectorAll('.creation-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            if (type === 'existing') {
                document.querySelector('.creation-option:first-child').classList.add('selected');
                document.getElementById('existingEmployeeSection').classList.add('active');
                document.getElementById('newPersonSection').classList.remove('active');
                
                // Make employee selection required
                document.getElementById('employee_id').required = true;
                setNewPersonFieldsRequired(false);
                
            } else if (type === 'new_person') {
                document.querySelector('.creation-option:last-child').classList.add('selected');
                document.getElementById('existingEmployeeSection').classList.remove('active');
                document.getElementById('newPersonSection').classList.add('active');
                
                // Make new person fields required
                document.getElementById('employee_id').required = false;
                setNewPersonFieldsRequired(true);
            }
        }

        function setNewPersonFieldsRequired(required) {
            const requiredFields = ['first_name', 'last_name', 'date_of_birth', 'gender', 'marital_status', 'nationality', 'tax_id', 'social_security_number', 'phone_number', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone'];
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.required = required;
                }
            });
        }

        // Modal functions
        function openModal(mode, userId = null) {
            const modal = document.getElementById('userModal');
            const form = document.getElementById('userForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');
            const passwordField = document.getElementById('password');
            const passwordHelp = document.getElementById('passwordHelp');
            const employeeSelect = document.getElementById('employee_id');
            const creationTypeSection = document.getElementById('creationTypeSection');

            if (mode === 'add') {
                title.textContent = 'Add New User';
                action.value = 'add';
                form.reset();
                document.getElementById('user_id').value = '';
                document.getElementById('is_active').checked = true;
                passwordField.required = true;
                passwordHelp.style.display = 'none';
                creationTypeSection.style.display = 'block';
                
                // Reset sections
                document.getElementById('existingEmployeeSection').classList.remove('active');
                document.getElementById('newPersonSection').classList.remove('active');
                document.querySelectorAll('.creation-option').forEach(option => {
                    option.classList.remove('selected');
                });
                
                // Reset employee dropdown
                employeeSelect.innerHTML = '<option value="">Select employee...</option>';
                availableEmployees.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.employee_id;
                    option.textContent = `${employee.full_name} - #${employee.employee_number}`;
                    employeeSelect.appendChild(option);
                });

                // Set default values for new person
                document.getElementById('nationality').value = 'Filipino';
                document.getElementById('employee_number').value = nextEmployeeNumber;
                document.getElementById('hire_date').value = new Date().toISOString().split('T')[0];
                document.getElementById('employment_status').value = 'Full-time';
                
            } else if (mode === 'edit' && userId) {
                title.textContent = 'Edit User';
                action.value = 'update';
                document.getElementById('user_id').value = userId;
                passwordField.required = false;
                passwordHelp.style.display = 'block';
                creationTypeSection.style.display = 'none';
                
                // Show existing employee section for editing
                document.getElementById('existingEmployeeSection').classList.add('active');
                document.getElementById('newPersonSection').classList.remove('active');
                
                populateEditForm(userId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('userModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(userId) {
            const user = usersData.find(u => u.user_id == userId);
            if (user) {
                document.getElementById('username').value = user.username || '';
                document.getElementById('password').value = '';
                document.getElementById('email').value = user.email || '';
                document.getElementById('role').value = user.role || '';
                document.getElementById('is_active').checked = user.is_active == 1;
                
                // Handle employee dropdown for editing
                const employeeSelect = document.getElementById('employee_id');
                employeeSelect.innerHTML = '<option value="">Select employee...</option>';
                
                // Add current employee if linked
                if (user.employee_id && user.full_name) {
                    const currentOption = document.createElement('option');
                    currentOption.value = user.employee_id;
                    currentOption.textContent = `${user.full_name} - #${user.employee_number}`;
                    currentOption.selected = true;
                    employeeSelect.appendChild(currentOption);
                }
                
                // Add available employees
                availableEmployees.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.employee_id;
                    option.textContent = `${employee.full_name} - #${employee.employee_number}`;
                    employeeSelect.appendChild(option);
                });
            }
        }

        function editUser(userId) {
            openModal('edit', userId);
        }

        function deleteUser(userId) {
            const user = usersData.find(u => u.user_id == userId);
            const userName = user ? user.username : 'this user';
            
            if (confirm(`Are you sure you want to delete ${userName}? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('userModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('userForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const action = document.getElementById('action').value;
            const creationType = document.querySelector('input[name="creation_type"]:checked');
            
            if (username.length < 3) {
                e.preventDefault();
                alert('Username must be at least 3 characters long');
                return;
            }
            
            if (action === 'add' && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }

            // Validate creation type selection for new users
            if (action === 'add' && !creationType) {
                e.preventDefault();
                alert('Please select how you want to create this user');
                return;
            }

            // Validate new person fields if creating new person
            if (action === 'add' && creationType && creationType.value === 'new_person') {
                const requiredPersonFields = {
                    'first_name': 'First Name',
                    'last_name': 'Last Name',
                    'date_of_birth': 'Date of Birth',
                    'gender': 'Gender',
                    'marital_status': 'Marital Status',
                    'tax_id': 'Tax ID',
                    'social_security_number': 'Social Security Number',
                    'phone_number': 'Phone Number',
                    'emergency_contact_name': 'Emergency Contact Name',
                    'emergency_contact_relationship': 'Emergency Contact Relationship',
                    'emergency_contact_phone': 'Emergency Contact Phone'
                };

                for (const [fieldId, fieldName] of Object.entries(requiredPersonFields)) {
                    const field = document.getElementById(fieldId);
                    if (!field.value.trim()) {
                        e.preventDefault();
                        alert(`${fieldName} is required when creating a new person`);
                        field.focus();
                        return;
                    }
                }

                // Age validation
                const birthDate = new Date(document.getElementById('date_of_birth').value);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }

                if (age < 16) {
                    e.preventDefault();
                    alert('Person must be at least 16 years old');
                    return;
                }

                // Salary validation if job role is selected
                const jobRole = document.getElementById('job_role_id').value;
                const salary = document.getElementById('current_salary').value;
                
                if (jobRole && (!salary || parseFloat(salary) <= 0)) {
                    e.preventDefault();
                    alert('Current salary is required and must be greater than 0 when job role is selected');
                    return;
                }
            }
        });

        // Auto-format Tax ID
        document.getElementById('tax_id').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 3) {
                value = value.substring(0, 3) + '-' + value.substring(3);
            }
            if (value.length >= 6) {
                value = value.substring(0, 6) + '-' + value.substring(6, 10);
            }
            e.target.value = value;
        });

        // Auto-format SSN
        document.getElementById('social_security_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value.substring(0, 9);
        });

        // Auto-populate work email with personal email if empty
        document.getElementById('email').addEventListener('blur', function() {
            const workEmail = document.getElementById('work_email');
            if (!workEmail.value && this.value) {
                workEmail.value = this.value;
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('#userTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Set date constraints
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_of_birth').setAttribute('max', today);
            document.getElementById('hire_date').setAttribute('max', today);

            const minDate = new Date();
            minDate.setFullYear(minDate.getFullYear() - 120);
            document.getElementById('date_of_birth').setAttribute('min', minDate.toISOString().split('T')[0]);
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>