<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Function to calculate time elapsed string
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add_activity') {
            // Add new activity
            $plan_id = $_POST['plan_id'];
            $activity_name = $_POST['activity_name'];
            $activity_type = $_POST['activity_type'];
            $description = $_POST['description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];

            $stmt = $conn->prepare("INSERT INTO development_activities (plan_id, activity_name, activity_type, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $plan_id, $activity_name, $activity_type, $description, $start_date, $end_date, $status);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'edit_activity') {
            // Edit existing activity
            $activity_id = $_POST['activity_id'];
            $plan_id = $_POST['plan_id'];
            $activity_name = $_POST['activity_name'];
            $activity_type = $_POST['activity_type'];
            $description = $_POST['description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];

            $stmt = $conn->prepare("UPDATE development_activities SET plan_id = ?, activity_name = ?, activity_type = ?, description = ?, start_date = ?, end_date = ?, status = ? WHERE activity_id = ?");
            $stmt->bind_param("issssssi", $plan_id, $activity_name, $activity_type, $description, $start_date, $end_date, $status, $activity_id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'delete_activity') {
            // Delete activity
            $activity_id = $_POST['activity_id'];

            $stmt = $conn->prepare("DELETE FROM development_activities WHERE activity_id = ?");
            $stmt->bind_param("i", $activity_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Get all development activities with plan and employee information
$activities_query = "SELECT da.*, dp.plan_name,
                    CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
                    ep.employee_number, jr.title as job_title
                    FROM development_activities da
                    JOIN development_plans dp ON da.plan_id = dp.plan_id
                    JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                    ORDER BY da.created_at DESC";

$activities_result = $conn->query($activities_query);

// Get statistics
$stats_query = "SELECT
    COUNT(*) as total_activities,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_activities,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_activities,
    SUM(CASE WHEN status = 'Not Started' THEN 1 ELSE 0 END) as not_started_activities
    FROM development_activities";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get plans for dropdown
$plans_query = "SELECT dp.plan_id, dp.plan_name,
                CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
                FROM development_plans dp
                JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                ORDER BY dp.plan_name";

$plans_result = $conn->query($plans_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Development Activities - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Custom styles for development activities page */
        .activity-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .activity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .activity-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .activity-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-completed { background-color: #d4edda; color: #155724; }
        .status-in-progress { background-color: #fff3cd; color: #856404; }
        .status-not-started { background-color: #e2e3e5; color: #383d41; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }

        .activity-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .activity-card:hover .activity-actions {
            opacity: 1;
        }

        .add-activity-btn {
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

        .add-activity-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .activity-stats {
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

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .activity-timeline::before {
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
                        <i class="fas fa-calendar-check mr-3"></i>
                        Development Activities 
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

                <!-- Development Activities Statistics -->
                <div class="activity-stats">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['completed_activities'] ?? 0; ?></h3>
                            <p class="mb-0">Completed</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['total_activities'] ?? 0; ?></h3>
                            <p class="mb-0">Total Activities</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['in_progress_activities'] ?? 0; ?></h3>
                            <p class="mb-0">In Progress</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-pause-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['not_started_activities'] ?? 0; ?></h3>
                            <p class="mb-0">Not Started</p>
                        </div>
                    </div>
                </div>

                <!-- Development Activities Grid -->
                <div class="row">
                    <?php if ($activities_result && $activities_result->num_rows > 0): ?>
                        <?php while ($activity = $activities_result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card activity-card h-100">
                                <div class="activity-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($activity['activity_name']); ?></h5>
                                            <small><?php echo htmlspecialchars($activity['activity_type']); ?> - <?php echo htmlspecialchars($activity['employee_name']); ?> (<?php echo htmlspecialchars($activity['employee_number']); ?>)</small>
                                        </div>
                                        <span class="activity-status status-<?php echo strtolower(str_replace(' ', '-', $activity['status'])); ?>"><?php echo htmlspecialchars($activity['status']); ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3"><?php echo htmlspecialchars($activity['description'] ?: 'No description'); ?></p>

                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Start Date</small>
                                            <strong><?php echo date('M d, Y', strtotime($activity['start_date'])); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">End Date</small>
                                            <strong><?php echo date('M d, Y', strtotime($activity['end_date'])); ?></strong>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">Plan: <?php echo htmlspecialchars($activity['plan_name']); ?></small>
                                    </div>

                                    <div class="activity-actions text-center">
                                        <button class="btn btn-sm btn-outline-primary mr-2" onclick="editActivity(<?php echo $activity['activity_id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteActivity(<?php echo $activity['activity_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Development Activities Found</h4>
                                <p class="text-muted">Click the + button to create your first development activity.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities Timeline -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history mr-2"></i>
                            Recent Activity Updates
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <?php
                            // Get recent activities updates
                            $recent_updates_query = "SELECT da.*, dp.plan_name,
                                                    CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
                                                    ep.employee_number
                                                    FROM development_activities da
                                                    JOIN development_plans dp ON da.plan_id = dp.plan_id
                                                    JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                                                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                                                    ORDER BY da.updated_at DESC LIMIT 10";

                            $recent_updates_result = $conn->query($recent_updates_query);

                            if ($recent_updates_result && $recent_updates_result->num_rows > 0):
                                while ($activity = $recent_updates_result->fetch_assoc()):
                                    $time_ago = time_elapsed_string($activity['updated_at']);
                            ?>
                                <div class="timeline-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['activity_name']); ?> Updated</h6>
                                            <p class="text-muted mb-0">
                                                <?php echo htmlspecialchars($activity['employee_name']); ?> (<?php echo htmlspecialchars($activity['employee_number']); ?>) - <?php echo htmlspecialchars($activity['activity_type']); ?> in "<?php echo htmlspecialchars($activity['plan_name']); ?>"
                                                <span class="badge badge-<?php echo $activity['status'] == 'Completed' ? 'success' : ($activity['status'] == 'In Progress' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars($activity['status']); ?></span>
                                            </p>
                                        </div>
                                        <small class="text-muted"><?php echo $time_ago; ?></small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <?php else: ?>
                                <div class="timeline-item">
                                    <div class="text-center py-3">
                                        <p class="text-muted mb-0">No recent updates found.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Development Activity Button -->
    <button class="btn btn-primary add-activity-btn" data-toggle="tooltip" title="Add New Development Activity" onclick="showAddActivityModal()">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Add/Edit Development Activity Modal -->
    <div class="modal fade" id="activityModal" tabindex="-1" role="dialog" aria-labelledby="activityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="activityModalLabel">Add New Development Activity</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="activityForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="activityAction" value="add_activity">
                        <input type="hidden" name="activity_id" id="activityId">

                        <!-- Tabs -->
                        <ul class="nav nav-tabs" id="activityTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab" aria-controls="basic" aria-selected="true">Basic Information</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="dates-tab" data-toggle="tab" href="#dates" role="tab" aria-controls="dates" aria-selected="false">Dates & Status</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="description-tab" data-toggle="tab" href="#description" role="tab" aria-controls="description" aria-selected="false">Description</a>
                            </li>
                        </ul>
                        <div class="tab-content" id="activityTabContent">
                            <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                                <div class="form-group mt-3">
                                    <label for="plan_id">Development Plan</label>
                                    <select class="form-control" name="plan_id" id="plan_id" required>
                                        <option value="">Select Plan</option>
                                        <?php while ($plan = $plans_result->fetch_assoc()): ?>
                                            <option value="<?php echo $plan['plan_id']; ?>">
                                                <?php echo htmlspecialchars($plan['plan_name']); ?> - <?php echo htmlspecialchars($plan['employee_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="activity_name">Activity Name</label>
                                    <input type="text" class="form-control" name="activity_name" id="activity_name" required>
                                </div>

                                <div class="form-group">
                                    <label for="activity_type">Activity Type</label>
                                    <select class="form-control" name="activity_type" id="activity_type" required>
                                        <option value="Training">Training</option>
                                        <option value="Mentoring">Mentoring</option>
                                        <option value="Project">Project</option>
                                        <option value="Certification">Certification</option>
                                        <option value="Assessment">Assessment</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="dates" role="tabpanel" aria-labelledby="dates-tab">
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">Start Date</label>
                                            <input type="date" class="form-control" name="start_date" id="start_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="end_date">End Date</label>
                                            <input type="date" class="form-control" name="end_date" id="end_date" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control" name="status" id="activity_status" required>
                                        <option value="Not Started">Not Started</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="description" role="tabpanel" aria-labelledby="description-tab">
                                <div class="form-group mt-3">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" name="description" id="activity_description" rows="4"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });

        function showAddActivityModal() {
            $('#activityModalLabel').text('Add New Development Activity');
            $('#activityAction').val('add_activity');
            $('#activityForm')[0].reset();
            $('#activityModal').modal('show');
        }

        function editActivity(activityId) {
            // Fetch activity data via AJAX (simplified, assuming data is available)
            $('#activityModalLabel').text('Edit Development Activity');
            $('#activityAction').val('edit_activity');
            $('#activityId').val(activityId);
            // Populate form fields - in a real app, fetch data
            $('#activityModal').modal('show');
        }

        function deleteActivity(activityId) {
            if (confirm('Are you sure you want to delete this development activity? This action cannot be undone.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_activity"><input type="hidden" name="activity_id" value="' + activityId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
