<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Role-based access control
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
    header('Location: login.php');
    exit;
}

require_once 'report_functions.php';

$page_title = 'Reports';

// Handle Clear Action
if (isset($_GET['clear'])) {
    unset($_SESSION['last_report_query']);
    header("Location: payroll_reports.php");
    exit;
}

// Restore from session if no GET params but session exists (Persistence)
if (empty($_GET['cycle_id']) && !empty($_SESSION['last_report_query'])) {
    $query = http_build_query($_SESSION['last_report_query']);
    header("Location: payroll_reports.php?" . $query);
    exit;
}

// Get params from GET instead of POST
$report_type = $_GET['report_type'] ?? 'general_payroll';
$cycle_id = isset($_GET['cycle_id']) ? (int)$_GET['cycle_id'] : null;
$department_id = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? (int)$_GET['department_id'] : null;

$report_data = [];
$report_title = '';
$report_headers = [];
$selected_cycle = null;

$departments = getDepartments($conn);
$cycles = getPayrollCycles($conn);

// Check if there are any employees at all
try {
    $empCheck = $conn->query("SELECT COUNT(*) FROM employee_profiles");
    $totalEmployees = $empCheck ? $empCheck->fetchColumn() : 0;
} catch (Exception $e) {
    $totalEmployees = 0;
}

if ($totalEmployees == 0) {
    echo "<div class='alert alert-warning m-3'><strong>Warning:</strong> No employees found in the database. Please add employees first.</div>";
}

