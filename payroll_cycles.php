<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

// BIR Tax Calculation Function
function calculateBIRTax($grossIncome) {
    $brackets = [
        ['min' => 0, 'max' => 20833, 'rate' => 0, 'fixed' => 0],
        ['min' => 20833, 'max' => 33333, 'rate' => 0.15, 'fixed' => 0],
        ['min' => 33333, 'max' => 66667, 'rate' => 0.20, 'fixed' => 2500],
        ['min' => 66667, 'max' => 166667, 'rate' => 0.25, 'fixed' => 10833.33],
        ['min' => 166667, 'max' => 666667, 'rate' => 0.30, 'fixed' => 40833.33],
        ['min' => 666667, 'max' => PHP_INT_MAX, 'rate' => 0.35, 'fixed' => 200833.33]
    ];

    foreach ($brackets as $bracket) {
        if ($grossIncome > $bracket['min'] && $grossIncome <= $bracket['max']) {
            $tax = ($grossIncome - $bracket['min']) * $bracket['rate'] + $bracket['fixed'];
            return round($tax, 2);
        }
    }
    return 0;
}

/* ==============================
   AUTO CREATE PAYROLL CYCLES
   ============================== */
function autoCreatePayrollCycles($conn) {
    $year  = date('Y');
    $month = date('m');

    $cycles = [
        ['1st Half Payroll', "$year-$month-01", "$year-$month-15", "$year-$month-20"],
        ['2nd Half Payroll', "$year-$month-16", date('Y-m-t'), date('Y-m-t')]
    ];

    foreach ($cycles as $cycle) {
        $check = $conn->prepare("SELECT COUNT(*) FROM payroll_cycles WHERE pay_period_start = ? AND pay_period_end = ?");
        $check->execute([$cycle[1], $cycle[2]]);

        if ($check->fetchColumn() == 0) {
            $insert = $conn->prepare("INSERT INTO payroll_cycles (cycle_name, pay_period_start, pay_period_end, pay_date, status) VALUES (?, ?, ?, ?, 'Pending')");
            $insert->execute($cycle);
        }
    }
}

autoCreatePayrollCycles($conn);

