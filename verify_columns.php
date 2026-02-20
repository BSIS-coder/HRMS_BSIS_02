<?php
require_once 'config.php';

try {
    $sql = "DESCRIBE attendance";
    $stmt = $conn->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Attendance table structure:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }

    // Check if overtime_hours and late_minutes exist
    $hasOvertime = false;
    $hasLate = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'overtime_hours') {
            $hasOvertime = true;
        }
        if ($column['Field'] === 'late_minutes') {
            $hasLate = true;
        }
    }

    if ($hasOvertime && $hasLate) {
        echo "\n✓ Both overtime_hours and late_minutes columns are present.\n";
    } else {
        echo "\n✗ Missing columns:\n";
        if (!$hasOvertime) echo "- overtime_hours\n";
        if (!$hasLate) echo "- late_minutes\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
