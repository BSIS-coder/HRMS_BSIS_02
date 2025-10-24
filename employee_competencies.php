<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'db.php';
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
      <i class="fas fa-plus"></i> Evaluate Employee
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

  <!-- Modal: Evaluate Employee -->
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
           <div class="mb-3">
              <label class="form-label">Review Cycle</label>
              <select class="form-select" name="cycle_id" id="cycleSelectEmployee" required>
                <option value="">-- Select Review Cycle --</option>
              </select>
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
            <label class="form-label">Review Cycle</label>
            <select class="form-select" name="cycle_id" id="updateCycleSelect" required>
              <option value="">-- Select Review Cycle --</option>
            </select>
          </div>

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
        select.innerHTML += `<option value="${emp.employee_id}">${emp.last_name}, ${emp.first_name}</option>`;
      });

      // Automatically select the first employee and load competencies
      if (data.length > 0) {
        select.value = data[0].employee_id;   // ✅ use employee_id
        document.getElementById('assignBtn').disabled = false;
        loadEmployeeCompetencies(data[0].employee_id);  // ✅ pass employee_id
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
                onclick="openUpdateModal(
                  ${ec.employee_id},   // ✅ fixed
                  ${ec.competency_id},
                  ${ec.cycle_id},
                  '${ec.assessment_date}',
                  ${ec.rating ?? 0},
                  '${encodeURIComponent(ec.comments ?? '')}'
                )">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-danger"
                onclick="removeEmployeeCompetency(${ec.employee_id}, ${ec.competency_id}, ${ec.cycle_id})">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>`;
      });
    })
    .catch(err => console.error("Failed to load competencies:", err));
}

function loadEmployeeCycles(selectedId = null) {
  return new Promise((resolve, reject) => {
    const select = document.getElementById('cycleSelectEmployee');
    select.innerHTML = '<option value="">-- Select Review Cycle --</option>';

    fetch('get_cycles.php')
      .then(res => res.json())
      .then(data => {
        if (data.success && Array.isArray(data.cycles)) {
          data.cycles.forEach(cycle => {
            const option = document.createElement('option');
            option.value = cycle.cycle_id;
            option.textContent = `${cycle.cycle_name} (${cycle.start_date} to ${cycle.end_date})`;
            if (selectedId && selectedId == cycle.cycle_id) option.selected = true;
            select.appendChild(option);
          });
        }
        resolve();
      })
      .catch(err => {
        console.error("Failed to load review cycles:", err);
        reject(err);
      });
  });
}

// ------------------------------
// Assign competency modal
// ------------------------------
document.getElementById('assignBtn').addEventListener('click', async function() {
  const employeeId = document.getElementById('employeeSelect').value;   // ✅ renamed
  if (!employeeId) return alert("Select an employee first.");

  try {
    // Get employee details (now use employee_id)
    const empRes = await fetch(`get_employee_details.php?employee_id=${employeeId}`);  // ✅ fixed
    const emp = await empRes.json();
    if (emp.error) return alert(emp.error);

    // 🔑 Assign employee_id directly to hidden field
    document.getElementById('assignEmployeeId').value = emp.employee_id;

    // Show employee name + role in the modal
    document.getElementById('employeeNameLabel').textContent = emp.name;
    document.getElementById('employeeRoleLabel').textContent = emp.job_role || 'No role assigned';

    const roleId = emp.job_role_id ?? 0;

    // Load competencies based on the employee's job role
    const compRes = await fetch(`get_competencies_by_role.php?job_role_id=${roleId}`);
    const competencies = await compRes.json();

    const competencySelect = document.getElementById('competencySelect');
    competencySelect.innerHTML = '<option value="">-- Select Competency --</option>';

    if (Array.isArray(competencies) && competencies.length > 0) {
      competencies.forEach(c => {
        const option = document.createElement('option');
        option.value = c.competency_id;
        option.textContent = c.name;
        competencySelect.appendChild(option);
      });
    } else {
      competencySelect.innerHTML = '<option value="">No competencies available for this role</option>';
    }

    // ✅ this belongs inside the try, not outside
    await loadEmployeeCycles();

    new bootstrap.Modal(document.getElementById('assignCompetencyModal')).show();
  } catch (err) {
    console.error("Failed loading employee info:", err);
    alert("Could not load employee data.");
  }
});

// ------------------------------
// Handle Assign Competency Form Submit
// ------------------------------
document.getElementById('assignCompetencyForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const form = this;
  const formData = new FormData(form);

  const saveBtn = form.querySelector('button[type="submit"]');
  saveBtn.disabled = true;

  fetch('assign_competency.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    saveBtn.disabled = false;
    if (data.success) {
      alert("Evaluation saved successfully!");
      const modalEl = document.getElementById('assignCompetencyModal');
      bootstrap.Modal.getInstance(modalEl).hide();
      const empId = document.getElementById('employeeSelect').value;
      loadEmployeeCompetencies(empId);
      form.reset();
    } else {
      alert("Error: " + (data.message || "Could not save evaluation."));
    }
  })
  .catch(err => {
    saveBtn.disabled = false;
    console.error("Assign competency error:", err);
    alert("Something went wrong. Check console for details.");
  });
});

// ------------------------------
// Update Evaluation Modal
// ------------------------------
async function openUpdateModal(empId, compId, cycleId, assessmentDate, rating, commentsEnc) {
  document.getElementById('updateEmployeeId').value = empId;
  document.getElementById('updateCompetencyId').value = compId;
  document.getElementById('updateAssessmentDate').value = assessmentDate;
  document.getElementById('updateRating').value = rating;
  document.getElementById('updateComments').value = decodeURIComponent(commentsEnc);

  const select = document.getElementById('updateCycleSelect');
  select.innerHTML = '<option value="">-- Select Review Cycle --</option>';

  try {
    const res = await fetch('get_cycles.php');
    const data = await res.json();

    if (data.success && Array.isArray(data.cycles)) {
      data.cycles.forEach(cycle => {
        const option = document.createElement('option');
        option.value = cycle.cycle_id;
        option.textContent = `${cycle.cycle_name} (${cycle.start_date} to ${cycle.end_date})`;
        if (cycle.cycle_id == cycleId) option.selected = true;
        select.appendChild(option);
      });
    }
  } catch (err) {
    console.error("Failed to load cycles:", err);
  }

  new bootstrap.Modal(document.getElementById('updateEvaluationModal')).show();
}

// Update Form Submit
document.getElementById('updateEvaluationForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const formData = new FormData(this);
  const selectedCycleId = document.getElementById('updateCycleSelect').value;
  formData.append('cycle_id', selectedCycleId);

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
    })
    .catch(err => {
      console.error("Update error:", err);
      alert("Something went wrong. Check console for details.");
    });
});

// ------------------------------
// Remove competency
// ------------------------------
function removeEmployeeCompetency(empId, compId, cycleId) {
  if (!confirm("Are you sure you want to remove this competency?")) return;

  fetch('remove_employee_competency.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `employee_id=${empId}&competency_id=${compId}&cycle_id=${cycleId}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert("Removed!");
      loadEmployeeCompetencies(document.getElementById('employeeSelect').value);
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
