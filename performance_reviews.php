<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'dp.php';

/**
 * Generate HTML for review cycle dropdown
 *
 * @param PDO $conn Database connection
 * @param string $id HTML id attribute for the select element
 * @param string $name HTML name attribute for the select element
 * @param string $class CSS class for the select element
 * @param string $defaultOption Text for the default option
 * @param string $selectedValue Value to be selected by default
 * @return string HTML string for the dropdown
 */
function generateReviewCycleDropdown($conn, $id = 'cycleFilter', $name = 'cycle', $class = 'form-select', $defaultOption = 'All Cycles', $selectedValue = '') {
    $cycles = [];
    try {
        $stmt = $conn->query("SELECT cycle_id, cycle_name FROM performance_review_cycles ORDER BY start_date DESC");
        $cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cycles = [];
    }

    $html = '<select id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '" class="' . htmlspecialchars($class) . '">';
    $html .= '<option value="">' . htmlspecialchars($defaultOption) . '</option>';

    foreach ($cycles as $cycle) {
        $selected = ($selectedValue == $cycle['cycle_id']) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($cycle['cycle_id']) . '"' . $selected . '>';
        $html .= htmlspecialchars($cycle['cycle_name']);
        $html .= '</option>';
    }

    $html .= '</select>';
    return $html;
}

// Fetch all review cycles for the filter dropdown
$cycles = [];
try {
    $stmt = $conn->query("SELECT cycle_id, cycle_name FROM performance_review_cycles ORDER BY start_date DESC");
    $cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cycles = [];
}

