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

class SyncInvoiceLinesMonthlyJob implements ShouldQueue
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
        Log::info('Starting monthly invoice lines synchronization job (payment_doc_date filled, current/last month of this year)...');

        try {
            $currentYear = now()->year;
            $currentMonth = now()->month;
            $previousMonth = now()->subMonthNoOverflow()->month;

            $sqlsrvData = InvReceipt::whereNotNull('payment_doc_date')
                ->whereYear('payment_doc_date', $currentYear)
                ->where(function ($query) use ($currentMonth, $previousMonth) {
                    $query->whereMonth('payment_doc_date', $currentMonth)
                          ->orWhereMonth('payment_doc_date', $previousMonth);
                })
                ->orderByDesc('payment_doc_date')
                ->get();

            $syncService = new DataSyncService();
            $results = $syncService->batchSync($sqlsrvData, 'po_gr');
            $stats = $syncService->getSyncStats($results);
            Log::info('Monthly invoice lines synchronized successfully via job.', $stats);

        } catch (\Exception $e) {
            Log::error('An error occurred during monthly synchronization job: ' . $e->getMessage(), ['exception' => $e]);
        }

        Log::info('Monthly synchronization job process finished.');
    }
}
