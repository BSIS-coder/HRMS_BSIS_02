<?php
// process_pds.php - Save PDS form data as JSON and generate simple HTML-to-PDF
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Extract all form data
    $data = $_POST;
    
    // Validate required fields
    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['phone'])) {
        die("Error: Required fields are missing.");
    }
    
    // Create directory if not exists
    $pdsDir = 'uploads/pds/';
    if (!is_dir($pdsDir)) {
        mkdir($pdsDir, 0755, true);
    }
    
    // Generate filename
    $timestamp = time();
    $filename = 'PDS_' . $data['last_name'] . '_' . $data['first_name'] . '_' . date('Ymd_His');
    
    // Save data as JSON for easy extraction later
    $jsonFile = $pdsDir . $filename . '.json';
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    
    // Generate HTML file that can be printed as PDF
    $htmlFile = $pdsDir . $filename . '.html';
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Personal Data Sheet - ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
        h2 { text-align: center; margin-bottom: 5px; }
        h3 { background-color: #d3d3d3; padding: 8px; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid black; }
        th, td { padding: 5px; text-align: left; }
        th { background-color: #d3d3d3; font-weight: bold; }
        .label { font-weight: bold; width: 30%; }
        .value { width: 70%; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h2>PERSONAL DATA SHEET</h2>
    <p style="text-align: center; font-size: 9px;">CS FORM NO. 212 (Revised 2017)</p>
    <p style="text-align: center;"><strong>Republic of the Philippines</strong></p>
    
    <h3>I. PERSONAL INFORMATION</h3>
    <table>
        <tr><td class="label">SURNAME:</td><td class="value">' . htmlspecialchars($data['last_name']) . '</td></tr>
        <tr><td class="label">FIRST NAME:</td><td class="value">' . htmlspecialchars($data['first_name']) . '</td></tr>
        <tr><td class="label">MIDDLE NAME:</td><td class="value">' . htmlspecialchars($data['middle_name'] ?? '') . '</td></tr>
        <tr><td class="label">DATE OF BIRTH:</td><td class="value">' . htmlspecialchars($data['date_of_birth'] ?? '') . '</td></tr>
        <tr><td class="label">PLACE OF BIRTH:</td><td class="value">' . htmlspecialchars($data['place_of_birth'] ?? '') . '</td></tr>
        <tr><td class="label">SEX:</td><td class="value">' . htmlspecialchars($data['sex'] ?? '') . '</td></tr>
        <tr><td class="label">CIVIL STATUS:</td><td class="value">' . htmlspecialchars($data['civil_status'] ?? '') . '</td></tr>
        <tr><td class="label">HEIGHT:</td><td class="value">' . htmlspecialchars($data['height'] ?? '') . '</td></tr>
        <tr><td class="label">WEIGHT:</td><td class="value">' . htmlspecialchars($data['weight'] ?? '') . '</td></tr>
        <tr><td class="label">BLOOD TYPE:</td><td class="value">' . htmlspecialchars($data['blood_type'] ?? '') . '</td></tr>
        <tr><td class="label">RESIDENTIAL ADDRESS:</td><td class="value">' . htmlspecialchars($data['address'] ?? '') . '</td></tr>
        <tr><td class="label">MOBILE NO.:</td><td class="value">' . htmlspecialchars($data['phone']) . '</td></tr>
        <tr><td class="label">EMAIL ADDRESS:</td><td class="value">' . htmlspecialchars($data['email']) . '</td></tr>
    </table>
    
    <h3>II. CURRENT EMPLOYMENT</h3>
    <table>
        <tr><td class="label">CURRENT POSITION:</td><td class="value">' . htmlspecialchars($data['current_position'] ?? '') . '</td></tr>
        <tr><td class="label">CURRENT COMPANY:</td><td class="value">' . htmlspecialchars($data['current_company'] ?? '') . '</td></tr>
        <tr><td class="label">NOTICE PERIOD:</td><td class="value">' . htmlspecialchars($data['notice_period'] ?? '') . '</td></tr>
        <tr><td class="label">EXPECTED SALARY:</td><td class="value">' . htmlspecialchars($data['expected_salary'] ?? '') . '</td></tr>
        <tr><td class="label">SOURCE:</td><td class="value">' . htmlspecialchars($data['source'] ?? '') . '</td></tr>
    </table>
    
    <p style="margin-top: 30px; text-align: center; font-size: 10px;">
        <strong>Instructions:</strong> Use your browser\'s Print function (Ctrl+P or Cmd+P) and select "Save as PDF"
    </p>
</body>
</html>';
    
    file_put_contents($htmlFile, $html);
    
    // Redirect to the HTML file for download/print
    header("Location: " . $htmlFile);
    exit();
    
} else {
    die("Invalid request method.");
}
?>
