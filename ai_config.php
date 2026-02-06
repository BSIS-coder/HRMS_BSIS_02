<?php
// AI Configuration File
// Switch between different AI providers easily

// AI Provider: 'mock', 'gemini' or 'openai'
// Use 'mock' for testing without API keys
define('AI_PROVIDER', 'mock');

// Google Gemini Configuration (FREE tier)
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE'); // Get from: https://makersuite.google.com/app/apikey
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent');

// OpenAI Configuration (Paid - better quality)
define('OPENAI_API_KEY', 'YOUR_OPENAI_API_KEY_HERE'); // Get from: https://platform.openai.com/api-keys
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('OPENAI_MODEL', 'gpt-3.5-turbo'); // or 'gpt-4' for better quality

/**
 * Generate job description using AI
 */
function generateJobWithAI($jobRoleTitle, $jobRoleDescription, $departmentName, $employmentType = 'Full-time', $salaryMin = null, $salaryMax = null) {
    $prompt = buildJobPrompt($jobRoleTitle, $jobRoleDescription, $departmentName, $employmentType, $salaryMin, $salaryMax);
    
    if (AI_PROVIDER === 'mock') {
        return generateMockJob($jobRoleTitle, $jobRoleDescription, $departmentName, $employmentType);
    } elseif (AI_PROVIDER === 'gemini') {
        return callGeminiAPI($prompt);
    } else {
        return callOpenAI($prompt);
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

Create a comprehensive job opening with the following sections. Return ONLY valid JSON with no markdown formatting:

{
  \"title\": \"Professional job title (e.g., 'Senior Software Developer - IT Department')\",
  \"description\": \"Compelling 2-3 paragraph job description that explains the role, its importance, and what the candidate will do\",
  \"requirements\": \"Detailed bullet-point list of qualifications, skills, and experience needed. Include education, certifications, technical skills, and soft skills. Format as bullet points with • symbol\",
  \"responsibilities\": \"Detailed bullet-point list of key duties and day-to-day responsibilities. Be specific and actionable. Format as bullet points with • symbol\",
  \"experience_level\": \"Entry Level, Mid-Level, Senior Level, or Executive\",
  \"education_requirements\": \"Specific educational qualifications needed (e.g., 'Bachelor's degree in Computer Science or related field')\"
}

Make it professional, clear, and attractive to qualified candidates. Use proper grammar and formatting.";

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
        return ['error' => 'Gemini API Error: ' . $response];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $result['candidates'][0]['content']['parts'][0]['text'];
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
?>