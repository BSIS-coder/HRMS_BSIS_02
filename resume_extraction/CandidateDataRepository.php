<?php
/**
 * CandidateDataRepository
 * 
 * Handles database operations for candidate extracted data
 * Uses PDO transactions for atomicity
 */
class CandidateDataRepository {
    
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Store education records for a candidate
     * 
     * @param int $candidateId
     * @param array $educationRecords
     * @return bool
     */
    public function storeEducation($candidateId, $educationRecords) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO candidate_education 
                (candidate_id, institution, degree, field_of_study, start_date, end_date, grade) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($educationRecords as $edu) {
                $stmt->execute([
                    $candidateId,
                    $edu['institution'] ?? null,
                    $edu['degree'] ?? null,
                    $edu['field_of_study'] ?? null,
                    $edu['start_date'] ?? null,
                    $edu['end_date'] ?? null,
                    $edu['grade'] ?? null
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to store education: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store skill records for a candidate
     * 
     * @param int $candidateId
     * @param array $skillRecords
     * @return bool
     */
    public function storeSkills($candidateId, $skillRecords) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO candidate_skills 
                (candidate_id, skill_name, proficiency_level, years_of_experience) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($skillRecords as $skill) {
                $stmt->execute([
                    $candidateId,
                    $skill['skill_name'] ?? null,
                    $skill['proficiency_level'] ?? 'Intermediate',
                    $skill['years_of_experience'] ?? null
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to store skills: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store work experience records for a candidate
     * 
     * @param int $candidateId
     * @param array $experienceRecords
     * @return bool
     */
    public function storeWorkExperience($candidateId, $experienceRecords) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO candidate_work_experience 
                (candidate_id, company, job_title, start_date, end_date, responsibilities, achievements) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($experienceRecords as $exp) {
                $stmt->execute([
                    $candidateId,
                    $exp['company'] ?? null,
                    $exp['job_title'] ?? null,
                    $exp['start_date'] ?? null,
                    $exp['end_date'] ?? null,
                    $exp['responsibilities'] ?? null,
                    $exp['achievements'] ?? null
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to store work experience: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store certification records for a candidate
     * 
     * @param int $candidateId
     * @param array $certificationRecords
     * @return bool
     */
    public function storeCertifications($candidateId, $certificationRecords) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO candidate_certifications 
                (candidate_id, certification_name, issuing_organization, issue_date, expiry_date, credential_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($certificationRecords as $cert) {
                $stmt->execute([
                    $candidateId,
                    $cert['certification_name'] ?? null,
                    $cert['issuing_organization'] ?? null,
                    $cert['issue_date'] ?? null,
                    $cert['expiry_date'] ?? null,
                    $cert['credential_id'] ?? null
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to store certifications: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete existing extracted data for a candidate (for re-extraction)
     * 
     * @param int $candidateId
     * @return bool
     */
    public function deleteExistingData($candidateId) {
        try {
            $this->conn->prepare("DELETE FROM candidate_education WHERE candidate_id = ?")->execute([$candidateId]);
            $this->conn->prepare("DELETE FROM candidate_skills WHERE candidate_id = ?")->execute([$candidateId]);
            $this->conn->prepare("DELETE FROM candidate_work_experience WHERE candidate_id = ?")->execute([$candidateId]);
            $this->conn->prepare("DELETE FROM candidate_certifications WHERE candidate_id = ?")->execute([$candidateId]);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to delete existing data: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store complete parser response for audit
     * 
     * @param int $candidateId
     * @param string $resumePath
     * @param string $jsonResponse
     * @param string $status - 'completed' or 'failed'
     * @param string|null $errorMessage
     * @return bool
     */
    public function storeParserResponse($candidateId, $resumePath, $jsonResponse, $status, $errorMessage = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO candidate_parsed_resumes 
                (candidate_id, resume_file_path, parser_response, extraction_status, error_message) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $candidateId,
                $resumePath,
                $jsonResponse,
                $status,
                $errorMessage
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to store parser response: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retrieve all extracted data for a candidate
     * 
     * @param int $candidateId
     * @return array - ['education' => array, 'skills' => array, 'experience' => array, 'certifications' => array]
     */
    public function getCandidateData($candidateId) {
        $data = [
            'education' => [],
            'skills' => [],
            'experience' => [],
            'certifications' => []
        ];
        
        try {
            // Get education
            $stmt = $this->conn->prepare("SELECT * FROM candidate_education WHERE candidate_id = ? ORDER BY start_date ASC");
            $stmt->execute([$candidateId]);
            $data['education'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get skills
            $stmt = $this->conn->prepare("SELECT * FROM candidate_skills WHERE candidate_id = ? ORDER BY proficiency_level DESC, skill_name ASC");
            $stmt->execute([$candidateId]);
            $data['skills'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get work experience
            $stmt = $this->conn->prepare("SELECT * FROM candidate_work_experience WHERE candidate_id = ? ORDER BY end_date DESC");
            $stmt->execute([$candidateId]);
            $data['experience'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get certifications
            $stmt = $this->conn->prepare("SELECT * FROM candidate_certifications WHERE candidate_id = ? ORDER BY issue_date DESC");
            $stmt->execute([$candidateId]);
            $data['certifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to retrieve candidate data: " . $e->getMessage());
        }
        
        return $data;
    }
}
