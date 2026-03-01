<?php
/**
 * report_functions.php
 * Municipal Payroll Reporting Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

function getDepartments(PDO $conn): array
{
    try {
        $stmt = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches all payroll cycles for the dropdown.
 */
function getPayrollCycles(PDO $conn): array
{
    try {
        $stmt = $conn->query("SELECT payroll_cycle_id, cycle_name, pay_period_start, pay_period_end, pay_date FROM payroll_cycles ORDER BY pay_period_start DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching payroll cycles: " . $e->getMessage());
        return [];
    }
}

/**
 * Generates the General Payroll Sheet (Municipal Format)
 * Lists employees with specific breakdown of GSIS, PhilHealth, Pag-IBIG, Tax
 */
function getGeneralPayroll(PDO $conn, int $cycleId, ?int $departmentId = null): array
{
    // We calculate the breakdown by joining statutory_deductions.
    // Note: We assume the deduction_amount in statutory_deductions is MONTHLY.
    // If the payroll cycle is semi-monthly, the transaction stores half.
    // This query aggregates transactions within the date range.
    
    $sql = "SELECT
                ep.employee_number,
                MAX(pi.first_name) as first_name,
                MAX(pi.last_name) as last_name,
                MAX(jr.title) as position,
                MAX(d.department_name) as department_name,
                MAX(ep.current_salary) as monthly_rate,
                
                -- Salary Structure for splitting Gross into Basic and Allowances
                MAX(ep.current_salary) as struct_basic,
                0 as struct_allowance,

                COALESCE(SUM(pt.gross_pay), 0) as gross_earned,
                -- Sum all deductions into one column
                COALESCE(SUM(pt.tax_deductions + pt.statutory_deductions + pt.other_deductions), 0) as total_deductions,
                COALESCE(SUM(pt.net_pay), 0) as net_amount,
                MAX(ps.payslip_id) as payslip_id
            FROM employee_profiles ep
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            
            -- LEFT JOIN to include employees even without payroll for the period
            LEFT JOIN payroll_transactions pt ON ep.employee_id = pt.employee_id 
                AND pt.payroll_cycle_id = :cycleId
            LEFT JOIN payslips ps ON pt.payroll_transaction_id = ps.payroll_transaction_id
            WHERE 1=1";

    if ($departmentId) {
        $sql .= " AND d.department_id = :departmentId";
    }

    $sql .= " GROUP BY ep.employee_id, ep.employee_number ORDER BY last_name, first_name";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':cycleId', $cycleId, PDO::PARAM_INT);
        if ($departmentId) {
            $stmt->bindParam(':departmentId', $departmentId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Display error on screen for debugging
        echo "<div class='alert alert-danger m-3'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        error_log("General Payroll Report Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Generates a Remittance Summary (Totals per agency)
 */
function getRemittanceReport(PDO $conn, int $cycleId, ?int $departmentId = null): array
{
    // This aggregates the totals for the entire selected period/department
    $sql = "SELECT
                'GSIS Premiums' as remittance_type,
                SUM(
                    (SELECT COALESCE(SUM(sd.deduction_amount), 0) 
                     FROM statutory_deductions sd 
                     WHERE sd.employee_id = ep.employee_id AND LOWER(sd.deduction_type) LIKE '%gsis%') / 2
                ) as total_amount
            FROM payroll_transactions pt
            JOIN payroll_cycles pc ON pt.payroll_cycle_id = pc.payroll_cycle_id
            JOIN employee_profiles ep ON pt.employee_id = ep.employee_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE pt.payroll_cycle_id = :cycleId1
            " . ($departmentId ? " AND d.department_id = :deptId1" : "") . "
            
            UNION ALL
            
            SELECT
                'PhilHealth Contributions',
                SUM(
                    (SELECT COALESCE(SUM(sd.deduction_amount), 0) 
                     FROM statutory_deductions sd 
                     WHERE sd.employee_id = ep.employee_id AND LOWER(sd.deduction_type) LIKE '%philhealth%') / 2
                )
            FROM payroll_transactions pt
            JOIN payroll_cycles pc ON pt.payroll_cycle_id = pc.payroll_cycle_id
            JOIN employee_profiles ep ON pt.employee_id = ep.employee_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE pt.payroll_cycle_id = :cycleId2
            " . ($departmentId ? " AND d.department_id = :deptId2" : "") . "
            
            UNION ALL
            
            SELECT
                'Pag-IBIG Funds',
                SUM(
                    (SELECT COALESCE(SUM(sd.deduction_amount), 0) 
                     FROM statutory_deductions sd 
                     WHERE sd.employee_id = ep.employee_id AND LOWER(sd.deduction_type) LIKE '%pag-ibig%') / 2
                )
            FROM payroll_transactions pt
            JOIN payroll_cycles pc ON pt.payroll_cycle_id = pc.payroll_cycle_id
            JOIN employee_profiles ep ON pt.employee_id = ep.employee_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE pt.payroll_cycle_id = :cycleId3
            " . ($departmentId ? " AND d.department_id = :deptId3" : "") . "
            
            UNION ALL
            
            SELECT
                'Withholding Tax (BIR)',
                SUM(pt.tax_deductions)
            FROM payroll_transactions pt
            JOIN payroll_cycles pc ON pt.payroll_cycle_id = pc.payroll_cycle_id
            JOIN employee_profiles ep ON pt.employee_id = ep.employee_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE pt.payroll_cycle_id = :cycleId4
            " . ($departmentId ? " AND d.department_id = :deptId4" : "");

    try {
        $stmt = $conn->prepare($sql);
        
        // Bind parameters uniquely for each part of the UNION query
        $stmt->bindValue(':cycleId1', $cycleId, PDO::PARAM_INT);
        if ($departmentId) $stmt->bindValue(':deptId1', $departmentId, PDO::PARAM_INT);
        
        $stmt->bindValue(':cycleId2', $cycleId, PDO::PARAM_INT);
        if ($departmentId) $stmt->bindValue(':deptId2', $departmentId, PDO::PARAM_INT);
        
        $stmt->bindValue(':cycleId3', $cycleId, PDO::PARAM_INT);
        if ($departmentId) $stmt->bindValue(':deptId3', $departmentId, PDO::PARAM_INT);
        
        $stmt->bindValue(':cycleId4', $cycleId, PDO::PARAM_INT);
        if ($departmentId) $stmt->bindValue(':deptId4', $departmentId, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Display error on screen for debugging
        echo "<div class='alert alert-danger m-3'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        error_log("Remittance Report Error: " . $e->getMessage());
        return [];
    }
}