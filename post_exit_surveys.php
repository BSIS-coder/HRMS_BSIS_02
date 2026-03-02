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
require_once 'dp.php';

// Database connection
    $host = getenv('DB_HOST') ?? 'localhost';
    $dbname = getenv('DB_NAME') ?? 'hr_system';
    $username = getenv('DB_USER') ?? 'root';
    $password = getenv('DB_PASS') ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// --- Ensure required tables and columns exist ---
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS exit_survey_tokens (
            token_id INT AUTO_INCREMENT PRIMARY KEY,
            exit_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            is_used TINYINT(1) NOT NULL DEFAULT 0,
            used_at DATETIME NULL,
            INDEX (token),
            INDEX (exit_id)
        )
    ");
    // survey_notifications: tracks when HR manually sends a survey to an employee
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS survey_notifications (
            notif_id INT AUTO_INCREMENT PRIMARY KEY,
            exit_id INT NOT NULL UNIQUE,
            sent_by_user_id INT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (exit_id)
        )
    ");
    // Add submitted_by_employee column if not exists
    $cols = $pdo->query("SHOW COLUMNS FROM post_exit_surveys LIKE 'submitted_by_employee'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE post_exit_surveys ADD COLUMN submitted_by_employee TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (PDOException $e) {
    // Tables may already exist, continue
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {

            case 'send_survey':
                // HR manually sends a survey notification to the employee
                try {
                    $exitId = intval($_POST['exit_id']);
                    $sentBy = $_SESSION['user_id'] ?? null;
                    $stmt = $pdo->prepare("
                        INSERT INTO survey_notifications (exit_id, sent_by_user_id, sent_at)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE sent_at = NOW(), sent_by_user_id = ?
                    ");
                    $stmt->execute([$exitId, $sentBy, $sentBy]);
                    $_SESSION['message'] = "Survey notification sent to employee successfully!";
                    $_SESSION['messageType'] = "success";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error sending notification: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }

            case 'generate_token':
                // Generate survey link token for an exit record
                try {
                    $exitId = intval($_POST['exit_id_token']);
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $stmt = $pdo->prepare("INSERT INTO exit_survey_tokens (exit_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$exitId, $token, $expiresAt]);
                    $_SESSION['message'] = "Survey link generated! Token: " . $token;
                    $_SESSION['messageType'] = "success";
                    $_SESSION['generated_token'] = $token;
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error generating token: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }

            case 'add':
                try {
                    $stmt_exit = $pdo->prepare("SELECT employee_id FROM exits WHERE exit_id = ?");
                    $stmt_exit->execute([$_POST['exit_id']]);
                    $exit_data = $stmt_exit->fetch(PDO::FETCH_ASSOC);
                    $employee_id = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] ? null : $exit_data['employee_id'];
                    $stmt = $pdo->prepare("INSERT INTO post_exit_surveys (employee_id, exit_id, survey_date, survey_response, satisfaction_rating, submitted_date, is_anonymous, evaluation_score, evaluation_criteria, submitted_by_employee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([
                        $employee_id,
                        $_POST['exit_id'],
                        $_POST['survey_date'],
                        $_POST['survey_response'],
                        $_POST['satisfaction_rating'],
                        $_POST['submitted_date'],
                        isset($_POST['is_anonymous']) ? 1 : 0,
                        isset($_POST['evaluation_score']) ? $_POST['evaluation_score'] : 0,
                        isset($_POST['evaluation_criteria']) ? $_POST['evaluation_criteria'] : null
                    ]);
                    $_SESSION['message'] = "Post-exit survey added successfully!";
                    $_SESSION['messageType'] = "success";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error adding survey: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }

            case 'update':
                try {
                    $stmt_exit = $pdo->prepare("SELECT employee_id FROM exits WHERE exit_id = ?");
                    $stmt_exit->execute([$_POST['exit_id']]);
                    $exit_data = $stmt_exit->fetch(PDO::FETCH_ASSOC);
                    $employee_id = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] ? null : $exit_data['employee_id'];
                    $stmt = $pdo->prepare("UPDATE post_exit_surveys SET employee_id=?, exit_id=?, survey_date=?, survey_response=?, satisfaction_rating=?, submitted_date=?, is_anonymous=?, evaluation_score=?, evaluation_criteria=? WHERE survey_id=?");
                    $stmt->execute([
                        $employee_id,
                        $_POST['exit_id'],
                        $_POST['survey_date'],
                        $_POST['survey_response'],
                        $_POST['satisfaction_rating'],
                        $_POST['submitted_date'],
                        isset($_POST['is_anonymous']) ? 1 : 0,
                        isset($_POST['evaluation_score']) ? $_POST['evaluation_score'] : 0,
                        isset($_POST['evaluation_criteria']) ? $_POST['evaluation_criteria'] : null,
                        $_POST['survey_id']
                    ]);
                    $_SESSION['message'] = "Post-exit survey updated successfully!";
                    $_SESSION['messageType'] = "success";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error updating survey: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }

            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM post_exit_surveys WHERE survey_id=?");
                    $stmt->execute([$_POST['survey_id']]);
                    $_SESSION['message'] = "Post-exit survey deleted successfully!";
                    $_SESSION['messageType'] = "success";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['message'] = "Error deleting survey: " . $e->getMessage();
                    $_SESSION['messageType'] = "error";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
                break;
        }
    }
}

// Get message from session if exists
$message = '';
$messageType = '';
$generatedToken = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}
if (isset($_SESSION['generated_token'])) {
    $generatedToken = $_SESSION['generated_token'];
    unset($_SESSION['generated_token']);
}

