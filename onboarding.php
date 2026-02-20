<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

$success_message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'hire_candidate':
            try {
                $application_id = $_POST['application_id'];
                
                // Update job application status to Offer (ready for offer letter)
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Offer' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                // Update onboarding status to Completed
                $stmt = $conn->prepare("UPDATE candidate_onboarding SET status = 'Completed' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                $success_message = "🎉 Candidate moved to Job Offer stage! Generate offer letter in Job Offers page.";
                $messageType = "success";
            } catch (Exception $e) {
                $success_message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
            break;
            
        case 'reject_candidate':
            try {
                $application_id = $_POST['application_id'];
                
                // Update job application status to Rejected
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Rejected' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                // Update onboarding status to Cancelled
                $stmt = $conn->prepare("UPDATE candidate_onboarding SET status = 'Cancelled' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                $success_message = "❌ Candidate rejected.";
                $messageType = "error";
            } catch (Exception $e) {
                $success_message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
            break;
            
        case 'create_onboarding':
            try {
                $candidate_id = $_POST['candidate_id'];
                $application_id = $_POST['application_id'];
                $start_date = date('Y-m-d');
                $completion_date = date('Y-m-d', strtotime('+30 days'));
                
                $stmt = $conn->prepare("INSERT INTO candidate_onboarding (candidate_id, application_id, start_date, expected_completion_date, status) VALUES (?, ?, ?, ?, 'Pending')");
                $stmt->bind_param('iiss', $candidate_id, $application_id, $start_date, $completion_date);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create onboarding: " . $stmt->error);
                }
                
                // Get the inserted onboarding_id
                $onboarding_id = $conn->insert_id; // Use $conn->insert_id instead of $stmt->insert_id
                
                if ($onboarding_id <= 0) {
                    throw new Exception("Failed to retrieve onboarding ID. Last insert ID: " . $onboarding_id);
                }
                
                $stmt->close();
                
                // Get candidate name for the modal
                $candidate_query = $conn->prepare("SELECT first_name FROM candidates WHERE candidate_id = ?");
                $candidate_query->bind_param('i', $candidate_id);
                $candidate_query->execute();
                $candidate_result = $candidate_query->get_result();
                $candidate_row = $candidate_result->fetch_assoc();
                $candidate_name = $candidate_row['first_name'] ?? '';
                $candidate_query->close();
                
                // Auto-assign tasks for this candidate
                $dept_query = $conn->prepare("SELECT jo.department_id FROM job_applications ja 
                                             JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id 
                                             WHERE ja.application_id = ?");
                $dept_query->bind_param('i', $application_id);
                $dept_query->execute();
                $dept_result = $dept_query->get_result();
                $dept_row = $dept_result->fetch_assoc();
                $dept_id = $dept_row['department_id'] ?? null;
                $dept_query->close();
                
                // Get tasks for this department and general tasks
                $tasks_query = $conn->prepare("SELECT task_id FROM onboarding_tasks WHERE department_id = ? OR department_id IS NULL");
                $tasks_query->bind_param('i', $dept_id);
                $tasks_query->execute();
                $tasks_result = $tasks_query->get_result();
                $tasks = [];
                while ($row = $tasks_result->fetch_assoc()) {
                    $tasks[] = $row['task_id'];
                }
                $tasks_query->close();
                
                $assigned_count = 0;
                foreach ($tasks as $task_id) {
                    $check = $conn->prepare("SELECT COUNT(*) as count FROM candidate_onboarding_tasks WHERE candidate_onboarding_id = ? AND task_id = ?");
                    $check->bind_param('ii', $onboarding_id, $task_id);
                    $check->execute();
                    $check_result = $check->get_result();
                    $count_row = $check_result->fetch_assoc();
                    $check->close();
                    
                    if ($count_row['count'] == 0) {
                        $due_date = date('Y-m-d', strtotime('+7 days'));
                        $insert = $conn->prepare("INSERT INTO candidate_onboarding_tasks (candidate_onboarding_id, task_id, due_date, status) VALUES (?, ?, ?, 'Not Started')");
                        $insert->bind_param('iis', $onboarding_id, $task_id, $due_date);
                        $insert->execute();
                        $insert->close();
                        $assigned_count++;
                    }
                }
                
                $success_message = "✅ Candidate onboarding started! " . $assigned_count . " tasks assigned.";
                $messageType = "success";
                
                // Store in session to auto-show the tasks modal
                $_SESSION['auto_show_tasks_modal'] = true;
                $_SESSION['auto_show_onboarding_id'] = (int)$onboarding_id;
                $_SESSION['auto_show_candidate_name'] = $candidate_name;
            } catch (Exception $e) {
                $success_message = "Error: " . $e->getMessage();
                $messageType = "error";
                $_SESSION['auto_show_tasks_modal'] = false;
            }
            break;

        case 'complete_task':
            try {
                $stmt = $conn->prepare("UPDATE candidate_onboarding_tasks SET status = 'Completed', completion_date = CURDATE() WHERE candidate_task_id = ?");
                $stmt->bind_param('i', $_POST['progress_id']);
                $stmt->execute();
                
                $success_message = "✅ Task marked as completed!";
                $messageType = "success";
            } catch (Exception $e) {
                $success_message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
            break;
            
        case 'fail_task':
            try {
                $stmt = $conn->prepare("UPDATE candidate_onboarding_tasks SET status = 'Cancelled', completion_date = CURDATE(), notes = ? WHERE candidate_task_id = ?");
                $notes = $_POST['notes'] ?? 'Task cancelled';
                $stmt->bind_param('si', $notes, $_POST['progress_id']);
                $stmt->execute();
                $success_message = "❌ Task marked as cancelled.";
                $messageType = "warning";
            } catch (Exception $e) {
                $success_message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
            break;
            
        case 'assign_tasks':
            try {
                $onboarding_id = $_POST['onboarding_id'];
                $application_id = $_POST['application_id'];
                
                // Get department for this application
                $dept_query = $conn->prepare("SELECT jo.department_id FROM job_applications ja 
                                             JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id 
                                             WHERE ja.application_id = ?");
                $dept_query->bind_param('i', $application_id);
                $dept_query->execute();
                $dept_result = $dept_query->get_result();
                $dept_row = $dept_result->fetch_assoc();
                $dept_id = $dept_row['department_id'];
                
                // Get tasks for this department and general tasks
                $tasks_query = $conn->prepare("SELECT task_id FROM onboarding_tasks WHERE department_id = ? OR department_id IS NULL");
                $tasks_query->bind_param('i', $dept_id);
                $tasks_query->execute();
                $tasks_result = $tasks_query->get_result();
                $tasks = [];
                while ($row = $tasks_result->fetch_assoc()) {
                    $tasks[] = $row['task_id'];
                }
                
                $assigned_count = 0;
                foreach ($tasks as $task_id) {
                    $check = $conn->prepare("SELECT COUNT(*) as count FROM candidate_onboarding_tasks WHERE candidate_onboarding_id = ? AND task_id = ?");
                    $check->bind_param('ii', $onboarding_id, $task_id);
                    $check->execute();
                    $check_result = $check->get_result();
                    
                    if ($check_result->fetch_assoc()['count'] == 0) {
                        $due_date = date('Y-m-d', strtotime('+7 days'));
                        $insert = $conn->prepare("INSERT INTO candidate_onboarding_tasks (candidate_onboarding_id, task_id, due_date, status) VALUES (?, ?, ?, 'Not Started')");
                        $status = 'Not Started';
                        $insert->bind_param('iis', $onboarding_id, $task_id, $due_date);
                        $insert->execute();
                        $assigned_count++;
                    }
                }
                
                $success_message = "✅ Assigned {$assigned_count} tasks to candidate.";
                $messageType = "success";
            } catch (Exception $e) {
                $success_message = "Error: " . $e->getMessage();
                $messageType = "error";
            }
            break;
    }
}

// Fetch applicants in Reference Check status
$result = $conn->query("SELECT c.*, ja.application_id, ja.status as application_status, ja.application_date, jo.title as job_title, d.department_name,
                                co.candidate_onboarding_id, co.start_date, co.status as onboarding_status,
                                COUNT(cot.candidate_task_id) as total_tasks,
                                SUM(CASE WHEN cot.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
                        FROM candidates c 
                        JOIN job_applications ja ON c.candidate_id = ja.candidate_id
                        JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                        JOIN departments d ON jo.department_id = d.department_id
                        LEFT JOIN candidate_onboarding co ON co.candidate_id = c.candidate_id AND co.application_id = ja.application_id
                        LEFT JOIN candidate_onboarding_tasks cot ON co.candidate_onboarding_id = cot.candidate_onboarding_id
                        WHERE ja.status = 'Reference Check'
                        GROUP BY c.candidate_id, ja.application_id
                        ORDER BY ja.application_date DESC");
$reference_check_applicants = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Onboarding - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="styles.css">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title">📋 Applicant Onboarding - Reference Check Status</h2>
                    <a href="onboarding_tasks.php" class="btn btn-primary">
                        <i class="fas fa-tasks"></i> Manage Tasks
                    </a>
                </div>

                <?php if ($success_message): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            showToast("<?php echo addslashes($success_message); ?>", "<?php echo $messageType; ?>");
                        });
                    </script>
                <?php endif; ?>

                <div class="card" style="border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                    <div class="card-header bg-primary text-white" style="border-radius: 15px 15px 0 0;">
                        <h5 class="mb-0"><i class="fas fa-user-check mr-2"></i>Applicants in Reference Check</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($reference_check_applicants)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-user"></i> Name</th>
                                            <th><i class="fas fa-briefcase"></i> Position</th>
                                            <th><i class="fas fa-building"></i> Department</th>
                                            <th><i class="fas fa-envelope"></i> Email</th>
                                            <th><i class="fas fa-tasks"></i> Tasks Progress</th>
                                            <th><i class="fas fa-calendar"></i> Applied Date</th>
                                            <th style="text-align: center;"><i class="fas fa-cogs"></i> Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reference_check_applicants as $applicant): 
                                            $total = $applicant['total_tasks'] ?? 0;
                                            $completed = $applicant['completed_tasks'] ?? 0;
                                            $progress_percent = $total > 0 ? round(($completed / $total) * 100) : 0;
                                        ?>
                                        <tr style="cursor: pointer;" onclick="<?php if ($applicant['candidate_onboarding_id']) { echo "viewTasksModal(" . $applicant['candidate_onboarding_id'] . ", '" . htmlspecialchars(addslashes($applicant['first_name'])) . "')"; } else { echo "showStartOnboardingConfirm(" . $applicant['candidate_id'] . ", '" . htmlspecialchars(addslashes($applicant['first_name'])) . "', " . $applicant['application_id'] . ")"; } ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($applicant['job_title']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($applicant['department_name']); ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($applicant['email']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($total > 0): ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar <?php echo $progress_percent == 100 ? 'bg-success' : 'bg-info'; ?>" role="progressbar" style="width: <?php echo $progress_percent; ?>%">
                                                            <?php echo $completed; ?>/<?php echo $total; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">No tasks</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($applicant['application_date'] ?? date('Y-m-d'))); ?></small>
                                            </td>
                                            <td style="text-align: center;" onclick="event.stopPropagation();">
                                                <?php if ($applicant['candidate_onboarding_id']): ?>
                                                    <button class="btn btn-sm btn-info" onclick="viewTasksModal(<?php echo $applicant['candidate_onboarding_id']; ?>, '<?php echo htmlspecialchars(addslashes($applicant['first_name'])); ?>')">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <?php if ($total == 0): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="assign_tasks">
                                                            <input type="hidden" name="onboarding_id" value="<?php echo $applicant['candidate_onboarding_id']; ?>">
                                                            <input type="hidden" name="application_id" value="<?php echo $applicant['application_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="fas fa-plus"></i> Assign
                                                            </button>
                                                        </form>
                                                    <?php elseif ($progress_percent == 100): ?>
                                                        <button type="button" class="btn btn-sm btn-success" onclick="hireCandidate(<?php echo $applicant['application_id']; ?>, '<?php echo htmlspecialchars(addslashes($applicant['first_name'] . ' ' . $applicant['last_name'])); ?>')">
                                                            <i class="fas fa-file-contract"></i> Create Offer
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="rejectCandidate(<?php echo $applicant['application_id']; ?>, '<?php echo htmlspecialchars(addslashes($applicant['first_name'] . ' ' . $applicant['last_name'])); ?>')">
                                                            <i class="fas fa-user-times"></i> Reject
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="create_onboarding">
                                                        <input type="hidden" name="candidate_id" value="<?php echo $applicant['candidate_id']; ?>">
                                                        <input type="hidden" name="application_id" value="<?php echo $applicant['application_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-plus"></i> Start
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No applicants in Reference Check status</h4>
                                <p class="text-muted">Applicants will appear here when their status changes to Reference Check.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Start Onboarding Confirmation Modal -->
    <div class="modal fade" id="startOnboardingModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-play-circle mr-2"></i>Start Candidate Onboarding</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-user-check text-success" style="font-size: 48px;"></i>
                        </div>
                        <h6 class="text-center mb-3">Start onboarding for:</h6>
                        <div class="alert alert-info">
                            <strong><span id="startCandidateName"></span></strong>
                        </div>
                        <p class="text-muted">This will create an onboarding record and allow you to assign tasks. The candidate has 30 days to complete all onboarding tasks.</p>
                        <div class="alert alert-light border-left border-success">
                            <i class="fas fa-check-circle text-success mr-2"></i>
                            <strong>Next steps:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Click "Start Onboarding"</li>
                                <li>Assign department-specific tasks</li>
                                <li>Monitor task completion</li>
                                <li>Mark as complete when ready</li>
                            </ol>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="create_onboarding">
                        <input type="hidden" name="candidate_id" id="startCandidateId">
                        <input type="hidden" name="application_id" id="startApplicationId">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-play-circle mr-1"></i>Start Onboarding
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fail Task Modal -->
    <div class="modal fade" id="failTaskModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Cancel Task</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="fail_task">
                        <input type="hidden" name="progress_id" id="failProgressId">
                        <div class="form-group">
                            <label><strong>Why are you cancelling this task?</strong></label>
                            <textarea name="notes" class="form-control" rows="4" placeholder="Enter reason for cancellation..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Keep Task</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Cancel Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tasks Modal -->
    <div class="modal fade" id="tasksModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-tasks"></i> Onboarding Tasks - <span id="applicantName"></span></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="tasksModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2">Loading tasks...</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        .table tbody tr:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
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

        function viewTasksModal(candidateOnboardingId, applicantName) {
            $('#applicantName').text(applicantName);
            $('#tasksModal').modal('show');
            
            $.get('fetch_candidate_onboarding_tasks.php', { candidate_onboarding_id: candidateOnboardingId }, function(html) {
                $('#tasksModalBody').html(html);
            }).fail(function() {
                $('#tasksModalBody').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to load tasks.</div>');
                showToast('Failed to load tasks', 'error');
            });
        }

        function openFailModal(progressId) {
            $('#failProgressId').val(progressId);
            $('#failTaskModal').modal('show');
        }

        function showStartOnboardingConfirm(candidateId, candidateName, applicationId) {
            $('#startCandidateId').val(candidateId);
            $('#startApplicationId').val(applicationId);
            $('#startCandidateName').text(candidateName);
            $('#startOnboardingModal').modal('show');
        }

        // SweetAlert2 functions for hire/reject
        function hireCandidate(applicationId, candidateName) {
            Swal.fire({
                title: 'Move to Job Offer?',
                text: `Complete onboarding for ${candidateName} and move to Job Offer stage?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-file-contract"></i> Yes, Create Offer',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="hire_candidate">
                        <input type="hidden" name="application_id" value="${applicationId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        function rejectCandidate(applicationId, candidateName) {
            Swal.fire({
                title: 'Reject Candidate?',
                text: `Reject ${candidateName}? This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-user-times"></i> Yes, Reject',
                cancelButtonText: '<i class="fas fa-ban"></i> Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="reject_candidate">
                        <input type="hidden" name="application_id" value="${applicationId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Refresh tasks after form submission
        $(document).ready(function() {
            // Auto-show tasks modal after creating onboarding
            <?php if (isset($_SESSION['auto_show_tasks_modal']) && $_SESSION['auto_show_tasks_modal']): ?>
                viewTasksModal(<?php echo $_SESSION['auto_show_onboarding_id']; ?>, '<?php echo htmlspecialchars(addslashes($_SESSION['auto_show_candidate_name'])); ?>');
                <?php unset($_SESSION['auto_show_tasks_modal']); unset($_SESSION['auto_show_onboarding_id']); unset($_SESSION['auto_show_candidate_name']); ?>
            <?php endif; ?>
            
            $('form').on('submit', function(e) {
                if ($(this).find('input[name="action"]').val() === 'complete_task' || 
                    $(this).find('input[name="action"]').val() === 'fail_task') {
                    $(this).on('submit', function() {
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    });
                }
            });
        });
    </script>

    <style>
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        .table tbody tr:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }
    </style>
</body>
</html>