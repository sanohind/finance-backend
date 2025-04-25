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

        // Get all uninvoiced lines for this supplier
        // (Those with a receipt date but no invoice number assigned yet)
        $invLines = InvLine::with('partner')
            ->whereNotNull('actual_receipt_date')
            ->whereNull('inv_supplier_no')
            ->where('bp_id', $bp_code)
            ->get();

        // Determine the cutoff date (10 days ago)
        $cutoffDate = Carbon::now()->subDays(10)->toDateString();

        // Add new property "category" to each invoice line object
        $invLines->each(function ($invLine) use ($cutoffDate) {
            $receiptDate = Carbon::parse($invLine->actual_receipt_date);

            if ($receiptDate->toDateString() <= $cutoffDate) {
                $invLine->category = "Danger, You Need To Invoicing This Item";
            } else {
                $invLine->category = "Safe, You Need To Invoicing In Time";
            }
        });

        return InvLineResource::collection($invLines);
    }
}
