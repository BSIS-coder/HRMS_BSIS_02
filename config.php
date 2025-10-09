<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'hr_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('UTC');

// Database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
} catch (PDOException $e) {
    // Log error and show generic message
    error_log("Connection failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

// Application settings
define('APP_NAME', 'HR System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/HR%20System');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// Security settings
define('PASSWORD_HASH_COST', 12);
define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@hrsystem.com');
define('SMTP_FROM_NAME', 'HR System');

// Pagination settings
define('ITEMS_PER_PAGE', 10);
define('MAX_PAGE_LINKS', 5);

// Cache settings
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 hour
define('CACHE_DIR', __DIR__ . '/cache/');

// Logging settings
define('LOG_ENABLED', true);
define('LOG_DIR', __DIR__ . '/logs/');
define('LOG_LEVEL', 'ERROR'); // DEBUG, INFO, WARNING, ERROR, CRITICAL

// API settings
define('API_KEY', 'your-api-key');
define('API_RATE_LIMIT', 100); // requests per hour

// Notification settings
define('NOTIFICATION_EMAIL_ENABLED', true);
define('NOTIFICATION_SMS_ENABLED', false);
define('NOTIFICATION_PUSH_ENABLED', false);

// Feature flags
define('FEATURE_TWO_FACTOR_AUTH', true);
define('FEATURE_SOCIAL_LOGIN', false);
define('FEATURE_FILE_UPLOAD', true);
define('FEATURE_EXPORT_DATA', true);

// Custom functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

function formatCurrency($amount) {
    return number_format($amount, 2, '.', ',');
}

function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function isAllowedFileType($filename) {
    return in_array(getFileExtension($filename), ALLOWED_FILE_TYPES);
}

function generateUniqueFilename($originalFilename) {
    $extension = getFileExtension($originalFilename);
    return uniqid() . '_' . time() . '.' . $extension;
}

function createDirectoryIfNotExists($path) {
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
}

// Create necessary directories
createDirectoryIfNotExists(UPLOAD_DIR);
createDirectoryIfNotExists(CACHE_DIR);
createDirectoryIfNotExists(LOG_DIR);

// Set error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $error = date('Y-m-d H:i:s') . " Error: [$errno] $errstr - $errfile:$errline\n";
    error_log($error, 3, LOG_DIR . 'error.log');

    if (LOG_LEVEL === 'DEBUG') {
        echo "<pre>$error</pre>";
    }

    return true;
}

set_error_handler("customErrorHandler");

// Set exception handler
function customExceptionHandler($exception) {
    $error = date('Y-m-d H:i:s') . " Exception: " . $exception->getMessage() . 
             " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
    error_log($error, 3, LOG_DIR . 'exception.log');

    if (LOG_LEVEL === 'DEBUG') {
        echo "<pre>$error</pre>";
    }
}

set_exception_handler("customExceptionHandler");
?>
