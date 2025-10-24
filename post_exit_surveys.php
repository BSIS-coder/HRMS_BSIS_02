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

// Include database connection and helper functions
require_once 'db.php';

// Database connection
$host = 'localhost';
$dbname = 'hr_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new survey
                try {
                    $stmt = $pdo->prepare("INSERT INTO post_exit_surveys (employee_id, exit_id, survey_date, survey_response, satisfaction_rating, submitted_date) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['exit_id'],
                        $_POST['survey_date'],
                        $_POST['survey_response'],
                        $_POST['satisfaction_rating'],
                        $_POST['submitted_date']
                    ]);
                    $message = "Post-exit survey added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding survey: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update survey
                try {
                    $stmt = $pdo->prepare("UPDATE post_exit_surveys SET employee_id=?, exit_id=?, survey_date=?, survey_response=?, satisfaction_rating=?, submitted_date=? WHERE survey_id=?");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['exit_id'],
                        $_POST['survey_date'],
                        $_POST['survey_response'],
                        $_POST['satisfaction_rating'],
                        $_POST['submitted_date'],
                        $_POST['survey_id']
                    ]);
                    $message = "Post-exit survey updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating survey: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete':
                // Delete survey
                try {
                    $stmt = $pdo->prepare("DELETE FROM post_exit_surveys WHERE survey_id=?");
                    $stmt->execute([$_POST['survey_id']]);
                    $message = "Post-exit survey deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting survey: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch surveys with related data
$stmt = $pdo->query("
    SELECT 
        pes.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        jr.title as job_title,
        jr.department,
        ex.exit_date,
        ex.exit_type
    FROM post_exit_surveys pes
    LEFT JOIN employee_profiles ep ON pes.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN exits ex ON pes.exit_id = ex.exit_id
    ORDER BY pes.survey_id DESC
");
$surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        ep.employee_number
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown
$stmt = $pdo->query("
    SELECT 
        ex.exit_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ex.exit_date,
        ex.exit_type
    FROM exits ex
    LEFT JOIN employee_profiles ep ON ex.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY ex.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Exit Surveys Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for post-exit surveys page */
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        .section-title {
            color: var(--azure-blue);
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
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .btn {
            padding: 12px 25px;
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
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
            margin: 0 3px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--azure-blue-lighter);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .rating-stars {
            color: #ffc107;
            font-size: 18px;
        }

        .rating-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .rating-excellent {
            background: #d4edda;
            color: #155724;
        }

        .rating-good {
            background: #d1ecf1;
            color: #0c5460;
        }

        .rating-average {
            background: #fff3cd;
            color: #856404;
        }

        .rating-poor {
            background: #f8d7da;
            color: #721c24;
        }

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
            max-width: 700px;
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
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
        }

        .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .form-control {
            width: 100%;
            padding: 6px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
        }

        .rating-input {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .rating-input input[type="radio"] {
            display: none;
        }

        .rating-input label {
            font-size: 28px;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0;
        }

        .rating-input label:hover,
        .rating-input input[type="radio"]:checked ~ label,
        .rating-input label.active {
            color: #ffc107;
            transform: scale(1.1);
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

        .no-results {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .survey-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            max-width: 300px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .form-row {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }

            .content {
                padding: 20px;
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
                <h2 class="section-title">Post-Exit Surveys Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search surveys by employee name or exit type...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add New Survey
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="surveyTable">
                            <thead>
                                <tr>
                                    <th>Survey ID</th>
                                    <th>Employee</th>
                                    <th>Exit Date</th>
                                    <th>Exit Type</th>
                                    <th>Survey Date</th>
                                    <th>Rating</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="surveyTableBody">
                                <?php foreach ($surveys as $survey): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($survey['survey_id']) ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($survey['employee_name']) ?></strong><br>
                                            <small style="color: #666;">👤 <?= htmlspecialchars($survey['employee_number']) ?></small><br>
                                            <small style="color: #666;">💼 <?= htmlspecialchars($survey['job_title']) ?> - <?= htmlspecialchars($survey['department']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($survey['exit_date'])) ?></td>
                                    <td><?= htmlspecialchars($survey['exit_type']) ?></td>
                                    <td><?= date('M d, Y', strtotime($survey['survey_date'])) ?></td>
                                    <td>
                                        <div class="rating-stars">
                                            <?php 
                                            $rating = $survey['satisfaction_rating'];
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $rating ? '⭐' : '☆';
                                            }
                                            ?>
                                        </div>
                                        <span class="rating-badge rating-<?= $rating >= 4 ? 'excellent' : ($rating == 3 ? 'good' : ($rating == 2 ? 'average' : 'poor')) ?>">
                                            <?= $rating ?>/5
                                        </span>
                                    </td>
                                    <td><?= $survey['submitted_date'] ? date('M d, Y H:i', strtotime($survey['submitted_date'])) : 'N/A' ?></td>
                                    <td>
                                        <button class="btn btn-info btn-small" onclick="viewSurvey(<?= $survey['survey_id'] ?>)">
                                            👁️ View
                                        </button>
                                        <button class="btn btn-warning btn-small" onclick="editSurvey(<?= $survey['survey_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteSurvey(<?= $survey['survey_id'] ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($surveys)): ?>
                        <div class="no-results">
                            <i>📋</i>
                            <h3>No surveys found</h3>
                            <p>Start by adding your first post-exit survey.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Survey Modal -->
    <div id="surveyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Survey</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="surveyForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="survey_id" name="survey_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="employee_id">Employee</label>
                                <select id="employee_id" name="employee_id" class="form-control" required>
                                    <option value="">Select employee...</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>"><?= htmlspecialchars($employee['full_name']) ?> (<?= htmlspecialchars($employee['employee_number']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="exit_id">Exit Record</label>
                                <select id="exit_id" name="exit_id" class="form-control" required>
                                    <option value="">Select exit record...</option>
                                    <?php foreach ($exits as $exit): ?>
                                    <option value="<?= $exit['exit_id'] ?>"><?= htmlspecialchars($exit['employee_name']) ?> - <?= date('M d, Y', strtotime($exit['exit_date'])) ?> (<?= htmlspecialchars($exit['exit_type']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="survey_date">Survey Date</label>
                                <input type="date" id="survey_date" name="survey_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="submitted_date">Submitted Date & Time</label>
                                <input type="datetime-local" id="submitted_date" name="submitted_date" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="satisfaction_rating">Satisfaction Rating</label>
                        <div class="rating-input" id="ratingStars">
                            <input type="radio" name="satisfaction_rating" id="star5" value="5">
                            <label for="star5" data-rating="5">⭐</label>
                            <input type="radio" name="satisfaction_rating" id="star4" value="4">
                            <label for="star4" data-rating="4">⭐</label>
                            <input type="radio" name="satisfaction_rating" id="star3" value="3">
                            <label for="star3" data-rating="3">⭐</label>
                            <input type="radio" name="satisfaction_rating" id="star2" value="2">
                            <label for="star2" data-rating="2">⭐</label>
                            <input type="radio" name="satisfaction_rating" id="star1" value="1">
                            <label for="star1" data-rating="1">⭐</label>
                        </div>
                        <small style="color: #666;">Click on a star to rate (1-5 stars)</small>
                    </div>

                    <div class="form-group">
                        <label for="survey_response">Survey Response</label>
                        <textarea id="survey_response" name="survey_response" class="form-control" placeholder="Enter detailed survey response here..."></textarea>
                        <small style="color: #666;">Include feedback, comments, and insights from the post-exit survey</small>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Survey</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Survey Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Survey Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be populated dynamically -->
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let surveysData = <?= json_encode($surveys) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('surveyTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Rating stars functionality
        const ratingStars = document.querySelectorAll('#ratingStars label');
        ratingStars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                updateStarDisplay(rating);
            });
        });

        function updateStarDisplay(rating) {
            const labels = document.querySelectorAll('#ratingStars label');
            labels.forEach(label => {
                const labelRating = parseInt(label.getAttribute('data-rating'));
                if (labelRating <= rating) {
                    label.classList.add('active');
                } else {
                    label.classList.remove('active');
                }
            });
        }

        // Modal functions
        function openModal(mode, surveyId = null) {
            const modal = document.getElementById('surveyModal');
            const form = document.getElementById('surveyForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Post-Exit Survey';
                action.value = 'add';
                form.reset();
                document.getElementById('survey_id').value = '';
                updateStarDisplay(0);
            } else if (mode === 'edit' && surveyId) {
                title.textContent = 'Edit Post-Exit Survey';
                action.value = 'update';
                document.getElementById('survey_id').value = surveyId;
                populateEditForm(surveyId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('surveyModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(surveyId) {
            const survey = surveysData.find(s => s.survey_id == surveyId);
            if (survey) {
                document.getElementById('employee_id').value = survey.employee_id || '';
                document.getElementById('exit_id').value = survey.exit_id || '';
                document.getElementById('survey_date').value = survey.survey_date || '';
                document.getElementById('survey_response').value = survey.survey_response || '';
                
                // Set submitted date
                if (survey.submitted_date) {
                    const date = new Date(survey.submitted_date);
                    const formattedDate = date.toISOString().slice(0, 16);
                    document.getElementById('submitted_date').value = formattedDate;
                }
                
                // Set rating
                const rating = survey.satisfaction_rating || 0;
                document.getElementById('star' + rating).checked = true;
                updateStarDisplay(rating);
            }
        }

        function editSurvey(surveyId) {
            openModal('edit', surveyId);
        }

        function deleteSurvey(surveyId) {
            if (confirm('Are you sure you want to delete this survey? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="survey_id" value="${surveyId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewSurvey(surveyId) {
            const survey = surveysData.find(s => s.survey_id == surveyId);
            if (survey) {
                const modalBody = document.getElementById('viewModalBody');
                const rating = survey.satisfaction_rating || 0;
                const stars = '⭐'.repeat(rating) + '☆'.repeat(5 - rating);
                
                modalBody.innerHTML = `
                    <div style="padding: 20px;">
                        <h3 style="color: var(--azure-blue); margin-bottom: 20px;">Survey Information</h3>
                        
                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--azure-blue-dark);">Employee:</strong>
                            <p style="margin: 5px 0;">${survey.employee_name} (${survey.employee_number})</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--azure-blue-dark);">Job Title:</strong>
                            <p style="margin: 5px 0;">${survey.job_title} - ${survey.department}</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--azure-blue-dark);">Exit Date:</strong>
                            <p style="margin: 5px 0;">${new Date(survey.exit_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--azure-blue-dark);">Exit Type:</strong>
                            <p style="margin: 5px 0;">${survey.exit_type}</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--azure-blue-dark);">Survey Date:</strong>
                            <p style="margin: 5px 0;">${new Date(survey.survey_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--azure-blue-dark);">Satisfaction Rating:</strong>
                            <p style="margin: 5px 0; font-size: 24px;">${stars} (${rating}/5)</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--azure-blue-dark);">Submitted Date:</strong>
                            <p style="margin: 5px 0;">${survey.submitted_date ? new Date(survey.submitted_date).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Not submitted'}</p>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--azure-blue-dark);">Survey Response:</strong>
                            <div class="survey-preview" style="margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px; max-height: 300px; overflow-y: auto;">
                                ${survey.survey_response || 'No response provided'}
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <button class="btn btn-primary" onclick="closeViewModal()">Close</button>
                        </div>
                    </div>
                `;
                
                document.getElementById('viewModal').style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const surveyModal = document.getElementById('surveyModal');
            const viewModal = document.getElementById('viewModal');
            if (event.target === surveyModal) {
                closeModal();
            } else if (event.target === viewModal) {
                closeViewModal();
            }
        }

        // Form validation
        document.getElementById('surveyForm').addEventListener('submit', function(e) {
            const rating = document.querySelector('input[name="satisfaction_rating"]:checked');
            if (!rating) {
                e.preventDefault();
                alert('Please select a satisfaction rating');
                return;
            }

            const surveyResponse = document.getElementById('survey_response').value;
            if (surveyResponse.length > 5000) {
                e.preventDefault();
                alert('Survey response is too long. Please limit to 5000 characters.');
                return;
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#surveyTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Set default date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('survey_date').value = today;
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
