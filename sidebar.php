<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if user is logged in
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

// Get user role and permissions (you can expand this based on your user system)
$user_role = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? 'User';

// Function to check if menu item should be active
function isActiveMenu($page_name) {
    global $current_page;
    return $current_page === $page_name ? 'active' : '';
}

// Function to check if user has permission for menu item
function hasPermission($required_role = 'user') {
    global $user_role;
    // Add your permission logic here
    return true; // For now, allow all users
}

// Get dashboard statistics for sidebar
function getSidebarStats() {
    global $conn;
    $stats = [];
    
    try {
        // Total employees
        $sql = "SELECT COUNT(*) as count FROM employee_profiles";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['employees'] = $result['count'] ?? 0;
        
        // Pending leave requests
        $sql = "SELECT COUNT(*) as count FROM leave_requests WHERE status = 'Pending'";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['pending_leaves'] = $result['count'] ?? 0;
        
        // Active job openings
        $sql = "SELECT COUNT(*) as count FROM job_openings WHERE status = 'Open'";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['job_openings'] = $result['count'] ?? 0;
        
        // Upcoming training sessions
        $sql = "SELECT COUNT(*) as count FROM training_sessions WHERE start_date >= CURDATE()";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['training_sessions'] = $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        // If database connection fails, return default values
        $stats = [
            'employees' => 0,
            'pending_leaves' => 0,
            'job_openings' => 0,
            'training_sessions' => 0
        ];
    }
    
    return $stats;
}

// Get sidebar statistics
$sidebar_stats = getSidebarStats();
?>

