-- Create patient_feedback table
CREATE TABLE IF NOT EXISTS patient_feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_name VARCHAR(255) NOT NULL,
    department VARCHAR(100),
    feedback_type ENUM('Compliment', 'Complaint', 'Suggestion', 'Inquiry') NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comments TEXT,
    submitted_by INT,
    feedback_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_department (department),
    INDEX idx_feedback_date (feedback_date)
);

-- Create seminars table
CREATE TABLE IF NOT EXISTS seminars (
    seminar_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    speaker VARCHAR(255),
    location VARCHAR(255),
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_start_date (start_date),
    INDEX idx_status (status)
);

-- Add review_frequency column to performance_review_cycles if not exists
ALTER TABLE performance_review_cycles 
ADD COLUMN IF NOT EXISTS review_frequency ENUM('weekly', 'monthly', 'quarterly', 'semi-annual', 'annual') DEFAULT 'quarterly';

ALTER TABLE performance_review_cycles 
ADD COLUMN IF NOT EXISTS description TEXT;
