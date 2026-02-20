<?php
/**
 * Official Holidays for Municipality of Norzagaray, Bulacan - 2026
 * 
 * Based on:
 * - Proclamation No. 1006 (National Holidays 2026)
 * - Local holidays specific to Norzagaray, Bulacan
 * 
 * Source: Presidential Proclamation No. 1006 signed by President Ferdinand R. Marcos Jr.
 * on September 3, 2025
 */

/**
 * Get all holidays for Norzagaray, Bulacan in 2026
 * 
 * @return array Array of holidays with type classification
 */
function getNorzagarayBulacanHolidays2026() {
    return [
        // ========== REGULAR HOLIDAYS (Nationwide) ==========
        [
            'holiday_name' => 'New Year\'s Day',
            'holiday_date' => '2026-01-01',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Bagong Taon - Nationwide regular holiday'
        ],
        [
            'holiday_name' => 'Maundy Thursday',
            'holiday_date' => '2026-04-02',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Huwebes Santo - Nationwide regular holiday'
        ],
        [
            'holiday_name' => 'Good Friday',
            'holiday_date' => '2026-04-03',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Biyernes Santo - Nationwide regular holiday'
        ],
        [
            'holiday_name' => 'Araw ng Kagitingan',
            'holiday_date' => '2026-04-09',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Day of Valor - Nationwide regular holiday'
        ],
        [
            'holiday_name' => 'Labor Day',
            'holiday_date' => '2026-05-01',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Araw ng Paggawa - Nationwide regular holiday'
        ],
        [
            'holiday_name' => 'Independence Day',
            'holiday_date' => '2026-06-12',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Araw ng Kalayaan - Nationwide regular holiday'
        ],
        [
            'holiday_name' => 'National Heroes Day',
            'holiday_date' => '2026-08-31',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Araw ng mga Bayani (Last Monday of August) - Nationwide regular holiday'
        ],
        [
            'holiday_name' => 'Bonifacio Day',
            'holiday_date' => '2026-11-30',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Araw ni Bonifacio - Nationwide regular holiday'
        ],
        [
            'holiday_name' => 'Christmas Day',
            'holiday_date' => '2026-12-25',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Pasko - Nationwide regular holiday'
        ],
        [
            'holiday_name' => 'Rizal Day',
            'holiday_date' => '2026-12-30',
            'holiday_type' => 'Regular Holiday',
            'description' => 'Araw ni Rizal - Nationwide regular holiday'
        ],
        
        // ========== SPECIAL NON-WORKING HOLIDAYS (Nationwide) ==========
        [
            'holiday_name' => 'Chinese New Year',
            'holiday_date' => '2026-02-17',
            'holiday_type' => 'Special Non-Working Holiday',
            'description' => 'Chinese New Year - Nationwide special non-working holiday'
        ],
        [
            'holiday_name' => 'Black Saturday',
            'holiday_date' => '2026-04-04',
            'holiday_type' => 'Special Non-Working Holiday',
            'description' => 'Sabado de Gloria - Nationwide special non-working holiday'
        ],
        [
            'holiday_name' => 'Ninoy Aquino Day',
            'holiday_date' => '2026-08-21',
            'holiday_type' => 'Special Non-Working Holiday',
            'description' => 'Araw ng Kamatayan ni Senador Benigno Simeon "Ninoy" Aquino Jr. - Nationwide special non-working holiday'
        ],
        [
            'holiday_name' => 'All Saints\' Day',
            'holiday_date' => '2026-11-01',
            'holiday_type' => 'Special Non-Working Holiday',
            'description' => 'Undas - Nationwide special non-working holiday'
        ],
        [
            'holiday_name' => 'All Souls\' Day',
            'holiday_date' => '2026-11-02',
            'holiday_type' => 'Special Non-Working Holiday',
            'description' => 'All Souls\' Day - Nationwide special non-working holiday'
        ],
        [
            'holiday_name' => 'Feast of the Immaculate Conception of Mary',
            'holiday_date' => '2026-12-08',
            'holiday_type' => 'Special Non-Working Holiday',
            'description' => 'Feast of the Immaculate Conception - Nationwide special non-working holiday'
        ],
        [
            'holiday_name' => 'Christmas Eve',
            'holiday_date' => '2026-12-24',
            'holiday_type' => 'Special Non-Working Holiday',
            'description' => 'Bisperas ng Pasko - Nationwide special non-working holiday'
        ],
        [
            'holiday_name' => 'New Year\'s Eve',
            'holiday_date' => '2026-12-31',
            'holiday_type' => 'Special Non-Working Holiday',
            'description' => 'Last Day of the Year - Nationwide special non-working holiday'
        ],
        
        // ========== SPECIAL WORKING HOLIDAY (Nationwide) ==========
        [
            'holiday_name' => 'EDSA People Power Revolution Anniversary',
            'holiday_date' => '2026-02-25',
            'holiday_type' => 'Special Working Holiday',
            'description' => 'EDSA People Power Revolution Anniversary - Nationwide special working holiday (work as usual)'
        ],
        
        // ========== LOCAL SPECIAL HOLIDAYS (Norzagaray, Bulacan) ==========
        [
            'holiday_name' => 'Araw ng Norzagaray',
            'holiday_date' => '2026-08-13',
            'holiday_type' => 'Local Special Holiday',
            'description' => 'Norzagaray Foundation Day / Casay Festival - Local holiday in Norzagaray, Bulacan. Note: Not officially declared as special non-working holiday, but government work and classes are typically suspended.'
        ],
        [
            'holiday_name' => 'Bulacan Province Founding Anniversary',
            'holiday_date' => '2026-08-15',
            'holiday_type' => 'Local Special Holiday',
            'description' => 'Araw ng Bulacan - Bulacan Province Founding Anniversary, observed in Norzagaray and other Bulacan municipalities'
        ],
        
        // ========== ISLAMIC HOLIDAYS (To be declared) ==========
        // Note: Eidul Fitr and Eidul Adha dates will be declared separately
        // based on Islamic calendar. These are typically Special Non-Working Holidays.
    ];
}

