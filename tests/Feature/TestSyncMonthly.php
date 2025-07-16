<?php

namespace Tests\Feature;

use App\Jobs\SyncInvoiceLinesMonthlyJob;
use App\Models\ERP\InvReceipt;
use App\Models\InvLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Carbon;

class TestSyncMonthly extends TestCase
{

    public function test_sync_invoice_lines_monthly_from_financesync_to_finance()
    {
        // Data valid: bulan sekarang
        InvReceipt::create([
            'po_no' => 'POCURR',
            'receipt_no' => 'RCPTCURR',
            'receipt_line' => 1,
            'bp_id' => 'BP01',
            'bp_name' => 'PT Current',
            'currency' => 'IDR',
            'po_type' => 'TYPECURR',
            'po_reference' => 'REFCURR',
            'po_line' => 1,
            'po_sequence' => 1,
            'po_receipt_sequence' => 1,
            'actual_receipt_date' => now()->toDateString(),
            'actual_receipt_year' => now()->year,
            'actual_receipt_period' => now()->month,
            'gr_no' => 'GR01',
            'packing_slip' => 'PACKCURR',
            'item_no' => 'ITEMCURR',
            'ics_code' => 'ICS01',
            'ics_part' => 'PARTCURR',
            'part_no' => 'PARTNOCURR',
            'item_desc' => 'Item Current Month',
            'item_group' => 'GROUPCURR',
            'item_type' => 'TYPECURR',
            'item_type_desc' => 'Type Desc Curr',
            'request_qty' => 10,
            'actual_receipt_qty' => 10,
            'approve_qty' => 10,
            'unit' => 'PCS',
            'receipt_amount' => 100000,
            'receipt_unit_price' => 10000,
            'is_final_receipt' => 'Yes',
            'is_confirmed' => 'Yes',
            'inv_doc_no' => 'INV01',
            'inv_doc_date' => now()->toDateString(),
            'inv_qty' => 10,
            'inv_amount' => 100000,
            'inv_supplier_no' => 'SUPCURR',
            'inv_due_date' => now()->addDays(30)->toDateString(),
            'payment_doc' => 'PAYCURR',
            'payment_doc_date' => now()->toDateString(),
        ]);

        // Data valid: bulan sebelumnya
        $prevMonthDate = now()->subMonthNoOverflow()->setDay(13);
        InvReceipt::create([
            'po_no' => 'POPREV',
            'receipt_no' => 'RCPTPREV',
            'receipt_line' => 2,
            'bp_id' => 'BP02',
            'bp_name' => 'PT Previous',
            'currency' => 'IDR',
            'po_type' => 'TYPEPREV',
            'po_reference' => 'REFPREV',
            'po_line' => 2,
            'po_sequence' => 2,
            'po_receipt_sequence' => 2,
            'actual_receipt_date' => $prevMonthDate->toDateString(),
            'actual_receipt_year' => $prevMonthDate->year,
            'actual_receipt_period' => $prevMonthDate->month,
            'gr_no' => 'GR02',
            'packing_slip' => 'PACKPREV',
            'item_no' => 'ITEMPREV',
            'ics_code' => 'ICS02',
            'ics_part' => 'PARTPREV',
            'part_no' => 'PARTNOPREV',
            'item_desc' => 'Item Previous Month',
            'item_group' => 'GROUPPREV',
            'item_type' => 'TYPEPREV',
            'item_type_desc' => 'Type Desc Prev',
            'request_qty' => 20,
            'actual_receipt_qty' => 20,
            'approve_qty' => 20,
            'unit' => 'PCS',
            'receipt_amount' => 200000,
            'receipt_unit_price' => 10000,
            'is_final_receipt' => 'Yes',
            'is_confirmed' => 'Yes',
            'inv_doc_no' => 'INV02',
            'inv_doc_date' => $prevMonthDate->toDateString(),
            'inv_qty' => 20,
            'inv_amount' => 200000,
            'inv_supplier_no' => 'SUPPREV',
            'inv_due_date' => $prevMonthDate->addDays(30)->toDateString(),
            'payment_doc' => 'PAYPREV',
            'payment_doc_date' => $prevMonthDate->toDateString(),
        ]);

        // Data invalid: payment_doc_date null (tidak boleh masuk inv_line)
        InvReceipt::create([
            'po_no' => 'POSKIP',
            'receipt_no' => 'RCPTSKIP',
            'receipt_line' => 3,
            'bp_id' => 'BP03',
            'bp_name' => 'PT Skip',
            'currency' => 'IDR',
            'po_type' => 'TYPESKIP',
            'po_reference' => 'REFSKIP',
            'po_line' => 3,
            'po_sequence' => 3,
            'po_receipt_sequence' => 3,
            'actual_receipt_date' => now()->toDateString(),
            'actual_receipt_year' => now()->year,
            'actual_receipt_period' => now()->month,
            'gr_no' => 'GR03',
            'packing_slip' => 'PACKSKIP',
            'item_no' => 'ITEMSKIP',
            'ics_code' => 'ICS03',
            'ics_part' => 'PARTSKIP',
            'part_no' => 'PARTNOSKIP',
            'item_desc' => 'Item Skip',
            'item_group' => 'GROUPSKIP',
            'item_type' => 'TYPESKIP',
            'item_type_desc' => 'Type Desc Skip',
            'request_qty' => 30,
            'actual_receipt_qty' => 30,
            'approve_qty' => 30,
            'unit' => 'PCS',
            'receipt_amount' => 300000,
            'receipt_unit_price' => 10000,
            'is_final_receipt' => 'Yes',
            'is_confirmed' => 'Yes',
            'inv_doc_no' => 'INV03',
            'inv_doc_date' => now()->toDateString(),
            'inv_qty' => 30,
            'inv_amount' => 300000,
            'inv_supplier_no' => 'SUPSKIP',
            'inv_due_date' => now()->addDays(30)->toDateString(),
            'payment_doc' => 'PAYSKIP',
            'payment_doc_date' => null,
        ]);

        // Jalankan job sinkronisasi bulanan
        (new SyncInvoiceLinesMonthlyJob())->handle();

        // Assertion: data valid harus masuk ke data_receipt_purchase
        $this->assertDatabaseHas('data_receipt_purchase', [
            'po_no' => 'POCURR',
            'receipt_no' => 'RCPTCURR',
            'receipt_line' => 1,
        ]);
        $this->assertDatabaseHas('data_receipt_purchase', [
            'po_no' => 'POPREV',
            'receipt_no' => 'RCPTPREV',
            'receipt_line' => 2,
        ]);
    }
}
