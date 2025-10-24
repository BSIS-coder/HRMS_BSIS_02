<?php
require 'dp.php';

try {
    $conn->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS overtime_hours DECIMAL(5,2) DEFAULT 0.00");
    $conn->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS late_minutes DECIMAL(5,2) DEFAULT 0.00");
    echo "Columns added successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
