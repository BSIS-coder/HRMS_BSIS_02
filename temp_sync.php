<?php
require_once 'holiday_actions.php';

$result = syncHolidaysFromAPI();

echo json_encode($result);
?>