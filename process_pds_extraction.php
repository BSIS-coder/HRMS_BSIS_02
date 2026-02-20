<?php
/**
 * Background PDS Extraction Processor
 * This script extracts PDS data using AI immediately after application submission
 */

require_once 'db_connect.php';
require_once 'ai_config.php';

// Get candidate ID from request
$candidateId = $_GET['candidate_id'] ?? null;

if (!$candidateId) {
    echo json_encode(['error' => 'No candidate ID provided']);
    exit;
}

// Set headers for SSE (Server-Sent Events) for real-time updates
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Function to send SSE message
function sendSSE($event, $data) {
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

// Start extraction process
sendSSE('status', ['step' => 1, 'message' => 'Starting PDS extraction...', 'status' => 'processing']);

try {
    // Step 1: Retrieve candidate and PDS data
    sendSSE('status', ['step' => 1, 'message' => 'Retrieving PDS from database...', 'status' => 'processing']);
    
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            p.*
        FROM candidates c
        LEFT JOIN pds_data p ON c.candidate_id = p.candidate_id
        WHERE c.candidate_id = ?
    ");
    $stmt->bind_param('i', $candidateId);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    
    if (!$candidate || !$candidate['pds_file_blob']) {
        sendSSE('error', ['message' => 'No PDS file found for this candidate']);
        exit;
    }
    
    sendSSE('status', [
        'step' => 1, 
        'message' => 'PDS file retrieved: ' . $candidate['pds_file_name'], 
        'status' => 'success',
        'details' => [
            'filename' => $candidate['pds_file_name'],
            'size' => number_format($candidate['pds_file_size']) . ' bytes',
            'type' => $candidate['pds_file_type']
        ]
    ]);
    
    // Step 2: Convert BLOB to file
    sendSSE('status', ['step' => 2, 'message' => 'Converting BLOB to file...', 'status' => 'processing']);
    
    $fileExt = strtolower(pathinfo($candidate['pds_file_name'], PATHINFO_EXTENSION));
    $tempFile = sys_get_temp_dir() . '/pds_temp_' . $candidateId . '.' . $fileExt;
    file_put_contents($tempFile, $candidate['pds_file_blob']);
    
    sendSSE('status', [
        'step' => 2, 
        'message' => 'Temporary file created', 
        'status' => 'success',
        'details' => ['temp_file' => basename($tempFile)]
    ]);
    
    // Step 3: Extract content
    sendSSE('status', ['step' => 3, 'message' => 'Extracting content from file...', 'status' => 'processing']);
    
    $pdsContent = '';
    $contentType = '';
    
    if ($fileExt === 'json') {
        $pdsContent = file_get_contents($tempFile);
        $contentType = 'json';
        sendSSE('status', [
            'step' => 3, 
            'message' => 'JSON content extracted', 
            'status' => 'success',
            'details' => ['content_length' => strlen($pdsContent) . ' characters']
        ]);
    } else if ($fileExt === 'pdf') {
        $pdfText = extractPDFText($tempFile);
        
        if ($pdfText && strlen($pdfText) > 100) {
            $pdsContent = $pdfText;
            $contentType = 'pdf_text';
            sendSSE('status', [
                'step' => 3, 
                'message' => 'PDF text extracted successfully', 
                'status' => 'success',
                'details' => ['content_length' => strlen($pdsContent) . ' characters']
            ]);
        } else {
            $pdsContent = base64_encode($candidate['pds_file_blob']);
            $contentType = 'pdf_image';
            sendSSE('status', [
                'step' => 3, 
                'message' => 'Using AI vision for PDF processing', 
                'status' => 'success',
                'details' => ['method' => 'AI Vision (Gemini)']
            ]);
        }
    } else {
        $pdsContent = base64_encode($candidate['pds_file_blob']);
        $contentType = 'document_image';
        sendSSE('status', [
            'step' => 3, 
            'message' => 'Document prepared for AI processing', 
            'status' => 'success'
        ]);
    }
    
    // Step 4: Prepare AI prompt
    sendSSE('status', ['step' => 4, 'message' => 'Preparing AI extraction prompt...', 'status' => 'processing']);
    
    $prompt = buildPDSExtractionPrompt($pdsContent, $contentType);
    
    sendSSE('status', [
        'step' => 4, 
        'message' => 'AI prompt prepared', 
        'status' => 'success',
        'details' => [
            'prompt_length' => strlen($prompt) . ' characters',
            'content_type' => $contentType
        ]
    ]);
    
    // Step 5: Call AI API
    sendSSE('status', [
        'step' => 5, 
        'message' => 'Calling AI API (' . strtoupper(AI_PROVIDER) . ')...', 
        'status' => 'processing',
        'details' => [
            'provider' => AI_PROVIDER,
            'model' => defined('GEMINI_MODEL') ? GEMINI_MODEL : 'N/A'
        ]
    ]);
    
    $aiResult = extractPDSWithAI($prompt, $contentType, $candidate['pds_file_blob'], $fileExt);
    
    if (isset($aiResult['success']) && $aiResult['success']) {
        sendSSE('status', [
            'step' => 5, 
            'message' => 'AI extraction successful', 
            'status' => 'success',
            'details' => ['fields_extracted' => count($aiResult['data'], COUNT_RECURSIVE)]
        ]);
        
        // Step 6: Update database
        sendSSE('status', ['step' => 6, 'message' => 'Updating database...', 'status' => 'processing']);
        
        $updateResult = updatePDSData($conn, $candidateId, $aiResult['data']);
        
        if ($updateResult['success']) {
            sendSSE('status', [
                'step' => 6, 
                'message' => 'Database updated successfully', 
                'status' => 'success',
                'details' => ['fields_updated' => $updateResult['fields_updated']]
            ]);
            
            // Send completion event
            sendSSE('complete', [
                'message' => 'PDS extraction completed successfully',
                'data' => $aiResult['data'],
                'candidate_id' => $candidateId
            ]);
        } else {
            sendSSE('error', ['message' => 'Database update failed: ' . $updateResult['error']]);
        }
    } else {
        sendSSE('error', ['message' => 'AI extraction failed: ' . ($aiResult['error'] ?? 'Unknown error')]);
    }
    
    // Cleanup
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    
} catch (Exception $e) {
    sendSSE('error', ['message' => 'Extraction error: ' . $e->getMessage()]);
}

// Include extraction functions from test file
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
    $baseStructure = '{
  "personal_info": {
    "surname": "Last name",
    "first_name": "First name",
    "middle_name": "Middle name",
    "name_extension": "Jr/Sr/III etc",
    "date_of_birth": "YYYY-MM-DD",
    "place_of_birth": "City/Province",
    "sex": "Male or Female",
    "civil_status": "Single/Married/Widowed/Separated",
    "height": "Height in meters",
    "weight": "Weight in kg",
    "blood_type": "Blood type"
  },
  "contact_info": {
    "email": "Email address",
    "mobile": "Mobile number",
    "telephone": "Telephone number"
  },
  "address": {
    "residential_address": "Full address",
    "residential_city": "City",
    "residential_province": "Province",
    "residential_zipcode": "Zip code"
  },
  "government_ids": {
    "gsis_id": "GSIS ID",
    "pagibig_id": "Pag-IBIG ID",
    "philhealth_no": "PhilHealth number",
    "sss_no": "SSS number",
    "tin_no": "TIN number"
  },
  "education": [
    {
      "level": "Elementary/Secondary/College/Graduate",
      "school": "School name",
      "course": "Course/Degree",
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
  "skills": "Special skills and hobbies",
  "references": [
    {
      "name": "Reference name",
      "address": "Address",
      "telephone": "Contact number"
    }
  ]
}';

    if ($contentType === 'json') {
        return "Extract and structure this PDS JSON data.\n\nPDS Content:\n{$content}\n\nCRITICAL: Respond with ONLY valid JSON. No markdown.\n\nReturn this structure:\n{$baseStructure}\n\nExtract all data. Use null for missing fields.";
    } else if ($contentType === 'pdf_text') {
        return "Extract data from this PDS (Philippine Civil Service Form No. 212) text.\n\nPDS Text:\n{$content}\n\nCRITICAL: Respond with ONLY valid JSON. No markdown.\n\nReturn this structure:\n{$baseStructure}\n\nExtract all data. Use null for missing fields.";
    } else {
        return "You are viewing a PDS (Philippine Civil Service Form No. 212). Extract ALL information.\n\nCRITICAL: Respond with ONLY valid JSON starting with { and ending with }.\n\nReturn this structure:\n{$baseStructure}\n\nExtract all data. Use null for missing fields.";
    }
}

function extractPDSWithAI($prompt, $contentType, $fileBlob = null, $fileExt = null) {
    if (AI_PROVIDER === 'gemini') {
        return callGeminiForPDS($prompt, $contentType, $fileBlob, $fileExt);
    } else {
        return [
            'success' => true,
            'data' => [
                'personal_info' => ['surname' => 'Test', 'first_name' => 'User'],
                'contact_info' => ['email' => 'test@email.com'],
                'education' => [],
                'work_experience' => []
            ]
        ];
    }
}

function callGeminiForPDS($prompt, $contentType, $fileBlob = null, $fileExt = null) {
    $apiKey = GEMINI_API_KEY;
    $url = GEMINI_API_URL . '?key=' . $apiKey;
    
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
            'contents' => [[
                'parts' => [['text' => $prompt]]
            ]],
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
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => 'Gemini API Error (HTTP ' . $httpCode . ')'];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $result['candidates'][0]['content']['parts'][0]['text'];
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*$/s', '', $text);
        $text = trim($text);
        
        if (preg_match('/\{[\s\S]*\}/s', $text, $matches)) {
            $text = $matches[0];
        }
        
        $pdsData = json_decode($text, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['success' => true, 'data' => $pdsData];
        }
    }
    
    return ['error' => 'Failed to parse AI response'];
}

