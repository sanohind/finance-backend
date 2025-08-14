<?php

namespace App\Http\Controllers\Api\Local2;

use App\Http\Controllers\Controller;
use App\Models\ERP\InvReceipt;
use App\Models\InvLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceReceiptController extends Controller
{
    /**
     * Check for duplicate records in inv_line table
     */
    public function checkDuplicates()
    {
        Log::info('Starting duplicate check process');
        
        try {
            // Find duplicate records
            $duplicates = DB::table('inv_line')
                ->select('po_no', 'receipt_no', 'receipt_line', 'item_no', DB::raw('COUNT(*) as count'))
                ->whereNotNull('po_no')
                ->whereNotNull('receipt_no')
                ->whereNotNull('receipt_line')
                ->groupBy('po_no', 'receipt_no', 'receipt_line', 'item_no')
                ->having('count', '>', 1)
                ->get();
            
            $duplicateDetails = [];
            $totalDuplicates = 0;
            
            foreach ($duplicates as $duplicate) {
                // Get all records for this combination
                $records = InvLine::where([
                    'po_no' => $duplicate->po_no,
                    'receipt_no' => $duplicate->receipt_no,
                    'receipt_line' => $duplicate->receipt_line,
                    'item_no' => $duplicate->item_no
                ])->orderBy('created_at', 'desc')->get();
                
                $duplicateDetails[] = [
                    'po_no' => $duplicate->po_no,
                    'receipt_no' => $duplicate->receipt_no,
                    'receipt_line' => $duplicate->receipt_line,
                    'item_no' => $duplicate->item_no,
                    'count' => $duplicate->count,
                    'records' => $records->map(function ($record) {
                        return [
                            'inv_line_id' => $record->inv_line_id,
                            'created_at' => $record->created_at,
                            'updated_at' => $record->updated_at,
                            'inv_supplier_no' => $record->inv_supplier_no,
                            'inv_due_date' => $record->inv_due_date
                        ];
                    })
                ];
                
                $totalDuplicates += $duplicate->count - 1;
            }
            
            Log::info("Duplicate check completed", [
                'duplicate_groups' => $duplicates->count(),
                'total_duplicates' => $totalDuplicates
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Duplicate check completed',
                'stats' => [
                    'duplicate_groups' => $duplicates->count(),
                    'total_duplicates' => $totalDuplicates
                ],
                'duplicates' => $duplicateDetails
            ]);
            
        } catch (\Exception $e) {
            Log::error("Duplicate check failed: " . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error checking duplicates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean duplicate records from inv_line table
     */
    public function cleanDuplicates()
    {
        Log::info('Starting duplicate cleanup process');
        
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
            
            Log::info("Found " . $duplicates->count() . " duplicate groups to clean");
            
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
                    $record->delete();
                    $deletedCount++;
                }
                
                Log::info("Cleaned duplicates for: po_no={$duplicate->po_no}, receipt_no={$duplicate->receipt_no}, item_no={$duplicate->item_no}, deleted={$recordsToDelete->count()}");
            }
            
            DB::commit();
            
            Log::info("Duplicate cleanup completed", ['deleted_count' => $deletedCount]);
            
            return response()->json([
                'success' => true,
                'message' => 'Duplicate cleanup completed successfully',
                'deleted_count' => $deletedCount,
                'duplicate_groups' => $duplicates->count()
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Duplicate cleanup failed: " . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error cleaning duplicates: ' . $e->getMessage()
            ], 500);
        }
    }

    public function copyInvLines()
    {
        Log::info('Starting InvoiceReceiptController copy process');
        
        try {
            DB::beginTransaction();
            
            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $duplicateCount = 0;
            
            // Get all data from SQL Server, from March 2025 onwards with validation
            $currentYear = 2025;
            $startMonth = 6; // Juni

            $sqlsrvData = InvReceipt::whereYear('actual_receipt_date', $currentYear)
                                    ->whereMonth('actual_receipt_date', '>=', $startMonth)
                                    ->whereNotNull('po_no')
                                    ->whereNotNull('receipt_no')
                                    ->whereNotNull('receipt_line')
                                    ->where('po_no', '!=', '')
                                    ->where('receipt_no', '!=', '')
                                    ->where('receipt_line', '!=', '')
                                    ->orderByDesc('actual_receipt_date')
                                    ->get();

            Log::info("Found {$sqlsrvData->count()} records from ERP to process");

            // Group data by unique combination to detect duplicates in source
            $groupedData = $sqlsrvData->groupBy(function ($item) {
                return $item->po_no . '|' . $item->receipt_no . '|' . $item->receipt_line . '|' . $item->item_no;
            });

            Log::info("Grouped into " . $groupedData->count() . " unique combinations");

            // Process each unique combination
            foreach ($groupedData as $uniqueKey => $records) {
                try {
                    // Take the first record from each group (most recent based on orderByDesc)
                    $data = $records->first();
                    
                    // Validate required fields
                    if (empty($data->po_no) || empty($data->receipt_no) || empty($data->receipt_line)) {
                        Log::warning("Skipping record with missing required fields: po_no={$data->po_no}, receipt_no={$data->receipt_no}, receipt_line={$data->receipt_line}");
                        $skippedCount++;
                        continue;
                    }

                    // Check if we have duplicates in source data
                    if ($records->count() > 1) {
                        Log::warning("Found {$records->count()} duplicate records in source for key: {$uniqueKey}");
                        $duplicateCount += $records->count() - 1;
                    }
                    
                    // Create unique key combination to prevent duplicates in target
                    $targetUniqueKey = [
                        'po_no' => $data->po_no,
                        'receipt_no' => $data->receipt_no,
                        'receipt_line' => $data->receipt_line,
                        'item_no' => $data->item_no ?? null
                    ];
                    
                    // Check if record already exists in target database
                    $existingRecord = InvLine::where($targetUniqueKey)->first();
                    
                    if ($existingRecord) {
                        Log::debug("Record already exists in target, updating: po_no={$data->po_no}, receipt_no={$data->receipt_no}, item_no={$data->item_no}");
                    }
                    
                    // Update or create with proper data validation
                    InvLine::updateOrCreate(
                        $targetUniqueKey,
                        [
                            'po_no' => $data->po_no,
                            'bp_id' => $data->bp_id ?? null,
                            'bp_name' => $data->bp_name ?? null,
                            'currency' => $data->currency ?? null,
                            'po_type' => $data->po_type ?? null,
                            'po_reference' => $data->po_reference ?? null,
                            'po_line' => $data->po_line ?? null,
                            'po_sequence' => $data->po_sequence ?? null,
                            'po_receipt_sequence' => $data->po_receipt_sequence ?? null,
                            'actual_receipt_date' => $data->actual_receipt_date ?? null,
                            'actual_receipt_year' => $data->actual_receipt_year ?? null,
                            'actual_receipt_period' => $data->actual_receipt_period ?? null,
                            'receipt_no' => $data->receipt_no,
                            'receipt_line' => $data->receipt_line,
                            'gr_no' => $data->gr_no ?? null,
                            'packing_slip' => $data->packing_slip ?? null,
                            'item_no' => $data->item_no ?? null,
                            'ics_code' => $data->ics_code ?? null,
                            'ics_part' => $data->ics_part ?? null,
                            'part_no' => $data->part_no ?? null,
                            'item_desc' => $data->item_desc ?? null,
                            'item_group' => $data->item_group ?? null,
                            'item_type' => $data->item_type ?? null,
                            'item_type_desc' => $data->item_type_desc ?? null,
                            'request_qty' => $data->request_qty ?? 0,
                            'actual_receipt_qty' => $data->actual_receipt_qty ?? 0,
                            'approve_qty' => $data->approve_qty ?? 0,
                            'unit' => $data->unit ?? null,
                            'receipt_amount' => $data->receipt_amount ?? 0,
                            'receipt_unit_price' => $data->receipt_unit_price ?? 0,
                            'is_final_receipt' => $data->is_final_receipt ?? false,
                            'is_confirmed' => $data->is_confirmed ?? false,
                            'inv_doc_no' => $data->inv_doc_no ?? null,
                            'inv_doc_date' => $data->inv_doc_date ?? null,
                            'inv_qty' => $data->inv_qty ?? 0,
                            'inv_amount' => $data->inv_amount ?? 0,
                            'inv_supplier_no' => $data->inv_supplier_no ?? null,
                            'inv_due_date' => $data->inv_due_date ?? null,
                            'payment_doc' => $data->payment_doc ?? null,
                            'payment_doc_date' => $data->payment_doc_date ?? null
                        ]
                    );
                    
                    $processedCount++;
                    
                    // Log progress every 100 records
                    if ($processedCount % 100 === 0) {
                        Log::info("Processed {$processedCount} records so far");
                    }
                    
                } catch (\Exception $e) {
                    Log::error("Error processing record: " . $e->getMessage(), [
                        'unique_key' => $uniqueKey,
                        'error' => $e->getMessage()
                    ]);
                    $errorCount++;
                }
            }

            DB::commit();
            
            Log::info("InvoiceReceiptController copy completed", [
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'duplicates_in_source' => $duplicateCount,
                'year' => $currentYear,
                'start_month' => $startMonth
            ]);

            return response()->json([
                'success' => true,
                'message' => "Data inv_line successfully copied for year {$currentYear} from month {$startMonth} onwards.",
                'stats' => [
                    'processed' => $processedCount,
                    'skipped' => $skippedCount,
                    'errors' => $errorCount,
                    'duplicates_in_source' => $duplicateCount,
                    'total_found' => $sqlsrvData->count(),
                    'unique_combinations' => $groupedData->count()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("InvoiceReceiptController copy failed: " . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error copying data: ' . $e->getMessage()
            ], 500);
        }
    }
}