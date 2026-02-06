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
    'openai_key' => '',
    'openai_model' => 'gpt-3.5-turbo'
];

if (file_exists($config_file)) {
    $content = file_get_contents($config_file);
    
    // Extract current values
    if (preg_match("/define\('AI_PROVIDER',\s*'([^']+)'\)/", $content, $matches)) {
        $current_config['provider'] = $matches[1];
    }
    if (preg_match("/define\('GEMINI_API_KEY',\s*'([^']+)'\)/", $content, $matches)) {
        $current_config['gemini_key'] = $matches[1];
    }
    if (preg_match("/define\('OPENAI_API_KEY',\s*'([^']+)'\)/", $content, $matches)) {
        $current_config['openai_key'] = $matches[1];
    }
    if (preg_match("/define\('OPENAI_MODEL',\s*'([^']+)'\)/", $content, $matches)) {
        $current_config['openai_model'] = $matches[1];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = $_POST['provider'] ?? 'gemini';
    $gemini_key = $_POST['gemini_key'] ?? '';
    $openai_key = $_POST['openai_key'] ?? '';
    $openai_model = $_POST['openai_model'] ?? 'gpt-3.5-turbo';
    
    // Validate
    if ($provider === 'gemini' && empty($gemini_key)) {
        $error_message = 'Please provide a Gemini API key';
    } elseif ($provider === 'openai' && empty($openai_key)) {
        $error_message = 'Please provide an OpenAI API key';
    } else {
        // Generate new config file content
        $new_content = "<?php
// AI Configuration File
// Switch between different AI providers easily

// AI Provider: 'mock', 'gemini' or 'openai'
// Use 'mock' for testing without API keys
define('AI_PROVIDER', '{$provider}');

// Google Gemini Configuration (FREE tier)
define('GEMINI_API_KEY', '{$gemini_key}'); // Get from: https://makersuite.google.com/app/apikey
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent');

// OpenAI Configuration (Paid - better quality)
define('OPENAI_API_KEY', '{$openai_key}'); // Get from: https://platform.openai.com/api-keys
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('OPENAI_MODEL', '{$openai_model}'); // or 'gpt-4' for better quality

/**
 * Generate job description using AI
 */
function generateJobWithAI(\$jobRoleTitle, \$jobRoleDescription, \$departmentName, \$employmentType = 'Full-time', \$salaryMin = null, \$salaryMax = null) {
    \$prompt = buildJobPrompt(\$jobRoleTitle, \$jobRoleDescription, \$departmentName, \$employmentType, \$salaryMin, \$salaryMax);
    
    if (AI_PROVIDER === 'mock') {
        return generateMockJob(\$jobRoleTitle, \$jobRoleDescription, \$departmentName, \$employmentType);
    } elseif (AI_PROVIDER === 'gemini') {
        return callGeminiAPI(\$prompt);
    } else {
        return callOpenAI(\$prompt);
    }
}

/**
 * Generate mock job data (no API needed - for testing)
 */
function generateMockJob(\$jobRoleTitle, \$jobRoleDescription, \$departmentName, \$employmentType) {
    // Generate realistic job data without calling any API
    \$title = \$jobRoleTitle . \" - \" . \$departmentName;
    
    \$description = \"We are seeking a qualified {\$jobRoleTitle} to join our {\$departmentName}. \" .
                   \"This {\$employmentType} position offers an excellent opportunity to contribute to our municipal operations. \" .
                   \"The ideal candidate will work collaboratively with our team to deliver high-quality services to our community. \" .
                   \"{\$jobRoleDescription}\";
    
    \$requirements = \"• Bachelor's degree in relevant field or equivalent experience\\n\" .
                   \"• Minimum 2 years of professional experience in related role\\n\" .
                   \"• Strong communication and interpersonal skills\\n\" .
                   \"• Proficiency in Microsoft Office Suite (Word, Excel, PowerPoint)\\n\" .
                   \"• Excellent organizational and time management abilities\\n\" .
                   \"• Ability to work independently and as part of a team\\n\" .
                   \"• Strong problem-solving and analytical skills\\n\" .
                   \"• Commitment to public service and community development\";
    
    \$responsibilities = \"• Perform daily tasks and responsibilities as assigned by the department head\\n\" .
                       \"• Collaborate with team members to achieve departmental goals\\n\" .
                       \"• Maintain accurate records and documentation\\n\" .
                       \"• Prepare reports and presentations as required\\n\" .
                       \"• Respond to inquiries from the public and other departments\\n\" .
                       \"• Participate in meetings and training sessions\\n\" .
                       \"• Ensure compliance with municipal policies and procedures\\n\" .
                       \"• Contribute to continuous improvement initiatives\";
    
    \$experienceLevel = \"Mid-Level\";
    \$educationRequirements = \"Bachelor's degree in relevant field or equivalent combination of education and experience\";
    
    return [
        'success' => true,
        'data' => [
            'title' => \$title,
            'description' => \$description,
            'requirements' => \$requirements,
            'responsibilities' => \$responsibilities,
            'experience_level' => \$experienceLevel,
            'education_requirements' => \$educationRequirements
        ]
    ];
}

/**
 * Build the prompt for AI
 */
function buildJobPrompt(\$jobRoleTitle, \$jobRoleDescription, \$departmentName, \$employmentType, \$salaryMin, \$salaryMax) {
    \$salaryInfo = '';
    if (\$salaryMin && \$salaryMax) {
        \$salaryInfo = \"\\nSalary Range: ₱\" . number_format(\$salaryMin) . \" - ₱\" . number_format(\$salaryMax);
    }
    
    \$prompt = \"You are an expert HR professional creating a job opening for a government/municipal office.

Job Role: {\$jobRoleTitle}
Department: {\$departmentName}
Employment Type: {\$employmentType}
Role Description: {\$jobRoleDescription}{\$salaryInfo}

Create a comprehensive job opening with the following sections. Return ONLY valid JSON with no markdown formatting:

{
  \\\"title\\\": \\\"Professional job title (e.g., 'Senior Software Developer - IT Department')\\\",
  \\\"description\\\": \\\"Compelling 2-3 paragraph job description that explains the role, its importance, and what the candidate will do\\\",
  \\\"requirements\\\": \\\"Detailed bullet-point list of qualifications, skills, and experience needed. Include education, certifications, technical skills, and soft skills. Format as bullet points with • symbol\\\",
  \\\"responsibilities\\\": \\\"Detailed bullet-point list of key duties and day-to-day responsibilities. Be specific and actionable. Format as bullet points with • symbol\\\",
  \\\"experience_level\\\": \\\"Entry Level, Mid-Level, Senior Level, or Executive\\\",
  \\\"education_requirements\\\": \\\"Specific educational qualifications needed (e.g., 'Bachelor's degree in Computer Science or related field')\\\"
}

Make it professional, clear, and attractive to qualified candidates. Use proper grammar and formatting.\";

    return \$prompt;
}

/**
 * Call Google Gemini API
 */
function callGeminiAPI(\$prompt) {
    \$apiKey = GEMINI_API_KEY;
    \$url = GEMINI_API_URL . '?key=' . \$apiKey;
    
    \$data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => \$prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 2048,
        ]
    ];
    
    \$ch = curl_init(\$url);
    curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(\$ch, CURLOPT_POST, true);
    curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(\$data));
    curl_setopt(\$ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    \$response = curl_exec(\$ch);
    \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
    curl_close(\$ch);
    
    if (\$httpCode !== 200) {
        return ['error' => 'Gemini API Error: ' . \$response];
    }
    
    \$result = json_decode(\$response, true);
    
    if (isset(\$result['candidates'][0]['content']['parts'][0]['text'])) {
        \$text = \$result['candidates'][0]['content']['parts'][0]['text'];
        // Clean up markdown code blocks if present
        \$text = preg_replace('/```json\\s*/', '', \$text);
        \$text = preg_replace('/```\\s*$/', '', \$text);
        \$text = trim(\$text);
        
        \$jobData = json_decode(\$text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => \$jobData];
        } else {
            return ['error' => 'Failed to parse AI response: ' . json_last_error_msg()];
        }
    }
    
    return ['error' => 'Invalid response from Gemini API'];
}

