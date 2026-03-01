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

// Include database connection
require_once 'dp.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Evaluation Form - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --primary-color: #E91E63;
            --primary-light: #F06292;
            --primary-dark: #C2185B;
            --primary-pale: #FCE4EC;
        }

        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }

        .container-fluid {
            padding: 0;
        }

        .row {
            margin-right: 0;
            margin-left: 0;
        }

        body {
            background: var(--primary-pale);
        }

        .main-content {
            background: var(--primary-pale);
            padding: 20px;
        }

        .eval-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .eval-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .eval-header h2 {
            margin: 0 0 10px 0;
        }

        .category-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-pale);
        }

        .eval-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
        }

        .eval-item h5 {
            color: var(--primary-dark);
            margin-bottom: 15px;
        }

        .rating-scale {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .rating-option {
            flex: 1;
            min-width: 80px;
            text-align: center;
            padding: 12px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .rating-option:hover {
            border-color: var(--primary-color);
            background: var(--primary-pale);
        }

        .rating-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }

        .rating-option input {
            display: none;
        }

        .rating-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
        }

        .rating-desc {
            display: block;
            font-size: 11px;
            margin-top: 5px;
            opacity: 0.8;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .form-control, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #1976d2;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .info-box i {
            color: #1976d2;
            margin-right: 10px;
        }

        .score-summary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 20px;
        }

        .score-summary h3 {
            margin: 0;
            font-size: 2.5rem;
        }

        .score-summary p {
            margin: 0;
            opacity: 0.9;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-competencies {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #666;
        }

        .no-competencies i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
            background: none;
            border: none;
        }

        .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px;
        }

        .competency-checkbox {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 3px solid var(--primary-color);
        }

        .competency-checkbox:hover {
            background: var(--primary-pale);
        }

        .competency-checkbox input {
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            .rating-option {
                min-width: 60px;
                padding: 10px 5px;
            }
            
            .rating-label {
                font-size: 12px;
            }
            
            .rating-desc {
                display: none;
            }
            
            .action-buttons {
                flex-direction: column;
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
                <h2 class="section-title"><i class="fas fa-clipboard-check"></i> Employee Evaluation Form</h2>
                
                <div class="eval-card">
                    <div class="eval-header">
                        <h2><i class="fas fa-user-check"></i> Performance Evaluation</h2>
                        <p class="mb-0">Evaluate employee performance based on assigned competencies.</p>
                    </div>

                    <div id="messageBox"></div>

                    <form id="evaluationForm">
                        <!-- Employee and Cycle Selection with Assign Button -->
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="employeeSelect"><i class="fas fa-user"></i> Select Employee *</label>
                                    <select id="employeeSelect" class="form-select" required>
                                        <option value="">-- Select Employee --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="cycleSelect"><i class="fas fa-calendar"></i> Review Cycle *</label>
                                    <select id="cycleSelect" class="form-select" required>
                                        <option value="">-- Select Review Cycle --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="button" class="btn btn-primary btn-block" onclick="openAssignModal()">
                                        <i class="fas fa-calendar-plus"></i> Create Cycle
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <strong>Evaluation Scale:</strong> 
                            5 - Strongly Agree | 4 - Agree | 3 - Neutral | 2 - Disagree | 1 - Strongly Disagree
                        </div>

                        <!-- Competencies Container - Dynamic Content -->
                        <div id="competenciesContainer">
                            <div class="no-competencies">
                                <i class="fas fa-clipboard-list"></i>
                                <h5>No Competencies Selected</h5>
                                <p>Please select both an employee and a review cycle to load their assigned competencies.</p>
                            </div>
                        </div>

                        <!-- Additional Comments -->
                        <div class="form-group" style="margin-top: 30px; display: none;" id="additionalCommentsContainer">
                            <label for="additionalComments"><i class="fas fa-comment"></i> Additional Comments</label>
                            <textarea id="additionalComments" class="form-control" placeholder="Enter any additional feedback or observations..."></textarea>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons" id="actionButtons" style="display: none;">
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                <i class="fas fa-redo"></i> Reset Form
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Submit Evaluation
                            </button>
                            <button type="button" class="btn btn-primary" onclick="generateReport()">
                                <i class="fas fa-file-alt"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Training Records Section -->
                <div class="eval-card" style="margin-top: 25px;">
                    <div class="eval-header">
                        <h2><i class="fas fa-graduation-cap"></i> Training Records</h2>
                        <p class="mb-0">Employee training history and certifications.</p>
                    </div>

                    <div id="trainingRecordsLoading" class="text-center" style="padding: 40px;">
                        <i class="fas fa-spinner fa-spin"></i> Loading training records...
                    </div>

                    <div id="trainingRecordsContent" style="display: none;">
                        <!-- Training Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <div class="stats-card-small" style="background: #e3f2fd; padding: 15px; border-radius: 10px; text-align: center;">
                                    <div style="font-size: 24px; font-weight: bold; color: #1976d2;" id="statTotalTrainings">0</div>
                                    <div style="font-size: 11px; color: #666;">Total Trainings</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card-small" style="background: #d4edda; padding: 15px; border-radius: 10px; text-align: center;">
                                    <div style="font-size: 24px; font-weight: bold; color: #155724;" id="statCompletedTrainings">0</div>
                                    <div style="font-size: 11px; color: #666;">Completed</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card-small" style="background: #fff3cd; padding: 15px; border-radius: 10px; text-align: center;">
                                    <div style="font-size: 24px; font-weight: bold; color: #856404;" id="statInProgressTrainings">0</div>
                                    <div style="font-size: 11px; color: #666;">In Progress</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card-small" style="background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: center;">
                                    <div style="font-size: 24px; font-weight: bold; color: #333;" id="statAvgScore">0%</div>
                                    <div style="font-size: 11px; color: #666;">Avg Score</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card-small" style="background: #e0e7ff; padding: 15px; border-radius: 10px; text-align: center;">
                                    <div style="font-size: 24px; font-weight: bold; color: #4338ca;" id="statTotalCerts">0</div>
                                    <div style="font-size: 11px; color: #666;">Certifications</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card-small" style="background: #d1fae5; padding: 15px; border-radius: 10px; text-align: center;">
                                    <div style="font-size: 24px; font-weight: bold; color: #059669;" id="statActiveCerts">0</div>
                                    <div style="font-size: 11px; color: #666;">Active Certs</div>
                                </div>
                            </div>
                        </div>

                        <!-- Training Enrollments -->
                        <h5 class="category-title"><i class="fas fa-book"></i> Training Enrollments</h5>
                        <div id="trainingEnrollmentsContainer">
                            <div class="no-data-message" style="text-align: center; padding: 30px; color: #666;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>No training enrollments found.</p>
                            </div>
                        </div>

                        <!-- Certifications -->
                        <h5 class="category-title" style="margin-top: 30px;"><i class="fas fa-certificate"></i> Certifications</h5>
                        <div id="certificationsContainer">
                            <div class="no-data-message" style="text-align: center; padding: 30px; color: #666;">
                                <i class="fas fa-certificate" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>No certifications found.</p>
                            </div>
                        </div>

                        <!-- View Full Report Button -->
                        <div class="action-buttons" style="margin-top: 30px;">
                            <button type="button" class="btn btn-success" onclick="generateReport()">
                                <i class="fas fa-file-alt"></i> View Full Evaluation & Training Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Review Cycle Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-plus"></i> Create Cycle</h2>
                <button class="close" onclick="closeAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assignForm">
                    <div class="form-group">
                        <label for="cycleName">Cycle Name *</label>
                        <input type="text" id="cycleName" class="form-control" placeholder="e.g., Review Jan 2025" required>
                    </div>

                    <div class="form-group">
                        <label for="startDate">Start Date *</label>
                        <input type="date" id="startDate" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="endDate">End Date *</label>
                        <input type="date" id="endDate" class="form-control" required>
                    </div>

                    <div style="text-align: center; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" style="margin-right: 10px;" onclick="closeAssignModal()">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Create Cycle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Store loaded competencies
        let loadedCompetencies = [];
        let allCompetencies = [];
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadEmployees();
            loadCycles();
            loadAllCompetencies();
        });

        // Load employees
        function loadEmployees() {
            fetch('get_employees.php')
                .then(res => {
                    console.log('Response status:', res.status);
                    return res.json();
                })
                .then(data => {
                    console.log('Employee data received:', data);
                    const select = document.getElementById('employeeSelect');
                    
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(emp => {
                            const name = emp.full_name || emp.employee_name || 'Unknown';
                            select.innerHTML += `<option value="${emp.employee_id}">${name} (${emp.employee_number || ''})</option>`;
                        });
                        console.log('Loaded ' + data.length + ' employees');
                    } else {
                        console.log('No employees found or data is not an array');
                        select.innerHTML += `<option value="">No employees available</option>`;
                    }
                })
                .catch(err => {
                    console.error("Failed to load employees:", err);
                    document.getElementById('messageBox').innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            Error loading employees. Please check console for details.
                        </div>
                    `;
                });
        }

        // Load review cycles
        function loadCycles() {
            return fetch('get_cycles.php')
                .then(res => res.json())
                .then(data => {
                    const select = document.getElementById('cycleSelect');
                    // Clear existing options except the first one
                    select.innerHTML = '<option value="">-- Select Review Cycle --</option>';
                    if (data.success && data.cycles) {
                        data.cycles.forEach(cycle => {
                            select.innerHTML += `<option value="${cycle.cycle_id}">${cycle.cycle_name}</option>`;
                        });
                    }
                })
                .catch(err => console.error("Failed to load cycles:", err));
        }

        // Load available competencies filtered by employee's job role
        function loadAllCompetencies(jobRoleId = 0) {
            let url = 'get_competencies.php';
            if (jobRoleId > 0) {
                url += `?job_role_id=${jobRoleId}`;
            }

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    allCompetencies = Array.isArray(data) ? data : [];
                    // Competency list rendering removed as modal no longer has competency checkboxes
                })
                .catch(err => console.error("Failed to load competencies:", err));
        }

        // Setup change listeners for employee and cycle selection
        document.getElementById('employeeSelect').addEventListener('change', loadCompetencies);
        document.getElementById('cycleSelect').addEventListener('change', loadCompetencies);

        // Open assign modal - now for creating a new review cycle
        function openAssignModal() {
            // Reset the form
            document.getElementById('assignForm').reset();
            // Open the modal
            document.getElementById('assignModal').style.display = 'block';
        }

        // Close assign modal
        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
        }

        // Load competencies for selected employee and cycle
        function loadCompetencies() {
            const employeeId = document.getElementById('employeeSelect').value;
            const cycleId = document.getElementById('cycleSelect').value;
            const container = document.getElementById('competenciesContainer');
            const actionButtons = document.getElementById('actionButtons');
            const additionalCommentsContainer = document.getElementById('additionalCommentsContainer');

            // If no employee selected, don't try to load competencies - just show message
            if (!employeeId) {
                container.innerHTML = `
                    <div class="no-competencies">
                        <i class="fas fa-clipboard-list"></i>
                        <h5>No Competencies Selected</h5>
                        <p>Please select an employee first to load their competencies.</p>
                    </div>
                `;
                actionButtons.style.display = 'none';
                additionalCommentsContainer.style.display = 'none';
                return;
            }

            if (!cycleId) {
                container.innerHTML = `
                    <div class="no-competencies">
                        <i class="fas fa-clipboard-list"></i>
                        <h5>No Competencies Selected</h5>
                        <p>Please select a review cycle to load competencies.</p>
                    </div>
                `;
                actionButtons.style.display = 'none';
                additionalCommentsContainer.style.display = 'none';
                return;
            }

            // Show loading message
            container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading competencies...</div>';

            // Fetch competencies for this employee and cycle
            fetch(`get_employee_competencies.php?employee_id=${employeeId}&cycle_id=${cycleId}`)
                .then(res => res.json())
                .then(data => {
                    loadedCompetencies = Array.isArray(data) ? data : [];
                    
                    if (loadedCompetencies.length === 0) {
                        container.innerHTML = `
                            <div class="no-competencies">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h5>No Competencies Assigned</h5>
                                <p>This employee has no competencies assigned for the selected review cycle.</p>
                                <button type="button" class="btn btn-primary mt-2" onclick="openAssignModal()">
                                    <i class="fas fa-plus"></i> Assign Competencies Now
                                </button>
                            </div>
                        `;
                        actionButtons.style.display = 'none';
                        additionalCommentsContainer.style.display = 'none';
                    } else {
                        renderCompetencies(loadedCompetencies);
                        actionButtons.style.display = 'flex';
                        additionalCommentsContainer.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error("Failed to load competencies:", err);
                    container.innerHTML = `
                        <div class="no-competencies">
                            <i class="fas fa-exclamation-circle"></i>
                            <h5>Error Loading Competencies</h5>
                            <p>Failed to load competencies. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Render competencies dynamically
        function renderCompetencies(competencies) {
            const container = document.getElementById('competenciesContainer');
            
            if (!competencies || competencies.length === 0) {
                container.innerHTML = `
                    <div class="no-competencies">
                        <i class="fas fa-clipboard-list"></i>
                        <h5>No Competencies Found</h5>
                        <p>No competencies are assigned to this employee for the selected review cycle.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            
            competencies.forEach((comp, index) => {
                const itemNum = index + 1;
                const existingRating = comp.rating || '';
                const existingComment = comp.comments || '';
                
                html += `
                    <div class="eval-item" data-competency-id="${comp.competency_id}">
                        <h5>${itemNum}. ${escapeHtml(comp.name)}</h5>
                        ${comp.description ? `<p class="text-muted mb-3">${escapeHtml(comp.description)}</p>` : ''}
                        <div class="rating-scale">
                            <label class="rating-option ${existingRating == 5 ? 'selected' : ''}">
                                <input type="radio" name="rating_${itemNum}" value="5" ${existingRating == 5 ? 'checked' : ''}>
                                <span class="rating-label">5</span>
                                <span class="rating-desc">Strongly Agree</span>
                            </label>
                            <label class="rating-option ${existingRating == 4 ? 'selected' : ''}">
                                <input type="radio" name="rating_${itemNum}" value="4" ${existingRating == 4 ? 'checked' : ''}>
                                <span class="rating-label">4</span>
                                <span class="rating-desc">Agree</span>
                            </label>
                            <label class="rating-option ${existingRating == 3 ? 'selected' : ''}">
                                <input type="radio" name="rating_${itemNum}" value="3" ${existingRating == 3 ? 'checked' : ''}>
                                <span class="rating-label">3</span>
                                <span class="rating-desc">Neutral</span>
                            </label>
                            <label class="rating-option ${existingRating == 2 ? 'selected' : ''}">
                                <input type="radio" name="rating_${itemNum}" value="2" ${existingRating == 2 ? 'checked' : ''}>
                                <span class="rating-label">2</span>
                                <span class="rating-desc">Disagree</span>
                            </label>
                            <label class="rating-option ${existingRating == 1 ? 'selected' : ''}">
                                <input type="radio" name="rating_${itemNum}" value="1" ${existingRating == 1 ? 'checked' : ''}>
                                <span class="rating-label">1</span>
                                <span class="rating-desc">Strongly Disagree</span>
                            </label>
                        </div>
                        <input type="hidden" name="competency_id_${itemNum}" value="${comp.competency_id}">
                        <div class="form-group" style="margin-top: 15px;">
                            <textarea class="form-control" name="comment_${itemNum}" placeholder="Add comments (optional)...">${escapeHtml(existingComment)}</textarea>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            
            // Re-attach click listeners to new rating options
            setupRatingListeners();
        }

        // Setup rating option click listeners
        function setupRatingListeners() {
            document.querySelectorAll('.rating-option').forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                    
                    const name = this.querySelector('input').name;
                    document.querySelectorAll(`input[name="${name}"]`).forEach(input => {
                        input.closest('.rating-option').classList.remove('selected');
                    });
                    this.classList.add('selected');
                });
            });
        }

        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All ratings and comments will be cleared.')) {
                document.getElementById('evaluationForm').reset();
                document.querySelectorAll('.rating-option').forEach(opt => opt.classList.remove('selected'));
                document.getElementById('messageBox').innerHTML = '';
                loadCompetencies();
            }
        }

        // Form submission for evaluation
        document.getElementById('evaluationForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const employeeId = document.getElementById('employeeSelect').value;
            const cycleId = document.getElementById('cycleSelect').value;

            console.log('Submitting evaluation - Employee ID:', employeeId, 'Cycle ID:', cycleId);

            if (!employeeId || employeeId === '') {
                showMessage('Please select an employee.', 'error');
                return;
            }

            if (!cycleId || cycleId === '') {
                showMessage('Please select a review cycle.', 'error');
                return;
            }

            if (loadedCompetencies.length === 0) {
                showMessage('No competencies to evaluate. Please assign competencies first.', 'error');
                return;
            }

            const evaluations = [];
            const competencyItems = document.querySelectorAll('#competenciesContainer .eval-item');
            let allRated = true;
            
            competencyItems.forEach((item, index) => {
                const competencyId = item.getAttribute('data-competency-id');
                const ratingEl = item.querySelector(`input[name="rating_${index + 1}"]:checked`);
                const commentEl = item.querySelector(`textarea[name="comment_${index + 1}"]`);
                
                if (!ratingEl) {
                    allRated = false;
                } else {
                    evaluations.push({
                        competency_id: competencyId,
                        rating: parseInt(ratingEl.value),
                        comment: commentEl ? commentEl.value : ''
                    });
                }
            });

            if (!allRated || evaluations.length === 0) {
                showMessage('Please rate all competencies before submitting.', 'error');
                return;
            }

            // Validate and convert to integers properly
            const employeeIdInt = parseInt(employeeId);
            const cycleIdInt = parseInt(cycleId);
            
            // Check for NaN
            if (isNaN(employeeIdInt) || employeeIdInt <= 0) {
                showMessage('Please select a valid employee.', 'error');
                return;
            }
            
            if (isNaN(cycleIdInt) || cycleIdInt <= 0) {
                showMessage('Please select a valid review cycle.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('employee_id', employeeIdInt);
            formData.append('cycle_id', cycleIdInt);
            formData.append('evaluations', JSON.stringify(evaluations));
            formData.append('additional_comments', document.getElementById('additionalComments').value);

            console.log('Form data:', {
                employee_id: employeeIdInt,
                cycle_id: cycleIdInt,
                evaluations: evaluations
            });

            fetch('save_employee_evaluation.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                console.log('Response:', data);
                if (data.success) {
                    showMessage('Evaluation submitted successfully!', 'success');
                    resetForm();
                } else {
                    showMessage('Error: ' + (data.message || 'Failed to submit evaluation'), 'error');
                }
            })
            .catch(err => {
                console.error("Submit error:", err);
                showMessage("Failed to submit evaluation. Please try again.", 'error');
            });
        });

        // Form submission for creating review cycle
        document.getElementById('assignForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const cycleName = document.getElementById('cycleName').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            if (!cycleName || !startDate || !endDate) {
                showMessage('Please fill in all fields.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('cycleName', cycleName);
            formData.append('startDate', startDate);
            formData.append('endDate', endDate);

            fetch('save_cycle.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage('Review cycle created successfully!', 'success');
                    closeAssignModal();
                    
                    // Reload cycles and then select the newly created cycle
                    loadCycles().then(() => {
                        // Find and select the newly created cycle by name
                        const select = document.getElementById('cycleSelect');
                        for (let i = 0; i < select.options.length; i++) {
                            if (select.options[i].text === cycleName) {
                                select.selectedIndex = i;
                                // Only load competencies if an employee is already selected
                                const employeeId = document.getElementById('employeeSelect').value;
                                if (employeeId) {
                                    loadCompetencies();
                                }
                                break;
                            }
                        }
                    });
                } else {
                    showMessage('Error: ' + (data.message || 'Failed to create cycle'), 'error');
                }
            })
            .catch(err => {
                console.error("Create cycle error:", err);
                showMessage("Failed to create review cycle. Please try again.", 'error');
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('assignModal');
            if (event.target === modal) {
                closeAssignModal();
            }
        }

        // Show message
        function showMessage(message, type) {
            const box = document.getElementById('messageBox');
            box.innerHTML = `
                <div class="alert alert-${type === 'success' ? 'success' : 'error'}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                </div>
            `;
            
            setTimeout(() => {
                box.innerHTML = '';
            }, 5000);
        }

        // Utility function to escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Load training records when employee is selected
        document.getElementById('employeeSelect').addEventListener('change', loadTrainingRecords);

        // Load training records for the selected employee
        function loadTrainingRecords() {
            const employeeId = document.getElementById('employeeSelect').value;
            const loadingDiv = document.getElementById('trainingRecordsLoading');
            const contentDiv = document.getElementById('trainingRecordsContent');

            if (!employeeId) {
                loadingDiv.style.display = 'none';
                contentDiv.style.display = 'none';
                return;
            }

            // Show loading
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';

            // Fetch training records
            fetch(`get_employee_training_records.php?employee_id=${employeeId}`)
                .then(res => res.json())
                .then(data => {
                    loadingDiv.style.display = 'none';
                    
                    if (data.success) {
                        contentDiv.style.display = 'block';
                        
                        // Update statistics
                        document.getElementById('statTotalTrainings').textContent = data.training_stats.total_trainings || 0;
                        document.getElementById('statCompletedTrainings').textContent = data.training_stats.completed_trainings || 0;
                        document.getElementById('statInProgressTrainings').textContent = data.training_stats.in_progress_trainings || 0;
                        document.getElementById('statAvgScore').textContent = (data.training_stats.average_score || 0) + '%';
                        document.getElementById('statTotalCerts').textContent = data.training_stats.total_certifications || 0;
                        document.getElementById('statActiveCerts').textContent = data.training_stats.active_certifications || 0;

                        // Render training enrollments
                        const enrollmentsContainer = document.getElementById('trainingEnrollmentsContainer');
                        if (data.enrollments && data.enrollments.length > 0) {
                            let enrollmentsHtml = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Course</th><th>Session</th><th>Trainer</th><th>Status</th><th>Score</th></tr></thead><tbody>';
                            data.enrollments.forEach(enroll => {
                                const statusClass = enroll.enrollment_status === 'Completed' ? 'status-completed' : 
                                                   enroll.enrollment_status === 'Enrolled' ? 'status-enrolled' : '';
                                enrollmentsHtml += `<tr>
                                    <td>${escapeHtml(enroll.course_name || 'N/A')}</td>
                                    <td>${escapeHtml(enroll.session_name || 'N/A')}</td>
                                    <td>${escapeHtml(enroll.trainer_name || 'N/A')}</td>
                                    <td><span class="status-badge ${statusClass}">${escapeHtml(enroll.enrollment_status)}</span></td>
                                    <td>${enroll.score ? enroll.score + '%' : '-'}</td>
                                </tr>`;
                            });
                            enrollmentsHtml += '</tbody></table></div>';
                            enrollmentsContainer.innerHTML = enrollmentsHtml;
                        } else {
                            enrollmentsContainer.innerHTML = `
                                <div class="no-data-message" style="text-align: center; padding: 30px; color: #666;">
                                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                    <p>No training enrollments found.</p>
                                </div>
                            `;
                        }

                        // Render certifications
                        const certsContainer = document.getElementById('certificationsContainer');
                        if (data.certifications && data.certifications.length > 0) {
                            let certsHtml = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Certification</th><th>Organization</th><th>Issue Date</th><th>Status</th></tr></thead><tbody>';
                            data.certifications.forEach(cert => {
                                const statusClass = cert.certification_status === 'Active' ? 'status-active' : 
                                                   cert.certification_status === 'Expired' ? 'status-expired' : '';
                                certsHtml += `<tr>
                                    <td>${escapeHtml(cert.certification_name)}</td>
                                    <td>${escapeHtml(cert.issuing_organization)}</td>
                                    <td>${cert.issue_date ? new Date(cert.issue_date).toLocaleDateString() : 'N/A'}</td>
                                    <td><span class="status-badge ${statusClass}">${escapeHtml(cert.certification_status)}</span></td>
                                </tr>`;
                            });
                            certsHtml += '</tbody></table></div>';
                            certsContainer.innerHTML = certsHtml;
                        } else {
                            certsContainer.innerHTML = `
                                <div class="no-data-message" style="text-align: center; padding: 30px; color: #666;">
                                    <i class="fas fa-certificate" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                    <p>No certifications found.</p>
                                </div>
                            `;
                        }
                    } else {
                        showMessage('Error loading training records: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(err => {
                    console.error("Failed to load training records:", err);
                    loadingDiv.style.display = 'none';
                    showMessage('Failed to load training records. Please try again.', 'error');
                });
        }

        // Generate report - opens the comprehensive evaluation and training report
        function generateReport() {
            const employeeId = document.getElementById('employeeSelect').value;
            const cycleId = document.getElementById('cycleSelect').value;

            if (!employeeId) {
                showMessage('Please select an employee first.', 'error');
                return;
            }

            // Open the report in a new window
            window.open(`evaluation_training_report.php?employee_id=${employeeId}&cycle_id=${cycleId}`, '_blank');
        }


    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
