<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\InvLine;
use App\Http\Resources\InvLineResource;
use Carbon\Carbon;

class SupplierInvLineController extends Controller
{
    public function getInvLineTransaction()
    {
        $sp_code = Auth::user()->bp_code;

        $invLines = InvLine::where('bp_id', $sp_code)->get();
        return InvLineResource::collection($invLines);
    }

    public function getUninvoicedInvLineTransaction(Request $request)
    {
        $sp_code = Auth::user()->bp_code;

        $query = InvLine::where('bp_id', $sp_code)
            ->where(function($q) {
                $q->whereNull('inv_supplier_no')->orWhere('inv_supplier_no', '');
            })
            ->where(function($q) {
                $q->whereNull('inv_due_date')->orWhere('inv_due_date', '');
            });

        if ($request->filled('gr_no')) {
            $query->where('gr_no', 'like', '%' . $request->query('gr_no') . '%');
        }

        if ($request->filled('tax_number')) {
            $query->where('inv_doc_no', 'like', '%' . $request->query('tax_number') . '%');
        }

        if ($request->filled('po_no')) {
            $query->where('po_no', 'like', '%' . $request->query('po_no') . '%');
        }

        if ($request->filled('invoice_no')) {
            $query->where('inv_doc_no', 'like', '%' . $request->query('invoice_no') . '%');
        }

        if ($request->filled('status')) {
            $statusValue = strtolower($request->query('status'));
            if ($statusValue === 'yes' || $statusValue === 'true' || $statusValue === '1') {
                $query->where('is_confirmed', true);
            } elseif ($statusValue === 'no' || $statusValue === 'false' || $statusValue === '0') {
                $query->where('is_confirmed', false);
            }
        }

        if ($request->filled('gr_date')) {
            $query->whereDate('actual_receipt_date', $request->query('gr_date'));
        }

        if ($request->filled('tax_date')) {
            $query->whereDate('inv_doc_date', $request->query('tax_date'));
        }

        if ($request->filled('po_date')) {
            $query->whereDate('created_at', $request->query('po_date'));
        }

        if ($request->filled('invoice_date')) {
            $query->whereDate('inv_doc_date', $request->query('invoice_date'));
        }

        if ($request->filled('dn_number')) {
            $query->where('inv_doc_no', 'like', '%' . $request->query('dn_number') . '%');
        }

        if ($request->filled('inv_due_date')) {
            $query->whereDate('inv_due_date', $request->query('inv_due_date'));
        }

        $invLines = $query->get();

        return InvLineResource::collection($invLines);
    }

    public function getInvLine($inv_no)
    {
        $sp_code = Auth::user()->bp_code;

        $invLines = InvLine::where('inv_supplier_no', $inv_no)
                           ->where('bp_id', $sp_code)
                           ->get();
        return InvLineResource::collection($invLines);
    }

    public function getOutstandingInvLine()
    {
        // Get the authenticated user's bp_code
        $bp_code = Auth::user()->bp_code;

        // Determine the cutoff date (10 days ago)
        $cutoffDate = Carbon::now()->subDays(10)->toDateString();

        // Get invoice lines where actual_receipt_date is <= cutoffDate
        $invLines = InvLine::with('partner')
            ->whereDate('actual_receipt_date', '<=', $cutoffDate)
            ->where('bp_id', $bp_code)
            ->get();

        // Add new property "category" to each invoice line object
        $invLines->each(function ($invLine) {
            $invLine->category = "Danger, You Need To Invoicing This Item";
        });

        return InvLineResource::collection($invLines);
    }
}
