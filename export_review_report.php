<?php
// export_review_report.php
// Export performance review data to Excel format

session_start();

// Require authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'dp.php'; // database connection

$cycle_id = isset($_GET['cycle_id']) ? (int) $_GET['cycle_id'] : 0;

if ($cycle_id <= 0) {
    // Try to fall back to the most recent cycle if none provided
    try {
        $row = $conn->query("SELECT cycle_id FROM performance_review_cycles ORDER BY start_date DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['cycle_id'])) {
            $cycle_id = (int)$row['cycle_id'];
            error_log("export_review_report: no cycle_id provided, falling back to latest cycle_id={$cycle_id}");
        }
    } catch (Exception $e) {
        error_log('export_review_report fallback error: ' . $e->getMessage());
    }

    if ($cycle_id <= 0) {
        die('No valid cycle selected');
    }
}

// Get cycle information
$cycleSql = "SELECT cycle_id, cycle_name, start_date, end_date FROM performance_review_cycles WHERE cycle_id = :cycle_id";
$cycleStmt = $conn->prepare($cycleSql);
$cycleStmt->bindParam(':cycle_id', $cycle_id, PDO::PARAM_INT);
$cycleStmt->execute();
$cycle = $cycleStmt->fetch(PDO::FETCH_ASSOC);

if (!$cycle) {
    // If debug, list available cycles to help diagnose
    if (isset($_GET['debug']) && $_GET['debug']) {
        header('Content-Type: text/plain');
        echo "Requested cycle_id={$cycle_id} not found.\nAvailable cycles:\n";
        $all = $conn->query("SELECT cycle_id, cycle_name, start_date, end_date FROM performance_review_cycles ORDER BY start_date DESC");
        foreach ($all->fetchAll(PDO::FETCH_ASSOC) as $c) {
            echo json_encode($c) . "\n";
        }
        exit;
    }
    die('Cycle not found');
}

