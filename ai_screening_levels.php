<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Only admin and HR can manage screening levels
if (!in_array($_SESSION['role'], ['admin', 'hr'])) {
    die('Access denied. Only administrators and HR can manage AI screening levels.');
}

require_once 'db_connect.php';

$success_message = '';
$error_message = '';

// Handle screening level updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_level') {
        $job_id = (int)$_POST['job_id'];
        $new_level = $_POST['screening_level'];
        
        if (in_array($new_level, ['Easy', 'Moderate', 'Strict'])) {
            $stmt = $conn->prepare("UPDATE job_openings SET screening_level = ? WHERE job_opening_id = ?");
            $stmt->bind_param('si', $new_level, $job_id);
            
            if ($stmt->execute()) {
                $success_message = "✅ AI screening level updated successfully!";
            } else {
                $error_message = "❌ Failed to update screening level.";
            }
        }
    } elseif ($_POST['action'] === 'bulk_update') {
        $level = $_POST['bulk_level'];
        $job_ids = $_POST['job_ids'] ?? [];
        
        if (in_array($level, ['Easy', 'Moderate', 'Strict']) && count($job_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
            $types = str_repeat('i', count($job_ids));
            
            $stmt = $conn->prepare("UPDATE job_openings SET screening_level = ? WHERE job_opening_id IN ($placeholders)");
            $params = array_merge([$level], $job_ids);
            $stmt->bind_param('s' . $types, ...$params);
            
            if ($stmt->execute()) {
                $success_message = "✅ Updated " . count($job_ids) . " job(s) to " . $level . " screening level!";
            } else {
                $error_message = "❌ Failed to update screening levels.";
            }
        }
    }
}

// Get all job openings with their current screening levels
$query = "SELECT jo.*, d.department_name, 
          COUNT(DISTINCT ja.application_id) as total_applications,
          COUNT(DISTINCT CASE WHEN ja.assessment_scores IS NOT NULL THEN ja.application_id END) as screened_count
          FROM job_openings jo
          LEFT JOIN departments d ON jo.department_id = d.department_id
          LEFT JOIN job_applications ja ON jo.job_opening_id = ja.job_opening_id
          WHERE jo.status IN ('Open', 'On Hold')
          GROUP BY jo.job_opening_id
          ORDER BY jo.posting_date DESC";
