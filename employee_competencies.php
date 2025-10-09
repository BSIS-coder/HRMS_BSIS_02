<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HR Dashboard - Competencies</title>
  
  <!-- Bootstrap & FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
  
  <!-- Custom CSS -->
  <link rel="stylesheet" href="styles.css">
  <style>
    .section-title {
      color: var(--primary-color);
      margin-bottom: 30px;
      font-weight: 600;
    }
    .container-fluid { padding: 0; }
    .row { margin: 0; }
    .container { max-width: 1150px; margin-left: 265px; padding-top:50px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }  
  </style>
</head>
<body>

  <!-- Navigation & Sidebar -->
  <div class="container-fluid"><?php include 'navigation.php'; ?></div>
  <div class="row"><?php include 'sidebar.php'; ?></div>

  <div class="container mt-5">
    <h1 class="section-title">Employee Competencies</h1>

    <!-- Employee Selector -->
    <div class="row mb-3">
      <div class="col-md-6">
        <label for="employeeSelect" class="form-label">Select Employee</label>
        <select id="employeeSelect" class="form-select"></select>
      </div>
    </div>

    <!-- Assign Competency Button -->
    <button id="assignBtn" class="btn btn-primary mb-3" disabled>
      <i class="fas fa-plus"></i> Assign Competency
    </button>

    <!-- Employee Competencies Table -->
    <table class="table table-bordered" id="employeeCompetencyTable">
      <thead>
        <tr>
          <th>Competency</th>
          <th>Description</th>
          <th>Job Role</th>
          <th>Rating</th>
          <th>Comments</th>
          <th>Evaluation Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody><!-- Filled dynamically --></tbody>
    </table>
  </div>

  <!-- Modal: Assign Competency -->
  <div class="modal fade" id="assignCompetencyModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="assignCompetencyForm">
          <div class="modal-header">
            <h5 class="modal-title">Evaluate Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="employee_id" id="assignEmployeeId">

            <!-- Non-editable Employee Info -->
            <div class="mb-3">
              <label class="form-label">Employee Name</label>
              <div id="employeeNameLabel" class="form-control-plaintext text-center fw-bold" style="font-size:1.3rem;"></div>
            </div>
            <div class="mb-3">
              <label class="form-label">Job Role</label>
              <div id="employeeRoleLabel" class="form-control-plaintext text-center fw-bold" style="font-size:1rem;"></div>
            </div>

            <!-- Competency & Rating -->
            <div class="mb-3">
              <label class="form-label">Competency</label>
              <select class="form-select" name="competency_id" id="competencySelect" required></select>
            </div>
            <div class="mb-3">
              <label class="form-label">Rating</label>
              <select class="form-select" name="rating" required>
                <option value="">-- Select Rating --</option>
                <option value="1">1 - Needs Improvement</option>
                <option value="2">2 - Basic</option>
                <option value="3">3 - Meets Expectations</option>
                <option value="4">4 - Exceeds Expectations</option>
                <option value="5">5 - Expert</option>
              </select>
            </div>

            <!-- Notes -->
            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" name="notes"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Update Evaluation -->
  <div class="modal fade" id="updateEvaluationModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="updateEvaluationForm">
          <div class="modal-header">
            <h5 class="modal-title">Update Evaluation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="employee_id" id="updateEmployeeId">
            <input type="hidden" name="competency_id" id="updateCompetencyId">

            <div class="mb-3">
              <label class="form-label">Assessment Date</label>
              <input type="date" class="form-control" name="assessment_date" id="updateAssessmentDate" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Rating</label>
              <select class="form-select" name="rating" id="updateRating" required>
                <option value="1">1 - Needs Improvement</option>
                <option value="2">2 - Basic</option>
                <option value="3">3 - Meets Expectations</option>
                <option value="4">4 - Exceeds Expectations</option>
                <option value="5">5 - Expert</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Comments</label>
              <textarea class="form-control" name="comments" id="updateComments"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Update</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- JS Scripts -->
  <script>
  // ------------------------------
// Fetch and populate employees
// ------------------------------
function loadEmployees() {
  fetch('get_employees.php')
    .then(res => res.json())
    .then(data => {
      const select = document.getElementById('employeeSelect');
      select.innerHTML = '<option value="">-- Choose Employee --</option>';

      data.forEach(emp => {
        select.innerHTML += `<option value="${emp.personal_info_id}">${emp.last_name}, ${emp.first_name}</option>`;
      });

      // Automatically select the first employee and load competencies
      if (data.length > 0) {
        select.value = data[0].personal_info_id;
        document.getElementById('assignBtn').disabled = false;
        loadEmployeeCompetencies(data[0].personal_info_id);
      }
    })
    .catch(err => console.error("Failed to load employees:", err));
}

