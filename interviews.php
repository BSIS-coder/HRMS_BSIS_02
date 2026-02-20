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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'schedule_interview':
            case 'reschedule_interview':
                $interview_id = $_POST['interview_id'];
                $schedule_date = $_POST['schedule_date'];
                $duration = $_POST['duration'];
                $location = $_POST['location'];
                $interview_type = $_POST['interview_type'];
                
                $stmt = $conn->prepare("UPDATE interviews SET schedule_date = ?, duration = ?, location = ?, interview_type = ?, status = 'Scheduled' WHERE interview_id = ?");
                $stmt->execute([$schedule_date, $duration, $location, $interview_type, $interview_id]);
                $success_message = "📅 Interview " . ($_POST['action'] == 'reschedule_interview' ? 'rescheduled' : 'scheduled') . " successfully!";
                break;
                
            case 'auto_schedule_all':
                // Auto-schedule all pending interviews
                $pending = $conn->query("SELECT interview_id FROM interviews WHERE status = 'Rescheduled'");
                $count = 0;
                
                foreach ($pending->fetchAll(PDO::FETCH_ASSOC) as $interview) {
                    // AI picks next available business day + random time between 9 AM - 4 PM
                    $next_day = date('Y-m-d', strtotime('+' . ($count + 1) . ' weekday'));
                    $hour = rand(9, 16); // 9 AM to 4 PM
                    $minute = rand(0, 1) * 30; // 0 or 30 minutes
                    $schedule_time = $next_day . ' ' . sprintf('%02d:%02d:00', $hour, $minute);
                    
                    $stmt = $conn->prepare("UPDATE interviews SET schedule_date = ?, duration = 60, location = 'HR Office', interview_type = 'In-person', status = 'Scheduled' WHERE interview_id = ?");
                    $stmt->execute([$schedule_time, $interview['interview_id']]);
                    $count++;
                }
                
                $success_message = "🤖 Auto-scheduled $count interviews successfully!";
                break;
                
            case 'auto_complete_all':
                // Auto-complete only today's scheduled interviews
                $scheduled = $conn->query("SELECT i.interview_id, i.application_id, i.stage_id, ja.candidate_id, ist.job_opening_id, ist.stage_order, ist.stage_name FROM interviews i JOIN job_applications ja ON i.application_id = ja.application_id JOIN interview_stages ist ON i.stage_id = ist.stage_id WHERE i.status = 'Scheduled' AND DATE(i.schedule_date) = CURDATE()");
                $count = 0;
                
                $feedbacks = ['Good communication skills', 'Strong technical knowledge', 'Excellent problem-solving abilities', 'Great team player', 'Shows leadership potential'];
                $recommendations = ['Strong Yes', 'Yes', 'Strong Yes', 'Yes'];
                
                foreach ($scheduled->fetchAll(PDO::FETCH_ASSOC) as $interview) {
                    $feedback = $feedbacks[array_rand($feedbacks)];
                    $rating = rand(35, 50) / 10; // 3.5 to 5.0
                    $recommendation = $recommendations[array_rand($recommendations)];
                    
                    // Complete interview
                    $stmt = $conn->prepare("UPDATE interviews SET status = 'Completed', feedback = ?, rating = ?, recommendation = ?, completed_date = NOW() WHERE interview_id = ?");
                    $stmt->execute([$feedback, $rating, $recommendation, $interview['interview_id']]);
                    
                    // Check if there's a next stage
                    $next_stage = $conn->prepare("SELECT stage_id, stage_name FROM interview_stages WHERE job_opening_id = ? AND stage_order = ?");
                    $next_stage->execute([$interview['job_opening_id'], $interview['stage_order'] + 1]);
                    $next_stage_info = $next_stage->fetch(PDO::FETCH_ASSOC);
                    
                    if ($next_stage_info) {
                        // Move to next stage
                        $stmt = $conn->prepare("UPDATE candidates SET source = ? WHERE candidate_id = ?");
                        $stmt->execute(['Interview - ' . $next_stage_info['stage_name'], $interview['candidate_id']]);
                        
                        // Create next interview
                        $stmt = $conn->prepare("INSERT INTO interviews (application_id, stage_id, schedule_date, duration, interview_type, status) VALUES (?, ?, NOW(), 60, 'Interview', 'Rescheduled')");
                        $stmt->execute([$interview['application_id'], $next_stage_info['stage_id']]);
                        
                        // Keep status as 'Interview' - don't change it
                    } else {
                        // No more stages
                        // No more stages: mark for assessment
                        $stmt = $conn->prepare("UPDATE job_applications SET status = 'Assessment' WHERE application_id = ?");
                        $stmt->execute([$interview['application_id']]);
                        
                        $stmt = $conn->prepare("UPDATE candidates SET source = 'Assessment' WHERE candidate_id = ?");
                        $stmt->execute([$interview['candidate_id']]);
                    }
                    $count++;
                }
                
                $success_message = "🤖 Auto-completed $count interviews successfully!";
                break;
                
            case 'reschedule_back':
                $interview_id = $_POST['interview_id'];
                
                // Change scheduled interview back to rescheduled status
                $stmt = $conn->prepare("UPDATE interviews SET status = 'Rescheduled' WHERE interview_id = ?");
                $stmt->execute([$interview_id]);
                
                $success_message = "📅 Interview moved back to scheduler for rescheduling!";
                break;
                
            case 'complete_interview':
                $interview_id = $_POST['interview_id'];
                $feedback = $_POST['feedback'];
                $rating = $_POST['rating'];
                $recommendation = $_POST['recommendation'];
                
                $stmt = $conn->prepare("UPDATE interviews SET status = 'Completed', feedback = ?, rating = ?, recommendation = ?, completed_date = NOW() WHERE interview_id = ?");
                $stmt->execute([$feedback, $rating, $recommendation, $interview_id]);
                
                // Get interview and stage information
                $stmt = $conn->prepare("SELECT i.application_id, i.stage_id, ja.candidate_id, ist.job_opening_id, ist.stage_order, ist.stage_name 
                                       FROM interviews i 
                                       JOIN job_applications ja ON i.application_id = ja.application_id 
                                       JOIN interview_stages ist ON i.stage_id = ist.stage_id 
                                       WHERE i.interview_id = ?");
                $stmt->execute([$interview_id]);
                $interview_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($recommendation == 'Strong Yes' || $recommendation == 'Yes') {
                    // Check if there's a next stage
                    $next_stage = $conn->prepare("SELECT stage_id, stage_name FROM interview_stages WHERE job_opening_id = ? AND stage_order = ?");
                    $next_stage->execute([$interview_data['job_opening_id'], $interview_data['stage_order'] + 1]);
                    $next_stage_info = $next_stage->fetch(PDO::FETCH_ASSOC);
                    
                    if ($next_stage_info) {
                        // Move to next interview stage
                        $stmt = $conn->prepare("UPDATE candidates SET source = ? WHERE candidate_id = ?");
                        $stmt->execute(['Interview - ' . $next_stage_info['stage_name'], $interview_data['candidate_id']]);
                        
                        // Create next interview with Rescheduled status
                        $stmt = $conn->prepare("INSERT INTO interviews (application_id, stage_id, schedule_date, duration, interview_type, status) VALUES (?, ?, NOW(), 60, 'Interview', 'Rescheduled')");
                        $stmt->execute([$interview_data['application_id'], $next_stage_info['stage_id']]);
                            
                        // Keep job application status as 'Interview' - don't change it
                        // The stage is tracked in the interviews table via stage_id
                        
                        $success_message = "✅ Interview completed! Candidate moved to {$next_stage_info['stage_name']}.";
                    } else {
                        // No more stages - set status to Assessment so HR can review
                        $stmt = $conn->prepare("UPDATE job_applications SET status = 'Assessment' WHERE application_id = ?");
                        $stmt->execute([$interview_data['application_id']]);
                        
                        $stmt = $conn->prepare("UPDATE candidates SET source = 'Assessment' WHERE candidate_id = ?");
                        $stmt->execute([$interview_data['candidate_id']]);
                        
                        $success_message = "✅ All interview stages completed! Moved to Assessment for final review.";
                    }
                } else {
                    // Clear all interview history for this candidate
                    $stmt = $conn->prepare("DELETE FROM interviews WHERE application_id = ?");
                    $stmt->execute([$interview_data['application_id']]);
                    
                    $stmt = $conn->prepare("UPDATE job_applications SET status = 'Rejected' WHERE application_id = ?");
                    $stmt->execute([$interview_data['application_id']]);
                    
                    $stmt = $conn->prepare("UPDATE candidates SET source = 'Rejected' WHERE candidate_id = ?");
                    $stmt->execute([$interview_data['candidate_id']]);
                    
                    $success_message = "❌ Candidate rejected and interview history cleared.";
                }
                break;
                
            case 'cancel_interview':
                $interview_id = $_POST['interview_id'];
                
                $stmt = $conn->prepare("SELECT i.application_id, ja.candidate_id FROM interviews i JOIN job_applications ja ON i.application_id = ja.application_id WHERE i.interview_id = ?");
                $stmt->execute([$interview_id]);
                $interview_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Clear all interview history for this candidate
                $stmt = $conn->prepare("DELETE FROM interviews WHERE application_id = ?");
                $stmt->execute([$interview_data['application_id']]);
                
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Rejected' WHERE application_id = ?");
                $stmt->execute([$interview_data['application_id']]);
                
                $stmt = $conn->prepare("UPDATE candidates SET source = 'Rejected' WHERE candidate_id = ?");
                $stmt->execute([$interview_data['candidate_id']]);
                
                $success_message = "❌ Candidate rejected and interview history cleared!";
                break;
                
            case 'reopen_candidate':
                $application_id = $_POST['application_id'];
                
                // Get candidate info
                $stmt = $conn->prepare("SELECT ja.candidate_id FROM job_applications ja WHERE ja.application_id = ?");
                $stmt->execute([$application_id]);
                $app_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Clear any existing interview history
                $stmt = $conn->prepare("DELETE FROM interviews WHERE application_id = ?");
                $stmt->execute([$application_id]);
                
                // Reset application status to Applied
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Applied' WHERE application_id = ?");
                $stmt->execute([$application_id]);
                
                // Reset candidate source
                $stmt = $conn->prepare("UPDATE candidates SET source = 'Job Application' WHERE candidate_id = ?");
                $stmt->execute([$app_data['candidate_id']]);
                
                $success_message = "🔄 Candidate reopened with fresh start!";
                break;
        }
    }
}

