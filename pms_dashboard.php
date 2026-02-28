<?php
// PMS Dashboard - Performance Management System (Enhanced)
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

// Get cycle statistics
function getCycleStats() {
    global $conn;
    try {
        $stats = [];

        // Total cycles
        $stmt = $conn->query("SELECT COUNT(*) as total FROM performance_review_cycles");
        $stats['total_cycles'] = $stmt->fetch()['total'] ?? 0;

        // Active cycles
        $stmt = $conn->query("SELECT COUNT(*) as active FROM performance_review_cycles WHERE status = 'Active'");
        $stats['active_cycles'] = $stmt->fetch()['active'] ?? 0;

        // Completed cycles
        $stmt = $conn->query("SELECT COUNT(*) as completed FROM performance_review_cycles WHERE status = 'Completed'");
        $stats['completed_cycles'] = $stmt->fetch()['completed'] ?? 0;

        // Pending cycles
        $stmt = $conn->query("SELECT COUNT(*) as pending FROM performance_review_cycles WHERE status = 'Pending'");
        $stats['pending_cycles'] = $stmt->fetch()['pending'] ?? 0;

        // Cycles with deadline approaching (within 7 days)
        $stmt = $conn->query("SELECT COUNT(*) as upcoming FROM performance_review_cycles 
            WHERE status = 'Active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        $stats['upcoming_deadlines'] = $stmt->fetch()['upcoming'] ?? 0;

        return $stats;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['total_cycles' => 0, 'active_cycles' => 0, 'completed_cycles' => 0, 'pending_cycles' => 0, 'upcoming_deadlines' => 0];
    }
}

function getReviewStats() {
    global $conn;
    try {
        $stats = [];

        // Total reviews
        $stmt = $conn->query("SELECT COUNT(*) as total FROM performance_reviews");
        $stats['total_reviews'] = $stmt->fetch()['total'] ?? 0;

        // Completed reviews
        $stmt = $conn->query("SELECT COUNT(*) as completed FROM performance_reviews WHERE status = 'Finalized'");
        $stats['completed_reviews'] = $stmt->fetch()['completed'] ?? 0;

        // Pending reviews
        $stmt = $conn->query("SELECT COUNT(*) as pending FROM performance_reviews WHERE status = 'Pending'");
        $stats['pending_reviews'] = $stmt->fetch()['pending'] ?? 0;

        // In Progress reviews
        $stmt = $conn->query("SELECT COUNT(*) as in_progress FROM performance_reviews WHERE status = 'In Progress'");
        $stats['in_progress_reviews'] = $stmt->fetch()['in_progress'] ?? 0;

        // Submitted reviews
        $stmt = $conn->query("SELECT COUNT(*) as submitted FROM performance_reviews WHERE status = 'Submitted'");
        $stats['submitted_reviews'] = $stmt->fetch()['submitted'] ?? 0;

        // Average rating
        $stmt = $conn->query("SELECT AVG(overall_rating) as avg_rating FROM performance_reviews WHERE overall_rating IS NOT NULL");
        $stats['avg_rating'] = round($stmt->fetch()['avg_rating'] ?? 0, 1);

        // Highest rating
        $stmt = $conn->query("SELECT MAX(overall_rating) as max_rating FROM performance_reviews WHERE overall_rating IS NOT NULL");
        $stats['max_rating'] = $stmt->fetch()['max_rating'] ?? 0;

        // Lowest rating
        $stmt = $conn->query("SELECT MIN(overall_rating) as min_rating FROM performance_reviews WHERE overall_rating IS NOT NULL AND overall_rating > 0");
        $stats['min_rating'] = $stmt->fetch()['min_rating'] ?? 0;

        return $stats;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['total_reviews' => 0, 'completed_reviews' => 0, 'pending_reviews' => 0, 'in_progress_reviews' => 0, 'submitted_reviews' => 0, 'avg_rating' => 0, 'max_rating' => 0, 'min_rating' => 0];
    }
}

// Get rating distribution
function getRatingDistribution() {
    global $conn;
    try {
        $distribution = [];
        
        // Rating 5
        $stmt = $conn->query("SELECT COUNT(*) as count FROM performance_reviews WHERE overall_rating = 5");
        $distribution[5] = $stmt->fetch()['count'] ?? 0;
        
        // Rating 4
        $stmt = $conn->query("SELECT COUNT(*) as count FROM performance_reviews WHERE overall_rating = 4");
        $distribution[4] = $stmt->fetch()['count'] ?? 0;
        
        // Rating 3
        $stmt = $conn->query("SELECT COUNT(*) as count FROM performance_reviews WHERE overall_rating = 3");
        $distribution[3] = $stmt->fetch()['count'] ?? 0;
        
        // Rating 2
        $stmt = $conn->query("SELECT COUNT(*) as count FROM performance_reviews WHERE overall_rating = 2");
        $distribution[2] = $stmt->fetch()['count'] ?? 0;
        
        // Rating 1
        $stmt = $conn->query("SELECT COUNT(*) as count FROM performance_reviews WHERE overall_rating = 1");
        $distribution[1] = $stmt->fetch()['count'] ?? 0;
        
        return $distribution;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    }
}

// Get competency statistics
function getCompetencyStats() {
    global $conn;
    try {
        $stats = [];

        // Total competencies
        $stmt = $conn->query("SELECT COUNT(*) as total FROM competencies");
        $stats['total_competencies'] = $stmt->fetch()['total'] ?? 0;

        // Total assigned
        $stmt = $conn->query("SELECT COUNT(*) as assigned FROM employee_competencies");
        $stats['assigned_competencies'] = $stmt->fetch()['assigned'] ?? 0;

        // Unique employees with competencies
        $stmt = $conn->query("SELECT COUNT(DISTINCT employee_id) as employees_with_competencies FROM employee_competencies");
        $stats['employees_with_competencies'] = $stmt->fetch()['employees_with_competencies'] ?? 0;

        // Average competency rating
        $stmt = $conn->query("SELECT AVG(rating) as avg_rating FROM employee_competencies WHERE rating IS NOT NULL");
        $stats['avg_competency_rating'] = round($stmt->fetch()['avg_rating'] ?? 0, 1);

        // Competencies by category
        $stmt = $conn->query("SELECT category, COUNT(*) as count FROM competencies GROUP BY category");
        $stats['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['total_competencies' => 0, 'assigned_competencies' => 0, 'employees_with_competencies' => 0, 'avg_competency_rating' => 0, 'by_category' => []];
    }
}

// Get top performers
function getTopPerformers($limit = 5) {
    global $conn;
    try {
        $stmt = $conn->query("
            SELECT pr.pr_id, pr.overall_rating, pr.status, pr.review_date,
                   ep.employee_id, pi.first_name, pi.last_name, jr.title as job_title, d.department_name
            FROM performance_reviews pr
            JOIN employee_profiles ep ON pr.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE pr.overall_rating >= 4
            ORDER BY pr.overall_rating DESC, pr.review_date DESC
            LIMIT $limit
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

// Get needs improvement employees
function getNeedsImprovement($limit = 5) {
    global $conn;
    try {
        $stmt = $conn->query("
            SELECT pr.pr_id, pr.overall_rating, pr.status, pr.review_date,
                   ep.employee_id, pi.first_name, pi.last_name, jr.title as job_title, d.department_name
            FROM performance_reviews pr
            JOIN employee_profiles ep ON pr.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE pr.overall_rating <= 2 AND pr.overall_rating > 0
            ORDER BY pr.overall_rating ASC, pr.review_date DESC
            LIMIT $limit
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

// Get reviews by department
function getReviewsByDepartment() {
    global $conn;
    try {
        $stmt = $conn->query("
            SELECT d.department_name, 
                   COUNT(pr.pr_id) as total_reviews,
                   AVG(pr.overall_rating) as avg_rating,
                   SUM(CASE WHEN pr.status = 'Finalized' THEN 1 ELSE 0 END) as completed
            FROM performance_reviews pr
            JOIN employee_profiles ep ON pr.employee_id = ep.employee_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            GROUP BY d.department_name
            ORDER BY total_reviews DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

function getRecentCycles() {
    global $conn;
    try {
        $stmt = $conn->query("
            SELECT cycle_id, cycle_name, start_date, end_date, status,
                   (SELECT COUNT(*) FROM performance_reviews WHERE cycle_id = performance_review_cycles.cycle_id) as review_count
            FROM performance_review_cycles
            ORDER BY start_date DESC
            LIMIT 5
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

function getRecentReviews() {
    global $conn;
    try {
        $stmt = $conn->query("
            SELECT pr.pr_id, pr.overall_rating, pr.status, pr.review_date,
                   ep.employee_id, pi.first_name, pi.last_name, jr.title as job_title
            FROM performance_reviews pr
            JOIN employee_profiles ep ON pr.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            ORDER BY pr.review_date DESC
            LIMIT 10
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

// Get employee count for competency coverage
function getEmployeeCount() {
    global $conn;
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM employee_profiles WHERE employment_status = 'Active'");
        return $stmt->fetch()['total'] ?? 0;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return 0;
    }
}

// Get goals statistics
function getGoalsStats() {
    global $conn;
    try {
        $stats = [];
        
        // Check if goal_tracking table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'goal_tracking'");
        if ($stmt->rowCount() == 0) {
            return ['total_goals' => 0, 'completed_goals' => 0, 'in_progress_goals' => 0, 'pending_goals' => 0];
        }
        
        $stmt = $conn->query("SELECT COUNT(*) as total FROM goal_tracking");
        $stats['total_goals'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $conn->query("SELECT COUNT(*) as completed FROM goal_tracking WHERE status = 'Completed'");
        $stats['completed_goals'] = $stmt->fetch()['completed'] ?? 0;
        
        $stmt = $conn->query("SELECT COUNT(*) as in_progress FROM goal_tracking WHERE status = 'In Progress'");
        $stats['in_progress_goals'] = $stmt->fetch()['in_progress'] ?? 0;
        
        $stmt = $conn->query("SELECT COUNT(*) as pending FROM goal_tracking WHERE status = 'Pending'");
        $stats['pending_goals'] = $stmt->fetch()['pending'] ?? 0;
        
        return $stats;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return ['total_goals' => 0, 'completed_goals' => 0, 'in_progress_goals' => 0, 'pending_goals' => 0];
    }
}

$cycleStats = getCycleStats();
$reviewStats = getReviewStats();
$competencyStats = getCompetencyStats();
$recentCycles = getRecentCycles();
$recentReviews = getRecentReviews();
$ratingDistribution = getRatingDistribution();
$topPerformers = getTopPerformers();
$needsImprovement = getNeedsImprovement();
$reviewsByDept = getReviewsByDepartment();
$employeeCount = getEmployeeCount();
$goalsStats = getGoalsStats();

// Calculate competency coverage
$competencyCoverage = $employeeCount > 0 ? round(($competencyStats['employees_with_competencies'] / $employeeCount) * 100) : 0;

// Calculate review completion rate
$reviewCompletionRate = $reviewStats['total_reviews'] > 0 ? round(($reviewStats['completed_reviews'] / $reviewStats['total_reviews']) * 100) : 0;

// Calculate goal completion rate
$goalCompletionRate = $goalsStats['total_goals'] > 0 ? round(($goalsStats['completed_goals'] / $goalsStats['total_goals']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS Dashboard - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title {
            color: var(--primary-color);
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

        .pms-menu-item {
            display: block;
            text-decoration: none;
            color: #333;
            padding: 15px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--border-light);
            transition: all 0.3s;
            text-align: center;
        }

        .pms-menu-item:hover {
            background: var(--primary-pale);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            text-decoration: none;
            color: #333;
        }

        .pms-menu-item i {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 8px;
            display: block;
        }

        .pms-menu-item h6 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .pms-stats-card {
            text-align: center;
            background: linear-gradient(145deg, var(--bg-card) 0%, var(--bg-secondary) 100%);
            border: 1px solid var(--border-light);
        }

        .pms-stats-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .pms-stats-card h3 {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .pms-stats-card p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin: 0;
        }

        .pms-progress {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: 8px;
            padding: 25px;
            color: white;
        }

        .pms-progress h2 {
            font-size: 3rem;
            font-weight: 700;
            margin: 0;
        }

        .pms-progress p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        /* Enhanced card styles */
        .stats-highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .stats-highlight h4 {
            margin: 0;
            font-weight: 700;
        }

        .stats-highlight p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .rating-bar {
            height: 25px;
            border-radius: 12px;
            background: #e9ecef;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .rating-bar-fill {
            height: 100%;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            transition: width 0.5s ease;
        }

        .rating-5 { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .rating-4 { background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); }
        .rating-3 { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
        .rating-2 { background: linear-gradient(135deg, #fd7e14 0%, #dc3545 100%); }
        .rating-1 { background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%); }

        .badge-rating {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        .badge-excellent { background: #d4edda; color: #155724; }
        .badge-good { background: #d1ecf1; color: #0c5460; }
        .badge-average { background: #fff3cd; color: #856404; }
        .badge-poor { background: #f8d7da; color: #721c24; }

        .dept-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid var(--border-light);
            transition: all 0.3s;
        }

        .dept-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .dept-card h6 {
            margin: 0 0 10px 0;
            color: var(--primary-color);
            font-weight: 600;
        }

        .performer-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .improvement-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #dc3545;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .performer-info h6 {
            margin: 0;
            font-weight: 600;
        }

        .performer-info small {
            color: #6c757d;
        }

        .completion-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .completion-high { background: #d4edda; color: #155724; }
        .completion-medium { background: #fff3cd; color: #856404; }
        .completion-low { background: #f8d7da; color: #721c24; }

        .category-tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-right: 5px;
        }

        .category-core { background: #e7f1ff; color: #004085; }
        .category-technical { background: #d4edda; color: #155724; }
        .category-leadership { background: #fff3cd; color: #856404; }
        .category-other { background: #e2e3e5; color: #383d41; }

        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
        }

        .card {
            border-radius: 10px;
            border: 1px solid var(--border-light);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid var(--border-light);
            font-weight: 600;
            color: var(--primary-color);
        }

        .quick-action-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <?php include 'navigation.php'; ?>
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
                <h2 class="section-title">
                    <i class="fas fa-chart-line mr-2"></i>
                    Performance Management System Dashboard
                </h2>

                <!-- Quick Links -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <a href="performance_review_cycles.php" class="pms-menu-item">
                            <i class="fas fa-calendar-alt"></i>
                            <h6>Review Cycles</h6>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="performance_reviews.php" class="pms-menu-item">
                            <i class="fas fa-clipboard-check"></i>
                            <h6>Reviews</h6>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="competencies.php" class="pms-menu-item">
                            <i class="fas fa-star"></i>
                            <h6>Competencies</h6>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="goals.php" class="pms-menu-item">
                            <i class="fas fa-bullseye"></i>
                            <h6>Goals</h6>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="performance_metrics.php" class="pms-menu-item">
                            <i class="fas fa-chart-bar"></i>
                            <h6>Metrics</h6>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="employee_competencies.php" class="pms-menu-item">
                            <i class="fas fa-users"></i>
                            <h6>Employee Competencies</h6>
                        </a>
                    </div>
                </div>

                <!-- Enhanced Statistics Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card pms-stats-card">
                            <div class="card-body">
                                <i class="fas fa-calendar-check"></i>
                                <h3><?php echo $cycleStats['total_cycles']; ?></h3>
                                <p>Total Cycles</p>
                                <?php if($cycleStats['upcoming_deadlines'] > 0): ?>
                                    <span class="completion-badge completion-medium">
                                        <i class="fas fa-exclamation-triangle"></i> <?php echo $cycleStats['upcoming_deadlines']; ?> deadline<?php echo $cycleStats['upcoming_deadlines'] > 1 ? 's' : ''; ?> soon
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card pms-stats-card">
                            <div class="card-body">
                                <i class="fas fa-check-circle"></i>
                                <h3><?php echo $cycleStats['active_cycles']; ?></h3>
                                <p>Active Cycles</p>
                                <small class="text-muted"><?php echo $cycleStats['pending_cycles']; ?> pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card pms-stats-card">
                            <div class="card-body">
                                <i class="fas fa-clock"></i>
                                <h3><?php echo $reviewStats['pending_reviews']; ?></h3>
                                <p>Pending Reviews</p>
                                <small class="text-muted"><?php echo $reviewStats['in_progress_reviews']; ?> in progress</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card pms-stats-card">
                            <div class="card-body">
                                <i class="fas fa-star"></i>
                                <h3><?php echo $reviewStats['avg_rating']; ?>/5</h3>
                                <p>Avg Rating</p>
                                <small class="text-muted">Range: <?php echo $reviewStats['min_rating']; ?>-<?php echo $reviewStats['max_rating']; ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Statistics Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-highlight">
                            <h4><?php echo $competencyStats['total_competencies']; ?></h4>
                            <p>Total Competencies</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-highlight" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h4><?php echo $competencyCoverage; ?>%</h4>
                            <p>Competency Coverage</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-highlight" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h4><?php echo count($topPerformers); ?></h4>
                            <p>Top Performers</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-highlight" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <h4><?php echo $goalsStats['total_goals']; ?></h4>
                            <p>Total Goals Tracked</p>
                        </div>
                    </div>
                </div>

                <!-- Progress and Competency Overview -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-line mr-2"></i>
                                Reviews Completion
                            </div>
                            <div class="card-body">
                                <div class="pms-progress">
                                    <h2><?php echo $reviewCompletionRate; ?>%</h2>
                                    <p>Reviews Completed (<?php echo $reviewStats['completed_reviews']; ?> of <?php echo $reviewStats['total_reviews']; ?>)</p>
                                </div>
                                <div class="progress mt-3" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $reviewCompletionRate; ?>%; background: var(--primary-color);"></div>
                                </div>
                                <div class="row mt-3 text-center">
                                    <div class="col-4">
                                        <span class="text-success font-weight-bold"><?php echo $reviewStats['completed_reviews']; ?></span>
                                        <br><small class="text-muted">Completed</small>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-warning font-weight-bold"><?php echo $reviewStats['pending_reviews']; ?></span>
                                        <br><small class="text-muted">Pending</small>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-info font-weight-bold"><?php echo $reviewStats['in_progress_reviews']; ?></span>
                                        <br><small class="text-muted">In Progress</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-pie mr-2"></i>
                                Competency Overview
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <h3 class="text-primary"><?php echo $competencyStats['total_competencies']; ?></h3>
                                        <p class="text-muted">Total Competencies</p>
                                    </div>
                                    <div class="col-6">
                                        <h3 class="text-success"><?php echo $competencyStats['assigned_competencies']; ?></h3>
                                        <p class="text-muted">Assignments</p>
                                    </div>
                                </div>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h3 class="text-info"><?php echo $competencyStats['employees_with_competencies']; ?></h3>
                                        <p class="text-muted">Employees Assessed</p>
                                    </div>
                                    <div class="col-6">
                                        <h3 class="text-warning"><?php echo $competencyStats['avg_competency_rating']; ?></h3>
                                        <p class="text-muted">Avg Rating</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="category-breakdown">
                                    <p class="text-muted mb-2"><strong>By Category:</strong></p>
                                    <?php if(count($competencyStats['by_category']) > 0): ?>
                                        <?php foreach($competencyStats['by_category'] as $cat): ?>
                                            <span class="category-tag <?php echo 'category-' . strtolower($cat['category']); ?>">
                                                <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <small class="text-muted">No categories defined</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-bullseye mr-2"></i>
                                Goals Progress
                            </div>
                            <div class="card-body">
                                <div class="pms-progress" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                    <h2><?php echo $goalCompletionRate; ?>%</h2>
                                    <p>Goals Completed (<?php echo $goalsStats['completed_goals']; ?> of <?php echo $goalsStats['total_goals']; ?>)</p>
                                </div>
                                <div class="progress mt-3" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $goalCompletionRate; ?>%; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);"></div>
                                </div>
                                <div class="row mt-3 text-center">
                                    <div class="col-4">
                                        <span class="text-success font-weight-bold"><?php echo $goalsStats['completed_goals']; ?></span>
                                        <br><small class="text-muted">Completed</small>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-warning font-weight-bold"><?php echo $goalsStats['in_progress_goals']; ?></span>
                                        <br><small class="text-muted">In Progress</small>
                                    </div>
                                    <div class="col-4">
                                        <span class="text-secondary font-weight-bold"><?php echo $goalsStats['pending_goals']; ?></span>
                                        <br><small class="text-muted">Pending</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rating Distribution and Top Performers -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-bar mr-2"></i>
                                Rating Distribution
                            </div>
                            <div class="card-body">
                                <?php 
                                $totalRatings = array_sum($ratingDistribution);
                                $maxRating = max($ratingDistribution);
                                ?>
                                <?php for($i = 5; $i >= 1; $i--): ?>
                                    <div class="rating-row">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span><?php echo $i; ?> <span class="text-warning">★</span></span>
                                            <small class="text-muted"><?php echo $ratingDistribution[$i]; ?> reviews</small>
                                        </div>
                                        <div class="rating-bar">
                                            <div class="rating-bar-fill rating-<?php echo $i; ?>" 
                                                 style="width: <?php echo $maxRating > 0 ? ($ratingDistribution[$i] / $maxRating) * 100 : 0; ?>%">
                                                <?php echo $totalRatings > 0 ? round(($ratingDistribution[$i] / $totalRatings) * 100) : 0; ?>%
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-trophy mr-2"></i>
                                Top Performers
                            </div>
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php if(count($topPerformers) > 0): ?>
                                    <?php foreach($topPerformers as $performer): ?>
                                        <div class="performer-card">
                                            <div class="performer-info">
                                                <h6><?php echo htmlspecialchars(($performer['first_name'] ?? '') . ' ' . ($performer['last_name'] ?? '')); ?></h6>
                                                <small><?php echo htmlspecialchars($performer['job_title'] ?? 'N/A'); ?></small>
                                            </div>
                                            <div class="text-right">
                                                <span class="badge-rating badge-excellent">
                                                    <?php echo number_format($performer['overall_rating'], 1); ?> ★
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-center text-muted">No top performers yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                Recent Review Cycles
                            </div>
                            <div class="card-body">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Cycle Name</th>
                                            <th>Reviews</th>
                                            <th>Dates</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($recentCycles) > 0): ?>
                                            <?php foreach ($recentCycles as $cycle): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($cycle['cycle_name']); ?></strong></td>
                                                    <td><?php echo $cycle['review_count']; ?></td>
                                                    <td><?php echo date('M d', strtotime($cycle['start_date'])) . ' - ' . date('M d, Y', strtotime($cycle['end_date'])); ?></td>
                                                    <td>
                                                        <?php
                                                            $statusClass = strtolower($cycle['status']) === 'active' ? 'success' : (strtolower($cycle['status']) === 'completed' ? 'info' : 'warning');
                                                        ?>
                                                        <span class="badge badge-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($cycle['status']); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No cycles found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-clipboard-list mr-2"></i>
                                Recent Performance Reviews
                            </div>
                            <div class="card-body">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Rating</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($recentReviews) > 0): ?>
                                            <?php foreach ($recentReviews as $review): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')); ?></strong></td>
                                                    <td>
                                                        <?php if ($review['overall_rating']): ?>
                                                            <?php 
                                                                $rating = round($review['overall_rating']);
                                                                $ratingClass = $rating >= 4 ? 'excellent' : ($rating >= 3 ? 'good' : ($rating >= 2 ? 'average' : 'poor'));
                                                            ?>
                                                            <span class="badge-rating badge-<?php echo $ratingClass; ?>">
                                                                <?php echo str_repeat('★', $rating); ?>
                                                                (<?php echo number_format($review['overall_rating'], 1); ?>)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                            $statusClass = strtolower($review['status']) === 'finalized' ? 'success' : (strtolower($review['status']) === 'submitted' ? 'info' : 'warning');
                                                        ?>
                                                        <span class="badge badge-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($review['status']); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No reviews found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Overview -->
                <?php if(count($reviewsByDept) > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-building mr-2"></i>
                                Performance by Department
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach($reviewsByDept as $dept): ?>
                                        <div class="col-md-4">
                                            <div class="dept-card">
                                                <h6><?php echo htmlspecialchars($dept['department_name'] ?? 'Unknown'); ?></h6>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <small class="text-muted">Total Reviews: <?php echo $dept['total_reviews']; ?></small>
                                                    <small class="text-muted">Completed: <?php echo $dept['completed']; ?></small>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <?php 
                                                    $deptCompletion = $dept['total_reviews'] > 0 ? ($dept['completed'] / $dept['total_reviews']) * 100 : 0;
                                                    $deptRating = $dept['avg_rating'] ? number_format($dept['avg_rating'], 1) : 'N/A';
                                                    ?>
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $deptCompletion; ?>%"></div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="completion-badge <?php echo $deptCompletion >= 80 ? 'completion-high' : ($deptCompletion >= 50 ? 'completion-medium' : 'completion-low'); ?>">
                                                        <?php echo round($deptCompletion); ?>% Complete
                                                    </span>
                                                    <span class="ml-2 text-muted">
                                                        <i class="fas fa-star text-warning"></i> <?php echo $deptRating; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-bolt mr-2"></i>
                                Quick Actions
                            </div>
                            <div class="card-body text-center">
                                <a href="performance_review_cycles.php" class="btn btn-primary quick-action-btn mr-2 mb-2">
                                    <i class="fas fa-plus mr-1"></i> Create New Cycle
                                </a>
                                <a href="competencies.php" class="btn btn-success quick-action-btn mr-2 mb-2">
                                    <i class="fas fa-star mr-1"></i> Add Competency
                                </a>
                                <a href="performance_metrics.php" class="btn btn-info quick-action-btn mr-2 mb-2">
                                    <i class="fas fa-chart-line mr-1"></i> View Metrics
                                </a>
                                <a href="goals.php" class="btn btn-warning quick-action-btn mr-2 mb-2" style="color: white;">
                                    <i class="fas fa-bullseye mr-1"></i> Manage Goals
                                </a>
                                <a href="export_review_report.php" class="btn btn-secondary quick-action-btn mb-2">
                                    <i class="fas fa-download mr-1"></i> Export Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
