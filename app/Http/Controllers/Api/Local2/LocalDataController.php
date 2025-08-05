<?php

namespace App\Http\Controllers\Api\Local2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvLine;
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
            
            $processedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            
            // Fetch data from po_detail with validation
            $poDetails = PoDetail::whereNotNull('po_no')
                ->where('po_no', '!=', '')
                ->get();
            
            Log::info("Found {$poDetails->count()} PO details to process");
            
            foreach ($poDetails as $poDetail) {
                try {
                    // Validate required fields
                    if (empty($poDetail->po_no)) {
                        Log::warning("Skipping PO detail with empty po_no");
                        $skippedCount++;
                        continue;
                    }
                    
                    // Fetch related dn_header with validation
                    $dnHeader = DnHeader::where('po_no', $poDetail->po_no)
                        ->whereNotNull('no_dn')
                        ->where('no_dn', '!=', '')
                        ->first();
                    
                    if (!$dnHeader) {
                        Log::debug("No DN header found for PO: {$poDetail->po_no}");
                        $skippedCount++;
                        continue;
                    }
                    
                    // Fetch related dn_detail with validation
                    $dnDetails = DnDetail::where('no_dn', $dnHeader->no_dn)
                        ->whereNotNull('no_dn')
                        ->where('no_dn', '!=', '')
                        ->get();
                    
                    if ($dnDetails->isEmpty()) {
                        Log::debug("No DN details found for DN: {$dnHeader->no_dn}");
                        $skippedCount++;
                        continue;
                    }
                    
                    foreach ($dnDetails as $dnDetail) {
                        try {
                            // Validate DN detail required fields
                            if (empty($dnDetail->no_dn) || empty($dnDetail->dn_line)) {
                                Log::warning("Skipping DN detail with missing required fields: no_dn={$dnDetail->no_dn}, dn_line={$dnDetail->dn_line}");
                                $skippedCount++;
                                continue;
                            }
                            
                            // Create unique key combination to prevent duplicates
                            $uniqueKey = [
                                'po_no' => $poDetail->po_no,
                                'receipt_no' => $dnDetail->no_dn,
                                'receipt_line' => $dnDetail->dn_line
                            ];
                            
                            // Check if record already exists
                            $existingRecord = InvLine::where($uniqueKey)->first();
                            
                            if ($existingRecord) {
                                Log::debug("Record already exists, updating: po_no={$poDetail->po_no}, receipt_no={$dnDetail->no_dn}");
                            }
                            
                            // Update or create with proper data mapping
                            InvLine::updateOrCreate(
                                $uniqueKey,
                                [
                                    'po_no' => $poDetail->po_no,
                                    'bp_id' => $dnHeader->supplier_code ?? null,
                                    'bp_name' => $dnHeader->supplier_name ?? null,
                                    'po_date' => $poDetail->planned_receipt_date ?? null,
                                    'po_qty' => $poDetail->po_qty ?? 0,
                                    'po_price' => $poDetail->price ?? 0,
                                    'currency' => null,
                                    'rate' => null,
                                    'receipt_no' => $dnDetail->no_dn,
                                    'receipt_date' => $dnDetail->actual_receipt_date ?? null,
                                    'receipt_line' => $dnDetail->dn_line,
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
                                    // Explicitly set these columns to null:
                                    'supplier_invoice' => null,
                                    'supplier_invoice_date' => null,
                                    'inv_doc' => null,
                                    'inv_date' => null,
                                    'doc_code' => null,
                                    'doc_no' => null,
                                    'doc_date' => null,
                                ]
                            );
                            
                            $processedCount++;
                            
                        } catch (\Exception $e) {
                            Log::error("Error processing DN detail: " . $e->getMessage(), [
                                'po_no' => $poDetail->po_no,
                                'dn_no' => $dnDetail->no_dn ?? 'N/A',
                                'error' => $e->getMessage()
                            ]);
                            $errorCount++;
                        }
                    }
                    
                } catch (\Exception $e) {
                    Log::error("Error processing PO detail: " . $e->getMessage(), [
                        'po_no' => $poDetail->po_no ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                    $errorCount++;
                }
            }
            
            DB::commit();
            
            Log::info("LocalDataController sync completed", [
                'processed' => $processedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Data synchronized successfully',
                'stats' => [
                    'processed' => $processedCount,
                    'skipped' => $skippedCount,
                    'errors' => $errorCount
                ]
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