/* ==============================
   PROCESS PAYROLL & ADD CYCLE
   ============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'process_payroll') {

        $payroll_cycle_id = $_POST['payroll_cycle_id'];

        $cycle_stmt = $conn->prepare("SELECT cycle_name FROM payroll_cycles WHERE payroll_cycle_id = ?");
        $cycle_stmt->execute([$payroll_cycle_id]);
        $cycle_name = $cycle_stmt->fetchColumn();

        error_log("Processing payroll for cycle ID: $payroll_cycle_id, Cycle Name: '$cycle_name'");

        try {
            $conn->beginTransaction();

            $conn->prepare("UPDATE payroll_cycles SET status='Processing' WHERE payroll_cycle_id=?")->execute([$payroll_cycle_id]);

            $employees = $conn->query("
                SELECT ep.employee_id, ep.current_salary, ss.basic_salary, ss.allowances, ss.deductions
                FROM employee_profiles ep
                LEFT JOIN salary_structures ss ON ep.employee_id = ss.employee_id
                WHERE ep.employment_status IN ('Full-time','Part-time','Contract','Contractual')
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Total employees found for payroll: " . count($employees));

            foreach ($employees as $emp) {

                $exists = $conn->prepare("SELECT COUNT(*) FROM payroll_transactions WHERE employee_id=? AND payroll_cycle_id=?");
                $exists->execute([$emp['employee_id'], $payroll_cycle_id]);
                if ($exists->fetchColumn() > 0) continue;

                $monthly_gross = $emp['basic_salary'] ? ($emp['basic_salary'] + $emp['allowances']) : $emp['current_salary'];

                if (strpos($cycle_name, 'Half') !== false) {
                    $gross = $monthly_gross / 2;
                    error_log("HALF SALARY: Cycle '$cycle_name', Monthly Gross: ₱$monthly_gross, Half Gross: ₱$gross");
                } else {
                    $gross = $monthly_gross;
                    error_log("FULL SALARY: Cycle '$cycle_name', Gross: ₱$gross");
                }

                // TAX - Half for half cycles, full for full month cycles
                $tax = 0;
                $taxStmt = $conn->prepare("SELECT SUM(CASE WHEN tax_percentage IS NOT NULL THEN ? * (tax_percentage / 100) ELSE tax_amount END) FROM tax_deductions WHERE employee_id=?");
                $taxStmt->execute([$monthly_gross, $emp['employee_id']]);
                $monthly_tax = $taxStmt->fetchColumn() ?? 0;
                
                if (strpos($cycle_name, 'Half') !== false) {
                    $tax = $monthly_tax / 2;
                } else {
                    $tax = $monthly_tax;
                }

                // STATUTORY - Half for half cycles, full for full month cycles
                $statutory = 0;
                $statStmt = $conn->prepare("SELECT SUM(deduction_amount) FROM statutory_deductions WHERE employee_id=?");
                $statStmt->execute([$emp['employee_id']]);
                $monthly_statutory = $statStmt->fetchColumn() ?? 0;
                
                if (strpos($cycle_name, 'Half') !== false) {
                    $statutory = $monthly_statutory / 2;  // Half month
                } else {
                    $statutory = $monthly_statutory;  // Full month
                }

                $other = $emp['deductions'] ?? 0;
                $net = $gross - $tax - $statutory - $other;

                $conn->prepare("
                    INSERT INTO payroll_transactions
                    (employee_id,payroll_cycle_id,gross_pay,tax_deductions,statutory_deductions,other_deductions,net_pay,processed_date,status)
                    VALUES (?,?,?,?,?,?,?,NOW(),'Processed')
                ")->execute([$emp['employee_id'], $payroll_cycle_id, $gross, $tax, $statutory, $other, $net]);
            }

            $conn->prepare("UPDATE payroll_cycles SET status='Completed' WHERE payroll_cycle_id=?")->execute([$payroll_cycle_id]);
            $conn->commit();
            $success_message = "Payroll processed successfully.";

        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = $e->getMessage();
        }
    }

    elseif ($_POST['action'] === 'add_cycle') {
        try {
            $stmt = $conn->prepare("INSERT INTO payroll_cycles (cycle_name, pay_period_start, pay_period_end, pay_date, status) VALUES (?, ?, ?, ?, 'Pending')");
            $stmt->execute([$_POST['cycle_name'], $_POST['pay_period_start'], $_POST['pay_period_end'], $_POST['pay_date']]);
            header("Location: payroll_cycles.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error_message = "Error adding payroll cycle: " . $e->getMessage();
        }
    }

    elseif ($_POST['action'] === 'reprocess_payroll') {
        $payroll_cycle_id = $_POST['payroll_cycle_id'];
        try {
            $conn->beginTransaction();
            $conn->prepare("DELETE FROM payroll_transactions WHERE payroll_cycle_id=?")->execute([$payroll_cycle_id]);
            $conn->prepare("UPDATE payroll_cycles SET status='Pending' WHERE payroll_cycle_id=?")->execute([$payroll_cycle_id]);
            $conn->commit();
            $success_message = "Payroll cycle reset successfully. You can now reprocess it.";
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Error resetting payroll cycle: " . $e->getMessage();
        }
    }
}

/* ==============================
   FETCH PAYROLL CYCLES
   ============================== */