// Get pending interviews (created from candidates dashboard - need scheduling)
$pending_interviews = $conn->query("SELECT i.interview_id, i.application_id, c.first_name, c.last_name, jo.title as job_title, ist.stage_name
                                    FROM interviews i
                                    JOIN job_applications ja ON i.application_id = ja.application_id
                                    JOIN candidates c ON ja.candidate_id = c.candidate_id
                                    JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                                    JOIN interview_stages ist ON i.stage_id = ist.stage_id
                                    WHERE i.status = 'Rescheduled'
                                    ORDER BY i.interview_id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get today's interviews
$today_interviews = $conn->query("SELECT i.*, c.first_name, c.last_name, jo.title as job_title, ist.stage_name
                                  FROM interviews i
                                  JOIN job_applications ja ON i.application_id = ja.application_id
                                  JOIN candidates c ON ja.candidate_id = c.candidate_id
                                  JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                                  JOIN interview_stages ist ON i.stage_id = ist.stage_id
                                  WHERE DATE(i.schedule_date) = CURDATE() AND i.status = 'Scheduled'
                                  ORDER BY i.schedule_date ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get all interviews
$all_interviews = $conn->query("SELECT i.*, c.first_name, c.last_name, c.email, jo.title as job_title, d.department_name, ist.stage_name
                                FROM interviews i
                                JOIN job_applications ja ON i.application_id = ja.application_id
                                JOIN candidates c ON ja.candidate_id = c.candidate_id
                                JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                                JOIN departments d ON jo.department_id = d.department_id
                                JOIN interview_stages ist ON i.stage_id = ist.stage_id
                                WHERE i.status IN ('Scheduled', 'Completed')
                                GROUP BY i.interview_id
                                ORDER BY i.schedule_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'pending' => count($pending_interviews),
    'today' => count($today_interviews),
    'scheduled' => count(array_filter($all_interviews, function($i) { return $i['status'] == 'Scheduled'; })),
    'completed' => count(array_filter($all_interviews, function($i) { return $i['status'] == 'Completed'; }))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interviews - HR Management System</title>
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
                <h2>🗣️ Interview Management</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['pending']; ?></h3>
                                <p class="stats-label">Need Scheduling</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-info">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['today']; ?></h3>
                                <p class="stats-label">Today's Interviews</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-primary">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['scheduled']; ?></h3>
                                <p class="stats-label">Scheduled</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['completed']; ?></h3>
                                <p class="stats-label">Completed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interview Scheduler -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-plus"></i> Interview Scheduler</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($pending_interviews) > 0): ?>
                            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-exclamation-triangle"></i> <strong><?php echo count($pending_interviews); ?> candidates</strong> moved to interview stage
                                </div>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="auto_schedule_all">
                                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Auto-schedule all pending interviews?')">
                                        <i class="fas fa-robot"></i> Auto-Schedule All
                                    </button>
                                </form>
                            </div>
                            <div class="row">
                                <?php foreach($pending_interviews as $interview): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card border-warning schedule-card" style="cursor: pointer;" 
                                         data-interview-id="<?php echo $interview['interview_id']; ?>"
                                         data-candidate-name="<?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?>"
                                         data-job-title="<?php echo htmlspecialchars($interview['job_title']); ?>">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">
                                                👤 <?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?>
                                            </h6>
                                            <p class="card-text">
                                                <strong><?php echo htmlspecialchars($interview['job_title']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($interview['stage_name']); ?></small>
                                            </p>
                                            <span class="badge badge-warning">📅 Click to Schedule</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle"></i> No candidates pending interview scheduling
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Today's Interviews -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar-day"></i> Today's Interviews</h5>
                        <?php if (count($today_interviews) > 0): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="auto_complete_all">
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Auto-complete all scheduled interviews?')">
                                    <i class="fas fa-robot"></i> Auto-Complete All
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (count($today_interviews) > 0): ?>
                            <div class="row">
                                <?php foreach($today_interviews as $interview): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                👤 <?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?>
                                            </h6>
                                            <p class="card-text">
                                                <strong><?php echo htmlspecialchars($interview['job_title']); ?></strong><br>
                                                <small class="text-muted">
                                                    🕐 <?php echo date('g:i A', strtotime($interview['schedule_date'])); ?> • 
                                                    📍 <?php echo htmlspecialchars($interview['location'] ?: 'TBD'); ?>
                                                </small>
                                            </p>
                                            <button class="btn btn-success btn-sm action-btn" onclick="completeInterview(<?php echo $interview['interview_id']; ?>)">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                            <form method="POST" style="display:inline;" class="ml-1">
                                                <input type="hidden" name="action" value="reschedule_back">
                                                <input type="hidden" name="interview_id" value="<?php echo $interview['interview_id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Move this interview back to scheduler for rescheduling?')">
                                                    <i class="fas fa-calendar-times"></i> Reschedule
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-calendar"></i> No interviews scheduled for today
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- All Interviews List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Interview History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Candidate</th>
                                        <th>Position</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($all_interviews) > 0): ?>
                                        <?php foreach($all_interviews as $interview): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($interview['email']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($interview['job_title']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($interview['stage_name']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y g:i A', strtotime($interview['schedule_date'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $interview['status'] == 'Scheduled' ? 'primary' : 'success'; ?>">
                                                    <?php echo $interview['status'] == 'Scheduled' ? '📅 Scheduled' : '✅ Completed'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($interview['status'] == 'Scheduled'): ?>
                                                    <button class="btn btn-success btn-sm action-btn" onclick="completeInterview(<?php echo $interview['interview_id']; ?>)">
                                                        <i class="fas fa-check"></i> Complete
                                                    </button>
                                                    <form method="POST" style="display:inline;" class="ml-1">
                                                        <input type="hidden" name="action" value="reschedule_back">
                                                        <input type="hidden" name="interview_id" value="<?php echo $interview['interview_id']; ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Move this interview back to scheduler?')">
                                                            <i class="fas fa-calendar-times"></i> Reschedule
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <small class="text-muted">
                                                        Rating: <?php echo $interview['rating']; ?>/5 • <?php echo $interview['recommendation']; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No interviews found</td>
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

    <!-- Complete Interview Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Interview</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="complete_interview">
                        <input type="hidden" name="interview_id" id="modalInterviewId">
                        
                        <div class="form-group">
                            <label>Feedback</label>
                            <textarea name="feedback" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Rating (1-5)</label>
                                    <input type="number" name="rating" class="form-control" min="1" max="5" step="0.1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Recommendation</label>
                                    <select name="recommendation" class="form-control" required>
                                        <option value="Strong Yes">Strong Yes</option>
                                        <option value="Yes">Yes</option>
                                        <option value="Maybe">Maybe</option>
                                        <option value="No">No</option>
                                        <option value="Strong No">Strong No</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Complete Interview</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Schedule Interview Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Interview</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="schedule_interview">
                        <input type="hidden" name="interview_id" id="scheduleInterviewId">
                        
                        <div class="alert alert-info">
                            <strong id="candidateName"></strong> - <span id="jobTitle"></span>
                        </div>
                        
                        <div class="form-group">
                            <label>Date & Time</label>
                            <input type="datetime-local" name="schedule_date" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Duration</label>
                                    <select name="duration" class="form-control" required>
                                        <option value="30">30 minutes</option>
                                        <option value="60" selected>1 hour</option>
                                        <option value="90">1.5 hours</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Interview Type</label>
                                    <select name="interview_type" class="form-control" required>
                                        <option value="In-person">In-person</option>
                                        <option value="Video Call">Video Call</option>
                                        <option value="Phone">Phone</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" class="form-control" placeholder="HR Office / Zoom Link">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule Interview</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js">
</script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Handle schedule card clicks
            $(document).on('click', '.schedule-card', function() {
                var interviewId = $(this).data('interview-id');
                var candidateName = $(this).data('candidate-name');
                var jobTitle = $(this).data('job-title');
                
                console.log('Schedule interview:', interviewId, candidateName, jobTitle);
                
                document.getElementById('scheduleInterviewId').value = interviewId;
                document.getElementById('candidateName').textContent = candidateName;
                document.getElementById('jobTitle').textContent = jobTitle;
                $('#scheduleModal').modal('show');
            });
        });
        
        function completeInterview(interviewId) {
            console.log('Complete interview:', interviewId);
            document.getElementById('modalInterviewId').value = interviewId;
            $('#completeModal').modal('show');
        }
        
        function scheduleInterview(interviewId, candidateName, jobTitle) {
            console.log('Schedule interview (direct):', interviewId, candidateName, jobTitle);
            document.getElementById('scheduleInterviewId').value = interviewId;
            document.getElementById('candidateName').textContent = candidateName;
            document.getElementById('jobTitle').textContent = jobTitle;
            $('#scheduleModal').modal('show');
        }
    </script>
</body>
</html>
