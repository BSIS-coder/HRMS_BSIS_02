<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once 'dp.php';

// Initialize notifications table if it doesn't exist
function initializeNotificationsTable($conn) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS user_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
            is_read BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL DEFAULT NULL,
            action_url VARCHAR(500) NULL,
            priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
            category VARCHAR(100) DEFAULT 'general',
            expires_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created_at (created_at),
            INDEX idx_priority (priority)
        )";
        $conn->exec($sql);
        
        // Insert sample notifications if table is empty
        $count_sql = "SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ?";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute([$_SESSION['user_id']]);
        $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            insertSampleNotifications($conn, $_SESSION['user_id']);
        }
        
    } catch (PDOException $e) {
        // Table creation failed, we'll use sample data
        return false;
    }
    return true;
}

function insertSampleNotifications($conn, $userId) {
    $sampleNotifications = [
        [
            'title' => 'Welcome to HR System',
            'message' => 'Welcome to the HR Management System. Please complete your profile setup and explore the available features.',
            'type' => 'info',
            'priority' => 'normal',
            'category' => 'system',
            'action_url' => 'profile.php'
        ],
        [
            'title' => 'Leave Request Approved',
            'message' => 'Your leave request for next week has been approved by your manager. Enjoy your time off!',
            'type' => 'success',
            'priority' => 'normal',
            'category' => 'leave',
            'action_url' => 'leave_requests.php'
        ],
        [
            'title' => 'Training Session Reminder',
            'message' => 'Mandatory safety training is scheduled for tomorrow at 10:00 AM in Conference Room A. Please arrive 15 minutes early.',
            'type' => 'warning',
            'priority' => 'high',
            'category' => 'training',
            'action_url' => 'training.php'
        ],
        [
            'title' => 'System Maintenance Notice',
            'message' => 'Scheduled system maintenance will occur this weekend from 2:00 AM to 6:00 AM. Some features may be unavailable.',
            'type' => 'info',
            'priority' => 'low',
            'category' => 'system',
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'title' => 'Performance Review Due',
            'message' => 'Your quarterly performance review is due in 3 days. Please complete the self-assessment form before the deadline.',
            'type' => 'warning',
            'priority' => 'high',
            'category' => 'performance',
            'action_url' => 'performance.php'
        ],
        [
            'title' => 'Payroll Processed',
            'message' => 'Your salary for this month has been processed and will be credited to your account by end of business day.',
            'type' => 'success',
            'priority' => 'normal',
            'category' => 'payroll',
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'title' => 'Document Upload Required',
            'message' => 'Please upload your updated tax documents to complete your profile. Missing documents may delay payroll processing.',
            'type' => 'error',
            'priority' => 'urgent',
            'category' => 'documents',
            'action_url' => 'documents.php'
        ],
        [
            'title' => 'Team Meeting Scheduled',
            'message' => 'Monthly team meeting is scheduled for Friday at 2:00 PM. Agenda will be shared via email.',
            'type' => 'info',
            'priority' => 'normal',
            'category' => 'meetings'
        ]
    ];
    
    foreach ($sampleNotifications as $notification) {
        $sql = "INSERT INTO user_notifications (user_id, title, message, type, priority, category, action_url, is_read, read_at, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $userId,
            $notification['title'],
            $notification['message'],
            $notification['type'],
            $notification['priority'],
            $notification['category'],
            $notification['action_url'] ?? null,
            $notification['is_read'] ?? 0,
            $notification['read_at'] ?? null,
            date('Y-m-d H:i:s', strtotime('-' . rand(1, 72) . ' hours'))
        ]);
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'mark_read':
                $notification_id = (int)$_POST['notification_id'];
                $sql = "UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([$notification_id, $_SESSION['user_id']]);
                echo json_encode(['success' => $result]);
                exit();
                
            case 'mark_unread':
                $notification_id = (int)$_POST['notification_id'];
                $sql = "UPDATE user_notifications SET is_read = 0, read_at = NULL WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([$notification_id, $_SESSION['user_id']]);
                echo json_encode(['success' => $result]);
                exit();
                
            case 'delete_notification':
                $notification_id = (int)$_POST['notification_id'];
                $sql = "DELETE FROM user_notifications WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([$notification_id, $_SESSION['user_id']]);
                echo json_encode(['success' => $result]);
                exit();
                
            case 'mark_all_read':
                $sql = "UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([$_SESSION['user_id']]);
                echo json_encode(['success' => $result, 'message' => 'All notifications marked as read']);
                exit();
                
            case 'delete_all_read':
                $sql = "DELETE FROM user_notifications WHERE user_id = ? AND is_read = 1";
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([$_SESSION['user_id']]);
                $affected_rows = $stmt->rowCount();
                echo json_encode(['success' => $result, 'deleted_count' => $affected_rows]);
                exit();
                
            case 'get_unread_count':
                $sql = "SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$_SESSION['user_id']]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo json_encode(['count' => $count]);
                exit();
                
            case 'get_recent_notifications':
                $limit = (int)($_POST['limit'] ?? 5);
                $sql = "SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$_SESSION['user_id'], $limit]);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['notifications' => $notifications]);
                exit();
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Initialize notifications table
$table_exists = initializeNotificationsTable($conn);

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build SQL query based on filters
$where_conditions = ["user_id = ?"];
$params = [$_SESSION['user_id']];

