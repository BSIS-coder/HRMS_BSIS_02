<?php
/**
 * EMPLOYEE SHIFTS ASSIGNMENT PAGE
 * 
 * Applicable Philippine Republic Acts:
 * - RA 6727 (Implementing Rules and Regulations of the Wage Order)
 *   - Enforces maximum 8-hour regular work day
 *   - Overtime regulations and compensation requirements
 *   - IsOvertime flag indicates work beyond 8 hours
 *   - Overtime compensation at 1.25x (first 4 hours) and 1.5x (after 4 hours) rates
 *   - Rest day requirements and compensation (2x pay)
 *   - No continuous work beyond legal limits
 * 
 * - RA 10173 (Data Privacy Act of 2012) - APPLIES TO ALL PAGES
 *   - Employee shift assignments contain PERSONAL INFORMATION
 *   - Work schedule reveals employee work history and patterns
 *   - Only authorized personnel should access shift assignments
 *   - Employees have right to access their own shift assignments
 *   - Protect overtime tracking data - reveals workload/compensation
 *   - Secure employee_id and assignment history
 *   - Maintain detailed audit logs for shift assignment changes
 *   - Do not share shift data with unauthorized third parties
 *   - Encrypt shift assignment data in transit and at rest
 * 
 * Compliance Note: Track overtime assignments carefully. Excessive continuous
 * overtime may violate labor laws. Ensure overtime compensation is accurately
 * calculated in payroll system. Rest days must be enforced per wage order.
 * All shift assignments are personal data protected under RA 10173.
 */

session_start();
// Restrict access for employees
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] === 'employee') {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';
require_once 'employee_status_functions.php';

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

// Update employee statuses based on current leave status
updateAllEmployeesStatusBasedOnLeave();

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
                                                        <td><?php echo htmlspecialchars($shift['shift_name'] ?? 'No Shift Assigned'); ?></td>
                                                        <td><?php echo htmlspecialchars($shift['assigned_date'] ?? 'N/A'); ?></td>
                                                        <td><?php echo isset($shift['is_overtime']) ? ($shift['is_overtime'] ? 'Yes' : 'No') : 'N/A'; ?></td>
                                                        <td>
                                                            <?php
                                                            $status = $shift['status'] ?? 'Active';
                                                            $displayStatus = ($status === 'Inactive') ? 'ON LEAVE' : $status;
                                                            $badgeClass = ($status === 'Inactive') ? 'badge-danger' : 'badge-success';
                                                            ?>
                                                            <span class="badge <?php echo $badgeClass; ?>">
                                                                <?php echo $displayStatus; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if (isset($shift['employee_shift_id'])): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-primary mr-2"
                                                                        data-toggle="modal"
                                                                        data-target="#editEmployeeShiftModal"
                                                                        data-employee-shift-id="<?php echo $shift['employee_shift_id']; ?>"
                                                                        data-employee-id="<?php echo $shift['employee_id']; ?>"
                                                                        data-shift-id="<?php echo $shift['shift_id']; ?>"
                                                                        data-assigned-date="<?php echo $shift['assigned_date']; ?>"
                                                                        data-is-overtime="<?php echo $shift['is_overtime']; ?>">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <form method="POST" action="employee_shifts.php" class="d-inline">
                                                                    <input type="hidden" name="employeeShiftId" value="<?php echo $shift['employee_shift_id']; ?>">
                                                                    <button type="submit" name="deleteEmployeeShift" class="btn btn-sm btn-outline-danger">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="text-muted">No shift assigned</span>
                                                            <?php endif; ?>
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
                                        <select name="employeeId" id="employeeId" class="form-control" required>
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
                                        <input type="date" name="assignedDate" id="assignedDate" class="form-control" required>
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

                <!-- Edit Employee Shift Modal -->
                <div class="modal fade" id="editEmployeeShiftModal" tabindex="-1" role="dialog" aria-labelledby="editEmployeeShiftModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editEmployeeShiftModalLabel">Edit Employee Shift</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" action="employee_shifts.php">
                                    <input type="hidden" name="employeeShiftId" id="editEmployeeShiftId">
                                    <div class="form-group">
                                        <label for="editEmployeeId">Employee</label>
                                        <select name="employeeId" id="editEmployeeId" class="form-control" required>
                                            <option value="">Select Employee</option>
                                            <?php foreach ($employees as $employee): ?>
                                                <option value="<?php echo $employee['employee_id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="editShiftId">Shift</label>
                                        <select name="shiftId" id="editShiftId" class="form-control" required>
                                            <option value="">Select Shift</option>
                                            <?php foreach ($shifts as $shift): ?>
                                                <option value="<?php echo $shift['shift_id']; ?>"><?php echo htmlspecialchars($shift['shift_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="editAssignedDate">Assigned Date</label>
                                        <input type="date" name="assignedDate" id="editAssignedDate" class="form-control" required>
                                    </div>
                                    <div class="form-group form-check">
                                        <input type="checkbox" name="isOvertime" class="form-check-input" id="editIsOvertime">
                                        <label class="form-check-label" for="editIsOvertime">Overtime</label>
                                    </div>
                                    <button type="submit" name="editEmployeeShift" class="btn btn-primary">Update Shift Assignment</button>
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

    <script>
        // Function to fetch employee hire date and set default assigned date
        function updateAssignedDate() {
            const employeeId = document.getElementById('employeeId').value;
            const assignedDateField = document.getElementById('assignedDate');

            if (employeeId) {
                fetch(`get_employee_hire_date.php?employee_id=${employeeId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.hire_date) {
                            assignedDateField.value = data.hire_date;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching hire date:', error);
                    });
            } else {
                // Clear the date if no employee is selected
                assignedDateField.value = '';
            }
        }

        // Function to update edit assigned date
        function updateEditAssignedDate() {
            const employeeId = document.getElementById('editEmployeeId').value;
            const assignedDateField = document.getElementById('editAssignedDate');

            if (employeeId) {
                fetch(`get_employee_hire_date.php?employee_id=${employeeId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.hire_date) {
                            assignedDateField.value = data.hire_date;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching hire date:', error);
                    });
            } else {
                // Clear the date if no employee is selected
                assignedDateField.value = '';
            }
        }

        // Add event listener to employee select dropdown
        document.getElementById('employeeId').addEventListener('change', updateAssignedDate);

        // Also update when modal is shown (in case employee is pre-selected)
        $('#addEmployeeShiftModal').on('shown.bs.modal', function () {
            updateAssignedDate();
        });

        // Handle edit modal population
        $('#editEmployeeShiftModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const employeeShiftId = button.data('employee-shift-id');
            const employeeId = button.data('employee-id');
            const shiftId = button.data('shift-id');
            const assignedDate = button.data('assigned-date');
            const isOvertime = button.data('is-overtime');

            const modal = $(this);
            modal.find('#editEmployeeShiftId').val(employeeShiftId);
            modal.find('#editEmployeeId').val(employeeId);
            modal.find('#editShiftId').val(shiftId);
            modal.find('#editAssignedDate').val(assignedDate);
            modal.find('#editIsOvertime').prop('checked', isOvertime == 1);
        });

        // Add event listener to edit employee select dropdown
        document.getElementById('editEmployeeId').addEventListener('change', updateEditAssignedDate);
    </script>
</body>
</html>
