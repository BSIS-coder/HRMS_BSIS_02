<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config.php';

// Get filter parameters
$cycle_id = $_GET['cycle_id'] ?? null;
$employee_search = $_GET['employee_search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid CSRF token!";
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_transaction_status':
                $transaction_id = $_POST['transaction_id'];
                $status = $_POST['status'];

                // ✅ validate allowed statuses
                $valid_statuses = ['Pending', 'Processed', 'Paid', 'Cancelled'];
                if (!in_array($status, $valid_statuses)) {
                    $error_message = "Invalid status value!";
                    break;
                }
                
                try {
                    $sql = "UPDATE payroll_transactions SET status = ? WHERE payroll_transaction_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$status, $transaction_id]);
                    $success_message = "Transaction status updated successfully!";
                } catch (PDOException $e) {

                    $error_message = "Error updating transaction: " . $e->getMessage();
                }
                break;
                
            case 'generate_payslips':
                $cycle_id = $_POST['cycle_id'];
                
                try {
                // Get all processed transactions for this cycle
                $trans_sql = "SELECT pt.*, ep.employee_number, pi.first_name, pi.last_name
                              FROM payroll_transactions pt
                              JOIN employee_profiles ep ON pt.employee_id = ep.employee_id
                              JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                              WHERE pt.payroll_cycle_id = ? AND pt.status = 'Processed'";
                    $trans_stmt = $conn->prepare($trans_sql);
                    $trans_stmt->execute([$cycle_id]);
                    $transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $generated_count = 0;
                    
                    foreach ($transactions as $transaction) {
                        // Check if payslip already exists
                        $check_sql = "SELECT payslip_id FROM payslips WHERE payroll_transaction_id = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->execute([$transaction['payroll_transaction_id']]);
                        
                        if (!$check_stmt->fetch()) {
                            // Generate payslip record
                            $payslip_url = "payslips/payslip_" . $transaction['employee_number'] . "_" . date('Y_m', strtotime($transaction['processed_date'])) . ".pdf";
                            
                            $payslip_sql = "INSERT INTO payslips (payroll_transaction_id, employee_id, payslip_url, generated_date, status) 
                                           VALUES (?, ?, ?, NOW(), 'Generated')";
                            $payslip_stmt = $conn->prepare($payslip_sql);
                            $payslip_stmt->execute([
                                $transaction['payroll_transaction_id'],
                                $transaction['employee_id'],
                                $payslip_url
                            ]);
                            
                            $generated_count++;
                        }
                    }
                    
                    $success_message = "Payslips generated successfully! {$generated_count} new payslips created.";
                    
                } catch (PDOException $e) {
                    $error_message = "Error generating payslips: " . $e->getMessage();
                }
                break;
        }
    }
}

// Build the query with filters
$sql = "SELECT 
            pt.*, 
            pc.cycle_name, 
            ep.employee_number, 
            pi.first_name, 
            pi.last_name,
            jr.title as job_title, 
            d.department_name,
            ps.payslip_id, 
            ps.status as payslip_status,
            COALESCE(td.tax_deductions, 0) AS tax_deductions
        FROM payroll_transactions pt
        JOIN payroll_cycles pc ON pt.payroll_cycle_id = pc.payroll_cycle_id
        JOIN employee_profiles ep ON pt.employee_id = ep.employee_id
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_name
        LEFT JOIN payslips ps ON pt.payroll_transaction_id = ps.payroll_transaction_id

        /* ✅ Added: pull tax deduction totals from the tax_deductions table */
        LEFT JOIN (
            SELECT employee_id, SUM(tax_amount) AS tax_deductions
            FROM tax_deductions
            GROUP BY employee_id
        ) td ON pt.employee_id = td.employee_id

        WHERE 1=1";

$params = [];

if ($cycle_id) {
    $sql .= " AND pt.payroll_cycle_id = ?";
    $params[] = $cycle_id;
}

if ($employee_search) {
    $sql .= " AND (pi.first_name LIKE ? OR pi.last_name LIKE ? OR ep.employee_number LIKE ?)";
    $params[] = "%$employee_search%";
    $params[] = "%$employee_search%";
    $params[] = "%$employee_search%";
}

