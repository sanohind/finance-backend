<?php

namespace App\Http\Controllers\Api\Finance;

use Carbon\Carbon;
use App\Models\Local\Partner;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvHeader;
use App\Models\InvLine;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\FinanceInvHeaderUpdateRequest;
use App\Http\Requests\FinancePaymentDocumentRequest;
use App\Http\Requests\FinanceInvHeaderStoreRequest;
use App\Http\Resources\InvHeaderResource;
use App\Mail\InvoiceReadyMail;
use App\Models\User;
use App\Models\InvPph;
use App\Models\InvPpn;
use App\Models\InvDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Mail\InvoiceCreateMail;

class FinanceInvHeaderController extends Controller
{
    public function getInvHeader()
    {
        // Eager load the invLine relationship for all invoice headers
        $invHeaders = InvHeader::with('invLine')->orderBy('created_at', 'desc')->get();
        return InvHeaderResource::collection($invHeaders);
    }

    public function getInvHeaderByBpCode($bp_code)
    {
        $invHeaders = InvHeader::where('bp_code', $bp_code)->get();
        return InvHeaderResource::collection($invHeaders);
    }

    public function getPph()
    {
        $pph = InvPph::select('pph_id', 'pph_description')->get();
        return response()->json($pph);
    }

    public function getPpn()
    {
        $ppn = InvPpn::select('ppn_id', 'ppn_description')->get();
        return response()->json($ppn);
    }

    public function getInvHeaderDetail($inv_no)
    {
        // Fetch InvHeader with related invLine and ppn
        $invHeader = InvHeader::with(['invLine'])->where('inv_no', $inv_no)->first();

        if (!$invHeader) {
            return response()->json([
                'message' => 'Invoice header not found'
            ], 404);
        }

        // Return the InvHeader data including related invLine and ppn
        return new InvHeaderResource($invHeader);
    }

    public function store(FinanceInvHeaderStoreRequest $request)
    {
        $invHeader = DB::transaction(function () use ($request) {
            $request->validated();

            $total_dpp = 0;

            // Gather total DPP from selected inv lines
            $firstInvLine = null;
            foreach ($request->inv_line_detail as $line) {
                $invLine = InvLine::find($line);
                if (!$invLine) {
                    throw new \Exception("InvLine with ID {$line} not found.");
                }
                if (!$firstInvLine) {
                    $firstInvLine = $invLine;
                }
                $total_dpp += $invLine->approve_qty * $invLine->receipt_unit_price;
            }

            // Use bp_id from the first selected InvLine as bp_code for InvHeader
            if (!$firstInvLine) {
                throw new \Exception("No InvLine selected.");
            }
            $bp_code = $firstInvLine->bp_id;

            // Fetch the chosen PPN record
            $ppn = InvPpn::find($request->ppn_id);
            $ppnRate = $ppn ? $ppn->ppn_rate : null;

            if ($ppnRate === null) {
                return response()->json([
                    'message' => 'PPN Rate not found',
                ], 404);
            }

            // Calculate amounts
            $tax_base_amount = $total_dpp;
            $tax_amount      = $tax_base_amount + ($tax_base_amount * $ppnRate);
            $total_amount    = $tax_amount;

            // Create InvHeader
            $invHeader = InvHeader::create([
                'inv_no'          => $request->inv_no,
                'bp_code'         => $bp_code, // Use bp_id from InvLine
                'inv_date'        => $request->inv_date,
                'inv_faktur'      => $request->inv_faktur,
                'inv_faktur_date' => $request->inv_faktur_date,
                'total_dpp'       => $total_dpp,
                'ppn_id'          => $request->ppn_id,
                'tax_base_amount' => $tax_base_amount,
                'tax_amount'      => $tax_amount,
                'total_amount'    => $total_amount,
                'status'          => 'New',
                'created_by'      => Auth::user()->name,
            ]);

            // Handle file uploads
            $files = [];
            if ($request->hasFile('invoice_file')) {
                $files[] = [
                    'type' => 'invoice',
                    'path' => $request->file('invoice_file')
                        ->storeAs('invoices', 'INVOICE_'.$request->inv_no.'.pdf')
                ];
            }
            if ($request->hasFile('fakturpajak_file')) {
                $files[] = [
                    'type' => 'fakturpajak',
                    'path' => $request->file('fakturpajak_file')
                        ->storeAs('faktur', 'FAKTURPAJAK_'.$request->inv_no.'.pdf')
                ];
            }
            if ($request->hasFile('suratjalan_file')) {
                $files[] = [
                    'type' => 'suratjalan',
                    'path' => $request->file('suratjalan_file')
                        ->storeAs('suratjalan', 'SURATJALAN_'.$request->inv_no.'.pdf')
                ];
            }
            if ($request->hasFile('po_file')) {
                $files[] = [
                    'type' => 'po',
                    'path' => $request->file('po_file')
                        ->storeAs('po', 'PO_'.$request->inv_no.'.pdf')
                ];
            }

            // Save file references with type
            foreach ($files as $file) {
                InvDocument::create([
                    'inv_no' => $request->inv_no,
                    'type' => $file['type'],
                    'file' => $file['path']
                ]);
            }

            // Update inv_line references
            foreach ($request->inv_line_detail as $line) {
                InvLine::where('inv_line_id', $line)->update([
                    'inv_supplier_no' => $request->inv_no,
                    'inv_due_date'    => $request->inv_date,
                ]);
            }

            $partner = Partner::where('bp_code', $invHeader->bp_code)->select('adr_line_1')->first();

            // Send email
            $adminUsers = User::where('role', 2)->get();
            foreach ($adminUsers as $adminUser) {
                Mail::to($adminUser->email)->send(new InvoiceCreateMail([
                    'partner_address' => $partner->adr_line_1 ?? '',
                    'bp_code'         => $invHeader->bp_code,
                    'inv_no'          => $request->inv_no,
                    'status'          => $invHeader->status,
                    'total_amount'    => $invHeader->total_amount,
                    'plan_date'       => $invHeader->plan_date,
                ]));
            }

            return $invHeader;
        });

        // Return the newly created InvHeader outside the transaction
        return new InvHeaderResource($invHeader);
    }

