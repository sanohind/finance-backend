<?php

namespace App\Jobs;

use App\Services\DataSyncService;
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

            $syncService = new DataSyncService();
            $results = $syncService->batchSync($sqlsrvData, 'po_gr');
            DB::commit();
            $stats = $syncService->getSyncStats($results);
            Log::info("SyncManualJob completed", $stats);
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
