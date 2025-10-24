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
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        try {
            // Update employee profile
            $stmt = $pdo->prepare("UPDATE employee_profiles SET 
                current_salary=?, work_email=?, work_phone=?, location=?, remote_work=? 
                WHERE employee_id=?");
            $stmt->execute([
                $_POST['current_salary'],
                $_POST['work_email'],
                $_POST['work_phone'],
                $_POST['location'],
                isset($_POST['remote_work']) ? 1 : 0,
                $_POST['employee_id']
            ]);

            // Update personal information
            $stmt = $pdo->prepare("UPDATE personal_information SET 
                first_name=?, last_name=?, email=?, phone_number=?, address=?, 
                date_of_birth=?, gender=? 
                WHERE personal_info_id=?");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone_number'],
                $_POST['address'],
                $_POST['date_of_birth'],
                $_POST['gender'],
                $_POST['personal_info_id']
            ]);

            $message = "Your profile has been updated successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error updating profile: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get current user's profile information
$currentUserId = $_SESSION['user_id'] ?? null; // Get user_id from session

if (!$currentUserId) {
    header("Location: login.php");
    exit;
}

// Fetch current user's employee profile with related data by joining with users table
$stmt = $pdo->prepare("
    SELECT 
        ep.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        pi.*,
        jr.title as job_title,
        jr.department
    FROM users u
    JOIN employee_profiles ep ON u.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    WHERE u.user_id = ?
");
$stmt->execute([$currentUserId]);
$currentEmployee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentEmployee) {
    die("Employee profile not found. Please contact HR.");
}

// Fetch job roles for dropdown (in case user wants to request role change)
$stmt = $pdo->query("SELECT job_role_id, title, department FROM job_roles ORDER BY title");
$jobRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Custom styles for my profile page */
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 20px;
            border: 4px solid rgba(255,255,255,0.3);
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .profile-title {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .profile-department {
            opacity: 0.8;
            margin-bottom: 20px;
        }

        .profile-details {
            padding: 30px;
        }

        .detail-section {
            margin-bottom: 30px;
        }

        .detail-section h3 {
            color: var(--azure-blue-dark);
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--azure-blue-lighter);
        }

        .detail-row {
            display: flex;
            margin-bottom: 15px;
            align-items: center;
        }

        .detail-label {
            font-weight: 600;
            color: var(--azure-blue-dark);
            min-width: 150px;
            margin-right: 20px;
        }

        .detail-value {
            flex: 1;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #e9ecef;
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

        .edit-profile-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            font-size: 14px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
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
            width: 90%;
            max-width: 700px;
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
            padding: 12px 15px;
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

        .readonly-field {
            background: #e9ecef !important;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .detail-label {
                min-width: auto;
                margin-right: 0;
                margin-bottom: 5px;
            }
            
            .profile-header {
                padding: 20px;
            }
            
            .edit-profile-btn {
                position: static;
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'employee_navigation.php'; ?>
        <div class="row">
            <?php include 'employee_sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">My Profile</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="profile-card">
                    <div class="profile-header">
                        <button class="btn btn-primary edit-profile-btn" onclick="openEditModal()">
                            ✏️ Edit Profile
                        </button>
                        <div class="profile-avatar">
                            👤
                        </div>
                        <div class="profile-name"><?= htmlspecialchars($currentEmployee['full_name']) ?></div>
                        <div class="profile-title"><?= htmlspecialchars($currentEmployee['job_title']) ?></div>
                        <div class="profile-department"><?= htmlspecialchars($currentEmployee['department']) ?></div>
                        <span class="status-badge status-<?= strtolower($currentEmployee['employment_status']) === 'full-time' ? 'active' : 'inactive' ?>">
                            <?= htmlspecialchars($currentEmployee['employment_status']) ?>
                        </span>
                    </div>

                    <div class="profile-details">
                        <div class="detail-section">
                            <h3>🏢 Employment Information</h3>
                            <div class="detail-row">
                                <div class="detail-label">Employee Number:</div>
                                <div class="detail-value"><?= htmlspecialchars($currentEmployee['employee_number']) ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Hire Date:</div>
                                <div class="detail-value"><?= date('F d, Y', strtotime($currentEmployee['hire_date'])) ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Current Salary:</div>
                                <div class="detail-value">₱<?= number_format($currentEmployee['current_salary'], 2) ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Work Location:</div>
                                <div class="detail-value"><?= htmlspecialchars($currentEmployee['location'] ?: 'Not specified') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Remote Work:</div>
                                <div class="detail-value"><?= $currentEmployee['remote_work'] ? 'Enabled' : 'Not enabled' ?></div>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3>📞 Contact Information</h3>
                            <div class="detail-row">
                                <div class="detail-label">Work Email:</div>
                                <div class="detail-value"><?= htmlspecialchars($currentEmployee['work_email'] ?: 'Not provided') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Work Phone:</div>
                                <div class="detail-value"><?= htmlspecialchars($currentEmployee['work_phone'] ?: 'Not provided') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Personal Email:</div>
                                <div class="detail-value"><?= htmlspecialchars($currentEmployee['email'] ?: 'Not provided') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Personal Phone:</div>
                                <div class="detail-value"><?= htmlspecialchars($currentEmployee['phone_number'] ?: 'Not provided') ?></div>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3>👤 Personal Information</h3>
                            <div class="detail-row">
                                <div class="detail-label">Date of Birth:</div>
                                <div class="detail-value"><?= $currentEmployee['date_of_birth'] ? date('F d, Y', strtotime($currentEmployee['date_of_birth'])) : 'Not provided' ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Gender:</div>
                                <div class="detail-value"><?= htmlspecialchars($currentEmployee['gender'] ?: 'Not specified') ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Address:</div>
                                <div class="detail-value"><?= htmlspecialchars($currentEmployee['address'] ?: 'Not provided') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit My Profile</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="profileForm" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="employee_id" value="<?= $currentEmployee['employee_id'] ?>">
                    <input type="hidden" name="personal_info_id" value="<?= $currentEmployee['personal_info_id'] ?>">

                    <div class="detail-section">
                        <h3>👤 Personal Information</h3>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" 
                                           value="<?= htmlspecialchars($currentEmployee['first_name']) ?>" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" 
                                           value="<?= htmlspecialchars($currentEmployee['last_name']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                           value="<?= $currentEmployee['date_of_birth'] ?>">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender" class="form-control">
                                        <option value="">Select gender...</option>
                                        <option value="Male" <?= $currentEmployee['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= $currentEmployee['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= $currentEmployee['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                        <option value="Prefer not to say" <?= $currentEmployee['gender'] === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($currentEmployee['address']) ?></textarea>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3>📞 Contact Information</h3>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="email">Personal Email</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($currentEmployee['email']) ?>">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="phone_number">Personal Phone</label>
                                    <input type="tel" id="phone_number" name="phone_number" class="form-control" 
                                           value="<?= htmlspecialchars($currentEmployee['phone_number']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="work_email">Work Email</label>
                                    <input type="email" id="work_email" name="work_email" class="form-control" 
                                           value="<?= htmlspecialchars($currentEmployee['work_email']) ?>">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="work_phone">Work Phone</label>
                                    <input type="tel" id="work_phone" name="work_phone" class="form-control" 
                                           value="<?= htmlspecialchars($currentEmployee['work_phone']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3>🏢 Employment Information</h3>
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="current_salary">Current Salary (₱)</label>
                                    <input type="number" id="current_salary" name="current_salary" class="form-control readonly-field" 
                                           value="<?= $currentEmployee['current_salary'] ?>" step="0.01" readonly>
                                    <small style="color: #666;">Contact HR to request salary changes</small>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="location">Work Location</label>
                                    <input type="text" id="location" name="location" class="form-control" 
                                           value="<?= htmlspecialchars($currentEmployee['location']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="remote_work" name="remote_work" 
                                       <?= $currentEmployee['remote_work'] ? 'checked' : '' ?>>
                                <label for="remote_work">Request Remote Work Access</label>
                            </div>
                            <small style="color: #666;">This request will be reviewed by HR</small>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditModal() {
            const modal = document.getElementById('editProfileModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            const modal = document.getElementById('editProfileModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editProfileModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const workEmail = document.getElementById('work_email').value;
            
            if (email && !isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid personal email address');
                return;
            }
            
            if (workEmail && !isValidEmail(workEmail)) {
                e.preventDefault();
                alert('Please enter a valid work email address');
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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modal
            if (e.key === 'Escape') {
                closeEditModal();
            }
            // Ctrl+E to open edit modal
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                openEditModal();
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth transitions to profile cards
            const profileCard = document.querySelector('.profile-card');
            profileCard.style.opacity = '0';
            profileCard.style.transform = 'translateY(20px)';
            
            setTimeout(function() {
                profileCard.style.transition = 'all 0.6s ease';
                profileCard.style.opacity = '1';
                profileCard.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>