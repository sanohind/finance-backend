<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Database Connection Troubleshooter ===\n\n";

// Test main database (MySQL)
echo "1. Testing MySQL database connection...\n";
try {
    DB::connection()->getPdo();
    echo "✓ MySQL database connection successful\n";
} catch (Exception $e) {
    echo "✗ MySQL database connection failed: " . $e->getMessage() . "\n";
}

// Test sqlsrv
echo "\n2. Testing SQL Server connection...\n";
try {
    DB::connection('sqlsrv')->getPdo();
    echo "✓ SQL Server connection successful\n";
} catch (Exception $e) {
    echo "✗ SQL Server connection failed: " . $e->getMessage() . "\n";
}

// Check environment variables
echo "\n3. Checking environment variables...\n";
$envVars = [
    'DB_CONNECTION' => env('DB_CONNECTION'),
    'DB_HOST' => env('DB_HOST'),
    'DB_PORT' => env('DB_PORT'),
    'DB_DATABASE' => env('DB_DATABASE'),
    'DB_USERNAME' => env('DB_USERNAME'),
    'DB_HOST_SQLSRV' => env('DB_HOST_SQLSRV'),
    'DB_PORT_SQLSRV' => env('DB_PORT_SQLSRV'),
    'DB_DATABASE_SQLSRV' => env('DB_DATABASE_SQLSRV'),
    'DB_USERNAME_SQLSRV' => env('DB_USERNAME_SQLSRV'),
];

foreach ($envVars as $key => $value) {
    $status = $value ? "✓" : "✗";
    echo "$status $key: " . ($value ?: 'NOT SET') . "\n";
}

echo "\n=== Troubleshooting Complete ===\n"; 