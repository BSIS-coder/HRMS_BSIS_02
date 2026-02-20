-- Create tax_brackets table for configurable tax rates
CREATE TABLE IF NOT EXISTS tax_brackets (
    bracket_id INT AUTO_INCREMENT PRIMARY KEY,
    tax_type VARCHAR(50) NOT NULL, -- e.g., 'Income Tax', 'Withholding Tax'
    min_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_salary DECIMAL(10,2) NULL, -- NULL for unlimited
    tax_rate DECIMAL(5,4) NOT NULL DEFAULT 0, -- e.g., 0.20 for 20%
    fixed_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    excess_over DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tax_type (tax_type),
    INDEX idx_salary_range (min_salary, max_salary)
);

-- Insert default BIR Income Tax brackets for monthly salary (2023 rates)
-- Note: These are approximate monthly equivalents of annual brackets
INSERT INTO tax_brackets (tax_type, min_salary, max_salary, tax_rate, fixed_amount, excess_over) VALUES
('Income Tax', 0, 20833, 0, 0, 0),
('Income Tax', 20834, 33332, 0.20, 0, 20833),
('Income Tax', 33333, 66665, 0.25, 2500, 33333),
('Income Tax', 66666, 166665, 0.30, 10833.33, 66666),
('Income Tax', 166666, 666665, 0.32, 40833.33, 166666),
('Income Tax', 666666, NULL, 0.35, 200833.33, 666666);
