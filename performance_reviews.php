<?php
session_start();

// Require authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'dp.php'; // database connection
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>HR Dashboard - Performance Reviews</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
  <link rel="stylesheet" href="styles.css">
  <style>
    .container { max-width: 85%; margin-left: 265px; padding-top: 3.5rem; }
    .section-title { color: var(--primary-color); margin-bottom: 1.5rem; font-weight:600 }
    .stat-card { min-height:100px }
    table th, table td { vertical-align: middle }
  </style>
</head>
<body>
  <div class="container-fluid"><?php include 'navigation.php'; ?></div>
  <div class="row"><?php include 'sidebar.php'; ?></div>

  <div class="container">
    <div class="d-flex justify-content-between align-items-center">
      <h1 class="section-title">Performance Reviews</h1>
      <div>
        <button id="refreshBtn" class="btn btn-outline-secondary me-2"><i class="fas fa-sync"></i> Refresh</button>
        <button id="exportBtn" class="btn btn-outline-primary"><i class="fas fa-file-export"></i> Export</button>
      </div>
    </div>

    <!-- Cycle selector -->
    <div class="row g-3 align-items-end">
      <div class="col-md-5">
        <label class="form-label">Select Review Cycle</label>
        <select id="cycleSelect" class="form-select"> 
          <option value="">-- Loading cycles --</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Filter by Department</label>
        <select id="deptFilter" class="form-select">
          <option value="">-- All Departments --</option>
        </select>
      </div>
      <div class="col-md-4 text-end">
        <button id="finalizeBtn" class="btn btn-success" disabled><i class="fas fa-check"></i> Finalize Cycle</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="row mt-4" id="statsRow">
      <div class="col-md-3">
        <div class="card stat-card">
          <div class="card-body text-center">
            <small class="text-muted">Average Rating</small>
            <h3 id="avgRating">-</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card">
          <div class="card-body text-center">
            <small class="text-muted">Completed Reviews</small>
            <h3 id="completedPct">-</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card">
          <div class="card-body text-center">
            <small class="text-muted">Pending Reviews</small>
            <h3 id="pendingCount">-</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stat-card">
          <div class="card-body text-center">
            <small class="text-muted">Employees Reviewed</small>
            <h3 id="employeesReviewed">-</h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Competencies table -->
    <div class="table-responsive mt-4">
      <table class="table table-hover table-bordered" id="competenciesTable">
        <thead class="table-dark">
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th>Job Role</th>
            <th>Avg Rating</th>
            <th>Competencies Assessed</th>
            <th>Last Assessment Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="competenciesTbody">
          <tr><td colspan="8" class="text-center">Select a review cycle to load data</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <nav><ul class="pagination" id="reviewsPagination"></ul></nav>
  </div>

  <!-- Details Modal -->
  <div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Employee Review Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="detailHeader" class="mb-3">
            <h5 id="detailEmployee"></h5>
            <small id="detailMeta" class="text-muted"></small>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead>
                <tr><th>Competency</th><th>Rating</th><th>Comments</th></tr>
              </thead>
              <tbody id="detailCompetencies"></tbody>
            </table>
          </div>

          <div class="mt-3">
            <label class="form-label">Manager Comments</label>
            <div id="detailManagerComments" class="border p-2">-</div>
          </div>
        </div>
        <div class="modal-footer">
          <button id="editEvalBtn" type="button" class="btn btn-warning">Edit Evaluation</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Evaluation Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Employee Evaluation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="editHeader" class="mb-3">
            <h5 id="editEmployee"></h5>
            <small id="editMeta" class="text-muted"></small>
          </div>

          <form id="editForm">
            <div id="editCompetencies" class="mb-3">
              <!-- Competencies will be populated here -->
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button id="saveEditBtn" type="button" class="btn btn-success">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </div>
  </div>

<script>
const rowsPerPage = 10;
let competenciesData = [];
let currentPage = 1;
let currentCycleId = null;
let currentEmployeeId = null;

