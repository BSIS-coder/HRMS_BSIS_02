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
                // Add new personal information
                try {
                    // Check for duplicate Tax ID or SSN
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM personal_information WHERE tax_id = ? OR social_security_number = ?");
                    $checkStmt->execute([$_POST['tax_id'], $_POST['social_security_number']]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        $message = "Error: Tax ID or Social Security Number already exists!";
                        $messageType = "error";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO personal_information (first_name, last_name, date_of_birth, gender, marital_status, nationality, tax_id, social_security_number, phone_number, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
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
                        $message = "Personal information added successfully!";
                        $messageType = "success";
                    }
                } catch (PDOException $e) {
                    $message = "Error adding personal information: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update personal information
                try {
                    // Check for duplicate Tax ID or SSN (excluding current record)
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM personal_information WHERE (tax_id = ? OR social_security_number = ?) AND personal_info_id != ?");
                    $checkStmt->execute([$_POST['tax_id'], $_POST['social_security_number'], $_POST['personal_info_id']]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        $message = "Error: Tax ID or Social Security Number already exists!";
                        $messageType = "error";
                    } else {
                        $stmt = $pdo->prepare("UPDATE personal_information SET first_name=?, last_name=?, date_of_birth=?, gender=?, marital_status=?, nationality=?, tax_id=?, social_security_number=?, phone_number=?, emergency_contact_name=?, emergency_contact_relationship=?, emergency_contact_phone=? WHERE personal_info_id=?");
                        $stmt->execute([
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
                            $_POST['emergency_contact_phone'],
                            $_POST['personal_info_id']
                        ]);
                        $message = "Personal information updated successfully!";
                        $messageType = "success";
                    }
                } catch (PDOException $e) {
                    $message = "Error updating personal information: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete personal information
                try {
                    // Check if this person is linked to any employee profiles
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE personal_info_id = ?");
                    $checkStmt->execute([$_POST['personal_info_id']]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        $message = "Error: Cannot delete personal information. This person is linked to an employee profile!";
                        $messageType = "error";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM personal_information WHERE personal_info_id=?");
                        $stmt->execute([$_POST['personal_info_id']]);
                        $message = "Personal information deleted successfully!";
                        $messageType = "success";
                    }
                } catch (PDOException $e) {
                    $message = "Error deleting personal information: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch all personal information
$stmt = $pdo->query("
    SELECT *,
           CONCAT(first_name, ' ', last_name) as full_name,
           TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age
    FROM personal_information 
    ORDER BY personal_info_id DESC
");
$personalInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Information Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for personal information page */
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

        .status-single {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-married {
            background: #d4edda;
            color: #155724;
        }

        .status-divorced {
            background: #f8d7da;
            color: #721c24;
        }

        .status-widowed {
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
                <h2 class="section-title">Personal Information Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by name, phone, tax ID, or SSN...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            👤 Add New Person
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="personalInfoTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Age/DOB</th>
                                    <th>Gender</th>
                                    <th>Marital Status</th>
                                    <th>Contact Info</th>
                                    <th>Tax ID</th>
                                    <th>Emergency Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="personalInfoTableBody">
                                <?php foreach ($personalInfo as $person): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($person['personal_info_id']) ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($person['full_name']) ?></strong><br>
                                            <small style="color: #666;">🌍 <?= htmlspecialchars($person['nationality']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= $person['age'] ?> years old</strong><br>
                                            <small style="color: #666;">🎂 <?= date('M d, Y', strtotime($person['date_of_birth'])) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($person['gender']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($person['marital_status']) ?>">
                                            <?= htmlspecialchars($person['marital_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            📞 <?= htmlspecialchars($person['phone_number']) ?><br>
                                            <small style="color: #666;">🆔 <?= htmlspecialchars($person['social_security_number']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($person['tax_id']) ?></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($person['emergency_contact_name']) ?></strong><br>
                                            <small style="color: #666;"><?= htmlspecialchars($person['emergency_contact_relationship']) ?> - <?= htmlspecialchars($person['emergency_contact_phone']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editPerson(<?= $person['personal_info_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deletePerson(<?= $person['personal_info_id'] ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($personalInfo)): ?>
                        <div class="no-results">
                            <i>👥</i>
                            <h3>No personal information found</h3>
                            <p>Start by adding your first person's information.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Personal Information Modal -->
    <div id="personalInfoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Person</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="personalInfoForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="personal_info_id" name="personal_info_id">

                    <!-- Basic Information Section -->
                    <div class="section-header">📋 Basic Information</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" class="form-control" required>
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
                                <select id="marital_status" name="marital_status" class="form-control" required>
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
                                <input type="text" id="nationality" name="nationality" class="form-control" value="Filipino" required>
                            </div>
                        </div>
                    </div>

                    <!-- Government Information Section -->
                    <div class="section-divider">
                        <div class="section-header">🏛️ Government Information</div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="tax_id">Tax ID</label>
                                <input type="text" id="tax_id" name="tax_id" class="form-control" placeholder="123-45-6789" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="social_security_number">Social Security Number</label>
                                <input type="text" id="social_security_number" name="social_security_number" class="form-control" placeholder="123456789" required>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="section-divider">
                        <div class="section-header">📞 Contact Information</div>
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="555-1234" required>
                    </div>

                    <!-- Emergency Contact Section -->
                    <div class="section-divider">
                        <div class="section-header">🚨 Emergency Contact</div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="emergency_contact_name">Emergency Contact Name</label>
                                <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="emergency_contact_relationship">Relationship</label>
                                <select id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" required>
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
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_phone">Emergency Contact Phone</label>
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" placeholder="555-5678" required>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Information</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let personalInfoData = <?= json_encode($personalInfo) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('personalInfoTableBody');
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
        function openModal(mode, personalInfoId = null) {
            const modal = document.getElementById('personalInfoModal');
            const form = document.getElementById('personalInfoForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Person';
                action.value = 'add';
                form.reset();
                document.getElementById('personal_info_id').value = '';
                document.getElementById('nationality').value = 'Filipino'; // Default value
            } else if (mode === 'edit' && personalInfoId) {
                title.textContent = 'Edit Personal Information';
                action.value = 'update';
                document.getElementById('personal_info_id').value = personalInfoId;
                populateEditForm(personalInfoId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('personalInfoModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(personalInfoId) {
            const person = personalInfoData.find(p => p.personal_info_id == personalInfoId);
            if (person) {
                document.getElementById('first_name').value = person.first_name || '';
                document.getElementById('last_name').value = person.last_name || '';
                document.getElementById('date_of_birth').value = person.date_of_birth || '';
                document.getElementById('gender').value = person.gender || '';
                document.getElementById('marital_status').value = person.marital_status || '';
                document.getElementById('nationality').value = person.nationality || '';
                document.getElementById('tax_id').value = person.tax_id || '';
                document.getElementById('social_security_number').value = person.social_security_number || '';
                document.getElementById('phone_number').value = person.phone_number || '';
                document.getElementById('emergency_contact_name').value = person.emergency_contact_name || '';
                document.getElementById('emergency_contact_relationship').value = person.emergency_contact_relationship || '';
                document.getElementById('emergency_contact_phone').value = person.emergency_contact_phone || '';
            }
        }

        function editPerson(personalInfoId) {
            openModal('edit', personalInfoId);
        }

        function deletePerson(personalInfoId) {
            if (confirm('Are you sure you want to delete this person\'s information? This action cannot be undone and will fail if the person is linked to an employee profile.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="personal_info_id" value="${personalInfoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('personalInfoModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('personalInfoForm').addEventListener('submit', function(e) {
            // Validate age (must be at least 16 years old)
            const birthDate = new Date(document.getElementById('date_of_birth').value);
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            if (age < 16) {
                e.preventDefault();
                alert('Person must be at least 16 years old');
                return;
            }

            if (age > 120) {
                e.preventDefault();
                alert('Please enter a valid birth date');
                return;
            }

            // Validate phone numbers (basic format check)
            const phone = document.getElementById('phone_number').value;
            const emergencyPhone = document.getElementById('emergency_contact_phone').value;
            
            if (phone && !isValidPhone(phone)) {
                e.preventDefault();
                alert('Please enter a valid phone number format');
                return;
            }

            if (emergencyPhone && !isValidPhone(emergencyPhone)) {
                e.preventDefault();
                alert('Please enter a valid emergency contact phone number format');
                return;
            }

            // Validate Tax ID format (basic check)
            const taxId = document.getElementById('tax_id').value;
            if (taxId && !isValidTaxId(taxId)) {
                e.preventDefault();
                alert('Please enter a valid Tax ID format (XXX-XX-XXXX)');
                return;
            }

            // Validate SSN format (basic check)
            const ssn = document.getElementById('social_security_number').value;
            if (ssn && !isValidSSN(ssn)) {
                e.preventDefault();
                alert('Please enter a valid Social Security Number (9 digits)');
                return;
            }
        });

        function isValidPhone(phone) {
            // Basic phone validation - allows various formats
            const phoneRegex = /^[\d\s\-\+\(\)]{7,15}$/;
            return phoneRegex.test(phone);
        }

        function isValidTaxId(taxId) {
            // Basic Tax ID validation - XXX-XX-XXXX format
            const taxIdRegex = /^\d{3}-\d{2}-\d{4}$/;
            return taxIdRegex.test(taxId);
        }

        function isValidSSN(ssn) {
            // Basic SSN validation - 9 digits
            const ssnRegex = /^\d{9}$/;
            return ssnRegex.test(ssn.replace(/\D/g, ''));
        }

        // Auto-format inputs
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

        document.getElementById('social_security_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value.substring(0, 9);
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
            const tableRows = document.querySelectorAll('#personalInfoTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Set max date for birth date (today)
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_of_birth').setAttribute('max', today);

            // Set min date for birth date (120 years ago)
            const minDate = new Date();
            minDate.setFullYear(minDate.getFullYear() - 120);
            document.getElementById('date_of_birth').setAttribute('min', minDate.toISOString().split('T')[0]);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // ESC to close modal
            if (e.key === 'Escape') {
                closeModal();
            }
            
            // Ctrl+N to add new person
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openModal('add');
            }
        });

        // Age calculator helper function
        function calculateAge(birthDate) {
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            
            return age;
        }

        // Live age calculation on birth date change
        document.getElementById('date_of_birth').addEventListener('change', function() {
            const birthDate = this.value;
            if (birthDate) {
                const age = calculateAge(birthDate);
                console.log(`Age will be: ${age} years`);
            }
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>