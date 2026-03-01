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

// Include database connection and helper functions
require_once 'config.php';

// For backward compatibility with existing code
$pdo = $conn;

// Get passed personal_info_id for linking from personal information
$linkedPersonalInfoId = isset($_GET['personal_info_id']) ? intval($_GET['personal_info_id']) : null;

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {

            // ─── INTERNAL EMPLOYMENT HISTORY ───────────────────────────────────
            case 'add':
            case 'update':
                try {
                    $end_date             = !empty($_POST['end_date'])             ? $_POST['end_date']             : null;
                    $manager_id           = !empty($_POST['reporting_manager_id']) ? $_POST['reporting_manager_id'] : null;
                    $dept_id              = !empty($_POST['department_id'])         ? $_POST['department_id']         : null;
                    $salary_effective_date = !empty($_POST['salary_effective_date']) ? $_POST['salary_effective_date'] : $_POST['start_date'];
                    $previous_salary      = !empty($_POST['previous_salary'])       ? $_POST['previous_salary']       : null;
                    $base_salary          = floatval($_POST['base_salary'] ?? 0);
                    $salary_increase_amount     = 0;
                    $salary_increase_percentage = 0;

                    if ($previous_salary) {
                        $salary_increase_amount     = $base_salary - floatval($previous_salary);
                        $salary_increase_percentage = $previous_salary > 0 ? ($salary_increase_amount / floatval($previous_salary)) * 100 : 0;
                    }

                    $is_current = (!$end_date) ? 1 : 0;

                    if ($_POST['action'] === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO employment_history 
                            (employee_id, job_title, salary_grade, department_id, employment_type, start_date, end_date, 
                             employment_status, reporting_manager_id, location, base_salary, allowances, 
                             bonuses, salary_adjustments, salary_effective_date, salary_increase_amount, 
                             salary_increase_percentage, previous_salary, position_sequence, is_current_position, 
                             promotion_type, reason_for_change, promotions_transfers, 
                             duties_responsibilities, performance_evaluations, training_certifications, 
                             contract_details, remarks) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $params = [
                            $_POST['employee_id'], $_POST['job_title'], $_POST['salary_grade'] ?? null,
                            $dept_id, $_POST['employment_type'], $_POST['start_date'], $end_date,
                            $_POST['employment_status'], $manager_id, $_POST['location'], $base_salary,
                            $_POST['allowances'] ?: 0, $_POST['bonuses'] ?: 0, $_POST['salary_adjustments'] ?: 0,
                            $salary_effective_date, $salary_increase_amount, $salary_increase_percentage,
                            $previous_salary, $_POST['position_sequence'] ?? 1, $is_current,
                            $_POST['promotion_type'] ?? 'Initial Hire', $_POST['reason_for_change'],
                            $_POST['promotions_transfers'], $_POST['duties_responsibilities'],
                            $_POST['performance_evaluations'], $_POST['training_certifications'],
                            $_POST['contract_details'], $_POST['remarks']
                        ];
                        $message = "Employment history record added successfully!";
                    } else {
                        $stmt = $pdo->prepare("UPDATE employment_history SET 
                            employee_id=?, job_title=?, salary_grade=?, department_id=?, employment_type=?, start_date=?, 
                            end_date=?, employment_status=?, reporting_manager_id=?, location=?, 
                            base_salary=?, allowances=?, bonuses=?, salary_adjustments=?, salary_effective_date=?, 
                            salary_increase_amount=?, salary_increase_percentage=?, previous_salary=?, position_sequence=?, 
                            is_current_position=?, promotion_type=?, reason_for_change=?, 
                            promotions_transfers=?, duties_responsibilities=?, performance_evaluations=?, 
                            training_certifications=?, contract_details=?, remarks=? 
                            WHERE history_id=?");
                        $params = [
                            $_POST['employee_id'], $_POST['job_title'], $_POST['salary_grade'] ?? null,
                            $dept_id, $_POST['employment_type'], $_POST['start_date'], $end_date,
                            $_POST['employment_status'], $manager_id, $_POST['location'], $base_salary,
                            $_POST['allowances'] ?: 0, $_POST['bonuses'] ?: 0, $_POST['salary_adjustments'] ?: 0,
                            $salary_effective_date, $salary_increase_amount, $salary_increase_percentage,
                            $previous_salary, $_POST['position_sequence'] ?? 1, $is_current,
                            $_POST['promotion_type'] ?? 'Initial Hire', $_POST['reason_for_change'],
                            $_POST['promotions_transfers'], $_POST['duties_responsibilities'],
                            $_POST['performance_evaluations'], $_POST['training_certifications'],
                            $_POST['contract_details'], $_POST['remarks'], $_POST['history_id']
                        ];
                        $message = "Employment history updated successfully!";
                    }
                    $stmt->execute($params);
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete':
                try {
                    $pdo->beginTransaction();
                    $fetchStmt = $pdo->prepare("SELECT * FROM employment_history WHERE history_id = ?");
                    $fetchStmt->execute([$_POST['history_id']]);
                    $recordToArchive = $fetchStmt->fetch(PDO::FETCH_ASSOC);

                    if ($recordToArchive) {
                        $archived_by          = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                        $employeeId           = $recordToArchive['employee_id'] ?? null;
                        $archiveReason        = 'Data Cleanup';
                        $archiveReasonDetails = 'Employment history record deleted by user';

                        if ($recordToArchive['employment_status'] === 'Resigned')    { $archiveReason = 'Resignation';  $archiveReasonDetails = 'Employment history archived after resignation'; }
                        elseif ($recordToArchive['employment_status'] === 'Terminated') { $archiveReason = 'Termination'; $archiveReasonDetails = 'Employment history archived after termination'; }
                        elseif ($recordToArchive['employment_status'] === 'Retired')    { $archiveReason = 'Retirement';  $archiveReasonDetails = 'Employment history archived after retirement'; }

                        $archiveStmt = $pdo->prepare("INSERT INTO archive_storage (source_table, record_id, employee_id, archive_reason, archive_reason_details, archived_by, archived_at, can_restore, record_data, notes) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1, ?, ?)");
                        $archiveStmt->execute([
                            'employment_history', $recordToArchive['history_id'], $employeeId,
                            $archiveReason, $archiveReasonDetails, $archived_by,
                            json_encode($recordToArchive, JSON_PRETTY_PRINT),
                            'Employment history archived. Job Title: ' . ($recordToArchive['job_title'] ?? 'N/A')
                        ]);
                        $pdo->prepare("DELETE FROM employment_history WHERE history_id=?")->execute([$_POST['history_id']]);
                        $pdo->commit();
                        $message = "Employment history archived successfully!";
                        $messageType = "success";
                    } else {
                        $pdo->rollBack();
                        $message = "Error: Record not found!";
                        $messageType = "error";
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Error archiving: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            // ─── EXTERNAL EMPLOYMENT HISTORY ───────────────────────────────────
            case 'add_external':
            case 'update_external':
                try {
                    $end_date  = !empty($_POST['ext_end_date'])  ? $_POST['ext_end_date']  : null;
                    $is_current = empty($end_date) ? 1 : 0;
                    $yoe = !empty($_POST['years_of_experience']) ? $_POST['years_of_experience'] : null;
                    $salary = !empty($_POST['monthly_salary']) ? $_POST['monthly_salary'] : null;

                    if ($_POST['action'] === 'add_external') {
                        $stmt = $pdo->prepare("INSERT INTO external_employment_history 
                            (employee_id, employer_name, employer_type, employer_address, job_title,
                             department_or_division, employment_type, start_date, end_date, is_current,
                             years_of_experience, monthly_salary, currency, reason_for_leaving,
                             key_responsibilities, achievements, immediate_supervisor, supervisor_contact,
                             reference_available, skills_gained, verified, remarks)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $params = [
                            $_POST['employee_id'], $_POST['employer_name'], $_POST['employer_type'],
                            $_POST['employer_address'] ?? null, $_POST['ext_job_title'],
                            $_POST['department_or_division'] ?? null, $_POST['ext_employment_type'],
                            $_POST['ext_start_date'], $end_date, $is_current, $yoe, $salary,
                            $_POST['currency'] ?? 'PHP', $_POST['reason_for_leaving'] ?? null,
                            $_POST['key_responsibilities'] ?? null, $_POST['achievements'] ?? null,
                            $_POST['immediate_supervisor'] ?? null, $_POST['supervisor_contact'] ?? null,
                            isset($_POST['reference_available']) ? 1 : 0,
                            $_POST['skills_gained'] ?? null,
                            isset($_POST['verified']) ? 1 : 0,
                            $_POST['ext_remarks'] ?? null
                        ];
                        $message = "External employment record added successfully!";
                    } else {
                        $stmt = $pdo->prepare("UPDATE external_employment_history SET 
                            employee_id=?, employer_name=?, employer_type=?, employer_address=?, job_title=?,
                            department_or_division=?, employment_type=?, start_date=?, end_date=?, is_current=?,
                            years_of_experience=?, monthly_salary=?, currency=?, reason_for_leaving=?,
                            key_responsibilities=?, achievements=?, immediate_supervisor=?, supervisor_contact=?,
                            reference_available=?, skills_gained=?, verified=?, remarks=?
                            WHERE ext_history_id=?");
                        $params = [
                            $_POST['employee_id'], $_POST['employer_name'], $_POST['employer_type'],
                            $_POST['employer_address'] ?? null, $_POST['ext_job_title'],
                            $_POST['department_or_division'] ?? null, $_POST['ext_employment_type'],
                            $_POST['ext_start_date'], $end_date, $is_current, $yoe, $salary,
                            $_POST['currency'] ?? 'PHP', $_POST['reason_for_leaving'] ?? null,
                            $_POST['key_responsibilities'] ?? null, $_POST['achievements'] ?? null,
                            $_POST['immediate_supervisor'] ?? null, $_POST['supervisor_contact'] ?? null,
                            isset($_POST['reference_available']) ? 1 : 0,
                            $_POST['skills_gained'] ?? null,
                            isset($_POST['verified']) ? 1 : 0,
                            $_POST['ext_remarks'] ?? null,
                            $_POST['ext_history_id']
                        ];
                        $message = "External employment record updated successfully!";
                    }
                    $stmt->execute($params);
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete_external':
                try {
                    $pdo->prepare("DELETE FROM external_employment_history WHERE ext_history_id=?")->execute([$_POST['ext_history_id']]);
                    $message = "External employment record deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            // ─── SEMINARS & TRAININGS ───────────────────────────────────────────
            case 'add_seminar':
            case 'update_seminar':
                try {
                    $end_date = !empty($_POST['sem_end_date']) ? $_POST['sem_end_date'] : null;
                    $cert_expiry = !empty($_POST['certificate_expiry']) ? $_POST['certificate_expiry'] : null;

                    if ($_POST['action'] === 'add_seminar') {
                        $stmt = $pdo->prepare("INSERT INTO employee_seminars_trainings
                            (employee_id, title, category, organizer, venue, modality,
                             start_date, end_date, duration_hours, certificate_received,
                             certificate_number, certificate_expiry, funded_by, amount_spent,
                             learning_outcomes, remarks)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $params = [
                            $_POST['employee_id'], $_POST['sem_title'], $_POST['sem_category'],
                            $_POST['organizer'] ?? null, $_POST['venue'] ?? null, $_POST['modality'] ?? 'Face-to-Face',
                            $_POST['sem_start_date'], $end_date, $_POST['duration_hours'] ?? null,
                            isset($_POST['certificate_received']) ? 1 : 0,
                            $_POST['certificate_number'] ?? null, $cert_expiry,
                            $_POST['funded_by'] ?? 'LGU Budget', $_POST['amount_spent'] ?? 0,
                            $_POST['learning_outcomes'] ?? null, $_POST['sem_remarks'] ?? null
                        ];
                        $message = "Seminar/Training record added successfully!";
                    } else {
                        $stmt = $pdo->prepare("UPDATE employee_seminars_trainings SET
                            employee_id=?, title=?, category=?, organizer=?, venue=?, modality=?,
                            start_date=?, end_date=?, duration_hours=?, certificate_received=?,
                            certificate_number=?, certificate_expiry=?, funded_by=?, amount_spent=?,
                            learning_outcomes=?, remarks=?
                            WHERE seminar_id=?");
                        $params = [
                            $_POST['employee_id'], $_POST['sem_title'], $_POST['sem_category'],
                            $_POST['organizer'] ?? null, $_POST['venue'] ?? null, $_POST['modality'] ?? 'Face-to-Face',
                            $_POST['sem_start_date'], $end_date, $_POST['duration_hours'] ?? null,
                            isset($_POST['certificate_received']) ? 1 : 0,
                            $_POST['certificate_number'] ?? null, $cert_expiry,
                            $_POST['funded_by'] ?? 'LGU Budget', $_POST['amount_spent'] ?? 0,
                            $_POST['learning_outcomes'] ?? null, $_POST['sem_remarks'] ?? null,
                            $_POST['seminar_id']
                        ];
                        $message = "Seminar/Training record updated successfully!";
                    }
                    $stmt->execute($params);
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete_seminar':
                try {
                    $pdo->prepare("DELETE FROM employee_seminars_trainings WHERE seminar_id=?")->execute([$_POST['seminar_id']]);
                    $message = "Seminar/Training record deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            // ─── LICENSES & CERTIFICATIONS ─────────────────────────────────────
            case 'add_license':
            case 'update_license':
                try {
                    $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
                    $renewal = !empty($_POST['renewal_date']) ? $_POST['renewal_date'] : null;
                    $exam_date = !empty($_POST['date_of_exam']) ? $_POST['date_of_exam'] : null;
                    $issued = !empty($_POST['date_issued']) ? $_POST['date_issued'] : null;
                    $rating = !empty($_POST['rating']) ? $_POST['rating'] : null;

                    if ($_POST['action'] === 'add_license') {
                        $stmt = $pdo->prepare("INSERT INTO employee_licenses_certifications
                            (employee_id, license_name, license_type, issuing_body, license_number,
                             date_issued, date_of_exam, expiry_date, rating, status, renewal_date, remarks)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                        $params = [
                            $_POST['employee_id'], $_POST['license_name'], $_POST['license_type'],
                            $_POST['issuing_body'] ?? null, $_POST['license_number'] ?? null,
                            $issued, $exam_date, $expiry, $rating,
                            $_POST['lic_status'] ?? 'Active', $renewal, $_POST['lic_remarks'] ?? null
                        ];
                        $message = "License/Certification added successfully!";
                    } else {
                        $stmt = $pdo->prepare("UPDATE employee_licenses_certifications SET
                            employee_id=?, license_name=?, license_type=?, issuing_body=?, license_number=?,
                            date_issued=?, date_of_exam=?, expiry_date=?, rating=?, status=?, renewal_date=?, remarks=?
                            WHERE license_id=?");
                        $params = [
                            $_POST['employee_id'], $_POST['license_name'], $_POST['license_type'],
                            $_POST['issuing_body'] ?? null, $_POST['license_number'] ?? null,
                            $issued, $exam_date, $expiry, $rating,
                            $_POST['lic_status'] ?? 'Active', $renewal, $_POST['lic_remarks'] ?? null,
                            $_POST['license_id']
                        ];
                        $message = "License/Certification updated successfully!";
                    }
                    $stmt->execute($params);
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete_license':
                try {
                    $pdo->prepare("DELETE FROM employee_licenses_certifications WHERE license_id=?")->execute([$_POST['license_id']]);
                    $message = "License/Certification deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            // ─── AWARDS & RECOGNITION ──────────────────────────────────────────
            case 'add_award':
            case 'update_award':
                try {
                    $date_received = !empty($_POST['date_received']) ? $_POST['date_received'] : null;

                    if ($_POST['action'] === 'add_award') {
                        $stmt = $pdo->prepare("INSERT INTO employee_awards_recognition
                            (employee_id, award_title, award_type, awarding_body, date_received, description, remarks)
                            VALUES (?,?,?,?,?,?,?)");
                        $params = [
                            $_POST['employee_id'], $_POST['award_title'], $_POST['award_type'],
                            $_POST['awarding_body'] ?? null, $date_received,
                            $_POST['award_description'] ?? null, $_POST['award_remarks'] ?? null
                        ];
                        $message = "Award/Recognition added successfully!";
                    } else {
                        $stmt = $pdo->prepare("UPDATE employee_awards_recognition SET
                            employee_id=?, award_title=?, award_type=?, awarding_body=?, date_received=?,
                            description=?, remarks=?
                            WHERE award_id=?");
                        $params = [
                            $_POST['employee_id'], $_POST['award_title'], $_POST['award_type'],
                            $_POST['awarding_body'] ?? null, $date_received,
                            $_POST['award_description'] ?? null, $_POST['award_remarks'] ?? null,
                            $_POST['award_id']
                        ];
                        $message = "Award/Recognition updated successfully!";
                    }
                    $stmt->execute($params);
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete_award':
                try {
                    $pdo->prepare("DELETE FROM employee_awards_recognition WHERE award_id=?")->execute([$_POST['award_id']]);
                    $message = "Award/Recognition deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            // ─── VOLUNTARY WORK ────────────────────────────────────────────────
            case 'add_voluntary':
            case 'update_voluntary':
                try {
                    $end_date = !empty($_POST['vol_end_date']) ? $_POST['vol_end_date'] : null;
                    $start_date = !empty($_POST['vol_start_date']) ? $_POST['vol_start_date'] : null;

                    if ($_POST['action'] === 'add_voluntary') {
                        $stmt = $pdo->prepare("INSERT INTO employee_voluntary_work
                            (employee_id, organization, position_nature_of_work, start_date, end_date, hours_per_week, description)
                            VALUES (?,?,?,?,?,?,?)");
                        $params = [
                            $_POST['employee_id'], $_POST['organization'],
                            $_POST['position_nature_of_work'] ?? null,
                            $start_date, $end_date,
                            !empty($_POST['hours_per_week']) ? $_POST['hours_per_week'] : null,
                            $_POST['vol_description'] ?? null
                        ];
                        $message = "Voluntary work record added successfully!";
                    } else {
                        $stmt = $pdo->prepare("UPDATE employee_voluntary_work SET
                            employee_id=?, organization=?, position_nature_of_work=?, start_date=?,
                            end_date=?, hours_per_week=?, description=?
                            WHERE voluntary_id=?");
                        $params = [
                            $_POST['employee_id'], $_POST['organization'],
                            $_POST['position_nature_of_work'] ?? null,
                            $start_date, $end_date,
                            !empty($_POST['hours_per_week']) ? $_POST['hours_per_week'] : null,
                            $_POST['vol_description'] ?? null,
                            $_POST['voluntary_id']
                        ];
                        $message = "Voluntary work record updated successfully!";
                    }
                    $stmt->execute($params);
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;

            case 'delete_voluntary':
                try {
                    $pdo->prepare("DELETE FROM employee_voluntary_work WHERE voluntary_id=?")->execute([$_POST['voluntary_id']]);
                    $message = "Voluntary work record deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// ─── FETCH ALL DATA ──────────────────────────────────────────────────────────

// Internal employment history
$stmt = $pdo->query("
    SELECT eh.*, ep.personal_info_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        d.department_name,
        CONCAT(pi2.first_name, ' ', pi2.last_name) as manager_name
    FROM employment_history eh
    LEFT JOIN employee_profiles ep ON eh.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN departments d ON eh.department_id = d.department_id
    LEFT JOIN employee_profiles ep2 ON eh.reporting_manager_id = ep2.employee_id
    LEFT JOIN personal_information pi2 ON ep2.personal_info_id = pi2.personal_info_id
    ORDER BY eh.start_date DESC, eh.history_id DESC
");
$employmentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// External employment history
$stmt = $pdo->query("
    SELECT eeh.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM external_employment_history eeh
    LEFT JOIN employee_profiles ep ON eeh.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY eeh.start_date DESC
");
$externalHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Seminars & Trainings
$stmt = $pdo->query("
    SELECT est.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM employee_seminars_trainings est
    LEFT JOIN employee_profiles ep ON est.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY est.start_date DESC
");
$seminars = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Licenses & Certifications
$stmt = $pdo->query("
    SELECT elc.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM employee_licenses_certifications elc
    LEFT JOIN employee_profiles ep ON elc.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY elc.date_issued DESC
");
$licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Awards & Recognition
$stmt = $pdo->query("
    SELECT ear.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM employee_awards_recognition ear
    LEFT JOIN employee_profiles ep ON ear.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY ear.date_received DESC
");
$awards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Voluntary Work
$stmt = $pdo->query("
    SELECT evw.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM employee_voluntary_work evw
    LEFT JOIN employee_profiles ep ON evw.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY evw.start_date DESC
");
$voluntaryWork = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Employees dropdown
$stmt = $pdo->query("
    SELECT ep.employee_id, ep.employee_number,
        CONCAT(pi.first_name, ' ', pi.last_name) as full_name
    FROM employee_profiles ep
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY pi.first_name
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Departments dropdown
$stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$managers = $employees;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employment History Management - HR System</title>
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
        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }
        .section-title { color: var(--azure-blue); margin-bottom: 20px; font-weight: 600; }
        .main-content { background: var(--azure-blue-pale); padding: 20px; }

        /* ── TAB NAV ── */
        .tab-nav {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: white;
            padding: 10px 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .tab-btn {
            padding: 9px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 22px;
            background: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            color: #666;
            transition: all 0.25s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .tab-btn:hover { border-color: var(--azure-blue-light); color: var(--azure-blue); }
        .tab-btn.active {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 12px rgba(233,30,99,0.3);
        }
        .tab-badge {
            background: rgba(255,255,255,0.3);
            color: inherit;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 11px;
        }
        .tab-btn:not(.active) .tab-badge {
            background: #f0f0f0;
            color: #888;
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── CONTROLS ── */
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .search-box { position: relative; flex: 1; max-width: 380px; }
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 42px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .search-box input:focus { border-color: var(--azure-blue); outline: none; box-shadow: 0 0 8px rgba(233,30,99,0.25); }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999; }

        /* ── BUTTONS ── */
        .btn {
            padding: 10px 22px;
            border: none;
            border-radius: 22px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(233,30,99,0.4); color: white; }
        .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
        .btn-success:hover { transform: translateY(-1px); color: white; }
        .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; }
        .btn-info { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-small { padding: 6px 12px; font-size: 12px; margin: 0 2px; }

        /* ── TABLE ── */
        .table-container { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .table { width: 100%; border-collapse: collapse; }
        .table th {
            background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #f0f0f0 100%);
            padding: 13px 15px;
            text-align: left;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        .table td { padding: 12px 15px; border-bottom: 1px solid #f3f3f3; vertical-align: middle; font-size: 13px; }
        .table tbody tr:hover { background: var(--azure-blue-pale); }

        /* ── BADGES ── */
        .badge-pill {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-active    { background: #d4edda; color: #155724; }
        .badge-resigned  { background: #f8d7da; color: #721c24; }
        .badge-terminated{ background: #f8d7da; color: #721c24; }
        .badge-promoted  { background: #cce5ff; color: #004085; }
        .badge-retired   { background: #e2d9f3; color: #4a235a; }
        .badge-end       { background: #fff3cd; color: #856404; }
        .badge-transferred{ background: #d1ecf1; color: #0c5460; }
        .badge-expired   { background: #f8d7da; color: #721c24; }
        .badge-pending   { background: #fff3cd; color: #856404; }
        .badge-gov       { background: #cce5ff; color: #004085; }
        .badge-private   { background: #d4edda; color: #155724; }
        .badge-ngo       { background: #e2d9f3; color: #4a235a; }
        .badge-verified  { background: #d4edda; color: #155724; }
        .badge-unverified{ background: #fff3cd; color: #856404; }
        .duration-badge  { background: #e3f2fd; color: #1565c0; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }

        /* ── MODALS ── */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 95%;
            max-width: 920px;
            max-height: 94vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            animation: slideIn 0.28s ease;
        }
        .modal-content.modal-lg { max-width: 1050px; }
        .modal-content.modal-sm { max-width: 680px; }
        @keyframes slideIn { from { transform: translateY(-40px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 18px 28px;
            border-radius: 15px 15px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 { margin: 0; font-size: 18px; }
        .close { font-size: 26px; font-weight: bold; cursor: pointer; color: white; opacity: 0.75; line-height: 1; background: none; border: none; }
        .close:hover { opacity: 1; }
        .modal-body { padding: 28px; }

        /* ── FORM ── */
        .form-section-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--azure-blue);
            margin: 20px 0 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid var(--azure-blue-lighter);
        }
        .form-row { display: flex; gap: 16px; margin-bottom: 0; }
        .form-col { flex: 1; }
        .form-col-3 { flex: 0 0 33.333%; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #444; }
        .form-control {
            width: 100%;
            padding: 8px 13px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-control:focus { border-color: var(--azure-blue); outline: none; box-shadow: 0 0 8px rgba(233,30,99,0.2); }
        textarea.form-control { resize: vertical; min-height: 75px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
        .checkbox-group input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--azure-blue); }

        /* ── VIEW DETAILS ── */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .detail-card { background: #f8f9fa; border-radius: 10px; padding: 18px; border-left: 4px solid var(--azure-blue); }
        .detail-card h5 { font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--azure-blue-dark); margin-bottom: 14px; }
        .detail-item { margin-bottom: 10px; }
        .detail-item strong { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px; }
        .detail-item p { margin: 0; font-size: 14px; color: #333; }
        .detail-full { background: #f8f9fa; border-radius: 10px; padding: 16px; margin-bottom: 14px; border-left: 4px solid var(--azure-blue-lighter); }
        .detail-full h5 { font-size: 13px; font-weight: 700; color: var(--azure-blue-dark); margin-bottom: 10px; }
        .detail-full p { margin: 0; font-size: 14px; color: #555; line-height: 1.65; background: white; padding: 12px; border-radius: 6px; }

        /* ── ALERTS ── */
        .alert { padding: 13px 18px; margin-bottom: 18px; border-radius: 8px; font-weight: 500; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .no-results { text-align: center; padding: 40px; color: #888; }
        .no-results i { font-size: 3rem; margin-bottom: 12px; color: #ddd; display: block; }

        .nav-buttons-section { background: linear-gradient(135deg, var(--azure-blue-pale) 0%, #f5f5f5 100%); padding: 20px; border-radius: 10px; margin-top: 18px; border-left: 4px solid var(--azure-blue); }
        .nav-buttons-section h5 { color: var(--azure-blue-dark); margin-bottom: 12px; font-weight: 600; }
        .nav-button-group { display: flex; gap: 10px; flex-wrap: wrap; }

        @media (max-width: 768px) {
            .controls { flex-direction: column; align-items: stretch; }
            .search-box { max-width: none; }
            .form-row { flex-direction: column; }
            .detail-grid { grid-template-columns: 1fr; }
            .table-container { overflow-x: auto; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include 'navigation.php'; ?>
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <h2 class="section-title"><i class="fas fa-history mr-2"></i>Employment History Management</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <!-- TAB NAVIGATION -->
            <div class="tab-nav">
                <button class="tab-btn active" onclick="switchTab('internal', this)">
                    🏢 Internal History <span class="tab-badge"><?= count($employmentHistory) ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('external', this)">
                    🌐 Work Experience <span class="tab-badge"><?= count($externalHistory) ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('seminars', this)">
                    🎓 Seminars & Trainings <span class="tab-badge"><?= count($seminars) ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('licenses', this)">
                    📜 Licenses & Certifications <span class="tab-badge"><?= count($licenses) ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('awards', this)">
                    🏆 Awards & Recognition <span class="tab-badge"><?= count($awards) ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('voluntary', this)">
                    🤝 Voluntary Work <span class="tab-badge"><?= count($voluntaryWork) ?></span>
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 1: INTERNAL EMPLOYMENT HISTORY                            -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div id="tab-internal" class="tab-panel active">
                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchInternal" placeholder="Search employee, job title, department...">
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <?php if ($linkedPersonalInfoId): ?>
                            <a class="btn btn-info" href="personal_information.php?personal_info_id=<?= $linkedPersonalInfoId ?>">⬅️ Back</a>
                        <?php endif; ?>
                        <button class="btn btn-primary" onclick="openModal('historyModal')">➕ Add Record</button>
                    </div>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Job Title</th>
                                <th>Department</th>
                                <th>Type</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Base Salary</th>
                                <th>Manager</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bodyInternal">
                            <?php foreach ($employmentHistory as $h): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($h['employee_name']) ?></strong><br>
                                    <small style="color:#888">#<?= htmlspecialchars($h['employee_number']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($h['job_title']) ?></strong><br>
                                    <small style="color:#888"><?= htmlspecialchars($h['salary_grade'] ?? '') ?></small>
                                </td>
                                <td><?= htmlspecialchars($h['department_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($h['employment_type']) ?></td>
                                <td>
                                    <div><?= date('M d, Y', strtotime($h['start_date'])) ?></div>
                                    <small style="color:#888">to <?= $h['end_date'] ? date('M d, Y', strtotime($h['end_date'])) : 'Present' ?></small>
                                    <?php if (!$h['end_date']): ?><div class="duration-badge">Current</div><?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $sc = strtolower(str_replace(' ', '-', $h['employment_status']));
                                        $scClass = in_array($sc, ['active','resigned','promoted','transferred','terminated','retired']) ? 'badge-'.$sc : 'badge-end';
                                    ?>
                                    <span class="badge-pill <?= $scClass ?>"><?= htmlspecialchars($h['employment_status']) ?></span>
                                </td>
                                <td>
                                    <strong>₱<?= number_format($h['base_salary'], 2) ?></strong>
                                    <?php if ($h['allowances'] > 0): ?>
                                        <br><small style="color:#888">+₱<?= number_format($h['allowances'],2) ?> allow.</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($h['manager_name'] ?? '—') ?></td>
                                <td>
                                    <button class="btn btn-warning btn-small" onclick='editInternalHistory(<?= $h["history_id"] ?>)' title="Edit">✏️</button>
                                    <button class="btn btn-primary btn-small"  onclick='viewInternalDetails(<?= $h["history_id"] ?>)' title="View">👁️</button>
                                    <button class="btn btn-info btn-small"     onclick='archiveInternal(<?= $h["history_id"] ?>)' title="Archive">📦</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($employmentHistory)): ?>
                            <tr><td colspan="9"><div class="no-results"><i class="fas fa-history"></i><p>No internal employment history found.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 2: EXTERNAL EMPLOYMENT HISTORY                            -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div id="tab-external" class="tab-panel">
                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchExternal" placeholder="Search employer, job title, employee...">
                    </div>
                    <button class="btn btn-primary" onclick="openModal('externalModal')">➕ Add Work Experience</button>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Employer</th>
                                <th>Type</th>
                                <th>Job Title</th>
                                <th>Employment Type</th>
                                <th>Period</th>
                                <th>Salary</th>
                                <th>Reason Leaving</th>
                                <th>Verified</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bodyExternal">
                            <?php foreach ($externalHistory as $ex): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($ex['employee_name']) ?></strong><br>
                                    <small style="color:#888">#<?= htmlspecialchars($ex['employee_number']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($ex['employer_name']) ?></strong>
                                    <?php if ($ex['employer_address']): ?>
                                        <br><small style="color:#888"><?= htmlspecialchars($ex['employer_address']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $etClass = match($ex['employer_type']) {
                                            'Government' => 'badge-gov',
                                            'Private'    => 'badge-private',
                                            default      => 'badge-ngo'
                                        };
                                    ?>
                                    <span class="badge-pill <?= $etClass ?>"><?= htmlspecialchars($ex['employer_type']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($ex['job_title']) ?></strong>
                                    <?php if ($ex['department_or_division']): ?>
                                        <br><small style="color:#888"><?= htmlspecialchars($ex['department_or_division']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($ex['employment_type']) ?></td>
                                <td>
                                    <?= date('M Y', strtotime($ex['start_date'])) ?> –
                                    <?= $ex['end_date'] ? date('M Y', strtotime($ex['end_date'])) : '<span class="duration-badge">Present</span>' ?>
                                    <?php if ($ex['years_of_experience']): ?>
                                        <br><small style="color:#888"><?= $ex['years_of_experience'] ?> yrs</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $ex['monthly_salary'] ? '₱'.number_format($ex['monthly_salary'],2) : '—' ?></td>
                                <td><small><?= htmlspecialchars($ex['reason_for_leaving'] ?? '—') ?></small></td>
                                <td>
                                    <span class="badge-pill <?= $ex['verified'] ? 'badge-verified' : 'badge-unverified' ?>">
                                        <?= $ex['verified'] ? '✓ Verified' : 'Pending' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-warning btn-small" onclick='editExternal(<?= $ex["ext_history_id"] ?>)' title="Edit">✏️</button>
                                    <button class="btn btn-primary btn-small"  onclick='viewExternal(<?= $ex["ext_history_id"] ?>)' title="View">👁️</button>
                                    <button class="btn btn-danger btn-small"   onclick='deleteRecord("delete_external","ext_history_id",<?= $ex["ext_history_id"] ?>,"this work experience record")' title="Delete">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($externalHistory)): ?>
                            <tr><td colspan="10"><div class="no-results"><i class="fas fa-briefcase"></i><p>No external work experience found.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 3: SEMINARS & TRAININGS                                   -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div id="tab-seminars" class="tab-panel">
                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchSeminars" placeholder="Search training title, organizer, employee...">
                    </div>
                    <button class="btn btn-primary" onclick="openModal('seminarModal')">➕ Add Seminar/Training</button>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Training Title</th>
                                <th>Category</th>
                                <th>Organizer</th>
                                <th>Modality</th>
                                <th>Date</th>
                                <th>Hours</th>
                                <th>Certificate</th>
                                <th>Funded By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bodySeminars">
                            <?php foreach ($seminars as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($s['employee_name']) ?></strong><br>
                                    <small style="color:#888">#<?= htmlspecialchars($s['employee_number']) ?></small>
                                </td>
                                <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
                                <td><small><?= htmlspecialchars($s['category']) ?></small></td>
                                <td><small><?= htmlspecialchars($s['organizer'] ?? '—') ?></small></td>
                                <td><?= htmlspecialchars($s['modality'] ?? '—') ?></td>
                                <td>
                                    <?= date('M d, Y', strtotime($s['start_date'])) ?>
                                    <?php if ($s['end_date'] && $s['end_date'] !== $s['start_date']): ?>
                                        <br><small style="color:#888">to <?= date('M d, Y', strtotime($s['end_date'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $s['duration_hours'] ? $s['duration_hours'].'h' : '—' ?></td>
                                <td>
                                    <?php if ($s['certificate_received']): ?>
                                        <span class="badge-pill badge-verified">✓ Received</span>
                                        <?php if ($s['certificate_expiry']): ?>
                                            <br><small style="color:#888">Exp: <?= date('M Y', strtotime($s['certificate_expiry'])) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge-pill badge-unverified">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= htmlspecialchars($s['funded_by'] ?? '—') ?></small></td>
                                <td>
                                    <button class="btn btn-warning btn-small" onclick='editSeminar(<?= $s["seminar_id"] ?>)' title="Edit">✏️</button>
                                    <button class="btn btn-primary btn-small"  onclick='viewSeminar(<?= $s["seminar_id"] ?>)' title="View">👁️</button>
                                    <button class="btn btn-danger btn-small"   onclick='deleteRecord("delete_seminar","seminar_id",<?= $s["seminar_id"] ?>,"this seminar/training record")' title="Delete">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($seminars)): ?>
                            <tr><td colspan="10"><div class="no-results"><i class="fas fa-graduation-cap"></i><p>No seminar/training records found.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 4: LICENSES & CERTIFICATIONS                              -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div id="tab-licenses" class="tab-panel">
                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchLicenses" placeholder="Search license name, issuing body, employee...">
                    </div>
                    <button class="btn btn-primary" onclick="openModal('licenseModal')">➕ Add License/Certification</button>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>License / Certification</th>
                                <th>Type</th>
                                <th>Issuing Body</th>
                                <th>License No.</th>
                                <th>Date Issued</th>
                                <th>Expiry</th>
                                <th>Rating</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bodyLicenses">
                            <?php foreach ($licenses as $lic): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($lic['employee_name']) ?></strong><br>
                                    <small style="color:#888">#<?= htmlspecialchars($lic['employee_number']) ?></small>
                                </td>
                                <td><strong><?= htmlspecialchars($lic['license_name']) ?></strong></td>
                                <td><small><?= htmlspecialchars($lic['license_type']) ?></small></td>
                                <td><small><?= htmlspecialchars($lic['issuing_body'] ?? '—') ?></small></td>
                                <td><small><?= htmlspecialchars($lic['license_number'] ?? '—') ?></small></td>
                                <td><?= $lic['date_issued'] ? date('M d, Y', strtotime($lic['date_issued'])) : '—' ?></td>
                                <td>
                                    <?php if ($lic['expiry_date']): ?>
                                        <?= date('M d, Y', strtotime($lic['expiry_date'])) ?>
                                        <?php if (strtotime($lic['expiry_date']) < time()): ?>
                                            <br><span class="badge-pill badge-expired" style="font-size:9px;">Expired</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small style="color:#888">No expiry</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $lic['rating'] ? number_format($lic['rating'],2).'%' : '—' ?></td>
                                <td>
                                    <?php
                                        $statClass = match($lic['status']) {
                                            'Active'         => 'badge-active',
                                            'Expired'        => 'badge-expired',
                                            'Pending Renewal'=> 'badge-end',
                                            default          => 'badge-resigned'
                                        };
                                    ?>
                                    <span class="badge-pill <?= $statClass ?>"><?= htmlspecialchars($lic['status']) ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-warning btn-small" onclick='editLicense(<?= $lic["license_id"] ?>)' title="Edit">✏️</button>
                                    <button class="btn btn-primary btn-small"  onclick='viewLicense(<?= $lic["license_id"] ?>)' title="View">👁️</button>
                                    <button class="btn btn-danger btn-small"   onclick='deleteRecord("delete_license","license_id",<?= $lic["license_id"] ?>,"this license/certification record")' title="Delete">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($licenses)): ?>
                            <tr><td colspan="10"><div class="no-results"><i class="fas fa-id-card"></i><p>No license/certification records found.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 5: AWARDS & RECOGNITION                                   -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div id="tab-awards" class="tab-panel">
                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchAwards" placeholder="Search award title, awarding body, employee...">
                    </div>
                    <button class="btn btn-primary" onclick="openModal('awardModal')">➕ Add Award</button>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Award Title</th>
                                <th>Award Type</th>
                                <th>Awarding Body</th>
                                <th>Date Received</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bodyAwards">
                            <?php foreach ($awards as $aw): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($aw['employee_name']) ?></strong><br>
                                    <small style="color:#888">#<?= htmlspecialchars($aw['employee_number']) ?></small>
                                </td>
                                <td><strong><?= htmlspecialchars($aw['award_title']) ?></strong></td>
                                <td><span class="badge-pill badge-promoted"><?= htmlspecialchars($aw['award_type']) ?></span></td>
                                <td><?= htmlspecialchars($aw['awarding_body'] ?? '—') ?></td>
                                <td><?= $aw['date_received'] ? date('M d, Y', strtotime($aw['date_received'])) : '—' ?></td>
                                <td><small><?= htmlspecialchars(substr($aw['description'] ?? '', 0, 80)).(strlen($aw['description'] ?? '') > 80 ? '…' : '') ?></small></td>
                                <td>
                                    <button class="btn btn-warning btn-small" onclick='editAward(<?= $aw["award_id"] ?>)' title="Edit">✏️</button>
                                    <button class="btn btn-primary btn-small"  onclick='viewAward(<?= $aw["award_id"] ?>)' title="View">👁️</button>
                                    <button class="btn btn-danger btn-small"   onclick='deleteRecord("delete_award","award_id",<?= $aw["award_id"] ?>,"this award record")' title="Delete">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($awards)): ?>
                            <tr><td colspan="7"><div class="no-results"><i class="fas fa-trophy"></i><p>No awards/recognition records found.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 6: VOLUNTARY WORK                                         -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            <div id="tab-voluntary" class="tab-panel">
                <div class="controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchVoluntary" placeholder="Search organization, employee...">
                    </div>
                    <button class="btn btn-primary" onclick="openModal('voluntaryModal')">➕ Add Voluntary Work</button>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Organization</th>
                                <th>Position / Nature of Work</th>
                                <th>Period</th>
                                <th>Hours/Week</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bodyVoluntary">
                            <?php foreach ($voluntaryWork as $vw): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($vw['employee_name']) ?></strong><br>
                                    <small style="color:#888">#<?= htmlspecialchars($vw['employee_number']) ?></small>
                                </td>
                                <td><strong><?= htmlspecialchars($vw['organization']) ?></strong></td>
                                <td><?= htmlspecialchars($vw['position_nature_of_work'] ?? '—') ?></td>
                                <td>
                                    <?= $vw['start_date'] ? date('M Y', strtotime($vw['start_date'])) : '—' ?> –
                                    <?= $vw['end_date'] ? date('M Y', strtotime($vw['end_date'])) : '<span class="duration-badge">Present</span>' ?>
                                </td>
                                <td><?= $vw['hours_per_week'] ? $vw['hours_per_week'].' hrs' : '—' ?></td>
                                <td><small><?= htmlspecialchars(substr($vw['description'] ?? '', 0, 80)).(strlen($vw['description'] ?? '') > 80 ? '…' : '') ?></small></td>
                                <td>
                                    <button class="btn btn-warning btn-small" onclick='editVoluntary(<?= $vw["voluntary_id"] ?>)' title="Edit">✏️</button>
                                    <button class="btn btn-primary btn-small"  onclick='viewVoluntary(<?= $vw["voluntary_id"] ?>)' title="View">👁️</button>
                                    <button class="btn btn-danger btn-small"   onclick='deleteRecord("delete_voluntary","voluntary_id",<?= $vw["voluntary_id"] ?>,"this voluntary work record")' title="Delete">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($voluntaryWork)): ?>
                            <tr><td colspan="7"><div class="no-results"><i class="fas fa-hands-helping"></i><p>No voluntary work records found.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- end main-content -->
    </div><!-- end row -->
</div><!-- end container-fluid -->


<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: INTERNAL EMPLOYMENT HISTORY (ADD/EDIT)                             -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="historyModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 id="historyModalTitle">Add Employment History</h3>
            <button class="close" onclick="closeModal('historyModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="historyForm" method="POST">
                <input type="hidden" id="ih_action" name="action" value="add">
                <input type="hidden" id="history_id" name="history_id">

                <div class="form-section-title">Basic Information</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employee *</label>
                            <select id="ih_employee_id" name="employee_id" class="form-control" required>
                                <option value="">Select employee...</option>
                                <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['employee_id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (#<?= $e['employee_number'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Job Title *</label>
                            <input type="text" id="ih_job_title" name="job_title" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Department</label>
                            <select id="ih_department_id" name="department_id" class="form-control">
                                <option value="">Select department...</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employment Type *</label>
                            <select id="ih_employment_type" name="employment_type" class="form-control" required>
                                <option value="">Select type...</option>
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Project-based">Project-based</option>
                                <option value="Casual">Casual</option>
                                <option value="Intern">Intern</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Salary Grade</label>
                            <input type="text" id="ih_salary_grade" name="salary_grade" class="form-control" placeholder="e.g., Grade 10">
                        </div>
                    </div>
                </div>

                <div class="form-section-title">Employment Period & Status</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="date" id="ih_start_date" name="start_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" id="ih_end_date" name="end_date" class="form-control">
                            <small style="color:#888">Leave blank if current position</small>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employment Status *</label>
                            <select id="ih_employment_status" name="employment_status" class="form-control" required>
                                <option value="">Select status...</option>
                                <option value="Active">Active</option>
                                <option value="Promoted">Promoted</option>
                                <option value="Demoted">Demoted</option>
                                <option value="Lateral Move">Lateral Move</option>
                                <option value="Resigned">Resigned</option>
                                <option value="Transferred">Transferred</option>
                                <option value="Terminated">Terminated</option>
                                <option value="Retired">Retired</option>
                                <option value="End of Contract">End of Contract</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Promotion/Movement Type</label>
                            <select id="ih_promotion_type" name="promotion_type" class="form-control">
                                <option value="Initial Hire">Initial Hire</option>
                                <option value="Promotion">Promotion</option>
                                <option value="Demotion">Demotion</option>
                                <option value="Lateral Move">Lateral Move</option>
                                <option value="Rehire">Rehire</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Position Sequence #</label>
                            <input type="number" id="ih_position_sequence" name="position_sequence" class="form-control" value="1" min="1">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Reporting Manager</label>
                            <select id="ih_reporting_manager_id" name="reporting_manager_id" class="form-control">
                                <option value="">Select manager...</option>
                                <?php foreach ($managers as $m): ?>
                                <option value="<?= $m['employee_id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section-title">Compensation</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Base Salary (₱) *</label>
                            <input type="number" id="ih_base_salary" name="base_salary" class="form-control" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Allowances (₱)</label>
                            <input type="number" id="ih_allowances" name="allowances" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Bonuses (₱)</label>
                            <input type="number" id="ih_bonuses" name="bonuses" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Salary Adjustments (₱)</label>
                            <input type="number" id="ih_salary_adjustments" name="salary_adjustments" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Previous Salary (₱)</label>
                            <input type="number" id="ih_previous_salary" name="previous_salary" class="form-control" step="0.01">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Salary Effective Date</label>
                            <input type="date" id="ih_salary_effective_date" name="salary_effective_date" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-section-title">Details</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" id="ih_location" name="location" class="form-control" placeholder="e.g., City Hall - 1st Floor">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Reason for Change</label>
                            <input type="text" id="ih_reason_for_change" name="reason_for_change" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Promotions / Transfers</label>
                    <input type="text" id="ih_promotions_transfers" name="promotions_transfers" class="form-control">
                </div>
                <div class="form-group">
                    <label>Duties & Responsibilities</label>
                    <textarea id="ih_duties_responsibilities" name="duties_responsibilities" class="form-control"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Performance Evaluations</label>
                            <textarea id="ih_performance_evaluations" name="performance_evaluations" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Training & Certifications</label>
                            <textarea id="ih_training_certifications" name="training_certifications" class="form-control"></textarea>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Contract Details</label>
                            <textarea id="ih_contract_details" name="contract_details" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea id="ih_remarks" name="remarks" class="form-control"></textarea>
                        </div>
                    </div>
                </div>

                <div style="text-align:center;margin-top:24px;display:flex;gap:12px;justify-content:center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('historyModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">💾 Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: EXTERNAL EMPLOYMENT HISTORY (ADD/EDIT)                             -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="externalModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 id="externalModalTitle">Add Work Experience</h3>
            <button class="close" onclick="closeModal('externalModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="externalForm" method="POST">
                <input type="hidden" id="ex_action" name="action" value="add_external">
                <input type="hidden" id="ext_history_id" name="ext_history_id">

                <div class="form-section-title">Employer Information</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employee *</label>
                            <select id="ex_employee_id" name="employee_id" class="form-control" required>
                                <option value="">Select employee...</option>
                                <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['employee_id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (#<?= $e['employee_number'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employer Name *</label>
                            <input type="text" id="ex_employer_name" name="employer_name" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employer Type *</label>
                            <select id="ex_employer_type" name="employer_type" class="form-control" required>
                                <option value="">Select type...</option>
                                <option value="Government">Government</option>
                                <option value="Private">Private</option>
                                <option value="NGO/Non-Profit">NGO/Non-Profit</option>
                                <option value="Self-Employed/Freelance">Self-Employed/Freelance</option>
                                <option value="International Organization">International Organization</option>
                                <option value="Academic Institution">Academic Institution</option>
                                <option value="Military/Uniformed Service">Military/Uniformed Service</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employer Address</label>
                            <input type="text" id="ex_employer_address" name="employer_address" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-section-title">Position Details</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Job Title *</label>
                            <input type="text" id="ex_job_title" name="ext_job_title" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Department / Division</label>
                            <input type="text" id="ex_dept" name="department_or_division" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employment Type *</label>
                            <select id="ex_employment_type" name="ext_employment_type" class="form-control" required>
                                <option value="">Select type...</option>
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Project-based">Project-based</option>
                                <option value="Casual">Casual</option>
                                <option value="Intern">Intern</option>
                                <option value="Volunteer">Volunteer</option>
                                <option value="Consultant">Consultant</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="date" id="ex_start_date" name="ext_start_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" id="ex_end_date" name="ext_end_date" class="form-control">
                            <small style="color:#888">Leave blank if current</small>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Years of Experience</label>
                            <input type="number" id="ex_yoe" name="years_of_experience" class="form-control" step="0.1" min="0">
                        </div>
                    </div>
                </div>

                <div class="form-section-title">Compensation & Separation</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Monthly Salary</label>
                            <input type="number" id="ex_salary" name="monthly_salary" class="form-control" step="0.01">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Currency</label>
                            <input type="text" id="ex_currency" name="currency" class="form-control" value="PHP" maxlength="10">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Reason for Leaving</label>
                            <select id="ex_reason" name="reason_for_leaving" class="form-control">
                                <option value="">Select reason...</option>
                                <option value="Resigned">Resigned</option>
                                <option value="End of Contract">End of Contract</option>
                                <option value="Terminated">Terminated</option>
                                <option value="Promoted">Promoted</option>
                                <option value="Transferred">Transferred</option>
                                <option value="Retired">Retired</option>
                                <option value="Business Closure">Business Closure</option>
                                <option value="Personal Reasons">Personal Reasons</option>
                                <option value="Better Opportunity">Better Opportunity</option>
                                <option value="Migration">Migration</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section-title">Responsibilities & Achievements</div>
                <div class="form-group">
                    <label>Key Responsibilities</label>
                    <textarea id="ex_key_resp" name="key_responsibilities" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>Achievements</label>
                    <textarea id="ex_achievements" name="achievements" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>Skills Gained</label>
                    <input type="text" id="ex_skills" name="skills_gained" class="form-control" placeholder="e.g., Project management, AutoCAD, Tax assessment">
                </div>

                <div class="form-section-title">Reference & Verification</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Immediate Supervisor</label>
                            <input type="text" id="ex_supervisor" name="immediate_supervisor" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Supervisor Contact</label>
                            <input type="text" id="ex_sup_contact" name="supervisor_contact" class="form-control" placeholder="Email or phone">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="checkbox-group">
                            <input type="checkbox" id="ex_ref_avail" name="reference_available" value="1" checked>
                            <label for="ex_ref_avail">Reference Available</label>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="checkbox-group">
                            <input type="checkbox" id="ex_verified" name="verified" value="1">
                            <label for="ex_verified">HR Verified</label>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-top:12px;">
                    <label>Remarks</label>
                    <textarea id="ex_remarks" name="ext_remarks" class="form-control"></textarea>
                </div>

                <div style="text-align:center;margin-top:24px;display:flex;gap:12px;justify-content:center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('externalModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">💾 Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: SEMINAR / TRAINING (ADD/EDIT)                                      -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="seminarModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="seminarModalTitle">Add Seminar / Training</h3>
            <button class="close" onclick="closeModal('seminarModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="seminarForm" method="POST">
                <input type="hidden" id="sem_action" name="action" value="add_seminar">
                <input type="hidden" id="seminar_id" name="seminar_id">

                <div class="form-section-title">Training Information</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employee *</label>
                            <select id="sem_employee_id" name="employee_id" class="form-control" required>
                                <option value="">Select employee...</option>
                                <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['employee_id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (#<?= $e['employee_number'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Training Title *</label>
                            <input type="text" id="sem_title" name="sem_title" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Category *</label>
                            <select id="sem_category" name="sem_category" class="form-control" required>
                                <option value="">Select category...</option>
                                <option value="Technical/Skills Training">Technical/Skills Training</option>
                                <option value="Leadership & Management">Leadership & Management</option>
                                <option value="Legal & Compliance">Legal & Compliance</option>
                                <option value="Health & Safety">Health & Safety</option>
                                <option value="Financial Management">Financial Management</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Customer Service">Customer Service</option>
                                <option value="Communication & Soft Skills">Communication & Soft Skills</option>
                                <option value="Civil Service & Governance">Civil Service & Governance</option>
                                <option value="Disaster Risk Reduction">Disaster Risk Reduction</option>
                                <option value="Gender & Development">Gender & Development</option>
                                <option value="Ethics & Anti-Corruption">Ethics & Anti-Corruption</option>
                                <option value="Environmental Management">Environmental Management</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Modality</label>
                            <select id="sem_modality" name="modality" class="form-control">
                                <option value="Face-to-Face">Face-to-Face</option>
                                <option value="Online/Virtual">Online/Virtual</option>
                                <option value="Blended">Blended</option>
                                <option value="Self-paced">Self-paced</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Organizer</label>
                            <input type="text" id="sem_organizer" name="organizer" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Venue</label>
                            <input type="text" id="sem_venue" name="venue" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="date" id="sem_start_date" name="sem_start_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" id="sem_end_date" name="sem_end_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Duration (Hours)</label>
                            <input type="number" id="sem_hours" name="duration_hours" class="form-control" step="0.5" min="0">
                        </div>
                    </div>
                </div>

                <div class="form-section-title">Certificate & Funding</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="checkbox-group">
                            <input type="checkbox" id="sem_cert_received" name="certificate_received" value="1">
                            <label for="sem_cert_received">Certificate Received</label>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Certificate Number</label>
                            <input type="text" id="sem_cert_no" name="certificate_number" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Certificate Expiry</label>
                            <input type="date" id="sem_cert_expiry" name="certificate_expiry" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Funded By</label>
                            <select id="sem_funded_by" name="funded_by" class="form-control">
                                <option value="LGU Budget">LGU Budget</option>
                                <option value="Employee">Employee (Self-funded)</option>
                                <option value="Scholarship/Grant">Scholarship/Grant</option>
                                <option value="CSC">CSC</option>
                                <option value="DILG">DILG</option>
                                <option value="DOH">DOH</option>
                                <option value="DepEd">DepEd</option>
                                <option value="Other Agency">Other Agency</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Amount Spent (₱)</label>
                            <input type="number" id="sem_amount" name="amount_spent" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Learning Outcomes</label>
                    <textarea id="sem_outcomes" name="learning_outcomes" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <textarea id="sem_remarks" name="sem_remarks" class="form-control"></textarea>
                </div>

                <div style="text-align:center;margin-top:24px;display:flex;gap:12px;justify-content:center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('seminarModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">💾 Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: LICENSE / CERTIFICATION (ADD/EDIT)                                 -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="licenseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="licenseModalTitle">Add License / Certification</h3>
            <button class="close" onclick="closeModal('licenseModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="licenseForm" method="POST">
                <input type="hidden" id="lic_action" name="action" value="add_license">
                <input type="hidden" id="license_id" name="license_id">

                <div class="form-section-title">License Details</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Employee *</label>
                            <select id="lic_employee_id" name="employee_id" class="form-control" required>
                                <option value="">Select employee...</option>
                                <?php foreach ($employees as $e): ?>
                                <option value="<?= $e['employee_id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (#<?= $e['employee_number'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>License / Certification Name *</label>
                            <input type="text" id="lic_name" name="license_name" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>License Type *</label>
                            <select id="lic_type" name="license_type" class="form-control" required>
                                <option value="">Select type...</option>
                                <option value="Professional License">Professional License</option>
                                <option value="Board Exam Passer">Board Exam Passer</option>
                                <option value="Civil Service Eligibility">Civil Service Eligibility</option>
                                <option value="Government Certification">Government Certification</option>
                                <option value="Industry Certification">Industry Certification</option>
                                <option value="Academic Credential">Academic Credential</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Issuing Body</label>
                            <input type="text" id="lic_issuer" name="issuing_body" class="form-control" placeholder="e.g., PRC, CSC, TESDA">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>License Number</label>
                            <input type="text" id="lic_number" name="license_number" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Date of Exam</label>
                            <input type="date" id="lic_exam_date" name="date_of_exam" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Date Issued</label>
                            <input type="date" id="lic_issued" name="date_issued" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Exam Rating (%)</label>
                            <input type="number" id="lic_rating" name="rating" class="form-control" step="0.01" min="0" max="100">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" id="lic_expiry" name="expiry_date" class="form-control">
                            <small style="color:#888">Leave blank if no expiry</small>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Renewal Date</label>
                            <input type="date" id="lic_renewal" name="renewal_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Status</label>
                            <select id="lic_status" name="lic_status" class="form-control">
                                <option value="Active">Active</option>
                                <option value="Expired">Expired</option>
                                <option value="Suspended">Suspended</option>
                                <option value="Revoked">Revoked</option>
                                <option value="Pending Renewal">Pending Renewal</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <textarea id="lic_remarks" name="lic_remarks" class="form-control"></textarea>
                </div>

                <div style="text-align:center;margin-top:24px;display:flex;gap:12px;justify-content:center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('licenseModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">💾 Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: AWARDS & RECOGNITION (ADD/EDIT)                                    -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="awardModal" class="modal">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3 id="awardModalTitle">Add Award / Recognition</h3>
            <button class="close" onclick="closeModal('awardModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="awardForm" method="POST">
                <input type="hidden" id="aw_action" name="action" value="add_award">
                <input type="hidden" id="award_id" name="award_id">

                <div class="form-group">
                    <label>Employee *</label>
                    <select id="aw_employee_id" name="employee_id" class="form-control" required>
                        <option value="">Select employee...</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['employee_id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (#<?= $e['employee_number'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Award Title *</label>
                    <input type="text" id="aw_title" name="award_title" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Award Type *</label>
                            <select id="aw_type" name="award_type" class="form-control" required>
                                <option value="">Select type...</option>
                                <option value="Internal Award">Internal Award</option>
                                <option value="External Award">External Award</option>
                                <option value="Presidential/National">Presidential/National</option>
                                <option value="Regional">Regional</option>
                                <option value="Provincial">Provincial</option>
                                <option value="Municipal/City">Municipal/City</option>
                                <option value="Academic">Academic</option>
                                <option value="Community">Community</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Awarding Body</label>
                            <input type="text" id="aw_body" name="awarding_body" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Date Received</label>
                    <input type="date" id="aw_date" name="date_received" class="form-control">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="aw_desc" name="award_description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <textarea id="aw_remarks" name="award_remarks" class="form-control"></textarea>
                </div>

                <div style="text-align:center;margin-top:24px;display:flex;gap:12px;justify-content:center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('awardModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">💾 Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: VOLUNTARY WORK (ADD/EDIT)                                          -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="voluntaryModal" class="modal">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3 id="voluntaryModalTitle">Add Voluntary Work</h3>
            <button class="close" onclick="closeModal('voluntaryModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="voluntaryForm" method="POST">
                <input type="hidden" id="vol_action" name="action" value="add_voluntary">
                <input type="hidden" id="voluntary_id" name="voluntary_id">

                <div class="form-group">
                    <label>Employee *</label>
                    <select id="vol_employee_id" name="employee_id" class="form-control" required>
                        <option value="">Select employee...</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['employee_id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (#<?= $e['employee_number'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Organization *</label>
                    <input type="text" id="vol_org" name="organization" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Position / Nature of Work</label>
                    <input type="text" id="vol_position" name="position_nature_of_work" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" id="vol_start_date" name="vol_start_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" id="vol_end_date" name="vol_end_date" class="form-control">
                            <small style="color:#888">Leave blank if ongoing</small>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Hours per Week</label>
                            <input type="number" id="vol_hours" name="hours_per_week" class="form-control" min="0">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="vol_desc" name="vol_description" class="form-control"></textarea>
                </div>

                <div style="text-align:center;margin-top:24px;display:flex;gap:12px;justify-content:center;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('voluntaryModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">💾 Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: GENERIC VIEW DETAILS                                                -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<div id="detailsModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 id="detailsModalTitle">Details</h3>
            <button class="close" onclick="closeModal('detailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="detailsContent"></div>
    </div>
</div>

<!-- Generic delete form -->
<form id="deleteForm" method="POST" style="display:none">
    <input type="hidden" id="del_action" name="action">
    <input type="hidden" id="del_field"  name="">
    <input type="hidden" id="del_id"     name="">
</form>

<script>
// ── DATA STORES ──────────────────────────────────────────────────────────────
const internalData  = <?= json_encode($employmentHistory) ?>;
const externalData  = <?= json_encode($externalHistory) ?>;
const seminarData   = <?= json_encode($seminars) ?>;
const licenseData   = <?= json_encode($licenses) ?>;
const awardData     = <?= json_encode($awards) ?>;
const voluntaryData = <?= json_encode($voluntaryWork) ?>;

// ── TAB SWITCHING ─────────────────────────────────────────────────────────────
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabId).classList.add('active');
    btn.classList.add('active');
}

// ── MODAL HELPERS ─────────────────────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id).style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = 'auto';
}
window.onclick = function(e) {
    document.querySelectorAll('.modal').forEach(m => {
        if (e.target === m) { m.style.display = 'none'; document.body.style.overflow = 'auto'; }
    });
};

// ── SEARCH HELPERS ────────────────────────────────────────────────────────────
function wireSearch(inputId, bodyId) {
    document.getElementById(inputId).addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#' + bodyId + ' tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
}
wireSearch('searchInternal',  'bodyInternal');
wireSearch('searchExternal',  'bodyExternal');
wireSearch('searchSeminars',  'bodySeminars');
wireSearch('searchLicenses',  'bodyLicenses');
wireSearch('searchAwards',    'bodyAwards');
wireSearch('searchVoluntary', 'bodyVoluntary');

// ── GENERIC DELETE ────────────────────────────────────────────────────────────
function deleteRecord(action, fieldName, id, label) {
    if (!confirm('Are you sure you want to delete ' + label + '? This action cannot be undone.')) return;
    const form = document.getElementById('deleteForm');
    document.getElementById('del_action').value = action;
    // rebuild dynamic hidden field
    form.querySelectorAll('input[type="hidden"]').forEach((el, i) => { if (i > 0) el.remove(); });
    const f1 = document.createElement('input'); f1.type = 'hidden'; f1.name = fieldName; f1.value = id; form.appendChild(f1);
    form.submit();
}

// ── INTERNAL HISTORY ──────────────────────────────────────────────────────────
function editInternalHistory(id) {
    const h = internalData.find(r => r.history_id == id);
    if (!h) return;
    document.getElementById('historyModalTitle').textContent = 'Edit Employment History';
    document.getElementById('ih_action').value    = 'update';
    document.getElementById('history_id').value   = h.history_id;
    document.getElementById('ih_employee_id').value          = h.employee_id || '';
    document.getElementById('ih_job_title').value             = h.job_title || '';
    document.getElementById('ih_salary_grade').value          = h.salary_grade || '';
    document.getElementById('ih_department_id').value         = h.department_id || '';
    document.getElementById('ih_employment_type').value       = h.employment_type || '';
    document.getElementById('ih_start_date').value            = h.start_date || '';
    document.getElementById('ih_end_date').value              = h.end_date || '';
    document.getElementById('ih_employment_status').value     = h.employment_status || '';
    document.getElementById('ih_reporting_manager_id').value  = h.reporting_manager_id || '';
    document.getElementById('ih_location').value              = h.location || '';
    document.getElementById('ih_base_salary').value           = h.base_salary || '';
    document.getElementById('ih_allowances').value            = h.allowances || '0';
    document.getElementById('ih_bonuses').value               = h.bonuses || '0';
    document.getElementById('ih_salary_adjustments').value    = h.salary_adjustments || '0';
    document.getElementById('ih_previous_salary').value       = h.previous_salary || '';
    document.getElementById('ih_salary_effective_date').value = h.salary_effective_date || '';
    document.getElementById('ih_position_sequence').value     = h.position_sequence || '1';
    document.getElementById('ih_promotion_type').value        = h.promotion_type || 'Initial Hire';
    document.getElementById('ih_reason_for_change').value     = h.reason_for_change || '';
    document.getElementById('ih_promotions_transfers').value  = h.promotions_transfers || '';
    document.getElementById('ih_duties_responsibilities').value= h.duties_responsibilities || '';
    document.getElementById('ih_performance_evaluations').value= h.performance_evaluations || '';
    document.getElementById('ih_training_certifications').value= h.training_certifications || '';
    document.getElementById('ih_contract_details').value      = h.contract_details || '';
    document.getElementById('ih_remarks').value               = h.remarks || '';
    openModal('historyModal');
}

function archiveInternal(id) {
    if (!confirm('Archive this employment history record? It will be moved to Archive Storage and can be restored.')) return;
    const f = document.createElement('form'); f.method = 'POST';
    f.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="history_id" value="${id}">`;
    document.body.appendChild(f); f.submit();
}

function viewInternalDetails(id) {
    const h = internalData.find(r => r.history_id == id);
    if (!h) return;
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}) : '—';
    const peso = v => v ? '₱' + parseFloat(v).toLocaleString('en-US',{minimumFractionDigits:2}) : '—';
    const total = (parseFloat(h.base_salary||0)+parseFloat(h.allowances||0)+parseFloat(h.bonuses||0)+parseFloat(h.salary_adjustments||0));

    document.getElementById('detailsModalTitle').textContent = '📋 Internal Employment Record';
    document.getElementById('detailsContent').innerHTML = `
        <div style="background:white;border-radius:12px;padding:24px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">
                <div style="background:linear-gradient(135deg,#f0f4ff 0%,#f8faff 100%);padding:20px;border-radius:10px;border-left:5px solid var(--azure-blue);">
                    <h5 style="color:var(--azure-blue-dark);margin:0 0 16px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">📋 Position Information</h5>
                    <div style="display:grid;gap:14px;">
                        <div style="border-bottom:1px solid rgba(233,30,99,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Employee</span><p style="margin:4px 0 0 0;font-size:15px;color:#333;font-weight:600;">${h.employee_name||'—'} <span style="color:#999;font-weight:400;">#${h.employee_number||'—'}</span></p></div>
                        <div style="border-bottom:1px solid rgba(233,30,99,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Job Title</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${h.job_title||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(233,30,99,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Department</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${h.department_name||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(233,30,99,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Salary Grade</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${h.salary_grade||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(233,30,99,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Employment Type</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${h.employment_type||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(233,30,99,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Location</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${h.location||'—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Reporting Manager</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${h.manager_name||'—'}</p></div>
                    </div>
                </div>
                <div style="background:linear-gradient(135deg,#fff5f8 0%,#fffafc 100%);padding:20px;border-radius:10px;border-left:5px solid #ffc107;">
                    <h5 style="color:#d4860b;margin:0 0 16px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">💰 Compensation Details</h5>
                    <div style="display:grid;gap:14px;">
                        <div style="border-bottom:1px solid rgba(255,193,7,0.2);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Base Salary</span><p style="margin:4px 0 0 0;font-size:15px;color:#333;font-weight:600;">${peso(h.base_salary)}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.2);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Allowances</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${peso(h.allowances)}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.2);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Bonuses</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${peso(h.bonuses)}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.2);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Adjustments</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${peso(h.salary_adjustments)}</p></div>
                        <div style="background:linear-gradient(135deg,var(--azure-blue-pale) 0%,#f0f0f0 100%);padding:12px 14px;border-radius:8px;margin:6px 0 0 0;border-left:4px solid var(--azure-blue);"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Total Compensation</span><p style="margin:6px 0 0 0;font-size:16px;color:var(--azure-blue);font-weight:700;">₱${total.toLocaleString('en-US',{minimumFractionDigits:2})}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.2);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Previous Salary</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${peso(h.previous_salary)}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Effective Date</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${fmt(h.salary_effective_date)}</p></div>
                    </div>
                </div>
            </div>
            <div style="height:2px;background:linear-gradient(90deg,transparent,var(--azure-blue-lighter),transparent);margin:28px 0;"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">
                <div style="background:linear-gradient(135deg,#f5f9ff 0%,#f8faff 100%);padding:20px;border-radius:10px;border-left:5px solid #17a2b8;">
                    <h5 style="color:#0c5460;margin:0 0 14px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">📅 Employment Period</h5>
                    <div style="display:grid;gap:12px;">
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Start Date</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;font-weight:600;">${fmt(h.start_date)}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">End Date</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${h.end_date ? fmt(h.end_date) : '<span style="color:var(--azure-blue);font-weight:700;">Present (Current)</span>'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Status</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${h.employment_status||'—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Promotion Type</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${h.promotion_type||'—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Position Sequence</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">#${h.position_sequence||1}</p></div>
                    </div>
                </div>
                <div style="background:linear-gradient(135deg,#f0fff4 0%,#f5fffb 100%);padding:20px;border-radius:10px;border-left:5px solid #28a745;">
                    <h5 style="color:#1e7e34;margin:0 0 14px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">ℹ️ Additional Information</h5>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Reason for Change</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;line-height:1.5;">${h.reason_for_change||'—'}</p></div>
                </div>
            </div>
            <div style="height:2px;background:linear-gradient(90deg,transparent,var(--azure-blue-lighter),transparent);margin:28px 0;"></div>
            ${h.duties_responsibilities ? `<div style="background:#f8f9fa;padding:18px;border-radius:10px;border-left:5px solid var(--azure-blue);margin-bottom:20px;"><h5 style="color:var(--azure-blue-dark);margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📝 Duties & Responsibilities</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${h.duties_responsibilities}</p></div>` : ''}
            ${h.performance_evaluations ? `<div style="background:#fff5f8;padding:18px;border-radius:10px;border-left:5px solid #ffc107;margin-bottom:20px;"><h5 style="color:#d4860b;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">⭐ Performance Evaluations</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${h.performance_evaluations}</p></div>` : ''}
            ${h.training_certifications ? `<div style="background:#f0f5ff;padding:18px;border-radius:10px;border-left:5px solid #17a2b8;margin-bottom:20px;"><h5 style="color:#0c5460;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">🎓 Training & Certifications</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${h.training_certifications}</p></div>` : ''}
            ${h.contract_details ? `<div style="background:#f5f9ff;padding:18px;border-radius:10px;border-left:5px solid #28a745;margin-bottom:20px;"><h5 style="color:#1e7e34;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📋 Contract Details</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${h.contract_details}</p></div>` : ''}
            ${h.remarks ? `<div style="background:#fffbf0;padding:18px;border-radius:10px;border-left:5px solid #fd7e14;margin-bottom:20px;"><h5 style="color:#b85116;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📌 Remarks</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${h.remarks}</p></div>` : ''}
            ${h.personal_info_id ? `<div style="height:2px;background:linear-gradient(90deg,transparent,var(--azure-blue-lighter),transparent);margin:28px 0;"></div><div style="background:#f0f7ff;padding:16px;border-radius:10px;border-left:5px solid var(--azure-blue);"><h5 style="color:var(--azure-blue-dark);margin:0 0 12px 0;font-weight:700;">🔗 Related Records</h5><div style="display:flex;gap:10px;flex-wrap:wrap;"><a href="personal_information.php?personal_info_id=${h.personal_info_id}" class="btn btn-info" style="font-size:12px;padding:8px 16px;">👤 Personal Info</a><a href="employee_profile.php" class="btn btn-primary" style="font-size:12px;padding:8px 16px;">👨‍💼 Employee Profile</a></div></div>` : ''}
        </div>
    `;
    openModal('detailsModal');
}

// ── EXTERNAL HISTORY ──────────────────────────────────────────────────────────
function editExternal(id) {
    const ex = externalData.find(r => r.ext_history_id == id);
    if (!ex) return;
    document.getElementById('externalModalTitle').textContent = 'Edit Work Experience';
    document.getElementById('ex_action').value      = 'update_external';
    document.getElementById('ext_history_id').value = ex.ext_history_id;
    document.getElementById('ex_employee_id').value   = ex.employee_id || '';
    document.getElementById('ex_employer_name').value = ex.employer_name || '';
    document.getElementById('ex_employer_type').value = ex.employer_type || '';
    document.getElementById('ex_employer_address').value = ex.employer_address || '';
    document.getElementById('ex_job_title').value      = ex.job_title || '';
    document.getElementById('ex_dept').value           = ex.department_or_division || '';
    document.getElementById('ex_employment_type').value= ex.employment_type || '';
    document.getElementById('ex_start_date').value     = ex.start_date || '';
    document.getElementById('ex_end_date').value       = ex.end_date || '';
    document.getElementById('ex_yoe').value            = ex.years_of_experience || '';
    document.getElementById('ex_salary').value         = ex.monthly_salary || '';
    document.getElementById('ex_currency').value       = ex.currency || 'PHP';
    document.getElementById('ex_reason').value         = ex.reason_for_leaving || '';
    document.getElementById('ex_key_resp').value       = ex.key_responsibilities || '';
    document.getElementById('ex_achievements').value   = ex.achievements || '';
    document.getElementById('ex_skills').value         = ex.skills_gained || '';
    document.getElementById('ex_supervisor').value     = ex.immediate_supervisor || '';
    document.getElementById('ex_sup_contact').value    = ex.supervisor_contact || '';
    document.getElementById('ex_ref_avail').checked    = ex.reference_available == 1;
    document.getElementById('ex_verified').checked     = ex.verified == 1;
    document.getElementById('ex_remarks').value        = ex.remarks || '';
    openModal('externalModal');
}

function viewExternal(id) {
    const ex = externalData.find(r => r.ext_history_id == id);
    if (!ex) return;
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}) : '—';
    const peso = v => v ? '₱' + parseFloat(v).toLocaleString('en-US',{minimumFractionDigits:2}) : '—';

    document.getElementById('detailsModalTitle').textContent = '💼 External Work Experience';
    document.getElementById('detailsContent').innerHTML = `
        <div style="background:white;border-radius:12px;padding:24px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">
                <div style="background:linear-gradient(135deg,#f0f4ff 0%,#f8faff 100%);padding:20px;border-radius:10px;border-left:5px solid #1976d2;">
                    <h5 style="color:#1565c0;margin:0 0 16px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">🏢 Employer Details</h5>
                    <div style="display:grid;gap:14px;">
                        <div style="border-bottom:1px solid rgba(25,118,210,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Employer</span><p style="margin:4px 0 0 0;font-size:15px;color:#333;font-weight:600;">${ex.employer_name||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(25,118,210,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Type</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.employer_type||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(25,118,210,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Address</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.employer_address||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(25,118,210,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Department/Division</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.department_or_division||'—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Employee</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.employee_name||'—'} <span style="color:#999;">#${ex.employee_number||'—'}</span></p></div>
                    </div>
                </div>
                <div style="background:linear-gradient(135deg,#fff5f0 0%,#fffaf8 100%);padding:20px;border-radius:10px;border-left:5px solid #fd7e14;">
                    <h5 style="color:#b85116;margin:0 0 16px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">💼 Position & Duration</h5>
                    <div style="display:grid;gap:14px;">
                        <div style="border-bottom:1px solid rgba(253,126,20,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Job Title</span><p style="margin:4px 0 0 0;font-size:15px;color:#333;font-weight:600;">${ex.job_title||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(253,126,20,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Employment Type</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.employment_type||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(253,126,20,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Period</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;font-weight:600;">${fmt(ex.start_date)} – ${ex.end_date ? fmt(ex.end_date) : '<span style="color:var(--azure-blue);">Present</span>'}</p></div>
                        <div style="border-bottom:1px solid rgba(253,126,20,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Years of Experience</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.years_of_experience ? ex.years_of_experience+' years' : '—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Monthly Salary</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;font-weight:600;">${peso(ex.monthly_salary)} <span style="color:#999;font-size:12px;">${ex.currency||'PHP'}</span></p></div>
                    </div>
                </div>
            </div>
            <div style="height:2px;background:linear-gradient(90deg,transparent,var(--azure-blue-lighter),transparent);margin:28px 0;"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">
                <div style="background:#f0fff4;padding:18px;border-radius:10px;border-left:5px solid #28a745;">
                    <h5 style="color:#1e7e34;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">👨‍💼 Supervisor & Reference</h5>
                    <div style="display:grid;gap:12px;">
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Supervisor</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.immediate_supervisor||'—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Contact</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.supervisor_contact||'—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Reference Available</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.reference_available == 1 ? '<span style="color:var(--azure-blue);font-weight:600;">✓ Yes</span>' : 'No'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Verified</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${ex.verified == 1 ? '<span style="color:#28a745;font-weight:600;">✓ Verified</span>' : '<span style="color:#ffc107;font-weight:600;">Pending</span>'}</p></div>
                    </div>
                </div>
                <div style="background:#fff5f8;padding:18px;border-radius:10px;border-left:5px solid #ffc107;">
                    <h5 style="color:#d4860b;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📋 Separation</h5>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Reason for Leaving</span><p style="margin:6px 0 0 0;font-size:13px;color:#555;line-height:1.5;">${ex.reason_for_leaving||'Not specified'}</p></div>
                </div>
            </div>
            <div style="height:2px;background:linear-gradient(90deg,transparent,var(--azure-blue-lighter),transparent);margin:28px 0;"></div>
            ${ex.key_responsibilities ? `<div style="background:#f8f9fa;padding:18px;border-radius:10px;border-left:5px solid #007bff;margin-bottom:20px;"><h5 style="color:#004085;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📋 Key Responsibilities</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${ex.key_responsibilities}</p></div>` : ''}
            ${ex.achievements ? `<div style="background:#fff8e1;padding:18px;border-radius:10px;border-left:5px solid #ffc107;margin-bottom:20px;"><h5 style="color:#7e6008;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">🏆 Achievements</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${ex.achievements}</p></div>` : ''}
            ${ex.skills_gained ? `<div style="background:#e7f3ff;padding:18px;border-radius:10px;border-left:5px solid #1976d2;margin-bottom:20px;"><h5 style="color:#1565c0;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">💡 Skills Gained</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${ex.skills_gained}</p></div>` : ''}
            ${ex.remarks ? `<div style="background:#f5f9ff;padding:18px;border-radius:10px;border-left:5px solid #17a2b8;margin-bottom:20px;"><h5 style="color:#0c5460;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📌 Remarks</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${ex.remarks}</p></div>` : ''}
        </div>
    `;
    openModal('detailsModal');
}

// ── SEMINARS ──────────────────────────────────────────────────────────────────
function editSeminar(id) {
    const s = seminarData.find(r => r.seminar_id == id);
    if (!s) return;
    document.getElementById('seminarModalTitle').textContent = 'Edit Seminar / Training';
    document.getElementById('sem_action').value    = 'update_seminar';
    document.getElementById('seminar_id').value    = s.seminar_id;
    document.getElementById('sem_employee_id').value = s.employee_id || '';
    document.getElementById('sem_title').value       = s.title || '';
    document.getElementById('sem_category').value    = s.category || '';
    document.getElementById('sem_modality').value    = s.modality || 'Face-to-Face';
    document.getElementById('sem_organizer').value   = s.organizer || '';
    document.getElementById('sem_venue').value       = s.venue || '';
    document.getElementById('sem_start_date').value  = s.start_date || '';
    document.getElementById('sem_end_date').value    = s.end_date || '';
    document.getElementById('sem_hours').value       = s.duration_hours || '';
    document.getElementById('sem_cert_received').checked = s.certificate_received == 1;
    document.getElementById('sem_cert_no').value     = s.certificate_number || '';
    document.getElementById('sem_cert_expiry').value = s.certificate_expiry || '';
    document.getElementById('sem_funded_by').value   = s.funded_by || 'LGU Budget';
    document.getElementById('sem_amount').value      = s.amount_spent || '0';
    document.getElementById('sem_outcomes').value    = s.learning_outcomes || '';
    document.getElementById('sem_remarks').value     = s.remarks || '';
    openModal('seminarModal');
}

function viewSeminar(id) {
    const s = seminarData.find(r => r.seminar_id == id);
    if (!s) return;
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}) : '—';

    document.getElementById('detailsModalTitle').textContent = '🎓 Seminar / Training Details';
    document.getElementById('detailsContent').innerHTML = `
        <div style="background:white;border-radius:12px;padding:24px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">
                <div style="background:linear-gradient(135deg,#e0f7fa 0%,#f1f8f9 100%);padding:20px;border-radius:10px;border-left:5px solid #17a2b8;">
                    <h5 style="color:#0c5460;margin:0 0 16px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">🎓 Training Information</h5>
                    <div style="display:grid;gap:14px;">
                        <div style="border-bottom:1px solid rgba(23,162,184,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Title</span><p style="margin:4px 0 0 0;font-size:15px;color:#333;font-weight:600;">${s.title||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(23,162,184,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Category</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${s.category||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(23,162,184,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Organizer</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${s.organizer||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(23,162,184,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Venue</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${s.venue||'—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Modality</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${s.modality||'—'}</p></div>
                    </div>
                </div>
                <div style="background:linear-gradient(135deg,#fff3e0 0%,#fffaf5 100%);padding:20px;border-radius:10px;border-left:5px solid #ffc107;">
                    <h5 style="color:#b89c3a;margin:0 0 16px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">📜 Duration & Dates</h5>
                    <div style="display:grid;gap:14px;">
                        <div style="border-bottom:1px solid rgba(255,193,7,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Duration</span><p style="margin:4px 0 0 0;font-size:15px;color:#333;font-weight:600;">${s.duration_hours ? s.duration_hours+' hours' : '—'}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Start Date</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${fmt(s.start_date)}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">End Date</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${s.end_date&&s.end_date!==s.start_date?fmt(s.end_date):'Same as Start'}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Funded By</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${s.funded_by||'—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Amount Spent</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;font-weight:600;">₱${parseFloat(s.amount_spent||0).toLocaleString('en-US',{minimumFractionDigits:2})}</p></div>
                    </div>
                </div>
            </div>
            <div style="height:2px;background:linear-gradient(90deg,transparent,var(--azure-blue-lighter),transparent);margin:28px 0;"></div>
            <div style="background:#f0f7ff;padding:18px;border-radius:10px;border-left:5px solid #e91e63;margin-bottom:20px;">
                <h5 style="color:#880e4f;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📜 Certificate Information</h5>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Received</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${s.certificate_received ? '<span style="color:#28a745;font-weight:600;">✓ Yes</span>' : 'No'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Certificate Number</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${s.certificate_number||'—'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Expiry Date</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${fmt(s.certificate_expiry)}</p></div>
                </div>
            </div>
            ${s.learning_outcomes ? `<div style="background:#f0fff4;padding:18px;border-radius:10px;border-left:5px solid #28a745;margin-bottom:20px;"><h5 style="color:#1e7e34;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">💡 Learning Outcomes</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${s.learning_outcomes}</p></div>` : ''}
            ${s.remarks ? `<div style="background:#f5f9ff;padding:18px;border-radius:10px;border-left:5px solid #007bff;"><h5 style="color:#004085;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📌 Remarks</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${s.remarks}</p></div>` : ''}
        </div>
    `;
    openModal('detailsModal');
}

// ── LICENSES ──────────────────────────────────────────────────────────────────
function editLicense(id) {
    const l = licenseData.find(r => r.license_id == id);
    if (!l) return;
    document.getElementById('licenseModalTitle').textContent = 'Edit License / Certification';
    document.getElementById('lic_action').value    = 'update_license';
    document.getElementById('license_id').value    = l.license_id;
    document.getElementById('lic_employee_id').value = l.employee_id || '';
    document.getElementById('lic_name').value        = l.license_name || '';
    document.getElementById('lic_type').value        = l.license_type || '';
    document.getElementById('lic_issuer').value      = l.issuing_body || '';
    document.getElementById('lic_number').value      = l.license_number || '';
    document.getElementById('lic_exam_date').value   = l.date_of_exam || '';
    document.getElementById('lic_issued').value      = l.date_issued || '';
    document.getElementById('lic_rating').value      = l.rating || '';
    document.getElementById('lic_expiry').value      = l.expiry_date || '';
    document.getElementById('lic_renewal').value     = l.renewal_date || '';
    document.getElementById('lic_status').value      = l.status || 'Active';
    document.getElementById('lic_remarks').value     = l.remarks || '';
    openModal('licenseModal');
}

function viewLicense(id) {
    const l = licenseData.find(r => r.license_id == id);
    if (!l) return;
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}) : '—';

    document.getElementById('detailsModalTitle').textContent = '📜 License / Certification Details';
    document.getElementById('detailsContent').innerHTML = `
        <div style="background:white;border-radius:12px;padding:24px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">
                <div style="background:linear-gradient(135deg,#e3f2fd 0%,#f3f7fd 100%);padding:20px;border-radius:10px;border-left:5px solid #1976d2;">
                    <h5 style="color:#1565c0;margin:0 0 16px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">📜 License Information</h5>
                    <div style="display:grid;gap:14px;">
                        <div style="border-bottom:1px solid rgba(25,118,210,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">License Name</span><p style="margin:4px 0 0 0;font-size:15px;color:#333;font-weight:600;">${l.license_name||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(25,118,210,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">License Type</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${l.license_type||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(25,118,210,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Issuing Body</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${l.issuing_body||'—'}</p></div>
                        <div style="border-bottom:1px solid rgba(25,118,210,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">License Number</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;font-weight:600;">${l.license_number||'—'}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Status</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${l.status=='Active'?'<span style="color:#28a745;font-weight:600;">● Active</span>':'<span style="color:#dc3545;">● '+l.status+'</span>'||'—'}</p></div>
                    </div>
                </div>
                <div style="background:linear-gradient(135deg,#fff3e0 0%,#fffaf5 100%);padding:20px;border-radius:10px;border-left:5px solid #ffc107;">
                    <h5 style="color:#b89c3a;margin:0 0 16px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">📅 Important Dates</h5>
                    <div style="display:grid;gap:14px;">
                        <div style="border-bottom:1px solid rgba(255,193,7,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Date of Exam</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${fmt(l.date_of_exam)}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Date Issued</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${fmt(l.date_issued)}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Expiry Date</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;font-weight:600;">${l.expiry_date ? fmt(l.expiry_date) : '<span style="color:#28a745;">No expiry</span>'}</p></div>
                        <div style="border-bottom:1px solid rgba(255,193,7,0.1);padding-bottom:12px;"><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Renewal Date</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;">${fmt(l.renewal_date)}</p></div>
                        <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Exam Rating</span><p style="margin:4px 0 0 0;font-size:14px;color:#333;font-weight:600;">${l.rating ? parseFloat(l.rating).toFixed(2)+'%' : '—'}</p></div>
                    </div>
                </div>
            </div>
            <div style="height:2px;background:linear-gradient(90deg,transparent,var(--azure-blue-lighter),transparent);margin:28px 0;"></div>
            ${l.remarks ? `<div style="background:#f5f9ff;padding:18px;border-radius:10px;border-left:5px solid #17a2b8;"><h5 style="color:#0c5460;margin:0 0 12px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📌 Remarks</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${l.remarks}</p></div>` : ''}
        </div>
    `;
    openModal('detailsModal');
}

// ── AWARDS ────────────────────────────────────────────────────────────────────
function editAward(id) {
    const aw = awardData.find(r => r.award_id == id);
    if (!aw) return;
    document.getElementById('awardModalTitle').textContent = 'Edit Award / Recognition';
    document.getElementById('aw_action').value    = 'update_award';
    document.getElementById('award_id').value     = aw.award_id;
    document.getElementById('aw_employee_id').value = aw.employee_id || '';
    document.getElementById('aw_title').value       = aw.award_title || '';
    document.getElementById('aw_type').value        = aw.award_type || '';
    document.getElementById('aw_body').value        = aw.awarding_body || '';
    document.getElementById('aw_date').value        = aw.date_received || '';
    document.getElementById('aw_desc').value        = aw.description || '';
    document.getElementById('aw_remarks').value     = aw.remarks || '';
    openModal('awardModal');
}

function viewAward(id) {
    const aw = awardData.find(r => r.award_id == id);
    if (!aw) return;
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}) : '—';

    document.getElementById('detailsModalTitle').textContent = '🏆 Award & Recognition Details';
    document.getElementById('detailsContent').innerHTML = `
        <div style="background:white;border-radius:12px;padding:24px;">
            <div style="background:linear-gradient(135deg,#fff8e1 0%,#fffbf0 100%);padding:22px;border-radius:10px;border-left:5px solid #ffc107;margin-bottom:24px;">
                <h5 style="color:#d4860b;margin:0 0 18px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">🏆 Award Information</h5>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Award Title</span><p style="margin:6px 0 0 0;font-size:15px;color:#333;font-weight:600;">${aw.award_title||'—'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Award Type</span><p style="margin:6px 0 0 0;font-size:14px;color:#333;">${aw.award_type||'—'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Awarding Body</span><p style="margin:6px 0 0 0;font-size:14px;color:#333;">${aw.awarding_body||'—'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Date Received</span><p style="margin:6px 0 0 0;font-size:14px;color:#333;font-weight:600;">${fmt(aw.date_received)}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Employee</span><p style="margin:6px 0 0 0;font-size:14px;color:#333;">${aw.employee_name||'—'} <span style="color:#999;">#${aw.employee_number||'—'}</span></p></div>
                </div>
            </div>
            ${aw.description ? `<div style="background:#e7f3ff;padding:20px;border-radius:10px;border-left:5px solid #0066cc;margin-bottom:24px;"><h5 style="color:#004085;margin:0 0 14px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📋 Description</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${aw.description}</p></div>` : ''}
            ${aw.remarks ? `<div style="background:#f5f9ff;padding:20px;border-radius:10px;border-left:5px solid #17a2b8;"><h5 style="color:#0c5460;margin:0 0 14px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📌 Remarks</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${aw.remarks}</p></div>` : ''}
        </div>
    `;
    openModal('detailsModal');
}

// ── VOLUNTARY WORK ────────────────────────────────────────────────────────────
function editVoluntary(id) {
    const vw = voluntaryData.find(r => r.voluntary_id == id);
    if (!vw) return;
    document.getElementById('voluntaryModalTitle').textContent = 'Edit Voluntary Work';
    document.getElementById('vol_action').value    = 'update_voluntary';
    document.getElementById('voluntary_id').value  = vw.voluntary_id;
    document.getElementById('vol_employee_id').value = vw.employee_id || '';
    document.getElementById('vol_org').value         = vw.organization || '';
    document.getElementById('vol_position').value    = vw.position_nature_of_work || '';
    document.getElementById('vol_start_date').value  = vw.start_date || '';
    document.getElementById('vol_end_date').value    = vw.end_date || '';
    document.getElementById('vol_hours').value       = vw.hours_per_week || '';
    document.getElementById('vol_desc').value        = vw.description || '';
    openModal('voluntaryModal');
}

function viewVoluntary(id) {
    const vw = voluntaryData.find(r => r.voluntary_id == id);
    if (!vw) return;
    const fmt = d => d ? new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}) : '—';

    document.getElementById('detailsModalTitle').textContent = '🤝 Voluntary Work Details';
    document.getElementById('detailsContent').innerHTML = `
        <div style="background:white;border-radius:12px;padding:24px;">
            <div style="background:linear-gradient(135deg,#f0fff4 0%,#f5fffb 100%);padding:22px;border-radius:10px;border-left:5px solid #28a745;margin-bottom:24px;">
                <h5 style="color:#1e7e34;margin:0 0 18px 0;font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;">🤝 Voluntary Work Information</h5>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:0;">
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Organization</span><p style="margin:6px 0 0 0;font-size:15px;color:#333;font-weight:600;">${vw.organization||'—'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Position / Nature of Work</span><p style="margin:6px 0 0 0;font-size:14px;color:#333;">${vw.position_nature_of_work||'—'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Start Date</span><p style="margin:6px 0 0 0;font-size:14px;color:#333;font-weight:600;">${vw.start_date ? fmt(vw.start_date) : '—'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">End Date</span><p style="margin:6px 0 0 0;font-size:14px;color:#333;">${vw.end_date ? fmt(vw.end_date) : '<span style="color:var(--azure-blue);font-weight:600;">Ongoing</span>'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Hours per Week</span><p style="margin:6px 0 0 0;font-size:14px;color:#333;">${vw.hours_per_week ? vw.hours_per_week+' hrs/week' : '—'}</p></div>
                    <div><span style="font-size:11px;text-transform:uppercase;color:#999;font-weight:700;">Employee</span><p style="margin:6px 0 0 0;font-size:14px;color:#333;">${vw.employee_name||'—'} <span style="color:#999;">#${vw.employee_number||'—'}</span></p></div>
                </div>
            </div>
            ${vw.description ? `<div style="background:#e7f3ff;padding:20px;border-radius:10px;border-left:5px solid #0066cc;"><h5 style="color:#004085;margin:0 0 14px 0;font-weight:700;font-size:13px;text-transform:uppercase;">📋 Description</h5><p style="margin:0;color:#555;font-size:14px;line-height:1.7;">${vw.description}</p></div>` : ''}
        </div>
    `;
    openModal('detailsModal');
}

// ── FORM VALIDATION ───────────────────────────────────────────────────────────
document.getElementById('historyForm').addEventListener('submit', function(e) {
    const start = document.getElementById('ih_start_date').value;
    const end   = document.getElementById('ih_end_date').value;
    const salary= parseFloat(document.getElementById('ih_base_salary').value);
    if (end && start && new Date(end) <= new Date(start)) { e.preventDefault(); alert('End date must be after start date.'); return; }
    if (!salary || salary <= 0) { e.preventDefault(); alert('Base salary must be greater than 0.'); return; }
});

// ── RESET MODALS ON OPEN (ADD MODE) ──────────────────────────────────────────
document.querySelector('[onclick="openModal(\'historyModal\')"]')?.addEventListener('click', function() {
    document.getElementById('historyModalTitle').textContent = 'Add Employment History';
    document.getElementById('ih_action').value  = 'add';
    document.getElementById('history_id').value = '';
    document.getElementById('historyForm').reset();
    document.getElementById('ih_allowances').value = '0';
    document.getElementById('ih_bonuses').value    = '0';
    document.getElementById('ih_salary_adjustments').value = '0';
});
document.querySelector('[onclick="openModal(\'externalModal\')"]')?.addEventListener('click', function() {
    document.getElementById('externalModalTitle').textContent = 'Add Work Experience';
    document.getElementById('ex_action').value      = 'add_external';
    document.getElementById('ext_history_id').value = '';
    document.getElementById('externalForm').reset();
    document.getElementById('ex_currency').value    = 'PHP';
    document.getElementById('ex_ref_avail').checked = true;
});
document.querySelector('[onclick="openModal(\'seminarModal\')"]')?.addEventListener('click', function() {
    document.getElementById('seminarModalTitle').textContent = 'Add Seminar / Training';
    document.getElementById('sem_action').value = 'add_seminar';
    document.getElementById('seminar_id').value = '';
    document.getElementById('seminarForm').reset();
    document.getElementById('sem_modality').value   = 'Face-to-Face';
    document.getElementById('sem_funded_by').value  = 'LGU Budget';
    document.getElementById('sem_amount').value     = '0';
});
document.querySelector('[onclick="openModal(\'licenseModal\')"]')?.addEventListener('click', function() {
    document.getElementById('licenseModalTitle').textContent = 'Add License / Certification';
    document.getElementById('lic_action').value = 'add_license';
    document.getElementById('license_id').value = '';
    document.getElementById('licenseForm').reset();
    document.getElementById('lic_status').value = 'Active';
});
document.querySelector('[onclick="openModal(\'awardModal\')"]')?.addEventListener('click', function() {
    document.getElementById('awardModalTitle').textContent = 'Add Award / Recognition';
    document.getElementById('aw_action').value = 'add_award';
    document.getElementById('award_id').value  = '';
    document.getElementById('awardForm').reset();
});
document.querySelector('[onclick="openModal(\'voluntaryModal\')"]')?.addEventListener('click', function() {
    document.getElementById('voluntaryModalTitle').textContent = 'Add Voluntary Work';
    document.getElementById('vol_action').value   = 'add_voluntary';
    document.getElementById('voluntary_id').value = '';
    document.getElementById('voluntaryForm').reset();
});

// ── AUTO-HIDE ALERTS ──────────────────────────────────────────────────────────
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity 0.5s';
        a.style.opacity = '0';
        setTimeout(() => a.remove(), 500);
    });
}, 5000);
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>