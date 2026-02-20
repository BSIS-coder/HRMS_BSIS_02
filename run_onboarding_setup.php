<!DOCTYPE html>
<html>
<head>
    <title>Onboarding Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <h2>🎯 Onboarding System Setup</h2>
    
    <?php
    require_once 'config.php';
    
    try {
        // Create onboarding_progress table if it doesn't exist
        $conn->exec("CREATE TABLE IF NOT EXISTS onboarding_progress (
            progress_id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            task_id INT NOT NULL,
            status ENUM('Pending', 'Completed') DEFAULT 'Pending',
            completed_date DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES job_applications(application_id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES onboarding_tasks(task_id) ON DELETE CASCADE
        )");
        
        echo "<p class='info'>✅ Created onboarding_progress table</p>";

        // Clear existing onboarding tasks
        $conn->exec("DELETE FROM onboarding_tasks");
        
        // Insert sample onboarding tasks
        $tasks = [
            ['Complete Employment Forms', 'Fill out all required employment documentation including tax forms, emergency contacts, and personal information'],
            ['IT Setup & Equipment', 'Set up computer account, email, and receive necessary equipment (laptop, phone, ID card)'],
            ['Office Tour & Orientation', 'Tour of facilities, introduction to team members, and overview of office policies'],
            ['HR Policy Briefing', 'Review employee handbook, code of conduct, and company policies'],
            ['Department Introduction', 'Meet with department head and team members, understand role responsibilities'],
            ['Training Schedule Setup', 'Arrange required training sessions and professional development programs'],
            ['Benefits Enrollment', 'Complete health insurance, retirement plan, and other benefit enrollments'],
            ['Security Clearance', 'Complete background verification and security access setup'],
            ['Workspace Assignment', 'Assign desk/office space and provide necessary supplies'],
            ['First Week Check-in', 'Meet with supervisor to discuss progress and address any questions']
        ];

        $stmt = $conn->prepare("INSERT INTO onboarding_tasks (task_name, description) VALUES (?, ?)");
        foreach ($tasks as $task) {
            $stmt->execute([$task[0], $task[1]]);
        }
        
        echo "<p class='success'>✅ Added " . count($tasks) . " onboarding tasks</p>";

        // Update some candidates to 'Reference Check' status for onboarding
        $conn->exec("UPDATE job_applications SET status = 'Reference Check' WHERE application_id IN (1, 2)");
        
        echo "<p class='success'>✅ Updated existing candidates to Reference Check status</p>";

        // Add more sample candidates in Reference Check status
        $conn->exec("INSERT IGNORE INTO candidates (first_name, last_name, email, phone, address, source, current_position, expected_salary, resume_filename, photo_filename, email_verified) VALUES
            ('John', 'Martinez', 'john.martinez@email.com', '0917-777-8888', '321 Elm St, City', 'Job Portal', 'Administrative Officer', 28000.00, 'john_martinez_resume.pdf', 'john_martinez_photo.jpg', 1),
            ('Maria', 'Fernandez', 'maria.fernandez@email.com', '0917-999-0000', '654 Maple Ave, City', 'Referral', 'Municipal Clerk', 26000.00, 'maria_fernandez_resume.pdf', 'maria_fernandez_photo.jpg', 1)");

        // Get the new candidate IDs
        $john_id = $conn->query("SELECT candidate_id FROM candidates WHERE email = 'john.martinez@email.com'")->fetchColumn();
        $maria_id = $conn->query("SELECT candidate_id FROM candidates WHERE email = 'maria.fernandez@email.com'")->fetchColumn();

        if ($john_id && $maria_id) {
            // Add job applications for new candidates
            $conn->exec("INSERT IGNORE INTO job_applications (job_opening_id, candidate_id, application_date, status) VALUES
                (2, $john_id, NOW(), 'Reference Check'),
                (1, $maria_id, NOW(), 'Reference Check')");
            
            echo "<p class='success'>✅ Added new candidates with job applications</p>";
        }

        // Clear existing progress and recreate
        $conn->exec("DELETE FROM onboarding_progress");
        
        // Create onboarding progress for candidates in Reference Check status
        $conn->exec("INSERT INTO onboarding_progress (application_id, task_id, status)
                     SELECT ja.application_id, ot.task_id, 'Pending'
                     FROM job_applications ja
                     CROSS JOIN onboarding_tasks ot
                     WHERE ja.status = 'Reference Check'");

        $progress_count = $conn->query("SELECT COUNT(*) FROM onboarding_progress")->fetchColumn();
        echo "<p class='success'>✅ Created $progress_count onboarding progress records</p>";

        // Show summary
        $candidates_count = $conn->query("SELECT COUNT(*) FROM job_applications WHERE status = 'Reference Check'")->fetchColumn();
        $tasks_count = $conn->query("SELECT COUNT(*) FROM onboarding_tasks")->fetchColumn();
        
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "<h3>🎉 Setup Complete!</h3>";
        echo "<p><strong>📋 Tasks:</strong> $tasks_count onboarding tasks created</p>";
        echo "<p><strong>👥 Candidates:</strong> $candidates_count candidates in onboarding process</p>";
        echo "<p><strong>📊 Progress Records:</strong> $progress_count task assignments created</p>";
        echo "<p><a href='onboarding.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Go to Onboarding System</a></p>";
        echo "</div>";

    } catch (Exception $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    }
    ?>
</body>
</html>