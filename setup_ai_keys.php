<?php
/**
 * Quick Setup for AI Keys
 * Run this once to create the ai_keys.php file
 */

$keysFile = __DIR__ . '/ai_keys.php';

if (file_exists($keysFile)) {
    echo "<h2>✓ AI Keys Already Configured</h2>";
    echo "<p>The file <code>ai_keys.php</code> already exists.</p>";
    echo "<p>To update your keys, go to: <a href='ai_keys_setup.php'>AI Configuration Page</a></p>";
    exit;
}

// Create ai_keys.php from template
$keysContent = "<?php\n";
$keysContent .= "/**\n";
$keysContent .= " * AI API Keys Configuration\n";
$keysContent .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
$keysContent .= " * IMPORTANT: This file is not tracked in git for security\n";
$keysContent .= " */\n\n";
$keysContent .= "// Google Gemini API Key (FREE tier available)\n";
$keysContent .= "// Get from: https://makersuite.google.com/app/apikey\n";
$keysContent .= "define('GEMINI_API_KEY', '');\n\n";
$keysContent .= "// OpenAI API Key (Paid)\n";
$keysContent .= "// Get from: https://platform.openai.com/api-keys\n";
$keysContent .= "define('OPENAI_API_KEY', '');\n";
$keysContent .= "?>\n";

if (file_put_contents($keysFile, $keysContent)) {
    echo "<h2>✓ Setup Complete!</h2>";
    echo "<p>Created <code>ai_keys.php</code> successfully.</p>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='ai_keys_setup.php'>AI Configuration Page</a></li>";
    echo "<li>Login as admin</li>";
    echo "<li>Enter your API keys</li>";
    echo "<li>Select AI provider</li>";
    echo "<li>Save configuration</li>";
    echo "</ol>";
    echo "<p><strong>Note:</strong> The file <code>ai_keys.php</code> is excluded from git for security.</p>";
} else {
    echo "<h2>✗ Setup Failed</h2>";
    echo "<p>Could not create <code>ai_keys.php</code>. Check file permissions.</p>";
    echo "<p>Try running: <code>chmod 755 " . __DIR__ . "</code></p>";
}
?>
