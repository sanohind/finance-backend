<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;

class DiagnoseBusinessPartnerIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'business-partner:diagnose {bp_code? : Specific bp_code to diagnose} {--fix : Automatically fix issues found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose business partner integration issues, especially for bp_codes with -2 suffix';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $bpCode = $this->argument('bp_code');
        $shouldFix = $this->option('fix');

        if ($bpCode) {
            $this->diagnoseSpecificBpCode($bpCode, $unifiedService, $shouldFix);
        } else {
            $this->diagnoseAllBusinessPartners($unifiedService, $shouldFix);
        }

        return 0;
    }

    private function diagnoseSpecificBpCode($bpCode, $unifiedService, $shouldFix)
    {
        $this->info("=== DIAGNOSING SPECIFIC BP_CODE: {$bpCode} ===");
        $this->newLine();

        // Normalize bp_code
        $normalizedBpCode = $unifiedService->normalizeBpCode($bpCode);
        $this->info("Normalized bp_code: {$normalizedBpCode}");

        // Check if bp_code exists
        $partner = Partner::where('bp_code', $normalizedBpCode)->first();
        if (!$partner) {
            $this->error("❌ BP_CODE '{$normalizedBpCode}' not found in database!");
            return;
        }

        $this->info("✓ BP_CODE '{$normalizedBpCode}' found in database");
        $this->info("  - Name: {$partner->bp_name}");
        $this->info("  - Parent BP_CODE: " . ($partner->parent_bp_code ?? 'None'));

        // Get base bp_code
        $baseBpCode = $unifiedService->getBaseBpCode($normalizedBpCode);
        $this->info("  - Base BP_CODE: {$baseBpCode}");

        // Check if it's old or new system
        if ($unifiedService->isOldSystemBpCode($normalizedBpCode)) {
            $this->info("  - System: OLD (has suffix)");
            
            // Check if parent exists
            $parent = Partner::where('bp_code', $baseBpCode)->first();
            if (!$parent) {
                $this->warn("⚠ Parent BP_CODE '{$baseBpCode}' not found!");
                if ($shouldFix) {
                    $this->info("Creating parent record...");
                    // You might want to create the parent record here
                }
            } else {
                $this->info("✓ Parent BP_CODE '{$baseBpCode}' found");
            }

            // Check if parent_bp_code is set correctly
            if ($partner->parent_bp_code !== $baseBpCode) {
                $this->warn("⚠ Parent relationship not set correctly!");
                $this->info("  Current parent_bp_code: " . ($partner->parent_bp_code ?? 'NULL'));
                $this->info("  Expected parent_bp_code: {$baseBpCode}");
                
                if ($shouldFix && $parent) {
                    $partner->parent_bp_code = $baseBpCode;
                    $partner->save();
                    $this->info("✓ Fixed parent relationship");
                }
            } else {
                $this->info("✓ Parent relationship set correctly");
            }
        } else {
            $this->info("  - System: NEW (no suffix)");
        }

        // Get unified data
        $this->newLine();
        $this->info("=== UNIFIED DATA ANALYSIS ===");
        
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($normalizedBpCode);
        $this->info("Unified BP_CODES found: " . $unifiedBpCodes->count());
        foreach ($unifiedBpCodes as $code) {
            $this->line("  - {$code}");
        }

        // Check InvLine data
        $invLines = InvLine::whereIn('bp_id', $unifiedBpCodes)->get();
        $this->info("InvLine records found: " . $invLines->count());
        
        // Group by bp_id to see distribution
        $invLineDistribution = $invLines->groupBy('bp_id');
        foreach ($invLineDistribution as $bpId => $lines) {
            $this->line("  - {$bpId}: {$lines->count()} records");
        }

        // Check InvHeader data
        $invHeaders = InvHeader::whereIn('bp_code', $unifiedBpCodes)->get();
        $this->info("InvHeader records found: " . $invHeaders->count());
        
        // Group by bp_code to see distribution
        $invHeaderDistribution = $invHeaders->groupBy('bp_code');
        foreach ($invHeaderDistribution as $bpCode => $headers) {
            $this->line("  - {$bpCode}: {$headers->count()} records");
        }

        $this->newLine();
        $this->info("=== DIAGNOSIS COMPLETE ===");
    }

    private function diagnoseAllBusinessPartners($unifiedService, $shouldFix)
    {
        $this->info("=== DIAGNOSING ALL BUSINESS PARTNERS ===");
        $this->newLine();

        $partners = Partner::all();
        $this->info("Total business partners: {$partners->count()}");

        $issues = [];
        $oldSystemPartners = [];
        $newSystemPartners = [];

        foreach ($partners as $partner) {
            if ($unifiedService->isOldSystemBpCode($partner->bp_code)) {
                $oldSystemPartners[] = $partner;
                
                $baseBpCode = $unifiedService->getBaseBpCode($partner->bp_code);
                $parent = Partner::where('bp_code', $baseBpCode)->first();
                
                if (!$parent) {
                    $issues[] = "Parent not found for {$partner->bp_code} (base: {$baseBpCode})";
                } elseif ($partner->parent_bp_code !== $baseBpCode) {
                    $issues[] = "Incorrect parent relationship for {$partner->bp_code} (current: " . ($partner->parent_bp_code ?? 'NULL') . ", expected: {$baseBpCode})";
                }
            } else {
                $newSystemPartners[] = $partner;
            }
        }

        $this->info("Old system partners (with suffix): " . count($oldSystemPartners));
        $this->info("New system partners (no suffix): " . count($newSystemPartners));

        if (!empty($issues)) {
            $this->newLine();
            $this->warn("=== ISSUES FOUND ===");
            foreach ($issues as $issue) {
                $this->error($issue);
            }

            if ($shouldFix) {
                $this->newLine();
                $this->info("=== FIXING ISSUES ===");
                $this->call('business-partner:update-relations');
            }
        } else {
            $this->newLine();
            $this->info("✓ No issues found!");
        }

        // Show distribution by suffix
        $this->newLine();
        $this->info("=== SUFFIX DISTRIBUTION ===");
        $suffixDistribution = [];
        foreach ($oldSystemPartners as $partner) {
            $suffix = str_replace($unifiedService->getBaseBpCode($partner->bp_code), '', $partner->bp_code);
            $suffixDistribution[$suffix] = ($suffixDistribution[$suffix] ?? 0) + 1;
        }

        foreach ($suffixDistribution as $suffix => $count) {
            $this->line("  {$suffix}: {$count} partners");
        }

        // Test specific suffixes
        $this->newLine();
        $this->info("=== TESTING SPECIFIC SUFFIXES ===");
        
        foreach (['-1', '-2', '-3'] as $suffix) {
            $partnersWithSuffix = Partner::where('bp_code', 'like', '%' . $suffix)->get();
            $this->info("Partners with suffix {$suffix}: " . $partnersWithSuffix->count());
            
            if ($partnersWithSuffix->count() > 0) {
                $samplePartner = $partnersWithSuffix->first();
                $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($samplePartner->bp_code);
                $this->line("  Sample: {$samplePartner->bp_code} -> " . $unifiedBpCodes->count() . " unified bp_codes");
            }
        }

        $this->newLine();
        $this->info("=== DIAGNOSIS COMPLETE ===");
    }
}
