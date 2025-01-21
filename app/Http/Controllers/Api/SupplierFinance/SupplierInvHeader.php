<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\SupplierInvHeaderStoreRequest;
use App\Models\InvHeader;
use App\Models\InvDocument;
use App\Models\InvLine;

class SupplierInvHeader extends Controller
{
    public function store(SupplierInvHeaderStoreRequest $request)
    {
        $request->validated();

        InvHeader::create([
            'inv_no' => $request->inv_no,
            'inv_date' => $request->inv_date,
            'inv_faktur' => $request->inv_faktur,
            'inv_supplier' => $request->inv_supplier,
            'status' => $request->status,
            'reason' => $request->reason,
        ]);

        $files = [];

        if ($request->hasFile('invoice_file')) {
            $originalName = $request->file('invoice_file')->getClientOriginalName();
            if (!str_contains(strtolower($originalName), 'invoice')) {
                return response()->json(['error' => 'File name must contain "invoice"'], 422);
            }
            $path = $request->file('invoice_file')
                ->storeAs('invoices', 'INVOICE_'.$request->inv_no.'.pdf');
            $files['invoice'] = $path;
        }

        if ($request->hasFile('fakturpajak_file')) {
            $originalName = $request->file('fakturpajak_file')->getClientOriginalName();
            if (!str_contains(strtolower($originalName), 'fakturpajak')) {
                return response()->json(['error' => 'File name must contain "fakturpajak"'], 422);
            }
            $path = $request->file('fakturpajak_file')
                ->storeAs('faktur', 'FAKTURPAJAK_'.$request->inv_no.'.pdf');
            $files['fakturpajak'] = $path;
        }

        if ($request->hasFile('suratjalan_file')) {
            $originalName = $request->file('suratjalan_file')->getClientOriginalName();
            if (!str_contains(strtolower($originalName), 'suratjalan')) {
                return response()->json(['error' => 'File name must contain "suratjalan"'], 422);
            }
            $path = $request->file('suratjalan_file')
                ->storeAs('suratjalan', 'SURATJALAN_'.$request->inv_no.'.pdf');
            $files['suratjalan'] = $path;
        }

        if ($request->hasFile('po_file')) {
            $originalName = $request->file('po_file')->getClientOriginalName();
            if (!str_contains(strtolower($originalName), 'po')) {
                return response()->json(['error' => 'File name must contain "po"'], 422);
            }
            $path = $request->file('po_file')
                ->storeAs('po', 'PO_'.$request->inv_no.'.pdf');
            $files['po'] = $path;
        }

        // Save file paths array to inv_document
        InvDocument::create([
            'inv_no' => $request->inv_no,
            'file'   => json_encode($files),
        ]);

        // Update inv_no in inv_line
        foreach ($request->inv_line_detail as $line) {
            InvLine::where('id', $line['id'])->update(['inv_no' => $request->inv_no]);
        }

        return response()->json(['message' => 'Invoice created']);
    }

}
