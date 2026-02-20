<?php
require_once 'config.php';

try {
    // Add holiday_type column if it doesn't exist
    $conn->exec("ALTER TABLE public_holidays ADD COLUMN holiday_type VARCHAR(50) NOT NULL DEFAULT 'Regular Holiday'");
    echo "<li>Successfully added 'holiday_type' column.</li>";
} catch (PDOException $e) {
    // Ignore error if column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
        echo "<li>Error adding 'holiday_type' column: " . $e->getMessage() . "</li>";
    } else {
        echo "<li>'holiday_type' column already exists.</li>";
    }
}

try {
    // Add source column if it doesn't exist
    $conn->exec("ALTER TABLE public_holidays ADD COLUMN source VARCHAR(50) DEFAULT 'manual'");
    echo "<li>Successfully added 'source' column.</li>";
} catch (PDOException $e) {
    // Ignore error if column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
        echo "<li>Error adding 'source' column: " . $e->getMessage() . "</li>";
    } else {
        echo "<li>'source' column already exists.</li>";
    }
}

echo "<br>Database schema update complete. You can now use the 'Migrate Holiday Types' button.";
?>