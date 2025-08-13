<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;

class FixBusinessPartnerSuffix2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'business-partner:fix-suffix2 {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix and verify business partner integration for bp_codes with -2 suffix';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $this->info('=== FIXING BUSINESS PARTNER INTEGRATION FOR SUFFIX -2 ===');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find all partners with -2 suffix
        $partnersWithSuffix2 = Partner::where('bp_code', 'like', '%-2')->get();
        $this->info("Found " . $partnersWithSuffix2->count() . " partners with -2 suffix");

        if ($partnersWithSuffix2->isEmpty()) {
            $this->warn("No partners with -2 suffix found!");
            return 0;
        }

        $fixedCount = 0;
        $errors = [];

        foreach ($partnersWithSuffix2 as $partner) {
            $this->newLine();
            $this->info("Processing: {$partner->bp_code}");
            
            try {
                $baseBpCode = $unifiedService->getBaseBpCode($partner->bp_code);
                $this->line("  Base BP_CODE: {$baseBpCode}");

                // Check if parent exists
                $parent = Partner::where('bp_code', $baseBpCode)->first();
                if (!$parent) {
                    $this->warn("  ⚠ Parent BP_CODE '{$baseBpCode}' not found!");
                    $errors[] = "Parent not found for {$partner->bp_code} (base: {$baseBpCode})";
                    continue;
                }

                $this->line("  ✓ Parent BP_CODE '{$baseBpCode}' found");

                // Check current parent relationship
                $currentParent = $partner->parent_bp_code;
                $this->line("  Current parent_bp_code: " . ($currentParent ?? 'NULL'));

                if ($currentParent !== $baseBpCode) {
                    $this->warn("  ⚠ Parent relationship incorrect!");
                    $this->line("  Expected parent_bp_code: {$baseBpCode}");
                    
                    if (!$this->option('dry-run')) {
                        $partner->parent_bp_code = $baseBpCode;
                        $partner->save();
                        $this->line("  ✓ Fixed parent relationship");
                        $fixedCount++;
                    } else {
                        $this->line("  Would fix parent relationship");
                        $fixedCount++;
                    }
                } else {
                    $this->line("  ✓ Parent relationship already correct");
                }

                // Test unified queries
                $this->line("  Testing unified queries...");
                $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($partner->bp_code);
                $this->line("  Unified BP_CODES: " . $unifiedBpCodes->count());
                foreach ($unifiedBpCodes as $code) {
                    $this->line("    - {$code}");
                }

                // Check data distribution
                $invLines = InvLine::whereIn('bp_id', $unifiedBpCodes)->get();
                $invHeaders = InvHeader::whereIn('bp_code', $unifiedBpCodes)->get();
                
                $this->line("  InvLine records: " . $invLines->count());
                $this->line("  InvHeader records: " . $invHeaders->count());

                // Show data distribution
                $invLineDistribution = $invLines->groupBy('bp_id');
                if ($invLineDistribution->count() > 0) {
                    $this->line("  InvLine distribution:");
                    foreach ($invLineDistribution as $bpId => $lines) {
                        $this->line("    - {$bpId}: {$lines->count()} records");
                    }
                }

                $invHeaderDistribution = $invHeaders->groupBy('bp_code');
                if ($invHeaderDistribution->count() > 0) {
                    $this->line("  InvHeader distribution:");
                    foreach ($invHeaderDistribution as $bpCode => $headers) {
                        $this->line("    - {$bpCode}: {$headers->count()} records");
                    }
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Error processing {$partner->bp_code}: " . $e->getMessage());
                $errors[] = "Error processing {$partner->bp_code}: " . $e->getMessage();
            }
        }

        $this->newLine();
        $this->info("=== SUMMARY ===");
        $this->info("Total partners with -2 suffix: " . $partnersWithSuffix2->count());
        $this->info("Partners fixed: {$fixedCount}");
        $this->info("Errors: " . count($errors));

        if (!empty($errors)) {
            $this->newLine();
            $this->error("=== ERRORS ===");
            foreach ($errors as $error) {
                $this->error($error);
            }
        }

        // Test specific examples
        $this->newLine();
        $this->info("=== TESTING SPECIFIC EXAMPLES ===");
        
        $samplePartner = $partnersWithSuffix2->first();
        if ($samplePartner) {
            $this->info("Testing with sample partner: {$samplePartner->bp_code}");
            
            // Test with -2 suffix
            $unifiedBpCodes2 = $unifiedService->getUnifiedBpCodes($samplePartner->bp_code);
            $this->line("  Searching with -2 suffix: " . $unifiedBpCodes2->count() . " unified bp_codes");
            
            // Test with base bp_code
            $baseBpCode = $unifiedService->getBaseBpCode($samplePartner->bp_code);
            $unifiedBpCodesBase = $unifiedService->getUnifiedBpCodes($baseBpCode);
            $this->line("  Searching with base bp_code: " . $unifiedBpCodesBase->count() . " unified bp_codes");
            
            // Compare results
            if ($unifiedBpCodes2->count() === $unifiedBpCodesBase->count()) {
                $this->line("  ✓ Both searches return same number of bp_codes");
            } else {
                $this->warn("  ⚠ Different results between -2 and base searches!");
            }
            
            // Show what bp_codes are found
            $this->line("  Unified bp_codes found:");
            foreach ($unifiedBpCodes2 as $code) {
                $this->line("    - {$code}");
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('This was a dry run. Run without --dry-run to apply fixes.');
        } else {
            $this->info('Business partner -2 suffix integration fixed successfully!');
        }

        return 0;
    }
}
