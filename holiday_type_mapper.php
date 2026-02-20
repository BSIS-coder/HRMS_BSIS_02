<?php
/**
 * Holiday Type Mapper for Philippine HRMS
 * 
 * This utility maps between the system's current holiday types and
 * the official Philippine holiday classifications as defined by the
 * Department of Labor and Employment (DOLE).
 */

/**
 * Official Philippine Holiday Types:
 * 1. Regular Holiday (Regular) - 100% pay if not working, 200% if working
 * 2. Special Non-Working Holiday (Special Non-Working) - No work no pay, 130% if working
 * 3. Special Working Holiday (Special Working) - Regular pay, work as usual
 * 4. Local Special Holiday (Local) - Regional/local holidays
 */

/**
 * Map system holiday types to Philippine official types
 * 
 * @param string $systemType Current system type (National, Regional, Special)
 * @param string $holidayName Optional holiday name for better classification
 * @return string Philippine official holiday type
 */
function mapToPhilippineHolidayType($systemType, $holidayName = '') {
    $holidayNameLower = strtolower($holidayName);
    
    // Known Regular Holidays (National Holidays that are Regular)
    $regularHolidays = [
        'new year', 'new year\'s day', 'bagong taon',
        'araw ng kagitingan', 'day of valor',
        'labor day', 'araw ng paggawa',
        'independence day', 'araw ng kalayaan',
        'national heroes day', 'araw ng mga bayani',
        'bonifacio day', 'araw ni bonifacio',
        'rizal day', 'araw ni rizal',
        'christmas', 'pasko',
        'rizal day'
    ];
    
    // Known Special Non-Working Holidays
    $specialNonWorkingHolidays = [
        'chinese new year',
        'eid\'l fitr', 'feast of ramadhan', 'eid al-fitr',
        'eid\'l adha', 'feast of sacrifice', 'eid al-adha',
        'ninoy aquino day', 'ninoy aquino',
        'all saints\' day', 'undas',
        'all souls\' day',
        'last day of the year', 'new year\'s eve',
        'edsa people power revolution', 'edsa revolution',
        'maundy thursday',
        'good friday'
    ];
    
    // Known Special Working Holidays
    $specialWorkingHolidays = [
        'edsa people power revolution anniversary'
    ];
    
    // Check if holiday name matches known patterns
    foreach ($regularHolidays as $pattern) {
        if (strpos($holidayNameLower, $pattern) !== false) {
            return 'Regular Holiday';
        }
    }
    
    foreach ($specialNonWorkingHolidays as $pattern) {
        if (strpos($holidayNameLower, $pattern) !== false) {
            return 'Special Non-Working Holiday';
        }
    }
    
    foreach ($specialWorkingHolidays as $pattern) {
        if (strpos($holidayNameLower, $pattern) !== false) {
            return 'Special Working Holiday';
        }
    }
    
    // Default mapping based on system type
    switch (strtolower($systemType)) {
        case 'national':
            // Most national holidays are Regular Holidays
            return 'Regular Holiday';
            
        case 'regional':
        case 'local':
            return 'Local Special Holiday';
            
        case 'special':
            // Special is ambiguous - default to Special Non-Working
            // (most common type of "special" holidays in Philippines)
            return 'Special Non-Working Holiday';
            
        default:
            // If already in Philippine format, return as is
            if (in_array($systemType, [
                'Regular Holiday',
                'Special Non-Working Holiday',
                'Special Working Holiday',
                'Local Special Holiday'
            ])) {
                return $systemType;
            }
            return 'Regular Holiday'; // Safe default
    }
}

/**
 * Map Philippine official types back to system types (for backward compatibility)
 * 
 * @param string $philippineType Philippine official type
 * @return string System type (National, Regional, Special)
 */
function mapFromPhilippineHolidayType($philippineType) {
    switch ($philippineType) {
        case 'Regular Holiday':
            return 'National';
            
        case 'Local Special Holiday':
            return 'Regional';
            
        case 'Special Non-Working Holiday':
        case 'Special Working Holiday':
            return 'Special';
            
        default:
            return 'National'; // Default fallback
    }
}