/**
 * Get holidays by type for Norzagaray, Bulacan 2026
 * 
 * @param string $type Holiday type to filter
 * @return array Filtered holidays
 */
function getNorzagarayHolidaysByType2026($type) {
    $allHolidays = getNorzagarayBulacanHolidays2026();
    return array_filter($allHolidays, function($holiday) use ($type) {
        return $holiday['holiday_type'] === $type;
    });
}

/**
 * Import Norzagaray, Bulacan holidays into the system for 2026
 * 
 * @param PDO $conn Database connection
 * @return array Result with import statistics
 */
function importNorzagarayHolidays2026($conn) {
    require_once 'holiday_actions.php';
    
    $holidays = getNorzagarayBulacanHolidays2026();
    $importedCount = 0;
    $updatedCount = 0;
    $errors = [];
    
    foreach ($holidays as $holiday) {
        try {
            // Check if holiday already exists
            $checkStmt = $conn->prepare("SELECT holiday_id FROM public_holidays WHERE holiday_date = ?");
            $checkStmt->execute([$holiday['holiday_date']]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing holiday
                $updateStmt = $conn->prepare("UPDATE public_holidays SET 
                    holiday_name = ?, 
                    holiday_type = ?, 
                    description = ? 
                    WHERE holiday_date = ?");
                $updateStmt->execute([
                    $holiday['holiday_name'],
                    $holiday['holiday_type'],
                    $holiday['description'],
                    $holiday['holiday_date']
                ]);
                $updatedCount++;
            } else {
                // Insert new holiday
                $hasHolidayType = holidayTypeColumnExists();
                
                if ($hasHolidayType) {
                    $insertStmt = $conn->prepare("INSERT INTO public_holidays 
                        (holiday_name, holiday_date, holiday_type, description) 
                        VALUES (?, ?, ?, ?)");
                    $insertStmt->execute([
                        $holiday['holiday_name'],
                        $holiday['holiday_date'],
                        $holiday['holiday_type'],
                        $holiday['description']
                    ]);
                } else {
                    $insertStmt = $conn->prepare("INSERT INTO public_holidays 
                        (holiday_name, holiday_date, description) 
                        VALUES (?, ?, ?)");
                    $insertStmt->execute([
                        $holiday['holiday_name'],
                        $holiday['holiday_date'],
                        $holiday['description']
                    ]);
                }
                $importedCount++;
            }
        } catch (PDOException $e) {
            $errors[] = "Error processing {$holiday['holiday_name']}: " . $e->getMessage();
            error_log("Error importing Norzagaray holiday 2026: " . $e->getMessage());
        }
    }
    
    return [
        'success' => count($errors) === 0,
        'message' => "Imported {$importedCount} holidays, updated {$updatedCount} holidays for Norzagaray, Bulacan 2026",
        'imported' => $importedCount,
        'updated' => $updatedCount,
        'errors' => $errors
    ];
}

// Display function for CLI or web
if (php_sapi_name() === 'cli' || isset($_GET['display'])) {
    $holidays = getNorzagarayBulacanHolidays2026();
    
    echo "=== Holidays for Municipality of Norzagaray, Bulacan - 2026 ===\n\n";
    
    $types = [
        'Regular Holiday' => getNorzagarayHolidaysByType2026('Regular Holiday'),
        'Special Non-Working Holiday' => getNorzagarayHolidaysByType2026('Special Non-Working Holiday'),
        'Special Working Holiday' => getNorzagarayHolidaysByType2026('Special Working Holiday'),
        'Local Special Holiday' => getNorzagarayHolidaysByType2026('Local Special Holiday')
    ];
    
    foreach ($types as $typeName => $typeHolidays) {
        echo str_repeat("=", 80) . "\n";
        echo strtoupper($typeName) . " (" . count($typeHolidays) . ")\n";
        echo str_repeat("=", 80) . "\n";
        printf("%-30s %-12s %-40s\n", "Holiday Name", "Date", "Description");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($typeHolidays as $holiday) {
            printf("%-30s %-12s %-40s\n", 
                substr($holiday['holiday_name'], 0, 29),
                $holiday['holiday_date'],
                substr($holiday['description'], 0, 39)
            );
        }
        echo "\n";
    }
    
    echo "\nTotal Holidays: " . count($holidays) . "\n";
    echo "\nNote: Islamic holidays (Eidul Fitr and Eidul Adha) will be declared separately.\n";
    echo "Source: Proclamation No. 1006 signed September 3, 2025\n";
}

?>
