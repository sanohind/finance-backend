<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\InvLine;

class SupplierInvLineController extends Controller
{
    public function getInvLine()
    {
        $sp_code = Auth::user()->bp_code;

        $invLines = InvLine::where('dn_supplier', $sp_code)
                           ->get();

        return response()->json($invLines);
    }
}