function elem(id){ return document.getElementById(id); }

// ---------- Load Cycles ----------
function loadCycles() {
  const sel = elem('cycleSelect');
  sel.disabled = true;
  sel.innerHTML = '<option value="">-- Loading cycles --</option>';

  fetch('get_cycles.php')
    .then(r => r.json())
    .then(data => {
      sel.disabled = false;
      sel.innerHTML = '<option value="">-- Select a Cycle --</option>';
      if (data.success && Array.isArray(data.cycles)) {
        data.cycles.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.cycle_id;
          opt.text = `${c.cycle_name} (${c.start_date} to ${c.end_date})`;
          sel.appendChild(opt);
        });
      } else {
        sel.innerHTML = '<option value="">No cycles available</option>';
      }
    })
    .catch(err => {
      console.error('loadCycles error', err);
      sel.innerHTML = '<option value="">Failed to load cycles</option>';
      sel.disabled = false;
    });
}


document.addEventListener('DOMContentLoaded', () => {
  loadCycles();
  const cycleSel = document.getElementById('cycleSelect');
  cycleSel.addEventListener('change', () => {
    console.log('Selected Cycle ID:', cycleSel.value);
  });
});


// ---------- Load Departments ----------
function loadDepartments(){
  fetch('get_departments.php')
    .then(r => r.json())
    .then(data => {
      const sel = elem('deptFilter');
      sel.innerHTML = '<option value="">-- All Departments --</option>';
      if (Array.isArray(data) && data.length > 0){
        data.forEach(d => {
          sel.innerHTML += `<option value="${d.department}">${d.department}</option>`;
        });
      }
    })
    .catch(err => console.error('loadDepartments error', err));
}

// ---------- Load Competencies ----------
function loadCompetencies(cycleId, page = 1) {
  if (!cycleId) return;
  currentCycleId = cycleId;
  elem('competenciesTbody').innerHTML = '<tr><td colspan="8" class="text-center">Loading...</td></tr>';

  const dept = elem('deptFilter').value;
  const url = `get_cycle_competencies.php?cycle_id=${encodeURIComponent(cycleId)}${dept ? `&department=${encodeURIComponent(dept)}` : ''}`;

  fetch(url)
    .then(r => r.json())
    .then(data => {
      console.log('Fetched data:', data);

      if (!data || !data.success || !Array.isArray(data.competencies)) {
        elem('competenciesTbody').innerHTML =
          '<tr><td colspan="8" class="text-center text-muted">No data found for this cycle</td></tr>';
        elem('finalizeBtn').disabled = true;
        return;
      }

      competenciesData = data.competencies;
      currentPage = page;
      renderCompetenciesPage();

      // Update stats
      const avgRating =
        competenciesData.length > 0
          ? competenciesData.reduce((sum, c) => sum + (parseFloat(c.avg_rating) || 0), 0) / competenciesData.length
          : 0;
      elem('avgRating').textContent = avgRating > 0 ? avgRating.toFixed(2) : '-';
      elem('completedPct').textContent = '-';
      elem('pendingCount').textContent = '-';
      elem('employeesReviewed').textContent = competenciesData.length;

      elem('finalizeBtn').disabled = false;
    })
    .catch(err => {
      console.error('loadCompetencies error', err);
      elem('competenciesTbody').innerHTML =
        '<tr><td colspan="8" class="text-center text-danger">Error loading data</td></tr>';
    });
}


