<?php

namespace App\Jobs;

use App\Models\ERP\InvReceipt;
use App\Services\DataSyncService;
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

            $syncService = new DataSyncService();
            $results = $syncService->batchSync($sqlsrvData, 'po_gr');
            DB::commit();
            $stats = $syncService->getSyncStats($results);
            Log::channel($this->logChannel)->info('Daily invoice lines synchronized successfully via job.', $stats);

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
