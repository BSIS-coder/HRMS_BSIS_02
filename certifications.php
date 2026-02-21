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

// Pull flash message from session (Post-Redirect-Get)
$message = '';
$messageType = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Handle form submissions
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
                        $_POST['certification_url'] ?: null,
                        (isset($_POST['expiry_date']) && $_POST['expiry_date'] !== '') ? $_POST['expiry_date'] : null,
                        $_POST['notes'] ?: null
                    ]);
                    $_SESSION['flash_message'] = "Certification added successfully!";
                    $_SESSION['flash_type'] = 'success';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error adding certification: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: certifications.php');
                exit();
                break;

            case 'update_certification':
                try {
                    $stmt = $pdo->prepare("UPDATE employee_skills SET proficiency_level = ?, assessed_date = ?, certification_url = ?, expiry_date = ?, notes = ? WHERE employee_skill_id = ?");
                    $stmt->execute([
                        $_POST['proficiency_level'],
                        $_POST['assessed_date'],
                        $_POST['certification_url'] ?: null,
                        (isset($_POST['expiry_date']) && $_POST['expiry_date'] !== '') ? $_POST['expiry_date'] : null,
                        $_POST['notes'] ?: null,
                        $_POST['employee_skill_id']
                    ]);
                    $_SESSION['flash_message'] = "Certification updated successfully!";
                    $_SESSION['flash_type'] = 'success';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error updating certification: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: certifications.php');
                exit();
                break;

            case 'delete_certification':
                try {
                    $stmt = $pdo->prepare("DELETE FROM employee_skills WHERE employee_skill_id=?");
                    $stmt->execute([$_POST['employee_skill_id']]);
                    $_SESSION['flash_message'] = "Certification deleted successfully!";
                    $_SESSION['flash_type'] = 'success';
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Error deleting certification: " . $e->getMessage();
                    $_SESSION['flash_type'] = 'error';
                }
                header('Location: certifications.php');
                exit();
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
        /* Match career_paths / skill_matrix L&D frontend */
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

        .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: white; }

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
            margin-bottom: 30px;
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

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }

        .modal-content { background: white; margin: 5% auto; padding: 0; border-radius: 15px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: slideIn 0.3s ease; }

        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; padding: 20px 30px; border-radius: 15px 15px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header .close { cursor: pointer; font-size: 28px; line-height: 1; opacity: 0.9; }

        .modal-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }

        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--azure-blue-dark); }

        .form-control { width: 100%; padding: 6px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: all 0.3s ease; }

        .form-control:focus { border-color: var(--azure-blue); outline: none; box-shadow: 0 0 10px rgba(233, 30, 99, 0.3); }

        .form-row { display: flex; gap: 20px; flex-wrap: wrap; }
        .form-col { flex: 1; min-width: 0; }

        @media (max-width: 768px) {
            .controls { flex-direction: column; align-items: stretch; }
            .search-box { max-width: none; }
            .form-row { flex-direction: column; }
            .table-container { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>

        <!-- Flash modal -->
        <div id="flashModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="flashModalTitle">Notification</h2>
                    <span class="close" onclick="closeFlashModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <p id="flashMessageText"><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        </div>

        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Certifications Management</h2>

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

                <!-- Controls -->
                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">&#128269;</span>
                        <input type="text" id="certificationSearch" placeholder="Search certifications..." onkeyup="searchCertifications()">
                    </div>
                    <button class="btn btn-primary" onclick="openModal('addCertification')">
                        &#10133; Add Certification
                    </button>
                </div>

                <!-- Certifications Table -->
                <div class="table-container">
                    <table class="table" id="certificationsTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Certification</th>
                                <th>Category</th>
                                <th>Level</th>
                                <th>Assessed Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($certifications)): ?>
                            <tr>
                                <td colspan="8" class="no-results">
                                    <i class="fas fa-certificate"></i>
                                    <h3>No certifications found</h3>
                                    <p>Add certifications linked to employee skills (with certificate URL).</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($certifications as $cert): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($cert['skill_name']); ?></td>
                                <td><?php echo htmlspecialchars($cert['category'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($cert['proficiency_level']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($cert['assessed_date'])); ?></td>
                                <td><?php echo ($cert['expiry_date'] ?? '') ? date('M d, Y', strtotime($cert['expiry_date'])) : 'No Expiry'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo getStatusBadgeClass($cert['expiry_date'] ?? null); ?>">
                                        <?php
                                        if (empty($cert['expiry_date'])) {
                                            echo 'No Expiry';
                                        } elseif (new DateTime($cert['expiry_date']) < new DateTime()) {
                                            echo 'Expired';
                                        } elseif ((new DateTime($cert['expiry_date']))->diff(new DateTime())->days <= 30) {
                                            echo 'Expiring Soon';
                                        } else {
                                            echo 'Active';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($cert['certification_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($cert['certification_url']); ?>" target="_blank" class="btn btn-small btn-primary" title="View certificate">&#128279;</a>
                                    <?php endif; ?>
                                    <button class="btn btn-warning btn-small" onclick="editCertification(<?php echo (int)$cert['employee_skill_id']; ?>, '<?php echo addslashes($cert['proficiency_level']); ?>', '<?php echo (isset($cert['assessed_date']) ? substr($cert['assessed_date'], 0, 10) : ''); ?>', '<?php echo addslashes($cert['certification_url'] ?? ''); ?>', '<?php echo (isset($cert['expiry_date']) && $cert['expiry_date']) ? substr($cert['expiry_date'], 0, 10) : ''; ?>', '<?php echo addslashes($cert['notes'] ?? ''); ?>')">&#9998; Edit</button>
                                    <button class="btn btn-danger btn-small" onclick="deleteCertification(<?php echo (int)$cert['employee_skill_id']; ?>)">&#128465; Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Certification Modal -->
    <div id="addCertificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Certification</h2>
                <span class="close" onclick="closeModal('addCertification')">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_certification">
                    <div class="form-group">
                        <label>Employee *</label>
                        <select class="form-control" name="employee_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Certification/Skill *</label>
                        <select class="form-control" name="skill_id" required>
                            <option value="">Select Certification</option>
                            <?php foreach ($skills as $skill): ?>
                            <option value="<?php echo $skill['skill_id']; ?>"><?php echo htmlspecialchars($skill['skill_name'] . ' (' . ($skill['category'] ?? '') . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Proficiency Level *</label>
                                <select class="form-control" name="proficiency_level" required>
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
                                <input type="date" class="form-control" name="assessed_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Certificate URL</label>
                        <input type="url" class="form-control" name="certification_url" placeholder="Link to digital certificate">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" class="form-control" name="expiry_date">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes"></textarea>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('addCertification')">Cancel</button>
                        <button type="submit" class="btn btn-success">&#128190; Save Certification</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Certification Modal -->
    <div id="editCertificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Certification</h2>
                <span class="close" onclick="closeModal('editCertification')">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_certification">
                    <input type="hidden" name="employee_skill_id" id="edit_employee_skill_id">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Proficiency Level *</label>
                                <select class="form-control" name="proficiency_level" id="edit_proficiency_level" required>
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
                                <input type="date" class="form-control" name="assessed_date" id="edit_assessed_date" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Certificate URL</label>
                        <input type="url" class="form-control" name="certification_url" id="edit_certification_url" placeholder="Link to digital certificate">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" class="form-control" name="expiry_date" id="edit_expiry_date">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" name="notes" id="edit_notes" rows="3"></textarea>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal('editCertification')">Cancel</button>
                        <button type="submit" class="btn btn-success">&#128190; Update Certification</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function openModal(type) {
            if (type === 'addCertification') {
                document.getElementById('addCertificationModal').style.display = 'block';
            } else if (type === 'editCertification') {
                document.getElementById('editCertificationModal').style.display = 'block';
            }
        }

        function closeModal(type) {
            if (type === 'addCertification') {
                document.getElementById('addCertificationModal').style.display = 'none';
            } else if (type === 'editCertification') {
                document.getElementById('editCertificationModal').style.display = 'none';
            }
        }

        function editCertification(id, proficiency, assessedDate, certUrl, expiryDate, notes) {
            document.getElementById('edit_employee_skill_id').value = id;
            document.getElementById('edit_proficiency_level').value = proficiency || 'Beginner';
            document.getElementById('edit_assessed_date').value = (assessedDate || '').toString().substr(0, 10);
            document.getElementById('edit_certification_url').value = certUrl || '';
            document.getElementById('edit_expiry_date').value = (expiryDate || '').toString().substr(0, 10);
            document.getElementById('edit_notes').value = notes || '';
            document.getElementById('editCertificationModal').style.display = 'block';
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

        function searchCertifications() {
            var input = document.getElementById('certificationSearch');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('certificationsTable');
            var tr = table.getElementsByTagName('tr');
            for (var i = 1; i < tr.length; i++) {
                var td = tr[i].getElementsByTagName('td');
                var found = false;
                for (var j = 0; j < td.length; j++) {
                    if (td[j]) {
                        var txt = td[j].textContent || td[j].innerText;
                        if (txt.toLowerCase().indexOf(filter) > -1) { found = true; break; }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }

        window.onclick = function(event) {
            var modals = ['addCertificationModal', 'editCertificationModal', 'flashModal'];
            modals.forEach(function(id) {
                var m = document.getElementById(id);
                if (m && event.target === m) m.style.display = 'none';
            });
        };

        document.addEventListener('DOMContentLoaded', function() {
            var flashMessage = <?php echo json_encode($message); ?>;
            if (flashMessage) {
                var textEl = document.getElementById('flashMessageText');
                var modal = document.getElementById('flashModal');
                if (textEl) textEl.textContent = flashMessage;
                if (modal) {
                    modal.style.display = 'block';
                    setTimeout(function() { document.getElementById('flashModal').style.display = 'none'; }, 3500);
                }
            }
        });

        function closeFlashModal() {
            var m = document.getElementById('flashModal');
            if (m) m.style.display = 'none';
        }
    </script>
</body>
</html>
