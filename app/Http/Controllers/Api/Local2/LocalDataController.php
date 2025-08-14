<?php

namespace App\Http\Controllers\Api\Local2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvLine;
use App\Services\DataSyncService;
use App\Models\Local\PoDetail;
use App\Models\Local\DnHeader;
use App\Models\Local\DnDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LocalDataController extends Controller
{
    public function syncInvLine()
    {
        Log::info('Starting LocalDataController sync process');
        
        try {
            // Test database connections first
            $this->testDatabaseConnections();
            
            DB::beginTransaction();
            
            $poDetails = PoDetail::whereNotNull('po_no')
                ->where('po_no', '!=', '')
                ->get();
            Log::info("Found {$poDetails->count()} PO details to process");
            $syncService = new DataSyncService();
            $batchData = [];
            foreach ($poDetails as $poDetail) {
                $dnHeader = DnHeader::where('po_no', $poDetail->po_no)
                    ->whereNotNull('no_dn')
                    ->where('no_dn', '!=', '')
                    ->first();
                if (!$dnHeader) continue;
                $dnDetails = DnDetail::where('no_dn', $dnHeader->no_dn)
                    ->whereNotNull('no_dn')
                    ->where('no_dn', '!=', '')
                    ->get();
                if ($dnDetails->isEmpty()) continue;
                foreach ($dnDetails as $dnDetail) {
                    $batchData[] = (object) [
                        'po_no' => $poDetail->po_no,
                        'receipt_no' => $dnDetail->no_dn,
                        'receipt_line' => $dnDetail->dn_line,
                        'bp_id' => $dnHeader->supplier_code ?? null,
                        'bp_name' => $dnHeader->supplier_name ?? null,
                        'po_date' => $poDetail->planned_receipt_date ?? null,
                        'po_qty' => $poDetail->po_qty ?? 0,
                        'po_price' => $poDetail->price ?? 0,
                        'currency' => null,
                        'rate' => null,
                        'receipt_date' => $dnDetail->actual_receipt_date ?? null,
                        'item' => $poDetail->item_code ?? null,
                        'item_desc' => $poDetail->item_desc_a ?? null,
                        'old_partno' => $poDetail->bp_part_no ?? null,
                        'receipt_qty' => $dnDetail->receipt_qty ?? 0,
                        'receipt_unit' => $poDetail->purchase_unit ?? null,
                        'packing_slip' => null,
                        'receipt_status' => $dnDetail->status_desc ?? null,
                        'warehouse' => null,
                        'extend_price' => $poDetail->amount ?? 0,
                        'extend_price_idr' => null,
                        'supplier_invoice' => null,
                        'supplier_invoice_date' => null,
                        'inv_doc' => null,
                        'inv_date' => null,
                        'doc_code' => null,
                        'doc_no' => null,
                        'doc_date' => null,
                    ];
                }
            }
            $results = $syncService->batchSync($batchData, 'po_receipt');
            DB::commit();
            $stats = $syncService->getSyncStats($results);
            Log::info("LocalDataController sync completed", $stats);
            return response()->json([
                'success' => true,
                'message' => 'Data synchronized successfully',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("LocalDataController sync failed: " . $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test database connections before starting sync
     */
    private function testDatabaseConnections()
    {
        try {
            // Test main database connection (MySQL)
            DB::connection()->getPdo();
            Log::info('MySQL database connection successful');
        } catch (\Exception $e) {
            Log::error('MySQL database connection failed: ' . $e->getMessage());
            throw new \Exception('MySQL database connection failed: ' . $e->getMessage());
        }
        
        try {
            // Test SQL Server connection
            DB::connection('sqlsrv')->getPdo();
            Log::info('SQL Server database connection successful');
        } catch (\Exception $e) {
            Log::error('SQL Server database connection failed: ' . $e->getMessage());
            throw new \Exception('SQL Server database connection failed: ' . $e->getMessage());
        }
    }
}
