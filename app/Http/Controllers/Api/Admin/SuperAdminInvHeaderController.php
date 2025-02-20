<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\SuperAdminInvHeaderStoreRequest;
use Illuminate\Support\Facades\DB;
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

    public function printInvoice($inv_no)
    {
        $invHeader = InvHeader::with('invLines')->findOrFail($inv_no);

        // Fetch the chosen PPN record
        $ppn = InvPpn::find($invHeader->ppn_id);
        $ppnRate = $ppn ? $ppn->ppn_rate : 0.0;

        // Calculate additional amounts
        $tax_base_amount = $invHeader->tax_base_amount;
        $ppn_amount = $tax_base_amount * $ppnRate;
        $tax_amount = $invHeader->tax_amount;

        // Fetch bp_name from the first InvLine (assuming all lines have the same bp_name)
        $bp_name = $invHeader->invLines->first()->bp_name;

        // Prepare data for printing
        $data = [
            'bp_name'         => $bp_name,
            'inv_no'          => $invHeader->inv_no,
            'inv_date'        => $invHeader->inv_date,
            'tax_base_amount' => $tax_base_amount,
            'ppn_amount'      => $ppn_amount,
            'tax_amount'      => $tax_amount,
        ];

        // Return the data as a JSON response for now
        // You can replace this with actual printing logic later
        return response()->json([
            'success' => true,
            'message' => 'Invoice data retrieved successfully',
            'data'    => $data,
        ]);
    }

    public function store(SuperAdminInvHeaderStoreRequest $request)
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
            $ppnRate         =  $ppn->ppn_rate;

            if ($ppnRate === null) {
                return response()->json([
                    'message' => 'PPN Rate not found',
                ], 404);
            }

            // Calculate amounts
            $tax_base_amount = $total_dpp;
            $tax_amount      = $tax_base_amount + ($tax_base_amount * $ppnRate);
            $total_amount    = $tax_amount;

            // Create InvHeader
            $invHeader = InvHeader::create([
                'inv_no'          => $request->inv_no,
                'bp_code'         => $sp_code,
                'inv_date'        => $request->inv_date,
                'inv_faktur'      => $request->inv_faktur,
                'inv_supplier'    => $request->inv_supplier,
                'total_dpp'       => $total_dpp,
                'ppn_id'          => $request->ppn_id,
                'pph_id'          => $request->pph_id,
                'tax_base_amount' => $tax_base_amount,
                'tax_amount'      => $tax_amount,
                'total_amount'    => $total_amount,
                'status'          => 'New',
                'reason'          => $request->reason,
                'created_by'      => Auth::user()->name,
            ]);

            // Handle file uploads
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

    public function update(SuperAdminInvHeaderUpdateRequest $request, $inv_no)
    {
        $invHeader = DB::transaction(function () use ($request, $inv_no) {
            $request->validated();

            $invHeader = InvHeader::findOrFail($inv_no);

            // 1) Fetch chosen PPH record
            $pph = InvPph::find($request->pph_id);
            $pphRate        = $pph->pph_rate;

            if ($pphRate === null) {
                return response()->json([
                    'message' => 'PPH Rate not found',
                ], 404);
            }

            // 2) Manually entered pph_base_amount
            $pphBase = $request->pph_base_amount;

            // 3) Remove (uncheck) lines from invoice if needed
            if (is_array($request->inv_line_remove)) {
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
                'pph_base_amount' => $pphBase,
                'pph_amount'      => $pphAmount,
                'total_amount'    => $totalAmount, // Now “ppn_amount - pph_amount”
                'status'          => $request->status,
                'reason'          => $request->reason,
                'updated_by'      => Auth::user()->name,
            ]);

            // If status is Rejected, remove inv_supplier_no from inv_line
            if ($request->status === 'Rejected') {
                foreach ($invHeader->invLines as $line) {
                    $line->update([
                        'inv_supplier_no' => null,
                    ]);
                }
            }

            return $invHeader;
        });

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
