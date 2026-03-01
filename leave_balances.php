<?php
/**
 * LEAVE BALANCES TRACKING PAGE
 * 
 * Applicable Philippine Republic Acts:
 * - RA 10911 (Paid Leave Bill of 2016)
 *   - Vacation leave balance: 15 days minimum per year
 *   - Sick leave balance: 15 days minimum per year
 *   - Carry-forward rules and conversions
 *   - Pro-rata computation for partial years
 * 
 * - RA 11210 (Expanded Maternity Leave Law of 2018)
 *   - Maternity leave balance: 120 days
 *   - Solo parent female entitlements
 *   - Gender-based leave provisions (female employees only)
 * 
 * - RA 11165 (Paternity Leave Bill of 2018)
 *   - Paternity leave balance: 7-14 days
 *   - Solo parent male entitlements
 *   - Gender-based leave provisions (male employees)
 * 
 * - RA 9403 (Leave Benefits for Solo Parents)
 *   - Additional 5-day solo parent leave allocation
 *   - Certification and benefit computation
 * 
 * - RA 10173 (Data Privacy Act of 2012) - APPLIES TO ALL PAGES
 *   - Leave balance data contains SENSITIVE PERSONAL INFORMATION
 *   - Maternity/Paternity balances reveal health/family status (sensitive)
 *   - Solo parent status is sensitive personal data
 *   - Only access leave balances with legitimate HR business purpose
 *   - Restrict view to authorized personnel (not visible to other employees)
 *   - Encrypt leave balance data in database and during transmission
 *   - Maintain audit logs for all leave balance queries/modifications
 *   - Gender-based leave data must be handled confidentially
 *   - Do not disclose employee leave balances without consent
 * 
 * Compliance Note: Gender-violating leave balance records should be prevented.
 * Balance computations must account for statutory minimums and pro-rata adjustments.
 * Gender restrictions on maternity/paternity leaves are legally mandated.
 * All leave balance information is sensitive personal data under RA 10173.
 */

session_start();
// Restrict access for employees
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] === 'employee') {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';

// Clean up any existing gender violations first
$cleanedUp = cleanupGenderViolations();
if ($cleanedUp > 0) {
    error_log("Cleaned up $cleanedUp gender-violating leave balance records");
}

// Ensure all employees have leave balance records with gender restrictions
$createdBalances = ensureEmployeeLeaveBalances();
if ($createdBalances > 0) {
    error_log("Created $createdBalances missing leave balance records with gender restrictions");
}

// Fetch leave balances data
$leaveBalances = getLeaveBalances();
$leaveTypeTotals = getLeaveTypeTotals();
$utilizationTrend = getLeaveUtilizationTrend();
$lowBalanceAlerts = getLowBalanceAlerts();

// Debug: Log the number of leave balances found
error_log("Leave balances found: " . count($leaveBalances));
if (count($leaveBalances) > 0) {
    error_log("First employee: " . json_encode($leaveBalances[0]));
}

// Get default days from leave_types table
$leaveTypesData = getLeaveTypes();
$defaultDays = [];
foreach ($leaveTypesData as $lt) {
    $defaultDays[$lt['leave_type_name']] = (float)($lt['default_days'] ?? 0);
}

// Build per-employee leave balance map for all leave types (current year)
$employeeLeaveMap = [];
try {
    $sql = "SELECT employee_id, leave_type_id, leaves_remaining 
            FROM leave_balances 
            WHERE year = YEAR(CURDATE())";
    $stmt = $conn->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $empId = $row['employee_id'];
        $typeId = $row['leave_type_id'];
        if (!isset($employeeLeaveMap[$empId])) {
            $employeeLeaveMap[$empId] = [];
        }
        $employeeLeaveMap[$empId][$typeId] = (float)$row['leaves_remaining'];
    }
} catch (PDOException $e) {
    $employeeLeaveMap = [];
}

// Calculate actual totals from database data
$vacationTotal = 0;
$sickTotal = 0;
$maternityTotal = 0;
$paternityTotal = 0;

