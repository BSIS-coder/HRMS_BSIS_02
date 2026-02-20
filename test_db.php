<?php
require_once 'config.php';

try {
    $stmt = $conn->query('SELECT COUNT(*) as count FROM attendance');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo 'Attendance records: ' . $result['count'] . PHP_EOL;
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