function updatePDSData($conn, $candidateId, $extractedData) {
    try {
        $stmt = $conn->prepare("UPDATE pds_data SET 
            surname = ?, first_name = ?, middle_name = ?, name_extension = ?,
            date_of_birth = ?, place_of_birth = ?, sex = ?, civil_status = ?,
            height = ?, weight = ?, blood_type = ?,
            gsis_id = ?, pagibig_id = ?, philhealth_no = ?, sss_no = ?, tin_no = ?,
            residential_address = ?, residential_city = ?, residential_province = ?, residential_zipcode = ?,
            telephone = ?, mobile = ?, email = ?,
            education_data = ?, work_experience_data = ?, skills_data = ?, references_data = ?,
            updated_at = NOW()
            WHERE candidate_id = ?
        ");
        
        $pi = $extractedData['personal_info'] ?? [];
        $ci = $extractedData['contact_info'] ?? [];
        $addr = $extractedData['address'] ?? [];
        $ids = $extractedData['government_ids'] ?? [];
        
        $stmt->bind_param('sssssssssssssssssssssssssi',
            $pi['surname'], $pi['first_name'], $pi['middle_name'], $pi['name_extension'],
            $pi['date_of_birth'], $pi['place_of_birth'], $pi['sex'], $pi['civil_status'],
            $pi['height'], $pi['weight'], $pi['blood_type'],
            $ids['gsis_id'], $ids['pagibig_id'], $ids['philhealth_no'], $ids['sss_no'], $ids['tin_no'],
            $addr['residential_address'], $addr['residential_city'], $addr['residential_province'], $addr['residential_zipcode'],
            $ci['telephone'], $ci['mobile'], $ci['email'],
            json_encode($extractedData['education'] ?? []),
            json_encode($extractedData['work_experience'] ?? []),
            $extractedData['skills'],
            json_encode($extractedData['references'] ?? []),
            $candidateId
        );
        
        $stmt->execute();
        
        return ['success' => true, 'fields_updated' => $stmt->affected_rows];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>
