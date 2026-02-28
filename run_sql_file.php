<?php
require_once 'dp.php';

$sql = file_get_contents('create_training_tables.sql');

// Split by semicolons to get individual statements
$statements = explode(';', $sql);

$conn->beginTransaction();
try {
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && strpos($statement, '--') !== 0) {
            // Skip comment lines
            $lines = explode("\n", $statement);
            $cleanLines = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '--') !== 0) {
                    $cleanLines[] = $line;
                }
            }
            if (!empty($cleanLines)) {
                $cleanStatement = implode("\n", $cleanLines);
                $conn->exec($cleanStatement);
            }
        }
    }
    $conn->commit();
    echo "Training tables created successfully!\n";
} catch (PDOException $e) {
    $conn->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
?>
