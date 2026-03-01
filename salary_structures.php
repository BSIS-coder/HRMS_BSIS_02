<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Check if user is admin or hr
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: unauthorized.php");
    exit;
}

// Include database connection
require_once 'config.php';

// Use existing connection
$pdo = $conn;

// Get date range for payroll period
$pay_cycle = isset($_GET['pay_cycle']) ? $_GET['pay_cycle'] : 'full';

// Get selected month/year or use current
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Calculate dates based on pay cycle
$monthStart = new DateTime($selected_month . '-01');
$monthEnd = new DateTime($selected_month . '-01');
$monthEnd->modify('last day of this month');

switch ($pay_cycle) {
    case 'first_half':
        $start_date = $selected_month . '-01';
        $end_date = $selected_month . '-15';
        break;
    case 'second_half':
        $start_date = $selected_month . '-16';
        $end_date = $monthEnd->format('Y-m-d');
        break;
    default: // full month
        $start_date = $selected_month . '-01';
        $end_date = $monthEnd->format('Y-m-d');
}

// Calculate working days in the period (excluding weekends)
function calculateWorkingDays($start, $end) {
    $startDate = new DateTime($start);
    $endDate = new DateTime($end);
    $workingDays = 0;
    
    while ($startDate <= $endDate) {
        $dayOfWeek = $startDate->format('N');
        if ($dayOfWeek < 6) { // Monday = 1, Friday = 5
            $workingDays++;
        }
        $startDate->modify('+1 day');
    }
    return $workingDays;
}

// Calculate total working days in the period
$total_working_days = calculateWorkingDays($start_date, $end_date);

// Calculate deduction ratio based on pay cycle (for pro-rating deductions)
$deduction_ratio = ($pay_cycle == 'full') ? 1 : 0.5; // Half for first/second half, full for full month

// Function to calculate statutory deduction
function calculateStatutoryDeduction($monthly_salary, $deduction_type) {
    $amount = 0;
    switch ($deduction_type) {
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
        case 'SSS':
            if ($monthly_salary <= 3250) {
                $amount = 135.00;
            } elseif ($monthly_salary <= 3750) {
                $amount = 157.50;
            } elseif ($monthly_salary <= 4250) {
                $amount = 180.00;
            } elseif ($monthly_salary <= 4750) {
                $amount = 202.50;
            } elseif ($monthly_salary <= 5250) {
                $amount = 225.00;
            } elseif ($monthly_salary <= 5750) {
                $amount = 247.50;
            } elseif ($monthly_salary <= 6250) {
                $amount = 270.00;
            } elseif ($monthly_salary <= 6750) {
                $amount = 292.50;
            } elseif ($monthly_salary <= 7250) {
                $amount = 315.00;
            } elseif ($monthly_salary <= 7750) {
                $amount = 337.50;
            } elseif ($monthly_salary <= 8250) {
                $amount = 360.00;
            } elseif ($monthly_salary <= 8750) {
                $amount = 382.50;
            } elseif ($monthly_salary <= 9250) {
                $amount = 405.00;
            } elseif ($monthly_salary <= 9750) {
                $amount = 427.50;
            } elseif ($monthly_salary <= 10250) {
                $amount = 450.00;
            } elseif ($monthly_salary <= 10750) {
                $amount = 472.50;
            } elseif ($monthly_salary <= 11250) {
                $amount = 495.00;
            } elseif ($monthly_salary <= 11750) {
                $amount = 517.50;
            } elseif ($monthly_salary <= 12250) {
                $amount = 540.00;
            } elseif ($monthly_salary <= 12750) {
                $amount = 562.50;
            } elseif ($monthly_salary <= 13250) {
                $amount = 585.00;
            } elseif ($monthly_salary <= 13750) {
                $amount = 607.50;
            } elseif ($monthly_salary <= 14250) {
                $amount = 630.00;
            } elseif ($monthly_salary <= 14750) {
                $amount = 652.50;
            } elseif ($monthly_salary <= 15250) {
                $amount = 675.00;
            } elseif ($monthly_salary <= 15750) {
                $amount = 697.50;
            } elseif ($monthly_salary <= 16250) {
                $amount = 720.00;
            } elseif ($monthly_salary <= 16750) {
                $amount = 742.50;
            } elseif ($monthly_salary <= 17250) {
                $amount = 765.00;
            } elseif ($monthly_salary <= 17750) {
                $amount = 787.50;
            } elseif ($monthly_salary <= 18250) {
                $amount = 810.00;
            } elseif ($monthly_salary <= 18750) {
                $amount = 832.50;
            } elseif ($monthly_salary <= 19250) {
                $amount = 855.00;
            } elseif ($monthly_salary <= 19750) {
                $amount = 877.50;
            } else {
                $amount = 900.00;
            }
            break;
    }
    return round($amount, 2);
}

