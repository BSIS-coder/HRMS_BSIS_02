<?php
require_once 'ai_config.php';

function extractPDSWithAI($pdsFileBlob, $fileExt) {
    if ($fileExt === 'json') {
        $pdsData = json_decode($pdsFileBlob, true);
        if ($pdsData && isset($pdsData['first_name']) && isset($pdsData['last_name']) && isset($pdsData['email'])) {
            return ['success' => true, 'data' => $pdsData];
        }
        return ['success' => false, 'error' => 'Invalid JSON format'];
    }
    
    // Determine content type and prepare for AI
    $contentType = '';
    $pdsContent = '';
    
    if ($fileExt === 'pdf') {
        // Try text extraction first
        $tempFile = sys_get_temp_dir() . '/pds_temp_' . uniqid() . '.pdf';
        file_put_contents($tempFile, $pdsFileBlob);
        $pdfText = extractPDFText($tempFile);
        unlink($tempFile);
        
        if ($pdfText && strlen($pdfText) > 100) {
            $pdsContent = $pdfText;
            $contentType = 'pdf_text';
        } else {
            $pdsContent = base64_encode($pdsFileBlob);
            $contentType = 'pdf_image';
        }
    } else {
        $pdsContent = base64_encode($pdsFileBlob);
        $contentType = 'document_image';
    }
    
    $prompt = buildPDSExtractionPrompt($pdsContent, $contentType);
    
    if (SCREENING_AI_PROVIDER === 'mock') {
        logAPIUsage('mock', 'pds_extraction');
        return generateMockPDSExtraction();
    } elseif (SCREENING_AI_PROVIDER === 'gemini') {
        $result = callGeminiForPDS($prompt, $contentType, $pdsFileBlob, $fileExt);
        if (isset($result['success']) && $result['success']) {
            logAPIUsage('gemini', 'pds_extraction');
        }
        return $result;
    } else {
        $result = callOpenAIForPDS($prompt, $contentType, $pdsFileBlob, $fileExt);
        if (isset($result['success']) && $result['success']) {
            logAPIUsage('openai', 'pds_extraction');
        }
        return $result;
    }
}

function extractPDFText($pdfFile) {
    $output = [];
    $return_var = 0;
    exec('pdftotext -v 2>&1', $output, $return_var);
    
    if ($return_var === 0 || $return_var === 1) {
        $txtFile = $pdfFile . '.txt';
        exec("pdftotext \"$pdfFile\" \"$txtFile\" 2>&1", $output, $return_var);
        
        if (file_exists($txtFile)) {
            $text = file_get_contents($txtFile);
            unlink($txtFile);
            return $text;
        }
    }
    return '';
}

function buildPDSExtractionPrompt($content, $contentType) {
    if ($contentType === 'json') {
        return "You are an expert data extraction AI. Extract and structure the following PDS (Personal Data Sheet) JSON data.\n\nPDS Content:\n{$content}\n\nCRITICAL: Respond with ONLY a valid JSON object. No markdown, no code blocks, no explanatory text.\n\nExtract and return this exact structure:\n" . getPDSStructure();
    } else if ($contentType === 'pdf_text') {
        return "You are an expert data extraction AI. Extract and structure data from this PDS (Personal Data Sheet) text extracted from PDF.\n\nPDS Text Content:\n{$content}\n\nCRITICAL: Respond with ONLY a valid JSON object. No markdown, no code blocks, no explanatory text.\n\nThis is a Philippine Civil Service Form No. 212 (Personal Data Sheet). Extract all information and return in this exact JSON structure:\n" . getPDSStructure();
    } else {
        return "You are viewing a PDS (Personal Data Sheet) - Philippine Civil Service Form No. 212. This is an official government form with structured fields.\n\nAnalyze the document image and extract ALL information into this exact JSON structure:\n" . getPDSStructure() . "\n\nCRITICAL: Respond with ONLY the JSON object. No markdown, no explanations, just pure JSON starting with { and ending with }.";
    }
}

