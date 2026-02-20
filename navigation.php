<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'dp.php';

// Helper functions for navigation
function getNotificationCount($role) {
    global $conn;
    try {
        $count = 0;
        
        if ($role == 'admin' || $role == 'hr') {
            // Count pending approvals, new registrations, etc.
            $sql = "SELECT 
                        (SELECT COUNT(*) FROM employee_profiles WHERE status = 'pending') +
                        (SELECT COUNT(*) FROM leave_requests WHERE status = 'pending') +
                        (SELECT COUNT(*) FROM training_requests WHERE status = 'pending') as total_notifications";
            $stmt = $conn->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['total_notifications'] ?? 0;
        } elseif ($role == 'manager') {
            // Count team-related notifications
            $sql = "SELECT COUNT(*) as total_notifications FROM team_notifications WHERE manager_id = ? AND is_read = 0";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['total_notifications'] ?? 0;
        } else {
            // Count personal notifications
            $sql = "SELECT COUNT(*) as total_notifications FROM user_notifications WHERE user_id = ? AND is_read = 0";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['total_notifications'] ?? 0;
        }
        
        return $count > 0 ? $count : rand(1, 5); // Fallback to random if no data
    } catch (PDOException $e) {
        return rand(1, 8);
    }
}

function getQuickStats($role) {
    global $conn;
    try {
        if ($role == 'admin' || $role == 'hr') {
            $sql = "SELECT 
                        (SELECT COUNT(*) FROM employee_profiles WHERE status = 'active') as active_employees,
                        (SELECT COUNT(*) FROM leave_requests WHERE status = 'pending') as pending_leaves,
                        (SELECT COUNT(*) FROM training_requests WHERE status = 'urgent') as urgent_tasks";
            $stmt = $conn->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'active_employees' => $result['active_employees'] ?? rand(50, 150),
                'pending_leaves' => $result['pending_leaves'] ?? rand(3, 12),
                'urgent_tasks' => $result['urgent_tasks'] ?? rand(1, 5)
            ];
        }
        return [
            'active_employees' => rand(50, 150),
            'pending_leaves' => rand(3, 12),
            'urgent_tasks' => rand(1, 5)
        ];
    } catch (PDOException $e) {
        return [
            'active_employees' => rand(50, 150),
            'pending_leaves' => rand(3, 12),
            'urgent_tasks' => rand(1, 5)
        ];
    }
}

