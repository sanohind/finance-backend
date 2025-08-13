<?php

require_once 'vendor/autoload.php';

use App\Models\InvHeader;
use App\Http\Resources\InvHeaderResource;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING INV HEADER ENDPOINT ===\n\n";

try {
    // Test 1: Check if InvHeader model works
    echo "1. Testing InvHeader model...\n";
    $count = InvHeader::count();
    echo "   Total InvHeader records: {$count}\n";
    
    if ($count > 0) {
        $firstRecord = InvHeader::first();
        echo "   First record ID: {$firstRecord->inv_id}\n";
        echo "   First record inv_no: {$firstRecord->inv_no}\n";
    }
    
    // Test 2: Test with relationship loading
    echo "\n2. Testing with invLine relationship...\n";
    $invHeaders = InvHeader::with('invLine')->limit(3)->get();
    echo "   Loaded {$invHeaders->count()} records with relationships\n";
    
    foreach ($invHeaders as $header) {
        echo "   - ID: {$header->inv_id}, Inv No: {$header->inv_no}, InvLines: {$header->invLine->count()}\n";
    }
    
    // Test 3: Test InvHeaderResource
    echo "\n3. Testing InvHeaderResource...\n";
    $resource = new InvHeaderResource($invHeaders->first());
    $resourceArray = $resource->toArray(request());
    echo "   Resource created successfully\n";
    echo "   Resource keys: " . implode(', ', array_keys($resourceArray)) . "\n";
    
    // Test 4: Test collection
    echo "\n4. Testing InvHeaderResource collection...\n";
    $collection = InvHeaderResource::collection($invHeaders);
    $collectionArray = $collection->toArray(request());
    echo "   Collection created successfully\n";
    echo "   Collection count: " . count($collectionArray) . "\n";
    
    echo "\n=== ALL TESTS PASSED ===\n";
    
} catch (\Exception $e) {
    echo "\n=== ERROR OCCURRED ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
