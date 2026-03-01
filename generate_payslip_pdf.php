<?php
session_start();

// SHOW ERRORS (REMOVE WHEN DONE)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die('Unauthorized access.');
}

// Database + FPDF
$configPath = __DIR__ . '/config.php';
$fpdfPath = __DIR__ . '/libs/fpdf.php';
if (!file_exists($configPath)) die("Error: config.php not found.");
if (!file_exists($fpdfPath)) die("Error: libs/fpdf.php not found.");

// Define font path explicitly and check existence
$fontPath = __DIR__ . '/libs/font/';
if (!file_exists($fontPath . 'helveticab.php')) {
    // Attempt to auto-download fonts if missing
    if (!file_exists($fontPath)) {
        @mkdir($fontPath, 0755, true);
    }
    
    $baseUrl = 'https://raw.githubusercontent.com/Setasign/FPDF/master/font/';
    $fonts = ['helvetica.php', 'helveticab.php', 'helveticai.php', 'helveticabi.php'];
    
    $ctx = stream_context_create([
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ]
    ]);
    
    foreach ($fonts as $f) {
        $c = @file_get_contents($baseUrl . $f, false, $ctx);
        if ($c) @file_put_contents($fontPath . $f, $c);
    }

    if (!file_exists($fontPath . 'helveticab.php')) {
        die("Error: FPDF font files missing.<br>Please run <a href='install_fonts.php'>install_fonts.php</a> to download them automatically, or copy the 'font' folder manually to: " . $fontPath);
    }
}
define('FPDF_FONTPATH', $fontPath);

require_once $configPath;
require_once $fpdfPath;

// Validate ID
if (!isset($_GET['payslip_id']) || !is_numeric($_GET['payslip_id'])) {
    die('Invalid payslip ID.');
}

$payslip_id = intval($_GET['payslip_id']);

