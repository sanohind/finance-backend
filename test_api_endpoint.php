<?php

// Test API endpoint directly
$url = 'http://127.0.0.1:8000/api/finance/inv-header';

echo "=== TESTING API ENDPOINT ===\n";
echo "URL: {$url}\n\n";

// Create context for the request
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        'timeout' => 30
    ]
]);

try {
    // Make the request
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "ERROR: Failed to fetch data\n";
        echo "HTTP Response Code: " . $http_response_header[0] . "\n";
    } else {
        echo "SUCCESS: Data fetched successfully\n";
        echo "Response length: " . strlen($response) . " characters\n";
        
        // Decode JSON to check structure
        $data = json_decode($response, true);
        if ($data !== null) {
            echo "JSON decoded successfully\n";
            echo "Data type: " . gettype($data) . "\n";
            
            if (is_array($data)) {
                echo "Array count: " . count($data) . "\n";
                if (count($data) > 0) {
                    echo "First item keys: " . implode(', ', array_keys($data[0])) . "\n";
                }
            }
        } else {
            echo "ERROR: Failed to decode JSON\n";
            echo "Response preview: " . substr($response, 0, 200) . "...\n";
        }
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