foreach ($leaveBalances as $employee) {
    $vacationTotal += $employee['vacation_leave'] ?? 0;
    $sickTotal += $employee['sick_leave'] ?? 0;
    $maternityTotal += $employee['maternity_leave'] ?? 0;
    $paternityTotal += $employee['paternity_leave'] ?? 0;
}

// Get default days for each leave type from database
$vacationDefaultDays = $defaultDays['Vacation Leave'] ?? 15;
$sickDefaultDays = $defaultDays['Sick Leave'] ?? 15;
$specialDefaultDays = $defaultDays['Special Leave'] ?? 0;

// Calculate total allocated days based on number of employees and default days from database
$totalEmployees = count($leaveBalances);
$vacationTotalAllocated = $totalEmployees * $vacationDefaultDays;
$sickTotalAllocated = $totalEmployees * $sickDefaultDays;
$specialTotalAllocated = $totalEmployees * $specialDefaultDays;

// Calculate utilization percentages based on actual data from leave_types table
$vacationUtilization = $vacationTotalAllocated > 0 ? round(($vacationTotal / $vacationTotalAllocated) * 100) : 0;
$sickUtilization = $sickTotalAllocated > 0 ? round(($sickTotal / $sickTotalAllocated) * 100) : 0;
$specialUtilization = $specialTotalAllocated > 0 ? round((0 / $specialTotalAllocated) * 100) : 0;

