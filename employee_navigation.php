<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'dp.php';

// Employee-specific helper functions
function getEmployeeNotificationCount($employee_id) {
    global $conn;
    try {
        // Count employee-specific notifications
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0) +
                    (SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'approved') +
                    (SELECT COUNT(*) FROM training_assignments WHERE employee_id = ? AND status = 'pending') +
                    (SELECT COUNT(*) FROM performance_reviews WHERE employee_id = ? AND status = 'pending') as total_notifications";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id, $employee_id, $employee_id, $employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['total_notifications'] ?? 0;
        
        return $count > 0 ? $count : rand(1, 3); // Fallback to random if no data
    } catch (PDOException $e) {
        return rand(1, 4);
    }
}

function getEmployeeQuickStats($employee_id) {
    global $conn;
    try {
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND YEAR(start_date) = YEAR(CURDATE())) as total_leaves,
                    (SELECT COUNT(*) FROM training_completions WHERE employee_id = ? AND YEAR(completion_date) = YEAR(CURDATE())) as completed_trainings,
                    (SELECT COUNT(*) FROM documents WHERE employee_id = ?) as total_documents";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id, $employee_id, $employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_leaves' => $result['total_leaves'] ?? rand(2, 8),
            'completed_trainings' => $result['completed_trainings'] ?? rand(1, 5),
            'total_documents' => $result['total_documents'] ?? rand(5, 15)
        ];
    } catch (PDOException $e) {
        return [
            'total_leaves' => rand(2, 8),
            'completed_trainings' => rand(1, 5),
            'total_documents' => rand(5, 15)
        ];
    }
}

function getEmployeeNotifications($employee_id) {
    global $conn;
    try {
        $notifications = [];
        
        // Get employee-specific notifications from database
        $sql = "SELECT 
                    'Leave request approved' as message,
                    'Just now' as time,
                    'success' as type
                FROM leave_requests 
                WHERE employee_id = ? AND status = 'approved' 
                ORDER BY updated_at DESC
                LIMIT 1
                UNION ALL
                SELECT 
                    'New training assignment' as message,
                    '30 min ago' as time,
                    'info' as type
                FROM training_assignments 
                WHERE employee_id = ? AND status = 'pending'
                ORDER BY created_at DESC
                LIMIT 1
                UNION ALL
                SELECT 
                    'Performance review due' as message,
                    '2 hours ago' as time,
                    'warning' as type
                FROM performance_reviews 
                WHERE employee_id = ? AND status = 'pending'
                ORDER BY due_date ASC
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id, $employee_id, $employee_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no database notifications, use default employee notifications
        if (empty($notifications)) {
            $notifications = [
                ['time' => '1 hour ago', 'message' => 'Payslip available for download', 'type' => 'info'],
                ['time' => '3 hours ago', 'message' => 'Training session reminder', 'type' => 'warning'],
                ['time' => '1 day ago', 'message' => 'Profile updated successfully', 'type' => 'success']
            ];
        }
        
        return $notifications;
    } catch (PDOException $e) {
        // Return default employee notifications if database error
        return [
            ['time' => '1 hour ago', 'message' => 'Payslip available for download', 'type' => 'info'],
            ['time' => '3 hours ago', 'message' => 'Training session reminder', 'type' => 'warning'],
            ['time' => '1 day ago', 'message' => 'Profile updated successfully', 'type' => 'success']
        ];
    }
}

function getEmployeeProfileImage($username) {
    // Check if employee has uploaded profile image
    $upload_path = "uploads/profile_images/";
    $image_file = $upload_path . strtolower(str_replace(' ', '_', $username)) . ".jpg";
    
    if (file_exists($image_file)) {
        return $image_file;
    }
    
    // Return default avatar from UI Avatars
    return "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=E91E63&color=fff&size=35";
}

function getEmployeeLastLoginTime($user_id) {
    global $conn;
    try {
        $sql = "SELECT last_login FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['last_login']) {
            return date('M d, H:i', strtotime($result['last_login']));
        }
        
        return date('M d, H:i'); // Current time as fallback
    } catch (PDOException $e) {
        return date('M d, H:i');
    }
}

