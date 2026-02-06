<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Only HR and Admin can access
if (!in_array($_SESSION['role'], ['hr', 'admin'])) {
    die('Access denied. Only HR and Admin can manage vacancies.');
}

require_once 'config.php';

// Get auto creation settings
$autoSettings = ['auto_job_creation_enabled' => false];
if (file_exists('auto_job_settings.json')) {
    $autoSettings = json_decode(file_get_contents('auto_job_settings.json'), true);
}

// Get recent employee terminations/resignations (potential vacancies)
$vacancies_query = "SELECT ep.*, 
                    CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
                    jr.title as job_title,
                    jr.department,
                    jr.job_role_id,
                    d.department_id,
                    d.department_name,
                    d.vacancy_limit,
                    COALESCE(SUM(CASE WHEN jo.status = 'Open' THEN jo.vacancy_count ELSE 0 END), 0) as current_vacancies
                    FROM employee_profiles ep
                    JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
                    JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
                    JOIN departments d ON jr.department = d.department_name
                    LEFT JOIN job_openings jo ON d.department_id = jo.department_id
                    WHERE ep.employment_status IN ('Terminated', 'Resigned')
                    GROUP BY ep.employee_id
                    ORDER BY ep.updated_at DESC
                    LIMIT 20";
$vacancies = $conn->query($vacancies_query)->fetchAll(PDO::FETCH_ASSOC);

// Get departments with vacancy info
$departments_query = "SELECT d.*, 
                      COALESCE(SUM(CASE WHEN jo.status = 'Open' THEN jo.vacancy_count ELSE 0 END), 0) as current_vacancies,
                      COUNT(DISTINCT ep.employee_id) as total_employees
                      FROM departments d
                      LEFT JOIN job_openings jo ON d.department_id = jo.department_id
                      LEFT JOIN job_roles jr ON d.department_name = jr.department
                      LEFT JOIN employee_profiles ep ON jr.job_role_id = ep.job_role_id AND ep.employment_status = 'Full-time'
                      GROUP BY d.department_id
                      ORDER BY d.department_name";
$departments = $conn->query($departments_query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vacancy Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2><i class="fas fa-users-slash mr-2"></i>Vacancy Management</h2>
                
                <!-- Auto Job Creation Toggle -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-robot mr-2"></i>Automated Job Creation</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6>Auto-Generate Job Openings for Vacancies</h6>
                                <p class="text-muted mb-0">
                                    When enabled, the system will automatically create AI-generated job openings when employees leave.
                                    Jobs will be sent to approval queue and respect department vacancy limits.
                                </p>
                            </div>
                            <div class="col-md-4 text-right">
                                <div class="custom-control custom-switch" style="font-size: 1.5rem;">
                                    <input type="checkbox" class="custom-control-input" id="autoJobToggle" 
                                           <?php echo $autoSettings['auto_job_creation_enabled'] ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="autoJobToggle">
                                        <span id="toggleStatus">
                                            <?php echo $autoSettings['auto_job_creation_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Vacancy Overview -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-building mr-2"></i>Department Vacancy Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Department</th>
                                        <th>Total Employees</th>
                                        <th>Open Vacancies</th>
                                        <th>Vacancy Limit</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($departments as $dept): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                            <td><?php echo $dept['total_employees']; ?></td>
                                            <td><span class="badge badge-primary"><?php echo $dept['current_vacancies']; ?></span></td>
                                            <td>
                                                <?php echo $dept['vacancy_limit'] ? $dept['vacancy_limit'] : '<span class="text-muted">Unlimited</span>'; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($dept['vacancy_limit'] === null) {
                                                    echo '<span class="badge badge-secondary">Unlimited</span>';
                                                } elseif ($dept['current_vacancies'] >= $dept['vacancy_limit']) {
                                                    echo '<span class="badge badge-danger">At Limit</span>';
                                                } else {
                                                    $remaining = $dept['vacancy_limit'] - $dept['current_vacancies'];
                                                    echo '<span class="badge badge-success">' . $remaining . ' Available</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Vacancies -->
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-user-times mr-2"></i>Recent Employee Departures (Potential Vacancies)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($vacancies)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Job Title</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Open Vacancies</th>
                                            <th>Limit Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($vacancies as $vacancy): ?>
                                            <?php 
                                            $canCreate = true;
                                            $limitMessage = '';
                                            if ($vacancy['vacancy_limit'] !== null && $vacancy['current_vacancies'] >= $vacancy['vacancy_limit']) {
                                                $canCreate = false;
                                                $limitMessage = 'At Limit';
                                            }
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($vacancy['full_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($vacancy['job_title']); ?></td>
                                                <td><?php echo htmlspecialchars($vacancy['department']); ?></td>
                                                <td>
                                                    <span class="badge badge-danger">
                                                        <?php echo htmlspecialchars($vacancy['employment_status']); ?>
                                                    </span>
                                                </td>
                                                <td><span class="badge badge-info"><?php echo $vacancy['current_vacancies']; ?></span></td>
                                                <td>
                                                    <?php if ($canCreate): ?>
                                                        <span class="badge badge-success">Can Create</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger"><?php echo $limitMessage; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($canCreate): ?>
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="createVacancyJob(<?php echo $vacancy['employee_id']; ?>, '<?php echo htmlspecialchars($vacancy['job_title']); ?>')">
                                                            <i class="fas fa-plus-circle mr-1"></i>Create Job
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>
                                                            <i class="fas fa-ban mr-1"></i>Limit Reached
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle mr-2"></i>
                                No recent employee departures found.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    // Toggle auto job creation
    $('#autoJobToggle').on('change', function() {
        const enabled = $(this).is(':checked');
        const $toggle = $(this);
        
        $.ajax({
            url: 'auto_job_creation.php',
            method: 'POST',
            data: {
                action: 'toggle_auto_creation',
                enabled: enabled ? 1 : 0
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#toggleStatus').text(enabled ? 'Enabled' : 'Disabled');
                    alert(response.message);
                } else {
                    alert('Error: ' + response.error);
                    $toggle.prop('checked', !enabled);
                }
            },
            error: function() {
                alert('Failed to update settings');
                $toggle.prop('checked', !enabled);
            }
        });
    });
    
    // Create job for specific vacancy
    function createVacancyJob(employeeId, jobTitle) {
        if (!confirm('Create AI-generated job opening for: ' + jobTitle + '?')) {
            return;
        }
        
        $.ajax({
            url: 'auto_job_creation.php',
            method: 'POST',
            data: {
                action: 'create_vacancy_job',
                employee_id: employeeId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.message);
                    location.reload();
                } else {
                    alert('❌ ' + response.error);
                }
            },
            error: function() {
                alert('Failed to create job opening');
            }
        });
    }
    </script>
</body>
</html>
