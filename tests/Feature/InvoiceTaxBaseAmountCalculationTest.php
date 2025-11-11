<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\InvHeader;
use App\Models\InvLine;
use App\Models\InvPpn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class InvoiceTaxBaseAmountCalculationTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $ppn;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 2, // Admin
        ]);

        // Create PPN record
        $this->ppn = InvPpn::create([
            'ppn_description' => 'PPN 11%',
            'ppn_rate' => 0.11,
        ]);
    }

    /**
     * Test case yang mensimulasikan masalah pada invoice 47/X/25-0945
     * Dimana penjumlahan receipt_amount berbeda dengan approve_qty * receipt_unit_price
     */
    public function test_tax_base_amount_discrepancy_case_47_X_25_0945(): void
    {
        // Simulasi data invoice lines yang menyebabkan perbedaan
        // Total receipt_amount seharusnya = 361,250
        // Total approve_qty * receipt_unit_price = 356,000
        // Selisih = 5,250

        $invLines = [
            [
                'approve_qty' => 100,
                'receipt_unit_price' => 1000,
                'actual_receipt_qty' => 105, // Qty aktual lebih besar
                'receipt_amount' => 105000,  // 105 * 1000 = 105,000
            ],
            [
                'approve_qty' => 200,
                'receipt_unit_price' => 500,
                'actual_receipt_qty' => 205,
                'receipt_amount' => 102500,  // 205 * 500 = 102,500
            ],
            [
                'approve_qty' => 150,
                'receipt_unit_price' => 1000,
                'actual_receipt_qty' => 153,
                'receipt_amount' => 153000,  // 153 * 1000 = 153,000
            ],
            [
                'approve_qty' => 6,
                'receipt_unit_price' => 125,
                'actual_receipt_qty' => 6,
                'receipt_amount' => 750,     // 6 * 125 = 750
            ],
        ];

        // Create invoice lines
        $createdInvLines = [];
        foreach ($invLines as $lineData) {
            $invLine = InvLine::create([
                'po_no' => 'PO-TEST-001',
                'bp_id' => 'BP001',
                'bp_name' => 'Test Supplier',
                'currency' => 'IDR',
                'po_type' => 'Regular',
                'item_no' => 'ITEM-' . uniqid(),
                'item_desc' => 'Test Item',
                'approve_qty' => $lineData['approve_qty'],
                'actual_receipt_qty' => $lineData['actual_receipt_qty'],
                'receipt_amount' => $lineData['receipt_amount'],
                'receipt_unit_price' => $lineData['receipt_unit_price'],
                'unit' => 'PCS',
            ]);
            $createdInvLines[] = $invLine;
        }

        // Hitung dengan metode yang ada saat ini (approve_qty * receipt_unit_price)
        $currentMethodTotal = 0;
        foreach ($createdInvLines as $line) {
            $currentMethodTotal += $line->approve_qty * $line->receipt_unit_price;
        }

        // Hitung dengan menjumlahkan receipt_amount
        $receiptAmountTotal = 0;
        foreach ($createdInvLines as $line) {
            $receiptAmountTotal += $line->receipt_amount;
        }

        // Expected values based on the problem
        $expectedCurrentMethodTotal = 356000;  // approve_qty * receipt_unit_price
        $expectedReceiptAmountTotal = 361250;  // sum of receipt_amount

        // Assert calculations
        $this->assertEquals($expectedCurrentMethodTotal, $currentMethodTotal, 
            "Current method calculation (approve_qty * receipt_unit_price) should be {$expectedCurrentMethodTotal}");
        
        $this->assertEquals($expectedReceiptAmountTotal, $receiptAmountTotal,
            "Receipt amount total should be {$expectedReceiptAmountTotal}");

        // Verify there IS a discrepancy
        $discrepancy = abs($receiptAmountTotal - $currentMethodTotal);
        $expectedDiscrepancy = 5250;
        
        $this->assertEquals($expectedDiscrepancy, $discrepancy,
            "There should be a discrepancy of {$expectedDiscrepancy} between the two calculation methods");

        // Output detailed information
        echo "\n=== Tax Base Amount Calculation Test ===\n";
        echo "Invoice simulation similar to: 47/X/25-0945\n\n";
        echo "Method 1 (Current - approve_qty × receipt_unit_price): " . number_format($currentMethodTotal, 0, ',', '.') . "\n";
        echo "Method 2 (Sum of receipt_amount): " . number_format($receiptAmountTotal, 0, ',', '.') . "\n";
        echo "Discrepancy: " . number_format($discrepancy, 0, ',', '.') . "\n\n";
        
        echo "Detailed breakdown:\n";
        foreach ($createdInvLines as $index => $line) {
            $lineCalcCurrent = $line->approve_qty * $line->receipt_unit_price;
            $lineCalcReceipt = $line->receipt_amount;
            $lineDiff = $lineCalcReceipt - $lineCalcCurrent;
            
            echo "Line " . ($index + 1) . ":\n";
            echo "  Approve Qty: {$line->approve_qty}, Actual Receipt Qty: {$line->actual_receipt_qty}\n";
            echo "  Receipt Unit Price: " . number_format($line->receipt_unit_price, 0, ',', '.') . "\n";
            echo "  Current Method: " . number_format($lineCalcCurrent, 0, ',', '.') . "\n";
            echo "  Receipt Amount: " . number_format($lineCalcReceipt, 0, ',', '.') . "\n";
            echo "  Line Difference: " . number_format($lineDiff, 0, ',', '.') . "\n\n";
        }
    }

    /**
     * Test untuk memverifikasi bahwa API store menggunakan approve_qty * receipt_unit_price
     */
    public function test_api_store_uses_approve_qty_calculation(): void
    {
        $this->actingAs($this->user);

        // Create invoice lines with discrepancy
        $invLine1 = InvLine::create([
            'po_no' => 'PO-API-001',
            'bp_id' => 'BP002',
            'bp_name' => 'API Test Supplier',
            'currency' => 'IDR',
            'po_type' => 'Regular',
            'item_no' => 'API-ITEM-001',
            'item_desc' => 'API Test Item 1',
            'approve_qty' => 50,
            'actual_receipt_qty' => 52, // Different from approve_qty
            'receipt_amount' => 52000,  // 52 * 1000
            'receipt_unit_price' => 1000,
            'unit' => 'PCS',
        ]);

        $invLine2 = InvLine::create([
            'po_no' => 'PO-API-001',
            'bp_id' => 'BP002',
            'bp_name' => 'API Test Supplier',
            'currency' => 'IDR',
            'po_type' => 'Regular',
            'item_no' => 'API-ITEM-002',
            'item_desc' => 'API Test Item 2',
            'approve_qty' => 30,
            'actual_receipt_qty' => 33,
            'receipt_amount' => 16500,  // 33 * 500
            'receipt_unit_price' => 500,
            'unit' => 'PCS',
        ]);

        // Expected: approve_qty * receipt_unit_price
        // Line 1: 50 * 1000 = 50,000
        // Line 2: 30 * 500 = 15,000
        // Total: 65,000
        $expectedTaxBaseAmount = 65000;

        // If using receipt_amount:
        // Line 1: 52,000
        // Line 2: 16,500
        // Total: 68,500
        $receiptAmountTotal = 68500;

        // Make API request
        $response = $this->postJson('/api/finance/inv-header/store', [
            'inv_no' => 'INV-TEST-001',
            'inv_date' => '2025-10-01',
            'inv_faktur' => 'FK-TEST-001',
            'inv_faktur_date' => '2025-10-01',
            'ppn_id' => $this->ppn->ppn_id,
            'inv_line_detail' => [
                $invLine1->inv_line_id,
                $invLine2->inv_line_id,
            ],
        ]);

        $response->assertStatus(201);
        
        $data = $response->json();
        $actualTaxBaseAmount = $data['tax_base_amount'];

        // Verify API uses approve_qty calculation
        $this->assertEquals($expectedTaxBaseAmount, $actualTaxBaseAmount,
            "API should use approve_qty * receipt_unit_price calculation");

        // Show that it's different from receipt_amount sum
        $this->assertNotEquals($receiptAmountTotal, $actualTaxBaseAmount,
            "API result should differ from receipt_amount sum when there's qty discrepancy");

        $discrepancy = abs($receiptAmountTotal - $actualTaxBaseAmount);

        echo "\n=== API Store Calculation Test ===\n";
        echo "API returned tax_base_amount: " . number_format($actualTaxBaseAmount, 0, ',', '.') . "\n";
        echo "Expected (approve_qty method): " . number_format($expectedTaxBaseAmount, 0, ',', '.') . "\n";
        echo "Sum of receipt_amount: " . number_format($receiptAmountTotal, 0, ',', '.') . "\n";
        echo "Discrepancy: " . number_format($discrepancy, 0, ',', '.') . "\n";
    }

    /**
     * Test untuk menghitung persentase perbedaan
     */
    public function test_calculate_percentage_difference(): void
    {
        $receiptAmountTotal = 361250;
        $approveQtyTotal = 356000;
        $discrepancy = $receiptAmountTotal - $approveQtyTotal;
        
        $percentageDiff = ($discrepancy / $receiptAmountTotal) * 100;

        echo "\n=== Percentage Difference Analysis ===\n";
        echo "Receipt Amount Total: " . number_format($receiptAmountTotal, 0, ',', '.') . "\n";
        echo "Approve Qty Total: " . number_format($approveQtyTotal, 0, ',', '.') . "\n";
        echo "Absolute Difference: " . number_format($discrepancy, 0, ',', '.') . "\n";
        echo "Percentage Difference: " . number_format($percentageDiff, 2) . "%\n";

        $this->assertGreaterThan(0, $discrepancy, "Discrepancy should be positive");
        $this->assertLessThan(2, $percentageDiff, "Percentage difference should be less than 2%");
    }

    /**
     * Test recommendation: Apa yang seharusnya digunakan?
     */
    public function test_recommendation_which_amount_to_use(): void
    {
        echo "\n=== REKOMENDASI ===\n\n";
        echo "MASALAH DITEMUKAN:\n";
        echo "- System saat ini menggunakan: approve_qty × receipt_unit_price\n";
        echo "- User mengharapkan total dari: receipt_amount\n";
        echo "- Perbedaan terjadi ketika approve_qty ≠ actual_receipt_qty\n\n";
        
        echo "ANALISIS:\n";
        echo "1. receipt_amount = actual_receipt_qty × receipt_unit_price\n";
        echo "   (Jumlah yang benar-benar diterima)\n\n";
        echo "2. approve_qty × receipt_unit_price\n";
        echo "   (Jumlah yang disetujui untuk dibayar)\n\n";
        
        echo "OPSI SOLUSI:\n";
        echo "A. Gunakan receipt_amount langsung:\n";
        echo "   + Lebih akurat dengan penerimaan aktual\n";
        echo "   + Konsisten dengan data warehouse\n";
        echo "   - Mungkin bayar lebih jika ada overdelivery\n\n";
        
        echo "B. Tetap gunakan approve_qty:\n";
        echo "   + Kontrol pembayaran lebih ketat\n";
        echo "   + Hanya bayar yang disetujui\n";
        echo "   - Perlu rekonsiliasi manual jika ada perbedaan\n\n";
        
        echo "REKOMENDASI:\n";
        echo "Gunakan OPSI A (receipt_amount) karena:\n";
        echo "- Data receipt_amount sudah dihitung dengan benar di sistem source\n";
        echo "- Lebih mencerminkan transaksi aktual\n";
        echo "- Mengurangi perbedaan perhitungan manual vs sistem\n";

        $this->assertTrue(true, "Recommendation test completed");
    }
}
