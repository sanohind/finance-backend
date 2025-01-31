<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvHeader;
use App\Models\InvLine;
use App\Http\Requests\FinanceInvHeaderUpdateRequest;
use App\Http\Resources\InvHeaderResource;

class FinanceInvHeaderController extends Controller
{
    public function getInvHeader()
    {
        $invHeaders = InvHeader::all();
        return InvHeaderResource::collection($invHeaders);
    }

    public function update(FinanceInvHeaderUpdateRequest $request, $inv_no)
    {
        $request->validated();

        $invHeader = InvHeader::findOrFail($inv_no);
        $invHeader->update([
            'status' => $request->status,
            'reason' => $request->reason,
        ]);

        switch ($request->status) {
            case 'Approved':
                return response()->json(['message' => 'Invoice ' . $inv_no . ' updated']);
            case 'Rejected':
                InvLine::where('supplier_invoice', $inv_no)->update(['supplier_invoice' => null]);
                return response()->json(['message' => 'Invoice ' . $inv_no . ' rejected']);
            default:
                return response()->json(['message' => 'Invoice ' . $inv_no . ' updated']);
        }
    }
}
