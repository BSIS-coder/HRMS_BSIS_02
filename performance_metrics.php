<?php
// Start session
session_start();

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include configuration (creates PDO connection)
require_once 'config.php';

// Verify connection (optional)
try {
    $conn->query("SELECT 1");
} catch (PDOException $e) {
    error_log("Database check failed: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// ---------- Performance Metrics (PDO version) ----------

function getAveragePerformanceRating() {
    global $conn;
    try {
        $stmt = $conn->query("SELECT AVG(overall_rating) AS avg_rating FROM performance_reviews");
        $row = $stmt->fetch();
        return round($row['avg_rating'], 1) ?: 0;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return 0;
    }
}

function getCompletedGoalsCount() {
    global $conn;
    try {
        $stmt = $conn->query("
            SELECT (COUNT(CASE WHEN status = 'Completed' THEN 1 END) * 100.0 / COUNT(*)) AS rate
            FROM goals
        ");
        $row = $stmt->fetch();
        return round($row['rate']) ?: 0;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return 0;
    }
}

function getTotalGoalsCount() {
    global $conn;
    try {
        $stmt = $conn->query("SELECT COUNT(*) AS total FROM goals");
        $row = $stmt->fetch();
        return $row['total'] ?: 0;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return 0;
    }
}

function getTopPerformers() {
    global $conn;
    try {
        $sql = "
            SELECT CONCAT(e.first_name, ' ', e.last_name) AS name, 
                   pr.overall_rating AS rating, 
                   d.department_name AS department
            FROM performance_reviews pr
            JOIN employees e ON pr.employee_id = e.employee_id
            JOIN job_roles jr ON e.job_role_id = jr.job_role_id
            JOIN departments d ON jr.department = d.department_name
            ORDER BY pr.overall_rating DESC
            LIMIT 5
        ";
        $stmt = $conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

function getDepartmentPerformance() {
    global $conn;
    try {
        $sql = "
            SELECT
                d.department_name AS department,
                COALESCE(AVG(pr.overall_rating), 0) AS avg_rating,
                COALESCE(SUM(CASE WHEN g.status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed_goals,
                COUNT(DISTINCT e.employee_id) AS employee_count
            FROM departments d
            LEFT JOIN job_roles jr ON d.department_name = jr.department
            LEFT JOIN employees e ON jr.job_role_id = e.job_role_id
            LEFT JOIN performance_reviews pr ON e.employee_id = pr.employee_id
            LEFT JOIN goals g ON e.employee_id = g.employee_id
            GROUP BY d.department_name
        ";
        $stmt = $conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($row) {
            return [
                'department' => $row['department'],
                'avg_rating' => round($row['avg_rating'], 1),
                'completed_goals' => (int)$row['completed_goals'],
                'employee_count' => (int)$row['employee_count']
            ];
        }, $rows);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

// Sample static metrics
function getPerformanceTrendData() {
    return [
        ['month' => 'Jan', 'rating' => 3.8],
        ['month' => 'Feb', 'rating' => 4.0],
        ['month' => 'Mar', 'rating' => 4.1],
        ['month' => 'Apr', 'rating' => 4.2],
        ['month' => 'May', 'rating' => 4.3],
        ['month' => 'Jun', 'rating' => 4.2]
    ];
}

function getTrainingCompletionRate() {
    global $conn;
    try {
        // Total training activities
        $totalStmt = $conn->query("SELECT COUNT(*) FROM development_activities WHERE activity_type = 'Training'");
        $total = (int)$totalStmt->fetchColumn();

        if ($total === 0) {
            return 0;
        }

        // Completed training activities
        $completedStmt = $conn->query("SELECT COUNT(*) FROM development_activities WHERE activity_type = 'Training' AND status = 'Completed'");
        $completed = (int)$completedStmt->fetchColumn();

        return (int) round(($completed * 100.0) / $total);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return 0;
    }
}

function getEmployeeEngagementScore() {
    global $conn;
    try {
        // Pull all feedback responses (JSON stored in `responses` column)
        $sql = "SELECT responses FROM feedback_responses WHERE responses IS NOT NULL";
        $stmt = $conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $sum = 0.0;
        $count = 0;

        foreach ($rows as $json) {
            $responses = json_decode($json, true);
            if (!is_array($responses)) continue;

            foreach ($responses as $value) {
                if (is_numeric($value)) {
                    $sum += (float)$value;
                    $count++;
                }
            }
        }

        if ($count === 0) return 0;

        // Average rating on a 1-5 scale -> convert to percentage
        $avg = $sum / $count;
        return (int) round(($avg / 5.0) * 100);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Metrics - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/font-awesome.min.css">
    <link rel="stylesheet" href="styles.css?v=performance">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Additional custom styles for performance metrics */
        .metric-card {
            background: linear-gradient(135deg, #ffffff 0%, #fce4ec 100%);
            border: 1px solid #f8bbd0;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.2);
        }

        .metric-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin: 0 auto 15px;
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #e91e63;
            margin-bottom: 5px;
        }

        .metric-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .trend-indicator {
            display: inline-flex;
            align-items: center;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-top: 8px;
        }

        .trend-up {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .trend-down {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        .performance-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .performance-table th {
            background: linear-gradient(135deg, #e91e63 0%, #c2185b 100%);
            color: white;
            border: none;
            padding: 15px;
            font-weight: 600;
        }

        .performance-table td {
            padding: 12px 15px;
            border-color: #f8bbd0;
        }

        .rating-stars {
            color: #ffc107;
            font-size: 1.1rem;
        }

        .department-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .dept-it { background-color: rgba(0, 123, 255, 0.1); color: #007bff; }
        .dept-hr { background-color: rgba(233, 30, 99, 0.1); color: #e91e63; }
        .dept-finance { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
        .dept-marketing { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .dept-sales { background-color: rgba(23, 162, 184, 0.1); color: #17a2b8; }

        .progress-circle {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }

        .progress-circle svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .progress-circle circle {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
        }

        .progress-bg {
            stroke: #f8bbd0;
        }

        .progress-fill {
            stroke: #e91e63;
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 1s ease-in-out;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            font-weight: 700;
            color: #e91e63;
        }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .animate-on-scroll.animate {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .metric-value {
                font-size: 2rem;
            }

            .chart-container {
                height: 250px;
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
                <h2 class="section-title">
                    <i class="fas fa-chart-line mr-2"></i>
                    Performance Metrics Dashboard
                </h2>

                <!-- Key Performance Indicators -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card metric-card animate-on-scroll">
                            <div class="card-body text-center">
                                <div class="metric-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="metric-value"><?php echo number_format(getAveragePerformanceRating(), 1); ?></div>
                                <div class="metric-label">Average Rating</div>
                                <div class="trend-indicator trend-up">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    +0.3
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card metric-card animate-on-scroll">
                            <div class="card-body text-center">
                                <div class="metric-icon">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="metric-value"><?php echo getCompletedGoalsCount(); ?>%</div>
                                <div class="metric-label">Goals Completed</div>
                                <div class="trend-indicator trend-up">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    +5%
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card metric-card animate-on-scroll">
                            <div class="card-body text-center">
                                <div class="metric-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="metric-value"><?php echo getTrainingCompletionRate(); ?>%</div>
                                <div class="metric-label">Training Completion</div>
                                <div class="trend-indicator trend-up">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    +8%
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card metric-card animate-on-scroll">
                            <div class="card-body text-center">
                                <div class="metric-icon">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="metric-value"><?php echo getEmployeeEngagementScore(); ?>%</div>
                                <div class="metric-label">Employee Engagement</div>
                                <div class="trend-indicator trend-down">
                                    <i class="fas fa-arrow-down mr-1"></i>
                                    -2%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-4">
                        <div class="card animate-on-scroll">
                            <div class="card-header">
                                <i class="fas fa-chart-line mr-2"></i>
                                Performance Trend (Last 6 Months)
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="performanceTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card animate-on-scroll">
                            <div class="card-header">
                                <i class="fas fa-chart-pie mr-2"></i>
                                Goal Completion Rate
                            </div>
                            <div class="card-body text-center">
                                <div class="progress-circle">
                                    <svg>
                                        <circle class="progress-bg" cx="60" cy="60" r="45"></circle>
                                        <circle class="progress-fill" cx="60" cy="60" r="45" style="stroke-dashoffset: <?php echo 283 - (283 * getCompletedGoalsCount() / 100); ?>"></circle>
                                    </svg>
                                    <div class="progress-text"><?php echo getCompletedGoalsCount(); ?>%</div>
                                </div>
                                <p class="mt-3 text-muted">
                                    <?php echo getCompletedGoalsCount(); ?> of <?php echo getTotalGoalsCount(); ?> goals completed
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Performance and Top Performers -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="card animate-on-scroll">
                            <div class="card-header">
                                <i class="fas fa-building mr-2"></i>
                                Department Performance
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="departmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card animate-on-scroll">
                            <div class="card-header">
                                <i class="fas fa-medal mr-2"></i>
                                Top Performers
                            </div>
                            <div class="card-body">
                                <div class="performance-table">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Rating</th>
                                                <th>Department</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $topPerformers = getTopPerformers();
                                            foreach ($topPerformers as $performer) {
                                                $deptClass = 'dept-' . strtolower(str_replace(' ', '', $performer['department']));
                                                echo "<tr>";
                                                echo "<td><strong>" . htmlspecialchars($performer['name']) . "</strong></td>";
                                                echo "<td><div class='rating-stars'>" . str_repeat('★', floor($performer['rating'])) . "</div></td>";
                                                echo "<td><span class='department-badge " . $deptClass . "'>" . htmlspecialchars($performer['department']) . "</span></td>";
                                                echo "</tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Department Metrics -->
                <div class="row">
                    <div class="col-12">
                        <div class="card animate-on-scroll">
                            <div class="card-header">
                                <i class="fas fa-table mr-2"></i>
                                Department-wise Performance Details
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table performance-table">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Average Rating</th>
                                                <th>Goals Completed</th>
                                                <th>Training Completion</th>
                                                <th>Employee Count</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $departments = getDepartmentPerformance();
                                            foreach ($departments as $dept) {
                                                $statusClass = $dept['avg_rating'] >= 4.0 ? 'success' : ($dept['avg_rating'] >= 3.5 ? 'warning' : 'danger');
                                                $statusText = $dept['avg_rating'] >= 4.0 ? 'Excellent' : ($dept['avg_rating'] >= 3.5 ? 'Good' : 'Needs Improvement');

                                                echo "<tr>";
                                                echo "<td><strong>" . htmlspecialchars($dept['department']) . "</strong></td>";
                                                echo "<td><span class='badge badge-" . $statusClass . "'>" . number_format($dept['avg_rating'], 1) . "</span></td>";
                                                echo "<td>" . $dept['completed_goals'] . "</td>";
                                                echo "<td>" . rand(75, 95) . "%</td>";
                                                echo "<td>" . rand(8, 25) . "</td>";
                                                echo "<td><span class='badge badge-" . $statusClass . "'>" . $statusText . "</span></td>";
                                                echo "</tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        // Performance Trend Chart
        const trendData = <?php echo json_encode(getPerformanceTrendData()); ?>;
        const trendCtx = document.getElementById('performanceTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(item => item.month),
                datasets: [{
                    label: 'Average Performance Rating',
                    data: trendData.map(item => item.rating),
                    borderColor: '#e91e63',
                    backgroundColor: 'rgba(233, 30, 99, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#e91e63',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 3.5,
                        max: 5.0,
                        grid: {
                            color: 'rgba(233, 30, 99, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(233, 30, 99, 0.1)'
                        }
                    }
                }
            }
        });

        // Department Performance Chart
        const deptData = <?php echo json_encode(getDepartmentPerformance()); ?>;
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: deptData.map(item => item.department),
                datasets: [{
                    label: 'Average Rating',
                    data: deptData.map(item => item.avg_rating),
                    backgroundColor: [
                        'rgba(233, 30, 99, 0.8)',
                        'rgba(0, 188, 212, 0.8)',
                        'rgba(76, 175, 80, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(156, 39, 176, 0.8)'
                    ],
                    borderColor: [
                        '#e91e63',
                        '#00bcd4',
                        '#4caf50',
                        '#ffc107',
                        '#9c27b0'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5.0,
                        grid: {
                            color: 'rgba(233, 30, 99, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(233, 30, 99, 0.1)'
                        }
                    }
                }
            }
        });

        // Animation on scroll
        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate-on-scroll');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;

                if (elementTop < windowHeight - 50) {
                    element.classList.add('animate');
                }
            });
        }

        // Initial check
        animateOnScroll();

        // Check on scroll
        window.addEventListener('scroll', animateOnScroll);

        // Smooth progress animation
        document.addEventListener('DOMContentLoaded', function() {
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                setTimeout(() => {
                    progressFill.style.strokeDashoffset = '<?php echo 283 - (283 * getCompletedGoalsCount() / 100); ?>';
                }, 500);
            }
        });
    </script>
</body>
</html>
