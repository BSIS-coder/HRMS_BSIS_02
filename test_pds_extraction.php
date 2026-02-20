<?php
require_once 'config.php';
require_once 'ai_config.php';

// Get all candidates with PDS files
$stmt = $conn->prepare("
    SELECT 
        c.candidate_id,
        c.first_name,
        c.last_name,
        c.email,
        p.pds_id,
        p.pds_file_name,
        p.pds_file_type,
        p.pds_file_size,
        p.pds_file_blob,
        p.surname,
        p.date_of_birth,
        p.education,
        p.work_experience
    FROM candidates c
    LEFT JOIN pds_data p ON c.candidate_id = p.candidate_id
    WHERE p.pds_file_blob IS NOT NULL
    ORDER BY c.candidate_id DESC
");
$stmt->execute();
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle extraction request
$extractionResult = null;
$extractionSteps = [];
$selectedCandidate = null;

if (isset($_POST['extract_pds']) && isset($_POST['candidate_id'])) {
    $candidateId = $_POST['candidate_id'];
    
    // Step 1: Retrieve candidate and PDS data
    $extractionSteps[] = ['step' => 1, 'title' => 'Retrieving PDS from Database', 'status' => 'processing'];
    
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            p.*
        FROM candidates c
        LEFT JOIN pds_data p ON c.candidate_id = p.candidate_id
        WHERE c.candidate_id = ?
    ");
    $stmt->execute([$candidateId]);
    $selectedCandidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedCandidate && $selectedCandidate['pds_file_blob']) {
        $extractionSteps[0]['status'] = 'success';
        $extractionSteps[0]['details'] = "Retrieved PDS file: {$selectedCandidate['pds_file_name']} ({$selectedCandidate['pds_file_type']}, " . number_format($selectedCandidate['pds_file_size']) . " bytes)";
        
        // Step 2: Convert BLOB to readable format
        $extractionSteps[] = ['step' => 2, 'title' => 'Converting BLOB to File', 'status' => 'processing'];
        
        $fileExt = strtolower(pathinfo($selectedCandidate['pds_file_name'], PATHINFO_EXTENSION));
        $tempFile = sys_get_temp_dir() . '/pds_temp_' . $candidateId . '.' . $fileExt;
        file_put_contents($tempFile, $selectedCandidate['pds_file_blob']);
        
        $extractionSteps[1]['status'] = 'success';
        $extractionSteps[1]['details'] = "Temporary file created: " . basename($tempFile);
        
        // Step 3: Extract text content
        $extractionSteps[] = ['step' => 3, 'title' => 'Extracting Content', 'status' => 'processing'];
        
        $pdsContent = '';
        $contentType = '';
        
        if ($fileExt === 'json') {
            $pdsContent = file_get_contents($tempFile);
            $contentType = 'json';
            $extractionSteps[2]['status'] = 'success';
            $extractionSteps[2]['details'] = "JSON content extracted (" . strlen($pdsContent) . " characters)";
        } else if ($fileExt === 'pdf') {
            // Try to extract text from PDF using pdftotext or similar
            $pdfText = extractPDFText($tempFile);
            
            if ($pdfText && strlen($pdfText) > 100) {
                $pdsContent = $pdfText;
                $contentType = 'pdf_text';
                $extractionSteps[2]['status'] = 'success';
                $extractionSteps[2]['details'] = "PDF text extracted (" . strlen($pdsContent) . " characters). Using AI vision for better extraction.";
            } else {
                // Fallback: Use PDF as image for AI vision
                $pdsContent = base64_encode($selectedCandidate['pds_file_blob']);
                $contentType = 'pdf_image';
                $extractionSteps[2]['status'] = 'success';
                $extractionSteps[2]['details'] = "PDF converted to base64 for AI vision processing (" . strlen($pdsContent) . " characters)";
            }
        } else {
            $extractionSteps[2]['status'] = 'info';
            $extractionSteps[2]['details'] = "Document format: {$fileExt}. Using base64 encoding for AI processing.";
            $pdsContent = base64_encode($selectedCandidate['pds_file_blob']);
            $contentType = 'document_image';
        }
        
        // Step 4: Prepare AI prompt
        $extractionSteps[] = ['step' => 4, 'title' => 'Preparing AI Extraction Prompt', 'status' => 'processing'];
        
        $prompt = buildPDSExtractionPrompt($pdsContent, $contentType);
        $extractionSteps[3]['status'] = 'success';
        $extractionSteps[3]['details'] = "Prompt prepared (" . strlen($prompt) . " characters) for content type: {$contentType}";
        
        // Step 5: Call AI API
        $extractionSteps[] = ['step' => 5, 'title' => 'Calling AI API (' . AI_PROVIDER . ')', 'status' => 'processing'];
        
        $aiResult = extractPDSWithAI($prompt, $contentType, $selectedCandidate['pds_file_blob'], $fileExt);
        
        if (isset($aiResult['success']) && $aiResult['success']) {
            $extractionSteps[4]['status'] = 'success';
            $extractionSteps[4]['details'] = "AI extraction successful. Received structured data.";
            
            // Step 6: Update database
            $extractionSteps[] = ['step' => 6, 'title' => 'Updating Database', 'status' => 'processing'];
            
            $updateResult = updatePDSData($conn, $candidateId, $aiResult['data']);
            
            if ($updateResult['success']) {
                $extractionSteps[5]['status'] = 'success';
                $extractionSteps[5]['details'] = "Database updated successfully. {$updateResult['fields_updated']} fields populated.";
                $extractionResult = ['success' => true, 'data' => $aiResult['data']];
            } else {
                $extractionSteps[5]['status'] = 'error';
                $extractionSteps[5]['details'] = "Database update failed: " . $updateResult['error'];
                $extractionResult = ['error' => $updateResult['error']];
            }
        } else {
            $extractionSteps[4]['status'] = 'error';
            $extractionSteps[4]['details'] = "AI extraction failed: " . ($aiResult['error'] ?? 'Unknown error');
            $extractionResult = ['error' => $aiResult['error'] ?? 'Unknown error'];
        }
        
        // Cleanup
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    } else {
        $extractionSteps[0]['status'] = 'error';
        $extractionSteps[0]['details'] = "No PDS file found for this candidate";
        $extractionResult = ['error' => 'No PDS file found'];
    }
}

