<?php
/**
 * SHIFTS MANAGEMENT PAGE
 */

session_start();
// Restrict access for employees
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] === 'employee') {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';

// Calculate shift duration
function calculateDuration($start_time, $end_time) {
    $start = new DateTime($start_time);
    $end = new DateTime($end_time);

    // If end time is before start time, assume it's overnight and add 24 hours
    if ($end < $start) {
        $end->modify('+1 day');
    }

    $interval = $start->diff($end);
    return $interval->format('%h hours %i minutes');
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['addShift'])) {
        // Add new shift
        $name = $_POST['shiftName'];
        $start_time = $_POST['startTime'];
        $end_time = $_POST['endTime'];
        $description = $_POST['shiftDescription'];

        try {
            $sql = "INSERT INTO shifts (shift_name, start_time, end_time, description) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, $start_time, $end_time, $description]);
            
            // Redirect to refresh the page and show the new shift
            header("Location: shifts.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding shift: " . $e->getMessage();
        }
    } elseif (isset($_POST['editShift'])) {
        // Edit existing shift
        $id = $_POST['shiftId'];
        $name = $_POST['shiftName'];
        $start_time = $_POST['startTime'];
        $end_time = $_POST['endTime'];
        $description = $_POST['shiftDescription'];

        try {
            $sql = "UPDATE shifts SET shift_name = ?, start_time = ?, end_time = ?, description = ? WHERE shift_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, $start_time, $end_time, $description, $id]);
            
            // Redirect to refresh the page
            header("Location: shifts.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error updating shift: " . $e->getMessage();
        }
    } elseif (isset($_POST['deleteShift'])) {
        // Delete shift
        $id = $_POST['shiftId'];

        try {
            $sql = "DELETE FROM shifts WHERE shift_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            // Redirect to refresh the page
            header("Location: shifts.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error deleting shift: " . $e->getMessage();
        }
    }
}

$shifts = getShifts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shifts - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .shift-card {
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
        }
        
        .shift-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-medium);
        }
        
        .shift-status {
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
<?php require_once 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Shifts Management</h2>
                
                
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
                                <h5 class="mb-0"><i class="fas fa-clock mr-2"></i>Shifts Overview</h5>
                                <button class="btn btn-primary" data-toggle="modal" data-target="#addShiftModal">
                                    <i class="fas fa-plus mr-2"></i>Add Shift
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Shift Name</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($shifts)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-clock fa-2x mb-2"></i>
                                                        <p>No shifts found. Add your first shift using the button above.</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($shifts as $shift): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($shift['shift_name']); ?></td>
                                                    <td><?php echo date('h:i A', strtotime($shift['start_time'])); ?></td>
                                                    <td><?php echo date('h:i A', strtotime($shift['end_time'])); ?></td>
                                                    <td><?php echo calculateDuration($shift['start_time'], $shift['end_time']); ?></td>
                                                    <td><span class="shift-status badge badge-success">Active</span></td>
                                                    <td>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="shiftId" value="<?php echo $shift['shift_id']; ?>">
                                                            <button type="button" class="btn btn-sm btn-outline-primary mr-2" data-toggle="modal" data-target="#editShiftModal" 
                                                                    data-id="<?php echo $shift['shift_id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($shift['shift_name']); ?>"
                                                                    data-starttime="<?php echo $shift['start_time']; ?>"
                                                                    data-endtime="<?php echo $shift['end_time']; ?>"
                                                                    data-description="<?php echo htmlspecialchars($shift['description']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="shiftId" value="<?php echo $shift['shift_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" name="deleteShift" onclick="return confirm('Are you sure you want to delete this shift?')">
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
                                <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Shift Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-4">
                                    <div class="col-4">
                                        <h4 class="text-primary"><?php echo count($shifts); ?></h4>
                                        <small class="text-muted">Total Shifts</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success"><?php echo count($shifts); ?></h4>
                                        <small class="text-muted">Active Shifts</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-info"><?php echo count($shifts); ?></h4>
                                        <small class="text-muted">Configured</small>
                                    </div>
                                </div>

                                <div class="alert alert-light border mb-0">
                                    <small class="text-muted">
                                        Tip: Deleting a shift may fail if it is assigned to employees.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Shift Notes</h5>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>Configure shifts according to company policies.</li>
                                    <li>Ensure proper shift scheduling for coverage.</li>
                                    <li>Restrict shift configuration access to authorized roles.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Shift Modal -->
                <div class="modal fade" id="addShiftModal" tabindex="-1" role="dialog" aria-labelledby="addShiftModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addShiftModalLabel">Add Shift</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form method="POST" action="shifts.php">
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="shiftName">Shift Name</label>
                                        <input type="text" name="shiftName" id="shiftName" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="startTime">Start Time</label>
                                        <input type="time" name="startTime" id="startTime" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="endTime">End Time</label>
                                        <input type="time" name="endTime" id="endTime" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="shiftDescription">Description</label>
                                        <textarea name="shiftDescription" id="shiftDescription" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" name="addShift" class="btn btn-primary">Save Shift</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Shift Modal -->
                <div class="modal fade" id="editShiftModal" tabindex="-1" role="dialog" aria-labelledby="editShiftModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editShiftModalLabel">Edit Shift</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form method="POST" action="shifts.php">
                                <div class="modal-body">
                                    <input type="hidden" name="shiftId" id="editShiftId">
                                    <div class="form-group">
                                        <label for="editShiftName">Shift Name</label>
                                        <input type="text" name="shiftName" id="editShiftName" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="editStartTime">Start Time</label>
                                        <input type="time" name="startTime" id="editStartTime" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="editEndTime">End Time</label>
                                        <input type="time" name="endTime" id="editEndTime" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="editShiftDescription">Description</label>
                                        <textarea name="shiftDescription" id="editShiftDescription" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" name="editShift" class="btn btn-primary">Update Shift</button>
                                </div>
                            </form>
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
        $('#editShiftModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const id = button.data('id');
            const name = button.data('name');
            const startTime = button.data('starttime');
            const endTime = button.data('endtime');
            const description = button.data('description');

            const modal = $(this);
            modal.find('#editShiftId').val(id);
            modal.find('#editShiftName').val(name);
            modal.find('#editStartTime').val(startTime);
            modal.find('#editEndTime').val(endTime);
            modal.find('#editShiftDescription').val(description || '');
        });
    </script>
</body>
</html>
