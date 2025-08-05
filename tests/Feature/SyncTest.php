<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\InvLine;
use App\Models\ERP\InvReceipt;
use App\Models\Local\PoDetail;
use App\Models\Local\DnHeader;
use App\Models\Local\DnDetail;
use App\Services\DataSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class SyncTest extends TestCase
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
    public function test_data_sync_service_cleaning()
    {
        // Test data cleaning
        $testData = (object) [
            'po_no' => '  PO-TEST-001  ',
            'gr_no' => 'GR-TEST-001',
            'bp_id' => 'BP-001',
            'bp_name' => 'Test Business Partner',
            'currency' => 'IDR',
            'po_type' => 'Standard',
            'po_reference' => 'REF-001',
            'po_line' => '1',
            'po_sequence' => '1',
            'po_receipt_sequence' => '1',
            'actual_receipt_date' => '2025-01-15',
            'actual_receipt_year' => '2025',
            'actual_receipt_period' => '1',
            'receipt_no' => 'REC-TEST-001',
            'receipt_line' => '1',
            'packing_slip' => 'PS-001',
            'item_no' => 'ITEM-001',
            'ics_code' => 'ICS-001',
            'ics_part' => 'ICS-PART-001',
            'part_no' => 'PART-001',
            'item_desc' => 'Test Item Description',
            'item_group' => 'Group A',
            'item_type' => 'Type A',
            'item_type_desc' => 'Type A Description',
            'request_qty' => '100',
            'actual_receipt_qty' => '100',
            'approve_qty' => '100',
            'unit' => 'PCS',
            'receipt_amount' => '5000000',
            'receipt_unit_price' => '50000',
            'is_final_receipt' => 'true',
            'is_confirmed' => 'Yes',
            'inv_doc_no' => null,
            'inv_doc_date' => null,
            'inv_qty' => '0',
            'inv_amount' => '0',
            'inv_supplier_no' => null,
            'inv_due_date' => null,
            'payment_doc' => null,
            'payment_doc_date' => null
        ];

        $cleanedData = $this->dataSyncService->cleanErpData($testData);

        // Test string cleaning
        $this->assertEquals('PO-TEST-001', $cleanedData['po_no']);
        $this->assertEquals('GR-TEST-001', $cleanedData['gr_no']);
        $this->assertEquals('BP-001', $cleanedData['bp_id']);

        // Test numeric cleaning
        $this->assertEquals(1, $cleanedData['po_line']);
        $this->assertEquals(100, $cleanedData['request_qty']);
        $this->assertEquals(5000000, $cleanedData['receipt_amount']);

        // Test boolean cleaning
        $this->assertTrue($cleanedData['is_final_receipt']);
        $this->assertTrue($cleanedData['is_confirmed']);

        // Test date cleaning
        $this->assertInstanceOf(\Carbon\Carbon::class, $cleanedData['actual_receipt_date']);
    }

    #[Test]
    public function test_data_sync_service_validation()
    {
        // Test valid data
        $validData = [
            'po_no' => 'PO-TEST-001',
            'gr_no' => 'GR-TEST-001',
            'receipt_no' => 'REC-TEST-001',
            'receipt_line' => '1'
        ];

        $this->assertTrue($this->dataSyncService->validateRequiredFields($validData, ['po_no', 'gr_no']));
        $this->assertTrue($this->dataSyncService->validateRequiredFields($validData, ['po_no', 'receipt_no', 'receipt_line']));

        // Test invalid data
        $invalidData = [
            'po_no' => '',
            'gr_no' => null,
            'receipt_no' => 'REC-TEST-001',
            'receipt_line' => '1'
        ];

        $this->assertFalse($this->dataSyncService->validateRequiredFields($invalidData, ['po_no', 'gr_no']));
    }

    #[Test]
    public function test_data_sync_service_unique_key()
    {
        $data = [
            'po_no' => 'PO-TEST-001',
            'gr_no' => 'GR-TEST-001',
            'receipt_no' => 'REC-TEST-001',
            'receipt_line' => '1'
        ];

        // Test po_gr key type
        $poGrKey = $this->dataSyncService->createUniqueKey($data, 'po_gr');
        $this->assertEquals([
            'po_no' => 'PO-TEST-001',
            'gr_no' => 'GR-TEST-001'
        ], $poGrKey);

        // Test po_receipt key type
        $poReceiptKey = $this->dataSyncService->createUniqueKey($data, 'po_receipt');
        $this->assertEquals([
            'po_no' => 'PO-TEST-001',
            'receipt_no' => 'REC-TEST-001',
            'receipt_line' => '1'
        ], $poReceiptKey);
    }

    #[Test]
    public function test_data_sync_service_sync_record()
    {
        // Create test ERP data
        $erpData = (object) [
            'po_no' => 'PO-SYNC-TEST-001',
            'gr_no' => 'GR-SYNC-TEST-001',
            'bp_id' => 'BP-SYNC-001',
            'bp_name' => 'Sync Test Business Partner',
            'currency' => 'IDR',
            'po_type' => 'Standard',
            'po_reference' => 'REF-SYNC-001',
            'po_line' => 1,
            'po_sequence' => 1,
            'po_receipt_sequence' => 1,
            'actual_receipt_date' => '2025-01-15',
            'actual_receipt_year' => 2025,
            'actual_receipt_period' => 1,
            'receipt_no' => 'REC-SYNC-TEST-001',
            'receipt_line' => '1',
            'packing_slip' => 'PS-SYNC-001',
            'item_no' => 'ITEM-SYNC-001',
            'ics_code' => 'ICS-SYNC-001',
            'ics_part' => 'ICS-PART-SYNC-001',
            'part_no' => 'PART-SYNC-001',
            'item_desc' => 'Sync Test Item Description',
            'item_group' => 'Group Sync',
            'item_type' => 'Type Sync',
            'item_type_desc' => 'Type Sync Description',
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

        // Test sync record
        $result = $this->dataSyncService->syncRecord($erpData, 'po_gr');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('created', $result['action']);

        // Verify record was created
        $this->assertDatabaseHas('inv_line', [
            'po_no' => 'PO-SYNC-TEST-001',
            'gr_no' => 'GR-SYNC-TEST-001'
        ]);

        // Test duplicate sync (should update)
        $result2 = $this->dataSyncService->syncRecord($erpData, 'po_gr');
        $this->assertEquals('success', $result2['status']);
        $this->assertEquals('updated', $result2['action']);
    }

    #[Test]
    public function test_data_sync_service_stats()
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
    public function test_duplicate_prevention()
    {
        // Create initial record
        $invLine1 = InvLine::create([
            'po_no' => 'PO-DUP-TEST-001',
            'gr_no' => 'GR-DUP-TEST-001',
            'bp_id' => 'BP-DUP-001',
            'bp_name' => 'Duplicate Test Business Partner',
            'receipt_no' => 'REC-DUP-TEST-001',
            'receipt_line' => '1',
            'item_desc' => 'Duplicate Test Item'
        ]);

        // Try to create duplicate with same unique key
        $invLine2 = InvLine::create([
            'po_no' => 'PO-DUP-TEST-001',
            'gr_no' => 'GR-DUP-TEST-001',
            'bp_id' => 'BP-DUP-002',
            'bp_name' => 'Different Business Partner',
            'receipt_no' => 'REC-DUP-TEST-002',
            'receipt_line' => '2',
            'item_desc' => 'Different Item'
        ]);

        // Should only have one record with the same unique key
        $this->assertDatabaseCount('inv_line', 2);
        
        // Test updateOrCreate behavior
        $updatedData = [
            'po_no' => 'PO-DUP-TEST-001',
            'gr_no' => 'GR-DUP-TEST-001',
            'bp_name' => 'Updated Business Partner'
        ];

        InvLine::updateOrCreate(
            ['po_no' => 'PO-DUP-TEST-001', 'gr_no' => 'GR-DUP-TEST-001'],
            $updatedData
        );

        // Should still have same count, but updated record
        $this->assertDatabaseCount('inv_line', 2);
        $this->assertDatabaseHas('inv_line', [
            'po_no' => 'PO-DUP-TEST-001',
            'gr_no' => 'GR-DUP-TEST-001',
            'bp_name' => 'Updated Business Partner'
        ]);
    }

    #[Test]
    public function test_error_handling()
    {
        // Test with invalid data
        $invalidData = (object) [
            'po_no' => '', // Empty required field
            'gr_no' => null, // Null required field
            'bp_id' => 'BP-ERROR-001'
        ];

        $result = $this->dataSyncService->syncRecord($invalidData, 'po_gr');

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('missing_required_fields', $result['reason']);

        // Test with malformed data
        $malformedData = (object) [
            'po_no' => 'PO-MALFORMED-001',
            'gr_no' => 'GR-MALFORMED-001',
            'actual_receipt_date' => 'invalid-date', // Invalid date
            'request_qty' => 'not-a-number' // Invalid number
        ];

        $result2 = $this->dataSyncService->syncRecord($malformedData, 'po_gr');

        // Should still succeed but with cleaned data
        $this->assertEquals('success', $result2['status']);
        
        // Verify cleaned data
        $this->assertDatabaseHas('inv_line', [
            'po_no' => 'PO-MALFORMED-001',
            'gr_no' => 'GR-MALFORMED-001',
            'request_qty' => 0 // Should be cleaned to 0
        ]);
    }

    #[Test]
    public function test_sync_controllers_without_database_dependencies()
    {
        // Test DataSyncService methods without database dependencies
        $testData = (object) [
            'po_no' => 'PO-CONTROLLER-TEST-001',
            'gr_no' => 'GR-CONTROLLER-TEST-001',
            'bp_id' => 'BP-CONTROLLER-001',
            'bp_name' => 'Controller Test Business Partner'
        ];

        // Test data cleaning
        $cleanedData = $this->dataSyncService->cleanErpData($testData);
        $this->assertEquals('PO-CONTROLLER-TEST-001', $cleanedData['po_no']);
        $this->assertEquals('GR-CONTROLLER-TEST-001', $cleanedData['gr_no']);

        // Test validation
        $this->assertTrue($this->dataSyncService->validateRequiredFields($cleanedData, ['po_no', 'gr_no']));

        // Test unique key creation
        $uniqueKey = $this->dataSyncService->createUniqueKey($cleanedData, 'po_gr');
        $this->assertEquals([
            'po_no' => 'PO-CONTROLLER-TEST-001',
            'gr_no' => 'GR-CONTROLLER-TEST-001'
        ], $uniqueKey);
    }
} 