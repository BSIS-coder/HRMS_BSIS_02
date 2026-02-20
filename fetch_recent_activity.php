<?php
session_start();
require_once 'dp.php';

header('Content-Type: application/json');

function humanTiming($time)
{
    $time = time() - $time; // to get the time since that moment
    $time = ($time < 1) ? 1 : $time;
    $tokens = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );
    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '');
    }
}

$recentActivities = getRecentAuditLogs(5);
error_log("fetch_recent_activity.php: fetched " . count($recentActivities) . " activities");

$response = [];

foreach ($recentActivities as $activity) {
    $response[] = [
        'username' => $activity['username'] ?? 'Unknown User',
        'action' => $activity['action'],
        'time_ago' => humanTiming(strtotime($activity['created_at']))
    ];
}

error_log("fetch_recent_activity.php: response array has " . count($response) . " items");
echo json_encode($response);
?>
