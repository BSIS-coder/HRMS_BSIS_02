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
            case 'add_resource':
                try {
                    $stmt = $pdo->prepare("INSERT INTO learning_resources (title, description, resource_type, file_url, category, tags, is_public, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['resource_type'],
                        $_POST['file_url'],
                        $_POST['category'],
                        $_POST['tags'],
                        isset($_POST['is_public']) ? 1 : 0,
                        $_POST['status']
                    ]);
                    $message = "Learning resource added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding resource: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'edit_resource':
                try {
                    $stmt = $pdo->prepare("UPDATE learning_resources SET title=?, description=?, resource_type=?, file_url=?, category=?, tags=?, is_public=?, status=? WHERE resource_id=?");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['resource_type'],
                        $_POST['file_url'],
                        $_POST['category'],
                        $_POST['tags'],
                        isset($_POST['is_public']) ? 1 : 0,
                        $_POST['status'],
                        $_POST['resource_id']
                    ]);
                    $message = "Learning resource updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating resource: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_resource':
                try {
                    $stmt = $pdo->prepare("DELETE FROM learning_resources WHERE resource_id=?");
                    $stmt->execute([$_POST['resource_id']]);
                    $message = "Learning resource deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting resource: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch learning resources
try {
    $stmt = $pdo->query("SELECT * FROM learning_resources ORDER BY title");
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $resources = [];
    $message = "Error fetching resources: " . $e->getMessage();
    $messageType = "error";
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM learning_resources");
    $totalResources = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM learning_resources WHERE status = 'Active'");
    $activeResources = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT category) as categories FROM learning_resources");
    $resourceCategories = $stmt->fetch(PDO::FETCH_ASSOC)['categories'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as public FROM learning_resources WHERE is_public = 1");
    $publicResources = $stmt->fetch(PDO::FETCH_ASSOC)['public'];
} catch (PDOException $e) {
    $totalResources = 0;
    $activeResources = 0;
    $resourceCategories = 0;
    $publicResources = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Resources Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Learning Resources Management</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-book"></i>
                            <h3><?php echo $totalResources; ?></h3>
                            <h6>Total Resources</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-check-circle"></i>
                            <h3><?php echo $activeResources; ?></h3>
                            <h6>Active Resources</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-tags"></i>
                            <h3><?php echo $resourceCategories; ?></h3>
                            <h6>Categories</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-globe"></i>
                            <h3><?php echo $publicResources; ?></h3>
                            <h6>Public Resources</h6>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="input-group" style="max-width: 400px;">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control" id="resourceSearch" placeholder="Search resources...">
                    </div>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addResourceModal">
                        <i class="fas fa-plus"></i> Add Resource
                    </button>
                </div>

                <!-- Resources Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-book"></i> Learning Resources List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Access</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($resource['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($resource['category']); ?></td>
                                        <td><?php echo htmlspecialchars($resource['resource_type']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($resource['description'], 0, 50)) . (strlen($resource['description']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $resource['is_public'] ? 'success' : 'warning'; ?>">
                                                <?php echo $resource['is_public'] ? 'Public' : 'Private'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($resource['status']); ?>">
                                                <?php echo htmlspecialchars($resource['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editResource(<?php echo $resource['resource_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteResource(<?php echo $resource['resource_id']; ?>)">
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

    <!-- Add Resource Modal -->
    <div class="modal fade" id="addResourceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Learning Resource</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_resource">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Title *</label>
                                    <input type="text" class="form-control" name="title" required>
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
                                    <label>Resource Type *</label>
                                    <select class="form-control" name="resource_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Document">Document</option>
                                        <option value="Video">Video</option>
                                        <option value="Presentation">Presentation</option>
                                        <option value="Manual">Manual</option>
                                        <option value="Guide">Guide</option>
                                        <option value="Template">Template</option>
                                        <option value="Checklist">Checklist</option>
                                        <option value="Assessment">Assessment</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>File URL *</label>
                                    <input type="url" class="form-control" name="file_url" required placeholder="https://example.com/resource.pdf">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the resource"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Tags</label>
                            <input type="text" class="form-control" name="tags" placeholder="e.g., leadership, management, skills (comma separated)">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_public" id="isPublic" checked>
                                    <label class="form-check-label" for="isPublic">Public Resource</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" name="status">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Resource</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Resource Modal -->
    <div class="modal fade" id="editResourceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Learning Resource</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_resource">
                        <input type="hidden" name="resource_id" id="edit_resource_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Title *</label>
                                    <input type="text" class="form-control" name="title" id="edit_title" required>
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
                                    <label>Resource Type *</label>
                                    <select class="form-control" name="resource_type" id="edit_resource_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Document">Document</option>
                                        <option value="Video">Video</option>
                                        <option value="Presentation">Presentation</option>
                                        <option value="Manual">Manual</option>
                                        <option value="Guide">Guide</option>
                                        <option value="Template">Template</option>
                                        <option value="Checklist">Checklist</option>
                                        <option value="Assessment">Assessment</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>File URL *</label>
                                    <input type="url" class="form-control" name="file_url" id="edit_file_url" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Tags</label>
                            <input type="text" class="form-control" name="tags" id="edit_tags">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="is_public" id="edit_is_public">
                                    <label class="form-check-label" for="edit_is_public">Public Resource</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" name="status" id="edit_status">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Update Resource</button>
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
        $('#resourceSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('table tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Edit resource function
        function editResource(resourceId) {
            // Fetch resource data and populate the edit modal
            $.ajax({
                url: 'get_resource.php',
                type: 'GET',
                data: { resource_id: resourceId },
                dataType: 'json',
                success: function(resource) {
                    $('#edit_resource_id').val(resource.resource_id);
                    $('#edit_title').val(resource.title);
                    $('#edit_category').val(resource.category);
                    $('#edit_resource_type').val(resource.resource_type);
                    $('#edit_file_url').val(resource.file_url);
                    $('#edit_description').val(resource.description);
                    $('#edit_tags').val(resource.tags);
                    $('#edit_is_public').prop('checked', resource.is_public == 1);
                    $('#edit_status').val(resource.status);
                    
                    $('#editResourceModal').modal('show');
                },
                error: function() {
                    alert('Error fetching resource data');
                }
            });
        }

        // Delete resource function
        function deleteResource(resourceId) {
            if (confirm('Are you sure you want to delete this learning resource?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_resource">
                    <input type="hidden" name="resource_id" value="${resourceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
