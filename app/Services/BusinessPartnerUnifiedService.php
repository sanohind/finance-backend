<?php

namespace App\Services;

use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;
use Illuminate\Support\Collection;

class BusinessPartnerUnifiedService
{
    /**
     * Get all related bp_codes (parent & child) for unified search.
     */
    public function getUnifiedBpCodes($bpCode): Collection
    {
        return Partner::getUnifiedBpCodes($bpCode);
    }

    /**
     * Get all related Partner models (parent & child).
     */
    public function getUnifiedPartners($bpCode): Collection
    {
        return Partner::getUnifiedPartnerData($bpCode);
    }

    /**
     * Get unified InvLine data for a business partner
     */
    public function getUnifiedInvLines($bpCode, array $filters = []): Collection
    {
        $bpCodes = $this->getUnifiedBpCodes($bpCode);
        
        if ($bpCodes->isEmpty()) {
            return collect();
        }

        $query = InvLine::with('partner')->whereIn('bp_id', $bpCodes);

        // Apply filters
        if (!empty($filters['packing_slip'])) {
            $query->where('packing_slip', 'like', '%' . $filters['packing_slip'] . '%');
        }

        if (!empty($filters['receipt_no'])) {
            $query->where('receipt_no', 'like', '%' . $filters['receipt_no'] . '%');
        }

        if (!empty($filters['po_no'])) {
            $query->where('po_no', 'like', '%' . $filters['po_no'] . '%');
        }

        if (!empty($filters['gr_date_from'])) {
            $query->whereDate('actual_receipt_date', '>=', $filters['gr_date_from']);
        }

        if (!empty($filters['gr_date_to'])) {
            $query->whereDate('actual_receipt_date', '<=', $filters['gr_date_to']);
        }

        return $query->orderBy('actual_receipt_date', 'desc')->get();
    }

    /**
     * Get unified uninvoiced InvLine data for a business partner
     */
    public function getUnifiedUninvoicedInvLines($bpCode, array $filters = []): Collection
    {
        $bpCodes = $this->getUnifiedBpCodes($bpCode);
        
        if ($bpCodes->isEmpty()) {
            return collect();
        }

        $query = InvLine::with('partner')
            ->whereIn('bp_id', $bpCodes)
            ->whereNull('inv_supplier_no')
            ->whereNull('inv_due_date');

        // Apply filters
        if (!empty($filters['packing_slip'])) {
            $query->where('packing_slip', 'like', '%' . $filters['packing_slip'] . '%');
        }

        if (!empty($filters['receipt_no'])) {
            $query->where('receipt_no', 'like', '%' . $filters['receipt_no'] . '%');
        }

        if (!empty($filters['po_no'])) {
            $query->where('po_no', 'like', '%' . $filters['po_no'] . '%');
        }

        if (!empty($filters['gr_date_from'])) {
            $query->whereDate('actual_receipt_date', '>=', $filters['gr_date_from']);
        }

        if (!empty($filters['gr_date_to'])) {
            $query->whereDate('actual_receipt_date', '<=', $filters['gr_date_to']);
        }

        return $query->orderBy('actual_receipt_date', 'desc')->get();
    }

    /**
     * Get unified InvHeader data for a business partner
     */
    public function getUnifiedInvHeaders($bpCode): Collection
    {
        $bpCodes = $this->getUnifiedBpCodes($bpCode);
        
        if ($bpCodes->isEmpty()) {
            return collect();
        }

        return InvHeader::with('invLine')
            ->whereIn('bp_code', $bpCodes)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get unified outstanding InvLine data for a business partner
     */
    public function getUnifiedOutstandingInvLines($bpCode, $cutoffDays = 10): Collection
    {
        $bpCodes = $this->getUnifiedBpCodes($bpCode);
        
        if ($bpCodes->isEmpty()) {
            return collect();
        }

        $cutoffDate = now()->subDays($cutoffDays)->toDateString();

        $invLines = InvLine::with('partner')
            ->whereDate('actual_receipt_date', '<=', $cutoffDate)
            ->whereIn('bp_id', $bpCodes)
            ->get();

        // Add category property
        $invLines->each(function ($invLine) {
            $invLine->category = "Danger, You Need To Invoicing This Item";
        });

        return $invLines;
    }

    /**
     * Get unified dashboard data for a business partner
     */
    public function getUnifiedDashboardData($bpCode): array
    {
        $bpCodes = $this->getUnifiedBpCodes($bpCode);
        
        if ($bpCodes->isEmpty()) {
            return [
                'new_invoices' => 0,
                'in_process_invoices' => 0,
                'rejected_invoices' => 0,
                'ready_to_payment_invoices' => 0,
                'paid_invoices' => 0,
            ];
        }

        return [
            'new_invoices' => InvHeader::whereIn('bp_code', $bpCodes)->where('status', 'New')->count(),
            'in_process_invoices' => InvHeader::whereIn('bp_code', $bpCodes)->where('status', 'In Process')->count(),
            'rejected_invoices' => InvHeader::whereIn('bp_code', $bpCodes)->where('status', 'Rejected')->count(),
            'ready_to_payment_invoices' => InvHeader::whereIn('bp_code', $bpCodes)->where('status', 'Ready To Payment')->count(),
            'paid_invoices' => InvHeader::whereIn('bp_code', $bpCodes)->where('status', 'Paid')->count(),
        ];
    }

    /**
     * Update parent-child relationship for existing data (migration helper).
     */
    public function updateParentChildRelation(): int
    {
        $updatedCount = 0;
        $partners = Partner::all();
        
        foreach ($partners as $partner) {
            if (preg_match('/-\d+$/', $partner->bp_code)) {
                $base = preg_replace('/-\d+$/', '', $partner->bp_code);
                
                // Check if parent exists
                $parent = Partner::where('bp_code', $base)->first();
                if ($parent && $partner->parent_bp_code !== $base) {
                    $partner->parent_bp_code = $base;
                    $partner->save();
                    $updatedCount++;
                }
            }
        }
        
        return $updatedCount;
    }

    /**
     * Validate and normalize bp_code input
     */
    public function normalizeBpCode($bpCode): string
    {
        return trim(strtoupper($bpCode));
    }

    /**
     * Check if bp_code is from old system (has suffix)
     */
    public function isOldSystemBpCode($bpCode): bool
    {
        return preg_match('/-\d+$/', $bpCode);
    }

    /**
     * Check if bp_code is from new system (no suffix)
     */
    public function isNewSystemBpCode($bpCode): bool
    {
        return !preg_match('/-\d+$/', $bpCode);
    }

    /**
     * Get base bp_code (remove suffix if exists)
     */
    public function getBaseBpCode($bpCode): string
    {
        return preg_replace('/-\d+$/', '', $bpCode);
    }
}