    public function update(FinanceInvHeaderUpdateRequest $request, $inv_no)
    {
        // Check if status is Rejected but no reason provided (can stay outside the transaction)
        if ($request->status === 'Rejected' && empty($request->reason)) {
            return response()->json([
                'message' => 'Reason is required when rejecting an invoice'
            ], 422);
        }

        // Wrap the entire process in a single database transaction
        return DB::transaction(function () use ($request, $inv_no) {
            $request->validated();

            // Eager load invPpn and invPph relationships
            $invHeader = InvHeader::with(['invLine', 'invPpn', 'invPph'])->findOrFail($inv_no);

            // If status is Rejected, skip pph/plan_date logic
            if ($request->status === 'Rejected') {
                if (empty($request->reason)) {
                    throw new \InvalidArgumentException('Reason is required when rejecting an invoice');
                }

                $invHeader->update([
                    'status'     => $request->status,
                    'reason'     => $request->reason,
                    'updated_by' => Auth::user()->name,
                    // Explicitly nullify PPH fields if it's a rejection
                    'pph_id'          => null,
                    'pph_base_amount' => null,
                    'pph_amount'      => null,
                ]);

                foreach ($invHeader->invLine as $line) {
                    $line->update([
                        'inv_supplier_no' => null,
                        'inv_due_date'    => null,
                    ]);
                }
            } else { // Status is not 'Rejected'
                $finalPphId = null;
                $finalPphBaseAmount = null;
                $finalPphAmountCalculated = null;

                // Only process PPH if pph_id is provided in the request
                if ($request->filled('pph_id')) {
                    $pph = InvPph::find($request->pph_id);

                    if (!$pph || $pph->pph_rate === null) {
                        return response()->json([
                            'message' => 'PPH record not found or PPH rate is missing for the provided PPH ID.',
                        ], 404);
                    }
                    $pphRate = $pph->pph_rate;
                    $finalPphId = $request->pph_id;

                    // Only process pph_base_amount if it's provided (when pph_id is also present)
                    if ($request->filled('pph_base_amount')) {
                        $finalPphBaseAmount = (float)$request->pph_base_amount;
                        $finalPphAmountCalculated = $finalPphBaseAmount + ($finalPphBaseAmount * $pphRate);
                    } else {
                        // pph_id was provided, but pph_base_amount was not. Set base and calculated amount to null.
                        $finalPphBaseAmount = null;
                        $finalPphAmountCalculated = null;
                    }
                }
                // If $request->pph_id was not filled, $finalPphId, $finalPphBaseAmount,
                // and $finalPphAmountCalculated remain null by their initial declaration.

                // 3) Remove (uncheck) lines from invoice if needed
                if (is_array($request->inv_line_remove)) {
                    foreach ($request->inv_line_remove as $lineId) {
                        InvLine::where('inv_line_id', $lineId)->update([
                            'inv_supplier_no' => null,
                        ]);
                    }
                }

                // 5) PPN Amount (tax_amount from InvHeader is PPN-inclusive based on store logic)
                $ppnInclusiveAmount = $invHeader->tax_amount;

                // 6) total_amount = "ppn_amount minus pph_amount_effect"
                $totalAmount = $ppnInclusiveAmount - ($finalPphAmountCalculated ?? 0);

                // 7) Update the InvHeader record
                $invHeader->update([
                    'pph_id'          => $finalPphId,
                    'pph_base_amount' => $finalPphBaseAmount,
                    'pph_amount'      => $finalPphAmountCalculated,
                    'total_amount'    => $totalAmount,
                    'status'          => $request->status,
                    'plan_date'       => $request->plan_date,
                    'reason'          => $request->reason,
                    'updated_by'      => Auth::user()->name,
                ]);
            }

            // 8) Respond based on status
            switch ($invHeader->status) {
                case 'Ready To Payment':
                    try {
                        $today = Carbon::parse($invHeader->updated_at)->format('Y-m-d');
                        $receiptCount = InvHeader::whereDate('updated_at', $today)
                            ->where('status', 'Ready To Payment')
                            ->count();
                        $receiptNumber = 'SANOH' . Carbon::parse($invHeader->updated_at)->format('Ymd') . '/' . ($receiptCount + 1);

                        $partner = Partner::where('bp_code', $invHeader->bp_code)->select("adr_line_1")->first();
                        $poNumbers = InvLine::where('inv_supplier_no', $inv_no)
                            ->pluck('po_no')
                            ->unique()
                            ->implode(', ');

                        $taxAmountForPdf = $invHeader->total_dpp * 0.11;

                        $pdfPphBaseAmount = $invHeader->pph_base_amount;
                        $pdfPphAmount = $invHeader->pph_amount;

                        $pdf = PDF::loadView('printreceipt', [
                            'invHeader'       => $invHeader,
                            'partner_address' => $partner->adr_line_1 ?? '',
                            'po_numbers'      => $poNumbers,
                            'tax_amount'      => $taxAmountForPdf,
                            'pph_base_amount' => $pdfPphBaseAmount,
                            'pph_amount'      => $pdfPphAmount,
                        ]);

                        $filepath = storage_path("app/public/receipts/RECEIPT_{$inv_no}.pdf");
                        if (!file_exists(dirname($filepath))) {
                            mkdir(dirname($filepath), 0777, true);
                        }
                        $pdf->save($filepath);

                        $supplierUser = User::where('bp_code', $invHeader->bp_code)->first();
                        if ($supplierUser && $supplierUser->email) {
                            Mail::to($supplierUser->email)->send(new InvoiceReadyMail([
                                'partner_address' => $partner->adr_line_1 ?? '',
                                'bp_code'         => $invHeader->bp_code,
                                'inv_no'          => $invHeader->inv_no,
                                'status'          => $invHeader->status,
                                'total_amount'    => $invHeader->total_amount,
                                'plan_date'       => $invHeader->plan_date,
                                'filepath'        => $filepath
                            ]));
                        }

                        $invHeader->update([
                            'receipt_path'   => "receipts/RECEIPT_{$inv_no}.pdf",
                            'receipt_number' => $receiptNumber
                        ]);

                        return response()->json([
                            'message'        => "Invoice {$inv_no} Is Ready To Payment",
                            'receipt_path'   => "receipts/RECEIPT_{$inv_no}.pdf",
                            'receipt_number' => $receiptNumber
                        ]);

                    } catch (\Exception $e) {
                        throw new \RuntimeException('Error generating receipt: ' . $e->getMessage(), 0, $e);
                    }
                case 'Rejected':
                    return response()->json([
                        'message' => "Invoice {$inv_no} Rejected: {$invHeader->reason}"
                    ]);
                default:
                    return response()->json([
                        'message' => "Invoice {$inv_no} updated to status: {$invHeader->status}"
                    ]);
            }
        });
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

    public function uploadPaymentDocuments(FinancePaymentDocumentRequest $request)
    {
        $invNos = $request->input('inv_nos', []);
        $updatedBy = Auth::user()->name;
        $actualDate = $request->input('actual_date');

        InvHeader::whereIn('inv_no', $invNos)
            ->where('status', 'Ready To Payment')
            ->update([
                'status'      => 'Paid',
                'updated_by'  => $updatedBy,
                'actual_date' => $actualDate,
            ]);

        return response()->json([
            'success'    => true,
            'message'    => count($invNos) . ' invoices marked as Paid',
            'actual_date'=> $actualDate,
        ]);
    }

    public function revertToReadyToPayment(Request $request)
    {
        $invNos = $request->input('inv_nos', []);

        if (empty($invNos)) {
            return response()->json([
                'success' => false,
                'message' => 'No invoice numbers provided.',
            ], 400);
        }

        // Target invoices that have an actual_date to revert them
        $updatedCount = InvHeader::whereIn('inv_no', $invNos)
            ->where('status', 'Paid') // Added condition for status
            ->whereNotNull('actual_date')
            ->update([
                'status'      => 'Ready To Payment',
                'updated_by'  => Auth::user()->name,
                'actual_date' => null,
            ]);

        if ($updatedCount > 0) {
            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} invoice(s) status reverted to Ready To Payment (actual_date nullified).",
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No invoices found with "Paid" status and an actual payment date to revert, or no updates were necessary for the provided invoice numbers.',
            ], 404);
        }
    }
}
