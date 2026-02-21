<?php
require_once 'dp.php';

$host = getenv('DB_HOST') ?? 'localhost';
$dbname = getenv('DB_NAME') ?? 'hr_system';
$username = getenv('DB_USER') ?? 'root';
$password = getenv('DB_PASS') ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (!isset($_GET['employee_id']) || !isset($_GET['exit_id'])) {
    die("Invalid request.");
}

$employee_id = $_GET['employee_id'];
$exit_id = $_GET['exit_id'];

$stmt = $pdo->prepare("
    SELECT 
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        e.exit_type,
        e.exit_date
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    WHERE e.exit_id = ?
");

$stmt->execute([$exit_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Exit record not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Exit Certificate</title>
    <style>
        @page {
            size: A4;
            margin: 40px;
        }

        body {
            font-family: "Times New Roman", serif;
            background: #f4f4f4;
        }

        .certificate-container {
            width: 800px;
            margin: auto;
            padding: 60px;
            background: white;
            border: 10px solid #C2185B;
            position: relative;
        }

        .watermark {
            position: absolute;
            top: 40%;
            left: 20%;
            font-size: 80px;
            color: rgba(0,0,0,0.05);
            transform: rotate(-30deg);
            z-index: 0;
        }

        .content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .company-name {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .certificate-title {
            font-size: 32px;
            margin: 30px 0;
            font-weight: bold;
            text-decoration: underline;
        }

        .text {
            font-size: 18px;
            margin: 20px 0;
            line-height: 1.8;
        }

        .employee-name {
            font-size: 26px;
            font-weight: bold;
            margin: 20px 0;
            color: #C2185B;
        }

        .details {
            margin-top: 30px;
            font-size: 18px;
        }

        .signature-section {
            margin-top: 80px;
            display: flex;
            justify-content: space-between;
        }

        .signature {
            text-align: center;
            width: 250px;
        }

        .signature-line {
            border-top: 2px solid #000;
            margin-top: 50px;
            padding-top: 10px;
        }

        .footer {
            margin-top: 50px;
            font-size: 14px;
            color: gray;
        }

        @media print {
            body {
                background: none;
            }
        }
    </style>
</head>

<body onload="window.print()">

<div class="certificate-container">

    <div class="watermark">HR SYSTEM</div>

    <div class="content">

        <div class="company-name">
            YOUR COMPANY NAME
        </div>

        <div class="certificate-title">
            EXIT CERTIFICATE
        </div>

        <div class="text">
            This is to formally certify that
        </div>

        <div class="employee-name">
            <?= htmlspecialchars($data['employee_name']) ?>
        </div>

        <div class="text">
            Employee Number: <strong><?= htmlspecialchars($data['employee_number']) ?></strong>
        </div>

        <div class="details">
            has officially completed the exit process under the category of 
            <strong><?= htmlspecialchars($data['exit_type']) ?></strong> 
            effective on 
            <strong><?= date('F d, Y', strtotime($data['exit_date'])) ?></strong>.
        </div>

        <div class="text">
            All required clearances and exit formalities have been duly processed.
        </div>

        <div class="signature-section">
            <div class="signature">
                <div class="signature-line">
                    HR Manager
                </div>
            </div>

            <div class="signature">
                <div class="signature-line">
                    Authorized Signatory
                </div>
            </div>
        </div>

        <div class="footer">
            Issued on <?= date('F d, Y') ?> | HR Exit Management System
        </div>

    </div>

</div>

</body>
</html>



