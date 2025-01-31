<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\InvLine;
use App\Http\Resources\InvLineResource;

class SupplierInvLineController extends Controller
{
    public function getInvLineTransaction()
    {
        $sp_code = Auth::user()->bp_code;

        $invLines = InvLine::where('supplier_id', $sp_code)->get();
        return InvLineResource::collection($invLines);
    }

    public function getInvLine($inv_no)
    {
        $sp_code = Auth::user()->bp_code;

        $invLines = InvLine::where('supplier_invoice', $inv_no)
                           ->where('supplier_id', $sp_code)
                           ->get();
        return InvLineResource::collection($invLines);
    }
}
