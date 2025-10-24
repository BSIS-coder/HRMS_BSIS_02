<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Check if user has permission (only admin and hr can manage bonus payments)
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'hr') {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once 'config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_bonus':
                $employee_id = $_POST['employee_id'];
                $bonus_type = $_POST['bonus_type'];
                $bonus_amount = $_POST['bonus_amount'];
                $payment_date = $_POST['payment_date'];
                $reason = $_POST['reason'];
                
                try {
                    $sql = "INSERT INTO bonus_payments (employee_id, bonus_type, bonus_amount, payment_date, reason) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt->execute([$employee_id, $bonus_type, $bonus_amount, $payment_date, $reason])) {
                        $success_message = "Bonus payment added successfully!";
                    } else {
                        $error_message = "Failed to add bonus payment.";
                    }
                } catch (PDOException $e) {
                    $error_message = "Error adding bonus payment: " . $e->getMessage();
                }
                break;
                
            case 'update_bonus':
                $bonus_payment_id = $_POST['bonus_payment_id'];
                $bonus_type = $_POST['bonus_type'];
                $bonus_amount = $_POST['bonus_amount'];
                $payment_date = $_POST['payment_date'];
                $reason = $_POST['reason'];
                
                try {
                    $sql = "UPDATE bonus_payments SET bonus_type = ?, bonus_amount = ?, payment_date = ?, reason = ? 
                            WHERE bonus_payment_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$bonus_type, $bonus_amount, $payment_date, $reason, $bonus_payment_id]);
                    $success_message = "Bonus payment updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating bonus payment: " . $e->getMessage();
                }
                break;
                
            case 'delete_bonus':
                $bonus_payment_id = $_POST['bonus_payment_id'];
                
                try {
                    $sql = "DELETE FROM bonus_payments WHERE bonus_payment_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$bonus_payment_id]);
                    $success_message = "Bonus payment deleted successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error deleting bonus payment: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch bonus payments
$sql = "SELECT bp.*, ep.employee_number, CONCAT(pi.first_name, ' ', pi.last_name) as full_name 
        FROM bonus_payments bp
        JOIN employee_profiles ep ON bp.employee_id = ep.employee_id
        JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
        ORDER BY bp.payment_date DESC";
$stmt = $conn->query($sql);
$bonuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Payments - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f5f5;
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
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #800000;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background-color: #fff;
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background-color: #f0f0f0;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .sidebar .nav-link.active {
            background-color: #fff;
            color: #800000;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
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
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(128, 0, 0, 0.1);
            padding: 15px 20px;
            font-weight: bold;
            color: #800000;
        }
        .card-header i {
            color: #800000;
        }
        .card-body {
            padding: 20px;
        }
        .table th {
            border-top: none;
            color: #800000;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
            color: #333;
            border-color: rgba(128, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #800000;
            border-color: #800000;
        }
        .btn-primary:hover {
            background-color: #660000;
            border-color: #660000;
        }
        .top-navbar {
            background: #fff;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(128, 0, 0, 0.1);
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 1020;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .section-title {
            color: #800000;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #800000;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-processing {
            background-color: #17a2b8;
        }
        .badge-completed {
            background-color: #28a745;
        }
        .modal-header {
            background-color: #800000;
            color: #fff;
        }
        .close {
            color: #fff;
            opacity: 0.8;
        }
        .close:hover {
            color: #fff;
            opacity: 1;
        }
        .salary-amount {
            font-weight: bold;
            color: #800000;
        }
        .btn-process {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-process:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Bonus Payments Management</h2>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error_message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-gift mr-2"></i> Bonus Payments</span>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addBonusModal">
                            <i class="fas fa-plus"></i> Add Bonus
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Bonus Type</th>
                                        <th>Amount</th>
                                        <th>Payment Date</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($bonuses)): ?>
                                        <?php foreach ($bonuses as $bonus): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($bonus['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($bonus['bonus_type']); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($bonus['bonus_amount'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($bonus['payment_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($bonus['reason']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editBonus(<?php echo htmlspecialchars(json_encode($bonus)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" style="display:inline;" 
                                                              onsubmit="return confirm('Are you sure you want to delete this bonus?');">
                                                            <input type="hidden" name="action" value="delete_bonus">
                                                            <input type="hidden" name="bonus_payment_id" value="<?php echo $bonus['bonus_payment_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No bonus payments found.</td>
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

    <!-- Add Bonus Modal -->
    <div class="modal fade" id="addBonusModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Bonus Payment</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_bonus">
                        <div class="form-group">
                            <label for="employee_id">Employee</label>
                            <select class="form-control" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php
                                // Fetch employees for dropdown
                                $emp_sql = "SELECT ep.employee_id, CONCAT(pi.first_name, ' ', pi.last_name) as full_name 
                                            FROM employee_profiles ep
                                            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id";
                                $emp_stmt = $conn->query($emp_sql);
                                $employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($employees as $employee) {
                                    echo "<option value=\"{$employee['employee_id']}\">" . htmlspecialchars($employee['full_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bonus_type">Bonus Type</label>
                            <input type="text" class="form-control" id="bonus_type" name="bonus_type" required>
                        </div>
                        <div class="form-group">
                            <label for="bonus_amount">Amount (₱)</label>
                            <input type="number" class="form-control" id="bonus_amount" name="bonus_amount" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="payment_date">Payment Date</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                        </div>
                        <div class="form-group">
                            <label for="reason">Reason</label>
                            <input type="text" class="form-control" id="reason" name="reason" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Bonus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function editBonus(bonus) {
            // Populate the edit modal with bonus data
            // Implementation here...
        }
    </script>
</body>
</html>
