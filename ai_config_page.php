<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Only admin can configure AI
if ($_SESSION['role'] !== 'admin') {
    die('Access denied. Only administrators can configure AI settings.');
}

$config_file = 'ai_config.php';
$success_message = '';
$error_message = '';

// Read current configuration
$current_config = [
    'provider' => 'mock',
    'gemini_key' => '',
    'gemini_model' => 'gemini-2.5-flash-lite',
    'gemini_api_version' => 'v1',
    'openai_key' => '',
    'openai_model' => 'gpt-3.5-turbo',
    'screening_provider' => 'gemini',
    'screening_gemini_key' => '',
    'screening_gemini_model' => 'gemini-2.5-flash-lite',
    'screening_gemini_api_version' => 'v1',
    'screening_openai_key' => '',
    'screening_openai_model' => 'gpt-3.5-turbo'
];

// Load AI config to get provider
require_once 'ai_config.php';
$current_config['provider'] = AI_PROVIDER;
$current_config['screening_provider'] = SCREENING_AI_PROVIDER;

// Load API keys from ai_keys.php
$keysFile = __DIR__ . '/ai_keys.php';
if (file_exists($keysFile)) {
    require_once $keysFile;
    if (defined('GEMINI_API_KEY')) {
        $current_config['gemini_key'] = GEMINI_API_KEY;
    }
    if (defined('OPENAI_API_KEY')) {
        $current_config['openai_key'] = OPENAI_API_KEY;
    }
    if (defined('SCREENING_GEMINI_API_KEY')) {
        $current_config['screening_gemini_key'] = SCREENING_GEMINI_API_KEY;
    }
    if (defined('SCREENING_OPENAI_API_KEY')) {
        $current_config['screening_openai_key'] = SCREENING_OPENAI_API_KEY;
    }
}

// Load model settings from ai_config.php
if (defined('GEMINI_MODEL')) {
    $current_config['gemini_model'] = GEMINI_MODEL;
}
if (defined('GEMINI_API_VERSION')) {
    $current_config['gemini_api_version'] = GEMINI_API_VERSION;
}
if (defined('OPENAI_MODEL')) {
    $current_config['openai_model'] = OPENAI_MODEL;
}
if (defined('SCREENING_GEMINI_MODEL')) {
    $current_config['screening_gemini_model'] = SCREENING_GEMINI_MODEL;
}
if (defined('SCREENING_GEMINI_API_VERSION')) {
    $current_config['screening_gemini_api_version'] = SCREENING_GEMINI_API_VERSION;
}
if (defined('SCREENING_OPENAI_MODEL')) {
    $current_config['screening_openai_model'] = SCREENING_OPENAI_MODEL;
}

// Determine which section is being updated
$update_section = $_POST['update_section'] ?? null;

// Connect to database for API usage tracking
require_once 'db_connect.php';

/**
 * Initialize API usage tracking table if it doesn't exist
 */
function initializeAPITrackingTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS api_usage_tracking (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(50) NOT NULL,
        api_type VARCHAR(50) NOT NULL,
        request_date DATE NOT NULL,
        request_count INT DEFAULT 1,
        status VARCHAR(50) DEFAULT 'success',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_tracking (provider, api_type, request_date)
    )";
    
    try {
        @$conn->query($sql);
    } catch (Exception $e) {
        // Table might already exist, ignore
    }
}

/**
 * Get today's API usage statistics
 */
