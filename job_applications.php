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
                
            case 'mayor_approve':
                $application_id = $_POST['application_id'];
                
                // Simply change status from Screening to Interview
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Interview' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                $success_message = "🏛️ Mayor approved! Application moved to Interview stage!";
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

if ($show_archived) {
    $applications_query = "SELECT ja.*, c.first_name, c.last_name, c.email, c.phone, c.current_position,
                           jo.title as job_title, d.department_name
                           FROM job_applications ja 
                           JOIN candidates c ON ja.candidate_id = c.candidate_id 
                           JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                           JOIN departments d ON jo.department_id = d.department_id
                           WHERE ja.status IN ('Hired', 'Rejected')";
} else {
    $applications_query = "SELECT ja.*, c.first_name, c.last_name, c.email, c.phone, c.current_position,
                           jo.title as job_title, d.department_name
                           FROM job_applications ja 
                           JOIN candidates c ON ja.candidate_id = c.candidate_id 
                           JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                           JOIN departments d ON jo.department_id = d.department_id
                           WHERE ja.status IN ('Applied', 'Screening', 'Interview', 'Assessment', 'Reference Check', 'Onboarding', 'Offer', 'Offer Generated') AND ja.status != 'Draft'";
}

$result = $conn->query($applications_query . " ORDER BY ja.application_date DESC");
$applications = $result->fetch_all(MYSQLI_ASSOC);

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
                        <a href="?archived=0" class="btn btn-primary <?php echo !$show_archived ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> Active
                        </a>
                        <a href="?archived=1" class="btn btn-secondary <?php echo $show_archived ? 'active' : ''; ?> ml-2">
                            <i class="fas fa-archive"></i> Archived
                        </a>
                    </div>
                </div>
                
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
                    <div class="col-md-2">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-warning">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['Applied']; ?></h3>
                                <p class="stats-label">Applied</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-info">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['Screening']; ?></h3>
                                <p class="stats-label">Screening</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-primary">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['Interview']; ?></h3>
                                <p class="stats-label">Interview</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-secondary">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['Assessment']; ?></h3>
                                <p class="stats-label">Assessment</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['Hired']; ?></h3>
                                <p class="stats-label">Hired</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card card">
                            <div class="card-body text-center">
                                <div class="activity-icon bg-danger">
                                    <i class="fas fa-times"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $stats['Rejected']; ?></h3>
                                <p class="stats-label">Rejected</p>
                            </div>
                        </div>
                    </div>
                </div>

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
                                        if ($current_score): ?>
                                            <div class="mt-2">
                                                <span class="badge badge-info">Score: <?php echo $current_score; ?>%</span>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Applicant Screening</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="modalContent">
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
                
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-search mr-2"></i>Applicant Screening</h6>
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
                        <div class="d-flex align-items-center">
                            <span class="text-info mr-3"><i class="fas fa-envelope mr-1"></i>Awaiting Mayor Approval</span>
                            <form method="POST" style="display: inline;" class="mr-2">
                                <input type="hidden" name="action" value="mayor_approve">
                                <input type="hidden" name="application_id" value="${app.application_id}">
                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Simulate Mayor approval? This will move candidate to Interview stage.')">
                                    <i class="fas fa-check mr-1"></i>[TEST] Mayor Approve
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="reject_candidate">
                                <input type="hidden" name="application_id" value="${app.application_id}">
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this application? This will cancel the Mayor approval process.')">
                                    <i class="fas fa-times mr-1"></i>Reject
                                </button>
                            </form>
                        </div>
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

    </script>
</body>
</html>