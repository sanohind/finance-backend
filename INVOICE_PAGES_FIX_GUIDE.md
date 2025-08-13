# Invoice Pages Integration Fix Guide

## 🎯 Masalah
Pada page **GR Tracking**, semua data sistem lama dan sistem baru sudah berfungsi, tetapi pada page **Invoice Creation** dan **Invoice Report** masih belum terintegrasi.

## 🔍 Analisis Masalah

### Kemungkinan Penyebab:
1. **Data Inconsistency**: Items yang seharusnya uninvoiced memiliki data invoice
2. **Orphaned Data**: Invoice data tanpa header yang valid
3. **Missing Relationships**: Header tanpa inv_lines yang terkait
4. **Unified Service Issues**: Query yang tidak konsisten antara halaman

## 🛠️ Langkah-langkah Perbaikan

### 1. Diagnose Masalah
```bash
# Diagnose Invoice Creation dan Invoice Report
php artisan invoice:diagnose SLAPMTI-2

# Diagnose dengan user tertentu
php artisan invoice:diagnose SLAPMTI-2 --user-id=1
```

### 2. Fix Masalah
```bash
# Dry run untuk melihat apa yang akan diperbaiki
php artisan invoice:fix SLAPMTI-2 --dry-run

# Apply fixes
php artisan invoice:fix SLAPMTI-2
```

### 3. Verify Perbaikan
```bash
# Test ulang setelah fix
php artisan invoice:diagnose SLAPMTI-2
```

## 🔧 Masalah Spesifik dan Solusi

### Masalah 1: Invoice Creation - Items Sudah Di-invoice
**Gejala**: Halaman Invoice Creation kosong padahal GR Tracking ada data
**Penyebab**: Items sudah memiliki `inv_supplier_no` dan `inv_due_date`
**Solusi**: 
```bash
# Check items dengan invoice data
php artisan tinker
>>> $service = app(App\Services\BusinessPartnerUnifiedService::class);
>>> $unifiedBpCodes = $service->getUnifiedBpCodes('SLAPMTI-2');
>>> App\Models\InvLine::whereIn('bp_id', $unifiedBpCodes)
    ->whereNotNull('inv_supplier_no')
    ->count();
```

### Masalah 2: Invoice Report - Header Tanpa Lines
**Gejala**: Halaman Invoice Report menampilkan header tanpa detail
**Penyebab**: Header tidak terhubung dengan inv_lines
**Solusi**:
```bash
# Check headers tanpa lines
php artisan tinker
>>> $service = app(App\Services\BusinessPartnerUnifiedService::class);
>>> $headers = $service->getUnifiedInvHeaders('SLAPMTI-2');
>>> $headers->filter(function($h) { return $h->invLine->isEmpty(); })->count();
```

### Masalah 3: Orphaned Invoice Data
**Gejala**: Items memiliki invoice data tapi tidak ada header yang valid
**Penyebab**: Data inconsistency antara sistem lama dan baru
**Solusi**:
```bash
# Check orphaned data
php artisan tinker
>>> $service = app(App\Services\BusinessPartnerUnifiedService::class);
>>> $unifiedBpCodes = $service->getUnifiedBpCodes('SLAPMTI-2');
>>> $lines = App\Models\InvLine::whereIn('bp_id', $unifiedBpCodes)
    ->whereNotNull('inv_supplier_no')
    ->get();
>>> $orphaned = $lines->filter(function($line) {
    return !App\Models\InvHeader::where('inv_no', $line->inv_supplier_no)->exists();
});
>>> $orphaned->count();
```

## 🧪 Testing Manual

### Test 1: Check Invoice Creation Data
```sql
-- Check uninvoiced items untuk bp_code tertentu
SELECT bp_id, po_no, receipt_no, inv_supplier_no, inv_due_date
FROM inv_line 
WHERE bp_id IN ('SLAPMTI', 'SLAPMTI-1', 'SLAPMTI-2')
  AND inv_supplier_no IS NULL 
  AND inv_due_date IS NULL
ORDER BY bp_id, actual_receipt_date DESC;
```

