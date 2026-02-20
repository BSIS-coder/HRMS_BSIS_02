<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');
        
        if (empty($email) || empty($code)) {
            throw new Exception('Email and code are required');
        }
        
        session_start();
        
        // Check if verification code exists and is valid
        if (!isset($_SESSION['verification_codes'][$email])) {
            throw new Exception('No verification code found for this email');
        }
        
        $storedData = $_SESSION['verification_codes'][$email];
        
        // Check if code has expired
        if (time() > $storedData['expires']) {
            unset($_SESSION['verification_codes'][$email]);
            throw new Exception('Verification code has expired');
        }
        
        // Check if code matches
        if ($code !== $storedData['code']) {
            throw new Exception('Invalid verification code');
        }
        
        // Code is valid - mark email as verified
        $_SESSION['verified_emails'][$email] = true;
        unset($_SESSION['verification_codes'][$email]);
        
        $response['success'] = true;
        $response['message'] = 'Email verified successfully';
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>