<?php
/**
 * SECURE DOCUMENT VIEWER
 * 
 * Handles secure viewing of leave request documents.
 * Enforces authentication and authorization checks.
 * 
 * Security Features:
 * - Requires user authentication (session check)
 * - Validates file path to prevent directory traversal
 * - Only serves files from uploads/leave_documents/ directory
 * - Sets appropriate MIME types
 * - Prevents direct execution of uploaded files
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    die('Unauthorized. Please log in to view documents.');
}

// Get the file parameter
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file)) {
    http_response_code(400);
    die('No file specified.');
}

// Normalize the file path - remove any ../ or similar traversal attempts
$file = str_replace('\\', '/', $file);
$file = str_replace('..', '', $file);

// Ensure the file is in the uploads/leave_documents/ directory
$allowedDir = 'uploads/leave_documents/';
if (strpos($file, $allowedDir) !== 0) {
    $file = $allowedDir . basename($file);
}

// Check if file exists
if (!file_exists($file) || !is_file($file)) {
    http_response_code(404);
    die('Document not found.');
}

// Get file info
$fileInfo = pathinfo($file);
$fileName = $fileInfo['basename'];
$fileExtension = strtolower($fileInfo['extension']);

// Define allowed MIME types
$mimeTypes = array(
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
);

// Check if file type is allowed
if (!isset($mimeTypes[$fileExtension])) {
    http_response_code(403);
    die('File type not supported for viewing.');
}

// Set appropriate headers for viewing

header('Content-Type: ' . $mimeTypes[$fileExtension]);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=3600');

// Read and output the file
readfile($file);
exit;
?>
