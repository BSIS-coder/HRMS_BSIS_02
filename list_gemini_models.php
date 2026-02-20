<?php
// List all available Gemini models
require_once 'ai_config.php';

echo "<h2>Available Gemini Models</h2>";
echo "<pre>";

if (empty(GEMINI_API_KEY)) {
    echo "❌ ERROR: API key not configured!\n";
    exit;
}

echo "Fetching available models from Gemini API...\n\n";

// List models for v1 API
echo "=== V1 API MODELS ===\n";
$url = 'https://generativelanguage.googleapis.com/v1/models?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['models'])) {
        echo "Found " . count($result['models']) . " models:\n\n";
        foreach ($result['models'] as $model) {
            $name = str_replace('models/', '', $model['name']);
            $methods = isset($model['supportedGenerationMethods']) ? implode(', ', $model['supportedGenerationMethods']) : 'N/A';
            echo "✓ {$name}\n";
            echo "  Methods: {$methods}\n";
            if (isset($model['displayName'])) {
                echo "  Display: {$model['displayName']}\n";
            }
            echo "\n";
        }
    }
} else {
    echo "Error fetching v1 models: HTTP {$httpCode}\n";
    echo $response . "\n";
}

// List models for v1beta API
echo "\n=== V1BETA API MODELS ===\n";
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['models'])) {
        echo "Found " . count($result['models']) . " models:\n\n";
        foreach ($result['models'] as $model) {
            $name = str_replace('models/', '', $model['name']);
            $methods = isset($model['supportedGenerationMethods']) ? implode(', ', $model['supportedGenerationMethods']) : 'N/A';
            
            // Only show models that support generateContent
            if (strpos($methods, 'generateContent') !== false) {
                echo "✓ {$name}\n";
                echo "  Methods: {$methods}\n";
                if (isset($model['displayName'])) {
                    echo "  Display: {$model['displayName']}\n";
                }
                echo "\n";
            }
        }
    }
} else {
    echo "Error fetching v1beta models: HTTP {$httpCode}\n";
    echo $response . "\n";
}

echo "</pre>";
?>