// Fetch all active employees with their details
try {
    $stmt = $pdo->query("
        SELECT 
            ep.employee_id,
            ep.employee_number,
            ep.current_salary,
            ep.salary_grade_id,
            pi.first_name,
            pi.last_name,
            jr.title as job_title,
            d.department_name,
            sg.grade_name as salary_grade,
            sg.monthly_salary as grade_salary
        FROM employee_profiles ep
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_id
        LEFT JOIN salary_grades sg ON ep.salary_grade_id = sg.grade_id
        WHERE ep.employment_status NOT IN ('Terminated', 'Resigned')
        ORDER BY pi.last_name ASC, pi.first_name ASC
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}

// Fetch salary structures for allowances
try {
    $stmt = $pdo->query("
        SELECT employee_id, basic_salary, allowances, deductions 
        FROM salary_structures
    ");
    $salaryStructures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array keyed by employee_id
    $salaryStructuresByEmployee = [];
    foreach ($salaryStructures as $structure) {
        $salaryStructuresByEmployee[$structure['employee_id']] = $structure;
    }
} catch (PDOException $e) {
    $salaryStructuresByEmployee = [];
}

// Fetch attendance data for the period
try {
    $stmt = $pdo->prepare("
        SELECT 
            employee_id,
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'On Leave' THEN 1 ELSE 0 END) as leave_days,
            SUM(COALESCE(overtime_hours, 0)) as total_overtime,
            SUM(COALESCE(working_hours, 0)) as total_hours
        FROM attendance 
        WHERE attendance_date BETWEEN ? AND ?
        GROUP BY employee_id
    ");
    $stmt->execute([$start_date, $end_date]);
    $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array keyed by employee_id
    $attendanceByEmployee = [];
    foreach ($attendanceData as $att) {
        $attendanceByEmployee[$att['employee_id']] = $att;
    }
} catch (PDOException $e) {
    $attendanceByEmployee = [];
}

// Fetch statutory deductions
try {
    $stmt = $pdo->query("
        SELECT employee_id, deduction_type, deduction_amount 
        FROM statutory_deductions
    ");
    $statutoryDeductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by employee_id
    $statutoryByEmployee = [];
    foreach ($statutoryDeductions as $ded) {
        if (!isset($statutoryByEmployee[$ded['employee_id']])) {
            $statutoryByEmployee[$ded['employee_id']] = [];
        }
        $statutoryByEmployee[$ded['employee_id']][] = $ded;
    }
} catch (PDOException $e) {
    $statutoryByEmployee = [];
}

// Fetch tax deductions
try {
    $stmt = $pdo->query("
        SELECT employee_id, tax_type, tax_amount 
        FROM tax_deductions
    ");
    $taxDeductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by employee_id
    $taxByEmployee = [];
    foreach ($taxDeductions as $tax) {
        if (!isset($taxByEmployee[$tax['employee_id']])) {
            $taxByEmployee[$tax['employee_id']] = [];
        }
        $taxByEmployee[$tax['employee_id']][] = $tax;
    }
} catch (PDOException $e) {
    $taxByEmployee = [];
}

// Calculate payroll for each employee
$payrollData = [];
foreach ($employees as $emp) {
    $employeeId = $emp['employee_id'];
    
    // Get attendance data
    $att = isset($attendanceByEmployee[$employeeId]) ? $attendanceByEmployee[$employeeId] : null;
    $daysWorked = $att ? ($att['present_days'] + $att['late_days']) : 0;
    $overtimeHours = $att ? floatval($att['total_overtime']) : 0;
    $absentDays = $att ? intval($att['absent_days']) : 0;
    
    // Get monthly salary (use current_salary or grade_salary)
    $monthlySalary = floatval($emp['current_salary'] ?? $emp['grade_salary'] ?? 0);
    
    // Calculate salary basis based on pay cycle (half for half-month, full for full month)
    $salaryBasis = $monthlySalary * $deduction_ratio;
    
    // Calculate rate per day (salary basis / total working days in period)
    $ratePerDay = $total_working_days > 0 ? $salaryBasis / $total_working_days : 0;
    
    // Get monthly salary (use current_salary or grade_salary) as basic pay
    $basicPay = $monthlySalary;
    
    // Get allowance from salary_structures or use default (can be configured)
    $allowance = 0;
    if (isset($salaryStructuresByEmployee[$employeeId])) {
        $allowance = floatval($salaryStructuresByEmployee[$employeeId]['allowances'] ?? 0);
    }
    // If no salary structure, use a default allowance (0 for now, can be set per employee)
    
    // Calculate overtime pay (rate per hour * 1.25 for overtime multiplier)
    $hourlyRate = $ratePerDay / 8; // Assuming 8 hours per day
    $overtimePay = $overtimeHours * $hourlyRate * 1.25; // 25% overtime premium
    
    // Calculate gross pay
    $grossPay = $basicPay + $allowance + $overtimePay;
    
    // Calculate statutory deductions (pro-rated based on pay cycle)
    $totalStatutory = 0;
    if (isset($statutoryByEmployee[$employeeId])) {
        foreach ($statutoryByEmployee[$employeeId] as $stat) {
            // Apply deduction ratio based on pay cycle (half for half-month, full for full month)
            $monthlyDeduction = floatval($stat['deduction_amount']);
            $totalStatutory += $monthlyDeduction * $deduction_ratio;
        }
    } else {
        // Auto-calculate statutory based on salary (government employees get GSIS)
        $dept = strtolower($emp['department_name'] ?? '');
        if (strpos($dept, 'office') !== false || strpos($dept, 'municipal') !== false || strpos($dept, 'department') !== false) {
            // Government employee - calculate GSIS (pro-rated)
            $totalStatutory += calculateStatutoryDeduction($monthlySalary, 'GSIS') * $deduction_ratio;
            $totalStatutory += calculateStatutoryDeduction($monthlySalary, 'PhilHealth') * $deduction_ratio;
            $totalStatutory += calculateStatutoryDeduction($monthlySalary, 'Pag-IBIG') * $deduction_ratio;
        }
    }
    
    // Calculate tax deductions (Income Tax - pro-rated based on pay cycle)
    $totalTax = 0;
    if (isset($taxByEmployee[$employeeId])) {
        foreach ($taxByEmployee[$employeeId] as $tax) {
            if ($tax['tax_type'] === 'Income Tax') {
                $totalTax += (floatval($tax['tax_amount'] ?? 0)) * $deduction_ratio;
            }
        }
    }
    
    // Calculate total deductions
    $totalDeductions = $totalStatutory + $totalTax;
    
    // Calculate net pay
    $netPay = $grossPay - $totalDeductions;
    
    $payrollData[] = [
        'employee_id' => $employeeId,
        'employee_number' => $emp['employee_number'],
        'employee_name' => $emp['first_name'] . ' ' . $emp['last_name'],
        'job_title' => $emp['job_title'] ?? 'N/A',
        'department' => $emp['department_name'] ?? 'N/A',
        'salary_grade' => $emp['salary_grade'] ?? 'N/A',
        'monthly_salary' => $monthlySalary,
        'days_worked' => $daysWorked,
        'absent_days' => $absentDays,
        'rate_per_day' => $ratePerDay,
        'basic_pay' => $basicPay,
        'allowance' => $allowance,
        'overtime_hours' => $overtimeHours,
        'overtime_pay' => $overtimePay,
        'gross_pay' => $grossPay,
        'statutory_deductions' => $totalStatutory,
        'tax_deductions' => $totalTax,
        'total_deductions' => $totalDeductions,
        'net_pay' => $netPay
    ];
}

// Calculate totals
$totalGrossPay = array_sum(array_column($payrollData, 'gross_pay'));
$totalDeductions = array_sum(array_column($payrollData, 'total_deductions'));
$totalNetPay = array_sum(array_column($payrollData, 'net_pay'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Computation - HR System</title>
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
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        body {
            background: var(--azure-blue-pale);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            font-size: 28px;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border: none;
        }

        .card-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            border: none;
        }

        .card-body {
            padding: 20px;
            background: white;
            border-radius: 0 0 10px 10px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }

        .summary-card .icon {
            font-size: 32px;
            color: var(--azure-blue);
            margin-bottom: 10px;
        }

        .summary-card .value {
            font-size: 24px;
            font-weight: bold;
            color: var(--azure-blue-dark);
        }

        .summary-card .label {
            color: #666;
            font-size: 14px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            font-size: 13px;
        }

        .table th {
            background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%);
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
            position: sticky;
            top: 0;
        }

        .table td {
            padding: 10px 8px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--azure-blue-lighter);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .badge-success {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .badge-danger {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            background: var(--azure-blue-lighter) !important;
            font-weight: bold;
        }

        .total-row td {
            border-top: 2px solid var(--azure-blue);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .table {
                font-size: 11px;
            }
            
            .table th, .table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <!-- Page Title -->
                <h1 class="section-title">
                    <i class="fas fa-calculator"></i>
                    Payroll Computation
                </h1>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="get" class="row align-items-end">
                        <div class="col-md-2">
                            <label for="month">Month</label>
                            <input type="month" class="form-control" id="month" name="month" value="<?php echo $selected_month; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="pay_cycle">Pay Cycle</label>
                            <select class="form-control" id="pay_cycle" name="pay_cycle">
                                <option value="full" <?php echo $pay_cycle == 'full' ? 'selected' : ''; ?>>Full Month</option>
                                <option value="first_half" <?php echo $pay_cycle == 'first_half' ? 'selected' : ''; ?>>1st Half (1-15)</option>
                                <option value="second_half" <?php echo $pay_cycle == 'second_half' ? 'selected' : ''; ?>>2nd Half (16-End)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="start_date">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="end_date">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Generate
                            </button>
                        </div>
                        z
                    </form>
                </div>

               
                <!-- Payroll Table -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="payrollTable">
                            <thead>
                                <tr>
                                    <th class="align-middle">#</th>
                                    <th class="align-middle">Employee</th>
                                    <th class="align-middle">SG</th>
                                    <th class="align-middle">Position</th>
                                    <th class="align-middle text-right">Basic Pay</th>
                                    <th class="align-middle text-right">Allowance</th>
                                    <th class="text-right">Statutory</th>
                                    <th class="text-right">Tax</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($payrollData)): ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($payrollData as $row): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['employee_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($row['employee_number']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['salary_grade']); ?></td>
                                            <td><?php echo htmlspecialchars($row['job_title']); ?></td>
                                            <td class="text-right">₱<?php echo number_format($row['basic_pay'], 2); ?></td>
                                            <td class="text-right">₱100<?php echo number_format($row['allowance'], 2); ?></td>
                                            <td class="text-right">₱<?php echo number_format($row['statutory_deductions'], 2); ?></td>
                                            <td class="text-right">₱<?php echo number_format($row['tax_deductions'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                   
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No employee data found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

               
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const table = document.getElementById('payrollTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            }
        });
    </script>
</body>
</html>
