<?php
// AI Screening Helper - Automated candidate screening using AI
require_once 'ai_config.php';

/**
 * Screen candidate using AI based on job requirements and candidate data
 */
function screenCandidateWithAI($candidateId, $jobOpeningId, $conn) {
    // Get candidate data
    $stmt = $conn->prepare("SELECT c.*, ja.application_id 
                           FROM candidates c 
                           JOIN job_applications ja ON c.candidate_id = ja.candidate_id 
                           WHERE c.candidate_id = ? AND ja.job_opening_id = ?");
    $stmt->bind_param('ii', $candidateId, $jobOpeningId);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    
    if (!$candidate) {
        return ['error' => 'Candidate not found'];
    }
    
    // Get job opening details
    $stmt = $conn->prepare("SELECT jo.*, jr.title as role_title, jr.description as role_description 
                           FROM job_openings jo 
                           LEFT JOIN job_roles jr ON jo.job_role_id = jr.job_role_id 
                           WHERE jo.job_opening_id = ?");
    $stmt->bind_param('i', $jobOpeningId);
    $stmt->execute();
    $result = $stmt->get_result();
    $job = $result->fetch_assoc();
    
    if (!$job) {
        return ['error' => 'Job opening not found'];
    }
    
    // Get PDS data if available
    $stmt = $conn->prepare("SELECT * FROM pds_data WHERE candidate_id = ?");
    $stmt->bind_param('i', $candidateId);
    $stmt->execute();
    $result = $stmt->get_result();
    $pdsData = $result->fetch_assoc();
    
    // Build screening prompt
    $prompt = buildScreeningPrompt($candidate, $job, $pdsData);
    
    // Call AI for screening
    if (SCREENING_AI_PROVIDER === 'mock') {
        logAPIUsage('mock', 'screening');
        return generateMockScreening($candidate, $job);
    } elseif (SCREENING_AI_PROVIDER === 'gemini') {
        $result = callGeminiScreening($prompt);
        if (isset($result['success']) && $result['success']) {
            logAPIUsage('gemini', 'screening');
        }
        return $result;
    } else {
        $result = callOpenAIScreening($prompt);
        if (isset($result['success']) && $result['success']) {
            logAPIUsage('openai', 'screening');
        }
        return $result;
    }
}

/**
 * Build AI screening prompt with difficulty level
 */
function buildScreeningPrompt($candidate, $job, $pdsData) {
    // Build comprehensive candidate profile
    $candidateInfo = "CANDIDATE PROFILE:\n";
    $candidateInfo .= "Name: {$candidate['first_name']} {$candidate['last_name']}\n";
    $candidateInfo .= "Email: {$candidate['email']}\n";
    $candidateInfo .= "Phone: {$candidate['phone']}\n";
    
    if (!empty($candidate['current_position'])) {
        $candidateInfo .= "Current Position: {$candidate['current_position']}\n";
    }
    if (!empty($candidate['current_company'])) {
        $candidateInfo .= "Current Company: {$candidate['current_company']}\n";
    }
    if (!empty($candidate['expected_salary'])) {
        $candidateInfo .= "Expected Salary: ₱" . number_format($candidate['expected_salary']) . "\n";
    }
    
    // Add PDS education data
    if ($pdsData && !empty($pdsData['education_data'])) {
        $education = json_decode($pdsData['education_data'], true);
        if (is_array($education) && count($education) > 0) {
            $candidateInfo .= "\nEDUCATION:\n";
            foreach ($education as $edu) {
                $candidateInfo .= "- {$edu['level']}: {$edu['degree']} at {$edu['school']}";
                if (!empty($edu['year_graduated'])) {
                    $candidateInfo .= " (Graduated: {$edu['year_graduated']})";
                }
                $candidateInfo .= "\n";
            }
        }
    }
    
    // Add PDS work experience
    if ($pdsData && !empty($pdsData['work_experience_data'])) {
        $experience = json_decode($pdsData['work_experience_data'], true);
        if (is_array($experience) && count($experience) > 0) {
            $candidateInfo .= "\nWORK EXPERIENCE:\n";
            foreach ($experience as $exp) {
                $candidateInfo .= "- {$exp['position']} at {$exp['company']}";
                if (!empty($exp['from_date']) && !empty($exp['to_date'])) {
                    $candidateInfo .= " ({$exp['from_date']} to {$exp['to_date']})";
                }
                $candidateInfo .= "\n";
            }
        }
    }
    
    // Add skills
    if ($pdsData && !empty($pdsData['skills_data'])) {
        $skills = json_decode($pdsData['skills_data'], true);
        if (is_array($skills) && count($skills) > 0) {
            $candidateInfo .= "\nSKILLS: " . implode(", ", $skills) . "\n";
        }
    }
    
    // Build job requirements
    $jobInfo = "JOB POSITION:\n";
    $jobInfo .= "Title: {$job['title']}\n";
    $jobInfo .= "Experience Level: {$job['experience_level']}\n";
    $jobInfo .= "Education: {$job['education_requirements']}\n\n";
    $jobInfo .= "REQUIREMENTS:\n{$job['requirements']}\n\n";
    $jobInfo .= "RESPONSIBILITIES:\n{$job['responsibilities']}\n";
    
    // Get screening level (default to Moderate if not set)
    $screeningLevel = $job['screening_level'] ?? 'Moderate';
    
    // Adjust scoring philosophy based on difficulty level
    $scoringPhilosophy = "";
    $scoringGuide = "";
    
    switch ($screeningLevel) {
        case 'Easy':
            $scoringPhilosophy = "SCREENING LEVEL: EASY (Inclusive - Focus on Potential)\n";
            $scoringPhilosophy .= "- Prioritize POTENTIAL over perfect qualifications\n";
            $scoringPhilosophy .= "- Give strong credit for transferable skills and willingness to learn\n";
            $scoringPhilosophy .= "- Consider candidates who meet 50%+ of requirements\n";
            $scoringPhilosophy .= "- Value attitude, motivation, and growth mindset highly\n";
            $scoringPhilosophy .= "- Be encouraging and identify training opportunities\n";
            
            $scoringGuide = "SCORING GUIDE - EASY LEVEL (0-100):\n";
            $scoringGuide .= "- 75-100: Strong potential - Recommend for interview\n";
            $scoringGuide .= "- 60-74: Good potential - Consider for interview\n";
            $scoringGuide .= "- 50-59: Acceptable with training - Possible candidate\n";
            $scoringGuide .= "- 40-49: Marginal - May need significant development\n";
            $scoringGuide .= "- Below 40: Not recommended for this role\n";
            $scoringGuide .= "IMPORTANT: Most candidates should score 60-80. Be generous with potential.";
            break;
            
        case 'Strict':
            $scoringPhilosophy = "SCREENING LEVEL: STRICT (Selective - Focus on Qualifications)\n";
            $scoringPhilosophy .= "- Require strong match to stated qualifications\n";
            $scoringPhilosophy .= "- Prioritize proven experience and exact skill matches\n";
            $scoringPhilosophy .= "- Candidates should meet 80%+ of requirements\n";
            $scoringPhilosophy .= "- Look for demonstrated excellence and achievements\n";
            $scoringPhilosophy .= "- Be thorough in identifying any gaps or concerns\n";
            
            $scoringGuide = "SCORING GUIDE - STRICT LEVEL (0-100):\n";
            $scoringGuide .= "- 85-100: Exceptional - Exceeds requirements significantly\n";
            $scoringGuide .= "- 75-84: Strong - Meets all key requirements well\n";
            $scoringGuide .= "- 65-74: Good - Meets most requirements adequately\n";
            $scoringGuide .= "- 55-64: Acceptable - Meets minimum requirements\n";
            $scoringGuide .= "- Below 55: Not recommended - Significant gaps\n";
            $scoringGuide .= "IMPORTANT: Only truly qualified candidates should score 75+. Be thorough.";
            break;
            
        default: // Moderate
            $scoringPhilosophy = "SCREENING LEVEL: MODERATE (Balanced - Realistic Standards)\n";
            $scoringPhilosophy .= "- Balance POTENTIAL and QUALIFICATIONS\n";
            $scoringPhilosophy .= "- Consider transferable skills and relevant experience\n";
            $scoringPhilosophy .= "- Candidates should meet 65%+ of requirements\n";
            $scoringPhilosophy .= "- Value both proven skills and growth potential\n";
            $scoringPhilosophy .= "- Be fair and objective in assessment\n";
            
            $scoringGuide = "SCORING GUIDE - MODERATE LEVEL (0-100):\n";
            $scoringGuide .= "- 85-100: Exceptional - Exceeds requirements, ready to excel\n";
            $scoringGuide .= "- 75-84: Strong - Meets most requirements, good potential\n";
            $scoringGuide .= "- 65-74: Good - Meets core requirements, trainable\n";
            $scoringGuide .= "- 55-64: Acceptable - Meets minimum, needs development\n";
            $scoringGuide .= "- 45-54: Marginal - Some gaps but has potential\n";
            $scoringGuide .= "- Below 45: Not recommended - Significant gaps\n";
            $scoringGuide .= "IMPORTANT: Most good candidates score 65-80. Be realistic and fair.";
            break;
    }
    
    $prompt = "You are a realistic HR screening AI for government positions. Analyze this candidate with the specified screening level.

{$candidateInfo}

{$jobInfo}

{$scoringPhilosophy}

INSTRUCTIONS:
1. Apply the screening level standards consistently
2. Compare candidate's qualifications to job requirements
3. Be objective and fair - no bias based on name, gender, or background
4. Identify both strengths AND areas for development
5. Generate practical interview questions

Respond with ONLY this JSON (no markdown, no explanations):

{
  \"overall_score\": 0,
  \"recommendation\": \"string\",
  \"qualifications_score\": 0,
  \"experience_score\": 0,
  \"skills_score\": 0,
  \"communication_score\": 0,
  \"strengths\": [\"actual strength from data\", \"another strength\"],
  \"concerns\": [\"area for development if any\", \"another area\"],
  \"interview_questions\": [\"relevant question?\", \"another question?\", \"third question?\"],
  \"summary\": \"Balanced 2-sentence analysis focusing on fit for this role.\"
}

{$scoringGuide}";

    return $prompt;
}

/**
 * Generate mock screening (for testing)
 */
function generateMockScreening($candidate, $job) {
    // Generate varied scores based on candidate name for more realistic testing
    $nameHash = crc32($candidate['first_name'] . $candidate['last_name']);
    $baseScore = 70 + ($nameHash % 25); // Score between 70-95
    
    $qualScore = min(95, $baseScore + rand(-5, 10));
    $expScore = min(95, $baseScore + rand(-8, 5));
    $skillScore = min(95, $baseScore + rand(-5, 8));
    $commScore = min(95, $baseScore + rand(-3, 7));
    
    $overallScore = round(($qualScore + $expScore + $skillScore + $commScore) / 4);
    
    // Determine recommendation based on score
    if ($overallScore >= 85) {
        $recommendation = 'Exceptional - Highly Recommended';
    } elseif ($overallScore >= 75) {
        $recommendation = 'Strong Candidate - Recommended';
    } elseif ($overallScore >= 65) {
        $recommendation = 'Good Candidate - Interview';
    } elseif ($overallScore >= 55) {
        $recommendation = 'Acceptable - Consider';
    } else {
        $recommendation = 'Needs Further Review';
    }
    
    // Generate realistic strengths
    $allStrengths = [
        'Strong educational background with relevant degree',
        'Extensive work experience in similar roles',
        'Excellent communication and interpersonal skills',
        'Demonstrated leadership capabilities',
        'Strong technical skills matching job requirements',
        'Proven track record of successful projects',
        'Good problem-solving abilities',
        'Team collaboration experience',
        'Adaptable to new technologies and processes',
        'Strong analytical and critical thinking skills'
    ];
    
    // Generate realistic concerns
    $allConcerns = [
        'Limited experience with specific tools mentioned in requirements',
        'Expected salary slightly above budget range',
        'Gap in employment history needs clarification',
        'May need additional training in certain areas',
        'Limited supervisory experience',
        'Relocation may be required',
        'Availability date is later than preferred',
        'Some required certifications are pending'
    ];
    
    // Generate interview questions
    $allQuestions = [
        'Can you describe your experience with ' . $job['title'] . ' responsibilities?',
        'How do you handle tight deadlines and multiple priorities?',
        'Tell us about a challenging project you completed successfully',
        'What interests you most about this position?',
        'How do you stay updated with industry trends and best practices?',
        'Describe a situation where you had to work with a difficult team member',
        'What are your long-term career goals?',
        'How would you approach the first 90 days in this role?'
    ];
    
    // Select random items
    shuffle($allStrengths);
    shuffle($allConcerns);
    shuffle($allQuestions);
    
    $strengths = array_slice($allStrengths, 0, rand(3, 5));
    $concerns = array_slice($allConcerns, 0, rand(2, 4));
    $questions = array_slice($allQuestions, 0, 3);
    
    return [
        'success' => true,
        'data' => [
            'overall_score' => $overallScore,
            'recommendation' => $recommendation,
            'qualifications_score' => $qualScore,
            'experience_score' => $expScore,
            'skills_score' => $skillScore,
            'communication_score' => $commScore,
            'strengths' => $strengths,
            'concerns' => $concerns,
            'interview_questions' => $questions,
            'summary' => "Based on the analysis, {$candidate['first_name']} {$candidate['last_name']} demonstrates strong potential for the {$job['title']} position. The candidate's qualifications align well with the job requirements, showing particular strength in relevant experience and skills. Recommended for interview to further assess cultural fit and discuss specific technical competencies."
        ]
    ];
}

/**
 * Call Gemini API for screening
 */
function callGeminiScreening($prompt) {
    $apiKey = SCREENING_GEMINI_API_KEY;
    
    // Check if API key is set
    if (empty($apiKey)) {
        return ['error' => 'Screening Gemini API key is not configured. Please visit ai_config_page.php to add your API key.'];
    }
    
    $url = SCREENING_GEMINI_API_URL . '?key=' . $apiKey;
    
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
            'maxOutputTokens' => 4096, // Increased from 2048
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['error' => 'Connection error: ' . $curlError];
    }
    
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
        
        return ['error' => 'Gemini API Error (HTTP ' . $httpCode . '): ' . substr($response, 0, 200)];
    }
    
    $result = json_decode($response, true);
    
    // Check for API errors
    if (isset($result['error'])) {
        return ['error' => 'Gemini API Error: ' . $result['error']['message']];
    }
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $result['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean up markdown and control characters
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*$/s', '', $text);
        $text = preg_replace('/^```\s*/s', '', $text);
        
        // Remove control characters (0x00-0x1F except tab, newline, carriage return)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
        
        // Remove any non-printable characters
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D\x09]/', '', $text);
        
        $text = trim($text);
        
        // Extract JSON if it's embedded in other text
        if (preg_match('/\{[\s\S]*\}/s', $text, $matches)) {
            $text = $matches[0];
        }
        
        // Check if JSON looks complete (has closing brace)
        if (!preg_match('/\}[\s]*$/', $text)) {
            return ['error' => 'AI response was truncated. Try using mock provider or check API limits. Sample: ' . substr($text, 0, 200)];
        }
        
        // Try to decode
        $screeningData = json_decode($text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // Validate required fields
            $requiredFields = ['overall_score', 'recommendation', 'qualifications_score', 
                              'experience_score', 'skills_score', 'communication_score',
                              'strengths', 'concerns', 'interview_questions', 'summary'];
            
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($screeningData[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                return ['error' => 'AI response missing fields: ' . implode(', ', $missingFields) . '. Try using mock provider.'];
            }
            
            return ['success' => true, 'data' => $screeningData];
        } else {
            // Return more detailed error with cleaned text sample
            $errorMsg = json_last_error_msg();
            $textSample = substr($text, 0, 500);
            return ['error' => "Failed to parse AI response: {$errorMsg}. Try using mock provider. Sample: {$textSample}"];
        }
    }
    
    return ['error' => 'Invalid response from Gemini API. Try using mock provider.'];
}