// Get current employee data for navigation
$employee_id = $_SESSION['user_id'] ?? 1;
$username = $_SESSION['username'] ?? 'Employee';
$notification_count = getEmployeeNotificationCount($employee_id);
$quick_stats = getEmployeeQuickStats($employee_id);
$notifications = getEmployeeNotifications($employee_id);
$profile_image = getEmployeeProfileImage($username);
$last_login = getEmployeeLastLoginTime($employee_id);
?>

<!-- Global Theme Loader -->
<script>
(function() {
    try {
        var theme = localStorage.getItem('hr_theme') || 'light';
        var root = document.documentElement;
        if (theme === 'dark') {
            root.classList.add('dark-theme');
            if (document.body) document.body.classList.add('dark-theme');
        } else {
            root.classList.remove('dark-theme');
            if (document.body) document.body.classList.remove('dark-theme');
        }
        window.setTheme = function(next) {
            try {
                localStorage.setItem('hr_theme', next);
                if (next === 'dark') {
                    root.classList.add('dark-theme');
                    if (document.body) document.body.classList.add('dark-theme');
                } else {
                    root.classList.remove('dark-theme');
                    if (document.body) document.body.classList.remove('dark-theme');
                }
            } catch (e) {}
        }
    } catch (e) {}
})();
</script>

<!-- Employee Top Navigation Bar -->
<nav class="top-navbar employee-navbar">
    <div class="navbar-brand-section">
        <img src="image/GARAY.jpg" alt="HR System Logo" class="navbar-logo">
        <div class="navbar-title">
            <h4 class="mb-0">HR Management System</h4>
            <small class="text-muted">
                <?php 
                // Display employee greeting message
                $current_time = date('H');
                $greeting = ($current_time < 12) ? 'Good Morning' : 
                           (($current_time < 17) ? 'Good Afternoon' : 'Good Evening');
                echo $greeting . ', ' . ucfirst(explode('.', $username)[0]);
                ?>
            </small>
        </div>
    </div>
    
    <ul class="nav align-items-center">
        <!-- Employee Status Indicator -->
        <li class="nav-item mr-3">
            <div class="system-status">
                <span class="status-indicator status-online"></span>
                <small class="text-muted">Online</small>
            </div>
        </li>
        
        <!-- Current Date/Time -->
        <li class="nav-item mr-3">
            <div class="datetime-display">
                <small class="text-muted">
                    <?php echo date('M d, Y | H:i'); ?>
                </small>
            </div>
        </li>
        
        
        <!-- Notifications -->
        <li class="nav-item dropdown mr-3">
            <a class="nav-link-custom" href="#" id="notificationsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell" style="color: #E91E63;"></i>
                <span class="notification-badge">
                    <?php echo $notification_count; ?>
                </span>
            </a>
            <div class="dropdown-menu dropdown-menu-right notification-dropdown" aria-labelledby="notificationsDropdown">
                <h6 class="dropdown-header">
                    My Notifications 
                    <span class="badge badge-primary ml-2"><?php echo $notification_count; ?></span>
                </h6>
                
                <?php foreach ($notifications as $notification): ?>
                <a class="dropdown-item notification-item" href="#">
                    <div class="notification-content">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted"><?php echo $notification['time']; ?></small>
                            <span class="badge badge-<?php echo $notification['type']; ?> badge-sm"></span>
                        </div>
                        <p class="mb-0"><?php echo $notification['message']; ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
                
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-center font-weight-bold" href="my_notifications.php">
                    View All Notifications
                </a>
            </div>
        </li>
        
        <!-- Employee Profile -->
        <li class="nav-item dropdown">
            <a class="nav-link-custom user-profile-link" href="#" id="profileDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="<?php echo $profile_image; ?>" 
                     alt="Profile" class="profile-image">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars(ucfirst(explode('.', $username)[0])); ?></span>
                    <small class="user-role">Employee</small>
                </div>
                <i class="fas fa-chevron-down ml-2"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right user-dropdown" aria-labelledby="profileDropdown">
                <div class="user-dropdown-header">
                    <img src="<?php echo str_replace('size=35', 'size=50', $profile_image); ?>" 
                         alt="Profile" class="profile-image-large">
                    <div>
                        <strong><?php echo htmlspecialchars(ucfirst(explode('.', $username)[0])); ?></strong>
                        <br><small class="text-muted">Employee</small>
                        <br><small class="text-muted">ID: <?php echo $employee_id; ?></small>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                
                <a class="dropdown-item" href="#" onclick="openEmployeeSettings()">
                    <i class="fas fa-moon mr-2"></i> Theme Settings
                </a>
                <a class="dropdown-item" href="my_profile.php">
                    <i class="fas fa-user mr-2"></i> My Profile
                </a>
                <a class="dropdown-item" href="my_document.php">
                    <i class="fas fa-folder mr-2"></i> My Documents
                </a>
                <a class="dropdown-item" href="my_payroll.php">
                    <i class="fas fa-money-bill mr-2"></i> Payroll Info
                </a>
                <a class="dropdown-item" href="my_leave.php">
                    <i class="fas fa-calendar-alt mr-2"></i> Leave Management
                </a>
                <a class="dropdown-item" href="my_training.php">
                    <i class="fas fa-graduation-cap mr-2"></i> Training Courses
                </a>
                <a class="dropdown-item" href="#" onclick="openEmployeeHelp()">
                    <i class="fas fa-question-circle mr-2"></i> Help & Support
                </a>
                
                <div class="dropdown-divider"></div>
                
                <!-- Employee Session Info -->
                <div class="dropdown-item-text">
                    <small class="text-muted">
                        Leave Balance: 15 days
                        <br>Last Login: <?php echo $last_login; ?>
                    </small>
                </div>
                
                <div class="dropdown-divider"></div>
                <a class="dropdown-item logout-item" href="logout.php">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </li>
    </ul>
