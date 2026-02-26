<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once 'config.php';

// Check if user is logged in and is an employee
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'employee') {
    header("location: login.php");
    exit;
}

// Initialize variables
$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$employee_id = 0;
$success_message = '';
$error_message = '';
$employee = null;
$existing_resignation = null;

// Get employee_id from users table
if ($user_id > 0) {
    try {
        // -------------------------------------------------------
        // FIX: Dynamically check which columns exist in 'users'
        // so this works on both localhost (no email col) and
        // the production server (has email col).
        // -------------------------------------------------------
        $columns_stmt = $conn->query("SHOW COLUMNS FROM users");
        $user_columns = array_column($columns_stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

        $select_fields = ['employee_id'];
        if (in_array('email', $user_columns)) {
            $select_fields[] = 'email';
        }

        $lookup_query = "SELECT " . implode(', ', $select_fields) . " FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($lookup_query);
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if ($row && $row['employee_id']) {
            $employee_id = $row['employee_id'];
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

if ($employee_id == 0) {
    $error_message = "Could not find your employee record. Please contact HR to link your account.";
}

// Get employee details
if ($employee_id > 0) {
    try {
        // -------------------------------------------------------
        // FIX: Dynamically check which columns exist in
        // 'personal_information' to handle environments where
        // 'email' (or other columns) may or may not be present.
        // -------------------------------------------------------
        $pi_cols_stmt = $conn->query("SHOW COLUMNS FROM personal_information");
        $pi_columns = array_column($pi_cols_stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

        $pi_select = ['pi.first_name', 'pi.last_name'];
        if (in_array('email', $pi_columns)) {
            $pi_select[] = 'pi.email';
        }

        $employee_query = "SELECT 
                            " . implode(', ', $pi_select) . ",
                            ep.employee_number as employee_code, 
                            jr.title as job_title, 
                            jr.department as department_name,
                            ep.personal_info_id,
                            ep.job_role_id
                           FROM employee_profiles ep
                           LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                           LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                           WHERE ep.employee_id = ?";

        $stmt = $conn->prepare($employee_query);
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch();

        if ($employee) {
            // If personal info is NULL, use username as fallback
            if (empty($employee['first_name']) && !empty($username)) {
                $name_parts = explode('.', $username);
                $employee['first_name'] = ucfirst($name_parts[0] ?? '');
                $employee['last_name']  = ucfirst($name_parts[1] ?? '');
            }

            // If employee_code is NULL, generate one from employee_id
            if (empty($employee['employee_code'])) {
                $employee['employee_code'] = 'EMP' . str_pad($employee_id, 4, '0', STR_PAD_LEFT);
            }

            // If job_title or department is NULL, try employment_history
            if (empty($employee['job_title']) || empty($employee['department_name'])) {
                $history_query = "SELECT eh.job_title, d.department_name 
                                 FROM employment_history eh
                                 LEFT JOIN departments d ON eh.department_id = d.department_id
                                 WHERE eh.employee_id = ?
                                 ORDER BY eh.start_date DESC LIMIT 1";
                $history_stmt = $conn->prepare($history_query);
                $history_stmt->execute([$employee_id]);
                $history = $history_stmt->fetch();

                if ($history) {
                    if (empty($employee['job_title']) && !empty($history['job_title'])) {
                        $employee['job_title'] = $history['job_title'];
                    }
                    if (empty($employee['department_name']) && !empty($history['department_name'])) {
                        $employee['department_name'] = $history['department_name'];
                    }
                }
            }

            // Final fallbacks
            if (empty($employee['job_title'])) {
                $employee['job_title'] = 'Employee';
            }
            if (empty($employee['department_name'])) {
                $employee['department_name'] = 'Not Assigned';
            }
        }
    } catch (PDOException $e) {
        $error_message = "Error loading employee data: " . $e->getMessage();
    }
}

// Check if employee already has a pending or processing resignation
if ($employee_id > 0) {
    try {
        $check_query = "SELECT * FROM exits WHERE employee_id = ? AND status IN ('Pending', 'Processing') ORDER BY created_at DESC LIMIT 1";
        $check_stmt  = $conn->prepare($check_query);
        $check_stmt->execute([$employee_id]);
        $existing_resignation = $check_stmt->fetch();
    } catch (PDOException $e) {
        // Silent fail
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$existing_resignation) {
    $exit_type   = trim($_POST['exit_type']   ?? '');
    $notice_date = trim($_POST['notice_date'] ?? '');
    $exit_date   = trim($_POST['exit_date']   ?? '');
    $exit_reason = trim($_POST['exit_reason'] ?? '');

    if (empty($exit_type) || empty($notice_date) || empty($exit_date) || empty($exit_reason)) {
        $error_message = "Please fill in all required fields.";
    } else {
        $today = date('Y-m-d');
        if ($notice_date < $today) {
            $error_message = "Notice date cannot be in the past.";
        } elseif ($exit_date <= $notice_date) {
            $error_message = "Exit date (last working day) must be after notice date.";
        } else {
            try {
                $insert_query = "INSERT INTO exits (employee_id, exit_type, exit_reason, notice_date, exit_date, status) 
                               VALUES (?, ?, ?, ?, ?, 'Pending')";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->execute([$employee_id, $exit_type, $exit_reason, $notice_date, $exit_date]);

                $resignation_id   = $conn->lastInsertId();
                $reference_number = "RES-" . $resignation_id;

                $success_message  = "Your resignation has been filed successfully (Reference: <strong>" . $reference_number . "</strong>). ";
                $success_message .= "HR will review your resignation shortly.";

                header("refresh:2;url=file_resignation.php");
            } catch (PDOException $e) {
                $error_message = "Error submitting resignation: " . $e->getMessage();
            }
        }
    }
}

// Resignation types
$resignation_types = [
    'Resignation',
    'Retirement',
    'End of Contract'
];

// Get departments list
$departments = [];
try {
    $dept_query = "SELECT department_id, department_name FROM departments ORDER BY department_name";
    $dept_stmt  = $conn->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll();
} catch (PDOException $e) {
    // Silent fail
}

// Get job roles list
$job_roles = [];
try {
    $role_query = "SELECT job_role_id, title FROM job_roles ORDER BY title";
    $role_stmt  = $conn->prepare($role_query);
    $role_stmt->execute();
    $job_roles = $role_stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching job roles: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Resignation - HRMS</title>

    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        body { background-color: #f8f9fa; }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .resignation-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .page-header {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .form-group label { font-weight: 600; color: #333; }

        .btn-submit {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #C2185B 0%, #AD1457 100%);
            color: white;
        }

        .alert-warning-custom {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
        }

        .pending-resignation {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .processing-resignation {
            background: #cfe2ff;
            border-left: 4px solid #0d6efd;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
        }
    </style>
</head>
<body class="employee-page">

    <?php include 'employee_sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <h2><i class="fas fa-sign-out-alt"></i> File Resignation</h2>
            <p class="mb-0">Submit your resignation request</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (!$employee && $employee_id > 0): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> Unable to load employee information. Please contact HR.
            </div>

        <?php elseif ($existing_resignation): ?>
            <div class="<?php echo $existing_resignation['status'] == 'Pending' ? 'pending-resignation' : 'processing-resignation'; ?>">
                <h5>
                    <i class="fas fa-info-circle"></i>
                    <?php echo $existing_resignation['status'] == 'Pending' ? 'Resignation Pending Review' : 'Resignation Being Processed'; ?>
                </h5>
                <p class="mb-2"><strong>Type:</strong> <?php echo htmlspecialchars($existing_resignation['exit_type']); ?></p>
                <p class="mb-2"><strong>Notice Date:</strong> <?php echo date('F d, Y', strtotime($existing_resignation['notice_date'])); ?></p>
                <p class="mb-2"><strong>Last Working Day:</strong> <?php echo date('F d, Y', strtotime($existing_resignation['exit_date'])); ?></p>
                <p class="mb-2"><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($existing_resignation['exit_reason'])); ?></p>
                <p class="mb-2"><strong>Status:</strong>
                    <span class="badge badge-<?php
                        echo $existing_resignation['status'] == 'Pending'    ? 'warning' :
                            ($existing_resignation['status'] == 'Processing' ? 'info'    : 'success');
                    ?>">
                        <?php echo htmlspecialchars($existing_resignation['status']); ?>
                    </span>
                </p>
                <?php if ($existing_resignation['status'] == 'Pending'): ?>
                    <p class="mb-0 mt-3"><em>Your resignation is currently under review by HR. You will be notified once it's processed.</em></p>
                <?php elseif ($existing_resignation['status'] == 'Processing'): ?>
                    <p class="mb-0 mt-3"><em>Your resignation is being processed. HR will contact you regarding the exit procedures.</em></p>
                <?php endif; ?>
            </div>

        <?php elseif ($employee): ?>

            <div class="resignation-card">
                <div class="alert-warning-custom mb-4">
                    <h6><i class="fas fa-exclamation-triangle"></i> Important Notice</h6>
                    <p class="mb-0">Please carefully review all information before submitting your resignation. This action will notify HR and initiate the exit process.</p>
                </div>

                <form method="POST" action="" id="resignationForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Employee Name</label>
                                <input type="text" class="form-control"
                                       value="<?php echo htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')); ?>"
                                       readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Employee ID</label>
                                <input type="text" class="form-control"
                                       value="<?php echo htmlspecialchars($employee['employee_code'] ?? 'N/A'); ?>"
                                       readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Department</label>
                                <select class="form-control" name="department_id">
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept['department_id']); ?>"
                                            <?php echo (isset($employee['department_name']) && $employee['department_name'] == $dept['department_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Select your department</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Position</label>
                                <select class="form-control" name="job_role_id">
                                    <option value="">-- Select Position --</option>
                                    <?php foreach ($job_roles as $role): ?>
                                        <option value="<?php echo htmlspecialchars($role['job_role_id']); ?>"
                                            <?php echo (isset($employee['job_title']) && $employee['job_title'] == $role['title']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Select your position</small>
                            </div>
                        </div>
                    </div>

                    <h5 class="mb-3">Resignation Details</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Resignation Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="exit_type" required>
                                    <option value="">-- Select Type --</option>
                                    <?php foreach ($resignation_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Submission Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="notice_date"
                                       min="<?php echo date('Y-m-d'); ?>"
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <small class="form-text text-muted">The date you submitted the resignation</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Last Working Day (Exit Date) <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="exit_date"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                <small class="form-text text-muted">Your intended last day at work</small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Reason for Leaving <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="exit_reason" rows="5"
                                          placeholder="Please provide your reason for leaving..." required></textarea>
                                <small class="form-text text-muted">Please be as detailed as possible</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input type="checkbox" class="form-check-input" id="confirmCheck" required>
                        <label class="form-check-label" for="confirmCheck">
                            I confirm that the information provided is accurate and I understand that this resignation will be reviewed by HR.
                        </label>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Resignation
                        </button>
                        <a href="employee_index.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        var form = document.getElementById('resignationForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                var noticeDate = new Date(document.querySelector('input[name="notice_date"]').value);
                var exitDate   = new Date(document.querySelector('input[name="exit_date"]').value);

                if (exitDate <= noticeDate) {
                    e.preventDefault();
                    alert('Last working day (Exit Date) must be after the notice date.');
                    return false;
                }

                if (!document.getElementById('confirmCheck').checked) {
                    e.preventDefault();
                    alert('Please confirm the information before submitting.');
                    return false;
                }

                return confirm('Are you sure you want to submit this resignation? This action cannot be undone.');
            });
        }

        var noticeDateInput = document.querySelector('input[name="notice_date"]');
        if (noticeDateInput) {
            noticeDateInput.addEventListener('change', function() {
                var selectedDate = new Date(this.value);
                selectedDate.setDate(selectedDate.getDate() + 1);
                var minExitDate = selectedDate.toISOString().split('T')[0];
                document.querySelector('input[name="exit_date"]').min = minExitDate;
            });
        }
    </script>
</body>
</html>