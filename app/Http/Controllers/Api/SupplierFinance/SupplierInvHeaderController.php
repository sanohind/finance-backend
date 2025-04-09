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
use App\Models\InvPph;

class SupplierInvHeaderController extends Controller
{
    public function getInvHeader()
    {
        $sp_code = Auth::user()->bp_code;

        // Fetch inv_headers filtered by the authenticated user's bp_code
        $invHeaders = InvHeader::where('bp_code', $sp_code)->get();

        return InvHeaderResource::collection($invHeaders);
    }

    public function getPpn()
    {
        $ppn = InvPpn::select('ppn_id', 'ppn_description')->get();
        return response()->json($ppn);
    }

    public function getPph()
    {
        $pph = InvPph::select('pph_id', 'pph_description')->get();
        return response()->json($pph);
    }

    public function store(SupplierInvHeaderStoreRequest $request)
    {
        $invHeader = DB::transaction(function () use ($request) {
            $sp_code = Auth::user()->bp_code;
            $request->validated();

            // Calculate totals
            $total_dpp = 0;
            foreach ($request->inv_line_detail as $line) {
                $invLine = InvLine::find($line);
                $total_dpp += $invLine->approve_qty * $invLine->receipt_unit_price;
            }

            // Find needed tax info
            $ppn = InvPpn::find($request->ppn_id);
            $ppnRate = $ppn ? $ppn->ppn_rate : 0;
            $tax_base_amount = $total_dpp;
            $tax_amount      = $tax_base_amount + ($tax_base_amount * $ppnRate);
            $total_amount    = $tax_amount;

            // Create new invoice header
            // IMPORTANT: Instead of using $request->inv_no directly here,
            // you may decide to auto-generate your own OR accept the one from frontend.
            // Below we just use whatever the request has for example, but keep reading
            // to see how we pass $invHeader->inv_no to InvDocument.
            $invHeader = InvHeader::create([
                'inv_no'          => $request->inv_no,  // or generate a brand new inv_no if you prefer
                'bp_code'         => $sp_code,
                'inv_date'        => $request->inv_date,
                'inv_faktur'      => $request->inv_faktur,
                'inv_faktur_date' => $request->inv_faktur_date,
                'total_dpp'       => $total_dpp,
                'ppn_id'          => $request->ppn_id,
                'tax_base_amount' => $tax_base_amount,
                'tax_amount'      => $tax_amount,
                'total_amount'    => $total_amount,
                'status'          => 'New',
                'reason'          => $request->reason,
                'created_by'      => Auth::user()->name,
            ]);

            // Handle file uploads; each file is stored, then we create an InvDocument
            // referencing *$invHeader->inv_no* to ensure correct linkage

            $files = [];

            if ($request->hasFile('invoice_file')) {
                $pdf = $request->file('invoice_file');
                $path = $pdf->store('public/invoices');
                $files[] = ['type' => 'invoice', 'path' => $path];
            }

            if ($request->hasFile('fakturpajak_file')) {
                $pdf = $request->file('fakturpajak_file');
                $path = $pdf->store('public/faktur');
                $files[] = ['type' => 'fakturpajak', 'path' => $path];
            }

            if ($request->hasFile('suratjalan_file')) {
                $pdf = $request->file('suratjalan_file');
                $path = $pdf->store('public/suratjalan');
                $files[] = ['type' => 'suratjalan', 'path' => $path];
            }

            if ($request->hasFile('po_file')) {
                $pdf = $request->file('po_file');
                $path = $pdf->store('public/po');
                $files[] = ['type' => 'po', 'path' => $path];
            }

            // Link each uploaded file to the new invoice number from $invHeader
            foreach ($files as $file) {
                InvDocument::create([
                    'inv_no' => $invHeader->inv_no,  // NOTE: we take the actual inv_no from the newly created record
                    'type'   => $file['type'],
                    'file'   => $file['path'],
                ]);
            }

            // Update lines
            foreach ($request->inv_line_detail as $line) {
                InvLine::where('inv_line_id', $line)->update([
                    'inv_supplier_no' => $invHeader->inv_no,
                    'inv_due_date'    => $request->inv_date,
                ]);
            }

            return $invHeader;
        });

        // Return the newly created InvHeader resource as usual
        return new InvHeaderResource($invHeader);
    }

}
