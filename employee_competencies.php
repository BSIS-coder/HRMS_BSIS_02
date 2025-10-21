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
    .container { max-width: 85%; margin-left: 265px; padding-top:50px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }

    /* Custom styles for search input and button */
    .search-input {
      border: 1px solid var(--primary-color);
      border-radius: 6px 0 0 6px;
    } 
    .search-input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.25);
    }
    .search-btn {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
      border: 1px solid var(--primary-color);
      border-radius: 0 6px 6px 0;
      color: var(--text-white);
      transition: all 0.3s;
    }
    .search-btn:hover {
      background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px var(--shadow-medium);
    }
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
        <select id="employeeSelect" class="form-control">
          <option value="">-- Choose Employee --</option>
        </select>
      </div>
      <div class="col-md-6">
        <label for="employeeSearch" class="form-label">Search Employee by Name</label>
        <div class="input-group">
          <input type="text" id="employeeSearch" class="form-control search-input" placeholder="Type employee name...">
          <button id="searchBtn" class="btn btn-primary search-btn" type="button">
            <i class="fas fa-search"></i> Search
          </button>
        </div>
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
    <div class="modal-dialog modal-lg">
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

            <!-- Dynamic Competencies Section -->
            <div id="competenciesContainer">
              <!-- Rows will be added dynamically based on competencies -->
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
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="updateEvaluationForm">
        <div class="modal-header">
          <h5 class="modal-title">Update Evaluation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="employee_id" id="updateEmployeeId">
          <input type="hidden" name="cycle_id" id="updateCycleId">

          <!-- Non-editable Employee Info -->
          <div class="mb-3">
            <label class="form-label">Employee Name</label>
            <div id="updateEmployeeNameLabel" class="form-control-plaintext text-center fw-bold" style="font-size:1.3rem;"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Job Role</label>
            <div id="updateEmployeeRoleLabel" class="form-control-plaintext text-center fw-bold" style="font-size:1rem;"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Review Cycle</label>
            <div id="updateCycleLabel" class="form-control-plaintext text-center fw-bold"></div>
          </div>

          <!-- Dynamic Competencies Section -->
          <div id="updateCompetenciesContainer">
            <!-- Rows will be added dynamically based on existing evaluations -->
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
// Global variable to store employees data
// ------------------------------
let employeesData = [];

