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

// Include database configuration
require_once 'config.php';

// Get the logged-in user's details
$username = $_SESSION['username'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$employee_id = 0;
$employee = null;
$error_message = '';

// Get employee_id from the employees table
if (!empty($username)) {
    try {
        $lookup_query = "SELECT employee_id, first_name, last_name, employee_code, job_title, department_id, email, username, date_hired
                        FROM employees 
                        WHERE username = ? OR email = ?
                        LIMIT 1";
        
        if ($lookup_stmt = $conn->prepare($lookup_query)) {
            $lookup_stmt->bind_param("ss", $username, $username);
            $lookup_stmt->execute();
            $lookup_result = $lookup_stmt->get_result();
            
            if ($lookup_row = $lookup_result->fetch_assoc()) {
                $employee_id = $lookup_row['employee_id'];
                $_SESSION['employee_id'] = $employee_id;
                $employee = $lookup_row;
            }
            $lookup_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error finding employee: " . $e->getMessage());
        $error_message = "Could not retrieve your employee information.";
    }
}

if ($employee_id == 0) {
    $error_message = "Could not find your employee record. Please contact HR.";
}

// Fetch performance reviews for this employee
$reviews_by_cycle = [];
$overall_performance = [];

if ($employee_id > 0) {
    try {
        // Get all competencies evaluated for this employee
        $review_sql = "
            SELECT 
                ec.cycle_id,
                pc.cycle_name,
                pc.start_date,
                pc.end_date,
                pc.status as cycle_status,
                ec.competency_id,
                c.name as competency_name,
                c.description as competency_description,
                ec.rating,
                ec.comments,
                ec.evaluated_at,
                ec.evaluator_id,
                CONCAT(e.first_name, ' ', e.last_name) as evaluator_name,
                e.job_title as evaluator_title
            FROM employee_competencies ec
            JOIN performance_review_cycles pc ON ec.cycle_id = pc.cycle_id
            JOIN competencies c ON ec.competency_id = c.competency_id
            LEFT JOIN employees e ON ec.evaluator_id = e.employee_id
            WHERE ec.employee_id = ?
            ORDER BY pc.cycle_id DESC, c.name ASC
        ";
        
        $review_stmt = $conn->prepare($review_sql);
        $review_stmt->bind_param("i", $employee_id);
        $review_stmt->execute();
        $review_result = $review_stmt->get_result();
        
        while ($row = $review_result->fetch_assoc()) {
            $cycle_id = $row['cycle_id'];
            
            if (!isset($reviews_by_cycle[$cycle_id])) {
                $reviews_by_cycle[$cycle_id] = [
                    'cycle_name' => $row['cycle_name'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'cycle_status' => $row['cycle_status'],
                    'competencies' => [],
                    'avg_rating' => 0,
                    'total_ratings' => 0,
                    'rated_count' => 0
                ];
            }
            
            $reviews_by_cycle[$cycle_id]['competencies'][] = [
                'competency_id' => $row['competency_id'],
                'name' => $row['competency_name'],
                'description' => $row['competency_description'],
                'rating' => $row['rating'],
                'comments' => $row['comments'],
                'evaluated_at' => $row['evaluated_at'],
                'evaluator_name' => $row['evaluator_name'],
                'evaluator_title' => $row['evaluator_title']
            ];
            
            if ($row['rating'] !== null) {
                $reviews_by_cycle[$cycle_id]['total_ratings'] += $row['rating'];
                $reviews_by_cycle[$cycle_id]['rated_count']++;
            }
        }
        $review_stmt->close();
        
        // Calculate average ratings for each cycle
        foreach ($reviews_by_cycle as $cycle_id => $data) {
            if ($data['rated_count'] > 0) {
                $reviews_by_cycle[$cycle_id]['avg_rating'] = $data['total_ratings'] / $data['rated_count'];
            }
        }
        
        // Calculate overall performance statistics
        if (!empty($reviews_by_cycle)) {
            $total_all_ratings = 0;
            $total_all_rated = 0;
            $performance_count = count($reviews_by_cycle);
            
            foreach ($reviews_by_cycle as $cycle_data) {
                $total_all_ratings += $cycle_data['total_ratings'];
                $total_all_rated += $cycle_data['rated_count'];
            }
            
            if ($total_all_rated > 0) {
                $overall_avg = $total_all_ratings / $total_all_rated;
                $overall_performance = [
                    'avg_rating' => $overall_avg,
                    'total_reviews' => $performance_count,
                    'total_competencies' => $total_all_rated
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error fetching employee reviews: " . $e->getMessage());
    }
}

// Function to get rating label
function getRatingLabel($rating) {
    $labels = [
        1 => 'Poor',
        2 => 'Below Average',
        3 => 'Average',
        4 => 'Good',
        5 => 'Excellent'
    ];
    return $labels[$rating] ?? 'N/A';
}

// Function to get rating color class
function getRatingColorClass($rating) {
    $colors = [
        1 => 'rating-poor',
        2 => 'rating-below-avg',
        3 => 'rating-avg',
        4 => 'rating-good',
        5 => 'rating-excellent'
    ];
    return $colors[$rating] ?? 'rating-none';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance Reviews - HRMS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #E91E63;
            --primary-light: #F06292;
            --primary-dark: #C2185B;
            --primary-pale: #FCE4EC;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            margin-left: 250px;
            padding: 30px 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 35px 30px;
            border-radius: 12px;
            margin-bottom: 35px;
            box-shadow: 0 4px 20px rgba(233, 30, 99, 0.2);
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            font-weight: 700;
            font-size: 32px;
        }
        
        .page-header p {
            margin: 0;
            opacity: 0.95;
            font-size: 16px;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--primary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .stat-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-subtext {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
        }
        
        .employee-info {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 35px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--info-color);
        }
        
        .employee-info h5 {
            margin-bottom: 20px;
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 16px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 0;
        }
        
        .info-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #999;
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 15px;
            font-weight: 500;
            color: #333;
        }
        
        .review-cycle-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .review-cycle-header {
            background: linear-gradient(135deg, var(--primary-pale) 0%, #fff0f6 100%);
            padding: 25px;
            border-bottom: 2px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .cycle-title {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .cycle-title h4 {
            margin: 0;
            color: var(--primary-dark);
            font-weight: 700;
        }
        
        .cycle-title .fa-icon {
            font-size: 24px;
            color: var(--primary-color);
        }
        
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 18px;
            letter-spacing: 2px;
        }
        
        .rating-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            min-width: 45px;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-open {
            background: #d4edda;
            color: #155724;
        }
        
        .status-completed {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-closed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .review-cycle-body {
            padding: 25px;
        }
        
        .cycle-dates {
            display: flex;
            gap: 30px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .date-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #666;
        }
        
        .date-item i {
            color: var(--primary-color);
            font-size: 16px;
        }
        
        .competencies-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .competencies-table thead th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 700;
            color: #333;
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .competencies-table tbody td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .competencies-table tbody tr:hover {
            background: var(--primary-pale);
            transition: background 0.2s ease;
        }
        
        .competency-name {
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }
        
        .competency-description {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
        
        .rating-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 12px;
            text-align: center;
            min-width: 120px;
        }
        
        .rating-poor {
            background: #f8d7da;
            color: #721c24;
        }
        
        .rating-below-avg {
            background: #fff3cd;
            color: #856404;
        }
        
        .rating-avg {
            background: #cce5ff;
            color: #004085;
        }
        
        .rating-good {
            background: #d4edda;
            color: #155724;
        }
        
        .rating-excellent {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .rating-none {
            background: #e9ecef;
            color: #666;
        }
        
        .comments-section {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            border-radius: 6px;
            margin-top: 8px;
            font-style: italic;
            color: #555;
            font-size: 13px;
        }
        
        .evaluator-info {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 30px;
            color: #999;
        }
        
        .no-data i {
            font-size: 60px;
            color: #ddd;
            display: block;
            margin-bottom: 20px;
        }
        
        .no-data h4 {
            color: #666;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .alert-custom {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-info {
            background: #cce5ff;
            border-left-color: #004085;
            color: #004085;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-left-color: #721c24;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn-custom {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(233, 30, 99, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .btn-secondary-custom {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary-custom:hover {
            background: #e0e0e0;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin-left: 0;
                padding: 15px;
            }
            
            .page-header {
                padding: 20px 15px;
            }
            
            .page-header h1 {
                font-size: 24px;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .review-cycle-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .cycle-dates {
                flex-direction: column;
                gap: 15px;
            }
            
            .competencies-table {
                font-size: 13px;
            }
            
            .competencies-table thead th,
            .competencies-table tbody td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    
    <?php include 'employee_sidebar.php'; ?>
    
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-award"></i> My Performance Reviews
            </h1>
            <p>View your performance evaluations, ratings, and feedback from your evaluation cycles</p>
        </div>
        
        <!-- Error Messages -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-custom alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Employee Information Card -->
        <?php if ($employee): ?>
        <div class="employee-info">
            <h5><i class="fas fa-user-circle"></i> Your Information</h5>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Employee Code</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['employee_code'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Current Position</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['job_title'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['email'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Overall Statistics -->
        <?php if (!empty($overall_performance)): ?>
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-label">Overall Rating</div>
                <div class="stat-value"><?php echo number_format($overall_performance['avg_rating'], 2); ?>/5.0</div>
                <div class="stat-subtext">Across all evaluations</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-label">Review Cycles</div>
                <div class="stat-value"><?php echo $overall_performance['total_reviews']; ?></div>
                <div class="stat-subtext">Evaluation periods completed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-list-check"></i>
                </div>
                <div class="stat-label">Competencies Evaluated</div>
                <div class="stat-value"><?php echo $overall_performance['total_competencies']; ?></div>
                <div class="stat-subtext">Total competencies assessed</div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Performance Reviews by Cycle -->
        <?php if (!empty($reviews_by_cycle)): ?>
            <div class="alert alert-custom alert-info">
                <i class="fas fa-info-circle"></i>
                You have <strong><?php echo count($reviews_by_cycle); ?></strong> performance evaluation cycle(s) on record.
            </div>
            
            <?php foreach ($reviews_by_cycle as $cycle_id => $cycle_data): ?>
            <div class="review-cycle-container">
                <!-- Cycle Header -->
                <div class="review-cycle-header">
                    <div class="cycle-title">
                        <i class="fas fa-clipboard-check fa-icon"></i>
                        <h4><?php echo htmlspecialchars($cycle_data['cycle_name']); ?></h4>
                    </div>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <?php if ($cycle_data['avg_rating'] > 0): ?>
                        <div class="rating-summary">
                            <div class="rating-stars">
                                <?php
                                $avg = $cycle_data['avg_rating'];
                                $full = floor($avg);
                                $half = ($avg - $full) >= 0.5;
                                $empty = 5 - $full - ($half ? 1 : 0);
                                
                                for ($i = 0; $i < $full; $i++) echo '<i class="fas fa-star"></i>';
                                if ($half) echo '<i class="fas fa-star-half-alt"></i>';
                                for ($i = 0; $i < $empty; $i++) echo '<i class="far fa-star"></i>';
                                ?>
                            </div>
                            <span class="rating-value"><?php echo number_format($avg, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <span class="status-badge status-<?php echo strtolower($cycle_data['cycle_status']); ?>">
                            <?php echo htmlspecialchars($cycle_data['cycle_status']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Cycle Body -->
                <div class="review-cycle-body">
                    <!-- Cycle Dates -->
                    <div class="cycle-dates">
                        <div class="date-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Start: <?php echo date('F d, Y', strtotime($cycle_data['start_date'])); ?></span>
                        </div>
                        <div class="date-item">
                            <i class="fas fa-calendar-check"></i>
                            <span>End: <?php echo date('F d, Y', strtotime($cycle_data['end_date'])); ?></span>
                        </div>
                        <div class="date-item">
                            <i class="fas fa-list"></i>
                            <span>Competencies: <?php echo count($cycle_data['competencies']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Competencies Table -->
                    <?php if (!empty($cycle_data['competencies'])): ?>
                    <table class="competencies-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Competency</th>
                                <th style="width: 15%;">Rating</th>
                                <th style="width: 35%;">Comments</th>
                                <th style="width: 25%;">Evaluator</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cycle_data['competencies'] as $comp): ?>
                            <tr>
                                <td>
                                    <div class="competency-name"><?php echo htmlspecialchars($comp['name']); ?></div>
                                    <?php if (!empty($comp['description'])): ?>
                                    <div class="competency-description"><?php echo htmlspecialchars(substr($comp['description'], 0, 80)); ?>...</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($comp['rating'] !== null): ?>
                                        <span class="rating-badge <?php echo getRatingColorClass($comp['rating']); ?>">
                                            <?php echo $comp['rating']; ?> - <?php echo getRatingLabel($comp['rating']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="rating-badge rating-none">Not Rated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($comp['comments'])): ?>
                                        <div class="comments-section">
                                            <i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($comp['comments']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic;">No comments provided</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($comp['evaluator_name'])): ?>
                                        <div style="font-weight: 500; color: #333;"><?php echo htmlspecialchars($comp['evaluator_name']); ?></div>
                                        <?php if (!empty($comp['evaluator_title'])): ?>
                                        <div style="font-size: 12px; color: #999;"><?php echo htmlspecialchars($comp['evaluator_title']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">System</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-inbox"></i>
                        <h4>No Competencies Evaluated</h4>
                        <p>Competencies for this cycle have not been evaluated yet.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
        <?php else: ?>
            <!-- No Reviews -->
            <div class="no-data">
                <i class="fas fa-chart-bar"></i>
                <h4>No Performance Reviews Found</h4>
                <p>You don't have any performance reviews yet. Once your manager completes your evaluation, it will appear here.</p>
                <div class="alert alert-custom alert-info" style="margin-top: 20px; justify-content: center;">
                    <i class="fas fa-info-circle"></i>
                    For more information, please contact your HR department or manager.
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="employee_index.php" class="btn btn-custom btn-secondary-custom">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
            <a href="my_profile.php" class="btn btn-custom btn-secondary-custom">
                <i class="fas fa-user"></i> View My Profile
            </a>
            <?php if (!empty($reviews_by_cycle)): ?>
            <a href="development_plans.php" class="btn btn-custom btn-primary-custom">
                <i class="fas fa-chart-line"></i> View Development Plans
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert-custom').fadeOut('slow');
        }, 5000);
    </script>
</body>
</html>
