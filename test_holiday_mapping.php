<?php
/**
 * Test script to demonstrate holiday type mapping
 * Run this to see how system types map to Philippine official types
 */

require_once 'config.php';
require_once 'holiday_type_mapper.php';

echo "=== Holiday Type Mapping Test ===\n\n";

// Test cases: System type => Holiday name => Expected Philippine type
$testCases = [
    ['National', 'New Year\'s Day', 'Regular Holiday'],
    ['National', 'Independence Day', 'Regular Holiday'],
    ['National', 'Labor Day', 'Regular Holiday'],
    ['National', 'Christmas Day', 'Regular Holiday'],
    ['Special', 'Chinese New Year', 'Special Non-Working Holiday'],
    ['Special', 'Ninoy Aquino Day', 'Special Non-Working Holiday'],
    ['Special', 'All Saints\' Day', 'Special Non-Working Holiday'],
    ['Regional', 'Manila Day', 'Local Special Holiday'],
    ['Special', 'EDSA People Power Revolution Anniversary', 'Special Working Holiday'],
];

echo "Testing System Type to Philippine Type Mapping:\n";
echo str_repeat("-", 80) . "\n";
printf("%-20s %-35s %-30s %-30s\n", "System Type", "Holiday Name", "Mapped Type", "Expected");
echo str_repeat("-", 80) . "\n";

foreach ($testCases as $test) {
    $systemType = $test[0];
    $holidayName = $test[1];
    $expected = $test[2];
    $mapped = mapToPhilippineHolidayType($systemType, $holidayName);
    $status = ($mapped === $expected) ? "✓" : "✗";
    
    printf("%-20s %-35s %-30s %-30s %s\n", 
        $systemType, 
        substr($holidayName, 0, 34),
        $mapped,
        $expected,
        $status
    );
}

echo "\n\n=== Philippine Holiday Types ===\n";
$philippineTypes = getPhilippineHolidayTypes();
foreach ($philippineTypes as $type) {
    echo "- {$type}: " . getHolidayTypeDescription($type) . "\n";
}

echo "\n\n=== Pay Multiplier Examples ===\n";
echo "Regular Holiday (not worked): " . (getHolidayPayMultiplier('Regular Holiday', false) * 100) . "%\n";
echo "Regular Holiday (worked): " . (getHolidayPayMultiplier('Regular Holiday', true) * 100) . "%\n";
echo "Special Non-Working Holiday (not worked): " . (getHolidayPayMultiplier('Special Non-Working Holiday', false) * 100) . "%\n";
echo "Special Non-Working Holiday (worked): " . (getHolidayPayMultiplier('Special Non-Working Holiday', true) * 100) . "%\n";
echo "Special Working Holiday (worked): " . (getHolidayPayMultiplier('Special Working Holiday', true) * 100) . "%\n";

// Test with actual database holidays if available
echo "\n\n=== Current Database Holidays ===\n";
try {
    $hasHolidayType = false;
    $stmt = $conn->query("SHOW COLUMNS FROM public_holidays LIKE 'holiday_type'");
    $hasHolidayType = $stmt->rowCount() > 0;
    
    if ($hasHolidayType) {
        $stmt = $conn->query("SELECT holiday_id, holiday_name, holiday_type FROM public_holidays ORDER BY holiday_date LIMIT 10");
        $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($holidays) > 0) {
            echo str_repeat("-", 80) . "\n";
            printf("%-35s %-25s %-30s\n", "Holiday Name", "Current Type", "Mapped Philippine Type");
            echo str_repeat("-", 80) . "\n";
            
            foreach ($holidays as $holiday) {
                $currentType = $holiday['holiday_type'] ?? 'National';
                $mappedType = mapToPhilippineHolidayType($currentType, $holiday['holiday_name']);
                
                printf("%-35s %-25s %-30s\n", 
                    substr($holiday['holiday_name'], 0, 34),
                    $currentType,
                    $mappedType
                );
            }
        } else {
            echo "No holidays found in database.\n";
        }
    } else {
        echo "holiday_type column does not exist in database.\n";
    }
} catch (PDOException $e) {
    echo "Error accessing database: " . $e->getMessage() . "\n";
}

echo "\n\n=== Migration Test ===\n";
echo "To migrate all holidays to Philippine types, you can use:\n";
echo "migrateHolidayTypesToPhilippine(\$conn);\n";
echo "\nNote: This will update all holiday types in the database.\n";
echo "Make sure to backup your database before running migration.\n";

?>
