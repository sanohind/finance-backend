<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\InvLine;

class SupplierInvLineController extends Controller
{
    public function getInvLineTransaction()
    {
        $sp_code = Auth::user()->bp_code;

        $invLines = InvLine::where('dn_supplier', $sp_code)
                           ->get();

        return response()->json($invLines);
    }

    public function getInvLine($inv_no)
    {
        $sp_code = Auth::user()->bp_code;

        $invLines = InvLine::where('inv_no', $inv_no)
                            ->where('dn_supplier', $sp_code)
                            ->get();
                            
        return response()->json($invLines);
    }
}
