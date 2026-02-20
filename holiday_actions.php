<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'config/api_keys.php'; // Include API keys
require_once 'holiday_type_mapper.php'; // Include holiday type mapper

// Function to fetch public holidays from Calendarific API
function fetchPublicHolidaysFromCalendarific($country = DEFAULT_COUNTRY, $year = null) {
    global $conn;
    
    if ($year === null) {
        $year = date('Y');
    }
    
    $url = CALENDARIFIC_API_URL . '?api_key=' . CALENDARIFIC_API_KEY . '&country=' . $country . '&year=' . $year;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, API_REQUEST_TIMEOUT);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        error_log("Error fetching holidays from Calendarific API. HTTP Code: " . $httpCode);
        return ['success' => false, 'message' => 'Failed to fetch holidays from API'];
    }

    $data = json_decode($response, true);
    
    if (isset($data['response']['holidays']) && is_array($data['response']['holidays'])) {
        $importedCount = 0;
        $updatedCount = 0;
        
        foreach ($data['response']['holidays'] as $holiday) {
            try {
                // Check if holiday_type and source columns exist
                $hasHolidayType = holidayTypeColumnExists();
                $hasSource = sourceColumnExists();
                
                if ($hasHolidayType && $hasSource) {
                    // Map API type to Philippine official type
                    $apiType = $holiday['type'][0] ?? 'National';
                    $philippineType = mapToPhilippineHolidayType($apiType, $holiday['name']);
                    
                    $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, holiday_type, description, source) 
                                           VALUES (?, ?, ?, ?, 'calendarific') 
                                           ON DUPLICATE KEY UPDATE 
                                           holiday_name = VALUES(holiday_name), 
                                           holiday_type = VALUES(holiday_type), 
                                           description = VALUES(description)");
                    
                    $stmt->execute([
                        $holiday['name'],
                        $holiday['date']['iso'],
                        $philippineType,
                        $holiday['description'] ?? ''
                    ]);
                } elseif ($hasHolidayType) {
                    // Map API type to Philippine official type
                    $apiType = $holiday['type'][0] ?? 'National';
                    $philippineType = mapToPhilippineHolidayType($apiType, $holiday['name']);
                    
                    $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, holiday_type, description) 
                                           VALUES (?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE 
                                           holiday_name = VALUES(holiday_name), 
                                           holiday_type = VALUES(holiday_type), 
                                           description = VALUES(description)");
                    
                    $stmt->execute([
                        $holiday['name'],
                        $holiday['date']['iso'],
                        $philippineType,
                        $holiday['description'] ?? ''
                    ]);
                } elseif ($hasSource) {
                    $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, description, source) 
                                           VALUES (?, ?, ?, 'calendarific') 
                                           ON DUPLICATE KEY UPDATE 
                                           holiday_name = VALUES(holiday_name), 
                                           description = VALUES(description)");
                    
                    $stmt->execute([
                        $holiday['name'],
                        $holiday['date']['iso'],
                        $holiday['description'] ?? ''
                    ]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, description) 
                                           VALUES (?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE 
                                           holiday_name = VALUES(holiday_name), 
                                           description = VALUES(description)");
                    
                    $stmt->execute([
                        $holiday['name'],
                        $holiday['date']['iso'],
                        $holiday['description'] ?? ''
                    ]);
                }
                
                if ($stmt->rowCount() > 0) {
                    $importedCount++;
                } else {
                    $updatedCount++;
                }
            } catch (PDOException $e) {
                error_log("Error processing holiday: " . $e->getMessage());
                continue;
            }
        }
        
        return [
            'success' => true, 
            'message' => "Successfully imported {$importedCount} holidays, updated {$updatedCount} holidays from Calendarific API"
        ];
    }
    
    return ['success' => false, 'message' => 'No holidays found in API response'];
}

