<?php
$db_host = getenv('DB_HOST') ?? 'localhost';
$db_name = getenv('DB_NAME') ?? 'hr_system';
$db_user = getenv('DB_USER') ?? 'root';
$db_pass = getenv('DB_PASS') ?? '';
$conn = new PDO('mysql:host=' . $db_host . ';dbname=' . $db_name, $db_user, $db_pass);
$tables = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$sql = '';
foreach ($tables as $table) {
    $result = $conn->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_ASSOC);
    $sql .= $result['Create Table'] . ";\n\n";
    $data = $conn->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($data)) {
        $columns = array_keys($data[0]);
        $sql .= "INSERT INTO $table (" . implode(',', $columns) . ") VALUES\n";
        $values = [];
        foreach ($data as $row) {
            $rowValues = [];
            foreach ($row as $value) {
                $rowValues[] = $conn->quote($value);
            }
            $values[] = '(' . implode(',', $rowValues) . ')';
        }
        $sql .= implode(",\n", $values) . ";\n\n";
    }
}
file_put_contents('hr_system.sql', $sql);
echo 'Database exported to hr_system.sql';
?>
