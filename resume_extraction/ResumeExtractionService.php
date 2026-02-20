<?php
/**
 * ResumeExtractionService
 * 
 * Orchestrates the resume extraction workflow
 * Executes Python parser, validates data, and stores in database
 */

require_once __DIR__ . '/DataValidator.php';
require_once __DIR__ . '/CandidateDataRepository.php';

// Configuration constants
define('PYTHON_EXECUTABLE', 'python');  // or 'python3' on Linux/Mac
define('RESUME_PARSER_SCRIPT', __DIR__ . '/resume_parser.py');
define('PARSER_TIMEOUT', 30); // seconds
define('EXTRACTION_LOG_FILE', __DIR__ . '/logs/resume_extraction.log');

class ResumeExtractionService {
    
    private $conn;
    private $validator;
    private $repository;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->validator = new DataValidator();
        $this->repository = new CandidateDataRepository($connection);
        
        // Ensure logs directory exists
        $logDir = dirname(EXTRACTION_LOG_FILE);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }
    
    /**
     * Extract resume data and store in database
     * 
     * @param int $candidateId - The candidate's database ID
     * @param string $resumePath - Absolute path to resume file
     * @return array - ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public function extractAndStore($candidateId, $resumePath) {
        $this->log("INFO", "Starting extraction for candidate $candidateId, file: $resumePath");
        
        // Update status to processing
        $this->updateExtractionStatus($candidateId, 'processing');
        
        try {
            // Execute Python parser
            $parserResult = $this->executePythonParser($resumePath);
            
            if (!$parserResult['success']) {
                $this->log("ERROR", "Parser execution failed for candidate $candidateId: " . $parserResult['error']);
                $this->updateExtractionStatus($candidateId, 'failed', $parserResult['error']);
                $this->repository->storeParserResponse($candidateId, $resumePath, json_encode($parserResult), 'failed', $parserResult['error']);
                return ['success' => false, 'message' => 'Parser execution failed', 'data' => null];
            }
            
            // Parse JSON output
            $parseResult = $this->parseParserOutput($parserResult['output']);
            
            if (!$parseResult['success']) {
                $this->log("ERROR", "JSON parsing failed for candidate $candidateId: " . $parseResult['error']);
                $this->updateExtractionStatus($candidateId, 'failed', $parseResult['error']);
                $this->repository->storeParserResponse($candidateId, $resumePath, $parserResult['output'], 'failed', $parseResult['error']);
                return ['success' => false, 'message' => 'JSON parsing failed', 'data' => null];
            }
            
            $data = $parseResult['data'];
            
            // Begin transaction
            $this->conn->beginTransaction();
            
            try {
                // Delete existing data (for re-extraction)
                $this->repository->deleteExistingData($candidateId);
                
                // Validate and store education
                $validEducation = [];
                foreach ($data['education'] ?? [] as $edu) {
                    $validation = $this->validator->validateEducation($edu);
                    if ($validation['valid']) {
                        $validEducation[] = $edu;
                    } else {
                        $this->log("WARNING", "Skipped invalid education record for candidate $candidateId: " . implode(', ', $validation['errors']));
                    }
                }
                if (!empty($validEducation)) {
                    $this->repository->storeEducation($candidateId, $validEducation);
                }
                
                // Validate and store skills
                $validSkills = [];
                foreach ($data['skills'] ?? [] as $skill) {
                    $validation = $this->validator->validateSkill($skill);
                    if ($validation['valid']) {
                        $validSkills[] = $skill;
                    } else {
                        $this->log("WARNING", "Skipped invalid skill record for candidate $candidateId: " . implode(', ', $validation['errors']));
                    }
                }
                if (!empty($validSkills)) {
                    $this->repository->storeSkills($candidateId, $validSkills);
                }
                
                // Validate and store work experience
                $validExperience = [];
                foreach ($data['work_experience'] ?? [] as $exp) {
                    $validation = $this->validator->validateWorkExperience($exp);
                    if ($validation['valid']) {
                        $validExperience[] = $exp;
                    } else {
                        $this->log("WARNING", "Skipped invalid experience record for candidate $candidateId: " . implode(', ', $validation['errors']));
                    }
                }
                if (!empty($validExperience)) {
                    $this->repository->storeWorkExperience($candidateId, $validExperience);
                }
                
                // Validate and store certifications
                $validCertifications = [];
                foreach ($data['certifications'] ?? [] as $cert) {
                    $validation = $this->validator->validateCertification($cert);
                    if ($validation['valid']) {
                        $validCertifications[] = $cert;
                    } else {
                        $this->log("WARNING", "Skipped invalid certification record for candidate $candidateId: " . implode(', ', $validation['errors']));
                    }
                }
                if (!empty($validCertifications)) {
                    $this->repository->storeCertifications($candidateId, $validCertifications);
                }
                
                // Store parser response for audit
                $this->repository->storeParserResponse($candidateId, $resumePath, json_encode($parseResult['data']), 'completed');
                
                // Commit transaction
                $this->conn->commit();
                
                // Update status to completed
                $this->updateExtractionStatus($candidateId, 'completed');
                
                $summary = sprintf(
                    "Extraction completed: %d education, %d skills, %d experience, %d certifications",
                    count($validEducation),
                    count($validSkills),
                    count($validExperience),
                    count($validCertifications)
                );
                
                $this->log("INFO", "Candidate $candidateId: $summary");
                
                return ['success' => true, 'message' => $summary, 'data' => $data];
                
            } catch (Exception $e) {
                $this->conn->rollBack();
                $this->log("ERROR", "Database error for candidate $candidateId: " . $e->getMessage());
                $this->updateExtractionStatus($candidateId, 'failed', 'Database error: ' . $e->getMessage());
                return ['success' => false, 'message' => 'Database error', 'data' => null];
            }
            
        } catch (Exception $e) {
            $this->log("ERROR", "Unexpected error for candidate $candidateId: " . $e->getMessage());
            $this->updateExtractionStatus($candidateId, 'failed', $e->getMessage());
            return ['success' => false, 'message' => 'Unexpected error', 'data' => null];
        }
    }
    
    /**
     * Execute Python parser and capture output
     * 
     * @param string $resumePath - Absolute path to resume file
     * @return array - ['success' => bool, 'output' => string, 'error' => string|null]
     */
    private function executePythonParser($resumePath) {
        // Validate configuration
        if (!file_exists(RESUME_PARSER_SCRIPT)) {
            return ['success' => false, 'output' => '', 'error' => 'Parser script not found: ' . RESUME_PARSER_SCRIPT];
        }
        
        // Build command
        $command = sprintf('%s "%s" "%s" 2>&1', PYTHON_EXECUTABLE, RESUME_PARSER_SCRIPT, $resumePath);
        
        // Execute with timeout
        $output = shell_exec($command);
        
        if ($output === null || $output === '') {
            return ['success' => false, 'output' => '', 'error' => 'Parser returned no output'];
        }
        
        return ['success' => true, 'output' => $output, 'error' => null];
    }
    
    /**
     * Parse JSON output from Python parser
     * 
     * @param string $jsonOutput - Raw JSON string from parser
     * @return array - ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    private function parseParserOutput($jsonOutput) {
        $decoded = json_decode($jsonOutput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'data' => null, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
        }
        
        // Validate required fields
        if (!isset($decoded['success'])) {
            return ['success' => false, 'data' => null, 'error' => 'Missing required field: success'];
        }
        
        if (!$decoded['success']) {
            return ['success' => false, 'data' => null, 'error' => $decoded['error'] ?? 'Unknown parser error'];
        }
        
        if (!isset($decoded['data'])) {
            return ['success' => false, 'data' => null, 'error' => 'Missing required field: data'];
        }
        
        return ['success' => true, 'data' => $decoded, 'error' => null];
    }
    
    /**
     * Update candidate extraction status
     * 
     * @param int $candidateId
     * @param string $status - 'processing', 'completed', 'failed'
     * @param string|null $errorMessage
     */
    private function updateExtractionStatus($candidateId, $status, $errorMessage = null) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE candidates 
                SET extraction_status = ?, 
                    extraction_attempted_at = NOW(), 
                    extraction_error = ? 
                WHERE candidate_id = ?
            ");
            $stmt->execute([$status, $errorMessage, $candidateId]);
        } catch (Exception $e) {
            $this->log("ERROR", "Failed to update extraction status for candidate $candidateId: " . $e->getMessage());
        }
    }
    
    /**
     * Log message to file
     * 
     * @param string $level - INFO, WARNING, ERROR
     * @param string $message
     */
    private function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents(EXTRACTION_LOG_FILE, $logMessage, FILE_APPEND);
    }
}
