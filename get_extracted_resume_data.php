<?php
/**
 * Get Extracted Resume Data
 * Returns extracted education, skills, experience, and certifications for a candidate
 */

require_once 'config.php';
require_once 'resume_extraction/CandidateDataRepository.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['candidate_id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$candidateId = intval($_POST['candidate_id']);

try {
    $repository = new CandidateDataRepository($conn);
    $data = $repository->getCandidateData($candidateId);
    
    echo json_encode($data);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
