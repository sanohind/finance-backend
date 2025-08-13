<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvLine;
use App\Models\Local\Partner;
use App\Http\Resources\InvLineResource;
use App\Services\BusinessPartnerUnifiedService;
use Carbon\Carbon;

class FinanceInvLineController extends Controller
{
    protected $unifiedService;

    public function __construct(BusinessPartnerUnifiedService $unifiedService)
    {
        $this->unifiedService = $unifiedService;
    }

    public function getInvLine($inv_no)
    {
        $invLines = InvLine::where('inv_supplier_no', $inv_no)->get();
        return InvLineResource::collection($invLines);
    }

    public function getInvLineTransaction(Request $request, $bp_code)
    {
        // Normalize bp_code
        $bp_code = $this->unifiedService->normalizeBpCode($bp_code);
        
        // Prepare filters from request
        $filters = [
            'packing_slip' => $request->query('packing_slip'),
            'receipt_no' => $request->query('receipt_no'),
            'po_no' => $request->query('po_no'),
            'gr_date_from' => $request->query('gr_date_from'),
            'gr_date_to' => $request->query('gr_date_to'),
        ];

        // Get unified InvLine data
        $invLines = $this->unifiedService->getUnifiedInvLines($bp_code, $filters);

        return InvLineResource::collection($invLines);
    }

    public function getUninvoicedInvLineTransaction(Request $request, $bp_code)
    {
        // Normalize bp_code
        $bp_code = $this->unifiedService->normalizeBpCode($bp_code);
        
        // Prepare filters from request
        $filters = [
            'packing_slip' => $request->query('packing_slip'),
            'receipt_no' => $request->query('receipt_no'),
            'po_no' => $request->query('po_no'),
            'gr_date_from' => $request->query('gr_date_from'),
            'gr_date_to' => $request->query('gr_date_to'),
        ];

        // Get unified uninvoiced InvLine data
        $invLines = $this->unifiedService->getUnifiedUninvoicedInvLines($bp_code, $filters);

        return InvLineResource::collection($invLines);
    }

    public function getOutstandingInvLine($bp_code)
    {
        // Normalize bp_code
        $bp_code = $this->unifiedService->normalizeBpCode($bp_code);
        
        // Get unified outstanding InvLine data
        $invLines = $this->unifiedService->getUnifiedOutstandingInvLines($bp_code);

        return InvLineResource::collection($invLines);
    }
}
