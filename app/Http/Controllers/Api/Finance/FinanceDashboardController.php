<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvHeader;

class FinanceDashboardController extends Controller
{
    public function dashboard()
    {
        // Initialize data array
        $data = [];

        // Get the count of invoices with different statuses
        $data['new_invoices'] = InvHeader::where('status', 'New')->count();
        $data['in_process_invoices'] = InvHeader::where('status', 'In Process')->count();
        $data['rejected_invoices'] = InvHeader::where('status', 'Rejected')->count();
        $data['ready_to_payment_invoices'] = InvHeader::where('status', 'Ready To Payment')->count();
        $data['paid_invoices'] = InvHeader::where('status', 'Paid')->count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Data Retrieved Successfully',
            'data' => $data,
        ]);
    }
}
