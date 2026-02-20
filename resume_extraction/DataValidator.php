<?php
/**
 * DataValidator
 * 
 * Validates extracted resume data before database insertion
 * Ensures data quality and consistency
 */
class DataValidator {
    
    /**
     * Validate education record
     * 
     * @param array $education - Education data from parser
     * @return array - ['valid' => bool, 'errors' => array]
     */
    public function validateEducation($education) {
        $errors = [];
        
        // Check required fields
        if (empty(trim($education['degree'] ?? ''))) {
            $errors[] = 'Degree is required';
        }
        
        if (empty(trim($education['institution'] ?? ''))) {
            $errors[] = 'Institution is required';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate work experience record
     * 
     * @param array $experience - Experience data from parser
     * @return array - ['valid' => bool, 'errors' => array]
     */
    public function validateWorkExperience($experience) {
        $errors = [];
        
        // Check required fields
        if (empty(trim($experience['job_title'] ?? ''))) {
            $errors[] = 'Job title is required';
        }
        
        if (empty(trim($experience['company'] ?? ''))) {
            $errors[] = 'Company is required';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate skill record
     * 
     * @param array $skill - Skill data from parser
     * @return array - ['valid' => bool, 'errors' => array]
     */
    public function validateSkill($skill) {
        $errors = [];
        
        // Check required fields
        if (empty(trim($skill['skill_name'] ?? ''))) {
            $errors[] = 'Skill name is required';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate certification record
     * 
     * @param array $certification - Certification data from parser
     * @return array - ['valid' => bool, 'errors' => array]
     */
    public function validateCertification($certification) {
        $errors = [];
        
        // Check required fields
        if (empty(trim($certification['certification_name'] ?? ''))) {
            $errors[] = 'Certification name is required';
        }
        
        if (empty(trim($certification['issuing_organization'] ?? ''))) {
            $errors[] = 'Issuing organization is required';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Sanitize string for database storage
     * Trims whitespace from both ends
     * 
     * @param string $value
     * @return string
     */
    public function sanitize($value) {
        if (!is_string($value)) {
            return '';
        }
        
        return trim($value);
    }
}