/**
 * Call OpenAI API for screening
 */
function callOpenAIScreening($prompt) {
    $apiKey = SCREENING_OPENAI_API_KEY;
    
    // Check if API key is set
    if (empty($apiKey)) {
        return ['error' => 'Screening OpenAI API key is not configured. Please visit ai_config_page.php to add your API key.'];
    }
    
    $url = OPENAI_API_URL;
    
    $data = [
        'model' => SCREENING_OPENAI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert HR recruiter. Always respond with valid JSON only, no markdown formatting.'
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
        
        // Clean up markdown and control characters
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*$/', '', $text);
        
        // Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D\x09]/', '', $text);
        
        $text = trim($text);
        
        // Extract JSON
        if (preg_match('/\{[\s\S]*\}/s', $text, $matches)) {
            $text = $matches[0];
        }
        
        $screeningData = json_decode($text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // Validate required fields
            $requiredFields = ['overall_score', 'recommendation', 'qualifications_score', 
                              'experience_score', 'skills_score', 'communication_score',
                              'strengths', 'concerns', 'interview_questions', 'summary'];
            
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($screeningData[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                return ['error' => 'AI response missing fields: ' . implode(', ', $missingFields)];
            }
            
            return ['success' => true, 'data' => $screeningData];
        } else {
            $errorMsg = json_last_error_msg();
            $textSample = substr($text, 0, 300);
            return ['error' => "Failed to parse AI response: {$errorMsg}. Sample: {$textSample}"];
        }
    }
    
    return ['error' => 'Invalid response from OpenAI API'];
}
?>
