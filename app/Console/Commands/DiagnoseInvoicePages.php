<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DiagnoseInvoicePages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:diagnose {bp_code : BP code to test} {--user-id= : Specific user ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose Invoice Creation and Invoice Report pages integration issues';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $bpCode = $this->argument('bp_code');
        $userId = $this->option('user-id');

        $this->info('=== INVOICE PAGES DIAGNOSIS ===');
        $this->newLine();

        if ($userId) {
            $this->diagnoseWithUser($userId, $unifiedService);
        } else {
            $this->diagnoseWithBpCode($bpCode, $unifiedService);
        }

        return 0;
    }

    private function diagnoseWithUser($userId, $unifiedService)
    {
        $this->info("=== DIAGNOSING WITH USER ID: {$userId} ===");
        $this->newLine();

        $user = User::find($userId);
        if (!$user) {
            $this->error("❌ User with ID {$userId} not found!");
            return;
        }

        $this->info("✓ User found: {$user->name}");
        $this->info("  - Email: {$user->email}");
        $this->info("  - Role: {$user->role}");
        $this->info("  - BP_CODE: {$user->bp_code}");
        $this->info("  - Status: " . ($user->status ? 'Active' : 'Inactive'));

        if ($user->bp_code) {
            $this->newLine();
            $this->diagnoseInvoicePages($user->bp_code, $unifiedService, $user);
        } else {
            $this->error("❌ User does not have a bp_code!");
        }
    }

    private function diagnoseWithBpCode($bpCode, $unifiedService)
    {
        $this->info("=== DIAGNOSING WITH BP_CODE: {$bpCode} ===");
        $this->newLine();

        // Check if bp_code exists
        $partner = Partner::where('bp_code', $bpCode)->first();
        if (!$partner) {
            $this->error("❌ BP_CODE '{$bpCode}' not found in business_partner table!");
            return;
        }

        $this->info("✓ BP_CODE found: {$partner->bp_name}");
        $this->info("  - Parent BP_CODE: " . ($partner->parent_bp_code ?? 'None'));

        // Check if any user has this bp_code
        $users = User::where('bp_code', $bpCode)->get();
        if ($users->isEmpty()) {
            $this->warn("⚠ No users found with bp_code: {$bpCode}");
            $this->diagnoseInvoicePages($bpCode, $unifiedService, null);
        } else {
            $this->info("✓ Found " . $users->count() . " user(s) with this bp_code");
            $user = $users->first();
            $this->diagnoseInvoicePages($bpCode, $unifiedService, $user);
        }
    }

    private function diagnoseInvoicePages($bpCode, $unifiedService, $user = null)
    {
        $this->newLine();
        $this->info("=== INVOICE CREATION PAGE DIAGNOSIS ===");
        $this->diagnoseInvoiceCreation($bpCode, $unifiedService);

        $this->newLine();
        $this->info("=== INVOICE REPORT PAGE DIAGNOSIS ===");
        $this->diagnoseInvoiceReport($bpCode, $unifiedService);

        $this->newLine();
        $this->info("=== COMPARISON WITH GR TRACKING ===");
        $this->compareWithGrTracking($bpCode, $unifiedService);
    }

    private function diagnoseInvoiceCreation($bpCode, $unifiedService)
    {
        $this->info("Testing Invoice Creation (Uninvoiced Items)...");

        // Normalize bp_code
        $normalizedBpCode = $unifiedService->normalizeBpCode($bpCode);
        $this->info("Normalized BP_CODE: {$normalizedBpCode}");

        // Get unified bp_codes
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($normalizedBpCode);
        $this->info("Unified BP_CODES: " . $unifiedBpCodes->count());
        foreach ($unifiedBpCodes as $code) {
            $this->line("  - {$code}");
        }

        // Test uninvoiced items
        $uninvoicedItems = $unifiedService->getUnifiedUninvoicedInvLines($normalizedBpCode);
        $this->info("Uninvoiced Items: " . $uninvoicedItems->count());

        if ($uninvoicedItems->count() > 0) {
            $this->info("Sample uninvoiced items:");
            $sampleItem = $uninvoicedItems->first();
            $this->line("  - BP_ID: {$sampleItem->bp_id}");
            $this->line("  - PO_NO: {$sampleItem->po_no}");
            $this->line("  - Receipt No: {$sampleItem->receipt_no}");
            $this->line("  - Receipt Date: {$sampleItem->actual_receipt_date}");
            $this->line("  - Inv Supplier No: " . ($sampleItem->inv_supplier_no ?? 'NULL'));
            $this->line("  - Inv Due Date: " . ($sampleItem->inv_due_date ?? 'NULL'));
        }

        // Check data distribution
        $distribution = $uninvoicedItems->groupBy('bp_id');
        if ($distribution->count() > 0) {
            $this->info("Data distribution by bp_id:");
            foreach ($distribution as $bpId => $items) {
                $this->line("  - {$bpId}: {$items->count()} items");
            }
        }

        // Check for potential issues
        $issues = [];
        
        if ($uninvoicedItems->isEmpty()) {
            $issues[] = "No uninvoiced items found";
        }

        // Check if items have inv_supplier_no or inv_due_date (should be null for uninvoiced)
        $itemsWithInvoice = $uninvoicedItems->filter(function($item) {
            return !is_null($item->inv_supplier_no) || !is_null($item->inv_due_date);
        });
        
        if ($itemsWithInvoice->count() > 0) {
            $issues[] = "Found " . $itemsWithInvoice->count() . " items with invoice data (should be uninvoiced)";
        }

        if (empty($issues)) {
            $this->info("✓ Invoice Creation page should work correctly");
        } else {
            $this->warn("Found " . count($issues) . " potential issues:");
            foreach ($issues as $issue) {
                $this->error("  - {$issue}");
            }
        }
    }

    private function diagnoseInvoiceReport($bpCode, $unifiedService)
    {
        $this->info("Testing Invoice Report (Invoice Headers)...");

        // Normalize bp_code
        $normalizedBpCode = $unifiedService->normalizeBpCode($bpCode);
        
        // Get unified bp_codes
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($normalizedBpCode);

        // Test invoice headers
        $invHeaders = $unifiedService->getUnifiedInvHeaders($normalizedBpCode);
        $this->info("Invoice Headers: " . $invHeaders->count());

        if ($invHeaders->count() > 0) {
            $this->info("Sample invoice headers:");
            $sampleHeader = $invHeaders->first();
            $this->line("  - BP_CODE: {$sampleHeader->bp_code}");
            $this->line("  - INV_NO: {$sampleHeader->inv_no}");
            $this->line("  - Status: {$sampleHeader->status}");
            $this->line("  - Total Amount: {$sampleHeader->total_amount}");
            $this->line("  - Created At: {$sampleHeader->created_at}");
        }

        // Check data distribution
        $distribution = $invHeaders->groupBy('bp_code');
        if ($distribution->count() > 0) {
            $this->info("Data distribution by bp_code:");
            foreach ($distribution as $bpCode => $headers) {
                $this->line("  - {$bpCode}: {$headers->count()} headers");
            }
        }

        // Check status distribution
        $statusDistribution = $invHeaders->groupBy('status');
        if ($statusDistribution->count() > 0) {
            $this->info("Status distribution:");
            foreach ($statusDistribution as $status => $headers) {
                $this->line("  - {$status}: {$headers->count()} headers");
            }
        }

        // Check for potential issues
        $issues = [];
        
        if ($invHeaders->isEmpty()) {
            $issues[] = "No invoice headers found";
        }

        // Check if headers have related inv_lines
        $headersWithoutLines = $invHeaders->filter(function($header) {
            return $header->invLine->isEmpty();
        });
        
        if ($headersWithoutLines->count() > 0) {
            $issues[] = "Found " . $headersWithoutLines->count() . " headers without inv_lines";
        }

        if (empty($issues)) {
            $this->info("✓ Invoice Report page should work correctly");
        } else {
            $this->warn("Found " . count($issues) . " potential issues:");
            foreach ($issues as $issue) {
                $this->error("  - {$issue}");
            }
        }
    }

    private function compareWithGrTracking($bpCode, $unifiedService)
    {
        $this->info("Comparing with GR Tracking...");

        // Normalize bp_code
        $normalizedBpCode = $unifiedService->normalizeBpCode($bpCode);

        // Get data for all three pages
        $grTrackingData = $unifiedService->getUnifiedInvLines($normalizedBpCode);
        $invoiceCreationData = $unifiedService->getUnifiedUninvoicedInvLines($normalizedBpCode);
        $invoiceReportData = $unifiedService->getUnifiedInvHeaders($normalizedBpCode);

        $this->info("Data comparison:");
        $this->line("  - GR Tracking: " . $grTrackingData->count() . " items");
        $this->line("  - Invoice Creation: " . $invoiceCreationData->count() . " items");
        $this->line("  - Invoice Report: " . $invoiceReportData->count() . " headers");

        // Check if GR Tracking has data but others don't
        if ($grTrackingData->count() > 0) {
            if ($invoiceCreationData->count() === 0) {
                $this->warn("⚠ GR Tracking has data but Invoice Creation is empty");
                $this->line("  This might indicate that all items are already invoiced");
            }
            
            if ($invoiceReportData->count() === 0) {
                $this->warn("⚠ GR Tracking has data but Invoice Report is empty");
                $this->line("  This might indicate that no invoices have been created yet");
            }
        }

        // Check data consistency
        $grTrackingBpIds = $grTrackingData->pluck('bp_id')->unique();
        $invoiceCreationBpIds = $invoiceCreationData->pluck('bp_id')->unique();
        $invoiceReportBpCodes = $invoiceReportData->pluck('bp_code')->unique();

        $this->info("BP_CODE coverage:");
        $this->line("  - GR Tracking covers: " . $grTrackingBpIds->count() . " bp_ids");
        $this->line("  - Invoice Creation covers: " . $invoiceCreationBpIds->count() . " bp_ids");
        $this->line("  - Invoice Report covers: " . $invoiceReportBpCodes->count() . " bp_codes");

        // Check if all pages cover the same bp_codes
        $allBpCodes = $grTrackingBpIds->merge($invoiceCreationBpIds)->merge($invoiceReportBpCodes)->unique();
        $this->line("  - Total unique bp_codes: " . $allBpCodes->count());

        if ($grTrackingBpIds->count() === $invoiceCreationBpIds->count() && 
            $grTrackingBpIds->count() === $invoiceReportBpCodes->count()) {
            $this->info("✓ All pages have consistent bp_code coverage");
        } else {
            $this->warn("⚠ Inconsistent bp_code coverage between pages");
        }
    }
}
