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
            // Add new plan
            $employee_id = $_POST['employee_id'];
            $plan_name = $_POST['plan_name'];
            $description = $_POST['description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];

            $stmt = $conn->prepare("INSERT INTO development_plans (employee_id, plan_name, plan_description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $employee_id, $plan_name, $description, $start_date, $end_date, $status);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'edit_plan') {
            // Edit existing plan
            $plan_id = $_POST['plan_id'];
            $employee_id = $_POST['employee_id'];
            $plan_name = $_POST['plan_name'];
            $description = $_POST['description'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];

            $stmt = $conn->prepare("UPDATE development_plans SET employee_id = ?, plan_name = ?, plan_description = ?, start_date = ?, end_date = ?, status = ? WHERE plan_id = ?");
            $stmt->bind_param("isssssi", $employee_id, $plan_name, $description, $start_date, $end_date, $status, $plan_id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action == 'delete_plan') {
            // Delete plan
            $plan_id = $_POST['plan_id'];

            $stmt = $conn->prepare("DELETE FROM development_plans WHERE plan_id = ?");
            $stmt->bind_param("i", $plan_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Handle AJAX request for fetching plan data
if (isset($_GET['action']) && $_GET['action'] == 'get_plan' && isset($_GET['plan_id'])) {
    $plan_id = $_GET['plan_id'];
    $stmt = $conn->prepare("SELECT * FROM development_plans WHERE plan_id = ?");
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $plan = $result->fetch_assoc();
    $stmt->close();
    echo json_encode($plan);
    exit;
}

// Get all development plans with employee information and activity counts
$plans_query = "SELECT dp.*, 
                CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
                ep.employee_number, 
                jr.title as job_title,
                (SELECT COUNT(*) FROM development_activities WHERE plan_id = dp.plan_id) as activity_count,
                (SELECT COUNT(*) FROM development_activities WHERE plan_id = dp.plan_id AND status = 'Completed') as completed_activities
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
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_plans,
    SUM(CASE WHEN status = 'On Hold' THEN 1 ELSE 0 END) as on_hold_plans
    FROM development_plans";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get employees for dropdown
$employees_query = "SELECT ep.employee_id, CONCAT(pi.first_name, ' ', pi.last_name) as employee_name, ep.employee_number
                    FROM employee_profiles ep
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    ORDER BY pi.first_name";

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
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            padding: 20px;
        }
        .plan-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-on-hold { background-color: #fff3cd; color: #856404; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        .plan-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .plan-card:hover .plan-actions {
            opacity: 1;
        }
        .add-plan-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        .add-plan-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
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
            background: #C2185B;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #C2185B;
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
                        Development Plans
                    </h2>
                    <div class="btn-group">
                        <a href="development_activities.php" class="btn btn-outline-primary">
                            <i class="fas fa-tasks mr-2"></i>View Activities
                        </a>
                    </div>
                </div>

                <!-- Development Plans Grid -->
                <div class="row">
                    <?php if ($plans_result && $plans_result->num_rows > 0): ?>
                        <?php while ($plan = $plans_result->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card plan-card h-100">
                                <div class="plan-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($plan['plan_name']); ?></h5>
                                            <small><?php echo htmlspecialchars($plan['employee_name']); ?> (<?php echo htmlspecialchars($plan['employee_number']); ?>)</small>
                                        </div>
                                        <span class="plan-status status-<?php echo strtolower(str_replace(' ', '-', $plan['status'])); ?>"><?php echo htmlspecialchars($plan['status']); ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3"><?php echo htmlspecialchars($plan['plan_description'] ?: 'No description'); ?></p>

                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Start Date</small>
                                            <strong><?php echo date('M d, Y', strtotime($plan['start_date'])); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">End Date</small>
                                            <strong><?php echo date('M d, Y', strtotime($plan['end_date'])); ?></strong>
                                        </div>
                                    </div>

                                    <!-- Activity Summary -->
                                    <div class="mb-3 text-center">
                                        <small class="text-muted">
                                            <i class="fas fa-tasks mr-1"></i>
                                            <?php echo $plan['activity_count']; ?> Activities 
                                            (<?php echo $plan['completed_activities']; ?> completed)
                                        </small>
                                    </div>

                                    <div class="plan-actions text-center">
                                        <a href="development_activities.php?plan_id=<?php echo $plan['plan_id']; ?>" class="btn btn-sm btn-outline-info mr-2" title="View Activities">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-primary mr-2" onclick="editPlan(<?php echo $plan['plan_id']; ?>)">
                                            <i class="fas fa-edit"></i>
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
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Development Plans Found</h4>
                                <p class="text-muted">Click the + button to create your first development plan.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Plans Timeline -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history mr-2"></i>
                            Recent Plan Updates
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="plan-timeline">
                            <?php
                            $recent_updates_query = "SELECT dp.*, CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
                                                    ep.employee_number
                                                    FROM development_plans dp
                                                    JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                                                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                                                    ORDER BY dp.updated_at DESC LIMIT 10";

                            $recent_updates_result = $conn->query($recent_updates_query);

                            if ($recent_updates_result && $recent_updates_result->num_rows > 0):
                                while ($plan = $recent_updates_result->fetch_assoc()):
                                    $time_ago = time_elapsed_string($plan['updated_at']);
                            ?>
                                <div class="timeline-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($plan['plan_name']); ?> Updated</h6>
                                            <p class="text-muted mb-0">
                                                <?php echo htmlspecialchars($plan['employee_name']); ?> (<?php echo htmlspecialchars($plan['employee_number']); ?>)
                                                <span class="badge badge-<?php echo $plan['status'] == 'Completed' ? 'success' : ($plan['status'] == 'Active' ? 'primary' : 'warning'); ?>"><?php echo htmlspecialchars($plan['status']); ?></span>
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

    <!-- Add Development Plan Button -->
    <button class="btn btn-primary add-plan-btn" data-toggle="tooltip" title="Add New Development Plan" onclick="showAddPlanModal()">
        <i class="fas fa-plus"></i>
    </button>

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
                                <?php 
                                $employees_result->data_seek(0);
                                while ($employee = $employees_result->fetch_assoc()): ?>
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
                                <option value="Active">Active</option>
                                <option value="On Hold">On Hold</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" name="description" id="plan_description" rows="4"></textarea>
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

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });

        function showAddPlanModal() {
            $('#planModalLabel').text('Add New Development Plan');
            $('#planAction').val('add_plan');
            $('#planForm')[0].reset();
            $('#planId').val('');
            $('#planModal').modal('show');
        }

        function editPlan(planId) {
            $('#planModalLabel').text('Edit Development Plan');
            $('#planAction').val('edit_plan');
            $('#planId').val(planId);
            
            // Fetch plan data via AJAX
            $.ajax({
                url: 'development_plans.php?action=get_plan&plan_id=' + planId,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    $('#employee_id').val(data.employee_id);
                    $('#plan_name').val(data.plan_name);
                    $('#start_date').val(data.start_date);
                    $('#end_date').val(data.end_date);
                    $('#plan_status').val(data.status);
                    $('#plan_description').val(data.plan_description);
                    $('#planModal').modal('show');
                },
                error: function() {
                    alert('Error fetching plan data');
                }
            });
        }

        function deletePlan(planId) {
            if (confirm('Are you sure you want to delete this development plan? This will also delete all associated activities. This action cannot be undone.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_plan"><input type="hidden" name="plan_id" value="' + planId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
