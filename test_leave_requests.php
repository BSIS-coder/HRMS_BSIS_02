<?php
require_once 'config.php';
require_once 'dp.php';

echo "Testing leave requests functionality...\n";

// Test database connection
try {
    $conn->query("SELECT 1");
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test getLeaveRequests function
try {
    $requests = getLeaveRequests();
    echo "✓ Fetched " . count($requests) . " leave requests successfully\n";

    // Check if document_path column exists in results
    if (!empty($requests)) {
        $firstRequest = $requests[0];
        if (array_key_exists('document_path', $firstRequest)) {
            echo "✓ document_path column exists in query results\n";
        } else {
            echo "✗ document_path column missing from query results\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Error fetching leave requests: " . $e->getMessage() . "\n";
}

// Test getLeaveTypes function
try {
    $leaveTypes = getLeaveTypes();
    echo "✓ Fetched " . count($leaveTypes) . " leave types successfully\n";
} catch (Exception $e) {
    echo "✗ Error fetching leave types: " . $e->getMessage() . "\n";
}

// Test getEmployees function
try {
    $employees = getEmployees();
    echo "✓ Fetched " . count($employees) . " employees successfully\n";
} catch (Exception $e) {
    echo "✗ Error fetching employees: " . $e->getMessage() . "\n";
}

// Test upload directory
$uploadDir = 'uploads/leave_documents/';
if (file_exists($uploadDir) && is_dir($uploadDir)) {
    echo "✓ Upload directory exists: $uploadDir\n";
} else {
    echo "✗ Upload directory missing: $uploadDir\n";
}

echo "\nTesting complete.\n";
?>
