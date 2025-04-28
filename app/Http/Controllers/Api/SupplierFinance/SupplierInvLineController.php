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

    public function getUninvoicedInvLineTransaction()
    {
        $sp_code = Auth::user()->bp_code;

        // Only get inv lines where inv_supplier_no and inv_due_date are null
        $invLines = InvLine::where('bp_id', $sp_code)
            ->whereNull('inv_supplier_no')
            ->whereNull('inv_due_date')
            ->get();

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
