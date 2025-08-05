<?php

namespace App\Jobs;

use App\Models\ERP\InvReceipt;
use App\Models\InvLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncInvoiceLinesDailyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Custom log channel for sync jobs
     */
    private $logChannel = 'sync_job';

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
        Log::channel($this->logChannel)->info('Starting daily invoice lines synchronization job');

        try {
            DB::beginTransaction();
            
            // Log the year/month being used for filtering
            $year = now()->year;
            $month = now()->month;
            Log::channel($this->logChannel)->info("Filtering records for year: {$year}, month: {$month}");

            // Get data with proper validation
            $query = InvReceipt::where('is_confirmed', 'Yes')
                ->where(function($q) {
                    $q->whereNull('inv_doc_no')
                      ->orWhere('inv_doc_no', '');
                })
                ->whereYear('actual_receipt_date', $year)
                ->whereMonth('actual_receipt_date', $month)
                ->whereNotNull('po_no')
                ->whereNotNull('gr_no')
                ->where('po_no', '!=', '')
                ->where('gr_no', '!=', '');

            Log::channel($this->logChannel)->debug('SQL Query: ' . $query->toSql());
            Log::channel($this->logChannel)->debug('SQL Bindings: ' . json_encode($query->getBindings()));

            // Get the count before executing the full query
            $recordCount = $query->count();
            Log::channel($this->logChannel)->info("Found {$recordCount} records matching the criteria");

            if ($recordCount === 0) {
                // Try with less restrictive criteria as a test
                $testCount = InvReceipt::count();
                Log::channel($this->logChannel)->info("Total records in InvReceipt table: {$testCount}");

                $confirmedCount = InvReceipt::where('is_confirmed', 'Yes')->count();
                Log::channel($this->logChannel)->info("Records with is_confirmed='Yes': {$confirmedCount}");

                // Check specifically for records with is_confirmed='Yes', including Raw SQL so we can see the exact values
                $testRecords = DB::connection('sqlsrv')
                    ->select("SELECT TOP 10 is_confirmed, inv_doc_no, actual_receipt_date FROM data_receipt_purchase WHERE is_confirmed='Yes' AND (inv_doc_no IS NULL OR inv_doc_no = '')");
                Log::channel($this->logChannel)->info("Sample records with is_confirmed='Yes' AND empty inv_doc_no: " . json_encode($testRecords));

                $emptyInvDocCount = InvReceipt::where(function($q) {
                    $q->whereNull('inv_doc_no')
                      ->orWhere('inv_doc_no', '');
                })->count();
                Log::channel($this->logChannel)->info("Records with empty inv_doc_no: {$emptyInvDocCount}");

                // Check if any records exist for the current month/year regardless of other criteria
                $dateCount = InvReceipt::whereYear('actual_receipt_date', $year)
                    ->whereMonth('actual_receipt_date', $month)
                    ->count();
                Log::channel($this->logChannel)->info("Records for current year/month: {$dateCount}");

                Log::channel($this->logChannel)->warning("No records match all criteria together. Check field values in the database.");
            }

            // Get data with original criteria
            $sqlsrvData = $query->orderByDesc('actual_receipt_date')->get();

            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            
            foreach ($sqlsrvData as $data) {
                try {
                    // Log each record's key fields for debugging
                    Log::channel($this->logChannel)->debug("Processing record: po_no={$data->po_no}, gr_no={$data->gr_no}, is_confirmed={$data->is_confirmed}, inv_doc_no=" . ($data->inv_doc_no ?? 'NULL'));

                    // Check if data->po_no and data->gr_no are not empty
                    if (empty($data->po_no) || empty($data->gr_no)) {
                        Log::channel($this->logChannel)->warning("Skipping record with empty key fields: po_no={$data->po_no}, gr_no={$data->gr_no}");
                        $skippedCount++;
                        continue;
                    }

                    // Define unique key combination
                    $uniqueKey = [
                        'po_no' => $data->po_no,
                        'gr_no' => $data->gr_no,
                    ];

                    // Check if record already exists
                    $existingRecord = InvLine::where($uniqueKey)->first();
                    
                    if ($existingRecord) {
                        Log::channel($this->logChannel)->debug("Record already exists, updating: po_no={$data->po_no}, gr_no={$data->gr_no}");
                    }

                    // Use updateOrCreate to prevent race condition duplicates
                    InvLine::updateOrCreate($uniqueKey, [
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
                    ]);

                    $processedCount++;

                    // Log progress every 100 records
                    if ($processedCount % 100 === 0) {
                        Log::channel($this->logChannel)->info("Processed {$processedCount} records so far");
                    }
                    
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle duplicate key constraint violation
                    if ($e->getCode() === '23000') { // Duplicate entry error
                        Log::channel($this->logChannel)->warning("Duplicate record skipped: po_no={$data->po_no}, gr_no={$data->gr_no}");
                        $skippedCount++;
                        continue;
                    }
                    Log::channel($this->logChannel)->error("Database error processing record: po_no={$data->po_no}, gr_no={$data->gr_no}. Error: " . $e->getMessage());
                    $errorCount++;
                } catch (\Exception $e) {
                    Log::channel($this->logChannel)->error("Error processing record: po_no={$data->po_no}, gr_no={$data->gr_no}. Error: " . $e->getMessage());
                    $errorCount++;
                }
            }

            DB::commit();

            Log::channel($this->logChannel)->info('Daily invoice lines synchronized successfully via job.', [
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'total_found' => $sqlsrvData->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel($this->logChannel)->error('An error occurred during daily synchronization job: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        Log::channel($this->logChannel)->info('Daily synchronization job process finished.');
    }
}
