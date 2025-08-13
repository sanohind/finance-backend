<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;
use App\Services\BusinessPartnerUnifiedService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnifiedBusinessPartnerTest extends TestCase
{
    use RefreshDatabase;

    protected $unifiedService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unifiedService = app(BusinessPartnerUnifiedService::class);
    }

    /** @test */
    public function it_can_get_unified_bp_codes_for_old_system_bp_code()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        $childPartner1 = Partner::create([
            'bp_code' => 'SLAPMTI-1',
            'parent_bp_code' => 'SLAPMTI',
            'bp_name' => 'PT. MISAWA TRADING INDONESIA',
        ]);

        $childPartner2 = Partner::create([
            'bp_code' => 'SLAPMTI-2',
            'parent_bp_code' => 'SLAPMTI',
            'bp_name' => 'PT. MISAWA TRADING INDONESIA',
        ]);

        // Test with old system bp_code
        $unifiedBpCodes = $this->unifiedService->getUnifiedBpCodes('SLAPMTI-1');

        $this->assertCount(3, $unifiedBpCodes);
        $this->assertContains('SLAPMTI', $unifiedBpCodes);
        $this->assertContains('SLAPMTI-1', $unifiedBpCodes);
        $this->assertContains('SLAPMTI-2', $unifiedBpCodes);
    }

    /** @test */
    public function it_can_get_unified_bp_codes_for_new_system_bp_code()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        $childPartner1 = Partner::create([
            'bp_code' => 'SLAPMTI-1',
            'parent_bp_code' => 'SLAPMTI',
            'bp_name' => 'PT. MISAWA TRADING INDONESIA',
        ]);

        $childPartner2 = Partner::create([
            'bp_code' => 'SLAPMTI-2',
            'parent_bp_code' => 'SLAPMTI',
            'bp_name' => 'PT. MISAWA TRADING INDONESIA',
        ]);

        // Test with new system bp_code
        $unifiedBpCodes = $this->unifiedService->getUnifiedBpCodes('SLAPMTI');

        $this->assertCount(3, $unifiedBpCodes);
        $this->assertContains('SLAPMTI', $unifiedBpCodes);
        $this->assertContains('SLAPMTI-1', $unifiedBpCodes);
        $this->assertContains('SLAPMTI-2', $unifiedBpCodes);
    }

    /** @test */
    public function it_returns_same_data_for_old_and_new_bp_codes()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        $childPartner1 = Partner::create([
            'bp_code' => 'SLAPMTI-1',
            'parent_bp_code' => 'SLAPMTI',
            'bp_name' => 'PT. MISAWA TRADING INDONESIA',
        ]);

        // Create InvLine data for both bp_codes
        $invLine1 = InvLine::create([
            'bp_id' => 'SLAPMTI',
            'po_no' => 'PO001',
            'receipt_no' => 'RC001',
            'actual_receipt_date' => now(),
        ]);

        $invLine2 = InvLine::create([
            'bp_id' => 'SLAPMTI-1',
            'po_no' => 'PO002',
            'receipt_no' => 'RC002',
            'actual_receipt_date' => now(),
        ]);

        // Test with old system bp_code
        $oldBpCodes = $this->unifiedService->getUnifiedBpCodes('SLAPMTI-1');
        $oldInvLines = $this->unifiedService->getUnifiedInvLines('SLAPMTI-1');

        // Test with new system bp_code
        $newBpCodes = $this->unifiedService->getUnifiedBpCodes('SLAPMTI');
        $newInvLines = $this->unifiedService->getUnifiedInvLines('SLAPMTI');

        // Both should return the same data
        $this->assertEquals($oldBpCodes->count(), $newBpCodes->count());
        $this->assertEquals($oldInvLines->count(), $newInvLines->count());
        $this->assertEquals(2, $oldInvLines->count()); // Both InvLines should be included
    }

    /** @test */
    public function it_normalizes_bp_code_input()
    {
        $normalized = $this->unifiedService->normalizeBpCode(' slapmti-1 ');
        $this->assertEquals('SLAPMTI-1', $normalized);

        $normalized = $this->unifiedService->normalizeBpCode('SLAPMTI');
        $this->assertEquals('SLAPMTI', $normalized);
    }

    /** @test */
    public function it_can_identify_old_and_new_system_bp_codes()
    {
        $this->assertTrue($this->unifiedService->isOldSystemBpCode('SLAPMTI-1'));
        $this->assertTrue($this->unifiedService->isOldSystemBpCode('SLAPMTI-2'));
        $this->assertFalse($this->unifiedService->isOldSystemBpCode('SLAPMTI'));

        $this->assertTrue($this->unifiedService->isNewSystemBpCode('SLAPMTI'));
        $this->assertFalse($this->unifiedService->isNewSystemBpCode('SLAPMTI-1'));
    }

    /** @test */
    public function it_can_get_base_bp_code()
    {
        $base = $this->unifiedService->getBaseBpCode('SLAPMTI-1');
        $this->assertEquals('SLAPMTI', $base);

        $base = $this->unifiedService->getBaseBpCode('SLAPMTI');
        $this->assertEquals('SLAPMTI', $base);
    }

    /** @test */
    public function it_handles_empty_or_invalid_bp_codes()
    {
        $unifiedBpCodes = $this->unifiedService->getUnifiedBpCodes('');
        $this->assertTrue($unifiedBpCodes->isEmpty());

        $unifiedBpCodes = $this->unifiedService->getUnifiedBpCodes(null);
        $this->assertTrue($unifiedBpCodes->isEmpty());
    }

    /** @test */
    public function it_can_get_unified_dashboard_data()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        // Create InvHeader data
        InvHeader::create([
            'bp_code' => 'SLAPMTI',
            'status' => 'New',
            'inv_no' => 'INV001',
        ]);

        InvHeader::create([
            'bp_code' => 'SLAPMTI-1',
            'status' => 'In Process',
            'inv_no' => 'INV002',
        ]);

        $dashboardData = $this->unifiedService->getUnifiedDashboardData('SLAPMTI');

        $this->assertEquals(1, $dashboardData['new_invoices']);
        $this->assertEquals(1, $dashboardData['in_process_invoices']);
        $this->assertEquals(0, $dashboardData['rejected_invoices']);
    }

    /** @test */
    public function it_can_get_unified_inv_headers()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        $childPartner = Partner::create([
            'bp_code' => 'SLAPMTI-1',
            'parent_bp_code' => 'SLAPMTI',
            'bp_name' => 'PT. MISAWA TRADING INDONESIA',
        ]);

        // Create InvHeader data
        InvHeader::create([
            'bp_code' => 'SLAPMTI',
            'status' => 'New',
            'inv_no' => 'INV001',
        ]);

        InvHeader::create([
            'bp_code' => 'SLAPMTI-1',
            'status' => 'In Process',
            'inv_no' => 'INV002',
        ]);

        $invHeaders = $this->unifiedService->getUnifiedInvHeaders('SLAPMTI');

        $this->assertCount(2, $invHeaders);
        $this->assertTrue($invHeaders->contains('inv_no', 'INV001'));
        $this->assertTrue($invHeaders->contains('inv_no', 'INV002'));
    }

    /** @test */
    public function it_can_get_unified_outstanding_inv_lines()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        $childPartner = Partner::create([
            'bp_code' => 'SLAPMTI-1',
            'parent_bp_code' => 'SLAPMTI',
            'bp_name' => 'PT. MISAWA TRADING INDONESIA',
        ]);

        // Create InvLine data with old dates
        InvLine::create([
            'bp_id' => 'SLAPMTI',
            'po_no' => 'PO001',
            'actual_receipt_date' => now()->subDays(15),
        ]);

        InvLine::create([
            'bp_id' => 'SLAPMTI-1',
            'po_no' => 'PO002',
            'actual_receipt_date' => now()->subDays(12),
        ]);

        $outstandingInvLines = $this->unifiedService->getUnifiedOutstandingInvLines('SLAPMTI');

        $this->assertCount(2, $outstandingInvLines);
        $this->assertEquals('Danger, You Need To Invoicing This Item', $outstandingInvLines->first()->category);
    }
}
