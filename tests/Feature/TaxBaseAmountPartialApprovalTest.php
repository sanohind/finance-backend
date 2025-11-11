<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\InvHeader;
use App\Models\InvLine;
use App\Models\InvPpn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxBaseAmountPartialApprovalTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $ppn;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 2,
        ]);

        $this->ppn = InvPpn::create([
            'ppn_description' => 'PPN 11%',
            'ppn_rate' => 0.11,
        ]);
    }

    /**
     * Test Skenario A: Full Approval (Normal Case)
     * approve_qty == actual_receipt_qty
     * Expected: Gunakan receipt_amount
     */
    public function test_full_approval_uses_receipt_amount(): void
    {
        $invLine = InvLine::create([
            'po_no' => 'PO-001',
            'bp_id' => 'BP001',
            'bp_name' => 'Test Supplier',
            'currency' => 'IDR',
            'po_type' => 'Regular',
            'item_no' => 'ITEM-001',
            'item_desc' => 'Test Item',
            'actual_receipt_qty' => 100,
            'approve_qty' => 100, // SAMA dengan actual
            'receipt_unit_price' => 1000,
            'receipt_amount' => 100000, // Full amount
            'unit' => 'PCS',
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/finance/inv-header/store', [
            'inv_no' => 'INV-001',
            'inv_date' => '2025-11-11',
            'inv_faktur' => 'FK-001',
            'inv_faktur_date' => '2025-11-11',
            'ppn_id' => $this->ppn->ppn_id,
            'inv_line_detail' => [$invLine->inv_line_id],
        ]);

        $response->assertStatus(201);
        
        $data = $response->json();
        
        // Harus gunakan receipt_amount karena full approval
        $this->assertEquals(100000, $data['tax_base_amount'],
            "Full approval should use receipt_amount from ERP");
        
        echo "\n✅ Skenario A: Full Approval\n";
        echo "   Approve Qty = Actual Qty = 100\n";
        echo "   Receipt Amount: Rp 100,000\n";
        echo "   Tax Base Amount: Rp " . number_format($data['tax_base_amount'], 0, ',', '.') . "\n";
        echo "   Status: PASSED (Uses receipt_amount)\n";
    }

    /**
     * Test Skenario B: Partial Rejection
     * approve_qty < actual_receipt_qty
     * Expected: Gunakan approve_qty × price
     */
    public function test_partial_rejection_uses_approve_qty(): void
    {
        $invLine = InvLine::create([
            'po_no' => 'PO-002',
            'bp_id' => 'BP002',
            'bp_name' => 'Test Supplier',
            'currency' => 'IDR',
            'po_type' => 'Regular',
            'item_no' => 'ITEM-002',
            'item_desc' => 'Test Item with Rejection',
            'actual_receipt_qty' => 100, // Terima 100
            'approve_qty' => 80,          // Approve hanya 80 (reject 20)
            'receipt_unit_price' => 1000,
            'receipt_amount' => 100000,   // Receipt amount untuk 100 unit
            'unit' => 'PCS',
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/finance/inv-header/store', [
            'inv_no' => 'INV-002',
            'inv_date' => '2025-11-11',
            'inv_faktur' => 'FK-002',
            'inv_faktur_date' => '2025-11-11',
            'ppn_id' => $this->ppn->ppn_id,
            'inv_line_detail' => [$invLine->inv_line_id],
        ]);

        $response->assertStatus(201);
        
        $data = $response->json();
        
        // Harus gunakan approve_qty × price, BUKAN receipt_amount
        $expectedAmount = 80 * 1000; // 80,000
        $this->assertEquals($expectedAmount, $data['tax_base_amount'],
            "Partial approval should use approve_qty × price to prevent overpayment");
        
        // Pastikan TIDAK menggunakan receipt_amount
        $this->assertNotEquals(100000, $data['tax_base_amount'],
            "Should NOT use full receipt_amount when there's rejection");
        
        echo "\n✅ Skenario B: Partial Rejection\n";
        echo "   Actual Qty: 100, Approve Qty: 80 (Reject 20)\n";
        echo "   Receipt Amount: Rp 100,000\n";
        echo "   Tax Base Amount: Rp " . number_format($data['tax_base_amount'], 0, ',', '.') . "\n";
        echo "   Status: PASSED (Uses approve_qty × price)\n";
        echo "   ⚠️  Prevented overpayment of Rp 20,000\n";
    }

    /**
     * Test Skenario C: Decimal Quantity (Invoice 47/X/25-0945 case)
     * approve_qty == actual_receipt_qty = 0 (rounded from 0.25)
     * receipt_amount memiliki nilai dari qty desimal
     * Expected: Gunakan receipt_amount
     */
    public function test_decimal_quantity_uses_receipt_amount(): void
    {
        $invLine = InvLine::create([
            'po_no' => 'PO-003',
            'bp_id' => 'BP003',
            'bp_name' => 'Test Supplier',
            'currency' => 'IDR',
            'po_type' => 'Regular',
            'item_no' => 'GL8BA0PAKU3CM00',
            'item_desc' => 'PAKU 3CM',
            'actual_receipt_qty' => 0,   // Rounded dari 0.25
            'approve_qty' => 0,           // Rounded dari 0.25
            'receipt_unit_price' => 25000,
            'receipt_amount' => 6250,     // 0.25 × 25,000
            'unit' => 'BOX',
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/finance/inv-header/store', [
            'inv_no' => 'INV-003',
            'inv_date' => '2025-11-11',
            'inv_faktur' => 'FK-003',
            'inv_faktur_date' => '2025-11-11',
            'ppn_id' => $this->ppn->ppn_id,
            'inv_line_detail' => [$invLine->inv_line_id],
        ]);

        $response->assertStatus(201);
        
        $data = $response->json();
        
        // Harus gunakan receipt_amount karena approve = actual
        // Ini handle decimal qty yang di-round ke 0
        $this->assertEquals(6250, $data['tax_base_amount'],
            "Decimal qty case should use receipt_amount to prevent data loss");
        
        // Kalau pakai approve_qty × price, akan 0!
        $wrongCalculation = 0 * 25000; // = 0
        $this->assertNotEquals($wrongCalculation, $data['tax_base_amount'],
            "Should NOT calculate 0 × price which loses decimal qty data");
        
        echo "\n✅ Skenario C: Decimal Quantity\n";
        echo "   Approve Qty = Actual Qty = 0 (rounded from 0.25)\n";
        echo "   Receipt Amount: Rp 6,250 (0.25 × 25,000)\n";
        echo "   Tax Base Amount: Rp " . number_format($data['tax_base_amount'], 0, ',', '.') . "\n";
        echo "   Status: PASSED (Uses receipt_amount)\n";
        echo "   ⚠️  Prevented data loss of Rp 6,250\n";
    }

    /**
     * Test Skenario D: With Discount/Adjustment
     * approve_qty == actual_receipt_qty
     * receipt_amount berbeda dari qty × price (ada diskon)
     * Expected: Gunakan receipt_amount
     */
    public function test_with_discount_uses_receipt_amount(): void
    {
        $invLine = InvLine::create([
            'po_no' => 'PO-004',
            'bp_id' => 'BP004',
            'bp_name' => 'Test Supplier',
            'currency' => 'IDR',
            'po_type' => 'Regular',
            'item_no' => 'GL8BA0PAKU4CM00',
            'item_desc' => 'PAKU 4CM with 50% discount',
            'actual_receipt_qty' => 1,
            'approve_qty' => 1,
            'receipt_unit_price' => 22000,
            'receipt_amount' => 11000,    // Diskon 50%
            'unit' => 'BOX',
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/finance/inv-header/store', [
            'inv_no' => 'INV-004',
            'inv_date' => '2025-11-11',
            'inv_faktur' => 'FK-004',
            'inv_faktur_date' => '2025-11-11',
            'ppn_id' => $this->ppn->ppn_id,
            'inv_line_detail' => [$invLine->inv_line_id],
        ]);

        $response->assertStatus(201);
        
        $data = $response->json();
        
        // Harus gunakan receipt_amount untuk dapat diskon
        $this->assertEquals(11000, $data['tax_base_amount'],
            "Should use receipt_amount to capture discount from ERP");
        
        // Kalau pakai approve_qty × price, kehilangan diskon!
        $wrongCalculation = 1 * 22000; // = 22,000
        $this->assertNotEquals($wrongCalculation, $data['tax_base_amount'],
            "Should NOT ignore discount by calculating qty × original price");
        
        echo "\n✅ Skenario D: With Discount\n";
        echo "   Approve Qty = Actual Qty = 1\n";
        echo "   Unit Price: Rp 22,000\n";
        echo "   Receipt Amount: Rp 11,000 (50% discount)\n";
        echo "   Tax Base Amount: Rp " . number_format($data['tax_base_amount'], 0, ',', '.') . "\n";
        echo "   Status: PASSED (Uses receipt_amount with discount)\n";
        echo "   💰 Saved Rp 11,000 from discount\n";
    }

    /**
     * Test Skenario E: Mixed Items (Full + Partial + Decimal)
     * Kombinasi berbagai skenario dalam satu invoice
     */
    public function test_mixed_scenarios_in_one_invoice(): void
    {
        // Line 1: Full approval
        $line1 = InvLine::create([
            'po_no' => 'PO-005',
            'bp_id' => 'BP005',
            'bp_name' => 'Test Supplier',
            'currency' => 'IDR',
            'item_no' => 'ITEM-FULL',
            'actual_receipt_qty' => 10,
            'approve_qty' => 10,
            'receipt_unit_price' => 1000,
            'receipt_amount' => 10000,
            'unit' => 'PCS',
        ]);

        // Line 2: Partial rejection
        $line2 = InvLine::create([
            'po_no' => 'PO-005',
            'bp_id' => 'BP005',
            'bp_name' => 'Test Supplier',
            'currency' => 'IDR',
            'item_no' => 'ITEM-PARTIAL',
            'actual_receipt_qty' => 100,
            'approve_qty' => 80,
            'receipt_unit_price' => 500,
            'receipt_amount' => 50000,
            'unit' => 'PCS',
        ]);

        // Line 3: Decimal qty
        $line3 = InvLine::create([
            'po_no' => 'PO-005',
            'bp_id' => 'BP005',
            'bp_name' => 'Test Supplier',
            'currency' => 'IDR',
            'item_no' => 'ITEM-DECIMAL',
            'actual_receipt_qty' => 0,
            'approve_qty' => 0,
            'receipt_unit_price' => 25000,
            'receipt_amount' => 6250,
            'unit' => 'BOX',
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/finance/inv-header/store', [
            'inv_no' => 'INV-005',
            'inv_date' => '2025-11-11',
            'inv_faktur' => 'FK-005',
            'inv_faktur_date' => '2025-11-11',
            'ppn_id' => $this->ppn->ppn_id,
            'inv_line_detail' => [
                $line1->inv_line_id,
                $line2->inv_line_id,
                $line3->inv_line_id,
            ],
        ]);

        $response->assertStatus(201);
        
        $data = $response->json();
        
        // Expected calculation:
        // Line 1: 10,000 (receipt_amount, full approval)
        // Line 2: 40,000 (80 × 500, partial approval)
        // Line 3: 6,250 (receipt_amount, decimal qty)
        // Total: 56,250
        $expectedTotal = 10000 + 40000 + 6250;
        
        $this->assertEquals($expectedTotal, $data['tax_base_amount'],
            "Mixed scenarios should calculate correctly");
        
        echo "\n✅ Skenario E: Mixed Items\n";
        echo "   Line 1 (Full): Rp 10,000\n";
        echo "   Line 2 (Partial 80/100): Rp 40,000 (saved 10,000 from rejection)\n";
        echo "   Line 3 (Decimal 0.25): Rp 6,250\n";
        echo "   Total Tax Base: Rp " . number_format($data['tax_base_amount'], 0, ',', '.') . "\n";
        echo "   Status: PASSED\n";
    }

    /**
     * Test Skenario F: Extreme Case - Over-delivery with partial approval
     * actual_receipt_qty > PO qty, approve_qty < actual
     */
    public function test_over_delivery_with_partial_approval(): void
    {
        $invLine = InvLine::create([
            'po_no' => 'PO-006',
            'bp_id' => 'BP006',
            'bp_name' => 'Test Supplier',
            'currency' => 'IDR',
            'item_no' => 'ITEM-OVER',
            'request_qty' => 100,         // PO request 100
            'actual_receipt_qty' => 120,  // Terima 120 (over 20)
            'approve_qty' => 100,          // Approve hanya 100 (reject over-delivery)
            'receipt_unit_price' => 1000,
            'receipt_amount' => 120000,    // Amount untuk 120 unit
            'unit' => 'PCS',
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/finance/inv-header/store', [
            'inv_no' => 'INV-006',
            'inv_date' => '2025-11-11',
            'inv_faktur' => 'FK-006',
            'inv_faktur_date' => '2025-11-11',
            'ppn_id' => $this->ppn->ppn_id,
            'inv_line_detail' => [$invLine->inv_line_id],
        ]);

        $response->assertStatus(201);
        
        $data = $response->json();
        
        // Harus bayar hanya 100,000, bukan 120,000
        $expectedAmount = 100 * 1000;
        $this->assertEquals($expectedAmount, $data['tax_base_amount'],
            "Should only pay for approved quantity, not over-delivery");
        
        echo "\n✅ Skenario F: Over-delivery with Rejection\n";
        echo "   Request: 100, Actual: 120, Approve: 100\n";
        echo "   Receipt Amount: Rp 120,000\n";
        echo "   Tax Base Amount: Rp " . number_format($data['tax_base_amount'], 0, ',', '.') . "\n";
        echo "   Status: PASSED (Rejected over-delivery)\n";
        echo "   ⚠️  Prevented overpayment of Rp 20,000\n";
    }
}
