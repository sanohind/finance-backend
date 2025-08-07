<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\InvLine;
use App\Http\Resources\InvLineResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // Add this
use Illuminate\Support\Facades\Log; // Add this

class SupplierInvLineController extends Controller
{
    public function getInvLineTransaction()
    {
        $sp_code = Auth::user()->bp_code;
        $bpCodes = \App\Models\Local\Partner::relatedBpCodes($sp_code)->pluck('bp_code');
        $invLines = InvLine::whereIn('bp_id', $bpCodes)->orderBy('actual_receipt_date', 'desc')->get();
        return InvLineResource::collection($invLines);
    }

    public function getUninvoicedInvLineTransaction()
    {
        $sp_code = Auth::user()->bp_code;
        $bpCodes = \App\Models\Local\Partner::relatedBpCodes($sp_code)->pluck('bp_code');
        $invLines = InvLine::whereIn('bp_id', $bpCodes)
            ->whereNull('inv_supplier_no')
            ->whereNull('inv_due_date')
            ->orderBy('actual_receipt_date', 'desc')
            ->get();
        return InvLineResource::collection($invLines);
    }

    public function getInvLine($inv_no)
    {
        $sp_code = Auth::user()->bp_code;
        $bpCodes = \App\Models\Local\Partner::relatedBpCodes($sp_code)->pluck('bp_code');
        $invLines = InvLine::where('inv_supplier_no', $inv_no)
                           ->whereIn('bp_id', $bpCodes)
                           ->get();
        return InvLineResource::collection($invLines);
    }

    public function getOutstandingInvLine()
    {
        $bp_code = Auth::user()->bp_code;
        $bpCodes = \App\Models\Local\Partner::relatedBpCodes($bp_code)->pluck('bp_code');
        $cutoffDate = Carbon::now()->subDays(10)->toDateString();
        $invLines = InvLine::with('partner')
            ->whereDate('actual_receipt_date', '<=', $cutoffDate)
            ->whereIn('bp_id', $bpCodes)
            ->get();
        $invLines->each(function ($invLine) {
            $invLine->category = "Danger, You Need To Invoicing This Item";
        });
        return InvLineResource::collection($invLines);
    }
}
