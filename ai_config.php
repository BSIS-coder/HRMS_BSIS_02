<?php
// AI Configuration File
// Switch between different AI providers easily

// AI Provider: 'mock', 'gemini' or 'openai'
// Use 'mock' for testing without API keys
define('AI_PROVIDER', 'gemini');

// Load API keys from secure config file (not tracked in git)
$apiKeysFile = __DIR__ . '/ai_keys.php';
if (file_exists($apiKeysFile)) {
    require_once $apiKeysFile;
} else {
    // Default empty keys if file doesn't exist
    define('GEMINI_API_KEY', '');
    define('OPENAI_API_KEY', '');
    define('SCREENING_GEMINI_API_KEY', '');
    define('SCREENING_OPENAI_API_KEY', '');
}

/**
 * Log API usage to database (PDO Compatible)
 */
function logAPIUsage($provider, $apiType = 'job_generation') {
    try {
        // Try to get PDO connection from globals
        $conn = $GLOBALS['conn'] ?? null;
        
        if (!$conn) {
            require_once 'config.php';
            $conn = $GLOBALS['conn'] ?? null;
        }
        
        if (!$conn || !($conn instanceof PDO)) {
            return; // Silently fail if no connection
        }
        
        $today = date('Y-m-d');
        
        // Check if tracking table exists (PDO)
        try {
            $result = $conn->query("SHOW TABLES LIKE 'api_usage_tracking'");
            $tableExists = ($result && $result->rowCount() > 0);
        } catch (Exception $e) {
            $tableExists = false;
        }
        
        if (!$tableExists) {
            try {
                $conn->exec("CREATE TABLE IF NOT EXISTS api_usage_tracking (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    provider VARCHAR(50) NOT NULL,
                    api_type VARCHAR(50) NOT NULL,
                    request_date DATE NOT NULL,
                    request_count INT DEFAULT 1,
                    status VARCHAR(50) DEFAULT 'success',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_tracking (provider, api_type, request_date)
                )");
            } catch (Exception $e) {
                // Table might already exist, ignore
            }
        }
        
        // Insert or update usage count (PDO)
        try {
            $stmt = $conn->prepare("INSERT INTO api_usage_tracking (provider, api_type, request_date, request_count) 
                                   VALUES (?, ?, ?, 1)
                                   ON DUPLICATE KEY UPDATE request_count = request_count + 1");
            $stmt->execute([$provider, $apiType, $today]);
        } catch (Exception $e) {
            // Silently fail if query fails
        }
        
    } catch (Exception $e) {
        // Silently fail - don't break the API calls
    }
}

// Gemini Models: gemini-2.5-flash-lite, gemini-2.0-flash-lite, gemini-2.5-flash, gemini-2.5-pro, gemini-2.0-flash

define('GEMINI_MODEL', 'gemini-2.5-flash-lite'); // Lite version with available quota
define('GEMINI_API_VERSION', 'v1'); // v1 or v1beta
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/' . GEMINI_API_VERSION . '/models/' . GEMINI_MODEL . ':generateContent');

// OpenAI Configuration (Paid - better quality)
// Keys are now stored in ai_keys.php (not tracked in git)
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('OPENAI_MODEL', 'gpt-3.5-turbo'); // or 'gpt-4' for better quality

// ==================== AI SCREENING CONFIGURATION ====================
// Separate configuration for candidate screening (can use different provider/model)

// AI Screening Provider: 'mock', 'gemini' or 'openai'
define('SCREENING_AI_PROVIDER', 'gemini'); // Can be different from job generation

// Screening API Keys (stored in ai_keys.php)
// SCREENING_GEMINI_API_KEY
// SCREENING_OPENAI_API_KEY

// Gemini Screening Configuration
define('SCREENING_GEMINI_MODEL', 'gemini-2.5-flash-lite');
define('SCREENING_GEMINI_API_VERSION', 'v1');
define('SCREENING_GEMINI_API_URL', 'https://generativelanguage.googleapis.com/' . SCREENING_GEMINI_API_VERSION . '/models/' . SCREENING_GEMINI_MODEL . ':generateContent');

// OpenAI Screening Configuration
define('SCREENING_OPENAI_MODEL', 'gpt-3.5-turbo');

// ==================== AI EXTRACTOR CONFIGURATION ====================
// Separate configuration for PDS extraction (can use different provider/model)

// AI Extractor Provider: 'mock', 'gemini' or 'openai'
define('EXTRACTOR_AI_PROVIDER', 'gemini');

// Extractor API Keys (stored in ai_keys.php)
// EXTRACTOR_GEMINI_API_KEY
// EXTRACTOR_OPENAI_API_KEY

// Gemini Extractor Configuration
define('EXTRACTOR_GEMINI_MODEL', 'gemini-1.5-flash-8b');
define('EXTRACTOR_GEMINI_API_VERSION', 'v1beta');
define('EXTRACTOR_GEMINI_API_URL', 'https://generativelanguage.googleapis.com/' . EXTRACTOR_GEMINI_API_VERSION . '/models/' . EXTRACTOR_GEMINI_MODEL . ':generateContent');

// OpenAI Extractor Configuration
define('EXTRACTOR_OPENAI_MODEL', 'gpt-3.5-turbo');

/**
 * Generate job description using AI
 */
function generateJobWithAI($jobRoleTitle, $jobRoleDescription, $departmentName, $employmentType = 'Full-time', $salaryMin = null, $salaryMax = null) {
    $prompt = buildJobPrompt($jobRoleTitle, $jobRoleDescription, $departmentName, $employmentType, $salaryMin, $salaryMax);
    
    if (AI_PROVIDER === 'mock') {
        logAPIUsage('mock', 'job_generation');
        return generateMockJob($jobRoleTitle, $jobRoleDescription, $departmentName, $employmentType);
    } elseif (AI_PROVIDER === 'gemini') {
        $result = callGeminiAPI($prompt);
        if (isset($result['success']) && $result['success']) {
            logAPIUsage('gemini', 'job_generation');
        }
        return $result;
    } else {
        $result = callOpenAI($prompt);
        if (isset($result['success']) && $result['success']) {
            logAPIUsage('openai', 'job_generation');
        }
        return $result;
    }
}

/**
 * Generate mock job data (no API needed - for testing)
 */
function generateMockJob($jobRoleTitle, $jobRoleDescription, $departmentName, $employmentType) {
    // Generate realistic job data without calling any API
    $title = $jobRoleTitle . " - " . $departmentName;
    
    $description = "We are seeking a qualified {$jobRoleTitle} to join our {$departmentName}. " .
                   "This {$employmentType} position offers an excellent opportunity to contribute to our municipal operations. " .
                   "The ideal candidate will work collaboratively with our team to deliver high-quality services to our community. " .
                   "{$jobRoleDescription}";
    
    $requirements = "• Bachelor's degree in relevant field or equivalent experience\n" .
                   "• Minimum 2 years of professional experience in related role\n" .
                   "• Strong communication and interpersonal skills\n" .
                   "• Proficiency in Microsoft Office Suite (Word, Excel, PowerPoint)\n" .
                   "• Excellent organizational and time management abilities\n" .
                   "• Ability to work independently and as part of a team\n" .
                   "• Strong problem-solving and analytical skills\n" .
                   "• Commitment to public service and community development";
    
    $responsibilities = "• Perform daily tasks and responsibilities as assigned by the department head\n" .
                       "• Collaborate with team members to achieve departmental goals\n" .
                       "• Maintain accurate records and documentation\n" .
                       "• Prepare reports and presentations as required\n" .
                       "• Respond to inquiries from the public and other departments\n" .
                       "• Participate in meetings and training sessions\n" .
                       "• Ensure compliance with municipal policies and procedures\n" .
                       "• Contribute to continuous improvement initiatives";
    
    $experienceLevel = "Mid-Level";
    $educationRequirements = "Bachelor's degree in relevant field or equivalent combination of education and experience";
    
    return [
        'success' => true,
        'data' => [
            'title' => $title,
            'description' => $description,
            'requirements' => $requirements,
            'responsibilities' => $responsibilities,
            'experience_level' => $experienceLevel,
            'education_requirements' => $educationRequirements
        ]
    ];
}

/**
 * Generate mock offer letter (no API needed - for testing)
 */
function generateMockOfferLetter($candidateName, $jobTitle, $departmentName, $salary, $startDate, $benefits) {
    $formattedSalary = number_format($salary, 2);
    $formattedStartDate = date('F j, Y', strtotime($startDate));
    $acceptanceDeadline = date('F j, Y', strtotime('+7 days'));
    $currentDate = date('F j, Y');
    
    $letter = <<<EOL
{$currentDate}

Dear {$candidateName},

SUBJECT: JOB OFFER - {$jobTitle}

We are pleased to extend to you an offer of employment with the Municipal Government in the position of {$jobTitle} within the {$departmentName}.

After careful consideration of your qualifications, experience, and performance throughout the selection process, we believe you will be an excellent addition to our team and will contribute significantly to our mission of serving the community.

POSITION DETAILS:

Position Title: {$jobTitle}
Department: {$departmentName}
Employment Type: Full-time
Start Date: {$formattedStartDate}
Salary: ₱{$formattedSalary} per month

BENEFITS PACKAGE:

{$benefits}

KEY RESPONSIBILITIES:

As {$jobTitle}, you will be responsible for:
• Performing duties and responsibilities as outlined in the job description
• Collaborating with team members to achieve departmental objectives
• Maintaining high standards of professionalism and public service
• Adhering to all municipal policies, procedures, and regulations
• Contributing to the continuous improvement of departmental operations

TERMS AND CONDITIONS:

This offer is contingent upon:
• Successful completion of pre-employment requirements
• Verification of credentials and references
• Compliance with all municipal employment policies

ACCEPTANCE:

To accept this offer, please sign and return this letter by {$acceptanceDeadline}. If you have any questions or need clarification regarding any aspect of this offer, please do not hesitate to contact our HR Department.

We are excited about the prospect of you joining our team and look forward to your positive response.

Sincerely,

Human Resources Department
Municipal Government

---

ACCEPTANCE OF OFFER

I, {$candidateName}, hereby accept the position of {$jobTitle} with the Municipal Government under the terms and conditions stated above.

Signature: _____________________     Date: _____________________
EOL;
    
    return $letter;
}

/**
 * Build the prompt for AI
 */
function buildJobPrompt($jobRoleTitle, $jobRoleDescription, $departmentName, $employmentType, $salaryMin, $salaryMax) {
    $salaryInfo = '';
    if ($salaryMin && $salaryMax) {
        $salaryInfo = "\nSalary Range: ₱" . number_format($salaryMin) . " - ₱" . number_format($salaryMax);
    }
    
    $prompt = "You are an expert HR professional creating a job opening for a government/municipal office.

Job Role: {$jobRoleTitle}
Department: {$departmentName}
Employment Type: {$employmentType}
Role Description: {$jobRoleDescription}{$salaryInfo}

CRITICAL: You must respond with ONLY a valid JSON object. Do not include any markdown formatting, code blocks, or explanatory text. Start your response with { and end with }.

Create a comprehensive job opening with the following JSON structure:

{
  \"title\": \"Professional job title (e.g., 'Senior Software Developer - IT Department')\",
  \"description\": \"Compelling 2-3 paragraph job description that explains the role, its importance, and what the candidate will do\",
  \"requirements\": \"Detailed bullet-point list of qualifications, skills, and experience needed. Include education, certifications, technical skills, and soft skills. Format as bullet points with • symbol\",
  \"responsibilities\": \"Detailed bullet-point list of key duties and day-to-day responsibilities. Be specific and actionable. Format as bullet points with • symbol\",
  \"experience_level\": \"Entry Level, Mid-Level, Senior Level, or Executive\",
  \"education_requirements\": \"Specific educational qualifications needed (e.g., 'Bachelor's degree in Computer Science or related field')\"
}

Make it professional, clear, and attractive to qualified candidates. Use proper grammar and formatting. Remember: respond with ONLY the JSON object, nothing else.";

    return $prompt;
}

/**
 * Call Google Gemini API
 */
function callGeminiAPI($prompt) {
    $apiKey = GEMINI_API_KEY;
    $url = GEMINI_API_URL . '?key=' . $apiKey;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 2048,
        ]
    ];
    
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
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        
        // Check for quota/rate limit errors
        if ($httpCode === 429 && isset($errorData['error'])) {
            $errorMsg = $errorData['error']['message'] ?? 'Rate limit exceeded';
            
            // Extract retry time if available
            $retrySeconds = 60; // default
            if (isset($errorData['error']['details'])) {
                foreach ($errorData['error']['details'] as $detail) {
                    if (isset($detail['retryDelay'])) {
                        preg_match('/(\d+)/', $detail['retryDelay'], $matches);
                        if (!empty($matches[1])) {
                            $retrySeconds = (int)$matches[1];
                        }
                    }
                }
            }
            
            // User-friendly error message
            $friendlyMsg = "⏱️ API Rate Limit Reached\n\n";
            $friendlyMsg .= "You've used up your free quota for this model. Options:\n\n";
            $friendlyMsg .= "1. Wait " . ceil($retrySeconds / 60) . " minutes and try again\n";
            $friendlyMsg .= "2. Switch to 'Mock' provider in AI Config (instant, no API needed)\n";
            $friendlyMsg .= "3. Generate a NEW API key at https://makersuite.google.com/app/apikey (fresh quota)\n";
            $friendlyMsg .= "4. Try a different model in AI Config page\n\n";
            $friendlyMsg .= "Current model: " . GEMINI_MODEL;
            
            return ['error' => $friendlyMsg];
        }
        
        return ['error' => 'Gemini API Error: ' . $response];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $result['candidates'][0]['content']['parts'][0]['text'];
        
        // More aggressive cleanup of markdown and extra formatting
        $text = preg_replace('/```json\s*/i', '', $text);  // Remove ```json
        $text = preg_replace('/```\s*$/s', '', $text);      // Remove trailing ```
        $text = preg_replace('/^```\s*/s', '', $text);      // Remove leading ```
        $text = trim($text);
        
        // Try to extract JSON if it's embedded in other text
        if (preg_match('/\{[\s\S]*\}/s', $text, $matches)) {
            $text = $matches[0];
        }
        
        $jobData = json_decode($text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => $jobData];
        } else {
            // Return more detailed error with the actual text for debugging
            return ['error' => 'Failed to parse AI response: ' . json_last_error_msg() . '. Raw text: ' . substr($text, 0, 200)];
        }
    }
    
    return ['error' => 'Invalid response from Gemini API'];
}

