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
require_once 'config.php';

// Use the global database connection
$pdo = $conn;

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_session':
                try {
                    $stmt = $pdo->prepare("INSERT INTO training_sessions (course_id, trainer_id, session_name, start_date, end_date, location, max_participants, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['course_id'],
                        $_POST['trainer_id'],
                        $_POST['session_name'],
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['location'],
                        $_POST['max_participants'],
                        $_POST['status']
                    ]);
                    $message = "Training session added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding session: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update_session':
                try {
                    $stmt = $pdo->prepare("UPDATE training_sessions SET course_id = ?, trainer_id = ?, session_name = ?, start_date = ?, end_date = ?, location = ?, max_participants = ?, status = ? WHERE session_id = ?");
                    $stmt->execute([
                        $_POST['course_id'],
                        $_POST['trainer_id'],
                        $_POST['session_name'],
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['location'],
                        $_POST['max_participants'],
                        $_POST['status'],
                        $_POST['session_id']
                    ]);
                    $message = "Training session updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error updating session: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'delete_session':
                try {
                    $stmt = $pdo->prepare("DELETE FROM training_sessions WHERE session_id=?");
                    $stmt->execute([$_POST['session_id']]);
                    $message = "Training session deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting session: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Get current month and year
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Fetch training sessions for the current month
try {
    $stmt = $pdo->prepare("
        SELECT ts.*, tc.course_name, t.first_name as trainer_first, t.last_name as trainer_last
        FROM training_sessions ts 
        JOIN training_courses tc ON ts.course_id = tc.course_id 
        JOIN trainers t ON ts.trainer_id = t.trainer_id 
        WHERE MONTH(ts.start_date) = ? AND YEAR(ts.start_date) = ?
        ORDER BY ts.start_date, ts.start_time
    ");
    $stmt->execute([$currentMonth, $currentYear]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sessions = [];
}

// Fetch courses for dropdowns
try {
    $stmt = $pdo->query("SELECT * FROM training_courses WHERE status = 'Active' ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $courses = [];
}

// Fetch trainers for dropdowns
try {
    $stmt = $pdo->query("SELECT * FROM trainers ORDER BY last_name, first_name");
    $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $trainers = [];
}

// Get statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_sessions WHERE status = 'Scheduled'");
    $scheduledSessions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_sessions WHERE status = 'In Progress'");
    $ongoingSessions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_sessions WHERE status = 'Completed'");
    $completedSessions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM training_sessions WHERE start_date >= CURDATE()");
    $upcomingSessions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $scheduledSessions = 0;
    $ongoingSessions = 0;
    $completedSessions = 0;
    $upcomingSessions = 0;
}

// Calendar helper functions
function getDaysInMonth($month, $year) {
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

function getFirstDayOfMonth($month, $year) {
    return date('w', mktime(0, 0, 0, $month, 1, $year));
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Scheduled': return 'status-scheduled';
        case 'In Progress': return 'status-ongoing';
        case 'Completed': return 'status-completed';
        case 'Cancelled': return 'status-cancelled';
        default: return 'status-unknown';
    }
}

// Group sessions by date
$sessionsByDate = [];
foreach ($sessions as $session) {
    $date = date('Y-m-d', strtotime($session['start_date']));
    if (!isset($sessionsByDate[$date])) {
        $sessionsByDate[$date] = [];
    }
    $sessionsByDate[$date][] = $session;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Calendar - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .btn-primary {
            background: var(--azure-blue);
            border-color: var(--azure-blue);
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            font-weight: 600;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .stats-card i {
            font-size: 3rem;
            color: var(--azure-blue);
            margin-bottom: 15px;
        }

        .calendar-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .calendar-nav .btn {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .calendar-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--azure-blue);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .calendar-day-header {
            background: var(--azure-blue);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }

        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 10px;
            position: relative;
        }

        .calendar-day.other-month {
            background: #f8f9fa;
            color: #6c757d;
        }

        .calendar-day.today {
            background: var(--azure-blue-lighter);
            border: 2px solid var(--azure-blue);
        }

        .day-number {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .session-item {
            background: var(--azure-blue);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            margin-bottom: 2px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .session-item:hover {
            background: var(--azure-blue-dark);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-scheduled { background: #d1ecf1; color: #0c5460; }
        .status-ongoing { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-unknown { background: #e2e3e5; color: #383d41; }

        .session-details {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">Training Calendar</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-calendar-check"></i>
                            <h3><?php echo $scheduledSessions; ?></h3>
                            <h6>Scheduled Sessions</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-play-circle"></i>
                            <h3><?php echo $ongoingSessions; ?></h3>
                            <h6>Ongoing Sessions</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-check-circle"></i>
                            <h3><?php echo $completedSessions; ?></h3>
                            <h6>Completed Sessions</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-clock"></i>
                            <h3><?php echo $upcomingSessions; ?></h3>
                            <h6>Upcoming Sessions</h6>
                        </div>
                    </div>

                </div>

                <!-- Calendar Controls -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="calendar-nav">
                        <a href="?month=<?php echo $currentMonth - 1; ?>&year=<?php echo $currentYear; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <span class="calendar-title"><?php echo date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)); ?></span>
                        <a href="?month=<?php echo $currentMonth + 1; ?>&year=<?php echo $currentYear; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addSessionModal">
                        <i class="fas fa-plus"></i> Add Session
                    </button>
                </div>

                <!-- Calendar -->
                <div class="calendar-container">
                    <div class="calendar-grid">
                        <!-- Day headers -->
                        <div class="calendar-day-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>
                        
                        <?php
                        $daysInMonth = getDaysInMonth($currentMonth, $currentYear);
                        $firstDay = getFirstDayOfMonth($currentMonth, $currentYear);
                        $currentDay = 1;
                        $today = date('Y-m-d');
                        
                        // Previous month days
                        $prevMonth = $currentMonth - 1;
                        $prevYear = $currentYear;
                        if ($prevMonth < 1) {
                            $prevMonth = 12;
                            $prevYear--;
                        }
                        $daysInPrevMonth = getDaysInMonth($prevMonth, $prevYear);
                        
                        for ($i = 0; $i < $firstDay; $i++) {
                            $prevDay = $daysInPrevMonth - $firstDay + $i + 1;
                            echo '<div class="calendar-day other-month">';
                            echo '<div class="day-number">' . $prevDay . '</div>';
                            echo '</div>';
                        }
                        
                        // Current month days
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $date = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                            $isToday = $date === $today;
                            $dayClass = $isToday ? 'calendar-day today' : 'calendar-day';
                            
                            echo '<div class="' . $dayClass . '">';
                            echo '<div class="day-number">' . $day . '</div>';
                            
                            // Show sessions for this day
                            if (isset($sessionsByDate[$date])) {
                                foreach ($sessionsByDate[$date] as $session) {
                                    echo '<div class="session-item" onclick="showSessionDetails(' . $session['session_id'] . ')">';
                                    echo htmlspecialchars($session['session_name']);
                                    echo '</div>';
                                }
                            }
                            
                            echo '</div>';
                        }
                        
                        // Next month days
                        $nextMonth = $currentMonth + 1;
                        $nextYear = $currentYear;
                        if ($nextMonth > 12) {
                            $nextMonth = 1;
                            $nextYear++;
                        }
                        
                        $remainingDays = 42 - ($firstDay + $daysInMonth); // 6 rows * 7 days
                        for ($i = 1; $i <= $remainingDays; $i++) {
                            echo '<div class="calendar-day other-month">';
                            echo '<div class="day-number">' . $i . '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Upcoming Sessions List -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Upcoming Training Sessions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Session Name</th>
                                        <th>Course</th>
                                        <th>Trainer</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($session['session_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($session['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['trainer_first'] . ' ' . $session['trainer_last']); ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($session['start_date'])); ?>
                                            <?php if ($session['start_time']): ?>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($session['start_time'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($session['location']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusBadgeClass($session['status']); ?>">
                                                <?php echo htmlspecialchars($session['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editSession(<?php echo $session['session_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteSession(<?php echo $session['session_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Session Modal -->
    <div class="modal fade" id="addSessionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add Training Session</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_session">
                        <div class="form-group">
                            <label>Course *</label>
                            <select class="form-control" name="course_id" required>
                                <option value="">Select Course</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Trainer *</label>
                            <select class="form-control" name="trainer_id" required>
                                <option value="">Select Trainer</option>
                                <?php foreach ($trainers as $trainer): ?>
                                <option value="<?php echo $trainer['trainer_id']; ?>">
                                    <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session Name *</label>
                            <input type="text" class="form-control" name="session_name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>End Date *</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Location</label>
                                    <input type="text" class="form-control" name="location" placeholder="Training location">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Max Participants</label>
                                    <input type="number" class="form-control" name="max_participants" min="1" placeholder="Maximum participants">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status *</label>
                            <select class="form-control" name="status" required>
                                <option value="Scheduled">Scheduled</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Session</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Session Details Modal -->
    <div class="modal fade" id="sessionDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> Session Details</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="sessionDetailsContent">
                    <!-- Session details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Message Modal -->
    <?php if ($message): ?>
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header <?php echo $messageType === 'success' ? 'bg-success' : 'bg-danger'; ?> text-white">
                    <h5 class="modal-title">
                        <?php echo $messageType === 'success' ? '<i class="fas fa-check-circle"></i> Success' : '<i class="fas fa-exclamation-circle"></i> Error'; ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Show message modal if there's a message
        <?php if ($message): ?>
        $(document).ready(function() {
            $('#messageModal').modal('show');
        });
        <?php endif; ?>

        // Session details function
        function showSessionDetails(sessionId) {
            // This would typically load session details via AJAX
            // For now, we'll show a simple alert
            alert('Session details for ID: ' + sessionId);
        }

        // Edit session function
        function editSession(sessionId) {
            alert('Edit session with ID: ' + sessionId);
        }

        // Delete session function
        function deleteSession(sessionId) {
            if (confirm('Are you sure you want to delete this training session?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_session">
                    <input type="hidden" name="session_id" value="${sessionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }

        }
    </script>
</body>
</html>

