-- ============================================
-- SAFE MIGRATION: Add New Features Only
-- This script adds new tables WITHOUT modifying existing ones
-- Safe to run on existing database
-- ============================================

-- Add AI-related columns to job_openings table (if not exists)
ALTER TABLE job_openings 
ADD COLUMN IF NOT EXISTS screening_level ENUM('Easy', 'Moderate', 'Strict') DEFAULT 'Moderate' COMMENT 'AI screening difficulty level',
ADD COLUMN IF NOT EXISTS ai_generated BOOLEAN DEFAULT FALSE COMMENT 'Flag if job was created by AI',
ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL COMMENT 'User ID who created the job',
ADD COLUMN IF NOT EXISTS approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT NULL COMMENT 'Approval status for AI-generated jobs',
ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL COMMENT 'User ID who approved/rejected the job',
ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL COMMENT 'Timestamp of approval/rejection',
ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL COMMENT 'Reason if job was rejected';

-- Add foreign keys for job_openings (if not exists)
ALTER TABLE job_openings 
ADD CONSTRAINT IF NOT EXISTS fk_job_created_by FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_job_approved_by FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- Add vacancy_limit column to departments table (if not exists)
ALTER TABLE departments 
ADD COLUMN IF NOT EXISTS vacancy_limit INT DEFAULT NULL COMMENT 'Maximum number of open job vacancies allowed for this department';

