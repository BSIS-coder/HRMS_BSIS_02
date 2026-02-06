<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$job_id = $_GET['id'] ?? null;
$success_message = '';
$error_message = '';

if (!$job_id) {
    header('Location: job_openings.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $requirements = $_POST['requirements'] ?? '';
    $responsibilities = $_POST['responsibilities'] ?? '';
    $location = $_POST['location'] ?? '';
    $employment_type = $_POST['employment_type'] ?? '';
    $vacancy_count = $_POST['vacancy_count'] ?? 1;
    $salary_min = !empty($_POST['salary_min']) ? $_POST['salary_min'] : null;
    $salary_max = !empty($_POST['salary_max']) ? $_POST['salary_max'] : null;
    $experience_level = $_POST['experience_level'] ?? null;
    $education_requirements = $_POST['education_requirements'] ?? null;
    
    try {
        $stmt = $conn->prepare("
            UPDATE job_openings 
            SET title = ?,
                description = ?,
                requirements = ?,
                responsibilities = ?,
                location = ?,
                employment_type = ?,
                vacancy_count = ?,
                salary_range_min = ?,
                salary_range_max = ?,
                experience_level = ?,
                education_requirements = ?
            WHERE job_opening_id = ?
        ");
        
        $stmt->execute([
            $title,
            $description,
            $requirements,
            $responsibilities,
            $location,
            $employment_type,
            $vacancy_count,
            $salary_min,
            $salary_max,
            $experience_level,
            $education_requirements,
            $job_id
        ]);
        
        $success_message = '✅ Job opening updated successfully!';
    } catch (PDOException $e) {
        $error_message = '❌ Error updating job: ' . $e->getMessage();
    }
}

// Get job details
$stmt = $conn->prepare("
    SELECT jo.*, d.department_name, jr.title as role_title
    FROM job_openings jo
    LEFT JOIN departments d ON jo.department_id = d.department_id
    LEFT JOIN job_roles jr ON jo.job_role_id = jr.job_role_id
    WHERE jo.job_opening_id = ?
");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: job_openings.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job Opening - HR Management System</title>
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
                <h2><i class="fas fa-edit mr-2"></i>Edit Job Opening</h2>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_message; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-briefcase mr-2"></i>Edit: <?php echo htmlspecialchars($job['title']); ?>
                            <?php if ($job['ai_generated']): ?>
                                <span class="badge badge-light ml-2">🤖 AI Generated</span>
                            <?php endif; ?>
                        </h5>
                        <small>
                            <i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($job['department_name']); ?> | 
                            <i class="fas fa-user-tie mr-1"></i><?php echo htmlspecialchars($job['role_title']); ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="font-weight-bold"><i class="fas fa-briefcase mr-1"></i>Job Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control form-control-lg" value="<?php echo htmlspecialchars($job['title']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="font-weight-bold"><i class="fas fa-align-left mr-1"></i>Job Description <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-check-circle mr-1"></i>Requirements <span class="text-danger">*</span></label>
                                        <textarea name="requirements" class="form-control" rows="8" required><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-tasks mr-1"></i>Responsibilities <span class="text-danger">*</span></label>
                                        <textarea name="responsibilities" class="form-control" rows="8" required><?php echo htmlspecialchars($job['responsibilities']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-map-marker-alt mr-1"></i>Location</label>
                                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($job['location']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-clock mr-1"></i>Employment Type</label>
                                        <select name="employment_type" class="form-control">
                                            <option value="Full-time" <?php echo $job['employment_type'] == 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                            <option value="Part-time" <?php echo $job['employment_type'] == 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                            <option value="Contract" <?php echo $job['employment_type'] == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                            <option value="Temporary" <?php echo $job['employment_type'] == 'Temporary' ? 'selected' : ''; ?>>Temporary</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-users mr-1"></i>Vacancies</label>
                                        <input type="number" name="vacancy_count" class="form-control" value="<?php echo $job['vacancy_count']; ?>" min="1">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-chart-line mr-1"></i>Experience Level</label>
                                        <input type="text" name="experience_level" class="form-control" value="<?php echo htmlspecialchars($job['experience_level'] ?? ''); ?>" placeholder="e.g., Mid-Level">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-graduation-cap mr-1"></i>Education Requirements</label>
                                        <input type="text" name="education_requirements" class="form-control" value="<?php echo htmlspecialchars($job['education_requirements'] ?? ''); ?>" placeholder="e.g., Bachelor's degree">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-peso-sign mr-1"></i>Minimum Salary</label>
                                        <input type="number" name="salary_min" class="form-control" value="<?php echo $job['salary_range_min']; ?>" placeholder="Optional">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold"><i class="fas fa-peso-sign mr-1"></i>Maximum Salary</label>
                                        <input type="number" name="salary_max" class="form-control" value="<?php echo $job['salary_range_max']; ?>" placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-right mt-4">
                                <a href="job_openings.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times mr-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save mr-1"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
