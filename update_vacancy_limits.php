<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if user has HR or Admin role
if (!in_array($_SESSION['role'], ['hr', 'admin'])) {
    echo json_encode(['error' => 'Only HR and Admin can manage vacancy limits']);
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $limits = json_decode($_POST['limits'] ?? '[]', true);
    
    if (!is_array($limits)) {
        echo json_encode(['error' => 'Invalid data format']);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        foreach ($limits as $limit) {
            $deptId = $limit['department_id'] ?? null;
            $vacancyLimit = $limit['vacancy_limit'];
            
            if (!$deptId) continue;
            
            $stmt = $conn->prepare("UPDATE departments SET vacancy_limit = ? WHERE department_id = ?");
            $stmt->execute([$vacancyLimit, $deptId]);
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Vacancy limits updated successfully'
        ]);
        
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
