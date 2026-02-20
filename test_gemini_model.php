<?php
// Quick test to verify Gemini API key and model configuration
require_once 'ai_config.php';

echo "<h2>Gemini API Configuration Test</h2>";
echo "<pre>";
echo "Provider: " . AI_PROVIDER . "\n";
echo "Model: " . GEMINI_MODEL . "\n";
echo "API Version: " . GEMINI_API_VERSION . "\n";
echo "API URL: " . GEMINI_API_URL . "\n";
echo "API Key: " . (empty(GEMINI_API_KEY) ? '❌ NOT SET' : '✓ Set (' . substr(GEMINI_API_KEY, 0, 10) . '...' . substr(GEMINI_API_KEY, -4) . ')') . "\n\n";

if (empty(GEMINI_API_KEY)) {
    echo "❌ ERROR: API key not configured!\n";
    echo "Please visit ai_config_page.php to configure your API key.\n";
    exit;
}

echo "Testing API call with simple prompt...\n\n";

$testPrompt = "Say 'Hello, I am working!' in JSON format: {\"message\": \"your response\"}";

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $testPrompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 100,
    ]
];

$url = GEMINI_API_URL . '?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: " . $httpCode . "\n\n";

if ($httpCode === 200) {
    echo "✅ SUCCESS! API is working correctly.\n\n";
    echo "Response:\n";
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        echo $result['candidates'][0]['content']['parts'][0]['text'] . "\n";
    } else {
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ ERROR: API call failed!\n\n";
    echo "Response:\n";
    echo $response . "\n";
}

echo "</pre>";
?>
