<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config.php';

// Function to calculate working days between two dates (excluding weekends)
function getWorkingDays($startDate, $endDate) {
    $begin = strtotime($startDate);
    $end = strtotime($endDate);
    $workingDays = 0;
    for ($i = $begin; $i <= $end; $i = strtotime('+1 day', $i)) {
        $day = date('N', $i); // 1 = Monday, 7 = Sunday
        if ($day < 6) { // Monday to Friday
            $workingDays++;
        }
    }
    return $workingDays;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_payroll_cycle':
                $cycle_name = $_POST['cycle_name'];
                $pay_period_start = $_POST['pay_period_start'];
                $pay_period_end = $_POST['pay_period_end'];
                $pay_date = $_POST['pay_date'];
                $status = $_POST['status'] ?? 'Pending';
                
                try {
                    $sql = "INSERT INTO payroll_cycles (cycle_name, pay_period_start, pay_period_end, pay_date, status) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$cycle_name, $pay_period_start, $pay_period_end, $pay_date, $status]);
                    $success_message = "Payroll cycle created successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error creating payroll cycle: " . $e->getMessage();
                }
                break;
                
            case 'update_payroll_cycle':
                $payroll_cycle_id = $_POST['payroll_cycle_id'];
                $cycle_name = $_POST['cycle_name'];
                $pay_period_start = $_POST['pay_period_start'];
                $pay_period_end = $_POST['pay_period_end'];
                $pay_date = $_POST['pay_date'];
                $status = $_POST['status'];
                
                try {
                    $sql = "UPDATE payroll_cycles SET cycle_name = ?, pay_period_start = ?, pay_period_end = ?, 
                            pay_date = ?, status = ? WHERE payroll_cycle_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$cycle_name, $pay_period_start, $pay_period_end, $pay_date, $status, $payroll_cycle_id]);
                    $success_message = "Payroll cycle updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating payroll cycle: " . $e->getMessage();
                }
                break;
                
            case 'delete_payroll_cycle':
                $payroll_cycle_id = $_POST['payroll_cycle_id'];
                
                try {
                    // Check if there are any payroll transactions for this cycle
                    $check_sql = "SELECT COUNT(*) FROM payroll_transactions WHERE payroll_cycle_id = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->execute([$payroll_cycle_id]);
                    $transaction_count = $check_stmt->fetchColumn();
                    
                    if ($transaction_count > 0) {
                        $error_message = "Cannot delete payroll cycle: There are existing payroll transactions for this cycle.";
                    } else {
                        $sql = "DELETE FROM payroll_cycles WHERE payroll_cycle_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$payroll_cycle_id]);
                        $success_message = "Payroll cycle deleted successfully!";
                    }
                } catch (PDOException $e) {
                    $error_message = "Error deleting payroll cycle: " . $e->getMessage();
                }
                break;
                
            case 'process_payroll':
    $payroll_cycle_id = $_POST['payroll_cycle_id'];

    try {
        $conn->beginTransaction();

        // Update cycle status to Processing
        $update_sql = "UPDATE payroll_cycles SET status = 'Processing' WHERE payroll_cycle_id = ?";
        $conn->prepare($update_sql)->execute([$payroll_cycle_id]);

        // Get all active employees with salary structures
        $emp_sql = "SELECT DISTINCT ep.employee_id, ep.current_salary, ss.basic_salary, ss.allowances, ss.deductions
                    FROM employee_profiles ep
                    LEFT JOIN salary_structures ss ON ep.employee_id = ss.employee_id
                    WHERE ep.employment_status IN ('Full-time', 'Part-time', 'Contract')
                    ORDER BY ep.employee_id";
        $employees = $conn->query($emp_sql)->fetchAll(PDO::FETCH_ASSOC);

        $processed_count = 0;

            foreach ($employees as $employee) {
                // Check if already processed
                $check_sql = "SELECT COUNT(*) FROM payroll_transactions WHERE employee_id = ? AND payroll_cycle_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->execute([$employee['employee_id'], $payroll_cycle_id]);
                if ($check_stmt->fetchColumn() > 0) {
                    continue; // skip duplicates
                }

                // Calculate gross pay
                $gross_pay = $employee['basic_salary'] 
                            ? ($employee['basic_salary'] + $employee['allowances']) 
                            : $employee['current_salary'];

                // Calculate leave days taken in the payroll period
                $leave_sql = "SELECT COALESCE(SUM(total_days), 0) as leave_days
                              FROM leave_requests
                              WHERE employee_id = ?
                              AND status = 'Approved'
                              AND start_date <= (SELECT pay_period_end FROM payroll_cycles WHERE payroll_cycle_id = ?)
                              AND end_date >= (SELECT pay_period_start FROM payroll_cycles WHERE payroll_cycle_id = ?)";
                $leave_stmt = $conn->prepare($leave_sql);
                $leave_stmt->execute([$employee['employee_id'], $payroll_cycle_id, $payroll_cycle_id]);
                $leave_result = $leave_stmt->fetch(PDO::FETCH_ASSOC);
                $leave_days = $leave_result ? $leave_result['leave_days'] : 0;

                // Calculate attendance days present in the payroll period
                $attendance_sql = "SELECT COUNT(*) as present_days
                                   FROM attendance
                                   WHERE employee_id = ?
                                   AND attendance_date BETWEEN
                                       (SELECT pay_period_start FROM payroll_cycles WHERE payroll_cycle_id = ?)
                                       AND (SELECT pay_period_end FROM payroll_cycles WHERE payroll_cycle_id = ?)
                                   AND status = 'Present'";
                $attendance_stmt = $conn->prepare($attendance_sql);
                $attendance_stmt->execute([$employee['employee_id'], $payroll_cycle_id, $payroll_cycle_id]);
                $attendance_result = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
                $present_days = $attendance_result ? $attendance_result['present_days'] : 0;

                // Calculate overtime hours in the payroll period
                $overtime_sql = "SELECT COALESCE(SUM(a.overtime_hours), 0) as total_overtime_hours
                                 FROM attendance a
                                 LEFT JOIN employee_shifts es ON a.employee_id = es.employee_id
                                     AND a.attendance_date = es.assigned_date
                                 WHERE a.employee_id = ?
                                 AND a.attendance_date BETWEEN
                                     (SELECT pay_period_start FROM payroll_cycles WHERE payroll_cycle_id = ?)
                                     AND (SELECT pay_period_end FROM payroll_cycles WHERE payroll_cycle_id = ?)
                                 AND (a.overtime_hours > 0 OR es.is_overtime = 1)";
                $overtime_stmt = $conn->prepare($overtime_sql);
                $overtime_stmt->execute([$employee['employee_id'], $payroll_cycle_id, $payroll_cycle_id]);
                $overtime_result = $overtime_stmt->fetch(PDO::FETCH_ASSOC);
                $total_overtime_hours = $overtime_result ? $overtime_result['total_overtime_hours'] : 0;

                // Assume a fixed number of working days in the payroll period (e.g., 22)
                $working_days = 22;

                // Adjust gross pay based on attendance and leave
                $payable_days = max(0, $present_days + $leave_days);
                $daily_rate = $gross_pay / $working_days;
                $adjusted_gross_pay = $daily_rate * $payable_days;

                // Calculate overtime pay (1.5x regular hourly rate)
                $regular_hours_per_day = 8; // Assume 8 hours per day
                $hourly_rate = $daily_rate / $regular_hours_per_day;
                $overtime_rate = $hourly_rate * 1.5; // 1.5x for overtime
                $overtime_pay = $overtime_rate * $total_overtime_hours;

                // Add overtime pay to gross pay
                $adjusted_gross_pay += $overtime_pay;

                // Calculate tax deductions from tax_deductions table
                $tax_deductions = 0;
                $tax_sql = "SELECT tax_percentage, tax_amount
                           FROM tax_deductions
                           WHERE employee_id = ?
                           AND effective_date <= (SELECT pay_period_end FROM payroll_cycles WHERE payroll_cycle_id = ?)
                           ORDER BY effective_date DESC
                           LIMIT 1";
                $tax_stmt = $conn->prepare($tax_sql);
                $tax_stmt->execute([$employee['employee_id'], $payroll_cycle_id]);
                $tax_result = $tax_stmt->fetch(PDO::FETCH_ASSOC);

                if ($tax_result) {
                    if ($tax_result['tax_percentage']) {
                        // Calculate percentage-based tax
                        $tax_deductions = $adjusted_gross_pay * ($tax_result['tax_percentage'] / 100);
                    } elseif ($tax_result['tax_amount']) {
                        // Use fixed tax amount
                        $tax_deductions = $tax_result['tax_amount'];
                    }
                }

                // Calculate statutory deductions from database
                $statutory_deductions = 0;
                $statutory_sql = "SELECT SUM(deduction_amount) as total_statutory 
                                 FROM statutory_deductions 
                                 WHERE employee_id = ? 
                                 AND effective_date <= (SELECT pay_period_end FROM payroll_cycles WHERE payroll_cycle_id = ?)";
                $statutory_stmt = $conn->prepare($statutory_sql);
                $statutory_stmt->execute([$employee['employee_id'], $payroll_cycle_id]);
                $statutory_result = $statutory_stmt->fetch(PDO::FETCH_ASSOC);
                if ($statutory_result && $statutory_result['total_statutory']) {
                    $statutory_deductions = $statutory_result['total_statutory'];
                }

                $other_deductions = $employee['deductions'] ?? 0;

                $net_pay = $adjusted_gross_pay - $tax_deductions - $statutory_deductions - $other_deductions;

                // Insert payroll transaction
                $trans_sql = "INSERT INTO payroll_transactions 
                             (employee_id, payroll_cycle_id, gross_pay, tax_deductions, 
                              statutory_deductions, other_deductions, net_pay, processed_date, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'Processed')";
                $conn->prepare($trans_sql)->execute([
                    $employee['employee_id'], 
                    $payroll_cycle_id, 
                    $adjusted_gross_pay, 
                    $tax_deductions, 
                    $statutory_deductions, 
                    $other_deductions, 
                    $net_pay
                ]);

                $processed_count++;
            }

        // Update cycle status to Completed
        $complete_sql = "UPDATE payroll_cycles SET status = 'Completed' WHERE payroll_cycle_id = ?";
        $conn->prepare($complete_sql)->execute([$payroll_cycle_id]);

        $conn->commit();
        $success_message = "Payroll processed successfully! {$processed_count} employees processed.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = "Error processing payroll: " . $e->getMessage();
    }
    break;

        }
    }
}

