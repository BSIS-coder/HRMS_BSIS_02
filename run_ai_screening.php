<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'db_connect.php';
require_once 'ai_screening.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidateId = $_POST['candidate_id'] ?? null;
    $jobOpeningId = $_POST['job_opening_id'] ?? null;
    
    if (!$candidateId || !$jobOpeningId) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }
    
    try {
        // Run AI screening
        $result = screenCandidateWithAI($candidateId, $jobOpeningId, $conn);
        
        if (isset($result['error'])) {
            echo json_encode(['error' => $result['error']]);
            exit;
        }
        
        if (isset($result['success']) && $result['success']) {
            $screeningData = $result['data'];
            
            // Save screening results to database using existing columns
            $assessmentJson = json_encode([
                'overall_score' => $screeningData['overall_score'],
                'qualifications_score' => $screeningData['qualifications_score'],
                'experience_score' => $screeningData['experience_score'],
                'skills_score' => $screeningData['skills_score'],
                'communication_score' => $screeningData['communication_score'],
                'ai_generated' => true,
                'generated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Store AI insights in notes field
            $notesText = "=== AI SCREENING RESULTS ===\n\n";
            $notesText .= "Recommendation: " . $screeningData['recommendation'] . "\n\n";
            $notesText .= "Summary: " . $screeningData['summary'] . "\n\n";
            $notesText .= "Strengths:\n";
            foreach ($screeningData['strengths'] as $strength) {
                $notesText .= "• " . $strength . "\n";
            }
            $notesText .= "\nConcerns:\n";
            foreach ($screeningData['concerns'] as $concern) {
                $notesText .= "• " . $concern . "\n";
            }
            $notesText .= "\nSuggested Interview Questions:\n";
            foreach ($screeningData['interview_questions'] as $i => $question) {
                $notesText .= ($i + 1) . ". " . $question . "\n";
            }
            
            // Get application ID
            $stmt = $conn->prepare("SELECT application_id FROM job_applications WHERE candidate_id = ? AND job_opening_id = ?");
            $stmt->bind_param('ii', $candidateId, $jobOpeningId);
            $stmt->execute();
            $result = $stmt->get_result();
            $application = $result->fetch_assoc();
            
            if ($application) {
                // Update job application with AI screening results
                $stmt = $conn->prepare("UPDATE job_applications 
                                       SET assessment_scores = ?, 
                                           notes = ?,
                                           status = 'Screening'
                                       WHERE application_id = ?");
                $stmt->bind_param('ssi', $assessmentJson, $notesText, $application['application_id']);
                $stmt->execute();
            }
            
            echo json_encode([
                'success' => true,
                'data' => $screeningData
            ]);
        } else {
            echo json_encode(['error' => 'AI screening failed']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
