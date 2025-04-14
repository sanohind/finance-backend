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

    public function getInvLineTransaction()
    {
        // Retrieve all InvLine records without any filtering
        $invLines = InvLine::all();

        // Return the collection using the resource
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