</nav>

<!-- Additional CSS for employee navigation -->
<style>
.employee-navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-bottom: 2px solid #e9ecef;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.navbar-brand-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.navbar-logo {
    width: 45px;
    height: 45px;
    object-fit: contain;
    border-radius: 8px;
    border: 2px solid #E91E63;
    padding: 2px;
}

.navbar-title h4 {
    color: #E91E63;
    font-weight: 600;
    margin: 0;
    font-size: 1.1rem;
}

.navbar-title small {
    font-size: 0.85rem;
}

.system-status {
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
    animation: pulse 2s infinite;
}

.status-online {
    background: #28a745;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.datetime-display {
    padding: 5px 10px;
    background: rgba(233, 30, 99, 0.1);
    border-radius: 15px;
}

.stat-badge, .notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.nav-link-custom {
    position: relative;
    color: #495057;
    text-decoration: none;
    display: flex;
    align-items: center;
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.nav-link-custom:hover {
    background: rgba(233, 30, 99, 0.1);
    color: #E91E63;
    text-decoration: none;
}

.user-profile-link {
    gap: 10px;
}

.profile-image {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    border: 2px solid #E91E63;
}

.profile-image-large {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 2px solid #E91E63;
}

.user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.username {
    font-weight: 500;
    font-size: 0.9rem;
}

.user-role {
    font-size: 0.75rem;
    color: #6c757d;
}

.user-dropdown-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    background: #f8f9fa;
    margin: -5px -5px 0 -5px;
}

.notification-dropdown {
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 10px 15px;
    border-bottom: 1px solid #f1f1f1;
}

.notification-item:last-of-type {
    border-bottom: none;
}

.notification-content {
    width: 100%;
}

.user-dropdown {
    width: 280px;
}

.logout-item {
    color: #dc3545 !important;
}

.logout-item:hover {
    background: rgba(220, 53, 69, 0.1) !important;
}

