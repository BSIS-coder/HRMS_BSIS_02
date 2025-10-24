-- ===============================
-- CORE USER AND EMPLOYEE TABLES
-- ===============================

-- Create user table (Admin, HR, and Employee access)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'hr', 'employee') NOT NULL,
    employee_id INT NULL, -- Links to employee_profiles for employee users
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create user roles table
    CREATE TABLE `user_roles` (
    role_id int(11) NOT NULL AUTO_INCREMENT,
    role_name varchar(50) NOT NULL,
    description text,
    PRIMARY KEY (`role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Create personal_information table
CREATE TABLE personal_information (
    personal_info_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Non-binary', 'Prefer not to say') NOT NULL,
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed') NOT NULL,
    nationality VARCHAR(50) NOT NULL,
    tax_id VARCHAR(20),
    social_security_number VARCHAR(20),
    phone_number VARCHAR(20) NOT NULL,
    emergency_contact_name VARCHAR(100),
    emergency_contact_relationship VARCHAR(50),
    emergency_contact_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE job_roles (
    job_role_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    department VARCHAR(150) NOT NULL,  -- Changed from 50 to 150
    min_salary DECIMAL(10,2),
    max_salary DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create employee_profiles table (no manager references)
CREATE TABLE employee_profiles (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    personal_info_id INT UNIQUE,
    job_role_id INT,
    employee_number VARCHAR(20) NOT NULL UNIQUE,
    hire_date DATE NOT NULL,
    employment_status ENUM('Full-time', 'Part-time', 'Contract', 'Intern', 'Terminated') NOT NULL,
    current_salary DECIMAL(10,2) NOT NULL,
    work_email VARCHAR(100) UNIQUE,
    work_phone VARCHAR(20),
    location VARCHAR(100),
    remote_work BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personal_info_id) REFERENCES personal_information(personal_info_id) ON DELETE SET NULL,
    FOREIGN KEY (job_role_id) REFERENCES job_roles(job_role_id) ON DELETE SET NULL
);

-- Add foreign key constraint to users table after employee_profiles is created
-- ALTER TABLE users ADD FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE SET NULL;

-- Create departments table (no manager references)
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    description TEXT,
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- New Employment History Table
CREATE TABLE employment_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    
    -- Core employment details
    job_title VARCHAR(150) NOT NULL,               -- Job Position / Title
    department_id INT,                             -- Department / Division (FK to departments table)
    employment_type ENUM('Full-time','Part-time','Contractual','Project-based','Casual','Intern') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    employment_status ENUM('Active','Resigned','Terminated','Retired','End of Contract','Transferred') NOT NULL,
    
    -- Reporting and assignment
    reporting_manager_id INT,                      -- Supervisor (FK to employee_profiles)
    location VARCHAR(150),                         -- Office/Branch/Remote
    
    -- Compensation
    base_salary DECIMAL(10,2) NOT NULL,
    allowances DECIMAL(10,2) DEFAULT 0.00,
    bonuses DECIMAL(10,2) DEFAULT 0.00,
    salary_adjustments DECIMAL(10,2) DEFAULT 0.00,
    
    -- Career movement
    reason_for_change VARCHAR(255),                -- Promotion, resignation, transfer, etc.
    promotions_transfers TEXT,                     -- Notes about movement history
    
    -- Performance & Training
    duties_responsibilities TEXT,
    performance_evaluations TEXT,
    training_certifications TEXT,
    
    -- Contract & Notes
    contract_details TEXT,                         -- Contract type, duration, renewals
    remarks TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    FOREIGN KEY (reporting_manager_id) REFERENCES employee_profiles(employee_id) ON DELETE SET NULL
);


CREATE TABLE document_management (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    document_type ENUM('Contract', 'ID', 'Resume', 'Certificate', 'Performance Review', 'Appointment', 'Training', 'Appreciation', 'Other') NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    document_status ENUM('Active', 'Expired', 'Pending Review') DEFAULT 'Active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);
-- ===============================
-- PAYROLL MANAGEMENT
-- ===============================

-- Create salary structure table
CREATE TABLE salary_structures (
    salary_structure_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    basic_salary DECIMAL(10, 2) NOT NULL,
    allowances DECIMAL(10, 2) DEFAULT 0,
    deductions DECIMAL(10, 2) DEFAULT 0,
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create payroll cycles table
CREATE TABLE payroll_cycles (
    payroll_cycle_id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_name VARCHAR(50) NOT NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    pay_date DATE NOT NULL,
    status ENUM('Pending', 'Processing', 'Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create tax deductions table
CREATE TABLE tax_deductions (
    tax_deduction_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    tax_type VARCHAR(50) NOT NULL,
    tax_percentage DECIMAL(5, 2),
    tax_amount DECIMAL(10, 2),
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create statutory deductions table
CREATE TABLE statutory_deductions (
    statutory_deduction_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    deduction_type VARCHAR(50) NOT NULL,
    deduction_amount DECIMAL(10, 2) NOT NULL,
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create benefits plans table
CREATE TABLE benefits_plans (
    benefit_plan_id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(100) NOT NULL,
    plan_type VARCHAR(50) NOT NULL,
    description TEXT,
    eligibility_criteria TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create employee benefits table
CREATE TABLE employee_benefits (
    benefit_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    benefit_plan_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    benefit_amount DECIMAL(10, 2),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (benefit_plan_id) REFERENCES benefits_plans(benefit_plan_id) ON DELETE CASCADE
);

-- Create bonus payments table
CREATE TABLE bonus_payments (
    bonus_payment_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    bonus_type VARCHAR(50) NOT NULL,
    bonus_amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payroll_cycle_id INT,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_cycle_id) REFERENCES payroll_cycles(payroll_cycle_id) ON DELETE SET NULL
);

-- Create compensation packages table
CREATE TABLE compensation_packages (
    compensation_package_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    package_name VARCHAR(100) NOT NULL,
    base_salary DECIMAL(10, 2) NOT NULL,
    variable_pay DECIMAL(10, 2) DEFAULT 0,
    benefits_summary TEXT,
    total_compensation DECIMAL(10, 2) NOT NULL,
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create payroll transactions table
CREATE TABLE payroll_transactions (
    payroll_transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    payroll_cycle_id INT NOT NULL,
    gross_pay DECIMAL(10, 2) NOT NULL,
    tax_deductions DECIMAL(10, 2) DEFAULT 0,
    statutory_deductions DECIMAL(10, 2) DEFAULT 0,
    other_deductions DECIMAL(10, 2) DEFAULT 0,
    net_pay DECIMAL(10, 2) NOT NULL,
    processed_date DATETIME NOT NULL,
    status ENUM('Pending', 'Processed', 'Paid', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (payroll_cycle_id) REFERENCES payroll_cycles(payroll_cycle_id) ON DELETE CASCADE
);

-- Create payment disbursements table
CREATE TABLE payment_disbursements (
    payment_disbursement_id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_transaction_id INT NOT NULL,
    employee_id INT NOT NULL,
    payment_method ENUM('Bank Transfer', 'Check', 'Cash', 'Other') NOT NULL,
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    payment_amount DECIMAL(10, 2) NOT NULL,
    disbursement_date DATETIME NOT NULL,
    status ENUM('Pending', 'Processed', 'Failed') DEFAULT 'Pending',
    reference_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_transaction_id) REFERENCES payroll_transactions(payroll_transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create payslips table
CREATE TABLE payslips (
    payslip_id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_transaction_id INT NOT NULL,
    employee_id INT NOT NULL,
    payslip_url VARCHAR(255),
    generated_date DATETIME NOT NULL,
    status ENUM('Generated', 'Sent', 'Viewed') DEFAULT 'Generated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_transaction_id) REFERENCES payroll_transactions(payroll_transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- ===============================
-- PERFORMANCE MANAGEMENT
-- ===============================

-- Create competencies table
CREATE TABLE competencies (
    competency_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create employee competencies table (no assessor reference)
CREATE TABLE employee_competencies (
    employee_id INT NOT NULL,
    competency_id INT NOT NULL,
    rating INT NOT NULL,
    assessment_date DATE NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (employee_id, competency_id, assessment_date),
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (competency_id) REFERENCES competencies(competency_id) ON DELETE CASCADE
);

-- Create performance review cycles table
CREATE TABLE performance_review_cycles (
    cycle_id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Upcoming', 'In Progress', 'Completed') DEFAULT 'Upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create performance reviews table (no reviewer references)
CREATE TABLE performance_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    cycle_id INT NOT NULL,
    review_date DATE NOT NULL,
    overall_rating DECIMAL(3,2) NOT NULL,
    strengths TEXT,
    areas_of_improvement TEXT,
    comments TEXT,
    status ENUM('Draft', 'Submitted', 'Acknowledged', 'Finalized') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (cycle_id) REFERENCES performance_review_cycles(cycle_id) ON DELETE CASCADE
);

-- Create goals table (no supervisor reference)
CREATE TABLE goals (
    goal_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Not Started', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Not Started',
    progress DECIMAL(5,2) DEFAULT 0,
    weight DECIMAL(5,2) DEFAULT 100.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create goal updates table (no creator reference)
CREATE TABLE goal_updates (
    update_id INT AUTO_INCREMENT PRIMARY KEY,
    goal_id INT NOT NULL,
    update_date DATE NOT NULL,
    progress DECIMAL(5,2) NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (goal_id) REFERENCES goals(goal_id) ON DELETE CASCADE
);

-- Create performance metrics table (no recorder reference)
CREATE TABLE performance_metrics (
    metric_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,2) NOT NULL,
    recorded_date DATE NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create development plans table (no coach reference)
CREATE TABLE development_plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    plan_description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Draft', 'Active', 'Completed', 'Cancelled') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create development activities table
CREATE TABLE development_activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    activity_name VARCHAR(100) NOT NULL,
    activity_type ENUM('Training', 'Mentoring', 'Project', 'Education', 'Other') NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    status ENUM('Not Started', 'In Progress', 'Completed') DEFAULT 'Not Started',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES development_plans(plan_id) ON DELETE CASCADE
);

-- ===============================
-- LEAVE AND ATTENDANCE MANAGEMENT
-- ===============================

-- Create public holidays table
CREATE TABLE public_holidays (
    holiday_id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE UNIQUE NOT NULL,
    holiday_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create leave types table
CREATE TABLE leave_types (
    leave_type_id INT AUTO_INCREMENT PRIMARY KEY,
    leave_type_name VARCHAR(50) NOT NULL,
    description TEXT,
    paid BOOLEAN DEFAULT TRUE,
    default_days DECIMAL(5,2) DEFAULT 0,
    carry_forward BOOLEAN DEFAULT FALSE,
    max_carry_forward_days DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create leave balances table
CREATE TABLE leave_balances (
    balance_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    year YEAR NOT NULL,
    total_leaves DECIMAL(5,2) NOT NULL,
    leaves_taken DECIMAL(5,2) DEFAULT 0,
    leaves_pending DECIMAL(5,2) DEFAULT 0,
    leaves_remaining DECIMAL(5,2),
    last_updated DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(leave_type_id) ON DELETE CASCADE
);

-- Create leave requests table (no approver reference)
CREATE TABLE leave_requests (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5,2) NOT NULL,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') DEFAULT 'Pending',
    applied_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_on DATETIME,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(leave_type_id) ON DELETE CASCADE
);

-- Create shifts table
CREATE TABLE shifts (
    shift_id INT AUTO_INCREMENT PRIMARY KEY,
    shift_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create employee shifts table
CREATE TABLE employee_shifts (
    employee_shift_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    shift_id INT NOT NULL,
    assigned_date DATE NOT NULL,
    is_overtime BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(shift_id) ON DELETE CASCADE
);

-- Create attendance table
CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    clock_in DATETIME,
    clock_out DATETIME,
    status ENUM('Present', 'Absent', 'Late', 'Half Day', 'On Leave') NOT NULL,
    working_hours DECIMAL(5,2),
    overtime_hours DECIMAL(5,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create attendance_summary table
CREATE TABLE attendance_summary (
    summary_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month INT NOT NULL,
    year YEAR NOT NULL,
    total_present INT DEFAULT 0,
    total_absent INT DEFAULT 0,
    total_late INT DEFAULT 0,
    total_leave INT DEFAULT 0,
    total_working_hours DECIMAL(7,2) DEFAULT 0,
    total_overtime_hours DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    UNIQUE KEY (employee_id, month, year)
);

-- ===============================
-- EXIT MANAGEMENT
-- ===============================

-- Create exits table (no initiator/approver references)
CREATE TABLE exits (
    exit_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    exit_type ENUM('Resignation', 'Termination', 'Retirement', 'End of Contract', 'Other') NOT NULL,
    exit_reason TEXT,
    notice_date DATE NOT NULL,
    exit_date DATE NOT NULL,
    status ENUM('Pending', 'Processing', 'Completed', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create exit checklist table (no completion reference)
CREATE TABLE exit_checklist (
    checklist_id INT AUTO_INCREMENT PRIMARY KEY,
    exit_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    description TEXT,
    responsible_department VARCHAR(50) NOT NULL,
    status ENUM('Pending', 'Completed', 'Not Applicable') DEFAULT 'Pending',
    completed_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exit_id) REFERENCES exits(exit_id) ON DELETE CASCADE
);

-- Create exit interviews table (no interviewer reference)
CREATE TABLE exit_interviews (
    interview_id INT AUTO_INCREMENT PRIMARY KEY,
    exit_id INT NOT NULL,
    employee_id INT NOT NULL,
    interview_date DATE NOT NULL,
    feedback TEXT,
    improvement_suggestions TEXT,
    reason_for_leaving TEXT,
    would_recommend BOOLEAN,
    status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exit_id) REFERENCES exits(exit_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create knowledge transfers table (no successor/completion references)
CREATE TABLE knowledge_transfers (
    transfer_id INT AUTO_INCREMENT PRIMARY KEY,
    exit_id INT NOT NULL,
    employee_id INT NOT NULL,
    handover_details TEXT,
    start_date DATE,
    completion_date DATE,
    status ENUM('Not Started', 'In Progress', 'Completed', 'N/A') DEFAULT 'Not Started',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exit_id) REFERENCES exits(exit_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create settlements table (no processor reference)
CREATE TABLE settlements (
    settlement_id INT AUTO_INCREMENT PRIMARY KEY,
    exit_id INT NOT NULL,
    employee_id INT NOT NULL,
    last_working_day DATE NOT NULL,
    final_salary DECIMAL(10,2) NOT NULL,
    severance_pay DECIMAL(10,2) DEFAULT 0,
    unused_leave_payout DECIMAL(10,2) DEFAULT 0,
    deductions DECIMAL(10,2) DEFAULT 0,
    final_settlement_amount DECIMAL(10,2) NOT NULL,
    payment_date DATE,
    payment_method VARCHAR(50),
    status ENUM('Pending', 'Processing', 'Completed') DEFAULT 'Pending',
    processed_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exit_id) REFERENCES exits(exit_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create exit documents table (no uploader reference)
CREATE TABLE exit_documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    exit_id INT NOT NULL,
    employee_id INT NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    document_url VARCHAR(255) NOT NULL,
    uploaded_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exit_id) REFERENCES exits(exit_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create post exit surveys table
CREATE TABLE post_exit_surveys (
    survey_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    exit_id INT NOT NULL,
    survey_date DATE NOT NULL,
    survey_response TEXT,
    satisfaction_rating INT,
    submitted_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (exit_id) REFERENCES exits(exit_id) ON DELETE CASCADE
);

-- ===============================
-- RECRUITMENT MANAGEMENT
-- ===============================

-- Create job_openings table (no hiring manager reference)
CREATE TABLE job_openings (
    job_opening_id INT AUTO_INCREMENT PRIMARY KEY,
    job_role_id INT NOT NULL,
    department_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT NOT NULL,
    responsibilities TEXT NOT NULL,
    location VARCHAR(100),
    employment_type ENUM('Full-time', 'Part-time', 'Contract', 'Temporary', 'Internship') NOT NULL,
    experience_level VARCHAR(50),
    education_requirements TEXT,
    salary_range_min DECIMAL(10,2),
    salary_range_max DECIMAL(10,2),
    vacancy_count INT DEFAULT 1,
    posting_date DATE NOT NULL,
    closing_date DATE,
    status ENUM('Draft', 'Open', 'On Hold', 'Closed', 'Cancelled') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_role_id) REFERENCES job_roles(job_role_id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE
);

-- Create candidates table
CREATE TABLE candidates (
    candidate_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    resume_url VARCHAR(255),
    cover_letter_url VARCHAR(255),
    source VARCHAR(100),
    current_position VARCHAR(100),
    current_company VARCHAR(100),
    notice_period VARCHAR(50),
    expected_salary DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create job_applications table
CREATE TABLE job_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    job_opening_id INT NOT NULL,
    candidate_id INT NOT NULL,
    application_date DATETIME NOT NULL,
    status ENUM('Applied', 'Screening', 'Interview', 'Assessment', 'Reference Check', 'Offer', 'Hired', 'Rejected', 'Withdrawn') DEFAULT 'Applied',
    notes TEXT,
    assessment_scores JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_opening_id) REFERENCES job_openings(job_opening_id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE
);

-- Create interview_stages table
CREATE TABLE interview_stages (
    stage_id INT AUTO_INCREMENT PRIMARY KEY,
    job_opening_id INT NOT NULL,
    stage_name VARCHAR(100) NOT NULL,
    stage_order INT NOT NULL,
    description TEXT,
    is_mandatory BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_opening_id) REFERENCES job_openings(job_opening_id) ON DELETE CASCADE
);

-- Create interviews table (no interviewer reference)
CREATE TABLE interviews (
    interview_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    stage_id INT NOT NULL,
    schedule_date DATETIME NOT NULL,
    duration INT NOT NULL COMMENT 'Duration in minutes',
    location VARCHAR(255),
    interview_type ENUM('In-person', 'Phone', 'Video Call', 'Technical Assessment') NOT NULL,
    status ENUM('Scheduled', 'Completed', 'Rescheduled', 'Cancelled') DEFAULT 'Scheduled',
    feedback TEXT,
    rating DECIMAL(3,2),
    recommendation ENUM('Strong Yes', 'Yes', 'Maybe', 'No', 'Strong No'),
    completed_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES job_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES interview_stages(stage_id) ON DELETE CASCADE
);

-- Create job_offers table (no approver/creator references)
CREATE TABLE job_offers (
    offer_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    job_opening_id INT NOT NULL,
    candidate_id INT NOT NULL,
    offered_salary DECIMAL(10,2) NOT NULL,
    offered_benefits TEXT,
    start_date DATE,
    expiration_date DATE NOT NULL,
    approval_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    offer_status ENUM('Draft', 'Sent', 'Accepted', 'Negotiating', 'Declined', 'Expired') DEFAULT 'Draft',
    offer_letter_url VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES job_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (job_opening_id) REFERENCES job_openings(job_opening_id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id) ON DELETE CASCADE
);

-- Create recruitment_analytics table
CREATE TABLE recruitment_analytics (
    analytics_id INT AUTO_INCREMENT PRIMARY KEY,
    job_opening_id INT NOT NULL,
    total_applications INT DEFAULT 0,
    applications_per_day DECIMAL(5,2) DEFAULT 0,
    average_processing_time INT DEFAULT 0 COMMENT 'In days',
    average_time_to_hire INT DEFAULT 0 COMMENT 'In days',
    offer_acceptance_rate DECIMAL(5,2) DEFAULT 0,
    recruitment_source_breakdown JSON,
    cost_per_hire DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_opening_id) REFERENCES job_openings(job_opening_id) ON DELETE CASCADE
);

-- Create onboarding_tasks table
CREATE TABLE onboarding_tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(255) NOT NULL,
    description TEXT,
    department_id INT,
    task_type ENUM('Administrative', 'Equipment', 'Training', 'Introduction', 'Documentation', 'Other') NOT NULL,
    is_mandatory BOOLEAN DEFAULT TRUE,
    default_due_days INT DEFAULT 7 COMMENT 'Days after joining date',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL
);

-- Create employee_onboarding table (no hiring manager reference)
CREATE TABLE employee_onboarding (
    onboarding_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    start_date DATE NOT NULL,
    expected_completion_date DATE NOT NULL,
    status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create employee_onboarding_tasks table (no assignment/completion references)
CREATE TABLE employee_onboarding_tasks (
    employee_task_id INT AUTO_INCREMENT PRIMARY KEY,
    onboarding_id INT NOT NULL,
    task_id INT NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Not Started', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Not Started',
    completion_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (onboarding_id) REFERENCES employee_onboarding(onboarding_id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES onboarding_tasks(task_id) ON DELETE CASCADE
);

-- ===============================
-- TRAINING AND DEVELOPMENT
-- ===============================

-- Create training_courses table
CREATE TABLE training_courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    delivery_method ENUM('Online', 'Classroom', 'Workshop', 'Self-paced', 'Hybrid') NOT NULL,
    duration INT COMMENT 'Duration in hours',
    max_participants INT,
    prerequisites TEXT,
    materials_url VARCHAR(255),
    status ENUM('Active', 'Inactive', 'In Development') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create trainers table (no employee reference)
CREATE TABLE trainers (
    trainer_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    specialization VARCHAR(255),
    bio TEXT,
    is_internal BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create training_sessions table
CREATE TABLE training_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    trainer_id INT NOT NULL,
    session_name VARCHAR(255) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    location VARCHAR(255),
    capacity INT NOT NULL,
    cost_per_participant DECIMAL(10,2),
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES training_courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE CASCADE
);

-- Create training_enrollments table (no nominator reference)
CREATE TABLE training_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    employee_id INT NOT NULL,
    enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Enrolled', 'Completed', 'Dropped', 'Failed', 'Waitlisted') DEFAULT 'Enrolled',
    completion_date DATE,
    score DECIMAL(5,2),
    feedback TEXT,
    certificate_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES training_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create learning_resources table
CREATE TABLE learning_resources (
    resource_id INT AUTO_INCREMENT PRIMARY KEY,
    resource_name VARCHAR(255) NOT NULL,
    resource_type ENUM('Book', 'Online Course', 'Video', 'Article', 'Webinar', 'Podcast', 'Other') NOT NULL,
    description TEXT,
    resource_url VARCHAR(255),
    author VARCHAR(100),
    publication_date DATE,
    duration VARCHAR(50),
    tags VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create employee_resources table (no assigner reference)
CREATE TABLE employee_resources (
    employee_resource_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    resource_id INT NOT NULL,
    assigned_date DATE NOT NULL,
    due_date DATE,
    completed_date DATE,
    status ENUM('Assigned', 'In Progress', 'Completed', 'Overdue') DEFAULT 'Assigned',
    rating INT,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (resource_id) REFERENCES learning_resources(resource_id) ON DELETE CASCADE
);

-- Create skill_matrix table
CREATE TABLE skill_matrix (
    skill_id INT AUTO_INCREMENT PRIMARY KEY,
    skill_name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create employee_skills table (no assessor reference)
CREATE TABLE employee_skills (
    employee_skill_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    skill_id INT NOT NULL,
    proficiency_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') NOT NULL,
    assessed_date DATE NOT NULL,
    certification_url VARCHAR(255),
    expiry_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skill_matrix(skill_id) ON DELETE CASCADE
);

-- Create training_needs_assessment table (no assessor reference)
CREATE TABLE training_needs_assessment (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    assessment_date DATE NOT NULL,
    skills_gap TEXT,
    recommended_trainings TEXT,
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
    status ENUM('Identified', 'In Progress', 'Completed') DEFAULT 'Identified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE
);

-- Create career_paths table
CREATE TABLE career_paths (
    path_id INT AUTO_INCREMENT PRIMARY KEY,
    path_name VARCHAR(100) NOT NULL,
    description TEXT,
    department_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL
);

-- Create career_path_stages table
CREATE TABLE career_path_stages (
    stage_id INT AUTO_INCREMENT PRIMARY KEY,
    path_id INT NOT NULL,
    job_role_id INT NOT NULL,
    stage_order INT NOT NULL,
    minimum_time_in_role INT COMMENT 'In months',
    required_skills TEXT,
    required_experience TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (path_id) REFERENCES career_paths(path_id) ON DELETE CASCADE,
    FOREIGN KEY (job_role_id) REFERENCES job_roles(job_role_id) ON DELETE CASCADE
);

-- Create employee_career_paths table (no mentor reference)
CREATE TABLE employee_career_paths (
    employee_path_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    path_id INT NOT NULL,
    current_stage_id INT NOT NULL,
    start_date DATE NOT NULL,
    target_completion_date DATE,
    status ENUM('Active', 'Completed', 'On Hold', 'Abandoned') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (path_id) REFERENCES career_paths(path_id) ON DELETE CASCADE,
    FOREIGN KEY (current_stage_id) REFERENCES career_path_stages(stage_id) ON DELETE CASCADE
);

-- Create certifications table
CREATE TABLE certifications (
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
);

-- ===============================
-- SIMPLIFIED TRAINING FEEDBACK TABLE
-- ===============================

CREATE TABLE training_feedback (
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
);

-- ===============================
-- SAMPLE DATA FOR TESTING
-- ===============================

-- Insert sample personal information for municipal employees (same as before)
INSERT INTO personal_information (first_name, last_name, date_of_birth, gender, marital_status, nationality, tax_id, social_security_number, phone_number, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone) VALUES
('Maria', 'Santos', '1985-03-12', 'Female', 'Married', 'Filipino', '123-45-6789', '123456789', '0917-123-4567', 'Carlos Santos', 'Spouse', '0917-567-8901'),
('Roberto', 'Cruz', '1978-07-20', 'Male', 'Married', 'Filipino', '234-56-7890', '234567890', '0917-234-5678', 'Elena Cruz', 'Spouse', '0917-678-9012'),
('Jennifer', 'Reyes', '1988-11-08', 'Female', 'Single', 'Filipino', '345-67-8901', '345678901', '0917-345-6789', 'Mark Reyes', 'Brother', '0917-789-0123'),
('Antonio', 'Garcia', '1975-01-25', 'Male', 'Married', 'Filipino', '456-78-9012', '456789012', '0917-456-7890', 'Rosa Garcia', 'Spouse', '0917-890-1234'),
('Lisa', 'Mendoza', '1982-09-14', 'Female', 'Divorced', 'Filipino', '567-89-0123', '567890123', '0917-567-8901', 'John Mendoza', 'Father', '0917-901-2345'),
('Michael', 'Torres', '1980-06-03', 'Male', 'Married', 'Filipino', '678-90-1234', '678901234', '0917-678-9012', 'Anna Torres', 'Spouse', '0917-012-3456'),
('Carmen', 'Dela Cruz', '1987-12-18', 'Female', 'Single', 'Filipino', '789-01-2345', '789012345', '0917-789-0123', 'Pedro Dela Cruz', 'Father', '0917-123-4567'),
('Ricardo', 'Villanueva', '1970-04-07', 'Male', 'Married', 'Filipino', '890-12-3456', '890123456', '0917-890-1234', 'Diana Villanueva', 'Spouse', '0917-234-5678'),
('Sandra', 'Pascual', '1984-08-29', 'Female', 'Married', 'Filipino', '901-23-4567', '901234567', '0917-901-2345', 'Luis Pascual', 'Spouse', '0917-345-6789'),
('Jose', 'Ramos', '1972-05-15', 'Male', 'Married', 'Filipino', '012-34-5678', '012345678', '0917-012-3456', 'Teresa Ramos', 'Spouse', '0917-456-7890'),
('Ana', 'Morales', '1986-10-30', 'Female', 'Single', 'Filipino', '123-56-7890', '123567890', '0917-135-7890', 'Maria Morales', 'Mother', '0917-579-0123'),
('Pablo', 'Fernandez', '1979-02-22', 'Male', 'Married', 'Filipino', '234-67-8901', '234678901', '0917-246-7890', 'Carmen Fernandez', 'Spouse', '0917-680-1234'),
('Grace', 'Lopez', '1983-09-07', 'Female', 'Married', 'Filipino', '345-78-9012', '345789012', '0917-357-8901', 'David Lopez', 'Spouse', '0917-791-2345'),
('Eduardo', 'Hernandez', '1977-12-03', 'Male', 'Married', 'Filipino', '456-89-0123', '456890123', '0917-468-9012', 'Sofia Hernandez', 'Spouse', '0917-802-3456'),
('Rosario', 'Gonzales', '1989-06-28', 'Female', 'Single', 'Filipino', '567-90-1234', '567901234', '0917-579-0123', 'Miguel Gonzales', 'Father', '0917-913-4567');

-- Insert sample data for departments
INSERT INTO departments (department_name, description, location) VALUES
('Office of the Mayor', 'Executive office responsible for municipal governance and administration', 'City Hall - 2nd Floor'),
('Sangguniang Bayan', 'Municipal legislative body responsible for enacting local ordinances', 'City Hall - Session Hall'),
('Municipal Treasurer''s Office', 'Handles municipal revenue collection, treasury operations, and financial management', 'City Hall - 1st Floor'),
('Municipal Budget Office', 'Responsible for budget preparation, monitoring, and financial planning', 'City Hall - 1st Floor'),
('Municipal Accountant''s Office', 'Manages municipal accounting, bookkeeping, and financial reporting', 'City Hall - 1st Floor'),
('Municipal Planning & Development Office', 'Handles municipal planning, development programs, and project management', 'City Hall - 3rd Floor'),
('Municipal Engineer''s Office', 'Oversees infrastructure projects, public works, and engineering services', 'Engineering Building'),
('Municipal Civil Registrar''s Office', 'Manages civil registration services and vital statistics', 'City Hall - Ground Floor'),
('Municipal Health Office', 'Provides public health services and healthcare programs', 'Health Center Building'),
('Municipal Social Welfare & Development Office', 'Administers social services and community development programs', 'Social Services Building'),
('Municipal Agriculture Office', 'Supports agricultural development and provides farming assistance', 'Agriculture Extension Office'),
('Municipal Assessor''s Office', 'Conducts property assessment and real property taxation', 'City Hall - Ground Floor'),
('Municipal Human Resource & Administrative Office', 'Manages personnel administration and human resources', 'City Hall - 2nd Floor'),
('Municipal Disaster Risk Reduction & Management Office', 'Coordinates disaster preparedness and emergency response', 'Emergency Operations Center'),
('General Services Office', 'Provides general administrative support and facility management', 'City Hall - Basement');

-- Insert sample data for job_roles
INSERT INTO job_roles (title, description, department, min_salary, max_salary) VALUES
-- Elected Officials (Higher salary grades)
('Mayor', 'Chief executive of the municipality responsible for overall governance', 'Office of the Mayor', 80000.00, 120000.00),
('Vice Mayor', 'Presiding officer of Sangguniang Bayan and assistant to the Mayor', 'Sangguniang Bayan', 70000.00, 100000.00),
('Councilor', 'Member of the municipal legislative body', 'Sangguniang Bayan', 60000.00, 85000.00),

-- Department Heads / Appointed Officials
('Municipal Treasurer', 'Head of treasury operations and revenue collection', 'Municipal Treasurer''s Office', 55000.00, 75000.00),
('Municipal Budget Officer', 'Responsible for municipal budget preparation and monitoring', 'Municipal Budget Office', 50000.00, 70000.00),
('Municipal Accountant', 'Chief accountant responsible for municipal financial records', 'Municipal Accountant''s Office', 50000.00, 70000.00),
('Municipal Planning & Development Coordinator', 'Head of municipal planning and development programs', 'Municipal Planning & Development Office', 55000.00, 75000.00),
('Municipal Engineer', 'Chief engineer overseeing infrastructure and public works', 'Municipal Engineer''s Office', 60000.00, 85000.00),
('Municipal Civil Registrar', 'Head of civil registration services', 'Municipal Civil Registrar''s Office', 45000.00, 65000.00),
('Municipal Health Officer', 'Chief medical officer and head of health services', 'Municipal Health Office', 70000.00, 95000.00),
('Municipal Social Welfare Officer', 'Head of social welfare and development programs', 'Municipal Social Welfare & Development Office', 50000.00, 70000.00),
('Municipal Agriculturist', 'Agricultural development officer and extension coordinator', 'Municipal Agriculture Office', 50000.00, 70000.00),
('Municipal Assessor', 'Head of property assessment and real property taxation', 'Municipal Assessor''s Office', 50000.00, 70000.00),
('Municipal HR Officer', 'Head of human resources and personnel administration', 'Municipal Human Resource & Administrative Office', 50000.00, 70000.00),
('MDRRM Officer', 'Disaster risk reduction and management coordinator', 'Municipal Disaster Risk Reduction & Management Office', 45000.00, 65000.00),
('General Services Officer', 'Head of general services and facility management', 'General Services Office', 40000.00, 60000.00),

-- Technical & Professional Staff
('Nurse', 'Provides nursing services and healthcare support', 'Municipal Health Office', 35000.00, 50000.00),
('Midwife', 'Provides maternal and child health services', 'Municipal Health Office', 30000.00, 45000.00),
('Sanitary Inspector', 'Conducts health and sanitation inspections', 'Municipal Health Office', 28000.00, 40000.00),
('Social Worker', 'Provides social services and community assistance', 'Municipal Social Welfare & Development Office', 35000.00, 50000.00),
('Agricultural Technician', 'Provides technical support for agricultural programs', 'Municipal Agriculture Office', 28000.00, 40000.00),
('Civil Engineer', 'Designs and supervises infrastructure projects', 'Municipal Engineer''s Office', 45000.00, 65000.00),
('CAD Operator', 'Creates technical drawings and engineering plans', 'Municipal Engineer''s Office', 30000.00, 45000.00),
('Building Inspector', 'Inspects construction projects for code compliance', 'Municipal Engineer''s Office', 35000.00, 50000.00),
('Budget Analyst', 'Analyzes budget data and prepares financial reports', 'Municipal Budget Office', 35000.00, 50000.00),
('Accounting Staff', 'Handles bookkeeping and accounting transactions', 'Municipal Accountant''s Office', 25000.00, 38000.00),
('Planning Staff', 'Assists in municipal planning and development activities', 'Municipal Planning & Development Office', 30000.00, 45000.00),

-- Administrative & Support Staff
('Administrative Aide', 'Provides administrative support to various departments', 'Municipal Human Resource & Administrative Office', 22000.00, 35000.00),
('Clerk', 'Handles clerical work and document processing', 'Municipal Civil Registrar''s Office', 20000.00, 32000.00),
('Cashier', 'Processes payments and financial transactions', 'Municipal Treasurer''s Office', 22000.00, 35000.00),
('Collection Officer', 'Collects municipal revenues and taxes', 'Municipal Treasurer''s Office', 25000.00, 38000.00),
('Property Custodian', 'Manages and maintains municipal property and assets', 'General Services Office', 22000.00, 35000.00),
('Maintenance Worker', 'Performs maintenance and repair work on municipal facilities', 'General Services Office', 18000.00, 28000.00),
('Utility Worker', 'Provides general utility and janitorial services', 'General Services Office', 16000.00, 25000.00),
('Driver', 'Operates municipal vehicles and provides transportation services', 'General Services Office', 20000.00, 32000.00),
('Security Personnel', 'Provides security services for municipal facilities', 'General Services Office', 18000.00, 28000.00),
('Legislative Staff', 'Provides secretarial support to Sangguniang Bayan', 'Sangguniang Bayan', 25000.00, 38000.00);

-- Insert sample data for employee_profiles
INSERT INTO employee_profiles (personal_info_id, job_role_id, employee_number, hire_date, employment_status, current_salary, work_email, work_phone, location, remote_work) VALUES
-- Department Heads and Key Officials
(1, 4, 'MUN001', '2019-07-01', 'Full-time', 65000.00, 'maria.santos@municipality.gov.ph', '034-123-0001', 'City Hall - 1st Floor', FALSE),
(2, 8, 'MUN002', '2018-06-15', 'Full-time', 75000.00, 'roberto.cruz@municipality.gov.ph', '034-123-0002', 'Engineering Building', FALSE),
(3, 17, 'MUN003', '2020-01-20', 'Full-time', 42000.00, 'jennifer.reyes@municipality.gov.ph', '034-123-0003', 'Municipal Health Office', FALSE),
(4, 21, 'MUN004', '2019-03-10', 'Full-time', 38000.00, 'antonio.garcia@municipality.gov.ph', '034-123-0004', 'Municipal Engineer''s Office', FALSE),
(5, 20, 'MUN005', '2021-09-05', 'Full-time', 45000.00, 'lisa.mendoza@municipality.gov.ph', '034-123-0005', 'Municipal Social Welfare & Development Office', FALSE),
(6, 25, 'MUN006', '2020-11-12', 'Full-time', 28000.00, 'michael.torres@municipality.gov.ph', '034-123-0006', 'Municipal Accountant''s Office', FALSE),
(7, 27, 'MUN007', '2022-02-28', 'Full-time', 30000.00, 'carmen.delacruz@municipality.gov.ph', '034-123-0007', 'Municipal Civil Registrar''s Office', FALSE),
(8, 32, 'MUN008', '2021-05-18', 'Full-time', 22000.00, 'ricardo.villanueva@municipality.gov.ph', '034-123-0008', 'General Services Office', FALSE),
(9, 28, 'MUN009', '2020-09-10', 'Full-time', 32000.00, 'sandra.pascual@municipality.gov.ph', '034-123-0009', 'Municipal Treasurer''s Office', FALSE),
(10, 29, 'MUN010', '2019-12-01', 'Full-time', 35000.00, 'jose.ramos@municipality.gov.ph', '034-123-0010', 'Municipal Treasurer''s Office', FALSE),
(11, 26, 'MUN011', '2022-04-15', 'Full-time', 28000.00, 'ana.morales@municipality.gov.ph', '034-123-0011', 'Municipal Human Resource & Administrative Office', FALSE),
(12, 19, 'MUN012', '2021-08-20', 'Full-time', 40000.00, 'pablo.fernandez@municipality.gov.ph', '034-123-0012', 'Municipal Agriculture Office', FALSE),
(13, 18, 'MUN013', '2020-06-30', 'Full-time', 42000.00, 'grace.lopez@municipality.gov.ph', '034-123-0013', 'Municipal Health Office', FALSE),
(14, 31, 'MUN014', '2022-01-10', 'Full-time', 25000.00, 'eduardo.hernandez@municipality.gov.ph', '034-123-0014', 'General Services Office', FALSE),
(15, 33, 'MUN015', '2021-11-05', 'Full-time', 24000.00, 'rosario.gonzales@municipality.gov.ph', '034-123-0015', 'General Services Office', FALSE);

-- Now add the foreign key constraint for users table
ALTER TABLE users ADD FOREIGN KEY (employee_id) REFERENCES employee_profiles(employee_id) ON DELETE SET NULL;

-- Insert sample data for users (Admin, HR, and all Employee users)
INSERT INTO users (username, password, email, role, employee_id) VALUES
-- Admin and HR users
('admin', 'admin123', 'admin@municipality.gov.ph', 'admin', NULL),
('hr_manager', 'hr123', 'hr@municipality.gov.ph', 'hr', NULL),

-- Employee users
('maria.santos', 'emp123', 'maria.santos@municipality.gov.ph', 'employee', 1),
('roberto.cruz', 'emp123', 'roberto.cruz@municipality.gov.ph', 'employee', 2),
('jennifer.reyes', 'emp123', 'jennifer.reyes@municipality.gov.ph', 'employee', 3),
('antonio.garcia', 'emp123', 'antonio.garcia@municipality.gov.ph', 'employee', 4),
('lisa.mendoza', 'emp123', 'lisa.mendoza@municipality.gov.ph', 'employee', 5),
('michael.torres', 'emp123', 'michael.torres@municipality.gov.ph', 'employee', 6),
('carmen.delacruz', 'emp123', 'carmen.delacruz@municipality.gov.ph', 'employee', 7),
('ricardo.villanueva', 'emp123', 'ricardo.villanueva@municipality.gov.ph', 'employee', 8),
('sandra.pascual', 'emp123', 'sandra.pascual@municipality.gov.ph', 'employee', 9),
('jose.ramos', 'emp123', 'jose.ramos@municipality.gov.ph', 'employee', 10),
('ana.morales', 'emp123', 'ana.morales@municipality.gov.ph', 'employee', 11),
('pablo.fernandez', 'emp123', 'pablo.fernandez@municipality.gov.ph', 'employee', 12),
('grace.lopez', 'emp123', 'grace.lopez@municipality.gov.ph', 'employee', 13),
('eduardo.hernandez', 'emp123', 'eduardo.hernandez@municipality.gov.ph', 'employee', 14),
('rosario.gonzales', 'emp123', 'rosario.gonzales@municipality.gov.ph', 'employee', 15);

-- Insert sample roles
INSERT INTO `user_roles` (`role_name`, `description`) VALUES
('admin', 'Administrator role with full system access.'),
('hr', 'Human Resources role with access to employee and payroll management.'),
('employee', 'Standard employee role with limited access to personal information and timesheets.');



-- ===============================
-- SAMPLE EMPLOYMENT HISTORY DATA
-- ===============================

INSERT INTO employment_history 
(employee_id, job_title, department_id, employment_type, start_date, end_date, employment_status, 
 reporting_manager_id, location, base_salary, allowances, bonuses, salary_adjustments, 
 reason_for_change, promotions_transfers, duties_responsibilities, performance_evaluations, 
 training_certifications, contract_details, remarks) 
VALUES
-- Current Positions
(1, 'Municipal Treasurer', 3, 'Full-time', '2019-07-01', NULL, 'Active', NULL, 'City Hall - 1st Floor',
 65000.00, 5000.00, 0.00, 0.00, 'Appointed as Municipal Treasurer', 
 'Promoted from Administrative Aide', 
 'Oversees treasury operations, municipal revenue collection, and financial management.', 
 'Consistently rated "Excellent" in financial audits', 
 'CPA Certification, Treasury Management Training', 
 'Appointed by Mayor, renewable 6-year term', 'Key finance official'),

(2, 'Municipal Engineer', 7, 'Full-time', '2018-06-15', NULL, 'Active', NULL, 'Engineering Building',
 75000.00, 6000.00, 0.00, 0.00, 'Appointed as Municipal Engineer',
 'Promoted from CAD Operator', 
 'Supervises infrastructure projects, designs municipal roads and buildings.', 
 'Rated "Very Satisfactory" in infrastructure project completion', 
 'PRC Civil Engineer License, Project Management Certification', 
 'Appointed by Mayor, renewable 6-year term', 'Head of engineering department'),

(3, 'Nurse', 9, 'Full-time', '2020-01-20', NULL, 'Active', 10, 'Municipal Health Office',
 42000.00, 3000.00, 0.00, 0.00, 'Hired as Nurse',
 NULL, 
 'Provides nursing care, assists doctors, administers vaccinations.', 
 'Highly commended during pandemic response', 
 'PRC Nursing License, Basic Life Support Training', 
 'Contract renewable every 3 years', 'Dedicated health staff'),

(4, 'CAD Operator', 7, 'Full-time', '2019-03-10', NULL, 'Active', 2, 'Municipal Engineer''s Office',
 38000.00, 2000.00, 0.00, 0.00, 'Hired as CAD Operator',
 NULL, 
 'Prepares AutoCAD drawings and engineering plans.', 
 'Satisfactory performance in multiple LGU projects', 
 'AutoCAD Certification', 
 'Fixed-term renewable contract', 'Key engineering support'),

(5, 'Social Worker', 10, 'Full-time', '2021-09-05', NULL, 'Active', NULL, 'Municipal Social Welfare & Development Office',
 45000.00, 3000.00, 0.00, 0.00, 'Hired as Social Worker',
 'Promoted from Administrative Aide', 
 'Handles casework, provides assistance to indigent families.', 
 'Rated "Very Good" in community outreach', 
 'Social Work License, Community Development Training', 
 'Regular plantilla position', 'Handles social services cases'),

(6, 'Accounting Staff', 5, 'Full-time', '2020-11-12', NULL, 'Active', NULL, 'Municipal Accountant''s Office',
 28000.00, 1500.00, 0.00, 0.00, 'Hired as Accounting Staff',
 NULL, 
 'Processes vouchers, prepares reports, assists in bookkeeping.', 
 'Satisfactory audit reviews', 
 'Bookkeeping Certification', 
 'Regular plantilla position', 'Junior accounting role'),

(7, 'Clerk', 8, 'Full-time', '2022-02-28', NULL, 'Active', NULL, 'Municipal Civil Registrar''s Office',
 30000.00, 1000.00, 0.00, 0.00, 'Hired as Clerk',
 NULL, 
 'Maintains registry records, assists clients with civil documents.', 
 'Rated "Good" by supervisor', 
 'Civil Registration Training', 
 'Contract renewable every 2 years', 'Support staff'),

(8, 'Maintenance Worker', 15, 'Full-time', '2021-05-18', NULL, 'Active', NULL, 'General Services Office',
 22000.00, 1000.00, 0.00, 0.00, 'Hired as Maintenance Worker',
 NULL, 
 'Performs facility maintenance and minor repairs.', 
 'Satisfactory in safety inspections', 
 'Electrical Safety Training', 
 'Casual employment converted to regular', 'Assigned to city hall facilities'),

(9, 'Cashier', 3, 'Full-time', '2020-09-10', NULL, 'Active', 1, 'Municipal Treasurer''s Office',
 32000.00, 2000.00, 0.00, 0.00, 'Hired as Cashier',
 'Promoted from Clerk', 
 'Handles cash collection, prepares daily receipts.', 
 'Commended for accurate handling of cash', 
 'Financial Management Training', 
 'Regular plantilla position', 'Treasury office staff'),

(10, 'Collection Officer', 3, 'Full-time', '2019-12-01', NULL, 'Active', 1, 'Municipal Treasurer''s Office',
 35000.00, 2000.00, 0.00, 0.00, 'Hired as Collection Officer',
 'Promoted from Clerk', 
 'Collects taxes and fees, manages accounts receivables.', 
 'Rated "Very Good" in collection efficiency', 
 'Revenue Collection Procedures Training', 
 'Regular plantilla position', 'Handles revenue collection'),

-- Previous Positions (Career Progression)
(1, 'Administrative Aide', 13, 'Full-time', '2017-03-01', '2019-06-30', 'Resigned', NULL, 'City Hall - 2nd Floor',
 25000.00, 1000.00, 0.00, 0.00, 'Started as Administrative Aide',
 'Later promoted to Treasurer', 
 'Clerical and administrative support tasks.', 
 'Rated "Good"', 
 NULL, 
 'Fixed-term appointment', 'Entry-level HR support'),

(2, 'CAD Operator', 7, 'Full-time', '2015-08-01', '2018-06-14', 'Transferred', NULL, 'Engineering Building',
 32000.00, 1500.00, 0.00, 0.00, 'Started as CAD Operator',
 'Later promoted to Municipal Engineer', 
 'Drafting technical drawings.', 
 'Rated "Good"', 
 'AutoCAD Certification', 
 'Contract ended due to promotion', 'Junior engineering support'),

(5, 'Administrative Aide', 13, 'Full-time', '2019-01-15', '2021-09-04', 'Transferred', NULL, 'City Hall - 2nd Floor',
 25000.00, 1000.00, 0.00, 0.00, 'Started as Administrative Aide',
 'Later promoted to Social Worker', 
 'Handled clerical support for social welfare programs.', 
 'Rated "Good"', 
 NULL, 
 'Casual contract converted to plantilla', 'Support role before promotion'),

(9, 'Clerk', 8, 'Full-time', '2018-05-01', '2020-09-09', 'Transferred', NULL, 'Municipal Civil Registrar''s Office',
 22000.00, 500.00, 0.00, 0.00, 'Started as Clerk',
 'Later promoted to Cashier', 
 'Maintained registry documents, clerical tasks.', 
 'Rated "Good"', 
 NULL, 
 'Contract ended due to transfer', 'Civil registrar support'),

(10, 'Clerk', 8, 'Full-time', '2017-10-01', '2019-11-30', 'Transferred', NULL, 'Municipal Civil Registrar''s Office',
 20000.00, 500.00, 0.00, 0.00, 'Started as Clerk',
 'Later promoted to Collection Officer', 
 'Clerical tasks, processing records.', 
 'Rated "Satisfactory"', 
 NULL, 
 'Contract ended due to promotion', 'Civil registrar support role');

-- Insert sample data for document_management
INSERT INTO document_management (employee_id, document_type, document_name, file_path, expiry_date, document_status, notes) VALUES
-- Appointment papers and contracts
(1, 'Appointment', 'Appointment Order - Municipal Treasurer', '/documents/appointments/maria_santos_appointment.pdf', NULL, 'Active', 'Appointed by Mayor per Civil Service guidelines'),
(1, 'Contract', 'Employment Contract - Municipal Treasurer', '/documents/contracts/maria_santos_contract.pdf', '2025-07-01', 'Active', 'Department head contract'),
(1, 'Resume', 'Resume - Maria Santos', '/documents/resumes/maria_santos_resume.pdf', NULL, 'Active', 'CPA with municipal finance experience'),
(2, 'Appointment', 'Appointment Order - Municipal Engineer', '/documents/appointments/roberto_cruz_appointment.pdf', NULL, 'Active', 'Licensed Civil Engineer appointment'),
(2, 'Certificate', 'Professional Engineer License', '/documents/licenses/roberto_cruz_pe_license.pdf', '2025-12-31', 'Active', 'Updated PRC license'),
(2, 'Contract', 'Employment Contract - Municipal Engineer', '/documents/contracts/roberto_cruz_contract.pdf', '2024-06-15', 'Active', 'Engineering department head'),
(3, 'Contract', 'Employment Contract - Nurse', '/documents/contracts/jennifer_reyes_contract.pdf', '2025-01-20', 'Active', 'Municipal health office nurse'),
(3, 'Certificate', 'Nursing License', '/documents/licenses/jennifer_reyes_rn_license.pdf', '2025-08-31', 'Active', 'Updated PRC nursing license'),
(3, 'Certificate', 'Basic Life Support Training', '/documents/certificates/jennifer_reyes_bls_cert.pdf', '2024-12-31', 'Active', 'Required medical certification'),
(4, 'Contract', 'Employment Contract - CAD Operator', '/documents/contracts/antonio_garcia_contract.pdf', '2024-03-10', 'Active', 'Engineering support staff'),
(4, 'Certificate', 'AutoCAD Certification', '/documents/certificates/antonio_garcia_autocad_cert.pdf', '2025-06-30', 'Active', 'Professional CAD certification'),
(5, 'Contract', 'Employment Contract - Social Worker', '/documents/contracts/lisa_mendoza_contract.pdf', '2024-09-05', 'Active', 'MSWDO social worker'),
(5, 'Certificate', 'Social Work License', '/documents/licenses/lisa_mendoza_sw_license.pdf', '2025-10-31', 'Active', 'Updated PRC social work license'),
(6, 'Contract', 'Employment Contract - Accounting Staff', '/documents/contracts/michael_torres_contract.pdf', '2025-11-12', 'Active', 'Municipal accountant office staff'),
(6, 'Certificate', 'Bookkeeping Certification', '/documents/certificates/michael_torres_bookkeeping_cert.pdf', '2024-12-31', 'Active', 'Professional bookkeeping certification'),
(7, 'Contract', 'Employment Contract - Clerk', '/documents/contracts/carmen_delacruz_contract.pdf', '2025-02-28', 'Active', 'Civil registrar office clerk'),
(7, 'Training', 'Civil Registration Training', '/documents/training/carmen_delacruz_civil_reg_training.pdf', NULL, 'Active', 'Specialized civil registration procedures'),
(8, 'Contract', 'Employment Contract - Maintenance Worker', '/documents/contracts/ricardo_villanueva_contract.pdf', '2024-05-18', 'Active', 'General services maintenance'),
(8, 'Certificate', 'Electrical Safety Training', '/documents/certificates/ricardo_villanueva_electrical_safety.pdf', '2024-12-31', 'Active', 'Safety certification for maintenance work'),
(9, 'Contract', 'Employment Contract - Cashier', '/documents/contracts/sandra_pascual_contract.pdf', '2025-09-10', 'Active', 'Treasury office cashier'),
(9, 'Training', 'Financial Management Training', '/documents/training/sandra_pascual_finance_training.pdf', NULL, 'Active', 'Municipal financial procedures training'),
(10, 'Contract', 'Employment Contract - Collection Officer', '/documents/contracts/jose_ramos_contract.pdf', '2024-12-01', 'Active', 'Revenue collection specialist'),
(10, 'Training', 'Revenue Collection Procedures', '/documents/training/jose_ramos_collection_training.pdf', NULL, 'Active', 'Specialized revenue collection training'),
(11, 'Contract', 'Employment Contract - Administrative Aide', '/documents/contracts/ana_morales_contract.pdf', '2025-04-15', 'Active', 'HR office administrative support'),
(12, 'Contract', 'Employment Contract - Agricultural Technician', '/documents/contracts/pablo_fernandez_contract.pdf', '2024-08-20', 'Active', 'Agriculture office technical staff'),
(12, 'Certificate', 'Agricultural Extension Training', '/documents/certificates/pablo_fernandez_agri_ext_cert.pdf', '2025-07-31', 'Active', 'Agricultural extension certification'),
(13, 'Contract', 'Employment Contract - Midwife', '/documents/contracts/grace_lopez_contract.pdf', '2025-06-30', 'Active', 'Municipal health office midwife'),
(13, 'Certificate', 'Midwifery License', '/documents/licenses/grace_lopez_midwife_license.pdf', '2025-09-30', 'Active', 'Updated PRC midwifery license'),
(14, 'Contract', 'Employment Contract - Driver', '/documents/contracts/eduardo_hernandez_contract.pdf', '2025-01-10', 'Active', 'Municipal vehicle operator'),
(14, 'Certificate', 'Professional Driver License', '/documents/licenses/eduardo_hernandez_driver_license.pdf', '2025-12-31', 'Active', 'Professional driver''s license'),
(15, 'Contract', 'Employment Contract - Security Personnel', '/documents/contracts/rosario_gonzales_contract.pdf', '2024-11-05', 'Active', 'Municipal facility security'),
(15, 'Certificate', 'Security Guard License', '/documents/licenses/rosario_gonzales_security_license.pdf', '2025-08-31', 'Active', 'SOSIA security guard license');


-- ===============================
-- EXIT MANAGEMENT SAMPLE DATA
-- ===============================

-- Sample data for exits table (3 employees exiting for different reasons)
INSERT INTO exits (employee_id, exit_type, exit_reason, notice_date, exit_date, status) VALUES
-- Employee 6 (Michael Torres) - Resignation for better opportunity
(6, 'Resignation', 'Accepted position as Senior Accountant at private firm with better compensation package', '2024-01-15', '2024-02-29', 'Completed'),

-- Employee 14 (Eduardo Hernandez) - Retirement after reaching mandatory age
(14, 'Retirement', 'Reached mandatory retirement age of 65 years old', '2023-11-01', '2024-01-31', 'Completed'),

-- Employee 15 (Rosario Gonzales) - Contract ended
(15, 'End of Contract', 'Fixed-term contract expired and not renewed due to budget constraints', '2024-09-01', '2024-10-31', 'Processing');

-- Sample data for exit_checklist table
INSERT INTO exit_checklist (exit_id, item_name, description, responsible_department, status, completed_date, notes) VALUES
-- Checklist for Michael Torres (Exit ID 1)
(1, 'Return Municipal Property', 'Return all municipal assets, equipment, and office supplies', 'General Services Office', 'Completed', '2024-02-28', 'All items accounted for and returned'),
(1, 'Clear Financial Obligations', 'Settle any outstanding loans or advances', 'Municipal Treasurer\'s Office', 'Completed', '2024-02-25', 'No outstanding obligations'),
(1, 'Handover Financial Records', 'Transfer custody of financial documents and ledgers', 'Municipal Accountant\'s Office', 'Completed', '2024-02-26', 'Complete handover to replacement staff'),
(1, 'Return ID and Access Cards', 'Submit employee ID and building access cards', 'Municipal Human Resource & Administrative Office', 'Completed', '2024-02-29', 'All credentials returned'),
(1, 'Update Personnel Records', 'Process final employment documentation', 'Municipal Human Resource & Administrative Office', 'Completed', '2024-02-29', 'Records updated in HRIS'),

-- Checklist for Eduardo Hernandez (Exit ID 2)  
(2, 'Process Retirement Benefits', 'File retirement papers with GSIS', 'Municipal Human Resource & Administrative Office', 'Completed', '2024-01-25', 'GSIS retirement filed successfully'),
(2, 'Return Municipal Vehicle', 'Turn over assigned municipal vehicle and documents', 'General Services Office', 'Completed', '2024-01-30', 'Vehicle inspected and transferred'),
(2, 'Clear Outstanding Leave', 'Process accumulated leave credits payout', 'Municipal Human Resource & Administrative Office', 'Completed', '2024-01-28', '45 days leave credits converted to cash'),
(2, 'Medical Clearance', 'Complete exit medical examination', 'Municipal Health Office', 'Completed', '2024-01-20', 'Medical clearance issued'),
(2, 'Knowledge Transfer', 'Brief successor on driving routes and procedures', 'General Services Office', 'Completed', '2024-01-29', 'Complete briefing provided to new driver'),

-- Checklist for Rosario Gonzales (Exit ID 3)
(3, 'Security Equipment Return', 'Return security equipment and uniforms', 'General Services Office', 'Completed', '2024-10-30', 'All security gear returned'),
(3, 'Access Revocation', 'Revoke building and area access permissions', 'General Services Office', 'Pending', NULL, 'Waiting for final security clearance'),
(3, 'Final Security Report', 'Submit final incident and patrol reports', 'General Services Office', 'Completed', '2024-10-29', 'All reports submitted and filed'),
(3, 'Clear Accountabilities', 'Settle any equipment or property accountabilities', 'Municipal Accountant\'s Office', 'Pending', NULL, 'Minor accountability for damaged flashlight'),
(3, 'Process Final Pay', 'Calculate and process final salary and benefits', 'Municipal Human Resource & Administrative Office', 'Pending', NULL, 'Awaiting clearance completion');

-- Sample data for exit_interviews table
INSERT INTO exit_interviews (exit_id, employee_id, interview_date, feedback, improvement_suggestions, reason_for_leaving, would_recommend, status) VALUES
-- Exit interview for Michael Torres
(1, 6, '2024-02-20', 
'Working in the Municipal Accountant\'s Office provided valuable experience in government accounting procedures. The work environment was professional and colleagues were supportive. However, limited career advancement opportunities and compensation not competitive with private sector.',
'Consider salary standardization review, create clear career progression paths for technical staff, provide more professional development opportunities, improve office equipment and technology.',
'Better compensation package offered by private accounting firm, more growth opportunities in private sector, desire to expand expertise in corporate accounting.',
TRUE, 'Completed'),

-- Exit interview for Eduardo Hernandez  
(2, 14, '2024-01-15',
'Proud to have served the municipality for over 30 years as a driver. Witnessed significant improvements in municipal services and infrastructure. Grateful for stable employment and benefits. Work was fulfilling knowing it served the community.',
'Ensure proper vehicle maintenance schedule, provide defensive driving training for new drivers, improve garage facilities, consider upgrading older vehicles for safety.',
'Reached mandatory retirement age, looking forward to spending time with family and pursuing personal interests.',
TRUE, 'Completed'),

-- Exit interview for Rosario Gonzales
(3, 15, '2024-10-15',
'Enjoyed the responsibility of protecting municipal facilities. Night shift work was challenging but manageable. Disappointed that contract was not renewed due to budget constraints. Would have preferred to continue working.',
'Provide better lighting in some building areas, upgrade security communication equipment, offer health insurance coverage for contractual security personnel, consider converting security positions to regular plantilla.',
'Contract not renewed due to municipal budget constraints, no fault of employee performance.',
TRUE, 'Completed');

-- Sample data for knowledge_transfers table
INSERT INTO knowledge_transfers (exit_id, employee_id, handover_details, start_date, completion_date, status, notes) VALUES
-- Knowledge transfer for Michael Torres
(1, 6, 
'Comprehensive handover of accounting procedures including: monthly financial reporting processes, budget monitoring systems, accounts payable/receivable procedures, audit preparation protocols, vendor payment processing, tax computation methods, and filing systems organization. Provided detailed documentation of all recurring tasks and deadlines.',
'2024-02-01', '2024-02-26', 'Completed',
'Successor (new accounting staff) properly oriented on all procedures. Documentation provided covers all essential tasks.'),

-- Knowledge transfer for Eduardo Hernandez
(2, 14,
'Detailed briefing on municipal vehicle operations including: official routes and shortcuts, vehicle maintenance schedules, fuel management procedures, parking protocols, emergency procedures, contact information for mechanics and suppliers, and safety protocols for transporting officials.',
'2024-01-10', '2024-01-29', 'Completed',
'New driver fully oriented on all routes and procedures. Vehicle logbooks and maintenance records properly turned over.'),

-- Knowledge transfer for Rosario Gonzales  
(3, 15,
'Security procedures handover covering: building patrol routes and schedules, alarm system operations, emergency contact protocols, incident reporting procedures, visitor management systems, key control procedures, and coordination with other security agencies.',
'2024-10-01', '2024-10-28', 'Completed',
'Replacement security personnel properly trained on all security protocols and emergency procedures.');

-- Sample data for settlements table
INSERT INTO settlements (exit_id, employee_id, last_working_day, final_salary, severance_pay, unused_leave_payout, deductions, final_settlement_amount, payment_date, payment_method, status, processed_date, notes) VALUES
-- Settlement for Michael Torres
(1, 6, '2024-02-29', 28000.00, 0.00, 15750.00, 2500.00, 41250.00, '2024-03-15', 'Bank Transfer', 'Completed', '2024-03-10',
'Final month salary plus 15 days unused leave credits. Deductions include loan balance and uniform cost.'),

-- Settlement for Eduardo Hernandez
(2, 14, '2024-01-31', 25000.00, 75000.00, 56250.00, 5000.00, 151250.00, '2024-02-28', 'Bank Transfer', 'Completed', '2024-02-15',
'Final salary plus retirement gratuity (3 months) plus 45 days leave conversion. Deductions for vehicle damage repair.'),

-- Settlement for Rosario Gonzales
(3, 15, '2024-10-31', 24000.00, 0.00, 8000.00, 1500.00, 30500.00, NULL, 'Bank Transfer', 'Pending', NULL,
'Final salary plus 10 days unused leave. Deduction for damaged equipment. Awaiting final clearance.');

-- Sample data for exit_documents table
INSERT INTO exit_documents (exit_id, employee_id, document_type, document_name, document_url, notes) VALUES
-- Documents for Michael Torres
(1, 6, 'Clearance', 'Exit Clearance Certificate', '/documents/exits/michael_torres_exit_clearance.pdf', 'Signed clearance from all departments'),
(1, 6, 'Certificate', 'Certificate of Employment', '/documents/exits/michael_torres_employment_cert.pdf', 'Official employment certificate for future reference'),
(1, 6, 'Settlement', 'Final Settlement Computation', '/documents/exits/michael_torres_settlement.pdf', 'Detailed breakdown of final settlement amount'),

-- Documents for Eduardo Hernandez
(2, 14, 'Clearance', 'Retirement Clearance Certificate', '/documents/exits/eduardo_hernandez_retirement_clearance.pdf', 'Complete retirement clearance documentation'),
(2, 14, 'Certificate', 'Certificate of Service', '/documents/exits/eduardo_hernandez_service_cert.pdf', 'Certificate acknowledging 30+ years of faithful service'),
(2, 14, 'Settlement', 'Retirement Settlement Statement', '/documents/exits/eduardo_hernandez_retirement_settlement.pdf', 'Retirement benefits and final pay computation'),
(2, 14, 'Appreciation', 'Letter of Appreciation', '/documents/exits/eduardo_hernandez_appreciation_letter.pdf', 'Official recognition for dedicated service'),

-- Documents for Rosario Gonzales  
(3, 15, 'Certificate', 'Certificate of Employment', '/documents/exits/rosario_gonzales_employment_cert.pdf', 'Employment certificate for job applications'),
(3, 15, 'Settlement', 'Final Settlement Statement', '/documents/exits/rosario_gonzales_settlement.pdf', 'Pending settlement computation'),
(3, 15, 'Clearance', 'Partial Clearance Document', '/documents/exits/rosario_gonzales_partial_clearance.pdf', 'Clearance pending from General Services Office');

-- Sample data for post_exit_surveys table
INSERT INTO post_exit_surveys (employee_id, exit_id, survey_date, survey_response, satisfaction_rating, submitted_date) VALUES
-- Post-exit survey for Michael Torres (3 months after exit)
(6, 1, '2024-05-15', 
'Overall experience working for the municipality was positive. Learned valuable skills in government accounting and financial management. Colleagues were professional and supportive. Management was fair and understanding. Main concern was limited career growth opportunities and salary competitiveness compared to private sector. Would consider returning if compensation and advancement opportunities improve.',
4, '2024-05-15'),

-- Post-exit survey for Eduardo Hernandez (6 months after retirement)
(14, 2, '2024-07-31',
'Very satisfied with my career in municipal service. Proud to have contributed to community development over three decades. Municipal government provided stable employment and good benefits. Retirement process was handled professionally. Enjoying retirement but miss the camaraderie with colleagues. Would definitely recommend municipal employment to others seeking stable, meaningful work.',
5, '2024-07-31');

-- ===============================
-- SAMPLE DATA FOR LEARNING & DEVELOPMENT MODULE
-- ===============================

-- Insert sample training courses
INSERT INTO training_courses (course_name, description, category, delivery_method, duration, max_participants, prerequisites, materials_url, status) VALUES
('Leadership Fundamentals', 'Essential leadership skills for supervisors and managers including communication, decision-making, and team management', 'Leadership', 'Classroom', 16, 20, 'Minimum 1 year work experience, Basic communication skills', 'https://drive.google.com/leadership-fundamentals', 'Active'),
('Digital Skills Training', 'Computer literacy and office software proficiency including Microsoft Office Suite and basic IT skills', 'Technology', 'Online', 8, 50, 'Basic computer knowledge', 'https://drive.google.com/digital-skills', 'Active'),
('Customer Service Excellence', 'Customer interaction, problem-solving, and service delivery best practices for municipal employees', 'Soft Skills', 'Workshop', 12, 25, 'None', 'https://drive.google.com/customer-service', 'Active'),
('Financial Management for Municipal Employees', 'Budget management, financial reporting, and fiscal responsibility training', 'Finance', 'Hybrid', 20, 15, 'Basic math skills, Interest in finance', 'https://drive.google.com/financial-management', 'Active'),
('Public Administration Principles', 'Core principles of public administration, governance, and municipal service delivery', 'Administration', 'Classroom', 24, 30, 'None', 'https://drive.google.com/public-administration', 'Active'),
('Project Management Essentials', 'Project planning, execution, monitoring, and evaluation for municipal projects', 'Management', 'Workshop', 18, 20, 'Basic organizational skills', 'https://drive.google.com/project-management', 'Active'),
('Communication Skills Workshop', 'Effective written and verbal communication, public speaking, and presentation skills', 'Communication', 'Workshop', 10, 25, 'None', 'https://drive.google.com/communication-skills', 'Active'),
('Safety and Emergency Response', 'Workplace safety, emergency procedures, and first aid training for municipal employees', 'Safety', 'Classroom', 14, 40, 'None', 'https://drive.google.com/safety-training', 'Active'),
('Data Analysis and Reporting', 'Data collection, analysis, and report generation for municipal operations', 'Analytics', 'Online', 12, 20, 'Basic Excel skills', 'https://drive.google.com/data-analysis', 'Active'),
('Environmental Management', 'Environmental protection, waste management, and sustainability practices', 'Environment', 'Hybrid', 16, 25, 'Interest in environmental issues', 'https://drive.google.com/environmental-management', 'Active');

-- Insert sample trainers
INSERT INTO trainers (first_name, last_name, email, phone, specialization, bio, is_internal) VALUES
('Dr. Maria', 'Santos', 'maria.santos@municipality.gov.ph', '0917-123-4567', 'Leadership & Management', 'Dr. Santos has over 15 years of experience in public administration and leadership development. She holds a PhD in Public Administration and has trained over 500 municipal employees.', TRUE),
('Engr. Roberto', 'Cruz', 'roberto.cruz@municipality.gov.ph', '0917-234-5678', 'Project Management & Engineering', 'Engr. Cruz is a licensed civil engineer with expertise in municipal infrastructure projects. He specializes in project planning and execution.', TRUE),
('Prof. Jennifer', 'Reyes', 'jennifer.reyes@municipality.gov.ph', '0917-345-6789', 'Communication & Public Relations', 'Prof. Reyes is a communication expert with 12 years of experience in public relations and media management for government agencies.', TRUE),
('Atty. Antonio', 'Garcia', 'antonio.garcia@municipality.gov.ph', '0917-456-7890', 'Legal & Compliance', 'Atty. Garcia specializes in local government law, compliance, and regulatory requirements for municipal operations.', TRUE),
('Ms. Lisa', 'Mendoza', 'lisa.mendoza@municipality.gov.ph', '0917-567-8901', 'Financial Management', 'Ms. Mendoza is a certified public accountant with extensive experience in municipal finance and budget management.', TRUE),
('Mr. Michael', 'Torres', 'michael.torres@external.com', '0917-678-9012', 'Digital Transformation', 'Mr. Torres is an external consultant specializing in digital transformation and technology adoption for government agencies.', FALSE),
('Dr. Carmen', 'Dela Cruz', 'carmen.delacruz@external.com', '0917-789-0123', 'Environmental Science', 'Dr. Dela Cruz is an environmental scientist and consultant with expertise in sustainable development and environmental management.', FALSE),
('Ms. Sandra', 'Pascual', 'sandra.pascual@municipality.gov.ph', '0917-890-1234', 'Human Resources', 'Ms. Pascual is an HR specialist with 10 years of experience in employee development and organizational psychology.', TRUE),
('Engr. Jose', 'Ramos', 'jose.ramos@municipality.gov.ph', '0917-901-2345', 'Information Technology', 'Engr. Ramos is an IT specialist focusing on digital skills training and technology implementation in government.', TRUE),
('Ms. Ana', 'Morales', 'ana.morales@municipality.gov.ph', '0917-012-3456', 'Customer Service', 'Ms. Morales is a customer service expert with experience in improving service delivery in municipal offices.', TRUE);

-- Insert sample skills for skill matrix
INSERT INTO skill_matrix (skill_name, description, category) VALUES
('Leadership', 'Ability to lead teams, make decisions, and inspire others', 'Management'),
('Communication', 'Effective written and verbal communication skills', 'Soft Skills'),
('Project Management', 'Planning, organizing, and managing projects effectively', 'Management'),
('Financial Analysis', 'Understanding and analyzing financial data', 'Finance'),
('Public Speaking', 'Confident presentation and public speaking abilities', 'Communication'),
('Problem Solving', 'Analytical thinking and creative problem-solving', 'Analytics'),
('Team Collaboration', 'Working effectively in teams and groups', 'Soft Skills'),
('Time Management', 'Efficient planning and time allocation', 'Management'),
('Customer Service', 'Providing excellent service to constituents', 'Service'),
('Data Analysis', 'Collecting, analyzing, and interpreting data', 'Analytics'),
('Microsoft Office', 'Proficiency in Word, Excel, PowerPoint, and Outlook', 'Technology'),
('Digital Literacy', 'Basic computer and internet skills', 'Technology'),
('Legal Compliance', 'Understanding of laws and regulations', 'Legal'),
('Environmental Awareness', 'Knowledge of environmental issues and sustainability', 'Environment'),
('Emergency Response', 'Safety procedures and emergency protocols', 'Safety'),
('Budget Management', 'Planning and managing budgets effectively', 'Finance'),
('Report Writing', 'Creating clear and comprehensive reports', 'Communication'),
('Conflict Resolution', 'Resolving disputes and conflicts professionally', 'Soft Skills'),
('Strategic Planning', 'Long-term planning and goal setting', 'Management'),
('Quality Assurance', 'Maintaining high standards and quality control', 'Management');

-- Insert sample career paths
INSERT INTO career_paths (path_name, description, department_id) VALUES
('Administrative Leadership Path', 'Career progression from administrative staff to department head', 13),
('Financial Management Path', 'Progression in financial roles from clerk to treasurer', 3),
('Engineering Career Path', 'Technical progression from assistant engineer to municipal engineer', 7),
('Health Services Path', 'Career development in health and medical services', 9),
('Social Services Path', 'Progression in social welfare and community development', 10),
('Planning and Development Path', 'Career growth in municipal planning and development', 6),
('Information Technology Path', 'Progression in IT and digital transformation roles', 13),
('Legal and Compliance Path', 'Career development in legal and regulatory compliance', 13);

-- Insert sample career path stages
INSERT INTO career_path_stages (path_id, job_role_id, stage_order, minimum_time_in_role, required_skills, required_experience) VALUES
(1, 1, 1, 12, 'Communication, Time Management', 'Entry-level administrative experience'),
(1, 1, 2, 24, 'Leadership, Project Management', '2+ years administrative experience'),
(1, 1, 3, 36, 'Strategic Planning, Team Management', '5+ years supervisory experience'),
(2, 4, 1, 12, 'Basic Accounting, Microsoft Excel', 'Entry-level financial experience'),
(2, 4, 2, 24, 'Financial Analysis, Budget Management', '2+ years financial experience'),
(2, 4, 3, 36, 'Financial Planning, Compliance', '5+ years financial management experience'),
(3, 8, 1, 12, 'Engineering Principles, AutoCAD', 'Entry-level engineering experience'),
(3, 8, 2, 24, 'Project Management, Technical Design', '2+ years engineering experience'),
(3, 8, 3, 36, 'Infrastructure Planning, Team Leadership', '5+ years engineering experience');

-- Insert sample training sessions
INSERT INTO training_sessions (course_id, trainer_id, session_name, start_date, end_date, location, capacity, cost_per_participant, status) VALUES
(1, 1, 'Leadership Fundamentals - Batch 1', '2024-03-15 09:00:00', '2024-03-16 17:00:00', 'City Hall Conference Room A', 20, 5000.00, 'Scheduled'),
(2, 9, 'Digital Skills Training - Online', '2024-03-20 10:00:00', '2024-03-22 16:00:00', 'Online Platform', 50, 2000.00, 'Scheduled'),
(3, 10, 'Customer Service Excellence - Workshop', '2024-03-25 08:00:00', '2024-03-26 17:00:00', 'Social Services Building', 25, 3000.00, 'Scheduled'),
(4, 5, 'Financial Management - Hybrid', '2024-04-01 09:00:00', '2024-04-05 17:00:00', 'City Hall + Online', 15, 8000.00, 'Scheduled'),
(5, 1, 'Public Administration - Classroom', '2024-04-10 09:00:00', '2024-04-12 17:00:00', 'City Hall Conference Room B', 30, 6000.00, 'Scheduled'),
(6, 2, 'Project Management - Workshop', '2024-04-15 08:00:00', '2024-04-17 17:00:00', 'Engineering Building', 20, 7000.00, 'Scheduled'),
(7, 3, 'Communication Skills - Workshop', '2024-04-22 09:00:00', '2024-04-23 17:00:00', 'City Hall Conference Room A', 25, 4000.00, 'Scheduled'),
(8, 1, 'Safety Training - Classroom', '2024-04-29 08:00:00', '2024-04-30 17:00:00', 'Emergency Operations Center', 40, 2500.00, 'Scheduled'),
(9, 9, 'Data Analysis - Online', '2024-05-06 10:00:00', '2024-05-08 16:00:00', 'Online Platform', 20, 3500.00, 'Scheduled'),
(10, 7, 'Environmental Management - Hybrid', '2024-05-13 09:00:00', '2024-05-15 17:00:00', 'City Hall + Field Visit', 25, 5000.00, 'Scheduled');

-- Insert sample learning resources
INSERT INTO learning_resources (resource_name, resource_type, description, resource_url, author, publication_date, duration, tags) VALUES
('Municipal Governance Handbook', 'Book', 'Comprehensive guide to municipal governance and administration', 'https://drive.google.com/municipal-handbook', 'Department of Interior and Local Government', '2023-01-15', '300 pages', 'governance, administration, municipal'),
('Excel for Municipal Employees', 'Online Course', 'Advanced Excel skills for data management and reporting', 'https://drive.google.com/excel-course', 'Microsoft Training', '2023-03-20', '8 hours', 'excel, data, reporting, technology'),
('Public Speaking Mastery', 'Video', 'Video series on effective public speaking and presentation', 'https://drive.google.com/public-speaking', 'Communication Institute', '2023-02-10', '6 hours', 'communication, public speaking, presentation'),
('Local Government Finance Guide', 'Article', 'Guide to financial management in local government units', 'https://drive.google.com/finance-guide', 'Commission on Audit', '2023-04-05', '45 minutes', 'finance, budget, compliance'),
('Project Management Fundamentals', 'Webinar', 'Webinar on project management principles and practices', 'https://drive.google.com/project-webinar', 'Project Management Institute', '2023-05-12', '2 hours', 'project management, planning, execution'),
('Customer Service Best Practices', 'Podcast', 'Podcast series on customer service excellence', 'https://drive.google.com/customer-podcast', 'Service Excellence Institute', '2023-06-18', '3 hours', 'customer service, communication, satisfaction'),
('Environmental Protection Guidelines', 'Book', 'Guidelines for environmental protection and sustainability', 'https://drive.google.com/environmental-guide', 'Department of Environment and Natural Resources', '2023-07-25', '200 pages', 'environment, sustainability, protection'),
('Digital Transformation in Government', 'Online Course', 'Course on digital transformation for government agencies', 'https://drive.google.com/digital-transformation', 'Digital Government Institute', '2023-08-30', '12 hours', 'digital, transformation, technology'),
('Leadership in Public Service', 'Video', 'Video series on leadership in public service', 'https://drive.google.com/leadership-video', 'Public Service Leadership Institute', '2023-09-15', '8 hours', 'leadership, public service, management'),
('Safety and Emergency Procedures', 'Article', 'Comprehensive guide to workplace safety and emergency response', 'https://drive.google.com/safety-guide', 'Department of Labor and Employment', '2023-10-20', '60 minutes', 'safety, emergency, procedures');

-- Insert sample training enrollments
INSERT INTO training_enrollments (session_id, employee_id, enrollment_date, status, completion_date, score, feedback, certificate_url) VALUES
(1, 1, '2024-02-15 10:30:00', 'Enrolled', NULL, NULL, NULL, NULL),
(1, 2, '2024-02-15 11:15:00', 'Enrolled', NULL, NULL, NULL, NULL),
(1, 3, '2024-02-15 14:20:00', 'Enrolled', NULL, NULL, NULL, NULL),
(2, 4, '2024-02-16 09:45:00', 'Enrolled', NULL, NULL, NULL, NULL),
(2, 5, '2024-02-16 10:30:00', 'Enrolled', NULL, NULL, NULL, NULL),
(3, 6, '2024-02-17 08:15:00', 'Enrolled', NULL, NULL, NULL, NULL),
(3, 7, '2024-02-17 09:00:00', 'Enrolled', NULL, NULL, NULL, NULL),
(4, 8, '2024-02-18 13:30:00', 'Enrolled', NULL, NULL, NULL, NULL),
(4, 9, '2024-02-18 14:15:00', 'Enrolled', NULL, NULL, NULL, NULL),
(5, 10, '2024-02-19 11:00:00', 'Enrolled', NULL, NULL, NULL, NULL);

-- Insert sample employee skills
INSERT INTO employee_skills (employee_id, skill_id, proficiency_level, assessed_date, certification_url, expiry_date, notes) VALUES
(1, 1, 'Intermediate', '2024-01-15', 'https://drive.google.com/cert1.pdf', '2025-01-15', 'Leadership training completed'),
(1, 2, 'Advanced', '2024-01-20', 'https://drive.google.com/cert2.pdf', '2025-01-20', 'Excellent communication skills'),
(2, 3, 'Beginner', '2024-01-25', NULL, NULL, 'Basic project management knowledge'),
(2, 4, 'Intermediate', '2024-02-01', 'https://drive.google.com/cert3.pdf', '2025-02-01', 'Financial analysis training'),
(3, 5, 'Advanced', '2024-02-05', 'https://drive.google.com/cert4.pdf', '2025-02-05', 'Experienced public speaker'),
(3, 6, 'Intermediate', '2024-02-10', NULL, NULL, 'Good problem-solving abilities'),
(4, 7, 'Advanced', '2024-02-15', 'https://drive.google.com/cert5.pdf', '2025-02-15', 'Team collaboration expert'),
(4, 8, 'Intermediate', '2024-02-20', NULL, NULL, 'Effective time management'),
(5, 9, 'Advanced', '2024-02-25', 'https://drive.google.com/cert6.pdf', '2025-02-25', 'Customer service specialist'),
(5, 10, 'Beginner', '2024-03-01', NULL, NULL, 'Basic data analysis skills');

-- Insert sample employee resources
INSERT INTO employee_resources (employee_id, resource_id, assigned_date, due_date, completed_date, status, rating, feedback) VALUES
(1, 1, '2024-01-10', '2024-02-10', '2024-01-25', 'Completed', 5, 'Excellent resource for understanding municipal governance'),
(1, 2, '2024-01-15', '2024-02-15', '2024-02-05', 'Completed', 4, 'Very helpful for improving Excel skills'),
(2, 3, '2024-01-20', '2024-02-20', '2024-02-10', 'Completed', 5, 'Great video series on public speaking'),
(2, 4, '2024-01-25', '2024-02-25', NULL, 'In Progress', NULL, NULL),
(3, 5, '2024-02-01', '2024-03-01', '2024-02-20', 'Completed', 4, 'Informative webinar on project management'),
(3, 6, '2024-02-05', '2024-03-05', NULL, 'Assigned', NULL, NULL),
(4, 7, '2024-02-10', '2024-03-10', '2024-02-28', 'Completed', 5, 'Comprehensive guide on environmental protection'),
(4, 8, '2024-02-15', '2024-03-15', NULL, 'In Progress', NULL, NULL),
(5, 9, '2024-02-20', '2024-03-20', '2024-03-05', 'Completed', 4, 'Good insights on leadership in public service'),
(5, 10, '2024-02-25', '2024-03-25', NULL, 'Assigned', NULL, NULL);

-- Insert sample training needs assessments
INSERT INTO training_needs_assessment (employee_id, assessment_date, skills_gap, recommended_trainings, priority, status) VALUES
(1, '2024-01-15', 'Advanced project management skills needed for department leadership', 'Project Management Essentials, Strategic Planning Workshop', 'High', 'Identified'),
(2, '2024-01-20', 'Digital skills improvement required for modern office operations', 'Digital Skills Training, Data Analysis and Reporting', 'Medium', 'In Progress'),
(3, '2024-01-25', 'Leadership development needed for supervisory role', 'Leadership Fundamentals, Communication Skills Workshop', 'High', 'Completed'),
(4, '2024-02-01', 'Financial management skills for budget responsibilities', 'Financial Management for Municipal Employees', 'Medium', 'Identified'),
(5, '2024-02-05', 'Customer service skills for public interaction', 'Customer Service Excellence, Communication Skills Workshop', 'Low', 'In Progress'),
(6, '2024-02-10', 'Safety training required for workplace compliance', 'Safety and Emergency Response', 'High', 'Identified'),
(7, '2024-02-15', 'Environmental awareness for municipal projects', 'Environmental Management', 'Medium', 'Completed'),
(8, '2024-02-20', 'Public administration principles for career advancement', 'Public Administration Principles', 'High', 'In Progress'),
(9, '2024-02-25', 'Technology skills for digital transformation', 'Digital Skills Training, Data Analysis and Reporting', 'Medium', 'Identified'),
(10, '2024-03-01', 'Communication skills for public relations role', 'Communication Skills Workshop, Public Speaking', 'High', 'Completed');

-- Insert sample employee career paths
INSERT INTO employee_career_paths (employee_id, path_id, current_stage_id, start_date, target_completion_date, status) VALUES
(1, 1, 2, '2023-01-15', '2025-01-15', 'Active'),
(2, 2, 1, '2023-06-20', '2025-06-20', 'Active'),
(3, 1, 3, '2022-03-10', '2024-03-10', 'Active'),
(4, 3, 1, '2023-09-05', '2025-09-05', 'Active'),
(5, 4, 2, '2022-12-15', '2024-12-15', 'Active'),
(6, 5, 1, '2023-11-20', '2025-11-20', 'Active'),
(7, 6, 2, '2022-08-30', '2024-08-30', 'Active'),
(8, 7, 1, '2023-04-12', '2025-04-12', 'Active'),
(9, 2, 3, '2021-07-25', '2023-07-25', 'Completed'),
(10, 1, 1, '2023-12-01', '2025-12-01', 'Active');


-- Insert sample certifications data
INSERT INTO certifications (
    employee_id, skill_id, certification_name, issuing_organization, certification_number, 
    category, proficiency_level, assessment_score, issue_date, expiry_date, assessed_date,
    certification_url, status, verification_status, cost, training_hours, cpe_credits,
    renewal_required, renewal_period_months, next_renewal_date, prerequisites, 
    description, notes, tags
) VALUES
-- Employee 1 - Administrative Staff with Leadership Focus
(1, 1, 'Certified Public Manager (CPM)', 'Philippine Association of Government Managers', 'CPM-2024-001', 'Leadership', 'Advanced', 92.5, '2024-01-15', '2027-01-15', '2024-01-15', 'https://drive.google.com/cpm-cert-001.pdf', 'Active', 'Verified', 15000.00, 40, 40.0, TRUE, 36, '2027-01-15', '3 years management experience', 'Comprehensive leadership certification for government managers', 'Completed with distinction', 'leadership,management,government'),

(1, 2, 'Professional Communication Certificate', 'Communication Institute of the Philippines', 'PCC-2024-002', 'Communication', 'Advanced', 88.0, '2024-01-20', '2025-01-20', '2024-01-20', 'https://drive.google.com/comm-cert-002.pdf', 'Active', 'Verified', 8000.00, 16, 16.0, TRUE, 12, '2025-01-20', 'Basic communication skills', 'Advanced communication and presentation skills certification', 'Excellent performance in public speaking module', 'communication,presentation,public-speaking'),

-- Employee 2 - Finance Staff
(2, 4, 'Certified Government Financial Manager', 'Government Finance Officers Association', 'CGFM-2024-003', 'Finance', 'Intermediate', 85.0, '2024-02-01', '2026-02-01', '2024-02-01', 'https://drive.google.com/cgfm-cert-003.pdf', 'Active', 'Verified', 12000.00, 32, 32.0, TRUE, 24, '2026-02-01', 'Bachelor\'s degree in Finance or Accounting', 'Specialized certification in government financial management', 'Strong understanding of municipal finance principles', 'finance,government,budget,accounting'),

(2, 11, 'Microsoft Office Specialist - Excel Expert', 'Microsoft Corporation', 'MOS-EXL-2024-004', 'Technology', 'Expert', 95.0, '2024-01-25', '2026-01-25', '2024-01-25', 'https://drive.google.com/mos-excel-cert-004.pdf', 'Active', 'Verified', 5000.00, 8, 8.0, TRUE, 24, '2026-01-25', 'Basic Excel knowledge', 'Expert-level Excel proficiency certification', 'Perfect score in advanced formulas and pivot tables', 'excel,microsoft,data-analysis,spreadsheet'),

-- Employee 3 - Senior Staff with Public Speaking
(3, 5, 'Certified Professional Speaker', 'Philippine Society of Professional Speakers', 'CPS-2024-005', 'Communication', 'Expert', 94.0, '2024-02-05', '2027-02-05', '2024-02-05', 'https://drive.google.com/cps-cert-005.pdf', 'Active', 'Verified', 20000.00, 60, 60.0, TRUE, 36, '2027-02-05', '50 hours of public speaking experience', 'Professional speaker certification with advanced presentation skills', 'Demonstrated excellence in training delivery', 'public-speaking,presentation,training,communication'),

(3, 6, 'Creative Problem Solving Certification', 'International Center for Studies in Creativity', 'CPS-CERT-2024-006', 'Analytics', 'Advanced', 89.5, '2024-02-10', '2025-02-10', '2024-02-10', 'https://drive.google.com/problem-solving-cert-006.pdf', 'Active', 'Verified', 10000.00, 24, 24.0, TRUE, 12, '2025-02-10', 'Basic analytical thinking skills', 'Advanced problem-solving methodologies and creative thinking', 'Excellent application of TRIZ methodology', 'problem-solving,creativity,analytics,innovation'),

-- Employee 4 - Team Leader
(4, 7, 'Team Leadership Excellence Certificate', 'Center for Creative Leadership', 'TLE-2024-007', 'Management', 'Advanced', 91.0, '2024-02-15', '2026-02-15', '2024-02-15', 'https://drive.google.com/team-leadership-cert-007.pdf', 'Active', 'Verified', 18000.00, 40, 40.0, TRUE, 24, '2026-02-15', '2 years supervisory experience', 'Comprehensive team leadership and collaboration certification', 'Strong focus on cross-functional team management', 'team-leadership,collaboration,management,supervision'),

(4, 8, 'Time Management Professional', 'Productivity Institute', 'TMP-2024-008', 'Management', 'Advanced', 87.0, '2024-02-20', '2025-02-20', '2024-02-20', 'https://drive.google.com/time-mgmt-cert-008.pdf', 'Active', 'Verified', 6000.00, 16, 16.0, TRUE, 12, '2025-02-20', 'Basic organizational skills', 'Advanced time management and productivity optimization', 'Implemented new scheduling systems in department', 'time-management,productivity,organization,efficiency'),

-- Employee 5 - Customer Service Specialist
(5, 9, 'Customer Service Excellence Professional', 'Customer Service Institute', 'CSE-2024-009', 'Service', 'Expert', 96.0, '2024-02-25', '2026-02-25', '2024-02-25', 'https://drive.google.com/customer-service-cert-009.pdf', 'Active', 'Verified', 12000.00, 32, 32.0, TRUE, 24, '2026-02-25', '1 year customer service experience', 'Expert-level customer service and satisfaction management', 'Achieved highest score in conflict resolution module', 'customer-service,communication,conflict-resolution,satisfaction'),

(5, 10, 'Data Analysis Fundamentals', 'Data Science Academy', 'DAF-2024-010', 'Analytics', 'Beginner', 78.0, '2024-03-01', '2025-03-01', '2024-03-01', 'https://drive.google.com/data-analysis-cert-010.pdf', 'Active', 'Verified', 7000.00, 20, 20.0, TRUE, 12, '2025-03-01', 'Basic math and statistics', 'Foundational data analysis and interpretation skills', 'Good grasp of basic statistical concepts', 'data-analysis,statistics,analytics,reporting'),

-- Employee 6 - Engineering Staff
(6, 3, 'Project Management Professional (PMP)', 'Project Management Institute', 'PMP-2024-011', 'Management', 'Advanced', 92.0, '2024-01-10', '2027-01-10', '2024-01-10', 'https://drive.google.com/pmp-cert-011.pdf', 'Active', 'Verified', 25000.00, 60, 60.0, TRUE, 36, '2027-01-10', 'Bachelor\'s degree and 4,500 hours project experience', 'Global standard for project management professionals', 'Successfully managed 3 major infrastructure projects', 'project-management,planning,execution,infrastructure'),

(6, 15, 'Emergency Response Coordinator', 'National Disaster Risk Reduction and Management Council', 'ERC-2024-012', 'Safety', 'Intermediate', 86.0, '2024-01-15', '2025-01-15', '2024-01-15', 'https://drive.google.com/emergency-response-cert-012.pdf', 'Active', 'Verified', 8000.00, 24, 24.0, TRUE, 12, '2025-01-15', 'First aid certification', 'Emergency response planning and coordination certification', 'Led emergency drills for municipal building', 'emergency,safety,disaster-management,coordination'),

-- Employee 7 - Health Services
(7, 14, 'Environmental Health Officer', 'Department of Health', 'EHO-2024-013', 'Environment', 'Advanced', 90.0, '2024-02-01', '2026-02-01', '2024-02-01', 'https://drive.google.com/env-health-cert-013.pdf', 'Active', 'Verified', 15000.00, 48, 48.0, TRUE, 24, '2026-02-01', 'Health science degree', 'Environmental health assessment and management', 'Specialized in water quality monitoring', 'environment,health,water-quality,public-health'),

-- Employee 8 - Senior Administrative
(8, 13, 'Legal Compliance Officer', 'Philippine Association of Legal Compliance', 'LCO-2024-014', 'Legal', 'Intermediate', 84.0, '2024-02-10', '2025-02-10', '2024-02-10', 'https://drive.google.com/legal-compliance-cert-014.pdf', 'Active', 'Verified', 18000.00, 40, 40.0, TRUE, 12, '2025-02-10', 'Basic legal knowledge', 'Government compliance and regulatory requirements', 'Strong understanding of local government regulations', 'legal,compliance,regulations,government-law'),

(8, 19, 'Strategic Planning Certification', 'Institute for Strategic Planning', 'SPC-2024-015', 'Management', 'Advanced', 88.5, '2024-02-15', '2026-02-15', '2024-02-15', 'https://drive.google.com/strategic-planning-cert-015.pdf', 'Active', 'Verified', 22000.00, 50, 50.0, TRUE, 24, '2026-02-15', '5 years management experience', 'Long-term strategic planning and implementation', 'Developed 5-year municipal development plan', 'strategic-planning,management,long-term-planning,development'),

-- Employee 9 - IT Specialist
(9, 12, 'Digital Transformation Specialist', 'Digital Government Institute', 'DTS-2024-016', 'Technology', 'Advanced', 93.0, '2024-02-20', '2025-02-20', '2024-02-20', 'https://drive.google.com/digital-transform-cert-016.pdf', 'Active', 'Verified', 16000.00, 35, 35.0, TRUE, 12, '2025-02-20', 'IT background and government experience', 'Digital transformation strategies for government agencies', 'Led digitization of municipal services', 'digital-transformation,technology,government-services,innovation'),

(9, 11, 'Microsoft Azure Fundamentals', 'Microsoft Corporation', 'AZ-900-2024-017', 'Technology', 'Intermediate', 82.0, '2024-01-30', '2026-01-30', '2024-01-30', 'https://drive.google.com/azure-cert-017.pdf', 'Active', 'Verified', 8000.00, 20, 20.0, TRUE, 24, '2026-01-30', 'Basic cloud computing knowledge', 'Cloud computing fundamentals and Azure services', 'Good understanding of cloud infrastructure', 'cloud-computing,azure,microsoft,infrastructure'),

-- Employee 10 - Administrative Assistant
(10, 17, 'Professional Report Writing', 'Business Communication Institute', 'PRW-2024-018', 'Communication', 'Intermediate', 81.0, '2024-03-05', '2025-03-05', '2024-03-05', 'https://drive.google.com/report-writing-cert-018.pdf', 'Active', 'Verified', 5500.00, 12, 12.0, TRUE, 12, '2025-03-05', 'Basic writing skills', 'Professional business and technical report writing', 'Improved department report quality significantly', 'report-writing,communication,documentation,business-writing'),

-- Additional certifications for comprehensive coverage

-- Expired certification for Employee 3
(3, 2, 'Basic Communication Skills', 'Local Training Institute', 'BCS-2022-019', 'Communication', 'Intermediate', 80.0, '2022-01-15', '2023-01-15', '2022-01-15', 'https://drive.google.com/basic-comm-cert-019.pdf', 'Expired', 'Verified', 3000.00, 8, 8.0, TRUE, 12, '2023-01-15', 'None', 'Basic communication and interpersonal skills', 'Superseded by advanced certification', 'communication,basic,interpersonal'),

-- Pending renewal certification for Employee 4
(4, 1, 'Supervisory Leadership', 'Management Development Institute', 'SL-2023-020', 'Leadership', 'Intermediate', 85.0, '2023-12-01', '2024-12-01', '2023-12-01', 'https://drive.google.com/supervisory-cert-020.pdf', 'Active', 'Verified', 10000.00, 24, 24.0, TRUE, 12, '2024-12-01', 'Supervisory role', 'Basic to intermediate supervisory skills', 'Due for renewal in 3 months', 'leadership,supervision,management,renewal-due'),

-- Suspended certification (compliance issue)
(6, 20, 'Quality Assurance Professional', 'Quality Management Institute', 'QAP-2023-021', 'Management', 'Advanced', 90.0, '2023-06-15', '2025-06-15', '2023-06-15', 'https://drive.google.com/quality-assurance-cert-021.pdf', 'Suspended', 'Pending', 14000.00, 36, 36.0, TRUE, 24, '2025-06-15', '3 years QA experience', 'Quality assurance and control systems', 'Suspended pending verification of training provider', 'quality-assurance,management,suspended,verification'),

-- High-value certification
(1, 19, 'Executive Leadership Program', 'Asian Institute of Management', 'ELP-2023-022', 'Leadership', 'Expert', 94.0, '2023-09-20', '2026-09-20', '2023-09-20', 'https://drive.google.com/exec-leadership-cert-022.pdf', 'Active', 'Verified', 150000.00, 120, 120.0, TRUE, 36, '2026-09-20', 'Senior management position', 'Executive leadership development for senior government officials', 'Comprehensive program covering strategic leadership', 'executive,leadership,strategic,senior-management'),

-- Recent certification with high CPE credits
(2, 16, 'Advanced Budget Management', 'Government Budget Institute', 'ABM-2024-023', 'Finance', 'Advanced', 91.5, '2024-03-10', '2025-03-10', '2024-03-10', 'https://drive.google.com/budget-mgmt-cert-023.pdf', 'Active', 'Verified', 25000.00, 60, 60.0, TRUE, 12, '2025-03-10', 'Intermediate budget knowledge', 'Advanced budgeting, forecasting, and financial planning', 'Recently completed, high CPE credit value', 'budget,finance,planning,advanced,recent');

INSERT INTO training_feedback (
    employee_id, feedback_type, session_id, trainer_id, course_id, 
    overall_rating, content_rating, instructor_rating,
    what_worked_well, what_could_improve, additional_comments,
    would_recommend, met_expectations, feedback_date, is_anonymous
) VALUES

-- Feedback for Leadership Fundamentals Training Session
(1, 'Training Session', 1, NULL, NULL, 
 5, 5, 5,
 'Excellent training session! Dr. Santos presented the material clearly and used practical examples from municipal governance. The interactive exercises were very engaging and helped reinforce key concepts.',
 'Could benefit from more case studies specific to small municipalities. The session ran slightly longer than scheduled.',
 'This training significantly improved my understanding of leadership principles. The materials provided were comprehensive and well-organized.',
 TRUE, TRUE, '2024-03-17', FALSE),

(2, 'Training Session', 1, NULL, NULL,
 4, 4, 5,
 'Dr. Santos is an excellent facilitator with deep knowledge of public administration. The training content was highly relevant to my role as Municipal Engineer.',
 'Some concepts were quite advanced and could use more basic explanations. More time needed for hands-on exercises.',
 'The networking opportunities with other department heads were valuable. Learned from their experiences and challenges.',
 TRUE, TRUE, '2024-03-17', FALSE),

-- Feedback for Digital Skills Training
(4, 'Training Session', 2, NULL, NULL,
 4, 4, 4,
 'Very practical training that addressed real workplace needs. Engr. Ramos demonstrated excellent technical knowledge and was patient with questions.',
 'Internet connectivity issues disrupted some online demonstrations. Some participants needed more basic computer instruction.',
 'The training materials were well-structured and easy to follow. Appreciate having both video tutorials and written guides.',
 TRUE, TRUE, '2024-03-23', FALSE),

-- Feedback for Customer Service Excellence Workshop
(7, 'Training Session', 3, NULL, NULL,
 5, 5, 4,
 'Ms. Morales provided excellent insights into dealing with difficult situations and improving public service delivery. Role-playing exercises were very helpful.',
 'Workshop room was too small for the number of participants. Some activities felt rushed due to time constraints.',
 'The focus on municipal service scenarios made the training highly relevant. Appreciated the practical tips for handling complaints.',
 TRUE, TRUE, '2024-03-27', FALSE),

-- Trainer-specific feedback
(3, 'Trainer', NULL, 1, NULL,
 5, NULL, 5,
 'Dr. Santos is an outstanding trainer with exceptional knowledge of leadership and public administration. Her teaching style is engaging and inclusive.',
 'Could provide more opportunities for participant questions during presentations rather than saving all questions for the end.',
 'Her use of real-world examples from her municipal experience made the concepts more relatable and practical.',
 TRUE, TRUE, '2024-03-18', FALSE),

-- Learning resource feedback
(1, 'Learning Resource', NULL, NULL, NULL,
 4, 4, NULL,
 'The Municipal Governance Handbook is comprehensive and well-written. It covers all essential aspects of municipal administration.',
 'Some sections are too technical for employees without legal background. Could use more illustrations and flowcharts.',
 'Excellent reference material that I refer to regularly. The index and cross-references are very helpful.',
 TRUE, FALSE, '2024-02-15', FALSE),

-- Course content feedback
(5, 'Course', NULL, NULL, 4,
 3, 3, NULL,
 'The Financial Management course covered important topics relevant to municipal finance. Content was accurate and up-to-date.',
 'Course materials were quite dry and theoretical. Needed more practical exercises and real municipal examples.',
 'While informative, the course could be more engaging. Some modules were repetitive.',
 FALSE, TRUE, '2024-04-06', FALSE),

-- More training session feedback
(8, 'Training Session', 5, NULL, NULL,
 4, 4, 4,
 'The overall training program offered by the municipality is excellent and shows commitment to employee development. Good variety of courses available.',
 'Training schedule sometimes conflicts with work duties. Need better coordination with department heads for release of employees.',
 'Appreciate that training certificates are recognized for performance evaluations. This motivates participation.',
 TRUE, TRUE, '2024-02-28', FALSE),

-- Anonymous feedback with constructive criticism
(9, 'Training Session', 4, NULL, NULL,
 2, 2, 3,
 'Ms. Mendoza has good technical knowledge of financial management principles.',
 'Training delivery was too fast-paced. Complex financial concepts need more explanation and examples. Trainer seemed unprepared for some participant questions.',
 'Materials were outdated and some information conflicted with current municipal procedures.',
 FALSE, FALSE, '2024-04-06', TRUE),

-- Positive feedback for excellent training
(10, 'Training Session', 7, NULL, NULL,
 5, 5, 5,
 'Prof. Reyes delivered an exceptional communication skills workshop! Her expertise in public relations and media management was evident throughout. Excellent use of multimedia and interactive exercises.',
 'Could not identify any significant areas for improvement. The workshop exceeded expectations.',
 'This was one of the best training sessions I have attended. The content was practical and immediately applicable to my work.',
 TRUE, TRUE, '2024-04-24', FALSE),

-- Trainer feedback with mixed review
(6, 'Trainer', NULL, 2, NULL,
 3, NULL, 2,
 'Engr. Cruz has excellent technical knowledge of project management principles and real-world experience.',
 'Poor presentation skills made it difficult to follow the training. No visual aids or handouts provided.',
 'Content was valuable but delivery needs significant improvement. Participants struggled to stay engaged.',
 FALSE, TRUE, '2024-04-18', FALSE),

-- More learning resource feedback
(11, 'Learning Resource', NULL, NULL, NULL,
 5, 5, NULL,
 'The online project management course was excellent. Self-paced format worked well with my schedule.',
 'Some video quality could be improved. A few modules had audio sync issues.',
 'Great investment in our professional development. The certificate is a nice bonus.',
 TRUE, TRUE, '2024-03-10', FALSE);


