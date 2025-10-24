<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if user has permission (only admin and hr can manage tax deductions)
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once 'config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_tax_deduction':
                $employee_id = $_POST['employee_id'];
                $tax_type = $_POST['tax_type'];
                $tax_percentage = $_POST['tax_percentage'] ?? null;
                $tax_amount = $_POST['tax_amount'] ?? null;
                $effective_date = $_POST['effective_date'];
                
                try {
                    $sql = "INSERT INTO tax_deductions (employee_id, tax_type, tax_percentage, tax_amount, effective_date) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$employee_id, $tax_type, $tax_percentage, $tax_amount, $effective_date]);
                    $success_message = "Tax deduction added successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error adding tax deduction: " . $e->getMessage();
                }
                break;
                
            case 'update_tax_deduction':
                $tax_deduction_id = $_POST['tax_deduction_id'];
                $tax_type = $_POST['tax_type'];
                $tax_percentage = $_POST['tax_percentage'] ?? null;
                $tax_amount = $_POST['tax_amount'] ?? null;
                $effective_date = $_POST['effective_date'];
                
                try {
                    $sql = "UPDATE tax_deductions SET tax_type = ?, tax_percentage = ?, tax_amount = ?, 
                            effective_date = ? WHERE tax_deduction_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$tax_type, $tax_percentage, $tax_amount, $effective_date, $tax_deduction_id]);
                    $success_message = "Tax deduction updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating tax deduction: " . $e->getMessage();
                }
                break;
                
            case 'delete_tax_deduction':
                $tax_deduction_id = $_POST['tax_deduction_id'];
                
                try {
                    $sql = "DELETE FROM tax_deductions WHERE tax_deduction_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$tax_deduction_id]);
                    $success_message = "Tax deduction deleted successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error deleting tax deduction: " . $e->getMessage();
                }
                break;
                
            case 'bulk_apply_tax':
                $tax_type = $_POST['bulk_tax_type'];
                $tax_percentage = $_POST['bulk_tax_percentage'] ?? null;
                $tax_amount = $_POST['bulk_tax_amount'] ?? null;
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
                        // Check if tax deduction already exists for this employee and type
                        $check_sql = "SELECT COUNT(*) FROM tax_deductions 
                                     WHERE employee_id = ? AND tax_type = ? AND effective_date = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->execute([$employee_id, $tax_type, $effective_date]);
                        
                        if ($check_stmt->fetchColumn() == 0) {
                            $insert_sql = "INSERT INTO tax_deductions (employee_id, tax_type, tax_percentage, tax_amount, effective_date) 
                                          VALUES (?, ?, ?, ?, ?)";
                            $insert_stmt = $conn->prepare($insert_sql);
                            $insert_stmt->execute([$employee_id, $tax_type, $tax_percentage, $tax_amount, $effective_date]);
                            $applied_count++;
                        }
                    }
                    
                    $success_message = "Tax deduction applied to {$applied_count} employees successfully!";
                    
                } catch (PDOException $e) {
                    $error_message = "Error applying bulk tax deduction: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get filter parameters
$employee_search = $_GET['employee_search'] ?? '';
$tax_type_filter = $_GET['tax_type_filter'] ?? '';
$department_filter = $_GET['department_filter'] ?? '';

// Fetch tax deductions with employee details
$sql = "SELECT td.*, ep.employee_number, pi.first_name, pi.last_name, 
               jr.title as job_title, d.department_name
        FROM tax_deductions td
        JOIN employee_profiles ep ON td.employee_id = ep.employee_id
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

if ($tax_type_filter) {
    $sql .= " AND td.tax_type = ?";
    $params[] = $tax_type_filter;
}

if ($department_filter) {
    $sql .= " AND d.department_id = ?";
    $params[] = $department_filter;
}

$sql .= " ORDER BY td.effective_date DESC, pi.first_name ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tax_deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tax_deductions = [];
    $error_message = "Error fetching tax deductions: " . $e->getMessage();
}

