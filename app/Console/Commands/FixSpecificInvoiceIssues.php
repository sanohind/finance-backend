<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;
use Illuminate\Support\Facades\DB;

class FixSpecificInvoiceIssues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:fix-specific {bp_code : BP code to fix} {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix specific invoice issues found in diagnose results';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $bpCode = $this->argument('bp_code');
        $dryRun = $this->option('dry-run');

        $this->info('=== FIXING SPECIFIC INVOICE ISSUES ===');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Check if bp_code exists
        $partner = Partner::where('bp_code', $bpCode)->first();
        if (!$partner) {
            $this->error("❌ BP_CODE '{$bpCode}' not found in business_partner table!");
            return 1;
        }

        $this->info("✓ BP_CODE found: {$partner->bp_name}");
        $this->info("  - Parent BP_CODE: " . ($partner->parent_bp_code ?? 'None'));

        // Fix specific issues
        $this->newLine();
        $this->info("=== FIXING BP_CODE COVERAGE ISSUES ===");
        $this->fixBpCodeCoverage($bpCode, $unifiedService, $dryRun);

        $this->newLine();
        $this->info("=== FIXING INVOICE REPORT RELATIONSHIPS ===");
        $this->fixInvoiceReportRelationships($bpCode, $unifiedService, $dryRun);

