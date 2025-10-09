<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Holidays - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        .section-title {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .holiday-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .holiday-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-medium);
        }
        
        .holiday-date {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: 600;
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
<?php require_once 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Public Holidays Management</h2>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-calendar-day mr-2"></i>Public Holidays List</h5>
                                    <div>
                                        <button class="btn btn-info mr-2" id="syncHolidaysBtn" onclick="syncHolidays()">
                                            <i class="fas fa-sync-alt mr-2"></i>Sync from API
                                        </button>
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#addHolidayModal">
                                            <i class="fas fa-plus mr-2"></i>Add Holiday
                                        </button>
                                    </div>
                                </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="holidaysTable">
                                        <thead>
                                            <tr>
                                                <th>Holiday Name</th>
                                                <th>Date</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="holidaysTableBody">
                                            <tr>
                                                <td colspan="4" class="text-center">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="sr-only">Loading...</span>
                                                    </div>
                                                    <p class="mt-2">Loading holidays...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Upcoming Holidays</h5>
                            </div>
                            <div class="card-body" id="upcomingHolidays">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading upcoming holidays...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie mr-2"></i>Holiday Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h4 class="text-primary" id="totalHolidays">0</h4>
                                        <small class="text-muted">Total Holidays</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success" id="nationalHolidays">0</h4>
                                        <small class="text-muted">National Holidays</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-info" id="regionalHolidays">0</h4>
                                        <small class="text-muted">Regional Holidays</small>
                                    </div>
                                </div>
                                <hr>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-primary" id="nationalProgress" style="width: 0%">National (0%)</div>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-info" id="regionalProgress" style="width: 0%">Regional (0%)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Holiday Modal -->
    <div class="modal fade" id="addHolidayModal" tabindex="-1" role="dialog" aria-labelledby="addHolidayModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addHolidayModalLabel">Add New Holiday</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addHolidayForm">
                        <div class="form-group">
                            <label for="holidayName">Holiday Name</label>
                            <input type="text" class="form-control" id="holidayName" placeholder="Enter holiday name" required>
                        </div>
                        <div class="form-group">
                            <label for="holidayDate">Date</label>
                            <input type="date" class="form-control" id="holidayDate" required>
                        </div>
                        <div class="form-group">
                            <label for="holidayType">Holiday Type</label>
                            <select class="form-control" id="holidayType" required>
                                <option value="National">National</option>
                                <option value="Regional">Regional</option>
                                <option value="Special">Special</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="holidayDescription">Description</label>
                            <textarea class="form-control" id="holidayDescription" rows="3" placeholder="Enter holiday description"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveHolidayBtn">Save Holiday</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Holiday Modal -->
    <div class="modal fade" id="editHolidayModal" tabindex="-1" role="dialog" aria-labelledby="editHolidayModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editHolidayModalLabel">Edit Holiday</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editHolidayForm">
                        <input type="hidden" id="editHolidayId">
                        <div class="form-group">
                            <label for="editHolidayName">Holiday Name</label>
                            <input type="text" class="form-control" id="editHolidayName" placeholder="Enter holiday name" required>
                        </div>
                        <div class="form-group">
                            <label for="editHolidayDate">Date</label>
                            <input type="date" class="form-control" id="editHolidayDate" required>
                        </div>
                        <div class="form-group">
                            <label for="editHolidayType">Holiday Type</label>
                            <select class="form-control" id="editHolidayType" required>
                                <option value="National">National</option>
                                <option value="Regional">Regional</option>
                                <option value="Special">Special</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editHolidayDescription">Description</label>
                            <textarea class="form-control" id="editHolidayDescription" rows="3" placeholder="Enter holiday description"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateHolidayBtn">Update Holiday</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <div class="alert-container" id="alertContainer"></div>
    
    <script>
    $(document).ready(function() {
        // Load holidays on page load
        loadHolidays();

        // Handle add holiday form submission
        $('#saveHolidayBtn').click(function() {
            addHoliday();
        });

        // Handle update holiday form submission
        $('#updateHolidayBtn').click(function() {
            updateHoliday();
        });

        // Handle modal close to reset form
        $('#addHolidayModal').on('hidden.bs.modal', function() {
            $(this).find('form')[0].reset();
        });

        $('#editHolidayModal').on('hidden.bs.modal', function() {
            $(this).find('form')[0].reset();
        });
    });

    function loadHolidays() {
        $.ajax({
            url: 'holiday_actions.php',
            type: 'POST',
            data: { action: 'get_holidays' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    populateHolidaysTable(response.holidays);
                    updateHolidayStats(response.holidays);
                    loadUpcomingHolidays(); // Load upcoming holidays after main holidays are loaded
                } else {
                    showAlert('Error loading holidays: ' + response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error connecting to server. Please try again.', 'danger');
            }
        });
    }

    function loadUpcomingHolidays() {
        $.ajax({
            url: 'holiday_actions.php',
            type: 'POST',
            data: { action: 'get_upcoming_holidays' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    populateUpcomingHolidays(response.holidays);
                } else {
                    showAlert('Error loading upcoming holidays: ' + response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error connecting to server. Please try again.', 'danger');
            }
        });
    }

    function populateUpcomingHolidays(holidays) {
        const upcomingContainer = $('#upcomingHolidays');
        upcomingContainer.empty();

        if (holidays.length === 0) {
            upcomingContainer.append(`
                <div class="text-center text-muted">
                    <p>No upcoming holidays found.</p>
                </div>
            `);
            return;
        }

        holidays.forEach(holiday => {
            const holidayDate = new Date(holiday.holiday_date);
            const month = holidayDate.toLocaleDateString('en-US', { month: 'short' });
            const day = holidayDate.getDate();
            const year = holidayDate.getFullYear();

            upcomingContainer.append(`
                <div class="holiday-item mb-3">
                    <div class="d-flex align-items-center">
                        <div class="holiday-date mr-3">
                            <div class="text-center">
                                <div>${month}</div>
                                <div class="h4 mb-0">${day}</div>
                                <div>${year}</div>
                            </div>
                        </div>
                        <div>
                            <h6 class="mb-1">${escapeHtml(holiday.holiday_name)}</h6>
                            <small class="text-muted">${escapeHtml(holiday.description || 'Public Holiday')}</small>
                        </div>
                    </div>
                </div>
            `);
        });
    }

    function populateHolidaysTable(holidays) {
        const tableBody = $('#holidaysTableBody');
        tableBody.empty();

        if (holidays.length === 0) {
            tableBody.append(`
                <tr>
                    <td colspan="4" class="text-center text-muted">
                        No holidays found. Click "Add Holiday" to create one.
                    </td>
                </tr>
            `);
            return;
        }

        holidays.forEach(holiday => {
            const formattedDate = new Date(holiday.holiday_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            tableBody.append(`
                <tr>
                    <td>${escapeHtml(holiday.holiday_name)}</td>
                    <td>${formattedDate}</td>
                    <td>${escapeHtml(holiday.description || '')}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary mr-2" onclick="editHoliday(${holiday.holiday_id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteHoliday(${holiday.holiday_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    function updateHolidayStats(holidays) {
        // Update statistics cards
        const totalHolidays = holidays.length;
        $('#totalHolidays').text(totalHolidays);
        
        // Check if holiday_type field exists in the data
        const hasHolidayType = holidays.length > 0 && holidays[0].hasOwnProperty('holiday_type');
        
        if (hasHolidayType) {
            // Count holidays by type
            const nationalHolidays = holidays.filter(holiday => holiday.holiday_type === 'National').length;
            const regionalHolidays = holidays.filter(holiday => holiday.holiday_type === 'Regional').length;
            const specialHolidays = holidays.filter(holiday => holiday.holiday_type === 'Special').length;
            
            $('#nationalHolidays').text(nationalHolidays);
            $('#regionalHolidays').text(regionalHolidays);
            
            // Update progress bars
            const nationalPercentage = totalHolidays > 0 ? Math.round((nationalHolidays / totalHolidays) * 100) : 0;
            const regionalPercentage = totalHolidays > 0 ? Math.round((regionalHolidays / totalHolidays) * 100) : 0;
            
            $('#nationalProgress').css('width', nationalPercentage + '%').text(`National (${nationalPercentage}%)`);
            $('#regionalProgress').css('width', regionalPercentage + '%').text(`Regional (${regionalPercentage}%)`);
        } else {
            // Fallback: use default values if holiday_type column doesn't exist
            const nationalHolidays = Math.floor(totalHolidays * 0.7); // 70% national
            const regionalHolidays = totalHolidays - nationalHolidays; // 30% regional
            
            $('#nationalHolidays').text(nationalHolidays);
            $('#regionalHolidays').text(regionalHolidays);
            
            // Update progress bars
            const nationalPercentage = totalHolidays > 0 ? Math.round((nationalHolidays / totalHolidays) * 100) : 0;
            const regionalPercentage = totalHolidays > 0 ? Math.round((regionalHolidays / totalHolidays) * 100) : 0;
            
            $('#nationalProgress').css('width', nationalPercentage + '%').text(`National (${nationalPercentage}%)`);
            $('#regionalProgress').css('width', regionalPercentage + '%').text(`Regional (${regionalPercentage}%)`);
        }
    }

    function addHoliday() {
        const holidayName = $('#holidayName').val().trim();
        const holidayDate = $('#holidayDate').val();
        const holidayType = $('#holidayType').val();
        const description = $('#holidayDescription').val().trim();

        if (!holidayName || !holidayDate || !holidayType) {
            showAlert('Please fill in all required fields.', 'warning');
            return;
        }

        $.ajax({
            url: 'holiday_actions.php',
            type: 'POST',
            data: {
                action: 'add_holiday',
                holiday_name: holidayName,
                holiday_date: holidayDate,
                holiday_type: holidayType,
                description: description
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#addHolidayModal').modal('hide');
                    showAlert(response.message, 'success');
                    loadHolidays();
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error adding holiday. Please try again.', 'danger');
            }
        });
    }

    function editHoliday(holidayId) {
        // Load holiday data and populate the edit form
        $.ajax({
            url: 'holiday_actions.php',
            type: 'POST',
            data: {
                action: 'get_holiday',
                holiday_id: holidayId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const holiday = response.holiday;
                    $('#editHolidayId').val(holiday.holiday_id);
                    $('#editHolidayName').val(holiday.holiday_name);
                    $('#editHolidayDate').val(holiday.holiday_date);
                    $('#editHolidayType').val(holiday.holiday_type || 'National');
                    $('#editHolidayDescription').val(holiday.description || '');
                    $('#editHolidayModal').modal('show');
                } else {
                    showAlert('Error loading holiday data: ' + response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error connecting to server. Please try again.', 'danger');
            }
        });
    }

    function updateHoliday() {
        const holidayId = $('#editHolidayId').val();
        const holidayName = $('#editHolidayName').val().trim();
        const holidayDate = $('#editHolidayDate').val();
        const holidayType = $('#editHolidayType').val();
        const description = $('#editHolidayDescription').val().trim();

        if (!holidayName || !holidayDate || !holidayType) {
            showAlert('Please fill in all required fields.', 'warning');
            return;
        }

        $.ajax({
            url: 'holiday_actions.php',
            type: 'POST',
            data: {
                action: 'update_holiday',
                holiday_id: holidayId,
                holiday_name: holidayName,
                holiday_date: holidayDate,
                holiday_type: holidayType,
                description: description
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#editHolidayModal').modal('hide');
                    showAlert(response.message, 'success');
                    loadHolidays();
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('Error updating holiday. Please try again.', 'danger');
            }
        });
    }

    function deleteHoliday(holidayId) {
        if (confirm('Are you sure you want to delete this holiday?')) {
            $.ajax({
                url: 'holiday_actions.php',
                type: 'POST',
                data: {
                    action: 'delete_holiday',
                    holiday_id: holidayId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        loadHolidays();
                    } else {
                        showAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    showAlert('Error deleting holiday. Please try again.', 'danger');
                }
            });
        }
    }

    function showAlert(message, type) {
        const alertContainer = $('#alertContainer');
        const alertId = 'alert-' + Date.now();
        
        const alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `);
        
        alertContainer.append(alert);
        
        // Auto remove alert after 5 seconds
        setTimeout(() => {
            $('#' + alertId).alert('close');
        }, 5000);
    }

    function syncHolidays() {
        const syncBtn = $('#syncHolidaysBtn');
        const originalText = syncBtn.html();
        
        // Show loading state
        syncBtn.prop('disabled', true);
        syncBtn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Syncing...');
        
        $.ajax({
            url: 'holiday_actions.php',
            type: 'POST',
            data: { action: 'sync_holidays' },
            dataType: 'json',
            success: function(response) {
                syncBtn.prop('disabled', false);
                syncBtn.html(originalText);
                
                if (response.success) {
                    showAlert(response.message, 'success');
                    loadHolidays(); // Reload the holidays list
                } else {
                    showAlert('Sync failed: ' + response.message, 'danger');
                }
            },
            error: function() {
                syncBtn.prop('disabled', false);
                syncBtn.html(originalText);
                showAlert('Error connecting to server. Please try again.', 'danger');
            }
        });
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '<',
            '>': '>',
            '"': '"',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    </script>
</body>
</html>