// Get employees for dropdown
try {
    $emp_sql = "SELECT ep.employee_id, ep.employee_number, pi.first_name, pi.last_name
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
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

// Philippine Tax Types
$tax_types = [
    'Income Tax' => 'Income Tax',
    'Withholding Tax' => 'Withholding Tax',
    'VAT' => 'VAT (12%)',
    'Other Tax' => 'Other Tax'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Deductions - HR System</title>
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
            background-color: #E91E63;
            color: #fff;
            padding-top: 20px;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #fff #E91E63;
            z-index: 1030;
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #E91E63;
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
            color: #E91E63;
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
            color: #E91E63;
        }
        .card-header i {
            color: #;
        }
        .card-body {
            padding: 20px;
        }
        .table th {
            border-top: none;
            color: #E91E63;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
            color: #333;
            border-color: rgba(128, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #E91E63;
            border-color: #E91E63;
        }
        .btn-primary:hover {
            background-color: #be0945ff;
            border-color: #be0945ff;
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
            color: #E91E63;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #E91E63;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }
        .tax-amount {
            font-weight: bold;
            color: #E91E63;
        }
        .modal-header {
            background-color: #E91E63;
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
        .tax-summary-card {
            background: linear-gradient(135deg, #E91E63 0%, #E91E63 100%);
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
        .tax-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .tax-type-income { background-color: #007bff; color: white; }
        .tax-type-withholding { background-color: #28a745; color: white; }
        .tax-type-vat { background-color: #ffc107; color: #212529; }
        .tax-type-other { background-color: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Tax Deductions Management</h2>
                
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

                <!-- Tax Summary Cards -->
                <?php if (!empty($tax_deductions)): ?>
                    <?php
                    $total_percentage_deductions = array_sum(array_filter(array_column($tax_deductions, 'tax_percentage')));
                    $total_fixed_deductions = array_sum(array_filter(array_column($tax_deductions, 'tax_amount')));
                    $unique_employees = count(array_unique(array_column($tax_deductions, 'employee_id')));
                    ?>
                    <div class="tax-summary-card">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="summary-item">
                                    <div class="summary-amount"><?php echo count($tax_deductions); ?></div>
                                    <div class="summary-label">Total Tax Rules</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-item">
                                    <div class="summary-amount"><?php echo $unique_employees; ?></div>
                                    <div class="summary-label">Employees Affected</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-item">
                                    <div class="summary-amount"><?php echo number_format($total_percentage_deductions, 1); ?>%</div>
                                    <div class="summary-label">Total Percentage Deductions</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-item">
                                    <div class="summary-amount">₱<?php echo number_format($total_fixed_deductions, 2); ?></div>
                                    <div class="summary-label">Total Fixed Deductions</div>
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
                            <label for="tax_type_filter" class="form-label">Tax Type</label>
                            <select name="tax_type_filter" class="form-control form-control-sm">
                                <option value="">All Types</option>
                                <?php foreach ($tax_types as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($tax_type_filter == $key) ? 'selected' : ''; ?>>
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
                            <a href="tax_deductions.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-percentage mr-2"></i> Tax Deductions (<?php echo count($tax_deductions); ?> records)</span>
                        <div>
                            <button type="button" class="btn btn-success btn-sm mr-2" data-toggle="modal" data-target="#bulkTaxModal">
                                <i class="fas fa-layer-group"></i> Bulk Apply
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addTaxModal">
                                <i class="fas fa-plus"></i> Add Tax Deduction
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
                                        <th>Tax Type</th>
                                        <th>Tax Rate (%)</th>
                                        <th>Fixed Amount</th>
                                        <th>Effective Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($tax_deductions)): ?>
                                        <?php foreach ($tax_deductions as $tax): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tax['first_name'] . ' ' . $tax['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($tax['employee_number']); ?></td>
                                                <td><?php echo htmlspecialchars($tax['department_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php 
                                                    $tax_class = 'tax-type-' . strtolower(str_replace(' ', '-', str_replace(['(', ')', '%'], '', $tax['tax_type'])));
                                                    ?>
                                                    <span class="tax-type-badge <?php echo $tax_class; ?>">
                                                        <?php echo htmlspecialchars($tax['tax_type']); ?>
                                                    </span>
                                                </td>
                                                <td class="tax-amount">
                                                    <?php echo $tax['tax_percentage'] ? number_format($tax['tax_percentage'], 2) . '%' : '-'; ?>
                                                </td>
                                                <td class="tax-amount">
                                                    <?php echo $tax['tax_amount'] ? '₱' . number_format($tax['tax_amount'], 2) : '-'; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($tax['effective_date'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editTaxDeduction(<?php echo htmlspecialchars(json_encode($tax)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" style="display: inline;" 
                                                              onsubmit="return confirm('Are you sure you want to delete this tax deduction?');">
                                                            <input type="hidden" name="action" value="delete_tax_deduction">
                                                            <input type="hidden" name="tax_deduction_id" value="<?php echo $tax['tax_deduction_id']; ?>">
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
                                            <td colspan="8" class="text-center">No tax deductions found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tax Calculation Guide -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-calculator mr-2"></i> Philippine Tax Calculation Guide
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Income Tax Brackets (2024)</h6>
                                <div class="calculation-method">
                                    <strong>Monthly Income Tax:</strong><br>
                                    • ₱0 - ₱20,833: 0%<br>
                                    • ₱20,834 - ₱33,333: 15%<br>
                                    • ₱33,334 - ₱66,667: 20%<br>
                                    • ₱66,668 - ₱166,667: 25%<br>
                                    • ₱166,668 - ₱666,667: 30%<br>
                                    • Above ₱666,667: 35%
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Other Deductions</h6>
                                <div class="calculation-method">
                                    <strong>Common Tax Types:</strong><br>
                                    • Withholding Tax: Variable %<br>
                                    • VAT: 12% on applicable income<br>
                                    • Other taxes as applicable<br><br>
                                    <small><em>Note: Consult with tax professionals for accurate calculations</em></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Tax Deduction Modal -->
    <div class="modal fade" id="addTaxModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Tax Deduction</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_tax_deduction">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="employee_id">Employee</label>
                                    <select class="form-control" id="employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['employee_id']; ?>">
                                                <?php echo htmlspecialchars($employee['employee_number'] . ' - ' . $employee['first_name'] . ' ' . $employee['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tax_type">Tax Type</label>
                                    <select class="form-control" id="tax_type" name="tax_type" required>
                                        <option value="">Select Tax Type</option>
                                        <?php foreach ($tax_types as $key => $value): ?>
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tax_percentage">Tax Percentage (%)</label>
                                    <input type="number" class="form-control" id="tax_percentage" name="tax_percentage"
                                           step="0.01" min="0" max="100" placeholder="e.g., 15.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tax_amount">Fixed Tax Amount (₱)</label>
                                    <input type="number" class="form-control" id="tax_amount" name="tax_amount"
                                           step="0.01" min="0" placeholder="e.g., 5000.00">
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Note:</strong> You can specify either a tax percentage OR a fixed amount, not both. 
                            Percentage will be calculated based on the employee's gross salary.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Tax Deduction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Tax Deduction Modal -->
    <div class="modal fade" id="editTaxModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Tax Deduction</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_tax_deduction">
                        <input type="hidden" name="tax_deduction_id" id="edit_tax_deduction_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_tax_type">Tax Type</label>
                                    <select class="form-control" id="edit_tax_type" name="tax_type" required>
                                        <option value="">Select Tax Type</option>
                                        <?php foreach ($tax_types as $key => $value): ?>
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
                                    <label for="edit_tax_percentage">Tax Percentage (%)</label>
                                    <input type="number" class="form-control" id="edit_tax_percentage" name="tax_percentage" 
                                           step="0.01" min="0" max="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_tax_amount">Fixed Tax Amount (₱)</label>
                                    <input type="number" class="form-control" id="edit_tax_amount" name="tax_amount" 
                                           step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Tax Deduction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Apply Tax Modal -->
    <div class="modal fade" id="bulkTaxModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Apply Tax Deduction</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_apply_tax">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Warning:</strong> This will apply the tax deduction to multiple employees. 
                            Existing tax deductions of the same type and date will not be duplicated.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bulk_tax_type">Tax Type</label>
                                    <select class="form-control" id="bulk_tax_type" name="bulk_tax_type" required>
                                        <option value="">Select Tax Type</option>
                                        <?php foreach ($tax_types as $key => $value): ?>
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
                                    <label for="bulk_tax_percentage">Tax Percentage (%)</label>
                                    <input type="number" class="form-control" id="bulk_tax_percentage" name="bulk_tax_percentage" 
                                           step="0.01" min="0" max="100" placeholder="e.g., 15.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bulk_tax_amount">Fixed Tax Amount (₱)</label>
                                    <input type="number" class="form-control" id="bulk_tax_amount" name="bulk_tax_amount" 
                                           step="0.01" min="0" placeholder="e.g., 5000.00">
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
                                onclick="return confirm('Are you sure you want to apply this tax deduction to multiple employees?');">
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
        function editTaxDeduction(tax) {
            $('#edit_tax_deduction_id').val(tax.tax_deduction_id);
            $('#edit_tax_type').val(tax.tax_type);
            $('#edit_tax_percentage').val(tax.tax_percentage);
            $('#edit_tax_amount').val(tax.tax_amount);
            $('#edit_effective_date').val(tax.effective_date);
            $('#editTaxModal').modal('show');
        }

        // Set default effective date to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('effective_date').value = today;
            document.getElementById('bulk_effective_date').value = today;
        });

        // Prevent both percentage and amount from being entered
        function validateTaxInput(isPercentage) {
            const percentageField = document.getElementById(isPercentage ? 'tax_percentage' : 'bulk_tax_percentage');
            const amountField = document.getElementById(isPercentage ? 'tax_amount' : 'bulk_tax_amount');
            
            if (isPercentage) {
                percentageField.addEventListener('input', function() {
                    if (this.value) {
                        amountField.value = '';
                        amountField.disabled = true;
                    } else {
                        amountField.disabled = false;
                    }
                });
                
                amountField.addEventListener('input', function() {
                    if (this.value) {
                        percentageField.value = '';
                        percentageField.disabled = true;
                    } else {
                        percentageField.disabled = false;
                    }
                });
            }
        }

        // Apply validation to both modals
        validateTaxInput(true);
        validateTaxInput(false);

        // Tax calculation helper
        function calculateTax(grossSalary, percentage, fixedAmount) {
            if (percentage) {
                return grossSalary * (percentage / 100);
            } else if (fixedAmount) {
                return fixedAmount;
            }
            return 0;
        }

        // Show tax calculation preview
        function showTaxPreview() {
            const percentage = document.getElementById('tax_percentage').value;
            const amount = document.getElementById('tax_amount').value;
            const sampleSalary = 50000; // Sample salary for calculation
            
            if (percentage) {
                const calculatedTax = calculateTax(sampleSalary, percentage, 0);
                console.log(`Tax on ₱${sampleSalary.toLocaleString()}: ₱${calculatedTax.toLocaleString()}`);
            } else if (amount) {
                console.log(`Fixed tax amount: ₱${parseFloat(amount).toLocaleString()}`);
            }
        }

        // Add event listeners for tax preview
        document.getElementById('tax_percentage')?.addEventListener('input', showTaxPreview);
        document.getElementById('tax_amount')?.addEventListener('input', showTaxPreview);
    </script>
</body>
</html>