function renderCompetenciesPage(){
  const tbody = elem('competenciesTbody');
  tbody.innerHTML = '';
  const start = (currentPage-1)*rowsPerPage;
  const pageItems = competenciesData.slice(start, start + rowsPerPage);

  if(pageItems.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No employees to show</td></tr>';
    elem('reviewsPagination').innerHTML = '';
    return;
  }

  pageItems.forEach(c => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHtml(c.employee_name)}</td>
      <td>${escapeHtml(c.department ?? '')}</td>
      <td>${escapeHtml(c.role ?? '')}</td>
      <td>${c.avg_rating !== null ? c.avg_rating.toFixed(2) : '-'}</td>
      <td>${c.competencies_assessed ?? 0}</td>
      <td>${escapeHtml(c.last_assessment_date ?? '')}</td>
      <td>
        <button class="btn btn-sm btn-info me-1" onclick="viewDetails(${c.employee_id})"><i class="fas fa-eye"></i></button>
        <button class="btn btn-sm btn-primary me-1" onclick="openEditModal(${c.employee_id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-danger" onclick="deleteEmployeeCompetencies(${c.employee_id})"><i class="fas fa-trash"></i></button>
      </td>`;
    tbody.appendChild(tr);
  });

  // Pagination
  const pages = Math.ceil(competenciesData.length/rowsPerPage);
  const pag = elem('reviewsPagination');
  pag.innerHTML = '';
  for(let i=1;i<=pages;i++){
    const li = document.createElement('li');
    li.className = `page-item ${i===currentPage? 'active':''}`;
    li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
    li.addEventListener('click', (e)=>{ e.preventDefault(); currentPage=i; renderCompetenciesPage(); });
    pag.appendChild(li);
  }
}

// ---------- Details Modal ----------
function viewDetails(empId){
  if(!currentCycleId) return alert('Select a cycle first');
  currentEmployeeId = empId;
  elem('detailCompetencies').innerHTML = '<tr><td colspan="3">Loading...</td></tr>';
  fetch(`get_employee_review_details.php?employee_id=${empId}&cycle_id=${currentCycleId}`)
    .then(r=>r.json())
    .then(data=>{
      if(!data || !data.review){
        elem('detailCompetencies').innerHTML = '<tr><td colspan="3">No details found</td></tr>';
        return;
      }
      const rev = data.review;
      elem('detailEmployee').textContent = rev.employee_name;
      elem('detailMeta').textContent = `${rev.dept || ''} • ${rev.role || ''} • Avg: ${rev.avg_rating !== null ? rev.avg_rating.toFixed(2) : '-'} `;

      const tbody = elem('detailCompetencies'); tbody.innerHTML = '';
      (rev.competencies || []).forEach(c => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${escapeHtml(c.name)}</td><td>${c.rating ?? '-'}</td><td>${escapeHtml(c.comments ?? '')}</td>`;
        tbody.appendChild(tr);
      });

      elem('detailManagerComments').textContent = data.review.manager_comments ?? '-';
      new bootstrap.Modal(document.getElementById('detailModal')).show();
    }).catch(err=>{ console.error('detail error', err); alert('Failed to load details'); });
}