<!-- Sidebar -->
<div class="sidebar">
    <!-- User Profile Section -->
    <div class="user-profile-section mb-4">
        <div class="text-center">
            <div class="user-avatar mb-2">
                <i class="fas fa-user-circle fa-3x" style="color: #fff;"></i>
            </div>
            <h6 class="text-white mb-1"><?php echo htmlspecialchars($username); ?></h6>
            <small class="text-light"><?php echo ucfirst($user_role); ?></small>
        </div>
    </div>



    <h4 class="text-center mb-4" style="color: #fff;">HR Dashboard</h4>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('index.php'); ?>" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
                <?php if ($current_page === 'index.php'): ?>
                    <span class="sr-only">(current)</span>
                <?php endif; ?>
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link dropdown-toggle" data-toggle="collapse" href="#employeesDepartmentsCollapse" role="button" aria-expanded="false" aria-controls="employeesDepartmentsCollapse">
                <i class="fas fa-users"></i> Employees
                <?php if ($sidebar_stats['employees'] > 0): ?>
                    <span class="badge badge-light ml-2"><?php echo $sidebar_stats['employees']; ?></span>
                <?php endif; ?>
            </a>
            <div class="collapse" id="employeesDepartmentsCollapse">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('employee_profile.php'); ?>" href="employee_profile.php">
                            <i class="fas fa-user"></i> Employee Profiles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('personal_information.php'); ?>" href="personal_information.php">
                            <i class="fas fa-address-card"></i> Personal Information
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('employment_history.php'); ?>" href="employment_history.php">
                            <i class="fas fa-history"></i> Employment History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('document_management.php'); ?>" href="document_management.php">
                            <i class="fas fa-file-alt"></i> Document Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('departments_list.php'); ?>" href="departments_list.php">
                            <i class="fas fa-list"></i> Departments List
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('job_roles.php'); ?>" href="job_roles.php">
                            <i class="fas fa-user-tag"></i> Job Roles
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        
        <li class="nav-item">
            <a class="nav-link dropdown-toggle" data-toggle="collapse" href="#payrollCollapse" role="button" aria-expanded="false" aria-controls="payrollCollapse">
                <i class="fas fa-money-bill-wave"></i> Payroll
            </a>
            <div class="collapse" id="payrollCollapse">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('salary_structures.php'); ?>" href="salary_structures.php">
                            <i class="fas fa-money-check"></i> Salary Structures
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('payroll_cycles.php'); ?>" href="payroll_cycles.php">
                            <i class="fas fa-calendar-alt"></i> Payroll Cycles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('tax_deductions.php'); ?>" href="tax_deductions.php">
                            <i class="fas fa-percentage"></i> Tax Deductions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('statutory_deductions.php'); ?>" href="statutory_deductions.php">
                            <i class="fas fa-file-invoice-dollar"></i> Statutory Deductions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('benefits_plans.php'); ?>" href="benefits_plans.php">
                            <i class="fas fa-gift"></i> Benefits Plans
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('bonus_payments.php'); ?>" href="bonus_payments.php">
                            <i class="fas fa-coins"></i> Bonus Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('compensation_packages.php'); ?>" href="compensation_packages.php">
                            <i class="fas fa-box-open"></i> Compensation Packages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('payroll_transactions.php'); ?>" href="payroll_transactions.php">
                            <i class="fas fa-exchange-alt"></i> Payroll Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('payment_disbursements.php'); ?>" href="payment_disbursements.php">
                            <i class="fas fa-credit-card"></i> Payment Disbursements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('payslips.php'); ?>" href="payslips.php">
                            <i class="fas fa-receipt"></i> Payslips
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        
        <li class="nav-item">
            <a class="nav-link dropdown-toggle" data-toggle="collapse" href="#performanceCollapse" role="button" aria-expanded="false" aria-controls="performanceCollapse">
                <i class="fas fa-chart-line"></i> Performance
            </a>
            <div class="collapse" id="performanceCollapse">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('competencies.php'); ?>" href="competencies.php">
                            <i class="fas fa-star"></i> Competencies
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('employee_competencies.php'); ?>" href="employee_competencies.php">
                            <i class="fas fa-user-star"></i> Employee Competencies
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('performance_review_cycles.php'); ?>" href="performance_review_cycles.php">
                            <i class="fas fa-sync-alt"></i> Performance Review Cycles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('performance_reviews.php'); ?>" href="performance_reviews.php">
                            <i class="fas fa-comments"></i> Performance Reviews
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('feedback_360.php'); ?>" href="feedback_360.php">
                            <i class="fas fa-comment-dots"></i> Feedback 360
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('goals.php'); ?>" href="goals.php">
                            <i class="fas fa-bullseye"></i> Goals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('goal_updates.php'); ?>" href="goal_updates.php">
                            <i class="fas fa-tasks"></i> Goal Updates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('performance_metrics.php'); ?>" href="performance_metrics.php">
                            <i class="fas fa-chart-bar"></i> Performance Metrics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('development_plans.php'); ?>" href="development_plans.php">
                            <i class="fas fa-project-diagram"></i> Development Plans
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('development_activities.php'); ?>" href="development_activities.php">
                            <i class="fas fa-calendar-check"></i> Development Activities
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        
        <li class="nav-item">
            <a class="nav-link dropdown-toggle" data-toggle="collapse" href="#leaveCollapse" role="button" aria-expanded="false" aria-controls="leaveCollapse">
                <i class="fas fa-calendar-alt"></i> Leave
                <?php if ($sidebar_stats['pending_leaves'] > 0): ?>
                    <span class="badge badge-warning ml-2"><?php echo $sidebar_stats['pending_leaves']; ?></span>
                <?php endif; ?>
            </a>
            <div class="collapse" id="leaveCollapse">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('public_holidays.php'); ?>" href="public_holidays.php">
                            <i class="fas fa-calendar-day"></i> Public Holidays
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('leave_types.php'); ?>" href="leave_types.php">
                            <i class="fas fa-list-alt"></i> Leave Types
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('leave_balances.php'); ?>" href="leave_balances.php">
                            <i class="fas fa-balance-scale"></i> Leave Balances
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('leave_requests.php'); ?>" href="leave_requests.php">
                            <i class="fas fa-paper-plane"></i> Leave Requests
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('shifts.php'); ?>" href="shifts.php">
                            <i class="fas fa-clock"></i> Shifts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('employee_shifts.php'); ?>" href="employee_shifts.php">
                            <i class="fas fa-user-clock"></i> Employee Shifts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('attendance.php'); ?>" href="attendance.php">
                            <i class="fas fa-calendar-check"></i> Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('attendance_summary.php'); ?>" href="attendance_summary.php">
                            <i class="fas fa-clipboard-list"></i> Attendance Summary
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        
        <li class="nav-item">
            <a class="nav-link dropdown-toggle" data-toggle="collapse" href="#exitCollapse" role="button" aria-expanded="false" aria-controls="exitCollapse">
                <i class="fas fa-sign-out-alt"></i> Exit Management
            </a>
            <div class="collapse" id="exitCollapse">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('exits.php'); ?>" href="exits.php">
                            <i class="fas fa-door-open"></i> Exits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('exit_checklist.php'); ?>" href="exit_checklist.php">
                            <i class="fas fa-clipboard-check"></i> Exit Checklist
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('exit_interviews.php'); ?>" href="exit_interviews.php">
                            <i class="fas fa-comments"></i> Exit Interviews
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('knowledge_transfers.php'); ?>" href="knowledge_transfers.php">
                            <i class="fas fa-exchange-alt"></i> Knowledge Transfers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('settlements.php'); ?>" href="settlements.php">
                            <i class="fas fa-money-bill"></i> Settlements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('exit_documents.php'); ?>" href="exit_documents.php">
                            <i class="fas fa-file-alt"></i> Exit Documents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('post_exit_surveys.php'); ?>" href="post_exit_surveys.php">
                            <i class="fas fa-clipboard-list"></i> Post Exit Surveys
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        
        <li class="nav-item">
            <a class="nav-link dropdown-toggle" data-toggle="collapse" href="#recruitmentCollapse" role="button" aria-expanded="false" aria-controls="recruitmentCollapse">
                <i class="fas fa-user-plus"></i> Recruitment
                <?php if ($sidebar_stats['job_openings'] > 0): ?>
                    <span class="badge badge-primary ml-2"><?php echo $sidebar_stats['job_openings']; ?></span>
                <?php endif; ?>
            </a>
            <div class="collapse" id="recruitmentCollapse">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('job_openings.php'); ?>" href="job_openings.php">
                            <i class="fas fa-briefcase"></i> Job Openings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('candidates.php'); ?>" href="candidates.php">
                            <i class="fas fa-user"></i> Candidates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('job_applications.php'); ?>" href="job_applications.php">
                            <i class="fas fa-file-alt"></i> Job Applications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('interview_stages.php'); ?>" href="interview_stages.php">
                            <i class="fas fa-tasks"></i> Interview Stages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('interviews.php'); ?>" href="interviews.php">
                            <i class="fas fa-comments"></i> Interviews
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('job_offers.php'); ?>" href="job_offers.php">
                            <i class="fas fa-file-contract"></i> Job Offers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('recruitment_analytics.php'); ?>" href="recruitment_analytics.php">
                            <i class="fas fa-chart-pie"></i> Recruitment Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('onboarding_tasks.php'); ?>" href="onboarding_tasks.php">
                            <i class="fas fa-tasks"></i> Onboarding Tasks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('employee_onboarding.php'); ?>" href="employee_onboarding.php">
                            <i class="fas fa-user-plus"></i> Employee Onboarding
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('employee_onboarding_tasks.php'); ?>" href="employee_onboarding_tasks.php">
                            <i class="fas fa-tasks"></i> Employee Onboarding Tasks
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        
        <li class="nav-item">
            <a class="nav-link dropdown-toggle" data-toggle="collapse" href="#trainingCollapse" role="button" aria-expanded="false" aria-controls="trainingCollapse">
                <i class="fas fa-graduation-cap"></i> Training
                <?php if ($sidebar_stats['training_sessions'] > 0): ?>
                    <span class="badge badge-success ml-2"><?php echo $sidebar_stats['training_sessions']; ?></span>
                <?php endif; ?>
            </a>
            <div class="collapse" id="trainingCollapse">
                <ul class="nav flex-column pl-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('training_courses.php'); ?>" href="training_courses.php">
                            <i class="fas fa-book"></i> Training Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('trainers.php'); ?>" href="trainers.php">
                            <i class="fas fa-chalkboard-teacher"></i> Trainers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('training_sessions.php'); ?>" href="training_sessions.php">
                            <i class="fas fa-calendar-alt"></i> Training Sessions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('training_enrollments.php'); ?>" href="training_enrollments.php">
                            <i class="fas fa-user-plus"></i> Training Enrollments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('learning_resources.php'); ?>" href="learning_resources.php">
                            <i class="fas fa-book-open"></i> Learning Resources
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('employee_resources.php'); ?>" href="employee_resources.php">
                            <i class="fas fa-user-graduate"></i> Employee Resources
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('skill_matrix.php'); ?>" href="skill_matrix.php">
                            <i class="fas fa-table"></i> Skill Matrix
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('employee_skills.php'); ?>" href="employee_skills.php">
                            <i class="fas fa-user-cog"></i> Employee Skills
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('training_needs_assessment.php'); ?>" href="training_needs_assessment.php">
                            <i class="fas fa-clipboard-list"></i> Training Needs Assessment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('career_paths.php'); ?>" href="career_paths.php">
                            <i class="fas fa-road"></i> Career Paths
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('career_path_stages.php'); ?>" href="career_path_stages.php">
                            <i class="fas fa-route"></i> Career Path Stages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActiveMenu('employee_career_paths.php'); ?>" href="employee_career_paths.php">
                            <i class="fas fa-user-check"></i> Employee Career Paths
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
    <a class="nav-link dropdown-toggle" data-toggle="collapse" href="#userCollapse" role="button" aria-expanded="false" aria-controls="userCollapse">
        <i class="fas fa-user-cog"></i> User Management
    </a>
    <div class="collapse" id="userCollapse">
        <ul class="nav flex-column pl-4">
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu('users.php'); ?>" href="users.php">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu('user_role.php'); ?>" href="user_role.php">
                    <i class="fas fa-user-shield"></i> User Roles
                </a>
            </li>
        </ul>
    </div>
