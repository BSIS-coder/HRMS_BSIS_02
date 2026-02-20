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
        
        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .btn-primary {
            background: var(--azure-blue);
            border-color: var(--azure-blue);
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            font-weight: 600;
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Training Courses Management</h2>
                
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

                <!-- Controls -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="input-group" style="max-width: 400px;">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control" id="courseSearch" placeholder="Search courses...">
                    </div>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addCourseModal">
                        <i class="fas fa-plus"></i> Add Course
                    </button>
                </div>

                <!-- Courses Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Training Courses List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
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
                                <tbody>
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
                                            <button class="btn btn-sm btn-outline-primary" onclick="editCourse(<?php echo $course['course_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCourse(<?php echo $course['course_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Training Course</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_course">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Course Name *</label>
                                    <input type="text" class="form-control" name="course_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Category *</label>
                                    <select class="form-control" name="category" required>
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Duration (Hours) *</label>
                                    <input type="number" class="form-control" name="duration" min="0.5" step="0.5" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Delivery Method *</label>
                                    <select class="form-control" name="delivery_method" required>
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
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the course"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Prerequisites</label>
                            <textarea class="form-control" name="prerequisites" rows="2" placeholder="Any prerequisites for this course"></textarea>
                        </div>
                                                 <div class="form-group">
                             <label>Max Participants</label>
                             <input type="number" class="form-control" name="max_participants" min="1" placeholder="Maximum number of participants">
                         </div>
                         <div class="form-group">
                             <label>Materials URL</label>
                             <input type="url" class="form-control" name="materials_url" placeholder="Link to course materials">
                         </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Training Course</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_course">
                        <input type="hidden" name="course_id" id="edit_course_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Course Name *</label>
                                    <input type="text" class="form-control" name="course_name" id="edit_course_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Category *</label>
                                    <select class="form-control" name="category" id="edit_category" required>
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Duration (Hours) *</label>
                                    <input type="number" class="form-control" name="duration" id="edit_duration" min="0.5" step="0.5" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Delivery Method *</label>
                                    <select class="form-control" name="delivery_method" id="edit_delivery_method" required>
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
                            <label>Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3" placeholder="Brief description of the course"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Prerequisites</label>
                            <textarea class="form-control" name="prerequisites" id="edit_prerequisites" rows="2" placeholder="Any prerequisites for this course"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Max Participants</label>
                            <input type="number" class="form-control" name="max_participants" id="edit_max_participants" min="1" placeholder="Maximum number of participants">
                        </div>
                        <div class="form-group">
                            <label>Materials URL</label>
                            <input type="url" class="form-control" name="materials_url" id="edit_materials_url" placeholder="Link to course materials">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status" id="edit_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success/Error Message Modal -->
    <?php if ($message): ?>
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header <?php echo $messageType === 'success' ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <h5 class="modal-title">
                        <?php echo $messageType === 'success' ? '<i class="fas fa-check-circle"></i> Success' : '<i class="fas fa-exclamation-circle"></i> Error'; ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Show message modal if there's a message
        <?php if ($message): ?>
        $(document).ready(function() {
            $('#messageModal').modal('show');
        });
        <?php endif; ?>

        // Search functionality
        $('#courseSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Edit course function
        function editCourse(courseId) {
            // Fetch course data and populate the edit modal
            $.ajax({
                url: 'get_course.php',
                type: 'GET',
                data: { course_id: courseId },
                dataType: 'json',
                success: function(course) {
                    $('#edit_course_id').val(course.course_id);
                    $('#edit_course_name').val(course.course_name);
                    $('#edit_category').val(course.category);
                    $('#edit_duration').val(course.duration);
                    $('#edit_delivery_method').val(course.delivery_method);
                    $('#edit_description').val(course.description);
                    $('#edit_prerequisites').val(course.prerequisites);
                    $('#edit_max_participants').val(course.max_participants);
                    $('#edit_materials_url').val(course.materials_url);
                    $('#edit_status').val(course.status);
                    
                    $('#editCourseModal').modal('show');
                },
                error: function() {
                    alert('Error fetching course data');
                }
            });
        }

        // Delete course function
        function deleteCourse(courseId) {
            if (confirm('Are you sure you want to delete this training course?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="course_id" value="${courseId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
