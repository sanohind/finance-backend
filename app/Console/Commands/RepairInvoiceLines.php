<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\InvHeader;
use App\Models\InvLine;
use Illuminate\Support\Facades\DB;

class RepairInvoiceLines extends Command
{
    protected $signature = 'repair:invoice-lines';
    protected $description = 'Menghubungkan inv_line ke invoice yang belum punya line berdasarkan bp_code dan inv_no';

    public function handle()
    {
        $invoices = InvHeader::doesntHave('invLine')->get();
        $this->info('Total invoice tanpa inv_line: ' . $invoices->count());

        foreach ($invoices as $invoice) {
            // Cari inv_line yang cocok berdasarkan bp_code dan inv_no
            $lines = InvLine::where('bp_id', $invoice->bp_code)
                ->where('inv_supplier_no', $invoice->inv_no)
                ->get();

            if ($lines->isEmpty()) {
                $this->warn("Invoice #{$invoice->inv_no} (ID: {$invoice->inv_id}) tidak ditemukan inv_line yang cocok.");
                continue;
            }

            // Attach semua line yang cocok
            $invoice->invLine()->syncWithoutDetaching($lines->pluck('inv_line_id')->toArray());
            $this->info("Invoice #{$invoice->inv_no} (ID: {$invoice->inv_id}) berhasil dihubungkan dengan " . $lines->count() . " inv_line.");
        }

        $this->info('Repair selesai.');
    }
}