function getAPIUsageStats($conn, $provider, $apiType) {
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare("SELECT request_count FROM api_usage_tracking 
                           WHERE provider = ? AND api_type = ? AND request_date = ?");
    if ($stmt) {
        $stmt->bind_param('sss', $provider, $apiType, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['request_count'] : 0;
    }
    return 0;
}

/**
 * Get quota limits based on provider
 */
function getQuotaLimits($provider) {
    $quotas = [
        'gemini' => [
            'daily_limit' => 1500,
            'per_minute' => 60,
            'name' => 'Google Gemini (Free)'
        ],
        'openai' => [
            'daily_limit' => 500, // Default estimate for paid plans
            'per_minute' => 60,
            'name' => 'OpenAI (Paid)'
        ],
        'mock' => [
            'daily_limit' => 99999,
            'per_minute' => 99999,
            'name' => 'Mock AI (Unlimited)'
        ]
    ];
    
    return $quotas[$provider] ?? $quotas['mock'];
}

/**
 * Calculate API usage statistics
 */
function calculateUsageStats($conn, $provider, $apiType) {
    initializeAPITrackingTable($conn);
    
    $quotas = getQuotaLimits($provider);
    $requested = getAPIUsageStats($conn, $provider, $apiType);
    $remaining = max(0, $quotas['daily_limit'] - $requested);
    $usagePercent = $quotas['daily_limit'] > 0 ? round(($requested / $quotas['daily_limit']) * 100, 1) : 0;
    
    return [
        'requested' => $requested,
        'remaining' => $remaining,
        'limit' => $quotas['daily_limit'],
        'percent' => $usagePercent,
        'provider_name' => $quotas['name'],
        'reset_time' => 'Tomorrow at 00:00 UTC'
    ];
}

// Get usage stats for all APIs
$job_gen_stats = calculateUsageStats($conn, $current_config['provider'], 'job_generation');
$screening_stats = calculateUsageStats($conn, $current_config['screening_provider'], 'screening');
$extractor_stats = calculateUsageStats($conn, $current_config['screening_provider'], 'pds_extraction');

/**
 * Check Gemini API quota and availability
 */
function checkGeminiAPIStatus($apiKey, $model = 'gemini-2.5-flash-lite', $apiVersion = 'v1') {
    if (empty($apiKey)) {
        return ['status' => 'unconfigured', 'message' => 'API key not set'];
    }
    
    $url = "https://generativelanguage.googleapis.com/{$apiVersion}/models/{$model}:generateContent?key={$apiKey}";
    
    // Prepare a minimal test request
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'test']
                ]
            ]
        ],
        'generationConfig' => ['maxOutputTokens' => 10]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerInfo = curl_getinfo($ch);
    curl_close($ch);
    
    // Parse response
    $result = json_decode($response, true);
    
    // Check if successful
    if ($httpCode === 200 && isset($result['candidates'])) {
        return [
            'status' => 'available',
            'message' => 'API is working and quota available',
            'icon' => 'fas fa-check-circle',
            'badge' => 'success'
        ];
    }
    
    // Check for quota exceeded
    if ($httpCode === 429) {
        $errorMsg = $result['error']['message'] ?? 'Rate limit exceeded';
        return [
            'status' => 'quota_exceeded',
            'message' => 'Request quota exceeded - ' . $errorMsg,
            'icon' => 'fas fa-exclamation-circle',
            'badge' => 'danger'
        ];
    }
    
    // Check for invalid key
    if ($httpCode === 403 && isset($result['error'])) {
        return [
            'status' => 'invalid_key',
            'message' => 'Invalid or expired API key',
            'icon' => 'fas fa-times-circle',
            'badge' => 'danger'
        ];
    }
    
    // Generic error
    if ($httpCode !== 200) {
        $errorMsg = $result['error']['message'] ?? 'Unknown error';
        return [
            'status' => 'error',
            'message' => 'Error: ' . $errorMsg,
            'icon' => 'fas fa-question-circle',
            'badge' => 'warning'
        ];
    }
    
    return [
        'status' => 'unknown',
        'message' => 'Unable to determine API status',
        'icon' => 'fas fa-question-circle',
        'badge' => 'secondary'
    ];
}

/**
 * Check OpenAI API quota and availability
 */
function checkOpenAIAPIStatus($apiKey) {
    if (empty($apiKey)) {
        return ['status' => 'unconfigured', 'message' => 'API key not set'];
    }
    
    $url = "https://api.openai.com/v1/models";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 200 && isset($result['data'])) {
        return [
            'status' => 'available',
            'message' => 'API is working and quota available',
            'icon' => 'fas fa-check-circle',
            'badge' => 'success'
        ];
    }
    
    if ($httpCode === 429) {
        return [
            'status' => 'quota_exceeded',
            'message' => 'Request quota exceeded',
            'icon' => 'fas fa-exclamation-circle',
            'badge' => 'danger'
        ];
    }
    
    if ($httpCode === 401) {
        return [
            'status' => 'invalid_key',
            'message' => 'Invalid or expired API key',
            'icon' => 'fas fa-times-circle',
            'badge' => 'danger'
        ];
    }
    
    $errorMsg = $result['error']['message'] ?? 'Unknown error';
    return [
        'status' => 'error',
        'message' => 'Error: ' . $errorMsg,
        'icon' => 'fas fa-question-circle',
        'badge' => 'warning'
    ];
}

// Check API statuses
$job_gen_status = null;
$screening_status = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_api'])) {
    if ($_GET['check_api'] === 'job_gen') {
        if ($current_config['provider'] === 'gemini') {
            $job_gen_status = checkGeminiAPIStatus($current_config['gemini_key'], $current_config['gemini_model'], $current_config['gemini_api_version']);
        } elseif ($current_config['provider'] === 'openai') {
            $job_gen_status = checkOpenAIAPIStatus($current_config['openai_key']);
        }
    } elseif ($_GET['check_api'] === 'screening') {
        if ($current_config['screening_provider'] === 'gemini') {
            $screening_status = checkGeminiAPIStatus($current_config['screening_gemini_key'], $current_config['screening_gemini_model'], $current_config['screening_gemini_api_version']);
        } elseif ($current_config['screening_provider'] === 'openai') {
            $screening_status = checkOpenAIAPIStatus($current_config['screening_openai_key']);
        }
    }
}

