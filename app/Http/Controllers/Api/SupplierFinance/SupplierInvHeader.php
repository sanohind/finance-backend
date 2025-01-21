<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\SupplierInvHeaderStoreRequest;
use App\Models\InvHeader;

class SupplierInvHeader extends Controller
{
    public function store(SupplierInvHeaderStoreRequest $request)
    {
        $request->validated();

        InvHeader::create([
            'inv_no' => $request->inv_no,
            'inv_date' => $request->inv_date,
            'inv_faktur' => $request->inv_faktur,
            'inv_supplier' => $request->inv_supplier,
            'status' => $request->status,
            'reason' => $request->reason,
        ]);

        return response()->json(['message' => 'Invoice created']);
    }

}
