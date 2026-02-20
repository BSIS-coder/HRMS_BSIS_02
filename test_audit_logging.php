<?php
session_start();
require_once 'config.php';
require_once 'dp.php';

// Simulate a logged-in user
$_SESSION['user_id'] = 1; // Assuming user_id 1 exists

// Test logActivity function
echo "Testing audit logging...<br>";

logActivity("Test Action", "test_table", 123, ['test' => 'data']);

echo "Test completed. Check audit_logs table for new entry.<br>";

// Query the audit_logs table to verify
try {
    $sql = "SELECT * FROM audit_logs ORDER BY audit_id DESC LIMIT 1";
    $stmt = $conn->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo "<br>Last audit log entry:<br>";
        echo "ID: " . $result['audit_id'] . "<br>";
        echo "User ID: " . $result['user_id'] . "<br>";
        echo "Action: " . $result['action'] . "<br>";
        echo "Table: " . $result['table_name'] . "<br>";
        echo "Record ID: " . $result['record_id'] . "<br>";
        echo "New Values: " . $result['new_values'] . "<br>";
        echo "Created At: " . $result['created_at'] . "<br>";
    } else {
        echo "No audit log entries found.";
    }
} catch (PDOException $e) {
    echo "Error querying audit_logs: " . $e->getMessage();
}
?>
