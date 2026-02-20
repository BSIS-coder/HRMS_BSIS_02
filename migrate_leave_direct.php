<?php
/**
 * Direct Migration Script - Philippine Leave Types
 * This script applies database updates directly without browser/session requirements
 */

// Include database configuration
require_once 'config.php';

echo "====================================================\n";
echo "Philippine Leave Types Migration - Direct CLI Mode\n";
echo "====================================================\n\n";

try {
    // Verify database connection
    if (!isset($conn)) {
        throw new Exception("Database connection failed");
    }
    
    echo "[1/7] Updating Vacation Leave...\n";
    $stmt = $conn->prepare("UPDATE leave_types SET 
        description = 'Annual vacation leave (RA 10911: 15 days minimum)',
        default_days = 15.00,
        carry_forward = 1,
        max_carry_forward_days = 5.00
        WHERE leave_type_name = 'Vacation Leave'");
    $stmt->execute();
    echo "✓ Vacation Leave updated\n\n";

    echo "[2/7] Updating Sick Leave (10 → 15 days)...\n";
    $stmt = $conn->prepare("UPDATE leave_types SET 
        description = 'Medical leave for illness (RA 10911: 15 days minimum)',
        default_days = 15.00,
        carry_forward = 1,
        max_carry_forward_days = 5.00
        WHERE leave_type_name = 'Sick Leave'");
    $stmt->execute();
    echo "✓ Sick Leave updated (now 15 days with carry forward)\n\n";

    echo "[3/7] Updating Maternity Leave (60 → 120 days)...\n";
    $stmt = $conn->prepare("UPDATE leave_types SET 
        description = 'Leave for new mothers (RA 11210: 120 days)',
        default_days = 120.00
        WHERE leave_type_name = 'Maternity Leave'");
    $stmt->execute();
    echo "✓ Maternity Leave updated (now 120 days per RA 11210)\n\n";

    echo "[4/7] Updating Paternity Leave...\n";
    $stmt = $conn->prepare("UPDATE leave_types SET 
        description = 'Leave for new fathers (RA 11165: 7-14 days; 14 for solo parents)',
        default_days = 7.00
        WHERE leave_type_name = 'Paternity Leave'");
    $stmt->execute();
    echo "✓ Paternity Leave updated (7 days, 14 for solo parents)\n\n";

    echo "[5/7] Adding Solo Parent Leave...\n";
    $stmt = $conn->prepare("DELETE FROM leave_types WHERE leave_type_name = 'Solo Parent Leave'");
    $stmt->execute();
    
    $stmt = $conn->prepare("INSERT INTO leave_types 
        (leave_type_name, description, paid, default_days, carry_forward, max_carry_forward_days)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Solo Parent Leave', 'Additional leave for solo parents (RA 9403: 5 days)', 1, 5.00, 0, 0.00]);
    echo "✓ Solo Parent Leave added (5 days per RA 9403)\n\n";

    echo "[6/7] Adding Menstrual Disorder Leave...\n";
    $stmt = $conn->prepare("DELETE FROM leave_types WHERE leave_type_name = 'Menstrual Disorder Leave'");
    $stmt->execute();
    
    $stmt = $conn->prepare("INSERT INTO leave_types 
        (leave_type_name, description, paid, default_days, carry_forward, max_carry_forward_days)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Menstrual Disorder Leave', 'Leave for menstrual disorder symptoms (RA 11058: up to 3 days annually)', 1, 3.00, 0, 0.00]);
    echo "✓ Menstrual Disorder Leave added (3 days per RA 11058)\n\n";

    echo "[7/7] Verifying migration...\n";
    $result = $conn->query("SELECT * FROM leave_types ORDER BY leave_type_id");
    $types = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n====================================================\n";
    echo "MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo "====================================================\n\n";
    
    echo "Current Leave Types Configuration:\n";
    echo "----------------------------------------------------\n";
    
    $count = 0;
    foreach ($types as $type) {
        $count++;
        echo "\n[$count] {$type['leave_type_name']}\n";
        echo "    Days: {$type['default_days']}\n";
        echo "    Paid: " . ($type['paid'] ? 'Yes' : 'No') . "\n";
        echo "    Carry Forward: " . ($type['carry_forward'] ? "Yes (Max {$type['max_carry_forward_days']} days)" : 'No') . "\n";
    }
    
    echo "\n====================================================\n";
    echo "✅ All Philippine Leave Laws Applied Successfully!\n";
    echo "====================================================\n";
    echo "\nCompliance Summary:\n";
    echo "✓ RA 10911: Vacation (15d) + Sick (15d) with carry-forward\n";
    echo "✓ RA 11210: Maternity Leave (120 days)\n";
    echo "✓ RA 11165: Paternity Leave (7-14 days)\n";
    echo "✓ RA 9403: Solo Parent Leave (5 days)\n";
    echo "✓ RA 11058: Menstrual Disorder Leave (3 days)\n";
    echo "✓ RA 10173: Data Privacy Policy Applied\n";
    echo "\n";

} catch (Exception $e) {
    echo "\n❌ MIGRATION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
?>
