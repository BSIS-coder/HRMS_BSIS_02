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
                    $stmt = $pdo->prepare("INSERT INTO learning_resources (resource_name, description, resource_type, resource_url, author, publication_date, duration, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['resource_type'],
                        $_POST['file_url'],
                        $_POST['author'],
                        $_POST['publication_date'],
                        $_POST['duration'],
                        $_POST['tags']
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
                    $stmt = $pdo->prepare("UPDATE learning_resources SET resource_name=?, description=?, resource_type=?, resource_url=?, author=?, publication_date=?, duration=?, tags=? WHERE resource_id=?");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['resource_type'],
                        $_POST['file_url'],
                        $_POST['author'],
                        $_POST['publication_date'],
                        $_POST['duration'],
                        $_POST['tags'],
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
    $stmt = $pdo->query("SELECT * FROM learning_resources ORDER BY resource_name");
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
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM learning_resources WHERE resource_type IS NOT NULL");
    $activeResources = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT resource_type) as categories FROM learning_resources");
    $resourceCategories = $stmt->fetch(PDO::FETCH_ASSOC)['categories'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as public FROM learning_resources WHERE resource_url IS NOT NULL");
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
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for learning resources page */
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

        .resource-type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-video {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-document {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .type-link {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .type-course {
            background: #fff3e0;
            color: #f57c00;
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
            padding: 12px 15px;
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
                <h2 class="section-title">Learning Resources Management</h2>
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

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchResources" placeholder="Search resources by name, type, or author..." onkeyup="searchResources()">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('resource')">
                            ➕ Add New Resource
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="resourcesTable">
                            <thead>
                                <tr>
                                    <th>Resource Name</th>
                                    <th>Type</th>
                                    <th>Author</th>
                                    <th>Description</th>
                                    <th>Duration</th>
                                    <th>Publication Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="resourceTableBody">
                                <?php foreach ($resources as $resource): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($resource['resource_name']); ?></strong></td>
                                    <td>
                                        <span class="resource-type-badge type-<?php echo strtolower(str_replace(' ', '-', $resource['resource_type'])); ?>">
                                            <?php echo htmlspecialchars($resource['resource_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($resource['author'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($resource['description'] ?? '', 0, 50)) . (strlen($resource['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars($resource['duration'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($resource['publication_date'] ?? 'N/A'); ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-small" onclick="editResource(<?php echo $resource['resource_id']; ?>, '<?php echo addslashes($resource['resource_name']); ?>', '<?php echo addslashes($resource['author']); ?>', '<?php echo $resource['resource_type']; ?>', '<?php echo addslashes($resource['resource_url']); ?>', '<?php echo $resource['publication_date']; ?>', '<?php echo addslashes($resource['duration']); ?>', '<?php echo addslashes($resource['description']); ?>', '<?php echo addslashes($resource['tags']); ?>')">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-danger btn-small" onclick="deleteResource(<?php echo $resource['resource_id']; ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($resources)): ?>
                        <div class="no-results">
                            <i class="fas fa-book"></i>
                            <h3>No resources found</h3>
                            <p>Start by adding your first learning resource.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Resource Modal -->
    <div id="resourceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="resourceModalTitle">Add New Learning Resource</h2>
                <span class="close" onclick="closeModal('resource')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="resourceForm" method="POST">
                    <input type="hidden" id="resource_action" name="action" value="add_resource">
                    <input type="hidden" id="resource_id" name="resource_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="title">Resource Name *</label>
                                <input type="text" id="title" name="title" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="author">Author *</label>
                                <input type="text" id="author" name="author" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="resource_type">Resource Type *</label>
                                <select id="resource_type" name="resource_type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="Book">Book</option>
                                    <option value="Online Course">Online Course</option>
                                    <option value="Video">Video</option>
                                    <option value="Article">Article</option>
                                    <option value="Webinar">Webinar</option>
                                    <option value="Podcast">Podcast</option>
                                    <option value="Document">Document</option>
                                    <option value="Link">Link</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="file_url">Resource URL *</label>
                                <input type="url" id="file_url" name="file_url" class="form-control" required placeholder="https://example.com/resource.pdf">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="publication_date">Publication Date</label>
                                <input type="date" id="publication_date" name="publication_date" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="duration">Duration</label>
                                <input type="text" id="duration" name="duration" class="form-control" placeholder="e.g., 2 hours, 300 pages">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Brief description of the resource"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" class="form-control" placeholder="e.g., leadership, management, skills (comma separated)">
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('resource')">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Resource</button>
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
        // Modal functions
        function openModal(type) {
            if (type === 'resource') {
                document.getElementById('resourceModal').style.display = 'block';
                document.getElementById('resourceModalTitle').textContent = 'Add New Learning Resource';
                document.getElementById('resource_action').value = 'add_resource';
                document.getElementById('resourceForm').reset();
            }
        }

        function closeModal(type) {
            if (type === 'resource') {
                document.getElementById('resourceModal').style.display = 'none';
            }
        }

        function editResource(id, title, author, resourceType, fileUrl, publicationDate, duration, description, tags) {
            document.getElementById('resourceModal').style.display = 'block';
            document.getElementById('resourceModalTitle').textContent = 'Edit Learning Resource';
            document.getElementById('resource_action').value = 'update_resource';
            document.getElementById('resource_id').value = id;
            document.getElementById('title').value = title;
            document.getElementById('author').value = author;
            document.getElementById('resource_type').value = resourceType;
            document.getElementById('file_url').value = fileUrl;
            document.getElementById('publication_date').value = publicationDate;
            document.getElementById('duration').value = duration;
            document.getElementById('description').value = description;
            document.getElementById('tags').value = tags;
        }

        function deleteResource(id) {
            if (confirm('Are you sure you want to delete this resource?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_resource"><input type="hidden" name="resource_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Search functionality
        function searchResources() {
            var input = document.getElementById('searchResources');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('resourcesTable');
            var tr = table.getElementsByTagName('tr');

            for (var i = 1; i < tr.length; i++) {
                var td = tr[i].getElementsByTagName('td');
                var found = false;
                for (var j = 0; j < td.length; j++) {
                    if (td[j]) {
                        var txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('resourceModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Show message modal if there's a message
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            alert('<?php echo addslashes($message); ?>');
        });
        <?php endif; ?>
    </script>
</body>
</html>



