<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Only admin can configure API keys
if ($_SESSION['role'] !== 'admin') {
    header('Location: unauthorized.php');
    exit;
}

$success = '';
$error = '';
$keysFile = __DIR__ . '/ai_keys.php';

// Load current keys
$currentGeminiKey = '';
$currentOpenAIKey = '';
$currentProvider = 'mock';

if (file_exists($keysFile)) {
    require_once $keysFile;
    $currentGeminiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    $currentOpenAIKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
}

require_once 'ai_config.php';
$currentProvider = AI_PROVIDER;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $geminiKey = trim($_POST['gemini_key'] ?? '');
    $openaiKey = trim($_POST['openai_key'] ?? '');
    $provider = $_POST['provider'] ?? 'mock';
    
    try {
        // Save API keys to ai_keys.php
        $keysContent = "<?php\n";
        $keysContent .= "/**\n";
        $keysContent .= " * AI API Keys Configuration\n";
        $keysContent .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $keysContent .= " * IMPORTANT: This file is not tracked in git for security\n";
        $keysContent .= " */\n\n";
        $keysContent .= "// Google Gemini API Key\n";
        $keysContent .= "define('GEMINI_API_KEY', '" . addslashes($geminiKey) . "');\n\n";
        $keysContent .= "// OpenAI API Key\n";
        $keysContent .= "define('OPENAI_API_KEY', '" . addslashes($openaiKey) . "');\n";
        $keysContent .= "?>\n";
        
        if (file_put_contents($keysFile, $keysContent) === false) {
            throw new Exception('Failed to write keys file. Check file permissions.');
        }
        
        // Update AI provider in ai_config.php
        $configFile = __DIR__ . '/ai_config.php';
        $configContent = file_get_contents($configFile);
        $configContent = preg_replace(
            "/define\('AI_PROVIDER',\s*'[^']+'\);/",
            "define('AI_PROVIDER', '$provider');",
            $configContent
        );
        file_put_contents($configFile, $configContent);
        
        $success = 'AI configuration saved successfully! API keys are now secure.';
        $currentGeminiKey = $geminiKey;
        $currentOpenAIKey = $openaiKey;
        $currentProvider = $provider;
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
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
    <link rel="stylesheet" href="styles.css">
    <style>
        .config-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .provider-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .provider-option:hover {
            border-color: #E91E63;
            background: #FCE4EC;
        }
        
        .provider-option.active {
            border-color: #E91E63;
            background: linear-gradient(135deg, #FCE4EC 0%, #F8BBD0 100%);
        }
        
        .provider-option input[type="radio"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
        
        .key-input {
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        .security-notice {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2><i class="fas fa-robot mr-2"></i>AI Configuration</h2>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <div class="security-notice">
                    <h6><i class="fas fa-shield-alt mr-2"></i>Security Notice</h6>
                    <p class="mb-0">
                        API keys are stored in <code>ai_keys.php</code> which is excluded from git.
                        Never commit this file to your repository. Keys are only accessible to admin users.
                    </p>
                </div>
                
                <form method="POST">
                    <div class="config-card">
                        <h5 class="mb-4"><i class="fas fa-cog mr-2"></i>AI Provider Selection</h5>
                        
                        <div class="provider-option <?php echo $currentProvider === 'mock' ? 'active' : ''; ?>" onclick="selectProvider('mock')">
                            <label class="mb-0 d-flex align-items-center">
                                <input type="radio" name="provider" value="mock" <?php echo $currentProvider === 'mock' ? 'checked' : ''; ?>>
                                <div>
                                    <strong>Mock Provider (Testing)</strong>
                                    <p class="mb-0 text-muted small">Instant results, no API needed. Good for testing and development.</p>
                                </div>
                            </label>
                        </div>
                        
                        <div class="provider-option <?php echo $currentProvider === 'gemini' ? 'active' : ''; ?>" onclick="selectProvider('gemini')">
                            <label class="mb-0 d-flex align-items-center">
                                <input type="radio" name="provider" value="gemini" <?php echo $currentProvider === 'gemini' ? 'checked' : ''; ?>>
                                <div>
                                    <strong>Google Gemini (Recommended)</strong>
                                    <p class="mb-0 text-muted small">Free tier available. Fast and accurate AI screening.</p>
                                </div>
                            </label>
                        </div>
                        
                        <div class="provider-option <?php echo $currentProvider === 'openai' ? 'active' : ''; ?>" onclick="selectProvider('openai')">
                            <label class="mb-0 d-flex align-items-center">
                                <input type="radio" name="provider" value="openai" <?php echo $currentProvider === 'openai' ? 'checked' : ''; ?>>
                                <div>
                                    <strong>OpenAI (ChatGPT)</strong>
                                    <p class="mb-0 text-muted small">Paid service. High quality AI analysis.</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="config-card">
                        <h5 class="mb-4"><i class="fas fa-key mr-2"></i>API Keys</h5>
                        
                        <div class="form-group">
                            <label for="gemini_key">
                                <i class="fab fa-google mr-2"></i>Google Gemini API Key
                                <a href="https://makersuite.google.com/app/apikey" target="_blank" class="btn btn-sm btn-outline-primary ml-2">
                                    <i class="fas fa-external-link-alt"></i> Get Key
                                </a>
                            </label>
                            <input type="text" 
                                   class="form-control key-input" 
                                   id="gemini_key" 
                                   name="gemini_key" 
                                   value="<?php echo htmlspecialchars($currentGeminiKey); ?>"
                                   placeholder="AIzaSy...">
                            <small class="form-text text-muted">
                                Free tier: 60 requests per minute. Leave empty if not using Gemini.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="openai_key">
                                <i class="fas fa-brain mr-2"></i>OpenAI API Key
                                <a href="https://platform.openai.com/api-keys" target="_blank" class="btn btn-sm btn-outline-primary ml-2">
                                    <i class="fas fa-external-link-alt"></i> Get Key
                                </a>
                            </label>
                            <input type="text" 
                                   class="form-control key-input" 
                                   id="openai_key" 
                                   name="openai_key" 
                                   value="<?php echo htmlspecialchars($currentOpenAIKey); ?>"
                                   placeholder="sk-...">
                            <small class="form-text text-muted">
                                Paid service. Charges per API call. Leave empty if not using OpenAI.
                            </small>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save mr-2"></i>Save Configuration
                        </button>
                    </div>
                </form>
                
                <div class="config-card mt-4">
                    <h5 class="mb-3"><i class="fas fa-info-circle mr-2"></i>Current Status</h5>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Active Provider:</strong></td>
                            <td>
                                <?php 
                                $providerNames = [
                                    'mock' => '🧪 Mock (Testing)',
                                    'gemini' => '🤖 Google Gemini',
                                    'openai' => '🧠 OpenAI'
                                ];
                                echo $providerNames[$currentProvider] ?? $currentProvider;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Gemini Key:</strong></td>
                            <td>
                                <?php 
                                if (!empty($currentGeminiKey)) {
                                    echo '<span class="badge badge-success">Configured</span> ' . 
                                         substr($currentGeminiKey, 0, 10) . '...' . substr($currentGeminiKey, -4);
                                } else {
                                    echo '<span class="badge badge-secondary">Not Set</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>OpenAI Key:</strong></td>
                            <td>
                                <?php 
                                if (!empty($currentOpenAIKey)) {
                                    echo '<span class="badge badge-success">Configured</span> ' . 
                                         substr($currentOpenAIKey, 0, 7) . '...' . substr($currentOpenAIKey, -4);
                                } else {
                                    echo '<span class="badge badge-secondary">Not Set</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Keys File:</strong></td>
                            <td>
                                <?php 
                                if (file_exists($keysFile)) {
                                    echo '<span class="badge badge-success">Exists</span> <code>ai_keys.php</code>';
                                } else {
                                    echo '<span class="badge badge-warning">Not Created</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function selectProvider(provider) {
            document.querySelectorAll('.provider-option').forEach(el => {
                el.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            document.querySelector(`input[value="${provider}"]`).checked = true;
        }
    </script>
</body>
</html>
