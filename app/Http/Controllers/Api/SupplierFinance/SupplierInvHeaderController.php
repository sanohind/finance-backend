<?php

namespace App\Http\Controllers\Api\SupplierFinance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\SupplierInvHeaderStoreRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\InvHeaderResource;
use App\Http\Requests\SupplierInvHeaderRejectedRequest;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Local\Partner;
use App\Models\InvHeader;
use App\Models\InvDocument;
use App\Models\InvLine;
use App\Models\InvPpn;
use App\Models\InvPph;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceCreateMail;
use Illuminate\Support\Facades\Storage;
use App\Services\BusinessPartnerUnifiedService;

class SupplierInvHeaderController extends Controller
{
    protected $unifiedService;

    public function __construct(BusinessPartnerUnifiedService $unifiedService)
    {
        $this->unifiedService = $unifiedService;
    }

    public function getInvHeader(Request $request)
    {
        $sp_code = Auth::user()->bp_code;
        $sp_code = $this->unifiedService->normalizeBpCode($sp_code);
        
        // Get unified bp_codes matching the service logic
        $bpCodes = $this->unifiedService->getUnifiedBpCodes($sp_code);
        
        // Start query without invLine relationship for better performance
        // Invoice lines will be loaded separately when viewing details
        $query = InvHeader::query()
            ->whereIn('bp_code', $bpCodes);

        // Prepare filter metadata
        $filterUsed = [];
        $dateFrom = null;
        $dateTo = null;

        // Apply filters
        if ($request->filled('bp_code')) {
            // Use unified bp_codes to support both old (SLSDELA-1) and new (SLSDELA) formats
            $unifiedBpCodes = Partner::getUnifiedBpCodes(trim(strtoupper($request->bp_code)));
            if ($unifiedBpCodes->isNotEmpty()) {
                $query->whereIn('bp_code', $unifiedBpCodes);
            } else {
                $query->where('bp_code', $request->bp_code);
            }
            $filterUsed['bp_code'] = $request->bp_code;
        }

        if ($request->filled('inv_no')) {
            $query->where('inv_no', 'like', '%' . $request->inv_no . '%');
            $filterUsed['inv_no'] = $request->inv_no;
        }

        // Check if any filter is provided by the user
        $hasAnyFilter = $request->filled('bp_code')
            || $request->filled('inv_no')
            || $request->filled('invoice_date_from')
            || $request->filled('invoice_date_to')
            || $request->filled('status')
            || $request->filled('plan_date');

        // Apply date range filter:
        // - If user provides invoice_date_from / invoice_date_to → use those
        // - If user provides NO filter at all → default to last 30 days
        // - If user provides other filters (status, plan_date, etc.) but no date → no date restriction
        if ($request->filled('invoice_date_from') || $request->filled('invoice_date_to')) {
            if ($request->filled('invoice_date_from')) {
                $dateFrom = $request->invoice_date_from;
                $query->whereDate('inv_date', '>=', $request->invoice_date_from);
            }
            if ($request->filled('invoice_date_to')) {
                $dateTo = $request->invoice_date_to;
                $query->whereDate('inv_date', '<=', $request->invoice_date_to);
            }
            $filterUsed['date_filter'] = 'custom';
        } elseif (!$hasAnyFilter) {
            // No filters at all → default to last 30 days
            $dateFrom = now()->subDays(30)->format('Y-m-d');
            $dateTo = now()->format('Y-m-d');
            $query->whereDate('inv_date', '>=', $dateFrom);
            $filterUsed['date_filter'] = 'default_30_days';
        } else {
            // Other filters exist but no date filter → no date restriction
            $filterUsed['date_filter'] = 'none';
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
            $filterUsed['status'] = $request->status;
        }

        if ($request->filled('plan_date')) {
            $query->whereDate('plan_date', $request->plan_date);
            $filterUsed['plan_date'] = $request->plan_date;
        }

        // Apply sorting
        $query->orderBy('inv_date', 'desc');

        // Get all results without pagination
        $invHeaders = $query->get();
        
        return response()->json([
            'data' => InvHeaderResource::collection($invHeaders),
            'filter_info' => [
                'filters_applied' => $filterUsed,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
                'total_records' => $invHeaders->count(),
            ]
        ]);
    }