if ($filter == 'unread') {
    $where_conditions[] = "is_read = 0";
} elseif ($filter == 'read') {
    $where_conditions[] = "is_read = 1";
}

if ($type_filter != 'all') {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if ($category_filter != 'all') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if ($priority_filter != 'all') {
    $where_conditions[] = "priority = ?";
    $params[] = $priority_filter;
}

// Add expiration filter
$where_conditions[] = "(expires_at IS NULL OR expires_at > NOW())";

$where_clause = implode(' AND ', $where_conditions);

// Get notifications
$notifications = [];
$total_count = 0;

try {
    if ($table_exists) {
        $sql = "SELECT * FROM user_notifications WHERE $where_clause ORDER BY 
                CASE priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'normal' THEN 3 
                    WHEN 'low' THEN 4 
                END, 
                is_read ASC, created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $count_sql = "SELECT COUNT(*) as total FROM user_notifications WHERE $where_clause";
        $count_params = array_slice($params, 0, -2);
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
} catch (PDOException $e) {
    // Use sample data as fallback
    $notifications = [
        [
            'id' => 1, 'title' => 'Welcome to HR System', 'message' => 'Welcome! Please complete your profile setup.',
            'type' => 'info', 'priority' => 'normal', 'category' => 'system', 'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'read_at' => null, 'action_url' => 'profile.php'
        ],
        [
            'id' => 2, 'title' => 'Leave Request Update', 'message' => 'Your leave request has been approved.',
            'type' => 'success', 'priority' => 'normal', 'category' => 'leave', 'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'read_at' => null, 'action_url' => 'leave_requests.php'
        ]
    ];
    $total_count = count($notifications);
}

// Get notification counts for filters
$counts = ['total' => 0, 'unread' => 0, 'read' => 0, 'urgent' => 0, 'high' => 0];

try {
    if ($table_exists) {
        $count_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
                        SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read,
                        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high
                      FROM user_notifications WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->execute([$_SESSION['user_id']]);
        $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $counts = ['total' => 8, 'unread' => 4, 'read' => 4, 'urgent' => 1, 'high' => 2];
}

$total_pages = ceil($total_count / $limit);

// Get categories for filter
$categories = [];
try {
    if ($table_exists) {
        $cat_sql = "SELECT DISTINCT category FROM user_notifications WHERE user_id = ? ORDER BY category";
        $cat_stmt = $conn->prepare($cat_sql);
        $cat_stmt->execute([$_SESSION['user_id']]);
        $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $categories = ['system', 'leave', 'training', 'performance', 'payroll', 'documents', 'meetings'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - HR Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            margin-top: 20px;
            margin-bottom: 40px;
        }
        
        .notifications-header {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            padding: 40px;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 8px 32px rgba(233, 30, 99, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .notifications-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .notifications-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            position: relative;
            z-index: 1;
        }
        
        .notifications-header .subtitle {
            opacity: 0.95;
            font-size: 1.2rem;
            margin-top: 10px;
            position: relative;
            z-index: 1;
        }
        
        .notifications-card {
            background: white;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .filters-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px 30px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }
        
        .filter-group h6 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .filter-btn {
            padding: 10px 18px;
            border: 2px solid #E91E63;
            background: white;
            color: #E91E63;
            border-radius: 30px;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.85rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }
        
        .filter-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }
        
        .filter-btn:hover::before {
            left: 100%;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            text-decoration: none;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
        }
        
        .filter-badge {
            background: rgba(255,255,255,0.25);
            border-radius: 15px;
            padding: 3px 10px;
            font-size: 0.7rem;
            margin-left: 8px;
            font-weight: 700;
        }
        
        .priority-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .priority-urgent { background: #dc3545; box-shadow: 0 0 8px rgba(220, 53, 69, 0.6); }
        .priority-high { background: #fd7e14; box-shadow: 0 0 6px rgba(253, 126, 20, 0.6); }
        .priority-normal { background: #28a745; }
        .priority-low { background: #6c757d; }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .action-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.4s ease;
            font-size: 0.9rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }
        
        .btn-mark-all {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-mark-all:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }
        
        .btn-delete-read {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-delete-read:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }
        
        .notifications-list {
            padding: 0;
            max-height: calc(100vh - 400px);
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 25px 30px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            cursor: pointer;
            background: white;
        }
        
        .notification-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: transparent;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            background: rgba(233, 30, 99, 0.02);
            transform: translateX(8px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .notification-item.unread {
            background: linear-gradient(135deg, rgba(233, 30, 99, 0.08) 0%, rgba(233, 30, 99, 0.03) 100%);
            border-left: 6px solid #E91E63;
            font-weight: 500;
        }
        
        .notification-item.unread::before {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
        }
        
        .notification-item.read {
            opacity: 0.75;
        }
        
        .notification-content {
            flex: 1;
            margin-right: 20px;
        }
        
        .notification-title {
            font-weight: 700;
            font-size: 1.15rem;
            margin-bottom: 10px;
            color: #2c3e50;
            line-height: 1.4;
        }
        
        .notification-message {
            color: #5a6c7d;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .notification-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 0.8rem;
            color: #8e9aaf;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .type-badge, .category-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .type-info { background: rgba(23, 162, 184, 0.15); color: #17a2b8; }
        .type-success { background: rgba(40, 167, 69, 0.15); color: #28a745; }
        .type-warning { background: rgba(255, 193, 7, 0.15); color: #e68900; }
        .type-error { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
        
        .category-badge {
            background: rgba(108, 117, 125, 0.15);
            color: #495057;
        }
        
        .notification-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            position: relative;
        }
        
        .status-unread {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.2);
            animation: heartbeat 2s ease-in-out infinite;
        }
        
        .status-read {
            background: #6c757d;
            opacity: 0.6;
        }
        
        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .action-dropdown {
            position: relative;
        }
        
        .action-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 180px;
            display: none;
            overflow: hidden;
        }
        
        .action-menu.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .action-menu-item {
            padding: 15px 20px;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-menu-item:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            transform: translateX(5px);
        }
        
        .action-menu-item:last-child {
            border-bottom: none;
        }
        
        .action-menu-item i {
            width: 16px;
            text-align: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 30px;
            color: #8e9aaf;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 30px;
            opacity: 0.4;
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: #495057;
            font-weight: 600;
        }
        
        .pagination-container {
            padding: 25px 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: 2px solid #e9ecef;
        }
        
        .back-button {
            position: fixed;
            top: 90px;
            left: 30px;
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.4rem;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
        }
        
        .back-button:hover {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 12px 35px rgba(233, 30, 99, 0.5);
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #E91E63;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .notification-item.removing {
            opacity: 0;
            transform: translateX(-100%);
            transition: all 0.5s ease;
        }
        
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 9999;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .toast-notification.show {
            transform: translateX(0);
        }
        
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: #E91E63;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
            outline: none;
        }
        
        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .notification-actions-mobile {
            display: none;
        }
        
        @media (max-width: 768px) {
            .notifications-header {
                padding: 25px 20px;
                text-align: center;
            }
            
            .notifications-header h1 {
                font-size: 2rem;
            }
            
            .filters-section {
                padding: 20px;
            }
            
            .notification-item {
                padding: 20px;
            }
            
            .filter-buttons {
                justify-content: center;
            }
            
            .action-buttons {
                justify-content: center;
                margin-top: 15px;
            }
            
            .notification-actions {
                display: none;
            }
            
            .notification-actions-mobile {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #f1f1f1;
            }
            
            .back-button {
                top: 20px;
                left: 20px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
        }
        
        .notification-item.urgent {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.08) 0%, rgba(220, 53, 69, 0.03) 100%);
        }
        
        .notification-item.high {
            border-left-color: #fd7e14;
            background: linear-gradient(135deg, rgba(253, 126, 20, 0.08) 0%, rgba(253, 126, 20, 0.03) 100%);
        }
        
        /* Animation for new notifications */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .notification-item.new {
            animation: slideInRight 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .refresh-button {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 8px 16px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .refresh-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }
        
        .priority-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .stats-row {
            background: linear-gradient(135deg, rgba(233, 30, 99, 0.05) 0%, rgba(233, 30, 99, 0.02) 100%);
            padding: 15px 30px;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .stats-item {
            text-align: center;
            padding: 10px;
        }
        
        .stats-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #E91E63;
            display: block;
        }
        
        .stats-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <!-- Include your navigation -->
    <?php include_once 'navigation.php'; ?>
    
    <!-- Back button -->
    <button class="back-button" onclick="history.back()" title="Go Back">
        <i class="fas fa-arrow-left"></i>
    </button>
    
    <!-- Toast Notification Template -->
    <div id="toast-template" class="toast-notification" style="display: none;">
        <div class="d-flex align-items-center">
            <i class="fas fa-check-circle text-success mr-2"></i>
            <span id="toast-message"></span>
        </div>
    </div>
    
    <div class="container main-container">
        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-10">
                <div class="notifications-header">
                    <h1><i class="fas fa-bell mr-3"></i>Notifications Center</h1>
                    <div class="subtitle">Stay connected with real-time updates and important announcements</div>
                </div>
                
                <div class="notifications-card">
                    <!-- Stats Row -->
                    <div class="stats-row">
                        <div class="row">
                            <div class="col-3 stats-item">
                                <span class="stats-number"><?php echo $counts['total'] ?? 0; ?></span>
                                <span class="stats-label">Total</span>
                            </div>
                            <div class="col-3 stats-item">
                                <span class="stats-number text-danger"><?php echo $counts['unread'] ?? 0; ?></span>
                                <span class="stats-label">Unread</span>
                            </div>
                            <div class="col-3 stats-item">
                                <span class="stats-number text-warning"><?php echo $counts['urgent'] ?? 0; ?></span>
                                <span class="stats-label">Urgent</span>
                            </div>
                            <div class="col-3 stats-item">
                                <span class="stats-number text-info"><?php echo $counts['high'] ?? 0; ?></span>
                                <span class="stats-label">High Priority</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Box -->
                    <div class="px-4 pt-4">
                        <div class="search-box">
                            <input type="text" class="search-input" id="searchInput" placeholder="Search notifications...">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                    
                    <!-- Filters Section -->
                    <div class="filters-section">
                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Status Filters -->
                                <div class="filter-group">
                                    <h6>Status</h6>
                                    <div class="filter-buttons">
                                        <a href="?filter=all&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>" 
                                           class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                                            All <span class="filter-badge"><?php echo $counts['total'] ?? 0; ?></span>
                                        </a>
                                        <a href="?filter=unread&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>" 
                                           class="filter-btn <?php echo $filter == 'unread' ? 'active' : ''; ?>">
                                            Unread <span class="filter-badge"><?php echo $counts['unread'] ?? 0; ?></span>
                                        </a>
                                        <a href="?filter=read&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>" 
                                           class="filter-btn <?php echo $filter == 'read' ? 'active' : ''; ?>">
                                            Read <span class="filter-badge"><?php echo $counts['read'] ?? 0; ?></span>
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Type & Category Filters -->
                                <div class="filter-group">
                                    <h6>Filters</h6>
                                    <div class="filter-buttons">
                                        <!-- Type Dropdown -->
                                        <div class="dropdown d-inline-block">
                                            <button class="filter-btn dropdown-toggle <?php echo $type_filter != 'all' ? 'active' : ''; ?>" 
                                                    type="button" data-toggle="dropdown">
                                                <i class="fas fa-tag mr-1"></i>
                                                Type: <?php echo ucfirst($type_filter); ?>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=all&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>">All Types</a>
                                                <a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=info&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>">Info</a>
                                                <a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=success&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>">Success</a>
                                                <a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=warning&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>">Warning</a>
                                                <a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=error&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>">Error</a>
                                            </div>
                                        </div>
                                        
                                        <!-- Category Dropdown -->
                                        <div class="dropdown d-inline-block">
                                            <button class="filter-btn dropdown-toggle <?php echo $category_filter != 'all' ? 'active' : ''; ?>" 
                                                    type="button" data-toggle="dropdown">
                                                <i class="fas fa-folder mr-1"></i>
                                                Category: <?php echo ucfirst($category_filter); ?>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=all&priority=<?php echo $priority_filter; ?>">All Categories</a>
                                                <?php foreach ($categories as $category): ?>
                                                <a class="dropdown-item" href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category; ?>&priority=<?php echo $priority_filter; ?>">
                                                    <?php echo ucfirst($category); ?>
                                                </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Priority Filters -->
                                <div class="filter-group">
                                    <h6>Priority</h6>
                                    <div class="priority-filters">
                                        <a href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=all" 
                                           class="filter-btn <?php echo $priority_filter == 'all' ? 'active' : ''; ?>">
                                            All Priority
                                        </a>
                                        <a href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=urgent" 
                                           class="filter-btn <?php echo $priority_filter == 'urgent' ? 'active' : ''; ?>">
                                            <span class="priority-indicator priority-urgent"></span>Urgent
                                        </a>
                                        <a href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=high" 
                                           class="filter-btn <?php echo $priority_filter == 'high' ? 'active' : ''; ?>">
                                            <span class="priority-indicator priority-high"></span>High
                                        </a>
                                        <a href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=normal" 
                                           class="filter-btn <?php echo $priority_filter == 'normal' ? 'active' : ''; ?>">
                                            <span class="priority-indicator priority-normal"></span>Normal
                                        </a>
                                        <a href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=low" 
                                           class="filter-btn <?php echo $priority_filter == 'low' ? 'active' : ''; ?>">
                                            <span class="priority-indicator priority-low"></span>Low
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4 text-lg-right">
                                <div class="action-buttons">
                                    <button class="refresh-button" onclick="refreshNotifications()">
                                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                                    </button>
                                    
                                    <?php if (($counts['unread'] ?? 0) > 0): ?>
                                    <button class="action-btn btn-mark-all" onclick="markAllAsRead()">
                                        <i class="fas fa-check-double mr-1"></i> Mark All Read
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (($counts['read'] ?? 0) > 0): ?>
                                    <button class="action-btn btn-delete-read" onclick="deleteAllRead()">
                                        <i class="fas fa-trash mr-1"></i> Delete Read
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notifications List -->
                    <div class="notifications-list" id="notificationsList">
                        <?php if (empty($notifications)): ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <h3>No notifications found</h3>
                                <p>You're all caught up! No notifications match your current filters.</p>
                                <button class="btn btn-outline-primary" onclick="location.reload()">
                                    <i class="fas fa-refresh mr-2"></i>Refresh Page
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php 
                                echo $notification['is_read'] ? 'read' : 'unread';
                                echo ' ' . ($notification['priority'] ?? 'normal');
                            ?>" data-id="<?php echo $notification['id']; ?>" 
                                 data-category="<?php echo $notification['category'] ?? 'general'; ?>"
                                 data-priority="<?php echo $notification['priority'] ?? 'normal'; ?>">
                                <div class="d-flex align-items-start">
                                    <div class="notification-content">
                                        <div class="notification-title">
                                            <span class="priority-indicator priority-<?php echo $notification['priority'] ?? 'normal'; ?>"></span>
                                            <?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?>
                                        </div>
                                        <div class="notification-message">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </div>
                                        
                                        <?php if (!empty($notification['action_url'])): ?>
                                        <div class="notification-action mb-2">
                                            <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt mr-1"></i> View Details
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="notification-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-clock"></i>
                                                <?php 
                                                $time = strtotime($notification['created_at']);
                                                $now = time();
                                                $diff = $now - $time;
                                                
                                                if ($diff < 60) {
                                                    echo 'Just now';
                                                } elseif ($diff < 3600) {
                                                    echo floor($diff / 60) . ' minutes ago';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . ' hours ago';
                                                } elseif ($diff < 604800) {
                                                    echo floor($diff / 86400) . ' days ago';
                                                } else {
                                                    echo date('M d, Y', $time);
                                                }
                                                ?>
                                            </div>
                                            <div class="meta-item">
                                                <span class="type-badge type-<?php echo $notification['type']; ?>">
                                                    <?php echo $notification['type']; ?>
                                                </span>
                                            </div>
                                            <div class="meta-item">
                                                <span class="category-badge">
                                                    <?php echo $notification['category'] ?? 'general'; ?>
                                                </span>
                                            </div>
                                            <?php if ($notification['is_read'] && $notification['read_at']): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-eye"></i>
                                                Read <?php echo date('M d, H:i', strtotime($notification['read_at'])); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Mobile Actions -->
                                        <div class="notification-actions-mobile">
                                            <div class="status-indicator <?php echo $notification['is_read'] ? 'status-read' : 'status-unread'; ?>"></div>
                                            <div>
                                                <?php if ($notification['is_read']): ?>
                                                <button class="btn btn-sm btn-outline-secondary mr-2" onclick="markAsUnread(<?php echo $notification['id']; ?>)">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                                <?php else: ?>
                                                <button class="btn btn-sm btn-outline-primary mr-2" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Desktop Actions -->
                                    <div class="notification-actions">
                                        <div class="status-indicator <?php echo $notification['is_read'] ? 'status-read' : 'status-unread'; ?>"></div>
                                        
                                        <div class="action-dropdown">
                                            <button class="btn btn-sm btn-light" onclick="toggleActionMenu(this)" title="Actions">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="action-menu">
                                                <?php if ($notification['is_read']): ?>
                                                <div class="action-menu-item" onclick="markAsUnread(<?php echo $notification['id']; ?>)">
                                                    <i class="fas fa-eye-slash"></i> Mark as Unread
                                                </div>
                                                <?php else: ?>
                                                <div class="action-menu-item" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                    <i class="fas fa-eye"></i> Mark as Read
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($notification['action_url'])): ?>
                                                <div class="action-menu-item" onclick="window.open('<?php echo htmlspecialchars($notification['action_url']); ?>', '_blank')">
                                                    <i class="fas fa-external-link-alt"></i> Open Link
                                                </div>
                                                <?php endif; ?>
                                                <div class="action-menu-item text-danger" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <nav aria-label="Notifications pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&page=<?php echo $page-1; ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&page=1">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                                </li>
                                <?php endif; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?filter=<?php echo $filter; ?>&type=<?php echo $type_filter; ?>&category=<?php echo $category_filter; ?>&priority=<?php echo $priority_filter; ?>&page=<?php echo $page+1; ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Showing <?php echo min($offset + 1, $total_count); ?> to <?php echo min($offset + $limit, $total_count); ?> of <?php echo $total_count; ?> notifications
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
<script>
    let searchTimeout;
    
    // Search functionality
    $('#searchInput').on('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = $(this).val().toLowerCase();
        
        searchTimeout = setTimeout(() => {
            $('.notification-item').each(function() {
                const title = $(this).find('.notification-title').text().toLowerCase();
                const message = $(this).find('.notification-message').text().toLowerCase();
                const category = $(this).data('category').toString().toLowerCase();
                
                if (title.includes(searchTerm) || message.includes(searchTerm) || category.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }, 300);
    });
    
    // Close action menus when clicking outside
    $(document).on('click', function(event) {
        if (!$(event.target).closest('.action-dropdown').length) {
            $('.action-menu').removeClass('show');
        }
    });

    function toggleActionMenu(button) {
        const menu = $(button).next('.action-menu');
        const isShow = menu.hasClass('show');
        $('.action-menu').removeClass('show');
        if (!isShow) menu.addClass('show');
    }

    function showToast(message, type = 'success') {
        const toast = $('#toast-template').clone();
        toast.attr('id', '');
        toast.find('#toast-message').text(message);
        
        if (type === 'error') {
            toast.find('i').removeClass('fa-check-circle text-success').addClass('fa-exclamation-circle text-danger');
        }
        
        $('body').append(toast);
        toast.show().addClass('show');
        
        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function sendAction(action, notificationId = null, showLoading = true) {
        if (showLoading) {
            $('#notificationsList').addClass('loading');
        }
        
        return $.ajax({
            url: '',
            method: 'POST',
            data: { action: action, notification_id: notificationId },
            dataType: 'json'
        }).always(() => {
            if (showLoading) {
                $('#notificationsList').removeClass('loading');
            }
        });
    }

    function markAsRead(id) {
        sendAction('mark_read', id).done(res => {
            if (res.success) {
                const item = $(`.notification-item[data-id="${id}"]`);
                item.removeClass('unread').addClass('read');
                item.find('.status-indicator')
                    .removeClass('status-unread')
                    .addClass('status-read');
                
                // Update action buttons
                const readBtn = item.find('.action-menu-item:contains("Mark as Read"), button:contains("eye"):not(:contains("slash"))');
                readBtn.html('<i class="fas fa-eye-slash"></i> Mark as Unread')
                    .attr('onclick', `markAsUnread(${id})`);
                
                updateNotificationCounts();
                showToast('Notification marked as read');
            } else {
                showToast('Failed to mark notification as read', 'error');
            }
        });
    }

    function markAsUnread(id) {
        sendAction('mark_unread', id).done(res => {
            if (res.success) {
                const item = $(`.notification-item[data-id="${id}"]`);
                item.removeClass('read').addClass('unread');
                item.find('.status-indicator')
                    .removeClass('status-read')
                    .addClass('status-unread');
                
                // Update action buttons
                const unreadBtn = item.find('.action-menu-item:contains("Mark as Unread"), button:contains("eye-slash")');
                unreadBtn.html('<i class="fas fa-eye"></i> Mark as Read')
                    .attr('onclick', `markAsRead(${id})`);
                
                updateNotificationCounts();
                showToast('Notification marked as unread');
            } else {
                showToast('Failed to mark notification as unread', 'error');
            }
        });
    }

    function deleteNotification(id) {
        Swal.fire({
            title: 'Delete Notification?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                sendAction('delete_notification', id).done(res => {
                    if (res.success) {
                        const item = $(`.notification-item[data-id="${id}"]`);
                        item.addClass('removing');
                        setTimeout(() => {
                            item.remove();
                            updateNotificationCounts();
                            
                            // Check if no notifications left
                            if ($('.notification-item').length === 0) {
                                $('#notificationsList').html(`
                                    <div class="empty-state">
                                        <i class="fas fa-bell-slash"></i>
                                        <h3>No notifications found</h3>
                                        <p>You're all caught up! No notifications match your current filters.</p>
                                        <button class="btn btn-outline-primary" onclick="location.reload()">
                                            <i class="fas fa-refresh mr-2"></i>Refresh Page
                                        </button>
                                    </div>
                                `);
                            }
                        }, 300);
                        
                        showToast('Notification deleted successfully');
                        Swal.fire('Deleted!', 'The notification has been deleted.', 'success');
                    } else {
                        showToast('Failed to delete notification', 'error');
                        Swal.fire('Error!', 'Failed to delete the notification.', 'error');
                    }
                });
            }
        });
    }

    function markAllAsRead() {
        const unreadCount = $('.notification-item.unread').length;
        
        if (unreadCount === 0) {
            showToast('No unread notifications to mark', 'error');
            return;
        }
        
        Swal.fire({
            title: 'Mark All as Read?',
            text: `This will mark ${unreadCount} notifications as read.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, mark all!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                sendAction('mark_all_read').done(res => {
                    if (res.success) {
                        $('.notification-item.unread').each(function() {
                            $(this).removeClass('unread').addClass('read');
                            $(this).find('.status-indicator')
                                .removeClass('status-unread')
                                .addClass('status-read');
                        });
                        
                        updateNotificationCounts();
                        showToast(`${unreadCount} notifications marked as read`);
                        Swal.fire('Success!', 'All notifications have been marked as read.', 'success');
                        
                        // Hide mark all button
                        $('.btn-mark-all').fadeOut();
                    } else {
                        showToast('Failed to mark all notifications as read', 'error');
                        Swal.fire('Error!', 'Failed to mark notifications as read.', 'error');
                    }
                });
            }
        });
    }

    function deleteAllRead() {
        const readCount = $('.notification-item.read').length;
        
        if (readCount === 0) {
            showToast('No read notifications to delete', 'error');
            return;
        }
        
        Swal.fire({
            title: 'Delete All Read Notifications?',
            text: `This will permanently delete ${readCount} read notifications. This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete all!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                sendAction('delete_all_read').done(res => {
                    if (res.success) {
                        $('.notification-item.read').addClass('removing');
                        setTimeout(() => {
                            $('.notification-item.read').remove();
                            updateNotificationCounts();
                            
                            // Check if no notifications left
                            if ($('.notification-item').length === 0) {
                                $('#notificationsList').html(`
                                    <div class="empty-state">
                                        <i class="fas fa-bell-slash"></i>
                                        <h3>No notifications found</h3>
                                        <p>You're all caught up! No notifications match your current filters.</p>
                                        <button class="btn btn-outline-primary" onclick="location.reload()">
                                            <i class="fas fa-refresh mr-2"></i>Refresh Page
                                        </button>
                                    </div>
                                `);
                            }
                            
                            // Hide delete read button
                            $('.btn-delete-read').fadeOut();
                        }, 300);
                        
                        const deletedCount = res.deleted_count || readCount;
                        showToast(`${deletedCount} read notifications deleted`);
                        Swal.fire('Deleted!', `${deletedCount} read notifications have been deleted.`, 'success');
                    } else {
                        showToast('Failed to delete read notifications', 'error');
                        Swal.fire('Error!', 'Failed to delete read notifications.', 'error');
                    }
                });
            }
        });
    }
    
    function refreshNotifications() {
        $('.refresh-button').addClass('loading');
        $('.refresh-button i').addClass('fa-spin');
        
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
    
    function updateNotificationCounts() {
        const totalCount = $('.notification-item').length;
        const unreadCount = $('.notification-item.unread').length;
        const readCount = $('.notification-item.read').length;
        
        // Update stats display
        $('.stats-item:eq(0) .stats-number').text(totalCount);
        $('.stats-item:eq(1) .stats-number').text(unreadCount);
        
        // Update filter badges
        $('.filter-btn:contains("All") .filter-badge').text(totalCount);
        $('.filter-btn:contains("Unread") .filter-badge').text(unreadCount);
        $('.filter-btn:contains("Read") .filter-badge').text(readCount);
        
        // Show/hide action buttons
        if (unreadCount > 0) {
            $('.btn-mark-all').show();
        } else {
            $('.btn-mark-all').hide();
        }
        
        if (readCount > 0) {
            $('.btn-delete-read').show();
        } else {
            $('.btn-delete-read').hide();
        }
    }
    
    // Real-time notification updates (simulated)
    function checkForNewNotifications() {
        sendAction('get_unread_count', null, false).done(res => {
            const currentUnread = $('.notification-item.unread').length;
            if (res.count > currentUnread) {
                // New notifications available
                showToast('New notifications received! Click refresh to see them.');
                $('.refresh-button').addClass('btn-warning').removeClass('refresh-button');
            }
        }).catch(() => {
            // Silently handle errors for background requests
        });
    }
    
    // Auto-refresh notification counts every 30 seconds
    setInterval(checkForNewNotifications, 30000);
    
    // Click on notification item to mark as read
    $(document).on('click', '.notification-item.unread .notification-content', function() {
        const notificationId = $(this).closest('.notification-item').data('id');
        markAsRead(notificationId);
    });
    
    // Initialize tooltips
    $(document).ready(function() {
        $('[title]').tooltip();
        
        // Auto-hide success messages
        setTimeout(() => {
            $('.alert-success').fadeOut();
        }, 5000);
        
        // Keyboard shortcuts
        $(document).keydown(function(e) {
            // Ctrl/Cmd + A to mark all as read
            if ((e.ctrlKey || e.metaKey) && e.keyCode == 65 && $('.notification-item.unread').length > 0) {
                e.preventDefault();
                markAllAsRead();
            }
            
            // Ctrl/Cmd + D to delete all read
            if ((e.ctrlKey || e.metaKey) && e.keyCode == 68 && $('.notification-item.read').length > 0) {
                e.preventDefault();
                deleteAllRead();
            }
            
            // Ctrl/Cmd + R to refresh
            if ((e.ctrlKey || e.metaKey) && e.keyCode == 82) {
                e.preventDefault();
                refreshNotifications();
            }
            
            // Escape to close action menus
            if (e.keyCode == 27) {
                $('.action-menu').removeClass('show');
            }
        });
        
        // Focus search on Ctrl/Cmd + F
        $(document).keydown(function(e) {
            if ((e.ctrlKey || e.metaKey) && e.keyCode == 70) {
                e.preventDefault();
                $('#searchInput').focus();
            }
        });
        
        // Update counts on page load
        updateNotificationCounts();
    });
    
    // Add loading animation for buttons
    function addButtonLoading(button) {
        const $btn = $(button);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin mr-1"></i> Processing...');
        
        return function() {
            $btn.prop('disabled', false).html(originalText);
        };
    }
    
    // Enhanced error handling
    $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
        console.error('AJAX Error:', thrownError);
        showToast('Connection error. Please try again.', 'error');
        $('#notificationsList').removeClass('loading');
    });
    
    // Service Worker for push notifications (if supported)
    if ('serviceWorker' in navigator && 'Notification' in window) {
        navigator.serviceWorker.ready.then(function(registration) {
            // Service worker is ready for push notifications
            console.log('Service Worker ready for notifications');
        });
    }
</script>
</body>
</html>