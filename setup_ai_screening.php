<?php
/**
 * Setup script for AI Screening feature
 * Verifies database structure is ready
 */

require_once 'config.php';

echo "<h2>AI Screening Setup</h2>";
echo "<p>Verifying database structure...</p>";

try {
    // Check if job_applications table has required columns
    $stmt = $conn->query("SHOW COLUMNS FROM job_applications");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasNotes = false;
    $hasAssessmentScores = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'notes') {
            $hasNotes = true;
        }
        if ($column['Field'] === 'assessment_scores') {
            $hasAssessmentScores = true;
        }
    }
    
    if ($hasNotes) {
        echo "<p style='color: green;'>✓ Column 'notes' exists in job_applications table</p>";
    } else {
        echo "<p style='color: red;'>✗ Column 'notes' is missing from job_applications table</p>";
    }
    
    if ($hasAssessmentScores) {
        echo "<p style='color: green;'>✓ Column 'assessment_scores' exists in job_applications table</p>";
    } else {
        echo "<p style='color: red;'>✗ Column 'assessment_scores' is missing from job_applications table</p>";
    }
    
    // Check job_roles table structure
    $stmt = $conn->query("SHOW COLUMNS FROM job_roles");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasTitle = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'title') {
            $hasTitle = true;
        }
    }
    
    if ($hasTitle) {
        echo "<p style='color: green;'>✓ Column 'title' exists in job_roles table</p>";
    } else {
        echo "<p style='color: red;'>✗ Column 'title' is missing from job_roles table</p>";
    }
    
    // Check AI config
    echo "<hr>";
    echo "<h3>AI Configuration</h3>";
    
    if (defined('AI_PROVIDER')) {
        echo "<p style='color: green;'>✓ AI Provider: " . AI_PROVIDER . "</p>";
        
        if (AI_PROVIDER === 'gemini') {
            if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== 'YOUR_API_KEY_HERE') {
                echo "<p style='color: green;'>✓ Gemini API Key is configured</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Gemini API Key needs to be set in ai_config.php</p>";
            }
        } elseif (AI_PROVIDER === 'openai') {
            if (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== 'YOUR_OPENAI_API_KEY_HERE') {
                echo "<p style='color: green;'>✓ OpenAI API Key is configured</p>";
            } else {
                echo "<p style='color: orange;'>⚠ OpenAI API Key needs to be set in ai_config.php</p>";
            }
        } elseif (AI_PROVIDER === 'mock') {
            echo "<p style='color: blue;'>ℹ Using Mock provider (testing mode - no API needed)</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ AI_PROVIDER not defined in ai_config.php</p>";
    }
    
    echo "<hr>";
    
    if ($hasNotes && $hasAssessmentScores && $hasTitle) {
        echo "<h3 style='color: green;'>✓ Setup Complete!</h3>";
        echo "<p>The AI Screening feature is ready to use.</p>";
        echo "<p><strong>Next Steps:</strong></p>";
        echo "<ol>";
        echo "<li>Configure your AI provider in <code>ai_config.php</code></li>";
        echo "<li>Go to <a href='job_applications.php'>Job Applications</a></li>";
        echo "<li>Click on any candidate</li>";
        echo "<li>Click 'Run AI Screening' button</li>";
        echo "</ol>";
    } else {
        echo "<h3 style='color: red;'>✗ Setup Incomplete</h3>";
        echo "<p>Some required database columns are missing. Please run the hr_system.sql schema.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>
