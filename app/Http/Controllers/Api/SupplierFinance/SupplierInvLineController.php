<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\InvLine;
use App\Http\Resources\InvLineResource;
use App\Services\BusinessPartnerUnifiedService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierInvLineController extends Controller
{
    protected $unifiedService;

    public function __construct(BusinessPartnerUnifiedService $unifiedService)
    {
        $this->unifiedService = $unifiedService;
    }

    public function getInvLineTransaction()
    {
        $sp_code = Auth::user()->bp_code;
        $sp_code = $this->unifiedService->normalizeBpCode($sp_code);
        
        $invLines = $this->unifiedService->getUnifiedInvLines($sp_code);
        
        return InvLineResource::collection($invLines);
    }

    public function getUninvoicedInvLineTransaction()
    {
        $sp_code = Auth::user()->bp_code;
        $sp_code = $this->unifiedService->normalizeBpCode($sp_code);
        
        $invLines = $this->unifiedService->getUnifiedUninvoicedInvLines($sp_code);
        
        return InvLineResource::collection($invLines);
    }

    public function getInvLine($inv_no)
    {
        $sp_code = Auth::user()->bp_code;
        $sp_code = $this->unifiedService->normalizeBpCode($sp_code);
        
        $bpCodes = $this->unifiedService->getUnifiedBpCodes($sp_code);
        
        $invLines = InvLine::where('inv_supplier_no', $inv_no)
                           ->whereIn('bp_id', $bpCodes)
                           ->get();
        
        return InvLineResource::collection($invLines);
    }

    public function getOutstandingInvLine()
    {
        $bp_code = Auth::user()->bp_code;
        $bp_code = $this->unifiedService->normalizeBpCode($bp_code);
        
        $invLines = $this->unifiedService->getUnifiedOutstandingInvLines($bp_code);
        
        return InvLineResource::collection($invLines);
    }
}
