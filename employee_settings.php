<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'My Settings';

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'change_password':
            // Handle password change
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password === $confirm_password && strlen($new_password) >= 6) {
                // Here you would verify current password and update
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Passwords do not match or are too short.';
            }
            break;
            
        case 'update_preferences':
            // Handle preferences update
            $success_message = 'Preferences updated successfully!';
            break;
            
        case 'update_notifications':
            // Handle notification preferences
            $success_message = 'Notification preferences updated!';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HR Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=rose">
    
    <style>
        :root {
            /* Align palette with existing design */
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
            /* Back-compat with existing variable names */
            --primary-color: var(--azure-blue);
            --secondary-color: var(--azure-blue-dark);
        }

        body {
            background: var(--azure-blue-pale);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container-fluid { padding: 0; }
        .row { margin-right: 0; margin-left: 0; }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .settings-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .settings-card-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 15px 20px;
            border-bottom: none;
        }

        .settings-card-body { padding: 20px; }

        .form-group label {
            font-weight: 600;
            color: var(--azure-blue-dark);
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            padding: 6px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: 600;
            color: #fff;
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }

        .nav-tabs { border-bottom: 2px solid #dee2e6; margin-bottom: 20px; }
        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 20px;
            margin-right: 5px;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            border: none;
        }

        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc; transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--azure-blue); }
        input:checked + .slider:before { transform: translateX(26px); }

        .stat-card {
            background: white; border-radius: 10px; padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;
        }
        .stat-card i { font-size: 2.5rem; color: var(--azure-blue); margin-bottom: 15px; }
        .stat-card h4 { color: #333; margin-bottom: 5px; }
        .stat-card p { color: #6c757d; margin: 0; }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--azure-blue), var(--azure-blue-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }

        .readonly-field {
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'employee_sidebar.php'; ?>

            <!-- Main Content -->
            <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Settings Navigation -->
        <ul class="nav nav-tabs" id="settingsTabs">
            <li class="nav-item">
                <a class="nav-link active" id="security-tab" data-toggle="tab" href="#security">
                    <i class="fas fa-shield-alt mr-2"></i>Security
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="preferences-tab" data-toggle="tab" href="#preferences">
                    <i class="fas fa-sliders-h mr-2"></i>Preferences
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="notifications-tab" data-toggle="tab" href="#notifications">
                    <i class="fas fa-bell mr-2"></i>Notifications
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="settingsTabsContent">
            <!-- Security Settings Tab -->
            <div class="tab-pane fade show active" id="security" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h5 class="mb-0"><i class="fas fa-key mr-2"></i>Change Password</h5>
                            </div>
                            <div class="settings-card-body">
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="form-group">
                                        <label for="current_password">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="new_password">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password" required>
                                        <small class="form-text text-muted">
                                            Password must be at least 6 characters long
                                        </small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-lock mr-2"></i>Update Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-shield-alt"></i>
                            <h4>Security Status</h4>
                            <p class="text-success">
                                <i class="fas fa-check-circle mr-1"></i>Account Secure
                            </p>
                            <hr>
                            <small class="text-muted">
                                Password last changed: 30 days ago
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preferences Tab -->
            <div class="tab-pane fade" id="preferences" role="tabpanel">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h5 class="mb-0"><i class="fas fa-sliders-h mr-2"></i>Personal Preferences</h5>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST" id="preferencesForm">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="time_zone">Time Zone</label>
                                        <select class="form-control" id="time_zone" name="time_zone">
                                            <option value="UTC-8">Pacific Time (UTC-8)</option>
                                            <option value="UTC-5" selected>Eastern Time (UTC-5)</option>
                                            <option value="UTC">GMT (UTC)</option>
                                            <option value="UTC+1">Central European (UTC+1)</option>
                                            <option value="UTC+8">Philippine Time (UTC+8)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="date_format">Date Format</label>
                                        <select class="form-control" id="date_format" name="date_format">
                                            <option value="MM/DD/YYYY" selected>MM/DD/YYYY</option>
                                            <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                                            <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="language">Display Language</label>
                                        <select class="form-control" id="language" name="language">
                                            <option value="en" selected>English</option>
                                            <option value="es">Spanish</option>
                                            <option value="fr">French</option>
                                            <option value="de">German</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="theme">Theme Preference</label>
                                        <select class="form-control" id="theme" name="theme">
                                            <option value="light" selected>Light Theme</option>
                                            <option value="dark">Dark Theme</option>
                                            <option value="auto">Auto (System)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Email Digest</strong>
                                                <br><small class="text-muted">Receive daily summary emails</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_digest" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Desktop Notifications</strong>
                                                <br><small class="text-muted">Show browser notifications</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="desktop_notifications">
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i>Save Preferences
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div class="tab-pane fade" id="notifications" role="tabpanel">
                <div class="settings-card">
                    <div class="settings-card-header">
                        <h5 class="mb-0"><i class="fas fa-bell mr-2"></i>Notification Settings</h5>
                    </div>
                    <div class="settings-card-body">
                        <form method="POST" id="notificationsForm">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <h6 class="mb-3">Email Notifications</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Leave Status Updates</strong>
                                                <br><small class="text-muted">Updates on your leave requests</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_leave" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Performance Reviews</strong>
                                                <br><small class="text-muted">Review deadlines and feedback</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_performance" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Training Reminders</strong>
                                                <br><small class="text-muted">Upcoming training sessions</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_training" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Payroll Notifications</strong>
                                                <br><small class="text-muted">Payslip and benefits updates</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_payroll" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Company Announcements</strong>
                                                <br><small class="text-muted">Important company updates</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_announcements" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Birthday Reminders</strong>
                                                <br><small class="text-muted">Team member birthdays</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="email_birthdays">
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3">Push Notifications</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Urgent Messages</strong>
                                                <br><small class="text-muted">High priority notifications</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="push_urgent" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Meeting Reminders</strong>
                                                <br><small class="text-muted">Upcoming meeting alerts</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="push_meetings" checked>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Task Deadlines</strong>
                                                <br><small class="text-muted">Project deadline reminders</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="push_deadlines">
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Time Off Reminders</strong>
                                                <br><small class="text-muted">Vacation and leave alerts</small>
                                            </div>
                                            <label class="switch">
                                                <input type="checkbox" name="push_timeoff">
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i>Save Notification Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- jQuery, Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Form validation and submission
            $('#passwordForm').on('submit', function(e) {
                const currentPassword = $('#current_password').val();
                const newPassword = $('#new_password').val();
                const confirmPassword = $('#confirm_password').val();
                
                if (!currentPassword || !newPassword || !confirmPassword) {
                    e.preventDefault();
                    showAlert('Please fill in all password fields.', 'danger');
                    return false;
                }
                
                if (newPassword.length < 6) {
                    e.preventDefault();
                    showAlert('New password must be at least 6 characters long.', 'danger');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showAlert('New passwords do not match.', 'danger');
                    return false;
                }
            });

            // Tab switching with URL hash
            if (window.location.hash) {
                const tab = window.location.hash.substring(1);
                $('#' + tab + '-tab').tab('show');
            }
            
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                const target = $(e.target).attr('href');
                window.location.hash = target.substring(1);
            });

            // Switch toggles
            $('.switch input[type="checkbox"]').on('change', function() {
                const isChecked = $(this).is(':checked');
                const settingName = $(this).attr('id') || $(this).attr('name');
                
                // Auto-save setting
                saveToggleSetting(settingName, isChecked);
            });

            // Theme switching
            $('#theme').on('change', function() {
                const theme = $(this).val();
                // Persist to localStorage if available
                try { 
                    localStorage.setItem('employee_theme', theme); 
                } catch (e) {
                    console.log('localStorage not available');
                }
                // Apply immediately
                applyTheme(theme);
            });

            // Initialize theme selector from saved preference
            (function initThemeFromStorage(){
                try {
                    const saved = localStorage.getItem('employee_theme');
                    if (saved) {
                        $('#theme').val(saved);
                        applyTheme(saved);
                    }
                } catch(e) {
                    console.log('localStorage not available');
                }
            })();

            // Auto-save for preferences
            $('#preferencesForm input, #preferencesForm select').on('change', function() {
                const settingName = $(this).attr('name');
                const settingValue = $(this).val();
                
                // Auto-save after 2 seconds of no changes
                clearTimeout(window.settingsTimeout);
                window.settingsTimeout = setTimeout(function() {
                    saveSetting(settingName, settingValue);
                }, 2000);
            });

            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();

            // Password strength indicator
            $('#new_password').on('input', function() {
                const password = $(this).val();
                updatePasswordStrength(password);
            });

            // Request notification permission
            if ('Notification' in window && $('.switch input[name="desktop_notifications"]').length) {
                $('.switch input[name="desktop_notifications"]').on('change', function() {
                    if ($(this).is(':checked')) {
                        if (Notification.permission === 'default') {
                            Notification.requestPermission().then(function(permission) {
                                if (permission !== 'granted') {
                                    $(this).prop('checked', false);
                                    showAlert('Notification permission denied. Please enable in browser settings.', 'warning');
                                }
                            });
                        } else if (Notification.permission === 'denied') {
                            $(this).prop('checked', false);
                            showAlert('Notifications are blocked. Please enable in browser settings.', 'warning');
                        }
                    }
                });
            }

            // Switch to active tab based on URL hash
            (function() {
                if (window.location.hash) {
                    const hash = window.location.hash;
                    $('a[data-toggle="tab"][href="' + hash + '"]').tab('show');
                }
            })();
        });

        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'} mr-2"></i>
                    ${message}
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            `;
            $('.main-content').prepend(alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);
        }

        function saveToggleSetting(settingName, isChecked) {
            // Show saving indicator
            const toggle = $(`[name="${settingName}"]`).closest('.form-group');
            const saveIndicator = $('<small class="text-muted ml-2"><i class="fas fa-spinner fa-spin"></i> Saving...</small>');
            toggle.append(saveIndicator);
            
            // Simulate AJAX save
            setTimeout(function() {
                saveIndicator.remove();
                const savedIndicator = $('<small class="text-success ml-2"><i class="fas fa-check"></i> Saved</small>');
                toggle.append(savedIndicator);
                
                setTimeout(function() {
                    savedIndicator.fadeOut(function() {
                        $(this).remove();
                    });
                }, 2000);
            }, 1000);
        }

        function saveSetting(settingName, settingValue) {
            // Show saving indicator
            const saveIndicator = $('<small class="text-muted ml-2"><i class="fas fa-spinner fa-spin"></i> Saving...</small>');
            $(`[name="${settingName}"]`).parent().append(saveIndicator);
            
            // Simulate AJAX save
            setTimeout(function() {
                saveIndicator.remove();
                const savedIndicator = $('<small class="text-success ml-2"><i class="fas fa-check"></i> Saved</small>');
                $(`[name="${settingName}"]`).parent().append(savedIndicator);
                
                setTimeout(function() {
                    savedIndicator.fadeOut(function() {
                        $(this).remove();
                    });
                }, 2000);
            }, 1000);
        }

        function applyTheme(theme) {
            // Theme switching logic
            if (theme === 'dark') {
                $('body').addClass('dark-theme');
                showAlert('Dark theme applied successfully!', 'success');
            } else if (theme === 'light') {
                $('body').removeClass('dark-theme');
                showAlert('Light theme applied successfully!', 'success');
            } else if (theme === 'auto') {
                // Auto theme based on system preference
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    $('body').addClass('dark-theme');
                } else {
                    $('body').removeClass('dark-theme');
                }
                showAlert('Auto theme applied successfully!', 'success');
            }
        }

        function updatePasswordStrength(password) {
            let strength = 0;
            let strengthText = '';
            let strengthClass = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch (strength) {
                case 0:
                case 1:
                    strengthText = 'Very Weak';
                    strengthClass = 'text-danger';
                    break;
                case 2:
                    strengthText = 'Weak';
                    strengthClass = 'text-warning';
                    break;
                case 3:
                    strengthText = 'Fair';
                    strengthClass = 'text-info';
                    break;
                case 4:
                    strengthText = 'Good';
                    strengthClass = 'text-primary';
                    break;
                case 5:
                    strengthText = 'Strong';
                    strengthClass = 'text-success';
                    break;
            }
            
            // Remove existing strength indicator
            $('#new_password').siblings('.password-strength').remove();
            
            // Add new strength indicator
            if (password.length > 0) {
                const strengthIndicator = $(`<small class="form-text password-strength ${strengthClass}">Password Strength: ${strengthText}</small>`);
                $('#new_password').after(strengthIndicator);
            }
        }

        // Responsive sidebar toggle for mobile
        function toggleSidebar() {
            $('.sidebar').toggleClass('show');
        }

        // Add mobile menu button if screen is small
        if (window.innerWidth <= 768) {
            const mobileToggle = `
                <button class="btn btn-primary d-md-none mb-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i> Menu
                </button>
            `;
            $('.page-header .d-flex').prepend(mobileToggle);
        }
    </script>

    <!-- Dark theme styles -->
    <style>
        .dark-theme {
            background-color: #121212 !important;
            color: #ffffff !important;
        }
        
        .dark-theme .settings-card,
        .dark-theme .page-header,
        .dark-theme .stat-card {
            background-color: #1e1e1e !important;
            color: #ffffff !important;
            border-color: #333 !important;
        }
        
        .dark-theme .form-control {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
            color: #ffffff !important;
        }
        
        .dark-theme .form-control:focus {
            background-color: #2d2d2d !important;
            border-color: var(--primary-color) !important;
            color: #ffffff !important;
        }
        
        .dark-theme .form-control.readonly-field {
            background-color: #363636 !important;
            border-color: #555 !important;
            color: #cccccc !important;
        }
        
        .dark-theme .nav-tabs .nav-link {
            color: #cccccc !important;
            border-color: #444 !important;
        }
        
        .dark-theme .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: white !important;
        }
        
        .dark-theme .profile-avatar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
        }
        
        .dark-theme .text-muted {
            color: #aaa !important;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                padding: 15px !important;
            }
            
            .settings-card-body {
                padding: 15px !important;
            }
            
            .profile-avatar {
                width: 80px !important;
                height: 80px !important;
                font-size: 2rem !important;
            }
        }
    </style>
</body>
</html>