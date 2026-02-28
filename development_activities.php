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
            $activity_id = $_POST['activity_id'];
            $stmt = $conn->prepare("DELETE FROM development_activities WHERE activity_id = ?");
            $stmt->bind_param("i", $activity_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Handle AJAX request for fetching activity data
if (isset($_GET['action']) && $_GET['action'] == 'get_activity' && isset($_GET['activity_id'])) {
    $activity_id = $_GET['activity_id'];
    $stmt = $conn->prepare("SELECT * FROM development_activities WHERE activity_id = ?");
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $activity = $result->fetch_assoc();
    $stmt->close();
    echo json_encode($activity);
    exit;
}

// Get filter from URL if present
$filter_plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;

// Get all development activities grouped by plan
$activities_query = "SELECT da.*, dp.plan_name, dp.status as plan_status,
                    CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
                    ep.employee_number, jr.title as job_title
                    FROM development_activities da
                    JOIN development_plans dp ON da.plan_id = dp.plan_id
                    JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id";

if ($filter_plan_id > 0) {
    $activities_query .= " WHERE da.plan_id = " . $filter_plan_id;
}

$activities_query .= " ORDER BY dp.plan_name, da.created_at DESC";

$activities_result = $conn->query($activities_query);

// Group activities by plan
$grouped_activities = [];
if ($activities_result && $activities_result->num_rows > 0) {
    while ($row = $activities_result->fetch_assoc()) {
        $plan_key = $row['plan_id'];
        if (!isset($grouped_activities[$plan_key])) {
            $grouped_activities[$plan_key] = [
                'plan_name' => $row['plan_name'],
                'plan_status' => $row['plan_status'],
                'employee_name' => $row['employee_name'],
                'employee_number' => $row['employee_number'],
                'activities' => []
            ];
        }
        $grouped_activities[$plan_key]['activities'][] = $row;
    }
}

// Get statistics
$stats_query = "SELECT
    COUNT(*) as total_activities,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_activities,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_activities,
    SUM(CASE WHEN status = 'Not Started' THEN 1 ELSE 0 END) as not_started_activities
    FROM development_activities";

if ($filter_plan_id > 0) {
    $stats_query .= " WHERE plan_id = " . $filter_plan_id;
}

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get plans for dropdown
$plans_query = "SELECT dp.plan_id, dp.plan_name, dp.status as plan_status,
                CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
                FROM development_plans dp
                JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                ORDER BY dp.plan_name";

$plans_result = $conn->query($plans_query);

// Get selected plan info if filtering
$selected_plan = null;
if ($filter_plan_id > 0) {
    $selected_plan_query = "SELECT dp.*, 
                          CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
                          FROM development_plans dp
                          JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                          JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                          WHERE dp.plan_id = " . $filter_plan_id;
    $selected_plan_result = $conn->query($selected_plan_query);
    $selected_plan = $selected_plan_result->fetch_assoc();
}
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
        /* Different layout design - List/Table style instead of cards */
        .activity-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .activity-section-header {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .activity-section-header:hover {
            background: linear-gradient(135deg, #d81b60 0%, #ad1457 100%);
        }
        
        .activity-section-header .plan-info h5 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .activity-section-header .plan-info small {
            opacity: 0.9;
        }
        
        .activity-section-header .activity-count {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s ease;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background: #f8f9ff;
        }
        
        .activity-item .activity-info {
            flex: 1;
        }
        
        .activity-item .activity-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }
        
        .activity-item .activity-meta {
            font-size: 0.85rem;
            color: #666;
        }
        
        .activity-item .activity-meta i {
            margin-right: 5px;
            color: #C2185B;
        }
        
        .activity-item .activity-dates {
            font-size: 0.8rem;
            color: #999;
            margin-top: 3px;
        }
        
        .activity-item .activity-actions {
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .activity-item:hover .activity-actions {
            opacity: 1;
        }
        
        /* Status badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-completed { background: #d4edda; color: #155724; }
        .status-in-progress { background: #fff3cd; color: #856404; }
        .status-not-started { background: #e2e3e5; color: #383d41; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        /* Type badges */
        .type-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            background: #e9ecef;
            color: #495057;
            margin-left: 10px;
        }
        
        /* Stats cards - different style */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #C2185B;
        }
        
        .stat-card:nth-child(2) { border-left-color: #E91E63; }
        .stat-card:nth-child(3) { border-left-color: #f06292; }
        .stat-card:nth-child(4) { border-left-color: #ec407a; }
        
        .stat-card .stat-icon {
            font-size: 1.8rem;
            color: #C2185B;
            margin-bottom: 10px;
        }
        
        .stat-card:nth-child(2) .stat-icon { color: #E91E63; }
        .stat-card:nth-child(3) .stat-icon { color: #f06292; }
        .stat-card:nth-child(4) .stat-icon { color: #ec407a; }
        
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
        }
        
        .stat-card .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        /* Floating action button */
        .add-activity-btn {
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
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .add-activity-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        /* Timeline style */
        .timeline-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 30px;
        }
        
        .timeline-section h5 {
            color: #C2185B;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .timeline-entry {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .timeline-entry:last-child {
            border-bottom: none;
        }
        
        .timeline-entry .timeline-time {
            min-width: 100px;
            color: #999;
            font-size: 0.85rem;
        }
        
        .timeline-entry .timeline-content {
            flex: 1;
        }
        
        .timeline-entry .timeline-content h6 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .timeline-entry .timeline-content p {
            margin: 0;
            font-size: 0.85rem;
            color: #666;
        }
        
        /* Expand/Collapse icon */
        .expand-icon {
            transition: transform 0.3s ease;
        }
        
        .activity-section.collapsed .expand-icon {
            transform: rotate(-90deg);
        }
        
        .activity-section.collapsed .activity-list {
            display: none;
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .activity-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .activity-item .activity-actions {
                opacity: 1;
                margin-top: 10px;
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
                <!-- Header Section -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title mb-0">
                            <i class="fas fa-tasks mr-3"></i>
                            Development Activities
                        </h2>
                        <?php if ($selected_plan): ?>
                            <small class="text-muted">
                                <i class="fas fa-filter mr-1"></i>
                                Filtered by: <strong><?php echo htmlspecialchars($selected_plan['plan_name']); ?></strong>
                                (<?php echo htmlspecialchars($selected_plan['employee_name']); ?>)
                                <a href="development_activities.php" class="ml-2"><i class="fas fa-times"></i> Clear</a>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="btn-group">
                        <a href="development_plans.php" class="btn btn-outline-primary">
                            <i class="fas fa-calendar-check mr-2"></i>View Plans
                        </a>
                    </div>
                </div>

                <!-- Activities by Plan Sections -->
                <?php if (!empty($grouped_activities)): ?>
                    <?php foreach ($grouped_activities as $plan_id => $plan_data): ?>
                    <div class="activity-section" id="section-<?php echo $plan_id; ?>">
                        <div class="activity-section-header" onclick="toggleSection(<?php echo $plan_id; ?>)">
                            <div class="plan-info">
                                <h5><i class="fas fa-folder-open mr-2"></i><?php echo htmlspecialchars($plan_data['plan_name']); ?></h5>
                                <small><?php echo htmlspecialchars($plan_data['employee_name']); ?> (<?php echo htmlspecialchars($plan_data['employee_number']); ?>)</small>
                            </div>
                            <div class="activity-count">
                                <i class="fas fa-tasks mr-2"></i>
                                <?php echo count($plan_data['activities']); ?> Activities
                                <i class="fas fa-chevron-down ml-2 expand-icon"></i>
                            </div>
                        </div>
                        <ul class="activity-list">
                            <?php foreach ($plan_data['activities'] as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-info">
                                    <div class="activity-name">
                                        <?php echo htmlspecialchars($activity['activity_name']); ?>
                                        <span class="type-badge"><?php echo htmlspecialchars($activity['activity_type']); ?></span>
                                    </div>
                                    <div class="activity-meta">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($activity['start_date'])); ?> - <?php echo date('M d, Y', strtotime($activity['end_date'])); ?>
                                    </div>
                                </div>
                                <div class="activity-actions">
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $activity['status'])); ?>">
                                        <?php echo htmlspecialchars($activity['status']); ?>
                                    </span>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editActivity(<?php echo $activity['activity_id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteActivity(<?php echo $activity['activity_id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No Development Activities Found</h4>
                        <p class="text-muted">Click the + button to create your first development activity.</p>
                    </div>
                <?php endif; ?>

                <!-- Recent Updates Timeline -->
                <div class="timeline-section">
                    <h5><i class="fas fa-history mr-2"></i>Recent Activity Updates</h5>
                    <?php
                    $recent_query = "SELECT da.*, dp.plan_name,
                                    CONCAT(pi.first_name, ' ', pi.last_name) as employee_name
                                    FROM development_activities da
                                    JOIN development_plans dp ON da.plan_id = dp.plan_id
                                    JOIN employee_profiles ep ON dp.employee_id = ep.employee_id
                                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                                    ORDER BY da.updated_at DESC LIMIT 5";
                    
                    $recent_result = $conn->query($recent_query);
                    
                    if ($recent_result && $recent_result->num_rows > 0):
                        while ($item = $recent_result->fetch_assoc()):
                            $time_ago = time_elapsed_string($item['updated_at']);
                    ?>
                        <div class="timeline-entry">
                            <div class="timeline-time"><?php echo $time_ago; ?></div>
                            <div class="timeline-content">
                                <h6><?php echo htmlspecialchars($item['activity_name']); ?></h6>
                                <p>
                                    <?php echo htmlspecialchars($item['employee_name']); ?> - 
                                    <span class="type-badge"><?php echo htmlspecialchars($item['activity_type']); ?></span> in 
                                    "<?php echo htmlspecialchars($item['plan_name']); ?>"
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $item['status'])); ?> ml-2"><?php echo htmlspecialchars($item['status']); ?></span>
                                </p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No recent updates found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Add Button -->
    <button class="add-activity-btn" data-toggle="tooltip" title="Add New Activity" onclick="showAddActivityModal()">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Add/Edit Activity Modal -->
    <div class="modal fade" id="activityModal" tabindex="-1" role="dialog" aria-labelledby="activityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%); color: white;">
                    <h5 class="modal-title" id="activityModalLabel">Add New Development Activity</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="activityForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="activityAction" value="add_activity">
                        <input type="hidden" name="activity_id" id="activityId">

                        <div class="form-group">
                            <label for="plan_id">Development Plan</label>
                            <select class="form-control" name="plan_id" id="plan_id" required>
                                <option value="">Select Plan</option>
                                <?php 
                                $plans_result->data_seek(0);
                                while ($plan = $plans_result->fetch_assoc()): ?>
                                    <option value="<?php echo $plan['plan_id']; ?>" <?php echo ($filter_plan_id == $plan['plan_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($plan['plan_name']); ?> - <?php echo htmlspecialchars($plan['employee_name']); ?> (<?php echo htmlspecialchars($plan['plan_status']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="activity_name">Activity Name</label>
                                    <input type="text" class="form-control" name="activity_name" id="activity_name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="activity_type">Activity Type</label>
                                    <select class="form-control" name="activity_type" id="activity_type" required>
                                        <option value="Training">Training</option>
                                        <option value="Mentoring">Mentoring</option>
                                        <option value="Project">Project</option>
                                        <option value="Certification">Certification</option>
                                        <option value="Assessment">Assessment</option>
                                        <option value="Seminar">Seminar</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
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
                            <select class="form-control" name="status" id="activity_status" required>
                                <option value="Not Started">Not Started</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" name="description" id="activity_description" rows="3" placeholder="Describe the activity..."></textarea>
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

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });

        function toggleSection(planId) {
            $('#section-' + planId).toggleClass('collapsed');
        }

        function showAddActivityModal() {
            $('#activityModalLabel').text('Add New Development Activity');
            $('#activityAction').val('add_activity');
            $('#activityForm')[0].reset();
            $('#activityId').val('');
            $('#activityModal').modal('show');
        }

        function editActivity(activityId) {
            $('#activityModalLabel').text('Edit Development Activity');
            $('#activityAction').val('edit_activity');
            $('#activityId').val(activityId);
            
            $.ajax({
                url: 'development_activities.php?action=get_activity&activity_id=' + activityId,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    $('#plan_id').val(data.plan_id);
                    $('#activity_name').val(data.activity_name);
                    $('#activity_type').val(data.activity_type);
                    $('#start_date').val(data.start_date);
                    $('#end_date').val(data.end_date);
                    $('#activity_status').val(data.status);
                    $('#activity_description').val(data.description);
                    $('#activityModal').modal('show');
                },
                error: function() {
                    alert('Error fetching activity data');
                }
            });
        }

        function deleteActivity(activityId) {
            if (confirm('Are you sure you want to delete this development activity?')) {
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
