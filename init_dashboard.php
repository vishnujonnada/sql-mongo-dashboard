<?php
require 'vendor/autoload.php'; 
header('Content-Type: application/json');

// MySQL connection
$mysqlHost = 'localhost';
$mysqlUser = 'root';
$mysqlPass = '';
$mysql = new mysqli($mysqlHost, $mysqlUser, $mysqlPass);
$mysqlDatabases = [];

if ($mysql->connect_error) {
    die(json_encode(['error' => 'MySQL Connection Failed']));
}

$result = $mysql->query("SHOW DATABASES");
while ($row = $result->fetch_assoc()) {
    $mysqlDatabases[] = $row['Database'];
}
$mysql->close();


$mongoDatabases = [];
try {
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    foreach ($mongoClient->listDatabases() as $db) {
        $mongoDatabases[] = $db->getName();
    }
} catch (Exception $e) {
    die(json_encode(['error' => 'MongoDB Connection Failed']));
}


echo json_encode([
    'mysql' => $mysqlDatabases,
    'mongo' => $mongoDatabases
]);
