<?php
// debug_employee_query.php
// Debug script to test employee data retrieval

require_once 'config.php';

$employee_id = 2; // From debug info
$cycle_id = 5;

echo "Debugging Employee Query for ID: $employee_id\n\n";

// 1. Check employee_profiles using the SAME approach as evaluation_training_report.php
echo "1. Testing the exact query from evaluation_training_report.php:\n";
$checkEmpSql = "SELECT COUNT(*) as cnt FROM employee_profiles WHERE employee_id = ?";
$checkStmt = $conn->prepare($checkEmpSql);
$checkStmt->bindParam(1, $employee_id, PDO::PARAM_INT);
$checkStmt->execute();
$empExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
echo "Employee exists check: " . json_encode($empExists) . "\n\n";

// 2. Get employee data with JOIN
echo "2. Running the JOIN query:\n";
$empSql = "SELECT ep.*, pi.first_name, pi.last_name, pi.email, pi.phone, 
           jr.title as job_title
           FROM employee_profiles ep
           LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
           LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
           WHERE ep.employee_id = ?";

$stmt = $conn->prepare($empSql);
$stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
$stmt->execute();
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Employee Data (JOIN): " . json_encode($employee) . "\n\n";

// 3. Test if issue is with PDO::PARAM_INT
echo "3. Testing without PDO::PARAM_INT:\n";
$empSql2 = "SELECT ep.*, pi.first_name, pi.last_name, pi.email, pi.phone, 
           jr.title as job_title
           FROM employee_profiles ep
           LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
           LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
           WHERE ep.employee_id = ?";

$stmt2 = $conn->prepare($empSql2);
$stmt2->execute([$employee_id]);
$employee2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "Employee Data (no PARAM_INT): " . json_encode($employee2) . "\n\n";

// 4. Test the exact code from evaluation_training_report.php
echo "4. Testing exact code block from evaluation_training_report.php:\n";

// Check if personal_information table exists and has data
$personalInfoExists = true;
$personalInfoHasData = false;
try {
    $conn->query("SELECT 1 FROM personal_information LIMIT 1");
    $piCount = $conn->query("SELECT COUNT(*) as cnt FROM personal_information")->fetch(PDO::FETCH_ASSOC);
    $personalInfoHasData = ($piCount['cnt'] > 0);
    echo "Personal Info Count: " . $piCount['cnt'] . "\n";
} catch (PDOException $e) {
    $personalInfoExists = false;
    echo "Personal Info Error: " . $e->getMessage() . "\n";
}

echo "personalInfoExists: " . ($personalInfoExists ? "true" : "false") . "\n";
echo "personalInfoHasData: " . ($personalInfoHasData ? "true" : "false") . "\n\n";

// The exact query from the file
$empSql = "SELECT ep.*, pi.first_name, pi.last_name, pi.email, pi.phone, 
           jr.title as job_title
           FROM employee_profiles ep
           LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
           LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
           WHERE ep.employee_id = ?";

$stmt = $conn->prepare($empSql);
$stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
$stmt->execute();
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Final Employee Data: " . json_encode($employee) . "\n\n";

// 5. Check cycle data
echo "5. Checking cycle data:\n";
$cycleStmt = $conn->prepare("SELECT * FROM performance_review_cycles WHERE cycle_id = ?");
$cycleStmt->bindParam(1, $cycle_id, PDO::PARAM_INT);
$cycleStmt->execute();
$cycle = $cycleStmt->fetch(PDO::FETCH_ASSOC);
echo "Cycle Data: " . json_encode($cycle) . "\n\n";

// 6. Check evaluations
echo "6. Checking evaluations:\n";
$evalSql = "SELECT ec.*, c.name as competency_name, c.description as competency_description
            FROM employee_competencies ec
            LEFT JOIN competencies c ON ec.competency_id = c.competency_id
            WHERE ec.employee_id = ? AND ec.cycle_id = ?
            ORDER BY c.name";

$stmt = $conn->prepare($evalSql);
$stmt->bindParam(1, $employee_id, PDO::PARAM_INT);
$stmt->bindParam(2, $cycle_id, PDO::PARAM_INT);
$stmt->execute();
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Evaluations Count: " . count($evaluations) . "\n";
echo "Evaluations: " . json_encode($evaluations) . "\n\n";
?>
