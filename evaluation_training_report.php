<?php
// evaluation_training_report.php
// Comprehensive Evaluation and Training Report

session_start();

// Require authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'dp.php'; // database connection

$employee_id = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
$cycle_id = isset($_GET['cycle_id']) ? (int) $_GET['cycle_id'] : 0;

// If no cycle selected, try to get the most recent cycle
if ($cycle_id <= 0) {
    try {
        $row = $conn->query("SELECT cycle_id FROM performance_review_cycles ORDER BY start_date DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['cycle_id'])) {
            $cycle_id = (int)$row['cycle_id'];
        }
    } catch (Exception $e) {
        error_log('Error getting cycle: ' . $e->getMessage());
    }
}

// DEBUG: Log the employee_id being passed
error_log("evaluation_training_report.php: Received employee_id = " . $employee_id . ", cycle_id = " . $cycle_id);

// Get employee information
$employee = null;
try {
    // Check if employee exists in employee_profiles
    $checkEmpSql = "SELECT COUNT(*) as cnt FROM employee_profiles WHERE employee_id = ?";
    $checkStmt = $conn->prepare($checkEmpSql);
    $checkStmt->bindParam(1, $employee_id, PDO::PARAM_INT);
    $checkStmt->execute();
    $empExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("evaluation_training_report.php: Employee exists check = " . json_encode($empExists));
    
    if (!$empExists || $empExists['cnt'] == 0) {
        // Employee does not exist at all
        $employee = null;
        error_log("evaluation_training_report.php: Employee not found in employee_profiles");
    } else {
        // Employee exists, get details with JOIN to personal_information
        // Note: personal_information table has phone_number, not phone or email
        $empSql = "SELECT ep.*, pi.first_name, pi.last_name, pi.phone_number as phone, 
                   jr.title as job_title, jr.department as department_name
                   FROM employee_profiles ep
                   LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                   LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                   WHERE ep.employee_id = ?";
        
        $stmt = $conn->prepare($empSql);
        $stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
        $stmt->execute();
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If personal_information fields are NULL, generate from work_email
        if ($employee && (empty($employee['first_name']) || empty($employee['last_name']))) {
            // Generate name from work_email
            $email = $employee['work_email'] ?? '';
            $nameParts = explode('@', $email);
            $nameFromEmail = isset($nameParts[0]) ? str_replace(['.', '_'], ' ', $nameParts[0]) : '';
            $employee['first_name'] = ucwords(trim($nameFromEmail));
            $employee['last_name'] = '';
            error_log("evaluation_training_report.php: Generated name from email: " . $employee['first_name']);
        }
        
        error_log("evaluation_training_report.php: Employee data retrieved: " . json_encode($employee));
    }
} catch (PDOException $e) {
    error_log("Error getting employee: " . $e->getMessage());
}

// Get cycle information
$cycle = null;
if ($cycle_id > 0) {
    try {
        $cycleStmt = $conn->prepare("SELECT * FROM performance_review_cycles WHERE cycle_id = ?");
        $cycleStmt->bindParam(1, $cycle_id, PDO::PARAM_INT);
        $cycleStmt->execute();
        $cycle = $cycleStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting cycle: " . $e->getMessage());
    }
}

// Get competencies/evaluations for this employee and cycle
$evaluations = [];
if ($employee_id > 0 && $cycle_id > 0) {
    try {
        $evalSql = "SELECT ec.*, c.name as competency_name, c.description as competency_description
                    FROM employee_competencies ec
                    LEFT JOIN competencies c ON ec.competency_id = c.competency_id
                    WHERE ec.employee_id = ? AND ec.cycle_id = ?
                    ORDER BY c.name";
        
        $stmt = $conn->prepare($evalSql);
        $stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $cycle_id, PDO::PARAM_INT);
        $stmt->execute();
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting evaluations: " . $e->getMessage());
    }
}

// Calculate average rating
$avgRating = 0;
$ratedCount = 0;
$totalRating = 0;
foreach ($evaluations as $eval) {
    if ($eval['rating'] > 0) {
        $totalRating += $eval['rating'];
        $ratedCount++;
    }
}
if ($ratedCount > 0) {
    $avgRating = round($totalRating / $ratedCount, 2);
}

