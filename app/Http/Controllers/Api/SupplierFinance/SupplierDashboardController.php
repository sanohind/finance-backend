<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvHeader;
use Illuminate\Support\Facades\Auth;
use App\Models\Local\Partner;
use App\Services\BusinessPartnerUnifiedService;

class SupplierDashboardController extends Controller
{
    protected $unifiedService;

    public function __construct(BusinessPartnerUnifiedService $unifiedService)
    {
        $this->unifiedService = $unifiedService;
    }

    public function dashboard()
    {
        // Get the authenticated user
        $user = Auth::user();
        $bp_code = $this->unifiedService->normalizeBpCode($user->bp_code);

        // Get unified dashboard data
        $data = $this->unifiedService->getUnifiedDashboardData($bp_code);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Data Retrieved Successfully',
            'data' => $data,
        ]);
    }

    public function getBusinessPartner()
    {
        $user = Auth::user();
        $bp_code = $this->unifiedService->normalizeBpCode($user->bp_code);
        
        $partners = $this->unifiedService->getUnifiedPartners($bp_code)
            ->map(function($partner) {
                return [
                    'bp_code' => $partner->bp_code,
                    'bp_name' => $partner->bp_name,
                    'adr_line_1' => $partner->adr_line_1 ?? null,
                ];
            });
            
        return response()->json($partners);
    }
}