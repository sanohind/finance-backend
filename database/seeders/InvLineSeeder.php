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
            // Define the date range
            $startDate = Carbon::create(2025, 3, 1)->startOfDay();
            $today = Carbon::today()->endOfDay(); // Current date is May 18, 2025

            $chunkSize = 2000; // Define chunk size
            $totalProcessed = 0; // Initialize counter for total processed records

            $this->command->info("Fetching records from InvReceipt with actual_receipt_date between {$startDate->toDateTimeString()} and {$today->toDateTimeString()}, in chunks of {$chunkSize}...");

            InvReceipt::where('actual_receipt_date', '>=', $startDate)
                ->where('actual_receipt_date', '<=', $today)
                ->orderByDesc('actual_receipt_date') // Keep existing primary order
                ->orderBy('po_no', 'asc')            // Add secondary sort for determinism
                ->orderBy('receipt_no', 'asc')       // Add tertiary sort
                ->orderBy('receipt_line', 'asc')     // Add quaternary sort
                ->chunk($chunkSize, function ($invReceiptsChunk) use (&$totalProcessed) {
                    $countInChunk = $invReceiptsChunk->count();
                    if ($countInChunk === 0) {
                        return false; // Stop chunking if no more records
                    }

                    $this->command->info("Processing a chunk of {$countInChunk} records...");

                    $invLinesToUpsert = $invReceiptsChunk->map(function ($data) {
                        return [
                            'po_no' => $data->po_no,
                            'receipt_no' => $data->receipt_no,
                            'receipt_line' => $data->receipt_line,
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
                            'inv_supplier_no' => $data->inv_supplier_no,
                            'inv_due_date' => $data->inv_due_date,
                            'payment_doc' => $data->payment_doc,
                            'payment_doc_date' => $data->payment_doc_date,
                            // created_at and updated_at are handled by Eloquent's upsert
                        ];
                    })->toArray();

                    if (!empty($invLinesToUpsert)) {
                        InvLine::upsert(
                            $invLinesToUpsert,
                            ['po_no', 'receipt_no', 'receipt_line'], // Unique keys for matching
                            [ // Columns to update on duplicate
                                'bp_id', 'bp_name', 'currency', 'po_type', 'po_reference', 'po_line',
                                'po_sequence', 'po_receipt_sequence', 'actual_receipt_date',
                                'actual_receipt_year', 'actual_receipt_period', 'gr_no', 'packing_slip',
                                'item_no', 'ics_code', 'ics_part', 'part_no', 'item_desc', 'item_group',
                                'item_type', 'item_type_desc', 'request_qty', 'actual_receipt_qty',
                                'approve_qty', 'unit', 'receipt_amount', 'receipt_unit_price',
                                'is_final_receipt', 'is_confirmed', 'inv_doc_no', 'inv_doc_date',
                                'inv_qty', 'inv_amount', 'inv_supplier_no', 'inv_due_date',
                                'payment_doc', 'payment_doc_date',
                                // 'updated_at' is automatically handled by upsert
                            ]
                        );
                    }

                    $totalProcessed += $countInChunk;
                    return true; // Continue to the next chunk
                });

            if ($totalProcessed > 0) {
                $this->command->info("InvLineSeeder successfully copied/updated {$totalProcessed} records.");
            } else {
                $this->command->info('No data to seed into inv_lines table for the specified period.');
            }

        } catch (\Exception $e) {
            $this->command->error('Error in InvLineSeeder: ' . $e->getMessage());
        }
    }
}