-- ============================================
-- NEW TABLE: PDS Data (Personal Data Sheet)
-- ============================================
CREATE TABLE IF NOT EXISTS pds_data (
    pds_id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    
    -- I. Personal Information
    surname VARCHAR(100),
    first_name VARCHAR(100),
    middle_name VARCHAR(100),
    name_extension VARCHAR(20),
    date_of_birth DATE,
    place_of_birth VARCHAR(255),
    sex ENUM('Male', 'Female'),
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated'),
    height DECIMAL(5,2),
    weight DECIMAL(5,2),
    blood_type VARCHAR(10),
    
    -- Government IDs
    gsis_id VARCHAR(50),
    pagibig_id VARCHAR(50),
    philhealth_no VARCHAR(50),
    sss_no VARCHAR(50),
    tin_no VARCHAR(50),
    agency_employee_no VARCHAR(50),
    
    -- Citizenship
    citizenship_type VARCHAR(50),
    citizenship_country VARCHAR(100),
    
    -- Residential Address
    residential_address TEXT,
    residential_subdivision VARCHAR(100),
    residential_barangay VARCHAR(100),
    residential_city VARCHAR(100),
    residential_province VARCHAR(100),
    residential_zipcode VARCHAR(20),
    
    -- Permanent Address
    permanent_address TEXT,
    permanent_subdivision VARCHAR(100),
    permanent_barangay VARCHAR(100),
    permanent_city VARCHAR(100),
    permanent_province VARCHAR(100),
    permanent_zipcode VARCHAR(20),
    
    -- Contact Information
    telephone VARCHAR(50),
    mobile VARCHAR(50),
    email VARCHAR(255),
    
    -- II. Family Background
    spouse_surname VARCHAR(100),
    spouse_firstname VARCHAR(100),
    spouse_middlename VARCHAR(100),
    spouse_occupation VARCHAR(100),
    spouse_employer VARCHAR(255),
    spouse_business_address TEXT,
    spouse_telephone VARCHAR(50),
    
    father_surname VARCHAR(100),
    father_firstname VARCHAR(100),
    father_middlename VARCHAR(100),
    
    mother_surname VARCHAR(100),
    mother_firstname VARCHAR(100),
    mother_middlename VARCHAR(100),
    
    -- Children (stored as JSON array)
    children JSON,
    
    -- III. Educational Background (stored as JSON array)
    education JSON,
    
    -- IV. Civil Service Eligibility (stored as JSON array)
    eligibility JSON,
    
    -- V. Work Experience (stored as JSON array)
    work_experience JSON,
    
    -- VI. Voluntary Work (stored as JSON array)
    voluntary_work JSON,
    
    -- VII. Learning and Development (stored as JSON array)
    training JSON,
    
    -- VIII. Other Information
    special_skills TEXT,
    distinctions TEXT,
    memberships TEXT,
    
    -- IX. Additional Application Info
    current_position VARCHAR(150),
    current_company VARCHAR(255),
    notice_period VARCHAR(100),
    expected_salary DECIMAL(10,2),
    application_source VARCHAR(100),
    
    -- X. Character References (stored as JSON array)
    `references` JSON,
    
    -- File storage (PDF/JSON stored in database)
    pds_file_blob LONGBLOB COMMENT 'PDF file content stored in database',
    pds_file_name VARCHAR(255) COMMENT 'Original filename',
    pds_file_type VARCHAR(50) COMMENT 'MIME type (application/pdf, application/json)',
    pds_file_size INT COMMENT 'File size in bytes',
    json_file_blob LONGBLOB COMMENT 'JSON file content stored in database',
    
    -- File paths (optional - for backward compatibility)
    pds_file_path VARCHAR(255),
    json_file_path VARCHAR(255),
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE,
    INDEX idx_candidate (candidate_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NEW TABLE: Certifications
-- ============================================
CREATE TABLE IF NOT EXISTS certifications (
    certification_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    skill_id INT,

    -- Certification Details
    certification_name VARCHAR(255) NOT NULL,
    issuing_organization VARCHAR(255) NOT NULL,
    certification_number VARCHAR(100),
    category VARCHAR(100),

    -- Proficiency and Assessment
    proficiency_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') NOT NULL,
    assessment_score DECIMAL(5,2),
    
    -- Dates
    issue_date DATE NOT NULL,
    expiry_date DATE,
    assessed_date DATE NOT NULL,
    
    -- Documentation
    certification_url VARCHAR(500),
    certificate_file_path VARCHAR(500),
    
    -- Status and Validation
    status ENUM('Active', 'Expired', 'Suspended', 'Pending Renewal') DEFAULT 'Active',
    verification_status ENUM('Verified', 'Pending', 'Failed') DEFAULT 'Pending',
    
    -- Cost and Training Info
    cost DECIMAL(10,2) DEFAULT 0,
    training_hours INT DEFAULT 0,
    cpe_credits DECIMAL(5,2) DEFAULT 0, -- Continuing Professional Education credits
    
    -- Renewal Information
    renewal_required BOOLEAN DEFAULT FALSE,
    renewal_period_months INT,
    renewal_reminder_sent BOOLEAN DEFAULT FALSE,
    next_renewal_date DATE,
    
    -- Additional Information
    prerequisites TEXT,
    description TEXT,
    notes TEXT,
    tags VARCHAR(255), -- For searching and categorization
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skill_matrix(skill_id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_employee_id (employee_id),
    INDEX idx_skill_id (skill_id),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_status (status),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- NEW TABLE: Training Feedback
-- ============================================
CREATE TABLE IF NOT EXISTS training_feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Who is providing feedback and what it's about
    employee_id INT NOT NULL,
    feedback_type ENUM('Training Session', 'Learning Resource', 'Trainer', 'Course') NOT NULL,
    
    -- What they're giving feedback on (only one will be filled)
    session_id INT NULL,
    resource_id INT NULL,
    trainer_id INT NULL,
    course_id INT NULL,
    
    -- Simple ratings (1-5 scale)
    overall_rating INT NOT NULL CHECK (overall_rating BETWEEN 1 AND 5),
    content_rating INT CHECK (content_rating BETWEEN 1 AND 5),
    instructor_rating INT CHECK (instructor_rating BETWEEN 1 AND 5),
    
    -- Text feedback
    what_worked_well TEXT,
    what_could_improve TEXT,
    additional_comments TEXT,
    
    -- Simple yes/no questions
    would_recommend BOOLEAN DEFAULT TRUE,
    met_expectations BOOLEAN DEFAULT TRUE,
    
    -- Basic info
    feedback_date DATE NOT NULL DEFAULT (CURRENT_DATE),
    is_anonymous BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES training_sessions(session_id) ON DELETE SET NULL,
    FOREIGN KEY (resource_id) REFERENCES learning_resources(resource_id) ON DELETE SET NULL,
    FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES training_courses(course_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
-- Run these to verify the migration was successful:

-- Check if new columns were added to job_openings
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'job_openings' AND COLUMN_NAME IN ('screening_level', 'ai_generated', 'approval_status');

-- Check if new tables were created
-- SHOW TABLES LIKE 'pds_data';
-- SHOW TABLES LIKE 'certifications';
-- SHOW TABLES LIKE 'training_feedback';

-- Check if vacancy_limit was added to departments
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'departments' AND COLUMN_NAME = 'vacancy_limit';

SELECT 'Migration completed successfully!' AS status;