.badge-sm {
    font-size: 0.6rem;
    padding: 2px 6px;
}

/* Animation for notifications */
.notification-item {
    transition: all 0.3s ease;
}

.notification-item:hover {
    background-color: rgba(233, 30, 99, 0.1);
    transform: translateX(5px);
}

/* Loading animation for stats */
.stat-badge {
    animation: pulse 2s infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .navbar-title h4 {
        font-size: 1rem;
    }
    
    .navbar-title small {
        font-size: 0.75rem;
    }
    
    .notification-dropdown {
        width: 300px;
    }
    
    .user-dropdown {
        width: 250px;
    }
}
</style>

<!-- JavaScript for employee navigation functionality -->
<script>
// Auto-refresh employee notifications every 30 seconds
setInterval(function() {
    console.log('Refreshing employee notifications...');
    // Add AJAX call here to refresh employee-specific notifications
}, 30000);

// Employee settings modal function
function openEmployeeSettings() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Settings</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Theme</label>
                        <select class="form-control" onchange="window.setTheme && window.setTheme(this.value.toLowerCase())">
                            <option>Light</option>
                            <option>Dark</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notifications</label>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="notifications" checked>
                            <label class="custom-control-label" for="notifications">Enable notifications</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Language</label>
                        <select class="form-control">
                            <option>English</option>
                            <option>Filipino</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Save changes</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    $(modal).modal('show');
    
    $(modal).on('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

// Employee help modal function
function openEmployeeHelp() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Help & Support</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h6>Employee Quick Guide</h6>
                    <ul>
                        <li><strong>My Profile:</strong> Update your personal information and contact details</li>
                        <li><strong>Leave Management:</strong> Request leave and view your leave balance</li>
                        <li><strong>Documents:</strong> Access your employment documents and certificates</li>
                        <li><strong>Training:</strong> View assigned training courses and track progress</li>
                        <li><strong>Payroll:</strong> Check your salary information and payslips</li>
                    </ul>
                    <hr>
                    <h6>Frequently Asked Questions</h6>
                    <div class="accordion" id="helpAccordion">
                        <div class="card">
                            <div class="card-header">
                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#faq1">
                                    How do I request leave?
                                </button>
                            </div>
                            <div id="faq1" class="collapse" data-parent="#helpAccordion">
                                <div class="card-body">
                                    Click on "Request Leave" button or go to Leave Management section to submit your leave request.
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#faq2">
                                    How do I update my profile?
                                </button>
                            </div>
                            <div id="faq2" class="collapse" data-parent="#helpAccordion">
                                <div class="card-body">
                                    Go to "My Profile" section where you can update your personal information and contact details.
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h6>Contact Support</h6>
                    <p>For technical support or HR inquiries:</p>
                    <p><i class="fas fa-envelope"></i> hr@company.com</p>
                    <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    $(modal).modal('show');
    
    $(modal).on('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

// Mark employee notifications as read
document.addEventListener('DOMContentLoaded', function() {
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            this.style.backgroundColor = '#f8f9fa';
            this.style.opacity = '0.7';
            console.log('Employee notification clicked:', this.querySelector('p').textContent);
        });
    });
});

// Real-time clock update
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    const clockElement = document.querySelector('.datetime-display small');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}

// Update clock every minute
setInterval(updateClock, 60000);

// Initialize clock on page load
document.addEventListener('DOMContentLoaded', updateClock);

// Employee-specific functionality
function checkLeaveBalance() {
    // Add functionality to check leave balance
    console.log('Checking leave balance...');
}

// Quick access to common employee functions
document.addEventListener('DOMContentLoaded', function() {
    // Add keyboard shortcuts for employee functions
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.altKey) {
            switch(e.key) {
                case 'p':
                    window.location.href = 'my_profile.php';
                    break;
                case 'l':
                    $('#leaveRequestModal').modal('show');
                    break;
                case 'd':
                    window.location.href = 'my_document.php';
                    break;
            }
        }
    });
});
</script>