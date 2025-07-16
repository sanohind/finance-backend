<?php

namespace Tests\Feature;

use App\Jobs\SyncInvoiceLinesDailyJob;
use App\Models\ERP\InvReceipt;
use App\Models\InvLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestSyncDaily extends TestCase
{
    public function test_sync_invoice_lines_from_financesync_to_finance()
    {
        // Buat data dummy di database sumber (financesync)
        // Pastikan model InvReceipt sudah menggunakan $connection = 'sqlsrv' (atau koneksi financesync)
        InvReceipt::create([
            'po_no' => 'PO123',
            'receipt_no' => 'RCPT123',
            'receipt_line' => 1,
            'is_confirmed' => 'Yes',
            'inv_doc_no' => '',
            'actual_receipt_date' => now(),
            'bp_id' => 'BP001',
            'bp_name' => 'BP Test',
            'currency' => 'IDR',
            'po_type' => 'Standard',
            'po_reference' => 'REF123',
            'po_line' => 10,
            'po_sequence' => 1,
            'po_receipt_sequence' => 1,
            'actual_receipt_year' => now()->year,
            'actual_receipt_period' => now()->month,
            'gr_no' => 'GR001',
            'packing_slip' => 'PS001',
            'item_no' => 'ITEM123',
            'ics_code' => 'ICS1',
            'ics_part' => 'PARTA',
            'part_no' => 'PART123',
            'item_desc' => 'Item Test',
            'item_group' => 'GroupA',
            'item_type' => 'TypeA',
            'item_type_desc' => 'DescTypeA',
            'request_qty' => 100,
            'actual_receipt_qty' => 95,
            'approve_qty' => 95,
            'unit' => 'PCS',
            'receipt_amount' => 1000000,
            'receipt_unit_price' => 10526,
            'is_final_receipt' => 'Yes',
            'inv_doc_date' => null,
            'inv_qty' => 95,
            'inv_amount' => 1000000,
            'inv_supplier_no' => 'SUPP123',
            'inv_due_date' => null,
            'payment_doc' => null,
            'payment_doc_date' => null,
        ]);

        // Jalankan job sinkronisasi (data akan masuk ke InvLine di koneksi finance)
        (new SyncInvoiceLinesDailyJob())->handle();

        // Validasi bahwa data sudah berhasil masuk ke database tujuan (finance)
        $this->assertDatabaseHas('data_receipt_purchase', [
            'po_no' => 'PO123',
            'receipt_no' => 'RCPT123',
            'receipt_line' => 1,
            'item_no' => 'ITEM123',
            'actual_receipt_qty' => 95,
            'is_confirmed' => 'Yes',
        ]);
    }
}