</li>

        
        <li class="nav-item">
            <a class="nav-link <?php echo isActiveMenu('settings.php'); ?>" href="settings.php">
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
/* Additional styles for the enhanced sidebar */
.sidebar {
    background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%) !important;
}
.user-profile-section {
    padding: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.user-avatar {
    margin-bottom: 10px;
}

.quick-stats {
    padding: 10px 15px;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    margin: 0 10px;
}

.stat-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 0.9em;
}

.stat-item:last-child {
    margin-bottom: 0;
}

.stat-item i {
    margin-right: 8px;
    width: 16px;
}

.stat-number {
    font-weight: bold;
    margin-right: 5px;
    color: #fff;
}

.sidebar .nav-link {
    transition: all 0.3s ease;
    border-radius: 5px;
    margin: 2px 5px;
}

.sidebar .nav-link:hover {
    background-color: rgba(255,255,255,0.1);
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    background-color: var(--primary-color);
    color: #fff;
    font-weight: 600;
}

.sidebar .nav-link.active i {
    color: #fff;
}

.badge {
    font-size: 0.7em;
    padding: 0.25em 0.5em;
}

/* Animation for dropdown arrows */
.dropdown-toggle[aria-expanded="true"]::after {
    transform: rotate(180deg);
}

.dropdown-toggle::after {
    transition: transform 0.3s ease;
}

/* Ensure active/focus states use rose, even if cached CSS is stale */
.sidebar .nav-link.active {
    background-color: #E91E63 !important;
}
.sidebar .nav-link:focus,
.sidebar .nav-link:active {
    background-color: rgba(233, 30, 99, 0.18) !important;
    color: #fff !important;
    outline: none;
}
</style>

<script>
// JavaScript to enhance sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Auto-expand current section
    const currentPage = '<?php echo $current_page; ?>';
    const menuItems = document.querySelectorAll('.sidebar .nav-link');
    
    menuItems.forEach(item => {
        if (item.href && item.href.includes(currentPage)) {
            item.classList.add('active');
            // Expand parent dropdown if exists
            const parentCollapse = item.closest('.collapse');
            if (parentCollapse) {
                parentCollapse.classList.add('show');
                const parentToggle = document.querySelector(`[href="#${parentCollapse.id}"]`);
                if (parentToggle) {
                    parentToggle.setAttribute('aria-expanded', 'true');
                }
            }
        }
    });
    
    // Add click animation
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            menuItems.forEach(i => i.classList.remove('active'));
            // Add active class to clicked item
            this.classList.add('active');
        });
    });
});
</script> 