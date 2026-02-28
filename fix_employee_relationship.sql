-- Fix Employee Relationship Script
-- This script fixes the foreign key relationship between employee_profiles and personal_information

-- First, let's check the current state
SELECT 'Current employee_profiles data:' as info;
SELECT employee_id, personal_info_id, employee_number, work_email 
FROM employee_profiles 
WHERE employee_id = 2;

SELECT 'Current personal_information data:' as info;
SELECT personal_info_id, first_name, last_name, email 
FROM personal_information 
WHERE personal_info_id = 2;

-- Check if foreign key constraint exists
SELECT 'Foreign key constraints:' as info;
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_NAME = 'personal_information' 
AND TABLE_SCHEMA = 'hr_system';

-- Add foreign key constraint if it doesn't exist
ALTER TABLE employee_profiles 
ADD CONSTRAINT fk_employee_personal_info 
FOREIGN KEY (personal_info_id) 
REFERENCES personal_information(personal_info_id) 
ON DELETE SET NULL;

-- Verify the constraint was added
SELECT 'After adding constraint:' as info;
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE REFERENCED_TABLE_NAME = 'personal_information' 
AND TABLE_SCHEMA = 'hr_system';

-- Test the JOIN query that was failing
SELECT 'Testing JOIN query:' as info;
SELECT ep.employee_id, ep.employee_number, ep.work_email, 
       pi.first_name, pi.last_name, pi.email as personal_email,
       jr.title as job_title
FROM employee_profiles ep
LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
WHERE ep.employee_id = 2;