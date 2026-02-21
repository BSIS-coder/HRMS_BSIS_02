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
            case 'add_trainer':
                // Add new trainer
                try {
                    $stmt = $pdo->prepare("INSERT INTO trainers (first_name, last_name, email, phone, specialization, bio, is_internal) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['specialization'],
                        $_POST['bio'],
                        isset($_POST['is_internal']) ? 1 : 0
                    ]);
                    $message = "Trainer added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding trainer: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update_trainer':
                // Update trainer
                try {
                    $stmt = $pdo->prepare("UPDATE trainers SET first_name=?, last_name=?, email=?, phone=?, specialization=?, bio=?, is_internal=? WHERE trainer_id=?");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['specialization'],
                        $_POST['bio'],
                        isset($_POST['is_internal']) ? 1 : 0,
                        $_POST['trainer_id']
                    ]);
                    $message = "Trainer updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating trainer: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_trainer':
                // Delete trainer
                try {
                    $stmt = $pdo->prepare("DELETE FROM trainers WHERE trainer_id=?");
                    $stmt->execute([$_POST['trainer_id']]);
                    $message = "Trainer deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting trainer: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch trainers with session count
$stmt = $pdo->query("
    SELECT 
        t.*,
        COUNT(ts.session_id) as session_count,
        COUNT(DISTINCT te.employee_id) as total_trainees
    FROM trainers t
    LEFT JOIN training_sessions ts ON t.trainer_id = ts.trainer_id
    LEFT JOIN training_enrollments te ON ts.session_id = te.session_id
    GROUP BY t.trainer_id
    ORDER BY t.last_name, t.first_name
");
$trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get trainer statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM trainers");
$totalTrainers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as internal FROM trainers WHERE is_internal = 1");
$internalTrainers = $stmt->fetch(PDO::FETCH_ASSOC)['internal'];

$stmt = $pdo->query("SELECT COUNT(*) as external FROM trainers WHERE is_internal = 0");
$externalTrainers = $stmt->fetch(PDO::FETCH_ASSOC)['external'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainers Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for trainers page */
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
        
        .container-fluid {
            padding: 0;
        }
        
        .row {
            margin-right: 0;
            margin-left: 0;
        }

        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .btn-primary {
            background: var(--azure-blue);
            border-color: var(--azure-blue);
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--azure-blue-dark);
            border-color: var(--azure-blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.3);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            font-weight: 600;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
            font-weight: 600;
            padding: 15px;
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-internal {
            background: #d4edda;
            color: #155724;
        }

        .status-external {
            background: #fff3cd;
            color: #856404;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stats-card i {
            font-size: 3rem;
            color: var(--azure-blue);
            margin-bottom: 15px;
        }

        .stats-card h3 {
            color: var(--azure-blue-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stats-card h6 {
            color: var(--text-muted);
            font-weight: 600;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.2);
        }

        .trainer-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .trainer-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .trainer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--azure-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin-right: 15px;
        }

        .trainer-info h5 {
            color: var(--azure-blue-dark);
            margin-bottom: 5px;
        }

        .trainer-stats {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 18px;
            font-weight: bold;
            color: var(--azure-blue);
        }

        .stat-label {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Trainers Management</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <h3><?php echo $totalTrainers; ?></h3>
                            <h6>Total Trainers</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-user-tie"></i>
                            <h3><?php echo $internalTrainers; ?></h3>
                            <h6>Internal Trainers</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-user-friends"></i>
                            <h3><?php echo $externalTrainers; ?></h3>
                            <h6>External Trainers</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-calendar-check"></i>
                            <h3><?php echo array_sum(array_column($trainers, 'session_count')); ?></h3>
                            <h6>Total Sessions</h6>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="controls">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="trainerSearch" placeholder="Search trainers...">
                    </div>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addTrainerModal">
                        <i class="fas fa-plus"></i> Add Trainer
                    </button>
                </div>

                <!-- Trainers Grid View -->
                <div class="row" id="trainersGrid">
                    <?php foreach ($trainers as $trainer): ?>
                    <div class="col-md-6 col-lg-4 trainer-item">
                        <div class="trainer-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="trainer-avatar">
                                    <?php echo strtoupper(substr($trainer['first_name'], 0, 1) . substr($trainer['last_name'], 0, 1)); ?>
                                </div>
                                <div class="trainer-info">
                                    <h5><?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?></h5>
                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($trainer['specialization']); ?></p>
                                    <span class="status-badge <?php echo $trainer['is_internal'] ? 'status-internal' : 'status-external'; ?>">
                                        <?php echo $trainer['is_internal'] ? 'Internal' : 'External'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($trainer['email']); ?><br>
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($trainer['phone']); ?>
                                </small>
                            </div>
                            
                            <div class="trainer-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $trainer['session_count']; ?></div>
                                    <div class="stat-label">Sessions</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $trainer['total_trainees']; ?></div>
                                    <div class="stat-label">Trainees</div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-primary" onclick="editTrainer(<?php echo $trainer['trainer_id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTrainer(<?php echo $trainer['trainer_id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Table View (Hidden by default) -->
                <div class="card" id="trainersTable" style="display: none;">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Trainers List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Specialization</th>
                                        <th>Type</th>
                                        <th>Sessions</th>
                                        <th>Trainees</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trainers as $trainer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($trainer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($trainer['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($trainer['specialization']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $trainer['is_internal'] ? 'status-internal' : 'status-external'; ?>">
                                                <?php echo $trainer['is_internal'] ? 'Internal' : 'External'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $trainer['session_count']; ?></td>
                                        <td><?php echo $trainer['total_trainees']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editTrainer(<?php echo $trainer['trainer_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTrainer(<?php echo $trainer['trainer_id']; ?>)">
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

    <!-- Add Trainer Modal -->
    <div class="modal fade" id="addTrainerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Trainer</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_trainer">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" class="form-control" name="phone">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Specialization *</label>
                            <input type="text" class="form-control" name="specialization" required>
                        </div>
                        <div class="form-group">
                            <label>Bio/Experience</label>
                            <textarea class="form-control" name="bio" rows="3" placeholder="Brief description of trainer's experience and expertise"></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_internal" id="isInternal" checked>
                            <label class="form-check-label" for="isInternal">Internal Trainer (Municipal Employee)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Trainer</button>
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
        $('#trainerSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('.trainer-item').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Edit trainer function
        function editTrainer(trainerId) {
            // Implement edit functionality
            alert('Edit trainer with ID: ' + trainerId);
        }

        // Delete trainer function
        function deleteTrainer(trainerId) {
            if (confirm('Are you sure you want to delete this trainer?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_trainer">
                    <input type="hidden" name="trainer_id" value="${trainerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
