# Specific Invoice Issues Fix Guide

## 📊 Analisis Hasil Diagnose

Berdasarkan hasil diagnose untuk `SLAPMTI-2`, ditemukan masalah spesifik:

### ✅ **Yang Sudah Berfungsi:**
- **GR Tracking**: 3 items (covers 2 bp_ids: SLAPMTI, SLAPMTI-1)
- **Invoice Creation**: 2 items (covers 1 bp_id: SLAPMTI)
- **Invoice Report**: 1 header (covers 1 bp_code: SLAPMTI-1)

### ⚠️ **Masalah yang Ditemukan:**

1. **Inconsistent BP_CODE Coverage**:
   - GR Tracking: 2 bp_ids (SLAPMTI, SLAPMTI-1)
   - Invoice Creation: 1 bp_id (SLAPMTI)
   - Invoice Report: 1 bp_code (SLAPMTI-1)

2. **Invoice Report Issue**: Header tanpa inv_lines

3. **Missing Data**: SLAPMTI-2 tidak muncul di semua halaman

## 🛠️ Langkah-langkah Perbaikan

### 1. Diagnose Masalah Spesifik
```bash
# Diagnose masalah spesifik
php artisan invoice:diagnose SLAPMTI-2
```

### 2. Fix Masalah Spesifik
```bash
# Dry run untuk melihat apa yang akan diperbaiki
php artisan invoice:fix-specific SLAPMTI-2 --dry-run

# Apply fixes
php artisan invoice:fix-specific SLAPMTI-2
```

### 3. Verify Perbaikan
```bash
# Test ulang setelah fix
php artisan invoice:diagnose SLAPMTI-2
```

## 🔧 Masalah Spesifik dan Solusi

### Masalah 1: Inconsistent BP_CODE Coverage
**Gejala**: 
- GR Tracking: 2 bp_ids (SLAPMTI, SLAPMTI-1)
- Invoice Creation: 1 bp_id (SLAPMTI)
- Invoice Report: 1 bp_code (SLAPMTI-1)

**Penyebab**: 
- SLAPMTI-1 tidak muncul di Invoice Creation
- SLAPMTI tidak muncul di Invoice Report
- SLAPMTI-2 tidak muncul di semua halaman

**Solusi**:
```bash
# Check data untuk SLAPMTI-1
php artisan tinker
>>> $service = app(App\Services\BusinessPartnerUnifiedService::class);
>>> $unifiedBpCodes = $service->getUnifiedBpCodes('SLAPMTI-2');
>>> App\Models\InvLine::where('bp_id', 'SLAPMTI-1')
    ->whereNull('inv_supplier_no')
    ->whereNull('inv_due_date')
    ->count();
```

### Masalah 2: Invoice Report Header Tanpa Lines
**Gejala**: Header `INSA-25026-08` (BP_CODE: SLAPMTI-1) tidak memiliki inv_lines
**Penyebab**: Relationship antara header dan inv_lines tidak ter-set dengan benar
**Solusi**:
```bash
# Check header tanpa lines
php artisan tinker
>>> $header = App\Models\InvHeader::where('inv_no', 'INSA-25026-08')->first();
>>> $header->invLine->count();

# Check lines yang seharusnya terkait
>>> App\Models\InvLine::where('bp_id', 'SLAPMTI-1')
    ->where('inv_supplier_no', 'INSA-25026-08')
    ->count();
```

### Masalah 3: Missing SLAPMTI-2 Data
**Gejala**: SLAPMTI-2 tidak muncul di semua halaman
**Penyebab**: Tidak ada data untuk SLAPMTI-2 atau data tidak terintegrasi dengan benar
**Solusi**:
```bash
# Check data untuk SLAPMTI-2
php artisan tinker
>>> App\Models\InvLine::where('bp_id', 'SLAPMTI-2')->count();
>>> App\Models\InvHeader::where('bp_code', 'SLAPMTI-2')->count();
```

## 🧪 Testing Manual

