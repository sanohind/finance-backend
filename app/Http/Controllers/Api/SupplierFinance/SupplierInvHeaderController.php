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

        foreach ($request->inv_line_detail as $line) {
            $invLine = InvLine::find($line);
            $total_dpp += $invLine->receipt_qty * $invLine->po_price;
        }

        $tax = $total_dpp * 0.11;
        $total_amount = $total_dpp + $tax;

        InvHeader::create([
            'inv_no' => $request->inv_no,
            'inv_date' => $request->inv_date,
            'inv_faktur' => $request->inv_faktur,
            'inv_supplier' => $request->inv_supplier,
            'total_dpp' => $total_dpp,
            'tax' => $tax,
            'total_amount' => $total_amount,
            'status' => "In Process",
            'reason' => $request->reason,
            'bp_code' => $sp_code, // Autofill with the authenticated user's bp_code
        ]);

        $files = [];

        if ($request->hasFile('invoice_file')) {
            $path = $request->file('invoice_file')
                ->storeAs('public/invoices', 'INVOICE_'.$request->inv_no.'.pdf');
            $files['invoice'] = $path;
        }

        if ($request->hasFile('fakturpajak_file')) {
            $path = $request->file('fakturpajak_file')
                ->storeAs('public/faktur', 'FAKTURPAJAK_'.$request->inv_no.'.pdf');
            $files['fakturpajak'] = $path;
        }

        if ($request->hasFile('suratjalan_file')) {
            $path = $request->file('suratjalan_file')
                ->storeAs('public/suratjalan', 'SURATJALAN_'.$request->inv_no.'.pdf');
            $files['suratjalan'] = $path;
        }

        if ($request->hasFile('po_file')) {
            $path = $request->file('po_file')
                ->storeAs('public/po', 'PO_'.$request->inv_no.'.pdf');
            $files['po'] = $path;
        }

        // Save file paths array to inv_document
        InvDocument::create([
            'inv_no' => $request->inv_no,
            'file'   => json_encode($files),
        ]);

        // Update supplier_invoice in inv_line
        foreach ($request->inv_line_detail as $line) {
            InvLine::where('inv_line_id', $line)->update([
                'supplier_invoice' => $request->inv_no,
                'supplier_invoice_date' => $request->inv_date
            ]);
        }

        return new InvHeaderResource(['message' => 'Invoice created']);
    }

}
