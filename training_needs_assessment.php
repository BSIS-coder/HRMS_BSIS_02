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
            case 'add_assessment':
                // Add new training needs assessment
                try {
                    $stmt = $pdo->prepare("INSERT INTO training_needs_assessment (employee_id, assessment_date, current_skills, desired_skills, skill_gaps, training_recommendations, priority_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['assessment_date'],
                        $_POST['current_skills'],
                        $_POST['desired_skills'],
                        $_POST['skill_gaps'],
                        $_POST['training_recommendations'],
                        $_POST['priority_level'],
                        $_POST['status']
                    ]);
                    $message = "Training needs assessment added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding assessment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update_assessment':
                // Update training needs assessment
                try {
                    $stmt = $pdo->prepare("UPDATE training_needs_assessment SET assessment_date=?, current_skills=?, desired_skills=?, skill_gaps=?, training_recommendations=?, priority_level=?, status=? WHERE assessment_id=?");
                    $stmt->execute([
                        $_POST['assessment_date'],
                        $_POST['current_skills'],
                        $_POST['desired_skills'],
                        $_POST['skill_gaps'],
                        $_POST['training_recommendations'],
                        $_POST['priority_level'],
                        $_POST['status'],
                        $_POST['assessment_id']
                    ]);
                    $message = "Training needs assessment updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating assessment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_assessment':
                // Delete training needs assessment
                try {
                    $stmt = $pdo->prepare("DELETE FROM training_needs_assessment WHERE assessment_id=?");
                    $stmt->execute([$_POST['assessment_id']]);
                    $message = "Training needs assessment deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting assessment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch training needs assessments with employee data
$stmt = $pdo->query("
    SELECT 
        tna.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        jr.title as job_title,
        jr.department
    FROM training_needs_assessment tna
    LEFT JOIN employee_profiles ep ON tna.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY tna.assessment_date DESC
");
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        jr.title as job_title,
        jr.department
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY pi.first_name, pi.last_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assessment statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM training_needs_assessment");
$totalAssessments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as pending FROM training_needs_assessment WHERE status = 'Pending'");
$pendingAssessments = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];

$stmt = $pdo->query("SELECT COUNT(*) as completed FROM training_needs_assessment WHERE status = 'Completed'");
$completedAssessments = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];

$stmt = $pdo->query("SELECT COUNT(*) as high_priority FROM training_needs_assessment WHERE priority_level = 'High'");
$highPriorityAssessments = $stmt->fetch(PDO::FETCH_ASSOC)['high_priority'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Needs Assessment - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for training needs assessment page */
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

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .priority-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-low {
            background: #d4edda;
            color: #155724;
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

        .assessment-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 5px solid var(--azure-blue);
        }

        .assessment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .assessment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .assessment-title {
            color: var(--azure-blue-dark);
            font-weight: 600;
            margin: 0;
        }

        .assessment-meta {
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

        .skills-display {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .skills-section {
            margin-bottom: 15px;
        }

        .skills-section h6 {
            color: var(--azure-blue-dark);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .skill-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .skill-tag {
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid var(--azure-blue);
            background: transparent;
            color: var(--azure-blue);
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background: var(--azure-blue);
            color: white;
        }

        .department-badge {
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
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
                <h2 class="section-title">Training Needs Assessment</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-clipboard-list"></i>
                            <h3><?php echo $totalAssessments; ?></h3>
                            <h6>Total Assessments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-clock"></i>
                            <h3><?php echo $pendingAssessments; ?></h3>
                            <h6>Pending Reviews</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-check-circle"></i>
                            <h3><?php echo $completedAssessments; ?></h3>
                            <h6>Completed</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3><?php echo $highPriorityAssessments; ?></h3>
                            <h6>High Priority</h6>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="controls">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="assessmentSearch" placeholder="Search assessments...">
                    </div>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addAssessmentModal">
                        <i class="fas fa-plus"></i> Add Assessment
                    </button>
                </div>

                <!-- Filter Buttons -->
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All Assessments</button>
                    <button class="filter-btn" data-filter="Pending">Pending</button>
                    <button class="filter-btn" data-filter="In Progress">In Progress</button>
                    <button class="filter-btn" data-filter="Completed">Completed</button>
                    <button class="filter-btn" data-filter="High">High Priority</button>
                    <button class="filter-btn" data-filter="Medium">Medium Priority</button>
                    <button class="filter-btn" data-filter="Low">Low Priority</button>
                </div>

                <!-- Assessments Grid View -->
                <div class="row" id="assessmentsGrid">
                    <?php foreach ($assessments as $assessment): ?>
                    <div class="col-md-6 col-lg-4 assessment-item" 
                         data-status="<?php echo $assessment['status']; ?>"
                         data-priority="<?php echo $assessment['priority_level']; ?>">
                        <div class="assessment-card">
                            <div class="assessment-header">
                                <h5 class="assessment-title"><?php echo htmlspecialchars($assessment['employee_name']); ?></h5>
                                <div>
                                    <span class="priority-badge priority-<?php echo strtolower($assessment['priority_level']); ?>">
                                        <?php echo htmlspecialchars($assessment['priority_level']); ?>
                                    </span>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $assessment['status'])); ?>">
                                        <?php echo htmlspecialchars($assessment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="assessment-meta">
                                <div class="meta-item">
                                    <i class="fas fa-briefcase"></i>
                                    <span><?php echo htmlspecialchars($assessment['job_title']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span class="department-badge"><?php echo htmlspecialchars($assessment['department']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('M d, Y', strtotime($assessment['assessment_date'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="skills-display">
                                <div class="skills-section">
                                    <h6><i class="fas fa-star"></i> Current Skills</h6>
                                    <div class="skill-tags">
                                        <?php 
                                        $currentSkills = explode(',', $assessment['current_skills']);
                                        foreach (array_slice($currentSkills, 0, 3) as $skill): 
                                        ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($currentSkills) > 3): ?>
                                        <span class="skill-tag">+<?php echo count($currentSkills) - 3; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="skills-section">
                                    <h6><i class="fas fa-target"></i> Desired Skills</h6>
                                    <div class="skill-tags">
                                        <?php 
                                        $desiredSkills = explode(',', $assessment['desired_skills']);
                                        foreach (array_slice($desiredSkills, 0, 3) as $skill): 
                                        ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($desiredSkills) > 3): ?>
                                        <span class="skill-tag">+<?php echo count($desiredSkills) - 3; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="skills-section">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Skill Gaps</h6>
                                    <div class="skill-tags">
                                        <?php 
                                        $skillGaps = explode(',', $assessment['skill_gaps']);
                                        foreach (array_slice($skillGaps, 0, 3) as $gap): 
                                        ?>
                                        <span class="skill-tag" style="background: #f8d7da; color: #721c24;"><?php echo htmlspecialchars(trim($gap)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($skillGaps) > 3): ?>
                                        <span class="skill-tag">+<?php echo count($skillGaps) - 3; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Training Recommendations:</strong><br>
                                    <?php echo htmlspecialchars(substr($assessment['training_recommendations'], 0, 100)) . (strlen($assessment['training_recommendations']) > 100 ? '...' : ''); ?>
                                </small>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> Assessment ID: <?php echo $assessment['assessment_id']; ?>
                                    </small>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssessment(<?php echo $assessment['assessment_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAssessment(<?php echo $assessment['assessment_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Table View (Hidden by default) -->
                <div class="card" id="assessmentsTable" style="display: none;">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Training Needs Assessment List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Assessment Date</th>
                                        <th>Current Skills</th>
                                        <th>Desired Skills</th>
                                        <th>Skill Gaps</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assessments as $assessment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($assessment['employee_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($assessment['job_title']); ?></small>
                                        </td>
                                        <td><span class="department-badge"><?php echo htmlspecialchars($assessment['department']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($assessment['assessment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($assessment['current_skills'], 0, 30)) . (strlen($assessment['current_skills']) > 30 ? '...' : ''); ?></td>
                                        <td><?php echo htmlspecialchars(substr($assessment['desired_skills'], 0, 30)) . (strlen($assessment['desired_skills']) > 30 ? '...' : ''); ?></td>
                                        <td><?php echo htmlspecialchars(substr($assessment['skill_gaps'], 0, 30)) . (strlen($assessment['skill_gaps']) > 30 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="priority-badge priority-<?php echo strtolower($assessment['priority_level']); ?>">
                                                <?php echo htmlspecialchars($assessment['priority_level']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $assessment['status'])); ?>">
                                                <?php echo htmlspecialchars($assessment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editAssessment(<?php echo $assessment['assessment_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteAssessment(<?php echo $assessment['assessment_id']; ?>)">
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

    <!-- Add Assessment Modal -->
    <div class="modal fade" id="addAssessmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Training Needs Assessment</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_assessment">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Employee *</label>
                                    <select class="form-control" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['employee_id']; ?>">
                                            <?php echo htmlspecialchars($employee['employee_name'] . ' (' . $employee['job_title'] . ' - ' . $employee['department'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Assessment Date *</label>
                                    <input type="date" class="form-control" name="assessment_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Current Skills</label>
                            <textarea class="form-control" name="current_skills" rows="3" placeholder="Enter current skills (comma-separated)"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Desired Skills</label>
                            <textarea class="form-control" name="desired_skills" rows="3" placeholder="Enter desired skills (comma-separated)"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Skill Gaps</label>
                            <textarea class="form-control" name="skill_gaps" rows="3" placeholder="Enter identified skill gaps (comma-separated)"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Training Recommendations</label>
                            <textarea class="form-control" name="training_recommendations" rows="3" placeholder="Enter training recommendations and action plan"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Priority Level</label>
                                    <select class="form-control" name="priority_level">
                                        <option value="Low">Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" name="status">
                                        <option value="Pending">Pending</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Assessment</button>
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
        $('#assessmentSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('.assessment-item').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Filter functionality
        $('.filter-btn').click(function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            
            var filter = $(this).data('filter');
            
            if (filter === 'all') {
                $('.assessment-item').show();
            } else if (['High', 'Medium', 'Low'].includes(filter)) {
                $('.assessment-item').hide();
                $('.assessment-item[data-priority="' + filter + '"]').show();
            } else {
                $('.assessment-item').hide();
                $('.assessment-item[data-status="' + filter + '"]').show();
            }
        });

        // Edit assessment function
        function editAssessment(assessmentId) {
            // Implement edit functionality
            alert('Edit assessment with ID: ' + assessmentId);
        }

        // Delete assessment function
        function deleteAssessment(assessmentId) {
            if (confirm('Are you sure you want to delete this training needs assessment?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_assessment">
                    <input type="hidden" name="assessment_id" value="${assessmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
