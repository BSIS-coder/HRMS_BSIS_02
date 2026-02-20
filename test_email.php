<?php
require_once 'config.php';
require_once 'email_sender.php';

$emailSender = new EmailSender();

// Test email
$testEmail = 'rnldasban2@gmail.com'; // Change this to your email
$subject = 'HRMS Email Test';
$body = 'This is a test email from HRMS system.';

if ($emailSender->sendEmail($testEmail, $subject, $body)) {
    echo "✅ Email sent successfully!";
} else {
    echo "❌ Email failed to send.";
    // Check error log for details
    $errorLog = file_get_contents('php://stderr');
    if (file_exists('error.log')) {
        echo "<br>Last error: " . file_get_contents('error.log');
    }
}
?>