// Function to fetch public holidays from Nager.Date API (fallback)
function fetchPublicHolidaysFromNager($country = DEFAULT_COUNTRY, $year = null) {
    global $conn;
    
    if ($year === null) {
        $year = date('Y');
    }
    
    $url = NAGER_API_URL . '/' . $year . '/' . $country;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, API_REQUEST_TIMEOUT);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        error_log("Error fetching holidays from Nager.Date API. HTTP Code: " . $httpCode);
        return ['success' => false, 'message' => 'Failed to fetch holidays from Nager.Date API'];
    }

    $holidays = json_decode($response, true);
    
    if (is_array($holidays)) {
        $importedCount = 0;
        $updatedCount = 0;
        
        foreach ($holidays as $holiday) {
            try {
                // Check if holiday_type and source columns exist
                $hasHolidayType = holidayTypeColumnExists();
                $hasSource = sourceColumnExists();
                
                if ($hasHolidayType && $hasSource) {
                    // Nager.Date doesn't provide type, so map based on holiday name
                    $philippineType = mapToPhilippineHolidayType('National', $holiday['name']);
                    
                    $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, holiday_type, description, source) 
                                           VALUES (?, ?, ?, ?, 'nager') 
                                           ON DUPLICATE KEY UPDATE 
                                           holiday_name = VALUES(holiday_name), 
                                           holiday_type = VALUES(holiday_type),
                                           description = VALUES(description)");
                    
                    $stmt->execute([
                        $holiday['name'],
                        $holiday['date'],
                        $philippineType,
                        $holiday['localName'] ?? $holiday['name']
                    ]);
                } elseif ($hasHolidayType) {
                    // Nager.Date doesn't provide type, so map based on holiday name
                    $philippineType = mapToPhilippineHolidayType('National', $holiday['name']);
                    
                    $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, holiday_type, description) 
                                           VALUES (?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE 
                                           holiday_name = VALUES(holiday_name), 
                                           holiday_type = VALUES(holiday_type),
                                           description = VALUES(description)");
                    
                    $stmt->execute([
                        $holiday['name'],
                        $holiday['date'],
                        $philippineType,
                        $holiday['localName'] ?? $holiday['name']
                    ]);
                } elseif ($hasSource) {
                    $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, description, source) 
                                           VALUES (?, ?, ?, 'nager') 
                                           ON DUPLICATE KEY UPDATE 
                                           holiday_name = VALUES(holiday_name), 
                                           description = VALUES(description)");
                    
                    $stmt->execute([
                        $holiday['name'],
                        $holiday['date'],
                        $holiday['localName'] ?? $holiday['name']
                    ]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, description) 
                                           VALUES (?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE 
                                           holiday_name = VALUES(holiday_name), 
                                           description = VALUES(description)");
                    
                    $stmt->execute([
                        $holiday['name'],
                        $holiday['date'],
                        $holiday['localName'] ?? $holiday['name']
                    ]);
                }
                
                if ($stmt->rowCount() > 0) {
                    $importedCount++;
                } else {
                    $updatedCount++;
                }
            } catch (PDOException $e) {
                error_log("Error processing holiday: " . $e->getMessage());
                continue;
            }
        }
        
        return [
            'success' => true, 
            'message' => "Successfully imported {$importedCount} holidays, updated {$updatedCount} holidays from Nager.Date API"
        ];
    }
    
    return ['success' => false, 'message' => 'No holidays found in API response'];
}

// Function to automatically sync holidays from available APIs
function syncHolidaysFromAPI($country = DEFAULT_COUNTRY, $year = null) {
    // Try Calendarific first
    $result = fetchPublicHolidaysFromCalendarific($country, $year);
    
    if (!$result['success']) {
        // Fallback to Nager.Date
        $result = fetchPublicHolidaysFromNager($country, $year);
    }
    
    return $result;
}
function getPublicHolidays() {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM public_holidays ORDER BY holiday_date");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching public holidays: " . $e->getMessage());
        return [];
    }
}

// Function to get a single public holiday by ID
function getPublicHoliday($holiday_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM public_holidays WHERE holiday_id = ?");
        $stmt->execute([$holiday_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching public holiday: " . $e->getMessage());
        return null;
    }
}

// Function to get upcoming public holidays
function getUpcomingHolidays() {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM public_holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date LIMIT 5");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching upcoming holidays: " . $e->getMessage());
        return [];
    }
}

// Function to check if holiday_type column exists
function holidayTypeColumnExists() {
    global $conn;
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM public_holidays LIKE 'holiday_type'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error checking holiday_type column: " . $e->getMessage());
        return false;
    }
}

// Function to check if source column exists
function sourceColumnExists() {
    global $conn;
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM public_holidays LIKE 'source'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error checking source column: " . $e->getMessage());
        return false;
    }
}

