<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';

$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_opening':
                $screening_level = $_POST['screening_level'] ?? 'Moderate';
                $stmt = $conn->prepare("INSERT INTO job_openings (job_role_id, department_id, title, description, requirements, responsibilities, location, employment_type, salary_range_min, salary_range_max, vacancy_count, posting_date, status, screening_level, ai_generated, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, FALSE, ?)");
                $stmt->execute([$_POST['job_role_id'], $_POST['department_id'], $_POST['title'], $_POST['description'], $_POST['requirements'], $_POST['responsibilities'], $_POST['location'], $_POST['employment_type'], $_POST['salary_min'], $_POST['salary_max'], $_POST['vacancy_count'], $_POST['status'], $screening_level, $_SESSION['user_id']]);
                $success_message = "✨ Job opening '" . htmlspecialchars($_POST['title']) . "' created successfully with " . $screening_level . " AI screening level!";
                break;
            case 'update_status':
                if ($_POST['new_status'] == 'Closed') {
                    // Check for pending applications
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_applications WHERE job_opening_id = ? AND status IN ('Applied', 'Approved', 'Interview')");
                    $check_stmt->execute([$_POST['job_opening_id']]);
                    $pending_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($pending_count > 0) {
                        $success_message = "⚠️ Cannot close job! There are " . $pending_count . " pending applications that need to be processed first.";
                        break;
                    }
                    
                    // Set closing date when job is closed
                    $stmt = $conn->prepare("UPDATE job_openings SET status = ?, closing_date = CURDATE() WHERE job_opening_id = ?");
                    $stmt->execute([$_POST['new_status'], $_POST['job_opening_id']]);
                } else {
                    // Clear closing date when reopening
                    $stmt = $conn->prepare("UPDATE job_openings SET status = ?, closing_date = NULL WHERE job_opening_id = ?");
                    $stmt->execute([$_POST['new_status'], $_POST['job_opening_id']]);
                }
                
                $emoji = $_POST['new_status'] == 'Open' ? '🚀' : '🚫';
                $success_message = $emoji . " Job status updated to " . $_POST['new_status'] . " successfully!";
                break;
        }
    }
}

// Check and auto-close filled positions
$conn->exec("UPDATE job_openings jo SET status = 'Closed', closing_date = CURDATE() WHERE jo.status = 'Open' AND (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_opening_id = jo.job_opening_id AND ja.status = 'Hired') >= jo.vacancy_count");