/**
 * Call OpenAI API
 */
function callOpenAI(\$prompt) {
    \$apiKey = OPENAI_API_KEY;
    \$url = OPENAI_API_URL;
    
    \$data = [
        'model' => OPENAI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert HR professional. Always respond with valid JSON only, no markdown formatting.'
            ],
            [
                'role' => 'user',
                'content' => \$prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 2000
    ];
    
    \$ch = curl_init(\$url);
    curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(\$ch, CURLOPT_POST, true);
    curl_setopt(\$ch, CURLOPT_POSTFIELDS, json_encode(\$data));
    curl_setopt(\$ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . \$apiKey
    ]);
    
    \$response = curl_exec(\$ch);
    \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
    curl_close(\$ch);
    
    if (\$httpCode !== 200) {
        return ['error' => 'OpenAI API Error: ' . \$response];
    }
    
    \$result = json_decode(\$response, true);
    
    if (isset(\$result['choices'][0]['message']['content'])) {
        \$text = \$result['choices'][0]['message']['content'];
        // Clean up markdown code blocks if present
        \$text = preg_replace('/```json\\s*/', '', \$text);
        \$text = preg_replace('/```\\s*$/', '', \$text);
        \$text = trim(\$text);
        
        \$jobData = json_decode(\$text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => \$jobData];
        } else {
            return ['error' => 'Failed to parse AI response: ' . json_last_error_msg()];
        }
    }
    
    return ['error' => 'Invalid response from OpenAI API'];
}
?>";
        
        if (file_put_contents($config_file, $new_content)) {
            $success_message = '✅ AI configuration saved successfully!';
            $current_config = [
                'provider' => $provider,
                'gemini_key' => $gemini_key,
                'openai_key' => $openai_key,
                'openai_model' => $openai_model
            ];
        } else {
            $error_message = '❌ Failed to save configuration file. Check file permissions.';
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
</head>
<body>
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
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cog mr-2"></i>Configure AI Provider</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
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
                                        <li>⚠️ Not as sophisticated as real AI</li>
                                    </ul>
                                    <hr>
                                    <strong>How it works:</strong>
                                    <p class="mb-0">Mock AI generates professional job descriptions using templates. It's perfect for testing the system without setting up API keys. When you're ready for production, switch to Gemini (FREE) or OpenAI for better quality.</p>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i><strong>Ready to use!</strong> No configuration needed. Just click "Save Configuration" and start generating jobs.
                                </div>
                            </div>
                            
                            <!-- Gemini Configuration -->
                            <div id="gemini_config" style="display: none;">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle mr-2"></i>Google Gemini (FREE)</h6>
                                    <ul class="mb-0">
                                        <li>✅ Completely FREE</li>
                                        <li>✅ 60 requests per minute</li>
                                        <li>✅ Good quality job descriptions</li>
                                        <li>✅ No credit card required</li>
                                    </ul>
                                    <hr>
                                    <strong>Get your FREE API key:</strong>
                                    <ol class="mb-0">
                                        <li>Visit: <a href="https://makersuite.google.com/app/apikey" target="_blank">https://makersuite.google.com/app/apikey</a></li>
                                        <li>Sign in with your Google account</li>
                                        <li>Click "Create API Key"</li>
                                        <li>Copy and paste it below</li>
                                    </ol>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="fas fa-key mr-1"></i>Gemini API Key</label>
                                    <input type="text" name="gemini_key" class="form-control" value="<?php echo htmlspecialchars($current_config['gemini_key']); ?>" placeholder="AIzaSy...">
                                    <small class="text-muted">Your Google Gemini API key</small>
                                </div>
                            </div>
                            
                            <!-- OpenAI Configuration -->
                            <div id="openai_config" style="display: none;">
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-dollar-sign mr-2"></i>OpenAI (Paid Service)</h6>
                                    <ul class="mb-0">
                                        <li>💰 Paid service (requires credit card)</li>
                                        <li>✅ Better quality descriptions</li>
                                        <li>✅ More consistent results</li>
                                        <li>💵 Cost: ~$0.01-0.03 per job</li>
                                    </ul>
                                    <hr>
                                    <strong>Get your API key:</strong>
                                    <ol class="mb-0">
                                        <li>Visit: <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a></li>
                                        <li>Create account and add payment method</li>
                                        <li>Create new API key</li>
                                        <li>Copy and paste it below</li>
                                    </ol>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="fas fa-key mr-1"></i>OpenAI API Key</label>
                                    <input type="text" name="openai_key" class="form-control" value="<?php echo htmlspecialchars($current_config['openai_key']); ?>" placeholder="sk-...">
                                    <small class="text-muted">Your OpenAI API key</small>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="fas fa-brain mr-1"></i>Model</label>
                                    <select name="openai_model" class="form-control">
                                        <option value="gpt-3.5-turbo" <?php echo $current_config['openai_model'] === 'gpt-3.5-turbo' ? 'selected' : ''; ?>>GPT-3.5 Turbo (Faster, Cheaper)</option>
                                        <option value="gpt-4" <?php echo $current_config['openai_model'] === 'gpt-4' ? 'selected' : ''; ?>>GPT-4 (Best Quality, More Expensive)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="text-right mt-4">
                                <a href="job_openings.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left mr-1"></i>Back to Job Openings
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>Save Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function(){
        function toggleProviderConfig() {
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
        
        $('#provider').on('change', toggleProviderConfig);
        toggleProviderConfig();
    });
    </script>
</body>
</html>
