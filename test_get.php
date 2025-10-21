<?php
require_once 'config.php';

$cycle_id = isset($_GET['cycle_id']) ? (int) $_GET['cycle_id'] : 0;
echo "cycle_id received: $cycle_id\n";
echo "GET array: " . print_r($_GET, true) . "\n";
?>
