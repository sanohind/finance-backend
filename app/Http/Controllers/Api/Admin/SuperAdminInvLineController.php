<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvLine;
use App\Models\Local\Partner;
use App\Http\Resources\InvLineResource;

class SuperAdminInvLineController extends Controller
{
    public function getAllInvLine($bp_code)
    {
        $invLines = InvLine::with('partner')->where('bp_id', $bp_code)->get();

        return InvLineResource::collection($invLines);
    }

    public function getInvLine($inv_no)
    {
        $invLines = InvLine::where('inv_supplier_no', $inv_no)->get();
        return InvLineResource::collection($invLines);
    }
}
