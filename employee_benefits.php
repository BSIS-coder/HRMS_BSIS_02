<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if user has permission (only admin and hr can manage employee benefits)
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once 'config.php';

// Get filter parameters
$plan_id = $_GET['plan_id'] ?? null;
$employee_search = $_GET['employee_search'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'enroll_employee':
                $employee_id = $_POST['employee_id'];
                $benefit_plan_id = $_POST['benefit_plan_id'];
                $enrollment_date = $_POST['enrollment_date'];
                $benefit_amount = $_POST['benefit_amount'] ?? null;

                try {
                    $sql = "INSERT INTO employee_benefits (employee_id, benefit_plan_id, enrollment_date, benefit_amount)
                            VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$employee_id, $benefit_plan_id, $enrollment_date, $benefit_amount]);
                    $success_message = "Employee enrolled in benefit plan successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error enrolling employee: " . $e->getMessage();
                }
                break;

            case 'update_enrollment':
                $benefit_id = $_POST['benefit_id'];
                $benefit_amount = $_POST['benefit_amount'];
                $status = $_POST['status'];

                try {
                    $sql = "UPDATE employee_benefits SET benefit_amount = ?, status = ? WHERE benefit_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$benefit_amount, $status, $benefit_id]);
                    $success_message = "Employee benefit updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating employee benefit: " . $e->getMessage();
                }
                break;

            case 'unenroll_employee':
                $benefit_id = $_POST['benefit_id'];

                try {
                    $sql = "UPDATE employee_benefits SET status = 'Inactive' WHERE benefit_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$benefit_id]);
                    $success_message = "Employee unenrolled from benefit plan successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error unenrolling employee: " . $e->getMessage();
                }
                break;

            case 'bulk_enroll':
                $benefit_plan_id = $_POST['benefit_plan_id'];
                $employee_ids = $_POST['employee_ids'] ?? [];
                $enrollment_date = $_POST['bulk_enrollment_date'];

                try {
                    $conn->beginTransaction();
                    $enrolled_count = 0;

                    foreach ($employee_ids as $employee_id) {
                        // Check if already enrolled
                        $check_sql = "SELECT COUNT(*) FROM employee_benefits
                                     WHERE employee_id = ? AND benefit_plan_id = ? AND status = 'Active'";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->execute([$employee_id, $benefit_plan_id]);

                        if ($check_stmt->fetchColumn() == 0) {
                            $sql = "INSERT INTO employee_benefits (employee_id, benefit_plan_id, enrollment_date)
                                    VALUES (?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$employee_id, $benefit_plan_id, $enrollment_date]);
                            $enrolled_count++;
                        }
                    }

                    $conn->commit();
                    $success_message = "{$enrolled_count} employees enrolled in benefit plan successfully!";
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $error_message = "Error in bulk enrollment: " . $e->getMessage();
                }
                break;
        }
    }
}

// Build the query for employee benefits with filters
$sql = "SELECT eb.*, ep.employee_number, CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
               bp.plan_name, bp.plan_type, bp.description as plan_description
        FROM employee_benefits eb
        JOIN employee_profiles ep ON eb.employee_id = ep.employee_id
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        JOIN benefits_plans bp ON eb.benefit_plan_id = bp.benefit_plan_id
        WHERE 1=1";

$params = [];

if ($plan_id) {
    $sql .= " AND eb.benefit_plan_id = ?";
    $params[] = $plan_id;
}

if ($employee_search) {
    $sql .= " AND (pi.first_name LIKE ? OR pi.last_name LIKE ? OR ep.employee_number LIKE ?)";
    $params[] = "%$employee_search%";
    $params[] = "%$employee_search%";
    $params[] = "%$employee_search%";
}

$sql .= " ORDER BY eb.enrollment_date DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $benefits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $benefits = [];
    $error_message = "Error fetching employee benefits: " . $e->getMessage();
}

// Fetch benefit plans for dropdown
try {
    $plans_sql = "SELECT * FROM benefits_plans ORDER BY plan_type, plan_name";
    $plans_stmt = $conn->query($plans_sql);
    $benefit_plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $benefit_plans = [];
}

