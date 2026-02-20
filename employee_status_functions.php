<?php
require_once 'dp.php';

function updateAllEmployeesStatusBasedOnLeave() {
    global $conn;
    try {
        // Update employee status based on current leave status
        $sql = "UPDATE employee_profiles ep
                SET ep.employment_status = CASE
                    WHEN EXISTS (
                        SELECT 1 FROM leave_requests lr
                        WHERE lr.employee_id = ep.employee_id
                        AND lr.status = 'Approved'
                        AND CURDATE() BETWEEN lr.start_date AND lr.end_date
                    ) THEN 'On Leave'
                    ELSE 'Active'
                END
                WHERE ep.employee_id IN (
                    SELECT employee_id FROM employment_history
                    WHERE history_id IN (
                        SELECT MAX(history_id) FROM employment_history GROUP BY employee_id
                    )
                )";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return true;
    } catch (PDOException $e) {
        error_log("updateAllEmployeesStatusBasedOnLeave error: " . $e->getMessage());
        return false;
    }
}

function getEmployeeStatus($employee_id) {
    global $conn;
    try {
        // Check if employee is currently on leave
        $sql = "SELECT CASE
                    WHEN EXISTS (
                        SELECT 1 FROM leave_requests lr
                        WHERE lr.employee_id = ?
                        AND lr.status = 'Approved'
                        AND CURDATE() BETWEEN lr.start_date AND lr.end_date
                    ) THEN 'On Leave'
                    ELSE 'Active'
                END as status";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['status'] ?? 'Active';
    } catch (PDOException $e) {
        error_log("getEmployeeStatus error: " . $e->getMessage());
        return 'Active';
    }
}

function updateEmployeeStatus($employee_id, $status) {
    global $conn;
    try {
        $sql = "UPDATE employee_profiles SET employment_status = ? WHERE employee_id = ?";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$status, $employee_id]);
    } catch (PDOException $e) {
        error_log("updateEmployeeStatus error: " . $e->getMessage());
        return false;
    }
}

function handleLeaveStatusChange($employee_id, $status) {
    // Re-evaluate employee status based on their leaves
    $newEmployeeStatus = getEmployeeStatus($employee_id);
    return updateEmployeeStatus($employee_id, $newEmployeeStatus);
}
