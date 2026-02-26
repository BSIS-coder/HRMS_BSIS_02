<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Check if user is admin or hr
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: unauthorized.php");
    exit;
}

// Include database connection
require_once 'config.php';
$pdo = $conn;

$message     = '';
$messageType = '';

// ─────────────────────────────────────────────────────────────
// HANDLE FORM SUBMISSIONS (Add / Update / Delete reports)
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {

        // ── ADD ─────────────────────────────────────────────
        case 'add':
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO reports (
                        report_code, report_type, report_title, description,
                        report_period_start, report_period_end,
                        department_id, employee_id,
                        total_employees_included,
                        total_gross_pay, total_tax_deductions,
                        total_statutory_deductions, total_other_deductions, total_net_pay,
                        payroll_cycle_id,
                        cycle_id, average_overall_rating,
                        total_reviews_submitted, total_reviews_finalized,
                        highest_rating, lowest_rating,
                        total_present, total_absent, total_late, total_on_leave,
                        total_working_hours, total_overtime_hours, attendance_rate_pct,
                        total_leave_requests, approved_leave_requests,
                        rejected_leave_requests, pending_leave_requests,
                        total_leave_days_taken, leave_type_breakdown,
                        report_status, file_path, file_format,
                        generated_by, notes
                    ) VALUES (
                        :report_code, :report_type, :report_title, :description,
                        :period_start, :period_end,
                        :dept_id, :emp_id,
                        :total_emp,
                        :gross_pay, :tax_ded,
                        :stat_ded, :other_ded, :net_pay,
                        :payroll_cycle,
                        :cycle_id, :avg_rating,
                        :reviews_sub, :reviews_fin,
                        :high_rating, :low_rating,
                        :present, :absent, :late, :on_leave,
                        :work_hrs, :ot_hrs, :att_rate,
                        :total_lv, :approved_lv,
                        :rejected_lv, :pending_lv,
                        :lv_days, :lv_breakdown,
                        :status, :file_path, :file_format,
                        :gen_by, :notes
                    )
                ");
                $stmt->execute([
                    ':report_code'  => strtoupper(trim($_POST['report_code'])),
                    ':report_type'  => $_POST['report_type'],
                    ':report_title' => $_POST['report_title'],
                    ':description'  => $_POST['description'] ?: null,
                    ':period_start' => $_POST['report_period_start'],
                    ':period_end'   => $_POST['report_period_end'],
                    ':dept_id'      => !empty($_POST['department_id'])  ? $_POST['department_id']  : null,
                    ':emp_id'       => !empty($_POST['employee_id'])    ? $_POST['employee_id']    : null,
                    ':total_emp'    => !empty($_POST['total_employees_included']) ? $_POST['total_employees_included'] : null,
                    ':gross_pay'    => !empty($_POST['total_gross_pay'])          ? $_POST['total_gross_pay']          : null,
                    ':tax_ded'      => !empty($_POST['total_tax_deductions'])     ? $_POST['total_tax_deductions']     : null,
                    ':stat_ded'     => !empty($_POST['total_statutory_deductions']) ? $_POST['total_statutory_deductions'] : null,
                    ':other_ded'    => !empty($_POST['total_other_deductions'])   ? $_POST['total_other_deductions']   : null,
                    ':net_pay'      => !empty($_POST['total_net_pay'])            ? $_POST['total_net_pay']            : null,
                    ':payroll_cycle'=> !empty($_POST['payroll_cycle_id'])         ? $_POST['payroll_cycle_id']         : null,
                    ':cycle_id'     => !empty($_POST['cycle_id'])                 ? $_POST['cycle_id']                 : null,
                    ':avg_rating'   => !empty($_POST['average_overall_rating'])   ? $_POST['average_overall_rating']   : null,
                    ':reviews_sub'  => !empty($_POST['total_reviews_submitted'])  ? $_POST['total_reviews_submitted']  : null,
                    ':reviews_fin'  => !empty($_POST['total_reviews_finalized'])  ? $_POST['total_reviews_finalized']  : null,
                    ':high_rating'  => !empty($_POST['highest_rating'])           ? $_POST['highest_rating']           : null,
                    ':low_rating'   => !empty($_POST['lowest_rating'])            ? $_POST['lowest_rating']            : null,
                    ':present'      => !empty($_POST['total_present'])            ? $_POST['total_present']            : null,
                    ':absent'       => !empty($_POST['total_absent'])             ? $_POST['total_absent']             : null,
                    ':late'         => !empty($_POST['total_late'])               ? $_POST['total_late']               : null,
                    ':on_leave'     => !empty($_POST['total_on_leave'])           ? $_POST['total_on_leave']           : null,
                    ':work_hrs'     => !empty($_POST['total_working_hours'])      ? $_POST['total_working_hours']      : null,
                    ':ot_hrs'       => !empty($_POST['total_overtime_hours'])     ? $_POST['total_overtime_hours']     : null,
                    ':att_rate'     => !empty($_POST['attendance_rate_pct'])      ? $_POST['attendance_rate_pct']      : null,
                    ':total_lv'     => !empty($_POST['total_leave_requests'])     ? $_POST['total_leave_requests']     : null,
                    ':approved_lv'  => !empty($_POST['approved_leave_requests'])  ? $_POST['approved_leave_requests']  : null,
                    ':rejected_lv'  => !empty($_POST['rejected_leave_requests'])  ? $_POST['rejected_leave_requests']  : null,
                    ':pending_lv'   => !empty($_POST['pending_leave_requests'])   ? $_POST['pending_leave_requests']   : null,
                    ':lv_days'      => !empty($_POST['total_leave_days_taken'])   ? $_POST['total_leave_days_taken']   : null,
                    ':lv_breakdown' => !empty($_POST['leave_type_breakdown'])     ? $_POST['leave_type_breakdown']     : null,
                    ':status'       => $_POST['report_status'],
                    ':file_path'    => !empty($_POST['file_path'])  ? $_POST['file_path']  : null,
                    ':file_format'  => $_POST['file_format'] ?? 'N/A',
                    ':gen_by'       => $_SESSION['user_id'] ?? 1,
                    ':notes'        => !empty($_POST['notes']) ? $_POST['notes'] : null,
                ]);
                $message     = "Report created successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message     = "Error creating report: " . $e->getMessage();
                $messageType = "error";
            }
            break;

        // ── UPDATE ──────────────────────────────────────────
        case 'update':
            try {
                $stmt = $pdo->prepare("
                    UPDATE reports SET
                        report_code=:report_code, report_type=:report_type,
                        report_title=:report_title, description=:description,
                        report_period_start=:period_start, report_period_end=:period_end,
                        department_id=:dept_id, employee_id=:emp_id,
                        total_employees_included=:total_emp,
                        total_gross_pay=:gross_pay, total_tax_deductions=:tax_ded,
                        total_statutory_deductions=:stat_ded, total_other_deductions=:other_ded,
                        total_net_pay=:net_pay, payroll_cycle_id=:payroll_cycle,
                        cycle_id=:cycle_id, average_overall_rating=:avg_rating,
                        total_reviews_submitted=:reviews_sub, total_reviews_finalized=:reviews_fin,
                        highest_rating=:high_rating, lowest_rating=:low_rating,
                        total_present=:present, total_absent=:absent,
                        total_late=:late, total_on_leave=:on_leave,
                        total_working_hours=:work_hrs, total_overtime_hours=:ot_hrs,
                        attendance_rate_pct=:att_rate,
                        total_leave_requests=:total_lv, approved_leave_requests=:approved_lv,
                        rejected_leave_requests=:rejected_lv, pending_leave_requests=:pending_lv,
                        total_leave_days_taken=:lv_days, leave_type_breakdown=:lv_breakdown,
                        report_status=:status, file_path=:file_path, file_format=:file_format,
                        notes=:notes
                    WHERE report_id=:report_id
                ");
                $stmt->execute([
                    ':report_code'  => strtoupper(trim($_POST['report_code'])),
                    ':report_type'  => $_POST['report_type'],
                    ':report_title' => $_POST['report_title'],
                    ':description'  => $_POST['description'] ?: null,
                    ':period_start' => $_POST['report_period_start'],
                    ':period_end'   => $_POST['report_period_end'],
                    ':dept_id'      => !empty($_POST['department_id'])  ? $_POST['department_id']  : null,
                    ':emp_id'       => !empty($_POST['employee_id'])    ? $_POST['employee_id']    : null,
                    ':total_emp'    => !empty($_POST['total_employees_included']) ? $_POST['total_employees_included'] : null,
                    ':gross_pay'    => !empty($_POST['total_gross_pay'])          ? $_POST['total_gross_pay']          : null,
                    ':tax_ded'      => !empty($_POST['total_tax_deductions'])     ? $_POST['total_tax_deductions']     : null,
                    ':stat_ded'     => !empty($_POST['total_statutory_deductions']) ? $_POST['total_statutory_deductions'] : null,
                    ':other_ded'    => !empty($_POST['total_other_deductions'])   ? $_POST['total_other_deductions']   : null,
                    ':net_pay'      => !empty($_POST['total_net_pay'])            ? $_POST['total_net_pay']            : null,
                    ':payroll_cycle'=> !empty($_POST['payroll_cycle_id'])         ? $_POST['payroll_cycle_id']         : null,
                    ':cycle_id'     => !empty($_POST['cycle_id'])                 ? $_POST['cycle_id']                 : null,
                    ':avg_rating'   => !empty($_POST['average_overall_rating'])   ? $_POST['average_overall_rating']   : null,
                    ':reviews_sub'  => !empty($_POST['total_reviews_submitted'])  ? $_POST['total_reviews_submitted']  : null,
                    ':reviews_fin'  => !empty($_POST['total_reviews_finalized'])  ? $_POST['total_reviews_finalized']  : null,
                    ':high_rating'  => !empty($_POST['highest_rating'])           ? $_POST['highest_rating']           : null,
                    ':low_rating'   => !empty($_POST['lowest_rating'])            ? $_POST['lowest_rating']            : null,
                    ':present'      => !empty($_POST['total_present'])            ? $_POST['total_present']            : null,
                    ':absent'       => !empty($_POST['total_absent'])             ? $_POST['total_absent']             : null,
                    ':late'         => !empty($_POST['total_late'])               ? $_POST['total_late']               : null,
                    ':on_leave'     => !empty($_POST['total_on_leave'])           ? $_POST['total_on_leave']           : null,
                    ':work_hrs'     => !empty($_POST['total_working_hours'])      ? $_POST['total_working_hours']      : null,
                    ':ot_hrs'       => !empty($_POST['total_overtime_hours'])     ? $_POST['total_overtime_hours']     : null,
                    ':att_rate'     => !empty($_POST['attendance_rate_pct'])      ? $_POST['attendance_rate_pct']      : null,
                    ':total_lv'     => !empty($_POST['total_leave_requests'])     ? $_POST['total_leave_requests']     : null,
                    ':approved_lv'  => !empty($_POST['approved_leave_requests'])  ? $_POST['approved_leave_requests']  : null,
                    ':rejected_lv'  => !empty($_POST['rejected_leave_requests'])  ? $_POST['rejected_leave_requests']  : null,
                    ':pending_lv'   => !empty($_POST['pending_leave_requests'])   ? $_POST['pending_leave_requests']   : null,
                    ':lv_days'      => !empty($_POST['total_leave_days_taken'])   ? $_POST['total_leave_days_taken']   : null,
                    ':lv_breakdown' => !empty($_POST['leave_type_breakdown'])     ? $_POST['leave_type_breakdown']     : null,
                    ':status'       => $_POST['report_status'],
                    ':file_path'    => !empty($_POST['file_path'])  ? $_POST['file_path']  : null,
                    ':file_format'  => $_POST['file_format'] ?? 'N/A',
                    ':notes'        => !empty($_POST['notes']) ? $_POST['notes'] : null,
                    ':report_id'    => $_POST['report_id'],
                ]);
                $message     = "Report updated successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message     = "Error updating report: " . $e->getMessage();
                $messageType = "error";
            }
            break;

        // ── DELETE ──────────────────────────────────────────
        case 'delete':
            try {
                $stmt = $pdo->prepare("DELETE FROM reports WHERE report_id = ?");
                $stmt->execute([$_POST['report_id']]);
                $message     = "Report deleted successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message     = "Error deleting report: " . $e->getMessage();
                $messageType = "error";
            }
            break;

        // ── APPROVE / REVIEW STATUS ─────────────────────────
        case 'update_status':
            try {
                $newStatus = $_POST['new_status'];
                $col       = $newStatus === 'Approved' ? ', approved_by=:uid, approved_at=NOW()' : ', reviewed_by=:uid, reviewed_at=NOW()';
                $stmt = $pdo->prepare("UPDATE reports SET report_status=:status $col WHERE report_id=:id");
                $stmt->execute([
                    ':status' => $newStatus,
                    ':uid'    => $_SESSION['user_id'] ?? 1,
                    ':id'     => $_POST['report_id'],
                ]);
                $message     = "Report status updated to <strong>$newStatus</strong>.";
                $messageType = "success";
            } catch (PDOException $e) {
                $message     = "Error updating status: " . $e->getMessage();
                $messageType = "error";
            }
            break;
    }
}