// Handle Job Generation Configuration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $update_section === 'job_generation') {
    $provider = $_POST['provider'] ?? 'gemini';
    $gemini_key = $_POST['gemini_key'] ?? '';
    $gemini_model = $_POST['gemini_model'] ?? 'gemini-2.5-flash-lite';
    $gemini_api_version = $_POST['gemini_api_version'] ?? 'v1';
    $openai_key = $_POST['openai_key'] ?? '';
    $openai_model = $_POST['openai_model'] ?? 'gpt-3.5-turbo';
    
    // Validate
    if ($provider === 'gemini' && empty($gemini_key)) {
        $error_message = 'Please provide a Gemini API key for job generation';
    } elseif ($provider === 'openai' && empty($openai_key)) {
        $error_message = 'Please provide an OpenAI API key for job generation';
    } else {
        // Ensure we don't overwrite existing keys with empty values (e.g. when switching to Mock)
        $gemini_key_to_save = $gemini_key !== '' ? $gemini_key : $current_config['gemini_key'];
        $openai_key_to_save = $openai_key !== '' ? $openai_key : $current_config['openai_key'];

        // Save API keys to ai_keys.php (preserve existing screening keys)
        $keysContent = "<?php\n";
        $keysContent .= "/**\n";
        $keysContent .= " * AI API Keys Configuration\n";
        $keysContent .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $keysContent .= " * IMPORTANT: This file is not tracked in git for security\n";
        $keysContent .= " */\n\n";
        $keysContent .= "// Job Generation API Keys\n";
        $keysContent .= "define('GEMINI_API_KEY', '" . addslashes($gemini_key_to_save) . "');\n";
        $keysContent .= "define('OPENAI_API_KEY', '" . addslashes($openai_key_to_save) . "');\n\n";
        $keysContent .= "// AI Screening API Keys\n";
        $keysContent .= "define('SCREENING_GEMINI_API_KEY', '" . addslashes($current_config['screening_gemini_key']) . "');\n";
        $keysContent .= "define('SCREENING_OPENAI_API_KEY', '" . addslashes($current_config['screening_openai_key']) . "');\n";
        $keysContent .= "?>\n";
        
        if (!file_put_contents($keysFile, $keysContent)) {
            $error_message = '❌ Failed to save API keys. Check file permissions.';
        } else {
            // Update provider and model settings in ai_config.php
            $template_content = file_get_contents($config_file);
            
            // Update Job Generation Configuration only
            $template_content = preg_replace(
                "/define\('AI_PROVIDER',\s*'[^']+'\);/",
                "define('AI_PROVIDER', '{$provider}');",
                $template_content
            );
            $template_content = preg_replace(
                "/define\('GEMINI_MODEL',\s*'[^']+'\);/",
                "define('GEMINI_MODEL', '{$gemini_model}');",
                $template_content
            );
            $template_content = preg_replace(
                "/define\('GEMINI_API_VERSION',\s*'[^']+'\);/",
                "define('GEMINI_API_VERSION', '{$gemini_api_version}');",
                $template_content
            );
            $template_content = preg_replace(
                "/define\('OPENAI_MODEL',\s*'[^']+'\);/",
                "define('OPENAI_MODEL', '{$openai_model}');",
                $template_content
            );
            
            if (file_put_contents($config_file, $template_content)) {
                $success_message = '✅ Job Generation configuration saved!';
                $current_config['provider'] = $provider;
                $current_config['gemini_key'] = $gemini_key_to_save;
                $current_config['gemini_model'] = $gemini_model;
                $current_config['gemini_api_version'] = $gemini_api_version;
                $current_config['openai_key'] = $openai_key_to_save;
                $current_config['openai_model'] = $openai_model;
            } else {
                $error_message = '❌ Failed to save configuration file. Check file permissions.';
            }
        }
    }
}

