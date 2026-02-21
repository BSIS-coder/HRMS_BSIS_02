<?php
// Database connection
$host = getenv('DB_HOST') ?? 'localhost';
$dbname = getenv('DB_NAME') ?? 'hr_system';
$username = getenv('DB_USER') ?? 'root';
$password = getenv('DB_PASS') ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if columns exist and add them if they don't
    $stmt = $pdo->query("DESCRIBE goal_updates");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $alterQueries = [];
    
    // Add missing columns
    if (!in_array('updated_by', $columns)) {
        $alterQueries[] = "ALTER TABLE goal_updates ADD COLUMN updated_by INT(11) NULL AFTER comments";
    }
    
    if (!in_array('status_before', $columns)) {
        $alterQueries[] = "ALTER TABLE goal_updates ADD COLUMN status_before VARCHAR(50) NULL AFTER updated_by";
    }
    
    if (!in_array('status_after', $columns)) {
        $alterQueries[] = "ALTER TABLE goal_updates ADD COLUMN status_after VARCHAR(50) NULL AFTER status_before";
    }
    
    if (!in_array('goal_update_id', $columns)) {
        // Rename update_id to goal_update_id if it exists
        $alterQueries[] = "ALTER TABLE goal_updates CHANGE COLUMN update_id goal_update_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY";
    }
    
    // Execute alter queries
    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
            echo "✓ " . $query . "<br>";
        } catch (PDOException $e) {
            echo "✗ " . $query . " - " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br><strong>Migration completed!</strong>";
    echo "<br><a href='goal_updates.php'>Back to Goal Updates</a>";
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
