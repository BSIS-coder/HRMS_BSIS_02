<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'dp.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competency Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --primary-color: #E91E63;
            --primary-light: #F06292;
            --primary-dark: #C2185B;
            --primary-pale: #FCE4EC;
        }

        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }

        .container-fluid {
            padding: 0;
        }

        .row {
            margin-right: 0;
            margin-left: 0;
        }

        body {
            background: var(--primary-pale);
        }

        .main-content {
            background: var(--primary-pale);
            padding: 20px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
            margin: 0 3px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: linear-gradient(135deg, var(--primary-light) 0%, #e9ecef 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-dark);
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #F8BBD0;
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1976d2;
        }

        .no-competency {
            background: #f5f5f5;
            color: #999;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
        }

        .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-results {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .stats-cards {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            flex: 1;
            min-width: 200px;
        }

        .stat-card h3 {
            margin: 0;
            font-size: 2rem;
            color: var(--primary-color);
        }

        .stat-card p {
            margin: 0;
            color: #666;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box, .filter-select {
                max-width: none;
                width: 100%;
            }

            .stats-cards {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }

            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Competency Management</h2>
                <div class="content">
                    <!-- Statistics Cards -->
                    <div class="stats-cards">
                        <div class="stat-card">
                            <h3 id="totalCompetencies">0</h3>
                            <p>Total Competencies</p>
                        </div>
                        <div class="stat-card">
                            <h3 id="totalRoles">0</h3>
                            <p>Linked Job Roles</p>
                        </div>
                    </div>

                    <!-- Category Statistics Cards -->
                    <div class="stats-cards" id="categoryStats">
                        <div class="stat-card" style="border-left: 4px solid #E91E63;">
                            <h3 id="coreCount">0</h3>
                            <p>Core</p>
                        </div>
                        <div class="stat-card" style="border-left: 4px solid #2196F3;">
                            <h3 id="technicalCount">0</h3>
                            <p>Technical</p>
                        </div>
                        <div class="stat-card" style="border-left: 4px solid #4CAF50;">
                            <h3 id="behavioralCount">0</h3>
                            <p>Behavioral</p>
                        </div>
                        <div class="stat-card" style="border-left: 4px solid #FF9800;">
                            <h3 id="administrativeCount">0</h3>
                            <p>Administrative</p>
                        </div>
                    </div>

                    <!-- Category Chart -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="chart-container" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                                <h5 style="color: var(--primary-dark); margin-bottom: 15px;">Competencies by Category</h5>
                                <canvas id="categoryChart" height="200"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                                <h5 style="color: var(--primary-dark); margin-bottom: 15px;">Top Job Roles with Most Competencies</h5>
                                <canvas id="roleChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search competencies by name or description...">
                        </div>
                        <select id="roleFilter" class="filter-select">
                            <option value="">All Job Roles</option>
                        </select>
                        <select id="categoryFilter" class="filter-select">
                            <option value="">All Categories</option>
                            <option value="Core">Core</option>
                            <option value="Technical">Technical</option>
                            <option value="Behavioral">Behavioral</option>
                            <option value="Administrative">Administrative</option>
                        </select>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            <i class="fas fa-plus"></i> Add New Competency
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="competencyTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Competency Name</th>
                                    <th>Description</th>
                                    <th>Job Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="competencyTableBody">
                                <!-- Loaded dynamically -->
                            </tbody>
                        </table>
                        
                        <div class="no-results" id="noResults" style="display: none;">
                            <i class="fas fa-star"></i>
                            <h3>No competencies found</h3>
                            <p>Start by adding your first competency.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Competency Modal -->
    <div id="competencyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Competency</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="competencyForm">
                    <input type="hidden" id="competency_id" name="competency_id">
                    
                    <div class="form-group">
                        <label for="competency_name">Competency Name *</label>
                        <input type="text" id="competency_name" name="competency_name" class="form-control" required placeholder="Enter competency name">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" placeholder="Describe what this competency measures..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="job_role_id">Job Role (Optional)</label>
                        <select id="job_role_id" name="job_role_id" class="form-select">
                            <option value="">-- No Specific Role --</option>
                            <!-- Loaded dynamically -->
                        </select>
                        <small class="text-muted">Leave empty to make this competency available to all roles</small>
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-select">
                            <option value="Core">Core</option>
                            <option value="Technical" selected>Technical</option>
                            <option value="Behavioral">Behavioral</option>
                            <option value="Administrative">Administrative</option>
                        </select>
                        <small class="text-muted">Select the category for this competency</small>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Competency</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let competenciesData = [];
        let jobRolesData = [];

        // Load job roles for filter and modal
        function loadJobRoles() {
            fetch('get_roles.php')
                .then(res => res.json())
                .then(data => {
                    jobRolesData = data;
                    
                    // Populate filter dropdown
                    const filterSelect = document.getElementById('roleFilter');
                    // Clear existing options except the first one (All Job Roles)
                    filterSelect.innerHTML = '<option value="">All Job Roles</option>';
                    data.forEach(role => {
                        const option = document.createElement('option');
                        option.value = role.job_role_id;
                        option.textContent = role.title;
                        filterSelect.appendChild(option);
                    });

                    // Populate modal dropdown
                    const modalSelect = document.getElementById('job_role_id');
                    // Clear existing options except the first one (No Specific Role)
                    modalSelect.innerHTML = '<option value="">-- No Specific Role --</option>';
                    data.forEach(role => {
                        const option = document.createElement('option');
                        option.value = role.job_role_id;
                        option.textContent = role.title;
                        modalSelect.appendChild(option);
                    });

                    // Update stats
                    document.getElementById('totalRoles').textContent = data.length;
                })
                .catch(err => console.error("Failed to load job roles:", err));
        }

        // Load all competencies
        function loadCompetencies() {
            fetch('get_competencies.php')
                .then(res => res.json())
                .then(data => {
                    competenciesData = data;
                    renderTable(data);
                    updateAllStats(data);
                })
                .catch(err => console.error("Failed to load competencies:", err));
        }

        // Update all statistics including category breakdown and charts
        function updateAllStats(data) {
            document.getElementById('totalCompetencies').textContent = data.length;

            // Category counts
            const coreCount = data.filter(c => c.category === 'Core').length;
            const technicalCount = data.filter(c => c.category === 'Technical').length;
            const behavioralCount = data.filter(c => c.category === 'Behavioral').length;
            const adminCount = data.filter(c => c.category === 'Administrative').length;

            document.getElementById('coreCount').textContent = coreCount;
            document.getElementById('technicalCount').textContent = technicalCount;
            document.getElementById('behavioralCount').textContent = behavioralCount;
            document.getElementById('administrativeCount').textContent = adminCount;

            // Update charts
            updateCategoryChart([coreCount, technicalCount, behavioralCount, adminCount]);
            updateRoleChart(data);
        }

        // Render table
        function renderTable(data) {
            const tbody = document.getElementById('competencyTableBody');
            const noResults = document.getElementById('noResults');
            
            if (!data || data.length === 0) {
                tbody.innerHTML = '';
                noResults.style.display = 'block';
                return;
            }

            noResults.style.display = 'none';
            tbody.innerHTML = data.map(comp => `
                <tr>
                    <td>${comp.competency_id}</td>
                    <td><strong>${escapeHtml(comp.name)}</strong></td>
                    <td>${comp.description ? escapeHtml(comp.description.substring(0, 100)) + (comp.description.length > 100 ? '...' : '') : '<span class="text-muted">No description</span>'}</td>
                    <td>
                        ${comp.role ? 
                            `<span class="role-badge">${escapeHtml(comp.role)}</span>` : 
                            '<span class="role-badge no-competency">All Roles</span>'}
                    </td>
                    <td>
                        <button class="btn btn-warning btn-small" onclick="editCompetency(${comp.competency_id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger btn-small" onclick="deleteCompetency(${comp.competency_id}, '${escapeHtml(comp.name)}')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Filter competencies
        function filterCompetencies() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;

            let filtered = competenciesData;

            // Filter by search term
            if (searchTerm) {
                filtered = filtered.filter(comp => 
                    comp.name.toLowerCase().includes(searchTerm) || 
                    (comp.description && comp.description.toLowerCase().includes(searchTerm))
                );
            }

            // Filter by job role
            if (roleFilter) {
                filtered = filtered.filter(comp => 
                    comp.job_role_id == roleFilter || !comp.job_role_id
                );
            }

            renderTable(filtered);
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', filterCompetencies);
        document.getElementById('roleFilter').addEventListener('change', filterCompetencies);

        // Modal functions
        function openModal(mode, competencyId = null) {
            const modal = document.getElementById('competencyModal');
            const title = document.getElementById('modalTitle');
            const form = document.getElementById('competencyForm');

            if (mode === 'add') {
                title.textContent = 'Add New Competency';
                form.reset();
                document.getElementById('competency_id').value = '';
            } else if (mode === 'edit' && competencyId) {
                title.textContent = 'Edit Competency';
                populateEditForm(competencyId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('competencyModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Populate edit form
        function populateEditForm(competencyId) {
            fetch(`get_competencies.php?id=${competencyId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.competency_id) {
                        document.getElementById('competency_id').value = data.competency_id;
                        document.getElementById('competency_name').value = data.name || '';
                        document.getElementById('description').value = data.description || '';
                        document.getElementById('job_role_id').value = data.job_role_id || '';
                    }
                })
                .catch(err => {
                    console.error("Failed to load competency:", err);
                    alert("Failed to load competency data");
                });
        }

        // Edit competency
        function editCompetency(competencyId) {
            openModal('edit', competencyId);
        }

        // Delete competency
        function deleteCompetency(competencyId, name) {
            if (!confirm(`Are you sure you want to delete the competency "${name}"? This will also remove it from all employee evaluations.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('id', competencyId);

            fetch('delete_competency.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Competency deleted successfully!');
                    loadCompetencies();
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete competency'));
                }
            })
            .catch(err => {
                console.error("Delete error:", err);
                alert("Failed to delete competency");
            });
        }

        // Form submission
        document.getElementById('competencyForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const competencyId = document.getElementById('competency_id').value;
            
            // Determine action
            const action = competencyId ? 'update' : 'add';
            formData.append('action', action);

            // Choose endpoint
            const endpoint = competencyId ? 'update_competency.php' : 'add_competency.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(competencyId ? 'Competency updated successfully!' : 'Competency added successfully!');
                    closeModal();
                    loadCompetencies();
                } else {
                    alert('Error: ' + (data.message || 'Failed to save competency'));
                }
            })
            .catch(err => {
                console.error("Save error:", err);
                alert("Failed to save competency");
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('competencyModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Utility function
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadJobRoles();
            loadCompetencies();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Chart instances
        let categoryChartInstance = null;
        let roleChartInstance = null;

        // Category Pie Chart
        function updateCategoryChart(data) {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            
            if (categoryChartInstance) {
                categoryChartInstance.destroy();
            }

            categoryChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Core', 'Technical', 'Behavioral', 'Administrative'],
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            '#E91E63',
                            '#2196F3',
                            '#4CAF50',
                            '#FF9800'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }

        // Role Bar Chart - Top 10 roles with most competencies
        function updateRoleChart(data) {
            const ctx = document.getElementById('roleChart').getContext('2d');
            
            // Count competencies per role
            const roleCounts = {};
            data.forEach(comp => {
                const roleName = comp.role || 'Unassigned';
                roleCounts[roleName] = (roleCounts[roleName] || 0) + 1;
            });

            // Sort and get top 10
            const sortedRoles = Object.entries(roleCounts)
                .sort((a, b) => b[1] - a[1])
                .slice(0, 10);

            const labels = sortedRoles.map(item => item[0]);
            const counts = sortedRoles.map(item => item[1]);

            if (roleChartInstance) {
                roleChartInstance.destroy();
            }

            roleChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Competencies',
                        data: counts,
                        backgroundColor: [
                            '#E91E63', '#F06292', '#2196F3', '#4CAF50', 
                            '#FF9800', '#9C27B0', '#00BCD4', '#795548',
                            '#607D8B', '#E91E63'
                        ],
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Filter competencies with category
        function filterCompetencies() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const categoryFilter = document.getElementById('categoryFilter').value;

            let filtered = competenciesData;

            // Filter by search term
            if (searchTerm) {
                filtered = filtered.filter(comp => 
                    comp.name.toLowerCase().includes(searchTerm) || 
                    (comp.description && comp.description.toLowerCase().includes(searchTerm))
                );
            }

            // Filter by job role
            if (roleFilter) {
                filtered = filtered.filter(comp => 
                    comp.job_role_id == roleFilter || !comp.job_role_id
                );
            }

            // Filter by category
            if (categoryFilter) {
                filtered = filtered.filter(comp => 
                    comp.category === categoryFilter
                );
            }

            renderTable(filtered);
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', filterCompetencies);
        document.getElementById('roleFilter').addEventListener('change', filterCompetencies);
        document.getElementById('categoryFilter').addEventListener('change', filterCompetencies);
    </script>
</body>
</html>
