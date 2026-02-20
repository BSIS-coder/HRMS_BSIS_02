# Holiday Type Mapping Guide

## Overview

This document explains how to match the holiday types currently in the system with the official Philippine holiday classifications.

## Current System Types vs. Philippine Official Types

### Current System Types
- **National** - Nationwide holidays
- **Regional** - Regional/local holidays  
- **Special** - Special holidays (ambiguous)

### Official Philippine Holiday Types
1. **Regular Holiday** (Regular Holidays)
   - Nationwide holidays established by law
   - **Pay**: 100% of daily wage if not working, 200% if working
   - Examples: New Year's Day, Independence Day, Christmas Day

2. **Special Non-Working Holiday**
   - Government-declared special occasions
   - **Pay**: No work, no pay (unless company policy says otherwise), 130% if working
   - Examples: Chinese New Year, Ninoy Aquino Day, All Saints' Day

3. **Special Working Holiday**
   - Work proceeds as usual
   - **Pay**: Regular daily wage (100%)
   - Examples: EDSA People Power Revolution Anniversary

4. **Local Special Holiday**
   - Declared for specific localities
   - **Pay**: Typically follows Special Non-Working rules (130% if working)
   - Examples: Manila Day, local foundation days

## Mapping Function

The `holiday_type_mapper.php` file provides functions to convert between system types and Philippine official types:

### `mapToPhilippineHolidayType($systemType, $holidayName)`
Converts system types to Philippine official types using intelligent matching based on holiday names.

**Example:**
```php
mapToPhilippineHolidayType('National', 'New Year\'s Day')
// Returns: 'Regular Holiday'

mapToPhilippineHolidayType('Special', 'Chinese New Year')
// Returns: 'Special Non-Working Holiday'
```

### `mapFromPhilippineHolidayType($philippineType)`
Converts Philippine types back to system types (for backward compatibility).

### `getHolidayPayMultiplier($holidayType, $worked)`
Returns the pay multiplier based on Philippine holiday type and whether employee worked.

**Examples:**
- Regular Holiday (not worked): 1.0 (100%)
- Regular Holiday (worked): 2.0 (200%)
- Special Non-Working Holiday (not worked): 0.0 (0% - no work no pay)
- Special Non-Working Holiday (worked): 1.3 (130%)

## Usage

### 1. Test the Mapping
Run the test script to see how types map:
```bash
php test_holiday_mapping.php
```

### 2. Migrate Existing Holidays
To convert all existing holidays in the database to Philippine official types:
```php
require_once 'config.php';
require_once 'holiday_type_mapper.php';

$result = migrateHolidayTypesToPhilippine($conn);
if ($result['success']) {
    echo $result['message'];
}
```

### 3. Use in Holiday Import
Update `holiday_actions.php` to use the mapping when importing holidays:
```php
require_once 'holiday_type_mapper.php';

// When importing from API
$philippineType = mapToPhilippineHolidayType(
    $holiday['type'][0] ?? 'National',
    $holiday['name']
);
```

## Benefits

1. **Compliance**: Matches official Philippine Department of Labor and Employment (DOLE) classifications
2. **Payroll Accuracy**: Proper pay multipliers for different holiday types
3. **Legal Compliance**: Ensures correct holiday pay calculations per Philippine labor law
4. **Backward Compatible**: Can still work with old system types

## Next Steps

1. **Update UI**: Modify `public_holidays.php` to show Philippine official types in dropdown
2. **Update Payroll**: Use `getHolidayPayMultiplier()` in payroll calculations
3. **Migrate Data**: Run migration to convert existing holidays
4. **Update Imports**: Modify API import functions to use mapping

## Notes

- The mapping is intelligent and uses holiday names for better classification
- Regular holidays are the most common type (most "National" holidays map to this)
- Special holidays default to "Special Non-Working" (most common special type)
- Local/Regional holidays map to "Local Special Holiday"
