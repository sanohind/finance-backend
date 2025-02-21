<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvLine;
use App\Http\Resources\InvLineResource;

class FinanceInvLineController extends Controller
{
    public function getInvLine($inv_no)
    {
        $invLines = InvLine::where('inv_supplier_no', $inv_no)->get();
        return InvLineResource::collection($invLines);
    }

    public function getInvLineTransaction()
    {
        $invLines = InvLine::all();
        return InvLineResource::collection($invLines);
    }
}