// Fetch payroll cycles with transaction counts
try {
    $sql = "SELECT pc.*, 
                   COUNT(pt.payroll_transaction_id) as employee_count,
                   SUM(pt.gross_pay) as total_gross_pay,
                   SUM(pt.net_pay) as total_net_pay
            FROM payroll_cycles pc
            LEFT JOIN payroll_transactions pt ON pc.payroll_cycle_id = pt.payroll_cycle_id
            GROUP BY pc.payroll_cycle_id
            ORDER BY pc.pay_period_start DESC";
    $stmt = $conn->query($sql);
    $payroll_cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payroll_cycles = [];
    $error_message = "Error fetching payroll cycles: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Cycles - HR System</title>
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
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-processing {
            background-color: #17a2b8;
        }
        .badge-completed {
            background-color: #28a745;
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
        .salary-amount {
            font-weight: bold;
            color: #800000;
        }
        .btn-process {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-process:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Payroll Cycles Management</h2>
                
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

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-calendar-alt mr-2"></i> Payroll Cycles</span>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCycleModal">
                            <i class="fas fa-plus"></i> Create New Cycle
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Cycle Name</th>
                                        <th>Pay Period</th>
                                        <th>Pay Date</th>
                                        <th>Status</th>
                                        <th>Employees</th>
                                        <th>Total Gross</th>
                                        <th>Total Net</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($payroll_cycles)): ?>
                                        <?php foreach ($payroll_cycles as $cycle): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($cycle['cycle_name']); ?></td>
                                                <td>
                                                    <?php echo date('M d', strtotime($cycle['pay_period_start'])); ?> - 
                                                    <?php echo date('M d, Y', strtotime($cycle['pay_period_end'])); ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($cycle['pay_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    $status = strtolower($cycle['status']);
                                                    $badge_class = 'badge-' . $status;
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo htmlspecialchars($cycle['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $cycle['employee_count']; ?></td>
                                                <td class="salary-amount">
                                                    ₱<?php echo number_format($cycle['total_gross_pay'] ?? 0, 2); ?>
                                                </td>
                                                <td class="salary-amount">
                                                    ₱<?php echo number_format($cycle['total_net_pay'] ?? 0, 2); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if ($cycle['status'] == 'Pending'): ?>
                                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to process this payroll cycle? This will calculate payroll for all employees.');">
                                                                <input type="hidden" name="action" value="process_payroll">
                                                                <input type="hidden" name="payroll_cycle_id" value="<?php echo $cycle['payroll_cycle_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-process" title="Process Payroll">
                                                                    <i class="fas fa-play"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCycle(<?php echo htmlspecialchars(json_encode($cycle)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        
                                                        <?php if ($cycle['employee_count'] == 0): ?>
                                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this payroll cycle?');">
                                                                <input type="hidden" name="action" value="delete_payroll_cycle">
                                                                <input type="hidden" name="payroll_cycle_id" value="<?php echo $cycle['payroll_cycle_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($cycle['status'] == 'Completed'): ?>
                                                            <a href="payroll_transactions.php?cycle_id=<?php echo $cycle['payroll_cycle_id']; ?>" class="btn btn-sm btn-outline-info" title="View Transactions">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No payroll cycles found.</td>
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

    <!-- Add Payroll Cycle Modal -->
    <div class="modal fade" id="addCycleModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Payroll Cycle</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_payroll_cycle">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="cycle_name">Cycle Name</label>
                                    <input type="text" class="form-control" id="cycle_name" name="cycle_name" placeholder="e.g. January 2024 Payroll" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pay_period_start">Pay Period Start</label>
                                    <input type="date" class="form-control" id="pay_period_start" name="pay_period_start" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pay_period_end">Pay Period End</label>
                                    <input type="date" class="form-control" id="pay_period_end" name="pay_period_end" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pay_date">Pay Date</label>
                                    <input type="date" class="form-control" id="pay_date" name="pay_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="Pending">Pending</option>
                                        <option value="Processing">Processing</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Cycle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Payroll Cycle Modal -->
    <div class="modal fade" id="editCycleModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Payroll Cycle</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_payroll_cycle">
                        <input type="hidden" name="payroll_cycle_id" id="edit_payroll_cycle_id">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="edit_cycle_name">Cycle Name</label>
                                    <input type="text" class="form-control" id="edit_cycle_name" name="cycle_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_pay_period_start">Pay Period Start</label>
                                    <input type="date" class="form-control" id="edit_pay_period_start" name="pay_period_start" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_pay_period_end">Pay Period End</label>
                                    <input type="date" class="form-control" id="edit_pay_period_end" name="pay_period_end" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_pay_date">Pay Date</label>
                                    <input type="date" class="form-control" id="edit_pay_date" name="pay_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_status">Status</label>
                                    <select class="form-control" id="edit_status" name="status">
                                        <option value="Pending">Pending</option>
                                        <option value="Processing">Processing</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Cycle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function editCycle(cycle) {
            $('#edit_payroll_cycle_id').val(cycle.payroll_cycle_id);
            $('#edit_cycle_name').val(cycle.cycle_name);
            $('#edit_pay_period_start').val(cycle.pay_period_start);
            $('#edit_pay_period_end').val(cycle.pay_period_end);
            $('#edit_pay_date').val(cycle.pay_date);
            $('#edit_status').val(cycle.status);
            $('#editCycleModal').modal('show');
        }

        // Auto-generate cycle name based on dates
        document.getElementById('pay_period_start').addEventListener('change', function() {
            const startDate = new Date(this.value);
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            const cycleName = monthNames[startDate.getMonth()] + " " + startDate.getFullYear() + " Payroll";
            document.getElementById('cycle_name').value = cycleName;
        });
    </script>
</body>
</html>