<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvLine;
use App\Models\Local\Partner;
use App\Http\Resources\InvLineResource;
use Carbon\Carbon;

class FinanceInvLineController extends Controller
{
    public function getInvLine($inv_no)
    {
        $invLines = InvLine::where('inv_supplier_no', $inv_no)->get();
        return InvLineResource::collection($invLines);
    }

    public function getInvLineTransaction(Request $request, $bp_code)
    {
        // Start base query for the specific business partner
        $query = InvLine::with('partner')->where('bp_id', $bp_code);

        // Apply filters based on query parameters present in the request
        // These keys should match the 'FilterParams' interface in GrTracking.tsx

        if ($request->filled('gr_no')) {
            $query->where('gr_no', 'like', '%' . $request->query('gr_no') . '%');
        }

        // Assuming 'tax_number' from frontend maps to 'inv_doc_no' in the DB
        if ($request->filled('tax_number')) {
            $query->where('inv_doc_no', 'like', '%' . $request->query('tax_number') . '%');
        }

        if ($request->filled('po_no')) {
            $query->where('po_no', 'like', '%' . $request->query('po_no') . '%');
        }

        // Assuming 'invoice_no' from frontend maps to 'inv_doc_no' in the DB
        if ($request->filled('invoice_no')) {
            $query->where('inv_doc_no', 'like', '%' . $request->query('invoice_no') . '%');
        }

        // Assuming 'status' from frontend maps to 'is_confirmed' (boolean) in the DB
        if ($request->filled('status')) {
            $statusValue = strtolower($request->query('status'));
            if ($statusValue === 'yes' || $statusValue === 'true' || $statusValue === '1') {
                $query->where('is_confirmed', true);
            } elseif ($statusValue === 'no' || $statusValue === 'false' || $statusValue === '0') {
                $query->where('is_confirmed', false);
            }
            // Add more conditions if other status representations are possible
        }

        // Assuming 'gr_date' from frontend maps to 'actual_receipt_date' in the DB
        if ($request->filled('gr_date')) {
            $query->whereDate('actual_receipt_date', $request->query('gr_date'));
        }

        // Assuming 'tax_date' from frontend maps to 'inv_doc_date' in the DB
        if ($request->filled('tax_date')) {
            $query->whereDate('inv_doc_date', $request->query('tax_date'));
        }

        // Assuming 'po_date' from frontend maps to 'created_at' or a specific PO date field
        // Adjust 'created_at' if you have a dedicated 'po_date' column
        if ($request->filled('po_date')) {
            $query->whereDate('created_at', $request->query('po_date'));
        }

        // Assuming 'invoice_date' from frontend maps to 'inv_doc_date' in the DB
        if ($request->filled('invoice_date')) {
            $query->whereDate('inv_doc_date', $request->query('invoice_date'));
        }

        // Assuming 'dn_number' from frontend maps to 'inv_doc_no' or a specific DN field
        if ($request->filled('dn_number')) {
            $query->where('inv_doc_no', 'like', '%' . $request->query('dn_number') . '%');
        }

        // Execute the query with applied filters
        $invLines = $query->get(); // Consider using ->paginate() for large datasets

        // Return the data using your resource collection
        return InvLineResource::collection($invLines);
    }

    public function getOutstandingInvLine($bp_code)
    {
        // Determine the cutoff date (10 days ago)
        $cutoffDate = Carbon::now()->subDays(10)->toDateString();

        // Get invoice lines where actual_receipt_date is <= cutoffDate
        $invLines = InvLine::with('partner')
            ->whereDate('actual_receipt_date', '<=', $cutoffDate)
            ->whereHas('partner', function ($q) use ($bp_code) {
                $q->where('bp_code', $bp_code);
            })
            ->get();

        // Add new property "category" to each invoice line object
        $invLines->each(function ($invLine) {
            $invLine->category = "Danger, You Need To Invoicing This Item";
        });

        return InvLineResource::collection($invLines);
    }
}
