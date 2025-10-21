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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Custom Styles */
    .section-title {
        color: var(--primary-color);
        margin-bottom: 30px;
        font-weight: 600;
    }
    .container-fluid { padding: 0; }
    .row { margin: 0; }
    .container {
        max-width: 85%;
        margin-left: 265px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    th, td {
        border: 1px solid #ccc;
        padding: 8px;
        text-align: left;
    }
  </style>
</head>
<body>
<div class="container-fluid">
  <?php include 'navigation.php'; ?>

  <div class="row">
    <?php include 'sidebar.php'; ?>

    <div class="container">
      <br><br><br><br><br>
      <h1>Competencies</h1>
      <br>
      <button id="addBtn" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Add Competency</button>

      <!-- Filter -->
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="filterRole" class="form-label">Filter by Job Role</label>
          <select id="filterRole" class="form-select">
            <option value="">-- All Roles --</option>
          </select>
        </div>
        <div class="col-md-4">
          <label for="filterDepartment" class="form-label">Filter by Department</label>
          <select id="filterDepartment" class="form-select">
            <option value="">-- All Departments --</option>
          </select>
        </div>
      </div>

      <!-- Competency Table -->
      <table class="table table-bordered" id="competencyTable">
        <thead>
          <tr>
            <th>Competency</th>
            <th>Description</th>
            <th>Job Role</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Filled by JS -->
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Competency Modal -->
<div class="modal fade" id="addCompetencyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="competencyForm">
        <div class="modal-header">
          <h5 class="modal-title">Add Competency</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Competency Name</label>
            <input type="text" class="form-control" name="competency_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Job Role</label>
            <select class="form-select" name="job_role_id" id="jobRoleSelect" required></select>
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

<!-- Edit Competency Modal (Bootstrap 5) -->
<div class="modal fade" id="editCompetencyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editCompetencyForm">
        <div class="modal-header">
          <h5 class="modal-title">Edit Competency</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="competency_id" id="editCompetencyId">
          <div class="mb-3">
            <label class="form-label">Competency Name</label>
            <input type="text" class="form-control" name="competency_name" id="editCompetencyName" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" id="editCompetencyDesc"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Job Role</label>
            <select class="form-select" name="job_role_id" id="editJobRoleSelect" required></select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</i></button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ===== Competency JS =====

// Show Add Modal
document.getElementById('addBtn').addEventListener('click', () => {
  new bootstrap.Modal(document.getElementById('addCompetencyModal')).show();
  loadRoles(); // refresh roles dropdown
});

// Load job roles into Add Competency dropdown
function loadRoles() {
  fetch('get_roles.php')
    .then(res => res.json())
    .then(data => {
      let select = document.getElementById('jobRoleSelect');
      select.innerHTML = '<option value="">-- Select Role --</option>';
      if (!Array.isArray(data)) return;
      data.forEach(r => {
        select.innerHTML += `<option value="${r.job_role_id}">${r.title}</option>`;
      });
    })
    .catch(err => console.error('Error loading roles:', err));
}

// Add competency
document.getElementById('competencyForm').addEventListener('submit', function(e) {
  e.preventDefault();
  let formData = new FormData(this);

  fetch('add_competency.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert("Competency added!");
        this.reset();
        bootstrap.Modal.getInstance(document.getElementById('addCompetencyModal')).hide();
        loadCompetencies();
      } else {
        alert("Error: " + data.message);
      }
    })
    .catch(err => console.error('Add competency error:', err));
});

// Load competencies
function loadCompetencies(roleId = "", department = "") {
  let url = 'load_competencies.php';
  let params = [];
  if (roleId) params.push('role_id=' + roleId);
  if (department) params.push('department=' + encodeURIComponent(department));
  if (params.length > 0) url += '?' + params.join('&');

  fetch(url)
    .then(res => res.json())
    .then(data => {
      let tbody = document.querySelector("#competencyTable tbody");
      tbody.innerHTML = "";

      if (!Array.isArray(data)) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center">No data found.</td></tr>`;
        return;
      }

      data.forEach(c => {
        tbody.innerHTML += `
          <tr>
            <td>${c.name}</td>
            <td>${c.description ?? ''}</td>
            <td>${c.role ?? ''}</td>
            <td>
              <button class="btn btn-sm btn-warning" onclick="editCompetency(${c.competency_id})"><i class="fas fa-edit"></i></button>
              <button class="btn btn-sm btn-danger" onclick="deleteCompetency(${c.competency_id})"><i class="fas fa-trash"></i></button>
            </td>
          </tr>`;
      });
    })
    .catch(err => console.error('Error loading competencies:', err));
}