// Fetch employees for enrollment
try {
    $emp_sql = "SELECT ep.employee_id, ep.employee_number,
                       CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
                       jr.title as job_title
                FROM employee_profiles ep
                JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                WHERE ep.employment_status IN ('Full-time', 'Part-time', 'Contract')
                ORDER BY pi.first_name";
    $emp_stmt = $conn->query($emp_sql);
    $employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Benefits Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .sidebar {
            height: 100vh;
            background-color: #800000;
            color: #fff;
            padding-top: 20px;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #fff #800000;
            z-index: 1030;
        }
        .main-content {
            margin-left: 250px;
            padding: 90px 20px 20px;
            transition: margin-left 0.3s;
            width: calc(100% - 250px);
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(128, 0, 0, 0.05);
            border: none;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-primary {
            background-color: #800000;
            border-color: #800000;
        }
        .btn-primary:hover {
            background-color: #660000;
            border-color: #660000;
        }
        .badge-active { background-color: #28a745; }
        .badge-inactive { background-color: #6c757d; }
        .plan-type-health { color: #667eea; }
        .plan-type-retirement { color: #f093fb; }
        .plan-type-insurance { color: #4facfe; }
        .plan-type-other { color: #43e97b; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-gift mr-2"></i>Employee Benefits Management</h2>
                    <div>
                        <button class="btn btn-primary mr-2" data-toggle="modal" data-target="#enrollEmployeeModal">
                            <i class="fas fa-user-plus"></i> Enroll Employee
                        </button>
                        <button class="btn btn-success" data-toggle="modal" data-target="#bulkEnrollModal">
                            <i class="fas fa-users"></i> Bulk Enroll
                        </button>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error_message; ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row">
                            <div class="col-md-4">
                                <select name="plan_id" class="form-control">
                                    <option value="">All Benefit Plans</option>
                                    <?php foreach ($benefit_plans as $plan): ?>
                                        <option value="<?php echo $plan['benefit_plan_id']; ?>"
                                                <?php echo ($plan_id == $plan['benefit_plan_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($plan['plan_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="employee_search" class="form-control"
                                       placeholder="Search employee..." value="<?php echo htmlspecialchars($employee_search); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary mr-2">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="employee_benefits.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list mr-2"></i>Employee Benefit Enrollments (<?php echo count($benefits); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Employee #</th>
                                        <th>Benefit Plan</th>
                                        <th>Type</th>
                                        <th>Enrollment Date</th>
                                        <th>Benefit Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($benefits)): ?>
                                        <?php foreach ($benefits as $benefit): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($benefit['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($benefit['employee_number']); ?></td>
                                                <td><?php echo htmlspecialchars($benefit['plan_name']); ?></td>
                                                <td>
                                                    <span class="plan-type-<?php echo strtolower($benefit['plan_type']); ?>">
                                                        <i class="fas fa-circle mr-1"></i><?php echo htmlspecialchars($benefit['plan_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($benefit['enrollment_date'])); ?></td>
                                                <td>
                                                    <?php if ($benefit['benefit_amount']): ?>
                                                        ₱<?php echo number_format($benefit['benefit_amount'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo strtolower($benefit['status']); ?>">
                                                        <?php echo htmlspecialchars($benefit['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary"
                                                                onclick="editEnrollment(<?php echo htmlspecialchars(json_encode($benefit)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($benefit['status'] == 'Active'): ?>
                                                            <form method="post" style="display: inline;" onsubmit="return confirm('Unenroll this employee from the benefit plan?');">
                                                                <input type="hidden" name="action" value="unenroll_employee">
                                                                <input type="hidden" name="benefit_id" value="<?php echo $benefit['benefit_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                                    <i class="fas fa-user-times"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="fas fa-gift fa-3x text-muted mb-3"></i>
                                                <h5>No benefit enrollments found</h5>
                                                <p class="text-muted">Use the buttons above to enroll employees in benefit plans.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enroll Employee Modal -->
    <div class="modal fade" id="enrollEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enroll Employee in Benefit Plan</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="enroll_employee">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="employee_id">Employee</label>
                                    <select class="form-control" id="employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['employee_id']; ?>">
                                                <?php echo htmlspecialchars($employee['full_name'] . ' (' . $employee['employee_number'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="benefit_plan_id">Benefit Plan</label>
                                    <select class="form-control" id="benefit_plan_id" name="benefit_plan_id" required>
                                        <option value="">Select Benefit Plan</option>
                                        <?php foreach ($benefit_plans as $plan): ?>
                                            <option value="<?php echo $plan['benefit_plan_id']; ?>">
                                                <?php echo htmlspecialchars($plan['plan_name'] . ' (' . $plan['plan_type'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="enrollment_date">Enrollment Date</label>
                                    <input type="date" class="form-control" id="enrollment_date" name="enrollment_date"
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="benefit_amount">Benefit Amount (₱) <small class="text-muted">Optional</small></label>
                                    <input type="number" class="form-control" id="benefit_amount" name="benefit_amount" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Enroll Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Enroll Modal -->
    <div class="modal fade" id="bulkEnrollModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Employee Enrollment</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_enroll">
                        <div class="form-group">
                            <label for="bulk_benefit_plan_id">Benefit Plan</label>
                            <select class="form-control" id="bulk_benefit_plan_id" name="benefit_plan_id" required>
                                <option value="">Select Benefit Plan</option>
                                <?php foreach ($benefit_plans as $plan): ?>
                                    <option value="<?php echo $plan['benefit_plan_id']; ?>">
                                        <?php echo htmlspecialchars($plan['plan_name'] . ' (' . $plan['plan_type'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bulk_enrollment_date">Enrollment Date</label>
                            <input type="date" class="form-control" id="bulk_enrollment_date" name="bulk_enrollment_date"
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Select Employees to Enroll</label>
                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="select_all" onchange="toggleAllEmployees()">
                                    <label class="form-check-label font-weight-bold" for="select_all">
                                        Select All Employees
                                    </label>
                                </div>
                                <hr>
                                <?php foreach ($employees as $employee): ?>
                                    <div class="form-check">
                                        <input class="form-check-input employee-checkbox" type="checkbox"
                                               name="employee_ids[]" value="<?php echo $employee['employee_id']; ?>"
                                               id="emp_<?php echo $employee['employee_id']; ?>">
                                        <label class="form-check-label" for="emp_<?php echo $employee['employee_id']; ?>">
                                            <?php echo htmlspecialchars($employee['full_name'] . ' (' . $employee['employee_number'] . ') - ' . $employee['job_title']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Enroll Selected Employees</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Enrollment Modal -->
    <div class="modal fade" id="editEnrollmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Benefit Enrollment</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_enrollment">
                        <input type="hidden" name="benefit_id" id="edit_benefit_id">
                        <div class="form-group">
                            <label>Employee</label>
                            <p class="form-control-plaintext" id="edit_employee_name"></p>
                        </div>
                        <div class="form-group">
                            <label>Benefit Plan</label>
                            <p class="form-control-plaintext" id="edit_plan_name"></p>
                        </div>
                        <div class="form-group">
                            <label for="edit_benefit_amount">Benefit Amount (₱)</label>
                            <input type="number" class="form-control" id="edit_benefit_amount" name="benefit_amount" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status</label>
                            <select class="form-control" id="edit_status" name="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Enrollment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        function editEnrollment(benefit) {
            document.getElementById('edit_benefit_id').value = benefit.benefit_id;
            document.getElementById('edit_employee_name').textContent = benefit.full_name + ' (' + benefit.employee_number + ')';
            document.getElementById('edit_plan_name').textContent = benefit.plan_name + ' (' + benefit.plan_type + ')';
            document.getElementById('edit_benefit_amount').value = benefit.benefit_amount || '';
            document.getElementById('edit_status').value = benefit.status;
            $('#editEnrollmentModal').modal('show');
        }

        function toggleAllEmployees() {
            const selectAll = document.getElementById('select_all');
            const checkboxes = document.querySelectorAll('.employee-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
    </script>
</body>
</html>
