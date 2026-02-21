<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection and helper functions
require_once 'dp.php';

// Database connection
$host = getenv('DB_HOST') ?? 'localhost';
$dbname = getenv('DB_NAME') ?? 'CC_HR';
$username = getenv('DB_USER') ?? 'root';
$password = getenv('DB_PASS') ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_stage':
                try {
                    $stmt = $pdo->prepare("INSERT INTO career_path_stages (path_id, stage_name, stage_order, description, requirements, estimated_duration, salary_range) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['path_id'],
                        $_POST['stage_name'],
                        $_POST['stage_order'],
                        $_POST['description'],
                        $_POST['requirements'],
                        $_POST['estimated_duration'],
                        $_POST['salary_range']
                    ]);
                    $message = "Career path stage added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding stage: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_stage':
                try {
                    $stmt = $pdo->prepare("DELETE FROM career_path_stages WHERE stage_id=?");
                    $stmt->execute([$_POST['stage_id']]);
                    $message = "Career path stage deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting stage: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch career path stages with related data
$stmt = $pdo->query("
    SELECT 
        cps.*,
        cp.path_name,
        cp.department
    FROM career_path_stages cps
    LEFT JOIN career_paths cp ON cps.path_id = cp.path_id
    ORDER BY cp.path_name, cps.stage_order
");
$stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch career paths for dropdown
$stmt = $pdo->query("
    SELECT 
        path_id,
        path_name,
        department
    FROM career_paths
    WHERE status = 'Active'
    ORDER BY path_name
");
$careerPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stages statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM career_path_stages");
$totalStages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT path_id) as paths FROM career_path_stages");
$pathsWithStages = $stmt->fetch(PDO::FETCH_ASSOC)['paths'];

$stmt = $pdo->query("SELECT AVG(estimated_duration) as avg_duration FROM career_path_stages");
$avgDuration = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_duration'], 1);

$stmt = $pdo->query("SELECT MAX(stage_order) as max_stages FROM career_path_stages");
$maxStages = $stmt->fetch(PDO::FETCH_ASSOC)['max_stages'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Path Stages Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .btn-primary {
            background: var(--azure-blue);
            border-color: var(--azure-blue);
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            font-weight: 600;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .stats-card i {
            font-size: 3rem;
            color: var(--azure-blue);
            margin-bottom: 15px;
        }

        .department-badge {
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .stage-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-left: 5px solid var(--azure-blue);
        }

        .stage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stage-title {
            color: var(--azure-blue-dark);
            font-weight: 600;
            margin: 0;
        }

        .stage-order {
            background: var(--azure-blue);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .stage-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #666;
        }

        .meta-item i {
            color: var(--azure-blue);
        }

        .salary-badge {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Career Path Stages Management</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-layer-group"></i>
                            <h3><?php echo $totalStages; ?></h3>
                            <h6>Total Stages</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-route"></i>
                            <h3><?php echo $pathsWithStages; ?></h3>
                            <h6>Paths with Stages</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-clock"></i>
                            <h3><?php echo $avgDuration; ?></h3>
                            <h6>Avg Duration (months)</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-sort-numeric-up"></i>
                            <h3><?php echo $maxStages; ?></h3>
                            <h6>Max Stages per Path</h6>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="input-group" style="max-width: 400px;">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control" id="stageSearch" placeholder="Search stages...">
                    </div>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addStageModal">
                        <i class="fas fa-plus"></i> Add Stage
                    </button>
                </div>

                <!-- Stages Grid -->
                <div class="row" id="stagesGrid">
                    <?php foreach ($stages as $stage): ?>
                    <div class="col-md-6 col-lg-4 stage-item">
                        <div class="stage-card">
                            <div class="stage-header">
                                <h5 class="stage-title"><?php echo htmlspecialchars($stage['stage_name']); ?></h5>
                                <div class="stage-order"><?php echo $stage['stage_order']; ?></div>
                            </div>
                            
                            <div class="stage-meta">
                                <div class="meta-item">
                                    <i class="fas fa-route"></i>
                                    <span><?php echo htmlspecialchars($stage['path_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span class="department-badge"><?php echo htmlspecialchars($stage['department']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $stage['estimated_duration']; ?> months</span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Description:</strong> <?php echo htmlspecialchars(substr($stage['description'], 0, 100)) . (strlen($stage['description']) > 100 ? '...' : ''); ?>
                                </small>
                            </div>
                            
                            <?php if ($stage['requirements']): ?>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Requirements:</strong> <?php echo htmlspecialchars(substr($stage['requirements'], 0, 100)) . (strlen($stage['requirements']) > 100 ? '...' : ''); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($stage['salary_range']): ?>
                            <div class="mb-3">
                                <span class="salary-badge">
                                    <i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($stage['salary_range']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        <i class="fas fa-id-card"></i> Stage ID: <?php echo $stage['stage_id']; ?>
                                    </small>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editStage(<?php echo $stage['stage_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteStage(<?php echo $stage['stage_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Stages Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-layer-group"></i> Career Path Stages List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Stage Name</th>
                                        <th>Career Path</th>
                                        <th>Department</th>
                                        <th>Duration</th>
                                        <th>Salary Range</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stages as $stage): ?>
                                    <tr>
                                        <td>
                                            <div class="stage-order"><?php echo $stage['stage_order']; ?></div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($stage['stage_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($stage['description'], 0, 50)) . (strlen($stage['description']) > 50 ? '...' : ''); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($stage['path_name']); ?></td>
                                        <td><span class="department-badge"><?php echo htmlspecialchars($stage['department']); ?></span></td>
                                        <td><?php echo $stage['estimated_duration']; ?> months</td>
                                        <td>
                                            <?php if ($stage['salary_range']): ?>
                                            <span class="salary-badge"><?php echo htmlspecialchars($stage['salary_range']); ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editStage(<?php echo $stage['stage_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteStage(<?php echo $stage['stage_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Stage Modal -->
    <div class="modal fade" id="addStageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Career Path Stage</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_stage">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Career Path *</label>
                                    <select class="form-control" name="path_id" required>
                                        <option value="">Select Career Path</option>
                                        <?php foreach ($careerPaths as $path): ?>
                                        <option value="<?php echo $path['path_id']; ?>">
                                            <?php echo htmlspecialchars($path['path_name'] . ' (' . $path['department'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Stage Name *</label>
                                    <input type="text" class="form-control" name="stage_name" required placeholder="e.g., Senior Administrative Officer">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Stage Order *</label>
                                    <input type="number" class="form-control" name="stage_order" required min="1" max="20" placeholder="e.g., 2">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Estimated Duration (months) *</label>
                                    <input type="number" class="form-control" name="estimated_duration" required min="1" max="60" placeholder="e.g., 12">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description *</label>
                            <textarea class="form-control" name="description" rows="3" required placeholder="Describe the stage and responsibilities"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Requirements</label>
                            <textarea class="form-control" name="requirements" rows="3" placeholder="Skills, experience, and qualifications required for this stage"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Salary Range</label>
                            <input type="text" class="form-control" name="salary_range" placeholder="e.g., ₱25,000 - ₱35,000">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success/Error Message Modal -->
    <?php if ($message): ?>
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header <?php echo $messageType === 'success' ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <h5 class="modal-title">
                        <?php echo $messageType === 'success' ? '<i class="fas fa-check-circle"></i> Success' : '<i class="fas fa-exclamation-circle"></i> Error'; ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Show message modal if there's a message
        <?php if ($message): ?>
        $(document).ready(function() {
            $('#messageModal').modal('show');
        });
        <?php endif; ?>

        // Search functionality
        $('#stageSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('.stage-item').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Edit stage function
        function editStage(stageId) {
            alert('Edit stage with ID: ' + stageId);
        }

        // Delete stage function
        function deleteStage(stageId) {
            if (confirm('Are you sure you want to delete this career path stage?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_stage">
                    <input type="hidden" name="stage_id" value="${stageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
