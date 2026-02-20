<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Check if user has required role (admin, hr, or manager)
$allowed_roles = ['admin', 'hr', 'manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // Redirect to unauthorized page
    header("Location: unauthorized.php");
    exit();
}

// Function to check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Function to check if user has any of the specified roles
function hasAnyRole($roles) {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

// Function to get current user's ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Function to get current user's role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Function to get current user's name
function getCurrentUserName() {
    return $_SESSION['username'] ?? null;
}

// Function to check if user is admin
function isAdmin() {
    return hasRole('admin');
}

// Function to check if user is HR
function isHR() {
    return hasRole('hr');
}

// Function to check if user is manager
function isManager() {
    return hasRole('manager');
}

// Function to check if user is employee
function isEmployee() {
    return hasRole('employee');
}

// Function to log user activity
function logUserActivity($action, $details = '') {
    global $conn;
    
    if (isset($conn)) {
        $user_id = getCurrentUserId();
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $user_id,
                $action,
                'training_courses',
                null,
                null,
                json_encode($details),
                $ip_address,
                $user_agent
            ]);
        } catch (PDOException $e) {
            // Log error silently
            error_log("Error logging user activity: " . $e->getMessage());
        }
    }
}

// Function to check if user has permission to perform action
function hasPermission($action) {
    $role = getCurrentUserRole();
    
    // Define permissions for each role
    $permissions = [
        'admin' => ['view', 'add', 'edit', 'delete', 'manage'],
        'hr' => ['view', 'add', 'edit', 'manage'],
        'manager' => ['view', 'add'],
        'employee' => ['view']
    ];
    
    return isset($permissions[$role]) && in_array($action, $permissions[$role]);
}

// Function to require specific permission
function requirePermission($action) {
    if (!hasPermission($action)) {
        header("Location: unauthorized.php");
        exit();
    }
}

// Function to get user's department
function getUserDepartment() {
    return $_SESSION['department'] ?? null;
}

// Function to check if user is in specific department
function isInDepartment($department) {
    return getUserDepartment() === $department;
}

// Function to get user's manager ID
function getUserManagerId() {
    return $_SESSION['manager_id'] ?? null;
}

// Function to check if user is manager of specific employee
function isManagerOf($employee_id) {
    global $conn;
    
    if (isset($conn)) {
        $sql = "SELECT manager_id FROM employee_profiles WHERE employee_id = ?";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$employee_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['manager_id'] == getCurrentUserId();
        } catch (PDOException $e) {
            return false;
        }
    }
    return false;
}

// Function to check if user is in same department as another user
function isInSameDepartment($employee_id) {
    global $conn;
    
    if (isset($conn)) {
        $sql = "SELECT department_id FROM employee_profiles WHERE employee_id = ?";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$employee_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['department_id'] == $_SESSION['department_id'];
        } catch (PDOException $e) {
            return false;
        }
    }
    return false;
}

// Function to get user's direct reports
function getDirectReports() {
    global $conn;
    
    if (isset($conn)) {
        $sql = "SELECT employee_id FROM employee_profiles WHERE manager_id = ?";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([getCurrentUserId()]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
    return [];
}

// Function to check if user is direct report of current user
function isDirectReport($employee_id) {
    return in_array($employee_id, getDirectReports());
}

// Function to get user's team members
function getTeamMembers() {
    global $conn;
    
    if (isset($conn)) {
        $sql = "SELECT employee_id FROM employee_profiles WHERE department_id = ?";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_SESSION['department_id']]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
    return [];
}

// Function to check if user is in same team
function isInSameTeam($employee_id) {
    return in_array($employee_id, getTeamMembers());
}

// Function to get user's permissions
function getUserPermissions() {
    $role = getCurrentUserRole();
    
    // Define permissions for each role
    $permissions = [
        'admin' => ['view', 'add', 'edit', 'delete', 'manage'],
        'hr' => ['view', 'add', 'edit', 'manage'],
        'manager' => ['view', 'add'],
        'employee' => ['view']
    ];
    
    return $permissions[$role] ?? [];
}

// Function to check if user has any of the specified permissions
function hasAnyPermission($permissions) {
    $userPermissions = getUserPermissions();
    return !empty(array_intersect($permissions, $userPermissions));
}

// Function to require any of the specified permissions
function requireAnyPermission($permissions) {
    if (!hasAnyPermission($permissions)) {
        header("Location: unauthorized.php");
        exit();
    }
}

// Function to get user's access level
function getUserAccessLevel() {
    $role = getCurrentUserRole();
    
    // Define access levels
    $accessLevels = [
        'admin' => 4,
        'hr' => 3,
        'manager' => 2,
        'employee' => 1
    ];
    
    return $accessLevels[$role] ?? 0;
}

// Function to check if user has minimum access level
function hasMinimumAccessLevel($level) {
    return getUserAccessLevel() >= $level;
}

// Function to require minimum access level
function requireMinimumAccessLevel($level) {
    if (!hasMinimumAccessLevel($level)) {
        header("Location: unauthorized.php");
        exit();
    }
}
?> 