<?php
require_once 'config.php';
require_once 'holiday_type_mapper.php';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    if ($action === 'migrate_types') {
        $result = migrateHolidayTypesToPhilippine($conn);
        if ($result['success']) {
            $response = ['success' => true, 'message' => $result['message']];
        } else {
            $response = ['success' => false, 'message' => $result['message']];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>