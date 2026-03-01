<?php
/**
 * PUBLIC HOLIDAYS MANAGEMENT PAGE
 */

session_start();
// Restrict access for employees
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] === 'employee') {
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

        .holiday-view-toggle .btn {
            min-width: 110px;
        }

        #holidaysCalendar {
            min-height: 620px;
        }

        /* Improve spacing in FullCalendar inside Bootstrap card */
        .fc .fc-toolbar-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .fc .fc-button {
            box-shadow: none !important;
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
                                        <div class="btn-group mr-3 holiday-view-toggle" role="group" aria-label="Holiday view toggle">
                                            <button type="button" class="btn btn-outline-secondary" id="calendarViewBtn">
                                                <i class="fas fa-calendar-alt mr-2"></i>Calendar
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="listViewBtn">
                                                <i class="fas fa-list mr-2"></i>List
                                            </button>
                                        </div>
                                        <button class="btn btn-info mr-2" id="syncHolidaysBtn" onclick="syncHolidays()">
                                            <i class="fas fa-sync-alt mr-2"></i>Sync from API
                                        </button>
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#addHolidayModal">
                                            <i class="fas fa-plus mr-2"></i>Add Holiday
                                        </button>
                                    </div>
                                </div>
                            <div class="card-body">
                                <div id="holidaysCalendarView">
                                    <div id="holidaysCalendar">
                                        <div class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>
                                            <p class="mt-2">Loading calendar...</p>
                                        </div>
                                    </div>
                                </div>

                                <div id="holidaysListView" style="display: none;">
                                    <div class="table-responsive">
                                        <table class="table table-striped" id="holidaysTable">
                                            <thead>
                                                <tr>
                                                    <th>Holiday Name</th>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Description</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="holidaysTableBody">
                                                <tr>
                                                    <td colspan="5" class="text-center">
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
                                <option value="Regular Holiday">Regular Holiday - 100% pay if not working, 200% if working</option>
                                <option value="Special Non-Working Holiday">Special Non-Working Holiday - No work no pay, 130% if working</option>
                                <option value="Special Working Holiday">Special Working Holiday - Regular pay, work as usual</option>
                                <option value="Local Special Holiday">Local Special Holiday - Regional/local holiday</option>
                            </select>
                            <small class="form-text text-muted">Official holiday classification</small>
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
                                <option value="Regular Holiday">Regular Holiday - 100% pay if not working, 200% if working</option>
                                <option value="Special Non-Working Holiday">Special Non-Working Holiday - No work no pay, 130% if working</option>
                                <option value="Special Working Holiday">Special Working Holiday - Regular pay, work as usual</option>
                                <option value="Local Special Holiday">Local Special Holiday - Regional/local holiday</option>
                            </select>
                            <small class="form-text text-muted">Official holiday classification</small>
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

    <!-- Holiday Action Modal (Edit/Delete from calendar) -->
    <div class="modal fade" id="holidayActionModal" tabindex="-1" role="dialog" aria-labelledby="holidayActionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="holidayActionModalLabel">Holiday</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="holidayActionId">
                    <p class="mb-0">What would you like to do with this holiday?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-danger" id="holidayActionDeleteBtn">
                        <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                    <button type="button" class="btn btn-primary" id="holidayActionEditBtn">
                        <i class="fas fa-edit mr-2"></i>Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
    
    <div class="alert-container" id="alertContainer"></div>
    
    <script>
    let holidayCalendar = null;

    $(document).ready(function() {
        // Load holidays on page load
        loadHolidays();

        // Default view: calendar
        setHolidayView('calendar');

        // View toggles
        $('#calendarViewBtn').on('click', function() {
            setHolidayView('calendar');
        });
        $('#listViewBtn').on('click', function() {
            setHolidayView('list');
        });

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

        // Holiday action modal: Edit
        $('#holidayActionEditBtn').on('click', function() {
            const holidayId = $('#holidayActionId').val();
            $('#holidayActionModal').modal('hide');
            if (holidayId) editHoliday(parseInt(holidayId, 10));
        });

        // Holiday action modal: Delete
        $('#holidayActionDeleteBtn').on('click', function() {
            const holidayId = $('#holidayActionId').val();
            $('#holidayActionModal').modal('hide');
            if (holidayId) deleteHoliday(parseInt(holidayId, 10));
        });
    });

    function setHolidayView(view) {
        const isCalendar = view === 'calendar';
        $('#holidaysCalendarView').toggle(isCalendar);
        $('#holidaysListView').toggle(!isCalendar);

        $('#calendarViewBtn')
            .toggleClass('btn-secondary', isCalendar)
            .toggleClass('btn-outline-secondary', !isCalendar);
        $('#listViewBtn')
            .toggleClass('btn-secondary', !isCalendar)
            .toggleClass('btn-outline-secondary', isCalendar);

        // If switching to calendar after it exists, ensure it sizes correctly.
        if (isCalendar && holidayCalendar) {
            setTimeout(() => holidayCalendar.updateSize(), 0);
        }
    }

    function loadHolidays() {
        $.ajax({
            url: 'holiday_actions.php',
            type: 'POST',
            data: { action: 'get_holidays' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    populateHolidaysTable(response.holidays);
                    renderHolidayCalendar(response.holidays);
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

    function renderHolidayCalendar(holidays) {
        const calendarEl = document.getElementById('holidaysCalendar');
        if (!calendarEl) return;

        const events = (holidays || []).map(h => {
            const type = h.holiday_type || 'Regular Holiday';
            const colors = getHolidayTypeColors(type);
            return {
                id: String(h.holiday_id),
                title: h.holiday_name || 'Holiday',
                start: h.holiday_date,
                allDay: true,
                backgroundColor: colors.bg,
                borderColor: colors.border,
                textColor: colors.text,
                extendedProps: {
                    holiday_id: h.holiday_id,
                    holiday_type: type,
                    description: h.description || ''
                }
            };
        });

        if (!holidayCalendar) {
            holidayCalendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listMonth'
                },
                navLinks: true,
                dayMaxEvents: true,
                events,
                eventClick: function(info) {
                    const holidayId = info.event.extendedProps && info.event.extendedProps.holiday_id;
                    const holidayName = info.event.title || 'Holiday';
                    if (holidayId) {
                        $('#holidayActionId').val(holidayId);
                        $('#holidayActionModalLabel').text(holidayName);
                        $('#holidayActionModal').modal('show');
                    }
                },
                dateClick: function(info) {
                    // Quick add: prefill the selected date
                    $('#holidayDate').val(info.dateStr);
                    $('#addHolidayModal').modal('show');
                },
                eventDidMount: function(info) {
                    // Add a simple tooltip via native title attribute
                    const desc = info.event.extendedProps && info.event.extendedProps.description;
                    if (desc) info.el.setAttribute('title', desc);
                }
            });
            holidayCalendar.render();
        } else {
            holidayCalendar.removeAllEvents();
            events.forEach(e => holidayCalendar.addEvent(e));
        }
    }

    function getHolidayTypeColors(type) {
        // Keep colors consistent with badges but readable on calendar
        const map = {
            'Regular Holiday': { bg: '#0d6efd', border: '#0b5ed7', text: '#ffffff' },
            'Special Non-Working Holiday': { bg: '#ffc107', border: '#ffb300', text: '#212529' },
            'Special Working Holiday': { bg: '#17a2b8', border: '#138496', text: '#ffffff' },
            'Local Special Holiday': { bg: '#6c757d', border: '#5a6268', text: '#ffffff' },
            // Backward compatibility
            'National': { bg: '#0d6efd', border: '#0b5ed7', text: '#ffffff' },
            'Regional': { bg: '#6c757d', border: '#5a6268', text: '#ffffff' },
            'Special': { bg: '#ffc107', border: '#ffb300', text: '#212529' }
        };
        return map[type] || { bg: '#e9ecef', border: '#ced4da', text: '#212529' };
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
                    <td colspan="5" class="text-center text-muted">
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

            const holidayType = holiday.holiday_type || 'Regular Holiday';
            const typeBadge = getHolidayTypeBadge(holidayType);
            
            tableBody.append(`
                <tr>
                    <td>${escapeHtml(holiday.holiday_name)}</td>
                    <td>${formattedDate}</td>
                    <td>${typeBadge}</td>
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

    function getHolidayTypeBadge(type) {
        const badges = {
            'Regular Holiday': '<span class="badge badge-primary">Regular Holiday</span>',
            'Special Non-Working Holiday': '<span class="badge badge-warning">Special Non-Working</span>',
            'Special Working Holiday': '<span class="badge badge-info">Special Working</span>',
            'Local Special Holiday': '<span class="badge badge-secondary">Local Special</span>',
            // Backward compatibility
            'National': '<span class="badge badge-primary">National</span>',
            'Regional': '<span class="badge badge-secondary">Regional</span>',
            'Special': '<span class="badge badge-warning">Special</span>'
        };
        return badges[type] || '<span class="badge badge-light">' + escapeHtml(type) + '</span>';
    }

    function updateHolidayStats(holidays) {
        // Update statistics cards
        const totalHolidays = holidays.length;
        $('#totalHolidays').text(totalHolidays);
        
        // Check if holiday_type field exists in the data
        const hasHolidayType = holidays.length > 0 && holidays[0].hasOwnProperty('holiday_type');
        
        if (hasHolidayType) {
            // Count holidays by official types
            const regularHolidays = holidays.filter(h =>
                h.holiday_type === 'Regular Holiday' || h.holiday_type === 'National'
            ).length;
            const specialNonWorking = holidays.filter(h => 
                h.holiday_type === 'Special Non-Working Holiday' || 
                (h.holiday_type === 'Special' && !h.holiday_type.includes('Working'))
            ).length;
            const localHolidays = holidays.filter(h => 
                h.holiday_type === 'Local Special Holiday' || h.holiday_type === 'Regional'
            ).length;
            
            $('#nationalHolidays').text(regularHolidays);
            $('#nationalHolidays').parent().find('small').text('Regular Holidays');
            $('#regionalHolidays').text(specialNonWorking);
            $('#regionalHolidays').parent().find('small').text('Special Non-Working');
            
            // Update progress bars
            const regularPercentage = totalHolidays > 0 ? Math.round((regularHolidays / totalHolidays) * 100) : 0;
            const specialPercentage = totalHolidays > 0 ? Math.round((specialNonWorking / totalHolidays) * 100) : 0;
            
            $('#nationalProgress').css('width', regularPercentage + '%').text(`Regular (${regularPercentage}%)`);
            $('#regionalProgress').css('width', specialPercentage + '%').text(`Special Non-Working (${specialPercentage}%)`);
        } else {
            // Fallback: use default values if holiday_type column doesn't exist
            const regularHolidays = Math.floor(totalHolidays * 0.7); // 70% regular
            const specialHolidays = totalHolidays - regularHolidays; // 30% special
            
            $('#nationalHolidays').text(regularHolidays);
            $('#regionalHolidays').text(specialHolidays);
            
            // Update progress bars
            const regularPercentage = totalHolidays > 0 ? Math.round((regularHolidays / totalHolidays) * 100) : 0;
            const specialPercentage = totalHolidays > 0 ? Math.round((specialHolidays / totalHolidays) * 100) : 0;
            
            $('#nationalProgress').css('width', regularPercentage + '%').text(`Regular (${regularPercentage}%)`);
            $('#regionalProgress').css('width', specialPercentage + '%').text(`Special (${specialPercentage}%)`);
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
                    // Map old types to new types if needed
                    let holidayType = holiday.holiday_type || 'Regular Holiday';
                    if (holidayType === 'National') holidayType = 'Regular Holiday';
                    else if (holidayType === 'Regional') holidayType = 'Local Special Holiday';
                    else if (holidayType === 'Special') holidayType = 'Special Non-Working Holiday';
                    $('#editHolidayType').val(holidayType);
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
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }


    </script>
</body>
</html>
