<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['exists' => false, 'message' => '', 'data' => null];
    
    try {
        $type = $_POST['type'] ?? '';
        
        if ($type === 'name') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            
            if ($firstName && $lastName) {
                $fullName = $firstName . ' ' . $lastName;
                $stmt = $conn->prepare("SELECT candidate_id, email, phone FROM candidates WHERE CONCAT(first_name, ' ', last_name) = ?");
                $stmt->execute([$fullName]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $response['exists'] = true;
                    $response['message'] = "A candidate with this name already exists";
                    $response['data'] = [
                        'email' => $existing['email'],
                        'phone' => $existing['phone']
                    ];
                }
            }
        } elseif ($type === 'email') {
            $email = trim($_POST['email'] ?? '');
            
            if ($email) {
                $stmt = $conn->prepare("SELECT candidate_id, first_name, last_name, phone FROM candidates WHERE email = ?");
                $stmt->execute([$email]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $response['exists'] = true;
                    $response['message'] = "This email is already registered";
                    $response['data'] = [
                        'name' => trim($existing['first_name'] . ' ' . $existing['last_name']),
                        'phone' => $existing['phone']
                    ];
                }
            }
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Error checking duplicate';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>