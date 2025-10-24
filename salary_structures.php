<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config.php';

// ================================
// ✅ ADDED: Dynamic statutory rate fetch (rates stored AS DECIMALS, e.g. 0.045 for 4.5%)
// ================================
function getStatutoryRate($conn, $type, $salary = 0) {
    try {
        $sql = "SELECT rate FROM statutory_deductions 
                WHERE type = ? 
                AND (min_salary IS NULL OR ? >= min_salary)
                AND (max_salary IS NULL OR ? <= max_salary)
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$type, $salary, $salary]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Expecting rate stored as decimal (e.g. 0.045 for 4.5%)
        return $result ? floatval($result['rate']) : 0.0;
    } catch (PDOException $e) {
        return 0.0;
    }
}
// ================================

/* Handle form submissions */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_salary_structure':
                $employee_id = $_POST['employee_id'];
                $basic_salary = $_POST['basic_salary'];
$allowances = $_POST['allowances'] ?? 0;
$deductions = $_POST['deductions'] ?? 0;
$effective_date = $_POST['effective_date'];

// ✅ Auto-fetch salary if not manually entered
if (empty($basic_salary) || $basic_salary == 0) {
    $stmt = $conn->prepare("SELECT salary FROM employee_profiles WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $basic_salary = $stmt->fetchColumn() ?? 0;
}
                try {
                    $sql = "INSERT INTO salary_structures (employee_id, basic_salary, allowances, deductions, effective_date) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$employee_id, $basic_salary, $allowances, $deductions, $effective_date]);
                    $success_message = "Salary structure added successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error adding salary structure: " . $e->getMessage();
                }
                break;
                
            case 'update_salary_structure':
                $salary_structure_id = $_POST['salary_structure_id'];
                $basic_salary = $_POST['basic_salary'];
                $allowances = $_POST['allowances'] ?? 0;
                $deductions = $_POST['deductions'] ?? 0;
                $effective_date = $_POST['effective_date'];
                
                try {
                    $sql = "UPDATE salary_structures SET basic_salary = ?, allowances = ?, deductions = ?, effective_date = ? 
                            WHERE salary_structure_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$basic_salary, $allowances, $deductions, $effective_date, $salary_structure_id]);
                    $success_message = "Salary structure updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating salary structure: " . $e->getMessage();
                }
                break;
                
            case 'delete_salary_structure':
                $salary_structure_id = $_POST['salary_structure_id'];
                
                try {
                    $sql = "DELETE FROM salary_structures WHERE salary_structure_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$salary_structure_id]);
                    $success_message = "Salary structure deleted successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error deleting salary structure: " . $e->getMessage();
                }
                break;
        }
    }
}

/* Fetch salary structures with employee details */
try {
    $sql = "SELECT ss.*, ep.employee_number, pi.first_name, pi.last_name, jr.title as job_title, d.department_name
            FROM salary_structures ss
            JOIN employee_profiles ep ON ss.employee_id = ep.employee_id
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            ORDER BY ss.effective_date DESC";
    $stmt = $conn->query($sql);
    $salary_structures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $salary_structures = [];
    $error_message = "Error fetching salary structures: " . $e->getMessage();
}

