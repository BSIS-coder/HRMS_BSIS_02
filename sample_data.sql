-- Insert sample data for shifts
INSERT INTO shifts (shift_name, start_time, end_time, description) VALUES
('Morning Shift', '08:00:00', '16:00:00', 'Standard morning shift from 8 AM to 4 PM'),
('Afternoon Shift', '14:00:00', '22:00:00', 'Afternoon/evening shift from 2 PM to 10 PM'),
('Night Shift', '22:00:00', '06:00:00', 'Night shift from 10 PM to 6 AM'),
('Flexible Shift', '09:00:00', '17:00:00', 'Flexible working hours');

-- Insert sample data for leave_balances
INSERT INTO leave_balances (employee_id, leave_type_id, year, total_leaves, leaves_taken, leaves_pending, leaves_remaining) VALUES
-- Vacation Leave (assuming leave_type_id 1 is Vacation)
(1, 1, 2024, 15, 3, 0, 12),
(2, 1, 2024, 15, 5, 1, 9),
(3, 1, 2024, 15, 2, 0, 13),
(4, 1, 2024, 15, 7, 0, 8),
(5, 1, 2024, 15, 4, 2, 9),
-- Sick Leave (assuming leave_type_id 2 is Sick)
(1, 2, 2024, 10, 1, 0, 9),
(2, 2, 2024, 10, 3, 0, 7),
(3, 2, 2024, 10, 0, 0, 10),
(4, 2, 2024, 10, 2, 0, 8),
(5, 2, 2024, 10, 1, 0, 9),
-- Maternity Leave (assuming leave_type_id 3 is Maternity)
(1, 3, 2024, 60, 0, 0, 60),
(2, 3, 2024, 60, 0, 0, 60),
(3, 3, 2024, 60, 0, 0, 60),
-- Paternity Leave (assuming leave_type_id 4 is Paternity)
(1, 4, 2024, 7, 0, 0, 7),
(2, 4, 2024, 7, 0, 0, 7),
(4, 4, 2024, 7, 0, 0, 7);

-- Insert sample data for employee_shifts
INSERT INTO employee_shifts (employee_id, shift_id, assigned_date, is_overtime) VALUES
(1, 1, '2024-01-15', 0),
(2, 2, '2024-01-15', 1),
(3, 1, '2024-01-16', 0),
(4, 3, '2024-01-16', 0),
(5, 1, '2024-01-17', 0),
(6, 2, '2024-01-17', 1),
(7, 1, '2024-01-18', 0),
(8, 4, '2024-01-18', 0);
