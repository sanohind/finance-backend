<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\SupplierInvHeaderStoreRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\InvHeaderResource;
use Illuminate\Support\Facades\DB;
use App\Models\InvHeader;
use App\Models\InvDocument;
use App\Models\InvLine;
use App\Models\InvPpn;

class SupplierInvHeaderController extends Controller
{
    public function getInvHeader()
    {
        $sp_code = Auth::user()->bp_code;

        // Fetch inv_headers filtered by the authenticated user's bp_code
        $invHeaders = InvHeader::where('bp_code', $sp_code)->get();

        return InvHeaderResource::collection($invHeaders);
    }

    public function store(SupplierInvHeaderStoreRequest $request)
    {
        $invHeader = DB::transaction(function () use ($request) {
            $sp_code = Auth::user()->bp_code;
            $request->validated();

            $total_dpp = 0;

            // Gather total DPP from selected inv lines
            foreach ($request->inv_line_detail as $line) {
                $invLine = InvLine::find($line);
                $total_dpp += $invLine->receipt_qty * $invLine->po_price;
            }

            // Fetch the chosen PPN record
            $ppn = InvPpn::find($request->ppn_id);
            $ppnRate         = $ppn ? $ppn->ppn_rate : 0.0;
            $ppnDescription  = $ppn ? $ppn->ppn_description : '';

            $tax_base_amount = $total_dpp;
            $tax_amount      = $tax_base_amount + ($tax_base_amount * $ppnRate);
            $total_amount    = $tax_amount;

            $invHeader = InvHeader::create([
                'inv_no'          => $request->inv_no,
                'bp_code'         => $sp_code,
                'inv_date'        => $request->inv_date,
                'inv_faktur'      => $request->inv_faktur,
                'inv_supplier'    => $request->inv_supplier,
                'total_dpp'       => $total_dpp,
                'ppn_id'          => $request->ppn_id,
                'tax_description' => $ppnDescription,
                'tax_base_amount' => $tax_base_amount,
                'tax_amount'      => $tax_amount,
                'total_amount'    => $total_amount,
                'status'          => 'New',
                'reason'          => $request->reason,
                'created_by'      => Auth::user()->name,
            ]);

            // Handle file uploads if needed
            $files = [];
            if ($request->hasFile('invoice_file')) {
                $files['invoice'] = $request->file('invoice_file')
                    ->storeAs('public/invoices', 'INVOICE_'.$request->inv_no.'.pdf');
            }
            if ($request->hasFile('fakturpajak_file')) {
                $files['fakturpajak'] = $request->file('fakturpajak_file')
                    ->storeAs('public/faktur', 'FAKTURPAJAK_'.$request->inv_no.'.pdf');
            }
            if ($request->hasFile('suratjalan_file')) {
                $files['suratjalan'] = $request->file('suratjalan_file')
                    ->storeAs('public/suratjalan', 'SURATJALAN_'.$request->inv_no.'.pdf');
            }
            if ($request->hasFile('po_file')) {
                $files['po'] = $request->file('po_file')
                    ->storeAs('public/po', 'PO_'.$request->inv_no.'.pdf');
            }

            // Save file references
            InvDocument::create([
                'inv_no' => $request->inv_no,
                'file'   => json_encode($files),
            ]);

            // Update inv_line references
            foreach ($request->inv_line_detail as $line) {
                InvLine::where('inv_line_id', $line)->update([
                    'inv_supplier_no' => $request->inv_no,
                    'inv_due_date'    => $request->inv_date,
                ]);
            }

            return $invHeader;
        });

        // Return the newly created InvHeader outside the transaction
        return new InvHeaderResource($invHeader);
    }

}
