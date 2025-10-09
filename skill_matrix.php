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
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Skills & Assessment Management</h2>
                
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

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="skillsTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="skills-tab" data-toggle="tab" href="#skills" role="tab">
                            <i class="fas fa-table"></i> Skills Matrix
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="assessments-tab" data-toggle="tab" href="#assessments" role="tab">
                            <i class="fas fa-user-cog"></i> Employee Skills
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="needs-tab" data-toggle="tab" href="#needs" role="tab">
                            <i class="fas fa-clipboard-list"></i> Training Needs
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="skillsTabsContent">
                    <!-- Skills Matrix Tab -->
                    <div class="tab-pane fade show active" id="skills" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="skillSearch" placeholder="Search skills...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addSkillModal">
                                <i class="fas fa-plus"></i> Add Skill
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-table"></i> Skills Matrix</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Skill Name</th>
                                                <th>Category</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($skills as $skill): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($skill['skill_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($skill['category']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($skill['description'], 0, 50)) . (strlen($skill['description']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editSkill(<?php echo $skill['skill_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSkill(<?php echo $skill['skill_id']; ?>)">
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

                    <!-- Employee Skills Tab -->
                    <div class="tab-pane fade" id="assessments" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="employeeSkillSearch" placeholder="Search employee skills...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addEmployeeSkillModal">
                                <i class="fas fa-plus"></i> Add Assessment
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user-cog"></i> Employee Skills Assessment</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
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
                                        <tbody>
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
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editEmployeeSkill(<?php echo $es['employee_skill_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
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

                    <!-- Training Needs Tab -->
                    <div class="tab-pane fade" id="needs" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="input-group" style="max-width: 400px;">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="needsSearch" placeholder="Search assessments...">
                            </div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addAssessmentModal">
                                <i class="fas fa-plus"></i> Add Assessment
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Training Needs Assessment</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
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
                                        <tbody>
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
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssessment(<?php echo $assessment['assessment_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
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
        </div>
    </div>

    <!-- Add Skill Modal -->
    <div class="modal fade" id="addSkillModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Skill</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_skill">
                        <div class="form-group">
                            <label>Skill Name *</label>
                            <input type="text" class="form-control" name="skill_name" required>
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select class="form-control" name="category" required>
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
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Description of the skill"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Skill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Employee Skill Modal -->
    <div class="modal fade" id="addEmployeeSkillModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Employee Skill Assessment</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_employee_skill">
                        <div class="form-group">
                            <label>Employee *</label>
                            <select class="form-control" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['employee_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Skill *</label>
                            <select class="form-control" name="skill_id" required>
                                <option value="">Select Skill</option>
                                <?php foreach ($skills as $skill): ?>
                                <option value="<?php echo $skill['skill_id']; ?>">
                                    <?php echo htmlspecialchars($skill['skill_name'] . ' (' . $skill['category'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Proficiency Level *</label>
                            <select class="form-control" name="proficiency_level" required>
                                <option value="">Select Level</option>
                                <option value="Beginner">Beginner</option>
                                <option value="Intermediate">Intermediate</option>
                                <option value="Advanced">Advanced</option>
                                <option value="Expert">Expert</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assessment Date *</label>
                            <input type="date" class="form-control" name="assessed_date" required>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes"></textarea>
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

    <!-- Add Training Needs Assessment Modal -->
    <div class="modal fade" id="addAssessmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Training Needs Assessment</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
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
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
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
                            <label>Skills Gap *</label>
                            <textarea class="form-control" name="skills_gap" rows="3" placeholder="Describe the skills gap identified" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Recommended Trainings</label>
                            <textarea class="form-control" name="recommended_trainings" rows="3" placeholder="Recommended training programs or courses"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Priority *</label>
                                    <select class="form-control" name="priority" required>
                                        <option value="">Select Priority</option>
                                        <option value="Low">Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status *</label>
                                    <select class="form-control" name="status" required>
                                        <option value="Identified">Identified</option>
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

        // Search functionality for skills
        $('#skillSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#skills table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Search functionality for employee skills
        $('#employeeSkillSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#assessments table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Search functionality for needs assessment
        $('#needsSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#needs table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Edit functions
        function editSkill(skillId) {
            alert('Edit skill with ID: ' + skillId);
        }

        function editEmployeeSkill(employeeSkillId) {
            alert('Edit employee skill with ID: ' + employeeSkillId);
        }

        function editAssessment(assessmentId) {
            alert('Edit assessment with ID: ' + assessmentId);
        }

        // Delete skill function
        function deleteSkill(skillId) {
            if (confirm('Are you sure you want to delete this skill?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_skill">
                    <input type="hidden" name="skill_id" value="${skillId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

</html>
