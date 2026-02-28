<?php
// get_employees.php
header('Content-Type: application/json');
require_once 'config.php';

try {
    // Get employees - extract name from work_email since personal_information table doesn't exist
    $sql = "SELECT 
                employee_id, 
                employee_number, 
                employment_status,
                work_email,
                CASE 
                    WHEN work_email IS NOT NULL AND work_email != '' THEN 
                        TRIM(REPLACE(REPLACE(REPLACE(REPLACE(work_email, '@municipality.gov.ph', ''), '.', ' '), '_', ' '), '  ', ' '))
                    ELSE employee_number
                END AS full_name
            FROM employee_profiles
            WHERE employment_status IN ('Full-time', 'Part-time', 'Contract')
            ORDER BY full_name";
    
    $stmt = $conn->query($sql);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no employees found, try fallback with no status filter
    if (empty($employees)) {
        $sqlFallback = "SELECT 
                employee_id, 
                employee_number, 
                employment_status,
                work_email,
                COALESCE(employee_number, 'Unknown') AS full_name
                FROM employee_profiles
                ORDER BY employee_number";
        $stmtFallback = $conn->query($sqlFallback);
        $employees = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($employees);
} catch (PDOException $e) {
    error_log("get_employees error: " . $e->getMessage());
    echo json_encode(["error" => true, "message" => $e->getMessage()]);
}
