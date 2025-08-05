<?php

echo "=== Simple Database Test ===\n";

// Test if .env file exists
if (file_exists('.env')) {
    echo "✓ .env file exists\n";
} else {
    echo "✗ .env file not found\n";
}

// Test if we can read .env
$envContent = file_get_contents('.env');
if ($envContent) {
    echo "✓ .env file is readable\n";
    echo "File size: " . strlen($envContent) . " bytes\n";
} else {
    echo "✗ Cannot read .env file\n";
}

// Check for specific variables
$lines = explode("\n", $envContent);
$foundVars = [];
foreach ($lines as $line) {
    $line = trim($line);
    if (strpos($line, 'DB_') === 0) {
        $foundVars[] = $line;
    }
}

echo "\nFound database variables:\n";
foreach ($foundVars as $var) {
    echo "- $var\n";
}

echo "\n=== Test Complete ===\n"; 