$payroll_cycles = $conn->query("
    SELECT pc.*, COUNT(pt.payroll_transaction_id) employee_count, SUM(pt.gross_pay) total_gross_pay, SUM(pt.net_pay) total_net_pay
    FROM payroll_cycles pc
    LEFT JOIN payroll_transactions pt ON pc.payroll_cycle_id = pt.payroll_cycle_id
    GROUP BY pc.payroll_cycle_id
    ORDER BY pc.pay_period_start DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = "Payroll cycle added successfully!";
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
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f5f5; margin: 0; padding: 0; }
.sidebar { height: 100vh; background-color: #E91E63; color: #fff; padding-top: 20px; position: fixed; width: 250px; z-index: 1030; }
.sidebar .nav-link { color: rgba(255, 255, 255, 0.8); margin-bottom: 5px; padding: 12px 20px; cursor: pointer; border-radius: 4px; }
.sidebar .nav-link:hover { background-color: rgba(255, 255, 255, 0.1); color: #fff; }
.sidebar .nav-link.active { background-color: #fff; color: #E91E63; }
.sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
.main-content { margin-left: 250px; padding: 90px 20px 20px; width: calc(100% - 250px); }
.card { margin-bottom: 20px; box-shadow: 0 2px 4px rgba(128, 0, 0, 0.05); border: none; border-radius: 8px; overflow: hidden; }
.card-header { background-color: #fff; border-bottom: 1px solid rgba(128, 0, 0, 0.1); padding: 15px 20px; font-weight: bold; color: #E91E63; }
.card-body { padding: 20px; }
.table th { border-top: none; color: #E91E63; font-weight: 600; }
.table td { vertical-align: middle; color: #333; border-color: rgba(128, 0, 0, 0.1); }
.btn-primary { background-color: #E91E63; border-color: #E91E63; }
.btn-primary:hover { background-color: #be0945ff; border-color: #be0945ff; }
.badge-pending { background-color: #ffc107; color: #212529; }
.badge-processing { background-color: #17a2b8; }
.badge-completed { background-color: #28a745; }
.modal-header { background-color: #E91E63; color: #fff; }
.section-title { color: #E91E63; margin-bottom: 25px; font-weight: 600; }
.btn-process { background-color: #28a745; border-color: #28a745; }
.btn-process:hover { background-color: #218838; border-color: #1e7e34; }
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
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error_message; ?>
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-calendar-alt mr-2"></i> Payroll Cycles</span>
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addCycleModal">
                            <i class="fas fa-plus"></i> Add Cycle
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
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($payroll_cycles)): ?>
                                        <?php foreach ($payroll_cycles as $cycle): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($cycle['cycle_name']); ?></td>
                                                <td><?php echo date('M d', strtotime($cycle['pay_period_start'])); ?> - <?php echo date('M d, Y', strtotime($cycle['pay_period_end'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($cycle['pay_date'])); ?></td>
                                                <td><span class="badge badge-<?php echo strtolower($cycle['status']); ?>"><?php echo htmlspecialchars($cycle['status']); ?></span></td>
                                                <td><?php echo $cycle['employee_count']; ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="payroll_transactions.php?cycle_id=<?php echo $cycle['payroll_cycle_id']; ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i> View Details</a>
                                                        <?php if ($cycle['status'] == 'Pending'): ?>
                                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to process this payroll cycle?');">
                                                                <input type="hidden" name="action" value="process_payroll">
                                                                <input type="hidden" name="payroll_cycle_id" value="<?php echo $cycle['payroll_cycle_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-process"><i class="fas fa-play"></i></button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($cycle['status'] == 'Completed'): ?>
                                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to reprocess this payroll cycle?');">
                                                                <input type="hidden" name="action" value="reprocess_payroll">
                                                                <input type="hidden" name="payroll_cycle_id" value="<?php echo $cycle['payroll_cycle_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-redo"></i></button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center">No payroll cycles found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addCycleModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Payroll Cycle</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="cycle_name">Cycle Name</label>
                            <input type="text" class="form-control" id="cycle_name" name="cycle_name" placeholder="e.g., January 1st Half Payroll" required>
                        </div>
                        <div class="form-group">
                            <label for="pay_period_start">Pay Period Start</label>
                            <input type="date" class="form-control" id="pay_period_start" name="pay_period_start" required>
                        </div>
                        <div class="form-group">
                            <label for="pay_period_end">Pay Period End</label>
                            <input type="date" class="form-control" id="pay_period_end" name="pay_period_end" required>
                        </div>
                        <div class="form-group">
                            <label for="pay_date">Pay Date</label>
                            <input type="date" class="form-control" id="pay_date" name="pay_date" required>
                        </div>
                        <input type="hidden" name="action" value="add_cycle">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Cycle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
