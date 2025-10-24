<?php
require_once 'config.php';

try {
    $sql = file_get_contents('add_overtime_late_columns.sql');
    $conn->exec($sql);
    echo "SQL script executed successfully.\n";
} catch (PDOException $e) {
    echo "Error executing SQL script: " . $e->getMessage() . "\n";
}
?>
