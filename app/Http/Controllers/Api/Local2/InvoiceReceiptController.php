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
    public function copyInvLines()
    {
        Log::info('Starting InvoiceReceiptController copy process');
        
        try {
            DB::beginTransaction();
            
            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            
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

            // Copy all data to local database with proper validation
            foreach ($sqlsrvData as $data) {
                try {
                    // Validate required fields
                    if (empty($data->po_no) || empty($data->receipt_no) || empty($data->receipt_line)) {
                        Log::warning("Skipping record with missing required fields: po_no={$data->po_no}, receipt_no={$data->receipt_no}, receipt_line={$data->receipt_line}");
                        $skippedCount++;
                        continue;
                    }
                    
                    // Create unique key combination to prevent duplicates
                    $uniqueKey = [
                        'po_no' => $data->po_no,
                        'receipt_no' => $data->receipt_no,
                        'receipt_line' => $data->receipt_line
                    ];
                    
                    // Check if record already exists
                    $existingRecord = InvLine::where($uniqueKey)->first();
                    
                    if ($existingRecord) {
                        Log::debug("Record already exists, updating: po_no={$data->po_no}, receipt_no={$data->receipt_no}");
                    }
                    
                    // Update or create with proper data validation
                    InvLine::updateOrCreate(
                        $uniqueKey,
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
                        'po_no' => $data->po_no ?? 'N/A',
                        'receipt_no' => $data->receipt_no ?? 'N/A',
                        'receipt_line' => $data->receipt_line ?? 'N/A',
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
                    'total_found' => $sqlsrvData->count()
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