$result = $conn->query($query);
$jobs = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Screening Levels - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .level-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .level-easy { background: #d4edda; color: #155724; }
        .level-moderate { background: #fff3cd; color: #856404; }
        .level-strict { background: #f8d7da; color: #721c24; }
        
        .job-card {
            border-left: 4px solid #E91E63;
            transition: all 0.3s ease;
        }
        .job-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
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
                    <h2><i class="fas fa-sliders-h mr-2"></i>AI Screening Levels</h2>
                    <a href="job_applications.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i>Back
                    </a>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_message; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <!-- Info Box -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-body">
                                <h6 class="text-success"><strong>🟢 Easy - Inclusive</strong></h6>
                                <p class="small mb-0">Focus on potential & trainability. Candidates need 50%+ match. Good for entry-level positions.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-body">
                                <h6 class="text-warning"><strong>🟡 Moderate - Balanced</strong></h6>
                                <p class="small mb-0">Realistic standards. Candidates need 65%+ match. Recommended for most positions.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-danger">
                            <div class="card-body">
                                <h6 class="text-danger"><strong>🔴 Strict - Selective</strong></h6>
                                <p class="small mb-0">High standards. Candidates need 80%+ match. For senior or specialized positions.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <?php if (count($jobs) > 0): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-tasks mr-2"></i>Bulk Update</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="bulkForm">
                                <input type="hidden" name="action" value="bulk_update">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label><strong>Select Level:</strong></label>
                                        <select name="bulk_level" class="form-control" required>
                                            <option value="Easy">🟢 Easy</option>
                                            <option value="Moderate" selected>🟡 Moderate</option>
                                            <option value="Strict">🔴 Strict</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-primary btn-block" onclick="return confirmBulkUpdate()">
                                                <i class="fas fa-check mr-1"></i>Apply to Selected
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div id="selectedCount" class="mt-2 text-muted"></div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Jobs List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-briefcase mr-2"></i>Active Positions (<?php echo count($jobs); ?>)</h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                <i class="fas fa-check-square mr-1"></i>Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-1" onclick="deselectAll()">
                                <i class="fas fa-square mr-1"></i>Clear
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($jobs) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                                            </th>
                                            <th>Position</th>
                                            <th width="150">Department</th>
                                            <th width="100" class="text-center">Apps</th>
                                            <th width="100" class="text-center">Screened</th>
                                            <th width="150">Current Level</th>
                                            <th width="150">Change To</th>
                                            <th width="100"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jobs as $job): 
                                            $levelClass = [
                                                'Easy' => 'level-easy',
                                                'Moderate' => 'level-moderate',
                                                'Strict' => 'level-strict'
                                            ][$job['screening_level']] ?? 'level-moderate';
                                            
                                            $levelIcon = [
                                                'Easy' => '🟢',
                                                'Moderate' => '🟡',
                                                'Strict' => '🔴'
                                            ][$job['screening_level']] ?? '⚪';
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="job-checkbox" name="job_ids[]" value="<?php echo $job['job_opening_id']; ?>" form="bulkForm" onchange="updateSelectedCount()">
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($job['title']); ?></strong><br>
                                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($job['posting_date'])); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($job['department_name']); ?></td>
                                                <td class="text-center">
                                                    <span class="badge badge-info"><?php echo $job['total_applications']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($job['screened_count'] > 0): ?>
                                                        <span class="badge badge-success"><?php echo $job['screened_count']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-light">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="level-badge <?php echo $levelClass; ?>">
                                                        <?php echo $levelIcon; ?> <?php echo $job['screening_level']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_level">
                                                        <input type="hidden" name="job_id" value="<?php echo $job['job_opening_id']; ?>">
                                                        <select name="screening_level" class="form-control form-control-sm" onchange="this.form.submit()">
                                                            <option value="Easy" <?php echo $job['screening_level'] === 'Easy' ? 'selected' : ''; ?>>🟢 Easy</option>
                                                            <option value="Moderate" <?php echo $job['screening_level'] === 'Moderate' ? 'selected' : ''; ?>>🟡 Moderate</option>
                                                            <option value="Strict" <?php echo $job['screening_level'] === 'Strict' ? 'selected' : ''; ?>>🔴 Strict</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td class="text-center">
                                                    <a href="job_applications.php?job=<?php echo $job['job_opening_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Applications">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5>No Active Job Openings</h5>
                                <p class="text-muted">Create job openings to manage their AI screening levels.</p>
                                <a href="job_openings.php" class="btn btn-primary">
                                    <i class="fas fa-plus mr-1"></i>Create Job Opening
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function toggleAll(checkbox) {
            $('.job-checkbox').prop('checked', checkbox.checked);
            updateSelectedCount();
        }
        
        function selectAll() {
            $('.job-checkbox').prop('checked', true);
            $('#selectAllCheckbox').prop('checked', true);
            updateSelectedCount();
        }
        
        function deselectAll() {
            $('.job-checkbox').prop('checked', false);
            $('#selectAllCheckbox').prop('checked', false);
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const count = $('.job-checkbox:checked').length;
            if (count > 0) {
                $('#selectedCount').html(`<i class="fas fa-check-circle text-success mr-1"></i><strong>${count} job(s) selected</strong>`);
            } else {
                $('#selectedCount').html('');
            }
        }
        
        function confirmBulkUpdate() {
            const count = $('.job-checkbox:checked').length;
            if (count === 0) {
                alert('Please select at least one job to update.');
                return false;
            }
            const level = $('select[name="bulk_level"]').val();
            return confirm(`Are you sure you want to change ${count} job(s) to ${level} screening level?`);
        }
        
        // Initialize
        updateSelectedCount();
    </script>
</body>
</html>