// Get training records
$enrollments = [];
$certifications = [];
$trainingStats = [
    'total_trainings' => 0,
    'completed_trainings' => 0,
    'in_progress_trainings' => 0,
    'average_score' => 0,
    'total_certifications' => 0,
    'active_certifications' => 0
];

// Check if training tables exist
$trainingTablesExist = true;
try {
    $conn->query("SELECT 1 FROM training_enrollments LIMIT 1");
} catch (PDOException $e) {
    $trainingTablesExist = false;
}

if ($trainingTablesExist && $employee_id > 0) {
    // Get training enrollments
    try {
        $enrollSql = "SELECT te.*, tc.course_name, tc.category as course_category, tc.duration,
                      ts.session_name, ts.start_date, ts.end_date, ts.location,
                      CONCAT(t.first_name, ' ', t.last_name) as trainer_name
                      FROM training_enrollments te
                      LEFT JOIN training_sessions ts ON te.session_id = ts.session_id
                      LEFT JOIN training_courses tc ON ts.course_id = tc.course_id
                      LEFT JOIN trainers t ON ts.trainer_id = t.trainer_id
                      WHERE te.employee_id = ?
                      ORDER BY ts.start_date DESC";
        
        $stmt = $conn->prepare($enrollSql);
        $stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
        $stmt->execute();
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate training stats
        $trainingStats['total_trainings'] = count($enrollments);
        $trainingStats['completed_trainings'] = count(array_filter($enrollments, function($e) {
            return $e['status'] === 'Completed';
        }));
        $trainingStats['in_progress_trainings'] = count(array_filter($enrollments, function($e) {
            return $e['status'] === 'Enrolled';
        }));
        
        // Calculate average score
        $scores = array_filter(array_map(function($e) { return $e['score']; }, $enrollments));
        $trainingStats['average_score'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
        
    } catch (PDOException $e) {
        error_log("Error getting enrollments: " . $e->getMessage());
    }
    
    // Get certifications - without notes column
    try {
        $certSql = "SELECT 
            certification_id,
            employee_id,
            certification_name,
            issuing_organization,
            certification_number,
            category,
            proficiency_level,
            assessment_score,
            issue_date,
            expiry_date,
            status,
            training_hours,
            description
        FROM certifications WHERE employee_id = ? ORDER BY issue_date DESC";
        $stmt = $conn->prepare($certSql);
        $stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
        $stmt->execute();
        $certifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate certification stats
        $trainingStats['total_certifications'] = count($certifications);
        $trainingStats['active_certifications'] = count(array_filter($certifications, function($c) {
            return $c['status'] === 'Active';
        }));
        
    } catch (PDOException $e) {
        error_log("Error getting certifications: " . $e->getMessage());
    }
}

// Helper function to get rating description
function getRatingDesc($rating) {
    $descriptions = [
        1 => 'Strongly Disagree',
        2 => 'Disagree',
        3 => 'Neutral',
        4 => 'Agree',
        5 => 'Strongly Agree'
    ];
    return $descriptions[$rating] ?? 'Not Rated';
}

// Helper function to get rating color
function getRatingColor($rating) {
    $colors = [
        1 => '#dc3545', // red
        2 => '#ffc107', // yellow
        3 => '#17a2b8', // cyan
        4 => '#28a745', // green
        5 => '#E91E63'  // pink
    ];
    return $colors[$rating] ?? '#6c757d';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Evaluation & Training Report</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #E91E63;
            --primary-light: #F06292;
            --primary-dark: #C2185B;
            --primary-pale: #FCE4EC;
        }
        
        body {
            background: #f5f5f5;
            padding: 20px;
        }
        
        .report-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .report-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .report-header h1 {
            margin: 0 0 10px 0;
        }
        
        .report-header .subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .employee-info {
            padding: 25px;
            background: var(--primary-pale);
            border-bottom: 2px solid var(--primary-color);
        }
        
        .employee-info h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .info-item label {
            display: block;
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .info-item value {
            display: block;
            color: #333;
            font-weight: 600;
        }
        
        .section {
            padding: 25px;
            border-bottom: 1px solid #eee;
        }
        
        .section-title {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-pale);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-card .label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .rating-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .eval-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .eval-table th {
            background: var(--primary-pale);
            color: var(--primary-dark);
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .eval-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .eval-table tr:hover {
            background: #f8f9fa;
        }
        
        .training-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .training-table th {
            background: #e3f2fd;
            color: #1976d2;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .training-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-enrolled {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-active {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }
        
        .overall-rating {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            border-radius: 15px;
            margin: 20px 0;
        }
        
        .overall-rating .score {
            font-size: 4rem;
            font-weight: bold;
        }
        
        .overall-rating .description {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 25px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        }
        
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.5);
        }
        
        @media print {
            body { padding: 0; }
            .report-container { box-shadow: none; }
            .print-btn { display: none; }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Print Report
    </button>
    
    <div class="report-container">
        <div class="report-header">
            <h1><i class="fas fa-clipboard-check"></i> Employee Evaluation & Training Report</h1>
            <div class="subtitle">
                <?php echo $cycle ? htmlspecialchars($cycle['cycle_name']) : 'Performance Review Report'; ?>
            </div>
        </div>
        
        <?php if ($employee): ?>
        <div class="employee-info">
            <h3><i class="fas fa-user"></i> Employee Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Employee Number</label>
                    <value><?php echo htmlspecialchars($employee['employee_number'] ?? 'N/A'); ?></value>
                </div>
                <div class="info-item">
                    <label>Full Name</label>
                    <value><?php 
                        if (isset($employee['first_name'])) {
                            echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']);
                        } else {
                            // Generate from email
                            $email = $employee['work_email'] ?? '';
                            $name = str_replace(['@municipality.gov.ph', '.', '_'], ' ', $email);
                            echo htmlspecialchars(trim($name));
                        }
                    ?></value>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <value><?php echo htmlspecialchars($employee['email'] ?? $employee['work_email'] ?? 'N/A'); ?></value>
                </div>
                <div class="info-item">
                    <label>Job Title</label>
                    <value><?php echo htmlspecialchars($employee['job_title'] ?? 'N/A'); ?></value>
                </div>
                <div class="info-item">
                    <label>Department</label>
                    <value><?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?></value>
                </div>
                <div class="info-item">
                    <label>Employment Status</label>
                    <value><?php echo htmlspecialchars($employee['employment_status'] ?? 'N/A'); ?></value>
                </div>
            </div>
        </div>
        
        <?php if ($cycle): ?>
        <div class="section">
            <h4 class="section-title"><i class="fas fa-calendar"></i> Review Period</h4>
            <p>
                <strong>Cycle:</strong> <?php echo htmlspecialchars($cycle['cycle_name']); ?><br>
                <strong>Period:</strong> 
                <?php 
                    $start = new DateTime($cycle['start_date'] ?? 'now');
                    $end = new DateTime($cycle['end_date'] ?? 'now');
                    echo $start->format('F d, Y') . ' - ' . $end->format('F d, Y');
                ?>
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Performance Evaluation Section -->
        <div class="section">
            <h4 class="section-title"><i class="fas fa-chart-line"></i> Performance Evaluation</h4>
            
            <?php if (count($evaluations) > 0): ?>
            <div class="overall-rating">
                <div class="score"><?php echo $avgRating; ?> / 5</div>
                <div class="description">Overall Rating: <?php echo getRatingDesc(round($avgRating)); ?></div>
            </div>
            
            <table class="eval-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Competency</th>
                        <th style="width: 15%;">Rating</th>
                        <th style="width: 55%;">Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluations as $eval): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($eval['competency_name'] ?? 'N/A'); ?></strong>
                            <?php if ($eval['competency_description']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($eval['competency_description'], 0, 100)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($eval['rating'] > 0): ?>
                            <span class="rating-badge" style="background-color: <?php echo getRatingColor($eval['rating']); ?>;">
                                <?php echo $eval['rating']; ?> - <?php echo getRatingDesc($eval['rating']); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">Not Rated</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($eval['comments'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-clipboard-list"></i>
                <p>No competencies assigned for evaluation.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Training Records Section -->
        <div class="section">
            <h4 class="section-title"><i class="fas fa-graduation-cap"></i> Training Records</h4>
            
            <?php if ($trainingTablesExist): ?>
            <!-- Training Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?php echo $trainingStats['total_trainings']; ?></div>
                    <div class="label">Total Trainings</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $trainingStats['completed_trainings']; ?></div>
                    <div class="label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $trainingStats['in_progress_trainings']; ?></div>
                    <div class="label">In Progress</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $trainingStats['average_score']; ?>%</div>
                    <div class="label">Avg Score</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $trainingStats['total_certifications']; ?></div>
                    <div class="label">Certifications</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo $trainingStats['active_certifications']; ?></div>
                    <div class="label">Active Certs</div>
                </div>
            </div>
            
            <!-- Training Enrollments -->
            <?php if (count($enrollments) > 0): ?>
            <h5 style="margin-top: 20px;"><i class="fas fa-book"></i> Training Enrollments</h5>
            <table class="training-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Session</th>
                        <th>Trainer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $enroll): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($enroll['course_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($enroll['session_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($enroll['trainer_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                                if ($enroll['start_date']) {
                                    $start = new DateTime($enroll['start_date']);
                                    echo $start->format('M d, Y');
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </td>
                        <td>
                            <?php 
                                $statusClass = '';
                                $status = $enroll['status'] ?? 'Enrolled';
                                if ($status === 'Completed') $statusClass = 'status-completed';
                                elseif ($status === 'Enrolled') $statusClass = 'status-enrolled';
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </td>
                        <td><?php echo $enroll['score'] ? $enroll['score'] . '%' : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-book"></i>
                <p>No training enrollments found.</p>
            </div>
            <?php endif; ?>
            
            <!-- Certifications -->
            <?php if (count($certifications) > 0): ?>
            <h5 style="margin-top: 30px;"><i class="fas fa-certificate"></i> Certifications</h5>
            <table class="training-table">
                <thead>
                    <tr>
                        <th>Certification</th>
                        <th>Organization</th>
                        <th>Issue Date</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certifications as $cert): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cert['certification_name']); ?></td>
                        <td><?php echo htmlspecialchars($cert['issuing_organization'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                                if ($cert['issue_date']) {
                                    $issue = new DateTime($cert['issue_date']);
                                    echo $issue->format('M d, Y');
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </td>
                        <td>
                            <?php 
                                if ($cert['expiry_date']) {
                                    $expiry = new DateTime($cert['expiry_date']);
                                    echo $expiry->format('M d, Y');
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </td>
                        <td>
                            <?php 
                                $certStatusClass = '';
                                $certStatus = $cert['status'] ?? 'Active';
                                if ($certStatus === 'Active') $certStatusClass = 'status-active';
                                elseif ($certStatus === 'Expired') $certStatusClass = 'status-expired';
                            ?>
                            <span class="status-badge <?php echo $certStatusClass; ?>"><?php echo htmlspecialchars($certStatus); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data" style="margin-top: 20px;">
                <i class="fas fa-certificate"></i>
                <p>No certifications found.</p>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-database"></i>
                <p>Training tables not set up yet. Please run the setup script.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; padding: 20px; color: #666; background: #f8f9fa;">
            <p><small>Generated on <?php echo date('F d, Y \a\t h:i A'); ?></small></p>
            <p><small>HR Management System - Norzagaray Municipal Government</small></p>
        </div>
        
        <?php else: ?>
        <div class="no-data">
            <i class="fas fa-user"></i>
            <p>Employee not found.</p>
            <!-- Debug info - remove after testing -->
            <div style="text-align: left; background: #f0f0f0; padding: 15px; margin: 20px; border-radius: 5px;">
                <h4>Debug Information:</h4>
                <p><strong>Employee ID received:</strong> <?php echo $employee_id; ?></p>
                <p><strong>Cycle ID received:</strong> <?php echo $cycle_id; ?></p>
                <p><strong>Personal Info Exists:</strong> <?php echo isset($personalInfoExists) && $personalInfoExists ? 'Yes' : 'No'; ?></p>
                <p><strong>Personal Info Has Data:</strong> <?php echo isset($personalInfoHasData) && $personalInfoHasData ? 'Yes' : 'No'; ?></p>
                <p><strong>Employee Exists Check Result:</strong> <?php echo isset($empExists) ? json_encode($empExists) : 'Not executed'; ?></p>
                <p><strong>Employee Data Retrieved:</strong> <?php echo isset($employee) ? json_encode($employee) : 'NULL - query returned no results'; ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
