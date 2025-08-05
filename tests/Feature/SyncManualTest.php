<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\InvLine;
use App\Services\DataSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncManualTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $dataSyncService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->superAdmin()->create();
        
        $this->dataSyncService = new DataSyncService();
    }

    #[Test]
    public function test_sync_service_with_mock_data()
    {
        // Test dengan data mock tanpa database eksternal
        $mockErpData = (object) [
            'po_no' => 'PO-MOCK-TEST-001',
            'gr_no' => 'GR-MOCK-TEST-001',
            'bp_id' => 'BP-MOCK-001',
            'bp_name' => 'Mock Business Partner',
            'currency' => 'IDR',
            'po_type' => 'Standard',
            'po_reference' => 'REF-MOCK-001',
            'po_line' => 1,
            'po_sequence' => 1,
            'po_receipt_sequence' => 1,
            'actual_receipt_date' => '2025-01-15',
            'actual_receipt_year' => 2025,
            'actual_receipt_period' => 1,
            'receipt_no' => 'REC-MOCK-TEST-001',
            'receipt_line' => '1',
            'packing_slip' => 'PS-MOCK-001',
            'item_no' => 'ITEM-MOCK-001',
            'ics_code' => 'ICS-MOCK-001',
            'ics_part' => 'ICS-PART-MOCK-001',
            'part_no' => 'PART-MOCK-001',
            'item_desc' => 'Mock Item Description',
            'item_group' => 'Group Mock',
            'item_type' => 'Type Mock',
            'item_type_desc' => 'Type Mock Description',
            'request_qty' => 100,
            'actual_receipt_qty' => 100,
            'approve_qty' => 100,
            'unit' => 'PCS',
            'receipt_amount' => 5000000,
            'receipt_unit_price' => 50000,
            'is_final_receipt' => true,
            'is_confirmed' => true,
            'inv_doc_no' => null,
            'inv_doc_date' => null,
            'inv_qty' => 0,
            'inv_amount' => 0,
            'inv_supplier_no' => null,
            'inv_due_date' => null,
            'payment_doc' => null,
            'payment_doc_date' => null
        ];

        // Test data cleaning
        $cleanedData = $this->dataSyncService->cleanErpData($mockErpData);
        
        $this->assertEquals('PO-MOCK-TEST-001', $cleanedData['po_no']);
        $this->assertEquals('GR-MOCK-TEST-001', $cleanedData['gr_no']);
        $this->assertEquals('BP-MOCK-001', $cleanedData['bp_id']);
        $this->assertEquals(100, $cleanedData['request_qty']);
        $this->assertEquals(5000000, $cleanedData['receipt_amount']);
        $this->assertTrue($cleanedData['is_final_receipt']);
        $this->assertTrue($cleanedData['is_confirmed']);

        // Test validation
        $this->assertTrue($this->dataSyncService->validateRequiredFields($cleanedData, ['po_no', 'gr_no']));

        // Test unique key creation
        $uniqueKey = $this->dataSyncService->createUniqueKey($cleanedData, 'po_gr');
        $this->assertEquals([
            'po_no' => 'PO-MOCK-TEST-001',
            'gr_no' => 'GR-MOCK-TEST-001'
        ], $uniqueKey);

        // Test sync record
        $result = $this->dataSyncService->syncRecord($mockErpData, 'po_gr');
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('created', $result['action']);

        // Verify record was created in database
        $this->assertDatabaseHas('inv_line', [
            'po_no' => 'PO-MOCK-TEST-001',
            'gr_no' => 'GR-MOCK-TEST-001'
        ]);
    }

    #[Test]
    public function test_sync_service_error_handling()
    {
        // Test dengan data yang tidak valid
        $invalidData = (object) [
            'po_no' => '', // Empty required field
            'gr_no' => null, // Null required field
            'bp_id' => 'BP-ERROR-001'
        ];

        $result = $this->dataSyncService->syncRecord($invalidData, 'po_gr');
        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('missing_required_fields', $result['reason']);

        // Test dengan data yang malformed
        $malformedData = (object) [
            'po_no' => 'PO-MALFORMED-001',
            'gr_no' => 'GR-MALFORMED-001',
            'actual_receipt_date' => 'invalid-date', // Invalid date
            'request_qty' => 'not-a-number' // Invalid number
        ];

        $result2 = $this->dataSyncService->syncRecord($malformedData, 'po_gr');
        $this->assertEquals('success', $result2['status']);
        
        // Verify cleaned data
        $this->assertDatabaseHas('inv_line', [
            'po_no' => 'PO-MALFORMED-001',
            'gr_no' => 'GR-MALFORMED-001',
            'request_qty' => 0 // Should be cleaned to 0
        ]);
    }

    #[Test]
    public function test_sync_service_duplicate_handling()
    {
        // Create initial record
        $mockData1 = (object) [
            'po_no' => 'PO-DUP-TEST-001',
            'gr_no' => 'GR-DUP-TEST-001',
            'bp_id' => 'BP-DUP-001',
            'bp_name' => 'Duplicate Test Business Partner',
            'receipt_no' => 'REC-DUP-TEST-001',
            'receipt_line' => '1',
            'item_desc' => 'Duplicate Test Item'
        ];

        // Sync first record
        $result1 = $this->dataSyncService->syncRecord($mockData1, 'po_gr');
        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('created', $result1['action']);

        // Try to sync same data again (should update)
        $result2 = $this->dataSyncService->syncRecord($mockData1, 'po_gr');
        $this->assertEquals('success', $result2['status']);
        $this->assertEquals('updated', $result2['action']);

        // Verify only one record exists
        $this->assertDatabaseCount('inv_line', 1);
        $this->assertDatabaseHas('inv_line', [
            'po_no' => 'PO-DUP-TEST-001',
            'gr_no' => 'GR-DUP-TEST-001'
        ]);
    }

    #[Test]
    public function test_sync_service_stats()
    {
        $results = [
            ['status' => 'success', 'action' => 'created'],
            ['status' => 'success', 'action' => 'updated'],
            ['status' => 'skipped', 'reason' => 'missing_required_fields'],
            ['status' => 'error', 'reason' => 'database_error']
        ];

        $stats = $this->dataSyncService->getSyncStats($results);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['success']);
        $this->assertEquals(1, $stats['skipped']);
        $this->assertEquals(1, $stats['error']);
    }

    #[Test]
    public function test_sync_service_batch_processing()
    {
        // Test processing multiple records
        $mockRecords = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $mockRecords[] = (object) [
                'po_no' => sprintf("PO-BATCH-TEST-%03d", $i),
                'gr_no' => sprintf("GR-BATCH-TEST-%03d", $i),
                'bp_id' => sprintf("BP-BATCH-%03d", $i),
                'bp_name' => "Batch Test Business Partner {$i}",
                'receipt_no' => sprintf("REC-BATCH-TEST-%03d", $i),
                'receipt_line' => '1',
                'item_desc' => "Batch Test Item {$i}",
                'request_qty' => 100,
                'actual_receipt_qty' => 100,
                'approve_qty' => 100,
                'unit' => 'PCS',
                'receipt_amount' => 5000000,
                'receipt_unit_price' => 50000,
                'is_final_receipt' => true,
                'is_confirmed' => true
            ];
        }

        $results = [];
        foreach ($mockRecords as $record) {
            $results[] = $this->dataSyncService->syncRecord($record, 'po_gr');
        }

        // Verify all records were processed successfully
        $successCount = 0;
        foreach ($results as $result) {
            if ($result['status'] === 'success') {
                $successCount++;
            }
        }

        $this->assertEquals(5, $successCount);
        $this->assertDatabaseCount('inv_line', 5);

        // Verify specific records
        for ($i = 1; $i <= 5; $i++) {
            $this->assertDatabaseHas('inv_line', [
                'po_no' => sprintf("PO-BATCH-TEST-%03d", $i),
                'gr_no' => sprintf("GR-BATCH-TEST-%03d", $i)
            ]);
        }
    }
} 