// Edit competency
function editCompetency(id) {
  fetch(`get_competencies.php?id=${id}`)
    .then(res => res.json())
    .then(c => {
      document.getElementById('editCompetencyId').value = c.competency_id;
      document.getElementById('editCompetencyName').value = c.name || '';
      document.getElementById('editCompetencyDesc').value = c.description || '';

      const editModal = new bootstrap.Modal(document.getElementById('editCompetencyModal'));
      editModal.show();

      fetch('get_roles.php')
        .then(res => res.json())
        .then(data => {
          let select = document.getElementById('editJobRoleSelect');
          select.innerHTML = '<option value="">-- Select Role --</option>';
          data.forEach(r => {
            let selected = (String(r.job_role_id) === String(c.job_role_id)) ? "selected" : "";
            select.innerHTML += `<option value="${r.job_role_id}" ${selected}>${r.title}</option>`;
          });
        });
    })
    .catch(err => {
      console.error('get_competency error:', err);
      alert('Failed to load competency details.');
    });
}

// Update competency
document.getElementById('editCompetencyForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = this;
  const submitBtn = form.querySelector('button[type="submit"]');
  const modalEl = document.getElementById('editCompetencyModal');
  const modalInstance = bootstrap.Modal.getInstance(modalEl);

  submitBtn.disabled = true;
  submitBtn.textContent = "Updating...";

  fetch('update_competency.php', {
    method: 'POST',
    body: new FormData(form)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert("Updated successfully!");
      if (modalInstance) modalInstance.hide();
      loadCompetencies();
      form.reset();
    } else {
      alert("Error: " + (data.message || "Update failed"));
    }
  })
  .catch(err => console.error("Update error:", err))
  .finally(() => {
    submitBtn.disabled = false;
    submitBtn.textContent = "Update";
  });
});

// Delete competency
function deleteCompetency(id) {
  if (!confirm("Are you sure you want to delete this competency?")) return;

  fetch('delete_competency.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `id=${id}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert("Deleted successfully!");
      loadCompetencies();
    } else {
      alert("Error: " + data.message);
    }
  });
}

// Load roles into Filter dropdown
function loadRoleFilter() {
  fetch('get_roles.php')
    .then(res => res.json())
    .then(data => {
      let select = document.getElementById('filterRole');
      select.innerHTML = '<option value="">-- All Roles --</option>';
      if (!Array.isArray(data)) return;
      data.forEach(r => {
        select.innerHTML += `<option value="${r.job_role_id}">${r.title}</option>`;
      });
    })
    .catch(err => console.error('Error loading roles:', err));
}

// Load departments into Filter dropdown
function loadDepartmentFilter() {
  fetch('get_departments.php')
    .then(res => res.json())
    .then(data => {
      let select = document.getElementById('filterDepartment');
      select.innerHTML = '<option value="">-- All Departments --</option>';
      if (data.error || data.message) {
        console.warn(data.error || data.message);
        return;
      }
      data.forEach(d => {
        select.innerHTML += `<option value="${d.department}">${d.department}</option>`;
      });
    })
    .catch(err => console.error('Error loading departments:', err));
}

// Filter change
document.getElementById('filterRole').addEventListener('change', function() {
  const department = document.getElementById('filterDepartment').value;
  loadCompetencies(this.value, department);
});

// Department filter change
document.getElementById('filterDepartment').addEventListener('change', function() {
  const roleId = document.getElementById('filterRole').value;
  loadCompetencies(roleId, this.value);
});

// Init
document.addEventListener("DOMContentLoaded", () => {
  loadCompetencies();
  loadRoleFilter();
  loadDepartmentFilter();
});

</script>


<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