// ---------- Open Edit Modal ----------
function openEditModal(empId){
  console.log('openEditModal called with empId:', empId);
  if(!currentCycleId) {
    console.log('No cycle selected');
    return alert('Select a cycle first');
  }
  currentEmployeeId = empId;
  console.log('currentEmployeeId:', currentEmployeeId, 'currentCycleId:', currentCycleId);
  elem('editCompetencies').innerHTML = 'Loading...';
  const url = `get_employee_review_details.php?employee_id=${currentEmployeeId}&cycle_id=${currentCycleId}`;
  console.log('Fetching URL:', url);
  fetch(url)
    .then(r => {
      console.log('Fetch response status:', r.status);
      return r.json();
    })
    .then(data => {
      console.log('Fetched data:', data);
      if(!data || !data.review){
        console.log('No review data');
        elem('editCompetencies').innerHTML = 'No details found';
        return;
      }
      const rev = data.review;
      console.log('Review data:', rev);
      elem('editEmployee').textContent = rev.employee_name;
      elem('editMeta').textContent = `${rev.dept || ''} • ${rev.role || ''} • Avg: ${rev.avg_rating !== null ? rev.avg_rating.toFixed(2) : '-'} `;

      const container = elem('editCompetencies');
      container.innerHTML = '';
      (rev.competencies || []).forEach(c => {
        console.log('Competency:', c);
        const div = document.createElement('div');
        div.className = 'mb-3 border p-3';
        div.innerHTML = `
          <label class="form-label fw-bold">${escapeHtml(c.name)}</label>
          <div class="row">
            <div class="col-md-3">
              <label class="form-label">Rating</label>
              <select class="form-select competency-rating" data-competency-id="${c.competency_id}">
                <option value="" ${!c.rating ? 'selected' : ''}>Select Rating</option>
                <option value="1" ${c.rating == 1 ? 'selected' : ''}>1 - Poor</option>
                <option value="2" ${c.rating == 2 ? 'selected' : ''}>2 - Below Average</option>
                <option value="3" ${c.rating == 3 ? 'selected' : ''}>3 - Average</option>
                <option value="4" ${c.rating == 4 ? 'selected' : ''}>4 - Good</option>
                <option value="5" ${c.rating == 5 ? 'selected' : ''}>5 - Excellent</option>
              </select>
            </div>
            <div class="col-md-9">
              <label class="form-label">Comments</label>
              <textarea class="form-control competency-comments" rows="2">${escapeHtml(c.comments ?? '')}</textarea>
            </div>
          </div>
        `;
        container.appendChild(div);
      });

      console.log('Showing modal');
      new bootstrap.Modal(document.getElementById('editModal')).show();
    }).catch(err => {
      console.error('edit load error', err);
      alert('Failed to load edit data');
    });
}

// ---------- Finalize ----------
elem('finalizeBtn').addEventListener('click', ()=>{
  if(!currentCycleId) return alert('Select a cycle first');
  if(!confirm('Finalize this cycle? This will lock further edits.')) return;
  fetch('finalize_cycle.php', { 
    method:'POST', 
    headers:{'Content-Type':'application/x-www-form-urlencoded'}, 
    body:`cycle_id=${encodeURIComponent(currentCycleId)}`
  })
    .then(r=>r.json())
    .then(d=>{
      alert(d.message || (d.success ? 'Cycle finalized' : 'Could not finalize'));
      loadCycles(); loadReviews(currentCycleId);
    }).catch(err=>console.error(err));
});

// ---------- Export ----------
elem('exportBtn').addEventListener('click', ()=>{
  if(!currentCycleId) return alert('Select a cycle to export');
  window.open(`export_review_report.php?cycle_id=${encodeURIComponent(currentCycleId)}`,'_blank');
});

// ---------- Refresh ----------
elem('refreshBtn').addEventListener('click', ()=>{
  if(currentCycleId) loadCompetencies(currentCycleId); else loadCycles();
});

