<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';

$success_message = '';
$selected_job = isset($_GET['job_id']) ? $_GET['job_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_stage':
                $job_id = $_POST['job_opening_id'];
                $stage_name = $_POST['stage_name'];
                $description = $_POST['description'];
                
                // Get next stage order
                $order_stmt = $conn->prepare("SELECT MAX(stage_order) as max_order FROM interview_stages WHERE job_opening_id = ?");
                $order_stmt->execute([$job_id]);
                $max_order = $order_stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;
                
                $stmt = $conn->prepare("INSERT INTO interview_stages (job_opening_id, stage_name, description, stage_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$job_id, $stage_name, $description, $max_order + 1]);
                
                $success_message = "✅ Stage added successfully!";
                break;
                
            case 'edit_stage':
                $stage_id = $_POST['stage_id'];
                $stage_name = $_POST['stage_name'];
                $description = $_POST['description'];
                
                $stmt = $conn->prepare("UPDATE interview_stages SET stage_name = ?, description = ? WHERE stage_id = ?");
                $stmt->execute([$stage_name, $description, $stage_id]);
                
                $success_message = "✅ Stage updated successfully!";
                break;
                
            case 'delete_stage':
                $stage_id = $_POST['stage_id'];
                
                $stmt = $conn->prepare("DELETE FROM interview_stages WHERE stage_id = ?");
                $stmt->execute([$stage_id]);
                
                $success_message = "✅ Stage deleted successfully!";
                break;
                
            case 'complete_interview':
                $interview_id = $_POST['interview_id'];
                $application_id = $_POST['application_id'];
                
                // Mark interview as completed
                $stmt = $conn->prepare("UPDATE interviews SET status = 'Completed', completed_date = NOW() WHERE interview_id = ?");
                $stmt->execute([$interview_id]);
                
                // Check if this was the final interview stage for this job
                $final_check = $conn->prepare("SELECT COUNT(*) as remaining FROM interviews i 
                                              JOIN interview_stages ist ON i.stage_id = ist.stage_id
                                              WHERE i.application_id = ? AND i.status != 'Completed'");
                $final_check->execute([$application_id]);
                $remaining = $final_check->fetch(PDO::FETCH_ASSOC)['remaining'];
                
                // If no more interviews remaining, move to Assessment
                if ($remaining == 0) {
                    $stmt = $conn->prepare("UPDATE job_applications SET status = 'Assessment' WHERE application_id = ?");
                    $stmt->execute([$application_id]);
                    $success_message = "✅ Interview completed! Candidate moved to Assessment for HR review.";
                } else {
                    $success_message = "✅ Interview stage completed!";
                }
                break;
                

        }
    }
}



