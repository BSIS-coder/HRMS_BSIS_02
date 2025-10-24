<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for index page */
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">HR Dashboard Overview</h2>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <i class="fas fa-users"></i>
                                <h6 class="text-muted">Total Employees</h6>
                                <h3 class="card-title">
                                    <?php
                                    echo getTotalEmployees();
                                    ?>
                                </h3>
                                <p class="text-success"><i class="fas fa-arrow-up"></i> 5% from last month</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <i class="fas fa-building"></i>
                                <h6 class="text-muted">Departments</h6>
                                <h3 class="card-title">
                                    <?php echo getTotalDepartments(); ?>
                                </h3>
                                <p class="text-muted">Active departments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <i class="fas fa-briefcase"></i>
                                <h6 class="text-muted">Active Jobs</h6>
                                <h3 class="card-title">
                                    <?php echo getActiveJobOpenings(); ?>
                                </h3>
                                <p class="text-primary">12 applications pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <i class="fas fa-graduation-cap"></i>
                                <h6 class="text-muted">Training Sessions</h6>
                                <h3 class="card-title">
                                    <?php echo getUpcomingTrainingSessions(); ?>
                                </h3>
                                <p class="text-info">Next session in 2 days</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-line mr-2"></i>
                                Performance Overview
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <h6 class="text-muted">Completed Reviews</h6>
                                        <h4>24</h4>
                                        <small class="text-success">+8 this month</small>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted">Pending Reviews</h6>
                                        <h4>12</h4>
                                        <small class="text-warning">Due this week</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <h6 class="text-muted">Average Rating</h6>
                                        <h4>4.2/5</h4>
                                        <small class="text-success">↑ 0.3 from last cycle</small>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted">Top Performers</h6>
                                        <h4>15</h4>
                                        <small class="text-muted">Exceeded goals</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                Leave Statistics
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <h6 class="text-muted">On Leave Today</h6>
                                        <h4>5</h4>
                                        <small class="text-muted">3 Planned, 2 Sick</small>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted">Pending Requests</h6>
                                        <h4>8</h4>
                                        <small class="text-warning">Requires approval</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <h6 class="text-muted">Average Leave Balance</h6>
                                        <h4>12.5 days</h4>
                                        <small class="text-muted">Per employee</small>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted">Upcoming Leaves</h6>
                                        <h4>15</h4>
                                        <small class="text-muted">Next 30 days</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-money-bill-wave mr-2"></i>
                                Payroll Overview
                            </div>
                            <div class="card-body">
                                <h6 class="text-muted">Next Payroll Date</h6>
                                <h4>25th Aug 2023</h4>
                                <hr>
                                <div class="row">
                                    <div class="col-6">
                                        <h6 class="text-muted">Processed</h6>
                                        <h4>142</h4>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted">Pending</h6>
                                        <h4>8</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-user-graduate mr-2"></i>
                                Training Metrics
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <h6 class="text-muted">Active Courses</h6>
                                        <h4>12</h4>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted">Completion Rate</h6>
                                        <h4>87%</h4>
                                    </div>
                                </div>
                                <hr>
                                <h6 class="text-muted">Most Popular Course</h6>
                                <p>Leadership Essentials</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-user-plus mr-2"></i>
                                Recruitment Status
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <h6 class="text-muted">Open Positions</h6>
                                        <h4>8</h4>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted">Applications</h6>
                                        <h4>45</h4>
                                    </div>
                                </div>
                                <hr>
                                <h6 class="text-muted">Interviews This Week</h6>
                                <h4>12</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-history mr-2"></i>
                                Recent Activities
                            </div>
                            <div class="card-body">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Activity</th>
                                            <th>User</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $recentActivities = getRecentActivities();
                                        if (count($recentActivities) > 0) {
                                            foreach ($recentActivities as $activity) {
                                                echo "<tr>";
                                                echo "<td><div class='activity-icon'><i class='fas fa-check-circle text-success'></i></div>" . $activity["action"] . "</td>";
                                                echo "<td>" . $activity["username"] . "</td>";
                                                echo "<td>" . $activity["created_at"] . "</td>";
                                                echo "<td><span class='badge badge-success'>Completed</span></td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4' class='text-center'>No recent activities</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-bell mr-2"></i>
                                Upcoming Events
                            </div>
                            <div class="card-body">
                                <div class="event-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon">
                                            <i class="fas fa-birthday-cake text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Team Birthdays</h6>
                                            <small class="text-muted">2 upcoming this week</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="event-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon">
                                            <i class="fas fa-users text-success"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Team Meeting</h6>
                                            <small class="text-muted">Tomorrow, 10:00 AM</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="event-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon">
                                            <i class="fas fa-certificate text-warning"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">Training Workshop</h6>
                                            <small class="text-muted">Friday, 2:00 PM</small>
                                        </div>
                                    </div>
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
</body>
</html>