// Function to add a new public holiday
function addPublicHoliday($holiday_name, $holiday_date, $holiday_type, $description) {
    global $conn;
    try {
        $hasHolidayType = holidayTypeColumnExists();
        
        if ($hasHolidayType) {
            $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, holiday_type, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$holiday_name, $holiday_date, $holiday_type, $description]);
        } else {
            $stmt = $conn->prepare("INSERT INTO public_holidays (holiday_name, holiday_date, description) VALUES (?, ?, ?)");
            $stmt->execute([$holiday_name, $holiday_date, $description]);
        }
        
        return ['success' => true, 'message' => 'Holiday added successfully'];
    } catch (PDOException $e) {
        error_log("Error adding public holiday: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error adding holiday: ' . $e->getMessage()];
    }
}

// Function to update a public holiday
function updatePublicHoliday($holiday_id, $holiday_name, $holiday_date, $holiday_type, $description) {
    global $conn;
    try {
        $hasHolidayType = holidayTypeColumnExists();
        
        if ($hasHolidayType) {
            $stmt = $conn->prepare("UPDATE public_holidays SET holiday_name = ?, holiday_date = ?, holiday_type = ?, description = ? WHERE holiday_id = ?");
            $stmt->execute([$holiday_name, $holiday_date, $holiday_type, $description, $holiday_id]);
        } else {
            $stmt = $conn->prepare("UPDATE public_holidays SET holiday_name = ?, holiday_date = ?, description = ? WHERE holiday_id = ?");
            $stmt->execute([$holiday_name, $holiday_date, $description, $holiday_id]);
        }
        
        return ['success' => true, 'message' => 'Holiday updated successfully'];
    } catch (PDOException $e) {
        error_log("Error updating public holiday: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error updating holiday: ' . $e->getMessage()];
    }
}

// Function to delete a public holiday
function deletePublicHoliday($holiday_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("DELETE FROM public_holidays WHERE holiday_id = ?");
        $stmt->execute([$holiday_id]);
        return ['success' => true, 'message' => 'Holiday deleted successfully'];
    } catch (PDOException $e) {
        error_log("Error deleting public holiday: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error deleting holiday: ' . $e->getMessage()];
    }
}

// Handle AJAX requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    switch ($action) {
        case 'add_holiday':
            if (isset($_POST['holiday_name'], $_POST['holiday_date'], $_POST['holiday_type'])) {
                $holiday_name = sanitizeInput($_POST['holiday_name']);
                $holiday_date = sanitizeInput($_POST['holiday_date']);
                $holiday_type = sanitizeInput($_POST['holiday_type']);
                $description = sanitizeInput($_POST['description'] ?? '');
                $response = addPublicHoliday($holiday_name, $holiday_date, $holiday_type, $description);
            }
            break;

        case 'update_holiday':
            if (isset($_POST['holiday_id'], $_POST['holiday_name'], $_POST['holiday_date'], $_POST['holiday_type'])) {
                $holiday_id = (int)$_POST['holiday_id'];
                $holiday_name = sanitizeInput($_POST['holiday_name']);
                $holiday_date = sanitizeInput($_POST['holiday_date']);
                $holiday_type = sanitizeInput($_POST['holiday_type']);
                $description = sanitizeInput($_POST['description'] ?? '');
                $response = updatePublicHoliday($holiday_id, $holiday_name, $holiday_date, $holiday_type, $description);
            }
            break;

        case 'delete_holiday':
            if (isset($_POST['holiday_id'])) {
                $holiday_id = (int)$_POST['holiday_id'];
                $response = deletePublicHoliday($holiday_id);
            }
            break;

        case 'get_holiday':
            if (isset($_POST['holiday_id'])) {
                $holiday_id = (int)$_POST['holiday_id'];
                $holiday = getPublicHoliday($holiday_id);
                if ($holiday) {
                    $response = ['success' => true, 'holiday' => $holiday];
                } else {
                    $response = ['success' => false, 'message' => 'Holiday not found'];
                }
            }
            break;

        case 'get_upcoming_holidays':
            $holidays = getUpcomingHolidays();
            $response = ['success' => true, 'holidays' => $holidays];
            break;

        case 'get_holidays':
            $holidays = getPublicHolidays();
            $response = ['success' => true, 'holidays' => $holidays];
            break;

        case 'sync_holidays':
            $country = $_POST['country'] ?? DEFAULT_COUNTRY;
            $year = $_POST['year'] ?? date('Y');
            $response = syncHolidaysFromAPI($country, $year);
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
