<?php
// Update leave days to match Philippine labor standards
require_once 'config.php';

try {
    // Update Sick Leave from 10 to 15 days
    $sql1 = "UPDATE leave_types SET default_days = 15 WHERE leave_type_name = 'Sick Leave'";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->execute();
    echo "Updated Sick Leave to 15 days\n";

    // Update Maternity Leave from 60 to 105 days
    $sql2 = "UPDATE leave_types SET default_days = 105 WHERE leave_type_name = 'Maternity Leave'";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->execute();
    echo "Updated Maternity Leave to 105 days\n";

    // Vacation Leave (15 days) already matches Philippine standards
    // Paternity Leave (7 days) already matches Philippine standards
    // Emergency Leave (5 days) remains as discretionary

    echo "Leave days successfully updated to match Philippine labor standards!\n";

    // Display updated leave types
    $sql = "SELECT leave_type_name, default_days FROM leave_types ORDER BY leave_type_name";
    $stmt = $conn->query($sql);
    $leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nUpdated Leave Types:\n";
    foreach ($leaveTypes as $type) {
        echo "- {$type['leave_type_name']}: {$type['default_days']} days\n";
    }

} catch (PDOException $e) {
    echo "Error updating leave days: " . $e->getMessage() . "\n";
}
?>
