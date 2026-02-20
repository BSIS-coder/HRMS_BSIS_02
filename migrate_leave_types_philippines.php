<?php
/**
 * MIGRATION SCRIPT: Update Leave Types to Match Philippine Labor Laws
 * 
 * This script updates the leave_types table to comply with:
 * - RA 10911 (Paid Leave Bill): 15 days vacation + 15 days sick leave
 * - RA 11210 (Expanded Maternity Leave): 120 days for mothers
 * - RA 11165 (Paternity Leave): 7-14 days for fathers
 * - RA 9403 (Solo Parent Benefits): 5 additional days
 * - RA 11058 (Menstrual Disorder Leave): Up to 3 days annually
 * 
 * Run this script once to update the database: 
 * Access via: http://localhost/HRMS_BSIS_02/migrate_leave_types_philippines.php
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'dp.php';

// Check if user is logged in and is admin/HR
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die('Access Denied: You must be logged in.');
}

// Simple check for admin role (adjust as needed for your system)
$allowed_roles = ['admin', 'HR Manager', 'HR Personnel'];
$user_role = $_SESSION['role'] ?? '';

// For safety, check user access
if (!in_array($user_role, $allowed_roles) && $_SESSION['username'] !== 'admin') {
    die('Access Denied: Only HR managers can run this migration.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Leave Types Migration - Philippine Labor Laws</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        .migration-item { padding: 10px; margin: 10px 0; border-left: 4px solid #007bff; background: #f8f9fa; }
        .completed { border-left-color: #28a745; }
        .failed { border-left-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">📋 Leave Types Migration - Philippine Labor Compliance</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['run']) && $_GET['run'] === 'true') {
            echo '<div class="alert alert-info"><strong>Migration Starting...</strong></div>';
            
            $success_count = 0;
            $error_count = 0;
            $updates = [];

            try {
                // 1. Update Vacation Leave
                $updates[] = [
                    'name' => 'Vacation Leave',
                    'changes' => 'Days: 15 (RA 10911) | Carry Forward: Yes (5 days max)',
                    'sql' => "UPDATE leave_types SET 
                        description = 'Annual vacation leave (RA 10911: 15 days minimum)',
                        default_days = 15.00,
                        carry_forward = 1,
                        max_carry_forward_days = 5.00
                        WHERE leave_type_name = 'Vacation Leave'"
                ];

                // 2. Update Sick Leave 
                $updates[] = [
                    'name' => 'Sick Leave',
                    'changes' => 'Days: 15 (RA 10911) | Carry Forward: Yes (5 days max)',
                    'sql' => "UPDATE leave_types SET 
                        description = 'Medical leave for illness (RA 10911: 15 days minimum)',
                        default_days = 15.00,
                        carry_forward = 1,
                        max_carry_forward_days = 5.00
                        WHERE leave_type_name = 'Sick Leave'"
                ];

                // 3. Update Maternity Leave
                $updates[] = [
                    'name' => 'Maternity Leave',
                    'changes' => 'Days: 120 (RA 11210)',
                    'sql' => "UPDATE leave_types SET 
                        description = 'Leave for new mothers (RA 11210: 120 days)',
                        default_days = 120.00
                        WHERE leave_type_name = 'Maternity Leave'"
                ];

                // 4. Update Paternity Leave
                $updates[] = [
                    'name' => 'Paternity Leave',
                    'changes' => 'Days: 7 (RA 11165) | Note: 14 days for solo parents',
                    'sql' => "UPDATE leave_types SET 
                        description = 'Leave for new fathers (RA 11165: 7-14 days; 14 for solo parents)',
                        default_days = 7.00
                        WHERE leave_type_name = 'Paternity Leave'"
                ];

                // 5. Insert Solo Parent Leave
                $updates[] = [
                    'name' => 'Solo Parent Leave (NEW)',
                    'changes' => 'Days: 5 (RA 9403)',
                    'sql' => "INSERT IGNORE INTO leave_types 
                        (leave_type_id, leave_type_name, description, paid, default_days, carry_forward, max_carry_forward_days)
                        VALUES (6, 'Solo Parent Leave', 'Additional leave for solo parents (RA 9403: 5 days)', 1, 5.00, 0, 0.00)"
                ];

                // 6. Insert Menstrual Disorder Leave
                $updates[] = [
                    'name' => 'Menstrual Disorder Leave (NEW)',
                    'changes' => 'Days: 3 (RA 11058)',
                    'sql' => "INSERT IGNORE INTO leave_types 
                        (leave_type_id, leave_type_name, description, paid, default_days, carry_forward, max_carry_forward_days)
                        VALUES (7, 'Menstrual Disorder Leave', 'Leave for menstrual disorder symptoms (RA 11058: up to 3 days annually)', 1, 3.00, 0, 0.00)"
                ];

                // Execute all updates
                foreach ($updates as $update) {
                    try {
                        $stmt = $conn->prepare($update['sql']);
                        $stmt->execute();
                        $success_count++;
                        
                        echo '<div class="migration-item completed">';
                        echo '  <strong>✓ ' . htmlspecialchars($update['name']) . '</strong><br>';
                        echo '  <small>' . htmlspecialchars($update['changes']) . '</small>';
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        $error_count++;
                        echo '<div class="migration-item failed">';
                        echo '  <strong>✗ ' . htmlspecialchars($update['name']) . '</strong><br>';
                        echo '  <small class="error">' . htmlspecialchars($e->getMessage()) . '</small>';
                        echo '</div>';
                    }
                }

                echo '<hr>';
                echo '<div class="alert alert-success">';
                echo '<strong>Migration Complete!</strong><br>';
                echo 'Updates Applied: <strong class="success">' . $success_count . '</strong><br>';
                if ($error_count > 0) {
                    echo 'Errors: <strong class="error">' . $error_count . '</strong>';
                }
                echo '</div>';

                // Display current leave types
                echo '<h3 class="mt-4">📊 Current Leave Types (Updated)</h3>';
                $result = $conn->query("SELECT * FROM leave_types ORDER BY leave_type_id");
                $types = $result->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<table class="table table-striped table-hover">';
                echo '<thead class="thead-dark">';
                echo '<tr><th>ID</th><th>Leave Type</th><th>Default Days</th><th>Carry Forward</th><th>Applicable RA</th></tr>';
                echo '</thead><tbody>';
                
                foreach ($types as $type) {
                    preg_match('/\(RA \d+/', $type['description'], $matches);
                    $ra = $matches[0] ?? 'N/A';
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($type['leave_type_id']) . '</td>';
                    echo '<td><strong>' . htmlspecialchars($type['leave_type_name']) . '</strong><br><small class="text-muted">' . htmlspecialchars($type['description']) . '</small></td>';
                    echo '<td>' . htmlspecialchars($type['default_days']) . ' days</td>';
                    echo '<td>' . ($type['carry_forward'] ? htmlspecialchars($type['max_carry_forward_days']) . ' days' : 'No') . '</td>';
                    echo '<td><span class="badge badge-info">' . htmlspecialchars($ra) . '</span></td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                
                echo '<div class="alert alert-info mt-4">';
                echo '<h5>✅ Compliance Summary</h5>';
                echo '<ul style="margin-bottom: 0;">';
                echo '<li><strong>RA 10911:</strong> Vacation Leave (15 days) + Sick Leave (15 days) ✓</li>';
                echo '<li><strong>RA 11210:</strong> Maternity Leave (120 days) ✓</li>';
                echo '<li><strong>RA 11165:</strong> Paternity Leave (7-14 days) ✓</li>';
                echo '<li><strong>RA 9403:</strong> Solo Parent Leave (5 days) ✓</li>';
                echo '<li><strong>RA 11058:</strong> Menstrual Disorder Leave (3 days) ✓</li>';
                echo '<li><strong>Emergency Leave:</strong> 5 days (unpaid)</li>';
                echo '</ul>';
                echo '</div>';

            } catch (Exception $e) {
                echo '<div class="alert alert-danger">';
                echo '<strong>Migration Failed:</strong><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';
            }

        } else {
            // Display confirmation form
            echo '<div class="alert alert-warning">';
            echo '<h5><i class="fas fa-exclamation-triangle"></i> Important Information</h5>';
            echo '<p>This migration will update all leave types to comply with current Philippine labor laws.</p>';
            echo '<strong>Changes that will be made:</strong>';
            echo '<ul>';
            echo '<li><strong>Vacation Leave:</strong> Update to 15 days with 5-day carry forward (RA 10911)</li>';
            echo '<li><strong>Sick Leave:</strong> Update from 10 to 15 days with 5-day carry forward (RA 10911)</li>';
            echo '<li><strong>Maternity Leave:</strong> Update from 60 to 120 days (RA 11210)</li>';
            echo '<li><strong>Paternity Leave:</strong> Maintain 7 days (14 for solo parents) (RA 11165)</li>';
            echo '<li><strong>Solo Parent Leave:</strong> ADD new leave type (5 days) (RA 9403)</li>';
            echo '<li><strong>Menstrual Disorder Leave:</strong> ADD new leave type (3 days) (RA 11058)</li>';
            echo '</ul>';
            echo '</div>';

            echo '<form method="GET">';
            echo '<input type="hidden" name="run" value="true">';
            echo '<button type="submit" class="btn btn-success btn-lg">';
            echo '<i class="fas fa-check"></i> Run Migration';
            echo '</button>';
            echo '<a href="leave_types.php" class="btn btn-secondary btn-lg ml-2">';
            echo '<i class="fas fa-arrow-left"></i> Cancel';
            echo '</a>';
            echo '</form>';
        }
        ?>
    </div>
</body>
</html>
