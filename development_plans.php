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

        if ($action == 'add_plan') {
            // Add new development plan
            $employee_id = $_POST['employee_id'];
            $plan_name = $_POST['plan_name'];
            $plan_description = $_POST['plan_description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];

            $stmt = $conn->prepare("INSERT INTO development_plans (employee_id, plan_name, plan_description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $employee_id, $plan_name, $plan_description, $start_date, $end_date, $status);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'edit_plan') {
            // Edit existing development plan
            $plan_id = $_POST['plan_id'];
            $plan_name = $_POST['plan_name'];
            $plan_description = $_POST['plan_description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];

            $stmt = $conn->prepare("UPDATE development_plans SET plan_name = ?, plan_description = ?, start_date = ?, end_date = ?, status = ? WHERE plan_id = ?");
            $stmt->bind_param("sssssi", $plan_name, $plan_description, $start_date, $end_date, $status, $plan_id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'delete_plan') {
            // Delete development plan
            $plan_id = $_POST['plan_id'];

            // First delete associated activities
            $stmt = $conn->prepare("DELETE FROM development_activities WHERE plan_id = ?");
            $stmt->bind_param("i", $plan_id);
            $stmt->execute();
            $stmt->close();

            // Then delete the plan
            $stmt = $conn->prepare("DELETE FROM development_plans WHERE plan_id = ?");
            $stmt->bind_param("i", $plan_id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'add_activity') {
            // Add new activity to a plan
            $plan_id = $_POST['plan_id'];
            $activity_name = $_POST['activity_name'];
            $activity_type = $_POST['activity_type'];
            $description = $_POST['description'];
            $start_date = $_POST['activity_start_date'];
            $end_date = $_POST['activity_end_date'];
            $status = $_POST['activity_status'];

            $stmt = $conn->prepare("INSERT INTO development_activities (plan_id, activity_name, activity_type, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $plan_id, $activity_name, $activity_type, $description, $start_date, $end_date, $status);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Get all development plans with employee information
$plans_query = "SELECT dp.*, ep.employee_number,
                CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
                jr.title as job_title
                FROM development_plans dp
                JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                ORDER BY dp.created_at DESC";

$plans_result = $conn->query($plans_query);

// Get statistics
$stats_query = "SELECT
    COUNT(*) as total_plans,
    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_plans,
    SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft_plans,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_plans
    FROM development_plans";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get employees for dropdown
$employees_query = "SELECT ep.employee_id, ep.employee_number,
                   CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
                   jr.title as job_title
                   FROM employee_profiles ep
                   JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                   LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                   ORDER BY pi.last_name, pi.first_name";

$employees_result = $conn->query($employees_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Development Plans - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Custom styles for development plans page */
        .plan-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .plan-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .plan-progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
        }

        .plan-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.6s ease;
        }

        .plan-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active { background-color: #d4edda; color: #155724; }
        .status-draft { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d1ecf1; color: #0c5460; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }

        .plan-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .plan-card:hover .plan-actions {
            opacity: 1;
        }

        .add-plan-btn {
            display: inline-flex !important;
            visibility: visible !important;
            opacity: 1 !important;
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
            z-index: 1051;
        }

        .add-plan-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .plan-stats {
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

        .plan-timeline {
            position: relative;
            padding-left: 30px;
        }

        .plan-timeline::before {
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

        .activity-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 10px;
            border-left: 3px solid #667eea;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
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
                        <i class="fas fa-project-diagram mr-3"></i>
                        Development Plans Management
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

                <!-- Development Plans Statistics -->
                <div class="plan-stats">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['active_plans'] ?? 0; ?></h3>
                            <p class="mb-0">Active Plans</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['total_plans'] ?? 0; ?></h3>
                            <p class="mb-0">Total Plans</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['draft_plans'] ?? 0; ?></h3>
                            <p class="mb-0">Draft Plans</p>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-icon mb-2">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['completed_plans'] ?? 0; ?></h3>
                            <p class="mb-0">Completed</p>
                        </div>
                    </div>
                </div>

<!-- Development Plans Wrapper -->
<div id="plans-container">

    <!-- Development Plans Grid -->
    <div class="row">
        <?php if ($plans_result && $plans_result->num_rows > 0): ?>
            <?php while ($plan = $plans_result->fetch_assoc()):
                // Get activities for this plan
                $activities_query = "SELECT * FROM development_activities WHERE plan_id = ? ORDER BY start_date";
                $activities_stmt = $conn->prepare($activities_query);
                $activities_stmt->bind_param("i", $plan['plan_id']);
                $activities_stmt->execute();
                $activities_result = $activities_stmt->get_result();

                // Calculate progress
                $total_activities = $activities_result->num_rows;
                $completed_activities = 0;
                while ($activity = $activities_result->fetch_assoc()) {
                    if ($activity['status'] == 'Completed') {
                        $completed_activities++;
                    }
                }
                $progress = $total_activities > 0 ? round(($completed_activities / $total_activities) * 100) : 0;

                // Reset activities result pointer
                $activities_result->data_seek(0);
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card plan-card h-100" id="plan-<?php echo $plan['plan_id']; ?>">

                    <div class="plan-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($plan['plan_name']); ?></h5>
                                <small>Employee: <?php echo htmlspecialchars($plan['employee_name']); ?> (<?php echo htmlspecialchars($plan['employee_number']); ?>)</small>
                            </div>
                            <span class="plan-status status-<?php echo strtolower($plan['status']); ?>"><?php echo htmlspecialchars($plan['status']); ?></span>
                        </div>
                    </div>

                    <div class="card-body">
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($plan['plan_description']); ?></p>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Progress</small>
                                <small><?php echo $progress; ?>%</small>
                            </div>
                            <div class="plan-progress">
                                <div class="plan-progress-bar" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>

                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <small class="text-muted d-block">Start Date</small>
                                <strong><?php echo date('M Y', strtotime($plan['start_date'])); ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">End Date</small>
                                <strong><?php echo date('M Y', strtotime($plan['end_date'])); ?></strong>
                            </div>
                        </div>

                        <div class="activity-list mb-3">
                            <?php if ($activities_result->num_rows > 0): ?>
                                <?php while ($activity = $activities_result->fetch_assoc()): ?>
                                    <div class="activity-item">
                                        <small><strong><?php echo $activity['status'] == 'Completed' ? '✓' : '○'; ?></strong> <?php echo htmlspecialchars($activity['activity_name']); ?></small>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="activity-item">
                                    <small><em>No activities added yet</em></small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="plan-actions text-center">
                            <button class="btn btn-sm btn-outline-primary mr-2" onclick="editPlan(<?php echo $plan['plan_id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success mr-2" onclick="addActivity(<?php echo $plan['plan_id']; ?>)">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePlan(<?php echo $plan['plan_id']; ?>)">
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
                    <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Development Plans Found</h4>
                    <p class="text-muted">Click the + button to create your first development plan.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div> <!-- END of #plans-container -->

<!-- Add Development Plan Button -->
<button class="btn btn-primary add-plan-btn" data-toggle="tooltip" title="Add New Development Plan" onclick="showAddPlanModal()">
    <i class="fas fa-plus"></i>
</button>

<!-- Development Plans Timeline -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-history mr-2"></i>
            Recent Development Activities
        </h5>
    </div>
    <div class="card-body">
        <div class="plan-timeline">
            <?php
            $recent_activities_query = "SELECT da.*, dp.plan_name,
                                        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
                                        ep.employee_number
                                        FROM development_activities da
                                        JOIN development_plans dp ON da.plan_id = dp.plan_id
                                        JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                                        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                                        ORDER BY da.updated_at DESC LIMIT 10";

            $recent_activities_result = $conn->query($recent_activities_query);

            if ($recent_activities_result && $recent_activities_result->num_rows > 0):
                while ($activity = $recent_activities_result->fetch_assoc()):
                    $time_ago = time_elapsed_string($activity['updated_at']);
            ?>
                <div class="timeline-item">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['activity_name']); ?> <?php echo $activity['status'] == 'Completed' ? 'Completed' : 'Updated'; ?></h6>
                            <p class="text-muted mb-0">
                                <?php echo htmlspecialchars($activity['employee_name']); ?> (<?php echo htmlspecialchars($activity['employee_number']); ?>) - <?php echo htmlspecialchars($activity['activity_type']); ?> in "<?php echo htmlspecialchars($activity['plan_name']); ?>"
                                <?php if ($activity['status'] == 'Completed'): ?>
                                    <span class="badge badge-success">Completed</span>
                                <?php elseif ($activity['status'] == 'In Progress'): ?>
                                    <span class="badge badge-warning">In Progress</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <small class="text-muted"><?php echo $time_ago; ?></small>
                    </div>
                </div>
            <?php endwhile; ?>
            <?php else: ?>
                <div class="timeline-item">
                    <div class="text-center py-3">
                        <p class="text-muted mb-0">No recent activities found.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <!-- Add/Edit Development Plan Modal -->
    <div class="modal fade" id="planModal" tabindex="-1" role="dialog" aria-labelledby="planModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="planModalLabel">Add New Development Plan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="planForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="planAction" value="add_plan">
                        <input type="hidden" name="plan_id" id="planId">

                        <div class="form-group">
                            <label for="employee_id">Employee</label>
                            <select class="form-control" name="employee_id" id="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php while ($employee = $employees_result->fetch_assoc()): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>">
                                        <?php echo htmlspecialchars($employee['employee_name']); ?> (<?php echo htmlspecialchars($employee['employee_number']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="plan_name">Plan Name</label>
                            <input type="text" class="form-control" name="plan_name" id="plan_name" required>
                        </div>

                        <div class="form-group">
                            <label for="plan_description">Description</label>
                            <textarea class="form-control" name="plan_description" id="plan_description" rows="3" required></textarea>
                        </div>

                        <div class="row">
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
                            <select class="form-control" name="status" id="plan_status" required>
                                <option value="Draft">Draft</option>
                                <option value="Active">Active</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Activity Modal -->
    <div class="modal fade" id="activityModal" tabindex="-1" role="dialog" aria-labelledby="activityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="activityModalLabel">Add New Activity</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="activityForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_activity">
                        <input type="hidden" name="plan_id" id="activityPlanId">

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

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" name="description" id="activity_description" rows="3"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="activity_start_date">Start Date</label>
                                    <input type="date" class="form-control" name="activity_start_date" id="activity_start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="activity_end_date">End Date</label>
                                    <input type="date" class="form-control" name="activity_end_date" id="activity_end_date" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="activity_status">Status</label>
                            <select class="form-control" name="activity_status" id="activity_status" required>
                                <option value="Not Started">Not Started</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});

// Show Add Plan modal
function showAddPlanModal() {
    $('#planModalLabel').text('Add New Development Plan');
    $('#planAction').val('add_plan');
    $('#planForm')[0].reset();
    $('#planModal').modal('show');
}

// Edit Plan
function editPlan(planId) {
    $('#planModalLabel').text('Edit Development Plan');
    $('#planAction').val('edit_plan');
    $('#planId').val(planId);
    $('#planModal').modal('show');
}

// Add Activity
function addActivity(planId) {
    $('#activityModalLabel').text('Add New Activity');
    $('#activityPlanId').val(planId);
    $('#activityForm')[0].reset();
    $('#activityModal').modal('show');
}

// Delete Plan
function deletePlan(planId) {
    if (confirm('Are you sure you want to delete this development plan? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_plan">
            <input type="hidden" name="plan_id" value="${planId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

$(document).ready(function () {

    // Handle Add Activity form submission via AJAX
    $('#activityForm').on('submit', function (e) {
        e.preventDefault();

        const planId = $('#activityPlanId').val();

        $.ajax({
            type: 'POST',
            url: '', // same PHP page
            data: $(this).serialize(),
            success: function () {
                $('#activityModal').modal('hide');
                alert('Activity added successfully!');

                // Reload the entire plan card to update progress bar and activity list
                const planCard = $(`#plan-${planId}`);
                const tempScroll = $(window).scrollTop();

                $.get(window.location.href, function (data) {
                    const updatedCard = $(data).find(`#plan-${planId}`);
                    planCard.replaceWith(updatedCard);

                    // Reinit tooltips and restore scroll position
                    $('[data-toggle="tooltip"]').tooltip();
                    $(window).scrollTop(tempScroll);
                });
            },
            error: function () {
                alert('Something went wrong while adding the activity.');
            }
        });
    });
});
</script>



</body>
</html>
                  