// Handle AI Screening Configuration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $update_section === 'screening') {
    $screening_provider = $_POST['screening_provider'] ?? 'gemini';
    $screening_gemini_key = $_POST['screening_gemini_key'] ?? '';
    $screening_gemini_model = $_POST['screening_gemini_model'] ?? 'gemini-2.5-flash-lite';
    $screening_gemini_api_version = $_POST['screening_gemini_api_version'] ?? 'v1';
    $screening_openai_key = $_POST['screening_openai_key'] ?? '';
    $screening_openai_model = $_POST['screening_openai_model'] ?? 'gpt-3.5-turbo';
    
    // Validate
    if ($screening_provider === 'gemini' && empty($screening_gemini_key)) {
        $error_message = 'Please provide a Gemini API key for screening';
    } elseif ($screening_provider === 'openai' && empty($screening_openai_key)) {
        $error_message = 'Please provide an OpenAI API key for screening';
    } else {
        // Ensure we don't overwrite existing screening keys with empty values
        $screening_gemini_key_to_save = $screening_gemini_key !== '' ? $screening_gemini_key : $current_config['screening_gemini_key'];
        $screening_openai_key_to_save = $screening_openai_key !== '' ? $screening_openai_key : $current_config['screening_openai_key'];

        // Save API keys to ai_keys.php (preserve existing job generation keys)
        $keysContent = "<?php\n";
        $keysContent .= "/**\n";
        $keysContent .= " * AI API Keys Configuration\n";
        $keysContent .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $keysContent .= " * IMPORTANT: This file is not tracked in git for security\n";
        $keysContent .= " */\n\n";
        $keysContent .= "// Job Generation API Keys\n";
        $keysContent .= "define('GEMINI_API_KEY', '" . addslashes($current_config['gemini_key']) . "');\n";
        $keysContent .= "define('OPENAI_API_KEY', '" . addslashes($current_config['openai_key']) . "');\n\n";
        $keysContent .= "// AI Screening API Keys\n";
        $keysContent .= "define('SCREENING_GEMINI_API_KEY', '" . addslashes($screening_gemini_key_to_save) . "');\n";
        $keysContent .= "define('SCREENING_OPENAI_API_KEY', '" . addslashes($screening_openai_key_to_save) . "');\n";
        $keysContent .= "?>\n";
        
        if (!file_put_contents($keysFile, $keysContent)) {
            $error_message = '❌ Failed to save API keys. Check file permissions.';
        } else {
            // Update provider and model settings in ai_config.php
            $template_content = file_get_contents($config_file);
            
            // Update Screening Configuration only
            $template_content = preg_replace(
                "/define\('SCREENING_AI_PROVIDER',\s*'[^']+'\);/",
                "define('SCREENING_AI_PROVIDER', '{$screening_provider}');",
                $template_content
            );
            $template_content = preg_replace(
                "/define\('SCREENING_GEMINI_MODEL',\s*'[^']+'\);/",
                "define('SCREENING_GEMINI_MODEL', '{$screening_gemini_model}');",
                $template_content
            );
            $template_content = preg_replace(
                "/define\('SCREENING_GEMINI_API_VERSION',\s*'[^']+'\);/",
                "define('SCREENING_GEMINI_API_VERSION', '{$screening_gemini_api_version}');",
                $template_content
            );
            $template_content = preg_replace(
                "/define\('SCREENING_OPENAI_MODEL',\s*'[^']+'\);/",
                "define('SCREENING_OPENAI_MODEL', '{$screening_openai_model}');",
                $template_content
            );
            
            if (file_put_contents($config_file, $template_content)) {
                $success_message = '✅ AI Screening configuration saved!';
                $current_config['screening_provider'] = $screening_provider;
                $current_config['screening_gemini_key'] = $screening_gemini_key_to_save;
                $current_config['screening_gemini_model'] = $screening_gemini_model;
                $current_config['screening_gemini_api_version'] = $screening_gemini_api_version;
                $current_config['screening_openai_key'] = $screening_openai_key_to_save;
                $current_config['screening_openai_model'] = $screening_openai_model;
            } else {
                $error_message = '❌ Failed to save configuration file. Check file permissions.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Configuration - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .toast-container { position: fixed; top: 80px; right: 20px; z-index: 9999; }
        .custom-toast { min-width: 300px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-bottom: 10px; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .toast-success { border-left: 4px solid #28a745; }
        .toast-error { border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2><i class="fas fa-robot mr-2"></i>AI Configuration</h2>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_message; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Job Generation Configuration -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <h5 class="mb-0"><i class="fas fa-briefcase mr-2"></i>Job Generation AI</h5>
                                <small>Configure AI for creating job descriptions</small>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="jobGenerationForm">
                                    <input type="hidden" name="update_section" value="job_generation">
                                    
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-server mr-1"></i>AI Provider</label>
                                        <select name="provider" id="provider" class="form-control form-control-lg" required>
                                            <option value="mock" <?php echo $current_config['provider'] === 'mock' ? 'selected' : ''; ?>>Mock AI (No API Key - For Testing)</option>
                                            <option value="gemini" <?php echo $current_config['provider'] === 'gemini' ? 'selected' : ''; ?>>Google Gemini (FREE)</option>
                                            <option value="openai" <?php echo $current_config['provider'] === 'openai' ? 'selected' : ''; ?>>OpenAI (Paid - Better Quality)</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Mock AI Configuration -->
                                    <div id="mock_config" style="display: none;">
                                        <div class="alert alert-success">
                                            <h6><i class="fas fa-rocket mr-2"></i>Mock AI (Demo Mode)</h6>
                                            <ul class="mb-0">
                                                <li>✅ No API key required</li>
                                                <li>✅ Works instantly</li>
                                                <li>✅ Perfect for testing</li>
                                                <li>✅ Generates realistic job descriptions</li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Gemini Configuration -->
                                    <div id="gemini_config" style="display: none;">
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-key mr-1"></i>Gemini API Key</label>
                                            <input type="text" name="gemini_key" class="form-control" value="<?php echo htmlspecialchars($current_config['gemini_key']); ?>" placeholder="AIzaSy...">
                                            <small class="text-muted">API key for job generation</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-brain mr-1"></i>Model</label>
                                            <select name="gemini_model" id="gemini_model" class="form-control">
                                                <option value="gemini-2.5-flash-lite" <?php echo $current_config['gemini_model'] === 'gemini-2.5-flash-lite' ? 'selected' : ''; ?>>gemini-2.5-flash-lite (Recommended)</option>
                                                <option value="gemini-2.0-flash-lite" <?php echo $current_config['gemini_model'] === 'gemini-2.0-flash-lite' ? 'selected' : ''; ?>>gemini-2.0-flash-lite</option>
                                                <option value="gemini-2.5-flash" <?php echo $current_config['gemini_model'] === 'gemini-2.5-flash' ? 'selected' : ''; ?>>gemini-2.5-flash</option>
                                                <option value="gemini-2.0-flash" <?php echo $current_config['gemini_model'] === 'gemini-2.0-flash' ? 'selected' : ''; ?>>gemini-2.0-flash</option>
                                                <option value="gemini-2.5-pro" <?php echo $current_config['gemini_model'] === 'gemini-2.5-pro' ? 'selected' : ''; ?>>gemini-2.5-pro</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-code-branch mr-1"></i>API Version</label>
                                            <select name="gemini_api_version" id="gemini_api_version" class="form-control">
                                                <option value="v1" <?php echo $current_config['gemini_api_version'] === 'v1' ? 'selected' : ''; ?>>v1 (Stable - Recommended)</option>
                                                <option value="v1beta" <?php echo $current_config['gemini_api_version'] === 'v1beta' ? 'selected' : ''; ?>>v1beta (Beta)</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- OpenAI Configuration -->
                                    <div id="openai_config" style="display: none;">
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-key mr-1"></i>OpenAI API Key</label>
                                            <input type="text" name="openai_key" class="form-control" value="<?php echo htmlspecialchars($current_config['openai_key']); ?>" placeholder="sk-...">
                                            <small class="text-muted">API key for job generation</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-brain mr-1"></i>Model</label>
                                            <select name="openai_model" class="form-control">
                                                <option value="gpt-3.5-turbo" <?php echo $current_config['openai_model'] === 'gpt-3.5-turbo' ? 'selected' : ''; ?>>GPT-3.5 Turbo (Faster, Cheaper)</option>
                                                <option value="gpt-4" <?php echo $current_config['openai_model'] === 'gpt-4' ? 'selected' : ''; ?>>GPT-4 (Best Quality)</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-save mr-1"></i>Save Job Generation Config
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- AI Screening Configuration -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                <h5 class="mb-0"><i class="fas fa-search mr-2"></i>AI Screening</h5>
                                <small>Configure AI for candidate screening</small>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="screeningForm">
                                    <input type="hidden" name="update_section" value="screening">
                                    
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-server mr-1"></i>Screening AI Provider</label>
                                        <select name="screening_provider" id="screening_provider" class="form-control form-control-lg" required>
                                            <option value="mock" <?php echo $current_config['screening_provider'] === 'mock' ? 'selected' : ''; ?>>Mock AI (No API Key - For Testing)</option>
                                            <option value="gemini" <?php echo $current_config['screening_provider'] === 'gemini' ? 'selected' : ''; ?>>Google Gemini (FREE)</option>
                                            <option value="openai" <?php echo $current_config['screening_provider'] === 'openai' ? 'selected' : ''; ?>>OpenAI (Paid - Better Quality)</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Screening Mock Configuration -->
                                    <div id="screening_mock_config" style="display: none;">
                                        <div class="alert alert-success">
                                            <p class="mb-0">Mock screening is active. No API key required. Perfect for testing.</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Screening Gemini Configuration -->
                                    <div id="screening_gemini_config" style="display: none;">
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-key mr-1"></i>Gemini API Key</label>
                                            <input type="text" name="screening_gemini_key" class="form-control" value="<?php echo htmlspecialchars($current_config['screening_gemini_key']); ?>" placeholder="AIzaSy...">
                                            <small class="text-muted">Can be same or different from job generation</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-brain mr-1"></i>Model</label>
                                            <select name="screening_gemini_model" id="screening_gemini_model" class="form-control">
                                                <option value="gemini-2.5-flash-lite" <?php echo $current_config['screening_gemini_model'] === 'gemini-2.5-flash-lite' ? 'selected' : ''; ?>>gemini-2.5-flash-lite (Recommended)</option>
                                                <option value="gemini-2.0-flash-lite" <?php echo $current_config['screening_gemini_model'] === 'gemini-2.0-flash-lite' ? 'selected' : ''; ?>>gemini-2.0-flash-lite</option>
                                                <option value="gemini-2.5-flash" <?php echo $current_config['screening_gemini_model'] === 'gemini-2.5-flash' ? 'selected' : ''; ?>>gemini-2.5-flash</option>
                                                <option value="gemini-2.0-flash" <?php echo $current_config['screening_gemini_model'] === 'gemini-2.0-flash' ? 'selected' : ''; ?>>gemini-2.0-flash</option>
                                                <option value="gemini-2.5-pro" <?php echo $current_config['screening_gemini_model'] === 'gemini-2.5-pro' ? 'selected' : ''; ?>>gemini-2.5-pro</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-code-branch mr-1"></i>API Version</label>
                                            <select name="screening_gemini_api_version" id="screening_gemini_api_version" class="form-control">
                                                <option value="v1" <?php echo $current_config['screening_gemini_api_version'] === 'v1' ? 'selected' : ''; ?>>v1 (Stable - Recommended)</option>
                                                <option value="v1beta" <?php echo $current_config['screening_gemini_api_version'] === 'v1beta' ? 'selected' : ''; ?>>v1beta (Beta)</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Screening OpenAI Configuration -->
                                    <div id="screening_openai_config" style="display: none;">
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-key mr-1"></i>OpenAI API Key</label>
                                            <input type="text" name="screening_openai_key" class="form-control" value="<?php echo htmlspecialchars($current_config['screening_openai_key']); ?>" placeholder="sk-...">
                                            <small class="text-muted">Can be same or different from job generation</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="font-weight-bold"><i class="fas fa-brain mr-1"></i>Model</label>
                                            <select name="screening_openai_model" class="form-control">
                                                <option value="gpt-3.5-turbo" <?php echo $current_config['screening_openai_model'] === 'gpt-3.5-turbo' ? 'selected' : ''; ?>>GPT-3.5 Turbo</option>
                                                <option value="gpt-4" <?php echo $current_config['screening_openai_model'] === 'gpt-4' ? 'selected' : ''; ?>>GPT-4</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-save mr-1"></i>Save Screening Config
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- API Status & Quota Monitoring -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0"><i class="fas fa-heartbeat mr-2"></i>API Status & Quota Monitoring</h4>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload();">
                                    <i class="fas fa-sync-alt mr-1"></i>Refresh Stats
                                </button>
                                <small class="text-muted ml-3">
                                    <i class="fas fa-info-circle mr-1"></i>Stats auto-refresh every 60 seconds
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Job Generation API Status -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <h5 class="mb-0"><i class="fas fa-briefcase mr-2"></i>Job Generation API Status</h5>
                                <small>Provider: <?php echo ucfirst($current_config['provider']); ?></small>
                            </div>
                            <div class="card-body">
                                <!-- Real-time Usage Display -->
                                <div class="mb-3 p-3" style="background: #f8f9fa; border-radius: 5px;">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <small class="text-muted">Requests Used</small>
                                                <h4 class="mb-0" style="color: #667eea;">
                                                    <i class="fas fa-arrow-up mr-1"></i><?php echo $job_gen_stats['requested']; ?>
                                                </h4>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <small class="text-muted">Remaining</small>
                                                <h4 class="mb-0" style="color: #28a745;">
                                                    <i class="fas fa-arrow-right mr-1"></i><?php echo $job_gen_stats['remaining']; ?>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Bar -->
                                    <div class="mt-3">
                                        <div class="progress" style="height: 25px; border-radius: 5px;">
                                            <div class="progress-bar <?php echo $job_gen_stats['percent'] > 80 ? 'bg-danger' : ($job_gen_stats['percent'] > 50 ? 'bg-warning' : 'bg-success'); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $job_gen_stats['percent']; ?>%" 
                                                 aria-valuenow="<?php echo $job_gen_stats['percent']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <small style="font-weight: bold; color: white;"><?php echo $job_gen_stats['percent']; ?>%</small>
                                            </div>
                                        </div>
                                        <small class="text-muted">Daily Limit: <?php echo $job_gen_stats['limit']; ?> requests</small>
                                    </div>
                                </div>
                                
                                <?php if ($job_gen_status): ?>
                                    <div class="alert alert-<?php echo $job_gen_status['badge']; ?> mb-3">
                                        <h6><i class="<?php echo $job_gen_status['icon']; ?> mr-2"></i><?php echo ucfirst(str_replace('_', ' ', $job_gen_status['status'])); ?></h6>
                                        <p class="mb-0"><?php echo $job_gen_status['message']; ?></p>
                                    </div>
                                <?php elseif ($current_config['provider'] === 'mock'): ?>
                                    <div class="alert alert-info mb-3">
                                        <h6><i class="fas fa-rocket mr-2"></i>Mock AI (Demo Mode)</h6>
                                        <p class="mb-0">No API quota restrictions. Operates locally for testing purposes.</p>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-3"><i class="fas fa-arrow-right mr-2"></i>Click "Check Status" to verify API availability and quota</p>
                                <?php endif; ?>
                                
                                <?php if ($current_config['provider'] !== 'mock'): ?>
                                    <form method="GET" class="d-inline">
                                        <input type="hidden" name="check_api" value="job_gen">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-sync-alt mr-1"></i>Check Status
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showRequestLeftInfo('job_gen')">
                                        <i class="fas fa-info-circle mr-1"></i>Quota Info
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- AI Extractor Status -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                                <h5 class="mb-0"><i class="fas fa-file-alt mr-2"></i>AI PDS Extractor</h5>
                                <small>Provider: <?php echo ucfirst($current_config['screening_provider']); ?> (Uses Screening Config)</small>
                            </div>
                            <div class="card-body">
                                <div class="mb-3 p-3" style="background: #f8f9fa; border-radius: 5px;">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <small class="text-muted">Extractions Used</small>
                                                <h4 class="mb-0" style="color: #4facfe;">
                                                    <i class="fas fa-arrow-up mr-1"></i><?php echo $extractor_stats['requested']; ?>
                                                </h4>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <small class="text-muted">Remaining</small>
                                                <h4 class="mb-0" style="color: #28a745;">
                                                    <i class="fas fa-infinity mr-1"></i>Unlimited
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="progress" style="height: 25px; border-radius: 5px;">
                                            <div class="progress-bar bg-success" 
                                                 role="progressbar" 
                                                 style="width: 100%" 
                                                 aria-valuenow="100" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <small style="font-weight: bold; color: white;">Unlimited Quota</small>
                                            </div>
                                        </div>
                                        <small class="text-muted">Using gemini-1.5-flash-8b (No daily limit)</small>
                                    </div>
                                </div>
                                <div class="alert alert-success mb-0">
                                    <h6><i class="fas fa-infinity mr-2"></i>Unlimited Quota</h6>
                                    <p class="mb-0">Using gemini-1.5-flash-8b model with no rate limits. Perfect for high-volume PDS extraction.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Screening API Status -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                                <h5 class="mb-0"><i class="fas fa-search mr-2"></i>Screening API Status</h5>
                                <small>Provider: <?php echo ucfirst($current_config['screening_provider']); ?></small>
                            </div>
                            <div class="card-body">
                                <!-- Real-time Usage Display -->
                                <div class="mb-3 p-3" style="background: #f8f9fa; border-radius: 5px;">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-center">
                                                <small class="text-muted">Requests Used</small>
                                                <h4 class="mb-0" style="color: #f5576c;">
                                                    <i class="fas fa-arrow-up mr-1"></i><?php echo $screening_stats['requested']; ?>
                                                </h4>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center">
                                                <small class="text-muted">Remaining</small>
                                                <h4 class="mb-0" style="color: #28a745;">
                                                    <i class="fas fa-arrow-right mr-1"></i><?php echo $screening_stats['remaining']; ?>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Bar -->
                                    <div class="mt-3">
                                        <div class="progress" style="height: 25px; border-radius: 5px;">
                                            <div class="progress-bar <?php echo $screening_stats['percent'] > 80 ? 'bg-danger' : ($screening_stats['percent'] > 50 ? 'bg-warning' : 'bg-success'); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $screening_stats['percent']; ?>%" 
                                                 aria-valuenow="<?php echo $screening_stats['percent']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <small style="font-weight: bold; color: white;"><?php echo $screening_stats['percent']; ?>%</small>
                                            </div>
                                        </div>
                                        <small class="text-muted">Daily Limit: <?php echo $screening_stats['limit']; ?> requests</small>
                                    </div>
                                </div>
                                
                                <?php if ($screening_status): ?>
                                    <div class="alert alert-<?php echo $screening_status['badge']; ?> mb-3">
                                        <h6><i class="<?php echo $screening_status['icon']; ?> mr-2"></i><?php echo ucfirst(str_replace('_', ' ', $screening_status['status'])); ?></h6>
                                        <p class="mb-0"><?php echo $screening_status['message']; ?></p>
                                    </div>
                                <?php elseif ($current_config['screening_provider'] === 'mock'): ?>
                                    <div class="alert alert-info mb-3">
                                        <h6><i class="fas fa-rocket mr-2"></i>Mock AI (Demo Mode)</h6>
                                        <p class="mb-0">No API quota restrictions. Operates locally for testing purposes.</p>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-3"><i class="fas fa-arrow-right mr-2"></i>Click "Check Status" to verify API availability and quota</p>
                                <?php endif; ?>
                                
                                <?php if ($current_config['screening_provider'] !== 'mock'): ?>
                                    <form method="GET" class="d-inline">
                                        <input type="hidden" name="check_api" value="screening">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-sync-alt mr-1"></i>Check Status
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showRequestLeftInfo('screening')">
                                        <i class="fas fa-info-circle mr-1"></i>Quota Info
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quota Information Modal -->
                <div class="modal fade" id="quotaModal" tabindex="-1" role="dialog" aria-labelledby="quotaModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="quotaModalLabel">API Quota Information</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div id="quotaContent">
                                    <!-- Content will be populated by JavaScript -->
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="job_openings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Job Openings
                    </a>
                </div>
                                    </select>
                            </div>
                
                        </form>
                    </div>
                </div>
                            
                </div>
                
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    
    // Show Quota Information Modal
    function showRequestLeftInfo(apiType) {
        const provider = '<?php echo $current_config['provider']; ?>';
        const screeningProvider = '<?php echo $current_config['screening_provider']; ?>';
        const currentProvider = apiType === 'job_gen' ? provider : screeningProvider;
        
        const quotaContent = `
            <div class="row">
                <div class="col-12">
                    <h6 class="mb-3">
                        <i class="fas fa-${apiType === 'job_gen' ? 'briefcase' : 'search'} mr-2" style="color: ${apiType === 'job_gen' ? '#667eea' : '#f5576c'}"></i>
                        ${apiType === 'job_gen' ? 'Job Generation' : 'Screening'} API
                    </h6>
                    <table class="table table-sm table-bordered">
                        <tbody>
                            <tr>
                                <td class="font-weight-bold">Provider</td>
                                <td>${currentProvider.toUpperCase()}</td>
                            </tr>
                            ${currentProvider === 'gemini' ? `
                            <tr>
                                <td class="font-weight-bold">Free Quota</td>
                                <td><span class="badge badge-success">60 requests/minute</span></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Daily Limit</td>
                                <td><span class="badge badge-info">1,500 requests/day</span></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Check Frequency</td>
                                <td>Current usage resets daily at 00:00 UTC</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Recommendation</td>
                                <td>
                                    <small>• Use Mock AI for extensive testing<br>
                                    • Monitor daily usage to avoid hitting limits<br>
                                    • Generate API key for increased quota</small>
                                </td>
                            </tr>
                            ` : currentProvider === 'openai' ? `
                            <tr>
                                <td class="font-weight-bold">Billing Status</td>
                                <td>Check at <a href="https://platform.openai.com/account/billing/overview" target="_blank">OpenAI Dashboard</a></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Request Limits</td>
                                <td>Depends on your plan and usage</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Check Frequency</td>
                                <td>Real-time monitoring via API responses</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Recommendation</td>
                                <td>
                                    <small>• Monitor your OpenAI account regularly<br>
                                    • Check rate limits and usage statistics<br>
                                    • Set up usage alerts in OpenAI dashboard</small>
                                </td>
                            </tr>
                            ` : `
                            <tr>
                                <td class="font-weight-bold">Status</td>
                                <td><span class="badge badge-secondary">Mock/Demo</span></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Request Limit</td>
                                <td>Unlimited (Local Testing)</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold">Recommendation</td>
                                <td>Perfect for development and testing</td>
                            </tr>
                            `}
                        </tbody>
                    </table>
                    
                    <div class="alert alert-${currentProvider === 'gemini' ? 'info' : currentProvider === 'openai' ? 'warning' : 'success'}">
                        <strong>ℹ️ How to Inspect Quota:</strong>
                        <ul class="mb-0 mt-2">
                            ${currentProvider === 'gemini' ? `
                            <li>Use the <strong>"Check Status"</strong> button above to verify current API availability</li>
                            <li>Visit <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a> to check quota details</li>
                            <li>Quota resets automatically every 24 hours</li>
                            <li>If quota is exhausted, generate a new API key for a fresh quota</li>
                            ` : currentProvider === 'openai' ? `
                            <li>Monitor usage in your <a href="https://platform.openai.com/account/billing/limits" target="_blank">OpenAI account</a></li>
                            <li>Use the "Check Status" button to verify API connectivity</li>
                            <li>Rate limits vary based on your subscription plan</li>
                            <li>Set up email notifications for usage alerts in OpenAI dashboard</li>
                            ` : `
                            <li>No quota tracking needed for Mock AI</li>
                            <li>Requests process instantly without API calls</li>
                            <li>Switch to Gemini or OpenAI for production use</li>
                            `}
                        </ul>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('quotaContent').innerHTML = quotaContent;
        $('#quotaModal').modal('show');
    }
    
    // Auto-refresh page stats every 60 seconds (can be toggled off)
    let autoRefreshEnabled = true;
    setInterval(function() {
        if (autoRefreshEnabled && !$('.modal.show').length) { // Don't refresh if modal is open
            location.reload();
        }
    }, 60000);
    
    // Toast Notification Function
    function showToast(message, type = 'success') {
        const toastId = 'toast-' + Date.now();
        const iconMap = {
            'success': 'fa-check-circle',
            'error': 'fa-times-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        const icon = iconMap[type] || iconMap['info'];
        
        const toast = $(`
            <div class="custom-toast toast-${type}" id="${toastId}">
                <div class="toast-header">
                    <i class="fas ${icon} mr-2"></i>
                    <strong class="mr-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                    <button type="button" class="ml-2 mb-1 close" onclick="$('#${toastId}').fadeOut(300, function(){ $(this).remove(); })">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `);
        
        $('#toastContainer').append(toast);
        
        // Auto remove after 5 seconds
        setTimeout(function() {
            $('#' + toastId).fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    $(document).ready(function(){
        // Show toast for success/error messages
        <?php if ($success_message): ?>
            showToast('<?php echo addslashes($success_message); ?>', 'success');
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            showToast('<?php echo addslashes($error_message); ?>', 'error');
        <?php endif; ?>
        
        // Toggle Job Generation Provider Config
        function toggleJobProviderConfig() {
            var provider = $('#provider').val();
            $('#mock_config, #gemini_config, #openai_config').hide();
            
            if (provider === 'mock') {
                $('#mock_config').show();
            } else if (provider === 'gemini') {
                $('#gemini_config').show();
            } else {
                $('#openai_config').show();
            }
        }
        
        // Toggle Screening Provider Config
        function toggleScreeningProviderConfig() {
            var provider = $('#screening_provider').val();
            $('#screening_mock_config, #screening_gemini_config, #screening_openai_config').hide();
            
            if (provider === 'mock') {
                $('#screening_mock_config').show();
            } else if (provider === 'gemini') {
                $('#screening_gemini_config').show();
            } else {
                $('#screening_openai_config').show();
            }
        }
        
        $('#provider').on('change', toggleJobProviderConfig);
        $('#screening_provider').on('change', toggleScreeningProviderConfig);
        toggleJobProviderConfig();
        toggleScreeningProviderConfig();
        
        // Form submission to prevent page reload and show feedback
        $('#jobGenerationForm').on('submit', function(e) {
            $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
        });
        
        $('#screeningForm').on('submit', function(e) {
            $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
        });
    });
    </script>
</body>
</html>
