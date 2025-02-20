<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvHeader;
use App\Models\InvLine;
use App\Http\Requests\FinanceInvHeaderUpdateRequest;
use App\Http\Resources\InvHeaderResource;
use App\Models\InvPph;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinanceInvHeaderController extends Controller
{
    public function getInvHeader()
    {
        $invHeaders = InvHeader::all();
        return InvHeaderResource::collection($invHeaders);
    }

    public function getInvHeaderByBpCode($bp_code)
    {
        $invHeaders = InvHeader::where('bp_code', $bp_code)->get();
        return InvHeaderResource::collection($invHeaders);
    }

    public function update(FinanceInvHeaderUpdateRequest $request, $inv_no)
    {
        $invHeader = DB::transaction(function () use ($request, $inv_no) {
            $request->validated();

            $invHeader = InvHeader::findOrFail($inv_no);

            // 1) Fetch chosen PPH record
            $pph = InvPph::find($request->pph_id);
            $pphRate = $pph ? $pph->pph_rate : null;
            if ($pphRate === null) {
                return response()->json([
                    'message' => 'PPH Rate not found',
                ], 404);
            }

            // 2) Manually entered pph_base_amount
            $pphBase = $request->pph_base_amount;

            // 3) Remove (uncheck) lines from invoice if needed
            if (is_array($request->inv_line_remove)) {
                foreach ($request->inv_line_remove as $lineId) {
                    InvLine::where('inv_line_id', $lineId)->update([
                        'inv_supplier_no' => null,
                    ]);
                }
            }

            // 4) Recalculate pph_amount
            $pphAmount = $pphBase + ($pphBase * $pphRate);

            // 5) Use the existing “tax_amount” column as “ppn_amount”
            $ppnAmount = $invHeader->tax_amount; // (Set during the 'store' step)

            // 6) total_amount = “ppn_amount minus pph_amount”
            $totalAmount = $ppnAmount - $pphAmount;

            // 7) Update the InvHeader record
            $invHeader->update([
                'pph_id'          => $request->pph_id,
                'pph_base_amount' => $pphBase,
                'pph_amount'      => $pphAmount,
                'total_amount'    => $totalAmount, // Now “ppn_amount - pph_amount”
                'status'          => $request->status,
                'reason'          => $request->reason,
                'updated_by'      => Auth::user()->name,
            ]);

            // If status is Rejected, remove inv_supplier_no from inv_line
            if ($request->status === 'Rejected') {
                foreach ($invHeader->invLines as $line) {
                    $line->update([
                        'inv_supplier_no' => null,
                    ]);
                }
            }

            return $invHeader;
        });

        // 8) Respond based on status
        switch ($request->status) {
            case 'Ready To Payment':
                return response()->json([
                    'message' => "Invoice {$inv_no} Is Ready To Pay"
                ]);
            case 'Rejected':
                return response()->json([
                    'message' => "Invoice {$inv_no} Rejected"
                ]);
            default:
                return response()->json([
                    'message' => "Invoice {$inv_no} updated"
                ]);
        }
    }

    public function updateStatusToInProcess($inv_no)
    {
        $invHeader = InvHeader::where('inv_no', $inv_no)->where('status', 'New')->firstOrFail();

        $invHeader->update([
            'status' => 'In Process',
            'updated_by' => Auth::user()->name,
        ]);

        return response()->json([
            'message' => "Invoice {$inv_no} status updated to In Process"
        ]);
    }

    // Example usage: call this function when the invoice number is clicked
    // document.getElementById('invoice-number').addEventListener('click', function() {
    //     const inv_no = this.dataset.invNo; // Assuming the invoice number is stored in a data attribute
    //     updateStatusToInProcess(inv_no);
    // });
}
