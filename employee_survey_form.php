<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit;
}

require_once 'dp.php';

    $host = getenv('DB_HOST') ?? 'localhost';
    $dbname = getenv('DB_NAME') ?? 'hr_system';
    $username = getenv('DB_USER') ?? 'root';
    $password = getenv('DB_PASS') ?? '';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$employee_id = $_SESSION['user_id'];
$username    = $_SESSION['username'];
$first_name  = ucfirst(explode('.', $username)[0]);
$current_page = basename($_SERVER['PHP_SELF']);

// Ensure submitted_by_employee column exists
try {
    $cols = $pdo->query("SHOW COLUMNS FROM post_exit_surveys LIKE 'submitted_by_employee'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE post_exit_surveys ADD COLUMN submitted_by_employee TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (PDOException $e) {}

// Resolve real employee_id from username (users.user_id != employee_profiles.employee_id)
$parts     = explode('.', $username);
$firstName = $parts[0] ?? '';
$lastName  = $parts[1] ?? '';
$stmtResolve = $pdo->prepare("
    SELECT ep.employee_id
    FROM employee_profiles ep
    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    WHERE LOWER(pi.first_name) = LOWER(?) AND LOWER(pi.last_name) = LOWER(?)
    LIMIT 1
");
$stmtResolve->execute([$firstName, $lastName]);
$profileRow      = $stmtResolve->fetch(PDO::FETCH_ASSOC);
$resolved_emp_id = $profileRow ? $profileRow['employee_id'] : $employee_id;

// Get the pending exit record ONLY if HR has sent a notification
$stmt = $pdo->prepare("
    SELECT ex.exit_id, ex.exit_date, ex.exit_type,
           CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
           pi.first_name, jr.title as job_title, jr.department
    FROM exits ex
    JOIN employee_profiles ep ON ex.employee_id = ep.employee_id
    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
    INNER JOIN survey_notifications sn ON sn.exit_id = ex.exit_id
    WHERE ep.employee_id = ?
      AND ex.exit_id NOT IN (
          SELECT exit_id FROM post_exit_surveys WHERE exit_id IS NOT NULL
      )
    ORDER BY ex.exit_date DESC
    LIMIT 1
");
$stmt->execute([$resolved_emp_id]);
$exitRecord = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if employee already submitted
$alreadySubmitted = false;
$submittedSurvey  = null;
if (!$exitRecord) {
    $stmt2 = $pdo->prepare("
        SELECT pes.*, ex.exit_date, ex.exit_type
        FROM post_exit_surveys pes
        JOIN exits ex ON pes.exit_id = ex.exit_id
        WHERE ex.employee_id = ? AND pes.submitted_by_employee = 1
        ORDER BY pes.submitted_date DESC LIMIT 1
    ");
    $stmt2->execute([$resolved_emp_id]);
    $submittedSurvey = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($submittedSurvey) $alreadySubmitted = true;
}

$success = false;
$error   = '';

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $exitRecord) {
    $isAnonymous  = isset($_POST['is_anonymous']) ? 1 : 0;
    $empId        = $isAnonymous ? null : $resolved_emp_id;
    $rating       = intval($_POST['satisfaction_rating'] ?? 0);
    $evalScore    = intval($_POST['evaluation_score'] ?? 0);
    $evalCriteria = trim($_POST['evaluation_criteria'] ?? '');
    $reasons      = $_POST['leave_reasons'] ?? [];
    $reasonsStr   = implode(', ', array_map('htmlspecialchars', $reasons));
    $recommend    = htmlspecialchars($_POST['would_recommend'] ?? '');
    $improvements = trim($_POST['improvements'] ?? '');
    $general      = trim($_POST['survey_response'] ?? '');

    if ($rating === 0) {
        $error = 'Please select a satisfaction rating before submitting.';
    } else {
        $fullResponse = "";
        if ($reasonsStr)   $fullResponse .= "Reasons for leaving: $reasonsStr\n\n";
        if ($recommend)    $fullResponse .= "Would recommend company: $recommend\n\n";
        if ($improvements) $fullResponse .= "Suggested improvements:\n$improvements\n\n";
        if ($general)      $fullResponse .= "General feedback:\n$general";
        $fullResponse = trim($fullResponse);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO post_exit_surveys
                (employee_id, exit_id, survey_date, survey_response, satisfaction_rating,
                 submitted_date, is_anonymous, evaluation_score, evaluation_criteria, submitted_by_employee)
                VALUES (?, ?, CURDATE(), ?, ?, NOW(), ?, ?, ?, 1)
            ");
            $stmt->execute([
                $empId,
                $exitRecord['exit_id'],
                $fullResponse,
                $rating,
                $isAnonymous,
                $evalScore,
                $evalCriteria ?: null
            ]);
            $success = true;
        } catch (PDOException $e) {
            $error = 'Something went wrong saving your survey. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exit Survey - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        body { background: #FCE4EC; }
        .main-content { padding: 30px; }

        /* Page header */
        .survey-page-header {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 28px;
            box-shadow: 0 10px 30px rgba(233,30,99,0.2);
            position: relative;
            overflow: hidden;
        }

        .survey-page-header::before {
            content: '';
            position: absolute; top: -40px; right: -40px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }

        .survey-page-header::after {
            content: '';
            position: absolute; bottom: -30px; right: 80px;
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }

        .survey-page-header h2 { margin: 0; font-weight: 700; position: relative; z-index: 1; }
        .survey-page-header p  { margin: 8px 0 0; opacity: .9; position: relative; z-index: 1; }

        /* State cards */
        .state-card {
            background: white;
            border-radius: 15px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.07);
        }

        .state-icon { font-size: 56px; margin-bottom: 16px; display: block; }
        .state-card h3 { font-weight: 700; color: #1a1a2e; margin-bottom: 10px; }
        .state-card p  { color: #6b7280; line-height: 1.7; }

        /* Form sections */
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 28px 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }

        .section-head {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #E91E63;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #FCE4EC;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Anonymous toggle */
        .anon-toggle {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 20px;
            cursor: pointer;
            transition: all .2s;
            user-select: none;
        }

        .anon-toggle:hover      { border-color: #F06292; background: #FCE4EC; }
        .anon-toggle.active     { border-color: #E91E63; background: #FCE4EC; }
        .anon-toggle input      { display: none; }

        .toggle-box {
            width: 22px; height: 22px;
            border: 2px solid #d1d5db;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; margin-top: 1px;
            transition: all .2s;
            font-size: 13px; color: white;
        }

        .anon-toggle.active .toggle-box { background: #E91E63; border-color: #E91E63; }

        .anon-text h5 { font-size: 14px; font-weight: 700; color: #1a1a2e; margin: 0 0 3px; }
        .anon-text p  { font-size: 13px; color: #6b7280; margin: 0; }

        /* Star rating */
        .star-row { display: flex; gap: 6px; margin-bottom: 6px; }

        .star-btn {
            background: none; border: none;
            font-size: 38px; cursor: pointer;
            color: #e5e7eb; transition: all .15s;
            line-height: 1; padding: 0;
        }

        .star-btn.lit   { color: #f59e0b; }
        .star-btn:hover { transform: scale(1.1); }

        .star-label { font-size: 13px; color: #6b7280; min-height: 18px; }

        /* Reason grid */
        .reason-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .reason-chip {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            transition: all .2s;
            user-select: none;
        }

        .reason-chip:hover  { border-color: #F06292; background: #FCE4EC; }
        .reason-chip.on     { border-color: #E91E63; background: #FCE4EC; color: #880E4F; font-weight: 600; }
        .reason-chip input  { display: none; }

        /* Recommend */
        .recommend-row { display: flex; gap: 10px; flex-wrap: wrap; }

        .rec-btn {
            flex: 1; min-width: 90px;
            padding: 12px 10px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            text-align: center;
            font-size: 13px; font-weight: 500;
            transition: all .2s;
        }

        .rec-btn .em { font-size: 22px; display: block; margin-bottom: 4px; }
        .rec-btn:hover { border-color: #F06292; background: #FCE4EC; }
        .rec-btn.on   { border-color: #E91E63; background: #FCE4EC; color: #880E4F; font-weight: 700; }

        /* Slider */
        .score-slider {
            -webkit-appearance: none; width: 100%; height: 6px;
            border-radius: 3px; background: #e5e7eb; outline: none;
        }

        .score-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 22px; height: 22px; border-radius: 50%;
            background: #E91E63; cursor: pointer;
            box-shadow: 0 2px 6px rgba(233,30,99,.4);
        }

        .score-pill {
            display: inline-block;
            background: #E91E63; color: white;
            font-size: 14px; font-weight: 700;
            padding: 4px 16px; border-radius: 20px;
            margin-top: 10px;
        }

        /* Eval criteria */
        .crit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }

        .crit-chip {
            display: flex; align-items: center; gap: 8px;
            padding: 9px 13px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer; font-size: 13px;
            transition: all .2s; user-select: none;
        }

        .crit-chip:hover { border-color: #F06292; }
        .crit-chip.on    { border-color: #E91E63; background: #FCE4EC; }
        .crit-chip input { display: none; }

        .crit-box {
            width: 16px; height: 16px;
            border: 2px solid #d1d5db; border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; color: white; flex-shrink: 0;
        }

        .crit-chip.on .crit-box { background: #E91E63; border-color: #E91E63; }

        /* Textarea */
        .survey-textarea {
            width: 100%; padding: 12px 16px;
            border: 2px solid #e5e7eb; border-radius: 10px;
            font-family: inherit; font-size: 14px;
            color: #374151; resize: vertical; min-height: 100px;
            background: #f9fafb; transition: border-color .2s;
        }

        .survey-textarea:focus { border-color: #E91E63; outline: none; background: white; }

        /* Field label */
        .field-label {
            display: block; font-size: 13px; font-weight: 600;
            color: #1a1a2e; margin-bottom: 8px;
        }

        .field-label span { color: #9ca3af; font-weight: 400; }

        /* Submit */
        .submit-btn {
            width: 100%; padding: 16px;
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white; border: none; border-radius: 12px;
            font-size: 16px; font-weight: 700; cursor: pointer;
            transition: all .3s; letter-spacing: .3px;
        }

        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(233,30,99,.35); }
        .submit-btn:active { transform: translateY(0); }

        .privacy-note {
            text-align: center; font-size: 12px;
            color: #9ca3af; margin-top: 14px; line-height: 1.6;
        }

        .meta-pill {
            display: inline-block;
            background: rgba(255,255,255,.18);
            padding: 4px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 500; margin-right: 6px; margin-top: 6px;
        }

        .alert-error {
            background: #fee2e2; border: 1px solid #fca5a5;
            color: #991b1b; border-radius: 10px; padding: 14px 18px;
            margin-bottom: 20px; font-size: 14px; font-weight: 500;
        }

        @media (max-width: 576px) {
            .reason-grid, .crit-grid { grid-template-columns: 1fr; }
            .recommend-row { flex-direction: column; }
            .form-section { padding: 20px; }
        }
    </style>
</head>
<body class="employee-page">
<div class="container-fluid">
    <?php include 'employee_navigation.php'; ?>
    <div class="row">
        <?php include 'employee_sidebar.php'; ?>
        <div class="main-content">

            <!-- Page Header -->
            <div class="survey-page-header">
                <h2><i class="fas fa-clipboard-list mr-3"></i>My Exit Survey</h2>
                <p>Share your honest experience to help improve the workplace for everyone</p>
            </div>

            <?php if ($success): ?>
            <!-- SUCCESS STATE -->
            <div class="state-card">
                <span class="state-icon">🎉</span>
                <h3>Thank you, <?= htmlspecialchars($first_name) ?>!</h3>
                <p>Your survey has been submitted successfully.<br>Your feedback will be reviewed by HR and used to improve the workplace experience.<br><br>We wish you all the best in your future endeavors!</p>
                <a href="employee_index.php" class="btn mt-4" style="background:linear-gradient(135deg,#E91E63,#C2185B);color:white;border-radius:25px;padding:12px 30px;font-weight:600;">
                    <i class="fas fa-home mr-2"></i>Back to Dashboard
                </a>
            </div>

            <?php elseif ($alreadySubmitted): ?>
            <!-- ALREADY SUBMITTED -->
            <div class="state-card">
                <span class="state-icon">✅</span>
                <h3>Survey Already Submitted</h3>
                <p>You've already completed your post-exit survey on
                    <?= date('F d, Y', strtotime($submittedSurvey['submitted_date'])) ?>.
                    <br>Thank you for your feedback!</p>
                <a href="employee_index.php" class="btn mt-4" style="background:linear-gradient(135deg,#E91E63,#C2185B);color:white;border-radius:25px;padding:12px 30px;font-weight:600;">
                    <i class="fas fa-home mr-2"></i>Back to Dashboard
                </a>
            </div>

            <?php elseif (!$exitRecord): ?>
            <!-- NO PENDING SURVEY -->
            <div class="state-card">
                <span class="state-icon">📋</span>
                <h3>No Pending Survey</h3>
                <p>You don't have any pending post-exit survey at this time.<br>A survey will appear here once HR processes your exit record.</p>
                <a href="employee_index.php" class="btn mt-4" style="background:linear-gradient(135deg,#E91E63,#C2185B);color:white;border-radius:25px;padding:12px 30px;font-weight:600;">
                    <i class="fas fa-home mr-2"></i>Back to Dashboard
                </a>
            </div>

            <?php else: ?>
            <!-- THE ACTUAL SURVEY FORM -->

            <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Context banner -->
            <div style="background:white;border-radius:14px;padding:20px 24px;margin-bottom:20px;box-shadow:0 3px 12px rgba(0,0,0,0.06);display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <div style="width:48px;height:48px;background:linear-gradient(135deg,#E91E63,#F06292);border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;flex-shrink:0;">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div>
                    <div style="font-weight:700;color:#1a1a2e;font-size:15px;"><?= htmlspecialchars($exitRecord['full_name'] ?? $first_name) ?></div>
                    <div style="margin-top:4px;">
                        <span class="meta-pill" style="background:#FCE4EC;color:#880E4F;">📅 Exit: <?= date('M d, Y', strtotime($exitRecord['exit_date'])) ?></span>
                        <span class="meta-pill" style="background:#FCE4EC;color:#880E4F;">🔖 <?= htmlspecialchars($exitRecord['exit_type']) ?></span>
                        <?php if ($exitRecord['job_title']): ?>
                        <span class="meta-pill" style="background:#FCE4EC;color:#880E4F;">💼 <?= htmlspecialchars($exitRecord['job_title']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <form method="POST" id="surveyForm">

                <!-- 1. Privacy -->
                <div class="form-section">
                    <div class="section-head"><i class="fas fa-lock"></i> Privacy</div>
                    <label class="anon-toggle" id="anonToggle" onclick="toggleAnon(event)">
                        <input type="checkbox" name="is_anonymous" id="is_anonymous" value="1">
                        <div class="toggle-box" id="toggleBox"></div>
                        <div class="anon-text">
                            <h5>Submit anonymously</h5>
                            <p>Your name will be hidden. Only your feedback will be recorded by HR.</p>
                        </div>
                    </label>
                </div>

                <!-- 2. Satisfaction -->
                <div class="form-section">
                    <div class="section-head"><i class="fas fa-star"></i> Overall Satisfaction</div>
                    <label class="field-label">How satisfied were you with your overall experience at the company? <span>*</span></label>
                    <div class="star-row" id="starRow">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="star-btn" data-val="<?= $i ?>" onclick="setRating(<?= $i ?>)">★</button>
                        <?php endfor; ?>
                    </div>
                    <div class="star-label" id="starLabel">Click a star to rate</div>
                    <input type="hidden" name="satisfaction_rating" id="satisfaction_rating">
                </div>

                <!-- 3. Reasons -->
                <div class="form-section">
                    <div class="section-head"><i class="fas fa-door-open"></i> Reason for Leaving</div>

                    <label class="field-label" style="margin-bottom:12px;">What were the main reasons for your departure? <span>(select all that apply)</span></label>
                    <div class="reason-grid">
                        <?php
                        $reasons = [
                            'better_opportunity' => '💼 Better Opportunity',
                            'compensation'       => '💰 Compensation',
                            'work_life_balance'  => '⚖️ Work-Life Balance',
                            'career_growth'      => '📈 Career Growth',
                            'management'         => '👔 Management Issues',
                            'company_culture'    => '🏢 Company Culture',
                            'personal_reasons'   => '🏠 Personal Reasons',
                            'relocation'         => '📍 Relocation',
                        ];
                        foreach ($reasons as $val => $label): ?>
                        <label class="reason-chip" onclick="toggleChip(this)">
                            <input type="checkbox" name="leave_reasons[]" value="<?= $val ?>">
                            <?= $label ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top:20px;">
                        <label class="field-label">Would you recommend this company to others?</label>
                        <div class="recommend-row" id="recommendRow">
                            <?php
                            $opts = [
                                'definitely'   => ['😍', 'Definitely'],
                                'probably'     => ['🙂', 'Probably'],
                                'unsure'       => ['🤔', 'Not Sure'],
                                'probably_not' => ['😐', 'Probably Not'],
                            ];
                            foreach ($opts as $val => [$em, $txt]): ?>
                            <button type="button" class="rec-btn" data-val="<?= $val ?>" onclick="selectRec('<?= $val ?>')">
                                <span class="em"><?= $em ?></span><?= $txt ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="would_recommend" id="would_recommend">
                    </div>
                </div>

                <!-- 4. Self-Evaluation -->
                <div class="form-section">
                    <div class="section-head"><i class="fas fa-chart-bar"></i> Self-Evaluation <span style="font-size:11px;color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></div>

                    <label class="field-label">How would you rate your own overall performance & contributions? <span>(0 = skip)</span></label>
                    <input type="range" class="score-slider" name="evaluation_score" id="evalScore" min="0" max="10" value="0" oninput="updateScore(this.value)">
                    <span class="score-pill" id="scorePill">0 / 10</span>

                    <div style="margin-top:20px;">
                        <label class="field-label">Areas you excelled in: <span>(optional)</span></label>
                        <div class="crit-grid">
                            <?php
                            $crits = [
                                'performance'   => '🎯 Performance',
                                'teamwork'      => '🤝 Teamwork',
                                'communication' => '💬 Communication',
                                'reliability'   => '⏰ Reliability',
                                'innovation'    => '💡 Innovation',
                                'leadership'    => '🌟 Leadership',
                            ];
                            foreach ($crits as $val => $label): ?>
                            <label class="crit-chip" onclick="toggleCrit(this)">
                                <input type="checkbox" class="eval-cb" value="<?= $val ?>">
                                <span class="crit-box"></span>
                                <?= $label ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="evaluation_criteria" id="evalCriteriaVal">
                    </div>
                </div>

                <!-- 5. Open Feedback -->
                <div class="form-section">
                    <div class="section-head"><i class="fas fa-comment-dots"></i> Open Feedback</div>

                    <div style="margin-bottom:20px;">
                        <label class="field-label" for="improvements">What could the company improve? <span>(optional)</span></label>
                        <textarea class="survey-textarea" name="improvements" id="improvements" placeholder="Share your suggestions for improvement..."></textarea>
                    </div>

                    <div>
                        <label class="field-label" for="survey_response">Any other comments or feedback? <span>(optional)</span></label>
                        <textarea class="survey-textarea" name="survey_response" id="survey_response" placeholder="Anything else you'd like to share about your experience..."></textarea>
                    </div>
                </div>

                <button type="submit" class="submit-btn" onclick="prepareSubmit()">
                    <i class="fas fa-paper-plane mr-2"></i>Submit My Survey
                </button>
                <p class="privacy-note">🔒 Your responses are confidential and used only to improve the workplace. Thank you for sharing your experience.</p>

            </form>
            <?php endif; ?>

        </div><!-- /main-content -->
    </div>
</div>

<script>
    // Anon toggle
    function toggleAnon(e) {
        e.preventDefault();
        const cb  = document.getElementById('is_anonymous');
        const tog = document.getElementById('anonToggle');
        const box = document.getElementById('toggleBox');
        cb.checked = !cb.checked;
        tog.classList.toggle('active', cb.checked);
        box.textContent = cb.checked ? '✓' : '';
    }

    // Stars
    const ratingLabels = ['','Very Dissatisfied','Dissatisfied','Neutral','Satisfied','Very Satisfied'];
    let currentRating = 0;

    function setRating(val) {
        currentRating = val;
        document.getElementById('satisfaction_rating').value = val;
        document.getElementById('starLabel').textContent = ratingLabels[val];
        document.querySelectorAll('.star-btn').forEach(b => {
            b.classList.toggle('lit', parseInt(b.dataset.val) <= val);
        });
    }

    document.querySelectorAll('.star-btn').forEach(btn => {
        btn.addEventListener('mouseenter', () => {
            const v = parseInt(btn.dataset.val);
            document.querySelectorAll('.star-btn').forEach(b => b.classList.toggle('lit', parseInt(b.dataset.val) <= v));
            document.getElementById('starLabel').textContent = ratingLabels[v];
        });
        btn.addEventListener('mouseleave', () => {
            document.querySelectorAll('.star-btn').forEach(b => b.classList.toggle('lit', parseInt(b.dataset.val) <= currentRating));
            document.getElementById('starLabel').textContent = currentRating ? ratingLabels[currentRating] : 'Click a star to rate';
        });
    });

    // Reason chips
    function toggleChip(el) {
        const cb = el.querySelector('input');
        cb.checked = !cb.checked;
        el.classList.toggle('on', cb.checked);
    }

    // Recommend
    function selectRec(val) {
        document.getElementById('would_recommend').value = val;
        document.querySelectorAll('.rec-btn').forEach(b => b.classList.toggle('on', b.dataset.val === val));
    }

    // Score
    function updateScore(val) {
        document.getElementById('scorePill').textContent = val + ' / 10';
    }

    // Criteria chips
    function toggleCrit(el) {
        const cb = el.querySelector('input');
        cb.checked = !cb.checked;
        el.classList.toggle('on', cb.checked);
    }

    // Pre-submit: collect criteria
    function prepareSubmit() {
        const checked = Array.from(document.querySelectorAll('.eval-cb:checked')).map(c => c.value);
        document.getElementById('evalCriteriaVal').value = checked.join(', ');
    }

    // Validate rating
    document.getElementById('surveyForm') && document.getElementById('surveyForm').addEventListener('submit', function(e) {
        if (!document.getElementById('satisfaction_rating').value) {
            e.preventDefault();
            alert('Please select a satisfaction rating (1–5 stars) before submitting.');
        }
    });
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>