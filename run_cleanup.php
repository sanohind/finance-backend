<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\InvLine;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== RUNNING DUPLICATE CLEANUP ===\n\n";

try {
    DB::beginTransaction();
    
    $deletedCount = 0;
    
    // Find and delete duplicate records
    $duplicates = DB::table('inv_line')
        ->select('po_no', 'receipt_no', 'receipt_line', 'item_no', DB::raw('COUNT(*) as count'))
        ->whereNotNull('po_no')
        ->whereNotNull('receipt_no')
        ->whereNotNull('receipt_line')
        ->groupBy('po_no', 'receipt_no', 'receipt_line', 'item_no')
        ->having('count', '>', 1)
        ->get();
    
    echo "Found " . $duplicates->count() . " duplicate groups to clean\n\n";
    
    foreach ($duplicates as $duplicate) {
        // Get all records for this combination
        $records = InvLine::where([
            'po_no' => $duplicate->po_no,
            'receipt_no' => $duplicate->receipt_no,
            'receipt_line' => $duplicate->receipt_line,
            'item_no' => $duplicate->item_no
        ])->orderBy('created_at', 'desc')->get();
        
        // Keep the most recent record (first one due to orderBy desc)
        $keepRecord = $records->first();
        
        // Delete all other records
        $recordsToDelete = $records->where('inv_line_id', '!=', $keepRecord->inv_line_id);
        
        foreach ($recordsToDelete as $record) {
            echo "Deleting duplicate: ID={$record->inv_line_id}, PO={$record->po_no}, Receipt={$record->receipt_no}, Line={$record->receipt_line}\n";
            $record->delete();
            $deletedCount++;
        }
    }
    
    DB::commit();
    
    echo "\n=== CLEANUP COMPLETED ===\n";
    echo "Total records deleted: {$deletedCount}\n";
    echo "Duplicate groups processed: " . $duplicates->count() . "\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}
