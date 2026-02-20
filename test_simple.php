<?php
require_once 'config.php';
require_once 'dp.php';
require_once 'leave_requests.php';

$requests = getLeaveRequests();
echo 'Fetched ' . count($requests) . ' leave requests';
if (!empty($requests)) {
    echo "\nFirst request document_path: " . ($requests[0]['document_path'] ?? 'NULL');
}
?>
