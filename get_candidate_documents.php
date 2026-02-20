<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    exit;
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id'])) {
    $candidate_id = $_POST['candidate_id'];
    
    $documents = [];
    $found_files = [];
    
    // Get documents from document_management table first (priority)
    $stmt = $conn->prepare("SELECT document_type, document_name, file_path FROM document_management WHERE employee_id = ? AND document_status = 'Active'");
    $stmt->bind_param('i', $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($doc = $result->fetch_assoc()) {
        if (file_exists($doc['file_path'])) {
            $type = strtolower($doc['document_type']);
            if ($type === 'contract') {
                if (strpos(strtolower($doc['document_name']), 'cover') !== false) {
                    $type = 'cover';
                    $name = 'Cover Letter';
                } else {
                    $type = 'pds';
                    $name = 'Personal Data Sheet';
                }
            } else {
                $name = ucfirst($type);
            }
            
            $documents[] = [
                'type' => $type,
                'name' => $name,
                'filename' => basename($doc['file_path']),
                'file_path' => $doc['file_path'],
                'size' => formatBytes(filesize($doc['file_path'])),
                'uploaded' => date('Y-m-d', filemtime($doc['file_path']))
            ];
            $found_files[] = $doc['file_path'];
        }
    }
    
    // Get candidate info for file paths (fallback if not in document_management)
    $stmt = $conn->prepare("SELECT resume_url, cover_letter_url FROM candidates WHERE candidate_id = ?");
    $stmt->bind_param('i', $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    
    // Add Resume if exists and not already found
    if (!empty($candidate['resume_url']) && file_exists($candidate['resume_url']) && !in_array($candidate['resume_url'], $found_files)) {
        $documents[] = [
            'type' => 'resume',
            'name' => 'Resume',
            'filename' => basename($candidate['resume_url']),
            'file_path' => $candidate['resume_url'],
            'size' => formatBytes(filesize($candidate['resume_url'])),
            'uploaded' => date('Y-m-d', filemtime($candidate['resume_url']))
        ];
    }
    
    // Add PDS if exists and not already found
    if (!empty($candidate['cover_letter_url']) && file_exists($candidate['cover_letter_url']) && !in_array($candidate['cover_letter_url'], $found_files)) {
        $documents[] = [
            'type' => 'pds',
            'name' => 'Personal Data Sheet',
            'filename' => basename($candidate['cover_letter_url']),
            'file_path' => $candidate['cover_letter_url'],
            'size' => formatBytes(filesize($candidate['cover_letter_url'])),
            'uploaded' => date('Y-m-d', filemtime($candidate['cover_letter_url']))
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($documents);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}
?>