// ─────────────────────────────────────────────────────────────
// FETCH DATA
// ─────────────────────────────────────────────────────────────

// All reports (join department + employee names)
try {
    $allReports = $pdo->query("
        SELECT r.*,
               d.department_name,
               CONCAT(pi.first_name,' ',pi.last_name) AS employee_name,
               ep.employee_number,
               CONCAT(ug.first_name,' ',ug.last_name) AS generated_by_name
        FROM reports r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN employee_profiles ep ON r.employee_id = ep.employee_id
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        LEFT JOIN users u ON r.generated_by = u.user_id
        LEFT JOIN personal_information ug ON u.employee_id = ep.employee_id
        ORDER BY r.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $allReports = [];
}

// Payroll reports
try {
    $payrollReports = $pdo->query("
        SELECT r.*, d.department_name,
               CONCAT(pi.first_name,' ',pi.last_name) AS employee_name
        FROM reports r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN employee_profiles ep ON r.employee_id = ep.employee_id
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        WHERE r.report_type IN ('Payroll Summary','Payroll Detail')
        ORDER BY r.report_period_start DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payrollReports = [];
}

// Performance reports
try {
    $performanceReports = $pdo->query("
        SELECT r.*, prc.cycle_name, d.department_name
        FROM reports r
        LEFT JOIN performance_review_cycles prc ON r.cycle_id = prc.cycle_id
        LEFT JOIN departments d ON r.department_id = d.department_id
        WHERE r.report_type IN ('Performance Evaluation Summary','Performance Competency Report')
        ORDER BY r.report_period_start DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $performanceReports = [];
}

// Attendance reports
try {
    $attendanceReports = $pdo->query("
        SELECT r.*, d.department_name
        FROM reports r
        LEFT JOIN departments d ON r.department_id = d.department_id
        WHERE r.report_type = 'Attendance Report'
        ORDER BY r.report_period_start DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $attendanceReports = [];
}

// Leave reports
try {
    $leaveReports = $pdo->query("
        SELECT r.*, d.department_name
        FROM reports r
        LEFT JOIN departments d ON r.department_id = d.department_id
        WHERE r.report_type IN ('Leave Request Summary','Leave Balance Report')
        ORDER BY r.report_period_start DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $leaveReports = [];
}

// Employee information reports
try {
    $empInfoReports = $pdo->query("
        SELECT r.*, d.department_name
        FROM reports r
        LEFT JOIN departments d ON r.department_id = d.department_id
        WHERE r.report_type = 'Employee Information Report'
        ORDER BY r.report_period_start DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $empInfoReports = [];
}

// Summary stats for dashboard cards
$totalReports     = count($allReports);
$draftCount       = count(array_filter($allReports, fn($r) => $r['report_status'] === 'Draft'));
$approvedCount    = count(array_filter($allReports, fn($r) => $r['report_status'] === 'Approved'));
$pendingCount     = count(array_filter($allReports, fn($r) => in_array($r['report_status'], ['Generated','Reviewed'])));

// Departments and employees for dropdowns
try {
    $departments = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $departments = []; }

try {
    $employees = $pdo->query("
        SELECT ep.employee_id, ep.employee_number,
               CONCAT(pi.first_name,' ',pi.last_name) AS full_name
        FROM employee_profiles ep
        LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        WHERE ep.employment_status NOT IN ('Terminated','Resigned')
        ORDER BY pi.last_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $employees = []; }

try {
    $perfCycles = $pdo->query("SELECT cycle_id, cycle_name FROM performance_review_cycles ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $perfCycles = []; }

try {
    $payrollCycles = $pdo->query("SELECT payroll_cycle_id, cycle_name FROM payroll_cycles ORDER BY pay_period_start DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $payrollCycles = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        body { background: var(--azure-blue-pale); }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        /* ── Section Title ── */
        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-title i { font-size: 28px; }

        /* ── Stats Cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.07);
            border-left: 4px solid var(--azure-blue);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(233,30,99,0.15);
        }
        .stat-card.blue  { border-left-color: #287ef3; }
        .stat-card.green { border-left-color: #28a745; }
        .stat-card.amber { border-left-color: #ffc107; }
        .stat-card.gray  { border-left-color: #6c757d; }

        .stat-icon { font-size: 26px; margin-bottom: 8px; }
        .stat-card.blue  .stat-icon { color: #287ef3; }
        .stat-card.green .stat-icon { color: #28a745; }
        .stat-card.amber .stat-icon { color: #e0a800; }
        .stat-card.gray  .stat-icon { color: #6c757d; }
        .stat-card       .stat-icon { color: var(--azure-blue); }

        .stat-number { font-size: 30px; font-weight: 700; color: #222; line-height: 1; }
        .stat-label  { font-size: 13px; color: #777; margin-top: 4px; font-weight: 500; }

        /* ── Tabs ── */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }
        .tab-button {
            padding: 11px 18px;
            background: none;
            border: none;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.25s ease;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .tab-button.active { color: var(--azure-blue); border-bottom-color: var(--azure-blue); }
        .tab-button:hover  { color: var(--azure-blue-dark); }
        .tab-count {
            background: var(--azure-blue-lighter);
            color: var(--azure-blue-dark);
            border-radius: 10px;
            padding: 1px 8px;
            font-size: 12px;
        }
        .tab-button.active .tab-count { background: var(--azure-blue); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.25s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Controls bar ── */
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .controls-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .controls-right { display: flex; align-items: center; gap: 10px; }

        .search-box { position: relative; }
        .search-box input {
            width: 300px;
            padding: 10px 15px 10px 42px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .search-box input:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(233,30,99,0.12);
        }
        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 13px;
        }

        .filter-select {
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .filter-select:focus {
            border-color: var(--azure-blue);
            outline: none;
        }

        /* ── Buttons ── */
        .btn {
            padding: 10px 22px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--azure-blue), var(--azure-blue-light));
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233,30,99,0.4);
            color: white;
            text-decoration: none;
        }
        .btn-success  { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .btn-danger   { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
        .btn-warning  { background: linear-gradient(135deg, #ffc107, #e0a800); color: white; }
        .btn-info     { background: linear-gradient(135deg, #17a2b8, #148a9f); color: white; }
        .btn-secondary{ background: linear-gradient(135deg, #6c757d, #545b62); color: white; }

        .btn-success:hover, .btn-danger:hover, .btn-warning:hover,
        .btn-info:hover, .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            color: white;
            text-decoration: none;
        }
        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 20px;
        }

        /* ── Table ── */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            margin-bottom: 30px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            font-size: 14px;
        }
        .table th {
            background: linear-gradient(135deg, var(--azure-blue-lighter), #f3e5f5);
            padding: 13px 15px;
            text-align: left;
            font-weight: 700;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        .table td {
            padding: 13px 15px;
            border-bottom: 1px solid #f5f5f5;
            vertical-align: middle;
        }
        .table tbody tr:hover { background: #fce4ec22; }
        .table tbody tr:last-child td { border-bottom: none; }

        /* ── Badges ── */
        .badge-pill {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: inline-block;
        }
        .badge-draft      { background: #e9ecef; color: #495057; }
        .badge-generated  { background: #cfe2ff; color: #084298; }
        .badge-reviewed   { background: #fff3cd; color: #664d03; }
        .badge-approved   { background: #d1e7dd; color: #0f5132; }
        .badge-archived   { background: #e2e3e5; color: #383d41; }
        .badge-pdf        { background: #f8d7da; color: #721c24; }
        .badge-excel      { background: #d1e7dd; color: #0f5132; }
        .badge-csv        { background: #fff3cd; color: #664d03; }
        .badge-html       { background: #cfe2ff; color: #084298; }
        .badge-na         { background: #e9ecef; color: #495057; }

        /* report type colors */
        .badge-payroll    { background: #d1ecf1; color: #0c5460; }
        .badge-perf       { background: #d4edda; color: #155724; }
        .badge-attend     { background: #cfe2ff; color: #084298; }
        .badge-leave      { background: #fff3cd; color: #664d03; }
        .badge-empinfo    { background: #f8d7da; color: #721c24; }

        /* ── Sub-section title inside tabs ── */
        .sub-title {
            color: var(--azure-blue);
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            margin-top: 10px;
        }

        /* ── Metric detail rows inside view modal ── */
        .metric-group { margin-bottom: 22px; }
        .metric-group-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--azure-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--azure-blue-lighter);
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .metric-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 20px;
        }
        .metric-item { display: flex; flex-direction: column; gap: 2px; }
        .metric-label { font-size: 11px; color: #999; font-weight: 600; text-transform: uppercase; }
        .metric-value { font-size: 15px; font-weight: 600; color: #222; }
        .metric-value.big { font-size: 20px; color: var(--azure-blue-dark); }

        /* ── Modal ── */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 16px;
            width: 92%;
            max-width: 720px;
            max-height: 93vh;
            overflow-y: auto;
            box-shadow: 0 24px 50px rgba(0,0,0,0.3);
            animation: slideIn 0.28s ease;
        }
        .modal-content-xl { max-width: 960px; }
        @keyframes slideIn {
            from { transform: translateY(-40px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
        .modal-header {
            background: linear-gradient(135deg, var(--azure-blue), var(--azure-blue-light));
            color: white;
            padding: 18px 28px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 { margin: 0; font-size: 20px; font-weight: 700; }
        .close {
            font-size: 26px; font-weight: bold; cursor: pointer;
            color: white; opacity: 0.75; border: none; background: none;
            line-height: 1;
        }
        .close:hover { opacity: 1; }
        .modal-body { padding: 28px; }

        /* ── Form ── */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 13px;
            color: var(--azure-blue-dark);
        }
        .form-control {
            width: 100%;
            padding: 9px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.25s ease;
        }
        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(233,30,99,0.12);
        }
        .form-row { display: flex; gap: 16px; }
        .form-col { flex: 1; }
        textarea.form-control { resize: vertical; }

        .section-divider {
            font-size: 12px; font-weight: 700; color: var(--azure-blue);
            text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 1px solid var(--azure-blue-lighter);
            padding-bottom: 5px; margin: 22px 0 14px;
        }
        .section-divider.optional { color: #999; border-color: #eee; }

        /* ── Alert ── */
        .alert {
            padding: 14px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* ── No data ── */
        .no-data { text-align: center; padding: 40px 20px; color: #bbb; }
        .no-data i { font-size: 44px; display: block; margin-bottom: 10px; opacity: 0.4; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .search-box input { width: 100%; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .table { font-size: 12px; }
            .table td, .table th { padding: 9px 10px; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>

    <div class="row">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">

            <!-- ── Alert ── -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- ── Page Title ── -->
            <h1 class="section-title">
                <i class="fas fa-file-chart-line"></i>
                Reports Management
            </h1>

            <!-- ── Summary Stats ── -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-number"><?php echo $totalReports; ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon"><i class="fas fa-check-double"></i></div>
                    <div class="stat-number"><?php echo $approvedCount; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo $pendingCount; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card gray">
                    <div class="stat-icon"><i class="fas fa-pencil-alt"></i></div>
                    <div class="stat-number"><?php echo $draftCount; ?></div>
                    <div class="stat-label">Drafts</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number"><?php echo count($payrollReports); ?></div>
                    <div class="stat-label">Payroll Reports</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon"><i class="fas fa-star-half-alt"></i></div>
                    <div class="stat-number"><?php echo count($performanceReports); ?></div>
                    <div class="stat-label">Performance Reports</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?php echo count($attendanceReports) + count($leaveReports); ?></div>
                    <div class="stat-label">Attendance & Leave</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo count($empInfoReports); ?></div>
                    <div class="stat-label">Employee Info Reports</div>
                </div>
            </div>

            <!-- ── Tabs ── -->
            <div class="tabs">
                <button class="tab-button active" onclick="switchTab(event,'tab-all')">
                    <i class="fas fa-th-list"></i> All Reports
                    <span class="tab-count"><?php echo $totalReports; ?></span>
                </button>
                <button class="tab-button" onclick="switchTab(event,'tab-payroll')">
                    <i class="fas fa-money-bill-wave"></i> Payroll
                    <span class="tab-count"><?php echo count($payrollReports); ?></span>
                </button>
                <button class="tab-button" onclick="switchTab(event,'tab-performance')">
                    <i class="fas fa-star-half-alt"></i> Performance
                    <span class="tab-count"><?php echo count($performanceReports); ?></span>
                </button>
                <button class="tab-button" onclick="switchTab(event,'tab-attendance')">
                    <i class="fas fa-calendar-check"></i> Attendance
                    <span class="tab-count"><?php echo count($attendanceReports); ?></span>
                </button>
                <button class="tab-button" onclick="switchTab(event,'tab-leave')">
                    <i class="fas fa-umbrella-beach"></i> Leave
                    <span class="tab-count"><?php echo count($leaveReports); ?></span>
                </button>
                <button class="tab-button" onclick="switchTab(event,'tab-empinfo')">
                    <i class="fas fa-id-badge"></i> Employee Info
                    <span class="tab-count"><?php echo count($empInfoReports); ?></span>
                </button>
            </div>

            <!-- ══════════════════════════════════════════════
                 TAB 1 – ALL REPORTS
            ══════════════════════════════════════════════ -->
            <div id="tab-all" class="tab-content active">
                <div class="controls">
                    <div class="controls-left">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchAll" placeholder="Search reports...">
                        </div>
                        <select class="filter-select" id="filterTypeAll" onchange="filterTableCustom('tblAll','filterTypeAll',1)">
                            <option value="">All Types</option>
                            <option>Payroll Summary</option>
                            <option>Payroll Detail</option>
                            <option>Performance Evaluation Summary</option>
                            <option>Performance Competency Report</option>
                            <option>Attendance Report</option>
                            <option>Leave Request Summary</option>
                            <option>Leave Balance Report</option>
                            <option>Employee Information Report</option>
                        </select>
                        <select class="filter-select" id="filterStatusAll" onchange="filterTableCustom('tblAll','filterStatusAll',7)">
                            <option value="">All Statuses</option>
                            <option>Draft</option>
                            <option>Generated</option>
                            <option>Reviewed</option>
                            <option>Approved</option>
                            <option>Archived</option>
                        </select>
                    </div>
                    <div class="controls-right">
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Create Report
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table" id="tblAll">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Period</th>
                                <th>Department</th>
                                <th>Format</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($allReports)): ?>
                                <?php foreach ($allReports as $r): ?>
                                <tr>
                                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($r['report_code']); ?></code></td>
                                    <td><?php echo renderTypeBadge($r['report_type']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['report_title']); ?></strong>
                                        <?php if (!empty($r['description'])): ?>
                                            <div style="font-size:11px;color:#999;margin-top:2px;">
                                                <?php echo htmlspecialchars(substr($r['description'], 0, 60)) . (strlen($r['description']) > 60 ? '…' : ''); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space:nowrap;">
                                        <?php echo date('M d, Y', strtotime($r['report_period_start'])); ?><br>
                                        <span style="color:#aaa;font-size:11px;">to</span><br>
                                        <?php echo date('M d, Y', strtotime($r['report_period_end'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['department_name'] ?? 'All Depts'); ?></td>
                                    <td><?php echo renderFormatBadge($r['file_format']); ?></td>
                                    <td><?php echo renderStatusBadge($r['report_status']); ?></td>
                                    <td style="white-space:nowrap;font-size:12px;"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                    <td style="white-space:nowrap;">
                                        <button class="btn btn-info btn-sm" onclick='openViewModal(<?php echo json_encode($r); ?>)' title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-success btn-sm" onclick='openEditModal(<?php echo json_encode($r); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="openDeleteModal(<?php echo $r['report_id']; ?>, '<?php echo addslashes($r['report_code']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9"><div class="no-data"><i class="fas fa-folder-open"></i> No reports found</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════
                 TAB 2 – PAYROLL REPORTS
            ══════════════════════════════════════════════ -->
            <div id="tab-payroll" class="tab-content">
                <div class="controls">
                    <div class="controls-left">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchPayroll" placeholder="Search payroll reports...">
                        </div>
                    </div>
                    <div class="controls-right">
                        <button class="btn btn-primary" onclick="openAddModal('Payroll Summary')">
                            <i class="fas fa-plus"></i> New Payroll Report
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table" id="tblPayroll">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Period</th>
                                <th>Employees</th>
                                <th>Gross Pay</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payrollReports)): ?>
                                <?php foreach ($payrollReports as $r): ?>
                                <tr>
                                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($r['report_code']); ?></code></td>
                                    <td><?php echo renderTypeBadge($r['report_type']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['report_title']); ?></strong>
                                        <div style="font-size:11px;color:#999;"><?php echo htmlspecialchars($r['department_name'] ?? 'All Departments'); ?></div>
                                    </td>
                                    <td style="white-space:nowrap;font-size:12px;">
                                        <?php echo date('M d, Y', strtotime($r['report_period_start'])); ?> –
                                        <?php echo date('M d, Y', strtotime($r['report_period_end'])); ?>
                                    </td>
                                    <td style="text-align:center;"><?php echo $r['total_employees_included'] ?? '—'; ?></td>
                                    <td>
                                        <?php if ($r['total_gross_pay']): ?>
                                            <strong style="color:#0d6efd;">₱<?php echo number_format($r['total_gross_pay'], 2); ?></strong>
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            $totalDed = ($r['total_tax_deductions'] ?? 0)
                                                      + ($r['total_statutory_deductions'] ?? 0)
                                                      + ($r['total_other_deductions'] ?? 0);
                                        ?>
                                        <?php if ($totalDed > 0): ?>
                                            <span style="color:#dc3545;">₱<?php echo number_format($totalDed, 2); ?></span>
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['total_net_pay']): ?>
                                            <strong style="color:#28a745;">₱<?php echo number_format($r['total_net_pay'], 2); ?></strong>
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td><?php echo renderStatusBadge($r['report_status']); ?></td>
                                    <td style="white-space:nowrap;">
                                        <button class="btn btn-info btn-sm"    onclick='openViewModal(<?php echo json_encode($r); ?>)'><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-success btn-sm" onclick='openEditModal(<?php echo json_encode($r); ?>)'><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-danger btn-sm"  onclick="openDeleteModal(<?php echo $r['report_id']; ?>, '<?php echo addslashes($r['report_code']); ?>')"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10"><div class="no-data"><i class="fas fa-money-bill-wave"></i> No payroll reports found</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════
                 TAB 3 – PERFORMANCE REPORTS
            ══════════════════════════════════════════════ -->
            <div id="tab-performance" class="tab-content">
                <div class="controls">
                    <div class="controls-left">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchPerf" placeholder="Search performance reports...">
                        </div>
                    </div>
                    <div class="controls-right">
                        <button class="btn btn-primary" onclick="openAddModal('Performance Evaluation Summary')">
                            <i class="fas fa-plus"></i> New Performance Report
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table" id="tblPerf">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Cycle</th>
                                <th>Period</th>
                                <th>Avg Rating</th>
                                <th>Reviews</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($performanceReports)): ?>
                                <?php foreach ($performanceReports as $r): ?>
                                <tr>
                                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($r['report_code']); ?></code></td>
                                    <td><?php echo renderTypeBadge($r['report_type']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['report_title']); ?></strong>
                                        <div style="font-size:11px;color:#999;"><?php echo htmlspecialchars($r['department_name'] ?? 'All Departments'); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['cycle_name'])): ?>
                                            <span class="badge-pill badge-reviewed"><?php echo htmlspecialchars($r['cycle_name']); ?></span>
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td style="font-size:12px;white-space:nowrap;">
                                        <?php echo date('M d, Y', strtotime($r['report_period_start'])); ?> –
                                        <?php echo date('M d, Y', strtotime($r['report_period_end'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($r['average_overall_rating'] !== null): ?>
                                            <?php
                                                $avgR = (float)$r['average_overall_rating'];
                                                $starColor = $avgR >= 4 ? '#28a745' : ($avgR >= 3 ? '#ffc107' : '#dc3545');
                                            ?>
                                            <strong style="color:<?php echo $starColor; ?>; font-size:16px;">
                                                <?php echo number_format($avgR, 2); ?> <i class="fas fa-star" style="font-size:13px;"></i>
                                            </strong>
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($r['total_reviews_submitted'] !== null): ?>
                                            <span style="color:#0d6efd;"><?php echo $r['total_reviews_finalized']; ?></span>
                                            / <?php echo $r['total_reviews_submitted']; ?> finalized
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td><?php echo renderStatusBadge($r['report_status']); ?></td>
                                    <td style="white-space:nowrap;">
                                        <button class="btn btn-info btn-sm"    onclick='openViewModal(<?php echo json_encode($r); ?>)'><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-success btn-sm" onclick='openEditModal(<?php echo json_encode($r); ?>)'><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-danger btn-sm"  onclick="openDeleteModal(<?php echo $r['report_id']; ?>, '<?php echo addslashes($r['report_code']); ?>')"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9"><div class="no-data"><i class="fas fa-star-half-alt"></i> No performance reports found</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════
                 TAB 4 – ATTENDANCE REPORTS
            ══════════════════════════════════════════════ -->
            <div id="tab-attendance" class="tab-content">
                <div class="controls">
                    <div class="controls-left">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchAttend" placeholder="Search attendance reports...">
                        </div>
                    </div>
                    <div class="controls-right">
                        <button class="btn btn-primary" onclick="openAddModal('Attendance Report')">
                            <i class="fas fa-plus"></i> New Attendance Report
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table" id="tblAttend">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Period</th>
                                <th>Department</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>On Leave</th>
                                <th>Attendance Rate</th>
                                <th>Total OT Hrs</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attendanceReports)): ?>
                                <?php foreach ($attendanceReports as $r): ?>
                                <tr>
                                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($r['report_code']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($r['report_title']); ?></strong></td>
                                    <td style="font-size:12px;white-space:nowrap;">
                                        <?php echo date('M d, Y', strtotime($r['report_period_start'])); ?> –
                                        <?php echo date('M d, Y', strtotime($r['report_period_end'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['department_name'] ?? 'All Depts'); ?></td>
                                    <td><span style="color:#28a745;font-weight:700;"><?php echo $r['total_present'] ?? '—'; ?></span></td>
                                    <td><span style="color:#dc3545;font-weight:700;"><?php echo $r['total_absent'] ?? '—'; ?></span></td>
                                    <td><span style="color:#ffc107;font-weight:700;"><?php echo $r['total_late'] ?? '—'; ?></span></td>
                                    <td><?php echo $r['total_on_leave'] ?? '—'; ?></td>
                                    <td>
                                        <?php if ($r['attendance_rate_pct'] !== null): ?>
                                            <?php $rate = (float)$r['attendance_rate_pct']; ?>
                                            <div style="display:flex;align-items:center;gap:6px;">
                                                <div style="flex:1;background:#eee;border-radius:10px;height:6px;overflow:hidden;">
                                                    <div style="width:<?php echo min($rate,100); ?>%;height:100%;background:<?php echo $rate>=90?'#28a745':($rate>=75?'#ffc107':'#dc3545'); ?>;border-radius:10px;"></div>
                                                </div>
                                                <span style="font-size:12px;font-weight:700;color:<?php echo $rate>=90?'#28a745':($rate>=75?'#e0a800':'#dc3545'); ?>">
                                                    <?php echo number_format($rate, 1); ?>%
                                                </span>
                                            </div>
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td><?php echo $r['total_overtime_hours'] !== null ? number_format($r['total_overtime_hours'], 1).' hrs' : '—'; ?></td>
                                    <td><?php echo renderStatusBadge($r['report_status']); ?></td>
                                    <td style="white-space:nowrap;">
                                        <button class="btn btn-info btn-sm"    onclick='openViewModal(<?php echo json_encode($r); ?>)'><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-success btn-sm" onclick='openEditModal(<?php echo json_encode($r); ?>)'><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-danger btn-sm"  onclick="openDeleteModal(<?php echo $r['report_id']; ?>, '<?php echo addslashes($r['report_code']); ?>')"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="12"><div class="no-data"><i class="fas fa-calendar-check"></i> No attendance reports found</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════
                 TAB 5 – LEAVE REPORTS
            ══════════════════════════════════════════════ -->
            <div id="tab-leave" class="tab-content">
                <div class="controls">
                    <div class="controls-left">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchLeave" placeholder="Search leave reports...">
                        </div>
                    </div>
                    <div class="controls-right">
                        <button class="btn btn-primary" onclick="openAddModal('Leave Request Summary')">
                            <i class="fas fa-plus"></i> New Leave Report
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table" id="tblLeave">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Title</th>
                                <th>Period</th>
                                <th>Total Requests</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Pending</th>
                                <th>Days Taken</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($leaveReports)): ?>
                                <?php foreach ($leaveReports as $r): ?>
                                <tr>
                                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($r['report_code']); ?></code></td>
                                    <td><?php echo renderTypeBadge($r['report_type']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['report_title']); ?></strong>
                                        <div style="font-size:11px;color:#999;"><?php echo htmlspecialchars($r['department_name'] ?? 'All Departments'); ?></div>
                                    </td>
                                    <td style="font-size:12px;white-space:nowrap;">
                                        <?php echo date('M d, Y', strtotime($r['report_period_start'])); ?> –
                                        <?php echo date('M d, Y', strtotime($r['report_period_end'])); ?>
                                    </td>
                                    <td style="text-align:center;"><?php echo $r['total_leave_requests'] ?? '—'; ?></td>
                                    <td><span style="color:#28a745;font-weight:700;"><?php echo $r['approved_leave_requests'] ?? '—'; ?></span></td>
                                    <td><span style="color:#dc3545;font-weight:700;"><?php echo $r['rejected_leave_requests'] ?? '—'; ?></span></td>
                                    <td><span style="color:#ffc107;font-weight:700;"><?php echo $r['pending_leave_requests'] ?? '—'; ?></span></td>
                                    <td>
                                        <?php if ($r['total_leave_days_taken'] !== null): ?>
                                            <strong><?php echo number_format($r['total_leave_days_taken'], 1); ?> days</strong>
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td><?php echo renderStatusBadge($r['report_status']); ?></td>
                                    <td style="white-space:nowrap;">
                                        <button class="btn btn-info btn-sm"    onclick='openViewModal(<?php echo json_encode($r); ?>)'><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-success btn-sm" onclick='openEditModal(<?php echo json_encode($r); ?>)'><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-danger btn-sm"  onclick="openDeleteModal(<?php echo $r['report_id']; ?>, '<?php echo addslashes($r['report_code']); ?>')"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="11"><div class="no-data"><i class="fas fa-umbrella-beach"></i> No leave reports found</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════
                 TAB 6 – EMPLOYEE INFO REPORTS
            ══════════════════════════════════════════════ -->
            <div id="tab-empinfo" class="tab-content">
                <div class="controls">
                    <div class="controls-left">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchEmpInfo" placeholder="Search employee info reports...">
                        </div>
                    </div>
                    <div class="controls-right">
                        <button class="btn btn-primary" onclick="openAddModal('Employee Information Report')">
                            <i class="fas fa-plus"></i> New Employee Info Report
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table" id="tblEmpInfo">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Period</th>
                                <th>Employees Included</th>
                                <th>Total Payroll Covered</th>
                                <th>Format</th>
                                <th>Status</th>
                                <th>Generated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($empInfoReports)): ?>
                                <?php foreach ($empInfoReports as $r): ?>
                                <tr>
                                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($r['report_code']); ?></code></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['report_title']); ?></strong>
                                        <?php if (!empty($r['description'])): ?>
                                            <div style="font-size:11px;color:#999;"><?php echo htmlspecialchars(substr($r['description'], 0, 70)); ?><?php if (strlen($r['description']) > 70) echo '…'; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px;white-space:nowrap;">
                                        <?php echo date('M d, Y', strtotime($r['report_period_start'])); ?> –
                                        <?php echo date('M d, Y', strtotime($r['report_period_end'])); ?>
                                    </td>
                                    <td style="text-align:center;font-weight:700;"><?php echo $r['total_employees_included'] ?? '—'; ?></td>
                                    <td>
                                        <?php if ($r['total_gross_pay']): ?>
                                            <strong style="color:#0d6efd;">₱<?php echo number_format($r['total_gross_pay'], 2); ?></strong>
                                        <?php else: echo '—'; endif; ?>
                                    </td>
                                    <td><?php echo renderFormatBadge($r['file_format']); ?></td>
                                    <td><?php echo renderStatusBadge($r['report_status']); ?></td>
                                    <td style="font-size:12px;"><?php echo date('M d, Y', strtotime($r['generated_at'])); ?></td>
                                    <td style="white-space:nowrap;">
                                        <button class="btn btn-info btn-sm"    onclick='openViewModal(<?php echo json_encode($r); ?>)'><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-success btn-sm" onclick='openEditModal(<?php echo json_encode($r); ?>)'><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-danger btn-sm"  onclick="openDeleteModal(<?php echo $r['report_id']; ?>, '<?php echo addslashes($r['report_code']); ?>')"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9"><div class="no-data"><i class="fas fa-id-badge"></i> No employee info reports found</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /.main-content -->
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: ADD / EDIT REPORT
══════════════════════════════════════════════════════════ -->
<div id="reportModal" class="modal">
    <div class="modal-content modal-content-xl">
        <div class="modal-header">
            <h2 id="reportModalTitle"><i class="fas fa-file-alt"></i> Create Report</h2>
            <button class="close" onclick="closeModal('reportModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="reportForm" method="POST">
                <input type="hidden" name="action"    id="formAction"   value="add">
                <input type="hidden" name="report_id" id="formReportId" value="">

                <!-- ── Basic Info ── -->
                <div class="section-divider">Report Identity</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Report Code <span style="color:red;">*</span></label>
                            <input type="text" class="form-control" name="report_code" id="fCode" required placeholder="e.g. RPT-PAY-2025-01">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Report Type <span style="color:red;">*</span></label>
                            <select class="form-control" name="report_type" id="fType" required onchange="toggleSections()">
                                <option value="">-- Select Type --</option>
                                <option>Payroll Summary</option>
                                <option>Payroll Detail</option>
                                <option>Performance Evaluation Summary</option>
                                <option>Performance Competency Report</option>
                                <option>Attendance Report</option>
                                <option>Leave Request Summary</option>
                                <option>Leave Balance Report</option>
                                <option>Employee Information Report</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Report Title <span style="color:red;">*</span></label>
                    <input type="text" class="form-control" name="report_title" id="fTitle" required placeholder="Enter a descriptive report title">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="description" id="fDescription" rows="2" placeholder="Optional description..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Period Start <span style="color:red;">*</span></label>
                            <input type="date" class="form-control" name="report_period_start" id="fPeriodStart" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Period End <span style="color:red;">*</span></label>
                            <input type="date" class="form-control" name="report_period_end" id="fPeriodEnd" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Department <small style="color:#999;">(leave blank for all)</small></label>
                            <select class="form-control" name="department_id" id="fDept">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['department_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Specific Employee <small style="color:#999;">(optional)</small></label>
                            <select class="form-control" name="employee_id" id="fEmployee">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?php echo $e['employee_id']; ?>"><?php echo htmlspecialchars($e['employee_number'].' – '.$e['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Report Status <span style="color:red;">*</span></label>
                            <select class="form-control" name="report_status" id="fStatus" required>
                                <option value="Draft">Draft</option>
                                <option value="Generated">Generated</option>
                                <option value="Reviewed">Reviewed</option>
                                <option value="Approved">Approved</option>
                                <option value="Archived">Archived</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>File Format</label>
                            <select class="form-control" name="file_format" id="fFileFormat">
                                <option value="N/A">N/A</option>
                                <option value="PDF">PDF</option>
                                <option value="Excel">Excel</option>
                                <option value="CSV">CSV</option>
                                <option value="HTML">HTML</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>File Path <small style="color:#999;">(generated file location)</small></label>
                    <input type="text" class="form-control" name="file_path" id="fFilePath" placeholder="e.g. /reports/payroll/RPT-PAY-2025-01.pdf">
                </div>

                <!-- ── Payroll Section ── -->
                <div id="secPayroll" style="display:none;">
                    <div class="section-divider optional">Payroll Metrics</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Employees Included</label>
                                <input type="number" class="form-control" name="total_employees_included" id="fTotalEmp" min="0" placeholder="e.g. 15">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label>Payroll Cycle</label>
                                <select class="form-control" name="payroll_cycle_id" id="fPayrollCycle">
                                    <option value="">None</option>
                                    <?php foreach ($payrollCycles as $pc): ?>
                                        <option value="<?php echo $pc['payroll_cycle_id']; ?>"><?php echo htmlspecialchars($pc['cycle_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group"><label>Total Gross Pay (₱)</label><input type="number" class="form-control" name="total_gross_pay" id="fGross" step="0.01" placeholder="0.00"></div>
                        </div>
                        <div class="form-col">
                            <div class="form-group"><label>Tax Deductions (₱)</label><input type="number" class="form-control" name="total_tax_deductions" id="fTaxDed" step="0.01" placeholder="0.00"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group"><label>Statutory Deductions (₱)</label><input type="number" class="form-control" name="total_statutory_deductions" id="fStatDed" step="0.01" placeholder="0.00"></div>
                        </div>
                        <div class="form-col">
                            <div class="form-group"><label>Other Deductions (₱)</label><input type="number" class="form-control" name="total_other_deductions" id="fOtherDed" step="0.01" placeholder="0.00"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Total Net Pay (₱)</label>
                        <input type="number" class="form-control" name="total_net_pay" id="fNetPay" step="0.01" placeholder="0.00">
                    </div>
                </div>

                <!-- ── Performance Section ── -->
                <div id="secPerformance" style="display:none;">
                    <div class="section-divider optional">Performance Metrics</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Performance Cycle</label>
                                <select class="form-control" name="cycle_id" id="fCycleId">
                                    <option value="">None</option>
                                    <?php foreach ($perfCycles as $pc): ?>
                                        <option value="<?php echo $pc['cycle_id']; ?>"><?php echo htmlspecialchars($pc['cycle_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group"><label>Average Overall Rating (0–5)</label><input type="number" class="form-control" name="average_overall_rating" id="fAvgRating" step="0.01" min="0" max="5" placeholder="e.g. 3.75"></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col"><div class="form-group"><label>Reviews Submitted</label><input type="number" class="form-control" name="total_reviews_submitted" id="fRevSub" min="0" placeholder="0"></div></div>
                        <div class="form-col"><div class="form-group"><label>Reviews Finalized</label><input type="number" class="form-control" name="total_reviews_finalized" id="fRevFin" min="0" placeholder="0"></div></div>
                    </div>
                    <div class="form-row">
                        <div class="form-col"><div class="form-group"><label>Highest Rating</label><input type="number" class="form-control" name="highest_rating" id="fHighRating" step="0.01" min="0" max="5" placeholder="0.00"></div></div>
                        <div class="form-col"><div class="form-group"><label>Lowest Rating</label><input type="number" class="form-control" name="lowest_rating" id="fLowRating" step="0.01" min="0" max="5" placeholder="0.00"></div></div>
                    </div>
                </div>

                <!-- ── Attendance Section ── -->
                <div id="secAttendance" style="display:none;">
                    <div class="section-divider optional">Attendance Metrics</div>
                    <div class="form-row">
                        <div class="form-col"><div class="form-group"><label>Total Present</label><input type="number" class="form-control" name="total_present" id="fPresent" min="0" placeholder="0"></div></div>
                        <div class="form-col"><div class="form-group"><label>Total Absent</label><input type="number" class="form-control" name="total_absent" id="fAbsent" min="0" placeholder="0"></div></div>
                    </div>
                    <div class="form-row">
                        <div class="form-col"><div class="form-group"><label>Total Late</label><input type="number" class="form-control" name="total_late" id="fLate" min="0" placeholder="0"></div></div>
                        <div class="form-col"><div class="form-group"><label>Total On Leave</label><input type="number" class="form-control" name="total_on_leave" id="fOnLeave" min="0" placeholder="0"></div></div>
                    </div>
                    <div class="form-row">
                        <div class="form-col"><div class="form-group"><label>Total Working Hours</label><input type="number" class="form-control" name="total_working_hours" id="fWorkHrs" step="0.01" placeholder="0.00"></div></div>
                        <div class="form-col"><div class="form-group"><label>Total Overtime Hours</label><input type="number" class="form-control" name="total_overtime_hours" id="fOTHrs" step="0.01" placeholder="0.00"></div></div>
                    </div>
                    <div class="form-group"><label>Attendance Rate (%)</label><input type="number" class="form-control" name="attendance_rate_pct" id="fAttRate" step="0.01" min="0" max="100" placeholder="e.g. 95.30"></div>
                </div>

                <!-- ── Leave Section ── -->
                <div id="secLeave" style="display:none;">
                    <div class="section-divider optional">Leave Metrics</div>
                    <div class="form-row">
                        <div class="form-col"><div class="form-group"><label>Total Leave Requests</label><input type="number" class="form-control" name="total_leave_requests" id="fTotalLv" min="0" placeholder="0"></div></div>
                        <div class="form-col"><div class="form-group"><label>Approved Requests</label><input type="number" class="form-control" name="approved_leave_requests" id="fApprovedLv" min="0" placeholder="0"></div></div>
                    </div>
                    <div class="form-row">
                        <div class="form-col"><div class="form-group"><label>Rejected Requests</label><input type="number" class="form-control" name="rejected_leave_requests" id="fRejectedLv" min="0" placeholder="0"></div></div>
                        <div class="form-col"><div class="form-group"><label>Pending Requests</label><input type="number" class="form-control" name="pending_leave_requests" id="fPendingLv" min="0" placeholder="0"></div></div>
                    </div>
                    <div class="form-row">
                        <div class="form-col"><div class="form-group"><label>Total Leave Days Taken</label><input type="number" class="form-control" name="total_leave_days_taken" id="fLvDays" step="0.5" min="0" placeholder="0.0"></div></div>
                        <div class="form-col"></div>
                    </div>
                    <div class="form-group">
                        <label>Leave Type Breakdown <small style="color:#999;">(JSON format)</small></label>
                        <textarea class="form-control" name="leave_type_breakdown" id="fLvBreakdown" rows="3" placeholder='{"Vacation Leave": 10, "Sick Leave": 5}'></textarea>
                    </div>
                </div>

                <!-- ── Notes ── -->
                <div class="section-divider optional">Notes</div>
                <div class="form-group">
                    <textarea class="form-control" name="notes" id="fNotes" rows="2" placeholder="Additional notes..."></textarea>
                </div>

                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">
                        <i class="fas fa-save"></i> Save Report
                    </button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('reportModal')" style="flex:1;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: VIEW REPORT DETAILS
══════════════════════════════════════════════════════════ -->
<div id="viewModal" class="modal">
    <div class="modal-content modal-content-xl">
        <div class="modal-header">
            <h2><i class="fas fa-file-magnifying-glass"></i> <span id="viewModalTitle">Report Details</span></h2>
            <button class="close" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div class="modal-body" id="viewModalBody">
            <!-- dynamically filled -->
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: DELETE CONFIRMATION
══════════════════════════════════════════════════════════ -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width:420px;">
        <div class="modal-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h2>
            <button class="close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:15px;">Are you sure you want to delete report <strong id="deleteReportCode" style="color:var(--azure-blue);"></strong>?</p>
            <p style="color:#dc3545;font-size:13px;"><i class="fas fa-exclamation-circle"></i> This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="action"    value="delete">
                <input type="hidden" name="report_id" id="deleteReportId">
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <button type="submit" class="btn btn-danger" style="flex:1;"><i class="fas fa-trash"></i> Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')" style="flex:1;"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PHP HELPERS (rendered inline)
══════════════════════════════════════════════════════════ -->
<?php
function renderStatusBadge(string $status): string {
    $map = [
        'Draft'     => 'badge-draft',
        'Generated' => 'badge-generated',
        'Reviewed'  => 'badge-reviewed',
        'Approved'  => 'badge-approved',
        'Archived'  => 'badge-archived',
    ];
    $cls = $map[$status] ?? 'badge-draft';
    return "<span class='badge-pill $cls'>$status</span>";
}
function renderFormatBadge(string $fmt): string {
    $map = ['PDF'=>'badge-pdf','Excel'=>'badge-excel','CSV'=>'badge-csv','HTML'=>'badge-html','N/A'=>'badge-na'];
    $cls = $map[$fmt] ?? 'badge-na';
    return "<span class='badge-pill $cls'>$fmt</span>";
}
function renderTypeBadge(string $type): string {
    $cls = 'badge-empinfo';
    if (strpos($type,'Payroll') !== false)     $cls = 'badge-payroll';
    if (strpos($type,'Performance') !== false) $cls = 'badge-perf';
    if (strpos($type,'Attendance') !== false)  $cls = 'badge-attend';
    if (strpos($type,'Leave') !== false)       $cls = 'badge-leave';
    return "<span class='badge-pill $cls'>" . htmlspecialchars($type) . "</span>";
}
?>

<script>
// ── PHP data passed to JS ──────────────────────────────────────────────
const departments  = <?= json_encode($departments) ?>;
const employees    = <?= json_encode($employees) ?>;
const perfCycles   = <?= json_encode($perfCycles) ?>;
const payrollCycles= <?= json_encode($payrollCycles) ?>;

// ── Tab switching ──────────────────────────────────────────────────────
function switchTab(event, tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-button').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
}

// ── Modal open/close ───────────────────────────────────────────────────
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

window.addEventListener('click', function(e) {
    ['reportModal','viewModal','deleteModal'].forEach(id => {
        const m = document.getElementById(id);
        if (e.target === m) m.style.display = 'none';
    });
});

// ── Open Add modal ─────────────────────────────────────────────────────
function openAddModal(presetType = '') {
    document.getElementById('reportForm').reset();
    document.getElementById('formAction').value   = 'add';
    document.getElementById('formReportId').value = '';
    document.getElementById('reportModalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Create New Report';

    if (presetType) {
        document.getElementById('fType').value = presetType;
    }
    toggleSections();
    document.getElementById('reportModal').style.display = 'block';
}

// ── Open Edit modal ────────────────────────────────────────────────────
function openEditModal(r) {
    document.getElementById('formAction').value   = 'update';
    document.getElementById('formReportId').value = r.report_id;
    document.getElementById('reportModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Report';

    // Basic
    setVal('fCode',          r.report_code);
    setVal('fType',          r.report_type);
    setVal('fTitle',         r.report_title);
    setVal('fDescription',   r.description     || '');
    setVal('fPeriodStart',   r.report_period_start);
    setVal('fPeriodEnd',     r.report_period_end);
    setVal('fDept',          r.department_id   || '');
    setVal('fEmployee',      r.employee_id     || '');
    setVal('fStatus',        r.report_status);
    setVal('fFileFormat',    r.file_format     || 'N/A');
    setVal('fFilePath',      r.file_path       || '');
    setVal('fNotes',         r.notes           || '');

    // Payroll
    setVal('fTotalEmp',      r.total_employees_included || '');
    setVal('fPayrollCycle',  r.payroll_cycle_id || '');
    setVal('fGross',         r.total_gross_pay  || '');
    setVal('fTaxDed',        r.total_tax_deductions || '');
    setVal('fStatDed',       r.total_statutory_deductions || '');
    setVal('fOtherDed',      r.total_other_deductions || '');
    setVal('fNetPay',        r.total_net_pay    || '');

    // Performance
    setVal('fCycleId',       r.cycle_id         || '');
    setVal('fAvgRating',     r.average_overall_rating || '');
    setVal('fRevSub',        r.total_reviews_submitted || '');
    setVal('fRevFin',        r.total_reviews_finalized || '');
    setVal('fHighRating',    r.highest_rating   || '');
    setVal('fLowRating',     r.lowest_rating    || '');

    // Attendance
    setVal('fPresent',       r.total_present    || '');
    setVal('fAbsent',        r.total_absent     || '');
    setVal('fLate',          r.total_late       || '');
    setVal('fOnLeave',       r.total_on_leave   || '');
    setVal('fWorkHrs',       r.total_working_hours || '');
    setVal('fOTHrs',         r.total_overtime_hours || '');
    setVal('fAttRate',       r.attendance_rate_pct || '');

    // Leave
    setVal('fTotalLv',       r.total_leave_requests || '');
    setVal('fApprovedLv',    r.approved_leave_requests || '');
    setVal('fRejectedLv',    r.rejected_leave_requests || '');
    setVal('fPendingLv',     r.pending_leave_requests || '');
    setVal('fLvDays',        r.total_leave_days_taken || '');
    setVal('fLvBreakdown',   r.leave_type_breakdown   || '');

    toggleSections();
    document.getElementById('reportModal').style.display = 'block';
}

function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}

// ── Toggle conditional sections based on type ─────────────────────────
function toggleSections() {
    const type = document.getElementById('fType').value;
    const isPayroll  = type.startsWith('Payroll') || type === 'Employee Information Report';
    const isPerf     = type.startsWith('Performance');
    const isAttend   = type === 'Attendance Report';
    const isLeave    = type.includes('Leave');

    document.getElementById('secPayroll').style.display    = isPayroll  ? 'block' : 'none';
    document.getElementById('secPerformance').style.display= isPerf     ? 'block' : 'none';
    document.getElementById('secAttendance').style.display = isAttend   ? 'block' : 'none';
    document.getElementById('secLeave').style.display      = isLeave    ? 'block' : 'none';
}

// ── Open View modal ────────────────────────────────────────────────────
function openViewModal(r) {
    document.getElementById('viewModalTitle').textContent = r.report_code + ' – ' + r.report_title;

    const fmt = (v) => v !== null && v !== undefined && v !== '' ? v : '—';
    const money = (v) => v ? '₱' + parseFloat(v).toLocaleString('en-PH', {minimumFractionDigits:2}) : '—';
    const statusHtml = renderStatusBadgeJS(r.report_status);
    const typeHtml   = renderTypeBadgeJS(r.report_type);

    let payrollHtml = '', perfHtml = '', attendHtml = '', leaveHtml = '';

    if (r.report_type.startsWith('Payroll') || r.report_type === 'Employee Information Report') {
        const totalDed = (parseFloat(r.total_tax_deductions)||0)
                       + (parseFloat(r.total_statutory_deductions)||0)
                       + (parseFloat(r.total_other_deductions)||0);
        payrollHtml = `
            <div class="metric-group">
                <div class="metric-group-title"><i class="fas fa-money-bill-wave"></i> Payroll Metrics</div>
                <div class="metric-grid">
                    <div class="metric-item"><div class="metric-label">Employees Included</div><div class="metric-value big">${fmt(r.total_employees_included)}</div></div>
                    <div class="metric-item"><div class="metric-label">Gross Pay</div><div class="metric-value big" style="color:#0d6efd;">${money(r.total_gross_pay)}</div></div>
                    <div class="metric-item"><div class="metric-label">Tax Deductions</div><div class="metric-value" style="color:#dc3545;">${money(r.total_tax_deductions)}</div></div>
                    <div class="metric-item"><div class="metric-label">Statutory Deductions</div><div class="metric-value" style="color:#dc3545;">${money(r.total_statutory_deductions)}</div></div>
                    <div class="metric-item"><div class="metric-label">Other Deductions</div><div class="metric-value" style="color:#dc3545;">${money(r.total_other_deductions)}</div></div>
                    <div class="metric-item"><div class="metric-label">Total Net Pay</div><div class="metric-value big" style="color:#28a745;">${money(r.total_net_pay)}</div></div>
                </div>
            </div>`;
    }

    if (r.report_type.startsWith('Performance')) {
        const avgR = r.average_overall_rating ? parseFloat(r.average_overall_rating).toFixed(2) : '—';
        const ratingColor = r.average_overall_rating >= 4 ? '#28a745' : (r.average_overall_rating >= 3 ? '#e0a800' : '#dc3545');
        perfHtml = `
            <div class="metric-group">
                <div class="metric-group-title"><i class="fas fa-star-half-alt"></i> Performance Metrics</div>
                <div class="metric-grid">
                    <div class="metric-item"><div class="metric-label">Average Rating</div><div class="metric-value big" style="color:${ratingColor};">${avgR} ★</div></div>
                    <div class="metric-item"><div class="metric-label">Reviews Submitted</div><div class="metric-value big">${fmt(r.total_reviews_submitted)}</div></div>
                    <div class="metric-item"><div class="metric-label">Reviews Finalized</div><div class="metric-value">${fmt(r.total_reviews_finalized)}</div></div>
                    <div class="metric-item"><div class="metric-label">Highest / Lowest Rating</div><div class="metric-value">${fmt(r.highest_rating)} / ${fmt(r.lowest_rating)}</div></div>
                </div>
            </div>`;
    }

    if (r.report_type === 'Attendance Report') {
        const rate = r.attendance_rate_pct ? parseFloat(r.attendance_rate_pct) : 0;
        const barColor = rate >= 90 ? '#28a745' : (rate >= 75 ? '#ffc107' : '#dc3545');
        attendHtml = `
            <div class="metric-group">
                <div class="metric-group-title"><i class="fas fa-calendar-check"></i> Attendance Metrics</div>
                <div class="metric-grid">
                    <div class="metric-item"><div class="metric-label">Present</div><div class="metric-value big" style="color:#28a745;">${fmt(r.total_present)}</div></div>
                    <div class="metric-item"><div class="metric-label">Absent</div><div class="metric-value big" style="color:#dc3545;">${fmt(r.total_absent)}</div></div>
                    <div class="metric-item"><div class="metric-label">Late</div><div class="metric-value" style="color:#e0a800;">${fmt(r.total_late)}</div></div>
                    <div class="metric-item"><div class="metric-label">On Leave</div><div class="metric-value">${fmt(r.total_on_leave)}</div></div>
                    <div class="metric-item"><div class="metric-label">Working Hours</div><div class="metric-value">${r.total_working_hours ? r.total_working_hours+' hrs' : '—'}</div></div>
                    <div class="metric-item"><div class="metric-label">Overtime Hours</div><div class="metric-value">${r.total_overtime_hours ? r.total_overtime_hours+' hrs' : '—'}</div></div>
                </div>
                ${r.attendance_rate_pct !== null ? `
                <div style="margin-top:14px;">
                    <div class="metric-label" style="margin-bottom:6px;">Attendance Rate</div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="flex:1;background:#eee;border-radius:10px;height:10px;overflow:hidden;">
                            <div style="width:${Math.min(rate,100)}%;height:100%;background:${barColor};border-radius:10px;transition:width 0.6s ease;"></div>
                        </div>
                        <strong style="color:${barColor};font-size:18px;">${rate.toFixed(1)}%</strong>
                    </div>
                </div>` : ''}
            </div>`;
    }

    if (r.report_type.includes('Leave')) {
        let breakdownHtml = '';
        if (r.leave_type_breakdown) {
            try {
                const bd = typeof r.leave_type_breakdown === 'string' ? JSON.parse(r.leave_type_breakdown) : r.leave_type_breakdown;
                breakdownHtml = '<div style="margin-top:12px;"><div class="metric-label" style="margin-bottom:8px;">Leave Type Breakdown</div><div style="display:flex;flex-wrap:wrap;gap:8px;">';
                for (const [ltype, count] of Object.entries(bd)) {
                    if (typeof count === 'object') {
                        breakdownHtml += `<span style="background:#f0f0f0;padding:5px 12px;border-radius:20px;font-size:13px;"><strong>${ltype}</strong>: taken ${count.total_taken||0} / ${count.total_allocated||0}</span>`;
                    } else {
                        breakdownHtml += `<span style="background:#f0f0f0;padding:5px 12px;border-radius:20px;font-size:13px;"><strong>${ltype}</strong>: ${count} days</span>`;
                    }
                }
                breakdownHtml += '</div></div>';
            } catch(e) { breakdownHtml = ''; }
        }
        leaveHtml = `
            <div class="metric-group">
                <div class="metric-group-title"><i class="fas fa-umbrella-beach"></i> Leave Metrics</div>
                <div class="metric-grid">
                    <div class="metric-item"><div class="metric-label">Total Requests</div><div class="metric-value big">${fmt(r.total_leave_requests)}</div></div>
                    <div class="metric-item"><div class="metric-label">Approved</div><div class="metric-value big" style="color:#28a745;">${fmt(r.approved_leave_requests)}</div></div>
                    <div class="metric-item"><div class="metric-label">Rejected</div><div class="metric-value" style="color:#dc3545;">${fmt(r.rejected_leave_requests)}</div></div>
                    <div class="metric-item"><div class="metric-label">Pending</div><div class="metric-value" style="color:#e0a800;">${fmt(r.pending_leave_requests)}</div></div>
                    <div class="metric-item"><div class="metric-label">Days Taken</div><div class="metric-value big">${r.total_leave_days_taken ? r.total_leave_days_taken+' days' : '—'}</div></div>
                </div>
                ${breakdownHtml}
            </div>`;
    }

    const body = `
        <div class="metric-group">
            <div class="metric-grid">
                <div class="metric-item"><div class="metric-label">Report Code</div><div class="metric-value"><code>${r.report_code}</code></div></div>
                <div class="metric-item"><div class="metric-label">Type</div><div class="metric-value">${typeHtml}</div></div>
                <div class="metric-item"><div class="metric-label">Period</div><div class="metric-value">${formatDate(r.report_period_start)} – ${formatDate(r.report_period_end)}</div></div>
                <div class="metric-item"><div class="metric-label">Status</div><div class="metric-value">${statusHtml}</div></div>
                <div class="metric-item"><div class="metric-label">Department</div><div class="metric-value">${fmt(r.department_name || 'All Departments')}</div></div>
                <div class="metric-item"><div class="metric-label">File Format</div><div class="metric-value">${renderFormatBadgeJS(r.file_format)}</div></div>
                ${r.file_path ? `<div class="metric-item" style="grid-column:1/-1;"><div class="metric-label">File Path</div><div class="metric-value" style="font-size:12px;word-break:break-all;"><code>${r.file_path}</code></div></div>` : ''}
                ${r.description ? `<div class="metric-item" style="grid-column:1/-1;"><div class="metric-label">Description</div><div class="metric-value" style="font-size:14px;font-weight:normal;">${r.description}</div></div>` : ''}
            </div>
        </div>
        ${payrollHtml}${perfHtml}${attendHtml}${leaveHtml}
        ${r.notes ? `<div class="metric-group"><div class="metric-group-title"><i class="fas fa-sticky-note"></i> Notes</div><div style="background:#fffde7;border-left:3px solid #ffc107;padding:12px 14px;border-radius:6px;font-size:14px;">${r.notes}</div></div>` : ''}
        <div style="display:flex;gap:10px;margin-top:20px;">
            <button class="btn btn-success btn-sm" onclick='openEditModal(${JSON.stringify(r)}); closeModal("viewModal");'>
                <i class="fas fa-edit"></i> Edit This Report
            </button>
            <button class="btn btn-secondary btn-sm" onclick='closeModal("viewModal")'>
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    `;

    document.getElementById('viewModalBody').innerHTML = body;
    document.getElementById('viewModal').style.display = 'block';
}

// ── Open Delete modal ──────────────────────────────────────────────────
function openDeleteModal(id, code) {
    document.getElementById('deleteReportId').value       = id;
    document.getElementById('deleteReportCode').textContent = code;
    document.getElementById('deleteModal').style.display  = 'block';
}

// ── Search / filter helpers ────────────────────────────────────────────
function wireSearch(inputId, tableId) {
    document.getElementById(inputId).addEventListener('input', function() {
        filterTable(tableId, this.value);
    });
}
function filterTable(tableId, val) {
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    const q = val.toLowerCase();
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
function filterTableCustom(tableId, selectId, colIdx) {
    const val = document.getElementById(selectId).value.toLowerCase();
    const searchInputId = { tblAll: 'searchAll' }[tableId];
    const searchVal = searchInputId ? document.getElementById(searchInputId).value.toLowerCase() : '';

    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
        const cells = row.getElementsByTagName('td');
        const matchesFilter = !val || (cells[colIdx] && cells[colIdx].textContent.toLowerCase().includes(val));
        const matchesSearch = !searchVal || row.textContent.toLowerCase().includes(searchVal);
        row.style.display = (matchesFilter && matchesSearch) ? '' : 'none';
    });
}

wireSearch('searchAll',     'tblAll');
wireSearch('searchPayroll', 'tblPayroll');
wireSearch('searchPerf',    'tblPerf');
wireSearch('searchAttend',  'tblAttend');
wireSearch('searchLeave',   'tblLeave');
wireSearch('searchEmpInfo', 'tblEmpInfo');

// Also re-apply text filter when dropdowns change
document.getElementById('searchAll').addEventListener('input', function() {
    filterTableCustom('tblAll','filterTypeAll',1);
    filterTableCustom('tblAll','filterStatusAll',7);
    filterTable('tblAll', this.value);
});

// ── JS badge helpers (mirroring PHP) ──────────────────────────────────
function renderStatusBadgeJS(status) {
    const map = { Draft:'badge-draft', Generated:'badge-generated', Reviewed:'badge-reviewed', Approved:'badge-approved', Archived:'badge-archived' };
    const cls = map[status] || 'badge-draft';
    return `<span class="badge-pill ${cls}">${status}</span>`;
}
function renderTypeBadgeJS(type) {
    let cls = 'badge-empinfo';
    if (type && type.startsWith('Payroll'))      cls = 'badge-payroll';
    if (type && type.startsWith('Performance'))  cls = 'badge-perf';
    if (type && type === 'Attendance Report')    cls = 'badge-attend';
    if (type && type.includes('Leave'))          cls = 'badge-leave';
    return `<span class="badge-pill ${cls}">${type}</span>`;
}
function renderFormatBadgeJS(fmt) {
    const map = { PDF:'badge-pdf', Excel:'badge-excel', CSV:'badge-csv', HTML:'badge-html', 'N/A':'badge-na' };
    const cls = map[fmt] || 'badge-na';
    return `<span class="badge-pill ${cls}">${fmt||'N/A'}</span>`;
}
function formatDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    return dt.toLocaleDateString('en-PH', { month:'short', day:'2-digit', year:'numeric' });
}

// ── Auto-hide alerts ───────────────────────────────────────────────────
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => {
        el.style.transition = 'opacity 0.5s ease';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 5000);
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>