/* Fetch employees for dropdown */
try {
    $sql = "SELECT ep.employee_id, ep.employee_number, pi.first_name, pi.last_name
            FROM employee_profiles ep
            JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            WHERE ep.employment_status IN ('Full-time', 'Part-time', 'Contract')
            ORDER BY pi.first_name, pi.last_name";
    $stmt = $conn->query($sql);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $employees = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Structures - HR System</title>
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
            background-color:#E91E63;
            color: #fff;
            padding-top: 20px;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #fff #E91E63;
            z-index: 1030;
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #E91E63;
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
            color: #E91E63;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .dropdown-menu {
            background-color: #ffffff;
            border: none;
            border-radius: 4px;
            padding-left: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .dropdown-menu .dropdown-item {
            color: #666;
            padding: 10px 20px;
            transition: all 0.3s;
        }
        .dropdown-menu .dropdown-item:hover {
            background-color: #fff0f0;
            color: #E91E63;
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
            color: #E91E63;
        }
        .card-header i {
            color: #E91E63;
        }
        .card-body {
            padding: 20px;
        }
        .table th {
            border-top: none;
            color: #C2185B;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
            color: #333;
            border-color: rgba(128, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #E91E63;
            border-color: #E91E63;
        }
        .btn-primary:hover {
            background-color: #be0945ff;
            border-color: #be0945ff;
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
            color: #E91E63;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #E91E63;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }
        .salary-amount {
            font-weight: bold;
            color: #E91E63;
        }
        .badge-active {
            background-color: #28a745;
        }
        .badge-inactive {
            background-color: #6c757d;
        }
        .modal-header {
            background-color: #E91E63;
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Salary Structures Management</h2>
                
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
                        <span><i class="fas fa-money-check mr-2"></i> Salary Structures</span>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSalaryModal">
                            <i class="fas fa-plus"></i> Add Salary Structure
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Employee Number</th>
                                        <th>Department</th>
                                        <th>Job Title</th>
                                        <th>Basic Salary</th>
                                        <th>Allowances</th>
                                        <th>Deductions</th>
                                        <th>Total Gross</th>
                                        <th>Net Pay</th>
                                        <th>Effective Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($salary_structures)): ?>
                                        <?php foreach ($salary_structures as $structure): ?>
                                            <?php
                                                // ✅ Compute Statutory Deductions (SSS, PhilHealth, Pag-IBIG)
                                                $basic_salary = $structure['basic_salary'];
                                                $allowances = $structure['allowances'];
                                                $deductions = $structure['deductions'];

                                                // rates are decimals (e.g. 0.045)
                                                $sss_rate = getStatutoryRate($conn, 'SSS', $basic_salary);
                                                $philhealth_rate = getStatutoryRate($conn, 'PhilHealth', $basic_salary);
                                                $pagibig_rate = getStatutoryRate($conn, 'Pag-IBIG', $basic_salary);

                                                $sss = $basic_salary * $sss_rate;
                                                $philhealth = $basic_salary * $philhealth_rate;
                                                $pagibig = $basic_salary * $pagibig_rate;

                                                $total_statutory = $sss + $philhealth + $pagibig;
                                                $gross = $basic_salary + $allowances;
                                                $net_pay = $gross - ($deductions + $total_statutory);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($structure['first_name'] . ' ' . $structure['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($structure['employee_number']); ?></td>
                                                <td><?php echo htmlspecialchars($structure['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($structure['job_title'] ?? 'N/A'); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($basic_salary, 2); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($allowances, 2); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($deductions, 2); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($gross, 2); ?></td>
                                                <td class="salary-amount">₱<?php echo number_format($net_pay, 2); ?></td>
                                                <td><?php echo htmlspecialchars($structure['effective_date']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editSalaryStructure(<?php echo htmlspecialchars(json_encode($structure)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this salary structure?');">
                                                            <input type="hidden" name="action" value="delete_salary_structure">
                                                            <input type="hidden" name="salary_structure_id" value="<?php echo $structure['salary_structure_id']; ?>">
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
                                            <td colspan="12" class="text-center">No salary structures found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Add Salary Structure Modal -->
                <div class="modal fade" id="addSalaryModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Salary Structure</h5>
                                <button type="button" class="close" data-dismiss="modal">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="add_salary_structure">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="employee_name">Employee Name</label>
                                                <input type="text" class="form-control" id="employee_name" name="employee_name" required list="employees" autocomplete="off" oninput="handleEmployeeInput()">
                                                <datalist id="employees">
                                                    <?php foreach ($employees as $employee): ?>
                                                        <option value="<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>" data-id="<?php echo $employee['employee_id']; ?>">
                                                    <?php endforeach; ?>
                                                </datalist>
                                                <input type="hidden" id="employee_id" name="employee_id">
                                            </div>
                                        </div>
                                    </div>
                                    <div id="employee_info" style="display: none;" class="alert alert-info">
                                        <strong>Employee Details:</strong><br>
                                        <span id="emp_number"></span><br>
                                        <span id="emp_status"></span><br>
                                        <span id="emp_job"></span><br>
                                        <span id="emp_dept"></span><br>
                                        <span id="emp_salary"></span>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="basic_salary">Basic Salary (₱)</label>
                                                <input type="number" class="form-control" id="basic_salary" name="basic_salary" step="0.01" min="0" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="allowances">Allowances (₱)</label>
                                                <input type="number" class="form-control" id="allowances" name="allowances" step="0.01" min="0" value="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="deductions">Deductions (₱)</label>
                                                <input type="number" class="form-control" id="deductions" name="deductions" step="0.01" min="0" value="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="effective_date">Effective Date</label>
                                                <input type="date" class="form-control" id="effective_date" name="effective_date" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Salary Structure</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Salary Structure Modal -->
                <div class="modal fade" id="editSalaryModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Salary Structure</h5>
                                <button type="button" class="close" data-dismiss="modal">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="update_salary_structure">
                                    <input type="hidden" name="salary_structure_id" id="edit_salary_structure_id">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="edit_basic_salary">Basic Salary (₱)</label>
                                                <input type="number" class="form-control" id="edit_basic_salary" name="basic_salary" step="0.01" min="0" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="edit_allowances">Allowances (₱)</label>
                                                <input type="number" class="form-control" id="edit_allowances" name="allowances" step="0.01" min="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="edit_deductions">Deductions (₱)</label>
                                                <input type="number" class="form-control" id="edit_deductions" name="deductions" step="0.01" min="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="edit_effective_date">Effective Date</label>
                                                <input type="date" class="form-control" id="edit_effective_date" name="effective_date" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Salary Structure</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    function editSalaryStructure(structure) {
        $('#edit_salary_structure_id').val(structure.salary_structure_id);
        $('#edit_basic_salary').val(structure.basic_salary);
        $('#edit_allowances').val(structure.allowances);
        $('#edit_deductions').val(structure.deductions);
        $('#edit_effective_date').val(structure.effective_date);
        $('#editSalaryModal').modal('show');
    }

    function handleEmployeeInput() {
        const input = document.getElementById('employee_name');
        const datalist = document.getElementById('employees');
        const hiddenId = document.getElementById('employee_id');
        const employeeInfoDiv = document.getElementById('employee_info');

        const inputValue = input.value.trim();
        if (!inputValue) {
            hiddenId.value = '';
            employeeInfoDiv.style.display = 'none';
            return;
        }

        // Find matching option
        const options = datalist.querySelectorAll('option');
        let matchedId = null;
        for (let option of options) {
            if (option.value.toLowerCase() === inputValue.toLowerCase()) {
                matchedId = option.getAttribute('data-id');
                break;
            }
        }

        if (matchedId) {
            hiddenId.value = matchedId;
            loadEmployeeDetails(matchedId);
        } else {
            hiddenId.value = '';
            employeeInfoDiv.style.display = 'none';
        }
    }

    function loadEmployeeDetails(employeeId) {
        const employeeInfoDiv = document.getElementById('employee_info');

        if (!employeeId) {
            employeeInfoDiv.style.display = 'none';
            return;
        }

        // Make AJAX request to get employee details
        fetch('get_employee_details.php?employee_id=' + employeeId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error:', data.error);
                    employeeInfoDiv.style.display = 'none';
                    return;
                }

                // Populate the employee info div
                document.getElementById('emp_number').textContent = 'Employee Number: ' + data.employee_number;
                document.getElementById('emp_status').textContent = 'Employment Status: ' + data.employment_status;
                document.getElementById('emp_job').textContent = 'Job Title: ' + (data.job_title || 'N/A');
                document.getElementById('emp_dept').textContent = 'Department: ' + (data.department_name || 'N/A');
                document.getElementById('emp_salary').textContent = 'Current Salary: ₱' + parseFloat(data.current_salary).toFixed(2);
                document.getElementById('basic_salary').value = parseFloat(data.current_salary).toFixed(2);
                // ✅ Automatically set Basic Salary input
document.getElementById('basic_salary').value = parseFloat(data.current_salary).toFixed(2);
                // Show the info div
                employeeInfoDiv.style.display = 'block';
            })
            .catch(error => {
                console.error('Error fetching employee details:', error);
                employeeInfoDiv.style.display = 'none';
            });
    }

    // Set default effective date to today
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const el = document.getElementById('effective_date');
        if (el) el.value = today;
    });
</script>
</body>
</html>