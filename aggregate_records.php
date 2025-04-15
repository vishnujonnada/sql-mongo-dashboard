<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$query = $input['query'] ?? [];

$allData = [];

// Load all records_page_*.json files
foreach (glob("temp/records_page_*.json") as $filename) {
    $json = file_get_contents($filename);
    $data = json_decode($json, true);
    if (is_array($data)) {
        $allData = array_merge($allData, $data);
    }
}

// Apply filters
$filtered = array_filter($allData, function ($item) use ($query) {
    if (!empty($query['match'])) {
        foreach ($query['match'] as $key => $val) {
            if (!isset($item[$key]) || $item[$key] != $val) return false;
        }
    }
    return true;
});

// project/select
if (!empty($query['select'])) {
    $fields = is_array($query['select']) ? $query['select'] : explode(',', $query['select']);
    $fields = array_map('trim', $fields);
    $filtered = array_map(function ($item) use ($fields) {
        return array_intersect_key($item, array_flip($fields));
    }, $filtered);
}


// Apply sorting
if (!empty($query['sortBy'])) {
    usort($filtered, function ($a, $b) use ($query) {
        $key = $query['sortBy'];
        $dir = strtolower($query['sortDir'] ?? 'asc');
        return ($dir === 'asc' ? 1 : -1) * strcmp($a[$key] ?? '', $b[$key] ?? '');
    });
}


// Apply Limit
if (!empty($query['limit'])) {
    $limit = intval($query['limit']);
    $filtered = array_slice($filtered, 0, $limit);
}
// Output result
echo json_encode(array_values($filtered));
