<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;
use Illuminate\Support\Facades\DB;

class FixInvoicePages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:fix {bp_code : BP code to fix} {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix Invoice Creation and Invoice Report pages integration issues';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $bpCode = $this->argument('bp_code');
        $dryRun = $this->option('dry-run');

        $this->info('=== FIXING INVOICE PAGES INTEGRATION ===');
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

        // Fix Invoice Creation issues
        $this->newLine();
        $this->info("=== FIXING INVOICE CREATION ===");
        $this->fixInvoiceCreation($bpCode, $unifiedService, $dryRun);

        // Fix Invoice Report issues
        $this->newLine();
        $this->info("=== FIXING INVOICE REPORT ===");
        $this->fixInvoiceReport($bpCode, $unifiedService, $dryRun);

        // Verify fixes
        $this->newLine();
        $this->info("=== VERIFYING FIXES ===");
        $this->verifyFixes($bpCode, $unifiedService);

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply fixes.');
        } else {
            $this->info('Invoice pages integration fixes completed!');
        }

        return 0;
    }

    private function fixInvoiceCreation($bpCode, $unifiedService, $dryRun)
    {
        $this->info("Fixing Invoice Creation (Uninvoiced Items)...");

        // Normalize bp_code
        $normalizedBpCode = $unifiedService->normalizeBpCode($bpCode);
        
        // Get unified bp_codes
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($normalizedBpCode);
        $this->info("Unified BP_CODES: " . $unifiedBpCodes->count());

        // Check current uninvoiced items
        $uninvoicedItems = $unifiedService->getUnifiedUninvoicedInvLines($normalizedBpCode);
        $this->info("Current uninvoiced items: " . $uninvoicedItems->count());

        // Check for items that should be uninvoiced but have invoice data
        $itemsWithInvoiceData = InvLine::whereIn('bp_id', $unifiedBpCodes)
            ->where(function($query) {
                $query->whereNotNull('inv_supplier_no')
                      ->orWhereNotNull('inv_due_date');
            })
            ->get();

        $this->info("Items with invoice data: " . $itemsWithInvoiceData->count());

        // Check for orphaned invoice data (items with invoice data but no corresponding header)
        $orphanedItems = collect();
        foreach ($itemsWithInvoiceData as $item) {
            if ($item->inv_supplier_no) {
                $header = InvHeader::where('inv_no', $item->inv_supplier_no)->first();
                if (!$header) {
                    $orphanedItems->push($item);
                }
            }
        }

        $this->info("Orphaned items (with invoice data but no header): " . $orphanedItems->count());

        // Fix orphaned items
        if ($orphanedItems->count() > 0) {
            $this->info("Fixing orphaned items...");
            
            foreach ($orphanedItems as $item) {
                $this->line("  - Fixing item: {$item->po_no} (BP_ID: {$item->bp_id})");
                $this->line("    Current inv_supplier_no: {$item->inv_supplier_no}");
                $this->line("    Current inv_due_date: " . ($item->inv_due_date ?? 'NULL'));
                
                if (!$dryRun) {
                    $item->update([
                        'inv_supplier_no' => null,
                        'inv_due_date' => null,
                    ]);
                    $this->line("    ✓ Fixed - cleared invoice data");
                } else {
                    $this->line("    Would fix - clear invoice data");
                }
            }
        }

        // Check for items that should be uninvoiced but are missing from unified query
        $allItems = InvLine::whereIn('bp_id', $unifiedBpCodes)->get();
        $shouldBeUninvoiced = $allItems->filter(function($item) {
            return is_null($item->inv_supplier_no) && is_null($item->inv_due_date);
        });

        $this->info("Items that should be uninvoiced: " . $shouldBeUninvoiced->count());
        $this->info("Items returned by unified service: " . $uninvoicedItems->count());

        if ($shouldBeUninvoiced->count() !== $uninvoicedItems->count()) {
            $this->warn("⚠ Discrepancy found in uninvoiced items count");
            $this->line("  Expected: " . $shouldBeUninvoiced->count());
            $this->line("  Actual: " . $uninvoicedItems->count());
        }
    }

    private function fixInvoiceReport($bpCode, $unifiedService, $dryRun)
    {
        $this->info("Fixing Invoice Report (Invoice Headers)...");

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
                    $this->line("    ⚠ No related lines found - header might be orphaned");
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

        $this->info("BP_CODE coverage:");
        $this->line("  - GR Tracking: " . $grTrackingBpIds->count() . " bp_ids");
        $this->line("  - Invoice Creation: " . $invoiceCreationBpIds->count() . " bp_ids");
        $this->line("  - Invoice Report: " . $invoiceReportBpCodes->count() . " bp_codes");

        if ($grTrackingBpIds->count() === $invoiceCreationBpIds->count() && 
            $grTrackingBpIds->count() === $invoiceReportBpCodes->count()) {
            $this->info("✓ All pages have consistent bp_code coverage");
        } else {
            $this->warn("⚠ Inconsistent bp_code coverage between pages");
        }
    }
}