/**
 * Call OpenAI API
 */
function callOpenAI($prompt) {
    $apiKey = OPENAI_API_KEY;
    $url = OPENAI_API_URL;
    
    $data = [
        'model' => OPENAI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert HR professional. Always respond with valid JSON only, no markdown formatting.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 2000
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => 'OpenAI API Error: ' . $response];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $text = $result['choices'][0]['message']['content'];
        // Clean up markdown code blocks if present
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*$/', '', $text);
        $text = trim($text);
        
        $jobData = json_decode($text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => $jobData];
        } else {
            return ['error' => 'Failed to parse AI response: ' . json_last_error_msg()];
        }
    }
    
    return ['error' => 'Invalid response from OpenAI API'];
}

/**
 * Call Gemini API specifically for offer letter generation (returns plain text)
 */
function callGeminiForOfferLetter($prompt) {
    $apiKey = GEMINI_API_KEY;
    $url = GEMINI_API_URL . '?key=' . $apiKey;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 2048,
        ]
    ];
    
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
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        
        if ($httpCode === 429) {
            $errorMsg = "⏱️ Gemini API Rate Limit Reached. Please wait a few minutes or switch to Mock mode.";
            return ['error' => $errorMsg];
        }
        
        return ['error' => 'Gemini API Error: ' . $response];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $letter = $result['candidates'][0]['content']['parts'][0]['text'];
        $letter = trim($letter);
        
        return ['success' => true, 'letter' => $letter];
    }
    
    return ['error' => 'Invalid response from Gemini API'];
}

/**
 * Call OpenAI API specifically for offer letter generation (returns plain text)
 */
function callOpenAIForOfferLetter($prompt) {
    $apiKey = OPENAI_API_KEY;
    $url = OPENAI_API_URL;
    
    $data = [
        'model' => OPENAI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert HR professional creating formal job offer letters. Write professional, well-formatted offer letters in plain text format.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 2000
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => 'OpenAI API Error: ' . $response];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $letter = $result['choices'][0]['message']['content'];
        $letter = trim($letter);
        
        return ['success' => true, 'letter' => $letter];
    }
    
    return ['error' => 'Invalid response from OpenAI API'];
}
?>