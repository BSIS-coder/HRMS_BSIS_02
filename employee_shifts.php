<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['addEmployeeShift'])) {
        // Add new employee shift assignment
        $employeeId = $_POST['employeeId'];
        $shiftId = $_POST['shiftId'];
        $assignedDate = $_POST['assignedDate'];
        $isOvertime = isset($_POST['isOvertime']) ? 1 : 0;

        if (addEmployeeShift($employeeId, $shiftId, $assignedDate, $isOvertime)) {
            // Redirect to refresh the page and show the new assignment
            header("Location: employee_shifts.php");
            exit;
        } else {
            $error = "Error adding employee shift assignment";
        }
    } elseif (isset($_POST['editEmployeeShift'])) {
        // Edit existing employee shift assignment
        $employeeShiftId = $_POST['employeeShiftId'];
        $shiftId = $_POST['shiftId'];
        $assignedDate = $_POST['assignedDate'];
        $isOvertime = isset($_POST['isOvertime']) ? 1 : 0;

        if (updateEmployeeShift($employeeShiftId, $shiftId, $assignedDate, $isOvertime)) {
            // Redirect to refresh the page
            header("Location: employee_shifts.php");
            exit;
        } else {
            $error = "Error updating employee shift assignment";
        }
    } elseif (isset($_POST['deleteEmployeeShift'])) {
        // Delete employee shift assignment
        $employeeShiftId = $_POST['employeeShiftId'];

        if (deleteEmployeeShift($employeeShiftId)) {
            // Redirect to refresh the page
            header("Location: employee_shifts.php");
            exit;
        } else {
            $error = "Error deleting employee shift assignment";
        }
    }
}

// Fetch actual data from database
$employeeShifts = getEmployeeShifts();
$employees = getEmployees();
$shifts = getShifts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Shifts - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .employee-shift-card {
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
        }
        
        .employee-shift-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-medium);
        }
        
        .sample-data {
            background-color: #f8f9fa;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Employee Shifts Management</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-user-clock mr-2"></i>Employee Shifts Overview</h5>
                                <button class="btn btn-primary" data-toggle="modal" data-target="#addEmployeeShiftModal">
                                    <i class="fas fa-plus mr-2"></i>Add Employee Shift
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Shift</th>
                                                <th>Assigned Date</th>
                                                <th>Overtime</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($employeeShifts)): ?>
                                                <!-- Sample data that will be removed when actual data exists -->
                                                <tr class="sample-data">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="https://ui-avatars.com/api/?name=John+Doe&background=E91E63&color=fff&size=35" 
                                                                 alt="Profile" class="profile-image mr-2">
                                                            <div>
                                                                <h6 class="mb-0">John Doe</h6>
                                                                <small class="text-muted">IT Department</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>Morning Shift</td>
                                                    <td>2024-01-15</td>
                                                    <td>No</td>
                                                    <td>
                                                        <span class="badge badge-success">Active</span>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">Add real data to enable actions</span>
                                                    </td>
                                                </tr>
                                                <tr class="sample-data">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="https://ui-avatars.com/api/?name=Jane+Smith&background=2196F3&color=fff&size=35" 
                                                                 alt="Profile" class="profile-image mr-2">
                                                            <div>
                                                                <h6 class="mb-0">Jane Smith</h6>
                                                                <small class="text-muted">HR Department</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>Evening Shift</td>
                                                    <td>2024-01-15</td>
                                                    <td>Yes</td>
                                                    <td>
                                                        <span class="badge badge-success">Active</span>
                                                    </td>
                                                    <td>
                                                        <span class="text-muted">Add real data to enable actions</span>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($employeeShifts as $shift): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($shift['first_name'] . ' ' . $shift['last_name']); ?>&background=E91E63&color=fff&size=35" 
                                                                     alt="Profile" class="profile-image mr-2">
                                                                <div>
                                                                    <h6 class="mb-0"><?php echo htmlspecialchars($shift['first_name'] . ' ' . $shift['last_name']); ?></h6>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($shift['department_name'] ?? 'Department'); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($shift['shift_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($shift['assigned_date']); ?></td>
                                                        <td><?php echo $shift['is_overtime'] ? 'Yes' : 'No'; ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $shift['is_overtime'] ? 'badge-success' : 'badge-danger'; ?>">
                                                                <?php echo $shift['is_overtime'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <form method="POST" action="employee_shifts.php" class="d-inline">
                                                                <input type="hidden" name="employeeShiftId" value="<?php echo $shift['employee_shift_id']; ?>">
                                                                <input type="hidden" name="shiftId" value="<?php echo $shift['shift_id']; ?>">
                                                                <input type="hidden" name="assignedDate" value="<?php echo $shift['assigned_date']; ?>">
                                                                <button type="submit" name="editEmployeeShift" class="btn btn-sm btn-outline-primary mr-2">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button type="submit" name="deleteEmployeeShift" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Employee Shift Modal -->
                <div class="modal fade" id="addEmployeeShiftModal" tabindex="-1" role="dialog" aria-labelledby="addEmployeeShiftModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addEmployeeShiftModalLabel">Add New Employee Shift</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" action="employee_shifts.php">
                                    <div class="form-group">
                                        <label for="employeeId">Employee</label>
                                        <select name="employeeId" class="form-control" required>
                                            <option value="">Select Employee</option>
                                            <?php foreach ($employees as $employee): ?>
                                                <option value="<?php echo $employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="shiftId">Shift</label>
                                        <select name="shiftId" class="form-control" required>
                                            <option value="">Select Shift</option>
                                            <?php foreach ($shifts as $shift): ?>
                                                <option value="<?php echo $shift['shift_id']; ?>"><?php echo htmlspecialchars($shift['shift_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="assignedDate">Assigned Date</label>
                                        <input type="date" name="assignedDate" class="form-control" required>
                                    </div>
                                    <div class="form-group form-check">
                                        <input type="checkbox" name="isOvertime" class="form-check-input" id="isOvertime">
                                        <label class="form-check-label" for="isOvertime">Overtime</label>
                                    </div>
                                    <button type="submit" name="addEmployeeShift" class="btn btn-primary">Add Shift Assignment</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
