<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';
require_once 'link_candidate_documents.php';

// Create notification_letters table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS notification_letters (
    letter_id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('Mayor Approval', 'Interview Letter', 'Offer Letter', 'Rejection Letter', 'General') NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('Draft', 'Sent', 'Delivered') DEFAULT 'Draft',
    created_by INT,
    created_at DATETIME,
    sent_at DATETIME
)");

$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'auto_generate_offer':
                $application_id = $_POST['application_id'];
                
                // Get candidate and job details
                $stmt = $conn->prepare("SELECT c.*, ja.*, jo.title as job_title, jo.salary_range_min, jo.salary_range_max, d.department_name FROM candidates c JOIN job_applications ja ON c.candidate_id = ja.candidate_id JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id JOIN departments d ON jo.department_id = d.department_id WHERE ja.application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $candidate = $result->fetch_assoc();
                
                // Auto-generate offer with default values
                $default_salary = $candidate['salary_range_min'] ?: 50000;
                $default_start_date = date('Y-m-d', strtotime('+30 days'));
                $default_benefits = 'Health insurance, 15 days vacation, retirement plan';
                
                // Create offer using existing table structure
                $stmt = $conn->prepare("INSERT INTO job_offers (application_id, job_opening_id, candidate_id, offered_salary, offered_benefits, start_date, expiration_date, approval_status, offer_status, notes) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 'Pending', 'Draft', 'Auto-generated offer - can be modified')");
                $stmt->bind_param('iiidss', $application_id, $candidate['job_opening_id'], $candidate['candidate_id'], $default_salary, $default_benefits, $default_start_date);
                $stmt->execute();
                $offer_id = $conn->insert_id;
                
                // Update application status to Screening for Mayor approval
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Screening' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                // Create notification letter for Mayor approval
                $mayor_email = 'mayor@city.gov'; // Change this to actual mayor email
                $subject = "Job Offer Approval Required - {$candidate['first_name']} {$candidate['last_name']}";
                $message = "Dear Mayor,\n\nA job offer has been auto-generated and requires your approval:\n\nCandidate: {$candidate['first_name']} {$candidate['last_name']}\nPosition: {$candidate['job_title']}\nDepartment: {$candidate['department_name']}\nProposed Salary: $" . number_format($default_salary) . "\nStart Date: {$default_start_date}\nBenefits: {$default_benefits}\n\nNote: This offer can be modified before approval.\nPlease review at: http://localhost/HRMS_BSIS_02-test/job_offers.php\n\nBest regards,\nHR Department";
                
                // Log notification (email sending removed)
                $status = 'Logged';
                $stmt = $conn->prepare("INSERT INTO notification_letters (type, recipient, subject, content, status, created_by, created_at, sent_at) VALUES ('Mayor Approval', ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param('ssssi', $mayor_email, $subject, $message, $status, $_SESSION['user_id']);
                $stmt->execute();
                
                $success_message = "🤖 Auto-generated offer created and logged for Mayor approval!";
                break;
                
            case 'approve_screening':
                $application_id = $_POST['application_id'];
                
                // Change status from Screening to Interview
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Interview' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                $success_message = "✅ Application approved! Moved to Interview stage!";
                break;
                
            case 'reject_candidate':
                $application_id = $_POST['application_id'];
                
                // Delete related job offers
                $stmt = $conn->prepare("DELETE FROM job_offers WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                // Delete related interviews
                $stmt = $conn->prepare("DELETE FROM interviews WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                // Delete related notification letters (Mayor approval letters)
                $stmt = $conn->prepare("DELETE FROM notification_letters WHERE type = 'Mayor Approval' AND content LIKE ?");
                $search_term = '%application_id: ' . $application_id . '%';
                $stmt->bind_param('s', $search_term);
                $stmt->execute();
                
                // Update application status to Rejected
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Rejected' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                $success_message = "❌ Application rejected and all related offers/notifications removed!";
                break;
                
            case 'update_assessment':
                $application_id = $_POST['application_id'];
                $assessment_score = $_POST['assessment_score'];
                
                $assessment_json = json_encode(['overall_score' => $assessment_score]);
                $stmt = $conn->prepare("UPDATE job_applications SET assessment_scores = ? WHERE application_id = ?");
                $stmt->bind_param('si', $assessment_json, $application_id);
                $stmt->execute();
                
                $success_message = "📊 Assessment score updated!";
                break;
                
            case 'hire_candidate':
                $application_id = $_POST['application_id'];
                
                // Get candidate details
                $stmt = $conn->prepare("SELECT candidate_id, first_name, last_name FROM candidates c JOIN job_applications ja ON c.candidate_id = ja.candidate_id WHERE ja.application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $candidate = $result->fetch_assoc();
                
                if ($candidate) {
                    // Create employee profile (simplified)
                    $employee_number = 'EMP' . date('Y') . str_pad($candidate['candidate_id'], 4, '0', STR_PAD_LEFT);
                    
                    // Insert into employee_profiles (basic info)
                    $stmt = $conn->prepare("INSERT INTO employee_profiles (employee_number, hire_date, employment_status, current_salary) VALUES (?, NOW(), 'Full-time', 50000)");
                    $stmt->bind_param('s', $employee_number);
                    $stmt->execute();
                    $employee_id = $conn->insert_id;
                    
                    // Link candidate documents to employee
                    $documents_linked = linkCandidateDocuments($candidate['candidate_id'], $employee_id, $conn);
                    
                    // Update application status
                    $stmt = $conn->prepare("UPDATE job_applications SET status = 'Hired' WHERE application_id = ?");
                    $stmt->bind_param('i', $application_id);
                    $stmt->execute();
                    
                    $success_message = "🎉 Candidate hired! Employee ID: {$employee_id}. Documents linked: {$documents_linked}";
                } else {
                    $success_message = "❌ Error: Candidate not found!";
                }
                break;
        }
    }
}

$show_archived = isset($_GET['archived']) && $_GET['archived'] == '1';
$show_screened = isset($_GET['screened']) && $_GET['screened'] == '1';
$sort_by_ai = isset($_GET['sort']) && $_GET['sort'] == 'ai_score';
$filter_job = isset($_GET['job']) ? (int)$_GET['job'] : null;

// Build WHERE clause
$whereConditions = [];
if ($show_archived) {
    $whereConditions[] = "ja.status IN ('Hired', 'Rejected')";
} else if ($show_screened) {
    // Show only screened applicants (those with AI scores)
    $whereConditions[] = "ja.status IN ('Applied', 'Screening', 'Interview', 'Assessment', 'Reference Check', 'Onboarding', 'Offer', 'Offer Generated')";
    $whereConditions[] = "ja.assessment_scores IS NOT NULL AND ja.assessment_scores != ''";
} else {
    // Show only unscreened applicants (Applied status without AI scores)
    $whereConditions[] = "ja.status = 'Applied'";
    $whereConditions[] = "(ja.assessment_scores IS NULL OR ja.assessment_scores = '')";
}

// Add job filter if specified
if ($filter_job) {
    $whereConditions[] = "ja.job_opening_id = " . $filter_job;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

$applications_query = "SELECT ja.*, c.first_name, c.last_name, c.email, c.phone, c.current_position,
                       jo.title as job_title, jo.screening_level, d.department_name
                       FROM job_applications ja 
                       JOIN candidates c ON ja.candidate_id = c.candidate_id 
                       JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                       JOIN departments d ON jo.department_id = d.department_id
                       {$whereClause}";

// Sort by AI score if requested
if ($sort_by_ai) {
    $result = $conn->query($applications_query . " ORDER BY ja.application_date DESC");
    $applications = $result->fetch_all(MYSQLI_ASSOC);
    
    // Sort by AI score (extract from JSON)
    usort($applications, function($a, $b) {
        $scoreA = 0;
        $scoreB = 0;
        
        if (!empty($a['assessment_scores'])) {
            $dataA = json_decode($a['assessment_scores'], true);
            $scoreA = $dataA['overall_score'] ?? 0;
        }
        
        if (!empty($b['assessment_scores'])) {
            $dataB = json_decode($b['assessment_scores'], true);
            $scoreB = $dataB['overall_score'] ?? 0;
        }
        
        return $scoreB - $scoreA; // Descending order (highest first)
    });
} else {
    $result = $conn->query($applications_query . " ORDER BY ja.application_date DESC");
    $applications = $result->fetch_all(MYSQLI_ASSOC);
}

// Get list of job openings for filter
$jobsQuery = "SELECT DISTINCT jo.job_opening_id, jo.title, jo.screening_level, COUNT(ja.application_id) as app_count
              FROM job_openings jo
              LEFT JOIN job_applications ja ON jo.job_opening_id = ja.job_opening_id
              WHERE jo.status = 'Open'
              GROUP BY jo.job_opening_id, jo.title, jo.screening_level
              ORDER BY jo.title";
$jobsList = $conn->query($jobsQuery)->fetch_all(MYSQLI_ASSOC);

$stats = [
    'Applied' => count(array_filter($applications, function($a) { return $a['status'] == 'Applied'; })),
    'Screening' => count(array_filter($applications, function($a) { return $a['status'] == 'Screening'; })),
    'Interview' => count(array_filter($applications, function($a) { return $a['status'] == 'Interview'; })),
    'Assessment' => count(array_filter($applications, function($a) { return $a['status'] == 'Assessment'; })),
    'Reference Check' => count(array_filter($applications, function($a) { return $a['status'] == 'Reference Check'; })),
    'Onboarding' => count(array_filter($applications, function($a) { return $a['status'] == 'Onboarding'; })),
    'Offer' => count(array_filter($applications, function($a) { return $a['status'] == 'Offer'; })),
    'Offer Generated' => count(array_filter($applications, function($a) { return $a['status'] == 'Offer Generated'; })),
    'Hired' => count(array_filter($applications, function($a) { return $a['status'] == 'Hired'; })),
    'Rejected' => count(array_filter($applications, function($a) { return $a['status'] == 'Rejected'; }))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Applications - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .application-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(233, 30, 99, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .application-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.15);
        }
        
        .application-header {
            background: linear-gradient(135deg, #E91E63 0%, #F06292 100%);
            color: white;
            padding: 20px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .filter-toolbar {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* AI Screening Styles */
        .ai-screening-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
            animation: slideIn 0.5s ease-out;
        }
        
        .btn-gradient-primary {
            background: linear-gradient(135deg, #E91E63 0%, #F06292 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
        }
        
        .btn-gradient-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
            color: white;
        }
        
        .ai-screening-loading {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 30px;
            border: 2px dashed #667eea;
        }
        
        .ai-robot-animation {
            animation: bounce 2s infinite;
        }
        
        .ai-screening-results {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: 2px solid #667eea;
            animation: fadeIn 0.5s ease-out;
        }
        
        .ai-results-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
        }
        
        .ai-score-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 20px;
        }
        
        .ai-score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .ai-score-circle.score-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .ai-score-circle.score-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        .ai-score-circle.score-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }
        
        .ai-score-circle.score-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .ai-score-circle .score-value {
            font-size: 32px;
            font-weight: bold;
            color: white;
            line-height: 1;
        }
        
        .ai-score-circle .score-max {
            font-size: 14px;
            color: rgba(255,255,255,0.8);
        }
        
        .ai-category-score {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px;
        }
        
        .ai-summary {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #2196F3;
        }
        
        .ai-insights {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
        }
        
        .ai-insights.strengths {
            border-left: 4px solid #28a745;
        }
        
        .ai-insights.concerns {
            border-left: 4px solid #ffc107;
        }
        
        .ai-insights ul {
            padding-left: 20px;
        }
        
        .ai-insights li {
            margin-bottom: 8px;
            color: #495057;
        }
        
        .ai-interview-questions {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #ff9800;
        }
        
        .ai-interview-questions ol {
            padding-left: 20px;
            margin-bottom: 0;
        }
        
        .ai-interview-questions li {
            margin-bottom: 10px;
            color: #495057;
            font-weight: 500;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title">📋 Job Applications Management</h2>
                    <div>
                        <a href="ai_screening_levels.php" class="btn btn-info">
                            <i class="fas fa-sliders-h mr-1"></i>AI Screening Levels
                        </a>
                        <a href="?" class="btn btn-warning <?php echo !$show_archived && !$show_screened ? 'active' : ''; ?> ml-2">
                            <i class="fas fa-clock"></i> Pending (<?php echo count(array_filter($applications, function($a) { return $a['status'] == 'Applied' && empty($a['assessment_scores']); })); ?>)
                        </a>
                        <a href="?screened=1" class="btn btn-primary <?php echo $show_screened ? 'active' : ''; ?> ml-2">
                            <i class="fas fa-check-circle"></i> Screened (<?php echo count(array_filter($applications, function($a) { return !empty($a['assessment_scores']); })); ?>)
                        </a>
                        <a href="?archived=1" class="btn btn-secondary <?php echo $show_archived ? 'active' : ''; ?> ml-2">
                            <i class="fas fa-archive"></i> Archived
                        </a>
                    </div>
                </div>
                
                <!-- Job Filter -->
                <?php if (count($jobsList) > 0): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label><i class="fas fa-briefcase mr-2"></i><strong>Filter by Position:</strong></label>
                            <select class="form-control" onchange="window.location.href='?job=' + this.value + '<?php echo $show_archived ? '&archived=1' : ''; ?>'">
                                <option value="">All Positions (<?php echo count($applications); ?> applications)</option>
                                <?php foreach ($jobsList as $job): 
                                    $levelIcon = ['Easy' => '🟢', 'Moderate' => '🟡', 'Strict' => '🔴'][$job['screening_level']] ?? '⚪';
                                ?>
                                    <option value="<?php echo $job['job_opening_id']; ?>" <?php echo $filter_job == $job['job_opening_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($job['title']); ?> - <?php echo $job['app_count']; ?> apps (<?php echo $levelIcon; ?> <?php echo $job['screening_level']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Applications Grid -->
                <?php if (count($applications) > 0): ?>
                    <div class="row">
                        <?php foreach($applications as $app): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="application-card" style="cursor: pointer;" onclick="showApplicationDetails(<?php echo $app['application_id']; ?>)">
                                    <div class="application-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></h6>
                                                <small class="opacity-75"><?php echo htmlspecialchars($app['job_title']); ?></small>
                                            </div>
                                            <?php
                                            $status_colors = [
                                                'Applied' => 'warning',
                                                'Screening' => 'info', 
                                                'Interview' => 'primary',
                                                'Assessment' => 'secondary',
                                                'Reference Check' => 'warning',
                                                'Onboarding' => 'info',
                                                'Offer' => 'success',
                                                'Hired' => 'success',
                                                'Rejected' => 'danger'
                                            ];
                                            $color = $status_colors[$app['status']] ?? 'secondary';
                                            ?>
                                            <span class="status-badge bg-<?php echo $color; ?> text-white">
                                                <?php echo htmlspecialchars($app['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-2"><i class="fas fa-building mr-2 text-muted"></i><?php echo htmlspecialchars($app['department_name']); ?></p>
                                        <p class="mb-2"><i class="fas fa-calendar mr-2 text-muted"></i><?php echo date('M d, Y', strtotime($app['application_date'])); ?></p>
                                        <p class="mb-0"><i class="fas fa-clock mr-2 text-muted"></i><?php echo floor((time() - strtotime($app['application_date'])) / 86400); ?> days</p>
                                        
                                        <?php 
                                        $assessment_data = json_decode($app['assessment_scores'], true);
                                        $current_score = $assessment_data['overall_score'] ?? '';
                                        if ($current_score): 
                                            // Color-code based on realistic scoring
                                            if ($current_score >= 85) {
                                                $score_color = 'success'; // Green - Exceptional
                                                $score_label = '🌟 Exceptional';
                                            } elseif ($current_score >= 75) {
                                                $score_color = 'primary'; // Blue - Strong
                                                $score_label = '💪 Strong';
                                            } elseif ($current_score >= 65) {
                                                $score_color = 'info'; // Cyan - Good
                                                $score_label = '👍 Good';
                                            } elseif ($current_score >= 55) {
                                                $score_color = 'warning'; // Yellow - Acceptable
                                                $score_label = '✓ Acceptable';
                                            } else {
                                                $score_color = 'secondary'; // Gray - Review
                                                $score_label = '⚠ Review';
                                            }
                                        ?>
                                            <div class="mt-2">
                                                <span class="badge badge-<?php echo $score_color; ?>" style="font-size: 13px; padding: 6px 12px;">
                                                    <?php echo $score_label; ?>: <?php echo $current_score; ?>%
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5>No Applications Found</h5>
                            <p class="text-muted">
                                <?php echo $show_archived ? 'No archived applications available.' : 'No active applications at the moment.'; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Application Detail Modal -->
    <div class="modal fade" id="applicationModal" tabindex="-1">
        <div class="modal-dialog modal-xl" style="max-width: 95%; height: 90vh;">
            <div class="modal-content" style="height: 100%;">
                <div class="modal-header">
                    <h5 class="modal-title">Applicant Screening</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="modalContent" style="overflow-y: auto; max-height: calc(90vh - 120px);">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <style>
        .assessment-container {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            border: 2px solid #E91E63;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #E91E63 0%, #F06292 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: white;
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.3);
            transition: all 0.3s ease;
        }
        
        .score-number {
            font-size: 32px;
            font-weight: bold;
            line-height: 1;
        }
        
        .score-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .category {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .category-label {
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            color: #333;
        }
        
        .star-rating {
            display: flex;
            gap: 5px;
        }
        
        .star {
            font-size: 24px;
            cursor: pointer;
            transition: all 0.2s ease;
            opacity: 0.3;
        }
        
        .star:hover,
        .star.active {
            opacity: 1;
            transform: scale(1.1);
        }
        
        .star.active {
            filter: drop-shadow(0 0 5px #ffd700);
        }
        
        .document-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .document-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.2);
            border-color: #E91E63;
        }
        
        .document-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .document-type-resume { color: #28a745; }
        .document-type-cover { color: #17a2b8; }
        .document-type-pds { color: #ffc107; }
        
        .document-status {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
        }
        
        .screening-notes {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .extraction-progress-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #17a2b8;
        }
        
        .extraction-steps-mini {
            max-height: 200px;
            overflow-y: auto;
            background: white;
            padding: 10px;
            border-radius: 8px;
        }
        
        .extraction-step-mini {
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 5px;
            background: #f8f9fa;
            border-left: 3px solid #e9ecef;
        }
        
        .extraction-step-mini.success {
            border-left-color: #28a745;
            background: #f0fff4;
        }
        
        .extraction-step-mini.processing {
            border-left-color: #17a2b8;
            background: #f0f9ff;
        }
        
        .extraction-step-mini.error {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        /* PDS Data Container Styles */
        .pds-data-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 20px;
        }
        
        .pds-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
        }
        
        .pds-tabs {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 0 15px;
        }
        
        .pds-tabs .nav-link {
            color: #6c757d;
            border: none;
            padding: 15px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .pds-tabs .nav-link:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .pds-tabs .nav-link.active {
            color: #667eea;
            background: white;
            border-bottom-color: #667eea;
        }
        
        .pds-tabs .nav-link .badge {
            margin-left: 5px;
        }
        
        .pds-tab-content {
            padding: 25px;
            background: white;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
        }
        
        .info-card-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .info-table {
            width: 100%;
        }
        
        .info-table tr {
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-table tr:last-child {
            border-bottom: none;
        }
        
        .info-table td {
            padding: 10px 5px;
        }
        
        .info-table .label {
            font-weight: 600;
            color: #6c757d;
            width: 40%;
        }
        
        .info-table .value {
            color: #212529;
        }
        
        /* Timeline Styles */
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .timeline-marker {
            position: absolute;
            left: -32px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
            z-index: 1;
        }
        
        .timeline-marker.bg-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        .timeline-content {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 20px;
            border-left: 3px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .timeline-content:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
        }
        
        .timeline-header h6 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .timeline-body {
            margin-top: 10px;
        }
        
        /* References Styles */
        .references-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .reference-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 3px solid #20c997;
            transition: all 0.3s ease;
        }
        
        .reference-item:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .reference-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .reference-details h6 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .skills-content {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Auto-refresh page every 30 seconds to show new applications
        setInterval(function() {
            if (!$('#applicationModal').hasClass('show')) {
                location.reload();
            }
        }, 30000);
        function showApplicationDetails(applicationId) {
            // Find application data
            const applications = <?php echo json_encode($applications); ?>;
            const app = applications.find(a => a.application_id == applicationId);
            
            if (!app) return;
            
            const assessmentData = app.assessment_scores ? JSON.parse(app.assessment_scores) : {};
            const currentScore = assessmentData.overall_score || '';
            
            // Check if AI screening was already done
            const hasAIResults = assessmentData.ai_generated === true;
            
            const content = `
                <div class="row mb-3">
                    <div class="col-md-8">
                        <h6 class="mb-2">${app.first_name} ${app.last_name} - ${app.job_title}</h6>
                        <small class="text-muted">${app.email} | ${app.phone || 'No phone'} | ${app.department_name}</small>
                    </div>
                    <div class="col-md-4 text-right">
                        <span class="badge badge-secondary">${app.status}</span><br>
                        <small class="text-muted">${new Date(app.application_date).toLocaleDateString()}</small>
                    </div>
                </div>
                
                <!-- AI Screening Section -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="ai-screening-banner">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><i class="fas fa-robot mr-2"></i>AI-Powered Screening</h6>
                                    <small class="text-muted">Let AI analyze this candidate's qualifications instantly</small>
                                </div>
                                <button type="button" class="btn btn-gradient-primary" onclick="runAIScreening(${app.candidate_id}, ${app.job_opening_id}, ${app.application_id})">
                                    <i class="fas fa-magic mr-2"></i>${hasAIResults ? 'Re-run' : 'Run'} AI Screening
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- AI Results Container -->
                <div id="aiScreeningResults" class="mb-3" style="display: ${hasAIResults ? 'block' : 'none'};">
                    ${hasAIResults ? generateAIResultsHTML(assessmentData, app.notes) : ''}
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-search mr-2"></i>Manual Screening</h6>
                        <form method="POST" id="assessmentForm">
                            <input type="hidden" name="action" value="update_assessment">
                            <input type="hidden" name="application_id" value="${app.application_id}">
                            <input type="hidden" name="assessment_score" id="hiddenScore" value="${currentScore}">
                            
                            <div class="text-center mb-3">
                                <div class="score-circle" id="scoreCircle" style="width: 100px; height: 100px;">
                                    <span class="score-number" id="scoreNumber" style="font-size: 28px;">${currentScore || 0}</span>
                                    <small class="score-label">Overall</small>
                                </div>
                            </div>
                            
                            <div class="rating-categories">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small>📋 Qualifications</small>
                                    <div class="star-rating" data-category="qualifications">
                                        <span class="star" data-value="1" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="2" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="3" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="4" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="5" style="font-size: 18px;">⭐</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small>💼 Experience</small>
                                    <div class="star-rating" data-category="experience">
                                        <span class="star" data-value="1" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="2" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="3" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="4" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="5" style="font-size: 18px;">⭐</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small>🎯 Skills Match</small>
                                    <div class="star-rating" data-category="skills">
                                        <span class="star" data-value="1" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="2" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="3" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="4" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="5" style="font-size: 18px;">⭐</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small>💬 Communication</small>
                                    <div class="star-rating" data-category="communication">
                                        <span class="star" data-value="1" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="2" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="3" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="4" style="font-size: 18px;">⭐</span>
                                        <span class="star" data-value="5" style="font-size: 18px;">⭐</span>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-sm btn-block mt-3">
                                <i class="fas fa-save mr-1"></i>Save Assessment
                            </button>
                        </form>
                    </div>
                    
                    <div class="col-md-6">
                        <h6><i class="fas fa-file-alt mr-2"></i>Documents</h6>
                        <div id="documentPreviews" class="document-list">
                            <!-- Documents will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- PDS Extraction Status & Data Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div id="pdsExtractionSection">
                            <!-- PDS extraction status and data will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-end">
                    ${app.status === 'Applied' ? `
                        <form method="POST" style="display: inline;" class="mr-2">
                            <input type="hidden" name="action" value="auto_generate_offer">
                            <input type="hidden" name="application_id" value="${app.application_id}">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check mr-1"></i>Approve Application
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reject_candidate">
                            <input type="hidden" name="application_id" value="${app.application_id}">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this application?')">
                                <i class="fas fa-times mr-1"></i>Reject Application
                            </button>
                        </form>
                    ` : app.status === 'Screening' ? `
                        <form method="POST" style="display: inline;" class="mr-2">
                            <input type="hidden" name="action" value="approve_screening">
                            <input type="hidden" name="application_id" value="${app.application_id}">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check mr-1"></i>Move to Interview
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reject_candidate">
                            <input type="hidden" name="application_id" value="${app.application_id}">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this application?')">
                                <i class="fas fa-times mr-1"></i>Reject
                            </button>
                        </form>
                    ` : app.status === 'Offer' ? `
                        <form method="POST" style="display: inline;" class="mr-2">
                            <input type="hidden" name="action" value="hire_candidate">
                            <input type="hidden" name="application_id" value="${app.application_id}">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Hire this candidate? This will create an employee profile and link all documents.')">
                                <i class="fas fa-user-plus mr-1"></i>Hire Candidate
                            </button>
                        </form>
                    ` : `<span class="text-muted">Application is in ${app.status} stage</span>`}
                </div>
            `;
            
            document.getElementById('modalContent').innerHTML = content;
            $('#applicationModal').modal('show');
            
            // Load documents for this candidate
            loadCandidateDocuments(app.candidate_id);
            
            // Load PDS extraction status and data
            loadPDSExtractionData(app.candidate_id);
            
            // Initialize star rating system
            setTimeout(() => {
                initializeStarRating();
            }, 100);
        }
        
        function initializeStarRating() {
            const ratings = {
                qualifications: 0,
                experience: 0,
                skills: 0,
                communication: 0
            };
            
            // Handle star clicks
            document.querySelectorAll('.star').forEach(star => {
                star.addEventListener('click', function() {
                    const category = this.parentElement.dataset.category;
                    const value = parseInt(this.dataset.value);
                    
                    ratings[category] = value;
                    
                    // Update visual stars
                    const categoryStars = this.parentElement.querySelectorAll('.star');
                    categoryStars.forEach((s, index) => {
                        if (index < value) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                    
                    // Calculate overall score
                    updateOverallScore(ratings);
                });
                
                // Hover effects
                star.addEventListener('mouseenter', function() {
                    const value = parseInt(this.dataset.value);
                    const categoryStars = this.parentElement.querySelectorAll('.star');
                    categoryStars.forEach((s, index) => {
                        if (index < value) {
                            s.style.opacity = '1';
                        } else {
                            s.style.opacity = '0.3';
                        }
                    });
                });
                
                star.addEventListener('mouseleave', function() {
                    const category = this.parentElement.dataset.category;
                    const activeValue = ratings[category];
                    const categoryStars = this.parentElement.querySelectorAll('.star');
                    categoryStars.forEach((s, index) => {
                        if (index < activeValue) {
                            s.style.opacity = '1';
                        } else {
                            s.style.opacity = '0.3';
                        }
                    });
                });
            });
        }
        
        
        function updateOverallScore(ratings) {
            const total = Object.values(ratings).reduce((sum, rating) => sum + rating, 0);
            const average = total / Object.keys(ratings).length;
            const percentage = Math.round((average / 5) * 100);
            
            document.getElementById('scoreNumber').textContent = percentage;
            document.getElementById('hiddenScore').value = percentage;
            
            // Update circle color based on score
            const circle = document.getElementById('scoreCircle');
            if (percentage >= 80) {
                circle.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
            } else if (percentage >= 60) {
                circle.style.background = 'linear-gradient(135deg, #ffc107 0%, #e0a800 100%)';
            } else if (percentage >= 40) {
                circle.style.background = 'linear-gradient(135deg, #fd7e14 0%, #e55a4e 100%)';
            } else {
                circle.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
            }
        }
        
        function loadCandidateDocuments(candidateId) {
            // Real-time AJAX call to fetch documents
            $.ajax({
                url: 'get_candidate_documents.php',
                method: 'POST',
                data: { candidate_id: candidateId },
                dataType: 'json',
                success: function(documents) {
                    displayDocuments(documents);
                },
                error: function() {
                    // Fallback to sample data if AJAX fails
                    const documents = [
                        {
                            type: 'resume',
                            name: 'Resume',
                            filename: 'resume.pdf',
                            file_path: 'uploads/resumes/sample_resume.pdf',
                            size: '245 KB',
                            uploaded: '2024-01-15'
                        },
                        {
                            type: 'pds',
                            name: 'Personal Data Sheet',
                            filename: 'pds.pdf', 
                            file_path: 'uploads/pds/sample_pds.pdf',
                            size: '892 KB',
                            uploaded: '2024-01-15'
                        }
                    ];
                    displayDocuments(documents);
                }
            });
        }
        
        function displayDocuments(documents) {
            let documentsHtml = '';
            
            documents.forEach(doc => {
                const iconClass = {
                    'resume': 'fas fa-file-user',
                    'cover': 'fas fa-file-alt',
                    'pds': 'fas fa-file-contract'
                }[doc.type];
                
                documentsHtml += `
                    <div class="d-flex justify-content-between align-items-center p-2 mb-2 border rounded" style="cursor: pointer;" onclick="previewDocument('${doc.type}', '${doc.file_path || doc.filename}')">
                        <div class="d-flex align-items-center">
                            <i class="${iconClass} document-type-${doc.type} mr-2"></i>
                            <div>
                                <small class="font-weight-bold">${doc.name}</small><br>
                                <small class="text-muted">${doc.size || 'Unknown'}</small>
                            </div>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); previewDocument('${doc.type}', '${doc.file_path || doc.filename}')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            if (documentsHtml === '') {
                documentsHtml = `<small class="text-muted">No documents found</small>`;
            }
            
            document.getElementById('documentPreviews').innerHTML = documentsHtml;
        }
        
        function previewDocument(type, filePath) {
            const filename = filePath.split('/').pop();
            
            // Create smaller, faster preview modal
            const previewModal = `
                <div class="modal fade" id="documentPreviewModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title">
                                    <i class="fas fa-file-alt mr-2"></i>${filename}
                                </h6>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body p-2">
                                <div class="text-center">
                                    <iframe src="${filePath}" width="100%" height="400px" frameborder="0" class="border rounded"></iframe>
                                </div>
                            </div>
                            <div class="modal-footer p-2">
                                <button class="btn btn-sm btn-success" onclick="approveDocument('${filePath}')">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="flagDocument('${filePath}')">
                                    <i class="fas fa-flag mr-1"></i>Flag
                                </button>
                                <button class="btn btn-sm btn-primary" onclick="downloadDocument('${filePath}')">
                                    <i class="fas fa-download mr-1"></i>Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#documentPreviewModal').remove();
            $('body').append(previewModal);
            $('#documentPreviewModal').modal('show');
        }
        
        function downloadDocument(filePath) {
            window.open(filePath, '_blank');
        }
        
        function approveDocument(filePath) {
            alert('Document approved!');
            $('#documentPreviewModal').modal('hide');
        }
        
        function flagDocument(filePath) {
            alert('Document flagged for review!');
            $('#documentPreviewModal').modal('hide');
        }
        
        function loadPDSExtractionData(candidateId) {
            // Show loading state
            document.getElementById('pdsExtractionSection').innerHTML = `
                <div class="text-center py-3">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2">Loading PDS data...</p>
                </div>
            `;
            
            // AJAX call to fetch PDS extraction status and data
            $.ajax({
                url: 'get_pds_data.php',
                method: 'POST',
                data: { candidate_id: candidateId },
                dataType: 'json',
                success: function(response) {
                    if (response.extraction_pending) {
                        // Show real-time extraction progress
                        showExtractionProgress(candidateId);
                    } else if (response.data) {
                        // Show extracted data
                        displayPDSData(response.data);
                    } else {
                        document.getElementById('pdsExtractionSection').innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                No PDS data available yet.
                            </div>
                        `;
                    }
                },
                error: function() {
                    document.getElementById('pdsExtractionSection').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle mr-2"></i>
                            Error loading PDS data.
                        </div>
                    `;
                }
            });
        }
        
        function showExtractionProgress(candidateId) {
            const progressHtml = `
                <div class="extraction-progress-container">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-robot mr-2"></i>
                        <strong>AI Extraction in Progress</strong> - Please wait while we process the PDS file...
                    </div>
                    <div class="progress mb-3" style="height: 25px;">
                        <div id="pdsProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <div id="pdsExtractionSteps" class="extraction-steps-mini"></div>
                </div>
            `;
            
            document.getElementById('pdsExtractionSection').innerHTML = progressHtml;
            
            // Start SSE connection for real-time updates
            const eventSource = new EventSource('process_pds_extraction.php?candidate_id=' + candidateId);
            
            const progressBar = document.getElementById('pdsProgressBar');
            const stepsContainer = document.getElementById('pdsExtractionSteps');
            
            let currentStep = 0;
            const totalSteps = 6;
            
            eventSource.addEventListener('status', function(e) {
                const data = JSON.parse(e.data);
                currentStep = data.step;
                
                // Update progress bar
                const progress = (currentStep / totalSteps) * 100;
                progressBar.style.width = progress + '%';
                progressBar.textContent = Math.round(progress) + '%';
                
                // Add step to list
                const stepDiv = document.createElement('div');
                stepDiv.className = 'extraction-step-mini ' + data.status;
                stepDiv.innerHTML = `
                    <small>
                        ${data.status === 'success' ? '<i class="fas fa-check-circle text-success"></i>' : 
                          data.status === 'processing' ? '<i class="fas fa-spinner fa-spin text-primary"></i>' : 
                          '<i class="fas fa-times-circle text-danger"></i>'}
                        <strong>Step ${data.step}:</strong> ${data.message}
                    </small>
                `;
                stepsContainer.appendChild(stepDiv);
            });
            
            eventSource.addEventListener('complete', function(e) {
                const data = JSON.parse(e.data);
                
                // Update progress to 100%
                progressBar.style.width = '100%';
                progressBar.textContent = '100%';
                progressBar.classList.remove('progress-bar-animated');
                progressBar.classList.add('bg-success');
                
                // Show completion and reload data
                setTimeout(() => {
                    displayPDSData(data.data);
                }, 1000);
                
                eventSource.close();
            });
            
            eventSource.addEventListener('error', function(e) {
                const data = e.data ? JSON.parse(e.data) : {message: 'Connection error'};
                
                document.getElementById('pdsExtractionSection').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Extraction Failed:</strong> ${data.message}
                    </div>
                `;
                
                eventSource.close();
            });
        }
        
        function displayPDSData(data) {
            if (!data || !data.personal_info) {
                document.getElementById('pdsExtractionSection').innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle mr-2"></i>
                        No PDS data extracted yet.
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="pds-data-container">
                    <div class="pds-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-contract mr-2"></i>Personal Data Sheet (PDS)
                            <span class="badge badge-success ml-2">
                                <i class="fas fa-check-circle"></i> Extracted
                            </span>
                        </h5>
                    </div>
                    
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs pds-tabs" id="pdsTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="personal-tab" data-toggle="tab" href="#personal" role="tab">
                                <i class="fas fa-user"></i> Personal Info
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="contact-tab" data-toggle="tab" href="#contact" role="tab">
                                <i class="fas fa-address-book"></i> Contact & Address
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="ids-tab" data-toggle="tab" href="#ids" role="tab">
                                <i class="fas fa-id-card"></i> Government IDs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="education-tab" data-toggle="tab" href="#education" role="tab">
                                <i class="fas fa-graduation-cap"></i> Education
                                <span class="badge badge-primary">${(data.education || []).length}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="experience-tab" data-toggle="tab" href="#experience" role="tab">
                                <i class="fas fa-briefcase"></i> Work Experience
                                <span class="badge badge-info">${(data.work_experience || []).length}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="skills-tab" data-toggle="tab" href="#skills" role="tab">
                                <i class="fas fa-tools"></i> Skills & References
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content pds-tab-content" id="pdsTabContent">
                        <!-- Personal Info Tab -->
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="info-card-title"><i class="fas fa-user-circle mr-2"></i>Basic Information</h6>
                                        <table class="info-table">
                                            <tr>
                                                <td class="label">Full Name:</td>
                                                <td class="value">${data.personal_info.first_name || ''} ${data.personal_info.middle_name || ''} ${data.personal_info.surname || ''} ${data.personal_info.name_extension || ''}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Date of Birth:</td>
                                                <td class="value">${data.personal_info.date_of_birth || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Place of Birth:</td>
                                                <td class="value">${data.personal_info.place_of_birth || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Sex:</td>
                                                <td class="value">${data.personal_info.sex || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Civil Status:</td>
                                                <td class="value">${data.personal_info.civil_status || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="info-card-title"><i class="fas fa-heartbeat mr-2"></i>Physical Attributes</h6>
                                        <table class="info-table">
                                            <tr>
                                                <td class="label">Height:</td>
                                                <td class="value">${data.personal_info.height || 'N/A'} ${data.personal_info.height ? 'm' : ''}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Weight:</td>
                                                <td class="value">${data.personal_info.weight || 'N/A'} ${data.personal_info.weight ? 'kg' : ''}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Blood Type:</td>
                                                <td class="value">
                                                    ${data.personal_info.blood_type ? '<span class="badge badge-danger">' + data.personal_info.blood_type + '</span>' : 'N/A'}
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact & Address Tab -->
                        <div class="tab-pane fade" id="contact" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="info-card-title"><i class="fas fa-phone mr-2"></i>Contact Information</h6>
                                        <table class="info-table">
                                            <tr>
                                                <td class="label">Email:</td>
                                                <td class="value">
                                                    <a href="mailto:${data.contact_info.email || ''}">${data.contact_info.email || 'N/A'}</a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="label">Mobile:</td>
                                                <td class="value">${data.contact_info.mobile || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Telephone:</td>
                                                <td class="value">${data.contact_info.telephone || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="info-card-title"><i class="fas fa-map-marker-alt mr-2"></i>Residential Address</h6>
                                        <table class="info-table">
                                            <tr>
                                                <td class="label">Address:</td>
                                                <td class="value">${data.address.residential_address || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">City:</td>
                                                <td class="value">${data.address.residential_city || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Province:</td>
                                                <td class="value">${data.address.residential_province || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Zip Code:</td>
                                                <td class="value">${data.address.residential_zipcode || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Government IDs Tab -->
                        <div class="tab-pane fade" id="ids" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="info-card-title"><i class="fas fa-id-badge mr-2"></i>Government Identification Numbers</h6>
                                        <table class="info-table">
                                            <tr>
                                                <td class="label">GSIS ID:</td>
                                                <td class="value">${data.government_ids.gsis_id || '<span class="text-muted">Not provided</span>'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">Pag-IBIG ID:</td>
                                                <td class="value">${data.government_ids.pagibig_id || '<span class="text-muted">Not provided</span>'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">PhilHealth No:</td>
                                                <td class="value">${data.government_ids.philhealth_no || '<span class="text-muted">Not provided</span>'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">SSS No:</td>
                                                <td class="value">${data.government_ids.sss_no || '<span class="text-muted">Not provided</span>'}</td>
                                            </tr>
                                            <tr>
                                                <td class="label">TIN No:</td>
                                                <td class="value">${data.government_ids.tin_no || '<span class="text-muted">Not provided</span>'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>Note:</strong> These government IDs are required for employment processing and benefits enrollment.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Education Tab -->
                        <div class="tab-pane fade" id="education" role="tabpanel">
                            ${displayEducation(data.education || [])}
                        </div>
                        
                        <!-- Work Experience Tab -->
                        <div class="tab-pane fade" id="experience" role="tabpanel">
                            ${displayWorkExperience(data.work_experience || [])}
                        </div>
                        
                        <!-- Skills & References Tab -->
                        <div class="tab-pane fade" id="skills" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="info-card-title"><i class="fas fa-tools mr-2"></i>Special Skills & Hobbies</h6>
                                        <div class="skills-content">
                                            ${data.skills ? '<p>' + data.skills + '</p>' : '<p class="text-muted">No skills information provided</p>'}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6 class="info-card-title"><i class="fas fa-users mr-2"></i>Character References</h6>
                                        ${displayReferences(data.references || [])}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('pdsExtractionSection').innerHTML = html;
        }
        
        function displayEducation(education) {
            if (!education || education.length === 0) {
                return `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        No education information provided
                    </div>
                `;
            }
            
            let html = '<div class="timeline">';
            
            education.forEach((edu, index) => {
                html += `
                    <div class="timeline-item">
                        <div class="timeline-marker">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="mb-1">${edu.level || 'N/A'}</h6>
                                <small class="text-muted">${edu.year_graduated ? 'Graduated: ' + edu.year_graduated : 'In Progress'}</small>
                            </div>
                            <div class="timeline-body">
                                <p class="mb-1"><strong>${edu.school || 'N/A'}</strong></p>
                                ${edu.course ? '<p class="mb-0 text-muted">' + edu.course + '</p>' : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        }
        
        function displayWorkExperience(experience) {
            if (!experience || experience.length === 0) {
                return `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        No work experience provided - May be a fresh graduate
                    </div>
                `;
            }
            
            let html = '<div class="timeline">';
            
            experience.forEach((exp, index) => {
                const duration = calculateDuration(exp.from_date, exp.to_date);
                html += `
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="mb-1">${exp.position || 'N/A'}</h6>
                                <small class="text-muted">${exp.from_date || 'N/A'} - ${exp.to_date || 'Present'} ${duration ? '(' + duration + ')' : ''}</small>
                            </div>
                            <div class="timeline-body">
                                <p class="mb-1"><strong>${exp.company || 'N/A'}</strong></p>
                                ${exp.salary ? '<p class="mb-0"><span class="badge badge-success">₱' + exp.salary + '/month</span></p>' : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        }
        
        function displayReferences(references) {
            if (!references || references.length === 0) {
                return '<p class="text-muted">No references provided</p>';
            }
            
            let html = '<div class="references-list">';
            
            references.forEach((ref, index) => {
                html += `
                    <div class="reference-item">
                        <div class="d-flex align-items-start">
                            <div class="reference-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="reference-details">
                                <h6 class="mb-1">${ref.name || 'N/A'}</h6>
                                <p class="mb-0 text-muted small">${ref.address || 'N/A'}</p>
                                <p class="mb-0 text-muted small"><i class="fas fa-phone mr-1"></i>${ref.telephone || 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        }
        
        function calculateDuration(fromDate, toDate) {
            if (!fromDate) return '';
            
            const start = new Date(fromDate);
            const end = toDate && toDate !== 'Present' ? new Date(toDate) : new Date();
            
            const months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
            const years = Math.floor(months / 12);
            const remainingMonths = months % 12;
            
            if (years > 0 && remainingMonths > 0) {
                return `${years}y ${remainingMonths}m`;
            } else if (years > 0) {
                return `${years} year${years > 1 ? 's' : ''}`;
            } else if (remainingMonths > 0) {
                return `${remainingMonths} month${remainingMonths > 1 ? 's' : ''}`;
            }
            return '';
        }
        
        function loadExtractedResumeData(candidateId) {
            // Legacy function - redirects to PDS data
            loadPDSExtractionData(candidateId);
        }
        
        // AI Screening Function
        function runAIScreening(candidateId, jobOpeningId, applicationId) {
            const resultsContainer = document.getElementById('aiScreeningResults');
            
            // Show loading state
            resultsContainer.style.display = 'block';
            resultsContainer.innerHTML = `
                <div class="ai-screening-loading">
                    <div class="text-center py-4">
                        <div class="ai-robot-animation mb-3">
                            <i class="fas fa-robot fa-3x text-primary"></i>
                            <div class="spinner-border spinner-border-sm text-primary ml-2" role="status"></div>
                        </div>
                        <h6 class="mb-2">AI is analyzing the candidate...</h6>
                        <p class="text-muted small mb-0">This may take a few seconds</p>
                    </div>
                </div>
            `;
            
            // Call AI screening endpoint
            $.ajax({
                url: 'run_ai_screening.php',
                method: 'POST',
                data: {
                    candidate_id: candidateId,
                    job_opening_id: jobOpeningId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        resultsContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Error:</strong> ${response.error}
                            </div>
                        `;
                        return;
                    }
                    
                    if (response.success && response.data) {
                        displayAIScreeningResults(response.data);
                        
                        // Auto-populate manual scores
                        populateManualScores(response.data);
                        
                        // Reload page after 2 seconds to show in screened table
                        setTimeout(function() {
                            window.location.href = '?screened=1';
                        }, 2000);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Failed to connect to AI screening service.';
                    
                    // Try to get detailed error from response
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    } else if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.error) {
                                errorMessage = response.error;
                            }
                        } catch (e) {
                            // If not JSON, show the raw text (truncated)
                            errorMessage = xhr.responseText.substring(0, 500);
                        }
                    }
                    
                    // Format error message with line breaks
                    errorMessage = errorMessage.replace(/\n/g, '<br>');
                    
                    resultsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle mr-2"></i>
                            <strong>Error:</strong><br>${errorMessage}
                        </div>
                    `;
                }
            });
        }
        
        // Generate AI results HTML from saved data
        function generateAIResultsHTML(assessmentData, notes) {
            // Parse notes to extract AI insights
            const aiData = parseAINotesData(notes);
            
            if (!aiData) {
                return '';
            }
            
            // Determine recommendation color
            let recommendationClass = 'success';
            const score = assessmentData.overall_score || 0;
            if (score < 60) recommendationClass = 'danger';
            else if (score < 70) recommendationClass = 'warning';
            else if (score < 80) recommendationClass = 'info';
            
            return `
                <div class="ai-screening-results">
                    <div class="ai-results-header">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                <i class="fas fa-robot mr-2"></i>AI Screening Results (Saved)
                                <span class="badge badge-${recommendationClass} ml-2">${aiData.recommendation}</span>
                            </h6>
                            <small class="text-muted">Generated: ${assessmentData.generated_at || 'Previously'}</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="ai-score-card text-center">
                                <div class="ai-score-circle score-${recommendationClass}">
                                    <span class="score-value">${score}</span>
                                    <span class="score-max">/100</span>
                                </div>
                                <p class="mt-2 mb-0 small font-weight-bold">Overall Score</p>
                            </div>
                        </div>
                        
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <div class="ai-category-score">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small">📋 Qualifications</span>
                                            <span class="badge badge-primary">${assessmentData.qualifications_score || 0}%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: ${assessmentData.qualifications_score || 0}%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="ai-category-score">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small">💼 Experience</span>
                                            <span class="badge badge-info">${assessmentData.experience_score || 0}%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" style="width: ${assessmentData.experience_score || 0}%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="ai-category-score">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small">🎯 Skills Match</span>
                                            <span class="badge badge-success">${assessmentData.skills_score || 0}%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: ${assessmentData.skills_score || 0}%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="ai-category-score">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small">💬 Communication</span>
                                            <span class="badge badge-warning">${assessmentData.communication_score || 0}%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: ${assessmentData.communication_score || 0}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${aiData.summary ? `
                    <div class="ai-summary mt-3">
                        <h6 class="mb-2"><i class="fas fa-file-alt mr-2"></i>Summary</h6>
                        <p class="mb-0">${aiData.summary}</p>
                    </div>
                    ` : ''}
                    
                    <div class="row mt-3">
                        ${aiData.strengths.length > 0 ? `
                        <div class="col-md-6">
                            <div class="ai-insights strengths">
                                <h6 class="mb-2"><i class="fas fa-check-circle text-success mr-2"></i>Strengths</h6>
                                <ul class="mb-0">
                                    ${aiData.strengths.map(s => '<li>' + s + '</li>').join('')}
                                </ul>
                            </div>
                        </div>
                        ` : ''}
                        ${aiData.concerns.length > 0 ? `
                        <div class="col-md-6">
                            <div class="ai-insights concerns">
                                <h6 class="mb-2"><i class="fas fa-exclamation-triangle text-warning mr-2"></i>Concerns</h6>
                                <ul class="mb-0">
                                    ${aiData.concerns.map(c => '<li>' + c + '</li>').join('')}
                                </ul>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${aiData.questions.length > 0 ? `
                    <div class="ai-interview-questions mt-3">
                        <h6 class="mb-2"><i class="fas fa-question-circle mr-2"></i>Suggested Interview Questions</h6>
                        <ol class="mb-0">
                            ${aiData.questions.map(q => '<li>' + q + '</li>').join('')}
                        </ol>
                    </div>
                    ` : ''}
                </div>
            `;
        }
        
        // Parse AI data from notes field
        function parseAINotesData(notes) {
            if (!notes || !notes.includes('=== AI SCREENING RESULTS ===')) {
                return null;
            }
            
            const data = {
                recommendation: '',
                summary: '',
                strengths: [],
                concerns: [],
                questions: []
            };
            
            // Extract recommendation
            const recMatch = notes.match(/Recommendation:\s*(.+)/);
            if (recMatch) data.recommendation = recMatch[1].trim();
            
            // Extract summary
            const sumMatch = notes.match(/Summary:\s*(.+?)(?:\n\n|\nStrengths:)/s);
            if (sumMatch) data.summary = sumMatch[1].trim();
            
            // Extract strengths
            const strengthsMatch = notes.match(/Strengths:\n((?:•.+\n?)+)/);
            if (strengthsMatch) {
                data.strengths = strengthsMatch[1].split('\n')
                    .filter(s => s.trim().startsWith('•'))
                    .map(s => s.replace('•', '').trim());
            }
            
            // Extract concerns
            const concernsMatch = notes.match(/Concerns:\n((?:•.+\n?)+)/);
            if (concernsMatch) {
                data.concerns = concernsMatch[1].split('\n')
                    .filter(s => s.trim().startsWith('•'))
                    .map(s => s.replace('•', '').trim());
            }
            
            // Extract questions
            const questionsMatch = notes.match(/Suggested Interview Questions:\n((?:\d+\..+\n?)+)/);
            if (questionsMatch) {
                data.questions = questionsMatch[1].split('\n')
                    .filter(s => s.trim().match(/^\d+\./))
                    .map(s => s.replace(/^\d+\.\s*/, '').trim());
            }
            
            return data;
        }
        
        function displayAIScreeningResults(data) {
            const resultsContainer = document.getElementById('aiScreeningResults');
            
            // Determine recommendation color
            let recommendationClass = 'success';
            if (data.overall_score < 60) recommendationClass = 'danger';
            else if (data.overall_score < 70) recommendationClass = 'warning';
            else if (data.overall_score < 80) recommendationClass = 'info';
            
            let html = `
                <div class="ai-screening-results">
                    <div class="ai-results-header">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">
                                <i class="fas fa-robot mr-2"></i>AI Screening Results
                                <span class="badge badge-${recommendationClass} ml-2">${data.recommendation}</span>
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('aiScreeningResults').style.display='none'">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Overall Score -->
                        <div class="col-md-3">
                            <div class="ai-score-card text-center">
                                <div class="ai-score-circle score-${recommendationClass}">
                                    <span class="score-value">${data.overall_score}</span>
                                    <span class="score-max">/100</span>
                                </div>
                                <p class="mt-2 mb-0 small font-weight-bold">Overall Score</p>
                            </div>
                        </div>
                        
                        <!-- Category Scores -->
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <div class="ai-category-score">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small">📋 Qualifications</span>
                                            <span class="badge badge-primary">${data.qualifications_score}%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: ${data.qualifications_score}%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="ai-category-score">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small">💼 Experience</span>
                                            <span class="badge badge-info">${data.experience_score}%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" style="width: ${data.experience_score}%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="ai-category-score">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small">🎯 Skills Match</span>
                                            <span class="badge badge-success">${data.skills_score}%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: ${data.skills_score}%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="ai-category-score">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small">💬 Communication</span>
                                            <span class="badge badge-warning">${data.communication_score}%</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: ${data.communication_score}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Summary -->
                    <div class="ai-summary mt-3">
                        <h6 class="mb-2"><i class="fas fa-file-alt mr-2"></i>Summary</h6>
                        <p class="mb-0">${data.summary}</p>
                    </div>
                    
                    <!-- Strengths & Concerns -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="ai-insights strengths">
                                <h6 class="mb-2"><i class="fas fa-check-circle text-success mr-2"></i>Strengths</h6>
                                <ul class="mb-0">
                                    ${data.strengths.map(s => '<li>' + s + '</li>').join('')}
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ai-insights concerns">
                                <h6 class="mb-2"><i class="fas fa-exclamation-triangle text-warning mr-2"></i>Concerns</h6>
                                <ul class="mb-0">
                                    ${data.concerns.map(c => '<li>' + c + '</li>').join('')}
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Interview Questions -->
                    <div class="ai-interview-questions mt-3">
                        <h6 class="mb-2"><i class="fas fa-question-circle mr-2"></i>Suggested Interview Questions</h6>
                        <ol class="mb-0">
                            ${data.interview_questions.map(q => '<li>' + q + '</li>').join('')}
                        </ol>
                    </div>
                </div>
            `;
            
            resultsContainer.innerHTML = html;
        }
        
        function populateManualScores(data) {
            // Auto-populate the manual rating stars based on AI scores
            const scoreMapping = {
                qualifications: data.qualifications_score,
                experience: data.experience_score,
                skills: data.skills_score,
                communication: data.communication_score
            };
            
            Object.keys(scoreMapping).forEach(category => {
                const score = scoreMapping[category];
                const stars = Math.round((score / 100) * 5); // Convert to 5-star rating
                
                const categoryStars = document.querySelectorAll(`.star-rating[data-category="${category}"] .star`);
                categoryStars.forEach((star, index) => {
                    if (index < stars) {
                        star.classList.add('active');
                    }
                });
            });
            
            // Update overall score display
            document.getElementById('scoreNumber').textContent = data.overall_score;
            document.getElementById('hiddenScore').value = data.overall_score;
            
            // Update circle color
            const circle = document.getElementById('scoreCircle');
            if (data.overall_score >= 80) {
                circle.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
            } else if (data.overall_score >= 60) {
                circle.style.background = 'linear-gradient(135deg, #ffc107 0%, #e0a800 100%)';
            } else if (data.overall_score >= 40) {
                circle.style.background = 'linear-gradient(135deg, #fd7e14 0%, #e55a4e 100%)';
            } else {
                circle.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
            }
        }

    </script>
</body>
</html>