/**
 * Extract text from PDF (simple method)
 */
function extractPDFText($pdfFile) {
    // Try using pdftotext command if available
    $output = [];
    $return_var = 0;
    
    // Check if pdftotext is available
    exec('pdftotext -v 2>&1', $output, $return_var);
    
    if ($return_var === 0 || $return_var === 1) {
        // pdftotext is available
        $txtFile = $pdfFile . '.txt';
        exec("pdftotext \"$pdfFile\" \"$txtFile\" 2>&1", $output, $return_var);
        
        if (file_exists($txtFile)) {
            $text = file_get_contents($txtFile);
            unlink($txtFile);
            return $text;
        }
    }
    
    // Fallback: return empty to trigger image-based extraction
    return '';
}

/**
 * Build prompt for PDS extraction
 */
function buildPDSExtractionPrompt($content, $contentType) {
    if ($contentType === 'json') {
        $prompt = "You are an expert data extraction AI. Extract and structure the following PDS (Personal Data Sheet) JSON data.

PDS Content:
{$content}

CRITICAL: Respond with ONLY a valid JSON object. No markdown, no code blocks, no explanatory text.

Extract and return this exact structure:
{
  \"personal_info\": {
    \"surname\": \"Last name\",
    \"first_name\": \"First name\",
    \"middle_name\": \"Middle name\",
    \"name_extension\": \"Jr/Sr/III etc\",
    \"date_of_birth\": \"YYYY-MM-DD\",
    \"place_of_birth\": \"City/Province\",
    \"sex\": \"Male or Female\",
    \"civil_status\": \"Single/Married/Widowed/Separated\",
    \"height\": \"Height in meters\",
    \"weight\": \"Weight in kg\",
    \"blood_type\": \"Blood type\"
  },
  \"contact_info\": {
    \"email\": \"Email address\",
    \"mobile\": \"Mobile number\",
    \"telephone\": \"Telephone number\"
  },
  \"address\": {
    \"residential_address\": \"Full address\",
    \"residential_city\": \"City\",
    \"residential_province\": \"Province\",
    \"residential_zipcode\": \"Zip code\"
  },
  \"government_ids\": {
    \"gsis_id\": \"GSIS ID\",
    \"pagibig_id\": \"Pag-IBIG ID\",
    \"philhealth_no\": \"PhilHealth number\",
    \"sss_no\": \"SSS number\",
    \"tin_no\": \"TIN number\"
  },
  \"education\": [
    {
      \"level\": \"Elementary/Secondary/College/Graduate\",
      \"school\": \"School name\",
      \"course\": \"Course/Degree\",
      \"year_graduated\": \"Year\"
    }
  ],
  \"work_experience\": [
    {
      \"position\": \"Job title\",
      \"company\": \"Company name\",
      \"from_date\": \"YYYY-MM-DD\",
      \"to_date\": \"YYYY-MM-DD or Present\",
      \"salary\": \"Monthly salary\"
    }
  ],
  \"skills\": \"Special skills and hobbies\",
  \"references\": [
    {
      \"name\": \"Reference name\",
      \"address\": \"Address\",
      \"telephone\": \"Contact number\"
    }
  ]
}

