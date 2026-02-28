<?php
require 'config.php';

// Check competencies
$comps = $conn->query("SELECT COUNT(*) as cnt FROM competencies")->fetch(PDO::FETCH_ASSOC);
echo "Total competencies: " . $comps['cnt'] . "\n";

// List competencies
$competencies = $conn->query("SELECT competency_id, name, description FROM competencies LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "\nCompetencies list:\n";
foreach ($competencies as $c) {
    echo "- " . $c['name'] . " (ID: " . $c['competency_id'] . ")\n";
}

// Check employee
$emp = $conn->query("SELECT employee_id, employee_number FROM employee_profiles WHERE employee_number = 'MUN011'")->fetch(PDO::FETCH_ASSOC);
if ($emp) {
    echo "\nEmployee MUN011 found: ID = " . $emp['employee_id'] . "\n";
} else {
    echo "\nEmployee MUN011 not found\n";
}

// Check cycles
$cycles = $conn->query("SELECT cycle_id, cycle_name FROM performance_review_cycles")->fetchAll(PDO::FETCH_ASSOC);
echo "\nReview Cycles:\n";
foreach ($cycles as $c) {
    echo "- " . $c['cycle_name'] . " (ID: " . $c['cycle_id'] . ")\n";
}

// Check existing employee competencies
$empComps = $conn->query("SELECT ec.*, c.name as comp_name FROM employee_competencies ec 
    LEFT JOIN competencies c ON ec.competency_id = c.competency_id
    WHERE ec.employee_id = (SELECT employee_id FROM employee_profiles WHERE employee_number = 'MUN011')")->fetchAll(PDO::FETCH_ASSOC);
echo "\nExisting employee competencies: " . count($empComps) . "\n";
?>
