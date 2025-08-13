<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Local\Partner;
use App\Models\InvLine;
use App\Models\InvHeader;
use App\Services\BusinessPartnerUnifiedService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BusinessPartnerSuffix2Test extends TestCase
{
    use RefreshDatabase;

    protected $unifiedService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unifiedService = app(BusinessPartnerUnifiedService::class);
    }

    /** @test */
    public function it_can_handle_bp_codes_with_suffix_2()
    {
        // Create test data with -2 suffix
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

        // Test with -2 suffix
        $unifiedBpCodes = $this->unifiedService->getUnifiedBpCodes('SLAPMTI-2');

        $this->assertCount(3, $unifiedBpCodes);
        $this->assertContains('SLAPMTI', $unifiedBpCodes);
        $this->assertContains('SLAPMTI-1', $unifiedBpCodes);
        $this->assertContains('SLAPMTI-2', $unifiedBpCodes);
    }

    /** @test */
    public function it_returns_same_data_for_suffix_2_and_base_bp_code()
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

        // Create InvLine data for different bp_codes
        InvLine::create([
            'bp_id' => 'SLAPMTI',
            'po_no' => 'PO001',
            'receipt_no' => 'RC001',
            'actual_receipt_date' => now(),
        ]);

        InvLine::create([
            'bp_id' => 'SLAPMTI-1',
            'po_no' => 'PO002',
            'receipt_no' => 'RC002',
            'actual_receipt_date' => now(),
        ]);

        InvLine::create([
            'bp_id' => 'SLAPMTI-2',
            'po_no' => 'PO003',
            'receipt_no' => 'RC003',
            'actual_receipt_date' => now(),
        ]);

        // Test with -2 suffix
        $suffix2BpCodes = $this->unifiedService->getUnifiedBpCodes('SLAPMTI-2');
        $suffix2InvLines = $this->unifiedService->getUnifiedInvLines('SLAPMTI-2');

        // Test with base bp_code
        $baseBpCodes = $this->unifiedService->getUnifiedBpCodes('SLAPMTI');
        $baseInvLines = $this->unifiedService->getUnifiedInvLines('SLAPMTI');

        // Both should return the same data
        $this->assertEquals($suffix2BpCodes->count(), $baseBpCodes->count());
        $this->assertEquals($suffix2InvLines->count(), $baseInvLines->count());
        $this->assertEquals(3, $suffix2InvLines->count()); // All InvLines should be included
    }

    /** @test */
    public function it_can_handle_multiple_suffixes_including_2()
    {
        // Create test data with multiple suffixes
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

        $childPartner3 = Partner::create([
            'bp_code' => 'SLAPMTI-3',
            'parent_bp_code' => 'SLAPMTI',
            'bp_name' => 'PT. MISAWA TRADING INDONESIA',
        ]);

        // Test with -1 suffix
        $unifiedBpCodes1 = $this->unifiedService->getUnifiedBpCodes('SLAPMTI-1');
        $this->assertCount(4, $unifiedBpCodes1);

        // Test with -2 suffix
        $unifiedBpCodes2 = $this->unifiedService->getUnifiedBpCodes('SLAPMTI-2');
        $this->assertCount(4, $unifiedBpCodes2);

        // Test with -3 suffix
        $unifiedBpCodes3 = $this->unifiedService->getUnifiedBpCodes('SLAPMTI-3');
        $this->assertCount(4, $unifiedBpCodes3);

        // Test with base bp_code
        $unifiedBpCodesBase = $this->unifiedService->getUnifiedBpCodes('SLAPMTI');
        $this->assertCount(4, $unifiedBpCodesBase);

        // All should return the same bp_codes
        $this->assertEquals($unifiedBpCodes1->toArray(), $unifiedBpCodes2->toArray());
        $this->assertEquals($unifiedBpCodes2->toArray(), $unifiedBpCodes3->toArray());
        $this->assertEquals($unifiedBpCodes3->toArray(), $unifiedBpCodesBase->toArray());
    }

    /** @test */
    public function it_can_handle_inv_headers_with_suffix_2()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        $childPartner2 = Partner::create([
            'bp_code' => 'SLAPMTI-2',
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
            'bp_code' => 'SLAPMTI-2',
            'status' => 'In Process',
            'inv_no' => 'INV002',
        ]);

        // Test with -2 suffix
        $suffix2InvHeaders = $this->unifiedService->getUnifiedInvHeaders('SLAPMTI-2');
        $this->assertCount(2, $suffix2InvHeaders);

        // Test with base bp_code
        $baseInvHeaders = $this->unifiedService->getUnifiedInvHeaders('SLAPMTI');
        $this->assertCount(2, $baseInvHeaders);

        // Both should return the same data
        $this->assertEquals($suffix2InvHeaders->count(), $baseInvHeaders->count());
    }

    /** @test */
    public function it_can_handle_dashboard_data_with_suffix_2()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        $childPartner2 = Partner::create([
            'bp_code' => 'SLAPMTI-2',
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
            'bp_code' => 'SLAPMTI-2',
            'status' => 'In Process',
            'inv_no' => 'INV002',
        ]);

        // Test with -2 suffix
        $suffix2Dashboard = $this->unifiedService->getUnifiedDashboardData('SLAPMTI-2');
        $this->assertEquals(1, $suffix2Dashboard['new_invoices']);
        $this->assertEquals(1, $suffix2Dashboard['in_process_invoices']);

        // Test with base bp_code
        $baseDashboard = $this->unifiedService->getUnifiedDashboardData('SLAPMTI');
        $this->assertEquals(1, $baseDashboard['new_invoices']);
        $this->assertEquals(1, $baseDashboard['in_process_invoices']);

        // Both should return the same data
        $this->assertEquals($suffix2Dashboard, $baseDashboard);
    }

    /** @test */
    public function it_can_handle_outstanding_inv_lines_with_suffix_2()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        $childPartner2 = Partner::create([
            'bp_code' => 'SLAPMTI-2',
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
            'bp_id' => 'SLAPMTI-2',
            'po_no' => 'PO002',
            'actual_receipt_date' => now()->subDays(12),
        ]);

        // Test with -2 suffix
        $suffix2Outstanding = $this->unifiedService->getUnifiedOutstandingInvLines('SLAPMTI-2');
        $this->assertCount(2, $suffix2Outstanding);

        // Test with base bp_code
        $baseOutstanding = $this->unifiedService->getUnifiedOutstandingInvLines('SLAPMTI');
        $this->assertCount(2, $baseOutstanding);

        // Both should return the same data
        $this->assertEquals($suffix2Outstanding->count(), $baseOutstanding->count());
    }

    /** @test */
    public function it_can_handle_uninvoiced_inv_lines_with_suffix_2()
    {
        // Create test data
        $parentPartner = Partner::create([
            'bp_code' => 'SLAPMTI',
            'parent_bp_code' => null,
            'bp_name' => 'MISAWA TRADING INDONESIA PT.',
        ]);

        $childPartner2 = Partner::create([
            'bp_code' => 'SLAPMTI-2',
            'parent_bp_code' => 'SLAPMTI',
            'bp_name' => 'PT. MISAWA TRADING INDONESIA',
        ]);

        // Create InvLine data (uninvoiced)
        InvLine::create([
            'bp_id' => 'SLAPMTI',
            'po_no' => 'PO001',
            'actual_receipt_date' => now(),
            'inv_supplier_no' => null,
            'inv_due_date' => null,
        ]);

        InvLine::create([
            'bp_id' => 'SLAPMTI-2',
            'po_no' => 'PO002',
            'actual_receipt_date' => now(),
            'inv_supplier_no' => null,
            'inv_due_date' => null,
        ]);

        // Test with -2 suffix
        $suffix2Uninvoiced = $this->unifiedService->getUnifiedUninvoicedInvLines('SLAPMTI-2');
        $this->assertCount(2, $suffix2Uninvoiced);

        // Test with base bp_code
        $baseUninvoiced = $this->unifiedService->getUnifiedUninvoicedInvLines('SLAPMTI');
        $this->assertCount(2, $baseUninvoiced);

        // Both should return the same data
        $this->assertEquals($suffix2Uninvoiced->count(), $baseUninvoiced->count());
    }
}
