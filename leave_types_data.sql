-- Update existing leave types to match Philippine labor standards
UPDATE leave_types SET default_days = 15.00 WHERE leave_type_name = 'Sick Leave';
UPDATE leave_types SET default_days = 105.00 WHERE leave_type_name = 'Maternity Leave';

-- Note: Vacation Leave (15 days), Paternity Leave (7 days), and Emergency Leave (5 days) already match Philippine standards
-- If you need to insert new leave types (for fresh database setup), uncomment the INSERT below:
/*
INSERT INTO leave_types (leave_type_name, description, paid, default_days, carry_forward, max_carry_forward_days) VALUES
('Vacation Leave', 'Annual vacation leave', 1, 15.00, 0, 0.00),
('Sick Leave', 'Medical leave for illness', 1, 15.00, 0, 0.00),
('Maternity Leave', 'Leave for new mothers', 1, 105.00, 0, 0.00),
('Paternity Leave', 'Leave for new fathers', 1, 7.00, 0, 0.00),
('Emergency Leave', 'Unplanned emergency leave', 0, 5.00, 0, 0.00);
*/