// Get selected cycle details for display
if ($cycle_id) {
    // Save to session for persistence
    $_SESSION['last_report_query'] = [
        'report_type' => $report_type,
        'cycle_id' => $cycle_id,
        'department_id' => $department_id
    ];

    foreach ($cycles as $c) {
        if ($c['payroll_cycle_id'] == $cycle_id) {
            $selected_cycle = $c;
            break;
        }
    }

    switch ($report_type) {
        case 'general_payroll':
            $report_title = 'General Payroll Sheet';
            $report_headers = ['Employee', 'Employee #', 'Position', 'Basic Salary', 'Allowances', 'Deductions', 'Net Pay', 'Payslip'];
            $report_data = getGeneralPayroll($conn, $cycle_id, $department_id);
            break;
        case 'remittance':
            $report_title = 'Remittance Summary Report';
            $report_headers = ['Remittance Type', 'Total Amount to Remit'];
            $report_data = getRemittanceReport($conn, $cycle_id, $department_id);
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HRMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=rose">
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
        .page-header {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .report-header {
            padding: 15px 20px;
            background-color: #f1f1f1;
            border-bottom: 1px solid #dee2e6;
        }
        .btn-primary {
            background: #E91E63;
            border-color: #E91E63;
        }
        .btn-primary:hover {
            background: #C2185B;
            border-color: #C2185B;
        }
        .table-responsive {
            max-height: 60vh;
        }
        @media print {
            .sidebar, .top-navbar, .filter-card, .page-header, .btn, .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .report-card { box-shadow: none !important; border: none !important; }
            .table-responsive { max-height: none !important; overflow: visible !important; }
            .card-footer { display: none !important; }
            body { background-color: white !important; }
            .report-header { background-color: white !important; border-bottom: 2px solid #000 !important; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; // Assuming you have a main navigation file ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <div class="main-content">
                <div class="page-header">
                    <h1 class="mb-0"><i class="fas fa-chart-pie mr-3"></i><?php echo $page_title; ?></h1>
                    <p class="lead mb-0">Generate official municipal payroll documents.</p>
                </div>

                <!-- Filter Section -->
                <div class="filter-card">
                    <h4 class="mb-4">Report Filters</h4>
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="report_type">Report Type</label>
                                    <select name="report_type" id="report_type" class="form-control">
                                        <option value="general_payroll" <?php echo ($report_type == 'general_payroll') ? 'selected' : ''; ?>>General Payroll Sheet</option>
                                        <option value="remittance" <?php echo ($report_type == 'remittance') ? 'selected' : ''; ?>>Remittance Summary</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cycle_id">Payroll Cycle</label>
                                    <select name="cycle_id" id="cycle_id" class="form-control" required>
                                        <option value="">-- Select Cycle --</option>
                                        <?php foreach ($cycles as $cycle): ?>
                                            <option value="<?php echo $cycle['payroll_cycle_id']; ?>" <?php echo ($cycle_id == $cycle['payroll_cycle_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cycle['cycle_name'] . ' (' . date('M d', strtotime($cycle['pay_period_start'])) . ' - ' . date('M d, Y', strtotime($cycle['pay_period_end'])) . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="department_id">Department</label>
                                    <select name="department_id" id="department_id" class="form-control">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>" <?php echo ($department_id == $dept['department_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <?php if(isset($_SESSION['last_report_query'])): ?>
                                <a href="payroll_reports.php?clear=1" class="btn btn-outline-secondary mr-2"><i class="fas fa-times mr-2"></i>Clear</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-cogs mr-2"></i>Generate Report</button>
                        </div>
                    </form>
                </div>

                <!-- Report Display Section -->
                <?php if ($cycle_id): ?>
                <div class="report-card">
                    <div class="report-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($report_title); ?></h5>
                        <div class="text-center flex-grow-1">
                            <?php 
                                if ($selected_cycle) {
                                    $start_date = $selected_cycle['pay_period_start'];
                                    $end_date = $selected_cycle['pay_period_end'];
                                    $period_str = date('F j', strtotime($start_date));
                                    if (date('Y-m', strtotime($start_date)) === date('Y-m', strtotime($end_date))) {
                                        $period_str .= '-' . date('j, Y', strtotime($end_date));
                                    } else {
                                        $period_str .= ' - ' . date('F j, Y', strtotime($end_date));
                                    }
                                    echo '<h5 class="mb-0">For the Period: ' . $period_str . '</h5>';
                                    if (!empty($selected_cycle['pay_date'])) {
                                        echo '<h5 class="mb-0 mt-1">Date Released: ' . date('F j, Y', strtotime($selected_cycle['pay_date'])) . '</h5>';
                                    }
                                }
                            ?>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary" onclick="window.print();"><i class="fas fa-print mr-1"></i> Print</button>
                            <button class="btn btn-sm btn-outline-success" onclick="exportTableToExcel('reportTable', '<?php echo $report_type; ?>_report')"><i class="fas fa-file-excel mr-1"></i> Save as Excel</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <?php if (!empty($report_data)): ?>
                        <table class="table table-striped table-hover mb-0" id="reportTable">
                            <thead class="thead-dark">
                                <tr>
                                    <?php foreach ($report_headers as $header): ?>
                                        <?php if ($header === 'Payslip'): ?>
                                            <th class="no-print"><?php echo $header; ?></th>
                                        <?php else: ?>
                                            <th><?php echo $header; ?></th>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Dynamically render rows based on report type
                                switch ($report_type) {
                                    case 'general_payroll':
                                        $total_basic = 0;
                                        $total_allowance = 0;
                                        $total_deductions = 0;
                                        $total_net = 0;

                                        foreach ($report_data as $row) {
                                            // Calculate split based on structure ratio applied to actual gross earned
                                            $struct_total = $row['struct_basic'] + $row['struct_allowance'];
                                            if ($struct_total > 0) {
                                                $basic_ratio = $row['struct_basic'] / $struct_total;
                                                $allowance_ratio = $row['struct_allowance'] / $struct_total;
                                            } else {
                                                $basic_ratio = 1;
                                                $allowance_ratio = 0;
                                            }

                                            $basic_earned = $row['gross_earned'] * $basic_ratio;
                                            $allowance_earned = $row['gross_earned'] * $allowance_ratio;

                                            $total_basic += $basic_earned;
                                            $total_allowance += $allowance_earned;
                                            $total_deductions += $row['total_deductions'];
                                            $total_net += $row['net_amount'];

                                            echo "<tr>
                                                <td>" . htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? 'N/A')) . "</td>
                                                <td>" . htmlspecialchars($row['employee_number']) . "</td>
                                                <td>" . htmlspecialchars($row['position'] ?? 'N/A') . "</td>
                                                <td>" . number_format($basic_earned, 2) . "</td>
                                                <td>" . number_format($allowance_earned, 2) . "</td>
                                                <td>" . number_format($row['total_deductions'], 2) . "</td>
                                                <td style='font-weight:bold'>₱" . number_format($row['net_amount'], 2) . "</td>
                                                <td class='no-print'>";
                                            if (!empty($row['payslip_id'])) {
                                                echo "<a href='generate_payslip_pdf.php?payslip_id=" . $row['payslip_id'] . "' target='_blank' class='btn btn-sm btn-outline-primary'><i class='fas fa-file-pdf'></i> View</a>";
                                            } else {
                                                echo "<span class='text-muted small'>Pending</span>";
                                            }
                                            echo "</td>
                                            </tr>";
                                        }
                                        // Totals Row
                                        echo "<tr style='background-color: #f8f9fa; font-weight: bold; border-top: 2px solid #dee2e6;'>
                                            <td colspan='3' class='text-right'>TOTALS:</td>
                                            <td>" . number_format($total_basic, 2) . "</td>
                                            <td>" . number_format($total_allowance, 2) . "</td>
                                            <td>" . number_format($total_deductions, 2) . "</td>
                                            <td>₱" . number_format($total_net, 2) . "</td>
                                            <td class='no-print'></td>
                                        </tr>";
                                        break;

                                    case 'remittance':
                                        foreach ($report_data as $row) {
                                            echo "<tr>
                                                <td><strong>" . htmlspecialchars($row['remittance_type']) . "</strong></td>
                                                <td>₱" . number_format($row['total_amount'], 2) . "</td>
                                            </tr>";
                                        }
                                        break;
                                }
                                ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                            <div class="text-center p-5">
                                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Data Found</h4>
                                <p>There is no data available for the selected report and filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($report_data)): ?>
                    <div class="card-footer text-muted text-right">
                        <div class="row mt-4">
                            <div class="col-md-4 text-center">
                                <p>Prepared By:</p>
                                <br><br>
                                <p class="border-top d-inline-block pt-2" style="min-width:200px">Payroll Officer</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <p>Certified Correct:</p>
                                <br><br>
                                <p class="border-top d-inline-block pt-2" style="min-width:200px">Municipal Accountant</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <p>Approved By:</p>
                                <br><br>
                                <p class="border-top d-inline-block pt-2" style="min-width:200px">Municipal Mayor</p>
                            </div>
                        </div>
                        <div class="mt-3">Generated on: <?php echo date('Y-m-d h:i A'); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="text-center p-5 bg-light rounded">
                    <i class="fas fa-hand-pointer fa-3x text-primary mb-3"></i>
                    <h4>Select Your Report</h4>
                    <p class="text-muted">Choose a report type and date range above, then click "Generate Report" to view data.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportTableToExcel(tableID, filename = ''){
            var downloadLink;
            var dataType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById(tableID);
            var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            // Specify file name
            filename = filename?filename+'.xls':'excel_data.xls';
            
            // Create download link element
            downloadLink = document.createElement("a");
            
            document.body.appendChild(downloadLink);
            
            if(navigator.msSaveOrOpenBlob){
                var blob = new Blob(['\ufeff', tableHTML], {
                    type: dataType
                });
                navigator.msSaveOrOpenBlob( blob, filename);
            }else{
                // Create a link to the file
                downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
            
                // Setting the file name
                downloadLink.download = filename;
                
                //triggering the function
                downloadLink.click();
            }
        }
    </script>
</body>
</html>