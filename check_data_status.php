<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\InvLine;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DATA STATUS CHECK ===\n\n";

// 1. Check total records
$totalRecords = InvLine::count();
echo "1. Total InvLine Records: {$totalRecords}\n";

// 2. Check for duplicates
$duplicates = DB::table('inv_line')
    ->select('po_no', 'receipt_no', 'receipt_line', 'item_no', DB::raw('COUNT(*) as count'))
    ->whereNotNull('po_no')
    ->whereNotNull('receipt_no')
    ->whereNotNull('receipt_line')
    ->groupBy('po_no', 'receipt_no', 'receipt_line', 'item_no')
    ->having('count', '>', 1)
    ->get();

echo "2. Duplicate Groups Found: " . $duplicates->count() . "\n";

if ($duplicates->count() > 0) {
    echo "   Duplicate Details:\n";
    foreach ($duplicates as $duplicate) {
        echo "   - PO: {$duplicate->po_no}, Receipt: {$duplicate->receipt_no}, Line: {$duplicate->receipt_line}, Item: {$duplicate->item_no}, Count: {$duplicate->count}\n";
    }
}

// 3. Check recent records
$recentRecords = InvLine::orderBy('created_at', 'desc')->limit(5)->get();
echo "\n3. 5 Most Recent Records:\n";
foreach ($recentRecords as $record) {
    echo "   - ID: {$record->inv_line_id}, PO: {$record->po_no}, Receipt: {$record->receipt_no}, Created: {$record->created_at}\n";
}

// 4. Check records by date range
$today = now()->format('Y-m-d');
$yesterday = now()->subDay()->format('Y-m-d');
$lastWeek = now()->subWeek()->format('Y-m-d');

$todayCount = InvLine::whereDate('created_at', $today)->count();
$yesterdayCount = InvLine::whereDate('created_at', $yesterday)->count();
$lastWeekCount = InvLine::whereDate('created_at', '>=', $lastWeek)->count();

echo "\n4. Records by Date:\n";
echo "   - Today ({$today}): {$todayCount}\n";
echo "   - Yesterday ({$yesterday}): {$yesterdayCount}\n";
echo "   - Last 7 days: {$lastWeekCount}\n";

// 5. Check unique combinations
$uniqueCombinations = DB::table('inv_line')
    ->select(DB::raw('COUNT(DISTINCT CONCAT(po_no, "|", receipt_no, "|", receipt_line, "|", COALESCE(item_no, ""))) as unique_count'))
    ->whereNotNull('po_no')
    ->whereNotNull('receipt_no')
    ->whereNotNull('receipt_line')
    ->first();

echo "\n5. Unique Combinations: {$uniqueCombinations->unique_count}\n";

// 6. Check for specific problematic data (based on your example)
echo "\n6. Checking for specific problematic data:\n";
$problematicData = InvLine::where('po_no', 'PL2502208')
    ->where('receipt_no', 'DN0031242')
    ->get();

echo "   Records with PO=PL2502208, Receipt=DN0031242: {$problematicData->count()}\n";

if ($problematicData->count() > 0) {
    foreach ($problematicData as $record) {
        echo "   - ID: {$record->inv_line_id}, Line: {$record->receipt_line}, Item: {$record->item_no}, Created: {$record->created_at}\n";
    }
}

echo "\n=== END OF CHECK ===\n";
