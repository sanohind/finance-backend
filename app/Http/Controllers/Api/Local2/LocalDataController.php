<?php

namespace App\Http\Controllers\Api\Local2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvLine;
use App\Models\Local\PoDetail;
use App\Models\Local\DnHeader;
use App\Models\Local\DnDetail;

class LocalDataController extends Controller
{
    public function syncInvLine()
    {
        // Fetch data from po_detail
        $poDetails = PoDetail::all();

        foreach ($poDetails as $poDetail) {
            // Fetch related dn_header
            $dnHeader = DnHeader::where('po_no', $poDetail->po_no)->first();

            if ($dnHeader) {
                // Fetch related dn_detail
                $dnDetails = DnDetail::where('no_dn', $dnHeader->no_dn)->get();

                foreach ($dnDetails as $dnDetail) {
                    InvLine::updateOrCreate(
        [
                        'po_no'               => $poDetail->po_no
                    ],
            [
                        'supplier_id'         => $dnHeader->supplier_code,
                        'supplier'            => $dnHeader->supplier_name,
                        'po_date'             => $poDetail->planned_receipt_date,
                        'po_qty'              => $poDetail->po_qty,
                        'po_price'            => $poDetail->price,
                        'currency'            => null,
                        'rate'                => null,
                        'receipt_no'          => $dnDetail->no_dn,
                        'receipt_date'        => $dnDetail->actual_receipt_date,
                        'receipt_line'        => $dnDetail->dn_line,
                        'item'                => $poDetail->item_code,
                        'item_desc'           => $poDetail->item_desc_a,
                        'old_partno'          => $poDetail->bp_part_no,
                        'receipt_qty'         => $dnDetail->receipt_qty,
                        'receipt_unit'        => $poDetail->purchase_unit,
                        'packing_slip'        => null,
                        'receipt_status'      => $dnDetail->status_desc,
                        'warehouse'           => null,
                        'extend_price'        => $poDetail->amount,
                        'extend_price_idr'    => null,
                        // Explicitly set these columns to null:
                        'supplier_invoice'    => null,
                        'supplier_invoice_date' => null,
                        'inv_doc'             => null,
                        'inv_date'            => null,
                        'doc_code'            => null,
                        'doc_no'              => null,
                        'doc_date'            => null,
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Data synchronized successfully', 'data' => $poDetails]);
    }
}
