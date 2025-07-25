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
        Log::channel($this->logChannel)->info('Starting daily invoice lines synchronization job (is_confirmed: Yes, inv_doc_no: null)...');

        try {
            // Log the year/month being used for filtering
            $year = now()->year;
            $month = now()->month;
            Log::channel($this->logChannel)->info("Filtering records for year: {$year}, month: {$month}");

            // Log the SQL query for debugging
            $query = InvReceipt::where('is_confirmed', 'Yes')
                ->where('inv_doc_no', '')
                ->whereYear('actual_receipt_date', $year)
                ->whereMonth('actual_receipt_date', $month);

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
                    ->select("SELECT TOP 10 is_confirmed, inv_doc_no, actual_receipt_date FROM data_receipt_purchase WHERE is_confirmed='Yes' AND inv_doc_no = ''");
                Log::channel($this->logChannel)->info("Sample records with is_confirmed='Yes' AND empty inv_doc_no: " . json_encode($testRecords));

                $emptyInvDocCount = InvReceipt::where('inv_doc_no', '')->count();
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
            foreach ($sqlsrvData as $data) {
                // Log each record's key fields for debugging
                Log::channel($this->logChannel)->debug("Processing record: po_no={$data->po_no}, receipt_no={$data->receipt_no}, receipt_line={$data->receipt_line}, is_confirmed={$data->is_confirmed}, inv_doc_no=" . ($data->inv_doc_no ?? 'NULL'));

                // Check if data->po_no is not empty
                if (empty($data->po_no) || empty($data->receipt_no) || empty($data->receipt_line)) {
                    Log::channel($this->logChannel)->warning("Skipping record with empty key fields: po_no={$data->po_no}, receipt_no={$data->receipt_no}, receipt_line={$data->receipt_line}");
                    continue;
                }

                try {
                    DB::beginTransaction();

                    // Define unique key combination
                    $uniqueKey = [
                        'po_no' => $data->po_no,
                        'gr_no' => $data->gr_no,
                    ];

                    // Use firstOrCreate to prevent race condition duplicates
                    $invLine = InvLine::updateOrCreate($uniqueKey, [
                        'po_no' => $data->po_no,
                        'bp_id' => $data->bp_id,
                        'bp_name' => $data->bp_name,
                        'currency' => $data->currency,
                        'po_type' => $data->po_type,
                        'po_reference' => $data->po_reference,
                        'po_line' => $data->po_line,
                        'po_sequence' => $data->po_sequence,
                        'po_receipt_sequence' => $data->po_receipt_sequence,
                        'actual_receipt_date' => $data->actual_receipt_date,
                        'actual_receipt_year' => $data->actual_receipt_year,
                        'actual_receipt_period' => $data->actual_receipt_period,
                        'gr_no' => $data->gr_no,
                        'packing_slip' => $data->packing_slip,
                        'item_no' => $data->item_no,
                        'ics_code' => $data->ics_code,
                        'ics_part' => $data->ics_part,
                        'part_no' => $data->part_no,
                        'item_desc' => $data->item_desc,
                        'item_group' => $data->item_group,
                        'item_type' => $data->item_type,
                        'item_type_desc' => $data->item_type_desc,
                        'request_qty' => $data->request_qty,
                        'actual_receipt_qty' => $data->actual_receipt_qty,
                        'approve_qty' => $data->approve_qty,
                        'unit' => $data->unit,
                        'receipt_amount' => $data->receipt_amount,
                        'receipt_unit_price' => $data->receipt_unit_price,
                        'is_final_receipt' => $data->is_final_receipt,
                        'is_confirmed' => $data->is_confirmed,
                        'inv_doc_no' => $data->inv_doc_no,
                        'inv_doc_date' => $data->inv_doc_date,
                        'inv_qty' => $data->inv_qty,
                        'inv_amount' => $data->inv_amount,
                        'payment_doc' => $data->payment_doc,
                        'payment_doc_date' => $data->payment_doc_date
                    ]);

                    // If record already existed, update it with latest data
                    // if (!$invLine->wasRecentlyCreated) {
                    //     $invLine->update([
                    //         'bp_id' => $data->bp_id,
                    //         'bp_name' => $data->bp_name,
                    //         'currency' => $data->currency,
                    //         'po_type' => $data->po_type,
                    //         'po_reference' => $data->po_reference,
                    //         'po_line' => $data->po_line,
                    //         'po_sequence' => $data->po_sequence,
                    //         'po_receipt_sequence' => $data->po_receipt_sequence,
                    //         'actual_receipt_date' => $data->actual_receipt_date,
                    //         'actual_receipt_year' => $data->actual_receipt_year,
                    //         'actual_receipt_period' => $data->actual_receipt_period,
                    //         'gr_no' => $data->gr_no,
                    //         'packing_slip' => $data->packing_slip,
                    //         'item_no' => $data->item_no,
                    //         'ics_code' => $data->ics_code,
                    //         'ics_part' => $data->ics_part,
                    //         'part_no' => $data->part_no,
                    //         'item_desc' => $data->item_desc,
                    //         'item_group' => $data->item_group,
                    //         'item_type' => $data->item_type,
                    //         'item_type_desc' => $data->item_type_desc,
                    //         'request_qty' => $data->request_qty,
                    //         'actual_receipt_qty' => $data->actual_receipt_qty,
                    //         'approve_qty' => $data->approve_qty,
                    //         'unit' => $data->unit,
                    //         'receipt_amount' => $data->receipt_amount,
                    //         'receipt_unit_price' => $data->receipt_unit_price,
                    //         'is_final_receipt' => $data->is_final_receipt,
                    //         'is_confirmed' => $data->is_confirmed,
                    //         'inv_doc_no' => $data->inv_doc_no,
                    //         'inv_doc_date' => $data->inv_doc_date,
                    //         'inv_qty' => $data->inv_qty,
                    //         'inv_amount' => $data->inv_amount,
                    //         'inv_supplier_no' => $data->inv_supplier_no,
                    //         'inv_due_date' => $data->inv_due_date,
                    //         'payment_doc' => $data->payment_doc,
                    //         'payment_doc_date' => $data->payment_doc_date
                    //     ]);
                    //     Log::channel($this->logChannel)->debug("Updated existing record: po_no={$data->po_no}, receipt_no={$data->receipt_no}, receipt_line={$data->receipt_line}");
                    // } else {
                    //     Log::channel($this->logChannel)->debug("Created new record: po_no={$data->po_no}, receipt_no={$data->receipt_no}, receipt_line={$data->receipt_line}");
                    // }

                    DB::commit();
                    $processedCount++;

                    // Log progress every 100 records
                    if ($processedCount % 100 === 0) {
                        Log::channel($this->logChannel)->info("Processed {$processedCount} records so far");
                    }
                } catch (\Illuminate\Database\QueryException $e) {
                    DB::rollBack();
                    // Handle duplicate key constraint violation
                    if ($e->getCode() === '23000') { // Duplicate entry error
                        Log::channel($this->logChannel)->warning("Duplicate record skipped: po_no={$data->po_no}, receipt_no={$data->receipt_no}, receipt_line={$data->receipt_line}");
                        continue;
                    }
                    Log::channel($this->logChannel)->error("Database error processing record: po_no={$data->po_no}, receipt_no={$data->receipt_no}, receipt_line={$data->receipt_line}. Error: " . $e->getMessage());
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::channel($this->logChannel)->error("Error processing record: po_no={$data->po_no}, receipt_no={$data->receipt_no}, receipt_line={$data->receipt_line}. Error: " . $e->getMessage());
                }
            }

            Log::channel($this->logChannel)->info('Daily invoice lines synchronized successfully via job. Records processed: ' . $processedCount);

        } catch (\Exception $e) {
            Log::channel($this->logChannel)->error('An error occurred during daily synchronization job: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        Log::channel($this->logChannel)->info('Daily synchronization job process finished.');
    }
}
