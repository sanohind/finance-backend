<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BusinessPartnerUnifiedService;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;
use App\Models\User;
use App\Http\Resources\InvLineResource;
use App\Http\Resources\InvHeaderResource;

class TestApiResponse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:test-response {bp_code : BP code to test} {--compare : Compare with base bp_code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test API response format and data for specific bp_code';

    /**
     * Execute the console command.
     */
    public function handle(BusinessPartnerUnifiedService $unifiedService)
    {
        $bpCode = $this->argument('bp_code');
        $compare = $this->option('compare');

        $this->info("=== TESTING API RESPONSE FOR: {$bpCode} ===");
        $this->newLine();

        // Test 1: Check if bp_code exists
        $partner = Partner::where('bp_code', $bpCode)->first();
        if (!$partner) {
            $this->error("❌ BP_CODE '{$bpCode}' not found!");
            return 1;
        }

        $this->info("✓ BP_CODE found: {$partner->bp_name}");

        // Test 2: Test unified service response
        $this->newLine();
        $this->info("=== UNIFIED SERVICE RESPONSE TEST ===");
        
        $unifiedBpCodes = $unifiedService->getUnifiedBpCodes($bpCode);
        $this->info("Unified BP_CODES: " . $unifiedBpCodes->count());
        foreach ($unifiedBpCodes as $code) {
            $this->line("  - {$code}");
        }

        // Test 3: Test InvLine data
        $this->newLine();
        $this->info("=== INVLINE DATA TEST ===");
        
        $invLines = $unifiedService->getUnifiedInvLines($bpCode);
        $this->info("Total InvLine records: " . $invLines->count());

        if ($invLines->count() > 0) {
            $this->info("Sample InvLine data:");
            $sampleInvLine = $invLines->first();
            $this->line("  - BP_ID: {$sampleInvLine->bp_id}");
            $this->line("  - PO_NO: {$sampleInvLine->po_no}");
            $this->line("  - Receipt No: {$sampleInvLine->receipt_no}");
            $this->line("  - Receipt Date: {$sampleInvLine->actual_receipt_date}");
        }

        // Test 4: Test InvHeader data
        $this->newLine();
        $this->info("=== INVHEADER DATA TEST ===");
        
        $invHeaders = $unifiedService->getUnifiedInvHeaders($bpCode);
        $this->info("Total InvHeader records: " . $invHeaders->count());

        if ($invHeaders->count() > 0) {
            $this->info("Sample InvHeader data:");
            $sampleInvHeader = $invHeaders->first();
            $this->line("  - BP_CODE: {$sampleInvHeader->bp_code}");
            $this->line("  - INV_NO: {$sampleInvHeader->inv_no}");
            $this->line("  - Status: {$sampleInvHeader->status}");
            $this->line("  - Total Amount: {$sampleInvHeader->total_amount}");
        }

        // Test 5: Test Resource transformation
        $this->newLine();
        $this->info("=== RESOURCE TRANSFORMATION TEST ===");
        
        try {
            $invLineResource = InvLineResource::collection($invLines->take(1));
            $this->info("✓ InvLineResource transformation successful");
            
            $invHeaderResource = InvHeaderResource::collection($invHeaders->take(1));
            $this->info("✓ InvHeaderResource transformation successful");
            
        } catch (\Exception $e) {
            $this->error("✗ Resource transformation failed: " . $e->getMessage());
        }

        // Test 6: Compare with base bp_code if requested
        if ($compare) {
            $this->newLine();
            $this->info("=== COMPARISON WITH BASE BP_CODE ===");
            
            $baseBpCode = $unifiedService->getBaseBpCode($bpCode);
            $this->info("Base BP_CODE: {$baseBpCode}");
            
            $baseInvLines = $unifiedService->getUnifiedInvLines($baseBpCode);
            $baseInvHeaders = $unifiedService->getUnifiedInvHeaders($baseBpCode);
            
            $this->info("Comparison results:");
            $this->line("  - Original InvLines: " . $invLines->count());
            $this->line("  - Base InvLines: " . $baseInvLines->count());
            $this->line("  - Original InvHeaders: " . $invHeaders->count());
            $this->line("  - Base InvHeaders: " . $baseInvHeaders->count());
            
            if ($invLines->count() === $baseInvLines->count() && $invHeaders->count() === $baseInvHeaders->count()) {
                $this->info("✓ Data consistency verified");
            } else {
                $this->warn("⚠ Data inconsistency detected");
            }
        }

        // Test 7: Check data distribution
        $this->newLine();
        $this->info("=== DATA DISTRIBUTION ANALYSIS ===");
        
        $invLineDistribution = $invLines->groupBy('bp_id');
        $this->info("InvLine distribution by bp_id:");
        foreach ($invLineDistribution as $bpId => $lines) {
            $this->line("  - {$bpId}: {$lines->count()} records");
        }

        $invHeaderDistribution = $invHeaders->groupBy('bp_code');
        $this->info("InvHeader distribution by bp_code:");
        foreach ($invHeaderDistribution as $bpCode => $headers) {
            $this->line("  - {$bpCode}: {$headers->count()} records");
        }

        // Test 8: Check for potential API issues
        $this->newLine();
        $this->info("=== POTENTIAL API ISSUES ===");
        
        $issues = [];
        
        // Check if data is empty
        if ($invLines->isEmpty()) {
            $issues[] = "No InvLine data found";
        }
        
        if ($invHeaders->isEmpty()) {
            $issues[] = "No InvHeader data found";
        }
        
        // Check if unified bp_codes are empty
        if ($unifiedBpCodes->isEmpty()) {
            $issues[] = "No unified bp_codes found";
        }
        
        // Check for data inconsistency
        $expectedBpCodes = collect([$bpCode]);
        if ($unifiedService->isOldSystemBpCode($bpCode)) {
            $baseBpCode = $unifiedService->getBaseBpCode($bpCode);
            $expectedBpCodes->push($baseBpCode);
        }
        
        $missingBpCodes = $expectedBpCodes->diff($unifiedBpCodes);
        if ($missingBpCodes->isNotEmpty()) {
            $issues[] = "Missing expected bp_codes: " . $missingBpCodes->implode(', ');
        }
        
        if (empty($issues)) {
            $this->info("✓ No API issues detected");
        } else {
            $this->warn("Found " . count($issues) . " potential API issues:");
            foreach ($issues as $issue) {
                $this->error("  - {$issue}");
            }
        }

        $this->newLine();
        $this->info("=== TEST COMPLETE ===");
        
        return 0;
    }
}