// ------------------------------
// Fetch and populate employee competencies
// ------------------------------
function loadEmployeeCompetencies(empId) {
  fetch(`get_employee_competencies.php?employee_id=${empId}`)
    .then(res => res.json())
    .then(data => {
      const tbody = document.querySelector("#employeeCompetencyTable tbody");
      tbody.innerHTML = "";

      if (!Array.isArray(data)) {
        console.error("Invalid data format", data);
        return;
      }

      data.forEach(ec => {
        tbody.innerHTML += `
          <tr>
            <td>${ec.name}</td>
            <td>${ec.description ?? ''}</td>
            <td>${ec.role ?? ''}</td>
            <td>${ec.rating ?? ''}</td>
            <td>${ec.comments ?? ''}</td>
            <td>${ec.assessment_date ?? ''}</td>
            <td>
              <button class="btn btn-sm btn-warning" 
                onclick="openUpdateModal(${ec.employee_id}, ${ec.competency_id}, '${ec.assessment_date}', ${ec.rating ?? 0}, '${encodeURIComponent(ec.comments ?? '')}')">
                Update
              </button>
              <button class="btn btn-sm btn-danger" 
                onclick="removeEmployeeCompetency(${ec.employee_id}, ${ec.competency_id})">
                Remove
              </button>
            </td>
          </tr>`;
      });
    })
    .catch(err => console.error("Failed to load competencies:", err));
}

// ------------------------------
// Assign competency modal
// ------------------------------
document.getElementById('assignBtn').addEventListener('click', async function() {
  const empId = document.getElementById('employeeSelect').value;
  if (!empId) return alert("Select an employee first.");
  document.getElementById('assignEmployeeId').value = empId;

  try {
    // Fetch employee details
    const empRes = await fetch(`get_employee_details.php?employee_id=${empId}`);
    const emp = await empRes.json();
    if (emp.error) return alert(emp.error);

    document.getElementById('employeeNameLabel').textContent = emp.name;
    document.getElementById('employeeRoleLabel').textContent = emp.job_role || 'No role assigned';

    // Determine job_role_id for fetching competencies
    const roleId = emp.job_role_id ?? 0; // 0 if null

    // Fetch competencies (specific role + global)
    const compRes = await fetch(`get_competencies.php?job_role_id=${roleId}`);
    const data = await compRes.json();

    const select = document.getElementById('competencySelect');
    select.innerHTML = '<option value="">-- Select Competency --</option>';

    if (Array.isArray(data) && data.length > 0) {
      data.forEach(c => {
        select.innerHTML += `<option value="${c.competency_id}">${c.name}</option>`;
      });
    } else {
      // Show message but keep dropdown enabled
      select.innerHTML = '<option value="">No competencies available for this role</option>';
    }

    // Show modal
    new bootstrap.Modal(document.getElementById('assignCompetencyModal')).show();

  } catch (err) {
    console.error("Failed loading competencies:", err);
    alert("Could not load competencies for this employee.");
  }
});

// ------------------------------
// Assign Form Submit
// ------------------------------
document.getElementById('assignCompetencyForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);

  fetch('assign_competency.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert("Competency assigned!");
        bootstrap.Modal.getInstance(document.getElementById('assignCompetencyModal')).hide();
        loadEmployeeCompetencies(document.getElementById('employeeSelect').value);
      } else {
        alert("Error: " + data.message);
      }
    });
});

// ------------------------------
// Update Evaluation Modal
// ------------------------------
function openUpdateModal(empId, compId, assessmentDate, rating, commentsEnc) {
  document.getElementById('updateEmployeeId').value = empId;
  document.getElementById('updateCompetencyId').value = compId;
  document.getElementById('updateAssessmentDate').value = assessmentDate;
  document.getElementById('updateRating').value = rating;
  document.getElementById('updateComments').value = decodeURIComponent(commentsEnc);
  new bootstrap.Modal(document.getElementById('updateEvaluationModal')).show();
}

// Update Form Submit
document.getElementById('updateEvaluationForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);

  fetch('update_employee_competency.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert("Evaluation updated!");
        bootstrap.Modal.getInstance(document.getElementById('updateEvaluationModal')).hide();
        loadEmployeeCompetencies(document.getElementById('employeeSelect').value);
      } else {
        alert("Error: " + data.message);
      }
    });
});

// ------------------------------
// Remove competency
// ------------------------------
function removeEmployeeCompetency(empId, compId) {
  if (!confirm("Are you sure you want to remove this competency?")) return;

  fetch('remove_employee_competency.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `employee_id=${empId}&competency_id=${compId}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert("Removed!");
      loadEmployeeCompetencies(empId);
    } else {
      alert("Error: " + data.message);
    }
  });
}

// ------------------------------
// Handle Employee Selection
// ------------------------------
document.getElementById('employeeSelect').addEventListener('change', function() {
  const empId = this.value;
  document.getElementById('assignBtn').disabled = !empId;
  if (empId) {
    loadEmployeeCompetencies(empId);
  } else {
    document.querySelector("#employeeCompetencyTable tbody").innerHTML = "";
  }
});

// ------------------------------
// Initialize
// ------------------------------
document.addEventListener("DOMContentLoaded", loadEmployees);

  </script>

  <!-- JS Libraries -->
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