if ($status_filter) {
    $sql .= " AND pt.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY pt.processed_date DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
     // ✅ Add this: filter tax deductions that match the payroll cycle period
    if ($cycle_id) {
        $date_query = "SELECT pay_period_start, pay_period_end FROM payroll_cycles WHERE payroll_cycle_id = ?";
        $date_stmt = $conn->prepare($date_query);
        $date_stmt->execute([$cycle_id]);
        $cycle_dates = $date_stmt->fetch(PDO::FETCH_ASSOC);

        if ($cycle_dates) {
            $pay_start = $cycle_dates['pay_period_start'];
            $pay_end = $cycle_dates['pay_period_end'];

            // Modify tax deduction totals dynamically for this cycle
            foreach ($transactions as &$transaction) {
                $tax_sql = "SELECT 
                                COALESCE(SUM(
                                    CASE 
                                        WHEN tax_percentage IS NOT NULL THEN (tax_percentage / 100) * ?
                                        ELSE tax_amount
                                    END
                                ), 0) AS total_tax
                            FROM tax_deductions
                            WHERE employee_id = ?
                            AND effective_date BETWEEN ? AND ?";
                $tax_stmt = $conn->prepare($tax_sql);
                $tax_stmt->execute([
                    $transaction['gross_pay'],
                    $transaction['employee_id'],
                    $pay_start,
                    $pay_end
                ]);
                $tax_result = $tax_stmt->fetch(PDO::FETCH_ASSOC);
                $transaction['tax_deductions'] = $tax_result['total_tax'];
                $transaction['net_pay'] = $transaction['gross_pay'] - $transaction['tax_deductions'] - $transaction['statutory_deductions'] - $transaction['other_deductions'];
            }
        }
    }

} catch (PDOException $e) {
} catch (PDOException $e) {
    $transactions = [];
    $error_message = "Error fetching transactions: " . $e->getMessage();
}

// Get payroll cycles for filter dropdown
try {
    $cycles_sql = "SELECT payroll_cycle_id, cycle_name FROM payroll_cycles ORDER BY pay_period_start DESC";
    $cycles_stmt = $conn->query($cycles_sql);
    $cycles = $cycles_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cycles = [];
}

