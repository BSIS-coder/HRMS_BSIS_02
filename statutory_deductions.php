<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if user has permission (only admin and hr can manage statutory deductions)
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once 'config.php';

// Function to determine statutory deduction type based on job role and department
function determineDeductionType($job_title, $department_name) {
    // Convert to lowercase for case-insensitive matching
    $job_title_lower = strtolower($job_title);
    $department_lower = strtolower($department_name);

    // Government employees (GSIS)
    if (strpos($department_lower, 'government') !== false ||
        strpos($department_lower, 'public') !== false ||
        strpos($department_lower, 'civil') !== false ||
        strpos($department_lower, 'state') !== false) {
        return 'GSIS';
    }

    // Government-related job titles
    $government_titles = [
        'civil servant', 'government employee', 'public servant',
        'teacher', 'professor', 'lecturer', 'instructor',
        'police', 'military', 'soldier', 'officer',
        'judge', 'magistrate', 'clerk', 'secretary'
    ];

    foreach ($government_titles as $title) {
        if (strpos($job_title_lower, $title) !== false) {
            return 'GSIS';
        }
    }

    // Private sector employees (SSS)
    return 'SSS';
}

// Function to calculate statutory deduction amount
function calculateStatutoryDeduction($monthly_salary, $deduction_type) {
    $amount = 0;

    switch ($deduction_type) {
        case 'SSS':
            $salary_credit = min(max($monthly_salary, 4000), 30000);
            $amount = $salary_credit * 0.045;
            break;
        case 'PhilHealth':
            $philhealth_salary = min(max($monthly_salary, 10000), 90000);
            $amount = $philhealth_salary * 0.02;
            break;
        case 'Pag-IBIG':
            if ($monthly_salary > 5000) {
                $amount = 100.00;
            } else {
                $amount = $monthly_salary * 0.02;
            }
            break;
        case 'GSIS':
            $gsis_salary = min($monthly_salary, 60000);
            $amount = $gsis_salary * 0.09;
            break;
        default:
            $amount = 0;
    }
    return round($amount, 2);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {

        switch ($_POST['action']) {
            case 'add_statutory_deduction':
                $employee_id = $_POST['employee_id'];
                $deduction_type = $_POST['deduction_type'];
                $effective_date = $_POST['effective_date'];

                // Calculate deduction amount based on employee salary and deduction type
                $amount_sql = "SELECT cp.base_salary FROM compensation_packages cp WHERE cp.employee_id = ?";
                $amount_stmt = $conn->prepare($amount_sql);
                $amount_stmt->execute([$employee_id]);
                $salary_data = $amount_stmt->fetch(PDO::FETCH_ASSOC);

                $deduction_amount = 0;
                if ($salary_data) {
                    $monthly_salary = $salary_data['base_salary'];
                    $deduction_amount = calculateStatutoryDeduction($monthly_salary, $deduction_type);
                }

                try {
                    $sql = "INSERT INTO statutory_deductions (employee_id, deduction_type, deduction_amount, effective_date) 
                            VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$employee_id, $deduction_type, $deduction_amount, $effective_date]);
                    $success_message = "Statutory deduction added successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error adding statutory deduction: " . $e->getMessage();
                }
                break;
                
            case 'update_statutory_deduction':
                $statutory_deduction_id = $_POST['statutory_deduction_id'];
                $deduction_type = $_POST['deduction_type'];
                $deduction_amount = $_POST['deduction_amount'];
                $effective_date = $_POST['effective_date'];
                
                try {
                    $sql = "UPDATE statutory_deductions SET deduction_type = ?, deduction_amount = ?, 
                            effective_date = ? WHERE statutory_deduction_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$deduction_type, $deduction_amount, $effective_date, $statutory_deduction_id]);
                    $success_message = "Statutory deduction updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating statutory deduction: " . $e->getMessage();
                }
                break;
                
            case 'delete_statutory_deduction':
                $statutory_deduction_id = $_POST['statutory_deduction_id'];
                
                try {
                    $sql = "DELETE FROM statutory_deductions WHERE statutory_deduction_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$statutory_deduction_id]);
                    $success_message = "Statutory deduction deleted successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error deleting statutory deduction: " . $e->getMessage();
                }
                break;
                
            case 'bulk_apply_deduction':
                $deduction_type = $_POST['bulk_deduction_type'];
                $deduction_amount = $_POST['bulk_deduction_amount'];
                $effective_date = $_POST['bulk_effective_date'];
                $department_filter = $_POST['department_filter'] ?? '';
                
                try {
                    // Get employees based on filter
                    $emp_sql = "SELECT DISTINCT ep.employee_id
                                FROM employee_profiles ep
                                LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                                LEFT JOIN departments d ON jr.department = d.department_name
                                WHERE ep.employment_status IN ('Full-time', 'Part-time', 'Contract')";
                    
                    $params = [];
                    if ($department_filter) {
                        $emp_sql .= " AND d.department_id = ?";
                        $params[] = $department_filter;
                    }
                    
                    $emp_stmt = $conn->prepare($emp_sql);
                    $emp_stmt->execute($params);
                    $employees = $emp_stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $applied_count = 0;
                    
                    foreach ($employees as $employee_id) {
                        // Check if statutory deduction already exists for this employee and type
                        $check_sql = "SELECT COUNT(*) FROM statutory_deductions 
                                     WHERE employee_id = ? AND deduction_type = ? AND effective_date = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->execute([$employee_id, $deduction_type, $effective_date]);
                        
                        if ($check_stmt->fetchColumn() == 0) {
                            $insert_sql = "INSERT INTO statutory_deductions (employee_id, deduction_type, deduction_amount, effective_date) 
                                          VALUES (?, ?, ?, ?)";
                            $insert_stmt = $conn->prepare($insert_sql);
                            $insert_stmt->execute([$employee_id, $deduction_type, $deduction_amount, $effective_date]);
                            $applied_count++;
                        }
                    }
                    
                    $success_message = "Statutory deduction applied to {$applied_count} employees successfully!";
                    
                } catch (PDOException $e) {
                    $error_message = "Error applying bulk statutory deduction: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get filter parameters
$employee_search = $_GET['employee_search'] ?? '';
$deduction_type_filter = $_GET['deduction_type_filter'] ?? '';
$department_filter = $_GET['department_filter'] ?? '';

// Fetch statutory deductions with employee details
$sql = "SELECT sd.*, ep.employee_number, pi.first_name, pi.last_name, 
               jr.title as job_title, d.department_name
        FROM statutory_deductions sd
        JOIN employee_profiles ep ON sd.employee_id = ep.employee_id
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_name
        WHERE 1=1";

$params = [];

if ($employee_search) {
    $sql .= " AND (pi.first_name LIKE ? OR pi.last_name LIKE ? OR ep.employee_number LIKE ?)";
    $params[] = "%$employee_search%";
    $params[] = "%$employee_search%";
    $params[] = "%$employee_search%";
}

if ($deduction_type_filter) {
    $sql .= " AND sd.deduction_type = ?";
    $params[] = $deduction_type_filter;
}

if ($department_filter) {
    $sql .= " AND d.department_id = ?";
    $params[] = $department_filter;
}

$sql .= " ORDER BY sd.effective_date DESC, pi.first_name ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $statutory_deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $statutory_deductions = [];
    $error_message = "Error fetching statutory deductions: " . $e->getMessage();
}

