@echo off
echo ========================================
echo Git Push Script
echo ========================================
echo.

echo Step 1: Adding all changes...
git add .

echo.
echo Step 2: Committing changes...
git commit -m "Fix: Add PDS table, update schema, and fix AI job generation - Added pds_data table for Personal Data Sheet storage - Added missing columns to job_openings - Added vacancy_limit to departments - Added late_minutes to attendance - Fixed logAPIUsage PDO compatibility - Updated PHP files for new schema"

echo.
echo Step 3: Pushing to remote repository...
git push

echo.
echo ========================================
echo Push completed!
echo ========================================
echo.
pause
