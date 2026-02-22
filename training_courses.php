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
require_once 'config.php';

// Use the global database connection
$pdo = $conn;

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_course':
                try {
                    $stmt = $pdo->prepare("INSERT INTO training_courses (course_name, description, category, delivery_method, duration, max_participants, prerequisites, materials_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['course_name'],
                        $_POST['description'],
                        $_POST['category'],
                        $_POST['delivery_method'],
                        $_POST['duration'],
                        $_POST['max_participants'] ?? null,
                        $_POST['prerequisites'],
                        $_POST['materials_url'] ?? '',
                        $_POST['status']
                    ]);
                    $message = "Training course added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding course: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'edit_course':
                try {
                    $stmt = $pdo->prepare("UPDATE training_courses SET course_name=?, description=?, category=?, delivery_method=?, duration=?, max_participants=?, prerequisites=?, materials_url=?, status=? WHERE course_id=?");
                    $stmt->execute([
                        $_POST['course_name'],
                        $_POST['description'],
                        $_POST['category'],
                        $_POST['delivery_method'],
                        $_POST['duration'],
                        $_POST['max_participants'] ?? null,
                        $_POST['prerequisites'],
                        $_POST['materials_url'] ?? '',
                        $_POST['status'],
                        $_POST['course_id']
                    ]);
                    $message = "Training course updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating course: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_course':
                try {
                    $stmt = $pdo->prepare("DELETE FROM training_courses WHERE course_id=?");
                    $stmt->execute([$_POST['course_id']]);
                    $message = "Training course deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting course: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch training courses
try {
    $stmt = $pdo->query("SELECT * FROM training_courses ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $courses = [];
    $message = "Error fetching courses: " . $e->getMessage();
    $messageType = "error";
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_courses");
    $totalCourses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM training_courses WHERE status = 'Active'");
    $activeCourses = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT category) as categories FROM training_courses");
    $courseCategories = $stmt->fetch(PDO::FETCH_ASSOC)['categories'];
    
    $stmt = $pdo->query("SELECT SUM(duration) as total_hours FROM training_courses WHERE status = 'Active'");
    $totalHours = $stmt->fetch(PDO::FETCH_ASSOC)['total_hours'] ?: 0;
} catch (PDOException $e) {
    $totalCourses = 0;
    $activeCourses = 0;
    $courseCategories = 0;
    $totalHours = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Courses Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for training courses page */
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

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
            margin: 0 3px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .stats-card i {
            font-size: 3rem;
            color: var(--azure-blue);
            margin-bottom: 15px;
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

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
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

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
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
                <h2 class="section-title">Training Courses Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-graduation-cap"></i>
                                <h3><?php echo $totalCourses; ?></h3>
                                <h6>Total Courses</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-check-circle"></i>
                                <h3><?php echo $activeCourses; ?></h3>
                                <h6>Active Courses</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-tags"></i>
                                <h3><?php echo $courseCategories; ?></h3>
                                <h6>Categories</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-clock"></i>
                                <h3><?php echo $totalHours; ?></h3>
                                <h6>Total Hours</h6>
                            </div>
                        </div>
                    </div>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search courses by name, category, or delivery method...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add New Course
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="courseTable">
                            <thead>
                                <tr>
                                    <th>Course Name</th>
                                    <th>Category</th>
                                    <th>Duration</th>
                                    <th>Delivery Method</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="courseTableBody">
                                <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['category']); ?></td>
                                    <td><?php echo $course['duration']; ?> hours</td>
                                    <td><?php echo htmlspecialchars($course['delivery_method']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($course['description'], 0, 50)) . (strlen($course['description']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($course['status']); ?>">
                                            <?php echo htmlspecialchars($course['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editCourse(<?php echo $course['course_id']; ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteCourse(<?php echo $course['course_id']; ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($courses)): ?>
                        <div class="no-results">
                            <i class="fas fa-graduation-cap"></i>
                            <h3>No courses found</h3>
                            <p>Start by adding your first training course.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Course Modal -->
    <div id="courseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Course</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="courseForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add_course">
                    <input type="hidden" id="course_id" name="course_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="course_name">Course Name *</label>
                                <input type="text" id="course_name" name="course_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="category">Category *</label>
                                <select id="category" name="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <option value="Leadership Development">Leadership Development</option>
                                    <option value="Technical Skills">Technical Skills</option>
                                    <option value="Soft Skills">Soft Skills</option>
                                    <option value="Compliance Training">Compliance Training</option>
                                    <option value="Safety Training">Safety Training</option>
                                    <option value="Customer Service">Customer Service</option>
                                    <option value="Project Management">Project Management</option>
                                    <option value="Communication Skills">Communication Skills</option>
                                    <option value="Digital Literacy">Digital Literacy</option>
                                    <option value="Administrative Skills">Administrative Skills</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="duration">Duration (Hours) *</label>
                                <input type="number" id="duration" name="duration" class="form-control" min="0.5" step="0.5" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="delivery_method">Delivery Method *</label>
                                <select id="delivery_method" name="delivery_method" class="form-control" required>
                                    <option value="">Select Method</option>
                                    <option value="Classroom Training">Classroom Training</option>
                                    <option value="Online Learning">Online Learning</option>
                                    <option value="Blended Learning">Blended Learning</option>
                                    <option value="Workshop">Workshop</option>
                                    <option value="Seminar">Seminar</option>
                                    <option value="Webinar">Webinar</option>
                                    <option value="Self-Paced">Self-Paced</option>
                                    <option value="On-the-Job Training">On-the-Job Training</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Brief description of the course"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="prerequisites">Prerequisites</label>
                        <textarea id="prerequisites" name="prerequisites" class="form-control" rows="2" placeholder="Any prerequisites for this course"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="max_participants">Max Participants</label>
                                <input type="number" id="max_participants" name="max_participants" class="form-control" min="1" placeholder="Maximum number of participants">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="status">Status *</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="materials_url">Materials URL</label>
                        <input type="url" id="materials_url" name="materials_url" class="form-control" placeholder="Link to course materials">
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let coursesData = <?= json_encode($courses) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('courseTableBody');
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

        // Modal functions
        function openModal(mode, courseId = null) {
            const modal = document.getElementById('courseModal');
            const form = document.getElementById('courseForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Course';
                action.value = 'add_course';
                form.reset();
                document.getElementById('course_id').value = '';
            } else if (mode === 'edit' && courseId) {
                title.textContent = 'Edit Course';
                action.value = 'edit_course';
                document.getElementById('course_id').value = courseId;
                populateEditForm(courseId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('courseModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(courseId) {
            const course = coursesData.find(c => c.course_id == courseId);
            if (course) {
                document.getElementById('course_name').value = course.course_name || '';
                document.getElementById('category').value = course.category || '';
                document.getElementById('duration').value = course.duration || '';
                document.getElementById('delivery_method').value = course.delivery_method || '';
                document.getElementById('description').value = course.description || '';
                document.getElementById('prerequisites').value = course.prerequisites || '';
                document.getElementById('max_participants').value = course.max_participants || '';
                document.getElementById('status').value = course.status || '';
                document.getElementById('materials_url').value = course.materials_url || '';
            }
        }

        function editCourse(courseId) {
            openModal('edit', courseId);
        }

        function deleteCourse(courseId) {
            if (confirm('Are you sure you want to delete this training course? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="course_id" value="${courseId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('courseModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Form validation
        document.getElementById('courseForm').addEventListener('submit', function(e) {
            const duration = document.getElementById('duration').value;
            if (duration <= 0) {
                e.preventDefault();
                alert('Duration must be greater than 0');
                return;
            }

            const materialsUrl = document.getElementById('materials_url').value;
            if (materialsUrl && !isValidUrl(materialsUrl)) {
                e.preventDefault();
                alert('Please enter a valid URL for materials');
                return;
            }
        });

        function isValidUrl(url) {
            try {
                new URL(url);
                return true;
            } catch {
                return false;
            }
        }

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
            const tableRows = document.querySelectorAll('#courseTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
