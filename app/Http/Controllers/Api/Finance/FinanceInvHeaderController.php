<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvHeader;
use App\Models\InvLine;
use App\Http\Requests\FinanceInvHeaderUpdateRequest;

class FinanceInvHeaderController extends Controller
{
    public function getInvHeader()
    {
        $invHeaders = InvHeader::all();
        return response()->json($invHeaders);
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
                InvLine::where('inv_no', $inv_no)->update(['inv_no' => null]);
                return response()->json(['message' => 'Invoice ' . $inv_no . ' rejected']);
            default:
                return response()->json(['message' => 'Invoice ' . $inv_no . ' updated']);
        }
    }
}
