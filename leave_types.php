<?php
/**
 * LEAVE TYPES MANAGEMENT PAGE
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Restrict access for employees
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] === 'employee') {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['addLeaveType'])) {
        // Add new leave type
        $name = trim($_POST['leaveTypeName']);
        $description = $_POST['leaveDescription'];
        $paid = isset($_POST['paid']) ? 1 : 0;
        $default_days = $_POST['defaultDays'];
        $carry_forward = isset($_POST['carryForward']) ? 1 : 0;
        $max_carry_forward_days = $_POST['maxCarryForwardDays'];

        try {
            // Check if leave type already exists (case-insensitive)
            $checkSql = "SELECT COUNT(*) FROM leave_types WHERE LOWER(leave_type_name) = LOWER(?)";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$name]);

            if ($checkStmt->fetchColumn() > 0) {
                $error = "Error: Leave type '$name' already exists.";
            } else {
                $sql = "INSERT INTO leave_types (leave_type_name, description, paid, default_days, carry_forward, max_carry_forward_days) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$name, $description, $paid, $default_days, $carry_forward, $max_carry_forward_days]);
                
                // Redirect to refresh the page and show the new leave type
                header("Location: leave_types.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error adding leave type: " . $e->getMessage();
        }
    } elseif (isset($_POST['editLeaveType'])) {
        // Edit existing leave type
        $id = $_POST['leaveTypeId'];
        $name = trim($_POST['leaveTypeName']);
        $description = $_POST['leaveDescription'];
        $paid = isset($_POST['paid']) ? 1 : 0;
        $default_days = $_POST['defaultDays'];
        $carry_forward = isset($_POST['carryForward']) ? 1 : 0;
        $max_carry_forward_days = $_POST['maxCarryForwardDays'];

        try {
            // Check if leave type already exists (excluding current record)
            $checkSql = "SELECT COUNT(*) FROM leave_types WHERE LOWER(leave_type_name) = LOWER(?) AND leave_type_id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$name, $id]);

            if ($checkStmt->fetchColumn() > 0) {
                $error = "Error: Leave type '$name' already exists.";
            } else {
                $sql = "UPDATE leave_types SET leave_type_name = ?, description = ?, paid = ?, default_days = ?, carry_forward = ?, max_carry_forward_days = ? WHERE leave_type_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$name, $description, $paid, $default_days, $carry_forward, $max_carry_forward_days, $id]);
                
                // Redirect to refresh the page
                header("Location: leave_types.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error updating leave type: " . $e->getMessage();
        }
    } elseif (isset($_POST['deleteLeaveType'])) {
        // Delete leave type
        $id = $_POST['leaveTypeId'];

        try {
            $sql = "DELETE FROM leave_types WHERE leave_type_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            // Redirect to refresh the page
            header("Location: leave_types.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error deleting leave type: " . $e->getMessage();
        }
    }
}

$leaveTypes = getLeaveTypes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Types - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .leave-type-card {
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
        }
        
        .leave-type-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px var(--shadow-medium);
        }
        
        .leave-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Leave Types Management</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-list-alt mr-2"></i>Leave Types</h5>
                                <div>
                                    <button class="btn btn-primary" data-toggle="modal" data-target="#addLeaveTypeModal">
                                        <i class="fas fa-plus mr-2"></i>Add Leave Type
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Leave Type</th>
                                                <th>Days Allowed</th>
                                                <th>Carry Forward</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($leaveTypes)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                                                        <p>No leave types found. Add your first leave type using the button above.</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($leaveTypes as $leaveType): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="leave-icon mr-3">
                                                                <i class="fas fa-calendar-alt"></i>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($leaveType['leave_type_name']); ?></h6>
                                                                <small class="text-muted"><?php echo htmlspecialchars($leaveType['description']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo intval($leaveType['default_days']); ?> days</td>
                                                    <td><?php echo $leaveType['carry_forward'] ? intval($leaveType['max_carry_forward_days']) . ' days' : 'No'; ?></td>
                                                    <td><span class="badge badge-success">Active</span></td>
                                                    <td>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="leaveTypeId" value="<?php echo $leaveType['leave_type_id']; ?>">
                                                            <button type="button" class="btn btn-sm btn-outline-primary mr-2" data-toggle="modal" data-target="#editLeaveTypeModal" 
                                                                    data-id="<?php echo $leaveType['leave_type_id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($leaveType['leave_type_name']); ?>"
                                                                    data-description="<?php echo htmlspecialchars($leaveType['description']); ?>"
                                                                    data-paid="<?php echo $leaveType['paid']; ?>"
                                                                    data-days="<?php echo intval($leaveType['default_days']); ?>"
                                                                    data-carryforward="<?php echo $leaveType['carry_forward']; ?>"
                                                                    data-maxcarryforward="<?php echo intval($leaveType['max_carry_forward_days']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="leaveTypeId" value="<?php echo $leaveType['leave_type_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" name="deleteLeaveType" onclick="return confirm('Are you sure you want to delete this leave type?')">
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

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Leave Type Distribution</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Calculate leave type distribution
                                $totalLeaveTypes = count($leaveTypes);
                                $leaveTypeCounts = [];
                                foreach ($leaveTypes as $leaveType) {
                                    $leaveTypeCounts[$leaveType['leave_type_name']] = isset($leaveTypeCounts[$leaveType['leave_type_name']]) ? $leaveTypeCounts[$leaveType['leave_type_name']] + 1 : 1;
                                }
                                foreach ($leaveTypeCounts as $type => $count) {
                                    $percentage = ($count / $totalLeaveTypes) * 100;
                                    echo '<div class="progress mb-3">
                                            <div class="progress-bar" style="width: ' . $percentage . '%">' . htmlspecialchars($type) . ' (' . round($percentage) . '%)</div>
                                          </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Leave Type Statistics</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Fetch total leave types
                                $totalLeaveTypes = count($leaveTypes);
                                $activeTypes = 0;
                                $inactiveTypes = 0;

                                // Check if 'active' field exists in the leave types
                                if (!empty($leaveTypes) && array_key_exists('active', $leaveTypes[0])) {
                                    foreach ($leaveTypes as $leaveType) {
                                        if ($leaveType['active'] == 1) {
                                            $activeTypes++;
                                        } else {
                                            $inactiveTypes++;
                                        }
                                    }
                                } else {
                                    $activeTypes = $totalLeaveTypes; // Assume all are active if no active field exists
                                }
                                ?>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h4 class="text-primary"><?php echo $totalLeaveTypes; ?></h4>
                                        <small class="text-muted">Total Types</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success"><?php echo $activeTypes; ?></h4>
                                        <small class="text-muted">Active Types</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-warning"><?php echo $inactiveTypes; ?></h4>
                                        <small class="text-muted">Inactive Types</small>
                                    </div>
                                </div>
                                <?php
                                // Calculate average days allowed
                                $totalDays = 0;
                                foreach ($leaveTypes as $leaveType) {
                                    $totalDays += $leaveType['default_days'];
                                }
                                $averageDays = $totalLeaveTypes > 0 ? round($totalDays / $totalLeaveTypes, 1) : 0;
                                
                                // Find most used type (assuming it's the one with highest default days)
                                $mostUsedType = 'None';
                                $maxDays = 0;
                                foreach ($leaveTypes as $leaveType) {
                                    if ($leaveType['default_days'] > $maxDays) {
                                        $maxDays = $leaveType['default_days'];
                                        $mostUsedType = $leaveType['leave_type_name'];
                                    }
                                }
                                ?>
                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Average Days Allowed:</span>
                                    <strong><?php echo intval($averageDays); ?> days</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Most Used Type:</span>
                                    <strong><?php echo htmlspecialchars($mostUsedType); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Leave Type Modal -->
    <div class="modal fade" id="addLeaveTypeModal" tabindex="-1" role="dialog" aria-labelledby="addLeaveTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLeaveTypeModalLabel">Add New Leave Type</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <div class="form-group">
                            <label for="leaveTypeName">Leave Type Name</label>
                            <input type="text" class="form-control" name="leaveTypeName" id="leaveTypeName" placeholder="Enter leave type name" required>
                        </div>
                        <div class="form-group">
                            <label for="leaveDescription">Description</label>
                            <textarea class="form-control" name="leaveDescription" id="leaveDescription" rows="3" placeholder="Enter leave type description"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="defaultDays">Default Days</label>
                            <input type="number" class="form-control" name="defaultDays" id="defaultDays" placeholder="Enter default days" required>
                        </div>
                        <div class="form-group">
                            <label for="carryForward">Carry Forward</label>
                            <select class="form-control" name="carryForward" id="carryForward">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="maxCarryForwardDays">Max Carry Forward Days</label>
                            <input type="number" class="form-control" name="maxCarryForwardDays" id="maxCarryForwardDays" placeholder="Enter max carry forward days" value="0">
                        </div>
                        <div class="form-group">
                            <label for="paid">Paid Leave</label>
                            <select class="form-control" name="paid" id="paid">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="addLeaveType">Save Leave Type</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Leave Type Modal -->
    <div class="modal fade" id="editLeaveTypeModal" tabindex="-1" role="dialog" aria-labelledby="editLeaveTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLeaveTypeModalLabel">Edit Leave Type</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="leaveTypeId" id="editLeaveTypeId">
                        <div class="form-group">
                            <label for="editLeaveTypeName">Leave Type Name</label>
                            <input type="text" class="form-control" name="leaveTypeName" id="editLeaveTypeName" placeholder="Enter leave type name" required>
                        </div>
                        <div class="form-group">
                            <label for="editLeaveDescription">Description</label>
                            <textarea class="form-control" name="leaveDescription" id="editLeaveDescription" rows="3" placeholder="Enter leave type description"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="editDefaultDays">Default Days</label>
                            <input type="number" class="form-control" name="defaultDays" id="editDefaultDays" placeholder="Enter default days" required>
                        </div>
                        <div class="form-group">
                            <label for="editCarryForward">Carry Forward</label>
                            <select class="form-control" name="carryForward" id="editCarryForward">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editMaxCarryForwardDays">Max Carry Forward Days</label>
                            <input type="number" class="form-control" name="maxCarryForwardDays" id="editMaxCarryForwardDays" placeholder="Enter max carry forward days" value="0">
                        </div>
                        <div class="form-group">
                            <label for="editPaid">Paid Leave</label>
                            <select class="form-control" name="paid" id="editPaid">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="editLeaveType">Update Leave Type</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#editLeaveTypeModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var name = button.data('name');
                var description = button.data('description');
                var paid = button.data('paid');
                var days = button.data('days');
                var carryForward = button.data('carryforward');
                var maxCarryForward = button.data('maxcarryforward');
                
                var modal = $(this);
                modal.find('#editLeaveTypeId').val(id);
                modal.find('#editLeaveTypeName').val(name);
                modal.find('#editLeaveDescription').val(description);
                modal.find('#editDefaultDays').val(days);
                modal.find('#editCarryForward').val(carryForward);
                modal.find('#editMaxCarryForwardDays').val(maxCarryForward);
                modal.find('#editPaid').val(paid);
            });
        });
    </script>
</body>
</html>
