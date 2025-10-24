<?php
require_once 'config.php';
require_once 'dp.php';

try {
    $requests = getLeaveRequests();
    $output = 'Fetched ' . count($requests) . ' leave requests';
    if (!empty($requests)) {
        $output .= "\nFirst request document_path: " . ($requests[0]['document_path'] ?? 'NULL');
        $output .= "\nAll keys in first request: " . implode(', ', array_keys($requests[0]));
    }
    echo $output;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
