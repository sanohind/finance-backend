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
use App\Http\Requests\FinanceRevertRequest;
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
        // Load invoice headers from transaction table with PPh and PPN relationships
        $invHeaders = InvHeader::with('invLine')
                            ->orderBy('created_at', 'desc')
                            ->get();

        return InvHeaderResource::collection($invHeaders);
    }

    public function getInvHeaderByBpCode($bp_code)
    {
    $invHeaders = InvHeader::with('invLine')->where('bp_code', $bp_code)->get();
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

    public function getInvHeaderDetail($inv_id)
    {
    // Fetch InvHeader with invPpn, invPph, and invLine relationships
    $invHeader = InvHeader::with(['invPpn', 'invPph', 'invLine'])->findOrFail($inv_id);
    // Return the InvHeader data including related invLine, ppn, and pph
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
            $tax_amount      = $tax_base_amount * $ppnRate;
            $total_amount    = $tax_base_amount + $tax_amount;

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
                        ->storeAs('invoices', 'INVOICE_'.$invHeader->inv_id.'.pdf', 'public')
                ];
            }
            if ($request->hasFile('fakturpajak_file')) {
                $files[] = [
                    'type' => 'fakturpajak',
                    'path' => $request->file('fakturpajak_file')
                        ->storeAs('faktur', 'FAKTURPAJAK_'.$invHeader->inv_id.'.pdf', 'public')
                ];
            }
            if ($request->hasFile('suratjalan_file')) {
                $files[] = [
                    'type' => 'suratjalan',
                    'path' => $request->file('suratjalan_file')
                        ->storeAs('suratjalan', 'SURATJALAN_'.$invHeader->inv_id.'.pdf', 'public')
                ];
            }
            if ($request->hasFile('po_file')) {
                $files[] = [
                    'type' => 'po',
                    'path' => $request->file('po_file')
                        ->storeAs('po', 'PO_'.$invHeader->inv_id.'.pdf', 'public')
                ];
            }

            // Save file references with type
            foreach ($files as $file) {
                InvDocument::create([
                    'inv_id' => $invHeader->inv_id,
                    'type' => $file['type'],
                    'file' => $file['path']
                ]);
            }

            // Update inv_line references
            foreach ($request->inv_line_detail as $line) {
                $invHeader->invLine()->attach($line);
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
                ]));
            }

            return $invHeader;
        });

        // Return the newly created InvHeader outside the transaction
        return new InvHeaderResource($invHeader);
    }

    public function update(FinanceInvHeaderUpdateRequest $request, $inv_id)
    {
        // Check if status is Rejected but no reason provided (can stay outside the transaction)
        if ($request->status === 'Rejected' && empty($request->reason)) {
            return response()->json([
                'message' => 'Reason is required when rejecting an invoice'
            ], 422);
        }

        // Wrap the entire process in a single database transaction
        return DB::transaction(function () use ($request, $inv_id) {
            $request->validated();

            // Eager load invPpn and invPph relationships - use findOrFail() since inv_id is primary key
            $invHeader = InvHeader::with(['invLine', 'invPpn', 'invPph'])->findOrFail($inv_id);

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

                // Remove inv_supplier_no and inv_due_date from all related inv_lines
                // Use the relationship to get the line IDs, then update via direct query
                $lineIds = $invHeader->invLine->pluck('inv_line_id');
                InvLine::whereIn('inv_line_id', $lineIds)->update([
                    'inv_supplier_no' => null,
                    'inv_due_date'    => null,
                ]);
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
                        $finalPphAmountCalculated = $finalPphBaseAmount * $pphRate;
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
                        // Update InvLine record
                        InvLine::where('inv_line_id', $lineId)->update([
                            'inv_supplier_no' => null,
                            'inv_due_date' => null,
                        ]);
                        
                        // Detach from pivot table
                        $invHeader->invLine()->detach($lineId);
                    }
                }

                // 5) PPN Amount (tax_amount from InvHeader is PPN-inclusive based on store logic)
                $ppnInclusiveAmount = $invHeader->tax_amount;
                $ppnAmount = $invHeader->tax_base_amount;

                // 6) total_amount = "ppn_amount minus pph_amount_effect"
                $totalAmount = $ppnAmount + $ppnInclusiveAmount - ($finalPphAmountCalculated ?? 0);

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
                        $poNumbers = InvLine::where('inv_supplier_no', $invHeader->inv_no)
                            ->pluck('po_no')
                            ->unique()
                            ->implode(', ');

                        // Use PPN rate from database instead of hardcoded value
                        $ppnRate = $invHeader->invPpn ? $invHeader->invPpn->ppn_rate : 0.11;
                        $taxAmountForPdf = $invHeader->total_dpp * $ppnRate;

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

                        $filepath = storage_path("app/public/receipts/RECEIPT_{$invHeader->inv_id}.pdf");
                        if (!file_exists(dirname($filepath))) {
                            mkdir(dirname($filepath), 0777, true);
                        }
                        $pdf->save($filepath);

                        $supplierUser = User::where('bp_code', $invHeader->bp_code)->first();
                        if ($supplierUser && $supplierUser->email) {
                            Mail::to($supplierUser->email)->send(new InvoiceReadyMail([
                                'partner_address' => $partner->adr_line_1 ?? '',
                                'bp_code'         => $invHeader->bp_code,
                                'inv_id'          => $invHeader->inv_id,
                                'inv_no'          => $invHeader->inv_no,
                                'status'          => $invHeader->status,
                                'total_amount'    => $invHeader->total_amount,
                                'plan_date'       => $invHeader->plan_date,
                                'filepath'        => $filepath
                            ]));
                        }

                        $invHeader->update([
                            'receipt_path'   => "receipts/RECEIPT_{$invHeader->inv_id}.pdf",
                            'receipt_number' => $receiptNumber
                        ]);

                        return response()->json([
                            'message'        => "Invoice {$invHeader->inv_no} Is Ready To Payment",
                            'receipt_path'   => "receipts/RECEIPT_{$invHeader->inv_id}.pdf",
                            'receipt_number' => $receiptNumber
                        ]);

                    } catch (\Exception $e) {
                        throw new \RuntimeException('Error generating receipt: ' . $e->getMessage(), 0, $e);
                    }
                case 'Rejected':
                    return response()->json([
                        'message' => "Invoice {$invHeader->inv_no} Rejected: {$invHeader->reason}"
                    ]);
                default:
                    return response()->json([
                        'message' => "Invoice {$invHeader->inv_no} updated to status: {$invHeader->status}"
                    ]);
            }
        });
    }

    public function updateStatusToInProcess($inv_id)
    {

    $invHeader = InvHeader::with('invLine')->where('inv_id', $inv_id)->where('status', 'New')->firstOrFail();

        $invHeader->update([
            'status' => 'In Process',
            'updated_by' => Auth::user()->name,
        ]);

        return response()->json([
            'message' => "Invoice {$invHeader->inv_no} status updated to In Process"
        ]);
    }

    public function uploadPaymentDocuments(FinancePaymentDocumentRequest $request)
    {
        $invIds = $request->input('inv_ids', []);
        $updatedBy = Auth::user()->name;
        $actualDate = $request->input('actual_date');

        // Update invoices directly using inv_ids
        InvHeader::whereIn('inv_id', $invIds)
            ->where('status', 'Ready To Payment')
            ->update([
                'status'      => 'Paid',
                'updated_by'  => $updatedBy,
                'actual_date' => $actualDate,
            ]);

        return response()->json([
            'success'    => true,
            'message'    => count($invIds) . ' invoices marked as Paid',
            'actual_date'=> $actualDate,
        ]);
    }

    public function revertToReadyToPayment(Request $request)
    {
        $updatedBy = Auth::user()->name;

        // Bulk operation (array of invoice IDs in request body)

        if ($request->has('invoice_numbers') && is_array($request->input('invoice_numbers'))) {
            $invoiceIds = $request->input('invoice_numbers'); // Now expecting inv_id values

            // Validate that all invoices exist and have 'Paid' status using inv_id directly
            $invHeaders = InvHeader::with('invLine')->whereIn('inv_id', $invoiceIds)
                ->where('status', 'Paid')
                ->get();

            if ($invHeaders->count() !== count($invoiceIds)) {
                $foundInvoiceIds = $invHeaders->pluck('inv_id')->toArray();
                $notFoundInvoiceIds = array_diff($invoiceIds, $foundInvoiceIds);

                return response()->json([
                    'success' => false,
                    'message' => 'Some invoices not found or not in Paid status',
                    'not_found' => $notFoundInvoiceIds,
                ], 404);
            }

            // Update all invoices in bulk using inv_ids directly
            InvHeader::whereIn('inv_id', $invoiceIds)
                ->where('status', 'Paid')
                ->update([
                    'status'      => 'Ready To Payment',
                    'updated_by'  => $updatedBy,
                    'actual_date' => null,
                ]);

            return response()->json([
                'success' => true,
                'message' => count($invoiceIds) . ' invoices reverted to Ready To Payment status',
                'updated_invoices' => $invoiceIds,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invoice IDs array is required',
        ], 400);
    }

    public function revertToInProcess($inv_id)
    {
        $updatedBy = Auth::user()->name;

        // Find the invoice and validate it exists and has 'Ready To Payment' status

        $invHeader = InvHeader::with('invLine')->where('inv_id', $inv_id)
            ->where('status', 'Ready To Payment')
            ->first();

        if (!$invHeader) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found or not in Ready To Payment status',
            ], 404);
        }

        // Calculate original total_amount (before PPH was applied)
        $originalTotalAmount = $invHeader->tax_base_amount + $invHeader->tax_amount;

        // Update the invoice back to In Process status and clear PPH data
        $invHeader->update([
            'status'         => 'In Process',
            'updated_by'     => $updatedBy,
            'plan_date'      => null,
            'receipt_path'   => null,
            'receipt_number' => null,
            // Clear PPH data to force re-entry
            'pph_id'         => null,
            'pph_base_amount'=> null,
            'pph_amount'     => null,
            'total_amount'   => $originalTotalAmount, // Reset to PPN-only amount
        ]);

        return response()->json([
            'success' => true,
            'message' => "Invoice {$invHeader->inv_no} reverted to In Process status. PPH data cleared for re-entry.",
            'updated_invoice' => $inv_id,
        ]);
    }
}