<?php
session_start();

// ✅ Ensure user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'dp.php'; // uses config.php internally

try {
    // ✅ Fetch distinct department names from job_roles table
    $sql = "
        SELECT DISTINCT department
        FROM job_roles
        WHERE department IS NOT NULL AND TRIM(department) != ''
        ORDER BY department ASC
    ";
    $stmt = $conn->query($sql);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Return departments or handle empty result
    if (!empty($departments)) {
        echo json_encode($departments);
    } else {
        echo json_encode(['message' => 'No departments found']);
    }

} catch (PDOException $e) {
    // ✅ Log and return error safely
    error_log("get_departments.php error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error. Please contact the administrator.']);
}
?>