// ---------- Utilities ----------
function escapeHtml(s){ if(s===null||s===undefined) return ''; return String(s).replace(/[&<>"]/g, c=> ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]); }

// ---------- Delete Employee Competencies ----------
function deleteEmployeeCompetencies(employeeId){
  if(!confirm('Are you sure you want to delete all competency assessments for this employee in this cycle?')) return;
  fetch('delete_employee_competencies.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `employee_id=${encodeURIComponent(employeeId)}&cycle_id=${encodeURIComponent(currentCycleId)}`
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message || (data.success ? 'Deleted successfully' : 'Failed to delete'));
    if(data.success) loadCompetencies(currentCycleId);
  })
  .catch(err => {
    console.error('delete error', err);
    alert('Failed to delete');
  });
}

// ---------- Edit Evaluation Modal ----------
elem('editEvalBtn').addEventListener('click', ()=>{
  if(!currentEmployeeId || !currentCycleId) return alert('No employee selected');
  elem('editCompetencies').innerHTML = 'Loading...';
  fetch(`get_employee_review_details.php?employee_id=${currentEmployeeId}&cycle_id=${currentCycleId}`)
    .then(r=>r.json())
    .then(data=>{
      if(!data || !data.review){
        elem('editCompetencies').innerHTML = 'No details found';
        return;
      }
      const rev = data.review;
      elem('editEmployee').textContent = rev.employee_name;
      elem('editMeta').textContent = `${rev.dept || ''} • ${rev.role || ''} • Avg: ${rev.avg_rating !== null ? rev.avg_rating.toFixed(2) : '-'} `;

      const container = elem('editCompetencies');
      container.innerHTML = '';
      (rev.competencies || []).forEach(c => {
        const div = document.createElement('div');
        div.className = 'mb-3 border p-3';
        div.innerHTML = `
          <label class="form-label fw-bold">${escapeHtml(c.name)}</label>
          <div class="row">
            <div class="col-md-3">
              <label class="form-label">Rating</label>
              <select class="form-select competency-rating" data-competency-id="${c.competency_id}">
                <option value="" ${!c.rating ? 'selected' : ''}>Select Rating</option>
                <option value="1" ${c.rating == 1 ? 'selected' : ''}>1 - Poor</option>
                <option value="2" ${c.rating == 2 ? 'selected' : ''}>2 - Below Average</option>
                <option value="3" ${c.rating == 3 ? 'selected' : ''}>3 - Average</option>
                <option value="4" ${c.rating == 4 ? 'selected' : ''}>4 - Good</option>
                <option value="5" ${c.rating == 5 ? 'selected' : ''}>5 - Excellent</option>
              </select>
            </div>
            <div class="col-md-9">
              <label class="form-label">Comments</label>
              <textarea class="form-control competency-comments" rows="2">${escapeHtml(c.comments ?? '')}</textarea>
            </div>
          </div>
        `;
        container.appendChild(div);
      });

      new bootstrap.Modal(document.getElementById('editModal')).show();
    }).catch(err=>{ console.error('edit load error', err); alert('Failed to load edit data'); });
});

// ---------- Save Edit ----------
elem('saveEditBtn').addEventListener('click', ()=>{
  if(!currentEmployeeId || !currentCycleId) return alert('No employee selected');
  const competencies = [];
  document.querySelectorAll('#editCompetencies > div').forEach(div => {
    const ratingSel = div.querySelector('.competency-rating');
    const commentsTa = div.querySelector('.competency-comments');
    if(ratingSel && commentsTa){
      competencies.push({
        competency_id: ratingSel.dataset.competencyId,
        rating: ratingSel.value,
        comments: commentsTa.value.trim()
      });
    }
  });

  if(competencies.length === 0) return alert('No competencies to save');

  fetch('update_employee_evaluation.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `employee_id=${encodeURIComponent(currentEmployeeId)}&cycle_id=${encodeURIComponent(currentCycleId)}&competencies=${encodeURIComponent(JSON.stringify(competencies))}`
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message || (data.success ? 'Saved successfully' : 'Failed to save'));
    if(data.success){
      bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
      loadCompetencies(currentCycleId); // Refresh the table
    }
  })
  .catch(err => {
    console.error('save error', err);
    alert('Failed to save');
  });
});

  // ---------- Events ----------
  // ---------- Init ----------
document.addEventListener('DOMContentLoaded', () => {
  loadCycles();
  loadDepartments();

  // ✅ Attach event listener here to ensure it's active AFTER DOM is ready
  elem('cycleSelect').addEventListener('change', (e) => {
    const cycleId = e.target.value;
    if (cycleId) {
      console.log('Cycle selected:', cycleId);
      loadCompetencies(cycleId);
    } else {
      elem('competenciesTbody').innerHTML =
        '<tr><td colspan="8" class="text-center text-muted">Select a review cycle to load data</td></tr>';
      elem('finalizeBtn').disabled = true;
    }
  });

  // ✅ Department filter refreshes the table for the same cycle
  elem('deptFilter').addEventListener('change', () => {
    if (currentCycleId) loadCompetencies(currentCycleId);
  });
});

</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
