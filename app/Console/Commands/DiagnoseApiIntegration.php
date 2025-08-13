<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DiagnoseApiIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:diagnose-integration {bp_code? : Specific bp_code to test} {--test-api : Test actual API endpoints}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose API integration issues for business partner data, especially suffix -2';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $bpCode = $this->argument('bp_code');
        $testApi = $this->option('test-api');

        $this->info('=== API INTEGRATION DIAGNOSIS ===');
        $this->newLine();

        if ($bpCode) {
            $this->diagnoseSpecificBpCode($bpCode, $unifiedService, $testApi);
        } else {
            $this->diagnoseAllSuffixes($unifiedService, $testApi);
        }

        return 0;
    }

    private function diagnoseSpecificBpCode($bpCode, $unifiedService, $testApi)
    {
        $this->info("=== DIAGNOSING BP_CODE: {$bpCode} ===");
        $this->newLine();

        // Step 1: Check if bp_code exists
        $partner = Partner::where('bp_code', $bpCode)->first();
        if (!$partner) {
            $this->error("❌ BP_CODE '{$bpCode}' not found in database!");
            return;
        }

        $this->info("✓ BP_CODE '{$bpCode}' found in database");
        $this->info("  - Name: {$partner->bp_name}");
        $this->info("  - Parent BP_CODE: " . ($partner->parent_bp_code ?? 'None'));

        // Step 2: Check unified service
        $this->newLine();
        $this->info("=== UNIFIED SERVICE TEST ===");
        
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($bpCode);
        $this->info("Unified BP_CODES: " . $unifiedBpCodes->count());
        foreach ($unifiedBpCodes as $code) {
            $this->line("  - {$code}");
        }

        // Step 3: Check data availability
        $this->newLine();
        $this->info("=== DATA AVAILABILITY CHECK ===");
        
        $invLines = InvLine::whereIn('bp_id', $unifiedBpCodes)->get();
        $invHeaders = InvHeader::whereIn('bp_code', $unifiedBpCodes)->get();
        
        $this->info("InvLine records: " . $invLines->count());
        $this->info("InvHeader records: " . $invHeaders->count());

        // Show data distribution
        $invLineDistribution = $invLines->groupBy('bp_id');
        if ($invLineDistribution->count() > 0) {
            $this->info("InvLine distribution:");
            foreach ($invLineDistribution as $bpId => $lines) {
                $this->line("  - {$bpId}: {$lines->count()} records");
            }
        }

        $invHeaderDistribution = $invHeaders->groupBy('bp_code');
        if ($invHeaderDistribution->count() > 0) {
            $this->info("InvHeader distribution:");
            foreach ($invHeaderDistribution as $bpCode => $headers) {
                $this->line("  - {$bpCode}: {$headers->count()} records");
            }
        }

        // Step 4: Test API endpoints if requested
        if ($testApi) {
            $this->newLine();
            $this->info("=== API ENDPOINT TESTING ===");
            $this->testApiEndpoints($bpCode, $unifiedService);
        }

        // Step 5: Check for potential issues
        $this->newLine();
        $this->info("=== POTENTIAL ISSUES ===");
        $this->checkPotentialIssues($bpCode, $unifiedService);
    }

    private function diagnoseAllSuffixes($unifiedService, $testApi)
    {
        $this->info("=== DIAGNOSING ALL SUFFIXES ===");
        $this->newLine();

        $suffixes = ['-1', '-2', '-3'];
        $results = [];

        foreach ($suffixes as $suffix) {
            $this->info("Testing suffix: {$suffix}");
            
            $partnersWithSuffix = Partner::where('bp_code', 'like', '%' . $suffix)->get();
            $this->line("  Partners with {$suffix}: " . $partnersWithSuffix->count());

            if ($partnersWithSuffix->count() > 0) {
                $samplePartner = $partnersWithSuffix->first();
                $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($samplePartner->bp_code);
                $baseBpCode = $unifiedService->getBaseBpCode($samplePartner->bp_code);
                $baseUnifiedBpCodes = $unifiedService->getUnifiedBpCodes($baseBpCode);

                $results[$suffix] = [
                    'sample' => $samplePartner->bp_code,
                    'unified_count' => $unifiedBpCodes->count(),
                    'base_unified_count' => $baseUnifiedBpCodes->count(),
                    'consistent' => $unifiedBpCodes->count() === $baseUnifiedBpCodes->count(),
                    'has_data' => InvLine::whereIn('bp_id', $unifiedBpCodes)->exists()
                ];

                $this->line("  Sample: {$samplePartner->bp_code}");
                $this->line("  Unified BP_CODES: " . $unifiedBpCodes->count());
                $this->line("  Base BP_CODES: " . $baseUnifiedBpCodes->count());
                $this->line("  Consistent: " . ($results[$suffix]['consistent'] ? '✓' : '✗'));
                $this->line("  Has Data: " . ($results[$suffix]['has_data'] ? '✓' : '✗'));
            }
            $this->newLine();
        }

        // Summary
        $this->info("=== SUMMARY ===");
        foreach ($results as $suffix => $result) {
            $status = $result['consistent'] && $result['has_data'] ? '✓' : '✗';
            $this->line("{$status} Suffix {$suffix}: {$result['sample']} -> {$result['unified_count']} bp_codes");
        }

        // Test specific -2 suffix if available
        if (isset($results['-2'])) {
            $this->newLine();
            $this->info("=== DETAILED -2 SUFFIX ANALYSIS ===");
            $this->diagnoseSpecificBpCode($results['-2']['sample'], $unifiedService, $testApi);
        }
    }

    private function testApiEndpoints($bpCode, $unifiedService)
    {
        $this->info("Testing API endpoints for: {$bpCode}");

        // Test 1: Check if user exists with this bp_code
        $user = User::where('bp_code', $bpCode)->first();
        if ($user) {
            $this->line("✓ User found with bp_code: {$bpCode}");
        } else {
            $this->warn("⚠ No user found with bp_code: {$bpCode}");
        }

        // Test 2: Check unified service methods
        $this->line("Testing unified service methods...");
        
        try {
            $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($bpCode);
            $this->line("  ✓ getUnifiedBpCodes: " . $unifiedBpCodes->count() . " codes");

            $unifiedInvLines = $unifiedService->getUnifiedInvLines($bpCode);
            $this->line("  ✓ getUnifiedInvLines: " . $unifiedInvLines->count() . " records");

            $unifiedInvHeaders = $unifiedService->getUnifiedInvHeaders($bpCode);
            $this->line("  ✓ getUnifiedInvHeaders: " . $unifiedInvHeaders->count() . " records");

            $dashboardData = $unifiedService->getUnifiedDashboardData($bpCode);
            $this->line("  ✓ getUnifiedDashboardData: " . json_encode($dashboardData));

        } catch (\Exception $e) {
            $this->error("  ✗ Error in unified service: " . $e->getMessage());
        }

        // Test 3: Check database queries
        $this->line("Testing database queries...");
        
        try {
            $invLines = InvLine::whereIn('bp_id', $unifiedBpCodes)->get();
            $this->line("  ✓ InvLine query: " . $invLines->count() . " records");

            $invHeaders = InvHeader::whereIn('bp_code', $unifiedBpCodes)->get();
            $this->line("  ✓ InvHeader query: " . $invHeaders->count() . " records");

        } catch (\Exception $e) {
            $this->error("  ✗ Error in database queries: " . $e->getMessage());
        }
    }

    private function checkPotentialIssues($bpCode, $unifiedService)
    {
        $issues = [];

        // Check 1: Parent-child relationship
        $partner = Partner::where('bp_code', $bpCode)->first();
        if ($partner) {
            if ($unifiedService->isOldSystemBpCode($bpCode)) {
                $baseBpCode = $unifiedService->getBaseBpCode($bpCode);
                $parent = Partner::where('bp_code', $baseBpCode)->first();
                
                if (!$parent) {
                    $issues[] = "Parent record not found for {$bpCode} (base: {$baseBpCode})";
                } elseif ($partner->parent_bp_code !== $baseBpCode) {
                    $issues[] = "Incorrect parent relationship for {$bpCode}";
                }
            }
        }

        // Check 2: Data consistency
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($bpCode);
        $baseBpCode = $unifiedService->getBaseBpCode($bpCode);
        $baseUnifiedBpCodes = $unifiedService->getUnifiedBpCodes($baseBpCode);

        if ($unifiedBpCodes->count() !== $baseUnifiedBpCodes->count()) {
            $issues[] = "Inconsistent unified bp_codes count";
        }

        // Check 3: Empty data
        $invLines = InvLine::whereIn('bp_id', $unifiedBpCodes)->get();
        if ($invLines->isEmpty()) {
            $issues[] = "No InvLine data found for unified bp_codes";
        }

        // Check 4: Database connection
        try {
            DB::connection('mysql2')->getPdo();
        } catch (\Exception $e) {
            $issues[] = "Database connection issue: " . $e->getMessage();
        }

        if (empty($issues)) {
            $this->info("✓ No issues found");
        } else {
            $this->warn("Found " . count($issues) . " potential issues:");
            foreach ($issues as $issue) {
                $this->error("  - {$issue}");
            }
        }
    }
}