Extract all available data. Use null for missing fields.";
    } else if ($contentType === 'pdf_text') {
        $prompt = "You are an expert data extraction AI. Extract and structure data from this PDS (Personal Data Sheet) text extracted from PDF.

PDS Text Content:
{$content}

CRITICAL: Respond with ONLY a valid JSON object. No markdown, no code blocks, no explanatory text.

This is a Philippine Civil Service Form No. 212 (Personal Data Sheet). Extract all information and return in this exact JSON structure:

{
  \"personal_info\": {
    \"surname\": \"Last name\",
    \"first_name\": \"First name\",
    \"middle_name\": \"Middle name\",
    \"name_extension\": \"Jr/Sr/III etc\",
    \"date_of_birth\": \"YYYY-MM-DD\",
    \"place_of_birth\": \"City/Province\",
    \"sex\": \"Male or Female\",
    \"civil_status\": \"Single/Married/Widowed/Separated\",
    \"height\": \"Height in meters\",
    \"weight\": \"Weight in kg\",
    \"blood_type\": \"Blood type\"
  },
  \"contact_info\": {
    \"email\": \"Email address\",
    \"mobile\": \"Mobile number\",
    \"telephone\": \"Telephone number\"
  },
  \"address\": {
    \"residential_address\": \"Full address\",
    \"residential_city\": \"City\",
    \"residential_province\": \"Province\",
    \"residential_zipcode\": \"Zip code\"
  },
  \"government_ids\": {
    \"gsis_id\": \"GSIS ID\",
    \"pagibig_id\": \"Pag-IBIG ID\",
    \"philhealth_no\": \"PhilHealth number\",
    \"sss_no\": \"SSS number\",
    \"tin_no\": \"TIN number\"
  },
  \"education\": [
    {
      \"level\": \"Elementary/Secondary/College/Graduate\",
      \"school\": \"School name\",
      \"course\": \"Course/Degree\",
      \"year_graduated\": \"Year\"
    }
  ],
  \"work_experience\": [
    {
      \"position\": \"Job title\",
      \"company\": \"Company name\",
      \"from_date\": \"YYYY-MM-DD\",
      \"to_date\": \"YYYY-MM-DD or Present\",
      \"salary\": \"Monthly salary\"
    }
  ],
  \"skills\": \"Special skills and hobbies\",
  \"references\": [
    {
      \"name\": \"Reference name\",
      \"address\": \"Address\",
      \"telephone\": \"Contact number\"
    }
  ]
}

