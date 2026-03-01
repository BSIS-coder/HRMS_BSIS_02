<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config.php';

// Get current user role and employee ID
$current_user_role = $_SESSION['role'];
$current_employee_id = null;

// If user is an employee, get their employee ID
if ($current_user_role === 'employee') {
    try {
        $user_sql = "SELECT employee_id FROM employee_profiles WHERE user_id = ?";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->execute([$_SESSION['user_id']]);
        $current_employee_id = $user_stmt->fetchColumn();
    } catch (PDOException $e) {
        $error_message = "Error fetching user information: " . $e->getMessage();
    }
}

// Get filter parameters
$employee_filter = $_GET['employee_filter'] ?? '';
$cycle_filter = $_GET['cycle_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$year_filter = $_GET['year_filter'] ?? date('Y');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_as_sent':
                $payslip_id = $_POST['payslip_id'];
                
                try {
                    $sql = "UPDATE payslips SET status = 'Sent' WHERE payslip_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$payslip_id]);
                    $success_message = "Payslip marked as sent!";
                } catch (PDOException $e) {
                    $error_message = "Error updating payslip: " . $e->getMessage();
                }
                break;
                
            case 'bulk_email_payslips':
                $cycle_id = $_POST['cycle_id'];
                
                try {
                    // Get all payslips for the cycle
                    $payslips_sql = "SELECT p.*, ep.employee_number, pi.first_name, pi.last_name, u.email
                                     FROM payslips p
                                     JOIN payroll_transactions pt ON p.payroll_transaction_id = pt.payroll_transaction_id
                                     JOIN employee_profiles ep ON p.employee_id = ep.employee_id
                                     JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                                     JOIN users u ON ep.user_id = u.user_id
                                     WHERE pt.payroll_cycle_id = ? AND p.status = 'Generated'";
                    $payslips_stmt = $conn->prepare($payslips_sql);
                    $payslips_stmt->execute([$cycle_id]);
                    $payslips = $payslips_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $sent_count = 0;
                    
                    foreach ($payslips as $payslip) {
                        // Update status to Sent (in real implementation, you would send actual emails)
                        $update_sql = "UPDATE payslips SET status = 'Sent' WHERE payslip_id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->execute([$payslip['payslip_id']]);
                        
                        $sent_count++;
                    }
                    
                    $success_message = "Payslips sent successfully! {$sent_count} emails sent.";
                    
                } catch (PDOException $e) {
                    $error_message = "Error sending payslips: " . $e->getMessage();
                }
                break;
        }
    }
}

// Build the query with filters and role-based access
$sql = "SELECT 
            p.*, 
            pt.gross_pay, pt.net_pay, pt.tax_deductions, 
            COALESCE(SUM(CASE WHEN LOWER(sd.deduction_type) = 'gsis' THEN sd.deduction_amount / 2 END), 0) AS gsis_contribution,
COALESCE(SUM(CASE WHEN LOWER(sd.deduction_type) = 'philhealth' THEN sd.deduction_amount / 2 END), 0) AS philhealth_contribution,
COALESCE(SUM(CASE WHEN LOWER(sd.deduction_type) = 'pag-ibig' THEN sd.deduction_amount / 2 END), 0) AS pagibig_contribution,
COALESCE(SUM(sd.deduction_amount) / 2, 0) AS statutory_deductions,
            ep.employee_number, pi.first_name, pi.last_name,
            jr.title AS job_title, d.department_name, 
            pc.cycle_name, pc.pay_period_start, pc.pay_period_end
        FROM payslips p
        JOIN payroll_transactions pt ON p.payroll_transaction_id = pt.payroll_transaction_id
        JOIN employee_profiles ep ON p.employee_id = ep.employee_id
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_name
        LEFT JOIN payroll_cycles pc ON pt.payroll_cycle_id = pc.payroll_cycle_id
        LEFT JOIN statutory_deductions sd ON sd.employee_id = ep.employee_id
        WHERE 1=1";

$params = [];

// Role-based filtering
if ($current_user_role === 'employee' && $current_employee_id) {
    $sql .= " AND p.employee_id = ?";
    $params[] = $current_employee_id;
}

if ($employee_filter && $current_user_role !== 'employee') {
    $sql .= " AND (pi.first_name LIKE ? OR pi.last_name LIKE ? OR ep.employee_number LIKE ?)";
    $params[] = "%$employee_filter%";
    $params[] = "%$employee_filter%";
    $params[] = "%$employee_filter%";
}

