<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\SupplierInvHeaderStoreRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\InvHeaderResource;
use App\Http\Requests\SupplierInvHeaderRejectedRequest;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Local\Partner;
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
            ->orderBy('created_at', 'desc')
            ->get();

        return InvHeaderResource::collection($invHeaders);
    }

    public function rejectInvoice(SupplierInvHeaderRejectedRequest $request, $inv_id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $sp_code = Auth::user()->bp_code;

        $invHeader = InvHeader::with('invLine')
            ->where('inv_id', $inv_id)
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
            // Use the relationship to get the line IDs, then update via direct query
            $lineIds = $invHeader->invLine->pluck('inv_line_id');
            InvLine::whereIn('inv_line_id', $lineIds)->update([
                'inv_supplier_no' => null,
                'inv_due_date'    => null,
            ]);
        });

        return response()->json([
            'message' => "Invoice {$invHeader->inv_no} has been rejected and can be invoiced again."
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
                'created_by'      => Auth::user()->name,
            ]);

            // Handle file uploads if needed
            $files = [];
            if ($request->hasFile('invoice_file')) {
                $files[] = [
                    'type' => 'invoice',
                    'path' => $request->file('invoice_file')
                        ->storeAs('invoices', 'INVOICE_'.$invHeader->inv_id.'.pdf', 'public')
                ];
            }
            if ($request->hasFile('fakturpajak_file')) {
                $files[] = [
                    'type' => 'fakturpajak',
                    'path' => $request->file('fakturpajak_file')
                        ->storeAs('faktur', 'FAKTURPAJAK_'.$invHeader->inv_id.'.pdf', 'public')
                ];
            }
            if ($request->hasFile('suratjalan_file')) {
                $files[] = [
                    'type' => 'suratjalan',
                    'path' => $request->file('suratjalan_file')
                        ->storeAs('suratjalan', 'SURATJALAN_'.$invHeader->inv_id.'.pdf', 'public')
                ];
            }
            if ($request->hasFile('po_file')) {
                $files[] = [
                    'type' => 'po',
                    'path' => $request->file('po_file')
                        ->storeAs('po', 'PO_'.$invHeader->inv_id.'.pdf', 'public')
                ];
            }

            // Save file references with type
            foreach ($files as $file) {
                InvDocument::create([
                    'inv_id' => $invHeader->inv_id,
                    'type' => $file['type'],
                    'file' => $file['path']
                ]);
            }

            // Update inv_line references
            foreach ($request->inv_line_detail as $line) {
                $invHeader->invLine()->attach($line);
                InvLine::where('inv_line_id', $line)->update([
                    'inv_supplier_no' => $request->inv_no,
                    'inv_due_date'    => $request->inv_date,
                ]);
            }

            $partner = Partner::where('bp_code', $invHeader->bp_code)->select('adr_line_1')->first();

            // Send email
            $adminUsers = User::where('role', 2)->get();
            foreach ($adminUsers as $adminUser) {
                Mail::to($adminUser->email)->send(new InvoiceCreateMail([
                    'partner_address' => $partner->adr_line_1 ?? '',
                    'bp_code'         => $invHeader->bp_code,
                    'inv_no'          => $request->inv_no,
                    'status'          => $invHeader->status,
                    'total_amount'    => $invHeader->total_amount,
                ]));
            }

            return $invHeader;
        });

        // Return the newly created InvHeader outside the transaction
        return new InvHeaderResource($invHeader);
    }

    public function stream($type, $filename)
    {
        $allowedTypes = ['invoices', 'faktur', 'suratjalan', 'po', 'receipts'];
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
