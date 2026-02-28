-- Create evaluation_comments table for storing additional comments
-- This table stores general feedback/comments for employee evaluations

CREATE TABLE IF NOT EXISTS evaluation_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    cycle_id INT NOT NULL,
    comments TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee_cycle (employee_id, cycle_id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_cycle_id (cycle_id)
);
