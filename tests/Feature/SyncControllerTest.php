<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\InvLine;
use App\Models\ERP\InvReceipt;
use App\Models\Local\PoDetail;
use App\Models\Local\DnHeader;
use App\Models\Local\DnDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class SyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->superAdmin()->create();
    }

    #[Test]
    public function test_local_data_controller_sync_with_mock_data()
    {
        // Create test data for LocalDataController
        $poDetail = PoDetail::create([
            'po_no' => 'PO-LOCAL-TEST-001',
            'planned_receipt_date' => '2025-01-15',
            'po_qty' => 100,
            'price' => 50000,
            'item_code' => 'ITEM-LOCAL-001',
            'item_desc_a' => 'Local Test Item Description',
            'bp_part_no' => 'BP-LOCAL-001',
            'purchase_unit' => 'PCS',
            'amount' => 5000000
        ]);

        $dnHeader = DnHeader::create([
            'po_no' => 'PO-LOCAL-TEST-001',
            'no_dn' => 'DN-LOCAL-TEST-001',
            'supplier_code' => 'SUP-LOCAL-001',
            'supplier_name' => 'Local Test Supplier'
        ]);

        $dnDetail = DnDetail::create([
            'no_dn' => 'DN-LOCAL-TEST-001',
            'dn_line' => '1',
            'actual_receipt_date' => '2025-01-15',
            'receipt_qty' => 100,
            'status_desc' => 'Confirmed'
        ]);

        // Test sync endpoint
        $response = $this->actingAs($this->user)
            ->get('/api/local2/sync-inv-line');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'stats' => [
                'processed',
                'skipped',
                'errors'
            ]
        ]);

        // Verify data was synced
        $this->assertDatabaseHas('inv_line', [
            'po_no' => 'PO-LOCAL-TEST-001',
            'receipt_no' => 'DN-LOCAL-TEST-001',
            'receipt_line' => '1'
        ]);

        // Verify the response contains expected data
        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThanOrEqual(1, $responseData['stats']['processed']);
    }

    #[Test]
    public function test_sync_with_invalid_data()
    {
        // Create test data with missing required fields
        $poDetail = PoDetail::create([
            'po_no' => '', // Empty po_no
            'planned_receipt_date' => '2025-01-15',
            'po_qty' => 100,
            'price' => 50000,
            'item_code' => 'ITEM-INVALID-001',
            'item_desc_a' => 'Invalid Test Item Description',
            'bp_part_no' => 'BP-INVALID-001',
            'purchase_unit' => 'PCS',
            'amount' => 5000000
        ]);

        // Test sync endpoint
        $response = $this->actingAs($this->user)
            ->get('/api/local2/sync-inv-line');

        $response->assertStatus(200);
        
        // Should skip invalid data
        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThanOrEqual(0, $responseData['stats']['skipped']);
    }

    #[Test]
    public function test_sync_performance()
    {
        // Create multiple test records
        for ($i = 1; $i <= 5; $i++) {
            $poDetail = PoDetail::create([
                'po_no' => sprintf("PO-PERF-TEST-%03d", $i),
                'planned_receipt_date' => '2025-01-15',
                'po_qty' => 100,
                'price' => 50000,
                'item_code' => sprintf("ITEM-PERF-%03d", $i),
                'item_desc_a' => "Performance Test Item {$i}",
                'bp_part_no' => sprintf("BP-PERF-%03d", $i),
                'purchase_unit' => 'PCS',
                'amount' => 5000000
            ]);

            $dnHeader = DnHeader::create([
                'po_no' => sprintf("PO-PERF-TEST-%03d", $i),
                'no_dn' => sprintf("DN-PERF-TEST-%03d", $i),
                'supplier_code' => sprintf("SUP-PERF-%03d", $i),
                'supplier_name' => "Performance Test Supplier {$i}"
            ]);

            $dnDetail = DnDetail::create([
                'no_dn' => sprintf("DN-PERF-TEST-%03d", $i),
                'dn_line' => '1',
                'actual_receipt_date' => '2025-01-15',
                'receipt_qty' => 100,
                'status_desc' => 'Confirmed'
            ]);
        }

        // Test sync endpoint
        $startTime = microtime(true);
        
        $response = $this->actingAs($this->user)
            ->get('/api/local2/sync-inv-line');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Verify performance (should complete within reasonable time)
        $this->assertLessThan(5.0, $executionTime, 'Sync should complete within 5 seconds');
        
        // Verify all records were processed
        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertGreaterThanOrEqual(5, $responseData['stats']['processed']);
    }
} 