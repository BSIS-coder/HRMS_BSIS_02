<?php 
session_start();

// Redirect to login if not authenticated
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
    <title>Performance Review Cycles</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">

   <style>
    /* Section title and container spacing */
    .section-title {
        color: var(--primary-color);
        margin-bottom: 30px;
        font-weight: 600;
    }

    .container {
        max-width: 85%;
        margin-left: 265px;
        padding-top: 5rem; /* replaces <br><br><br> */
    }

    /* Table styles */
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

    #cyclesTable tbody tr {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    #cyclesTable tbody tr:hover td {
        background-color: #b3e5fc !important; /* brighter blue */
        color: #000 !important; /* keep text readable */
    }

</style>

</head>
<body>

<div class="container-fluid">
    <!-- Navigation -->
    <?php include 'navigation.php'; ?>

    <div class="row">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="container">
            <h1 class="section-title">Performance Review Cycles</h1>

            <!-- Add Cycle Button -->
            <div class="mb-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCycleModal">
                    <i class="fas fa-plus"></i> Add Cycle
                </button>
            </div>

            <!-- Review Cycles Table -->
            <div class="table-responsive">
            <table class="table table-striped table-bordered" id="cyclesTable">
                <thead class="table-dark">
                    <tr>
                        <th>Cycle Names</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="cyclesTableBody">
                    <!-- Cycles will be loaded here via JavaScript -->
                </tbody>
            </table>
        </div>
            <!-- Pagination Controls -->
            <nav>
            <ul class="pagination" id="cyclesPagination">
                <!-- Pagination buttons will be added here dynamically -->
            </ul>
            </nav>



            <!-- Add Cycle Modal -->
            <div class="modal fade" id="addCycleModal" tabindex="-1" aria-labelledby="addCycleModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addCycleModalLabel">Add New Review Cycle</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addCycleForm">
                                <div class="mb-3">
                                    <label for="cycleName" class="form-label">Cycle Name</label>
                                    <input type="text" class="form-control" id="cycleName" name="cycleName" required>
                                </div>
                                <div class="mb-3">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                                </div>
                                <div class="mb-3">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required>
                                </div>
                                <button type="submit" style="display:none;"></button>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" form="addCycleForm" class="btn btn-primary">Add Cycle</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Cycle Modal -->
            <div class="modal fade" id="editCycleModal" tabindex="-1" aria-labelledby="editCycleModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editCycleModalLabel">Edit Review Cycle</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="editCycleForm">
                                <input type="hidden" id="editCycleId" name="cycle_id">
                                <div class="mb-3">
                                    <label for="editCycleName" class="form-label">Cycle Name</label>
                                    <input type="text" class="form-control" id="editCycleName" name="cycle_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editStartDate" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="editStartDate" name="start_date" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editEndDate" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="editEndDate" name="end_date" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editStatus" class="form-label">Status</label>
                                    <select class="form-select" id="editStatus" name="status" disabled>
                                        <option value="Upcoming">Upcoming</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>

                                <button type="submit" style="display:none;"></button>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" form="editCycleForm" class="btn btn-primary">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- End .container -->
    </div><!-- End .row -->
</div><!-- End .container-fluid -->



