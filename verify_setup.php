<?php
require 'config.php';

// Verify training tables
echo "=== Training System Verification ===\n\n";

// Check tables exist
$tables = ['training_courses', 'trainers', 'training_sessions', 'training_enrollments', 'certifications'];
foreach ($tables as $table) {
    try {
        $cnt = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch(PDO::FETCH_ASSOC);
        echo "✓ $table: " . $cnt['cnt'] . " records\n";
    } catch (PDOException $e) {
        echo "✗ $table: ERROR - " . $e->getMessage() . "\n";
    }
}

echo "\n=== Employee Competencies Verification ===\n";
$empId = 11;
$cycleId = 4;

// Check competencies for Weekly Evaluation
$comps = $conn->prepare("
    SELECT c.name, ec.rating, ec.comments 
    FROM employee_competencies ec 
    JOIN competencies c ON ec.competency_id = c.competency_id 
    WHERE ec.employee_id = ? AND ec.cycle_id = ?
");
$comps->execute([$empId, $cycleId]);
$results = $comps->fetchAll(PDO::FETCH_ASSOC);

echo "Employee MUN011 - Weekly Evaluation competencies:\n";
foreach ($results as $r) {
    echo "- " . $r['name'] . ": Rating " . $r['rating'] . "\n";
}

echo "\n=== Summary ===\n";
echo "Training tables: SET UP\n";
echo "Competencies assigned: " . count($results) . " for Weekly Evaluation\n";
echo "\nYou can now view the Employee Evaluation & Training Report!\n";
?>
