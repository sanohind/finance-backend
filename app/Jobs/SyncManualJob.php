<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\InvLine;
use Illuminate\Bus\Queueable;
use App\Models\ERP\InvReceipt;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncManualJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting SyncManualJob process');
        
        try {
            DB::beginTransaction();
            
            $currentYear = now()->year;
            $currentMonth = now()->endOfMonth()->format('Y-m-d');
            $oneMonthBefore = now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');

            Log::info("SyncManualJob filtering data for year: {$currentYear}, date range: {$oneMonthBefore} to {$currentMonth}");

            // Get data from ERP with proper validation
            $sqlsrvData = InvReceipt::whereYear('actual_receipt_date', $currentYear)
                ->whereBetween('actual_receipt_date', [$oneMonthBefore, $currentMonth])
                ->whereNotNull('po_no')
                ->whereNotNull('gr_no')
                ->where('po_no', '!=', '')
                ->where('gr_no', '!=', '')
                ->orderByDesc('actual_receipt_date')
                ->get();

            Log::info("Found {$sqlsrvData->count()} records from ERP to process");

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            // Store data to db with proper validation
            foreach ($sqlsrvData as $data) {
                try {
                    // Validate required fields
                    if (empty($data->po_no) || empty($data->gr_no)) {
                        Log::warning("Skipping record with missing required fields: po_no={$data->po_no}, gr_no={$data->gr_no}");
                        $skippedCount++;
                        continue;
                    }
                    
                    // Create unique key combination to prevent duplicates
                    $uniqueKey = [
                        'po_no' => $data->po_no,
                        'gr_no' => $data->gr_no,
                    ];
                    
                    // Check if record already exists
                    $existingRecord = InvLine::where($uniqueKey)->first();
                    
                    if ($existingRecord) {
                        Log::debug("Record already exists, updating: po_no={$data->po_no}, gr_no={$data->gr_no}");
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
                            'receipt_no' => $data->receipt_no ?? null,
                            'receipt_line' => $data->receipt_line ?? null,
                            'gr_no' => $data->gr_no,
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
                        'gr_no' => $data->gr_no ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                    $errorCount++;
                }
            }
            
            DB::commit();
            
            Log::info("SyncManualJob completed successfully", [
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'total_found' => $sqlsrvData->count()
            ]);
            
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("SyncManualJob failed: " . $th->getMessage(), [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            throw $th;
        }
    }
}