function getPDSStructure() {
    return '{
  "first_name": "string",
  "last_name": "string",
  "middle_name": "string",
  "email": "string",
  "phone": "string",
  "address": "string",
  "personal_info": {
    "date_of_birth": "YYYY-MM-DD",
    "place_of_birth": "string",
    "gender": "Male/Female",
    "civil_status": "Single/Married/Widowed/Separated",
    "citizenship": "string",
    "height": "string",
    "weight": "string",
    "blood_type": "string",
    "gsis_id": "string",
    "pagibig_id": "string",
    "philhealth_no": "string",
    "sss_no": "string",
    "tin_no": "string"
  },
  "education": [
    {
      "level": "Elementary/Secondary/College/Graduate",
      "school": "School name",
      "degree": "Course/Degree",
      "year_graduated": "Year"
    }
  ],
  "work_experience": [
    {
      "position": "Job title",
      "company": "Company name",
      "from_date": "YYYY-MM-DD",
      "to_date": "YYYY-MM-DD or Present",
      "salary": "Monthly salary"
    }
  ],
  "skills": ["skill1", "skill2"],
  "certifications": [
    {
      "name": "Certification name",
      "issuer": "Issuing organization",
      "date": "YYYY-MM"
    }
  ],
  "trainings": [
    {
      "title": "Training title",
      "organizer": "Conducting organization",
      "date": "YYYY-MM"
    }
  ],
  "references": [
    {
      "name": "Reference name",
      "address": "Address",
      "contact": "Contact number"
    }
  ]
}';
}

function generateMockPDSExtraction() {
    return [
        'success' => true,
        'data' => [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.delacruz' . rand(1000, 9999) . '@example.com',
            'phone' => '09171234567',
            'address' => 'Manila, Philippines',
            'personal_info' => ['date_of_birth' => '1990-01-01', 'gender' => 'Male', 'civil_status' => 'Single'],
            'education' => [['level' => 'College', 'school' => 'University of the Philippines', 'degree' => 'BS Computer Science', 'year_graduated' => '2012']],
            'work_experience' => [['position' => 'Software Developer', 'company' => 'Tech Corp', 'from_date' => '2012-06', 'to_date' => '2020-12']],
            'skills' => ['PHP', 'JavaScript', 'MySQL']
        ]
    ];
}

function callGeminiForPDS($prompt, $contentType, $fileBlob = null, $fileExt = null) {
    $apiKey = SCREENING_GEMINI_API_KEY;
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'Gemini API key not configured'];
    }
    
    $url = SCREENING_GEMINI_API_URL . '?key=' . $apiKey;
    
    if ($contentType === 'pdf_image' || $contentType === 'document_image') {
        $base64Data = base64_encode($fileBlob);
        $mimeType = ($fileExt === 'pdf') ? 'application/pdf' : 'image/jpeg';
        
        $data = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Data]]
                ]
            ]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 8192]
        ];
    } else {
        $data = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 8192]
        ];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['error' => 'CURL Error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        return ['error' => 'Gemini API Error (HTTP ' . $httpCode . '): ' . substr($response, 0, 500)];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $result['candidates'][0]['content']['parts'][0]['text'];
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*$/s', '', $text);
        $text = preg_replace('/^```\s*/s', '', $text);
        $text = trim($text);
        
        if (preg_match('/\{[\s\S]*\}/s', $text, $matches)) {
            $text = $matches[0];
        }
        
        $pdsData = json_decode($text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => $pdsData];
        } else {
            return ['error' => 'Failed to parse AI response: ' . json_last_error_msg()];
        }
    }
    
    if (isset($result['error'])) {
        return ['error' => 'Gemini API Error: ' . json_encode($result['error'])];
    }
    
    return ['error' => 'Invalid response from Gemini API'];
}

function callOpenAIForPDS($prompt, $contentType, $fileBlob = null, $fileExt = null) {
    $apiKey = SCREENING_OPENAI_API_KEY;
    if (empty($apiKey)) {
        return ['success' => false, 'error' => 'OpenAI API key not configured'];
    }
    
    $url = OPENAI_API_URL;
    $data = [
        'model' => SCREENING_OPENAI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a PDS data extractor. Always respond with valid JSON only.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.3,
        'max_tokens' => 4000
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'OpenAI extraction failed'];
    }
    
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        $text = $result['choices'][0]['message']['content'];
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*$/', '', $text);
        $text = trim($text);
        
        if (preg_match('/\{[\s\S]*\}/s', $text, $matches)) {
            $text = $matches[0];
        }
        
        $pdsData = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => $pdsData];
        }
    }
    
    return ['success' => false, 'error' => 'Failed to parse OpenAI response'];
}
?>
