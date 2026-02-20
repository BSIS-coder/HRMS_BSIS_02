<?php
require_once 'dp.php';

try {
    $stmt = $conn->prepare('SELECT employee_id FROM users WHERE username = ?');
    $stmt->execute(['test_employee']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo 'Employee ID: ' . $user['employee_id'] . PHP_EOL;
    } else {
        echo 'Test employee not found.' . PHP_EOL;
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