Extract all available data. Use null for missing fields.";
    } else {
        // For PDF images or other documents
        $prompt = "You are viewing a PDS (Personal Data Sheet) - Philippine Civil Service Form No. 212. This is an official government form with structured fields.

Analyze the document image and extract ALL information into this exact JSON structure:

{
  \"personal_info\": {
    \"surname\": \"Last name\",
    \"first_name\": \"First name\",
    \"middle_name\": \"Middle name\",
    \"name_extension\": \"Jr/Sr/III etc\",
    \"date_of_birth\": \"YYYY-MM-DD\",
    \"place_of_birth\": \"City/Province\",
    \"sex\": \"Male or Female\",
    \"civil_status\": \"Single/Married/Widowed/Separated\",
    \"height\": \"Height in meters\",
    \"weight\": \"Weight in kg\",
    \"blood_type\": \"Blood type\"
  },
  \"contact_info\": {
    \"email\": \"Email address\",
    \"mobile\": \"Mobile number\",
    \"telephone\": \"Telephone number\"
  },
  \"address\": {
    \"residential_address\": \"Full address\",
    \"residential_city\": \"City\",
    \"residential_province\": \"Province\",
    \"residential_zipcode\": \"Zip code\"
  },
  \"government_ids\": {
    \"gsis_id\": \"GSIS ID\",
    \"pagibig_id\": \"Pag-IBIG ID\",
    \"philhealth_no\": \"PhilHealth number\",
    \"sss_no\": \"SSS number\",
    \"tin_no\": \"TIN number\"
  },
  \"education\": [
    {
      \"level\": \"Elementary/Secondary/College/Graduate\",
      \"school\": \"School name\",
      \"course\": \"Course/Degree\",
      \"year_graduated\": \"Year\"
    }
  ],
  \"work_experience\": [
    {
      \"position\": \"Job title\",
      \"company\": \"Company name\",
      \"from_date\": \"YYYY-MM-DD\",
      \"to_date\": \"YYYY-MM-DD or Present\",
      \"salary\": \"Monthly salary\"
    }
  ],
  \"skills\": \"Special skills and hobbies\",
  \"references\": [
    {
      \"name\": \"Reference name\",
      \"address\": \"Address\",
      \"telephone\": \"Contact number\"
    }
  ]
}

CRITICAL: Respond with ONLY the JSON object. No markdown, no explanations, just pure JSON starting with { and ending with }.";
    }
    
    return $prompt;
}

/**
 * Extract PDS data using AI
 */
function extractPDSWithAI($prompt, $contentType, $fileBlob = null, $fileExt = null) {
    if (AI_PROVIDER === 'gemini') {
        return callGeminiForPDS($prompt, $contentType, $fileBlob, $fileExt);
    } else if (AI_PROVIDER === 'openai') {
        return callOpenAIForPDS($prompt, $contentType, $fileBlob, $fileExt);
    } else {
        // Mock extraction for testing
        return [
            'success' => true,
            'data' => [
                'personal_info' => [
                    'surname' => 'Dela Cruz',
                    'first_name' => 'Juan',
                    'middle_name' => 'Santos',
                    'date_of_birth' => '1990-05-15',
                    'sex' => 'Male',
                    'civil_status' => 'Single'
                ],
                'contact_info' => [
                    'email' => 'juan.delacruz@email.com',
                    'mobile' => '09171234567'
                ],
                'education' => [
                    ['level' => 'College', 'school' => 'University of the Philippines', 'course' => 'BS Computer Science', 'year_graduated' => '2012']
                ],
                'work_experience' => [
                    ['position' => 'Software Developer', 'company' => 'Tech Corp', 'from_date' => '2012-06-01', 'to_date' => 'Present', 'salary' => '35000']
                ]
            ]
        ];
    }
}

/**
 * Call Gemini API for PDS extraction
 */