// Find specific leave type totals from actual data
$vacationLeaveTotal = [
    'leave_type_name' => 'Vacation Leave',
    'total_remaining' => $vacationTotal,
    'total_allocated' => $vacationTotalAllocated,
    'utilization_percentage' => $vacationUtilization
];
$sickLeaveTotal = [
    'leave_type_name' => 'Sick Leave',
    'total_remaining' => $sickTotal,
    'total_allocated' => $sickTotalAllocated,
    'utilization_percentage' => $sickUtilization
];
$specialLeaveTotal = [
    'leave_type_name' => 'Special Leave',
    'total_remaining' => 0,
    'total_allocated' => $specialTotalAllocated,
    'utilization_percentage' => $specialUtilization
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Balances - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .balance-card {
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
        }
        
        .balance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-medium);
        }
        
        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: conic-gradient(var(--primary-color) 0% var(--percentage), var(--light-gray) var(--percentage) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .progress-circle::before {
            content: '';
            position: absolute;
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
        }
        
        .progress-text {
            position: relative;
            z-index: 1;
            font-weight: 600;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Leave Balances</h2>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-balance-scale mr-2"></i>Employee Leave Balances</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Department</th>
                                                <?php foreach ($leaveTypesData as $lt): ?>
                                                    <th><?= htmlspecialchars($lt['leave_type_name']); ?></th>
                                                <?php endforeach; ?>
                                                <th>Total Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($leaveBalances)): ?>
                                                <?php foreach ($leaveBalances as $employee): ?>
                                                    <?php
                                                        $empId = $employee['employee_id'];
                                                        $gender = $employee['gender'] ?? '';
                                                        $totalBalance = 0;
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($employee['first_name'] . '+' . $employee['last_name']) ?>&background=E91E63&color=fff&size=35" 
                                                                     alt="Profile" class="profile-image mr-2">
                                                                <div>
                                                                    <h6 class="mb-0"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h6>
                                                                    <small class="text-dark font-weight-bold"><?= htmlspecialchars($employee['employee_number'] ?? 'N/A') ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?= htmlspecialchars($employee['department_name'] ?? 'N/A') ?></td>
                                                        <?php foreach ($leaveTypesData as $lt): ?>
                                                            <?php
                                                                $leaveTypeId = $lt['leave_type_id'];
                                                                $leaveTypeName = $lt['leave_type_name'];

                                                                // Handle gender-restricted leave types as N/A where appropriate
                                                                $isNotApplicable = false;
                                                                if ($leaveTypeName === 'Maternity Leave' && $gender !== 'Female') {
                                                                    $isNotApplicable = true;
                                                                }
                                                                if ($leaveTypeName === 'Paternity Leave' && $gender !== 'Male') {
                                                                    $isNotApplicable = true;
                                                                }
                                                                if ($leaveTypeName === 'Menstrual Disorder Leave' && $gender !== 'Female') {
                                                                    $isNotApplicable = true;
                                                                }

                                                                if ($isNotApplicable) {
                                                                    $badgeClass = 'badge-secondary';
                                                                    $displayValue = 'N/A';
                                                                } else {
                                                                    $remaining = $employeeLeaveMap[$empId][$leaveTypeId] ?? ($lt['default_days'] ?? 0);
                                                                    $totalBalance += (float)$remaining;

                                                                    // Choose badge color based on known leave types
                                                                    $badgeClass = 'badge-secondary';
                                                                    if ($leaveTypeName === 'Vacation Leave') {
                                                                        $badgeClass = 'badge-primary';
                                                                    } elseif ($leaveTypeName === 'Sick Leave') {
                                                                        $badgeClass = 'badge-success';
                                                                    } elseif ($leaveTypeName === 'Maternity Leave') {
                                                                        $badgeClass = 'badge-info';
                                                                    } elseif ($leaveTypeName === 'Paternity Leave') {
                                                                        $badgeClass = 'badge-warning';
                                                                    }

                                                                    $displayValue = (int)$remaining . ' days';
                                                                }
                                                            ?>
                                                            <td>
                                                                <?php if ($displayValue === 'N/A'): ?>
                                                                    <span class="badge badge-secondary" title="Not applicable for <?= htmlspecialchars($gender); ?> employees">N/A</span>
                                                                <?php else: ?>
                                                                    <span class="badge <?= $badgeClass ?>"><?= $displayValue; ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                        <?php endforeach; ?>
                                                        <td>
                                                            <strong><?= (int)$totalBalance ?> days</strong>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="<?= 3 + count($leaveTypesData) ?>" class="text-center text-muted py-4">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        No leave balance data found
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

                <div class="row">
                    <div class="col-md-4">
                        <div class="card balance-card">
                            <div class="card-body text-center">
                                <div class="progress-circle mb-3" style="--percentage: <?= $vacationLeaveTotal['utilization_percentage'] ?>%">
                                    <span class="progress-text"><?= $vacationLeaveTotal['utilization_percentage'] ?>%</span>
                                </div>
                                <h5>Vacation Leave</h5>
                                <h3 class="text-primary"><?= (int)$vacationLeaveTotal['total_remaining'] ?>/<?= (int)$vacationLeaveTotal['total_allocated'] ?> days</h3>
                                <small class="text-muted">Average utilization</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card balance-card">
                            <div class="card-body text-center">
                                <div class="progress-circle mb-3" style="--percentage: <?= $sickLeaveTotal['utilization_percentage'] ?>%">
                                    <span class="progress-text"><?= $sickLeaveTotal['utilization_percentage'] ?>%</span>
                                </div>
                                <h5>Sick Leave</h5>
                                <h3 class="text-success"><?= (int)$sickLeaveTotal['total_remaining'] ?>/<?= (int)$sickLeaveTotal['total_allocated'] ?> days</h3>
                                <small class="text-muted">Average utilization</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-line mr-2"></i>Leave Utilization Trend</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Vacation Leave:</span>
                                    <strong>↑ 15% this quarter</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Sick Leave:</span>
                                    <strong>↓ 5% this quarter</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Overall Utilization:</span>
                                    <strong class="text-success">↓ 8% improvement</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle mr-2"></i>Low Balance Alerts</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning mb-2">
                                    <small>3 employees have less than 2 days of vacation leave remaining</small>
                                </div>
                                <div class="alert alert-danger mb-2">
                                    <small>1 employee has exhausted sick leave balance</small>
                                </div>
                                <div class="alert alert-info">
                                    <small>5 employees have full leave balances</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
