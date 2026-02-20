# TODO List for Attendance Issue Fix

## Investigation Phase
- [x] Debug employee count queries to understand why only 5 employees are shown
- [x] Check employment_status distribution in employee_profiles table
- [x] Identify that queries filter by employment_status IN ('Full-time', 'Part-time')

## Implementation Phase
- [x] Update fetch_attendance_overview.php to remove employment_status filter
- [x] Update attendance.php statistics queries to remove employment_status filter
- [x] Update attendance_summary.php statistics queries to remove employment_status filter
- [x] Test the changes to ensure all 16 employees are displayed
