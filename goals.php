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
    <title>Employee Goals - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Custom styles for goals page */
        .goal-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .goal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .goal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .goal-progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
        }

        .goal-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.6s ease;
        }

        .goal-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-completed { background-color: #d4edda; color: #155724; }
        .status-in-progress { background-color: #fff3cd; color: #856404; }
        .status-pending { background-color: #f8d7da; color: #721c24; }

        .goal-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .goal-card:hover .goal-actions {
            opacity: 1;
        }

        .add-goal-btn {
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

        .add-goal-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .goal-stats {
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

        .goal-timeline {
            position: relative;
            padding-left: 30px;
        }

        .goal-timeline::before {
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
                        <i class="fas fa-bullseye mr-3"></i>
                        Employee Goals Management
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

                <!-- Goals Statistics -->
                <div class="goal-stats">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="mb-1">24</h3>
                            <p class="mb-0">Completed Goals</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="mb-1">12</h3>
                            <p class="mb-0">In Progress</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <h3 class="mb-1">8</h3>
                            <p class="mb-0">Pending Review</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <h3 class="mb-1">92%</h3>
                            <p class="mb-0">Success Rate</p>
                        </div>
                    </div>
                </div>

                <!-- Goals Grid -->
                <div class="row">
                    <!-- Sample Goal Cards -->
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card goal-card h-100">
                            <div class="goal-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1">Improve Customer Satisfaction</h5>
                                        <small>Department: Sales</small>
                                    </div>
                                    <span class="goal-status status-in-progress">In Progress</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Increase customer satisfaction rating from 4.2 to 4.6 by Q4 2023 through improved response times and service quality.</p>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Progress</small>
                                        <small>75%</small>
                                    </div>
                                    <div class="goal-progress">
                                        <div class="goal-progress-bar" style="width: 75%"></div>
                                    </div>
                                </div>

                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Due Date</small>
                                        <strong>Dec 31, 2023</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Owner</small>
                                        <strong>John Doe</strong>
                                    </div>
                                </div>

                                <div class="goal-actions text-center">
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
                        <div class="card goal-card h-100">
                            <div class="goal-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1">Complete Leadership Training</h5>
                                        <small>Department: Management</small>
                                    </div>
                                    <span class="goal-status status-completed">Completed</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Complete advanced leadership training program and apply learned skills in team management.</p>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Progress</small>
                                        <small>100%</small>
                                    </div>
                                    <div class="goal-progress">
                                        <div class="goal-progress-bar" style="width: 100%"></div>
                                    </div>
                                </div>

                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Completed</small>
                                        <strong>Oct 15, 2023</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Owner</small>
                                        <strong>Jane Smith</strong>
                                    </div>
                                </div>

                                <div class="goal-actions text-center">
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
                        <div class="card goal-card h-100">
                            <div class="goal-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1">Implement New HR System</h5>
                                        <small>Department: IT</small>
                                    </div>
                                    <span class="goal-status status-pending">Pending</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Successfully implement and train staff on the new HR management system by end of Q1 2024.</p>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Progress</small>
                                        <small>25%</small>
                                    </div>
                                    <div class="goal-progress">
                                        <div class="goal-progress-bar" style="width: 25%"></div>
                                    </div>
                                </div>

                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Due Date</small>
                                        <strong>Mar 31, 2024</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Owner</small>
                                        <strong>Mike Johnson</strong>
                                    </div>
                                </div>

                                <div class="goal-actions text-center">
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

                <!-- Goals Timeline -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history mr-2"></i>
                            Recent Goal Activities
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="goal-timeline">
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">Goal "Improve Customer Satisfaction" updated</h6>
                                        <p class="text-muted mb-0">Progress increased to 75%</p>
                                    </div>
                                    <small class="text-muted">2 hours ago</small>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">New goal "Complete Leadership Training" created</h6>
                                        <p class="text-muted mb-0">Assigned to Jane Smith</p>
                                    </div>
                                    <small class="text-muted">1 day ago</small>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">Goal "Implement New HR System" marked as completed</h6>
                                        <p class="text-muted mb-0">Congratulations to the IT team!</p>
                                    </div>
                                    <small class="text-muted">3 days ago</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Goal Button -->
    <button class="btn btn-primary add-goal-btn" data-toggle="tooltip" title="Add New Goal">
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
