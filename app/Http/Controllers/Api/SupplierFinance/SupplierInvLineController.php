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

        $invLines = InvLine::where('bp_id', $sp_code)->orderBy('actual_receipt_date', 'desc')->get();
        return InvLineResource::collection($invLines);
    }

    public function getUninvoicedInvLineTransaction()
    {
        $sp_code = Auth::user()->bp_code;
        Log::info('[SupplierInvLineController] Attempting getUninvoicedInvLineTransaction for bp_code: ' . $sp_code);

        // Log the query and bindings
        $query = InvLine::where('bp_id', $sp_code)
            ->whereNull('inv_supplier_no')
            ->whereNull('inv_due_date')
            ->orderBy('actual_receipt_date', 'desc');

        Log::info('[SupplierInvLineController] SQL Query: ' . $query->toSql());
        Log::info('[SupplierInvLineController] Bindings: ', $query->getBindings());

        $invLines = $query->get();

        if ($invLines->isEmpty()) {
            Log::warning('[SupplierInvLineController] No records found with inv_supplier_no IS NULL and inv_due_date IS NULL.');
            Log::info('[SupplierInvLineController] Checking for empty strings instead of NULL...');

            $countWithEmptyStrings = InvLine::where('bp_id', $sp_code)
                ->where(function ($q) {
                    $q->where('inv_supplier_no', '')
                      ->orWhereNull('inv_supplier_no'); // Keep original null check too
                })
                ->where(function ($q) {
                    $q->where('inv_due_date', '')
                      ->orWhereNull('inv_due_date'); // Keep original null check too
                })
                ->count();
            Log::info('[SupplierInvLineController] Count if checking for empty strings OR NULL for relevant fields: ' . $countWithEmptyStrings);

            $countBpIdOnly = InvLine::where('bp_id', $sp_code)->count();
            Log::info('[SupplierInvLineController] Total InvLine records for bp_code ' . $sp_code . ': ' . $countBpIdOnly);
        } else {
            Log::info('[SupplierInvLineController] Found ' . $invLines->count() . ' uninvoiced lines.');
        }

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
