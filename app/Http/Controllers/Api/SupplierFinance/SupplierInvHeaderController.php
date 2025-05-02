<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\SupplierInvHeaderStoreRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\InvHeaderResource;
use App\Http\Requests\SupplierInvHeaderRejectedRequest;
use Illuminate\Support\Facades\DB;
use App\Models\InvHeader;
use App\Models\InvDocument;
use App\Models\InvLine;
use App\Models\InvPpn;
use App\Models\InvPph;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceCreateMail;
use Illuminate\Support\Facades\Storage;

class SupplierInvHeaderController extends Controller
{
    public function getInvHeader()
    {
        $sp_code = Auth::user()->bp_code;

        // Fetch inv_headers filtered by the authenticated user's bp_code, with related invLine
        $invHeaders = InvHeader::with('invLine')
            ->where('bp_code', $sp_code)
            ->get();

        return InvHeaderResource::collection($invHeaders);
    }

    public function rejectInvoice(SupplierInvHeaderRejectedRequest $request, $inv_no)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $sp_code = Auth::user()->bp_code;

        $invHeader = InvHeader::where('inv_no', $inv_no)
            ->where('bp_code', $sp_code)
            ->where('status', 'New')
            ->first();

        if (!$invHeader) {
            return response()->json([
                'message' => 'Invoice not found or cannot be rejected (already processed by admin)'
            ], 404);
        }

        DB::transaction(function () use ($invHeader, $request) {
            // Update invoice status and reason
            $invHeader->update([
                'status'     => 'Rejected',
                'reason'     => $request->reason,
                'updated_by' => Auth::user()->name,
            ]);

            // Remove inv_supplier_no and inv_due_date from all related inv_lines
            foreach ($invHeader->invLine as $line) {
                $line->update([
                    'inv_supplier_no' => null,
                    'inv_due_date'    => null,
                ]);
            }
        });

        return response()->json([
            'message' => "Invoice {$inv_no} has been rejected and can be invoiced again."
        ]);
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

            $total_dpp = 0;

            // Gather total DPP from selected inv lines
            foreach ($request->inv_line_detail as $line) {
                $invLine = InvLine::find($line);
                $total_dpp += $invLine->approve_qty * $invLine->receipt_unit_price;
            }

            // Fetch the chosen PPN record
            $ppn = InvPpn::find($request->ppn_id);
            $ppnRate = $ppn ? $ppn->ppn_rate : null;
            if ($ppnRate === null) {
                return response()->json([
                    'message' => 'PPN Rate not found',
                ], 404);
            }

            $tax_base_amount = $total_dpp;
            $tax_amount      = $tax_base_amount + ($tax_base_amount * $ppnRate);
            $total_amount    = $tax_amount;

            // Create the InvHeader record (note the inclusion of pph_id similar to SuperAdmin)
            $invHeader = InvHeader::create([
                'inv_no'          => $request->inv_no,
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

            // Handle file uploads if needed
            $files = [];
            if ($request->hasFile('invoice_file')) {
                $files[] = [
                    'type' => 'invoice',
                    'path' => $request->file('invoice_file')
                        ->storeAs('invoices', 'INVOICE_'.$request->inv_no.'.pdf')
                ];
            }
            if ($request->hasFile('fakturpajak_file')) {
                $files[] = [
                    'type' => 'fakturpajak',
                    'path' => $request->file('fakturpajak_file')
                        ->storeAs('faktur', 'FAKTURPAJAK_'.$request->inv_no.'.pdf')
                ];
            }
            if ($request->hasFile('suratjalan_file')) {
                $files[] = [
                    'type' => 'suratjalan',
                    'path' => $request->file('suratjalan_file')
                        ->storeAs('suratjalan', 'SURATJALAN_'.$request->inv_no.'.pdf')
                ];
            }
            if ($request->hasFile('po_file')) {
                $files[] = [
                    'type' => 'po',
                    'path' => $request->file('po_file')
                        ->storeAs('po', 'PO_'.$request->inv_no.'.pdf')
                ];
            }

            // Save file references with type
            foreach ($files as $file) {
                InvDocument::create([
                    'inv_no' => $request->inv_no,
                    'type' => $file['type'],
                    'file' => $file['path']
                ]);
            }

            // Update inv_line references
            foreach ($request->inv_line_detail as $line) {
                InvLine::where('inv_line_id', $line)->update([
                    'inv_supplier_no' => $request->inv_no,
                    'inv_due_date'    => $request->inv_date,
                ]);
            }

            $partner = \App\Models\Local\Partner::where('bp_code', $invHeader->bp_code)->select('adr_line_1')->first();

            // Send email
            Mail::to('neyvagheida@gmail.com')->send(new InvoiceCreateMail([
                'partner_address' => $partner->adr_line_1 ?? '',
                'bp_code'         => $invHeader->bp_code,
                'inv_no'          => $request->inv_no,
                'status'          => $invHeader->status,
                'total_amount'    => $invHeader->total_amount,
                'plan_date'       => $invHeader->plan_date,
            ]));

            return $invHeader;
        });

        // Return the newly created InvHeader outside the transaction
        return new InvHeaderResource($invHeader);
    }

    public function stream($type, $filename)
    {
        $allowedTypes = ['invoices', 'faktur', 'suratjalan', 'po'];
        if (!in_array($type, $allowedTypes)) {
            abort(404);
        }
        $path = $type . '/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }
        return response()->file(storage_path('app/public/' . $path));
    }

}
