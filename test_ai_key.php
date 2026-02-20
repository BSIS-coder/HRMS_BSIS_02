<?php
require_once 'ai_config.php';

echo "<h2>AI Configuration Test</h2>";

echo "<h3>Provider:</h3>";
echo "<p>" . AI_PROVIDER . "</p>";

echo "<h3>Gemini API Key:</h3>";
if (defined('GEMINI_API_KEY')) {
    if (!empty(GEMINI_API_KEY)) {
        echo "<p style='color: green;'>✓ Configured: " . substr(GEMINI_API_KEY, 0, 10) . "..." . substr(GEMINI_API_KEY, -4) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Empty</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Not defined</p>";
}

echo "<h3>OpenAI API Key:</h3>";
if (defined('OPENAI_API_KEY')) {
    if (!empty(OPENAI_API_KEY)) {
        echo "<p style='color: green;'>✓ Configured: " . substr(OPENAI_API_KEY, 0, 7) . "..." . substr(OPENAI_API_KEY, -4) . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Empty (OK if not using OpenAI)</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Not defined</p>";
}

echo "<h3>API Keys File:</h3>";
$keysFile = __DIR__ . '/ai_keys.php';
if (file_exists($keysFile)) {
    echo "<p style='color: green;'>✓ Exists: ai_keys.php</p>";
} else {
    echo "<p style='color: red;'>✗ Not found: ai_keys.php</p>";
}

echo "<h3>Gemini API URL:</h3>";
if (defined('GEMINI_API_URL')) {
    echo "<p>" . GEMINI_API_URL . "</p>";
} else {
    echo "<p style='color: red;'>✗ Not defined</p>";
}

echo "<hr>";
echo "<p><a href='ai_keys_setup.php'>Configure API Keys</a></p>";
?>
