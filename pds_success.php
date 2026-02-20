<?php
// pds_success.php - Success page after PDS submission
$candidate_id = $_GET['candidate_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted Successfully</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .success-container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }
        .success-icon {
            font-size: 60px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
        .candidate-id {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h1>Application Submitted Successfully!</h1>
        <p>Thank you for submitting your Personal Data Sheet. Your application has been received and saved in our system.</p>
        
        <?php if ($candidate_id): ?>
        <div class="candidate-id">
            Your Candidate ID: <?php echo htmlspecialchars($candidate_id); ?>
        </div>
        <p>Please keep this ID for your reference.</p>
        <?php endif; ?>
        
        <p>Our HR team will review your application and contact you if your qualifications match our requirements.</p>
        
        <a href="candidates.php" class="btn">View All Candidates</a>
    </div>
</body>
</html>