// ------------------------------
// Fetch and populate employees
// ------------------------------
function loadEmployees() {
  fetch('get_employees.php')
    .then(res => res.json())
    .then(data => {
      employeesData = data; // Store globally for search
      const select = document.getElementById('employeeSelect');
      select.innerHTML = '<option value="">-- Choose Employee --</option>';

      data.forEach(emp => {
        select.innerHTML += `<option value="${emp.employee_id}">
          ${emp.last_name}, ${emp.first_name} — ${emp.job_role || 'No Role Assigned'}
        </option>`;
      });

      // Automatically select the first employee and load competencies
      if (data.length > 0) {
        select.value = data[0].employee_id;
        document.getElementById('assignBtn').disabled = false;
        loadEmployeeCompetencies(data[0].employee_id);
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

      if (data.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" class="text-center text-muted">No evaluations yet</td>
          </tr>`;
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
// Global competencies data
// ------------------------------
let globalCompetencies = [];

// ------------------------------
// Assign competency modal
// ------------------------------
document.getElementById('assignBtn').addEventListener('click', async function() {
  const employeeId = document.getElementById('employeeSelect').value;
  if (!employeeId) return alert("Select an employee first.");

  try {
    // Get employee details
    const empRes = await fetch(`get_employee_details.php?employee_id=${employeeId}`);
    const emp = await empRes.json();
    if (emp.error) return alert(emp.error);

    document.getElementById('assignEmployeeId').value = emp.employee_id;
    document.getElementById('employeeNameLabel').textContent = emp.name;
    document.getElementById('employeeRoleLabel').textContent = emp.job_role || 'No role assigned';

    const roleId = emp.job_role_id ?? 0;

    // Load competencies
    const compRes = await fetch(`get_competencies_by_role.php?job_role_id=${roleId}`);
    globalCompetencies = await compRes.json();

    // Create rows for each competency
    const container = document.getElementById('competenciesContainer');
    container.innerHTML = '';

    if (Array.isArray(globalCompetencies) && globalCompetencies.length > 0) {
      globalCompetencies.forEach(c => {
        const row = document.createElement('div');
        row.className = 'competency-row mb-3 border p-3 rounded';
        row.innerHTML = `
          <div class="row">
            <div class="col-md-4">
              <label class="form-label">Competency</label>
              <input type="hidden" name="competency_ids[]" value="${c.competency_id}">
              <input type="text" class="form-control" value="${c.name}" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Rating</label>
              <select class="form-select rating-select" name="ratings[]" required>
                <option value="">-- Select Rating --</option>
                <option value="1">1 - Needs Improvement</option>
                <option value="2">2 - Basic</option>
                <option value="3">3 - Meets Expectations</option>
                <option value="4">4 - Exceeds Expectations</option>
                <option value="5">5 - Expert</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Remarks</label>
              <textarea class="form-control notes-textarea" name="notes[]" rows="2"></textarea>
            </div>
            <div class="col-md-1 d-flex align-items-end">
              <button type="button" class="btn btn-danger btn-sm remove-competency" style="display: none;">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        `;
        container.appendChild(row);
      });
    } else {
      container.innerHTML = '<p class="text-muted">No competencies available for this role.</p>';
    }

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

  // Validate that at least one competency is selected
  const competencySelects = form.querySelectorAll('.competency-row');
  let hasValidEntry = false;
  competencySelects.forEach(row => {
    const ratingSelect = row.querySelector('.rating-select');
    if (ratingSelect.value) hasValidEntry = true;
  });
  if (!hasValidEntry) {
    alert("Please select at least one rating for a competency.");
    return;
  }

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
      alert("Evaluations saved successfully!");
      const modalEl = document.getElementById('assignCompetencyModal');
      bootstrap.Modal.getInstance(modalEl).hide();
      const empId = document.getElementById('employeeSelect').value;
      loadEmployeeCompetencies(empId);
      // Reset form
      form.reset();
    } else {
      alert("Error: " + (data.message || "Could not save evaluations."));
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
  console.log("DEBUG cycleId:", cycleId); // ✅ Add this line
  try {
    // Get employee details
    const empRes = await fetch(`get_employee_details.php?employee_id=${empId}`);
    const emp = await empRes.json();
    if (emp.error) return alert(emp.error);

    document.getElementById('updateEmployeeId').value = emp.employee_id;
    document.getElementById('updateCycleId').value = cycleId;
    document.getElementById('updateEmployeeNameLabel').textContent = emp.name;
    document.getElementById('updateEmployeeRoleLabel').textContent = emp.job_role || 'No role assigned';

    // Get cycle details
    const cycleRes = await fetch(`get_cycle_details.php?cycle_id=${cycleId}`);
    const cycle = await cycleRes.json();
    document.getElementById('updateCycleLabel').textContent = cycle.cycle_name ? `${cycle.cycle_name} (${cycle.start_date} to ${cycle.end_date})` : 'Unknown Cycle';

    // Load existing evaluations for this employee and cycle
    const evalRes = await fetch(`get_employee_competencies.php?employee_id=${empId}&cycle_id=${cycleId}`);
    const evaluations = await evalRes.json();

    // Create rows for each evaluation
    const container = document.getElementById('updateCompetenciesContainer');
    container.innerHTML = '';

    if (Array.isArray(evaluations) && evaluations.length > 0) {
      evaluations.forEach(ev => {
        const row = document.createElement('div');
        row.className = 'competency-row mb-3 border p-3 rounded';
        row.innerHTML = `
          <div class="row">
            <div class="col-md-4">
              <label class="form-label">Competency</label>
              <input type="hidden" name="competency_ids[]" value="${ev.competency_id}">
              <input type="text" class="form-control" value="${ev.name}" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Rating</label>
              <select class="form-select rating-select" name="ratings[]" required>
                <option value="">-- Select Rating --</option>
                <option value="1" ${ev.rating == 1 ? 'selected' : ''}>1 - Needs Improvement</option>
                <option value="2" ${ev.rating == 2 ? 'selected' : ''}>2 - Basic</option>
                <option value="3" ${ev.rating == 3 ? 'selected' : ''}>3 - Meets Expectations</option>
                <option value="4" ${ev.rating == 4 ? 'selected' : ''}>4 - Exceeds Expectations</option>
                <option value="5" ${ev.rating == 5 ? 'selected' : ''}>5 - Expert</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Remarks</label>
              <textarea class="form-control notes-textarea" name="notes[]" rows="2">${ev.comments || ''}</textarea>
            </div>
            <div class="col-md-1 d-flex align-items-end">
              <button type="button" class="btn btn-danger btn-sm remove-competency" onclick="removeCompetencyFromUpdate(${ev.competency_id})">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </div>
        `;
        container.appendChild(row);
      });
    } else {
      container.innerHTML = '<p class="text-muted">No evaluations found for this cycle.</p>';
    }

    new bootstrap.Modal(document.getElementById('updateEvaluationModal')).show();
  } catch (err) {
    console.error("Failed loading update modal data:", err);
    alert("Could not load update data.");
  }
}

// Update Form Submit
document.getElementById('updateEvaluationForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const form = this;
  const formData = new FormData(form);

  // Validate that at least one competency is selected
  const competencySelects = form.querySelectorAll('.competency-row');
  let hasValidEntry = false;
  competencySelects.forEach(row => {
    const ratingSelect = row.querySelector('.rating-select');
    if (ratingSelect.value) hasValidEntry = true;
  });
  if (!hasValidEntry) {
    alert("Please select at least one rating for a competency.");
    return;
  }

  const updateBtn = form.querySelector('button[type="submit"]');
  updateBtn.disabled = true;

  fetch('assign_competency.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    updateBtn.disabled = false;
    if (data.success) {
      alert("Evaluations updated successfully!");
      const modalEl = document.getElementById('updateEvaluationModal');
      bootstrap.Modal.getInstance(modalEl).hide();
      const empId = document.getElementById('employeeSelect').value;
      loadEmployeeCompetencies(empId);
    } else {
      alert("Error: " + (data.message || "Could not update evaluations."));
    }
  })
  .catch(err => {
    updateBtn.disabled = false;
    console.error("Update competency error:", err);
    alert("Something went wrong. Check console for details.");
  });
});

// ------------------------------
// Remove competency from update modal
// ------------------------------
function removeCompetencyFromUpdate(compId) {
  if (!confirm("Are you sure you want to remove this competency from the evaluation?")) return;

  const empId = document.getElementById('updateEmployeeId').value;
  const cycleId = document.getElementById('updateCycleId').value;

  fetch('remove_employee_competency.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `employee_id=${empId}&competency_id=${compId}&cycle_id=${cycleId}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert("Competency removed from evaluation!");
      // Reload the update modal
      openUpdateModal(empId, compId, cycleId, '', 0, '');
    } else {
      alert("Error: " + data.message);
    }
  })
  .catch(err => {
    console.error("Remove error:", err);
    alert("Something went wrong. Check console for details.");
  });
}

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
    document.querySelector("#employeeCompetencyTable tbody").innerHTML = `
      <tr>
        <td colspan="7" class="text-center text-muted">No evaluations yet</td>
      </tr>`;
  }
});

