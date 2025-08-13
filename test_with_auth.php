<?php

// Test API endpoint with authentication
$baseUrl = 'http://127.0.0.1:8000/api';

echo "=== TESTING API WITH AUTHENTICATION ===\n\n";

// Step 1: Login to get token
echo "1. Attempting login...\n";
$loginData = [
    'username' => 'admin', // Replace with actual username
    'password' => 'password' // Replace with actual password
];

$loginContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        'content' => json_encode($loginData)
    ]
]);

$loginResponse = file_get_contents($baseUrl . '/login', false, $loginContext);

if ($loginResponse === false) {
    echo "ERROR: Login failed\n";
    echo "HTTP Response Code: " . $http_response_header[0] . "\n";
    exit;
}

$loginResult = json_decode($loginResponse, true);
if (isset($loginResult['access_token'])) {
    echo "SUCCESS: Login successful\n";
    $token = $loginResult['access_token'];
    echo "Token received: " . substr($token, 0, 20) . "...\n\n";
    
    // Step 2: Use token to access inv-header endpoint
    echo "2. Testing inv-header endpoint with token...\n";
    
    $apiContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]
    ]);
    
    $apiResponse = file_get_contents($baseUrl . '/finance/inv-header', false, $apiContext);
    
    if ($apiResponse === false) {
        echo "ERROR: Failed to fetch inv-header data\n";
        echo "HTTP Response Code: " . $http_response_header[0] . "\n";
    } else {
        echo "SUCCESS: Inv-header data fetched successfully\n";
        echo "Response length: " . strlen($apiResponse) . " characters\n";
        
        $data = json_decode($apiResponse, true);
        if ($data !== null && is_array($data)) {
            echo "Data count: " . count($data) . " records\n";
            if (count($data) > 0) {
                echo "First record keys: " . implode(', ', array_keys($data[0])) . "\n";
            }
        }
    }
    
} else {
    echo "ERROR: Login failed - no token received\n";
    echo "Response: " . $loginResponse . "\n";
}