### Test 1: Check Data Distribution
```sql
-- Check data distribution untuk semua bp_codes terkait
SELECT 
    bp_id,
    COUNT(*) as total_items,
    SUM(CASE WHEN inv_supplier_no IS NULL AND inv_due_date IS NULL THEN 1 ELSE 0 END) as uninvoiced_items,
    SUM(CASE WHEN inv_supplier_no IS NOT NULL OR inv_due_date IS NOT NULL THEN 1 ELSE 0 END) as invoiced_items
FROM inv_line 
WHERE bp_id IN ('SLAPMTI', 'SLAPMTI-1', 'SLAPMTI-2')
GROUP BY bp_id
ORDER BY bp_id;
```

### Test 2: Check Invoice Headers
```sql
-- Check invoice headers untuk semua bp_codes terkait
SELECT 
    bp_code,
    inv_no,
    status,
    total_amount,
    created_at
FROM inv_header 
WHERE bp_code IN ('SLAPMTI', 'SLAPMTI-1', 'SLAPMTI-2')
ORDER BY bp_code, created_at DESC;
```

### Test 3: Check Relationships
```sql
-- Check relationships antara header dan lines
SELECT 
    h.bp_code,
    h.inv_no,
    COUNT(t.inv_line_id) as line_count
FROM inv_header h
LEFT JOIN transaction_invoice t ON h.inv_id = t.inv_id
WHERE h.bp_code IN ('SLAPMTI', 'SLAPMTI-1', 'SLAPMTI-2')
GROUP BY h.bp_code, h.inv_no
ORDER BY h.bp_code, h.inv_no;
```

## 📊 Expected Results Setelah Fix

### Sebelum Fix:
- **GR Tracking**: 3 items (SLAPMTI: 2, SLAPMTI-1: 1)
- **Invoice Creation**: 2 items (SLAPMTI: 2)
- **Invoice Report**: 1 header (SLAPMTI-1: 1) - tanpa lines

### Setelah Fix:
- **GR Tracking**: 3 items (SLAPMTI: 2, SLAPMTI-1: 1)
- **Invoice Creation**: 2 items (SLAPMTI: 2) - konsisten
- **Invoice Report**: 1 header (SLAPMTI-1: 1) - dengan lines

### Jika Ada Data SLAPMTI-2:
- **GR Tracking**: 3+ items (termasuk SLAPMTI-2)
- **Invoice Creation**: 2+ items (termasuk SLAPMTI-2 jika uninvoiced)
- **Invoice Report**: 1+ headers (termasuk SLAPMTI-2 jika ada invoice)

## 🔍 Verification Checklist

### BP_CODE Coverage:
- [ ] GR Tracking menampilkan semua bp_ids terkait
- [ ] Invoice Creation menampilkan semua bp_ids dengan items uninvoiced
- [ ] Invoice Report menampilkan semua bp_codes dengan headers

### Data Consistency:
- [ ] Tidak ada orphaned data
- [ ] Relationships ter-set dengan benar
- [ ] Unified service berfungsi dengan baik

### Invoice Report:
- [ ] Semua headers memiliki inv_lines
- [ ] Data detail lengkap
- [ ] Status dan informasi ter-update

## 🚨 Common Issues dan Solusi

### Issue 1: "SLAPMTI-1 tidak muncul di Invoice Creation"
**Penyebab**: Semua items SLAPMTI-1 sudah di-invoice
**Solusi**: Check apakah items memang sudah di-invoice atau ada data inconsistency

### Issue 2: "SLAPMTI tidak muncul di Invoice Report"
**Penyebab**: Tidak ada invoice headers untuk SLAPMTI
**Solusi**: Check apakah memang belum ada invoice yang dibuat untuk SLAPMTI

### Issue 3: "Header tanpa inv_lines"
**Penyebab**: Relationship tidak ter-set dengan benar
**Solusi**: Jalankan fix command untuk memperbaiki relationships

## 📞 Support

Jika masalah masih berlanjut:

1. Jalankan diagnose: `php artisan invoice:diagnose SLAPMTI-2`
2. Jalankan fix-specific: `php artisan invoice:fix-specific SLAPMTI-2 --dry-run`
3. Review output dan apply fixes: `php artisan invoice:fix-specific SLAPMTI-2`
4. Verify hasil: `php artisan invoice:diagnose SLAPMTI-2`
5. Check manual dengan SQL queries di atas

---

**Note**: Pastikan untuk backup database sebelum menjalankan fix commands, terutama jika tidak menggunakan `--dry-run` option.