try {
// Fetch data
$sql = "
SELECT p.*, 
       pt.gross_pay, pt.net_pay, pt.tax_deductions, pt.other_deductions, pt.statutory_deductions as total_statutory,
       COALESCE(SUM(CASE WHEN LOWER(sd.deduction_type) = 'gsis' THEN sd.deduction_amount / 2 END), 0) AS gsis_contribution,
       COALESCE(SUM(CASE WHEN LOWER(sd.deduction_type) = 'philhealth' THEN sd.deduction_amount / 2 END), 0) AS philhealth_contribution,
       COALESCE(SUM(CASE WHEN LOWER(sd.deduction_type) = 'pag-ibig' THEN sd.deduction_amount / 2 END), 0) AS pagibig_contribution,
       ep.employee_number, 
       pi.first_name, pi.last_name,
       jr.title AS job_title, d.department_name,
       pc.cycle_name, pc.pay_period_start, pc.pay_period_end
FROM payslips p
JOIN payroll_transactions pt ON p.payroll_transaction_id = pt.payroll_transaction_id
JOIN employee_profiles ep ON p.employee_id = ep.employee_id
LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
LEFT JOIN departments d ON jr.department = d.department_name
LEFT JOIN payroll_cycles pc ON pt.payroll_cycle_id = pc.payroll_cycle_id
LEFT JOIN statutory_deductions sd ON sd.employee_id = ep.employee_id
WHERE p.payslip_id = ?
GROUP BY p.payslip_id
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->execute([$payslip_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

// Check data
if (!$data) {
    die('Payslip not found or associated data is incomplete. ID: ' . $payslip_id);
}

// Start buffering now to catch any FPDF output issues
ob_start();

// Helper function
function money($val) {
    return number_format((float)$val, 2, '.', ',');
}

// Compute total deductions
$total_deductions = 
    (float)($data['tax_deductions'] ?? 0) + 
    (float)($data['total_statutory'] ?? 0) + 
    (float)($data['other_deductions'] ?? 0);

// Create PDF
$pdf = new FPDF('P','mm','A4');
$pdf->AddPage();

// --- Header ---
// Logo
if(file_exists('image/GARAY.jpg')) {
    $pdf->Image('image/GARAY.jpg', 25, 10, 20); 
}

$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,8,'MUNICIPALITY OF NORZAGARAY, BULACAN',0,1,'C');
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,5,'Payroll Division - Official Payslip',0,1,'C');
$pdf->Ln(8);
$pdf->Line(10, 32, 200, 32); // Horizontal line
$pdf->Ln(5);

// --- Info Columns ---
$y_start = $pdf->GetY();
$x_start = 10;
$col_width = 95;

// Left Column: Employee Info
$pdf->SetFont('Arial','B',10);
$pdf->Cell($col_width, 6, 'Employee Information', 0, 1);
$pdf->SetFont('Arial','',9);
$pdf->Cell(30, 5, 'Name:', 0, 0); $pdf->Cell(60, 5, ($data['first_name'] ?? 'N/A').' '.($data['last_name'] ?? ''), 0, 1);
$pdf->Cell(30, 5, 'Employee #:', 0, 0); $pdf->Cell(60, 5, $data['employee_number'], 0, 1);
$pdf->Cell(30, 5, 'Department:', 0, 0); $pdf->Cell(60, 5, ($data['department_name'] ?? 'N/A'), 0, 1);
$pdf->Cell(30, 5, 'Job Title:', 0, 0); $pdf->Cell(60, 5, ($data['job_title'] ?? 'N/A'), 0, 1);
$pdf->Cell(30, 5, 'Salary Grade:', 0, 0); $pdf->Cell(60, 5, 'N/A', 0, 1);

// Right Column: Payroll Info
$pdf->SetXY($x_start + $col_width, $y_start);
$pdf->SetFont('Arial','B',10);
$pdf->Cell($col_width, 6, 'Payroll Information', 0, 1);
$pdf->SetFont('Arial','',9);

$pdf->SetX($x_start + $col_width);
$pdf->Cell(30, 5, 'Cycle:', 0, 0); $pdf->Cell(60, 5, ($data['cycle_name'] ?? 'N/A'), 0, 1);

$pp_start = isset($data['pay_period_start']) ? date('M d', strtotime($data['pay_period_start'])) : 'N/A';
$pp_end = isset($data['pay_period_end']) ? date('M d, Y', strtotime($data['pay_period_end'])) : 'N/A';
$pdf->SetX($x_start + $col_width);
$pdf->Cell(30, 5, 'Pay Period:', 0, 0); $pdf->Cell(60, 5, $pp_start . ' - ' . $pp_end, 0, 1);

$pdf->SetX($x_start + $col_width);
$pdf->Cell(30, 5, 'Generated:', 0, 0); $pdf->Cell(60, 5, date('M d, Y H:i', strtotime($data['generated_date'] ?? 'now')), 0, 1);

$pdf->SetX($x_start + $col_width);
$pdf->Cell(30, 5, 'Status:', 0, 0); $pdf->Cell(60, 5, ($data['status'] ?? 'N/A'), 0, 1);

$pdf->SetX($x_start + $col_width);
$pdf->Cell(30, 5, 'Reference No.:', 0, 0); $pdf->Cell(60, 5, str_pad($data['payslip_id'], 8, '0', STR_PAD_LEFT), 0, 1);

$pdf->Ln(10);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

// --- Earnings & Deductions Tables ---
$y_tables = $pdf->GetY();

// Left: Earnings
$pdf->SetXY(10, $y_tables);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(90, 8, 'Earnings', 0, 1);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(60, 7, 'Description', 1, 0, 'L', true);
$pdf->Cell(30, 7, 'Amount (P)', 1, 1, 'R', true);
$pdf->SetFont('Arial','',9);
// Rows
$pdf->Cell(60, 7, 'Basic Pay', 1, 0); $pdf->Cell(30, 7, money($data['gross_pay'] ?? 0), 1, 1, 'R');
$pdf->Cell(60, 7, 'Allowances', 1, 0); $pdf->Cell(30, 7, '0.00', 1, 1, 'R');
$pdf->Cell(60, 7, 'Overtime / Bonuses', 1, 0); $pdf->Cell(30, 7, '0.00', 1, 1, 'R');
// Total
$pdf->SetFont('Arial','B',9);
$pdf->Cell(60, 7, 'Total Gross', 1, 0, 'L', true);
$pdf->Cell(30, 7, money($data['gross_pay'] ?? 0), 1, 1, 'R', true);

// Right: Deductions
$pdf->SetXY(110, $y_tables);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(90, 8, 'Deductions', 0, 1);
$pdf->SetX(110);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(60, 7, 'Description', 1, 0, 'L', true);
$pdf->Cell(30, 7, 'Amount (P)', 1, 1, 'R', true);
$pdf->SetFont('Arial','',9);
// Rows
$pdf->SetX(110); $pdf->Cell(60, 7, 'Tax', 1, 0); $pdf->Cell(30, 7, money($data['tax_deductions'] ?? 0), 1, 1, 'R');
$pdf->SetX(110); $pdf->Cell(60, 7, 'GSIS', 1, 0); $pdf->Cell(30, 7, money($data['gsis_contribution'] ?? 0), 1, 1, 'R');
$pdf->SetX(110); $pdf->Cell(60, 7, 'PhilHealth', 1, 0); $pdf->Cell(30, 7, money($data['philhealth_contribution'] ?? 0), 1, 1, 'R');
$pdf->SetX(110); $pdf->Cell(60, 7, 'Pag-IBIG', 1, 0); $pdf->Cell(30, 7, money($data['pagibig_contribution'] ?? 0), 1, 1, 'R');
$pdf->SetX(110); $pdf->Cell(60, 7, 'Other Deductions', 1, 0); $pdf->Cell(30, 7, money($data['other_deductions'] ?? 0), 1, 1, 'R');
// Total
$pdf->SetX(110);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(60, 7, 'Total Deductions', 1, 0, 'L', true);
$pdf->Cell(30, 7, money($total_deductions), 1, 1, 'R', true);

$pdf->Ln(10);

// --- Net Pay ---
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0, 8, 'Net Pay', 0, 1, 'C');
$pdf->SetFont('Arial','B',18);
$pdf->SetTextColor(0, 128, 0); // Green
$pdf->Cell(0, 10, 'P '.money($data['net_pay'] ?? 0), 0, 1, 'C');
$pdf->SetTextColor(0); // Reset to black
$pdf->SetFont('Arial','',10);
$pdf->Cell(0, 5, '(After all deductions)', 0, 1, 'C');

$pdf->Ln(10);

// --- Remarks ---
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0, 6, 'Remarks / Notes:', 0, 1);
$pdf->SetFont('Arial','',10);
$pdf->MultiCell(0, 8, 'N/A', 1, 'L');

$pdf->Ln(15);

// --- Signatures ---
$pdf->SetFont('Arial','B',10);
$pdf->Cell(90, 5, 'Prepared by', 0, 0, 'C');
$pdf->Cell(10, 5, '', 0, 0);
$pdf->Cell(90, 5, 'Approved by', 0, 1, 'C');

$pdf->Ln(15);
$pdf->Line(30, $pdf->GetY(), 80, $pdf->GetY());
$pdf->Line(130, $pdf->GetY(), 180, $pdf->GetY());

// CLEAN OUTPUT BUFFER (IMPORTANT)
ob_end_clean();

// Download PDF
$pdf->Output('I', 'Payslip_'.($data['employee_number'] ?? '000').'.pdf');
exit;

} catch (Exception $e) {
    if (ob_get_level()) ob_end_clean();
    die('Error generating PDF: ' . $e->getMessage());
}
?>