<?php
// API Configuration for Government Holiday Data
// Note: You need to register and get API keys from the respective services

// Calendarific API (Primary - https://calendarific.com/)
define('CALENDARIFIC_API_KEY', 'your_calendarific_api_key_here');
define('CALENDARIFIC_API_URL', 'https://calendarific.com/api/v2/holidays');

// Nager.Date API (Fallback - https://date.nager.at/)
define('NAGER_API_URL', 'https://date.nager.at/api/v3/PublicHolidays');

// Default country code (ISO 3166-1 alpha-2)
define('DEFAULT_COUNTRY', 'PH'); // Philippines as default

// API Rate limiting settings
define('API_REQUEST_TIMEOUT', 30); // seconds
define('MAX_RETRY_ATTEMPTS', 3);
?>
