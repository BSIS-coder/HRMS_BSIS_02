<?php
// Test which models still have quota available
require_once 'ai_config.php';

echo "<h2>Finding Available Models</h2>";
echo "<pre>";

if (empty(GEMINI_API_KEY)) {
    echo "❌ ERROR: API key not configured!\n";
    exit;
}

$testPrompt = "Say hello";
$modelsToTest = [
    'gemini-2.0-flash-lite',
    'gemini-2.0-flash',
    'gemini-2.5-flash-lite',
    'gemini-2.5-flash',
    'gemini-2.5-pro',
    'gemma-3-1b-it',
    'gemma-3-4b-it',
    'gemini-flash-latest',
];

echo "Testing models to find one with available quota...\n\n";

foreach ($modelsToTest as $model) {
    echo "Testing: {$model}... ";
    
    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . GEMINI_API_KEY;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $testPrompt]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 50,
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ WORKS! This model has quota available.\n";
        echo "   Use this model: {$model}\n\n";
        break;
    } elseif ($httpCode === 429) {
        echo "❌ Quota exceeded\n";
    } elseif ($httpCode === 404) {
        echo "⚠️ Not found in v1 API\n";
    } else {
        echo "❌ Error: HTTP {$httpCode}\n";
    }
}

echo "\n=== RECOMMENDATION ===\n";
echo "If all models show quota exceeded, you need to:\n";
echo "1. Wait until tomorrow (quota resets daily)\n";
echo "2. OR create a NEW API key at: https://makersuite.google.com/app/apikey\n";
echo "3. OR use 'mock' provider for testing (no API needed)\n";

echo "</pre>";
?>
