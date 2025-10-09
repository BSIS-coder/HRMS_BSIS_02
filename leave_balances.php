<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';

// Fetch leave balances data
$leaveBalances = getLeaveBalances();
$leaveTypeTotals = getLeaveTypeTotals();
$utilizationTrend = getLeaveUtilizationTrend();
$lowBalanceAlerts = getLowBalanceAlerts();

// Calculate actual totals from database data
$vacationTotal = 0;
$sickTotal = 0;
$maternityTotal = 0;
$paternityTotal = 0;

foreach ($leaveBalances as $employee) {
    $vacationTotal += $employee['vacation_leave'];
    $sickTotal += $employee['sick_leave'];
    $maternityTotal += $employee['maternity_leave'];
    $paternityTotal += $employee['paternity_leave'];
}

// Calculate utilization percentages based on actual data
$vacationUtilization = count($leaveBalances) > 0 ? round(($vacationTotal / (count($leaveBalances) * 15)) * 100) : 0;
$sickUtilization = count($leaveBalances) > 0 ? round(($sickTotal / (count($leaveBalances) * 10)) * 100) : 0;

// Find specific leave type totals from actual data
$vacationLeaveTotal = [
    'leave_type_name' => 'Vacation Leave',
    'total_remaining' => $vacationTotal,
    'total_allocated' => count($leaveBalances) * 15,
    'utilization_percentage' => $vacationUtilization
];
$sickLeaveTotal = [
    'leave_type_name' => 'Sick Leave',
    'total_remaining' => $sickTotal,
    'total_allocated' => count($leaveBalances) * 10,
    'utilization_percentage' => $sickUtilization
];
$specialLeaveTotal = [
    'leave_type_name' => 'Special Leave',
    'total_remaining' => 0,
    'total_allocated' => 0,
    'utilization_percentage' => 0
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
                                                <th>Vacation Leave</th>
                                                <th>Sick Leave</th>
                                                <th>Maternity Leave</th>
                                                <th>Paternity Leave</th>
                                                <th>Total Balance</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($leaveBalances)): ?>
                                                <?php foreach ($leaveBalances as $employee): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($employee['first_name'] . '+' . $employee['last_name']) ?>&background=E91E63&color=fff&size=35" 
                                                                     alt="Profile" class="profile-image mr-2">
                                                                <div>
                                                                    <h6 class="mb-0"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h6>
                                                                    <small class="text-muted"><?= htmlspecialchars($employee['employee_code']) ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?= htmlspecialchars($employee['department_name'] ?? 'N/A') ?></td>
                                                        <td>
                                                            <span class="badge badge-primary"><?= $employee['vacation_leave'] ?> days</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-success"><?= $employee['sick_leave'] ?> days</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-info"><?= $employee['maternity_leave'] ?> days</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-warning"><?= $employee['paternity_leave'] ?> days</span>
                                                        </td>
                                                        <td>
                                                            <strong><?= $employee['total_balance'] ?> days</strong>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">
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
                                <h3 class="text-primary"><?= $vacationLeaveTotal['total_remaining'] ?>/<?= $vacationLeaveTotal['total_allocated'] ?> days</h3>
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
                                <h3 class="text-success"><?= $sickLeaveTotal['total_remaining'] ?>/<?= $sickLeaveTotal['total_allocated'] ?> days</h3>
                                <small class="text-muted">Average utilization</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card balance-card">
                            <div class="card-body text-center">
                                <div class="progress-circle mb-3" style="--percentage: 0%">
                                    <span class="progress-text">0%</span>
                                </div>
                                <h5>Special Leave</h5>
                                <h3 class="text-info">0/0 days</h3>
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
