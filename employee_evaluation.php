<?php
session_start();

// Require authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'dp.php'; // database connection

$employee_id = filter_input(INPUT_GET, 'employee_id', FILTER_VALIDATE_INT);
$cycle_id = filter_input(INPUT_GET, 'cycle_id', FILTER_VALIDATE_INT);

if (!$employee_id || !$cycle_id) {
    die('Invalid parameters.');
}

// Fetch employee details
$empStmt = $conn->prepare("SELECT pi.first_name, pi.last_name, d.department_name as department, jr.title as job_role FROM employee_profiles ep JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id LEFT JOIN departments d ON jr.department = d.department_name WHERE ep.employee_id = :id");
$empStmt->execute([':id' => $employee_id]);
$employee = $empStmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die('Employee not found.');
}

// Fetch competencies for the cycle
$compStmt = $conn->prepare("
    SELECT c.competency_id, c.name, ec.rating, ec.comments
    FROM competencies c
    LEFT JOIN employee_competencies ec ON ec.competency_id = c.competency_id AND ec.employee_id = :emp_id AND ec.cycle_id = :cycle_id
    ORDER BY c.name
");
$compStmt->execute([':emp_id' => $employee_id, ':cycle_id' => $cycle_id]);
$competencies = $compStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $competencies_data = json_decode($_POST['competencies'] ?? '[]', true);
    if (is_array($competencies_data)) {
        $conn->beginTransaction();
        try {
            foreach ($competencies_data as $comp) {
                $comp_id = (int) $comp['competency_id'];
                $rating = (int) $comp['rating'];
                $comments = trim($comp['comments'] ?? '');

                $upsertStmt = $conn->prepare("
                    INSERT INTO employee_competencies (employee_id, competency_id, cycle_id, rating, comments, updated_at)
                    VALUES (:emp_id, :comp_id, :cycle_id, :rating, :comments, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE rating = :rating, comments = :comments, updated_at = CURRENT_TIMESTAMP
                ");
                $upsertStmt->execute([
                    ':emp_id' => $employee_id,
                    ':comp_id' => $comp_id,
                    ':cycle_id' => $cycle_id,
                    ':rating' => $rating,
                    ':comments' => $comments
                ]);
            }
            $conn->commit();
            $success = 'Evaluation saved successfully.';
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Failed to save: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid data.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>HR Dashboard - Employee Evaluation</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
  <link rel="stylesheet" href="styles.css">
  <style>
    .container { max-width: 85%; margin-left: 265px; padding-top: 3.5rem; }
    .section-title { color: var(--primary-color); margin-bottom: 1.5rem; font-weight:600 }
  </style>
</head>
<body>
  <div class="container-fluid"><?php include 'navigation.php'; ?></div>
  <div class="row"><?php include 'sidebar.php'; ?></div>

  <div class="container">
    <h1 class="section-title">Evaluate Employee</h1>

    <div class="card">
      <div class="card-header">
        <h5><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h5>
        <small class="text-muted"><?php echo htmlspecialchars($employee['department'] ?? ''); ?> • <?php echo htmlspecialchars($employee['job_role'] ?? ''); ?> • Cycle ID: <?php echo $cycle_id; ?></small>
      </div>
      <div class="card-body">
        <?php if (isset($success)): ?>
          <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
          <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post">
          <div id="competencies">
            <?php foreach ($competencies as $comp): ?>
              <div class="mb-3 border p-3">
                <label class="form-label fw-bold"><?php echo htmlspecialchars($comp['name']); ?></label>
                <div class="row">
                  <div class="col-md-3">
                    <label class="form-label">Rating</label>
                    <select class="form-select" name="rating[<?php echo $comp['competency_id']; ?>]">
                      <option value="1" <?php echo ($comp['rating'] == 1 ? 'selected' : ''); ?>>1 - Poor</option>
                      <option value="2" <?php echo ($comp['rating'] == 2 ? 'selected' : ''); ?>>2 - Below Average</option>
                      <option value="3" <?php echo ($comp['rating'] == 3 ? 'selected' : ''); ?>>3 - Average</option>
                      <option value="4" <?php echo ($comp['rating'] == 4 ? 'selected' : ''); ?>>4 - Good</option>
                      <option value="5" <?php echo ($comp['rating'] == 5 ? 'selected' : ''); ?>>5 - Excellent</option>
                    </select>
                  </div>
                  <div class="col-md-9">
                    <label class="form-label">Comments</label>
                    <textarea class="form-control" name="comments[<?php echo $comp['competency_id']; ?>]" rows="2"><?php echo htmlspecialchars($comp['comments'] ?? ''); ?></textarea>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-success">Save Evaluation</button>
          <a href="performance_reviews.php" class="btn btn-secondary">Back to Reviews</a>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.querySelector('form').addEventListener('submit', function(e) {
      // Collect data into JSON for POST
      const competencies = [];
      document.querySelectorAll('#competencies > div').forEach(div => {
        const compId = div.querySelector('select').name.match(/\[(\d+)\]/)[1];
        const rating = div.querySelector('select').value;
        const comments = div.querySelector('textarea').value.trim();
        competencies.push({ competency_id: compId, rating: rating, comments: comments });
      });
      // Add hidden input with JSON
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'competencies';
      input.value = JSON.stringify(competencies);
      this.appendChild(input);
    });
  </script>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