// Fetch all departments for the filter dropdown
$departments = [];
try {
    $stmt = $conn->query("SELECT DISTINCT department FROM job_roles WHERE department IS NOT NULL AND department != '' ORDER BY department");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Reviews - HRMS</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">

    <style>
        :root {
            --primary-color: #E91E63;
            --primary-dark: #C2185B;
            --primary-light: #F06292;
            --primary-pale: #FCE4EC;
        }

        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }

        .container {
            max-width: 90%;
            margin-left: 265px;
            padding-top: 5rem;
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card .stat-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stats-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }

        .stats-card .stat-label {
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filter-section .form-label {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            font-weight: 600;
        }

        #reviewsTable tbody tr {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        #reviewsTable tbody tr:hover td {
            background-color: var(--primary-pale) !important;
        }

        /* Rating Badge */
        .rating-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .rating-excellent {
            background-color: #d4edda;
            color: #155724;
        }

        .rating-good {
            background-color: #cce5ff;
            color: #004085;
        }

        .rating-average {
            background-color: #fff3cd;
            color: #856404;
        }

        .rating-poor {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Status Badge */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-draft {
            background-color: #e2e3e5;
            color: #383d41;
        }

        /* Action Buttons */
        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
            border-radius: 5px;
        }

        /* Loading Spinner */
        .loading-spinner {
            text-align: center;
            padding: 40px;
        }

        .spinner-border {
            color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }

        /* Pagination */
        .pagination-container {
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .page-link {
            color: var(--primary-color);
        }

        .page-link:hover {
            color: var(--primary-dark);
        }

        /* Search Box */
        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .search-box input {
            padding-left: 35px;
        }

        /* Modal Styles */
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
        }

        .modal-header .btn-close {
            filter: invert(1);
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
            <h1 class="section-title">
                <i class="fas fa-comments"></i> Performance Reviews
            </h1>

            <!-- Stats Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                        <div class="stat-value" id="totalReviews">0</div>
                        <div class="stat-label">Total Reviews</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value" id="completedReviews">0</div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon"><i class="fas fa-star"></i></div>
                        <div class="stat-value" id="avgRating">0.0</div>
                        <div class="stat-label">Avg Rating</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-value" id="evaluatedEmployees">0</div>
                        <div class="stat-label">Employees Evaluated</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row">
                    <div class="col-md-3">
                        <label for="cycleFilter" class="form-label">Review Cycle</label>
                        <select id="cycleFilter" class="form-select">
                            <option value="">All Cycles</option>
                            <?php foreach ($cycles as $cycle): ?>
                                <option value="<?php echo htmlspecialchars($cycle['cycle_id']); ?>">
                                    <?php echo htmlspecialchars($cycle['cycle_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="departmentFilter" class="form-label">Department</label>
                        <select id="departmentFilter" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="statusFilter" class="form-label">Status</label>
                        <select id="statusFilter" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Finalized">Finalized</option>
                            <option value="Draft">Draft</option>
                            <option value="In Progress">In Progress</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="searchInput" class="form-label">Search</label>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search employee name...">
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <button type="button" class="btn btn-primary" onclick="applyFilters()">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportToCSV()">
                            <i class="fas fa-file-export"></i> Export to CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Reviews Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="reviewsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Review Cycle</th>
                            <th>Competencies Assessed</th>
                            <th>Overall Rating</th>
                            <th>Review Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reviewsTableBody">
                        <tr>
                            <td colspan="9" class="loading-spinner">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-container">
                <nav>
                    <ul class="pagination" id="reviewsPagination">
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- View Review Modal -->
<div class="modal fade" id="viewReviewModal" tabindex="-1" aria-labelledby="viewReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewReviewModalLabel">
                    <i class="fas fa-clipboard-check"></i> Performance Review Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="reviewModalBody">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Section -->
<script>
    // Global variables
    let allReviews = [];
    let filteredReviews = [];
    let currentPage = 1;
    const rowsPerPage = 10;

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadReviews();
    });

    // Load reviews from database
    function loadReviews() {
        showLoading(true);
        
        // Fetch all reviews with their details
        fetch('get_all_performance_reviews.php')
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    allReviews = data.reviews || [];
                    filteredReviews = [...allReviews];
                    updateStats();
                    displayReviewsPage(currentPage);
                    setupPagination();
                } else {
                    showError(data.message || 'Failed to load reviews');
                }
                showLoading(false);
            })
            .catch(err => {
                console.error('Error loading reviews:', err);
                // If API doesn't exist, load sample data for demo
                loadSampleData();
            });
    }

    // Load sample data for demo purposes
    function loadSampleData() {
        allReviews = [];
        filteredReviews = [];
        updateStats();
        displayReviewsPage(currentPage);
        setupPagination();
        showLoading(false);
    }

    // Update statistics cards
    function updateStats() {
        document.getElementById('totalReviews').textContent = allReviews.length;
        
        const completed = allReviews.filter(r => r.status === 'Finalized').length;
        document.getElementById('completedReviews').textContent = completed;
        
        const ratedReviews = allReviews.filter(r => r.avg_rating > 0);
        const avgRating = ratedReviews.length > 0 
            ? (ratedReviews.reduce((sum, r) => sum + parseFloat(r.avg_rating), 0) / ratedReviews.length).toFixed(1)
            : '0.0';
        document.getElementById('avgRating').textContent = avgRating;
        
        const uniqueEmployees = [...new Set(allReviews.map(r => r.employee_id))].length;
        document.getElementById('evaluatedEmployees').textContent = uniqueEmployees;
    }

    // Display reviews for current page
    function displayReviewsPage(page) {
        const tbody = document.getElementById('reviewsTableBody');
        tbody.innerHTML = '';

        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        const pageItems = filteredReviews.slice(start, end);

        if (pageItems.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h5>No Reviews Found</h5>
                            <p>There are no performance reviews matching your criteria.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        pageItems.forEach(review => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${escapeHtml(review.employee_name || 'N/A')}</strong></td>
                <td>${escapeHtml(review.department || 'N/A')}</td>
                <td>${escapeHtml(review.role || 'N/A')}</td>
                <td>${escapeHtml(review.cycle_name || 'N/A')}</td>
                <td>${review.competencies_assessed || 0}</td>
                <td>${renderRatingBadge(review.avg_rating)}</td>
                <td>${formatDate(review.last_assessment_date)}</td>
                <td>${renderStatusBadge(review.status)}</td>
                <td>
                    <button class="btn btn-sm btn-primary btn-action" onclick="viewReview(${review.employee_id}, ${review.cycle_id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-success btn-action" onclick="exportReview(${review.employee_id}, ${review.cycle_id})" title="Export">
                        <i class="fas fa-download"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    // Setup pagination
    function setupPagination() {
        const pagination = document.getElementById('reviewsPagination');
        pagination.innerHTML = '';

        if (filteredReviews.length === 0) return;

        const pageCount = Math.ceil(filteredReviews.length / rowsPerPage);
        
        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>`;
        pagination.appendChild(prevLi);

        // Page numbers
        for (let i = 1; i <= pageCount; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${i === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" onclick="changePage(${i})">${i}</a>`;
            pagination.appendChild(li);
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === pageCount ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>`;
        pagination.appendChild(nextLi);
    }

    // Change page
    function changePage(page) {
        currentPage = page;
        displayReviewsPage(currentPage);
        setupPagination();
    }

    // Apply filters
    function applyFilters() {
        const cycleId = document.getElementById('cycleFilter').value;
        const department = document.getElementById('departmentFilter').value;
        const status = document.getElementById('statusFilter').value;
        const search = document.getElementById('searchInput').value.toLowerCase();

        filteredReviews = allReviews.filter(review => {
            const matchCycle = !cycleId || review.cycle_id == cycleId;
            const matchDepartment = !department || review.department === department;
            const matchStatus = !status || review.status === status;
            const matchSearch = !search || 
                (review.employee_name && review.employee_name.toLowerCase().includes(search));
            
            return matchCycle && matchDepartment && matchStatus && matchSearch;
        });

        currentPage = 1;
        displayReviewsPage(currentPage);
        setupPagination();
    }

    // Reset filters
    function resetFilters() {
        document.getElementById('cycleFilter').value = '';
        document.getElementById('departmentFilter').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('searchInput').value = '';
        
        filteredReviews = [...allReviews];
        currentPage = 1;
        displayReviewsPage(currentPage);
        setupPagination();
    }

    // View review details
    function viewReview(employeeId, cycleId) {
        const review = allReviews.find(r => r.employee_id == employeeId && r.cycle_id == cycleId);
        
        if (!review) {
            alert('Review not found');
            return;
        }

        const modalBody = document.getElementById('reviewModalBody');
        modalBody.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted">Employee Information</h6>
                    <p><strong>Name:</strong> ${escapeHtml(review.employee_name || 'N/A')}</p>
                    <p><strong>Department:</strong> ${escapeHtml(review.department || 'N/A')}</p>
                    <p><strong>Role:</strong> ${escapeHtml(review.role || 'N/A')}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted">Review Information</h6>
                    <p><strong>Cycle:</strong> ${escapeHtml(review.cycle_name || 'N/A')}</p>
                    <p><strong>Status:</strong> ${renderStatusBadge(review.status)}</p>
                    <p><strong>Review Date:</strong> ${formatDate(review.last_assessment_date)}</p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h6 class="text-muted">Performance Summary</h6>
                    <p><strong>Overall Rating:</strong> ${renderRatingBadge(review.avg_rating)}</p>
                    <p><strong>Competencies Assessed:</strong> ${review.competencies_assessed || 0}</p>
                </div>
            </div>
            ${review.manager_comments ? `
            <hr>
            <div class="row">
                <div class="col-md-12">
                    <h6 class="text-muted">Manager Comments</h6>
                    <p>${escapeHtml(review.manager_comments)}</p>
                </div>
            </div>
            ` : ''}
        `;

        const modal = new bootstrap.Modal(document.getElementById('viewReviewModal'));
        modal.show();
    }

    // Export single review
    function exportReview(employeeId, cycleId) {
        const review = allReviews.find(r => r.employee_id == employeeId && r.cycle_id == cycleId);
        
        if (!review) {
            alert('Review not found');
            return;
        }

        const csvContent = generateCSV([review]);
        downloadCSV(csvContent, `performance_review_${review.employee_name}_${review.cycle_name}.csv`);
    }

    // Export all to CSV
    function exportToCSV() {
        if (filteredReviews.length === 0) {
            alert('No data to export');
            return;
        }

        const csvContent = generateCSV(filteredReviews);
        downloadCSV(csvContent, `performance_reviews_export_${new Date().toISOString().split('T')[0]}.csv`);
    }

    // Generate CSV content
    function generateCSV(reviews) {
        const headers = ['Employee Name', 'Department', 'Role', 'Review Cycle', 'Competencies Assessed', 'Overall Rating', 'Review Date', 'Status'];
        const rows = reviews.map(r => [
            r.employee_name || '',
            r.department || '',
            r.role || '',
            r.cycle_name || '',
            r.competencies_assessed || 0,
            r.avg_rating || 0,
            r.last_assessment_date || '',
            r.status || ''
        ]);

        return [headers, ...rows].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
    }

    // Download CSV file
    function downloadCSV(content, filename) {
        const blob = new Blob([content], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // Helper functions
    function renderRatingBadge(rating) {
        if (!rating || rating === 0) return '<span class="rating-badge rating-poor">Not Rated</span>';
        
        rating = parseFloat(rating);
        if (rating >= 4.5) return `<span class="rating-badge rating-excellent">${rating.toFixed(1)} - Excellent</span>`;
        if (rating >= 3.5) return `<span class="rating-badge rating-good">${rating.toFixed(1)} - Good</span>`;
        if (rating >= 2.5) return `<span class="rating-badge rating-average">${rating.toFixed(1)} - Average</span>`;
        return `<span class="rating-badge rating-poor">${rating.toFixed(1)} - Poor</span>`;
    }

    function renderStatusBadge(status) {
        const statusClass = {
            'Finalized': 'status-completed',
            'Completed': 'status-completed',
            'In Progress': 'status-pending',
            'Draft': 'status-draft'
        };
        return `<span class="status-badge ${statusClass[status] || 'status-draft'}">${status || 'N/A'}</span>`;
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showLoading(show) {
        const tbody = document.getElementById('reviewsTableBody');
        if (show) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="loading-spinner">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                </tr>
            `;
        }
    }

    function showError(message) {
        const tbody = document.getElementById('reviewsTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="9">
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h5>Error</h5>
                        <p>${escapeHtml(message)}</p>
                    </div>
                </td>
            </tr>
        `;
    }
</script>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
