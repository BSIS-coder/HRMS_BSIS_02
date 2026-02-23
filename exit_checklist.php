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

// Include database connection and helper functions
require_once 'dp.php';

// Database connection
$host = getenv('DB_HOST') ?? 'localhost';
$dbname = getenv('DB_NAME') ?? 'hr_system';
$username = getenv('DB_USER') ?? 'root';
$password = getenv('DB_PASS') ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new checklist item
                try {
                    // Check if new columns exist, if not use basic insert
                    $stmt = $pdo->prepare("INSERT INTO exit_checklist (exit_id, item_name, description, responsible_department, status, completed_date, notes, item_type, serial_number, approval_status, approved_by, approved_date, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['exit_id'],
                        $_POST['item_name'],
                        $_POST['description'],
                        $_POST['responsible_department'],
                        $_POST['status'],
                        !empty($_POST['completed_date']) ? $_POST['completed_date'] : null,
                        $_POST['notes'],
                        $_POST['item_type'] ?? 'Other',
                        $_POST['serial_number'] ?? '',
                        $_POST['approval_status'] ?? 'Pending',
                        $_POST['approved_by'] ?? null,
                        null,
                        $_POST['remarks'] ?? ''
                    ]);
                    $message = "Exit checklist item added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    // Fallback: Try without new columns if they don't exist
                    if (strpos($e->getMessage(), 'Unknown column') !== false) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO exit_checklist (exit_id, item_name, description, responsible_department, status, completed_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $_POST['exit_id'],
                                $_POST['item_name'],
                                $_POST['description'],
                                $_POST['responsible_department'],
                                $_POST['status'],
                                !empty($_POST['completed_date']) ? $_POST['completed_date'] : null,
                                $_POST['notes']
                            ]);
                            $message = "Exit checklist item added successfully! (Note: Some fields not available - update database schema)";
                            $messageType = "success";
                        } catch (PDOException $e2) {
                            $message = "Error adding checklist item: " . $e2->getMessage();
                            $messageType = "error";
                        }
                    } else {
                        $message = "Error adding checklist item: " . $e->getMessage();
                        $messageType = "error";
                    }
                }
                break;
            
            case 'update':
    // Update checklist item
    try {
        $approved_date = null;
        
        // Auto-set approval date when approved
        if ($_POST['approval_status'] === 'Approved' && !empty($_POST['approved_by'])) {
            $approved_date = date('Y-m-d');
        }
        
        $stmt = $pdo->prepare("UPDATE exit_checklist SET exit_id=?, item_name=?, description=?, responsible_department=?, status=?, completed_date=?, notes=?, item_type=?, serial_number=?, approval_status=?, approved_by=?, approved_date=?, remarks=? WHERE checklist_id=?");
        $stmt->execute([
            $_POST['exit_id'],
            $_POST['item_name'],
            $_POST['description'],
            $_POST['responsible_department'],
            $_POST['status'],
            !empty($_POST['completed_date']) ? $_POST['completed_date'] : null,
            $_POST['notes'],
            $_POST['item_type'] ?? 'Other',
            $_POST['serial_number'] ?? '',
            $_POST['approval_status'] ?? 'Pending',
            $_POST['approved_by'] ?? null,
            $approved_date,
            $_POST['remarks'] ?? '',
            $_POST['checklist_id']
        ]);
        $message = "Exit checklist item updated successfully!";
        $messageType = "success";
    } catch (PDOException $e) {
        // Fallback code remains the same
        $message = "Error updating checklist item: " . $e->getMessage();
        $messageType = "error";
    }
    break;
            
            case 'delete':
                // Delete checklist item
                try {
                    $stmt = $pdo->prepare("DELETE FROM exit_checklist WHERE checklist_id=?");
                    $stmt->execute([$_POST['checklist_id']]);
                    $message = "Exit checklist item deleted successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting checklist item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'bulk_update':
                // Bulk update checklist items status
                try {
                    $pdo->beginTransaction();
                    if (isset($_POST['checklist_items']) && is_array($_POST['checklist_items'])) {
                        foreach ($_POST['checklist_items'] as $item_id => $item_data) {
                            $stmt = $pdo->prepare("UPDATE exit_checklist SET status=?, completed_date=?, notes=? WHERE checklist_id=?");
                            $completed_date = ($item_data['status'] === 'Completed' && empty($item_data['completed_date'])) 
                                ? date('Y-m-d') 
                                : (!empty($item_data['completed_date']) ? $item_data['completed_date'] : null);
                            
                            $stmt->execute([
                                $item_data['status'],
                                $completed_date,
                                $item_data['notes'] ?? '',
                                $item_id
                            ]);
                        }
                    }
                    $pdo->commit();
                    $message = "Checklist items updated successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Error updating checklist items: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'approve':
                // Approve checklist item
                try {
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET approval_status=?, approved_by=?, approved_date=? WHERE checklist_id=?");
                    $stmt->execute([
                        'Approved',
                        $_POST['approved_by'] ?? $_SESSION['user_id'] ?? 'System',
                        date('Y-m-d'),
                        $_POST['checklist_id']
                    ]);
                    $message = "Checklist item approved successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error approving item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'reject':
                // Reject checklist item
                try {
                    $stmt = $pdo->prepare("UPDATE exit_checklist SET approval_status=?, remarks=? WHERE checklist_id=?");
                    $stmt->execute([
                        'Rejected',
                        $_POST['rejection_remarks'] ?? '',
                        $_POST['checklist_id']
                    ]);
                    $message = "Checklist item rejected successfully!";
                    $messageType = "error";
                } catch (PDOException $e) {
                    $message = "Error rejecting item: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch checklist items with related exit data
$stmt = $pdo->query("
    SELECT 
        ec.*,
        e.employee_id,
        e.exit_date,
        e.exit_type,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM exit_checklist ec
    LEFT JOIN exits e ON ec.exit_id = e.exit_id
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY ec.created_at DESC
");
$checklistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch exits for dropdown
$stmt = $pdo->query("
    SELECT 
        e.exit_id,
        e.exit_date,
        e.exit_type,
        CONCAT(pi.first_name, ' ', pi.last_name) as employee_name,
        ep.employee_number
    FROM exits e
    LEFT JOIN employee_profiles ep ON e.employee_id = ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
    ORDER BY e.exit_date DESC
");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Departments for dropdown
$departments = [
    'HR', 'IT', 'Finance', 'Security', 'Operations', 
    'Facilities', 'Legal', 'Marketing', 'Sales', 'Management'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exit Checklist Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* Additional custom styles for exit checklist page */
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
        
        .container-fluid {
            padding: 0;
        }
        
        .row {
            margin-right: 0;
            margin-left: 0;
        }

        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
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
            border-color: var(--azure-blue);
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
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            background: linear-gradient(135deg, var(--azure-blue-light) 0%, var(--azure-blue-dark) 100%);
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

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
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
            background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--azure-blue-lighter);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-not-applicable {
            background: #d1ecf1;
            color: #0c5460;
        }

        .priority-high {
            border-left: 4px solid #dc3545;
        }

        .priority-medium {
            border-left: 4px solid #ffc107;
        }

        .priority-low {
            border-left: 4px solid #28a745;
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
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
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
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
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
            color: var(--azure-blue-dark);
        }

        .form-control {
            width: 100%;
            padding: 6px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
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

        .bulk-update-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .checklist-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .checklist-item h5 {
            margin: 0 0 10px 0;
            color: var(--azure-blue-dark);
        }

        .quick-status {
            display: inline-flex;
            gap: 10px;
            margin-top: 10px;
        }

        .quick-status button {
            padding: 5px 10px;
            font-size: 12px;
        }

        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            height: 100%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            display: flex;
            align-items: center;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 1.8rem;
            margin-right: 15px;
            padding: 12px;
            border-radius: 8px;
            background: rgba(255,255,255,0.9);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .stat-info {
            flex: 1;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 600;
            line-height: 1.2;
            margin-bottom: 5px;
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .approval-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .approval-pending {
            background: #fff3cd;
            color: #856404;
        }

        .approval-approved {
            background: #d4edda;
            color: #155724;
        }

        .approval-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .item-type-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-right: 5px;
        }

        .type-physical {
            background: #cfe2ff;
            color: #084298;
        }

        .type-document {
            background: #e2e3e5;
            color: #383d41;
        }

        .type-access {
            background: #d1ecf1;
            color: #0c5460;
        }

        .type-financial {
            background: #f8d7da;
            color: #721c24;
        }

        .type-other {
            background: #e7d4f5;
            color: #6f42c1;
        }

        .physical-details {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-top: 8px;
            width: 100%;
            box-sizing: border-box;
        }

        .detail-tag {
            display: block;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .detail-tag strong {
            color: var(--azure-blue-dark);
            font-size: 14px;
        }

        .approval-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--azure-blue);
        }

        .approval-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .clearance-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
            font-weight: 600;
            color: #333;
        }

        .clearance-status.cleared {
            border-color: #28a745;
            background: #d4edda;
            color: #155724;
        }

        .clearance-status.pending {
            border-color: #ffc107;
            background: #fff3cd;
            color: #856404;
        }

        .remarks-box {
            background: #fff8dc;
            padding: 12px;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
            margin: 10px 0;
            font-style: italic;
            color: #333;
        }

        .approval-info {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            padding: 8px;
            background: white;
            border-radius: 4px;
        }

        /* NEW FEATURES STYLES */
        .feature-section {
            background: white;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .feature-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;              /* Space between title and button */
    flex-wrap: wrap;        /* Better responsive handling */
}

        .feature-header h3 {
    flex: 1;                /* Takes available space */
    min-width: 200px;       /* Minimum space for title */
    white-space: nowrap;    /* Prevents text wrapping */
}

       .feature-header .btn {
    flex-shrink: 0;         /* Button stays compact */
    white-space: nowrap;
}

        .feature-content {
            padding: 20px;
        }

        .clearance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .clearance-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .clearance-card:hover {
            border-color: var(--azure-blue);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.2);
            transform: translateY(-3px);
        }

        .clearance-card.pending {
            border-left: 4px solid #ffc107;
        }

        .clearance-card.approved {
            border-left: 4px solid #28a745;
            background: #f0fff4;
        }

        .clearance-card.returned {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }

        .department-label {
            font-weight: 600;
            color: var(--azure-blue-dark);
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .clearance-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin: 8px 0;
        }

        .clearance-status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .clearance-status-badge.approved {
            background: #d4edda;
            color: #155724;
        }

        .clearance-status-badge.returned {
            background: #f8d7da;
            color: #721c24;
        }

        .asset-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .asset-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid var(--azure-blue);
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .asset-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .asset-table tbody tr:hover {
            background: var(--azure-blue-pale);
        }

        .status-toggle {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            background: #ccc;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .toggle-switch.active {
            background: #28a745;
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: left 0.3s ease;
        }

        .toggle-switch.active::after {
            left: 28px;
        }

        .condition-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .condition-good {
            background: #d4edda;
            color: #155724;
        }

        .condition-damaged {
            background: #fff3cd;
            color: #856404;
        }

        .condition-lost {
            background: #f8d7da;
            color: #721c24;
        }

        .progress-indicator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
        }

        .progress-step::after {
            content: '';
            position: absolute;
            top: 20px;
            right: -25px;
            width: 50px;
            height: 2px;
            background: #e0e0e0;
        }

        .progress-step:last-child::after {
            display: none;
        }

        .progress-circle {
            width: 40px;
            height: 40px;
            background: #e0e0e0;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        .progress-circle.completed {
            background: #28a745;
        }

        .progress-circle.current {
            background: var(--azure-blue);
            box-shadow: 0 0 15px rgba(233, 30, 99, 0.4);
        }

        .progress-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #333;
        }

        .system-account-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .system-account-item.deactivated {
            border-left-color: #28a745;
            background: #f0fff4;
        }

        .system-account-item.active {
            border-left-color: var(--azure-blue);
        }

        .account-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .account-name {
            font-weight: 600;
            color: var(--azure-blue-dark);
            font-size: 1rem;
        }

        .deactivation-timestamp {
            font-size: 0.85rem;
            color: #666;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e0e0e0;
        }

        .proof-upload {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #e0e0e0;
            text-align: center;
        }

        .proof-upload:hover {
            border-color: var(--azure-blue);
            background: var(--azure-blue-pale);
        }

        .upload-input {
            display: none;
        }

        .file-name {
            font-size: 0.9rem;
            color: #333;
            margin-top: 8px;
        }

        .clearance-progress {
            display: flex;
            gap: 5px;
            margin: 10px 0;
        }

        .progress-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
        }

        .progress-dot.completed {
            background: #28a745;
        }

        .progress-dot.approved {
            background: var(--azure-blue);
        }

        .progress-dot.returned {
            background: #dc3545;
        }
        
        @media (max-width: 768px) {
            .clearance-grid {
                grid-template-columns: 1fr;
            }

            .feature-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .feature-header h3 {
                margin: 0;
                white-space: normal;
                text-align: center;
            }

            .feature-header .btn {
                width: 100%;
                text-align: center;
            }

            .asset-table {
                font-size: 0.85rem;
            }

            .progress-indicator {
                flex-direction: column;
            }

            .progress-step::after {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .form-row {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }

            .content {
                padding: 20px;
            }

            .physical-details {
                font-size: 12px;
            }

            .approval-buttons {
                flex-direction: column;
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
                <h2 class="section-title">Exit Checklist Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by employee name, department, or item...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            ➕ Add Checklist Item
                        </button>
                    </div>

                    <!-- Statistics Section -->
                    <div class="stats-container">
                        <?php
                        $totalItems = count($checklistItems);
                        $completedItems = count(array_filter($checklistItems, function($item) { return $item['status'] === 'Completed'; }));
                        $pendingItems = count(array_filter($checklistItems, function($item) { return $item['status'] === 'Pending'; }));
                        ?>
                        <div class="stat-card">
                            <i class="fas fa-tasks stat-icon" style="color: var(--azure-blue); background: var(--azure-blue-pale);"></i>
                            <div class="stat-info">
                                <div class="stat-number"><?= $totalItems ?></div>
                                <div class="stat-label">Total Items</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-check-circle stat-icon" style="color: #28a745; background: #d4edda;"></i>
                            <div class="stat-info">
                                <div class="stat-number" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= $completedItems ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-clock stat-icon" style="color: #ffc107; background: #fff3cd;"></i>
                            <div class="stat-info">
                                <div class="stat-number" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?= $pendingItems ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($checklistItems)): ?>
                    <?php
                    // Calculate progress statistics
                    $totalItems = count($checklistItems);
                    $completedItems = array_filter($checklistItems, function($item) { return $item['status'] === 'Completed'; });
                    $completedCount = count($completedItems);
                    $progressPercentage = $totalItems > 0 ? round(($completedCount / $totalItems) * 100) : 0;
                    ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progressPercentage ?>%">
                            <?= $completedCount ?> of <?= $totalItems ?> completed (<?= $progressPercentage ?>%)
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="table-container">
                        <table class="table" id="checklistTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Item & Type</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Approval</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="checklistTableBody">
                                <?php foreach ($checklistItems as $item): ?>
                                <tr class="priority-medium" data-checklist-id="<?= $item['checklist_id'] ?>">
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($item['employee_name']) ?></strong><br>
                                            <small style="color: #666;">
                                                #<?= htmlspecialchars($item['employee_number']) ?> | 
                                                Exit: <?= date('M d, Y', strtotime($item['exit_date'])) ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="item-type-badge type-<?= strtolower(str_replace(' ', '', $item['item_type'])) ?>">
                                                <?= htmlspecialchars($item['item_type']) ?>
                                            </span>
                                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                        </div>
                                        <?php if ($item['item_type'] === 'Physical' && $item['serial_number']): ?>
                                        <div class="physical-details">
                                            <div class="detail-tag">SN: <strong><?= htmlspecialchars($item['serial_number']) ?></strong></div>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['responsible_department']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower(str_replace([' ', '-'], ['', ''], $item['status'])) ?>">
                                            <?= htmlspecialchars($item['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="approval-badge approval-<?= strtolower($item['approval_status']) ?>">
                                            <?= htmlspecialchars($item['approval_status']) ?>
                                        </span>
                                        <?php if ($item['approval_status'] === 'Approved'): ?>
                                        <div class="approval-info">
                                            ✓ <?= htmlspecialchars($item['approved_by']) ?><br>
                                            <?= date('M d, Y', strtotime($item['approved_date'])) ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-small" onclick="viewChecklistDetails(<?= $item['checklist_id'] ?>)">
                                            👁️ View
                                        </button>
                                        <button class="btn btn-warning btn-small" onclick="editChecklistItem(<?= $item['checklist_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($checklistItems)): ?>
                        <div class="no-results">
                            <i>📋</i>
                            <h3>No checklist items found</h3>
                            <p>Start by adding checklist items for employee exits.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Checklist Item Modal -->
    <div id="checklistModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Checklist Item</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="checklistForm" method="POST">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="checklist_id" name="checklist_id">

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="exit_id">Employee Exit</label>
                                <select id="exit_id" name="exit_id" class="form-control" required>
                                    <option value="">Select employee exit...</option>
                                    <?php foreach ($exits as $exit): ?>
                                    <option value="<?= $exit['exit_id'] ?>">
                                        <?= htmlspecialchars($exit['employee_name']) ?> 
                                        (#<?= htmlspecialchars($exit['employee_number']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="item_type">Item Type</label>
                                <select id="item_type" name="item_type" class="form-control" required onchange="togglePhysicalDetails()">
                                    <option value="Physical">Physical Item</option>
                                    <option value="Document">Document</option>
                                    <option value="Access">Access/Permission</option>
                                    <option value="Financial">Financial</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="item_name">Item Name</label>
                                <input type="text" id="item_name" name="item_name" class="form-control" required 
                                       placeholder="e.g., Company Laptop">
                            </div>
                        </div>
                    </div>

                    <!-- Physical Item Details -->
                    <div id="physicalDetails" style="display: none;">
                        <div class="approval-section">
                            <h5 style="color: var(--azure-blue-dark); margin-top: 0;">📦 Physical Item Details</h5>
                            <div class="form-group">
                                <label for="serial_number">Serial Number</label>
                                <input type="text" id="serial_number" name="serial_number" class="form-control" 
                                       placeholder="e.g., SN123456789">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="responsible_department">Responsible Department</label>
                                <select id="responsible_department" name="responsible_department" class="form-control" required>
                                    <option value="">Select department...</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept ?>"><?= $dept ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="Pending">Pending</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="2" 
                                  placeholder="Detailed description of the checklist item..."></textarea>
                    </div>

                    <!-- Approval Section -->
                    <div class="approval-section">
                        <h5 style="color: var(--azure-blue-dark); margin-top: 0;">✓ Approval</h5>
                        
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="approval_status">Approval Status</label>
                                    <select id="approval_status" name="approval_status" class="form-control" required>
                                        <option value="Pending">Pending</option>
                                        <option value="Approved">Approved</option>
                                        <option value="Rejected">Rejected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="approved_by">Approved By (Name/ID)</label>
                                    <input type="text" id="approved_by" name="approved_by" class="form-control" 
                                           placeholder="Approver name or employee ID">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="completed_date">Completion Date</label>
                            <input type="date" id="completed_date" name="completed_date" class="form-control">
                        </div>
                    </div>

                    <!-- Remarks Section -->
                    <div class="approval-section" style="border-left-color: #ffc107;">
                        <h5 style="color: #856404; margin-top: 0;">📝 Remarks & Notes</h5>
                        
                        <div class="form-group">
                            <label for="remarks">Remarks/Comments</label>
                            <textarea id="remarks" name="remarks" class="form-control" rows="3" 
                                      placeholder="Any remarks, exceptions, or special notes..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="notes">Internal Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="2" 
                                      placeholder="Internal notes for staff reference..."></textarea>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Checklist Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Checklist Item Details</h2>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Dynamically populated -->
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Approve/Reject Item</h2>
                <span class="close" onclick="closeApprovalModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="approvalForm" method="POST">
                    <input type="hidden" name="checklist_id" id="approval_checklist_id">
                    <input type="hidden" name="action" id="approval_action" value="approve">

                    <div class="form-group">
                        <label for="approval_remarks">Decision Remarks</label>
                        <textarea id="approval_remarks" name="rejection_remarks" class="form-control" rows="3" 
                                  placeholder="Enter any remarks for approval/rejection..."></textarea>
                    </div>

                    <div class="approval-buttons">
                        <button type="button" class="btn btn-success" onclick="submitApproval('approve')">
                            ✓ Approve
                        </button>
                        <button type="button" class="btn" style="background: #dc3545; color: white;" onclick="submitApproval('reject')">
                            ✗ Reject
                        </button>
                        <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="closeApprovalModal()">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let checklistData = <?= json_encode($checklistItems) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('checklistTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Toggle physical item details visibility
        function togglePhysicalDetails() {
            const itemType = document.getElementById('item_type').value;
            const physicalDetails = document.getElementById('physicalDetails');
            
            if (itemType === 'Physical') {
                physicalDetails.style.display = 'block';
            } else {
                physicalDetails.style.display = 'none';
                document.getElementById('serial_number').value = '';
            }
        }

        // Modal functions
        function openModal(mode, checklistId = null) {
            const modal = document.getElementById('checklistModal');
            const form = document.getElementById('checklistForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add Checklist Item';
                action.value = 'add';
                form.reset();
                document.getElementById('checklist_id').value = '';
                document.getElementById('item_type').value = 'Physical';
                togglePhysicalDetails();
            } else if (mode === 'edit' && checklistId) {
                title.textContent = 'Edit Checklist Item';
                action.value = 'update';
                document.getElementById('checklist_id').value = checklistId;
                populateEditForm(checklistId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('checklistModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(checklistId) {
            const item = checklistData.find(item => item.checklist_id == checklistId);
            if (item) {
                document.getElementById('exit_id').value = item.exit_id || '';
                document.getElementById('item_type').value = item.item_type || 'Physical';
                document.getElementById('item_name').value = item.item_name || '';
                document.getElementById('serial_number').value = item.serial_number || '';
                document.getElementById('description').value = item.description || '';
                document.getElementById('responsible_department').value = item.responsible_department || '';
                document.getElementById('status').value = item.status || '';
                document.getElementById('completed_date').value = item.completed_date || '';
                document.getElementById('approval_status').value = item.approval_status || 'Pending';
                document.getElementById('approved_by').value = item.approved_by || '';
                document.getElementById('remarks').value = item.remarks || '';
                document.getElementById('notes').value = item.notes || '';
                togglePhysicalDetails();
            }
        }

        function editChecklistItem(checklistId) {
            openModal('edit', checklistId);
        }

        function viewChecklistDetails(checklistId) {
            const item = checklistData.find(item => item.checklist_id == checklistId);
            if (item) {
                const physicalInfo = item.item_type === 'Physical' && item.serial_number ? `
                    <div style="margin: 15px 0;">
                        <strong style="color: var(--azure-blue-dark);">📦 Physical Item Details:</strong>
                        <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <div><strong>Serial Number:</strong> ${item.serial_number}</div>
                        </div>
                    </div>
                ` : '';

                const approvalInfo = `
                    <div style="margin: 15px 0; padding: 12px; background: #f8f9fa; border-left: 4px solid var(--azure-blue); border-radius: 6px;">
                        <strong style="color: var(--azure-blue-dark);">✓ Approval Information:</strong>
                        <div style="margin-top: 8px; font-size: 14px;">
                            <div><strong>Status:</strong> <span class="approval-badge approval-${item.approval_status.toLowerCase()}">${item.approval_status}</span></div>
                            ${item.approved_by ? `<div style="margin-top: 5px;"><strong>Approved By:</strong> ${item.approved_by}</div>` : ''}
                            ${item.approved_date ? `<div style="margin-top: 5px;"><strong>Approval Date:</strong> ${new Date(item.approved_date).toLocaleDateString()}</div>` : ''}
                        </div>
                    </div>
                `;

                const remarksInfo = item.remarks ? `
                    <div class="remarks-box">
                        <strong>📝 Remarks:</strong><br>
                        ${item.remarks}
                    </div>
                ` : '';

                const detailsHTML = `
                    <div style="padding: 20px;">
                        <h4 style="color: var(--azure-blue); margin-bottom: 20px;">Item: ${item.item_name}</h4>
                        
                        <div style="margin: 15px 0;">
                            <strong>Employee:</strong> ${item.employee_name} (#${item.employee_number})
                        </div>
                        
                        <div style="margin: 15px 0;">
                            <strong>Item Type:</strong> <span class="item-type-badge type-${item.item_type.toLowerCase()}">${item.item_type}</span>
                        </div>
                        
                        ${physicalInfo}
                        
                        <div style="margin: 15px 0;">
                            <strong>Department:</strong> ${item.responsible_department}
                        </div>
                        
                        <div style="margin: 15px 0;">
                            <strong>Status:</strong> <span class="status-badge status-${item.status.toLowerCase()}">${item.status}</span>
                        </div>
                        
                        ${approvalInfo}
                        
                        ${remarksInfo}
                        
                        ${item.description ? `<div style="margin: 15px 0;"><strong>Description:</strong><br>${item.description}</div>` : ''}
                        
                        ${item.notes ? `<div style="margin: 15px 0; padding: 10px; background: #f0f0f0; border-radius: 6px;"><strong>Internal Notes:</strong><br>${item.notes}</div>` : ''}
                        
                        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                            <button class="btn btn-warning" onclick="editChecklistItem(${checklistId}); closeDetailsModal();">
                                ✏️ Edit
                            </button>
                            <button class="btn btn-primary" onclick="openApprovalModal(${checklistId})">
                                ✓ Approve/Reject
                            </button>
                            <button class="btn" style="background: #6c757d; color: white;" onclick="closeDetailsModal()">
                                Close
                            </button>
                        </div>
                    </div>
                `;
                
                document.getElementById('detailsContent').innerHTML = detailsHTML;
                document.getElementById('detailsModal').style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openApprovalModal(checklistId) {
            document.getElementById('approval_checklist_id').value = checklistId;
            document.getElementById('approvalModal').style.display = 'block';
        }

        function closeApprovalModal() {
            document.getElementById('approvalModal').style.display = 'none';
        }

        function submitApproval(action) {
            const checklistId = document.getElementById('approval_checklist_id').value;
            const remarks = document.getElementById('approval_remarks').value;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="${action}">
                <input type="hidden" name="checklist_id" value="${checklistId}">
                <input type="hidden" name="rejection_remarks" value="${remarks}">
                <input type="hidden" name="approved_by" value="System Admin">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        window.onclick = function(event) {
            const checklistModal = document.getElementById('checklistModal');
            const detailsModal = document.getElementById('detailsModal');
            const approvalModal = document.getElementById('approvalModal');
            
            if (event.target === checklistModal) closeModal();
            else if (event.target === detailsModal) closeDetailsModal();
            else if (event.target === approvalModal) closeApprovalModal();
        }

        // Form validation
        document.getElementById('checklistForm').addEventListener('submit', function(e) {
            const status = document.getElementById('status').value;
            const completedDate = document.getElementById('completed_date').value;
            
            if (status === 'Completed' && !completedDate) {
                if (confirm('No completion date specified. Set to today?')) {
                    document.getElementById('completed_date').value = new Date().toISOString().split('T')[0];
                } else {
                    e.preventDefault();
                    return;
                }
            }
        });

        // Auto-populate completion date when status changes to Completed
        document.getElementById('status').addEventListener('change', function() {
            const completedDateField = document.getElementById('completed_date');
            if (this.value === 'Completed' && !completedDateField.value) {
                completedDateField.value = new Date().toISOString().split('T')[0];
            } else if (this.value !== 'Completed') {
                completedDateField.value = '';
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // Initialize tooltips and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#checklistTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Update progress bar animation
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                const targetWidth = progressFill.style.width;
                progressFill.style.width = '0%';
                setTimeout(() => {
                    progressFill.style.width = targetWidth;
                }, 500);
            }

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + N for new checklist item
                if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                    e.preventDefault();
                    openModal('add');
                }
                
                // ESC to close modals
                if (e.key === 'Escape') {
                    closeModal();
                    closeBulkUpdateModal && closeBulkUpdateModal();
                }
            });
        });

        // Add some predefined checklist items templates
        function addTemplate(template) {
            const templates = {
                'it_offboarding': {
                    items: [
                        { name: 'Collect company laptop', dept: 'IT', desc: 'Retrieve and inventory company-issued laptop' },
                        { name: 'Disable user accounts', dept: 'IT', desc: 'Disable AD, email, and system access accounts' },
                        { name: 'Collect mobile device', dept: 'IT', desc: 'Retrieve company phone/tablet if issued' },
                        { name: 'Remove from security groups', dept: 'IT', desc: 'Remove from all security and distribution groups' },
                        { name: 'Backup user data', dept: 'IT', desc: 'Backup important files and transfer as needed' }
                    ]
                },
                'hr_standard': {
                    items: [
                        { name: 'Conduct exit interview', dept: 'HR', desc: 'Schedule and conduct exit interview session' },
                        { name: 'Collect ID badge', dept: 'HR', desc: 'Retrieve employee ID badge and access cards' },
                        { name: 'Process final paycheck', dept: 'HR', desc: 'Calculate and process final salary and benefits' },
                        { name: 'Update employee records', dept: 'HR', desc: 'Update HRIS with termination details' },
                        { name: 'Benefits transition', dept: 'HR', desc: 'Process COBRA and benefit continuation options' }
                    ]
                },
                'security_standard': {
                    items: [
                        { name: 'Collect access cards', dept: 'Security', desc: 'Retrieve all building and office access cards' },
                        { name: 'Update access control', dept: 'Security', desc: 'Remove from access control systems' },
                        { name: 'Collect keys', dept: 'Security', desc: 'Retrieve office, desk, and facility keys' },
                        { name: 'Escort if required', dept: 'Security', desc: 'Provide security escort during final day if needed' }
                    ]
                }
            };

            // This would be implemented as a separate feature
            console.log('Template feature can be added:', templates[template]);
        }
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
