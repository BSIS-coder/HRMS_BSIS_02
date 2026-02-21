<?php
ob_start();
session_start();

//  SHOW ERRORS (REMOVE LATER PAG OK NA)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die('Unauthorized access.');
}

//  FIXED PATH
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/libs/fpdf.php';
// Validate ID
if (!isset($_GET['payslip_id']) || !is_numeric($_GET['payslip_id'])) {
    die('Invalid payslip ID.');
}

$payslip_id = intval($_GET['payslip_id']);

// Fetch data
$sql = "
SELECT p.*, pt.gross_pay, pt.net_pay, pt.tax_deductions, pt.statutory_deductions, pt.other_deductions,
       ep.employee_number, pi.first_name, pi.last_name,
       jr.title AS job_title,
       pc.cycle_name, pc.pay_period_start, pc.pay_period_end
FROM payslips p
JOIN payroll_transactions pt ON p.payroll_transaction_id = pt.payroll_transaction_id
JOIN employee_profiles ep ON p.employee_id = ep.employee_id
JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
LEFT JOIN payroll_cycles pc ON pt.payroll_cycle_id = pc.payroll_cycle_id
WHERE p.payslip_id = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->execute([$payslip_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('Payslip not found.');
}

// Helper
function money($val) {
    return number_format((float)$val, 2, '.', ',');
}

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();

// Title
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Municipality of Norzagaray, Bulacan',0,1,'C');

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Payroll Division - Payslip',0,1,'C');
$pdf->Ln(5);

// Employee Info
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,6,'Employee Information',0,1);

$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,'Name: '.$data['first_name'].' '.$data['last_name'],0,1);
$pdf->Cell(0,6,'Employee #: '.$data['employee_number'],0,1);
$pdf->Cell(0,6,'Job Title: '.($data['job_title'] ?? 'N/A'),0,1);
$pdf->Ln(3);

// Payroll Info
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,6,'Payroll Information',0,1);

$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,'Cycle: '.$data['cycle_name'],0,1);

$pp = date('M d, Y', strtotime($data['pay_period_start'])) . ' - ' .
      date('M d, Y', strtotime($data['pay_period_end']));
$pdf->Cell(0,6,'Pay Period: '.$pp,0,1);

$pdf->Cell(0,6,'Status: '.$data['status'],0,1);
$pdf->Ln(5);

// Earnings & Deductions
$pdf->SetFont('Arial','B',11);
$pdf->Cell(95,6,'Earnings',1,0,'C');
$pdf->Cell(95,6,'Deductions',1,1,'C');

$pdf->SetFont('Arial','',10);
$pdf->Cell(95,6,'Gross Pay',1,0,'L');
$pdf->Cell(95,6,'Tax: '.money($data['tax_deductions']),1,1,'R');

$pdf->Cell(95,6,'',1,0);
$pdf->Cell(95,6,'Statutory: '.money($data['statutory_deductions']),1,1,'R');

$pdf->Cell(95,6,'',1,0);
$pdf->Cell(95,6,'Other: '.money($data['other_deductions']),1,1,'R');

$total_deductions = $data['tax_deductions'] + $data['statutory_deductions'] + $data['other_deductions'];

$pdf->SetFont('Arial','B',10);
$pdf->Cell(95,6,'Total Gross',1,0);
$pdf->Cell(95,6,'Total Deductions',1,1);

$pdf->SetFont('Arial','',10);
$pdf->Cell(95,6,'₱ '.money($data['gross_pay']),1,0);
$pdf->Cell(95,6,'₱ '.money($total_deductions),1,1);

$pdf->Ln(5);

// Net Pay
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,'NET PAY: ₱ '.money($data['net_pay']),1,1,'C');

//  VERY IMPORTANT (para hindi blank)
ob_end_clean();

// Download PDF
$pdf->Output('D', 'Payslip_'.$data['employee_number'].'.pdf');
exit;
?>