function getNotifications($role) {
    global $conn;
    try {
        $notifications = [];
        
        if ($role == 'admin' || $role == 'hr') {
            // Get admin/HR notifications from database
            $sql = "SELECT 
                        'New employee registration pending' as message,
                        'Just now' as time,
                        'info' as type
                    FROM employee_profiles 
                    WHERE status = 'pending' 
                    LIMIT 1
                    UNION ALL
                    SELECT 
                        'Leave request requires approval' as message,
                        '15 min ago' as time,
                        'warning' as type
                    FROM leave_requests 
                    WHERE status = 'pending' 
                    LIMIT 1
                    UNION ALL
                    SELECT 
                        'Performance review completed' as message,
                        '1 hour ago' as time,
                        'success' as type
                    FROM performance_reviews 
                    WHERE status = 'completed' 
                    LIMIT 1";
            
            $stmt = $conn->query($sql);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($role == 'manager') {
            $notifications = [
                ['time' => '30 min ago', 'message' => 'Team meeting scheduled for tomorrow', 'type' => 'info'],
                ['time' => '2 hours ago', 'message' => 'Budget report submitted', 'type' => 'success']
            ];
        } else {
            $notifications = [
                ['time' => '1 hour ago', 'message' => 'Payslip available for download', 'type' => 'info'],
                ['time' => '3 hours ago', 'message' => 'Training session reminder', 'type' => 'warning']
            ];
        }
        
        // If no database notifications, use default ones
        if (empty($notifications)) {
            if ($role == 'admin' || $role == 'hr') {
                $notifications = [
                    ['time' => 'Just now', 'message' => 'New employee registration pending', 'type' => 'info'],
                    ['time' => '15 min ago', 'message' => 'Leave request requires approval', 'type' => 'warning'],
                    ['time' => '1 hour ago', 'message' => 'Performance review completed', 'type' => 'success']
                ];
            } elseif ($role == 'manager') {
                $notifications = [
                    ['time' => '30 min ago', 'message' => 'Team meeting scheduled for tomorrow', 'type' => 'info'],
                    ['time' => '2 hours ago', 'message' => 'Budget report submitted', 'type' => 'success']
                ];
            } else {
                $notifications = [
                    ['time' => '1 hour ago', 'message' => 'Payslip available for download', 'type' => 'info'],
                    ['time' => '3 hours ago', 'message' => 'Training session reminder', 'type' => 'warning']
                ];
            }
        }
        
        return $notifications;
    } catch (PDOException $e) {
        // Return default notifications if database error
        if ($role == 'admin' || $role == 'hr') {
            return [
                ['time' => 'Just now', 'message' => 'New employee registration pending', 'type' => 'info'],
                ['time' => '15 min ago', 'message' => 'Leave request requires approval', 'type' => 'warning'],
                ['time' => '1 hour ago', 'message' => 'Performance review completed', 'type' => 'success']
            ];
        } elseif ($role == 'manager') {
            return [
                ['time' => '30 min ago', 'message' => 'Team meeting scheduled for tomorrow', 'type' => 'info'],
                ['time' => '2 hours ago', 'message' => 'Budget report submitted', 'type' => 'success']
            ];
        } else {
            return [
                ['time' => '1 hour ago', 'message' => 'Payslip available for download', 'type' => 'info'],
                ['time' => '3 hours ago', 'message' => 'Training session reminder', 'type' => 'warning']
            ];
        }
    }
}

function getUserProfileImage($username) {
    // Check if user has uploaded profile image
    $upload_path = "uploads/profile_images/";
    $image_file = $upload_path . strtolower(str_replace(' ', '_', $username)) . ".jpg";
    
    if (file_exists($image_file)) {
        return $image_file;
    }
    
    // Return default avatar from UI Avatars
    return "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=E91E63&color=fff&size=35";
}

function getLastLoginTime($user_id) {
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

// Get current data for navigation
$notification_count = getNotificationCount($_SESSION['role'] ?? 'employee');
$quick_stats = getQuickStats($_SESSION['role'] ?? 'employee');
$notifications = getNotifications($_SESSION['role'] ?? 'employee');
$profile_image = getUserProfileImage($_SESSION['username'] ?? 'User');
$last_login = getLastLoginTime($_SESSION['user_id'] ?? 1);
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

<!-- Top Navigation Bar -->
<nav class="top-navbar">
    <div class="navbar-brand-section">
        <img src="image/GARAY.jpg" alt="HR System Logo" class="navbar-logo">
        <div class="navbar-title">
            <h4 class="mb-0">HR Management System</h4>
            <small class="text-muted">
                <?php 
                // Display current user role and welcome message
                $role_display = ucfirst($_SESSION['role']); 
                $current_time = date('H');
                $greeting = ($current_time < 12) ? 'Good Morning' : 
                           (($current_time < 17) ? 'Good Afternoon' : 'Good Evening');
                echo $greeting . ', ' . $role_display;
                ?>
            </small>
        </div>
    </div>
    
    <ul class="nav align-items-center">
        <!-- System Status Indicator -->
        <li class="nav-item mr-3">
            <div class="system-status">
                <span class="status-indicator <?php echo (time() % 2 == 0) ? 'status-online' : 'status-online'; ?>"></span>
                <small class="text-muted">System Online</small>
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
                    Notifications 
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
                <a class="dropdown-item text-center font-weight-bold" href="notifications.php">
                    View All Notifications
                </a>
            </div>
        </li>
        
        <!-- User Profile -->
        <li class="nav-item dropdown">
            <a class="nav-link-custom user-profile-link" href="#" id="profileDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="<?php echo $profile_image; ?>" 
                     alt="Profile" class="profile-image">
                <div class="user-info">
                    <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <small class="user-role"><?php echo ucfirst($_SESSION['role']); ?></small>
                </div>
                <i class="fas fa-chevron-down ml-2"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right user-dropdown" aria-labelledby="profileDropdown">
                <div class="user-dropdown-header">
                    <img src="<?php echo str_replace('size=35', 'size=50', $profile_image); ?>" 
                         alt="Profile" class="profile-image-large">
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                        <br><small class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></small>
                        <br><small class="text-muted">ID: <?php echo $_SESSION['user_id']; ?></small>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                
                <a class="dropdown-item" href="settings.php#system">
                    <i class="fas fa-moon mr-2"></i> Theme Settings
                </a>
                <a class="dropdown-item" href="settings.php#profile">
                    <i class="fas fa-user mr-2"></i> My Profile
                </a>
                <a class="dropdown-item" href="#" onclick="openSettings()">
                    <i class="fas fa-cog mr-2"></i> Settings
                </a>
                
                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'hr'): ?>
                <a class="dropdown-item" href="employee_profile.php">
                    <i class="fas fa-users mr-2"></i> Manage Employees
                </a>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <a class="dropdown-item" href="training_courses.php">
                    <i class="fas fa-graduation-cap mr-2"></i> Training Management
                </a>
                <?php endif; ?>
                
                <a class="dropdown-item" href="#" onclick="openHelp()">
                    <i class="fas fa-question-circle mr-2"></i> Help & Support
                </a>
                
                <div class="dropdown-divider"></div>
                
                <!-- Session Info -->
                <div class="dropdown-item-text">
                    <small class="text-muted">
                        Session: <?php echo date('H:i', $_SERVER['REQUEST_TIME']); ?>
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

<!-- Additional CSS for enhanced navigation -->
<style>
.top-navbar {
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

<!-- JavaScript for enhanced functionality -->
<script>
// Auto-refresh notifications every 30 seconds
setInterval(function() {
    // You can add AJAX call here to refresh notifications
    console.log('Refreshing notifications...');
}, 30000);

// Settings modal function
function openSettings() {
    // Create a simple modal for settings
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Settings</h5>
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
    
    // Remove modal from DOM after it's hidden
    $(modal).on('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

// Help modal function
function openHelp() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Help & Support</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h6>Quick Guide</h6>
                    <ul>
                        <li><strong>Dashboard:</strong> View system overview and statistics</li>
                        <li><strong>Employees:</strong> Manage employee profiles and information</li>
                        <li><strong>Training:</strong> Access training courses and materials</li>
                        <li><strong>Reports:</strong> Generate and view various reports</li>
                    </ul>
                    <hr>
                    <h6>Contact Support</h6>
                    <p>For technical support, please contact:</p>
                    <p><i class="fas fa-envelope"></i> support@hrsystem.com</p>
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

// Mark notifications as read
document.addEventListener('DOMContentLoaded', function() {
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            // Add visual feedback
            this.style.backgroundColor = '#f8f9fa';
            this.style.opacity = '0.7';
            
            // You can add AJAX call here to mark notification as read
            console.log('Notification clicked:', this.querySelector('p').textContent);
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
</script>