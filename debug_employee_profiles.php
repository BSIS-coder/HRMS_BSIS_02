<?php
require_once 'dp.php';

try {
    $stmt = $conn->prepare('DESCRIBE employee_profiles');
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "employee_profiles columns:\n";
    foreach ($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . PHP_EOL;
    }
    echo "\n";

    $stmt = $conn->prepare('SELECT u.user_id, u.username, u.role, ep.employee_id FROM users u LEFT JOIN employee_profiles ep ON u.employee_id = ep.employee_id WHERE u.role = "employee" LIMIT 5');
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Employee users:\n";
    foreach ($users as $user) {
        echo 'User: ' . $user['username'] . ', User ID: ' . $user['user_id'] . ', Employee ID: ' . ($user['employee_id'] ?? 'NULL') . PHP_EOL;
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
