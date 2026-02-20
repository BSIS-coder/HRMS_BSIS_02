<?php
session_start();

// Align with dashboard access control
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Database connection (kept local to avoid impacting other includes)
$host = '127.0.0.1';
$dbname = 'hr_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle restore action
if (isset($_POST['restore']) && isset($_POST['archive_id'])) {
    $archive_id = $_POST['archive_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get archive record
        $stmt = $pdo->prepare("SELECT * FROM archive_storage WHERE archive_id = ?");
        $stmt->execute([$archive_id]);
        $archive = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$archive) {
            throw new Exception("Archive record not found.");
        }
        
        if ($archive['restored_at'] !== null) {
            throw new Exception("This record has already been restored.");
        }
        
        if ($archive['can_restore'] != 1) {
            throw new Exception("This record cannot be restored.");
        }
        
        // Get current user ID from session
        $restored_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Parse the archived record data
        $recordData = json_decode($archive['record_data'], true);
        if (!$recordData) {
            throw new Exception("Invalid record data format.");
        }
        
        $source_table = $archive['source_table'];
        $restored = false;
        
        // Restore based on source table
        switch ($source_table) {
            case 'personal_information':
                // Check if record with this ID already exists
                $existCheck = $pdo->prepare("SELECT COUNT(*) FROM personal_information WHERE personal_info_id = ?");
                $existCheck->execute([$recordData['personal_info_id']]);
                if ($existCheck->fetchColumn() > 0) {
                    throw new Exception("Cannot restore: A record with this ID already exists in the system.");
                }
                
                // Check for duplicate Tax ID or SSN before restoring
                $taxId = $recordData['tax_id'] ?? null;
                $ssn = $recordData['social_security_number'] ?? null;
                
                if (!empty($taxId) || !empty($ssn)) {
                    $conditions = [];
                    $checkParams = [];
                    
                    if (!empty($taxId)) {
                        $conditions[] = "tax_id = ?";
                        $checkParams[] = $taxId;
                    }
                    if (!empty($ssn)) {
                        $conditions[] = "social_security_number = ?";
                        $checkParams[] = $ssn;
                    }
                    
                    if (!empty($conditions)) {
                        $checkQuery = "SELECT COUNT(*) FROM personal_information WHERE " . implode(" OR ", $conditions);
                        $checkStmt = $pdo->prepare($checkQuery);
                        $checkStmt->execute($checkParams);
                        
                        if ($checkStmt->fetchColumn() > 0) {
                            throw new Exception("Cannot restore: Tax ID or Social Security Number already exists in the system.");
                        }
                    }
                }
                
                // Restore personal_information record (try to use original ID first)
                $restoreStmt = $pdo->prepare("INSERT INTO personal_information 
                    (personal_info_id, first_name, last_name, date_of_birth, gender, marital_status, nationality, 
                     tax_id, social_security_number, phone_number, emergency_contact_name, 
                     emergency_contact_relationship, emergency_contact_phone) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $restoreStmt->execute([
                    $recordData['personal_info_id'],
                    $recordData['first_name'],
                    $recordData['last_name'],
                    $recordData['date_of_birth'],
                    $recordData['gender'],
                    $recordData['marital_status'],
                    $recordData['nationality'],
                    $recordData['tax_id'] ?? null,
                    $recordData['social_security_number'] ?? null,
                    $recordData['phone_number'],
                    $recordData['emergency_contact_name'] ?? null,
                    $recordData['emergency_contact_relationship'] ?? null,
                    $recordData['emergency_contact_phone'] ?? null
                ]);
                
                $restored = true;
                break;
                
            case 'document_management':
                // Check if record with this ID already exists
                $existCheck = $pdo->prepare("SELECT COUNT(*) FROM document_management WHERE document_id = ?");
                $existCheck->execute([$recordData['document_id']]);
                if ($existCheck->fetchColumn() > 0) {
                    throw new Exception("Cannot restore: A document with this ID already exists in the system.");
                }
                
                // Check if employee still exists (optional validation)
                if (!empty($recordData['employee_id'])) {
                    $employeeCheck = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE employee_id = ?");
                    $employeeCheck->execute([$recordData['employee_id']]);
                    if ($employeeCheck->fetchColumn() == 0) {
                        throw new Exception("Cannot restore: The associated employee no longer exists in the system.");
                    }
                }
                
                // Restore document_management record
                $restoreStmt = $pdo->prepare("INSERT INTO document_management 
                    (document_id, employee_id, document_type, document_name, file_path, 
                     expiry_date, document_status, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                $restoreStmt->execute([
                    $recordData['document_id'],
                    $recordData['employee_id'],
                    $recordData['document_type'],
                    $recordData['document_name'],
                    $recordData['file_path'] ?? null,
                    $recordData['expiry_date'] ?? null,
                    $recordData['document_status'] ?? 'Active',
                    $recordData['notes'] ?? null
                ]);
                
                $restored = true;
                break;
                
            case 'employee_profiles':
                // Check if record with this ID already exists
                $existCheck = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE employee_id = ?");
                $existCheck->execute([$recordData['employee_id']]);
                if ($existCheck->fetchColumn() > 0) {
                    throw new Exception("Cannot restore: An employee profile with this ID already exists in the system.");
                }
                
                // Check if employee_number already exists (unique constraint)
                if (!empty($recordData['employee_number'])) {
                    $employeeNumberCheck = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE employee_number = ?");
                    $employeeNumberCheck->execute([$recordData['employee_number']]);
                    if ($employeeNumberCheck->fetchColumn() > 0) {
                        throw new Exception("Cannot restore: Employee number '" . $recordData['employee_number'] . "' already exists in the system.");
                    }
                }
                
                // Check if work_email already exists (unique constraint)
                if (!empty($recordData['work_email'])) {
                    $emailCheck = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE work_email = ?");
                    $emailCheck->execute([$recordData['work_email']]);
                    if ($emailCheck->fetchColumn() > 0) {
                        throw new Exception("Cannot restore: Work email '" . $recordData['work_email'] . "' already exists in the system.");
                    }
                }
                
                // Check if personal_info_id still exists and is not linked to another employee
                if (!empty($recordData['personal_info_id'])) {
                    $personalInfoCheck = $pdo->prepare("SELECT COUNT(*) FROM personal_information WHERE personal_info_id = ?");
                    $personalInfoCheck->execute([$recordData['personal_info_id']]);
                    if ($personalInfoCheck->fetchColumn() == 0) {
                        throw new Exception("Cannot restore: The associated personal information record no longer exists.");
                    }
                    
                    // Check if personal_info_id is linked to another employee
                    $linkedEmployeeCheck = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE personal_info_id = ?");
                    $linkedEmployeeCheck->execute([$recordData['personal_info_id']]);
                    if ($linkedEmployeeCheck->fetchColumn() > 0) {
                        throw new Exception("Cannot restore: The personal information is already linked to another employee profile.");
                    }
                }
                
                // Check if job_role_id still exists
                if (!empty($recordData['job_role_id'])) {
                    $jobRoleCheck = $pdo->prepare("SELECT COUNT(*) FROM job_roles WHERE job_role_id = ?");
                    $jobRoleCheck->execute([$recordData['job_role_id']]);
                    if ($jobRoleCheck->fetchColumn() == 0) {
                        // Allow restore but set job_role_id to null if job role doesn't exist
                        $recordData['job_role_id'] = null;
                    }
                }
                
                // Restore employee_profiles record
                $restoreStmt = $pdo->prepare("INSERT INTO employee_profiles 
                    (employee_id, personal_info_id, job_role_id, employee_number, hire_date, 
                     employment_status, current_salary, work_email, work_phone, location, remote_work) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $restoreStmt->execute([
                    $recordData['employee_id'],
                    $recordData['personal_info_id'] ?? null,
                    $recordData['job_role_id'] ?? null,
                    $recordData['employee_number'],
                    $recordData['hire_date'],
                    $recordData['employment_status'],
                    $recordData['current_salary'] ?? 0.00,
                    $recordData['work_email'] ?? null,
                    $recordData['work_phone'] ?? null,
                    $recordData['location'] ?? null,
                    $recordData['remote_work'] ?? 0
                ]);
                
                $restored = true;
                break;
                
            case 'employment_history':
                // Check if record with this ID already exists
                $existCheck = $pdo->prepare("SELECT COUNT(*) FROM employment_history WHERE history_id = ?");
                $existCheck->execute([$recordData['history_id']]);
                if ($existCheck->fetchColumn() > 0) {
                    throw new Exception("Cannot restore: An employment history record with this ID already exists in the system.");
                }
                
                // Check if employee_id still exists
                if (!empty($recordData['employee_id'])) {
                    $employeeCheck = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE employee_id = ?");
                    $employeeCheck->execute([$recordData['employee_id']]);
                    if ($employeeCheck->fetchColumn() == 0) {
                        throw new Exception("Cannot restore: The associated employee no longer exists in the system.");
                    }
                } else {
                    throw new Exception("Cannot restore: Employee ID is required for employment history.");
                }
                
                // Check if department_id still exists (if provided)
                if (!empty($recordData['department_id'])) {
                    $deptCheck = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_id = ?");
                    $deptCheck->execute([$recordData['department_id']]);
                    if ($deptCheck->fetchColumn() == 0) {
                        // Allow restore but set department_id to null if department doesn't exist
                        $recordData['department_id'] = null;
                    }
                }
                
                // Check if reporting_manager_id still exists (if provided)
                if (!empty($recordData['reporting_manager_id'])) {
                    $managerCheck = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE employee_id = ?");
                    $managerCheck->execute([$recordData['reporting_manager_id']]);
                    if ($managerCheck->fetchColumn() == 0) {
                        // Allow restore but set reporting_manager_id to null if manager doesn't exist
                        $recordData['reporting_manager_id'] = null;
                    }
                }
                
                // Restore employment_history record
                $restoreStmt = $pdo->prepare("INSERT INTO employment_history 
                    (history_id, employee_id, job_title, department_id, employment_type, start_date, end_date, 
                     employment_status, reporting_manager_id, location, base_salary, allowances, bonuses, 
                     salary_adjustments, reason_for_change, promotions_transfers, duties_responsibilities, 
                     performance_evaluations, training_certifications, contract_details, remarks) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $restoreStmt->execute([
                    $recordData['history_id'],
                    $recordData['employee_id'],
                    $recordData['job_title'],
                    $recordData['department_id'] ?? null,
                    $recordData['employment_type'],
                    $recordData['start_date'],
                    $recordData['end_date'] ?? null,
                    $recordData['employment_status'],
                    $recordData['reporting_manager_id'] ?? null,
                    $recordData['location'] ?? null,
                    $recordData['base_salary'],
                    $recordData['allowances'] ?? 0.00,
                    $recordData['bonuses'] ?? 0.00,
                    $recordData['salary_adjustments'] ?? 0.00,
                    $recordData['reason_for_change'] ?? null,
                    $recordData['promotions_transfers'] ?? null,
                    $recordData['duties_responsibilities'] ?? null,
                    $recordData['performance_evaluations'] ?? null,
                    $recordData['training_certifications'] ?? null,
                    $recordData['contract_details'] ?? null,
                    $recordData['remarks'] ?? null
                ]);
                
                $restored = true;
                break;
                
            default:
                throw new Exception("Restore not implemented for source table: " . $source_table);
        }
        
        if ($restored) {
            // Update archive record to mark as restored
            $updateStmt = $pdo->prepare("UPDATE archive_storage SET restored_at = NOW(), restored_by = ? WHERE archive_id = ?");
            $updateStmt->execute([$restored_by, $archive_id]);
            
            $pdo->commit();
            $success_message = "Record restored successfully to " . str_replace('_', ' ', $source_table) . "!";
        } else {
            throw new Exception("Failed to restore record.");
        }
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error_message = "Restore failed: " . $e->getMessage();
    } catch(Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = $e->getMessage();
    }
}

// Get filter parameters
$filter_table = isset($_GET['table']) ? $_GET['table'] : '';
$filter_reason = isset($_GET['reason']) ? $_GET['reason'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT a.*, u.username as archived_by_name 
          FROM archive_storage a 
          LEFT JOIN users u ON a.archived_by = u.user_id 
          WHERE 1=1";
$params = [];

if ($filter_table) {
    $query .= " AND a.source_table = ?";
    $params[] = $filter_table;
}

if ($filter_reason) {
    $query .= " AND a.archive_reason = ?";
    $params[] = $filter_reason;
}

if ($search) {
    $query .= " AND (a.record_data LIKE ? OR a.archive_reason_details LIKE ? OR a.notes LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY a.archived_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$archives = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN can_restore = 1 THEN 1 ELSE 0 END) as restorable,
                SUM(CASE WHEN restored_at IS NOT NULL THEN 1 ELSE 0 END) as restored
                FROM archive_storage";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Storage Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Page-specific tweaks on top of shared theme */
        .archive-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--text-white);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 18px var(--shadow-medium);
        }

        .stats {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .filters {
            padding: 20px 24px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-light);
            margin: 20px 0;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9em;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 15px;
            border: 2px solid var(--border-medium);
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
            background: var(--bg-card);
            color: var(--text-primary);
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem var(--shadow-light);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 2px 10px var(--shadow-light);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
        }

        thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--text-white);
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 1px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border-light);
        }

        tbody tr:hover { background: var(--bg-secondary); }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-profile { background: var(--primary-lighter); color: var(--primary-dark); }
        .badge-personal { background: var(--bg-secondary); color: var(--primary-color); }
        .badge-history { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
        .badge-document { background: rgba(40, 167, 69, 0.2); color: var(--success); }

        .badge-termination { background: rgba(220, 53, 69, 0.2); color: var(--danger); }
        .badge-resignation { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
        .badge-retirement { background: var(--primary-lighter); color: var(--primary-dark); }
        .badge-expired { background: var(--bg-secondary); color: var(--primary-dark); }
        .badge-cleanup { background: rgba(23, 162, 184, 0.2); color: var(--info); }

        .status-restored { color: var(--success); font-weight: 600; }
        .status-archived { color: var(--text-muted); }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .json-viewer {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            max-height: 400px;
            overflow-y: auto;
        }

        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .info-row:last-child { border-bottom: none; }

        .info-label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .info-value {
            color: var(--text-secondary);
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
            background: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--bg-card);
            margin: 50px auto;
            padding: 0;
            width: 90%;
            max-width: 800px;
            border-radius: 15px;
            box-shadow: 0 20px 60px var(--shadow-dark);
            animation: slideDown 0.3s;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--text-white);
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            margin: 0;
        }

        .modal-body {
            padding: 30px;
        }

        .close {
            color: var(--text-white);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            opacity: 0.8;
        }

        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        @media (max-width: 768px) {
            .filter-row { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr; }
            table { font-size: 0.9em; }
            th, td { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">


        <!-- Statistics -->
        <div class="row">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <i class="fas fa-archive"></i>
                        <h6 class="text-muted">Total Archived</h6>
                        <h3 class="card-title"><?php echo $stats['total']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <i class="fas fa-undo"></i>
                        <h6 class="text-muted">Restorable</h6>
                        <h3 class="card-title"><?php echo $stats['restorable']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <i class="fas fa-check-circle"></i>
                        <h6 class="text-muted">Restored</h6>
                        <h3 class="card-title"><?php echo $stats['restored']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="table">Source Table</label>
                        <select name="table" id="table">
                            <option value="">All Tables</option>
                            <option value="employee_profiles" <?php echo $filter_table == 'employee_profiles' ? 'selected' : ''; ?>>Employee Profiles</option>
                            <option value="personal_information" <?php echo $filter_table == 'personal_information' ? 'selected' : ''; ?>>Personal Information</option>
                            <option value="employment_history" <?php echo $filter_table == 'employment_history' ? 'selected' : ''; ?>>Employment History</option>
                            <option value="document_management" <?php echo $filter_table == 'document_management' ? 'selected' : ''; ?>>Document Management</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="reason">Archive Reason</label>
                        <select name="reason" id="reason">
                            <option value="">All Reasons</option>
                            <option value="Termination" <?php echo $filter_reason == 'Termination' ? 'selected' : ''; ?>>Termination</option>
                            <option value="Resignation" <?php echo $filter_reason == 'Resignation' ? 'selected' : ''; ?>>Resignation</option>
                            <option value="Retirement" <?php echo $filter_reason == 'Retirement' ? 'selected' : ''; ?>>Retirement</option>
                            <option value="Expired Document" <?php echo $filter_reason == 'Expired Document' ? 'selected' : ''; ?>>Expired Document</option>
                            <option value="Data Cleanup" <?php echo $filter_reason == 'Data Cleanup' ? 'selected' : ''; ?>>Data Cleanup</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" placeholder="Search in data, reason, or notes..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (count($archives) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Archive ID</th>
                                <th>Source Table</th>
                                <th>Employee ID</th>
                                <th>Archive Reason</th>
                                <th>Archived By</th>
                                <th>Archived At</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archives as $archive): ?>
                                <tr>
                                    <td><?php echo $archive['archive_id']; ?></td>
                                    <td>
                                        <?php
                                        $table_badges = [
                                            'employee_profiles' => 'badge-profile',
                                            'personal_information' => 'badge-personal',
                                            'employment_history' => 'badge-history',
                                            'document_management' => 'badge-document'
                                        ];
                                        $badge_class = $table_badges[$archive['source_table']] ?? '';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo str_replace('_', ' ', $archive['source_table']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $archive['employee_id'] ?? 'N/A'; ?></td>
                                    <td>
                                        <?php
                                        $reason_badges = [
                                            'Termination' => 'badge-termination',
                                            'Resignation' => 'badge-resignation',
                                            'Retirement' => 'badge-retirement',
                                            'Expired Document' => 'badge-expired',
                                            'Data Cleanup' => 'badge-cleanup'
                                        ];
                                        $reason_badge = $reason_badges[$archive['archive_reason']] ?? '';
                                        ?>
                                        <span class="badge <?php echo $reason_badge; ?>">
                                            <?php echo $archive['archive_reason']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $archive['archived_by_name'] ?? 'Unknown'; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($archive['archived_at'])); ?></td>
                                    <td>
                                        <?php if ($archive['restored_at']): ?>
                                            <span class="status-restored">✓ Restored</span>
                                        <?php else: ?>
                                            <span class="status-archived">Archived</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="viewDetails(<?php echo $archive['archive_id']; ?>)" class="btn btn-info">View</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="archive_id" value="<?php echo $archive['archive_id']; ?>">
                                            <button type="submit" name="restore" class="btn btn-success" 
                                                    <?php echo (!$archive['can_restore'] || $archive['restored_at']) ? 'disabled' : ''; ?>>
                                                Restore
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div style="font-size: 4em; margin-bottom: 20px; opacity: 0.3;">📭</div>
                    <h3>No archived records found</h3>
                    <p>Try adjusting your filters or search criteria</p>
                </div>
            <?php endif; ?>
        </div> <!-- /.main-content -->
    </div> <!-- /.row -->
    </div> <!-- /.container-fluid -->

    <!-- Modal -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Archive Details</h2>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Store all archive data for JavaScript access
        const archiveData = <?php echo json_encode($archives); ?>;

        function viewDetails(archiveId) {
            const archive = archiveData.find(a => a.archive_id == archiveId);
            if (!archive) return;

            const recordData = JSON.parse(archive.record_data);
            
            let html = `
                <div class="info-row">
                    <div class="info-label">Archive ID:</div>
                    <div class="info-value">${archive.archive_id}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Source Table:</div>
                    <div class="info-value">${archive.source_table.replace(/_/g, ' ')}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Record ID:</div>
                    <div class="info-value">${archive.record_id}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Employee ID:</div>
                    <div class="info-value">${archive.employee_id || 'N/A'}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Archive Reason:</div>
                    <div class="info-value">${archive.archive_reason}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Reason Details:</div>
                    <div class="info-value">${archive.archive_reason_details || 'N/A'}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Archived By:</div>
                    <div class="info-value">${archive.archived_by_name || 'Unknown'}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Archived At:</div>
                    <div class="info-value">${new Date(archive.archived_at).toLocaleString()}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Can Restore:</div>
                    <div class="info-value">${archive.can_restore == 1 ? 'Yes' : 'No'}</div>
                </div>
                ${archive.restored_at ? `
                <div class="info-row">
                    <div class="info-label">Restored At:</div>
                    <div class="info-value">${new Date(archive.restored_at).toLocaleString()}</div>
                </div>
                ` : ''}
                ${archive.notes ? `
                <div class="info-row">
                    <div class="info-label">Notes:</div>
                    <div class="info-value">${archive.notes}</div>
                </div>
                ` : ''}
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px;">Original Record Data:</h3>
                    <div class="json-viewer">
                        <pre>${JSON.stringify(recordData, null, 2)}</pre>
                    </div>
                </div>
            `;

            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('detailModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>