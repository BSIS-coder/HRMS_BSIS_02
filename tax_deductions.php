<?php
session_start();

// Check login and permissions
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
    header('Location: index.php');
    exit;
}

// Database connection
require_once 'config.php';

// Function to calculate income tax based on salary and tax brackets
function calculateIncomeTax($salary, $conn) {
    $stmt = $conn->prepare("SELECT * FROM tax_brackets WHERE tax_type = 'Income Tax' ORDER BY min_salary ASC");
    $stmt->execute();
    $brackets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tax = 0;
    foreach ($brackets as $bracket) {
        if ($salary > $bracket['min_salary']) {
            $taxable_amount = min($salary, $bracket['max_salary'] ?? $salary) - $bracket['excess_over'];
            $tax += $taxable_amount * $bracket['tax_rate'] + $bracket['fixed_amount'];
        }
        if ($bracket['max_salary'] && $salary <= $bracket['max_salary']) break;
    }
    return round($tax, 2);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'add_tax_deduction':
                $tax_type = $_POST['tax_type'];
                $tax_percentage = $_POST['tax_percentage'] !== '' ? $_POST['tax_percentage'] : null;
                $tax_amount = $_POST['tax_amount'] !== '' ? $_POST['tax_amount'] : null;

                // Auto-calculate for Income Tax if no amount provided
                if ($tax_type === 'Income Tax' && !$tax_amount && !$tax_percentage) {
                    $salary_stmt = $conn->prepare("SELECT current_salary FROM employee_profiles WHERE employee_id = ?");
                    $salary_stmt->execute([$_POST['employee_id']]);
                    $salary = $salary_stmt->fetchColumn();
                    if ($salary) {
                        $tax_amount = calculateIncomeTax($salary, $conn);
                    }
                }

                $stmt = $conn->prepare("INSERT INTO tax_deductions (employee_id, tax_type, tax_percentage, tax_amount, effective_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['employee_id'],
                    $tax_type,
                    $tax_percentage,
                    $tax_amount,
                    $_POST['effective_date']
                ]);
                $success_message = "Tax deduction added successfully!";
                break;

            case 'update_tax_deduction':
                $stmt = $conn->prepare("UPDATE tax_deductions SET tax_type=?, tax_percentage=?, tax_amount=?, effective_date=? WHERE tax_deduction_id=?");
                $stmt->execute([
                    $_POST['tax_type'],
                    $_POST['tax_percentage'] !== '' ? $_POST['tax_percentage'] : null,
                    $_POST['tax_amount'] !== '' ? $_POST['tax_amount'] : null,
                    $_POST['effective_date'],
                    $_POST['tax_deduction_id']
                ]);
                $success_message = "Tax deduction updated successfully!";
                break;

            case 'delete_tax_deduction':
                $stmt = $conn->prepare("DELETE FROM tax_deductions WHERE tax_deduction_id=?");
                $stmt->execute([$_POST['tax_deduction_id']]);
                $success_message = "Tax deduction deleted successfully!";
                break;

            case 'bulk_apply_tax':
                $department_filter = $_POST['department_filter'] ?? '';
                $tax_type = $_POST['bulk_tax_type'];
                $tax_percentage = $_POST['bulk_tax_percentage'] !== '' ? $_POST['bulk_tax_percentage'] : null;
                $tax_amount = $_POST['bulk_tax_amount'] !== '' ? $_POST['bulk_tax_amount'] : null;

                $emp_sql = "SELECT ep.employee_id, ep.current_salary
                            FROM employee_profiles ep
                            LEFT JOIN job_roles jr ON ep.job_role_id=jr.job_role_id
                            LEFT JOIN departments d ON jr.department = d.department_name
                            WHERE ep.employment_status IN ('Full-time','Part-time','Contract')";
                $params = [];
                if ($department_filter) {
                    $emp_sql .= " AND d.department_id=?";
                    $params[] = $department_filter;
                }
                $emp_stmt = $conn->prepare($emp_sql);
                $emp_stmt->execute($params);
                $employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);
                $applied_count = 0;
                foreach ($employees as $emp) {
                    $emp_id = $emp['employee_id'];
                    $check = $conn->prepare("SELECT COUNT(*) FROM tax_deductions WHERE employee_id=? AND tax_type=? AND effective_date=?");
                    $check->execute([$emp_id, $tax_type, $_POST['bulk_effective_date']]);
                    if ($check->fetchColumn() == 0) {
                        // Auto-calculate for Income Tax if no amount provided
                        $final_tax_amount = $tax_amount;
                        if ($tax_type === 'Income Tax' && !$tax_amount && !$tax_percentage && $emp['current_salary']) {
                            $final_tax_amount = calculateIncomeTax($emp['current_salary'], $conn);
                        }

                        $insert = $conn->prepare("INSERT INTO tax_deductions (employee_id, tax_type, tax_percentage, tax_amount, effective_date) VALUES (?, ?, ?, ?, ?)");
                        $insert->execute([
                            $emp_id,
                            $tax_type,
                            $tax_percentage,
                            $final_tax_amount,
                            $_POST['bulk_effective_date']
                        ]);
                        $applied_count++;
                    }
                }
                $success_message = "Tax deduction applied to {$applied_count} employees successfully!";
                break;

            case 'add_bracket':
                $stmt = $conn->prepare("INSERT INTO tax_brackets (tax_type, min_salary, max_salary, tax_rate, fixed_amount, excess_over) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['tax_type'],
                    $_POST['min_salary'],
                    $_POST['max_salary'] !== '' ? $_POST['max_salary'] : null,
                    $_POST['tax_rate'] / 100, // Convert percentage to decimal
                    $_POST['fixed_amount'],
                    $_POST['excess_over']
                ]);
                $success_message = "Tax bracket added successfully!";
                break;

            case 'update_bracket':
                $stmt = $conn->prepare("UPDATE tax_brackets SET tax_type=?, min_salary=?, max_salary=?, tax_rate=?, fixed_amount=?, excess_over=? WHERE bracket_id=?");
                $stmt->execute([
                    $_POST['tax_type'],
                    $_POST['min_salary'],
                    $_POST['max_salary'] !== '' ? $_POST['max_salary'] : null,
                    $_POST['tax_rate'] / 100, // Convert percentage to decimal
                    $_POST['fixed_amount'],
                    $_POST['excess_over'],
                    $_POST['bracket_id']
                ]);
                $success_message = "Tax bracket updated successfully!";
                break;
        }
    } catch (PDOException $e) {
        $error_message = $e->getMessage();
    }
}

