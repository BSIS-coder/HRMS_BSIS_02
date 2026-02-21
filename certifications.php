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
            case 'add_certification':
                try {
                    $stmt = $pdo->prepare("INSERT INTO employee_skills (employee_id, skill_id, proficiency_level, assessed_date, certification_url, expiry_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['skill_id'],
                        $_POST['proficiency_level'],
                        $_POST['assessed_date'],
                        $_POST['certification_url'],
                        $_POST['expiry_date'],
                        $_POST['notes']
                    ]);
                    $message = "Certification added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding certification: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update_certification':
                try {
                    $stmt = $pdo->prepare("UPDATE employee_skills SET proficiency_level = ?, assessed_date = ?, certification_url = ?, expiry_date = ?, notes = ? WHERE employee_skill_id = ?");
                    $stmt->execute([
                        $_POST['proficiency_level'],
                        $_POST['assessed_date'],
                        $_POST['certification_url'],
                        $_POST['expiry_date'],
                        $_POST['notes'],
                        $_POST['employee_skill_id']
                    ]);
                    $message = "Certification updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating certification: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_certification':
                try {
                    $stmt = $pdo->prepare("DELETE FROM employee_skills WHERE employee_skill_id=?");
                    $stmt->execute([$_POST['employee_skill_id']]);
                    $message = "Certification deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting certification: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch certifications with details
try {
    $stmt = $pdo->query("
        SELECT es.*, e.first_name, e.last_name, s.skill_name, s.category 
        FROM employee_skills es 
        JOIN employee_profiles ep ON es.employee_id = ep.employee_id 
        JOIN personal_information e ON ep.personal_info_id = e.personal_info_id 
        JOIN skill_matrix s ON es.skill_id = s.skill_id 
        WHERE es.certification_url IS NOT NULL AND es.certification_url != ''
        ORDER BY es.expiry_date ASC
    ");
    $certifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $certifications = [];
    $message = "Error fetching certifications: " . $e->getMessage();
    $messageType = "error";
}

// Fetch employees for dropdowns
try {
    $stmt = $pdo->query("
        SELECT ep.employee_id, pi.first_name, pi.last_name 
        FROM employee_profiles ep 
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id 
        ORDER BY pi.last_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}

// Fetch skills for dropdowns
try {
    $stmt = $pdo->query("SELECT * FROM skill_matrix ORDER BY skill_name");
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $skills = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_skills WHERE certification_url IS NOT NULL AND certification_url != ''");
    $totalCertifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_skills WHERE certification_url IS NOT NULL AND certification_url != '' AND expiry_date >= CURDATE()");
    $activeCertifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_skills WHERE certification_url IS NOT NULL AND certification_url != '' AND expiry_date < CURDATE()");
    $expiredCertifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_skills WHERE certification_url IS NOT NULL AND certification_url != '' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $expiringSoon = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $totalCertifications = 0;
    $activeCertifications = 0;
    $expiredCertifications = 0;
    $expiringSoon = 0;
}

// Function to get status badge class
function getStatusBadgeClass($expiryDate) {
    if (!$expiryDate) return 'status-unknown';
    
    $expiry = new DateTime($expiryDate);
    $today = new DateTime();
    $diff = $today->diff($expiry);
    
    if ($expiry < $today) {
        return 'status-expired';
    } elseif ($diff->days <= 30) {
        return 'status-expiring';
    } else {
        return 'status-active';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certifications Management - HR System</title>
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

        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }

        body { background: var(--azure-blue-pale); }

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
            transition: all 0.2s ease;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-expiring { background: #fff3cd; color: #856404; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-unknown { background: #e2e3e5; color: #383d41; }

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

        .modal-header h2 { margin: 0; }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
        }

        .close:hover { opacity: 1; }

        .modal-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .form-control {
            width: 100%;
            padding: 8px 15px;
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

        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }

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
            .controls { flex-direction: column; align-items: stretch; }
            .search-box { max-width: none; }
            .form-row { flex-direction: column; }
            .table-container { overflow-x: auto; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Certifications Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error'; ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-certificate"></i>
                                <h3><?php echo $totalCertifications; ?></h3>
                                <h6>Total Certifications</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-check-circle"></i>
                                <h3><?php echo $activeCertifications; ?></h3>
                                <h6>Active</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3><?php echo $expiringSoon; ?></h3>
                                <h6>Expiring Soon (30 days)</h6>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <i class="fas fa-times-circle"></i>
                                <h3><?php echo $expiredCertifications; ?></h3>
                                <h6>Expired</h6>
                            </div>
                        </div>
                    </div>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by employee, certification, or category...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add Certification
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="certTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Certification</th>
                                    <th>Category</th>
                                    <th>Level</th>
                                    <th>Assessed</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="certTableBody">
                                <?php foreach ($certifications as $cert): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($cert['skill_name']); ?></td>
                                    <td><?php echo htmlspecialchars($cert['category'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($cert['proficiency_level']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($cert['assessed_date'])); ?></td>
                                    <td><?php echo $cert['expiry_date'] ? date('M d, Y', strtotime($cert['expiry_date'])) : '—'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusBadgeClass($cert['expiry_date']); ?>">
                                            <?php
                                            if (!$cert['expiry_date']) echo 'No Expiry';
                                            elseif (new DateTime($cert['expiry_date']) < new DateTime()) echo 'Expired';
                                            elseif ((new DateTime($cert['expiry_date']))->diff(new DateTime())->days <= 30) echo 'Expiring Soon';
                                            else echo 'Active';
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($cert['certification_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($cert['certification_url']); ?>" target="_blank" class="btn btn-small btn-primary" title="View certificate"><i class="fas fa-external-link-alt"></i></a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-warning btn-small" onclick="editCertification(<?php echo (int)$cert['employee_skill_id']; ?>)">✏️ Edit</button>
                                        <button type="button" class="btn btn-danger btn-small" onclick="deleteCertification(<?php echo (int)$cert['employee_skill_id']; ?>)">🗑️ Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (empty($certifications)): ?>
                        <div class="no-results">
                            <i class="fas fa-certificate"></i>
                            <h3>No certifications found</h3>
                            <p>Add certifications linked to employee skills with a certificate URL.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Certification Modal -->
    <div id="certModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="certModalTitle">Add Certification</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="certForm" method="POST">
                    <input type="hidden" id="certAction" name="action" value="add_certification">
                    <input type="hidden" id="employee_skill_id" name="employee_skill_id" value="">

                    <div class="form-group">
                        <label>Employee *</label>
                        <select class="form-control" id="cert_employee_id" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['employee_id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Certification/Skill *</label>
                        <select class="form-control" id="cert_skill_id" name="skill_id" required>
                            <option value="">Select Certification</option>
                            <?php foreach ($skills as $skill): ?>
                            <option value="<?php echo $skill['skill_id']; ?>">
                                <?php echo htmlspecialchars($skill['skill_name'] . (isset($skill['category']) && $skill['category'] ? ' (' . $skill['category'] . ')' : '')); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Proficiency Level *</label>
                                <select class="form-control" id="cert_proficiency_level" name="proficiency_level" required>
                                    <option value="">Select Level</option>
                                    <option value="Beginner">Beginner</option>
                                    <option value="Intermediate">Intermediate</option>
                                    <option value="Advanced">Advanced</option>
                                    <option value="Expert">Expert</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Assessment Date *</label>
                                <input type="date" class="form-control" id="cert_assessed_date" name="assessed_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Certificate URL</label>
                        <input type="url" class="form-control" id="cert_certification_url" name="certification_url" placeholder="Link to digital certificate">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" class="form-control" id="cert_expiry_date" name="expiry_date">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" id="cert_notes" name="notes" rows="3" placeholder="Additional notes"></textarea>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        var certsData = <?= json_encode($certifications) ?>;

        document.getElementById('searchInput').addEventListener('input', function() {
            var term = this.value.toLowerCase();
            var rows = document.querySelectorAll('#certTableBody tr');
            rows.forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().indexOf(term) > -1 ? '' : 'none';
            });
        });

        function openModal(mode, id) {
            var modal = document.getElementById('certModal');
            var form = document.getElementById('certForm');
            var title = document.getElementById('certModalTitle');
            var action = document.getElementById('certAction');
            document.getElementById('employee_skill_id').value = '';
            if (mode === 'add') {
                title.textContent = 'Add Certification';
                action.value = 'add_certification';
                form.reset();
                document.getElementById('cert_employee_id').removeAttribute('readonly');
                document.getElementById('cert_employee_id').disabled = false;
                document.getElementById('cert_skill_id').disabled = false;
            } else if (mode === 'edit' && id) {
                title.textContent = 'Edit Certification';
                action.value = 'update_certification';
                document.getElementById('employee_skill_id').value = id;
                var c = certsData.find(function(x) { return x.employee_skill_id == id; });
                if (c) {
                    document.getElementById('cert_employee_id').value = c.employee_id;
                    document.getElementById('cert_employee_id').disabled = true;
                    document.getElementById('cert_skill_id').value = c.skill_id;
                    document.getElementById('cert_skill_id').disabled = true;
                    document.getElementById('cert_proficiency_level').value = c.proficiency_level || '';
                    document.getElementById('cert_assessed_date').value = c.assessed_date || '';
                    document.getElementById('cert_certification_url').value = c.certification_url || '';
                    document.getElementById('cert_expiry_date').value = c.expiry_date || '';
                    document.getElementById('cert_notes').value = c.notes || '';
                }
            }
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('certModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function editCertification(id) {
            openModal('edit', id);
        }

        function deleteCertification(id) {
            if (confirm('Are you sure you want to delete this certification?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_certification"><input type="hidden" name="employee_skill_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        window.onclick = function(e) {
            if (e.target.id === 'certModal') closeModal();
        };

        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(a) {
                a.style.transition = 'opacity 0.5s';
                a.style.opacity = '0';
                setTimeout(function() { a.remove(); }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
