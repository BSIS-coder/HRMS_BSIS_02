<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

$candidateId = $_POST['candidate_id'] ?? null;

if (!$candidateId) {
    echo json_encode(['error' => 'No candidate ID provided']);
    exit;
}

try {
    // Get PDS data for this candidate
    $stmt = $conn->prepare("
        SELECT 
            pds_id,
            surname,
            first_name,
            middle_name,
            name_extension,
            date_of_birth,
            place_of_birth,
            sex,
            civil_status,
            height,
            weight,
            blood_type,
            gsis_id,
            pagibig_id,
            philhealth_no,
            sss_no,
            tin_no,
            residential_address,
            residential_city,
            residential_province,
            residential_zipcode,
            telephone,
            mobile,
            email,
            education,
            work_experience,
            special_skills,
            `references`,
            pds_file_name,
            created_at,
            updated_at
        FROM pds_data
        WHERE candidate_id = ?
    ");
    $stmt->bind_param('i', $candidateId);
    $stmt->execute();
    $result = $stmt->get_result();
    $pdsData = $result->fetch_assoc();
    
    if (!$pdsData) {
        echo json_encode(['error' => 'No PDS data found']);
        exit;
    }
    
    // Check if extraction is pending (has file but no extracted data)
    $hasExtractedData = !empty($pdsData['surname']) || !empty($pdsData['first_name']);
    
    if (!$hasExtractedData) {
        // Extraction is pending
        echo json_encode([
            'extraction_pending' => true,
            'message' => 'PDS extraction in progress'
        ]);
        exit;
    }
    
    // Parse JSON fields
    $education = json_decode($pdsData['education'], true) ?? [];
    $workExperience = json_decode($pdsData['work_experience'], true) ?? [];
    $skills = json_decode($pdsData['special_skills'], true) ?? [];
    $references = json_decode($pdsData['references'], true) ?? [];
    
    // Build structured response
    $response = [
        'data' => [
            'personal_info' => [
                'surname' => $pdsData['surname'],
                'first_name' => $pdsData['first_name'],
                'middle_name' => $pdsData['middle_name'],
                'name_extension' => $pdsData['name_extension'],
                'date_of_birth' => $pdsData['date_of_birth'],
                'place_of_birth' => $pdsData['place_of_birth'],
                'sex' => $pdsData['sex'],
                'civil_status' => $pdsData['civil_status'],
                'height' => $pdsData['height'],
                'weight' => $pdsData['weight'],
                'blood_type' => $pdsData['blood_type']
            ],
            'contact_info' => [
                'email' => $pdsData['email'],
                'mobile' => $pdsData['mobile'],
                'telephone' => $pdsData['telephone']
            ],
            'address' => [
                'residential_address' => $pdsData['residential_address'],
                'residential_city' => $pdsData['residential_city'],
                'residential_province' => $pdsData['residential_province'],
                'residential_zipcode' => $pdsData['residential_zipcode']
            ],
            'government_ids' => [
                'gsis_id' => $pdsData['gsis_id'],
                'pagibig_id' => $pdsData['pagibig_id'],
                'philhealth_no' => $pdsData['philhealth_no'],
                'sss_no' => $pdsData['sss_no'],
                'tin_no' => $pdsData['tin_no']
            ],
            'education' => $education,
            'work_experience' => $workExperience,
            'skills' => is_array($skills) ? $skills : ($pdsData['special_skills'] ? [$pdsData['special_skills']] : []),
            'references' => $references
        ],
        'metadata' => [
            'pds_file_name' => $pdsData['pds_file_name'],
            'created_at' => $pdsData['created_at'],
            'updated_at' => $pdsData['updated_at']
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