if ($cycle_filter) {
    $sql .= " AND pt.payroll_cycle_id = ?";
    $params[] = $cycle_filter;
}

if ($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($year_filter) {
    $sql .= " AND YEAR(pt.processed_date) = ?";
    $params[] = $year_filter;
}

$sql .= " GROUP BY p.payslip_id";

$sql .= " ORDER BY pt.processed_date DESC, pi.first_name ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payslips = [];
    $error_message = "Error fetching payslips: " . $e->getMessage();
}

// Get payroll cycles for filter dropdown (only if not employee)
$cycles = [];
if ($current_user_role !== 'employee') {
    try {
        $cycles_sql = "SELECT payroll_cycle_id, cycle_name FROM payroll_cycles ORDER BY pay_period_start DESC";
        $cycles_stmt = $conn->query($cycles_sql);
        $cycles = $cycles_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cycles = [];
    }
}

// Get available years
try {
    $years_sql = "SELECT DISTINCT YEAR(processed_date) as year FROM payroll_transactions ORDER BY year DESC";
    $years_stmt = $conn->query($years_sql);
    $years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $years = [date('Y')];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslips - HR System</title>
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
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(128, 0, 0, 0.1);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(128, 0, 0, 0.1);
            padding: 15px 20px;
            font-weight: bold;
            color: #E91E63;
        }
        .card-header i {
            color: #E91E63;
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
        .salary-amount {
            font-weight: bold;
            color: #E91E63;
        }
        .badge-generated {
            background-color: #17a2b8;
        }
        .badge-sent {
            background-color: #28a745;
        }
        .badge-viewed {
            background-color: #6c757d;
        }
        .filters-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .payslip-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .payslip-card:hover {
            border-color: #800000;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.1);
        }
        .payslip-header {
            border-bottom: 2px solid #E91E63;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .payslip-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #800000;
            margin: 0;
        }
        .payslip-subtitle {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }
        .pay-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .pay-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #800000;
        }
        .pay-label {
            color: #666;
            font-size: 0.9rem;
        }
        .download-btn {
            background: linear-gradient(135deg, #800000 0%, #a60000 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .download-btn:hover {
            background: linear-gradient(135deg, #660000 0%, #800000 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        }
        .employee-view .section-title {
            text-align: center;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">
                    <?php if ($current_user_role === 'employee'): ?>
                        My Payslips
                    <?php else: ?>
                        Payslips Management
                    <?php endif; ?>
                </h2>
                
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

                <!-- Filters (Hidden for employee role) -->
                <?php if ($current_user_role !== 'employee'): ?>
                <div class="filters-card">
                    <form method="get" class="row align-items-end">
                        <div class="col-md-3">
                            <label for="employee_filter" class="form-label">Employee</label>
                            <input type="text" name="employee_filter" class="form-control form-control-sm" 
                                   placeholder="Search employee..." value="<?php echo htmlspecialchars($employee_filter); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="cycle_filter" class="form-label">Payroll Cycle</label>
                            <select name="cycle_filter" class="form-control form-control-sm">
                                <option value="">All Cycles</option>
                                <?php foreach ($cycles as $cycle_option): ?>
                                    <option value="<?php echo $cycle_option['payroll_cycle_id']; ?>" 
                                            <?php echo ($cycle_filter == $cycle_option['payroll_cycle_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cycle_option['cycle_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status_filter" class="form-label">Status</label>
                            <select name="status_filter" class="form-control form-control-sm">
                                <option value="">All Statuses</option>
                                <option value="Generated" <?php echo ($status_filter == 'Generated') ? 'selected' : ''; ?>>Generated</option>
                                <option value="Sent" <?php echo ($status_filter == 'Sent') ? 'selected' : ''; ?>>Sent</option>
                                <option value="Viewed" <?php echo ($status_filter == 'Viewed') ? 'selected' : ''; ?>>Viewed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="year_filter" class="form-label">Year</label>
                            <select name="year_filter" class="form-control form-control-sm">
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php echo ($year_filter == $year) ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="payslips.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Payslips Display -->
                <?php if ($current_user_role === 'employee'): ?>
                    <!-- Employee View - Card Layout -->
                    <div class="employee-view">
                        <?php if (!empty($payslips)): ?>
                            <?php foreach ($payslips as $payslip): ?>
                                <div class="payslip-card">
                                    <div class="payslip-header">
                                        <h5 class="payslip-title"><?php echo htmlspecialchars($payslip['cycle_name']); ?></h5>
                                        <p class="payslip-subtitle">
                                            Pay Period: <?php echo date('M d', strtotime($payslip['pay_period_start'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($payslip['pay_period_end'])); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="pay-summary">
                                                <div>
                                                    <div class="pay-label">Gross Pay</div>
                                                    <div class="pay-amount">Confidential</div>
                                                </div>
                                                <div class="text-center">
                                                    <i class="fas fa-lock" style="color: #800000; font-size: 1.2rem;"></i>
                                                </div>
                                                <div class="text-right">
                                                    <div class="pay-label">Net Pay</div>
                                                    <div class="pay-amount">Confidential</div>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar mr-1"></i>
                                                    Generated: <?php echo date('M d, Y g:i A', strtotime($payslip['generated_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 text-right">
                                            <div class="mb-3">
                                                <?php 
                                                $status = strtolower($payslip['status']);
                                                $badge_class = 'badge-' . $status;
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?> p-2">
                                                    <?php echo htmlspecialchars($payslip['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <button type="button" class="download-btn" onclick="viewPayslip(<?php echo $payslip['payslip_id']; ?>)">
                                                <i class="fas fa-download mr-2"></i>Download Payslip
                                            </button>
                                            
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-outline-info btn-sm" 
                                                        onclick="viewPayslipDetails(<?php echo htmlspecialchars(json_encode($payslip)); ?>)">
                                                    <i class="fas fa-eye mr-1"></i>View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Payslips Found</h4>
                                <p class="text-muted">You don't have any payslips available yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Admin/HR/Manager View - Table Layout -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-receipt mr-2"></i> 
                                Payslips (<?php echo count($payslips); ?> records)
                            </span>
                            <div>
                                <?php if ($cycle_filter && !empty($payslips)): ?>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Send payslips via email to all employees in this cycle?');">
                                        <input type="hidden" name="action" value="bulk_email_payslips">
                                        <input type="hidden" name="cycle_id" value="<?php echo $cycle_filter; ?>">
                                        <button type="submit" class="btn btn-success btn-sm mr-2">
                                            <i class="fas fa-envelope-bulk"></i> Email All Payslips
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-primary btn-sm" onclick="exportPayslipsToCSV()">
                                    <i class="fas fa-download"></i> Export CSV
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="payslipsTable">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Employee #</th>
                                            <th>Department</th>
                                            <th>Payroll Cycle</th>
                                            <th>Pay Period</th>
                                            <!-- Gross/Net hidden for privacy; use View Details -->
                                            <th class="text-muted">Amounts (hidden)</th>
                                            <th>Generated Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($payslips)): ?>
                                            <?php foreach ($payslips as $payslip): 
                                                // Calculate net pay dynamically: Gross - Tax - Statutory - Other
                                                $gross = floatval($payslip['gross_pay']);
                                                $tax = floatval($payslip['tax_deductions'] ?? 0);
                                                $statutory = floatval($payslip['statutory_deductions'] ?? 0);
                                                $other = floatval($payslip['other_deductions'] ?? 0);
                                                $calculated_net = $gross - $tax - $statutory - $other;
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($payslip['employee_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($payslip['cycle_name']); ?></td>
                                                    <td>
                                                        <?php echo date('M d', strtotime($payslip['pay_period_start'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($payslip['pay_period_end'])); ?>
                                                    </td>
                                                    <td class="text-muted">Confidential</td>
                                                    <td><?php echo date('M d, Y g:i A', strtotime($payslip['generated_date'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        $status = strtolower($payslip['status']);
                                                        $badge_class = 'badge-' . $status;
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo htmlspecialchars($payslip['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                                    onclick="viewPayslipDetails(<?php echo htmlspecialchars(json_encode($payslip)); ?>)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            
                                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                                    onclick="viewPayslip(<?php echo $payslip['payslip_id']; ?>)">
                                                                <i class="fas fa-download"></i>
                                                            </button>
                                                            
                                                            <?php if ($payslip['status'] === 'Generated'): ?>
                                                                <form method="post" style="display: inline;">
                                                                    <input type="hidden" name="action" value="mark_as_sent">
                                                                    <input type="hidden" name="payslip_id" value="<?php echo $payslip['payslip_id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Mark as Sent">
                                                                        <i class="fas fa-envelope"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="text-center">No payslips found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Enhanced Payslip Details Modal (Professional Black/Gray Layout) -->
<div class="modal fade" id="payslipDetailsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content" style="font-family: 'Segoe UI', sans-serif; color: #333;">
      <div class="modal-header bg-light">
        <h5 class="modal-title font-weight-bold text-dark">
          
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <!-- Header with Logo and Municipality Info -->
        <div class="text-center mb-3">
          <img src="image/GARAY.jpg" alt="Municipality Logo" style="width: 60px; height: 60px;">
          <h5 class="mt-2 mb-0 font-weight-bold text-uppercase">Municipality of Norzagaray, Bulacan</h5>
          <small class="text-muted">Payroll Division – Official Payslip</small>
          <hr style="border: 1px solid #ccc;">
        </div>

        <!-- Employee & Payroll Info -->
        <div class="row">
          <div class="col-md-6">
            <h6 class="font-weight-bold text-dark">Employee Information</h6>
            <table class="table table-sm table-borderless text-muted mb-3">
              <tr><td><strong>Name:</strong></td><td id="detail_employee_name"></td></tr>
              <tr><td><strong>Employee #:</strong></td><td id="detail_employee_number"></td></tr>
              <tr><td><strong>Department:</strong></td><td id="detail_department"></td></tr>
              <tr><td><strong>Job Title:</strong></td><td id="detail_job_title"></td></tr>
              <tr><td><strong>Salary Grade:</strong></td><td id="detail_salary_grade">N/A</td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <h6 class="font-weight-bold text-dark">Payroll Information</h6>
            <table class="table table-sm table-borderless text-muted mb-3">
              <tr><td><strong>Cycle:</strong></td><td id="detail_cycle_name"></td></tr>
              <tr><td><strong>Pay Period:</strong></td><td id="detail_pay_period"></td></tr>
              <tr><td><strong>Generated:</strong></td><td id="detail_generated_date"></td></tr>
              <tr><td><strong>Status:</strong></td><td id="detail_status"></td></tr>
              <tr><td><strong>Reference No.:</strong></td><td id="detail_reference_no"></td></tr>
            </table>
          </div>
        </div>

        <hr>

        <!-- Earnings and Deductions Breakdown -->
        <div class="row">
          <div class="col-md-6">
            <h6 class="font-weight-bold text-dark">Earnings</h6>
            <table class="table table-sm table-bordered text-center">
              <thead class="thead-light">
                <tr><th>Description</th><th>Amount (₱)</th></tr>
              </thead>
              <tbody>
                <tr><td>Basic Pay</td><td id="detail_basic_pay">0.00</td></tr>
                <tr><td>Allowances</td><td id="detail_allowances">0.00</td></tr>
                <tr><td>Overtime / Bonuses</td><td id="detail_overtime">0.00</td></tr>
                <tr class="font-weight-bold bg-light">
                  <td>Total Gross</td><td id="detail_gross_total">0.00</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="col-md-6">
            <h6 class="font-weight-bold text-dark">Deductions</h6>
            <table class="table table-sm table-bordered text-center">
              <thead class="thead-light">
                <tr><th>Description</th><th>Amount (₱)</th></tr>
              </thead>
              <tbody>
                <tr><td>Tax</td><td id="detail_tax">0.00</td></tr>
                <tr><td>GSIS</td><td id="detail_gsis">0.00</td></tr>
                <tr><td>PhilHealth</td><td id="detail_philhealth">0.00</td></tr>
                <tr><td>Pag-IBIG</td><td id="detail_pagibig">0.00</td></tr>
                <tr><td>Other Deductions</td><td id="detail_other_deductions">0.00</td></tr>
                <tr class="font-weight-bold bg-light">
                  <td>Total Deductions</td><td id="detail_total_deductions">0.00</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Net Pay Summary -->
        <div class="text-center my-3">
          <h5 class="font-weight-bold text-dark">Net Pay</h5>
          <h3 id="detail_net_pay" class="font-weight-bold text-success">₱0.00</h3>
          
          <small class="text-muted">(After all deductions)</small>
        </div>

        <!-- Remarks Section -->
        <div class="mt-4">
          <h6 class="font-weight-bold text-dark">Remarks / Notes:</h6>
          <p id="detail_remarks" class="border p-2 text-muted" style="min-height: 40px;">N/A</p>
        </div>

        <!-- Signatures Section -->
        <div class="mt-5">
          <div class="row text-center">
            <div class="col-md-6">
              <hr style="border: 1px solid #666; width: 80%;">
              <p class="mb-0 font-weight-bold">Prepared by</p>
            </div>
            <div class="col-md-6">
              <hr style="border: 1px solid #666; width: 80%;">
              <p class="mb-0 font-weight-bold">Approved by</p>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-dark" id="modal_download_btn">
          <i class="fas fa-download mr-2"></i>Download Payslip
        </button>
      </div>
    </div>
  </div>
</div>


    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function viewPayslipDetails(payslip) {
            $('#detail_employee_name').text(payslip.first_name + ' ' + payslip.last_name);
            $('#detail_employee_number').text(payslip.employee_number);
            $('#detail_department').text(payslip.department_name || 'N/A');
            $('#detail_job_title').text(payslip.job_title || 'N/A');
            $('#detail_cycle_name').text(payslip.cycle_name);
            
            const payPeriod = new Date(payslip.pay_period_start).toLocaleDateString('en-US', {month: 'short', day: 'numeric'}) + 
                             ' - ' + new Date(payslip.pay_period_end).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
            $('#detail_pay_period').text(payPeriod);
            
            $('#detail_generated_date').text(new Date(payslip.generated_date).toLocaleString());
            $('#detail_status').html('<span class="badge badge-' + payslip.status.toLowerCase() + '">' + payslip.status + '</span>');
            
            $('#detail_gross_pay').text('₱' + parseFloat(payslip.gross_pay).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#detail_net_pay').text('₱' + parseFloat(payslip.net_pay).toLocaleString('en-US', {minimumFractionDigits: 2}));
            // Treat gross_pay as basic pay (semi-monthly already computed)
let gross = parseFloat(payslip.gross_pay || 0);
let tax = parseFloat(payslip.tax_deductions || 0);
let statutory = parseFloat(payslip.statutory_deductions || 0);
let other = parseFloat(payslip.other_deductions || 0);

let totalDeductions = tax + statutory + other;

// Set earnings
$('#detail_basic_pay').text(gross.toLocaleString('en-US', {minimumFractionDigits: 2}));
$('#detail_allowances').text('0.00');
$('#detail_overtime').text('0.00');
$('#detail_gross_total').text(gross.toLocaleString('en-US', {minimumFractionDigits: 2}));

// Set total deductions correctly
$('#detail_total_deductions').text(totalDeductions.toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#detail_tax').text('₱' + (payslip.tax_deductions ? parseFloat(payslip.tax_deductions).toLocaleString('en-US', {minimumFractionDigits: 2}) : '0.00'));
            $('#detail_gsis').text('₱' + (payslip.gsis_contribution ? parseFloat(payslip.gsis_contribution).toLocaleString('en-US', {minimumFractionDigits: 2}) : '0.00'));
            $('#detail_philhealth').text('₱' + (payslip.philhealth_contribution ? parseFloat(payslip.philhealth_contribution).toLocaleString('en-US', {minimumFractionDigits: 2}) : '0.00'));
            $('#detail_pagibig').text('₱' + (payslip.pagibig_contribution ? parseFloat(payslip.pagibig_contribution).toLocaleString('en-US', {minimumFractionDigits: 2}) : '0.00'));
           $('#detail_total_deductions').text('₱' + totalDeductions.toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#modal_download_btn').off('click').on('click', function() { viewPayslip(payslip.payslip_id); });
            
            $('#payslipDetailsModal').modal('show');
        }

       function viewPayslip(payslipId) {
    // Open the PDF in a new tab
    window.open('generate_payslip_pdf.php?payslip_id=' + payslipId, '_blank');
}

        function exportPayslipsToCSV() {
            const table = document.getElementById('payslipsTable');
            if (!table) return;
            
            const rows = Array.from(table.querySelectorAll('tr'));
            
            const csvContent = rows.map(row => {
                const cells = Array.from(row.querySelectorAll('th, td'));
                return cells.slice(0, -1).map(cell => {
                    let content = cell.textContent.trim();
                    return '"' + content.replace(/"/g, '""') + '"';
                }).join(',');
            }).join('\n');
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'payslips_export_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
    
    
</body>
</html>