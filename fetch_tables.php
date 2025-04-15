<?php
require 'vendor/autoload.php';

if (!isset($_POST['source'])) {
    echo json_encode([]);
    exit;
}

$source = $_POST['source'];
$response = [];

if (strpos($source, 'mysql_') === 0) {
    $dbName = substr($source, 6);
    $mysql = new mysqli("localhost", "root", "", $dbName);
    if (!$mysql->connect_error) {
        $result = $mysql->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $response[] = $row[0];
        }
        $mysql->close();
    }
} elseif (strpos($source, 'mongodb_') === 0) {
    $dbName = substr($source, 8);
    try {
        $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
        $db = $mongoClient->$dbName;
        $collections = $db->listCollections();
        foreach ($collections as $collection) {
            $response[] = $collection->getName();
        }
    } catch (Exception $e) {
        // Log or handle error
    }
}

echo json_encode($response);
