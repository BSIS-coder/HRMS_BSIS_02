<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Only HR and Admin can access
if (!in_array($_SESSION['role'], ['hr', 'admin'])) {
    die('Access denied. Only HR and Admin can approve jobs.');
}

require_once 'config.php';

$success_message = '';

// Get all departments with their current vacancy counts and limits
$departments_query = "SELECT d.*, 
                      COALESCE(SUM(CASE WHEN jo.status = 'Open' THEN jo.vacancy_count ELSE 0 END), 0) as current_vacancies
                      FROM departments d
                      LEFT JOIN job_openings jo ON d.department_id = jo.department_id
                      GROUP BY d.department_id
                      ORDER BY d.department_name";
$departments_result = $conn->query($departments_query);
$departments = $departments_result->fetchAll(PDO::FETCH_ASSOC);

// Get all pending AI-generated jobs
$pending_jobs_query = "SELECT jo.*, 
                       COALESCE(d.department_name, 'Unknown Department') as department_name, 
                       COALESCE(jr.title, 'Unknown Role') as role_title,
                       COUNT(ja.application_id) as total_applications
                       FROM job_openings jo 
                       LEFT JOIN departments d ON jo.department_id = d.department_id 
                       LEFT JOIN job_roles jr ON jo.job_role_id = jr.job_role_id 
                       LEFT JOIN job_applications ja ON jo.job_opening_id = ja.job_opening_id
                       WHERE jo.ai_generated = TRUE AND jo.approval_status = 'Pending'
                       GROUP BY jo.job_opening_id
                       ORDER BY jo.created_at DESC";