        $this->newLine();
        $this->info("=== VERIFYING FIXES ===");
        $this->verifyFixes($bpCode, $unifiedService);

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply fixes.');
        } else {
            $this->info('Specific invoice issues fixes completed!');
        }

        return 0;
    }

    private function fixBpCodeCoverage($bpCode, $unifiedService, $dryRun)
    {
        $this->info("Fixing BP_CODE coverage issues...");

        // Normalize bp_code
        $normalizedBpCode = $unifiedService->normalizeBpCode($bpCode);
        
        // Get unified bp_codes
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($normalizedBpCode);
        $this->info("Unified BP_CODES: " . $unifiedBpCodes->count());
        foreach ($unifiedBpCodes as $code) {
            $this->line("  - {$code}");
        }

        // Check current data distribution
        $grTrackingData = $unifiedService->getUnifiedInvLines($normalizedBpCode);
        $invoiceCreationData = $unifiedService->getUnifiedUninvoicedInvLines($normalizedBpCode);
        $invoiceReportData = $unifiedService->getUnifiedInvHeaders($normalizedBpCode);

        $this->info("Current data distribution:");
        $this->line("  - GR Tracking: " . $grTrackingData->count() . " items");
        $this->line("  - Invoice Creation: " . $invoiceCreationData->count() . " items");
        $this->line("  - Invoice Report: " . $invoiceReportData->count() . " headers");

        // Check BP_CODE coverage
        $grTrackingBpIds = $grTrackingData->pluck('bp_id')->unique();
        $invoiceCreationBpIds = $invoiceCreationData->pluck('bp_id')->unique();
        $invoiceReportBpCodes = $invoiceReportData->pluck('bp_code')->unique();

        $this->info("Current BP_CODE coverage:");
        $this->line("  - GR Tracking covers: " . $grTrackingBpIds->count() . " bp_ids");
        $this->line("  - Invoice Creation covers: " . $invoiceCreationBpIds->count() . " bp_ids");
        $this->line("  - Invoice Report covers: " . $invoiceReportBpCodes->count() . " bp_codes");

        // Find missing bp_codes
        $missingInInvoiceCreation = $grTrackingBpIds->diff($invoiceCreationBpIds);
        $missingInInvoiceReport = $grTrackingBpIds->diff($invoiceReportBpCodes);

        if ($missingInInvoiceCreation->count() > 0) {
            $this->warn("Missing bp_codes in Invoice Creation: " . $missingInInvoiceCreation->implode(', '));
        }

        if ($missingInInvoiceReport->count() > 0) {
            $this->warn("Missing bp_codes in Invoice Report: " . $missingInInvoiceReport->implode(', '));
        }

        // Check if there are items that should be in Invoice Creation but aren't
        foreach ($missingInInvoiceCreation as $missingBpId) {
            $this->info("Checking missing bp_id in Invoice Creation: {$missingBpId}");
            
            $itemsForMissingBpId = InvLine::where('bp_id', $missingBpId)
                ->whereNull('inv_supplier_no')
                ->whereNull('inv_due_date')
                ->get();

            $this->line("  Found " . $itemsForMissingBpId->count() . " uninvoiced items for {$missingBpId}");
            
            if ($itemsForMissingBpId->count() > 0) {
                $this->warn("  ⚠ These items should appear in Invoice Creation but don't");
                $this->line("  This might indicate an issue with the unified service query");
            }
        }

        // Check if there are headers that should be in Invoice Report but aren't
        foreach ($missingInInvoiceReport as $missingBpCode) {
            $this->info("Checking missing bp_code in Invoice Report: {$missingBpCode}");
            
            $headersForMissingBpCode = InvHeader::where('bp_code', $missingBpCode)->get();
            $this->line("  Found " . $headersForMissingBpCode->count() . " headers for {$missingBpCode}");
            
            if ($headersForMissingBpCode->count() > 0) {
                $this->warn("  ⚠ These headers should appear in Invoice Report but don't");
                $this->line("  This might indicate an issue with the unified service query");
            }
        }
    }

    private function fixInvoiceReportRelationships($bpCode, $unifiedService, $dryRun)
    {
        $this->info("Fixing Invoice Report relationships...");

        // Normalize bp_code
        $normalizedBpCode = $unifiedService->normalizeBpCode($bpCode);
        
        // Get unified bp_codes
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($normalizedBpCode);

        // Check current invoice headers
        $invHeaders = $unifiedService->getUnifiedInvHeaders($normalizedBpCode);
        $this->info("Current invoice headers: " . $invHeaders->count());

        // Check for headers without inv_lines
        $headersWithoutLines = $invHeaders->filter(function($header) {
            return $header->invLine->isEmpty();
        });

        $this->info("Headers without inv_lines: " . $headersWithoutLines->count());

        // Fix headers without inv_lines
        if ($headersWithoutLines->count() > 0) {
            $this->info("Fixing headers without inv_lines...");
            
            foreach ($headersWithoutLines as $header) {
                $this->line("  - Header: {$header->inv_no} (BP_CODE: {$header->bp_code})");
                
                // Find inv_lines that should be linked to this header
                $relatedLines = InvLine::where('bp_id', $header->bp_code)
                    ->where('inv_supplier_no', $header->inv_no)
                    ->where('inv_due_date', $header->inv_date)
                    ->get();

                $this->line("    Found " . $relatedLines->count() . " related inv_lines");
                
                if ($relatedLines->count() > 0) {
                    if (!$dryRun) {
                        // Link the lines to the header
                        foreach ($relatedLines as $line) {
                            $header->invLine()->attach($line->inv_line_id);
                        }
                        $this->line("    ✓ Fixed - linked " . $relatedLines->count() . " lines");
                    } else {
                        $this->line("    Would fix - link " . $relatedLines->count() . " lines");
                    }
                } else {
                    $this->line("    ⚠ No related lines found - checking for orphaned data");
                    
                    // Check if there are lines with this invoice number but different bp_code
                    $orphanedLines = InvLine::where('inv_supplier_no', $header->inv_no)
                        ->where('bp_id', '!=', $header->bp_code)
                        ->get();
                    
                    if ($orphanedLines->count() > 0) {
                        $this->line("    Found " . $orphanedLines->count() . " lines with same inv_no but different bp_id");
                        foreach ($orphanedLines as $line) {
                            $this->line("      - BP_ID: {$line->bp_id}, PO_NO: {$line->po_no}");
                        }
                    }
                }
            }
        }

        // Check for orphaned inv_lines (lines with invoice data but no header)
        $linesWithInvoiceData = InvLine::whereIn('bp_id', $unifiedBpCodes)
            ->whereNotNull('inv_supplier_no')
            ->get();

        $orphanedLines = collect();
        foreach ($linesWithInvoiceData as $line) {
            $header = InvHeader::where('inv_no', $line->inv_supplier_no)->first();
            if (!$header) {
                $orphanedLines->push($line);
            }
        }

        $this->info("Orphaned inv_lines (with invoice data but no header): " . $orphanedLines->count());

        // Fix orphaned lines
        if ($orphanedLines->count() > 0) {
            $this->info("Fixing orphaned inv_lines...");
            
            foreach ($orphanedLines as $line) {
                $this->line("  - Line: {$line->po_no} (BP_ID: {$line->bp_id})");
                $this->line("    Invoice No: {$line->inv_supplier_no}");
                
                if (!$dryRun) {
                    $line->update([
                        'inv_supplier_no' => null,
                        'inv_due_date' => null,
                    ]);
                    $this->line("    ✓ Fixed - cleared invoice data");
                } else {
                    $this->line("    Would fix - clear invoice data");
                }
            }
        }
    }

    private function verifyFixes($bpCode, $unifiedService)
    {
        $this->info("Verifying fixes...");

        // Normalize bp_code
        $normalizedBpCode = $unifiedService->normalizeBpCode($bpCode);

        // Verify Invoice Creation
        $uninvoicedItems = $unifiedService->getUnifiedUninvoicedInvLines($normalizedBpCode);
        $this->info("Invoice Creation - Uninvoiced items: " . $uninvoicedItems->count());

        // Check for items with invoice data in uninvoiced results
        $itemsWithInvoiceData = $uninvoicedItems->filter(function($item) {
            return !is_null($item->inv_supplier_no) || !is_null($item->inv_due_date);
        });

        if ($itemsWithInvoiceData->count() > 0) {
            $this->warn("⚠ Found " . $itemsWithInvoiceData->count() . " items with invoice data in uninvoiced results");
        } else {
            $this->info("✓ Invoice Creation - All items are properly uninvoiced");
        }

        // Verify Invoice Report
        $invHeaders = $unifiedService->getUnifiedInvHeaders($normalizedBpCode);
        $this->info("Invoice Report - Invoice headers: " . $invHeaders->count());

        // Check for headers without inv_lines
        $headersWithoutLines = $invHeaders->filter(function($header) {
            return $header->invLine->isEmpty();
        });

        if ($headersWithoutLines->count() > 0) {
            $this->warn("⚠ Found " . $headersWithoutLines->count() . " headers without inv_lines");
        } else {
            $this->info("✓ Invoice Report - All headers have inv_lines");
        }

        // Compare with GR Tracking
        $grTrackingData = $unifiedService->getUnifiedInvLines($normalizedBpCode);
        $this->info("GR Tracking - Total items: " . $grTrackingData->count());

        // Check data consistency
        $grTrackingBpIds = $grTrackingData->pluck('bp_id')->unique();
        $invoiceCreationBpIds = $uninvoicedItems->pluck('bp_id')->unique();
        $invoiceReportBpCodes = $invHeaders->pluck('bp_code')->unique();

        $this->info("BP_CODE coverage after fixes:");
        $this->line("  - GR Tracking: " . $grTrackingBpIds->count() . " bp_ids");
        $this->line("  - Invoice Creation: " . $invoiceCreationBpIds->count() . " bp_ids");
        $this->line("  - Invoice Report: " . $invoiceReportBpCodes->count() . " bp_codes");

        if ($grTrackingBpIds->count() === $invoiceCreationBpIds->count() && 
            $grTrackingBpIds->count() === $invoiceReportBpCodes->count()) {
            $this->info("✓ All pages have consistent bp_code coverage");
        } else {
            $this->warn("⚠ Inconsistent bp_code coverage between pages");
            
            // Show specific differences
            $missingInCreation = $grTrackingBpIds->diff($invoiceCreationBpIds);
            $missingInReport = $grTrackingBpIds->diff($invoiceReportBpCodes);
            
            if ($missingInCreation->count() > 0) {
                $this->line("  Missing in Invoice Creation: " . $missingInCreation->implode(', '));
            }
            
            if ($missingInReport->count() > 0) {
                $this->line("  Missing in Invoice Report: " . $missingInReport->implode(', '));
            }
        }
    }
}
