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
require_once 'dp.php';

// Database connection
$host = 'localhost';
$dbname = 'CC_HR';
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
            case 'create_exit':
                try {
                    $stmt = $pdo->prepare("INSERT INTO exits (employee_id, exit_type, exit_reason, notice_date, exit_date, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                    $stmt->execute([
                        $_POST['employee_id'],
                        $_POST['exit_type'],
                        $_POST['exit_reason'],
                        $_POST['notice_date'],
                        $_POST['exit_date']
                    ]);
                    
                    // Create suggestion for job opening if it's a resignation or retirement
                    if (in_array($_POST['exit_type'], ['Resignation', 'Retirement', 'End of Contract'])) {
                        // Get employee details for job opening suggestion
                        $stmt = $pdo->prepare("SELECT ep.job_role_id, jr.department, jr.title FROM employee_profiles ep JOIN job_roles jr ON ep.job_role_id = jr.job_role_id WHERE ep.employee_id = ?");
                        $stmt->execute([$_POST['employee_id']]);
                        $emp_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($emp_data) {
                            // Create a draft job opening
                            $dept_stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = ?");
                            $dept_stmt->execute([$emp_data['department']]);
                            $dept_data = $dept_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($dept_data) {
                                $job_stmt = $pdo->prepare("INSERT INTO job_openings (job_role_id, department_id, title, description, requirements, responsibilities, location, employment_type, vacancy_count, posting_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Full-time', 1, CURDATE(), 'Draft')");
                                $job_stmt->execute([
                                    $emp_data['job_role_id'],
                                    $dept_data['department_id'],
                                    'Replacement for ' . $emp_data['title'],
                                    'Position available due to employee ' . $_POST['exit_type'],
                                    'To be defined by HR',
                                    'To be defined by department head',
                                    'Municipal Office'
                                ]);
                            }
                        }
                    }
                    
                    $message = "Exit record created successfully! Job opening suggestion has been generated.";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error creating exit record: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'update_status':
                try {
                    $stmt = $pdo->prepare("UPDATE exits SET status = ? WHERE exit_id = ?");
                    $stmt->execute([$_POST['new_status'], $_POST['exit_id']]);
                    $message = "Exit status updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating status: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch exits with employee data
$stmt = $pdo->query("
    SELECT 
        e.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        jr.title as job_title,
        jr.department
    FROM exits e
    JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    ORDER BY e.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active employees for dropdown
$stmt = $pdo->query("
    SELECT 
        ep.employee_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
        jr.title as job_title
    FROM employee_profiles ep
    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    WHERE ep.employment_status != 'Terminated'
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for employee profile page */
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
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

        .loading {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--azure-blue);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <h2 class="section-title">Exit Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchInput" placeholder="Search exits...">
                        </div>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addExitModal"><i class="fas fa-plus mr-2"></i>Add Exit Record</button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="exitsTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Job Title</th>
                                    <th>Department</th>
                                    <th>Exit Type</th>
                                    <th>Notice Date</th>
                                    <th>Exit Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($exits) > 0): ?>
                                    <?php foreach ($exits as $exit): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($exit['employee_name']) ?></td>
                                            <td><?= htmlspecialchars($exit['job_title']) ?></td>
                                            <td><?= htmlspecialchars($exit['department']) ?></td>
                                            <td><?= htmlspecialchars($exit['exit_type']) ?></td>
                                            <td><?= date('M d, Y', strtotime($exit['notice_date'])) ?></td>
                                            <td><?= date('M d, Y', strtotime($exit['exit_date'])) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= strtolower($exit['status']) ?>">
                                                    <?= htmlspecialchars($exit['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($exit['status'] == 'Pending'): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="exit_id" value="<?= $exit['exit_id'] ?>">
                                                        <input type="hidden" name="new_status" value="Processing">
                                                        <button type="submit" class="btn btn-warning btn-small">Process</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($exit['status'] == 'Processing'): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="exit_id" value="<?= $exit['exit_id'] ?>">
                                                        <input type="hidden" name="new_status" value="Completed">
                                                        <button type="submit" class="btn btn-success btn-small">Complete</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="no-results">
                                            <i class="fas fa-inbox"></i>
                                            <p>No exit records found.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>
        </div>

    <!-- Add Exit Modal -->
    <div class="modal fade" id="addExitModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(233, 30, 99, 0.3);">
                <div class="modal-header" style="background: linear-gradient(135deg, #E91E63 0%, #F06292 100%); color: white; border-radius: 15px 15px 0 0; border-bottom: none;">
                    <h5 class="modal-title" style="font-weight: 600;"><i class="fas fa-sign-out-alt mr-2"></i>Create Exit Record</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 0.8; text-shadow: none;">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body" style="padding: 30px;">
                        <input type="hidden" name="action" value="create_exit">
                        
                        <div class="form-group">
                            <label style="color: #C2185B; font-weight: 600;">Employee</label>
                            <select name="employee_id" class="form-control" style="border: 2px solid #F8BBD0; border-radius: 8px; padding: 12px;" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['employee_id'] ?>">
                                        <?= htmlspecialchars($employee['full_name']) ?> - <?= htmlspecialchars($employee['job_title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="color: #C2185B; font-weight: 600;">Exit Type</label>
                                    <select name="exit_type" class="form-control" style="border: 2px solid #F8BBD0; border-radius: 8px; padding: 12px;" required>
                                        <option value="">Select Type</option>
                                        <option value="Resignation">Resignation</option>
                                        <option value="Termination">Termination</option>
                                        <option value="Retirement">Retirement</option>
                                        <option value="End of Contract">End of Contract</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="color: #C2185B; font-weight: 600;">Notice Date</label>
                                    <input type="date" name="notice_date" class="form-control" style="border: 2px solid #F8BBD0; border-radius: 8px; padding: 12px;" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label style="color: #C2185B; font-weight: 600;">Exit Date</label>
                            <input type="date" name="exit_date" class="form-control" style="border: 2px solid #F8BBD0; border-radius: 8px; padding: 12px;" required>
                        </div>
                        
                        <div class="form-group">
                            <label style="color: #C2185B; font-weight: 600;">Exit Reason</label>
                            <textarea name="exit_reason" class="form-control" rows="4" style="border: 2px solid #F8BBD0; border-radius: 8px; padding: 12px;" placeholder="Provide details about the exit reason..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #F8BBD0; padding: 20px 30px;">
                        <button type="button" class="btn btn-light" data-dismiss="modal" style="border: 2px solid #F8BBD0; color: #C2185B; font-weight: 600; border-radius: 25px; padding: 10px 25px;">Cancel</button>
                        <button type="submit" class="btn" style="background: linear-gradient(135deg, #E91E63 0%, #F06292 100%); color: white; border: none; font-weight: 600; border-radius: 25px; padding: 10px 25px;"><i class="fas fa-plus mr-2"></i>Create Exit Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#exitsTable tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
