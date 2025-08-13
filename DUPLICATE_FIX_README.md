# Perbaikan Masalah Duplikasi Data Sync ERP

## Masalah yang Ditemukan

Berdasarkan data yang diberikan, terjadi duplikasi data pada saat sync dari database ERP ke sistem. Contoh duplikasi:

- `inv_line_id`: 44360 dan 44672 (data identik)
- `inv_line_id`: 44361 dan 44673 (data identik)

Kedua pasangan memiliki data yang sama kecuali beberapa field seperti `inv_supplier_no` dan `inv_due_date`.

## Penyebab Masalah

1. **Unique key yang tidak cukup**: Kode sebelumnya hanya menggunakan kombinasi `po_no`, `receipt_no`, dan `receipt_line` sebagai unique key
2. **Tidak ada validasi duplikasi di source**: Data dari ERP mungkin sudah memiliki duplikasi
3. **Tidak ada unique constraint di database**: Migration tidak mendefinisikan unique constraint

## Solusi yang Diterapkan

### 1. Perbaikan Logika Sync (`InvoiceReceiptController.php`)

#### Perubahan Utama:
- **Grouping data source**: Mengelompokkan data dari ERP berdasarkan kombinasi unik sebelum diproses
- **Unique key yang lebih komprehensif**: Menambahkan `item_no` ke dalam unique key
- **Deteksi duplikasi source**: Menghitung dan melaporkan duplikasi yang ditemukan di data source
- **Logging yang lebih detail**: Menambahkan log untuk tracking duplikasi

#### Kode Perbaikan:
```php
// Group data by unique combination to detect duplicates in source
$groupedData = $sqlsrvData->groupBy(function ($item) {
    return $item->po_no . '|' . $item->receipt_no . '|' . $item->receipt_line . '|' . $item->item_no;
});

// Process each unique combination
foreach ($groupedData as $uniqueKey => $records) {
    // Take the first record from each group (most recent)
    $data = $records->first();
    
    // Check if we have duplicates in source data
    if ($records->count() > 1) {
        Log::warning("Found {$records->count()} duplicate records in source for key: {$uniqueKey}");
        $duplicateCount += $records->count() - 1;
    }
    
    // Create unique key combination to prevent duplicates in target
    $targetUniqueKey = [
        'po_no' => $data->po_no,
        'receipt_no' => $data->receipt_no,
        'receipt_line' => $data->receipt_line,
        'item_no' => $data->item_no ?? null
    ];
    
    // Update or create with proper data validation
    InvLine::updateOrCreate($targetUniqueKey, [...]);
}
```

### 2. Tools untuk Manajemen Duplikasi

#### A. Check Duplicates (`/api/check-duplicates`)
- **Fungsi**: Mengecek dan melaporkan data duplikasi yang ada
- **Output**: Detail lengkap tentang duplikasi termasuk `inv_line_id`, `created_at`, dll
- **Gunakan**: Sebelum melakukan cleanup untuk analisis

#### B. Clean Duplicates (`/api/clean-duplicates`)
- **Fungsi**: Membersihkan data duplikasi yang sudah ada
- **Logika**: Menyimpan record terbaru (berdasarkan `created_at`) dan menghapus yang lain
- **Gunakan**: Setelah analisis untuk membersihkan data

#### C. Improved Sync (`/api/sync`)
- **Fungsi**: Sync data dengan pencegahan duplikasi
- **Fitur**: Deteksi duplikasi source, grouping, dan unique key yang lebih baik

## Cara Penggunaan

### 1. Cek Duplikasi yang Ada
```bash
GET /api/check-duplicates
```

### 2. Bersihkan Duplikasi (Opsional)
```bash
GET /api/clean-duplicates
```

### 3. Jalankan Sync yang Sudah Diperbaiki
```bash
GET /api/sync
```

## Monitoring dan Logging

### Log yang Ditambahkan:
- `Found X duplicate records in source for key: Y`
- `Grouped into X unique combinations`
- `Duplicate cleanup completed`
- `Duplicate check completed`

### Response yang Ditingkatkan:
```json
{
    "success": true,
    "message": "Data inv_line successfully copied",
    "stats": {
        "processed": 1000,
        "skipped": 5,
        "errors": 0,
        "duplicates_in_source": 25,
        "total_found": 1025,
        "unique_combinations": 1000
    }
}
```

## Pencegahan Duplikasi di Masa Depan

1. **Unique Key yang Komprehensif**: Menggunakan kombinasi `po_no`, `receipt_no`, `receipt_line`, dan `item_no`
2. **Grouping Data Source**: Mengelompokkan data sebelum diproses untuk menghindari duplikasi
3. **Logging Detail**: Tracking duplikasi untuk monitoring
4. **Tools Manajemen**: Endpoint untuk check dan clean duplikasi

## Testing

### Test Case 1: Data Normal
- Input: 1000 records tanpa duplikasi
- Expected: 1000 records processed, 0 duplicates

### Test Case 2: Data dengan Duplikasi Source
- Input: 1000 records dengan 50 duplikasi di source
- Expected: 950 records processed, 50 duplicates detected

### Test Case 3: Data dengan Duplikasi Target
- Input: Sync data yang sudah ada
- Expected: 0 new records, existing records updated

## Kesimpulan

Perbaikan ini mengatasi masalah duplikasi dengan:
1. **Pencegahan**: Unique key yang lebih baik dan grouping data
2. **Deteksi**: Tools untuk mengidentifikasi duplikasi
3. **Pembersihan**: Tools untuk membersihkan duplikasi yang ada
4. **Monitoring**: Logging dan reporting yang detail

Dengan perbaikan ini, masalah duplikasi data sync dari ERP ke sistem seharusnya sudah teratasi.
