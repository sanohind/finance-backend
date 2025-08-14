<?php

namespace App\Services;

use App\Models\InvLine;
use App\Models\ERP\InvReceipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DataSyncService
{
    /**
     * Clean and validate ERP data before syncing
     */
    public function cleanErpData($data)
    {
        $cleaned = [];
        
        // Clean string fields
        $stringFields = [
            'po_no', 'bp_id', 'bp_name', 'currency', 'po_type', 'po_reference',
            'receipt_no', 'receipt_line', 'gr_no', 'packing_slip', 'item_no',
            'ics_code', 'ics_part', 'part_no', 'item_desc', 'item_group',
            'item_type', 'item_type_desc', 'unit', 'inv_doc_no', 'inv_supplier_no',
            'payment_doc'
        ];
        
        foreach ($stringFields as $field) {
            $cleaned[$field] = $this->cleanString($data->$field ?? null);
        }
        
        // Clean numeric fields
        $numericFields = [
            'po_line', 'po_sequence', 'po_receipt_sequence', 'actual_receipt_year',
            'actual_receipt_period', 'request_qty', 'actual_receipt_qty', 'approve_qty',
            'receipt_amount', 'receipt_unit_price', 'inv_qty', 'inv_amount'
        ];
        
        foreach ($numericFields as $field) {
            $cleaned[$field] = $this->cleanNumeric($data->$field ?? null);
        }
        
        // Clean boolean fields
        $cleaned['is_final_receipt'] = $this->cleanBoolean($data->is_final_receipt ?? null);
        $cleaned['is_confirmed'] = $this->cleanBoolean($data->is_confirmed ?? null);
        
        // Clean date fields
        $dateFields = [
            'actual_receipt_date', 'inv_doc_date', 'inv_due_date', 'payment_doc_date'
        ];
        
        foreach ($dateFields as $field) {
            $cleaned[$field] = $this->cleanDate($data->$field ?? null);
        }
        
        return $cleaned;
    }
    
    /**
     * Clean string values
     */
    private function cleanString($value)
    {
        if (is_null($value)) {
            return null;
        }
        
        $cleaned = trim((string) $value);
        return empty($cleaned) ? null : $cleaned;
    }
    
    /**
     * Clean numeric values
     */
    private function cleanNumeric($value)
    {
        if (is_null($value)) {
            return 0;
        }
        
        $cleaned = (float) $value;
        return is_nan($cleaned) ? 0 : $cleaned;
    }
    
    /**
     * Clean boolean values
     */
    private function cleanBoolean($value)
    {
        if (is_null($value)) {
            return false;
        }
        
        if (is_bool($value)) {
            return $value;
        }
        
        $stringValue = strtolower(trim((string) $value));
        return in_array($stringValue, ['true', '1', 'yes', 'y', 'on']);
    }
    
    /**
     * Clean date values
     */
    private function cleanDate($value)
    {
        if (is_null($value)) {
            return null;
        }
        
        try {
            if ($value instanceof \Carbon\Carbon) {
                return $value;
            }
            
            if (is_string($value)) {
                return \Carbon\Carbon::parse($value);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning("Invalid date value: {$value}");
            return null;
        }
    }
    
    /**
     * Validate required fields for sync
     */
    public function validateRequiredFields($data, $requiredFields = ['po_no', 'gr_no'])
    {
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Create unique key for InvLine records
     */
    public function createUniqueKey($data, $keyType = 'po_gr')
    {
        // Kunci unik baru: po_no, gr_no, receipt_no, receipt_line, item_no
        $key = [
            'po_no' => $data['po_no'] ?? null,
            'gr_no' => $data['gr_no'] ?? null,
            'receipt_no' => $data['receipt_no'] ?? null,
            'receipt_line' => $data['receipt_line'] ?? null,
            'item_no' => $data['item_no'] ?? null
        ];
        // Hapus key yang null agar tetap backward compatible jika field tidak ada
        return array_filter($key, function($v) { return !is_null($v); });
    }
    

    /**
     * Sync a batch of ERP data records with validation and upsert.
     * @param iterable $erpDataList
     * @param string $keyType
     * @return array
     */
    public function batchSync(iterable $erpDataList, $keyType = 'po_gr')
    {
        $results = [];
        foreach ($erpDataList as $erpData) {
            $results[] = $this->syncRecord($erpData, $keyType);
        }
        return $results;
    }

    /**
     * Sync single record with proper validation and upsert
     */
    public function syncRecord($erpData, $keyType = 'po_gr')
    {
        $cleanedData = $this->cleanErpData($erpData);
        $requiredFields = $keyType === 'po_gr' ? ['po_no', 'gr_no'] : ['po_no', 'receipt_no', 'receipt_line'];
        if (!$this->validateRequiredFields($cleanedData, $requiredFields)) {
            Log::warning("[SYNC] Skipped: missing required fields", $cleanedData);
            return ['status' => 'skipped', 'reason' => 'missing_required_fields', 'data' => $cleanedData];
        }
        $uniqueKey = $this->createUniqueKey($cleanedData, $keyType);
        try {
            $result = InvLine::updateOrCreate($uniqueKey, $cleanedData);
            $action = $result->wasRecentlyCreated ? 'created' : 'updated';
            Log::info("[SYNC] $action", $uniqueKey);
            return ['status' => 'success', 'action' => $action, 'data' => $uniqueKey];
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') { // Duplicate entry error
                Log::warning("[SYNC] Duplicate skipped", $uniqueKey);
                return ['status' => 'skipped', 'reason' => 'duplicate', 'data' => $uniqueKey];
            }
            Log::error("[SYNC] DB error: " . $e->getMessage(), $uniqueKey);
            return ['status' => 'error', 'reason' => $e->getMessage(), 'data' => $uniqueKey];
        } catch (\Exception $e) {
            Log::error("[SYNC] Error: " . $e->getMessage(), $uniqueKey);
            return ['status' => 'error', 'reason' => $e->getMessage(), 'data' => $uniqueKey];
        }
    }
    
    /**
     * Get sync statistics from batch results
     */
    public function getSyncStats($results)
    {
        $stats = [
            'total' => count($results),
            'success' => 0,
            'skipped' => 0,
            'error' => 0,
            'created' => 0,
            'updated' => 0,
            'duplicate' => 0
        ];
        foreach ($results as $result) {
            switch ($result['status']) {
                case 'success':
                    $stats['success']++;
                    if (($result['action'] ?? '') === 'created') $stats['created']++;
                    if (($result['action'] ?? '') === 'updated') $stats['updated']++;
                    break;
                case 'skipped':
                    $stats['skipped']++;
                    if (($result['reason'] ?? '') === 'duplicate') $stats['duplicate']++;
                    break;
                case 'error':
                    $stats['error']++;
                    break;
            }
        }
        return $stats;
    }
} 