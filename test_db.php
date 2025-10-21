<?php
require_once 'config.php';

try {
    $stmt = $conn->query('SELECT cycle_id FROM performance_review_cycles LIMIT 1');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $result['cycle_id'] ?? 'No cycles found';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
