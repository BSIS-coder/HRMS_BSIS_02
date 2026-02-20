<?php
require_once 'config.php';
require_once 'email_sender.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            throw new Exception('Email is required');
        }
        
        // Generate 6-digit verification code
        $verificationCode = sprintf('%06d', mt_rand(100000, 999999));
        
        // Store verification code in session with expiration
        session_start();
        $_SESSION['verification_codes'][$email] = [
            'code' => $verificationCode,
            'expires' => time() + 300 // 5 minutes
        ];
        
        // For testing - just return success without actually sending email
        // In production, uncomment the email sending code below
        
        /*
        // Send verification email
        $emailSender = new EmailSender();
        $subject = "Email Verification Code - Job Application";
        $message = "Your verification code is: {$verificationCode}\n\nThis code will expire in 5 minutes.\n\nIf you did not request this code, please ignore this email.";
        
        $emailSent = $emailSender->sendEmail($email, $subject, $message);
        
        if (!$emailSent) {
            throw new Exception('Failed to send verification email');
        }
        */
        
        // For testing - show code in response (remove in production)
        $response['success'] = true;
        $response['message'] = 'Verification code sent successfully';
        $response['debug_code'] = $verificationCode; // Remove this in production
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>