// Fetch surveys with related data
$stmt = $pdo->query("
    SELECT 
        pes.*,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number,
        jr.title as job_title,
        jr.department,
        ex.exit_date,
        ex.exit_type
    FROM post_exit_surveys pes
    LEFT JOIN employee_profiles ep ON pes.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    LEFT JOIN exits ex ON pes.exit_id = ex.exit_id
    ORDER BY pes.survey_id DESC
");
$surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate employee-submitted surveys
$employeeSubmitted = array_filter($surveys, fn($s) => !empty($s['submitted_by_employee']) && $s['submitted_by_employee'] == 1);
$adminSurveys = array_filter($surveys, fn($s) => empty($s['submitted_by_employee']) || $s['submitted_by_employee'] == 0);

// Stats
$totalSurveys = count($surveys);
$employeeCount = count($employeeSubmitted);
$avgRating = $totalSurveys > 0 ? round(array_sum(array_column($surveys, 'satisfaction_rating')) / $totalSurveys, 1) : 0;
$anonymousCount = count(array_filter($surveys, fn($s) => $s['is_anonymous']));

// Fetch exits for dropdowns — include notification sent_at and survey submission status
$stmt = $pdo->query("
    SELECT 
        ex.exit_id,
        ex.employee_id,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ex.exit_date,
        ex.exit_type,
        sn.sent_at as survey_sent_at,
        (SELECT COUNT(*) FROM post_exit_surveys pes WHERE pes.exit_id = ex.exit_id) as survey_submitted
    FROM exits ex
    LEFT JOIN employee_profiles ep ON ex.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN survey_notifications sn ON sn.exit_id = ex.exit_id
    ORDER BY ex.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active tokens
$tokens = $pdo->query("
    SELECT est.*, CONCAT(pi.first_name, ' ', pi.last_name) as employee_name, ex.exit_date
    FROM exit_survey_tokens est
    JOIN exits ex ON est.exit_id = ex.exit_id
    JOIN employee_profiles ep ON ex.employee_id = ep.employee_id
    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY est.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/employee_survey.php?token=';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Exit Surveys Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        .section-title { color: var(--azure-blue); margin-bottom: 30px; font-weight: 600; }
        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }
        body { background: var(--azure-blue-pale); }
        .main-content { background: var(--azure-blue-pale); padding: 20px; }

        /* Stats Strip */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 14px;
            border-left: 4px solid transparent;
        }

        .stat-card.pink { border-left-color: var(--azure-blue); }
        .stat-card.blue { border-left-color: #2196F3; }
        .stat-card.green { border-left-color: #4CAF50; }
        .stat-card.purple { border-left-color: #9c27b0; }

        .stat-icon {
            font-size: 28px;
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-card.pink .stat-icon { background: var(--azure-blue-pale); }
        .stat-card.blue .stat-icon { background: #e3f2fd; }
        .stat-card.green .stat-icon { background: #e8f5e9; }
        .stat-card.purple .stat-icon { background: #f3e5f5; }

        .stat-val { font-size: 26px; font-weight: 700; color: #1a1a2e; line-height: 1; }
        .stat-lbl { font-size: 12px; color: #6b7280; margin-top: 3px; }

        /* Employee Submissions Banner */
        .emp-submissions-banner {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            border-radius: 16px;
            padding: 28px 32px;
            margin-bottom: 24px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .emp-submissions-banner::before {
            content: '';
            position: absolute; top: -40px; right: -40px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(233,30,99,0.15);
        }

        .emp-submissions-banner::after {
            content: '';
            position: absolute; bottom: -30px; right: 80px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(233,30,99,0.1);
        }

        .emp-banner-content { position: relative; z-index: 1; }
        .emp-banner-content h3 { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        .emp-banner-content p { font-size: 14px; opacity: 0.8; margin: 0; }

        .emp-badge {
            display: inline-block;
            background: var(--azure-blue);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }

        /* Employee submission card */
        .emp-survey-card {
            background: white;
            border-radius: 14px;
            padding: 22px 26px;
            margin-bottom: 16px;
            box-shadow: 0 3px 14px rgba(0,0,0,0.06);
            border-left: 5px solid var(--azure-blue);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .emp-survey-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .emp-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .emp-card-identity h4 { font-size: 16px; font-weight: 700; color: #1a1a2e; margin: 0 0 4px; }
        .emp-card-identity small { color: #6b7280; font-size: 12px; }

        .emp-card-badges { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }

        .tag {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .tag-emp { background: #e3f2fd; color: #1565c0; }
        .tag-anon { background: #ede7f6; color: #4527a0; }
        .tag-excellent { background: #e8f5e9; color: #1b5e20; }
        .tag-good { background: #e3f2fd; color: #0d47a1; }
        .tag-average { background: #fff8e1; color: #e65100; }
        .tag-poor { background: #fce4ec; color: #880e4f; }

        .emp-card-body { display: flex; gap: 24px; flex-wrap: wrap; }

        .emp-card-section { flex: 1; min-width: 200px; }
        .emp-card-section label { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }

        .star-display { font-size: 18px; color: #f59e0b; }
        .star-display .empty { color: #e5e7eb; }

        .response-snippet {
            background: #f9fafb;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: #374151;
            line-height: 1.6;
            max-height: 80px;
            overflow: hidden;
            position: relative;
        }

        .response-snippet::after {
            content: '';
            position: absolute; bottom: 0; left: 0; right: 0;
            height: 30px;
            background: linear-gradient(transparent, #f9fafb);
        }

        .emp-card-actions { margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9; display: flex; gap: 8px; }

        /* Controls */
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box { position: relative; flex: 1; max-width: 400px; }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus { border-color: var(--azure-blue); outline: none; box-shadow: 0 0 10px rgba(233,30,99,0.3); }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #666; }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(233,30,99,0.4); }
        .btn-success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
        .btn-danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: white; }
        .btn-info { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; }
        .btn-dark { background: linear-gradient(135deg, #343a40 0%, #1d2124 100%); color: white; }
        .btn-small { padding: 8px 15px; font-size: 14px; margin: 0 3px; }

        .table-container { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%); padding: 15px; text-align: left; font-weight: 600; color: var(--azure-blue-dark); border-bottom: 2px solid #dee2e6; }
        .table td { padding: 15px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        .table tbody tr:hover { background-color: var(--azure-blue-lighter); transition: all 0.2s ease; }

        .rating-stars { color: #ffc107; font-size: 18px; }
        .rating-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .rating-excellent { background: #d4edda; color: #155724; }
        .rating-good { background: #d1ecf1; color: #0c5460; }
        .rating-average { background: #fff3cd; color: #856404; }
        .rating-poor { background: #f8d7da; color: #721c24; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }
        .modal-content { background: white; margin: 5% auto; padding: 0; border-radius: 15px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: slideIn 0.3s ease; }

        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; padding: 20px 30px; border-radius: 15px 15px 0 0; }
        .modal-header h2 { margin: 0; }
        .close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: white; opacity: 0.7; }
        .close:hover { opacity: 1; }
        .modal-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--azure-blue-dark); }
        .form-control { width: 100%; padding: 6px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: all 0.3s ease; }
        .form-control:focus { border-color: var(--azure-blue); outline: none; box-shadow: 0 0 10px rgba(233,30,99,0.3); }
        textarea.form-control { min-height: 120px; resize: vertical; }
        .form-row { display: flex; gap: 20px; }
        .form-col { flex: 1; }

        .rating-input { display: flex; gap: 10px; align-items: center; }
        .rating-input input[type="radio"] { display: none; }
        .rating-input label { font-size: 28px; color: #ddd; cursor: pointer; transition: all 0.2s ease; margin: 0; }
        .rating-input label:hover, .rating-input input[type="radio"]:checked ~ label, .rating-input label.active { color: #ffc107; transform: scale(1.1); }

        .alert { padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .no-results { text-align: center; padding: 50px; color: #666; }
        .no-results i { font-size: 4rem; margin-bottom: 20px; color: #ddd; }

        .survey-preview { background: #f8f9fa; padding: 15px; border-radius: 8px; max-width: 300px; white-space: pre-wrap; word-wrap: break-word; }

        .flowchart-container { background: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .flowchart { display: flex; align-items: center; justify-content: space-around; flex-wrap: wrap; gap: 20px; padding: 20px; }
        .flowchart-step { background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%); padding: 20px; border-radius: 10px; text-align: center; min-width: 150px; border: 2px solid var(--azure-blue); position: relative; }
        .flowchart-step.active { background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%); color: white; border-color: var(--azure-blue-dark); }
        .flowchart-arrow { font-size: 24px; color: var(--azure-blue); }
        .anonymous-badge { background: #6c757d; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; margin-left: 10px; }
        .evaluation-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .evaluation-criteria { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0; }
        .evaluation-item { display: flex; align-items: center; gap: 10px; }
        .evaluation-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .evaluation-item label { margin: 0; cursor: pointer; font-weight: normal; }
        .evaluation-score-slider { width: 100%; height: 6px; border-radius: 3px; background: #ddd; outline: none; -webkit-appearance: none; }
        .evaluation-score-slider::-webkit-slider-thumb { -webkit-appearance: none; width: 20px; height: 20px; border-radius: 50%; background: var(--azure-blue); cursor: pointer; }
        .score-display { text-align: center; font-size: 18px; font-weight: 600; color: var(--azure-blue); margin-top: 10px; }

        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e0e0e0; flex-wrap: wrap; }
        .tab-btn { padding: 12px 20px; background: none; border: none; cursor: pointer; font-weight: 600; color: #666; border-bottom: 3px solid transparent; transition: all 0.3s ease; }
        .tab-btn.active { color: var(--azure-blue); border-bottom-color: var(--azure-blue); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Token management */
        .token-card {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .token-info h5 { font-size: 14px; font-weight: 700; margin: 0 0 4px; color: #1a1a2e; }
        .token-info small { color: #6b7280; font-size: 12px; }

        .token-link-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 8px 14px;
            flex: 1;
            min-width: 200px;
        }

        .token-link-box input {
            background: none;
            border: none;
            outline: none;
            font-size: 12px;
            color: #374151;
            width: 100%;
            font-family: monospace;
        }

        .copy-btn {
            background: var(--azure-blue);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            cursor: pointer;
            flex-shrink: 0;
            font-weight: 600;
            transition: all 0.2s;
        }

        .copy-btn:hover { background: var(--azure-blue-dark); }
        .copy-btn.copied { background: #28a745; }

        .token-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .token-status.active { background: #d4edda; color: #155724; }
        .token-status.used { background: #e2e8f0; color: #475569; }
        .token-status.expired { background: #f8d7da; color: #721c24; }

        /* New token banner */
        .new-token-banner {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }

        .new-token-banner h4 { margin: 0 0 8px; font-size: 15px; }
        .new-token-banner .link-display { font-family: monospace; font-size: 12px; word-break: break-all; background: rgba(255,255,255,0.15); padding: 10px 14px; border-radius: 8px; margin-top: 10px; display: flex; align-items: center; gap: 10px; }

        @media (max-width: 768px) {
            .stats-strip { grid-template-columns: 1fr 1fr; }
            .controls { flex-direction: column; align-items: stretch; }
            .search-box { max-width: none; }
            .form-row { flex-direction: column; }
            .table-container { overflow-x: auto; }
            .evaluation-criteria { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Post-Exit Surveys Management</h2>
                <div class="content">

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($generatedToken): ?>
                    <div class="new-token-banner">
                        <h4>✅ Survey Link Generated — Share this with the employee:</h4>
                        <div class="link-display">
                            <span id="newTokenLink"><?= htmlspecialchars($baseUrl . $generatedToken) ?></span>
                            <button class="copy-btn" onclick="copyLink('newTokenLink', this)" style="background: rgba(255,255,255,0.25); border: 1px solid rgba(255,255,255,0.4); flex-shrink:0;">📋 Copy</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Stats -->
                    <div class="stats-strip">
                        <div class="stat-card pink">
                            <div class="stat-icon">📋</div>
                            <div>
                                <div class="stat-val"><?= $totalSurveys ?></div>
                                <div class="stat-lbl">Total Surveys</div>
                            </div>
                        </div>
                        <div class="stat-card blue">
                            <div class="stat-icon">👤</div>
                            <div>
                                <div class="stat-val"><?= $employeeCount ?></div>
                                <div class="stat-lbl">Employee Submissions</div>
                            </div>
                        </div>
                        <div class="stat-card green">
                            <div class="stat-icon">⭐</div>
                            <div>
                                <div class="stat-val"><?= $avgRating ?></div>
                                <div class="stat-lbl">Avg. Satisfaction</div>
                            </div>
                        </div>
                        <div class="stat-card purple">
                            <div class="stat-icon">🔒</div>
                            <div>
                                <div class="stat-val"><?= $anonymousCount ?></div>
                                <div class="stat-lbl">Anonymous</div>
                            </div>
                        </div>
                    </div>

                    <!-- Process Flowchart -->
                    <div class="flowchart-container">
                        <h3 style="color: var(--azure-blue); margin-bottom: 20px;">📊 Survey Process Workflow</h3>
                        <div class="flowchart">
                            <div class="flowchart-step active">
                                <strong>1. Employee Exit</strong><br>
                                <small>Employee resigns/terminates</small>
                            </div>
                            <div class="flowchart-arrow">→</div>
                            <div class="flowchart-step active">
                                <strong>2. Generate Link</strong><br>
                                <small>HR creates survey token</small>
                            </div>
                            <div class="flowchart-arrow">→</div>
                            <div class="flowchart-step">
                                <strong>3. Employee Fills</strong><br>
                                <small>
                                    <span style="color:#6c757d;"><b>Anonymous</b> or Identified</span>
                                </small>
                                <div style="margin-top:5px;">
                                    <span class="anonymous-badge" style="background:#e7d4f5;color:#6f42c1;margin-left:0;">🔒 Anon</span>
                                    <span class="anonymous-badge" style="background:#d1ecf1;color:#0c5460;margin-left:4px;">📝 ID</span>
                                </div>
                            </div>
                            <div class="flowchart-arrow">→</div>
                            <div class="flowchart-step">
                                <strong>4. HR Reviews</strong><br>
                                <small>View in <b>Employee Submissions</b> tab</small>
                                <div style="margin-top:5px;">
                                    <span class="rating-badge rating-excellent">⭐ Rating</span>
                                </div>
                            </div>
                            <div class="flowchart-arrow">→</div>
                            <div class="flowchart-step">
                                <strong>5. Analyze & Act</strong><br>
                                <small>Improve processes</small>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="tabs">
                        <button class="tab-btn active" onclick="switchTab('employee-submissions', this)">👤 Employee Submissions <span style="background:var(--azure-blue);color:white;border-radius:10px;padding:2px 8px;font-size:11px;margin-left:4px;"><?= $employeeCount ?></span></button>
                        <button class="tab-btn" onclick="switchTab('anonymous-surveys', this)">🔒 Anonymous Only</button>
                        <button class="tab-btn" onclick="switchTab('evaluations', this)">� Evaluations</button>
                        <button class="tab-btn" onclick="switchTab('survey-links', this)">� Survey Links</button>
                    </div>

                    <!-- ===== EMPLOYEE SUBMISSIONS TAB ===== -->
                    <div id="employee-submissions" class="tab-content active">
                        <div class="emp-submissions-banner">
                            <div class="emp-banner-content">
                                <h3>📬 Surveys Submitted by Employees</h3>
                                <p>These surveys were filled out directly by employees through their personal survey links. Responses reflect genuine, first-hand feedback about their experience.</p>
                                <div style="margin-top:12px;">
                                    <span class="emp-badge">👤 Employee-Submitted</span>
                                    <span style="font-size:13px;opacity:0.7;">Total: <?= $employeeCount ?> response<?= $employeeCount !== 1 ? 's' : '' ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($employeeSubmitted)): ?>
                        <div class="no-results" style="background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.06);">
                            <i>📭</i>
                            <h3>No Employee Submissions Yet</h3>
                            <p>Generate a survey link from the <strong>Survey Links</strong> tab and share it with a departing employee.<br>Their completed survey will appear here.</p>
                            <button class="btn btn-primary" style="margin-top:16px;" onclick="switchTab('survey-links', null)">🔗 Go to Survey Links</button>
                        </div>
                        <?php else: ?>

                        <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                            <div class="search-box" style="max-width:350px;">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="empSearchInput" placeholder="Search employee submissions..." oninput="filterEmpCards()">
                            </div>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <label style="font-size:13px;font-weight:600;color:#6b7280;">Sort:</label>
                                <select class="form-control" style="width:auto;padding:8px 12px;" onchange="sortEmpCards(this.value)">
                                    <option value="newest">Newest First</option>
                                    <option value="highest">Highest Rating</option>
                                    <option value="lowest">Lowest Rating</option>
                                </select>
                            </div>
                        </div>

                        <div id="empCardsContainer">
                        <?php foreach ($employeeSubmitted as $survey): ?>
                        <?php
                            $rating = intval($survey['satisfaction_rating']);
                            $ratingClass = $rating >= 4 ? 'excellent' : ($rating == 3 ? 'good' : ($rating == 2 ? 'average' : 'poor'));
                            $ratingLabels = [1 => 'Very Dissatisfied', 2 => 'Dissatisfied', 3 => 'Neutral', 4 => 'Satisfied', 5 => 'Very Satisfied'];
                        ?>
                        <div class="emp-survey-card" 
                             data-name="<?= htmlspecialchars(strtolower($survey['employee_name'] ?? '')) ?>"
                             data-rating="<?= $rating ?>"
                             data-date="<?= $survey['submitted_date'] ?>">
                            <div class="emp-card-header">
                                <div class="emp-card-identity">
                                    <?php if ($survey['is_anonymous']): ?>
                                    <h4>🔒 Anonymous Employee</h4>
                                    <small>Identity hidden by request · <?= htmlspecialchars($survey['exit_type'] ?? 'Exit') ?></small>
                                    <?php else: ?>
                                    <h4><?= htmlspecialchars($survey['employee_name']) ?></h4>
                                    <small>
                                        <?= htmlspecialchars($survey['employee_number'] ?? '') ?>
                                        <?php if ($survey['job_title']): ?> · <?= htmlspecialchars($survey['job_title']) ?><?php endif; ?>
                                        <?php if ($survey['department']): ?> · <?= htmlspecialchars($survey['department']) ?><?php endif; ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <div class="emp-card-badges">
                                    <span class="tag tag-emp">👤 Employee Submitted</span>
                                    <?php if ($survey['is_anonymous']): ?>
                                    <span class="tag tag-anon">🔒 Anonymous</span>
                                    <?php endif; ?>
                                    <span class="tag tag-<?= $ratingClass ?>"><?= $rating ?>/5 — <?= $ratingLabels[$rating] ?? '' ?></span>
                                    <span style="font-size:12px;color:#9ca3af;">📅 <?= date('M d, Y', strtotime($survey['submitted_date'])) ?></span>
                                </div>
                            </div>

                            <div class="emp-card-body">
                                <div class="emp-card-section">
                                    <label>Satisfaction</label>
                                    <div class="star-display">
                                        <?php for ($i=1; $i<=5; $i++): ?>
                                        <span class="<?= $i <= $rating ? '' : 'empty' ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="emp-card-section">
                                    <label>Exit Details</label>
                                    <div style="font-size:13px;color:#374151;">
                                        🔖 <?= htmlspecialchars($survey['exit_type'] ?? 'N/A') ?><br>
                                        📅 Exit: <?= date('M d, Y', strtotime($survey['exit_date'])) ?>
                                    </div>
                                </div>
                                <?php if ($survey['evaluation_score'] > 0): ?>
                                <div class="emp-card-section">
                                    <label>Self-Eval Score</label>
                                    <span style="font-size:22px;font-weight:700;color:var(--azure-blue);"><?= $survey['evaluation_score'] ?><span style="font-size:14px;color:#9ca3af;">/10</span></span>
                                    <?php if ($survey['evaluation_criteria']): ?>
                                    <div style="font-size:11px;color:#6b7280;margin-top:4px;"><?= htmlspecialchars($survey['evaluation_criteria']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div class="emp-card-section" style="flex:2;min-width:260px;">
                                    <label>Response</label>
                                    <div class="response-snippet"><?= nl2br(htmlspecialchars(substr($survey['survey_response'], 0, 200))) ?></div>
                                </div>
                            </div>

                            <div class="emp-card-actions">
                                <button class="btn btn-info btn-small" onclick="viewSurvey(<?= $survey['survey_id'] ?>)">
                                    👁️ View Full Response
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- All Surveys Tab -->
                    <div id="all-surveys" class="tab-content">
                        <div class="controls">
                            <div class="search-box">
                                <span class="search-icon">🔍</span>
                                <input type="text" id="searchInput" placeholder="Search surveys...">
                            </div>
                            <button class="btn btn-primary" onclick="openModal('add')">➕ Add New Survey</button>
                        </div>
                        <div class="table-container">
                            <table class="table" id="surveyTable">
                                <thead>
                                    <tr>
                                        <th>Survey ID</th>
                                        <th>Employee</th>
                                        <th>Source</th>
                                        <th>Exit Date</th>
                                        <th>Survey Date</th>
                                        <th>Rating</th>
                                        <th>Evaluation</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="surveyTableBody">
                                    <?php foreach ($surveys as $survey): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($survey['survey_id']) ?></strong></td>
                                        <td>
                                            <?php if ($survey['is_anonymous']): ?>
                                                <span class="anonymous-badge">🔒 Anonymous</span>
                                            <?php else: ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($survey['employee_name']) ?></strong><br>
                                                    <small style="color:#666;">👤 <?= htmlspecialchars($survey['employee_number']) ?></small><br>
                                                    <small style="color:#666;">💼 <?= htmlspecialchars($survey['job_title']) ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($survey['submitted_by_employee'])): ?>
                                                <span class="tag tag-emp">👤 Employee</span>
                                            <?php else: ?>
                                                <span style="font-size:12px;color:#6b7280;">🏢 HR Added</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($survey['exit_date'])) ?></td>
                                        <td><?= date('M d, Y', strtotime($survey['survey_date'])) ?></td>
                                        <td>
                                            <div class="rating-stars">
                                                <?php $r = $survey['satisfaction_rating']; for ($i=1;$i<=5;$i++) echo $i<=$r?'⭐':'☆'; ?>
                                            </div>
                                            <span class="rating-badge rating-<?= $r>=4?'excellent':($r==3?'good':($r==2?'average':'poor')) ?>"><?= $r ?>/5</span>
                                        </td>
                                        <td>
                                            <?php if ($survey['evaluation_score'] > 0): ?>
                                                <span class="rating-badge rating-excellent">Score: <?= $survey['evaluation_score'] ?>/10</span>
                                            <?php else: ?><span style="color:#999;">—</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($survey['is_anonymous']): ?>
                                                <span style="background:#e7d4f5;color:#6f42c1;padding:5px 10px;border-radius:5px;font-size:12px;">🔐 Anon</span>
                                            <?php else: ?>
                                                <span style="background:#d1ecf1;color:#0c5460;padding:5px 10px;border-radius:5px;font-size:12px;">📝 Identified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-info btn-small" onclick="viewSurvey(<?= $survey['survey_id'] ?>)">👁️</button>
                                            <button class="btn btn-warning btn-small" onclick="editSurvey(<?= $survey['survey_id'] ?>)">✏️</button>
                                            <button class="btn btn-danger btn-small" onclick="deleteSurvey(<?= $survey['survey_id'] ?>)">🗑️</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($surveys)): ?>
                            <div class="no-results"><i>📋</i><h3>No surveys found</h3></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Anonymous Tab -->
                    <div id="anonymous-surveys" class="tab-content">
                        <div class="table-container">
                            <table class="table">
                                <thead><tr><th>Survey ID</th><th>Exit Date</th><th>Rating</th><th>Evaluation</th><th>Submitted</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($surveys as $survey): if ($survey['is_anonymous']): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($survey['survey_id']) ?></strong></td>
                                        <td><?= date('M d, Y', strtotime($survey['exit_date'])) ?></td>
                                        <td><div class="rating-stars"><?php for($i=1;$i<=5;$i++) echo $i<=$survey['satisfaction_rating']?'⭐':'☆'; ?></div></td>
                                        <td><?= $survey['evaluation_score']>0 ? '<span class="rating-badge rating-excellent">Score: '.$survey['evaluation_score'].'/10</span>' : '<span style="color:#999;">—</span>' ?></td>
                                        <td><?= date('M d, Y', strtotime($survey['submitted_date'])) ?></td>
                                        <td>
                                            <button class="btn btn-info btn-small" onclick="viewSurvey(<?= $survey['survey_id'] ?>)">👁️ View</button>
                                        </td>
                                    </tr>
                                    <?php endif; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Evaluations Tab -->
                    <div id="evaluations" class="tab-content">
                        <div class="table-container">
                            <table class="table">
                                <thead><tr><th>Survey ID</th><th>Employee</th><th>Source</th><th>Eval Score</th><th>Criteria</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($surveys as $survey): if ($survey['evaluation_score'] > 0): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($survey['survey_id']) ?></strong></td>
                                        <td><?= !$survey['is_anonymous'] ? htmlspecialchars($survey['employee_name']) : '<span class="anonymous-badge">Anon</span>' ?></td>
                                        <td><?= !empty($survey['submitted_by_employee']) ? '<span class="tag tag-emp">👤 Employee</span>' : '<span style="font-size:12px;color:#6b7280;">HR Added</span>' ?></td>
                                        <td><strong style="color:var(--azure-blue);font-size:18px;"><?= $survey['evaluation_score'] ?></strong><span style="color:#9ca3af;">/10</span></td>
                                        <td><small><?= htmlspecialchars(substr($survey['evaluation_criteria'] ?? '', 0, 60)) ?>...</small></td>
                                        <td>
                                            <button class="btn btn-info btn-small" onclick="viewSurvey(<?= $survey['survey_id'] ?>)">👁️ View</button>
                                        </td>
                                    </tr>
                                    <?php endif; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Survey Links Tab -->
                    <div id="survey-links" class="tab-content">

                        <!-- SEND SURVEY NOTIFICATION -->
                        <div style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);border-radius:16px;padding:28px 32px;margin-bottom:24px;color:white;position:relative;overflow:hidden;">
                            <div style="position:absolute;top:-30px;right:-30px;width:120px;height:120px;border-radius:50%;background:rgba(233,30,99,0.15);"></div>
                            <div style="position:relative;z-index:1;">
                                <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
                                    <span style="font-size:22px;">📣</span>
                                    <h4 style="margin:0;font-weight:700;font-size:18px;">Send Survey Notification to Employee</h4>
                                </div>
                                <p style="font-size:13px;opacity:.8;margin-bottom:20px;">
                                    Manually notify a departing employee that their post-exit survey is ready.
                                    The notification banner will appear on their portal dashboard the next time they log in.
                                    A timestamp is recorded so you know exactly when it was sent.
                                </p>
                                <form method="POST" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
                                    <input type="hidden" name="action" value="send_survey">
                                    <div style="flex:1;min-width:220px;">
                                        <label style="font-size:12px;font-weight:600;opacity:.8;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">Select Exit Record</label>
                                        <select name="exit_id" class="form-control" required style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.25);color:white;border-radius:8px;padding:10px 14px;">
                                            <option value="" style="color:#1a1a2e;">Choose an employee exit...</option>
                                            <?php foreach ($exits as $exit): ?>
                                            <option value="<?= $exit['exit_id'] ?>" style="color:#1a1a2e;">
                                                <?= htmlspecialchars($exit['employee_name']) ?>
                                                — <?= date('M d, Y', strtotime($exit['exit_date'])) ?>
                                                (<?= htmlspecialchars($exit['exit_type']) ?>)
                                                <?= $exit['survey_sent_at'] ? ' ✓ Sent ' . date('M d', strtotime($exit['survey_sent_at'])) : '' ?>
                                                <?= $exit['survey_submitted'] > 0 ? ' ✅ Submitted' : '' ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="margin-bottom:0;background:linear-gradient(135deg,#E91E63,#C2185B);border:none;border-radius:25px;padding:11px 24px;font-weight:700;white-space:nowrap;">
                                        📣 Send Survey Notification
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- NOTIFICATION HISTORY TABLE -->
                        <h5 style="font-weight:700;color:#1a1a2e;margin-bottom:14px;">📋 Survey Notification History</h5>
                        <div class="table-container" style="margin-bottom:30px;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Exit Date</th>
                                        <th>Exit Type</th>
                                        <th>Notification Sent</th>
                                        <th>Survey Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($exits as $exit):
                                    $sentAt = $exit['survey_sent_at'];
                                    $isSubmitted = $exit['survey_submitted'] > 0;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($exit['employee_name']) ?></strong></td>
                                    <td><?= date('M d, Y', strtotime($exit['exit_date'])) ?></td>
                                    <td><?= htmlspecialchars($exit['exit_type']) ?></td>
                                    <td>
                                        <?php if ($sentAt): ?>
                                            <div>
                                                <span style="background:#d1ecf1;color:#0c5460;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">✅ Sent</span>
                                                <div style="font-size:11px;color:#6b7280;margin-top:4px;">🕐 <?= date('M d, Y — h:i A', strtotime($sentAt)) ?></div>
                                            </div>
                                        <?php else: ?>
                                            <span style="background:#f3f4f6;color:#9ca3af;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">— Not Sent</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isSubmitted): ?>
                                            <span style="background:#d4edda;color:#155724;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">📝 Survey Submitted</span>
                                        <?php elseif ($sentAt): ?>
                                            <span style="background:#fff3cd;color:#856404;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">⏳ Awaiting Response</span>
                                        <?php else: ?>
                                            <span style="background:#f3f4f6;color:#9ca3af;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">💤 Not Notified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$isSubmitted): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="send_survey">
                                            <input type="hidden" name="exit_id" value="<?= $exit['exit_id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-small"
                                                style="background:linear-gradient(135deg,#E91E63,#C2185B);border:none;"
                                                onclick="return confirm('Send survey notification to <?= htmlspecialchars(addslashes($exit['employee_name'])) ?>?')">
                                                <?= $sentAt ? '🔁 Resend' : '📣 Send' ?>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span style="color:#9ca3af;font-size:13px;">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($exits)): ?>
                            <div class="no-results"><i>📋</i><h3>No exit records found</h3></div>
                            <?php endif; ?>
                        </div>



                    </div>


                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Survey Modal -->
    <div id="surveyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Survey</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="surveyForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="survey_id" name="survey_id">

                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1">
                            <strong>🔒 Submit as <span style="color:#6f42c1;">Anonymous Survey</span></strong>
                        </label>
                        <small style="color:#666;display:block;margin-top:5px;">Check to hide employee identity.</small>
                    </div>

                    <div class="form-group">
                        <label for="exit_id">Exit Record</label>
                        <select id="exit_id" name="exit_id" class="form-control" required>
                            <option value="">Select exit record...</option>
                            <?php foreach ($exits as $exit): ?>
                            <option value="<?= $exit['exit_id'] ?>"><?= htmlspecialchars($exit['employee_name']) ?> - <?= date('M d, Y', strtotime($exit['exit_date'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" id="employee_id" name="employee_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="survey_date">Survey Date</label>
                                <input type="date" id="survey_date" name="survey_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="submitted_date">Submitted Date & Time</label>
                                <input type="datetime-local" id="submitted_date" name="submitted_date" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Satisfaction Rating</label>
                        <div class="rating-input" id="ratingStars">
                            <input type="radio" name="satisfaction_rating" id="star1" value="1"><label for="star1" data-rating="1">⭐</label>
                            <input type="radio" name="satisfaction_rating" id="star2" value="2"><label for="star2" data-rating="2">⭐</label>
                            <input type="radio" name="satisfaction_rating" id="star3" value="3"><label for="star3" data-rating="3">⭐</label>
                            <input type="radio" name="satisfaction_rating" id="star4" value="4"><label for="star4" data-rating="4">⭐</label>
                            <input type="radio" name="satisfaction_rating" id="star5" value="5"><label for="star5" data-rating="5">⭐</label>
                        </div>
                    </div>

                    <div class="evaluation-section">
                        <h4 style="color:var(--azure-blue);margin-bottom:15px;">📋 Employee Evaluation</h4>
                        <div class="form-group">
                            <label>Overall Evaluation Score (0-10)</label>
                            <input type="range" id="evaluation_score" name="evaluation_score" class="evaluation-score-slider" min="0" max="10" value="0" onchange="updateScoreDisplay()">
                            <div class="score-display" id="scoreDisplay">Score: 0/10</div>
                        </div>
                        <div class="form-group">
                            <label>Evaluation Criteria Met</label>
                            <div class="evaluation-criteria">
                                <div class="evaluation-item"><input type="checkbox" id="crit1" value="performance" class="evaluation-checkbox"><label for="crit1">Performance Excellence</label></div>
                                <div class="evaluation-item"><input type="checkbox" id="crit2" value="teamwork" class="evaluation-checkbox"><label for="crit2">Teamwork & Collaboration</label></div>
                                <div class="evaluation-item"><input type="checkbox" id="crit3" value="communication" class="evaluation-checkbox"><label for="crit3">Communication Skills</label></div>
                                <div class="evaluation-item"><input type="checkbox" id="crit4" value="reliability" class="evaluation-checkbox"><label for="crit4">Reliability & Punctuality</label></div>
                                <div class="evaluation-item"><input type="checkbox" id="crit5" value="innovation" class="evaluation-checkbox"><label for="crit5">Innovation & Creativity</label></div>
                                <div class="evaluation-item"><input type="checkbox" id="crit6" value="leadership" class="evaluation-checkbox"><label for="crit6">Leadership Potential</label></div>
                            </div>
                            <input type="hidden" id="evaluation_criteria" name="evaluation_criteria" value="">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="survey_response">Survey Response & Feedback</label>
                        <textarea id="survey_response" name="survey_response" class="form-control" placeholder="Enter detailed survey response..."></textarea>
                    </div>

                    <div style="text-align:center;margin-top:30px;">
                        <button type="button" class="btn" style="background:#6c757d;color:white;margin-right:10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Survey</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Survey Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody"></div>
        </div>
    </div>

    <script>
        let surveysData = <?= json_encode(array_values($surveys)) ?>;

        function switchTab(tabName, btn) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            if (btn) btn.classList.add('active');
            else {
                // find and activate by tab id
                document.querySelectorAll('.tab-btn').forEach(b => {
                    if (b.getAttribute('onclick') && b.getAttribute('onclick').includes(tabName)) b.classList.add('active');
                });
            }
        }

        // Employee card search & sort
        function filterEmpCards() {
            const q = document.getElementById('empSearchInput').value.toLowerCase();
            document.querySelectorAll('.emp-survey-card').forEach(card => {
                const name = card.dataset.name || '';
                card.style.display = name.includes(q) || q === '' ? '' : 'none';
            });
        }

        function sortEmpCards(val) {
            const container = document.getElementById('empCardsContainer');
            const cards = Array.from(container.querySelectorAll('.emp-survey-card'));
            cards.sort((a, b) => {
                if (val === 'highest') return parseInt(b.dataset.rating) - parseInt(a.dataset.rating);
                if (val === 'lowest') return parseInt(a.dataset.rating) - parseInt(b.dataset.rating);
                return new Date(b.dataset.date) - new Date(a.dataset.date);
            });
            cards.forEach(c => container.appendChild(c));
        }

        // Copy link
        function copyLink(inputId, btn) {
            const input = document.getElementById(inputId);
            navigator.clipboard.writeText(input.value).then(() => {
                btn.textContent = '✅ Copied!';
                btn.classList.add('copied');
                setTimeout(() => { btn.textContent = '📋 Copy'; btn.classList.remove('copied'); }, 2000);
            });
        }

        // Rating stars
        const ratingStars = document.querySelectorAll('#ratingStars label');
        ratingStars.forEach(star => {
            star.addEventListener('click', function() {
                updateStarDisplay(parseInt(this.getAttribute('data-rating')));
            });
        });

        function updateStarDisplay(rating) {
            document.querySelectorAll('#ratingStars label').forEach(label => {
                label.classList.toggle('active', parseInt(label.getAttribute('data-rating')) <= rating);
            });
        }

        function updateScoreDisplay() {
            document.getElementById('scoreDisplay').textContent = 'Score: ' + document.getElementById('evaluation_score').value + '/10';
        }

        function getEvaluationCriteria() {
            const checked = Array.from(document.querySelectorAll('.evaluation-checkbox:checked')).map(cb => cb.value);
            document.getElementById('evaluation_criteria').value = checked.join(', ');
        }

        // Search table
        document.getElementById('searchInput').addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#surveyTableBody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });

        function openModal(mode, surveyId = null) {
            const modal = document.getElementById('surveyModal');
            if (mode === 'add') {
                document.getElementById('modalTitle').textContent = 'Add New Post-Exit Survey';
                document.getElementById('action').value = 'add';
                document.getElementById('surveyForm').reset();
                document.getElementById('survey_id').value = '';
                updateStarDisplay(0);
            } else if (mode === 'edit' && surveyId) {
                document.getElementById('modalTitle').textContent = 'Edit Post-Exit Survey';
                document.getElementById('action').value = 'update';
                document.getElementById('survey_id').value = surveyId;
                populateEditForm(surveyId);
            }
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('surveyModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(surveyId) {
            const survey = surveysData.find(s => s.survey_id == surveyId);
            if (survey) {
                document.getElementById('exit_id').value = survey.exit_id || '';
                document.getElementById('survey_date').value = survey.survey_date || '';
                document.getElementById('survey_response').value = survey.survey_response || '';
                document.getElementById('is_anonymous').checked = survey.is_anonymous == 1;
                document.getElementById('evaluation_score').value = survey.evaluation_score || 0;
                if (survey.evaluation_criteria) {
                    const criteria = survey.evaluation_criteria.split(', ');
                    document.querySelectorAll('.evaluation-checkbox').forEach(cb => { cb.checked = criteria.includes(cb.value); });
                }
                if (survey.submitted_date) {
                    const date = new Date(survey.submitted_date);
                    document.getElementById('submitted_date').value = date.toISOString().slice(0, 16);
                }
                const rating = survey.satisfaction_rating || 0;
                const starInput = document.getElementById('star' + rating);
                if (starInput) starInput.checked = true;
                updateStarDisplay(rating);
                updateScoreDisplay();
            }
        }

        function editSurvey(id) { openModal('edit', id); }

        function deleteSurvey(id) {
            if (confirm('Are you sure you want to delete this survey?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="survey_id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewSurvey(surveyId) {
            const survey = surveysData.find(s => s.survey_id == surveyId);
            if (!survey) return;
            const rating = survey.satisfaction_rating || 0;
            const stars = '⭐'.repeat(rating) + '☆'.repeat(5 - rating);
            const ratingLabels = {1:'Very Dissatisfied',2:'Dissatisfied',3:'Neutral',4:'Satisfied',5:'Very Satisfied'};
            const empBadge = !!(survey.submitted_by_employee) ? '<span style="background:#e3f2fd;color:#1565c0;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;display:inline-block;margin-bottom:12px;">👤 Submitted by Employee</span>' : '';
            const employeeInfo = survey.is_anonymous
                ? '<div style="margin-bottom:15px;"><strong>Identity:</strong><br><span style="background:#ede7f6;color:#4527a0;padding:5px 12px;border-radius:20px;font-size:13px;">🔒 Anonymous Submission</span></div>'
                : `<div style="margin-bottom:15px;"><strong>Employee:</strong><p style="margin:5px 0;">${survey.employee_name} (${survey.employee_number})</p></div><div style="margin-bottom:15px;"><strong>Role:</strong><p style="margin:5px 0;">${survey.job_title} — ${survey.department}</p></div>`;

            document.getElementById('viewModalBody').innerHTML = `
                <div style="padding:10px;">
                    ${empBadge}
                    ${employeeInfo}
                    <div style="margin-bottom:15px;"><strong>Exit:</strong><p style="margin:5px 0;">${new Date(survey.exit_date).toLocaleDateString()} · ${survey.exit_type}</p></div>
                    <div style="margin-bottom:15px;"><strong>Satisfaction:</strong><p style="margin:5px 0;font-size:22px;">${stars} (${rating}/5 — ${ratingLabels[rating]||''})</p></div>
                    ${survey.evaluation_score > 0 ? `<div style="margin-bottom:15px;"><strong>Self-Evaluation Score:</strong><p style="margin:5px 0;font-size:22px;color:var(--azure-blue);">${survey.evaluation_score}/10</p></div>` : ''}
                    ${survey.evaluation_criteria ? `<div style="margin-bottom:15px;"><strong>Criteria:</strong><p style="margin:5px 0;">${survey.evaluation_criteria}</p></div>` : ''}
                    <div style="margin-bottom:15px;"><strong>Response:</strong><div style="background:#f8f9fa;padding:16px;border-radius:8px;margin-top:8px;font-size:14px;line-height:1.7;white-space:pre-wrap;">${survey.survey_response||'No response provided'}</div></div>
                    <div style="text-align:center;margin-top:24px;"><button class="btn btn-primary" onclick="closeViewModal()">Close</button></div>
                </div>`;
            document.getElementById('viewModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        window.onclick = function(e) {
            if (e.target === document.getElementById('surveyModal')) closeModal();
            if (e.target === document.getElementById('viewModal')) closeViewModal();
        };

        document.getElementById('surveyForm').addEventListener('submit', function(e) {
            if (!document.querySelector('input[name="satisfaction_rating"]:checked')) {
                e.preventDefault();
                alert('Please select a satisfaction rating');
                return;
            }
            getEvaluationCriteria();
        });

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(a => {
                a.style.transition = 'opacity 0.5s';
                a.style.opacity = '0';
                setTimeout(() => a.remove(), 500);
            });
        }, 5000);

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('survey_date').value = new Date().toISOString().split('T')[0];
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>