// Get job openings with candidates ready for onboarding (based on job application status)
$job_openings = $conn->query("SELECT DISTINCT jo.job_opening_id, jo.title, d.department_name, 
                                     COUNT(CASE WHEN ja.status = 'Hired' THEN 1 END) as hired_count,
                                     COUNT(CASE WHEN ja.status = 'Interview' THEN 1 END) as in_process_count
                              FROM job_openings jo
                              JOIN departments d ON jo.department_id = d.department_id
                              JOIN job_applications ja ON jo.job_opening_id = ja.job_opening_id
                              WHERE ja.status IN ('Interview', 'Hired')
                              GROUP BY jo.job_opening_id, jo.title, d.department_name
                              ORDER BY jo.title")->fetchAll(PDO::FETCH_ASSOC);

if ($selected_job) {
    // Auto-create interview records for Interview status candidates without interviews
    $conn->exec("INSERT INTO interviews (application_id, stage_id, schedule_date, duration, interview_type, status)
                 SELECT ja.application_id, ist.stage_id, NOW(), 60, 'Interview', 'Rescheduled'
                 FROM job_applications ja 
                 JOIN interview_stages ist ON ja.job_opening_id = ist.job_opening_id 
                 WHERE ja.job_opening_id = $selected_job AND ja.status = 'Interview' 
                 AND ist.stage_order = 1
                 AND NOT EXISTS (SELECT 1 FROM interviews i WHERE i.application_id = ja.application_id)");
    
    // Get stages for selected job
    $job_stages = $conn->prepare("SELECT * FROM interview_stages WHERE job_opening_id = ? ORDER BY stage_order");
    $job_stages->execute([$selected_job]);
    $stages = $job_stages->fetchAll(PDO::FETCH_ASSOC);
    
    // Get job info
    $job_info = $conn->prepare("SELECT jo.title, d.department_name FROM job_openings jo 
                               JOIN departments d ON jo.department_id = d.department_id 
                               WHERE jo.job_opening_id = ?");
    $job_info->execute([$selected_job]);
    $job_details = $job_info->fetch(PDO::FETCH_ASSOC);
    
    // Get candidates for each stage with interview status
    $candidates_by_stage = [];
    foreach ($stages as $stage) {
        if ($stage['stage_order'] == 1) {
            // First stage: show all Interview status candidates
            $candidates = $conn->prepare("SELECT c.*, ja.application_id, ja.application_date, 
                                                i.interview_id, i.status as interview_status, i.schedule_date
                                         FROM candidates c 
                                         JOIN job_applications ja ON c.candidate_id = ja.candidate_id
                                         LEFT JOIN interviews i ON ja.application_id = i.application_id AND i.stage_id = ?
                                         WHERE ja.job_opening_id = ? AND ja.status = 'Interview'
                                         ORDER BY ja.application_date DESC");
            $candidates->execute([$stage['stage_id'], $selected_job]);
        } else {
            // Other stages: show candidates who completed previous stages
            $candidates = $conn->prepare("SELECT c.*, ja.application_id, ja.application_date, 
                                                i.interview_id, i.status as interview_status, i.schedule_date
                                         FROM candidates c 
                                         JOIN job_applications ja ON c.candidate_id = ja.candidate_id
                                         LEFT JOIN interviews i ON ja.application_id = i.application_id AND i.stage_id = ?
                                         WHERE ja.job_opening_id = ? AND ja.status = 'Interview'
                                         AND EXISTS (SELECT 1 FROM interviews prev_i 
                                                    JOIN interview_stages prev_s ON prev_i.stage_id = prev_s.stage_id
                                                    WHERE prev_i.application_id = ja.application_id 
                                                    AND prev_s.stage_order < ? AND prev_i.status = 'Completed')
                                         ORDER BY ja.application_date DESC");
            $candidates->execute([$stage['stage_id'], $selected_job, $stage['stage_order']]);
        }
        $candidates_by_stage[$stage['stage_name']] = $candidates->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Stages - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2>🎯 Interview Stages Management</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (!$selected_job): ?>
                    <!-- Job Openings Cards -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Select a job opening below to manage its interview stages and candidates.
                    </div>
                    
                    <div class="row">
                        <?php foreach ($job_openings as $job): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card job-card h-100" style="cursor: pointer;" onclick="window.location.href='?job_id=<?php echo $job['job_opening_id']; ?>'">
                                    <div class="card-body text-center">
                                        <div class="activity-icon bg-primary mb-3">
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                        <h5 class="card-title"><?php echo htmlspecialchars($job['title']); ?></h5>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($job['department_name']); ?></p>
                                        <div class="mt-3">
                                            <?php if ($job['hired_count'] > 0): ?>
                                                <span class="badge badge-success"><?php echo $job['hired_count']; ?> Hired</span>
                                            <?php endif; ?>
                                            <?php if ($job['in_process_count'] > 0): ?>
                                                <span class="badge badge-warning"><?php echo $job['in_process_count']; ?> In Process</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-3">
                                            <button class="btn btn-primary action-btn">
                                                <i class="fas fa-arrow-right"></i> Manage Stages
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($job_openings)): ?>
                        <div class="alert alert-warning text-center">
                            <h5><i class="fas fa-exclamation-triangle"></i> No Active Assessment</h5>
                            <p>No candidates are currently in the assessment process.</p>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Selected Job Stages -->
                    <div class="mb-3">
                        <a href="interview_stages.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Job Openings
                        </a>
                        <button class="btn btn-success ml-2" data-toggle="modal" data-target="#addStageModal">
                            <i class="fas fa-plus"></i> Add Stage
                        </button>
                        <button class="btn btn-info ml-2" data-toggle="modal" data-target="#manageStagesModal">
                            <i class="fas fa-cogs"></i> Manage Stages
                        </button>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-briefcase"></i> 
                                <?php echo htmlspecialchars($job_details['title']); ?>
                                <small class="text-muted">- <?php echo htmlspecialchars($job_details['department_name']); ?></small>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Screening Section (Mayor Approval Required) -->
                            <?php if (!empty($screening_list)): ?>
                                <div class="mb-4">
                                    <h6>
                                        <span class="badge badge-warning">📋</span>
                                        Screening - Awaiting Mayor Approval 
                                        (<?php echo count($screening_list); ?>)
                                    </h6>
                                    <p class="text-muted small">Candidates in screening status require mayor approval to proceed to interview stages.</p>
                                    
                                    <div class="row">
                                        <?php foreach ($screening_list as $candidate): ?>
                                            <div class="col-md-6 col-lg-4 mb-2">
                                                <div class="card border-warning">
                                                    <div class="card-body p-2">
                                                        <h6 class="mb-1">
                                                            <i class="fas fa-user"></i> 
                                                            <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                                        </h6>
                                                        <p class="mb-1 small">
                                                            <strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?>
                                                        </p>
                                                        <p class="mb-2 small">
                                                            <strong>Applied:</strong> <?php echo date('M d, Y', strtotime($candidate['application_date'])); ?>
                                                        </p>
                                                        
                                                        <?php if ($_SESSION['role'] == 'Mayor'): ?>
                                                            <form method="POST" class="w-100">
                                                                <input type="hidden" name="action" value="mayor_approve">
                                                                <input type="hidden" name="application_id" value="<?php echo $candidate['application_id']; ?>">
                                                                <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Approve this candidate for interview?')">
                                                                    <i class="fas fa-check"></i> Mayor Approve
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <div class="alert alert-warning p-2">
                                                                <small><i class="fas fa-clock"></i> Awaiting Mayor Approval</small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Interview Stages -->
                            <?php foreach ($stages as $stage): 
                                $stage_candidates = $candidates_by_stage[$stage['stage_name']] ?? [];
                                $stage_colors = ['border-primary', 'border-info', 'border-warning', 'border-success', 'border-danger'];
                                $color = $stage_colors[($stage['stage_order'] - 1) % count($stage_colors)];
                            ?>
                                <div class="mb-4">
                                    <h6>
                                        <span class="badge badge-primary"><?php echo $stage['stage_order']; ?></span>
                                        <?php echo htmlspecialchars($stage['stage_name']); ?> 
                                        (<?php echo count($stage_candidates); ?>)
                                    </h6>
                                    <?php if ($stage['description']): ?>
                                        <p class="text-muted small"><?php echo htmlspecialchars($stage['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (count($stage_candidates) > 0): ?>
                                        <div class="row">
                                            <?php foreach ($stage_candidates as $candidate): ?>
                                                <div class="col-md-6 col-lg-4 mb-2">
                                                    <div class="card <?php echo $color; ?>">
                                                        <div class="card-body p-2">
                                                            <h6 class="mb-1">
                                                                <i class="fas fa-user"></i> 
                                                                <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                                            </h6>
                                                            <p class="mb-1 small">
                                                                <strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?>
                                                            </p>
                                                            <p class="mb-2 small">
                                                                <strong>Applied:</strong> <?php echo date('M d, Y', strtotime($candidate['application_date'])); ?>
                                                            </p>
                                                            
                                                            <div class="w-100">
                                                                <!-- Debug Info -->
                                                                <small class="text-muted">Debug: App ID: <?php echo $candidate['application_id']; ?>, Stage ID: <?php echo $stage['stage_id']; ?>, Interview ID: <?php echo $candidate['interview_id'] ?? 'None'; ?></small>
                                                                
                                                                <?php if ($candidate['interview_id']): ?>
                                                                    <div class="mb-2">
                                                                        <span class="badge badge-<?php 
                                                                            echo $candidate['interview_status'] == 'Rescheduled' ? 'warning' : 
                                                                                ($candidate['interview_status'] == 'Scheduled' ? 'primary' : 
                                                                                ($candidate['interview_status'] == 'Completed' ? 'success' : 'secondary')); 
                                                                        ?>">
                                                                            <?php 
                                                                            $status_icons = [
                                                                                'Rescheduled' => '⏰ Need Scheduling',
                                                                                'Scheduled' => '📅 Scheduled',
                                                                                'Completed' => '✅ Completed',
                                                                                'Cancelled' => '❌ Cancelled'
                                                                            ];
                                                                            echo $status_icons[$candidate['interview_status']] ?? $candidate['interview_status'];
                                                                            ?>
                                                                        </span>
                                                                    </div>
                                                                    <?php if ($candidate['interview_status'] == 'Scheduled' && $candidate['schedule_date']): ?>
                                                                        <p class="mb-2 small text-info">
                                                                            <i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($candidate['schedule_date'])); ?>
                                                                        </p>
                                                                    <?php endif; ?>
                                                                    <?php if ($candidate['interview_status'] == 'Scheduled'): ?>
                                                                        <form method="POST" class="mb-1">
                                                                            <input type="hidden" name="action" value="complete_interview">
                                                                            <input type="hidden" name="interview_id" value="<?php echo $candidate['interview_id']; ?>">
                                                                            <input type="hidden" name="application_id" value="<?php echo $candidate['application_id']; ?>">
                                                                            <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Mark this interview as completed?')">
                                                                                <i class="fas fa-check"></i> Complete Interview
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                    <a href="interviews.php?interview_id=<?php echo $candidate['interview_id']; ?>" class="btn btn-info btn-sm w-100">
                                                                        <i class="fas fa-calendar-alt"></i> Manage Interview
                                                                    </a>
                                                                <?php else: ?>
                                                                    <div class="alert alert-warning p-2">
                                                                        <small><i class="fas fa-exclamation-triangle"></i> Interview not scheduled yet</small>
                                                                    </div>
                                                                    <a href="interviews.php?application_id=<?php echo $candidate['application_id']; ?>&stage_id=<?php echo $stage['stage_id']; ?>" class="btn btn-primary btn-sm w-100">
                                                                        <i class="fas fa-calendar-plus"></i> Schedule Interview
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-light">
                                            <i class="fas fa-info-circle"></i> No candidates in this stage.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Stage Modal -->
    <div class="modal fade" id="addStageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Interview Stage</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_stage">
                        <input type="hidden" name="job_opening_id" value="<?php echo $selected_job; ?>">
                        <div class="form-group">
                            <label>Stage Name</label>
                            <input type="text" name="stage_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Stage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manage Stages Modal -->
    <div class="modal fade" id="manageStagesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Interview Stages</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($stages)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Stage Name</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($stages as $stage): ?>
                                        <tr>
                                            <td><?php echo $stage['stage_order']; ?></td>
                                            <td><?php echo htmlspecialchars($stage['stage_name']); ?></td>
                                            <td><?php echo htmlspecialchars($stage['description'] ?? 'No description'); ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" onclick="editStage(<?php echo $stage['stage_id']; ?>, '<?php echo addslashes($stage['stage_name']); ?>', '<?php echo addslashes($stage['description'] ?? ''); ?>')">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display:inline;" class="ml-1">
                                                    <input type="hidden" name="action" value="delete_stage">
                                                    <input type="hidden" name="stage_id" value="<?php echo $stage['stage_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this stage?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No interview stages defined for this job. Add stages to begin candidate assessment.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Stage Modal -->
    <div class="modal fade" id="editStageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Interview Stage</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_stage">
                        <input type="hidden" name="stage_id" id="edit_stage_id">
                        <div class="form-group">
                            <label>Stage Name</label>
                            <input type="text" name="stage_name" id="edit_stage_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Stage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function editStage(stageId, stageName, description) {
            document.getElementById('edit_stage_id').value = stageId;
            document.getElementById('edit_stage_name').value = stageName;
            document.getElementById('edit_description').value = description;
            $('#manageStagesModal').modal('hide');
            $('#editStageModal').modal('show');
        }
    </script>
</body>
</html>