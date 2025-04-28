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
                $q->whereNull('inv_supplier_no')
                ->orWhere('inv_supplier_no', '')
                ->orWhere('inv_supplier_no', ' ');
            })
            ->where(function($q) {
                $q->whereNull('inv_due_date')
                ->orWhere('inv_due_date', '')
                ->orWhere('inv_due_date', ' ');
            });

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