    public function rejectInvoice(SupplierInvHeaderRejectedRequest $request, $inv_id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $sp_code = Auth::user()->bp_code;
        $sp_code = $this->unifiedService->normalizeBpCode($sp_code);
        
        $bpCodes = $this->unifiedService->getUnifiedBpCodes($sp_code);

        $invHeader = InvHeader::with('invLine')
            ->where('inv_id', $inv_id)
            ->whereIn('bp_code', $bpCodes)
            ->where('status', 'New')
            ->first();

        if (!$invHeader) {
            return response()->json([
                'message' => 'Invoice not found or cannot be rejected (already processed by admin)'
            ], 404);
        }

        DB::transaction(function () use ($invHeader, $request) {
            // Remove inv_supplier_no and inv_due_date from all related inv_lines
            // Use the relationship to get the line IDs, then update via direct query
            $lineIds = $invHeader->invLine->pluck('inv_line_id');
            InvLine::whereIn('inv_line_id', $lineIds)->update([
                'inv_supplier_no' => null,
                'inv_due_date'    => null,
            ]);

            // Detach all lines from pivot table before deleting header
            $invHeader->invLine()->detach();

            // Delete the invoice header record completely
            $invHeader->delete();
        });

        return response()->json([
            'message' => "Invoice {$invHeader->inv_no} has been rejected and deleted. Invoice lines are now available for invoicing again."
        ]);
    }

    public function getPpn()
    {
        $ppn = InvPpn::select('ppn_id', 'ppn_description')->get();
        return response()->json($ppn);
    }

    public function getPph()
    {
        $pph = InvPph::select('pph_id', 'pph_description')->get();
        return response()->json($pph);
    }

    public function getInvHeaderDetail($inv_id)
    {
        $sp_code = Auth::user()->bp_code;
        $sp_code = $this->unifiedService->normalizeBpCode($sp_code);
        
        $bpCodes = $this->unifiedService->getUnifiedBpCodes($sp_code);
        
        // Fetch InvHeader with invPpn, invPph, and invLine relationships
        // Only allow access to invoices belonging to this supplier's bp_codes
        $invHeader = InvHeader::with(['invPpn', 'invPph', 'invLine'])
            ->whereIn('bp_code', $bpCodes)
            ->findOrFail($inv_id);
            
        // Return the InvHeader data including related invLine, ppn, and pph
        return new InvHeaderResource($invHeader);
    }


    public function store(SupplierInvHeaderStoreRequest $request)
    {
        $invHeader = DB::transaction(function () use ($request) {
            $request->validated();
            $sp_code = Auth::user()->bp_code;
            $sp_code = $this->unifiedService->normalizeBpCode($sp_code);

            $total_dpp = 0;

            // Gather total DPP from selected inv lines
            foreach ($request->inv_line_detail as $line) {
                $invLine = InvLine::find($line);
                $total_dpp += $invLine->approve_qty * $invLine->receipt_unit_price;
            }

            // Fetch the chosen PPN record
            $ppn = InvPpn::find($request->ppn_id);
            $ppnRate = $ppn ? $ppn->ppn_rate : null;
            if ($ppnRate === null) {
                return response()->json([
                    'message' => 'PPN Rate not found',
                ], 404);
            }

            $tax_base_amount = $total_dpp;
            $tax_amount      = $tax_base_amount * $ppnRate;
            $total_amount    = $tax_base_amount + $tax_amount;

            // Create the InvHeader record (note the inclusion of pph_id similar to SuperAdmin)
            $invHeader = InvHeader::create([
                'inv_no'          => $request->inv_no,
                'bp_code'         => $sp_code,
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

            // Handle file uploads if needed
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

    public function stream($type, $filename)
    {
        $allowedTypes = ['invoices', 'faktur', 'suratjalan', 'po', 'receipts'];
        if (!in_array($type, $allowedTypes)) {
            abort(404);
        }
        $path = $type . '/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }
        return response()->file(storage_path('app/public/' . $path));
    }

}