-- Add Seminar to the activity_type enum in development_activities table
-- This SQL modifies the ENUM type to include 'Seminar'

-- First, let's check the current enum values
-- Then modify the column to include 'Seminar'

ALTER TABLE development_activities 
MODIFY COLUMN activity_type ENUM('Training', 'Mentoring', 'Project', 'Education', 'Seminar', 'Other') NOT NULL;

-- Verify the change
SHOW COLUMNS FROM development_activities LIKE 'activity_type';