// Calculate totals for current filtered results
$total_gross = array_sum(array_column($transactions, 'gross_pay'));
$total_net = array_sum(array_column($transactions, 'net_pay'));
$total_tax = array_sum(array_column($transactions, 'tax_deductions'));
$total_statutory = array_sum(array_column($transactions, 'statutory_deductions'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Transactions - HR System</title>
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
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-processed {
            background-color: #28a745;
        }
        .badge-paid {
            background-color: #17a2b8;
        }
        .badge-cancelled {
            background-color: #dc3545;
        }
        .summary-card {
            background: linear-gradient(135deg, #E91E63 0%, #a60000 100%);
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
        .filters-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .table-sm th, .table-sm td {
            padding: 0.5rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Payroll Transactions</h2>
                
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

                <!-- Summary Cards -->
                <?php if (!empty($transactions)): ?>
                <div class="summary-card">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="summary-item">
                                <div class="summary-amount">₱<?php echo number_format($total_gross, 2); ?></div>
                                <div class="summary-label">Total Gross Pay</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-item">
                                <div class="summary-amount">₱<?php echo number_format($total_tax, 2); ?></div>
                                <div class="summary-label">Total Tax Deductions</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-item">
                                <div class="summary-amount">₱<?php echo number_format($total_statutory, 2); ?></div>
                                <div class="summary-label">Total Statutory Deductions</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-item">
                                <div class="summary-amount">₱<?php echo number_format($total_net, 2); ?></div>
                                <div class="summary-label">Total Net Pay</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filters-card">
                    <form method="get" class="row">
                        <div class="col-md-3">
                            <select name="cycle_id" class="form-control form-control-sm">
                                <option value="">All Cycles</option>
                                <?php foreach ($cycles as $cycle_option): ?>
                                    <option value="<?php echo $cycle_option['payroll_cycle_id']; ?>" 
                                            <?php echo ($cycle_id == $cycle_option['payroll_cycle_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cycle_option['cycle_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="employee_search" class="form-control form-control-sm" 
                                   placeholder="Search employee..." value="<?php echo htmlspecialchars($employee_search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="status_filter" class="form-control form-control-sm">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?php echo ($status_filter == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="Processed" <?php echo ($status_filter == 'Processed') ? 'selected' : ''; ?>>Processed</option>
                                <option value="Paid" <?php echo ($status_filter == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                <option value="Cancelled" <?php echo ($status_filter == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="payroll_transactions.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-exchange-alt mr-2"></i> 
                            Payroll Transactions (<?php echo count($transactions); ?> records)
                        </span>
                        <div>
                            <?php if ($cycle_id && !empty($transactions)): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Generate payslips for all employees in this cycle?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="generate_payslips">
                                    <input type="hidden" name="cycle_id" value="<?php echo $cycle_id; ?>">
                                    <button type="submit" class="btn btn-success btn-sm mr-2">
                                        <i class="fas fa-file-invoice"></i> Generate Payslips
                                    </button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary btn-sm" onclick="exportToCSV()">
                                <i class="fas fa-download"></i> Export CSV
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm" id="transactionsTable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Emp. #</th>
                                        <th>Department</th>
                                        <th>Cycle</th>
                                        <th>Gross Pay</th>
                                        <th>Tax Deductions</th>
                                        <th>Statutory Deductions</th>
                                        <th>Other Deductions</th>
                                        <th>Net Pay</th>
                                        <th>Status</th>
                                        <th>Payslip</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($transactions)): ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['employee_number']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['cycle_name']); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($transaction['gross_pay'], 2); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($transaction['tax_deductions'], 2); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($transaction['statutory_deductions'], 2); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($transaction['other_deductions'], 2); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($transaction['net_pay'], 2); ?></td>
                                                <td>
                                                    <?php 
                                                    $status = strtolower($transaction['status']);
                                                    $badge_class = 'badge-' . $status;
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo htmlspecialchars($transaction['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($transaction['payslip_id']): ?>
                                                        <span class="badge badge-success">
                                                            <?php echo htmlspecialchars($transaction['payslip_status']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not Generated</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                                onclick="viewTransactionDetails(<?php echo htmlspecialchars(json_encode($transaction)); ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if ($transaction['status'] != 'Cancelled'): ?>
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                    <i class="fas fa-cog"></i>
                                                                </button>
                                                                <div class="dropdown-menu">
                                                                    <form method="post" style="display: inline;">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                        <input type="hidden" name="action" value="update_transaction_status">
                                                                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['payroll_transaction_id']; ?>">
                                                                        <input type="hidden" name="status" value="Paid">
                                                                        <button type="submit" class="dropdown-item">
                                                                            <i class="fas fa-check text-success mr-2"></i>Mark as Paid
                                                                        </button>
                                                                    </form>
                                                                    <form method="post" style="display: inline;">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                                        <input type="hidden" name="action" value="update_transaction_status">
                                                                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['payroll_transaction_id']; ?>">
                                                                        <input type="hidden" name="status" value="Cancelled">
                                                                        <button type="submit" class="dropdown-item"
                                                                                onclick="return confirm('Are you sure you want to cancel this transaction?');">
                                                                            <i class="fas fa-times text-danger mr-2"></i>Cancel
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($transaction['payslip_id']): ?>
                                                            <a href="view_payslip.php?id=<?php echo $transaction['payslip_id']; ?>" 
                                                               class="btn btn-sm btn-outline-success" target="_blank">
                                                                <i class="fas fa-file-invoice"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="12" class="text-center">No payroll transactions found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transaction Details</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Employee Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td><strong>Name:</strong></td><td id="detail_employee_name"></td></tr>
                                <tr><td><strong>Employee #:</strong></td><td id="detail_employee_number"></td></tr>
                                <tr><td><strong>Department:</strong></td><td id="detail_department"></td></tr>
                                <tr><td><strong>Job Title:</strong></td><td id="detail_job_title"></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Payroll Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td><strong>Cycle:</strong></td><td id="detail_cycle_name"></td></tr>
                                <tr><td><strong>Processed Date:</strong></td><td id="detail_processed_date"></td></tr>
                                <tr><td><strong>Status:</strong></td><td id="detail_status"></td></tr>
                            </table>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Pay Breakdown</h6>
                            <table class="table table-sm">
                                <tr><td>Gross Pay:</td><td class="text-right salary-amount" id="detail_gross_pay"></td></tr>
                                <tr><td>Tax Deductions:</td><td class="text-right salary-amount" id="detail_tax_deductions"></td></tr>
                                <tr><td>Statutory Deductions:</td><td class="text-right salary-amount" id="detail_statutory_deductions"></td></tr>
                                <tr><td>Other Deductions:</td><td class="text-right salary-amount" id="detail_other_deductions"></td></tr>
                                <tr class="table-active"><td><strong>Net Pay:</strong></td><td class="text-right salary-amount" id="detail_net_pay"></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Payslip Status</h6>
                            <div id="detail_payslip_info"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function viewTransactionDetails(transaction) {
            $('#detail_employee_name').text(transaction.first_name + ' ' + transaction.last_name);
            $('#detail_employee_number').text(transaction.employee_number);
            $('#detail_department').text(transaction.department_name || 'N/A');
            $('#detail_job_title').text(transaction.job_title || 'N/A');
            $('#detail_cycle_name').text(transaction.cycle_name);
            $('#detail_processed_date').text(new Date(transaction.processed_date).toLocaleString());
            $('#detail_status').html('<span class="badge badge-' + transaction.status.toLowerCase() + '">' + transaction.status + '</span>');
            
            $('#detail_gross_pay').text('₱' + parseFloat(transaction.gross_pay).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#detail_tax_deductions').text('₱' + parseFloat(transaction.tax_deductions).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#detail_statutory_deductions').text('₱' + parseFloat(transaction.statutory_deductions).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#detail_other_deductions').text('₱' + parseFloat(transaction.other_deductions).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#detail_net_pay').text('₱' + parseFloat(transaction.net_pay).toLocaleString('en-US', {minimumFractionDigits: 2}));
            
            if (transaction.payslip_id) {
                $('#detail_payslip_info').html('<span class="badge badge-success">' + transaction.payslip_status + '</span><br><small>Payslip available for download</small>');
            } else {
                $('#detail_payslip_info').html('<span class="text-muted">Payslip not generated</span>');
            }
            
            $('#transactionDetailsModal').modal('show');
        }

        function exportToCSV() {
            const table = document.getElementById('transactionsTable');
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
            link.setAttribute('download', 'payroll_transactions_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>