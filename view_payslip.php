<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config.php';

// Get payslip ID from URL
$payslip_id = $_GET['id'] ?? null;

if ($payslip_id) {
    try {
        // Fetch payslip details
        $sql = "SELECT p.*, pt.gross_pay, pt.net_pay, pt.tax_deductions, pt.statutory_deductions, 
                       ep.employee_number, pi.first_name, pi.last_name
                FROM payslips p
                JOIN payroll_transactions pt ON p.payroll_transaction_id = pt.payroll_transaction_id
                JOIN employee_profiles ep ON p.employee_id = ep.employee_id
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                WHERE p.payslip_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$payslip_id]);
        $payslip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payslip) {
            throw new Exception("Payslip not found.");
        }
    } catch (PDOException $e) {
        $error_message = "Error fetching payslip details: " . $e->getMessage();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
} else {
    $error_message = "Invalid payslip ID.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Details</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Payslip Details</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5><?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?> - Payslip</h5>
                </div>
                <div class="card-body">
                    <p><strong>Employee Number:</strong> <?php echo htmlspecialchars($payslip['employee_number']); ?></p>
                    <p><strong>Gross Pay:</strong> ₱<?php echo number_format($payslip['gross_pay'], 2); ?></p>
                    <p><strong>Tax Deductions:</strong> ₱<?php echo number_format($payslip['tax_deductions'], 2); ?></p>
                    <p><strong>Statutory Deductions:</strong> ₱<?php echo number_format($payslip['statutory_deductions'], 2); ?></p>
                    <p><strong>Net Pay:</strong> ₱<?php echo number_format($payslip['net_pay'], 2); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($payslip['status']); ?></p>
                    <p><strong>Generated Date:</strong> <?php echo date('M d, Y', strtotime($payslip['generated_date'])); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <a href="payslips.php" class="btn btn-secondary mt-3">Back to Payslips</a>
    </div>
</body>
</html>
