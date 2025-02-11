<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\SuperAdminInvHeaderStoreRequest;
use App\Http\Resources\InvHeaderResource;
use App\Http\Requests\SuperAdminInvHeaderUpdateRequest;
use App\Models\InvHeader;
use App\Models\InvLine;
use App\Models\InvDocument;
use App\Models\InvPpn;
use App\Models\InvPph;

class SuperAdminInvHeaderController extends Controller
{
    public function getInvHeader()
    {
        $invHeaders = InvHeader::all();
        return InvHeaderResource::collection($invHeaders);
    }

    public function getInvHeaderByBpCode($bp_code)
    {
        $invHeaders = InvHeader::where('bp_code', $bp_code)->get();
        return InvHeaderResource::collection($invHeaders);
    }

    public function store(SuperAdminInvHeaderStoreRequest $request)
    {
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
                // Update whichever field relates to the inv_no
                'inv_supplier_no'      => $request->inv_no,
                'inv_due_date' => $request->inv_date,
            ]);
        }

        return new InvHeaderResource($invHeader);
    }

    public function update(SuperAdminInvHeaderUpdateRequest $request, $inv_no)
    {
        $request->validated();

        $invHeader = InvHeader::findOrFail($inv_no);

        // 1) Fetch chosen PPH record
        $pph = InvPph::find($request->pph_id);
        $pphRate        = $pph ? $pph->pph_rate : 0.0;
        $pphDescription = $pph ? $pph->pph_description : '';

        // 2) Manually entered pph_base_amount
        $pphBase = $request->pph_base_amount;

        // 3) Remove (uncheck) lines from invoice if needed
        if (is_array(value: $request->inv_line_remove)) {
            foreach ($request->inv_line_remove as $lineId) {
                InvLine::where('inv_line_id', $lineId)->update([
                    'inv_supplier_no' => null,
                ]);
            }
        }

        // 4) Recalculate pph_amount
        $pphAmount = $pphBase + ($pphBase * $pphRate);

        // 5) Use the existing “tax_amount” column as “ppn_amount”
        $ppnAmount = $invHeader->tax_amount; // (Set during the 'store' step)

        // 6) total_amount = “ppn_amount minus pph_amount”
        $totalAmount = $ppnAmount - $pphAmount;

        // 7) Update the InvHeader record
        $invHeader->update([
            'pph_id'          => $request->pph_id,
            'pph_description' => $pphDescription,
            'pph_base_amount' => $pphBase,
            'pph_amount'      => $pphAmount,

            'total_amount'    => $totalAmount, // Now “ppn_amount - pph_amount”
            'status'          => $request->status,
            'reason'          => $request->reason,
            'updated_by'      => Auth::user()->name,
        ]);

        // 8) Respond based on status
        switch ($request->status) {
            case 'Ready To Pay':
                return response()->json([
                    'message' => "Invoice {$inv_no} Is Ready To Pay"
                ]);
            case 'Rejected':
                return response()->json([
                    'message' => "Invoice {$inv_no} Rejected"
                ]);
            default:
                return response()->json([
                    'message' => "Invoice {$inv_no} updated"
                ]);
        }
    }
}
