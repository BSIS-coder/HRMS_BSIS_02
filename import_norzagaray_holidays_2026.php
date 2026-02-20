<?php
/**
 * Import Holidays for Municipality of Norzagaray, Bulacan - 2026
 * 
 * This script imports all official holidays for Norzagaray, Bulacan into the system.
 */

require_once 'config.php';
require_once 'norzagaray_bulacan_holidays_2026.php';
require_once 'holiday_actions.php';

// Check if running from web or CLI
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    // Web mode - check for confirmation
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Import Norzagaray Holidays 2026</title>
            <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Import Holidays - Norzagaray, Bulacan 2026</h4>
                    </div>
                    <div class="card-body">
                        <p>This will import all official holidays for Norzagaray, Bulacan for the year 2026.</p>
                        <p><strong>Holidays to be imported:</strong></p>
                        <ul>
                            <li>10 Regular Holidays</li>
                            <li>8 Special Non-Working Holidays</li>
                            <li>1 Special Working Holiday</li>
                            <li>2 Local Special Holidays</li>
                        </ul>
                        <p><strong>Total: 21 holidays</strong></p>
                        <p class="text-info"><strong>Source:</strong> Proclamation No. 1006 signed by President Ferdinand R. Marcos Jr. on September 3, 2025</p>
                        <p class="text-warning"><strong>Note:</strong> Existing holidays with the same date will be updated.</p>
                        <a href="?confirm=yes" class="btn btn-primary">Proceed with Import</a>
                        <a href="public_holidays.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

echo "=== Importing Holidays for Norzagaray, Bulacan - 2026 ===\n\n";

try {
    $result = importNorzagarayHolidays2026($conn);
    
    if ($result['success']) {
        echo "✓ " . $result['message'] . "\n";
        echo "  - Imported: " . $result['imported'] . " holidays\n";
        echo "  - Updated: " . $result['updated'] . " holidays\n";
        
        if (!empty($result['errors'])) {
            echo "\n⚠ Errors encountered:\n";
            foreach ($result['errors'] as $error) {
                echo "  - " . $error . "\n";
            }
        }
        
        // Show summary by type
        echo "\n" . str_repeat("-", 80) . "\n";
        echo "Summary by Type:\n";
        echo str_repeat("-", 80) . "\n";
        
        $holidays = getNorzagarayBulacanHolidays2026();
        $byType = [];
        foreach ($holidays as $holiday) {
            $type = $holiday['holiday_type'];
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }
        
        foreach ($byType as $type => $count) {
            echo sprintf("%-30s: %d\n", $type, $count);
        }
        
        echo "\n✓ Import completed successfully!\n";
        echo "\nSource: Proclamation No. 1006 (September 3, 2025)\n";
        
        if ($isWeb) {
            echo "<br><br>";
            echo "<a href='public_holidays.php' class='btn btn-primary'>View Holidays</a>";
            echo "<a href='norzagaray_bulacan_holidays_2026.php?display=1' class='btn btn-info ml-2'>View Holiday List</a>";
        }
        
    } else {
        echo "✗ Error: " . $result['message'] . "\n";
        
        if (!empty($result['errors'])) {
            echo "\nErrors:\n";
            foreach ($result['errors'] as $error) {
                echo "  - " . $error . "\n";
            }
        }
        
        if ($isWeb) {
            echo "<br><br><a href='public_holidays.php' class='btn btn-secondary'>Go Back</a>";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Fatal Error: " . $e->getMessage() . "\n";
    error_log("Import error 2026: " . $e->getMessage());
    
    if ($isWeb) {
        echo "<br><br><a href='public_holidays.php' class='btn btn-secondary'>Go Back</a>";
    }
}

echo "\n";
?>
