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

// Include database connection
require_once 'config.php';

// Use the global database connection
$pdo = $conn;

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_skill':
                try {
                    $stmt = $pdo->prepare("INSERT INTO skill_matrix (skill_name, description, category) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $_POST['skill_name'],
                        $_POST['description'],
                        $_POST['category']
                    ]);
                    $message = "Skill added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding skill: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'add_employee_skill':
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_skills (employee_id, skill_id, proficiency_level, assessed_date, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['skill_id'],
                        $_POST['proficiency_level'],
                        $_POST['assessed_date'],
                        $_POST['notes']
                    ]);
                    $message = "Employee skill assessment added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding employee skill: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'add_assessment':
                try {
                    $stmt = $pdo->prepare("INSERT INTO training_needs_assessment (employee_id, assessment_date, skills_gap, recommended_trainings, priority, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['assessment_date'],
                        $_POST['skills_gap'],
                        $_POST['recommended_trainings'],
                        $_POST['priority'],
                        $_POST['status']
                    ]);
                    $message = "Training needs assessment added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding assessment: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_skill':
                try {
                    $stmt = $pdo->prepare("DELETE FROM skill_matrix WHERE skill_id=?");
                    $stmt->execute([$_POST['skill_id']]);
                    $message = "Skill deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting skill: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'delete_employee_skill':
                try {
                    $stmt = $pdo->prepare("DELETE FROM employee_skills WHERE employee_skill_id=?");
                    $stmt->execute([$_POST['employee_skill_id']]);
                    $message = "Employee skill deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting employee skill: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'delete_assessment':
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

// Fetch skills
try {
    $stmt = $pdo->query("SELECT * FROM skill_matrix ORDER BY skill_name");
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $skills = [];
    $message = "Error fetching skills: " . $e->getMessage();
    $messageType = "error";
}

// Fetch employee skills with employee and skill details
try {
    $stmt = $pdo->query("
        SELECT es.*, e.first_name, e.last_name, s.skill_name, s.category 
        FROM employee_skills es 
        JOIN employee_profiles ep ON es.employee_id = ep.employee_id 
        JOIN personal_information e ON ep.personal_info_id = e.personal_info_id 
        JOIN skill_matrix s ON es.skill_id = s.skill_id 
        ORDER BY e.last_name, s.skill_name
    ");
    $employeeSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employeeSkills = [];
}

// Fetch training needs assessments
try {
    $stmt = $pdo->query("
        SELECT tna.*, e.first_name, e.last_name 
        FROM training_needs_assessment tna 
        JOIN employee_profiles ep ON tna.employee_id = ep.employee_id 
        JOIN personal_information e ON ep.personal_info_id = e.personal_info_id 
        ORDER BY tna.assessment_date DESC
    ");
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $assessments = [];
}

// Fetch employees for dropdowns
try {
    $stmt = $pdo->query("
        SELECT ep.employee_id, pi.first_name, pi.last_name 
        FROM employee_profiles ep 
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id 
        ORDER BY pi.last_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM skill_matrix");
    $totalSkills = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_skills");
    $totalAssessments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_needs_assessment WHERE status = 'Identified'");
    $pendingAssessments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT category) as categories FROM skill_matrix");
    $skillCategories = $stmt->fetch(PDO::FETCH_ASSOC)['categories'];
} catch (PDOException $e) {
    $totalSkills = 0;
    $totalAssessments = 0;
    $pendingAssessments = 0;
    $skillCategories = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills & Assessment Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for skills & assessment page */
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
            color: #666;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
            margin: 0 3px;
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

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--azure-blue-lighter);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .proficiency-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .proficiency-beginner {
            background: #fff3cd;
            color: #856404;
        }

        .proficiency-intermediate {
            background: #d1ecf1;
            color: #0c5460;
        }

        .proficiency-advanced {
            background: #d4edda;
            color: #155724;
        }

        .proficiency-expert {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-low {
            background: #d1ecf1;
            color: #0c5460;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
        }

        .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-results {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .tab-navigation {
            display: flex;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .tab-button {
            flex: 1;
            padding: 20px;
            background: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            color: #666;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .tab-button.active {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .tab-button:hover:not(.active) {
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .form-row {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }

            .content {
                padding: 20px;
            }

            .tab-button {
                padding: 15px 10px;
                font-size: 14px;
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
                <h2 class="section-title">Skills & Assessment Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-table"></i>
                            <h3><?php echo $totalSkills; ?></h3>
                            <h6>Total Skills</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-user-cog"></i>
                            <h3><?php echo $totalAssessments; ?></h3>
                            <h6>Skill Assessments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-clipboard-list"></i>
                            <h3><?php echo $pendingAssessments; ?></h3>
                            <h6>Pending Assessments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-tags"></i>
                            <h3><?php echo $skillCategories; ?></h3>
                            <h6>Skill Categories</h6>
                        </div>
                    </div>
                </div>

                    <!-- Tab Navigation -->
                    <div class="tab-navigation">
                        <button class="tab-button active" onclick="showTab('skills')">
                            <i class="fas fa-table"></i>
                            Skills Matrix
                        </button>
                        <button class="tab-button" onclick="showTab('assessments')">
                            <i class="fas fa-user-cog"></i>
                            Employee Skills
                        </button>
                        <button class="tab-button" onclick="showTab('needs')">
                            <i class="fas fa-clipboard-list"></i>
                            Training Needs
                        </button>
                    </div>

                    <!-- Skills Matrix Tab -->
                    <div id="skills-tab" class="tab-content active">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="skillSearch" placeholder="Search skills by name or category...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add_skill')">
                                ➕ Add New Skill
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="skillTable">
                                <thead>
                                    <tr>
                                        <th>Skill Name</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="skillTableBody">
                                    <?php foreach ($skills as $skill): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($skill['skill_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($skill['category']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($skill['description'], 0, 50)) . (strlen($skill['description']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editSkill(<?php echo $skill['skill_id']; ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteSkill(<?php echo $skill['skill_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($skills)): ?>
                            <div class="no-results">
                                <i class="fas fa-table"></i>
                                <h3>No skills found</h3>
                                <p>Start by adding your first skill to the matrix.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Employee Skills Tab -->
                    <div id="assessments-tab" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="employeeSkillSearch" placeholder="Search employee skills...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add_employee_skill')">
                                ➕ Add Assessment
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="employeeSkillTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Skill</th>
                                        <th>Category</th>
                                        <th>Proficiency</th>
                                        <th>Assessed Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="employeeSkillTableBody">
                                    <?php foreach ($employeeSkills as $es): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($es['first_name'] . ' ' . $es['last_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($es['skill_name']); ?></td>
                                        <td><?php echo htmlspecialchars($es['category']); ?></td>
                                        <td>
                                            <span class="proficiency-badge proficiency-<?php echo strtolower($es['proficiency_level']); ?>">
                                                <?php echo htmlspecialchars($es['proficiency_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($es['assessed_date'])); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editEmployeeSkill(<?php echo $es['employee_skill_id']; ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteEmployeeSkill(<?php echo $es['employee_skill_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($employeeSkills)): ?>
                            <div class="no-results">
                                <i class="fas fa-user-cog"></i>
                                <h3>No employee skills found</h3>
                                <p>Start by adding your first employee skill assessment.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Training Needs Tab -->
                    <div id="needs-tab" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="needsSearch" placeholder="Search assessments...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add_assessment')">
                                ➕ Add Assessment
                            </button>
                        </div>

                        <div class="table-container">
                            <table class="table" id="needsTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Assessment Date</th>
                                        <th>Skills Gap</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="needsTableBody">
                                    <?php foreach ($assessments as $assessment): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($assessment['first_name'] . ' ' . $assessment['last_name']); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($assessment['assessment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($assessment['skills_gap'], 0, 50)) . (strlen($assessment['skills_gap']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="priority-badge priority-<?php echo strtolower($assessment['priority']); ?>">
                                                <?php echo htmlspecialchars($assessment['priority']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($assessment['status']); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-small" onclick="editAssessment(<?php echo $assessment['assessment_id']; ?>)">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="deleteAssessment(<?php echo $assessment['assessment_id']; ?>)">
                                                🗑️ Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($assessments)): ?>
                            <div class="no-results">
                                <i class="fas fa-clipboard-list"></i>
                                <h3>No assessments found</h3>
                                <p>Start by adding your first training needs assessment.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Skill Modal -->
    <div id="skillModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="skillModalTitle">Add New Skill</h2>
                <span class="close" onclick="closeModal('skill')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="skillForm" method="POST">
                    <input type="hidden" id="skill_action" name="action" value="add_skill">
                    <input type="hidden" id="skill_id" name="skill_id">

                    <div class="form-group">
                        <label for="skill_name">Skill Name *</label>
                        <input type="text" id="skill_name" name="skill_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Management">Management</option>
                            <option value="Technical">Technical</option>
                            <option value="Soft Skills">Soft Skills</option>
                            <option value="Communication">Communication</option>
                            <option value="Analytics">Analytics</option>
                            <option value="Finance">Finance</option>
                            <option value="Technology">Technology</option>
                            <option value="Legal">Legal</option>
                            <option value="Environment">Environment</option>
                            <option value="Safety">Safety</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Description of the skill"></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('skill')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Skill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Employee Skill Modal -->
    <div id="employeeSkillModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="employeeSkillModalTitle">Add Employee Skill Assessment</h2>
                <span class="close" onclick="closeModal('employeeSkill')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="employeeSkillForm" method="POST">
                    <input type="hidden" id="employee_skill_action" name="action" value="add_employee_skill">
                    <input type="hidden" id="employee_skill_id" name="employee_skill_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee *</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="skill_id">Skill *</label>
                                <select id="skill_id" name="skill_id" class="form-control" required>
                                    <option value="">Select Skill</option>
                                    <?php foreach ($skills as $skill): ?>
                                    <option value="<?php echo $skill['skill_id']; ?>">
                                        <?php echo htmlspecialchars($skill['skill_name'] . ' (' . $skill['category'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="proficiency_level">Proficiency Level *</label>
                                <select id="proficiency_level" name="proficiency_level" class="form-control" required>
                                    <option value="">Select Level</option>
                                    <option value="Beginner">Beginner</option>
                                    <option value="Intermediate">Intermediate</option>
                                    <option value="Advanced">Advanced</option>
                                    <option value="Expert">Expert</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="assessed_date">Assessment Date *</label>
                                <input type="date" id="assessed_date" name="assessed_date" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Additional notes"></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('employeeSkill')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Assessment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Assessment Modal -->
    <div id="assessmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="assessmentModalTitle">Add Training Needs Assessment</h2>
                <span class="close" onclick="closeModal('assessment')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="assessmentForm" method="POST">
                    <input type="hidden" id="assessment_action" name="action" value="add_assessment">
                    <input type="hidden" id="assessment_id" name="assessment_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="assessment_employee_id">Employee *</label>
                                <select id="assessment_employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="assessment_date">Assessment Date *</label>
                                <input type="date" id="assessment_date" name="assessment_date" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="skills_gap">Skills Gap *</label>
                        <textarea id="skills_gap" name="skills_gap" class="form-control" rows="3" placeholder="Describe the skills gap identified" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="recommended_trainings">Recommended Trainings</label>
                        <textarea id="recommended_trainings" name="recommended_trainings" class="form-control" rows="3" placeholder="Recommended training programs or courses"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="priority">Priority *</label>
                                <select id="priority" name="priority" class="form-control" required>
                                    <option value="">Select Priority</option>
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="status">Status *</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="Identified">Identified</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('assessment')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Assessment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let skillsData = <?= json_encode($skills) ?>;
        let employeeSkillsData = <?= json_encode($employeeSkills) ?>;
        let assessmentsData = <?= json_encode($assessments) ?>;
        let employeesData = <?= json_encode($employees) ?>;

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }

        // Search functionality
        document.getElementById('skillSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('skillTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        document.getElementById('employeeSkillSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('employeeSkillTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        document.getElementById('needsSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('needsTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Modal functions
        function openModal(mode, id = null) {
            if (mode === 'add_skill') {
                const modal = document.getElementById('skillModal');
                const form = document.getElementById('skillForm');
                const title = document.getElementById('skillModalTitle');
                const action = document.getElementById('skill_action');

                title.textContent = 'Add New Skill';
                action.value = 'add_skill';
                form.reset();
                document.getElementById('skill_id').value = '';
                modal.style.display = 'block';
                
            } else if (mode === 'edit_skill' && id) {
                const modal = document.getElementById('skillModal');
                const title = document.getElementById('skillModalTitle');
                const action = document.getElementById('skill_action');

                title.textContent = 'Edit Skill';
                action.value = 'edit_skill';
                document.getElementById('skill_id').value = id;
                populateSkillForm(id);
                modal.style.display = 'block';
                
            } else if (mode === 'add_employee_skill') {
                const modal = document.getElementById('employeeSkillModal');
                const form = document.getElementById('employeeSkillForm');
                const title = document.getElementById('employeeSkillModalTitle');
                const action = document.getElementById('employee_skill_action');

                title.textContent = 'Add Employee Skill Assessment';
                action.value = 'add_employee_skill';
                form.reset();
                document.getElementById('employee_skill_id').value = '';
                document.getElementById('assessed_date').value = new Date().toISOString().split('T')[0];
                modal.style.display = 'block';
                
            } else if (mode === 'edit_employee_skill' && id) {
                const modal = document.getElementById('employeeSkillModal');
                const title = document.getElementById('employeeSkillModalTitle');
                const action = document.getElementById('employee_skill_action');

                title.textContent = 'Edit Employee Skill Assessment';
                action.value = 'edit_employee_skill';
                document.getElementById('employee_skill_id').value = id;
                populateEmployeeSkillForm(id);
                modal.style.display = 'block';
                
            } else if (mode === 'add_assessment') {
                const modal = document.getElementById('assessmentModal');
                const form = document.getElementById('assessmentForm');
                const title = document.getElementById('assessmentModalTitle');
                const action = document.getElementById('assessment_action');

                title.textContent = 'Add Training Needs Assessment';
                action.value = 'add_assessment';
                form.reset();
                document.getElementById('assessment_id').value = '';
                document.getElementById('assessment_date').value = new Date().toISOString().split('T')[0];
                modal.style.display = 'block';
                
            } else if (mode === 'edit_assessment' && id) {
                const modal = document.getElementById('assessmentModal');
                const title = document.getElementById('assessmentModalTitle');
                const action = document.getElementById('assessment_action');

                title.textContent = 'Edit Training Needs Assessment';
                action.value = 'edit_assessment';
                document.getElementById('assessment_id').value = id;
                populateAssessmentForm(id);
                modal.style.display = 'block';
            }

            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalType) {
            const modal = document.getElementById(modalType + 'Modal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateSkillForm(skillId) {
            const skill = skillsData.find(s => s.skill_id == skillId);
            if (skill) {
                document.getElementById('skill_name').value = skill.skill_name || '';
                document.getElementById('category').value = skill.category || '';
                document.getElementById('description').value = skill.description || '';
            }
        }

        function populateEmployeeSkillForm(employeeSkillId) {
            const employeeSkill = employeeSkillsData.find(es => es.employee_skill_id == employeeSkillId);
            if (employeeSkill) {
                document.getElementById('employee_id').value = employeeSkill.employee_id || '';
                document.getElementById('skill_id').value = employeeSkill.skill_id || '';
                document.getElementById('proficiency_level').value = employeeSkill.proficiency_level || '';
                document.getElementById('assessed_date').value = employeeSkill.assessed_date || '';
                document.getElementById('notes').value = employeeSkill.notes || '';
            }
        }

        function populateAssessmentForm(assessmentId) {
            const assessment = assessmentsData.find(a => a.assessment_id == assessmentId);
            if (assessment) {
                document.getElementById('assessment_employee_id').value = assessment.employee_id || '';
                document.getElementById('assessment_date').value = assessment.assessment_date || '';
                document.getElementById('skills_gap').value = assessment.skills_gap || '';
                document.getElementById('recommended_trainings').value = assessment.recommended_trainings || '';
                document.getElementById('priority').value = assessment.priority || '';
                document.getElementById('status').value = assessment.status || '';
            }
        }

        function editSkill(skillId) {
            openModal('edit_skill', skillId);
        }

        function editEmployeeSkill(employeeSkillId) {
            openModal('edit_employee_skill', employeeSkillId);
        }

        function editAssessment(assessmentId) {
            openModal('edit_assessment', assessmentId);
        }

        function deleteSkill(skillId) {
            if (confirm('Are you sure you want to delete this skill? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_skill">
                    <input type="hidden" name="skill_id" value="${skillId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteEmployeeSkill(employeeSkillId) {
            if (confirm('Are you sure you want to delete this employee skill assessment? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_employee_skill">
                    <input type="hidden" name="employee_skill_id" value="${employeeSkillId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteAssessment(assessmentId) {
            if (confirm('Are you sure you want to delete this training needs assessment? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_assessment">
                    <input type="hidden" name="assessment_id" value="${assessmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const skillModal = document.getElementById('skillModal');
            const employeeSkillModal = document.getElementById('employeeSkillModal');
            const assessmentModal = document.getElementById('assessmentModal');
            
            if (event.target === skillModal) {
                closeModal('skill');
            } else if (event.target === employeeSkillModal) {
                closeModal('employeeSkill');
            } else if (event.target === assessmentModal) {
                closeModal('assessment');
            }
        }

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
