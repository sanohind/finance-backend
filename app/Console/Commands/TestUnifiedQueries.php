<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;

class TestUnifiedQueries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:unified-queries {bp_code : Business partner code to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test unified business partner queries';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $bpCode = $this->argument('bp_code');
        $this->info("Testing unified queries for bp_code: {$bpCode}");
        $this->newLine();

        // Test 1: Get unified bp_codes
        $this->info("=== TEST 1: Unified BP Codes ===");
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($bpCode);
        $this->info("Found " . $unifiedBpCodes->count() . " related bp_codes:");
        foreach ($unifiedBpCodes as $code) {
            $this->line("  - {$code}");
        }
        $this->newLine();

        // Test 2: Get unified partners
        $this->info("=== TEST 2: Unified Partners ===");
        $unifiedPartners = $unifiedService->getUnifiedPartners($bpCode);
        $this->info("Found " . $unifiedPartners->count() . " related partners:");
        foreach ($unifiedPartners as $partner) {
            $this->line("  - {$partner->bp_code} ({$partner->bp_name}) - Parent: " . ($partner->parent_bp_code ?? 'None'));
        }
        $this->newLine();

        // Test 3: Get unified InvLines
        $this->info("=== TEST 3: Unified InvLines ===");
        $unifiedInvLines = $unifiedService->getUnifiedInvLines($bpCode);
        $this->info("Found " . $unifiedInvLines->count() . " related InvLines");
        $this->newLine();

        // Test 4: Get unified InvHeaders
        $this->info("=== TEST 4: Unified InvHeaders ===");
        $unifiedInvHeaders = $unifiedService->getUnifiedInvHeaders($bpCode);
        $this->info("Found " . $unifiedInvHeaders->count() . " related InvHeaders");
        $this->newLine();

        // Test 5: Get unified dashboard data
        $this->info("=== TEST 5: Unified Dashboard Data ===");
        $dashboardData = $unifiedService->getUnifiedDashboardData($bpCode);
        foreach ($dashboardData as $key => $value) {
            $this->line("  - {$key}: {$value}");
        }
        $this->newLine();

        // Test 6: Compare with old method
        $this->info("=== TEST 6: Comparison with Old Method ===");
        $oldBpCodes = Partner::relatedBpCodes($bpCode)->pluck('bp_code');
        $this->info("Old method found " . $oldBpCodes->count() . " bp_codes");
        $this->info("New method found " . $unifiedBpCodes->count() . " bp_codes");
        
        if ($oldBpCodes->count() !== $unifiedBpCodes->count()) {
            $this->warn("⚠ Count mismatch detected!");
            $this->info("Old method bp_codes:");
            foreach ($oldBpCodes as $code) {
                $this->line("  - {$code}");
            }
            $this->info("New method bp_codes:");
            foreach ($unifiedBpCodes as $code) {
                $this->line("  - {$code}");
            }
        } else {
            $this->info("✓ Counts match");
        }

        $this->newLine();
        $this->info("Testing completed!");

        return 0;
    }
}