### Test 2: Check Invoice Report Data
```sql
-- Check invoice headers untuk bp_code tertentu
SELECT bp_code, inv_no, status, total_amount, created_at
FROM inv_header 
WHERE bp_code IN ('SLAPMTI', 'SLAPMTI-1', 'SLAPMTI-2')
ORDER BY created_at DESC;
```

### Test 3: Check Data Consistency
```sql
-- Compare data antara sistem lama dan baru
SELECT 
    'GR Tracking' as page,
    COUNT(*) as total_items,
    COUNT(DISTINCT bp_id) as unique_bp_ids
FROM inv_line 
WHERE bp_id IN ('SLAPMTI', 'SLAPMTI-1', 'SLAPMTI-2')

UNION ALL

SELECT 
    'Invoice Creation' as page,
    COUNT(*) as total_items,
    COUNT(DISTINCT bp_id) as unique_bp_ids
FROM inv_line 
WHERE bp_id IN ('SLAPMTI', 'SLAPMTI-1', 'SLAPMTI-2')
  AND inv_supplier_no IS NULL 
  AND inv_due_date IS NULL

UNION ALL

SELECT 
    'Invoice Report' as page,
    COUNT(*) as total_items,
    COUNT(DISTINCT bp_code) as unique_bp_codes
FROM inv_header 
WHERE bp_code IN ('SLAPMTI', 'SLAPMTI-1', 'SLAPMTI-2');
```

## 📊 Expected Results

Setelah perbaikan, Anda seharusnya melihat:

### Invoice Creation Page:
- Menampilkan semua items yang belum di-invoice
- Items dengan `inv_supplier_no` dan `inv_due_date` NULL
- Data dari semua bp_codes terkait (parent & child)

### Invoice Report Page:
- Menampilkan semua invoice headers
- Setiap header memiliki inv_lines yang terkait
- Data dari semua bp_codes terkait (parent & child)

### Data Consistency:
- GR Tracking: Semua items
- Invoice Creation: Items yang belum di-invoice
- Invoice Report: Items yang sudah di-invoice

## 🔍 Verification Checklist

### Invoice Creation:
- [ ] Menampilkan items yang belum di-invoice
- [ ] Tidak ada items dengan invoice data
- [ ] Data dari semua bp_codes terkait
- [ ] Filter berfungsi dengan baik

### Invoice Report:
- [ ] Menampilkan invoice headers
- [ ] Setiap header memiliki inv_lines
- [ ] Data dari semua bp_codes terkait
- [ ] Status dan detail lengkap

### Data Consistency:
- [ ] BP_CODE coverage konsisten
- [ ] Tidak ada orphaned data
- [ ] Relationships ter-set dengan benar
- [ ] Unified service berfungsi

## 🚨 Common Issues

### Issue 1: "Invoice Creation kosong"
**Penyebab**: Semua items sudah di-invoice
**Solusi**: Check apakah items memang sudah di-invoice atau ada data inconsistency

### Issue 2: "Invoice Report tidak menampilkan data"
**Penyebab**: Tidak ada invoice headers atau relationships rusak
**Solusi**: Check invoice headers dan relationships dengan inv_lines

### Issue 3: "Data tidak konsisten antara halaman"
**Penyebab**: Unified service tidak berfungsi dengan baik
**Solusi**: Check unified service dan parent-child relationships

## 📞 Support

Jika masalah masih berlanjut:

1. Jalankan diagnose command: `php artisan invoice:diagnose SLAPMTI-2`
2. Check output untuk masalah spesifik
3. Jalankan fix command: `php artisan invoice:fix SLAPMTI-2 --dry-run`
4. Review perubahan yang akan dilakukan
5. Apply fixes: `php artisan invoice:fix SLAPMTI-2`
6. Verify hasil: `php artisan invoice:diagnose SLAPMTI-2`

---

**Note**: Pastikan untuk backup database sebelum menjalankan fix commands, terutama jika tidak menggunakan `--dry-run` option.