<!-- JavaScript Section -->
<script>
    // Add Cycle
    document.getElementById("addCycleForm").addEventListener("submit", function(event){
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);

        fetch("save_cycle.php", { method:"POST", body: formData })
            .then(res => res.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    alert(data.success ? "Cycle added successfully!" : "Error: " + data.message);
                    if (data.success){
                        bootstrap.Modal.getInstance(document.getElementById('addCycleModal')).hide();
                        form.reset();
                        loadCycles();
                    }
                } catch(e){
                    console.error("JSON parse error:", e);
                    alert("Server returned invalid JSON");
                }
            })
            .catch(err => console.error("Fetch error:", err));
    });

    
    let cyclesData = []; // Store all cycles
    let currentPage = 1;
    const rowsPerPage = 10;





    // Load Cycles with automatic status and clickable rows + pagination
    function loadCycles() {
    fetch("get_cycles.php")
        .then(res => res.json())
        .then(data => {
            if (data.success && data.cycles.length > 0) {
                cyclesData = data.cycles;
                currentPage = 1;
                displayCyclesPage(currentPage);
                setupPagination();

            } else {
                document.getElementById("cyclesTableBody").innerHTML =
                    `<tr><td colspan="5" class="text-center text-muted">No cycles found</td></tr>`;
                document.getElementById("cyclesPagination").innerHTML = "";
            }
        })
        .catch(err => console.error("Error loading cycles:", err));
    }

    function displayCyclesPage(page) {
    const tbody = document.getElementById("cyclesTableBody");
    tbody.innerHTML = "";

    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    const pageItems = cyclesData.slice(start, end);

    const today = new Date();
    pageItems.forEach(cycle => {
        const startDate = new Date(cycle.start_date);
        const endDate = new Date(cycle.end_date);
        let status = "";
        if (today < startDate) status = "Upcoming";
        else if (today > endDate) status = "Completed";
        else status = "In Progress";

        const row = document.createElement("tr");
        row.dataset.id = cycle.cycle_id;
        row.innerHTML = `
            <td>${cycle.cycle_name}</td>
            <td>${cycle.start_date}</td>
            <td>${cycle.end_date}</td>
            <td>${status}</td>
            <td>
                <button class="btn btn-sm btn-warning edit-btn" data-id="${cycle.cycle_id}"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger delete-btn" data-id="${cycle.cycle_id}"><i class="fas fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(row);
    });

    // Attach Edit buttons
    document.querySelectorAll(".edit-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            openEditModal(this.dataset.id);
        });
    });

    // Attach Delete buttons
    document.querySelectorAll(".delete-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            if (confirm("Are you sure you want to delete this cycle?")) {
                deleteCycle(this.dataset.id);
            }
        });
    });
    }

    function setupPagination() {
    const pagination = document.getElementById("cyclesPagination");
    pagination.innerHTML = "";

    const pageCount = Math.ceil(cyclesData.length / rowsPerPage);
    for (let i = 1; i <= pageCount; i++) {
        const li = document.createElement("li");
        li.className = `page-item ${i === currentPage ? "active" : ""}`;
        li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
        li.addEventListener("click", e => {
            e.preventDefault();
            currentPage = i;
            displayCyclesPage(currentPage);
            setupPagination();
        });
        pagination.appendChild(li);
    }
    }


// Delete cycle
function deleteCycle(id){
    fetch("delete_cycle.php", {
        method:"POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded"},
        body:`cycle_id=${encodeURIComponent(id)}`
    })
    .then(res => res.json())
    .then(data => { alert(data.message); loadCycles(); })
    .catch(err => console.error(err));
}

    // Open Edit Modal with dynamic status selection
    function openEditModal(id){
        const row = [...document.querySelectorAll("#cyclesTableBody tr")]
            .find(r => r.querySelector(".edit-btn").dataset.id == id);
        if(!row) return;

        const cycleName = row.cells[0].innerText;
        const startDateStr = row.cells[1].innerText;
        const endDateStr = row.cells[2].innerText;

        const today = new Date();
        const startDate = new Date(startDateStr);
        const endDate = new Date(endDateStr);

        let status = "";
        if(today < startDate){
            status = "Upcoming";
        } else if(today > endDate){
            status = "Completed";
        } else {
            status = "In Progress";
        }

        document.getElementById("editCycleId").value = id;
        document.getElementById("editCycleName").value = cycleName;
        document.getElementById("editStartDate").value = startDateStr;
        document.getElementById("editEndDate").value = endDateStr;
        document.getElementById("editStatus").value = status; // dynamically set status

        new bootstrap.Modal(document.getElementById("editCycleModal")).show();
    }

    // Edit Cycle Form Submission with auto-status
    document.getElementById("editCycleForm").addEventListener("submit", function(event){
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    // Automatically determine status based on dates
    const startDateStr = formData.get("start_date");
    const endDateStr = formData.get("end_date");
    const today = new Date();
    const startDate = new Date(startDateStr);
    const endDate = new Date(endDateStr);

    let status = "";
    if(today < startDate){
        status = "Upcoming";
    } else if(today > endDate){
        status = "Completed";
    } else {
        status = "In Progress";
    }

    formData.set("status", status); // overwrite status in FormData

    fetch("edit_cycle.php", { method:"POST", body: formData })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if(data.success){
                bootstrap.Modal.getInstance(document.getElementById("editCycleModal")).hide();
                loadCycles();
            }
        })
        .catch(err => console.error("Edit cycle error:", err));
});


    // Initial load
    document.addEventListener("DOMContentLoaded", loadCycles);
</script>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