/**
 * Get all Philippine official holiday types
 * 
 * @return array List of official holiday types
 */
function getPhilippineHolidayTypes() {
    return [
        'Regular Holiday',
        'Special Non-Working Holiday',
        'Special Working Holiday',
        'Local Special Holiday'
    ];
}

/**
 * Get holiday type description for display
 * 
 * @param string $type Holiday type
 * @return string Description with pay information
 */
function getHolidayTypeDescription($type) {
    $descriptions = [
        'Regular Holiday' => 'Regular Holiday - 100% pay if not working, 200% if working',
        'Special Non-Working Holiday' => 'Special Non-Working Holiday - No work no pay, 130% if working',
        'Special Working Holiday' => 'Special Working Holiday - Regular pay, work as usual',
        'Local Special Holiday' => 'Local Special Holiday - Regional/local holiday',
        'National' => 'National Holiday (maps to Regular Holiday)',
        'Regional' => 'Regional Holiday (maps to Local Special Holiday)',
        'Special' => 'Special Holiday (maps to Special Non-Working Holiday)'
    ];
    
    return $descriptions[$type] ?? $type;
}

/**
 * Check if a holiday type is valid (either system or Philippine format)
 * 
 * @param string $type Holiday type to validate
 * @return bool True if valid
 */
function isValidHolidayType($type) {
    $validTypes = array_merge(
        ['National', 'Regional', 'Special'],
        getPhilippineHolidayTypes()
    );
    
    return in_array($type, $validTypes);
}

/**
 * Convert all holidays in database to Philippine official types
 * This function can be used for migration
 * 
 * @param PDO $conn Database connection
 * @return array Result with count of updated records
 */
function migrateHolidayTypesToPhilippine($conn) {
    try {
        // Check if holiday_type column exists
        $stmt = $conn->query("SHOW COLUMNS FROM public_holidays LIKE 'holiday_type'");
        if ($stmt->rowCount() == 0) {
            return [
                'success' => false,
                'message' => 'holiday_type column does not exist in public_holidays table'
            ];
        }
        
        // Get all holidays
        $stmt = $conn->query("SELECT holiday_id, holiday_name, holiday_type FROM public_holidays");
        $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updatedCount = 0;
        $updateStmt = $conn->prepare("UPDATE public_holidays SET holiday_type = ? WHERE holiday_id = ?");
        
        foreach ($holidays as $holiday) {
            $currentType = $holiday['holiday_type'] ?? 'National';
            $philippineType = mapToPhilippineHolidayType($currentType, $holiday['holiday_name']);
            
            // Only update if different
            if ($currentType !== $philippineType) {
                $updateStmt->execute([$philippineType, $holiday['holiday_id']]);
                $updatedCount++;
            }
        }
        
        return [
            'success' => true,
            'message' => "Successfully migrated {$updatedCount} holiday types to Philippine official types",
            'updated_count' => $updatedCount,
            'total_count' => count($holidays)
        ];
        
    } catch (PDOException $e) {
        error_log("Error migrating holiday types: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error migrating holiday types: ' . $e->getMessage()
        ];
    }
}

/**
 * Get holiday pay multiplier based on Philippine holiday type
 * 
 * @param string $holidayType Philippine holiday type
 * @param bool $worked Whether employee worked on the holiday
 * @return float Pay multiplier (1.0 = 100%, 2.0 = 200%, etc.)
 */
function getHolidayPayMultiplier($holidayType, $worked = false) {
    switch ($holidayType) {
        case 'Regular Holiday':
            return $worked ? 2.0 : 1.0; // 200% if worked, 100% if not
        
        case 'Special Non-Working Holiday':
            return $worked ? 1.3 : 0.0; // 130% if worked, 0% if not (no work no pay)
        
        case 'Special Working Holiday':
            return 1.0; // Regular pay
        
        case 'Local Special Holiday':
            // Local holidays typically follow Special Non-Working rules
            return $worked ? 1.3 : 0.0;
        
        default:
            // For backward compatibility with old system types
            if ($holidayType === 'National') {
                return $worked ? 2.0 : 1.0;
            }
            return 1.0; // Default to regular pay
    }
}

?>