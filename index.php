<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';
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
                <!-- AI Search Feature -->
                <div class="card mb-4 ai-search-card">
                    <div class="card-body">
                        <h4 class="text-white mb-3"><i class="fas fa-search mr-2"></i>AI Employee Search</h4>
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control ai-search-input" id="employeeSearch" placeholder="Search employee by name, email, or employee number..." autocomplete="off">
                            <div class="input-group-append">
                                <button class="btn ai-search-btn" type="button" id="searchBtn">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-info-circle"></i> Search across all modules: Employee, Payroll, Performance, Leave, Exit, Recruitment, and Training
                        </small>
                    </div>
                </div>

                <!-- Search Results Container -->
                <div id="searchResults" style="display: none;" class="mb-4">
                    <div class="card">
                        <div class="card-header ai-results-header">
                            <h5 class="mb-0 text-white"><i class="fas fa-list mr-2"></i>Search Results</h5>
                        </div>
                        <div class="card-body" id="searchResultsContent">
                            <!-- Results will be loaded here -->
                        </div>
                    </div>
                </div>

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
                                        error_log("Index: recent activities count: " . count($recentActivities));
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
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            let searchTimeout;
            
            // Search on button click
            $('#searchBtn').on('click', function() {
                performSearch();
            });
            
            // Search on Enter key
            $('#employeeSearch').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    performSearch();
                }
            });
            
            // Real-time search with debounce
            $('#employeeSearch').on('input', function() {
                const searchTerm = $(this).val().trim();
                if (searchTerm.length >= 2) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        performSearch();
                    }, 500);
                } else if (searchTerm.length === 0) {
                    $('#searchResults').hide();
                }
            });
            
            function performSearch() {
                const searchTerm = $('#employeeSearch').val().trim();
                
                if (searchTerm.length < 2) {
                    alert('Please enter at least 2 characters to search');
                    return;
                }
                
                // Show loading state
                $('#searchResults').show();
                $('#searchResultsContent').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Searching...</p></div>');
                
                $.ajax({
                    url: 'search_employee.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ search: searchTerm }),
                    success: function(response) {
                        console.log('Search response:', response);
                        if (response.success) {
                            console.log('Search results:', response.data);
                            displaySearchResults(response.data);
                        } else {
                            $('#searchResultsContent').html('<div class="alert alert-danger">' + (response.error || 'An error occurred') + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Search error:', xhr, status, error);
                        let errorMsg = 'Error: ' + error;
                        if (xhr.responseText) {
                            try {
                                const errorResponse = JSON.parse(xhr.responseText);
                                errorMsg = errorResponse.error || errorMsg;
                            } catch(e) {
                                errorMsg = xhr.responseText.substring(0, 200);
                            }
                        }
                        $('#searchResultsContent').html('<div class="alert alert-danger">' + errorMsg + '</div>');
                    }
                });
            }
            
            function displaySearchResults(data) {
                console.log('Displaying search results:', data);
                let html = '';
                
                // Show summary
                let totalResults = 0;
                if (data.employee) totalResults += data.employee.length;
                if (data.payroll) totalResults += data.payroll.length;
                if (data.performance) totalResults += data.performance.length;
                if (data.leave) totalResults += data.leave.length;
                if (data.exit) totalResults += data.exit.length;
                if (data.training) totalResults += data.training.length;
                if (data.recruitment) totalResults += data.recruitment.length;
                if (data.employment_history) totalResults += data.employment_history.length;
                if (data.documents) totalResults += data.documents.length;
                
                if (totalResults > 0) {
                    html += '<div class="alert alert-info mb-3"><i class="fas fa-info-circle mr-2"></i>Found ' + totalResults + ' total records across all modules</div>';
                }
                
                // Employee Information
                if (data.employee && data.employee.length > 0) {
                    html += '<div class="mb-4"><h6 class="text-primary"><i class="fas fa-user mr-2"></i>Employee Information</h6>';
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Name</th><th>Employee #</th><th>Department</th><th>Job Title</th><th>Status</th><th>Email</th><th>Phone</th></tr></thead><tbody>';
                    data.employee.forEach(function(emp) {
                        html += '<tr>';
                        html += '<td><strong>' + escapeHtml(emp.first_name + ' ' + emp.last_name) + '</strong></td>';
                        html += '<td>' + escapeHtml(emp.employee_number || 'N/A') + '</td>';
                        html += '<td>' + escapeHtml(emp.department_name || 'N/A') + '</td>';
                        html += '<td>' + escapeHtml(emp.job_title || 'N/A') + '</td>';
                        html += '<td><span class="badge badge-' + (emp.employment_status === 'Active' ? 'success' : 'secondary') + '">' + escapeHtml(emp.employment_status || 'N/A') + '</span></td>';
                        html += '<td>' + escapeHtml(emp.work_email || 'N/A') + '</td>';
                        html += '<td>' + escapeHtml(emp.work_phone || emp.phone_number || 'N/A') + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                
                // Payroll Information
                if (data.payroll && data.payroll.length > 0) {
                    html += '<div class="mb-4"><h6 class="text-success"><i class="fas fa-money-bill-wave mr-2"></i>Payroll Records</h6>';
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Employee</th><th>Cycle</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th>Status</th><th>Date</th></tr></thead><tbody>';
                    data.payroll.forEach(function(pay) {
                        html += '<tr>';
                        html += '<td>' + escapeHtml(pay.first_name + ' ' + pay.last_name) + '</td>';
                        html += '<td>' + escapeHtml(pay.cycle_name || 'N/A') + '</td>';
                        html += '<td>₱' + formatNumber(pay.base_salary || pay.gross_pay || 0) + '</td>';
                        html += '<td>₱' + formatNumber(pay.deductions || 0) + '</td>';
                        html += '<td><strong>₱' + formatNumber(pay.net_pay || 0) + '</strong></td>';
                        html += '<td><span class="badge badge-' + (pay.status === 'Processed' || pay.status === 'Paid' ? 'success' : pay.status === 'Pending' ? 'warning' : 'secondary') + '">' + escapeHtml(pay.status || 'N/A') + '</span></td>';
                        html += '<td>' + formatDate(pay.processed_date || pay.created_at) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                
                // Performance Reviews
                if (data.performance && data.performance.length > 0) {
                    html += '<div class="mb-4"><h6 class="text-info"><i class="fas fa-chart-line mr-2"></i>Performance Reviews</h6>';
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Employee</th><th>Cycle</th><th>Rating</th><th>Status</th><th>Date</th></tr></thead><tbody>';
                    data.performance.forEach(function(perf) {
                        html += '<tr>';
                        html += '<td>' + escapeHtml(perf.first_name + ' ' + perf.last_name) + '</td>';
                        html += '<td>' + escapeHtml(perf.cycle_name || 'N/A') + '</td>';
                        html += '<td><strong>' + escapeHtml(perf.overall_rating || 'N/A') + '</strong></td>';
                        html += '<td><span class="badge badge-' + (perf.status === 'Finalized' ? 'success' : perf.status === 'Submitted' ? 'info' : 'warning') + '">' + escapeHtml(perf.status || 'N/A') + '</span></td>';
                        html += '<td>' + formatDate(perf.review_date) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                
                // Leave Records
                if (data.leave && data.leave.length > 0) {
                    html += '<div class="mb-4"><h6 class="text-warning"><i class="fas fa-calendar-alt mr-2"></i>Leave Records</h6>';
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Employee</th><th>Leave Type</th><th>Start Date</th><th>End Date</th><th>Days</th><th>Status</th></tr></thead><tbody>';
                    data.leave.forEach(function(leave) {
                        html += '<tr>';
                        html += '<td>' + escapeHtml(leave.first_name + ' ' + leave.last_name) + '</td>';
                        html += '<td>' + escapeHtml(leave.leave_type_name || 'N/A') + '</td>';
                        html += '<td>' + formatDate(leave.start_date) + '</td>';
                        html += '<td>' + formatDate(leave.end_date) + '</td>';
                        html += '<td>' + escapeHtml(leave.days_requested || 'N/A') + '</td>';
                        html += '<td><span class="badge badge-' + (leave.status === 'Approved' ? 'success' : leave.status === 'Pending' ? 'warning' : 'danger') + '">' + escapeHtml(leave.status || 'N/A') + '</span></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                
                // Exit Management
                if (data.exit && data.exit.length > 0) {
                    html += '<div class="mb-4"><h6 class="text-danger"><i class="fas fa-door-open mr-2"></i>Exit Management</h6>';
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Employee</th><th>Exit Type</th><th>Reason</th><th>Notice Date</th><th>Exit Date</th><th>Status</th></tr></thead><tbody>';
                    data.exit.forEach(function(exit) {
                        html += '<tr>';
                        html += '<td>' + escapeHtml(exit.first_name + ' ' + exit.last_name) + '</td>';
                        html += '<td>' + escapeHtml(exit.exit_type || 'N/A') + '</td>';
                        html += '<td>' + escapeHtml(exit.exit_reason || 'N/A') + '</td>';
                        html += '<td>' + formatDate(exit.notice_date) + '</td>';
                        html += '<td>' + formatDate(exit.exit_date) + '</td>';
                        html += '<td><span class="badge badge-' + (exit.status === 'Completed' ? 'success' : 'warning') + '">' + escapeHtml(exit.status || 'N/A') + '</span></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                
                // Training Records
                if (data.training && data.training.length > 0) {
                    html += '<div class="mb-4"><h6 class="text-purple"><i class="fas fa-graduation-cap mr-2"></i>Training Records</h6>';
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Employee</th><th>Course</th><th>Session</th><th>Enrollment Date</th><th>Status</th></tr></thead><tbody>';
                    data.training.forEach(function(train) {
                        html += '<tr>';
                        html += '<td>' + escapeHtml(train.first_name + ' ' + train.last_name) + '</td>';
                        html += '<td>' + escapeHtml(train.course_name || 'N/A') + '</td>';
                        html += '<td>' + escapeHtml(train.session_name || 'N/A') + '</td>';
                        html += '<td>' + formatDate(train.enrollment_date) + '</td>';
                        html += '<td><span class="badge badge-' + (train.status === 'Completed' ? 'success' : train.status === 'Enrolled' ? 'info' : 'warning') + '">' + escapeHtml(train.status || 'N/A') + '</span></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                
                // Recruitment Records
                if (data.recruitment && data.recruitment.length > 0) {
                    html += '<div class="mb-4"><h6 class="text-secondary"><i class="fas fa-user-plus mr-2"></i>Recruitment Records</h6>';
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Candidate</th><th>Job Position</th><th>Application Date</th><th>Interview Date</th><th>Status</th></tr></thead><tbody>';
                    data.recruitment.forEach(function(rec) {
                        html += '<tr>';
                        html += '<td>' + escapeHtml(rec.first_name + ' ' + rec.last_name) + '</td>';
                        html += '<td>' + escapeHtml(rec.job_title || 'N/A') + '</td>';
                        html += '<td>' + formatDate(rec.application_date) + '</td>';
                        html += '<td>' + (rec.interview_date ? formatDate(rec.interview_date) : 'N/A') + '</td>';
                        html += '<td><span class="badge badge-info">' + escapeHtml(rec.status || 'N/A') + '</span></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                
                // Employment History
                if (data.employment_history && data.employment_history.length > 0) {
                    html += '<div class="mb-4"><h6 class="text-dark"><i class="fas fa-briefcase mr-2"></i>Employment History</h6>';
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Employee</th><th>Job Title</th><th>Department</th><th>Employment Type</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Base Salary</th><th>Manager</th></tr></thead><tbody>';
                    data.employment_history.forEach(function(hist) {
                        html += '<tr>';
                        html += '<td>' + escapeHtml(hist.first_name + ' ' + hist.last_name) + '</td>';
                        html += '<td>' + escapeHtml(hist.job_title || 'N/A') + '</td>';
                        html += '<td>' + escapeHtml(hist.department_name || 'N/A') + '</td>';
                        html += '<td>' + escapeHtml(hist.employment_type || 'N/A') + '</td>';
                        html += '<td>' + formatDate(hist.start_date) + '</td>';
                        html += '<td>' + (hist.end_date ? formatDate(hist.end_date) : 'Current') + '</td>';
                        html += '<td><span class="badge badge-' + (hist.employment_status === 'Active' ? 'success' : 'secondary') + '">' + escapeHtml(hist.employment_status || 'N/A') + '</span></td>';
                        html += '<td>₱' + formatNumber(hist.base_salary || 0) + '</td>';
                        html += '<td>' + escapeHtml((hist.manager_first || '') + ' ' + (hist.manager_last || '') || 'N/A') + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                
                // Document Management
                if (data.documents && data.documents.length > 0) {
                    html += '<div class="mb-4"><h6 class="text-info"><i class="fas fa-file-alt mr-2"></i>Document Management</h6>';
                    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Employee</th><th>Document Name</th><th>Type</th><th>Status</th><th>Upload Date</th><th>Expiry Date</th><th>Notes</th></tr></thead><tbody>';
                    data.documents.forEach(function(doc) {
                        html += '<tr>';
                        html += '<td>' + escapeHtml(doc.first_name + ' ' + doc.last_name) + '</td>';
                        html += '<td>' + escapeHtml(doc.document_name || 'N/A') + '</td>';
                        html += '<td><span class="badge badge-secondary">' + escapeHtml(doc.document_type || 'N/A') + '</span></td>';
                        html += '<td><span class="badge badge-' + (doc.document_status === 'Active' ? 'success' : doc.document_status === 'Expired' ? 'danger' : 'warning') + '">' + escapeHtml(doc.document_status || 'N/A') + '</span></td>';
                        html += '<td>' + formatDate(doc.upload_date) + '</td>';
                        html += '<td>' + (doc.expiry_date ? formatDate(doc.expiry_date) : 'N/A') + '</td>';
                        html += '<td>' + escapeHtml(doc.notes || 'N/A') + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }
                
                if (html === '') {
                    html = '<div class="alert alert-info"><i class="fas fa-info-circle mr-2"></i>No results found for your search.</div>';
                }
                
                $('#searchResultsContent').html(html);
            }
            
            function escapeHtml(text) {
                if (text === null || text === undefined) return 'N/A';
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
            }
            
            function formatNumber(num) {
                return parseFloat(num).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            
            function formatDate(dateStr) {
                if (!dateStr) return 'N/A';
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            }
        });
    </script>
    <style>
        /* AI Search Card Styling - Matching Theme */
        .ai-search-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            box-shadow: 0 4px 15px var(--shadow-medium);
            border-radius: 8px;
        }
        
        .ai-search-input {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px 0 0 6px;
            background-color: rgba(255, 255, 255, 0.95);
            transition: all 0.3s;
        }
        
        .ai-search-input:focus {
            border-color: var(--white);
            background-color: var(--white);
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
        }
        
        .ai-search-btn {
            background-color: var(--white);
            color: var(--primary-color);
            border: 2px solid var(--white);
            border-radius: 0 6px 6px 0;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .ai-search-btn:hover {
            background-color: var(--primary-lighter);
            color: var(--primary-dark);
            border-color: var(--primary-lighter);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .ai-search-btn:active {
            transform: translateY(0);
        }
        
        .ai-results-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 8px 8px 0 0;
        }
        
        .text-purple {
            color: var(--primary-color) !important;
        }
        
        #searchResults {
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .table th {
            background-color: var(--bg-secondary);
            color: var(--primary-color);
            font-weight: 600;
            border-color: var(--border-light);
        }
        
        .table td {
            border-color: var(--border-light);
        }
        
        .table-bordered {
            border-color: var(--border-light);
        }
        
        .card {
            border-color: var(--border-light);
            box-shadow: 0 2px 8px var(--shadow-light);
        }
        
        .badge {
            font-weight: 500;
        }
    </style>
</body>
</html>