$stats = [];
$stmt = $conn->query("SELECT COUNT(*) as count FROM job_openings WHERE status = 'Draft'");
$stats['draft'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$stmt = $conn->query("SELECT COUNT(*) as count FROM job_openings WHERE status = 'Open'");
$stats['open'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$stmt = $conn->query("SELECT COUNT(*) as count FROM job_applications");
$stats['applications'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$stmt = $conn->query("SELECT COUNT(*) as count FROM job_openings WHERE ai_generated = TRUE AND approval_status = 'Pending'");
$stats['pending_approval'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$job_openings_query = "SELECT jo.*, 
                       COALESCE(d.department_name, 'Unknown Department') as department_name, 
                       COALESCE(jr.title, 'Unknown Role') as role_title,
                       COALESCE(u_creator.username, 'System') as creator_name,
                       COALESCE(u_approver.username, NULL) as approver_name,
                       COUNT(ja.application_id) as total_applications,
                       SUM(CASE WHEN ja.status = 'Applied' THEN 1 ELSE 0 END) as pending_applications,
                       SUM(CASE WHEN ja.status = 'Interview' THEN 1 ELSE 0 END) as interview_stage,
                       SUM(CASE WHEN ja.status = 'Hired' THEN 1 ELSE 0 END) as hired_count
                       FROM job_openings jo 
                       LEFT JOIN departments d ON jo.department_id = d.department_id 
                       LEFT JOIN job_roles jr ON jo.job_role_id = jr.job_role_id 
                       LEFT JOIN users u_creator ON jo.created_by = u_creator.user_id
                       LEFT JOIN users u_approver ON jo.approved_by = u_approver.user_id
                       LEFT JOIN job_applications ja ON jo.job_opening_id = ja.job_opening_id
                       WHERE jo.status != 'Archived'
                       GROUP BY jo.job_opening_id
                       ORDER BY jo.posting_date DESC";
$job_openings_result = $conn->query($job_openings_query);

try {
    $departments = $conn->query("SELECT * FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);
    $job_roles = $conn->query("SELECT * FROM job_roles ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
    $job_roles = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Openings - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
        }
        .custom-toast {
            min-width: 300px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 10px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .toast-success { border-left: 4px solid #28a745; }
        .toast-error { border-left: 4px solid #dc3545; }
        .toast-warning { border-left: 4px solid #ffc107; }
        .toast-info { border-left: 4px solid #17a2b8; }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2>💼 Job Openings Management</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert <?php echo strpos($success_message, 'Cannot') !== false ? 'alert-warning' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-warning">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['draft']; ?></h3>
                                <p class="stats-label">Draft Openings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['open']; ?></h3>
                                <p class="stats-label">Active Openings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-info">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['applications']; ?></h3>
                                <p class="stats-label">Total Applications</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-danger">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['pending_approval']; ?></h3>
                                <p class="stats-label">Pending Approval</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">💼 Job Openings Management</h5>
                        <div class="d-flex">
                            <input type="text" id="searchJobs" class="form-control mr-2" placeholder="🔍 Search jobs..." style="width: 200px;">
                            <button class="btn btn-secondary btn-sm mr-2" id="toggleClosed">👁️ Show Closed</button>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#aiGenerateModal">🤖 AI Generate Job</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Department</th>
                                        <th>Role</th>
                                        <th>Vacancies</th>
                                        <th>Posted</th>
                                        <th>Closing</th>
                                        <th>Status</th>
                                        <th>Applications</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($job_openings_result && $job_openings_result->rowCount() > 0): ?>
                                        <?php while($row = $job_openings_result->fetch(PDO::FETCH_ASSOC)): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($row['title']); ?>
                                                    <?php if ($row['ai_generated']): ?>
                                                        <span class="badge badge-info ml-1" title="AI Generated">🤖 AI</span>
                                                    <?php endif; ?>
                                                    <?php if ($row['approval_status'] == 'Pending'): ?>
                                                        <span class="badge badge-warning ml-1">⏳ Pending Approval</span>
                                                    <?php elseif ($row['approval_status'] == 'Approved'): ?>
                                                        <span class="badge badge-success ml-1">✅ Approved</span>
                                                    <?php elseif ($row['approval_status'] == 'Rejected'): ?>
                                                        <span class="badge badge-danger ml-1">❌ Rejected</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user"></i> Created by: <?php echo htmlspecialchars($row['creator_name']); ?>
                                                        <?php if ($row['approver_name']): ?>
                                                            | <i class="fas fa-check-circle"></i> By: <?php echo htmlspecialchars($row['approver_name']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['role_title']); ?></td>
                                                <td><?php echo $row['vacancy_count']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['posting_date'])); ?></td>
                                                <td><?php echo $row['closing_date'] ? date('M d, Y', strtotime($row['closing_date'])) : 'No deadline'; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $row['status'] == 'Open' ? 'success' : ($row['status'] == 'Draft' ? 'warning' : 'danger'); ?>">
                                                        <?php 
                                                        $emoji = $row['status'] == 'Open' ? '🚀' : ($row['status'] == 'Draft' ? '📝' : '🚫');
                                                        echo $emoji . ' ' . $row['status']; 
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>
                                                        📈 Total: <strong><?php echo $row['total_applications']; ?></strong><br>
                                                        ⏳ Pending: <?php echo $row['pending_applications']; ?><br>
                                                        💬 Interview: <?php echo $row['interview_stage']; ?><br>
                                                        ✅ Hired: <?php echo $row['hired_count']; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column" style="min-width: 120px;">
                                                        <?php if ($row['approval_status'] == 'Pending' && in_array($_SESSION['role'], ['hr', 'admin'])): ?>
                                                            <a href="edit_job.php?id=<?php echo $row['job_opening_id']; ?>" class="btn btn-warning btn-sm mb-1 text-left">
                                                                <i class="fas fa-edit mr-1"></i>Edit
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="job_applications.php?job_id=<?php echo $row['job_opening_id']; ?>" class="btn btn-info btn-sm mb-1 text-left">👥 Applications</a>
                                                        <?php if ($row['status'] == 'Draft' && ($row['approval_status'] == 'Approved' || !$row['ai_generated'])): ?>
                                                            <button type="button" class="btn btn-success btn-sm w-100 text-left" onclick="showPublishModal('<?php echo $row['job_opening_id']; ?>', '<?php echo htmlspecialchars($row['title']); ?>')">🚀 Publish</button>
                                                        <?php endif; ?>
                                                        <?php if ($row['status'] == 'Open'): ?>
                                                            <button type="button" class="btn btn-danger btn-sm w-100 text-left" onclick="showCloseModal('<?php echo $row['job_opening_id']; ?>', '<?php echo htmlspecialchars($row['title']); ?>')">🚫 Close</button>
                                                        <?php endif; ?>
                                                        <?php if ($row['status'] == 'Closed'): ?>
                                                            <button type="button" class="btn btn-warning btn-sm w-100 text-left" onclick="showReopenModal('<?php echo $row['job_opening_id']; ?>', '<?php echo htmlspecialchars($row['title']); ?>')">🔄 Reopen</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No job openings found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Generate Job Modal -->
    <div class="modal fade" id="aiGenerateModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h4 class="modal-title mb-0"><i class="fas fa-robot mr-2"></i>AI Generate Job Opening</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="aiJobForm">
                    <div class="modal-body p-4">
                        <div class="alert alert-info">
                            <i class="fas fa-magic mr-2"></i><strong>AI-Powered Job Creation</strong><br>
                            Provide basic information and our AI will generate a complete, professional job posting for you!
                        </div>
                        
                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="fas fa-building mr-1"></i>Department <span class="text-danger">*</span></label>
                                    <select name="department_id" id="ai_department_select" class="form-control form-control-lg" required>
                                        <option value="">🏢 Choose Department</option>
                                        <?php foreach($departments as $dept): 
                                            // Get current vacancy info
                                            $stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN status = 'Open' THEN vacancy_count ELSE 0 END), 0) as current_vacancies FROM job_openings WHERE department_id = ?");
                                            $stmt->execute([$dept['department_id']]);
                                            $vacInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                                            $current = $vacInfo['current_vacancies'];
                                            $limit = $dept['vacancy_limit'];
                                            $available = $limit ? ($limit - $current) : 'Unlimited';
                                        ?>
                                            <option value="<?php echo $dept['department_id']; ?>" 
                                                    data-name="<?php echo htmlspecialchars($dept['department_name']); ?>"
                                                    data-limit="<?php echo $limit ?? ''; ?>"
                                                    data-current="<?php echo $current; ?>"
                                                    data-available="<?php echo is_numeric($available) ? $available : 999; ?>">
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                                <?php if ($limit): ?>
                                                    (<?php echo $current; ?>/<?php echo $limit; ?> used)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small id="vacancyLimitInfo" class="form-text text-muted"></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="fas fa-user-tie mr-1"></i>Job Role <span class="text-danger">*</span></label>
                                    <select name="job_role_id" id="ai_job_role_select" class="form-control form-control-lg" required>
                                        <option value="">👔 Select Job Role</option>
                                        <?php foreach($job_roles as $role): ?>
                                            <option value="<?php echo $role['job_role_id']; ?>" data-department="<?php echo $role['department']; ?>"><?php echo htmlspecialchars($role['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="fas fa-users mr-1"></i>Number of Vacancies <span class="text-danger">*</span></label>
                                    <input type="number" name="vacancy_count" id="ai_vacancy_count" class="form-control form-control-lg" 
                                           min="1" max="999" value="1" required>
                                    <small class="form-text text-muted">How many positions do you want to fill?</small>
                                    <div id="vacancyWarning" class="alert alert-warning mt-2" style="display:none;">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        <span id="vacancyWarningText"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="fas fa-robot mr-1"></i>AI Screening Level <span class="text-danger">*</span></label>
                                    <select name="screening_level" class="form-control form-control-lg" required>
                                        <option value="Easy">🟢 Easy - Inclusive (Focus on potential)</option>
                                        <option value="Moderate" selected>🟡 Moderate - Balanced (Recommended)</option>
                                        <option value="Strict">🔴 Strict - Selective (High standards)</option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <strong>Easy:</strong> 50%+ match, focus on trainability<br>
                                        <strong>Moderate:</strong> 65%+ match, balanced approach<br>
                                        <strong>Strict:</strong> 80%+ match, proven qualifications
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="ai_config_page.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-cog mr-1"></i>Edit AI Configuration
                            </a>
                        </div>
                        
                        <div id="aiGenerationStatus" class="alert" style="display:none;"></div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="generateBtn">
                            <i class="fas fa-magic mr-2"></i>Generate with AI
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Publish Confirmation Modal -->
    <div class="modal fade" id="publishModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-rocket mr-2"></i>Publish Job Opening</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-rocket text-success" style="font-size: 48px;"></i>
                    </div>
                    <h6 class="text-center mb-3">Are you sure you want to publish this job opening?</h6>
                    <div class="alert alert-info">
                        <strong id="jobTitleToPublish"></strong>
                    </div>
                    <p class="text-muted">This will make the job visible to applicants and they can start applying immediately.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;" id="publishForm">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="job_opening_id" id="jobIdToPublish">
                        <input type="hidden" name="new_status" value="Open">
                        <button type="submit" class="btn btn-success"><i class="fas fa-rocket mr-1"></i>Publish Job</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Close Confirmation Modal -->
    <div class="modal fade" id="closeModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle mr-2"></i>Close Job Opening</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-times-circle text-danger" style="font-size: 48px;"></i>
                    </div>
                    <h6 class="text-center mb-3">Are you sure you want to close this job opening?</h6>
                    <div class="alert alert-warning">
                        <strong id="jobTitleToClose"></strong>
                    </div>
                    <p class="text-muted">This will stop accepting new applications and set the closing date to today.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;" id="closeForm">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="job_opening_id" id="jobIdToClose">
                        <input type="hidden" name="new_status" value="Closed">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-times-circle mr-1"></i>Close Job</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reopen Confirmation Modal -->
    <div class="modal fade" id="reopenModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-redo mr-2"></i>Reopen Job Opening</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-redo text-warning" style="font-size: 48px;"></i>
                    </div>
                    <h6 class="text-center mb-3">Are you sure you want to reopen this job opening?</h6>
                    <div class="alert alert-info">
                        <strong id="jobTitleToReopen"></strong>
                    </div>
                    <p class="text-muted">This will allow new applications and clear the closing date.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;" id="reopenForm">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="job_opening_id" id="jobIdToReopen">
                        <input type="hidden" name="new_status" value="Open">
                        <button type="submit" class="btn btn-warning"><i class="fas fa-redo mr-1"></i>Reopen Job</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    // Toast Notification Function
    function showToast(message, type = 'success') {
        const toastId = 'toast-' + Date.now();
        const iconMap = {
            'success': 'fa-check-circle',
            'error': 'fa-times-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        const icon = iconMap[type] || iconMap['info'];
        
        const toast = $(`
            <div class="custom-toast toast-${type}" id="${toastId}">
                <div class="toast-header">
                    <i class="fas ${icon} mr-2"></i>
                    <strong class="mr-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                    <button type="button" class="ml-2 mb-1 close" onclick="$('#${toastId}').fadeOut(300, function(){ $(this).remove(); })">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `);
        
        $('#toastContainer').append(toast);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            $('#' + toastId).fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    </script>
    <script>
    function showPublishModal(jobId, jobTitle) {
        $('#jobIdToPublish').val(jobId);
        $('#jobTitleToPublish').text(jobTitle);
        $('#publishModal').modal('show');
    }
    
    function showCloseModal(jobId, jobTitle) {
        $('#jobIdToClose').val(jobId);
        $('#jobTitleToClose').text(jobTitle);
        $('#closeModal').modal('show');
    }
    
    function showReopenModal(jobId, jobTitle) {
        $('#jobIdToReopen').val(jobId);
        $('#jobTitleToReopen').text(jobTitle);
        $('#reopenModal').modal('show');
    }
    
    $(document).ready(function(){
        // AI Job Generation Form
        $('#aiJobForm').on('submit', function(e){
            e.preventDefault();
            
            var $btn = $('#generateBtn');
            var $status = $('#aiGenerationStatus');
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Generating...');
            $status.hide();
            
            $.ajax({
                url: 'generate_job_ai.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response){
                    if(response.success){
                        $status.removeClass('alert-danger').addClass('alert-success')
                               .html('<i class="fas fa-check-circle mr-2"></i>' + response.message)
                               .show();
                        
                        showToast('🤖 ' + response.message, 'success');
                        
                        setTimeout(function(){
                            location.reload();
                        }, 2000);
                    } else {
                        $status.removeClass('alert-success').addClass('alert-danger')
                               .html('<i class="fas fa-exclamation-circle mr-2"></i>' + response.error)
                               .show();
                        showToast(response.error, 'error');
                        $btn.prop('disabled', false).html('<i class="fas fa-magic mr-2"></i>Generate with AI');
                    }
                },
                error: function(xhr, status, error){
                    let errorMsg = 'Failed to generate job. ';
                    
                    // Try to parse response as JSON for better error message
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.error) {
                            errorMsg += response.error;
                        }
                    } catch(e) {
                        // If not JSON, use the raw response
                        if (xhr.responseText) {
                            errorMsg += 'Server response: ' + xhr.responseText.substring(0, 200);
                        } else {
                            errorMsg += 'Please try again. (HTTP ' + xhr.status + ')';
                        }
                    }
                    
                    $status.removeClass('alert-success').addClass('alert-danger')
                           .html('<i class="fas fa-exclamation-circle mr-2"></i>' + errorMsg)
                           .show();
                    showToast(errorMsg, 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-magic mr-2"></i>Generate with AI');
                }
            });
        });
        
        // Department filter for AI modal
        $('#ai_department_select').on('change', function(){
            var selectedDept = $(this).find('option:selected').data('name');
            var roleSelect = $('#ai_job_role_select');
            
            roleSelect.find('option').each(function(){
                if($(this).val() === '') {
                    $(this).show();
                } else {
                    var roleDept = $(this).data('department');
                    if(!selectedDept || roleDept === selectedDept) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                }
            });
            
            roleSelect.val('');
            updateVacancyInfo();
        });
        
        // Update vacancy info when department or vacancy count changes
        $('#ai_vacancy_count').on('input', function() {
            updateVacancyInfo();
        });
        
        function updateVacancyInfo() {
            var $deptSelect = $('#ai_department_select');
            var $selected = $deptSelect.find('option:selected');
            var limit = parseInt($selected.data('limit')) || null;
            var current = parseInt($selected.data('current')) || 0;
            var requested = parseInt($('#ai_vacancy_count').val()) || 0;
            var $info = $('#vacancyLimitInfo');
            var $warning = $('#vacancyWarning');
            var $warningText = $('#vacancyWarningText');
            var $generateBtn = $('#generateBtn');
            
            if (!$selected.val()) {
                $info.text('');
                $warning.hide();
                return;
            }
            
            if (limit === null) {
                $info.html('<i class="fas fa-infinity mr-1"></i>No vacancy limit set for this department');
                $warning.hide();
                $generateBtn.prop('disabled', false);
            } else {
                var available = limit - current;
                var newTotal = current + requested;
                
                $info.html('<i class="fas fa-info-circle mr-1"></i>Current: ' + current + ' | Limit: ' + limit + ' | Available: ' + available);
                
                if (newTotal > limit) {
                    $warningText.text('Warning: Requesting ' + requested + ' vacancies would exceed the limit by ' + (newTotal - limit) + '. This job will need special approval.');
                    $warning.show();
                    // Don't disable, just warn - let approval process handle it
                } else if (available <= 2 && available > 0) {
                    $warningText.text('Notice: Only ' + available + ' vacancy slots remaining in this department.');
                    $warning.removeClass('alert-danger').addClass('alert-warning').show();
                } else {
                    $warning.hide();
                }
            }
        }
        
        // Hide closed jobs by default
        $('tbody tr').each(function(){
            if($(this).find('.badge-danger').length > 0) {
                $(this).hide();
            }
        });
        
        $('#toggleClosed').on('click', function(){
            var closedRows = $('tbody tr').filter(function(){
                return $(this).find('.badge-danger').length > 0;
            });
            
            if(closedRows.is(':visible')) {
                closedRows.hide();
                $(this).text('👁️ Show Closed');
            } else {
                closedRows.show();
                $(this).text('🙈 Hide Closed');
            }
        });
        
        $('#searchJobs').on('keyup', function(){
            var value = $(this).val().toLowerCase();
            $('tbody tr').filter(function(){
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });
        
        $('#department_select').on('change', function(){
            var selectedDept = $(this).find('option:selected').text();
            var roleSelect = $('#job_role_select');
            
            roleSelect.find('option').each(function(){
                if($(this).val() === '') {
                    $(this).show();
                } else {
                    var roleDept = $(this).data('department');
                    if(selectedDept === '' || roleDept === selectedDept) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                }
            });
            
            roleSelect.val('');
        });
        
        $('#jobForm').on('submit', function(e){
            var salaryMin = parseFloat($('input[name="salary_min"]').val()) || 0;
            var salaryMax = parseFloat($('input[name="salary_max"]').val()) || 0;
            
            if(salaryMin > 0 && salaryMax > 0 && salaryMin >= salaryMax) {
                e.preventDefault();
                showToast('Maximum salary must be greater than minimum salary.', 'warning');
                return false;
            }
        });
    });
    </script>
</body>
</html>