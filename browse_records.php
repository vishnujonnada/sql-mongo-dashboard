<?php
require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$source = $_POST['source'] ?? '';
$table = $_POST['table'] ?? '';
$page = intval($_POST['page'] ?? 1);
$limit = 100;

if (!$source || !$table) {
    echo json_encode(["error" => "Missing source or table"]);
    exit;
}

$directory = "temp";

if (is_dir($directory)) {
    $items = array_diff(scandir($directory), array('.', '..'));
    
    foreach ($items as $item) {
        $itemPath = $directory . DIRECTORY_SEPARATOR . $item;
        if (is_dir($itemPath)) {
            $subItems = array_diff(scandir($itemPath), array('.', '..')); 
            foreach ($subItems as $subItem) {
                unlink($itemPath . DIRECTORY_SEPARATOR . $subItem); 
            }
            rmdir($itemPath); 
        } else {
            unlink($itemPath); 
        }
    }
    
    rmdir($directory); // Remove the now-empty directory
}

mkdir($directory); // Create the directory again

$total = 0;
$allData = [];

if (strpos($source, 'mysql_') === 0) {
    $dbName = substr($source, 6); 

    $conn = new mysqli('localhost', 'root', '', $dbName);
    if ($conn->connect_error) {
        echo json_encode(["error" => "MySQL connection failed"]);
        exit;
    }

    $result = $conn->query("SELECT * FROM `$table`");
    while ($row = $result->fetch_assoc()) {
        $allData[] = $row;
    }
    $conn->close();
}
elseif (strpos($source, 'mongodb_') === 0) {
    $dbName = substr($source, 8); 

    try {
        $client = new MongoDB\Client("mongodb://localhost:27017");
        $collection = $client->$dbName->$table;

        $cursor = $collection->find();
        foreach ($cursor as $doc) {
            $allData[] = json_decode(json_encode($doc), true);
        }
    } catch (Exception $e) {
        echo json_encode(["error" => "MongoDB error: " . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(["error" => "Unknown source type"]);
    exit;
}

// Paginate and save all data only once
$total = count($allData);
$totalPages = ceil($total / $limit);

for ($i = 1; $i <= $totalPages; $i++) {
    $offset = ($i - 1) * $limit;
    $pageData = array_slice($allData, $offset, $limit);
    file_put_contents("temp/records_page_$i.json", json_encode($pageData, JSON_PRETTY_PRINT));
}

// Now serve only the requested page
$pageFile = "temp/records_page_$page.json";
if (file_exists($pageFile)) {
    $data = json_decode(file_get_contents($pageFile), true);
} else {
    $data = [];
}

echo json_encode([
    "total" => $total,
    "totalPages" => $totalPages,
    "data" => $data
]);
