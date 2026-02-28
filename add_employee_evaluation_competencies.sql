-- Employee Evaluation Form Competencies
-- This SQL adds the competencies adapted from the Teacher Evaluation Form
-- Note: Using job_role_id = 1 as default (assuming it exists in job_roles table)

-- Category 1: Job Knowledge & Expertise
INSERT INTO competencies (name, description, job_role_id) VALUES 
('Job Knowledge', 'Has sound and updated knowledge on the job/field', 1),
('Information Sharing', 'Gives adequate information considering team members'' level', 1),
('Clarity of Tasks', 'Makes tasks/projects easily understandable', 1),
('Practical Examples', 'Gives appropriate and practical examples to motivate the team', 1),
('Additional Resources', 'Provides additional resources apart from standard documentation', 1);

-- Category 2: Work Presentation & Management
INSERT INTO competencies (name, description, job_role_id) VALUES 
('Communication Skills', 'Communicates clearly in meetings and presentations', 1),
('Positive Attitude', 'Keeps workplace lively using wit (humor) and positive attitude', 1),
('Team Engagement', 'Encourages team participation (question-answer/discussions)', 1),
('Work Environment', 'Maintains a conducive environment for productivity', 1),
('Planning Skills', 'Provides and maintains a work plan', 1);