function callGeminiForPDS($prompt, $contentType, $fileBlob = null, $fileExt = null) {
    $apiKey = GEMINI_API_KEY;
    $url = GEMINI_API_URL . '?key=' . $apiKey;
    
    // Build request based on content type
    if ($contentType === 'pdf_image' || $contentType === 'document_image') {
        // Use Gemini's vision capability for PDF/image
        $base64Data = base64_encode($fileBlob);
        $mimeType = ($fileExt === 'pdf') ? 'application/pdf' : 'image/jpeg';
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Data
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2, // Lower temperature for accurate extraction
                'maxOutputTokens' => 8192,
            ]
        ];
    } else {
        // Text-based extraction
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 8192,
            ]
        ];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increase timeout for large files
    
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
        
        // Clean up markdown and formatting
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*$/s', '', $text);
        $text = preg_replace('/^```\s*/s', '', $text);
        $text = trim($text);
        
        // Extract JSON if embedded in text
        if (preg_match('/\{[\s\S]*\}/s', $text, $matches)) {
            $text = $matches[0];
        }
        
        $pdsData = json_decode($text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => $pdsData, 'raw_response' => substr($text, 0, 1000)];
        } else {
            return ['error' => 'Failed to parse AI response: ' . json_last_error_msg() . '. Raw text: ' . substr($text, 0, 500)];
        }
    }
    
    if (isset($result['error'])) {
        return ['error' => 'Gemini API Error: ' . json_encode($result['error'])];
    }
    
    return ['error' => 'Invalid response from Gemini API: ' . substr($response, 0, 500)];
}

/**
 * Update PDS data in database
 */
