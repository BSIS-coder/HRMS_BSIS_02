<?php
/**
 * Feedback 360 Integration Helper
 * This file provides functions to integrate 360-degree feedback data
 * across the entire HR system
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'dp.php';

/**
 * Get feedback overview for an employee
 */
function getEmployeeFeedbackOverview($employee_id) {
    global $conn;
    try {
        $sql = "
            SELECT 
                COUNT(DISTINCT freq.cycle_id) as total_cycles,
                COUNT(CASE WHEN freq.status = 'Pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN freq.status = 'Completed' THEN 1 END) as completed_count,
                MAX(fr.submitted_at) as last_feedback_date
            FROM feedback_requests freq
            LEFT JOIN feedback_responses fr ON freq.request_id = fr.request_id
            WHERE freq.employee_id = ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?? [
            'total_cycles' => 0,
            'pending_count' => 0,
            'completed_count' => 0,
            'last_feedback_date' => null
        ];
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get pending feedback requests for a user
 */
function getUserPendingFeedbackCount($user_id) {
    global $conn;
    try {
        $sql = "
            SELECT COUNT(*) as count
            FROM feedback_requests
            WHERE reviewer_id = ? AND status = 'Pending'
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Get active feedback cycles
 */
function getActiveFeedbackCycles() {
    global $conn;
    try {
        $sql = "
            SELECT *
            FROM feedback_cycles
            WHERE status = 'Active'
            ORDER BY end_date DESC
        ";
        
        $result = $conn->query($sql);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get average feedback score for an employee
 */
function getEmployeeFeedbackScore($employee_id) {
    global $conn;
    try {
        $sql = "
            SELECT 
                ROUND(AVG(
                    (JSON_EXTRACT(fr.responses, '$.leadership') +
                     JSON_EXTRACT(fr.responses, '$.communication') +
                     JSON_EXTRACT(fr.responses, '$.teamwork') +
                     JSON_EXTRACT(fr.responses, '$.problem_solving') +
                     JSON_EXTRACT(fr.responses, '$.work_quality')) / 5
                ), 1) as average_score
            FROM feedback_responses fr
            LEFT JOIN feedback_requests freq ON fr.request_id = freq.request_id
            WHERE freq.employee_id = ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (float)($result['average_score'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Display feedback badge for employee profile
 */
function displayFeedbackBadge($employee_id) {
    $score = getEmployeeFeedbackScore($employee_id);
    if ($score > 0) {
        $color = $score >= 4 ? 'success' : ($score >= 3 ? 'warning' : 'danger');
        return '<span class="badge badge-' . $color . '">Feedback: ' . $score . '/5</span>';
    }
    return '';
}

/**
 * Get feedback comparison data for multiple employees
 */
function getFeedbackComparison($employee_ids) {
    global $conn;
    try {
        $placeholders = implode(',', array_fill(0, count($employee_ids), '?'));
        $sql = "
            SELECT 
                freq.employee_id,
                pi.first_name,
                pi.last_name,
                ROUND(AVG(
                    (JSON_EXTRACT(IFNULL(fr.responses, '{}'), '$.leadership') +
                     JSON_EXTRACT(IFNULL(fr.responses, '{}'), '$.communication') +
                     JSON_EXTRACT(IFNULL(fr.responses, '{}'), '$.teamwork') +
                     JSON_EXTRACT(IFNULL(fr.responses, '{}'), '$.problem_solving') +
                     JSON_EXTRACT(IFNULL(fr.responses, '{}'), '$.work_quality')) / 5
                ), 1) as average_score
            FROM feedback_requests freq
            LEFT JOIN feedback_responses fr ON freq.request_id = fr.request_id
            LEFT JOIN employee_profiles ep ON freq.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            WHERE freq.employee_id IN ($placeholders)
            GROUP BY freq.employee_id, pi.first_name, pi.last_name
            ORDER BY average_score DESC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($employee_ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Create a notification for pending feedback
 */
function createFeedbackNotification($reviewer_id, $employee_id, $cycle_id) {
    global $conn;
    try {
        // Get employee name
        $stmt = $conn->prepare("
            SELECT pi.first_name, pi.last_name, fc.cycle_name
            FROM employee_profiles ep
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN feedback_cycles fc ON fc.cycle_id = ?
            WHERE ep.employee_id = ?
        ");
        $stmt->execute([$cycle_id, $employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $message = "Feedback requested for " . $result['first_name'] . " " . 
                       $result['last_name'] . " in " . $result['cycle_name'] . " cycle";
            return $message;
        }
    } catch (PDOException $e) {
        // Silent fail
    }
    return null;
}

/**
 * Get recent feedback activities for dashboard
 */
function getRecentFeedbackForDashboard($limit = 5) {
    global $conn;
    try {
        $sql = "
            SELECT 
                pi.first_name,
                pi.last_name,
                fc.cycle_name,
                fr.status,
                fr.created_at,
                freq.relationship_type
            FROM feedback_requests fr
            LEFT JOIN feedback_cycles fc ON fr.cycle_id = fc.cycle_id
            LEFT JOIN employee_profiles ep ON fr.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN feedback_responses freq_resp ON fr.request_id = freq_resp.request_id
            LEFT JOIN feedback_requests freq ON freq_resp.request_id = freq.request_id
            ORDER BY fr.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Export feedback data for analysis
 */
function exportFeedbackData($employee_id, $cycle_id = null) {
    global $conn;
    try {
        $sql = "
            SELECT 
                pi.first_name,
                pi.last_name,
                fc.cycle_name,
                CASE freq.relationship_type
                    WHEN 'supervisor' THEN 'Manager'
                    WHEN 'peer' THEN 'Peer'
                    WHEN 'subordinate' THEN 'Team Member'
                    WHEN 'self' THEN 'Self'
                END as reviewer_type,
                JSON_EXTRACT(fr.responses, '$.leadership') as leadership,
                JSON_EXTRACT(fr.responses, '$.communication') as communication,
                JSON_EXTRACT(fr.responses, '$.teamwork') as teamwork,
                JSON_EXTRACT(fr.responses, '$.problem_solving') as problem_solving,
                JSON_EXTRACT(fr.responses, '$.work_quality') as work_quality,
                fr.comments,
                fr.submitted_at
            FROM feedback_responses fr
            LEFT JOIN feedback_requests freq ON fr.request_id = freq.request_id
            LEFT JOIN feedback_cycles fc ON freq.cycle_id = fc.cycle_id
            LEFT JOIN employee_profiles ep ON freq.employee_id = ep.employee_id
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            WHERE freq.employee_id = ?
        ";
        
        if ($cycle_id) {
            $sql .= " AND fc.cycle_id = ?";
        }
        
        $sql .= " ORDER BY fr.submitted_at DESC";
        
        $stmt = $conn->prepare($sql);
        if ($cycle_id) {
            $stmt->execute([$employee_id, $cycle_id]);
        } else {
            $stmt->execute([$employee_id]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

?>
