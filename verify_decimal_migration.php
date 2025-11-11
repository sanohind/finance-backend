<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=================================================================\n";
echo "VERIFICATION: Decimal Migration Success\n";
echo "=================================================================\n\n";

$columns = DB::select("SHOW COLUMNS FROM inv_line WHERE Field IN ('request_qty', 'actual_receipt_qty', 'approve_qty', 'receipt_amount', 'receipt_unit_price', 'inv_qty', 'inv_amount')");

echo "Column Data Types:\n";
echo str_repeat("-", 65) . "\n";
printf("%-25s %-30s %s\n", "Column", "Type", "Null");
echo str_repeat("-", 65) . "\n";

foreach ($columns as $col) {
    $status = $col->Type === 'decimal(15,4)' || $col->Type === 'decimal(15,2)' ? '✅' : '❌';
    printf("%-25s %-30s %s %s\n", $col->Field, $col->Type, $col->Null, $status);
}

echo str_repeat("=", 65) . "\n\n";

// Check if decimal values can be stored
echo "Testing Decimal Storage:\n";
echo str_repeat("-", 65) . "\n";

try {
    // Find or create test record
    $testLine = DB::table('inv_line')
        ->where('po_no', 'TEST-DECIMAL')
        ->first();
    
    if (!$testLine) {
        DB::table('inv_line')->insert([
            'po_no' => 'TEST-DECIMAL',
            'bp_id' => 'TEST',
            'item_no' => 'TEST-001',
            'actual_receipt_qty' => 0.2500,
            'approve_qty' => 0.2500,
            'receipt_amount' => 6250.00,
            'receipt_unit_price' => 25000.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ Test record created with decimal values\n";
    } else {
        echo "✅ Test record already exists\n";
    }
    
    // Retrieve and verify
    $retrieved = DB::table('inv_line')
        ->where('po_no', 'TEST-DECIMAL')
        ->first();
    
    echo "\nRetrieved Values:\n";
    echo "  actual_receipt_qty: {$retrieved->actual_receipt_qty}\n";
    echo "  approve_qty: {$retrieved->approve_qty}\n";
    echo "  receipt_amount: {$retrieved->receipt_amount}\n";
    echo "  receipt_unit_price: {$retrieved->receipt_unit_price}\n";
    
    if ($retrieved->actual_receipt_qty == 0.25) {
        echo "\n✅ SUCCESS: Decimal values stored and retrieved correctly!\n";
    } else {
        echo "\n❌ WARNING: Decimal values not matching!\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=================================================================\n";
echo "Migration verification completed!\n";
echo "=================================================================\n";
