<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\SupplierInvHeaderStoreRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\InvHeaderResource;
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
        $sp_code = Auth::user()->bp_code;
        $request->validated();

        $total_dpp = 0;

        // 1) Gather total DPP based on selected inv_line_detail
        foreach ($request->inv_line_detail as $line) {
            $invLine = InvLine::find($line);
            $total_dpp += $invLine->receipt_qty * $invLine->po_price;
        }

        // 2) Fetch the chosen PPN record (ID = 1 => 10%, ID = 2 => 11%, etc.)
        $ppn = InvPpn::find($request->ppn_id);
        $ppnRate = $ppn ? $ppn->ppn_rate : 0.0;          // e.g. 0.10 or 0.11
        $ppnDescription = $ppn ? $ppn->ppn_description : ''; // e.g. "10%" or "11%"

        // 3) Calculate tax_base_amount and tax_amount
        $tax_base_amount = $total_dpp;                          // base amount
        $tax_amount      = $tax_base_amount + ($tax_base_amount * $ppnRate);

        // 4) total_amount becomes whatever your final total is (here, the same as tax_amount)
        $total_amount = $tax_amount;

        // 5) Create the InvHeader record using your new columns
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

        // 6) Handle file uploads
        $files = [];

        if ($request->hasFile('invoice_file')) {
            $path = $request->file('invoice_file')->storeAs('public/invoices', 'INVOICE_'.$request->inv_no.'.pdf');
            $files['invoice'] = $path;
        }

        if ($request->hasFile('fakturpajak_file')) {
            $path = $request->file('fakturpajak_file')->storeAs('public/faktur', 'FAKTURPAJAK_'.$request->inv_no.'.pdf');
            $files['fakturpajak'] = $path;
        }

        if ($request->hasFile('suratjalan_file')) {
            $path = $request->file('suratjalan_file')->storeAs('public/suratjalan', 'SURATJALAN_'.$request->inv_no.'.pdf');
            $files['suratjalan'] = $path;
        }

        if ($request->hasFile('po_file')) {
            $path = $request->file('po_file')->storeAs('public/po', 'PO_'.$request->inv_no.'.pdf');
            $files['po'] = $path;
        }

        InvDocument::create([
            'inv_no' => $request->inv_no,
            'file'   => json_encode($files),
        ]);

        // 7) Update supplier_invoice in inv_line
        foreach ($request->inv_line_detail as $line) {
            InvLine::where('inv_line_id', $line)->update([
                'inv_supplier_no'      => $request->inv_no,
                'inv_due_date'    => $request->inv_date,
            ]);
        }

        // 8) Return the created InvHeader via the resource
        return new InvHeaderResource($invHeader);
    }

}
