<?php

namespace Database\Seeders;

use App\Models\ERP\InvReceipt;
use App\Models\InvLine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // DB facade might not be strictly needed if using Eloquent only
use Carbon\Carbon;

class InvLineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting InvLineSeeder...');

        try {
            // Get data from InvReceipt (similar to original controller logic)
            // Fetch for the year 2025 and from March onwards.
            $currentYear = 2025; // Or Carbon::now()->year if you want it to be dynamic in the future
            $startMonth = 3; // March

            $sqlsrvData = InvReceipt::whereYear('actual_receipt_date', $currentYear)
                                    ->whereMonth('actual_receipt_date', '>=', $startMonth)
                                    ->orderByDesc('actual_receipt_date')
                                    ->get();

            $this->command->info('Fetched ' . $sqlsrvData->count() . ' records from InvReceipt for year ' . $currentYear . ', from month ' . $startMonth . ' onwards.');

            if ($sqlsrvData->isEmpty()) {
                $this->command->info('No data to seed into inv_lines table.');
                return;
            }

            // Copy data to local inv_lines table
            foreach ($sqlsrvData as $data) {
                InvLine::updateOrCreate(
                    [
                        'po_no' => $data->po_no,
                        'receipt_no' => $data->receipt_no,
                        'receipt_line' => $data->receipt_line
                    ],
                    [
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
                        // 'receipt_no' => $data->receipt_no, // Already in key for updateOrCreate
                        // 'receipt_line' => $data->receipt_line, // Already in key for updateOrCreate
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
                        'inv_supplier_no' => $data->inv_supplier_no,
                        'inv_due_date' => $data->inv_due_date,
                        'payment_doc' => $data->payment_doc,
                        'payment_doc_date' => $data->payment_doc_date,
                        // Timestamps will be handled by Eloquent automatically if $timestamps = true in InvLine model
                        // 'created_at' => Carbon::now(),
                        // 'updated_at' => Carbon::now(),
                    ]
                );
            }

            $this->command->info('InvLineSeeder successfully copied/updated ' . count($sqlsrvData) . ' records.');

        } catch (\Exception $e) {
            $this->command->error('Error in InvLineSeeder: ' . $e->getMessage());
        }
    }
}