// Get employees for dropdown with job role and department info
try {
    $emp_sql = "SELECT ep.employee_id, ep.employee_number, pi.first_name, pi.last_name,
                       jr.title as job_title, d.department_name
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                LEFT JOIN departments d ON jr.department = d.department_name
                WHERE ep.employment_status IN ('Full-time', 'Part-time', 'Contract')
                ORDER BY pi.first_name, pi.last_name";
    $emp_stmt = $conn->query($emp_sql);
    $employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}

// Get departments for filtering
try {
    $dept_sql = "SELECT department_id, department_name FROM departments ORDER BY department_name";
    $dept_stmt = $conn->query($dept_sql);
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}

// Philippine Statutory Deduction Types
$deduction_types = [
    'SSS' => 'SSS Contribution',
    'PhilHealth' => 'PhilHealth Contribution',
    'Pag-IBIG' => 'Pag-IBIG Contribution',
    'GSIS' => 'GSIS Contribution',
    'Other' => 'Other Statutory Deduction'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statutory Deductions - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f5f5;
            margin: 0;
            padding: 0;
        }
        .sidebar {
            height: 100vh;
            background-color: #800000;
            color: #fff;
            padding-top: 20px;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #fff #800000;
            z-index: 1030;
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #800000;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background-color: #fff;
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background-color: #f0f0f0;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .sidebar .nav-link.active {
            background-color: #fff;
            color: #800000;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .main-content {
            margin-left: 250px;
            padding: 90px 20px 20px;
            transition: margin-left 0.3s;
            width: calc(100% - 250px);
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(128, 0, 0, 0.05);
            border: none;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(128, 0, 0, 0.1);
            padding: 15px 20px;
            font-weight: bold;
            color: #800000;
        }
        .card-header i {
            color: #800000;
        }
        .card-body {
            padding: 20px;
        }
        .table th {
            border-top: none;
            color: #800000;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
            color: #333;
            border-color: rgba(128, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #800000;
            border-color: #800000;
        }
        .btn-primary:hover {
            background-color: #660000;
            border-color: #660000;
        }
        .top-navbar {
            background: #fff;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(128, 0, 0, 0.1);
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 1020;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .section-title {
            color: #800000;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #800000;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }
        .deduction-amount {
            font-weight: bold;
            color: #800000;
        }
        .modal-header {
            background-color: #800000;
            color: #fff;
        }
        .close {
            color: #fff;
            opacity: 0.8;
        }
        .close:hover {
            color: #fff;
            opacity: 1;
        }
        .filters-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .deduction-summary-card {
            background: linear-gradient(135deg, #800000 0%, #a60000 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-amount {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .summary-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .calculation-method {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        .deduction-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .deduction-type-sss { background-color: #007bff; color: white; }
        .deduction-type-philhealth { background-color: #28a745; color: white; }
        .deduction-type-pagibig { background-color: #ffc107; color: #212529; }
        .deduction-type-gsis { background-color: #17a2b8; color: white; }
        .deduction-type-other { background-color: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Statutory Deductions Management</h2>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error_message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Deduction Summary Cards -->
                <?php if (!empty($statutory_deductions)): ?>
                    <?php
                    $total_deductions = array_sum(array_column($statutory_deductions, 'deduction_amount'));
                    $unique_employees = count(array_unique(array_column($statutory_deductions, 'employee_id')));
                    ?>
                    <div class="deduction-summary-card">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="summary-item">
                                    <div class="summary-amount"><?php echo count($statutory_deductions); ?></div>
                                    <div class="summary-label">Total Deduction Rules</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="summary-item">
                                    <div class="summary-amount"><?php echo $unique_employees; ?></div>
                                    <div class="summary-label">Employees Affected</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="summary-item">
                                    <div class="summary-amount">₱<?php echo number_format($total_deductions, 2); ?></div>
                                    <div class="summary-label">Total Deductions</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filters-card">
                    <form method="get" class="row align-items-end">
                        <div class="col-md-3">
                            <label for="employee_search" class="form-label">Employee</label>
                            <input type="text" name="employee_search" class="form-control form-control-sm" 
                                   placeholder="Search employee..." value="<?php echo htmlspecialchars($employee_search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="deduction_type_filter" class="form-label">Deduction Type</label>
                            <select name="deduction_type_filter" class="form-control form-control-sm">
                                <option value="">All Types</option>
                                <?php foreach ($deduction_types as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($deduction_type_filter == $key) ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="department_filter" class="form-label">Department</label>
                            <select name="department_filter" class="form-control form-control-sm">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" <?php echo ($department_filter == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="statutory_deductions.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-file-invoice-dollar mr-2"></i> Statutory Deductions (<?php echo count($statutory_deductions); ?> records)</span>
                        <div>
                            <button type="button" class="btn btn-success btn-sm mr-2" data-toggle="modal" data-target="#bulkDeductionModal">
                                <i class="fas fa-layer-group"></i> Bulk Apply
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addDeductionModal">
                                <i class="fas fa-plus"></i> Add Deduction
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Employee #</th>
                                        <th>Department</th>
                                        <th>Deduction Type</th>
                                        <th>Amount</th>
                                        <th>Effective Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($statutory_deductions)): ?>
                                        <?php foreach ($statutory_deductions as $deduction): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($deduction['first_name'] . ' ' . $deduction['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($deduction['employee_number']); ?></td>
                                                <td><?php echo htmlspecialchars($deduction['department_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php 
                                                    $deduction_class = 'deduction-type-' . strtolower($deduction['deduction_type']);
                                                    ?>
                                                    <span class="deduction-type-badge <?php echo $deduction_class; ?>">
                                                        <?php echo htmlspecialchars($deduction['deduction_type']); ?>
                                                    </span>
                                                </td>
                                                <td class="deduction-amount">₱<?php echo number_format($deduction['deduction_amount'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($deduction['effective_date'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editDeduction(<?php echo htmlspecialchars(json_encode($deduction)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to delete this statutory deduction?');">
                                                            <input type="hidden" name="action" value="delete_statutory_deduction">
                                                            <input type="hidden" name="statutory_deduction_id" value="<?php echo $deduction['statutory_deduction_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No statutory deductions found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Philippine Statutory Deduction Guide -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calculator mr-2"></i> Philippine Statutory Deduction Guide (2024)
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>SSS Contributions</h6>
                                <div class="calculation-method">
                                    <strong>Monthly Contribution:</strong><br>
                                    • Employee: 4.5% of monthly salary credit<br>
                                    • Employer: 8.5% of monthly salary credit<br>
                                    • Maximum salary credit: ₱30,000<br>
                                    • Minimum salary credit: ₱4,000
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>PhilHealth Contributions</h6>
                                <div class="calculation-method">
                                    <strong>Monthly Contribution:</strong><br>
                                    • Employee: 2% of monthly salary<br>
                                    • Employer: 2% of monthly salary<br>
                                    • Maximum monthly salary: ₱90,000<br>
                                    • Minimum monthly salary: ₱10,000
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>Pag-IBIG Contributions</h6>
                                <div class="calculation-method">
                                    <strong>Monthly Contribution:</strong><br>
                                    • Employee: ₱100 (fixed)<br>
                                    • Employer: ₱100 (fixed)<br>
                                    • For salaries above ₱5,000: 2% of monthly salary<br>
                                    • Maximum contribution: ₱200
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>GSIS Contributions</h6>
                                <div class="calculation-method">
                                    <strong>Monthly Contribution:</strong><br>
                                    • Employee: 9% of monthly salary<br>
                                    • Employer: 12% of monthly salary<br>
                                    • For government employees only<br>
                                    • Maximum salary: ₱60,000
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Statutory Deduction Modal -->
    <div class="modal fade" id="addDeductionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Statutory Deduction</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_statutory_deduction">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="employee_id">Employee</label>
                                    <select class="form-control" id="employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['employee_id']; ?>"
                                                    data-job-title="<?php echo htmlspecialchars($employee['job_title'] ?? ''); ?>"
                                                    data-department="<?php echo htmlspecialchars($employee['department_name'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_number'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="deduction_type">Deduction Type</label>
                                    <select class="form-control" id="deduction_type" name="deduction_type" required>
                                        <option value="">Select Deduction Type</option>
                                        <?php foreach ($deduction_types as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="effective_date">Effective Date</label>
                                    <input type="date" class="form-control" id="effective_date" name="effective_date" required>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Deduction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Statutory Deduction Modal -->
    <div class="modal fade" id="editDeductionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Statutory Deduction</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_statutory_deduction">
                        <input type="hidden" name="statutory_deduction_id" id="edit_statutory_deduction_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_deduction_type">Deduction Type</label>
                                    <select class="form-control" id="edit_deduction_type" name="deduction_type" required>
                                        <option value="">Select Deduction Type</option>
                                        <?php foreach ($deduction_types as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_effective_date">Effective Date</label>
                                    <input type="date" class="form-control" id="edit_effective_date" name="effective_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_deduction_amount">Deduction Amount (₱)</label>
                                    <input type="number" class="form-control" id="edit_deduction_amount" name="deduction_amount" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Deduction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Apply Deduction Modal -->
    <div class="modal fade" id="bulkDeductionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Apply Statutory Deduction</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_apply_deduction">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Warning:</strong> This will apply the statutory deduction to multiple employees. 
                            Existing deductions of the same type and date will not be duplicated.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bulk_deduction_type">Deduction Type</label>
                                    <select class="form-control" id="bulk_deduction_type" name="bulk_deduction_type" required>
                                        <option value="">Select Deduction Type</option>
                                        <?php foreach ($deduction_types as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bulk_effective_date">Effective Date</label>
                                    <input type="date" class="form-control" id="bulk_effective_date" name="bulk_effective_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bulk_deduction_amount">Deduction Amount (₱)</label>
                                    <input type="number" class="form-control" id="bulk_deduction_amount" name="bulk_deduction_amount" 
                                           step="0.01" min="0" placeholder="e.g., 500.00" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="department_filter">Filter by Department (Optional)</label>
                                    <select class="form-control" name="department_filter">
                                        <option value="">Apply to All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>">
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        Select a department to apply only to employees in that department, 
                                        or leave blank to apply to all active employees.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" 
                                onclick="return confirm('Are you sure you want to apply this statutory deduction to multiple employees?');">
                            Apply to Employees
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function editDeduction(deduction) {
            $('#edit_statutory_deduction_id').val(deduction.statutory_deduction_id);
            $('#edit_deduction_type').val(deduction.deduction_type);
            $('#edit_deduction_amount').val(deduction.deduction_amount);
            $('#edit_effective_date').val(deduction.effective_date);
            $('#editDeductionModal').modal('show');
        }

        // Set default effective date to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('effective_date').value = today;
            document.getElementById('bulk_effective_date').value = today;
        });

        // Auto-populate deduction type when employee is selected
        document.getElementById('employee_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const jobTitle = selectedOption.getAttribute('data-job-title') || '';
            const department = selectedOption.getAttribute('data-department') || '';

            if (jobTitle || department) {
                const deductionType = determineDeductionType(jobTitle, department);
                document.getElementById('deduction_type').value = deductionType;
            }
        });

        // Function to determine statutory deduction type based on job role and department
        function determineDeductionType(jobTitle, department) {
            // Convert to lowercase for case-insensitive matching
            const jobTitleLower = jobTitle.toLowerCase();
            const departmentLower = department.toLowerCase();

            // Government employees (GSIS)
            if (departmentLower.includes('government') ||
                departmentLower.includes('public') ||
                departmentLower.includes('civil') ||
                departmentLower.includes('state')) {
                return 'GSIS';
            }

            // Government-related job titles
            const governmentTitles = [
                'civil servant', 'government employee', 'public servant',
                'teacher', 'professor', 'lecturer', 'instructor',
                'police', 'military', 'soldier', 'officer',
                'judge', 'magistrate', 'clerk', 'secretary'
            ];

            for (const title of governmentTitles) {
                if (jobTitleLower.includes(title)) {
                    return 'GSIS';
                }
            }

            // Private sector employees (SSS)
            return 'SSS';
        }

        // Calculate statutory deductions based on Philippine rates
        function calculateStatutoryDeductions(grossSalary, deductionType) {
            let deductionAmount = 0;

            switch (deductionType) {
                case 'SSS':
                    // SSS calculation based on salary credit
                    const sssSalary = Math.min(Math.max(grossSalary, 4000), 30000);
                    deductionAmount = sssSalary * 0.045;
                    break;
                case 'PhilHealth':
                    // PhilHealth calculation
                    const philhealthSalary = Math.min(Math.max(grossSalary, 10000), 90000);
                    deductionAmount = philhealthSalary * 0.02;
                    break;
                case 'Pag-IBIG':
                    // Pag-IBIG calculation
                    if (grossSalary > 5000) {
                        deductionAmount = 100.00;
                    } else {
                        deductionAmount = grossSalary * 0.02;
                    }
                    break;
                case 'GSIS':
                    // GSIS calculation
                    const gsisSalary = Math.min(grossSalary, 60000);
                    deductionAmount = gsisSalary * 0.09;
                    break;
                default:
                    deductionAmount = 0;
            }
            return Math.round(deductionAmount * 100) / 100; // Round to 2 decimal places
        }