// ------------------------------
// Handle Search Button Click
// ------------------------------
document.getElementById('searchBtn').addEventListener('click', function() {
  const searchTerm = document.getElementById('employeeSearch').value.trim().toLowerCase();
  if (!searchTerm) {
    alert("Please enter an employee name to search.");
    return;
  }

  // Find the employee by name (case-insensitive match on first_name or last_name)
  const foundEmployee = employeesData.find(emp =>
    emp.first_name.toLowerCase().includes(searchTerm) ||
    emp.last_name.toLowerCase().includes(searchTerm) ||
    `${emp.first_name} ${emp.last_name}`.toLowerCase().includes(searchTerm)
  );

  if (foundEmployee) {
    // Set the dropdown to the found employee
    document.getElementById('employeeSelect').value = foundEmployee.employee_id;
    // Enable the assign button
    document.getElementById('assignBtn').disabled = false;
    // Load competencies for the found employee
    loadEmployeeCompetencies(foundEmployee.employee_id);
  } else {
    alert("No employee found with that name.");
    // Clear the table if no employee found
    document.querySelector("#employeeCompetencyTable tbody").innerHTML = `
      <tr>
        <td colspan="7" class="text-center text-muted">No evaluations yet</td>
      </tr>`;
    document.getElementById('assignBtn').disabled = true;
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
