<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if user is logged in and is an employee
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$user_role = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? 'User';

// Function to check if menu item should be active
function isActiveMenu($page_name) {
    global $current_page;
    return $current_page === $page_name ? 'active' : '';
}

// Only show sidebar if user is employee, otherwise don't render anything
if (!$is_logged_in || $user_role !== 'employee') {
    return; // Don't render the sidebar, just return
}
?>

<!-- Employee Sidebar -->
<div class="sidebar">
    <!-- User Profile Section -->
    <div class="user-profile-section mb-4">
        <div class="text-center">
            <div class="user-avatar mb-2">
                <i class="fas fa-user-circle fa-3x" style="color: #fff;"></i>
            </div>
            <h6 class="text-white mb-1"><?php echo htmlspecialchars($username); ?></h6>
            <small class="text-light">Employee</small>
        </div>
    </div>

    <h4 class="text-center mb-4" style="color: #fff;">Employee Portal</h4>

    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_index.php'); ?>" href="employee_index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
                <?php if ($current_page === 'employee_index.php'): ?>
                    <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
        </li>
        
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('my_profile.php'); ?>" href="my_profile.php">
                <i class="fas fa-user"></i> My Profile
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('document_management.php'); ?>" href="my_document.php">
                <i class="fas fa-file-alt"></i> My Documents
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('leave_requests.php'); ?>" href="leave_requests.php">
                <i class="fas fa-calendar-alt"></i> Leave Requests
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('leave_balances.php'); ?>" href="leave_balances.php">
                <i class="fas fa-balance-scale"></i> Leave Balance
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('attendance.php'); ?>" href="attendance.php">
                <i class="fas fa-calendar-check"></i> My Attendance
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('payslips.php'); ?>" href="payslips.php">
                <i class="fas fa-receipt"></i> My Payslips
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('performance_reviews.php'); ?>" href="performance_reviews.php">
                <i class="fas fa-chart-line"></i> Performance Reviews
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('training_enrollments.php'); ?>" href="training_enrollments.php">
                <i class="fas fa-graduation-cap"></i> My Training
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('goals.php'); ?>" href="goals.php">
                <i class="fas fa-bullseye"></i> My Goals
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_skills.php'); ?>" href="employee_skills.php">
                <i class="fas fa-user-cog"></i> My Skills
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('career_paths.php'); ?>" href="career_paths.php">
                <i class="fas fa-road"></i> Career Path
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_resources.php'); ?>" href="employee_resources.php">
                <i class="fas fa-book-open"></i> Learning Resources
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('employee_settings.php'); ?>" href="employee_settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
        
        <!-- Logout Section -->
        <li class="nav-item mt-4">
            <a class="nav-link text-danger" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>

<style>
/* Employee sidebar specific styles */
.sidebar {
    background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%) !important;
    min-height: 100vh;
    padding: 20px 0;
    width: 250px;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
}

.user-profile-section {
    padding: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.user-avatar {
    margin-bottom: 10px;
}

.sidebar .nav-link {
    transition: all 0.3s ease;
    border-radius: 5px;
    margin: 2px 5px;
    color: rgba(255,255,255,0.9);
    padding: 12px 15px;
}

.sidebar .nav-link:hover {
    background-color: rgba(255,255,255,0.15);
    transform: translateX(5px);
    color: #fff;
    text-decoration: none;
}

.sidebar .nav-link.active {
    background-color: rgba(255,255,255,0.2);
    color: #fff;
    font-weight: 600;
    border-left: 4px solid #fff;
}

.sidebar .nav-link i {
    margin-right: 10px;
    width: 20px;
    text-align: center;
}

.sidebar .nav-link.active i {
    color: #fff;
}

.sidebar .nav-link.text-danger {
    color: #ffebee !important;
    border-top: 1px solid rgba(255,255,255,0.1);
    margin-top: 20px;
    padding-top: 15px;
}

.sidebar .nav-link.text-danger:hover {
    background-color: rgba(255,255,255,0.1);
    color: #fff !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sidebar {
        min-height: auto;
        padding: 15px 0;
        width: 100%;
        position: relative;
    }
    
    .sidebar .nav-link {
        padding: 10px 15px;
        font-size: 0.9rem;
    }
}

/* Override main-content styles for employee pages */
body.employee-page .main-content {
    margin-left: 250px !important;
    width: calc(100% - 250px) !important;
}

@media (max-width: 768px) {
    body.employee-page .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }
}
</style>

<script>
// JavaScript to enhance employee sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Only run on employee pages
    if (!document.body.classList.contains('employee-page')) {
        return;
    }
    
    // Auto-highlight current page
    const currentPage = '<?php echo $current_page; ?>';
    const menuItems = document.querySelectorAll('.sidebar .nav-link');
    
    console.log('Employee sidebar loaded for page:', currentPage);
    
    menuItems.forEach(item => {
        if (item.href && item.href.includes(currentPage)) {
            item.classList.add('active');
            console.log('Active menu item:', currentPage);
        }
    });
    
    // Add click animation
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            menuItems.forEach(i => i.classList.remove('active'));
            // Add active class to clicked item
            this.classList.add('active');
            
            console.log('Clicked menu item:', this.href);
        });
    });
    
    // Ensure sidebar is visible
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.style.display = 'block';
        console.log('Employee sidebar is visible');
    }
});
</script>
