<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvHeader;
use Illuminate\Support\Facades\Auth;
use App\Models\Local\Partner;

class SupplierDashboardController extends Controller
{
    public function dashboard()
    {
        // Get the authenticated user
        $user = Auth::user();
        $bp_code = $user->bp_code;

        // Initialize data array
        $data = [];

        // Get the count of invoices with different statuses for the authenticated user
        $data['new_invoices'] = InvHeader::where('bp_code', $bp_code)->where('status', 'New')->count();
        $data['in_process_invoices'] = InvHeader::where('bp_code', $bp_code)->where('status', 'In Process')->count();
        $data['rejected_invoices'] = InvHeader::where('bp_code', $bp_code)->where('status', 'Rejected')->count();
        $data['ready_to_payment_invoices'] = InvHeader::where('bp_code', $bp_code)->where('status', 'Ready To Payment')->count();
        $data['paid_invoices'] = InvHeader::where('bp_code', $bp_code)->where('status', 'Paid')->count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Data Retrieved Successfully',
            'data' => $data,
        ]);
    }

    public function getBusinessPartner()
    {
        $user = Auth::user();
        $partner = Partner::where('bp_code', $user->bp_code)
                          ->select('bp_code', 'bp_name', 'adr_line_1')
                          ->first();
        return response()->json($partner);
    }
}
