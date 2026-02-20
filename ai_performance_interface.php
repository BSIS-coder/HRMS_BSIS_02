<?php
// AI Performance Management Interface
session_start();

// Check authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Check authorization - HR and Admin only
$user_role = $_SESSION['role'] ?? 'user';
if (!in_array($user_role, ['admin', 'hr'])) {
    header("Location: unauthorized.php");
    exit;
}

require_once 'dp.php';
require_once 'ai_performance_management.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $employee_id = $_POST['employee_id'] ?? null;
    
    try {
        switch ($action) {
            case 'get_insights':
                if (!$employee_id) {
                    echo json_encode(['error' => 'Employee ID required']);
                    exit;
                }
                $result = generatePerformanceInsights($employee_id);
                echo json_encode($result);
                exit;
                
            case 'get_feedback_suggestions':
                if (!$employee_id) {
                    echo json_encode(['error' => 'Employee ID required']);
                    exit;
                }
                $review_type = $_POST['review_type'] ?? 'general';
                $result = generateReviewFeedback($employee_id, $review_type);
                echo json_encode($result);
                exit;
                
            case 'get_performance_trend':
                if (!$employee_id) {
                    echo json_encode(['error' => 'Employee ID required']);
                    exit;
                }
                $result = predictPerformanceTrend($employee_id);
                echo json_encode($result);
                exit;
                
            case 'get_competency_gaps':
                if (!$employee_id) {
                    echo json_encode(['error' => 'Employee ID required']);
                    exit;
                }
                $result = analyzeCompetencyGaps($employee_id);
                echo json_encode($result);
                exit;
                
            case 'get_development_plan':
                if (!$employee_id) {
                    echo json_encode(['error' => 'Employee ID required']);
                    exit;
                }
                $result = generateDevelopmentRecommendations($employee_id);
                echo json_encode($result);
                exit;
                
            default:
                echo json_encode(['error' => 'Unknown action']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Get list of employees
$stmt = $conn->prepare("
    SELECT ep.employee_id, pi.first_name, pi.last_name, jr.title, d.department_name
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN departments d ON jr.department = d.department_name
    ORDER BY pi.first_name, pi.last_name
");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Performance Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --primary-color: #E91E63;
            --primary-dark: #C2185B;
            --primary-light: #F06292;
            --light-bg: #FCE4EC;
        }

        body {
            background: var(--light-bg);
        }

        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }

        .main-content {
            background: var(--light-bg);
            padding: 20px;
        }

        .ai-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .ai-card:hover {
            box-shadow: 0 4px 20px rgba(233, 30, 99, 0.2);
            transform: translateY(-2px);
        }

        .insight-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .strength-item {
            background: #d4edda;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
        }

        .improvement-item {
            background: #fff3cd;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #ffc107;
        }

        .gap-item {
            background: #f8d7da;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #dc3545;
        }

        .trend-improving {
            color: #28a745;
            font-weight: 600;
        }

        .trend-declining {
            color: #dc3545;
            font-weight: 600;
        }

        .trend-stable {
            color: #ffc107;
            font-weight: 600;
        }

        .ai-badge {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .btn-ai {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            transition: all 0.3s;
        }

        .btn-ai:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }

        .nav-tabs .nav-link.active {
            border-color: var(--primary-color);
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
        }

        .nav-tabs .nav-link {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-10 main-content">
                <div class="d-flex align-items-center mb-4">
                    <h2 class="section-title mb-0 flex-grow-1">
                        <i class="fas fa-brain"></i> AI Performance Management
                    </h2>
                    <span class="ai-badge">
                        <i class="fas fa-sparkles"></i> AI Powered
                    </span>
                </div>

                <!-- Employee Selection -->
                <div class="ai-card">
                    <h5 class="mb-3">
                        <i class="fas fa-user-select"></i> Select Employee
                    </h5>
                    <div class="row">
                        <div class="col-md-8">
                            <select id="employeeSelect" class="form-control form-control-lg">
                                <option value="">-- Select an Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>">
                                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' - ' . $emp['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button id="loadDataBtn" class="btn btn-ai btn-lg w-100" disabled>
                                <i class="fas fa-search"></i> Load Analysis
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-4" id="analysisTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="insights-tab" data-toggle="tab" href="#insights" role="tab">
                            <i class="fas fa-lightbulb"></i> Insights
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="feedback-tab" data-toggle="tab" href="#feedback" role="tab">
                            <i class="fas fa-comment-dots"></i> Feedback Suggestions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="trend-tab" data-toggle="tab" href="#trend" role="tab">
                            <i class="fas fa-chart-line"></i> Performance Trend
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="gaps-tab" data-toggle="tab" href="#gaps" role="tab">
                            <i class="fas fa-target"></i> Competency Gaps
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="development-tab" data-toggle="tab" href="#development" role="tab">
                            <i class="fas fa-graduation-cap"></i> Development Plan
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="analysisTabContent">
                    <!-- Insights Tab -->
                    <div class="tab-pane fade show active" id="insights" role="tabpanel">
                        <div class="loading-spinner" id="insightsLoading">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Generating AI insights...</p>
                        </div>
                        <div id="insightsContent"></div>
                    </div>

                    <!-- Feedback Tab -->
                    <div class="tab-pane fade" id="feedback" role="tabpanel">
                        <div class="ai-card">
                            <label class="form-label">Review Type</label>
                            <select id="reviewType" class="form-control mb-3">
                                <option value="general">General Review</option>
                                <option value="mid-year">Mid-Year Review</option>
                                <option value="annual">Annual Review</option>
                                <option value="promotion">Promotion Review</option>
                            </select>
                            <button class="btn btn-ai" id="generateFeedbackBtn" disabled>
                                <i class="fas fa-magic"></i> Generate Feedback
                            </button>
                        </div>
                        <div class="loading-spinner" id="feedbackLoading">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Generating feedback suggestions...</p>
                        </div>
                        <div id="feedbackContent"></div>
                    </div>

                    <!-- Trend Tab -->
                    <div class="tab-pane fade" id="trend" role="tabpanel">
                        <div class="loading-spinner" id="trendLoading">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Analyzing performance trend...</p>
                        </div>
                        <div id="trendContent"></div>
                    </div>

                    <!-- Gaps Tab -->
                    <div class="tab-pane fade" id="gaps" role="tabpanel">
                        <div class="loading-spinner" id="gapsLoading">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Analyzing competency gaps...</p>
                        </div>
                        <div id="gapsContent"></div>
                    </div>

                    <!-- Development Tab -->
                    <div class="tab-pane fade" id="development" role="tabpanel">
                        <div class="loading-spinner" id="developmentLoading">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Creating development plan...</p>
                        </div>
                        <div id="developmentContent"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        const API_BASE = 'ai_performance_interface.php';
        let selectedEmployeeId = null;

        // Employee selection handler
        $('#employeeSelect').on('change', function() {
            selectedEmployeeId = $(this).val();
            $('#loadDataBtn').prop('disabled', !selectedEmployeeId);
            if (selectedEmployeeId) {
                loadAllAnalyses();
            }
        });

        // Load all analyses
        function loadAllAnalyses() {
            if (!selectedEmployeeId) return;
            
            loadInsights();
            loadTrend();
            loadGaps();
            loadDevelopmentPlan();
        }

        // Load AI Insights
        function loadInsights() {
            $('#insightsLoading').show();
            $('#insightsContent').empty();
            
            $.ajax({
                url: API_BASE,
                method: 'POST',
                data: {
                    action: 'get_insights',
                    employee_id: selectedEmployeeId
                },
                dataType: 'json',
                success: function(data) {
                    $('#insightsLoading').hide();
                    displayInsights(data);
                },
                error: function(err) {
                    $('#insightsLoading').hide();
                    $('#insightsContent').html('<div class="alert alert-danger">Error loading insights</div>');
                }
            });
        }

        function displayInsights(data) {
            if (data.error) {
                $('#insightsContent').html(`<div class="alert alert-danger">${data.error}</div>`);
                return;
            }

            let html = '<div class="ai-card"><h5><i class="fas fa-chart-pie"></i> Overall Assessment</h5>';
            html += `<p>${data.assessment || 'Assessment data'}</p></div>`;

            if (data.strengths) {
                html += '<div class="ai-card"><h5><i class="fas fa-star"></i> Key Strengths</h5>';
                data.strengths.forEach(s => {
                    html += `<div class="strength-item">${s}</div>`;
                });
                html += '</div>';
            }

            if (data.areas_for_improvement) {
                html += '<div class="ai-card"><h5><i class="fas fa-exclamation-triangle"></i> Areas for Improvement</h5>';
                data.areas_for_improvement.forEach(a => {
                    html += `<div class="improvement-item">${a}</div>`;
                });
                html += '</div>';
            }

            if (data.recommendations) {
                html += '<div class="ai-card"><h5><i class="fas fa-thumbs-up"></i> Recommendations</h5>';
                data.recommendations.forEach(r => {
                    html += `<p><i class="fas fa-check"></i> ${r}</p>`;
                });
                html += '</div>';
            }

            if (data.career_suggestions) {
                html += '<div class="ai-card"><h5><i class="fas fa-rocket"></i> Career Path Suggestions</h5>';
                data.career_suggestions.forEach(c => {
                    html += `<p><i class="fas fa-arrow-right"></i> ${c}</p>`;
                });
                html += '</div>';
            }

            $('#insightsContent').html(html);
        }

        // Generate feedback suggestions
        $('#generateFeedbackBtn').on('click', function() {
            if (!selectedEmployeeId) return;
            
            $('#feedbackLoading').show();
            $('#feedbackContent').empty();
            
            $.ajax({
                url: API_BASE,
                method: 'POST',
                data: {
                    action: 'get_feedback_suggestions',
                    employee_id: selectedEmployeeId,
                    review_type: $('#reviewType').val()
                },
                dataType: 'json',
                success: function(data) {
                    $('#feedbackLoading').hide();
                    displayFeedback(data);
                },
                error: function() {
                    $('#feedbackLoading').hide();
                    $('#feedbackContent').html('<div class="alert alert-danger">Error generating feedback</div>');
                }
            });
        });

        function displayFeedback(data) {
            if (data.error) {
                $('#feedbackContent').html(`<div class="alert alert-danger">${data.error}</div>`);
                return;
            }

            let html = '<div class="ai-card">';
            html += `<h5>Performance Feedback</h5><p>${data.feedback || 'Feedback'}</p></div>`;

            if (data.accomplishments) {
                html += '<div class="ai-card"><h5><i class="fas fa-trophy"></i> Key Accomplishments</h5>';
                data.accomplishments.forEach(a => {
                    html += `<div class="strength-item">${a}</div>`;
                });
                html += '</div>';
            }

            if (data.development_opportunities) {
                html += '<div class="ai-card"><h5><i class="fas fa-lightbulb"></i> Development Opportunities</h5>';
                data.development_opportunities.forEach(o => {
                    html += `<div class="improvement-item">${o}</div>`;
                });
                html += '</div>';
            }

            if (data.goals_next_period) {
                html += '<div class="ai-card"><h5><i class="fas fa-bullseye"></i> Goals for Next Period</h5>';
                data.goals_next_period.forEach(g => {
                    html += `<p><i class="fas fa-check"></i> ${g}</p>`;
                });
                html += '</div>';
            }

            $('#feedbackContent').html(html);
        }

        // Load performance trend
        function loadTrend() {
            $('#trendLoading').show();
            $('#trendContent').empty();
            
            $.ajax({
                url: API_BASE,
                method: 'POST',
                data: {
                    action: 'get_performance_trend',
                    employee_id: selectedEmployeeId
                },
                dataType: 'json',
                success: function(data) {
                    $('#trendLoading').hide();
                    displayTrend(data);
                },
                error: function() {
                    $('#trendLoading').hide();
                    $('#trendContent').html('<div class="alert alert-info">No trend data available</div>');
                }
            });
        }

        function displayTrend(data) {
            if (data.error || data.status === 'insufficient_data') {
                $('#trendContent').html('<div class="alert alert-info">Insufficient data for trend analysis</div>');
                return;
            }

            const trendClass = 'trend-' + data.trend;
            let html = '<div class="ai-card">';
            html += `<h5><i class="fas fa-chart-line"></i> Performance Trajectory</h5>`;
            html += `<p class="${trendClass}"><strong>Trend: ${data.trend.toUpperCase()}</strong></p>`;
            html += `<p>Change: ${data.change_percentage}%</p>`;
            html += `<p>Current Average: <strong>${data.current_average}/5</strong></p>`;
            html += `<p>Previous Average: <strong>${data.previous_average}/5</strong></p>`;
            html += `<p class="text-muted">Based on ${data.data_points} data points</p>`;
            html += '</div>';
            $('#trendContent').html(html);
        }

        // Load competency gaps
        function loadGaps() {
            $('#gapsLoading').show();
            $('#gapsContent').empty();
            
            $.ajax({
                url: API_BASE,
                method: 'POST',
                data: {
                    action: 'get_competency_gaps',
                    employee_id: selectedEmployeeId
                },
                dataType: 'json',
                success: function(data) {
                    $('#gapsLoading').hide();
                    displayGaps(data);
                },
                error: function() {
                    $('#gapsLoading').hide();
                    $('#gapsContent').html('<div class="alert alert-danger">Error loading gap analysis</div>');
                }
            });
        }

        function displayGaps(data) {
            if (data.error) {
                $('#gapsContent').html(`<div class="alert alert-danger">${data.error}</div>`);
                return;
            }

            let html = '<div class="ai-card">';
            html += `<h5><i class="fas fa-bullseye"></i> Competency Assessment</h5>`;
            html += `<p>Total Gaps Identified: <strong>${data.gap_count}</strong></p>`;
            html += `<p>High Priority: <strong>${data.high_priority_gaps}</strong></p></div>`;

            if (data.gaps && data.gaps.length > 0) {
                html += '<div class="ai-card"><h5>Gap Details</h5>';
                data.gaps.forEach(gap => {
                    const priority = gap.priority === 'high' ? '🔴' : '🟡';
                    html += `<div class="gap-item">
                        <strong>${priority} ${gap.competency}</strong><br>
                        Current: ${gap.current_level}/5 | Required: ${gap.required_level}/5 | Gap: ${gap.gap}
                    </div>`;
                });
                html += '</div>';
            }

            $('#gapsContent').html(html);
        }

        // Load development plan
        function loadDevelopmentPlan() {
            if (!selectedEmployeeId) return;
            
            $('#developmentLoading').show();
            $('#developmentContent').empty();
            
            $.ajax({
                url: API_BASE,
                method: 'POST',
                data: {
                    action: 'get_development_plan',
                    employee_id: selectedEmployeeId
                },
                dataType: 'json',
                success: function(data) {
                    $('#developmentLoading').hide();
                    displayDevelopmentPlan(data);
                },
                error: function() {
                    $('#developmentLoading').hide();
                    $('#developmentContent').html('<div class="alert alert-danger">Error generating development plan</div>');
                }
            });
        }

        function displayDevelopmentPlan(data) {
            if (data.error) {
                $('#developmentContent').html(`<div class="alert alert-danger">${data.error}</div>`);
                return;
            }

            let html = '';

            if (data.recommended_trainings) {
                html += '<div class="ai-card"><h5><i class="fas fa-graduation-cap"></i> Recommended Training Programs</h5>';
                if (Array.isArray(data.recommended_trainings)) {
                    data.recommended_trainings.forEach(training => {
                        html += `<div class="insight-box"><strong>${training}</strong></div>`;
                    });
                } else {
                    html += `<p>${data.recommended_trainings}</p>`;
                }
                html += '</div>';
            }

            if (data.mentoring) {
                html += '<div class="ai-card"><h5><i class="fas fa-users"></i> Mentoring Opportunities</h5>';
                if (Array.isArray(data.mentoring)) {
                    data.mentoring.forEach(mentor => {
                        html += `<div class="insight-box">${mentor}</div>`;
                    });
                } else {
                    html += `<p>${data.mentoring}</p>`;
                }
                html += '</div>';
            }

            if (data.on_the_job_activities) {
                html += '<div class="ai-card"><h5><i class="fas fa-tasks"></i> On-The-Job Learning Activities</h5>';
                if (Array.isArray(data.on_the_job_activities)) {
                    data.on_the_job_activities.forEach(activity => {
                        html += `<div class="insight-box"><i class="fas fa-check"></i> ${activity}</div>`;
                    });
                } else {
                    html += `<p>${data.on_the_job_activities}</p>`;
                }
                html += '</div>';
            }

            if (data.timeline) {
                html += '<div class="ai-card"><h5><i class="fas fa-calendar"></i> Development Timeline</h5>';
                if (Array.isArray(data.timeline)) {
                    data.timeline.forEach(item => {
                        html += `<div class="insight-box"><strong>${item}</strong></div>`;
                    });
                } else {
                    html += `<p>${data.timeline}</p>`;
                }
                html += '</div>';
            }

            if (data.metrics) {
                html += '<div class="ai-card"><h5><i class="fas fa-chart-line"></i> Success Metrics</h5>';
                if (Array.isArray(data.metrics)) {
                    data.metrics.forEach(metric => {
                        html += `<div class="improvement-item"><i class="fas fa-target"></i> ${metric}</div>`;
                    });
                } else {
                    html += `<p>${data.metrics}</p>`;
                }
                html += '</div>';
            }

            $('#developmentContent').html(html || '<div class="alert alert-info">No development plan data</div>');
        }

        // Initialize loaders visibility and button states on document ready
        $(document).ready(function() {
            // Hide all spinners initially
            $('#insightsLoading').hide();
            $('#feedbackLoading').hide();
            $('#trendLoading').hide();
            $('#gapsLoading').hide();
            $('#developmentLoading').hide();
            
            // Employee selection change handler
            $('#employeeSelect').on('change', function() {
                selectedEmployeeId = $(this).val();
                updateButtonStates();
                
                if (selectedEmployeeId) {
                    // Auto-load analysis for selected employee
                    $('#analysisTab a:first').tab('show');
                    loadAllAnalyses();
                }
            });
            
            // Load Data button handler
            $('#loadDataBtn').on('click', function() {
                if (selectedEmployeeId) {
                    loadAllAnalyses();
                }
            });
            
            // Feedback generation button handler  
            $('#generateFeedbackBtn').on('click', function() {
                if (selectedEmployeeId) {
                    $.ajax({
                        url: API_BASE,
                        method: 'POST',
                        data: {
                            action: 'get_feedback_suggestions',
                            employee_id: selectedEmployeeId,
                            review_type: $('#reviewType').val()
                        },
                        dataType: 'json',
                        success: function(data) {
                            $('#feedbackLoading').hide();
                            displayFeedback(data);
                        },
                        error: function() {
                            $('#feedbackLoading').hide();
                            $('#feedbackContent').html('<div class="alert alert-danger">Error generating feedback</div>');
                        }
                    });
                    $('#feedbackLoading').show();
                    $('#feedbackContent').empty();
                }
            });
            
            // Tab change handler - load data when tab is shown
            $('#analysisTab a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                var target = $(e.target).attr("href");
                if (target === '#feedback' && selectedEmployeeId) {
                    // Don't auto-load, user must click generate button
                }
            });
        });

        // Helper function to update button states
        function updateButtonStates() {
            const hasEmployee = selectedEmployeeId !== null && selectedEmployeeId !== '';
            $('#generateFeedbackBtn').prop('disabled', !hasEmployee);
            $('#loadDataBtn').prop('disabled', !hasEmployee);
        }

        // Helper function to load all analyses
        function loadAllAnalyses() {
            if (!selectedEmployeeId) return;
            
            loadInsights();
            loadTrend();
            loadGaps();
            loadDevelopmentPlan();
        }
    </script>
</body>
</html>