function updatePDSData($conn, $candidateId, $extractedData) {
    try {
        $stmt = $conn->prepare("UPDATE pds_data SET 
            surname = ?,
            first_name = ?,
            middle_name = ?,
            name_extension = ?,
            date_of_birth = ?,
            place_of_birth = ?,
            sex = ?,
            civil_status = ?,
            height = ?,
            weight = ?,
            blood_type = ?,
            gsis_id = ?,
            pagibig_id = ?,
            philhealth_no = ?,
            sss_no = ?,
            tin_no = ?,
            residential_address = ?,
            residential_city = ?,
            residential_province = ?,
            residential_zipcode = ?,
            telephone = ?,
            mobile = ?,
            email = ?,
            education = ?,
            work_experience = ?,
            special_skills = ?,
            `references` = ?,
            updated_at = NOW()
            WHERE candidate_id = ?
        ");
        
        $pi = $extractedData['personal_info'] ?? [];
        $ci = $extractedData['contact_info'] ?? [];
        $addr = $extractedData['address'] ?? [];
        $ids = $extractedData['government_ids'] ?? [];
        
        $stmt->execute([
            $pi['surname'] ?? null,
            $pi['first_name'] ?? null,
            $pi['middle_name'] ?? null,
            $pi['name_extension'] ?? null,
            $pi['date_of_birth'] ?? null,
            $pi['place_of_birth'] ?? null,
            $pi['sex'] ?? null,
            $pi['civil_status'] ?? null,
            $pi['height'] ?? null,
            $pi['weight'] ?? null,
            $pi['blood_type'] ?? null,
            $ids['gsis_id'] ?? null,
            $ids['pagibig_id'] ?? null,
            $ids['philhealth_no'] ?? null,
            $ids['sss_no'] ?? null,
            $ids['tin_no'] ?? null,
            $addr['residential_address'] ?? null,
            $addr['residential_city'] ?? null,
            $addr['residential_province'] ?? null,
            $addr['residential_zipcode'] ?? null,
            $ci['telephone'] ?? null,
            $ci['mobile'] ?? null,
            $ci['email'] ?? null,
            json_encode($extractedData['education'] ?? []),
            json_encode($extractedData['work_experience'] ?? []),
            $extractedData['skills'] ?? null,
            json_encode($extractedData['references'] ?? []),
            $candidateId
        ]);
        
        $fieldsUpdated = $stmt->rowCount();
        
        return ['success' => true, 'fields_updated' => $fieldsUpdated];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDS AI Extraction Test</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .test-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .ai-config-badge {
            display: inline-block;
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .candidates-list {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .candidate-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .candidate-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }
        
        .candidate-card.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .extraction-steps {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .step-item {
            display: flex;
            align-items: flex-start;
            padding: 20px;
            border-left: 4px solid #e9ecef;
            margin-bottom: 15px;
            border-radius: 8px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .step-item.success {
            border-left-color: #28a745;
            background: #f0fff4;
        }
        
        .step-item.processing {
            border-left-color: #ffc107;
            background: #fffbf0;
            animation: pulse 1.5s infinite;
        }
        
        .step-item.error {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        .step-item.info {
            border-left-color: #17a2b8;
            background: #f0f9ff;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .step-icon {
            font-size: 24px;
            margin-right: 15px;
            min-width: 30px;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .step-details {
            font-size: 14px;
            color: #6c757d;
        }
        
        .result-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .json-viewer {
            background: #2d3748;
            color: #68d391;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .btn-extract {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-extract:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .file-info {
            display: inline-block;
            padding: 5px 12px;
            background: #e9ecef;
            border-radius: 15px;
            font-size: 12px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="header-section">
            <h2><i class="fas fa-robot mr-3"></i>PDS AI Extraction Test</h2>
            <p class="mb-2">Test the complete AI-powered PDS extraction process</p>
            <div class="ai-config-badge">
                <i class="fas fa-cog mr-2"></i>AI Provider: <strong><?php echo strtoupper(AI_PROVIDER); ?></strong>
                <?php if (AI_PROVIDER === 'gemini'): ?>
                    | Model: <?php echo GEMINI_MODEL; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="candidates-list">
            <h4 class="mb-4"><i class="fas fa-users mr-2"></i>Candidates with PDS Files</h4>
            
            <?php if (empty($candidates)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i>
                    No candidates with PDS files found. Please submit an application with a PDS file first.
                </div>
            <?php else: ?>
                <form method="POST">
                    <?php foreach ($candidates as $candidate): ?>
                        <div class="candidate-card <?php echo (isset($_POST['candidate_id']) && $_POST['candidate_id'] == $candidate['candidate_id']) ? 'selected' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <i class="fas fa-user-circle mr-2 text-primary"></i>
                                        <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                    </h5>
                                    <p class="mb-2 text-muted">
                                        <i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($candidate['email']); ?>
                                    </p>
                                    <div>
                                        <span class="file-info">
                                            <i class="fas fa-file mr-1"></i><?php echo htmlspecialchars($candidate['pds_file_name']); ?>
                                        </span>
                                        <span class="file-info">
                                            <i class="fas fa-hdd mr-1"></i><?php echo number_format($candidate['pds_file_size']); ?> bytes
                                        </span>
                                        <span class="file-info">
                                            <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($candidate['pds_file_type']); ?>
                                        </span>
                                    </div>
                                    <?php if ($candidate['surname']): ?>
                                        <div class="mt-2">
                                            <span class="badge badge-success">
                                                <i class="fas fa-check mr-1"></i>Already Extracted
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-2">
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock mr-1"></i>Pending Extraction
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button type="submit" name="extract_pds" value="1" class="btn btn-extract">
                                        <i class="fas fa-magic mr-2"></i>Extract with AI
                                    </button>
                                    <input type="hidden" name="candidate_id" value="<?php echo $candidate['candidate_id']; ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($extractionSteps)): ?>
            <div class="extraction-steps">
                <h4 class="mb-4"><i class="fas fa-tasks mr-2"></i>Extraction Process</h4>
                
                <?php foreach ($extractionSteps as $step): ?>
                    <div class="step-item <?php echo $step['status']; ?>">
                        <div class="step-icon">
                            <?php if ($step['status'] === 'success'): ?>
                                <i class="fas fa-check-circle text-success"></i>
                            <?php elseif ($step['status'] === 'processing'): ?>
                                <i class="fas fa-spinner fa-spin text-warning"></i>
                            <?php elseif ($step['status'] === 'error'): ?>
                                <i class="fas fa-times-circle text-danger"></i>
                            <?php else: ?>
                                <i class="fas fa-info-circle text-info"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-content">
                            <div class="step-title">
                                Step <?php echo $step['step']; ?>: <?php echo $step['title']; ?>
                            </div>
                            <?php if (isset($step['details'])): ?>
                                <div class="step-details"><?php echo $step['details']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($extractionResult): ?>
            <div class="result-panel">
                <h4 class="mb-4">
                    <?php if (isset($extractionResult['success'])): ?>
                        <i class="fas fa-check-circle text-success mr-2"></i>Extraction Result
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle text-danger mr-2"></i>Extraction Failed
                    <?php endif; ?>
                </h4>
                
                <?php if (isset($extractionResult['success'])): ?>
                    <div class="json-viewer">
<?php echo json_encode($extractionResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?php echo htmlspecialchars($extractionResult['error']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
