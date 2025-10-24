<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Updates - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Custom styles for goal updates page */
        .update-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .update-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .update-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .update-content {
            padding: 20px;
        }

        .update-meta {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .update-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-progress { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-blocked { background-color: #f8d7da; color: #721c24; }

        .update-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .update-card:hover .update-actions {
            opacity: 1;
        }

        .add-update-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .add-update-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .update-stats {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .update-timeline {
            position: relative;
            padding-left: 30px;
        }

        .update-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .progress-indicator {
            width: 100%;
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-bar-custom {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.6s ease;
        }

        .goal-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .goal-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title mb-0">
                        <i class="fas fa-tasks mr-3"></i>
                        Goal Updates Management
                    </h2>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <button class="btn btn-outline-secondary">
                            <i class="fas fa-sort mr-2"></i>Sort
                        </button>
                    </div>
                </div>

                <!-- Updates Statistics -->
                <div class="update-stats">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="mb-1">47</h3>
                            <p class="mb-0">Total Updates</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <h3 class="mb-1">23</h3>
                            <p class="mb-0">In Progress</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="mb-1">18</h3>
                            <p class="mb-0">Completed</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h3 class="mb-1">6</h3>
                            <p class="mb-0">Blocked</p>
                        </div>
                    </div>
                </div>

                <!-- Updates Grid -->
                <div class="row">
                    <!-- Sample Update Cards -->
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card update-card h-100">
                            <div class="update-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1">Customer Satisfaction Goal Update</h5>
                                        <small>Goal: Improve Customer Satisfaction</small>
                                    </div>
                                    <span class="update-status status-progress">In Progress</span>
                                </div>
                            </div>
                            <div class="update-content">
                                <div class="update-meta">
                                    <i class="fas fa-user mr-1"></i> John Doe
                                    <i class="fas fa-calendar ml-3 mr-1"></i> 2 hours ago
                                </div>
                                <p class="text-muted mb-3">Implemented new response time tracking system. Current average response time improved to 4.2 hours from 6.8 hours. Customer feedback shows 15% improvement in satisfaction scores.</p>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Goal Progress</small>
                                        <small>75%</small>
                                    </div>
                                    <div class="progress-indicator">
                                        <div class="progress-bar-custom" style="width: 75%"></div>
                                    </div>
                                </div>

                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Next Milestone</small>
                                        <strong>Survey Analysis</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Due Date</small>
                                        <strong>Dec 15, 2023</strong>
                                    </div>
                                </div>

                                <div class="update-actions text-center">
                                    <button class="btn btn-sm btn-outline-primary mr-2">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success mr-2">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card update-card h-100">
                            <div class="update-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1">Leadership Training Completion</h5>
                                        <small>Goal: Complete Leadership Training</small>
                                    </div>
                                    <span class="update-status status-completed">Completed</span>
                                </div>
                            </div>
                            <div class="update-content">
                                <div class="update-meta">
                                    <i class="fas fa-user mr-1"></i> Jane Smith
                                    <i class="fas fa-calendar ml-3 mr-1"></i> 1 day ago
                                </div>
                                <p class="text-muted mb-3">Successfully completed all modules of the advanced leadership training program. Applied new skills in team management with positive feedback from team members.</p>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Goal Progress</small>
                                        <small>100%</small>
                                    </div>
                                    <div class="progress-indicator">
                                        <div class="progress-bar-custom" style="width: 100%"></div>
                                    </div>
                                </div>

                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Completed</small>
                                        <strong>Oct 15, 2023</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Rating</small>
                                        <strong>4.8/5</strong>
                                    </div>
                                </div>

                                <div class="update-actions text-center">
                                    <button class="btn btn-sm btn-outline-primary mr-2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info mr-2">
                                        <i class="fas fa-share"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card update-card h-100">
                            <div class="update-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1">HR System Implementation Delay</h5>
                                        <small>Goal: Implement New HR System</small>
                                    </div>
                                    <span class="update-status status-blocked">Blocked</span>
                                </div>
                            </div>
                            <div class="update-content">
                                <div class="update-meta">
                                    <i class="fas fa-user mr-1"></i> Mike Johnson
                                    <i class="fas fa-calendar ml-3 mr-1"></i> 3 days ago
                                </div>
                                <p class="text-muted mb-3">Implementation delayed due to vendor API changes. Currently working with vendor to resolve compatibility issues. Expected resolution by end of week.</p>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Goal Progress</small>
                                        <small>25%</small>
                                    </div>
                                    <div class="progress-indicator">
                                        <div class="progress-bar-custom" style="width: 25%"></div>
                                    </div>
                                </div>

                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Blocker</small>
                                        <strong>Vendor API</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Resolution ETA</small>
                                        <strong>Dec 20, 2023</strong>
                                    </div>
                                </div>

                                <div class="update-actions text-center">
                                    <button class="btn btn-sm btn-outline-primary mr-2">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning mr-2">
                                        <i class="fas fa-clock"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Updates Timeline -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history mr-2"></i>
                            Recent Goal Update Activities
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="update-timeline">
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">
                                            <a href="#" class="goal-link">Customer Satisfaction Goal</a> updated
                                        </h6>
                                        <p class="text-muted mb-0">Progress increased to 75% - New response time tracking implemented</p>
                                    </div>
                                    <small class="text-muted">2 hours ago</small>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">
                                            <a href="#" class="goal-link">Leadership Training</a> completed
                                        </h6>
                                        <p class="text-muted mb-0">Jane Smith finished all training modules successfully</p>
                                    </div>
                                    <small class="text-muted">1 day ago</small>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">
                                            <a href="#" class="goal-link">HR System Implementation</a> blocked
                                        </h6>
                                        <p class="text-muted mb-0">Vendor API compatibility issues delaying progress</p>
                                    </div>
                                    <small class="text-muted">3 days ago</small>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">
                                            <a href="#" class="goal-link">Team Productivity Goal</a> updated
                                        </h6>
                                        <p class="text-muted mb-0">Monthly metrics show 12% improvement in productivity</p>
                                    </div>
                                    <small class="text-muted">5 days ago</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Update Button -->
    <button class="btn btn-primary add-update-btn" data-toggle="tooltip" title="Add New Goal Update">
        <i class="fas fa-plus"></i>
    </button>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>
