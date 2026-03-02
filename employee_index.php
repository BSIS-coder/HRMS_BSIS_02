<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit;
}

require_once 'dp.php';

$employee_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$current_role = $_SESSION['role'] ?? 'none';
$current_page = basename($_SERVER['PHP_SELF']);

// ─── Check for pending post-exit survey ───────────────────────────────────────
$hasPendingSurvey = false;
$manualSentAt     = false;
$autoTrigger      = false;

try {
    $host = 'localhost'; $dbname = 'hr_system';
    $pdo_dash = new PDO("mysql:host=$host;dbname=$dbname", 'root', '');
    $pdo_dash->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // survey_notifications table must already exist in the database.
    // submitted_by_employee column must already exist in post_exit_surveys.

    // Resolve employee_id from username (users.user_id != employee_profiles.employee_id)
    $parts     = explode('.', $username);
    $firstName = $parts[0] ?? '';
    $lastName  = $parts[1] ?? '';
    $stmtResolve = $pdo_dash->prepare("
        SELECT ep.employee_id
        FROM employee_profiles ep
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        WHERE LOWER(pi.first_name) = LOWER(?) AND LOWER(pi.last_name) = LOWER(?)
        LIMIT 1
    ");
    $stmtResolve->execute([$firstName, $lastName]);
    $profileRow          = $stmtResolve->fetch(PDO::FETCH_ASSOC);
    $resolved_emp_id     = $profileRow ? $profileRow['employee_id'] : $employee_id;

    // ONLY show notification when HR has explicitly sent it
    $stmtManual = $pdo_dash->prepare("
        SELECT sn.sent_at FROM survey_notifications sn
        JOIN exits ex ON sn.exit_id = ex.exit_id
        WHERE ex.employee_id = ?
          AND ex.exit_id NOT IN (
              SELECT exit_id FROM post_exit_surveys WHERE exit_id IS NOT NULL
          )
        ORDER BY sn.sent_at DESC LIMIT 1
    ");
    $stmtManual->execute([$resolved_emp_id]);
    $manualSentAt = $stmtManual->fetchColumn();

    $hasPendingSurvey = ($manualSentAt !== false);

} catch (Exception $e) {
    error_log("Survey check error: " . $e->getMessage());
}
// ──────────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title { color: var(--primary-color); margin-bottom: 30px; font-weight: 600; }
        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }

        .employee-welcome {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white; padding: 30px; border-radius: 15px;
            margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .employee-welcome h2 { margin: 0; font-weight: 700; }
        .employee-welcome p  { margin: 10px 0 0; opacity: .9; }

        /* ── Survey Notification Banner ── */
        .survey-notification {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            border-radius: 15px;
            padding: 22px 28px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 8px 28px rgba(0,0,0,0.18);
            position: relative;
            overflow: hidden;
            animation: pulseBorder 2.5s ease-in-out infinite;
            border: 2px solid transparent;
        }

        @keyframes pulseBorder {
            0%,100% { border-color: rgba(233,30,99,0.4); }
            50%      { border-color: rgba(233,30,99,0.9); }
        }

        .survey-notification::before {
            content: '';
            position: absolute; top: -30px; right: -30px;
            width: 120px; height: 120px; border-radius: 50%;
            background: rgba(233,30,99,0.15);
        }

        .survey-notification::after {
            content: '';
            position: absolute; bottom: -20px; right: 100px;
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(233,30,99,0.1);
        }

        .survey-notif-icon {
            width: 56px; height: 56px; flex-shrink: 0;
            background: linear-gradient(135deg, #E91E63, #F06292);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 14px rgba(233,30,99,0.4);
            position: relative; z-index: 1;
            animation: iconBounce 2s ease-in-out infinite;
        }

        @keyframes iconBounce {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-4px); }
        }

        .survey-notif-text { flex: 1; position: relative; z-index: 1; }
        .survey-notif-text h5 { font-size: 16px; font-weight: 700; margin: 0 0 4px; }
        .survey-notif-text p  { font-size: 13px; opacity: .8; margin: 0; }

        .survey-notif-badge {
            background: #E91E63;
            color: white; font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
            display: inline-block; margin-bottom: 6px;
            animation: badgePulse 1.5s ease-in-out infinite;
        }

        @keyframes badgePulse {
            0%,100% { opacity: 1; }
            50%      { opacity: .7; }
        }

        .survey-notif-btn {
            background: linear-gradient(135deg, #E91E63, #C2185B);
            color: white; border: none;
            padding: 11px 22px; border-radius: 25px;
            font-size: 14px; font-weight: 700;
            cursor: pointer; text-decoration: none;
            white-space: nowrap; flex-shrink: 0;
            transition: all .3s; position: relative; z-index: 1;
            box-shadow: 0 4px 14px rgba(233,30,99,.4);
        }

        .survey-notif-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233,30,99,.5);
            color: white; text-decoration: none;
        }

        .dismiss-btn {
            background: none; border: none;
            color: rgba(255,255,255,.4); font-size: 18px;
            cursor: pointer; padding: 0 0 0 10px;
            line-height: 1; position: relative; z-index: 1;
            transition: color .2s; flex-shrink: 0;
        }
        .dismiss-btn:hover { color: rgba(255,255,255,.8); }
        /* ─────────────────────────────────── */

        .quick-actions { margin-bottom: 30px; }

        .quick-action-btn {
            background: white; border: 2px solid #e9ecef;
            border-radius: 12px; padding: 20px; text-align: center;
            transition: all .3s; text-decoration: none;
            color: #495057; display: block; height: 100%;
        }

        .quick-action-btn:hover {
            border-color: #E91E63; transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(233,30,99,.15);
            text-decoration: none; color: #E91E63;
        }

        .quick-action-btn i  { font-size: 2.5rem; color: #E91E63; margin-bottom: 15px; }
        .quick-action-btn h5 { margin: 0; font-weight: 600; }

        .info-card {
            background: white; border-radius: 15px; padding: 25px;
            margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        .info-card h5 { color: #E91E63; margin-bottom: 20px; font-weight: 600; }

        .info-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 0; border-bottom: 1px solid #f8f9fa;
        }
        .info-item:last-child { border-bottom: none; }
        .info-label { color: #6c757d; font-weight: 500; }
        .info-value { color: #495057; font-weight: 600; }

        .status-badge    { padding: 5px 12px; border-radius: 20px; font-size: .8rem; font-weight: 500; }
        .status-active   { background: #d4edda; color: #155724; }
        .status-pending  { background: #fff3cd; color: #856404; }
        .status-approved { background: #d1ecf1; color: #0c5460; }

        .leave-request-btn {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white; border: none; padding: 12px 25px;
            border-radius: 25px; font-weight: 600; transition: all .3s;
        }
        .leave-request-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(233,30,99,.3); color: white; }

        .document-item {
            display: flex; align-items: center; padding: 15px;
            background: #f8f9fa; border-radius: 10px; margin-bottom: 10px;
            transition: all .3s;
        }
        .document-item:hover { background: #e9ecef; transform: translateX(5px); }

        .document-icon {
            width: 40px; height: 40px; background: #E91E63;
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; color: white; margin-right: 15px;
        }
        .document-info h6    { margin: 0; color: #495057; }
        .document-info small { color: #6c757d; }

        .profile-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px; padding: 25px; margin-bottom: 25px;
        }

        .profile-avatar {
            width: 80px; height: 80px; background: #E91E63;
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; color: white; font-size: 2rem;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body class="employee-page">
<div class="container-fluid">
    <?php include 'employee_navigation.php'; ?>
    <div class="row">
        <?php include 'employee_sidebar.php'; ?>
        <div class="main-content">

            <!-- Welcome -->
            <div class="employee-welcome">
                <h2><i class="fas fa-user-circle mr-3"></i>Welcome, <?php echo ucfirst(explode('.', $username)[0]); ?>!</h2>
                <p>Here's your personal HR dashboard with all the information you need</p>
            </div>



            <!-- ── SURVEY NOTIFICATION BANNER ── -->
            <?php if ($hasPendingSurvey): ?>
            <div class="survey-notification" id="surveyNotification">
                <div class="survey-notif-icon">📋</div>
                <div class="survey-notif-text">
                    <?php if ($manualSentAt): ?>
                        <div class="survey-notif-badge">📣 HR Notification</div>
                        <h5>HR has sent you a Post-Exit Survey</h5>
                        <p>
                            Sent by HR on <?= date('F d, Y 	 h:i A', strtotime($manualSentAt)) ?>.
                            Your honest feedback helps improve the workplace. Takes about 5 minutes.
                        </p>
                    <?php else: ?>
                        <div class="survey-notif-badge">⚡ Action Required</div>
                        <h5>You have a pending Post-Exit Survey</h5>
                        <p>HR has requested your feedback. Your honest responses help improve the workplace for everyone. Takes about 5 minutes.</p>
                    <?php endif; ?>
                </div>
                <a href="employee_survey_form.php" class="survey-notif-btn">
                    <i class="fas fa-clipboard-list mr-1"></i> Take Survey
                </a>
                <button class="dismiss-btn" onclick="dismissBanner()" title="Dismiss">✕</button>
            </div>
            <?php endif; ?>
            <!-- ─────────────────────────────── -->

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h4 class="section-title">Quick Actions</h4>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="my_profile.php" class="quick-action-btn">
                            <i class="fas fa-user-edit"></i>
                            <h5>Update Profile</h5>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="#" class="quick-action-btn" data-toggle="modal" data-target="#leaveRequestModal">
                            <i class="fas fa-calendar-plus"></i>
                            <h5>Request Leave</h5>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="my_document.php" class="quick-action-btn">
                            <i class="fas fa-file-alt"></i>
                            <h5>Documents</h5>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="#" class="quick-action-btn">
                            <i class="fas fa-chart-line"></i>
                            <h5>Performance</h5>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Personal Information -->
                <div class="col-md-4">
                    <div class="profile-section">
                        <div class="profile-avatar"><i class="fas fa-user"></i></div>
                        <h5 class="text-center mb-3">Personal Information</h5>
                        <div class="info-item">
                            <span class="info-label">Employee ID:</span>
                            <span class="info-value"><?php echo $employee_id; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Username:</span>
                            <span class="info-value"><?php echo $username; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status:</span>
                            <span class="status-badge status-active">Active</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Department:</span>
                            <span class="info-value">IT Department</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Position:</span>
                            <span class="info-value">Software Developer</span>
                        </div>
                    </div>
                </div>

                <!-- Leave Information -->
                <div class="col-md-4">
                    <div class="info-card">
                        <h5><i class="fas fa-calendar-alt mr-2"></i>Leave Information</h5>
                        <div class="info-item"><span class="info-label">Annual Leave Balance:</span><span class="info-value">15 days</span></div>
                        <div class="info-item"><span class="info-label">Sick Leave Balance:</span><span class="info-value">10 days</span></div>
                        <div class="info-item"><span class="info-label">Pending Requests:</span><span class="info-value">2</span></div>
                        <div class="info-item"><span class="info-label">Last Leave Date:</span><span class="info-value">Aug 15, 2023</span></div>
                        <div class="text-center mt-3">
                            <button class="leave-request-btn" data-toggle="modal" data-target="#leaveRequestModal">
                                <i class="fas fa-plus mr-2"></i>Request Leave
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payroll Information -->
                <div class="col-md-4">
                    <div class="info-card">
                        <h5><i class="fas fa-money-bill-wave mr-2"></i>Payroll Information</h5>
                        <div class="info-item"><span class="info-label">Basic Salary:</span><span class="info-value">$4,500</span></div>
                        <div class="info-item"><span class="info-label">Allowances:</span><span class="info-value">$500</span></div>
                        <div class="info-item"><span class="info-label">Next Payroll:</span><span class="info-value">Aug 25, 2023</span></div>
                        <div class="info-item"><span class="info-label">Bank Account:</span><span class="info-value">****1234</span></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Documents -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5><i class="fas fa-file-alt mr-2"></i>Recent Documents</h5>
                        <div class="document-item">
                            <div class="document-icon"><i class="fas fa-file-pdf"></i></div>
                            <div class="document-info"><h6>Employment Contract</h6><small>Updated: Aug 20, 2023</small></div>
                        </div>
                        <div class="document-item">
                            <div class="document-icon"><i class="fas fa-file-word"></i></div>
                            <div class="document-info"><h6>Performance Review</h6><small>Updated: Aug 15, 2023</small></div>
                        </div>
                        <div class="document-item">
                            <div class="document-icon"><i class="fas fa-file-image"></i></div>
                            <div class="document-info"><h6>ID Badge</h6><small>Updated: Aug 10, 2023</small></div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="col-md-6">
                    <div class="info-card">
                        <h5><i class="fas fa-bell mr-2"></i>Upcoming Events</h5>
                        <div class="info-item"><span class="info-label">Team Meeting:</span><span class="info-value">Tomorrow, 10:00 AM</span></div>
                        <div class="info-item"><span class="info-label">Training Session:</span><span class="info-value">Friday, 2:00 PM</span></div>
                        <div class="info-item"><span class="info-label">Performance Review:</span><span class="info-value">Sep 5, 2023</span></div>
                        <div class="info-item"><span class="info-label">Company Event:</span><span class="info-value">Sep 15, 2023</span></div>
                    </div>
                </div>
            </div>

            <!-- Performance Overview -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="info-card">
                        <h5><i class="fas fa-chart-line mr-2"></i>Performance Overview</h5>
                        <div class="row">
                            <div class="col-md-3 text-center"><h3 class="text-success">4.2/5</h3><p class="text-muted">Overall Rating</p></div>
                            <div class="col-md-3 text-center"><h3 class="text-primary">87%</h3><p class="text-muted">Goal Achievement</p></div>
                            <div class="col-md-3 text-center"><h3 class="text-info">95%</h3><p class="text-muted">Attendance</p></div>
                            <div class="col-md-3 text-center"><h3 class="text-warning">3</h3><p class="text-muted">Projects Completed</p></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Leave Request Modal -->
<div class="modal fade" id="leaveRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Leave</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="form-group">
                        <label>Leave Type</label>
                        <select class="form-control" required>
                            <option value="">Select leave type</option>
                            <option>Annual Leave</option>
                            <option>Sick Leave</option>
                            <option>Personal Leave</option>
                            <option>Maternity Leave</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea class="form-control" rows="3" placeholder="Please provide a reason for your leave request"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Submit Request</button>
            </div>
        </div>
    </div>
</div>

<script>
    function dismissBanner() {
        const banner = document.getElementById('surveyNotification');
        if (banner) {
            banner.style.transition = 'opacity .4s, transform .4s';
            banner.style.opacity = '0';
            banner.style.transform = 'translateY(-10px)';
            setTimeout(() => banner.remove(), 400);
        }
    }
</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>