// --- Debug: report table counts for this cycle ---
try {
    $c1 = $conn->prepare("SELECT COUNT(*) AS cnt FROM performance_reviews WHERE cycle_id = :cycle_id");
    $c1->execute([':cycle_id' => $cycle_id]);
    $cntPr = (int) $c1->fetchColumn();

    $c2 = $conn->prepare("SELECT COUNT(DISTINCT employee_id) AS cnt FROM employee_competencies WHERE cycle_id = :cycle_id");
    $c2->execute([':cycle_id' => $cycle_id]);
    $cntEc = (int) $c2->fetchColumn();

    error_log("export_review_report debug: cycle_id={$cycle_id} performance_reviews={$cntPr} employee_competencies={$cntEc}");

    if (isset($_GET['debug']) && $_GET['debug']) {
        header('Content-Type: text/plain');
        echo "Cycle: {$cycle['cycle_name']} (ID={$cycle_id})\n";
        echo "performance_reviews rows: {$cntPr}\n";
        echo "employee_competencies distinct employees: {$cntEc}\n\n";

        // show up to 10 rows from performance_reviews for inspection
        $s = $conn->prepare("SELECT pr_id, employee_id, overall_rating, status, review_date FROM performance_reviews WHERE cycle_id = :cycle_id LIMIT 10");
        $s->execute([':cycle_id' => $cycle_id]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample performance_reviews rows:\n";
        foreach ($rows as $r) {
            echo json_encode($r) . "\n";
        }

        exit;
    }
} catch (Exception $e) {
    error_log('export debug error: ' . $e->getMessage());
}

// Fetch rows: combine pending (from employee_competencies) and completed (from performance_reviews)
try {
    // Pending rows (those in employee_competencies but not finalized)
    $sql_pending = "
        SELECT
            ec.employee_id,
            CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
            d.department_name AS department,
            jr.title AS role,
            ROUND(AVG(ec.rating), 2) AS avg_rating,
            COUNT(ec.competency_id) AS competencies_assessed,
            MAX(ec.assessment_date) AS last_assessment_date,
            'Pending' AS status,
            NULL AS review_date,
            NULL AS manager_comments
        FROM employee_competencies ec
        JOIN employee_profiles ep ON ec.employee_id = ep.employee_id
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_name
        WHERE ec.cycle_id = :cycle_id
          AND NOT EXISTS (
              SELECT 1 FROM performance_reviews pr
              WHERE pr.employee_id = ec.employee_id AND pr.cycle_id = ec.cycle_id AND pr.status = 'Finalized'
          )
        GROUP BY ec.employee_id, pi.first_name, pi.last_name, d.department_name, jr.title
    ";

    $stmt = $conn->prepare($sql_pending);
    $stmt->bindParam(':cycle_id', $cycle_id, PDO::PARAM_INT);
    $stmt->execute();
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Completed rows (from performance_reviews)
    $sql_completed = "
        SELECT
            pr.employee_id,
            CONCAT(pi.first_name, ' ', pi.last_name) AS employee_name,
            d.department_name AS department,
            jr.title AS role,
            pr.overall_rating AS avg_rating,
            (SELECT COUNT(*) FROM employee_competencies ec2 WHERE ec2.employee_id = pr.employee_id AND ec2.cycle_id = pr.cycle_id) AS competencies_assessed,
            pr.review_date AS last_assessment_date,
            pr.status AS status,
            pr.review_date,
            pr.manager_comments
        FROM performance_reviews pr
        JOIN employee_profiles ep ON pr.employee_id = ep.employee_id
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
        LEFT JOIN departments d ON jr.department = d.department_name
        WHERE pr.cycle_id = :cycle_id
    ";

    $stmt2 = $conn->prepare($sql_completed);
    $stmt2->bindParam(':cycle_id', $cycle_id, PDO::PARAM_INT);
    $stmt2->execute();
    $completed = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Merge and sort by last_assessment_date desc
    $reviews = array_merge($pending, $completed);
    usort($reviews, function($a, $b){
        return strtotime($b['last_assessment_date'] ?? '1970-01-01') <=> strtotime($a['last_assessment_date'] ?? '1970-01-01');
    });
} catch (PDOException $e) {
    // fallback to empty
    $reviews = [];
}

// Format filename
$filename = 'Performance_Reviews_' . str_replace(' ', '_', $cycle['cycle_name']) . '_' . date('Y-m-d') . '.xls';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Generate Excel content
echo '<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Worksheet ss:Name="Performance Reviews">
<Table>';

// Header row
echo '<Row>
<Cell><Data ss:Type="String">Employee Name</Data></Cell>
<Cell><Data ss:Type="String">Department</Data></Cell>
<Cell><Data ss:Type="String">Job Role</Data></Cell>
<Cell><Data ss:Type="String">Average Rating</Data></Cell>
<Cell><Data ss:Type="String">Competencies Assessed</Data></Cell>
<Cell><Data ss:Type="String">Last Assessment Date</Data></Cell>
<Cell><Data ss:Type="String">Status</Data></Cell>
<Cell><Data ss:Type="String">Review Date</Data></Cell>
<Cell><Data ss:Type="String">Manager Comments</Data></Cell>
</Row>';

// Data rows
foreach ($reviews as $row) {
    echo '<Row>';

    // Employee Name
    echo '<Cell><Data ss:Type="String">' . escapeExcel($row['employee_name']) . '</Data></Cell>';

    // Department
    echo '<Cell><Data ss:Type="String">' . escapeExcel($row['department'] ?? '') . '</Data></Cell>';

    // Job Role
    echo '<Cell><Data ss:Type="String">' . escapeExcel($row['role'] ?? '') . '</Data></Cell>';

    // Average Rating (Number if numeric, otherwise blank string)
    if (isset($row['avg_rating']) && is_numeric($row['avg_rating'])) {
        $avgRating = number_format((float)$row['avg_rating'], 2, '.', '');
        echo '<Cell><Data ss:Type="Number">' . $avgRating . '</Data></Cell>';
    } else {
        echo '<Cell><Data ss:Type="String"></Data></Cell>';
    }

    // Competencies Assessed (Number)
    $compCount = is_numeric($row['competencies_assessed']) ? (int)$row['competencies_assessed'] : 0;
    echo '<Cell><Data ss:Type="Number">' . $compCount . '</Data></Cell>';

    // Last Assessment Date
    echo '<Cell><Data ss:Type="String">' . escapeExcel($row['last_assessment_date'] ?? '') . '</Data></Cell>';

    // Status
    echo '<Cell><Data ss:Type="String">' . escapeExcel($row['status'] ?? 'Pending') . '</Data></Cell>';

    // Review Date
    echo '<Cell><Data ss:Type="String">' . escapeExcel($row['review_date'] ?? '') . '</Data></Cell>';

    // Manager Comments
    echo '<Cell><Data ss:Type="String">' . escapeExcel($row['manager_comments'] ?? '') . '</Data></Cell>';

    echo '</Row>';
}

echo '</Table>
</Worksheet>
</Workbook>';

// Helper function to escape Excel special characters
function escapeExcel($str) {
    if ($str === null || $str === '') {
        return '';
    }
    // Replace special XML characters
    $str = str_replace(
        ['&', '<', '>', '"', "'"],
        ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
        $str
    );
    return $str;
}
