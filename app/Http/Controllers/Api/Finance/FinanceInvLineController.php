<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvLine;

class FinanceInvLineController extends Controller
{
    public function getInvLine($inv_no)
    {
        $invLines = InvLine::where('inv_no', $inv_no)->get();
        return response()->json($invLines);
    }
}