// Filters
$employee_search = $_GET['employee_search'] ?? '';
$tax_type_filter = $_GET['tax_type_filter'] ?? '';
$department_filter = $_GET['department_filter'] ?? '';

// Fetch tax deductions
$sql = "SELECT td.*, ep.employee_number, pi.first_name, pi.last_name, jr.title as job_title, d.department_name
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
    $sql .= " AND td.tax_type=?";
    $params[] = $tax_type_filter;
}
if ($department_filter) {
    $sql .= " AND d.department_id=?";
    $params[] = $department_filter;
}
$sql .= " ORDER BY td.effective_date DESC, pi.first_name ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$tax_deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Employees for dropdown
$emp_stmt = $conn->query("SELECT ep.employee_id, ep.employee_number, pi.first_name, pi.last_name FROM employee_profiles ep JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id WHERE ep.employment_status IN ('Full-time','Part-time','Contract') ORDER BY pi.first_name, pi.last_name");
$employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);

// Departments
$dept_stmt = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Philippine Tax Types
$tax_types = [
    'Income Tax'=>'Income Tax',
    'Withholding Tax'=>'Withholding Tax',
    'Other Tax'=>'Other Tax'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tax Deductions - HR System</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
<style>
/* Your current CSS design remains exactly the same */
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
        .calculation-method {W
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
    
<?php /* Paste your full CSS here from your code above */ ?>
</style>
</head>
<body>
<?php include 'navigation.php'; include 'sidebar.php'; ?>
<div class="main-content">
<h2 class="section-title">Tax Deductions Management</h2>
<?php if(isset($success_message)) echo '<div class="alert alert-success">'.$success_message.'</div>'; ?>
<?php if(isset($error_message)) echo '<div class="alert alert-danger">'.$error_message.'</div>'; ?>

<div class="filters-card">
<form method="get" class="form-row">
<div class="col-md-3"><input type="text" class="form-control" name="employee_search" placeholder="Search employee..." value="<?php echo htmlspecialchars($employee_search); ?>"></div>
<div class="col-md-2">
<select class="form-control" name="tax_type_filter">
<option value="">All Types</option>
<?php foreach($tax_types as $key=>$val): ?>
<option value="<?php echo $key; ?>" <?php echo ($tax_type_filter==$key)?'selected':''; ?>><?php echo $val; ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-3">
<select class="form-control" name="department_filter">
<option value="">All Departments</option>
<?php foreach($departments as $d): ?>
<option value="<?php echo $d['department_id']; ?>" <?php echo ($department_filter==$d['department_id'])?'selected':''; ?>><?php echo htmlspecialchars($d['department_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4"><button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button> <a href="tax_deductions.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a></div>
</form>
</div>

<div class="card">
<div class="card-header d-flex justify-content-between">
<span><i class="fas fa-percentage"></i> Tax Deductions (<?php echo count($tax_deductions); ?>)</span>
<div>
<button class="btn btn-info btn-sm" data-toggle="modal" data-target="#manageBracketsModal"><i class="fas fa-cogs"></i> Manage Tax</button>
<button class="btn btn-success btn-sm" data-toggle="modal" data-target="#bulkTaxModal"><i class="fas fa-layer-group"></i> Bulk Apply</button>
<button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addTaxModal"><i class="fas fa-plus"></i> Add Tax Deduction</button>
</div>
</div>
<div class="card-body table-responsive">
<table class="table table-striped table-hover">
<thead><tr><th>Employee</th><th>Employee #</th><th>Department</th><th>Tax Type</th><th>Rate (%)</th><th>Amount</th><th>Effective Date</th><th>Actions</th></tr></thead>
<tbody>
<?php if($tax_deductions): foreach($tax_deductions as $tax): ?>
<tr>
<td><?php echo htmlspecialchars($tax['first_name'].' '.$tax['last_name']); ?></td>
<td><?php echo htmlspecialchars($tax['employee_number']); ?></td>
<td><?php echo htmlspecialchars($tax['department_name']??'N/A'); ?></td>
<td><span class="tax-type-badge tax-type-<?php echo strtolower(str_replace([' ', '(', ')', '%'], '-', $tax['tax_type'])); ?>"><?php echo htmlspecialchars($tax['tax_type']); ?></span></td>
<td><?php echo $tax['tax_percentage']?number_format($tax['tax_percentage'],2).'%':'-'; ?></td>
<td>
    <?php if($tax['tax_amount']): ?>
        <span class="text-muted">Confidential</span>
        <button type="button" class="btn btn-sm btn-outline-info ml-2" data-amount="<?php echo htmlspecialchars($tax['tax_amount']); ?>" onclick="showTaxAmount(this)">View</button>
    <?php else: ?>
        -
    <?php endif; ?>
</td>
<td><?php echo date('M d, Y',strtotime($tax['effective_date'])); ?></td>
<td>
<div class="btn-group">
<button type="button" class="btn btn-sm btn-outline-primary" onclick='editTaxDeduction(<?php echo json_encode($tax); ?>)'><i class="fas fa-edit"></i></button>
<form method="post" style="display:inline;" onsubmit="return confirm('Delete this deduction?');">
<input type="hidden" name="action" value="delete_tax_deduction">
<input type="hidden" name="tax_deduction_id" value="<?php echo $tax['tax_deduction_id']; ?>">
<button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
</form>
</div>
</td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="8" class="text-center">No tax deductions found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<!-- Add Tax Modal -->
<div class="modal fade" id="addTaxModal" tabindex="-1" role="dialog">
<div class="modal-dialog" role="document">
<form id="addTaxForm" method="post">
<input type="hidden" name="action" value="add_tax_deduction">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Add Tax Deduction</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<div class="form-group">
<label>Employee</label>
<select class="form-control" name="employee_id" required>
<option value="">Select Employee</option>
<?php foreach($employees as $emp): ?>
<option value="<?php echo $emp['employee_id']; ?>"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label>Tax Type</label>
<select class="form-control" name="tax_type" required>
<?php foreach($tax_types as $key=>$val): ?>
<option value="<?php echo $key; ?>"><?php echo $val; ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label>Tax Percentage (%)</label>
<input type="number" step="0.01" name="tax_percentage" class="form-control">
</div>
<div class="form-group">
<label>Tax Amount (₱)</label>
<input type="number" step="0.01" name="tax_amount" class="form-control">
</div>
<div class="form-group">
<label>Effective Date</label>
<input type="date" name="effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
</div>
</div>
<div class="modal-footer">
<button type="submit" class="btn btn-primary">Add Tax</button>
<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
</div>
</div>
</form>
</div>
</div>

<!-- Bulk Apply Tax Modal -->
<div class="modal fade" id="bulkTaxModal" tabindex="-1" role="dialog">
<div class="modal-dialog" role="document">
<form id="bulkTaxForm" method="post">
<input type="hidden" name="action" value="bulk_apply_tax">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Bulk Apply Tax Deduction</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body">
<div class="form-group">
<label>Department (Optional)</label>
<select class="form-control" name="department_filter">
<option value="">All Departments</option>
<?php foreach($departments as $d): ?>
<option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label>Tax Type</label>
<select class="form-control" name="bulk_tax_type" required>
<?php foreach($tax_types as $key=>$val): ?>
<option value="<?php echo $key; ?>"><?php echo $val; ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label>Tax Percentage (%)</label>
<input type="number" step="0.01" name="bulk_tax_percentage" class="form-control">
</div>
<div class="form-group">
<label>Tax Amount (₱)</label>
<input type="number" step="0.01" name="bulk_tax_amount" class="form-control">
</div>
<div class="form-group">
<label>Effective Date</label>
<input type="date" name="bulk_effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
</div>
</div>
<div class="modal-footer">
<button type="submit" class="btn btn-success">Apply Tax</button>
<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
</div>
</div>
</form>
</div>
</div>

<!-- Manage Tax Brackets Modal -->
<div class="modal fade" id="manageBracketsModal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Manage Tax Brackets</h5>
<button type="button" class="close" data-dismiss="modal">
<span>&times;</span>
</button>
</div>
<div class="modal-body">
<div class="alert alert-info">
<i class="fas fa-info-circle mr-2"></i>
<strong>Note:</strong> Tax brackets are used to automatically calculate Income Tax based on employee salaries. You can update these rates as needed.
</div>

<div class="table-responsive">
<table class="table table-striped table-sm">
<thead>
<tr>
<th>Tax Type</th>
<th>Min Salary</th>
<th>Max Salary</th>
<th>Rate (%)</th>
<th>Fixed Amount</th>
<th>Excess Over</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php
$bracket_stmt = $conn->query("SELECT * FROM tax_brackets ORDER BY tax_type, min_salary");
$brackets = $bracket_stmt->fetchAll(PDO::FETCH_ASSOC);
if ($brackets): foreach($brackets as $bracket): ?>
<tr>
<td><?php echo htmlspecialchars($bracket['tax_type']); ?></td>
<td>₱<?php echo number_format($bracket['min_salary'], 2); ?></td>
<td><?php echo $bracket['max_salary'] ? '₱'.number_format($bracket['max_salary'], 2) : 'Unlimited'; ?></td>
<td><?php echo ($bracket['tax_rate'] * 100).'%'; ?></td>
<td>₱<?php echo number_format($bracket['fixed_amount'], 2); ?></td>
<td>₱<?php echo number_format($bracket['excess_over'], 2); ?></td>
<td>
<button class="btn btn-sm btn-outline-primary" onclick="editBracket(<?php echo htmlspecialchars(json_encode($bracket)); ?>)">
<i class="fas fa-edit"></i>
</button>
</td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="7" class="text-center">No tax brackets found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<hr>
<h6>Add/Edit Tax Bracket</h6>
<form method="post" id="bracketForm">
<input type="hidden" name="action" value="add_bracket">
<input type="hidden" name="bracket_id" id="bracket_id">
<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>Tax Type</label>
<select class="form-control" name="tax_type" id="tax_type" required>
<option value="Income Tax">Income Tax</option>
<option value="Withholding Tax">Withholding Tax</option>
<option value="Other Tax">Other Tax</option>
</select>
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Min Salary (₱)</label>
<input type="number" class="form-control" name="min_salary" id="min_salary" step="0.01" min="0" required>
</div>
</div>
</div>
<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>Max Salary (₱) - Leave empty for unlimited</label>
<input type="number" class="form-control" name="max_salary" id="max_salary" step="0.01" min="0">
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Tax Rate (%)</label>
<input type="number" class="form-control" name="tax_rate" id="tax_rate" step="0.01" min="0" max="100" required>
</div>
</div>
</div>
<div class="row">
<div class="col-md-6">
<div class="form-group">
<label>Fixed Amount (₱)</label>
<input type="number" class="form-control" name="fixed_amount" id="fixed_amount" step="0.01" min="0" value="0">
</div>
</div>
<div class="col-md-6">
<div class="form-group">
<label>Excess Over (₱)</label>
<input type="number" class="form-control" name="excess_over" id="excess_over" step="0.01" min="0" value="0">
</div>
</div>
</div>
<div class="text-right">
<button type="button" class="btn btn-secondary" onclick="resetBracketForm()">Cancel</button>
<button type="submit" class="btn btn-primary">Save Bracket</button>
</div>
</form>
</div>
</div>
</div>
</div>

<!-- Tax Amount Modal -->
<div class="modal fade" id="taxAmountModalTax" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tax Amount</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p id="taxAmountTextTax">₱0.00</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
function editTaxDeduction(tax){
    $('#edit_tax_deduction_id').val(tax.tax_deduction_id);
    $('#edit_tax_type').val(tax.tax_type);
    $('#edit_tax_percentage').val(tax.tax_percentage);
    $('#edit_tax_amount').val(tax.tax_amount);
    $('#edit_effective_date').val(tax.effective_date);
    $('#editTaxModal').modal('show');
}

function showTaxAmount(btn) {
    var amt = parseFloat(btn.getAttribute('data-amount')) || 0;
    var formatted = '₱' + amt.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('taxAmountTextTax').textContent = formatted;
    $('#taxAmountModalTax').modal('show');
}

function editBracket(bracket) {
    // Populate the form with bracket data
    $('#bracket_id').val(bracket.bracket_id);
    $('#tax_type').val(bracket.tax_type);
    $('#min_salary').val(bracket.min_salary);
    $('#max_salary').val(bracket.max_salary || '');
    $('#tax_rate').val(bracket.tax_rate * 100); // Convert to percentage
    $('#fixed_amount').val(bracket.fixed_amount);
    $('#excess_over').val(bracket.excess_over);

    // Change form action to update
    $('#bracketForm input[name="action"]').val('update_bracket');

    // Scroll to form and focus
    $('#manageBracketsModal .modal-body').animate({
        scrollTop: $('#bracketForm').offset().top - $('#manageBracketsModal .modal-body').offset().top + $('#manageBracketsModal .modal-body').scrollTop()
    }, 500);
    $('#min_salary').focus();
}

function resetBracketForm() {
    $('#bracketForm')[0].reset();
    $('#bracket_id').val('');
    $('#bracketForm input[name="action"]').val('add_bracket');
}

// Handle bracket form submission
$('#bracketForm').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.post(window.location.href, formData, function(response) {
        location.reload();
    });
});

// Set today's date for new/bulk forms
document.addEventListener('DOMContentLoaded', function(){
    var today = new Date().toISOString().split('T')[0];
    document.querySelector('#addTaxForm input[name="effective_date"]').value = today;
    document.querySelector('#bulkTaxForm input[name="bulk_effective_date"]').value = today;
});
</script>
</body>
</html>