$pending_jobs = $conn->query($pending_jobs_query);
$jobs_array = $pending_jobs->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];
$stmt = $conn->query("SELECT COUNT(*) as count FROM job_openings WHERE ai_generated = TRUE AND approval_status = 'Pending'");
$stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$stmt = $conn->query("SELECT COUNT(*) as count FROM job_openings WHERE ai_generated = TRUE AND approval_status = 'Approved'");
$stats['approved'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
$stmt = $conn->query("SELECT COUNT(*) as count FROM job_openings WHERE ai_generated = TRUE AND approval_status = 'Rejected'");
$stats['rejected'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Approval - HR Management System</title>
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
                <h2><i class="fas fa-check-circle mr-2"></i>AI Job Approval</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['pending']; ?></h3>
                                <p class="stats-label">Pending Approval</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['approved']; ?></h3>
                                <p class="stats-label">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-danger">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['rejected']; ?></h3>
                                <p class="stats-label">Rejected</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-robot mr-2"></i>AI-Generated Jobs Pending Approval</h5>
                        <div>
                            <button class="btn btn-warning btn-sm mr-2" data-toggle="modal" data-target="#vacancyLimitModal">
                                <i class="fas fa-cog mr-1"></i>Manage Vacancy Limits
                            </button>
                            <a href="job_openings.php" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left mr-1"></i>Back to Job Openings
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($jobs_array)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Job Title</th>
                                            <th>Department</th>
                                            <th>Role</th>
                                            <th>Location</th>
                                            <th>Vacancies</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($jobs_array as $job): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge badge-info mr-1">🤖 AI</span>
                                                    <strong><?php echo htmlspecialchars($job['title']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($job['department_name']); ?></td>
                                                <td><?php echo htmlspecialchars($job['role_title']); ?></td>
                                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                                                <td><span class="badge badge-secondary"><?php echo $job['vacancy_count']; ?></span></td>
                                                <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" data-job='<?php echo htmlspecialchars(json_encode($job), ENT_QUOTES, "UTF-8"); ?>' onclick="viewJobDetails(this)">
                                                        <i class="fas fa-eye mr-1"></i>View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>No pending jobs to approve.</strong><br>
                                All AI-generated jobs have been reviewed.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Job Details Modal -->
    <div class="modal fade" id="viewJobModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-briefcase mr-2"></i><span id="modalJobTitle"></span></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="jobDetailsContent">
                    <!-- Job details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="approveFromModal">
                        <i class="fas fa-check-circle mr-1"></i>Approve
                    </button>
                    <button type="button" class="btn btn-danger" id="rejectFromModal">
                        <i class="fas fa-times-circle mr-1"></i>Reject
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle mr-2"></i>Reject Job</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-3">Reject this job opening?</h6>
                    <div class="alert alert-warning">
                        <strong id="jobTitleToReject"></strong>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea id="rejectionReason" class="form-control" rows="3" placeholder="Explain why this job is being rejected..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmReject">
                        <i class="fas fa-times-circle mr-1"></i>Reject Job
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Confirmation Modal -->
    <div class="modal fade" id="approveConfirmModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle mr-2"></i>Approve Job</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-3">Approve and publish this job opening?</h6>
                    <div class="alert alert-success">
                        <strong id="jobTitleToApprove"></strong>
                    </div>
                    <p class="text-muted">
                        <i class="fas fa-info-circle mr-1"></i>
                        This job will be immediately published and visible to candidates.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmApprove">
                        <i class="fas fa-check-circle mr-1"></i>Approve & Publish
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Vacancy Limit Management Modal -->
    <div class="modal fade" id="vacancyLimitModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-cog mr-2"></i>Manage Department Vacancy Limits</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        Set maximum vacancy limits per department to prevent over-hiring. Leave blank for unlimited vacancies.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Current Open Vacancies</th>
                                    <th>Vacancy Limit</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($departments as $dept): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo $dept['current_vacancies']; ?></span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   class="form-control form-control-sm vacancy-limit-input" 
                                                   data-dept-id="<?php echo $dept['department_id']; ?>"
                                                   value="<?php echo $dept['vacancy_limit'] ?? ''; ?>"
                                                   placeholder="Unlimited"
                                                   min="0">
                                        </td>
                                        <td>
                                            <?php 
                                            if ($dept['vacancy_limit'] === null) {
                                                echo '<span class="badge badge-secondary">Unlimited</span>';
                                            } elseif ($dept['current_vacancies'] >= $dept['vacancy_limit']) {
                                                echo '<span class="badge badge-danger">At Limit</span>';
                                            } else {
                                                $remaining = $dept['vacancy_limit'] - $dept['current_vacancies'];
                                                echo '<span class="badge badge-success">' . $remaining . ' Available</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="saveVacancyLimits">
                        <i class="fas fa-save mr-1"></i>Save Limits
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    let currentJobId = null;
    let currentJobTitle = '';
    
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
    
    function viewJobDetails(button) {
        const job = JSON.parse(button.getAttribute('data-job'));
        currentJobId = job.job_opening_id;
        currentJobTitle = job.title;
        
        $('#modalJobTitle').text(job.title);
        
        let html = `
            <div class="row mb-3">
                <div class="col-md-12">
                    <span class="badge badge-info">🤖 AI Generated</span>
                    <span class="badge badge-warning">⏳ Pending Approval</span>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong><i class="fas fa-building mr-1"></i>Department:</strong> ${job.department_name}
                </div>
                <div class="col-md-6">
                    <strong><i class="fas fa-user-tie mr-1"></i>Role:</strong> ${job.role_title}
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <strong><i class="fas fa-briefcase mr-1"></i>Employment Type:</strong> ${job.employment_type}
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-map-marker-alt mr-1"></i>Location:</strong> ${job.location}
                </div>
                <div class="col-md-4">
                    <strong><i class="fas fa-users mr-1"></i>Vacancies:</strong> ${job.vacancy_count}
                </div>
            </div>
            
            ${job.experience_level ? `
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong><i class="fas fa-chart-line mr-1"></i>Experience Level:</strong> ${job.experience_level}
                </div>
                <div class="col-md-6">
                    <strong><i class="fas fa-graduation-cap mr-1"></i>Education:</strong> ${job.education_requirements || 'Not specified'}
                </div>
            </div>
            ` : ''}
            
            <hr>
            
            <div class="mb-3">
                <h6 class="text-primary"><i class="fas fa-align-left mr-1"></i>Job Description</h6>
                <p class="text-justify">${job.description.replace(/\n/g, '<br>')}</p>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <h6 class="text-primary"><i class="fas fa-check-circle mr-1"></i>Requirements</h6>
                    <div class="bg-light p-3 rounded">
                        ${job.requirements.replace(/\n/g, '<br>')}
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <h6 class="text-primary"><i class="fas fa-tasks mr-1"></i>Responsibilities</h6>
                    <div class="bg-light p-3 rounded">
                        ${job.responsibilities.replace(/\n/g, '<br>')}
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-12">
                    <small class="text-muted">
                        <i class="fas fa-calendar mr-1"></i>Created: ${new Date(job.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}
                    </small>
                </div>
            </div>
        `;
        
        $('#jobDetailsContent').html(html);
        $('#viewJobModal').modal('show');
    }
    
    $('#approveFromModal').on('click', function() {
        if (!currentJobId) return;
        
        // Close the view modal and show approve confirmation
        $('#viewJobModal').modal('hide');
        $('#jobTitleToApprove').text(currentJobTitle);
        $('#approveConfirmModal').modal('show');
    });
    
    $('#confirmApprove').on('click', function() {
        if (!currentJobId) return;
        
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Approving...');
        
        $.ajax({
            url: 'approve_job.php',
            method: 'POST',
            data: {
                action: 'approve',
                job_opening_id: currentJobId
            },
            dataType: 'json',
            success: function(response){
                if(response.success){
                    $('#approveConfirmModal').modal('hide');
                    showToast('✅ Job approved and published successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(response.error, 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i>Approve & Publish');
                }
            },
            error: function(){
                showToast('Failed to approve job. Please try again.', 'error');
                $btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i>Approve & Publish');
            }
        });
    });
    
    $('#rejectFromModal').on('click', function() {
        if (!currentJobId) return;
        
        // Close the view modal and show reject modal
        $('#viewJobModal').modal('hide');
        $('#jobTitleToReject').text(currentJobTitle);
        $('#rejectionReason').val('');
        $('#rejectModal').modal('show');
    });
    
    $('#confirmReject').on('click', function(){
        if(!currentJobId) return;
        
        var reason = $('#rejectionReason').val().trim();
        if(!reason){
            showToast('Please provide a reason for rejection.', 'warning');
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Rejecting...');
        
        $.ajax({
            url: 'approve_job.php',
            method: 'POST',
            data: {
                action: 'reject',
                job_opening_id: currentJobId,
                rejection_reason: reason
            },
            dataType: 'json',
            success: function(response){
                if(response.success){
                    showToast('❌ Job rejected successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(response.error, 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-times-circle mr-1"></i>Reject Job');
                }
            },
            error: function(){
                showToast('Failed to reject job. Please try again.', 'error');
                $btn.prop('disabled', false).html('<i class="fas fa-times-circle mr-1"></i>Reject Job');
            }
        });
    });
    
    // Save vacancy limits
    $('#saveVacancyLimits').on('click', function() {
        var limits = [];
        $('.vacancy-limit-input').each(function() {
            var deptId = $(this).data('dept-id');
            var limit = $(this).val();
            limits.push({
                department_id: deptId,
                vacancy_limit: limit === '' ? null : parseInt(limit)
            });
        });
        
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
        
        $.ajax({
            url: 'update_vacancy_limits.php',
            method: 'POST',
            data: { limits: JSON.stringify(limits) },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    showToast('✅ Vacancy limits updated successfully!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('Error: ' + response.error, 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Limits');
                }
            },
            error: function() {
                showToast('Failed to save vacancy limits. Please try again.', 'error');
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Save Limits');
            }
        });
    });
    </script>
</body>
</html>
