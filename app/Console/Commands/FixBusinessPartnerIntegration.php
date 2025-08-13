<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;

class FixBusinessPartnerIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'business-partner:fix-integration {--force : Force fix without confirmation} {--dry-run : Show what would be fixed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comprehensive fix for business partner integration issues, especially for suffix -2';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $this->info('=== COMPREHENSIVE BUSINESS PARTNER INTEGRATION FIX ===');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        if (!$this->option('force') && !$this->option('dry-run')) {
            if (!$this->confirm('This will fix business partner integration issues. Continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('Step 1: Analyzing current state...');
        $this->analyzeCurrentState($unifiedService);

        $this->newLine();
        $this->info('Step 2: Fixing parent-child relationships...');
        $this->fixParentChildRelationships($unifiedService);

        $this->newLine();
        $this->info('Step 3: Creating missing parent records...');
        $this->createMissingParentRecords($unifiedService);

        $this->newLine();
        $this->info('Step 4: Verifying data consistency...');
        $this->verifyDataConsistency($unifiedService);

        $this->newLine();
        $this->info('Step 5: Testing integration...');
        $this->testIntegration($unifiedService);

        $this->newLine();
        $this->info('=== INTEGRATION FIX COMPLETE ===');
        
        if ($this->option('dry-run')) {
            $this->warn('This was a dry run. Run without --dry-run to apply fixes.');
        } else {
            $this->info('Business partner integration has been fixed successfully!');
        }

        return 0;
    }

    private function analyzeCurrentState($unifiedService)
    {
        $partners = Partner::all();
        $this->info("Total business partners: {$partners->count()}");

        $oldSystemPartners = $partners->filter(function($partner) use ($unifiedService) {
            return $unifiedService->isOldSystemBpCode($partner->bp_code);
        });

        $newSystemPartners = $partners->filter(function($partner) use ($unifiedService) {
            return $unifiedService->isNewSystemBpCode($partner->bp_code);
        });

        $this->info("Old system partners (with suffix): " . $oldSystemPartners->count());
        $this->info("New system partners (no suffix): " . $newSystemPartners->count());

        // Analyze suffix distribution
        $suffixDistribution = [];
        foreach ($oldSystemPartners as $partner) {
            $suffix = str_replace($unifiedService->getBaseBpCode($partner->bp_code), '', $partner->bp_code);
            $suffixDistribution[$suffix] = ($suffixDistribution[$suffix] ?? 0) + 1;
        }

        $this->info("Suffix distribution:");
        foreach ($suffixDistribution as $suffix => $count) {
            $this->line("  {$suffix}: {$count} partners");
        }

        // Check for issues
        $issues = [];
        foreach ($oldSystemPartners as $partner) {
            $baseBpCode = $unifiedService->getBaseBpCode($partner->bp_code);
            $parent = Partner::where('bp_code', $baseBpCode)->first();
            
            if (!$parent) {
                $issues[] = "Parent not found for {$partner->bp_code} (base: {$baseBpCode})";
            } elseif ($partner->parent_bp_code !== $baseBpCode) {
                $issues[] = "Incorrect parent relationship for {$partner->bp_code}";
            }
        }

        if (!empty($issues)) {
            $this->warn("Found " . count($issues) . " issues:");
            foreach ($issues as $issue) {
                $this->line("  - {$issue}");
            }
        } else {
            $this->info("✓ No issues found in current state");
        }
    }

    private function fixParentChildRelationships($unifiedService)
    {
        $partners = Partner::where('bp_code', 'like', '%-%')->get();
        $fixedCount = 0;

        foreach ($partners as $partner) {
            $baseBpCode = $unifiedService->getBaseBpCode($partner->bp_code);
            $parent = Partner::where('bp_code', $baseBpCode)->first();
            
            if ($parent && $partner->parent_bp_code !== $baseBpCode) {
                if (!$this->option('dry-run')) {
                    $partner->parent_bp_code = $baseBpCode;
                    $partner->save();
                }
                $fixedCount++;
                $this->line("  Fixed {$partner->bp_code} -> parent: {$baseBpCode}");
            }
        }

        $this->info("Fixed {$fixedCount} parent-child relationships");
    }

    private function createMissingParentRecords($unifiedService)
    {
        $partners = Partner::where('bp_code', 'like', '%-%')->get();
        $createdCount = 0;

        foreach ($partners as $partner) {
            $baseBpCode = $unifiedService->getBaseBpCode($partner->bp_code);
            $parent = Partner::where('bp_code', $baseBpCode)->first();
            
            if (!$parent) {
                if (!$this->option('dry-run')) {
                    Partner::create([
                        'bp_code' => $baseBpCode,
                        'parent_bp_code' => null,
                        'bp_name' => $partner->bp_name . ' (Parent)',
                        'bp_address' => $partner->bp_address,
                        'bp_email' => $partner->bp_email,
                    ]);
                }
                $createdCount++;
                $this->line("  Created parent record: {$baseBpCode}");
            }
        }

        $this->info("Created {$createdCount} missing parent records");
    }

    private function verifyDataConsistency($unifiedService)
    {
        $partners = Partner::where('bp_code', 'like', '%-%')->get();
        $verifiedCount = 0;
        $inconsistentCount = 0;

        foreach ($partners as $partner) {
            $baseBpCode = $unifiedService->getBaseBpCode($partner->bp_code);
            $parent = Partner::where('bp_code', $baseBpCode)->first();
            
            if ($parent && $partner->parent_bp_code === $baseBpCode) {
                $verifiedCount++;
            } else {
                $inconsistentCount++;
                $this->warn("  Inconsistent: {$partner->bp_code}");
            }
        }

        $this->info("Verified: {$verifiedCount} partners");
        if ($inconsistentCount > 0) {
            $this->warn("Inconsistent: {$inconsistentCount} partners");
        } else {
            $this->info("✓ All relationships are consistent");
        }
    }

    private function testIntegration($unifiedService)
    {
        $this->info("Testing unified queries...");

        // Test with different suffixes
        $suffixes = ['-1', '-2', '-3'];
        $testResults = [];

        foreach ($suffixes as $suffix) {
            $partnersWithSuffix = Partner::where('bp_code', 'like', '%' . $suffix)->get();
            
            if ($partnersWithSuffix->count() > 0) {
                $samplePartner = $partnersWithSuffix->first();
                $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($samplePartner->bp_code);
                $baseBpCode = $unifiedService->getBaseBpCode($samplePartner->bp_code);
                $baseUnifiedBpCodes = $unifiedService->getUnifiedBpCodes($baseBpCode);
                
                $testResults[$suffix] = [
                    'sample' => $samplePartner->bp_code,
                    'unified_count' => $unifiedBpCodes->count(),
                    'base_unified_count' => $baseUnifiedBpCodes->count(),
                    'consistent' => $unifiedBpCodes->count() === $baseUnifiedBpCodes->count()
                ];
            }
        }

        foreach ($testResults as $suffix => $result) {
            $status = $result['consistent'] ? '✓' : '✗';
            $this->line("  {$status} Suffix {$suffix}: {$result['sample']} -> {$result['unified_count']} unified bp_codes");
        }

        // Test data distribution
        $this->info("Testing data distribution...");
        
        $samplePartner = Partner::where('bp_code', 'like', '%-2')->first();
        if ($samplePartner) {
            $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($samplePartner->bp_code);
            
            $invLines = InvLine::whereIn('bp_id', $unifiedBpCodes)->get();
            $invHeaders = InvHeader::whereIn('bp_code', $unifiedBpCodes)->get();
            
            $this->line("  Sample partner: {$samplePartner->bp_code}");
            $this->line("  Unified bp_codes: " . $unifiedBpCodes->count());
            $this->line("  InvLine records: " . $invLines->count());
            $this->line("  InvHeader records: " . $invHeaders->count());
            
            // Show distribution
            $invLineDistribution = $invLines->groupBy('bp_id');
            if ($invLineDistribution->count() > 0) {
                $this->line("  InvLine distribution:");
                foreach ($invLineDistribution as $bpId => $lines) {
                    $this->line("    - {$bpId}: {$lines->count()} records");
                }
            }
        }

        $this->info("✓ Integration testing completed");
    }
}
