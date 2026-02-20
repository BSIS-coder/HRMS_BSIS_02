<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die('Access denied');
}

require_once 'db_connect.php';

if (!isset($_GET['letter_id'])) {
    die('Invalid request');
}

$letter_id = $_GET['letter_id'];

// Get letter details
$stmt = $conn->prepare("SELECT ol.*, jo.offered_salary, jo.offered_benefits, jo.start_date,
                       c.first_name, c.last_name, job.title as job_title, d.department_name
                       FROM offer_letters ol
                       JOIN job_offers jo ON ol.offer_id = jo.offer_id
                       JOIN job_applications ja ON ol.application_id = ja.application_id
                       JOIN candidates c ON ja.candidate_id = c.candidate_id
                       JOIN job_openings job ON ja.job_opening_id = job.job_opening_id
                       JOIN departments d ON job.department_id = d.department_id
                       WHERE ol.letter_id = ?");
$stmt->bind_param('i', $letter_id);
$stmt->execute();
$letter = $stmt->get_result()->fetch_assoc();

if (!$letter) {
    die('Letter not found');
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Offer_Letter_' . $letter['first_name'] . '_' . $letter['last_name'] . '.pdf"');

// For now, we'll use a simple HTML to PDF conversion
// You can integrate libraries like TCPDF, FPDF, or mPDF for better PDF generation

// Simple approach: Generate HTML and let browser handle PDF conversion
// For production, consider using a proper PDF library

// Alternative: Generate HTML that can be printed as PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Offer Letter - <?php echo htmlspecialchars($letter['first_name'] . ' ' . $letter['last_name']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18pt;
            color: #333;
        }
        .header p {
            margin: 5px 0;
            font-size: 10pt;
        }
        .content {
            white-space: pre-wrap;
            text-align: justify;
        }
        .details-box {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .details-box h3 {
            margin-top: 0;
            color: #333;
            font-size: 14pt;
        }
        .details-box p {
            margin: 5px 0;
        }
        .signature-section {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 300px;
            margin-top: 50px;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MUNICIPAL GOVERNMENT</h1>
        <p>Human Resources Department</p>
        <p>Official Job Offer Letter</p>
    </div>

    <div class="details-box">
        <h3>Offer Details</h3>
        <p><strong>Candidate:</strong> <?php echo htmlspecialchars($letter['first_name'] . ' ' . $letter['last_name']); ?></p>
        <p><strong>Position:</strong> <?php echo htmlspecialchars($letter['job_title']); ?></p>
        <p><strong>Department:</strong> <?php echo htmlspecialchars($letter['department_name']); ?></p>
        <p><strong>Salary:</strong> ₱<?php echo number_format($letter['offered_salary'], 2); ?> per month</p>
        <p><strong>Start Date:</strong> <?php echo date('F j, Y', strtotime($letter['start_date'])); ?></p>
        <p><strong>Letter Date:</strong> <?php echo date('F j, Y', strtotime($letter['created_at'])); ?></p>
    </div>

    <div class="content">
<?php echo htmlspecialchars($letter['letter_content']); ?>
    </div>

    <?php if ($letter['offered_benefits']): ?>
    <div class="details-box">
        <h3>Benefits Package</h3>
        <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($letter['offered_benefits']); ?></div>
    </div>
    <?php endif; ?>

    <div class="signature-section">
        <p><strong>ACCEPTANCE OF OFFER</strong></p>
        <p>I, <?php echo htmlspecialchars($letter['first_name'] . ' ' . $letter['last_name']); ?>, hereby accept the position of <?php echo htmlspecialchars($letter['job_title']); ?> with the Municipal Government under the terms and conditions stated above.</p>
        
        <div style="margin-top: 40px;">
            <div class="signature-line"></div>
            <p>Signature of Candidate</p>
        </div>
        
        <div style="margin-top: 20px;">
            <div class="signature-line"></div>
            <p>Date</p>
        </div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 5px;">
        <p><strong>To save as PDF:</strong> Press Ctrl+P (Windows) or Cmd+P (Mac), then select "Save as PDF"</p>
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14pt; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            <i class="fas fa-print"></i> Print / Save as PDF
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14pt; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>

    <script>
        // Auto-trigger print dialog
        window.onload = function() {
            // Uncomment to auto-open print dialog
            // window.print();
        };
    </script>
</body>
</html>
