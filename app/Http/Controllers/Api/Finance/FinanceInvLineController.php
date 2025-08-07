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
        // Ambil seluruh bp_code parent & child
        $bpCodes = Partner::relatedBpCodes($bp_code)->pluck('bp_code');
        // Start base query for the specific business partner (unified)
        $query = InvLine::with('partner')->whereIn('bp_id', $bpCodes);

        // Apply filters based on query parameters present in the request
        // These are the main filters used by the GR Tracking form

        // Packing Slip filter - maps to 'packing_slip' field
        if ($request->filled('packing_slip')) {
            $query->where('packing_slip', 'like', '%' . $request->query('packing_slip') . '%');
        }

        // Receipt Number filter - maps to 'receipt_no' field
        if ($request->filled('receipt_no')) {
            $query->where('receipt_no', 'like', '%' . $request->query('receipt_no') . '%');
        }

        // PO Number filter
        if ($request->filled('po_no')) {
            $query->where('po_no', 'like', '%' . $request->query('po_no') . '%');
        }

        // Date range filters
        if ($request->filled('gr_date_from')) {
            $query->whereDate('actual_receipt_date', '>=', $request->query('gr_date_from'));
        }

        if ($request->filled('gr_date_to')) {
            $query->whereDate('actual_receipt_date', '<=', $request->query('gr_date_to'));
        }

        // Execute the query with applied filters and order by newest actual_receipt_date first
        $invLines = $query->orderBy('actual_receipt_date', 'desc')->get(); // Consider using ->paginate() for large datasets

        // Return the data using your resource collection
        return InvLineResource::collection($invLines);
    }

    public function getUninvoicedInvLineTransaction(Request $request, $bp_code)
    {
        // Ambil seluruh bp_code parent & child
        $bpCodes = Partner::relatedBpCodes($bp_code)->pluck('bp_code');
        // Start base query for the specific business partner and uninvoiced lines (unified)
        $query = InvLine::with('partner')
            ->whereIn('bp_id', $bpCodes)
            ->whereDoesntHave('invHeader');
        // Apply filters based on query parameters present in e request
        // These are the same filters used by the GR Tracking form

        // Packing Slip filter - maps to 'packing_slip' field
        if ($request->filled('packing_slip')) {
            $query->where('packing_slip', 'like', '%' . $request->query('packing_slip') . '%');
        }

        // Receipt Number filter - maps to 'receipt_no' field
        if ($request->filled('receipt_no')) {
            $query->where('receipt_no', 'like', '%' . $request->query('receipt_no') . '%');
        }

        // PO Number filter
        if ($request->filled('po_no')) {
            $query->where('po_no', 'like', '%' . $request->query('po_no') . '%');
        }

        // Date range filters
        if ($request->filled('gr_date_from')) {
            $query->whereDate('actual_receipt_date', '>=', $request->query('gr_date_from'));
        }

        if ($request->filled('gr_date_to')) {
            $query->whereDate('actual_receipt_date', '<=', $request->query('gr_date_to'));
        }

        // Execute the query with applied filters and order by newest actual_receipt_date first
        $invLines = $query->orderBy('actual_receipt_date', 'desc')->get();

        // Return the data using your resource collection
        return InvLineResource::collection($invLines);
    }

    public function getOutstandingInvLine($bp_code)
    {
        // Ambil seluruh bp_code parent & child
        $bpCodes = Partner::relatedBpCodes($bp_code)->pluck('bp_code');
        // Determine the cutoff date (10 days ago)
        $cutoffDate = Carbon::now()->subDays(10)->toDateString();

        // Get invoice lines where actual_receipt_date is <= cutoffDate (unified)
        $invLines = InvLine::with('partner')
            ->whereDate('actual_receipt_date', '<=', $cutoffDate)
            ->whereHas('partner', function ($q) use ($bpCodes) {
                $q->whereIn('bp_code', $bpCodes);
            })
            ->get();

        // Add new property "category" to each invoice line object
        $invLines->each(function ($invLine) {
            $invLine->category = "Danger, You Need To Invoicing This Item";
        });

        return InvLineResource::collection($invLines);
    }
}
