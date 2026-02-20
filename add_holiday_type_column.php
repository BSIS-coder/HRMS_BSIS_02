<?php
require_once 'config.php';

try {
    $conn->exec("ALTER TABLE public_holidays ADD COLUMN holiday_type VARCHAR(255) DEFAULT 'Regular Holiday' NOT NULL");
    echo "Successfully added 'holiday_type' column to 'public_holidays' table.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>