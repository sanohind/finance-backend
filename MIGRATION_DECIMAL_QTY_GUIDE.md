# Migration Guide: Integer to Decimal for Quantity Fields

## 🎯 Tujuan

Mengubah tipe data kolom quantity dan amount dari `INTEGER` ke `DECIMAL` untuk mendukung nilai pecahan/desimal dari ERP system.

---

## 🔍 Problem Statement

### Masalah yang Ditemukan

**Tabel:** `inv_line`  
**Kolom Bermasalah:**
```sql
request_qty           INT      -- ❌ Tidak bisa simpan 0.25, 0.5, dll
actual_receipt_qty    INT      -- ❌ Data loss saat sync dari ERP
approve_qty           INT      -- ❌ Pecahan di-round ke integer
receipt_amount        INT      -- ❌ Kehilangan sen/desimal
receipt_unit_price    INT      -- ❌ Harga tidak akurat
inv_qty               INT      -- ❌ Tidak support fractional
inv_amount            INT      -- ❌ Kehilangan desimal
```

### Impact

1. **Data Loss dari ERP**
   ```
   ERP Data:  actual_receipt_qty = 0.25 box
   Laravel:   actual_receipt_qty = 0        ← Data loss!
   ```

2. **Perhitungan Tidak Akurat**
   ```
   Correct:   0.25 × 25,000 = 6,250
   Current:   0 × 25,000 = 0                ← Kehilangan Rp 6,250!
   ```

3. **Invoice 47/X/25-0945 Issue**
   ```
   Selisih Rp 5,250 disebabkan oleh:
   - Line 65726: 0.25 unit → 0 (loss 6,250)
   - Line 65728: 0.25 unit → 0 (loss 5,000)
   - Line 65731: 0.25 unit → 0 (loss 5,000)
   Total potential loss per invoice!
   ```

---

## 📋 Solusi: Migration ke DECIMAL

### File Migration

**File:** `2025_11_11_071147_change_qty_columns_to_decimal_in_inv_line_table.php`

### Perubahan Tipe Data

| Column | Before | After | Reason |
|--------|--------|-------|--------|
| `request_qty` | `INT` | `DECIMAL(15,4)` | Support fractional (0.25, 0.5, 0.75) |
| `actual_receipt_qty` | `INT` | `DECIMAL(15,4)` | Preserve ERP decimal data |
| `approve_qty` | `INT` | `DECIMAL(15,4)` | Support partial approval in decimal |
| `receipt_amount` | `INT` | `DECIMAL(15,2)` | Currency standard (2 decimal places) |
| `receipt_unit_price` | `INT` | `DECIMAL(15,2)` | Accurate pricing |
| `inv_qty` | `INT` | `DECIMAL(15,4)` | Support fractional invoice qty |
| `inv_amount` | `INT` | `DECIMAL(15,2)` | Currency precision |

### Decimal Format

**DECIMAL(15,4) untuk Quantity:**
```
Format: XXXXXXXXXXXXX.XXXX
Total:  15 digits
        11 digits sebelum desimal
        4 digits setelah desimal

Contoh:
  12345678901.2500  ✅ Valid
  0.2500            ✅ Valid (0.25 box)
  100.5000          ✅ Valid (100.5 unit)
  999999999999.9999 ✅ Valid (max)
```

**DECIMAL(15,2) untuk Amount:**
```
Format: XXXXXXXXXXXXX.XX
Total:  15 digits
        13 digits sebelum desimal
        2 digits setelah desimal

Contoh:
  1234567890123.45  ✅ Valid
  25000.50          ✅ Valid (Rp 25,000.50)
  0.25              ✅ Valid (Rp 0.25)
```

---

## 🚀 Cara Menjalankan Migration

### Pre-requisites

1. **Backup Database!** ⚠️
   ```bash
   # Manual backup
   mysqldump -u username -p database_name > backup_before_decimal_migration.sql
   
   # Atau via Laravel
   php artisan db:backup
   ```

2. **Check Current Data**
   ```sql
   -- Check jika ada data yang akan berubah
   SELECT 
       COUNT(*) as total_rows,
       COUNT(CASE WHEN request_qty != FLOOR(request_qty) THEN 1 END) as decimal_request,
       COUNT(CASE WHEN actual_receipt_qty != FLOOR(actual_receipt_qty) THEN 1 END) as decimal_actual
   FROM inv_line;
   ```

### Step 1: Run Migration

```bash
# Production/Staging
php artisan migrate

# Expected output:
# Running: 2025_11_11_071147_change_qty_columns_to_decimal_in_inv_line_table
# Migrated: 2025_11_11_071147_change_qty_columns_to_decimal_in_inv_line_table (XXX ms)
```

### Step 2: Verify Changes

```sql
-- Check tipe data setelah migration
DESCRIBE inv_line;

-- Expected untuk qty columns:
-- request_qty:        decimal(15,4)
-- actual_receipt_qty: decimal(15,4)
-- approve_qty:        decimal(15,4)
-- receipt_amount:     decimal(15,2)
-- receipt_unit_price: decimal(15,2)
```

### Step 3: Re-sync Data dari ERP

Karena data lama sudah ter-truncate/rounded, perlu re-sync:

```bash
# Trigger re-sync dari ERP
curl -X POST http://localhost:8000/api/local2/copy-inv-lines \
  -H "Authorization: Bearer YOUR_TOKEN"

# Atau via Laravel command jika ada
php artisan sync:inv-lines
```

### Step 4: Validation

```sql
-- Check data decimal setelah re-sync
SELECT 
    inv_line_id,
    item_no,
    request_qty,
    actual_receipt_qty,
    approve_qty,
    receipt_amount,
    receipt_unit_price
FROM inv_line
WHERE actual_receipt_qty != FLOOR(actual_receipt_qty)  -- Ada desimal
   OR approve_qty != FLOOR(approve_qty)
LIMIT 10;

-- Expected: Melihat data dengan desimal seperti 0.2500, 0.5000, dst
```

---

## ⚠️ Important Considerations

### 1. **Data Migration Impact**

**Existing Data:**
- Data integer lama tetap valid (10 → 10.0000)
- Tidak ada data loss untuk data yang sudah ada
- Tapi data yang sudah ter-round tidak bisa di-recover

**Contoh:**
```
Before Migration:
- ERP: 0.25 → Laravel: 0 (rounded)
- Stored in DB: 0

After Migration:
- Column type: DECIMAL(15,4)
- Existing data: 0.0000 (tidak bisa kembali ke 0.25)
- Perlu re-sync dari ERP untuk data akurat
```

### 2. **Performance Considerations**

**Storage:**
```
INT:          4 bytes per value
DECIMAL(15,4): 8 bytes per value
Increase:     4 bytes × 7 columns = 28 bytes per row

For 100,000 rows: +2.8 MB (negligible)
```

**Query Performance:**
- Minimal impact untuk table size < 1M rows
- Decimal arithmetic sedikit lebih lambat dari integer
- Trade-off worth it untuk data accuracy

### 3. **Application Code Impact**

**PHP/Laravel Code:**
```php
// Sebelum: Integer
$qty = $invLine->actual_receipt_qty;  // 0
$amount = $qty * $price;               // 0

// Sesudah: Decimal
$qty = $invLine->actual_receipt_qty;  // 0.25
$amount = $qty * $price;               // 6250.00
```

**No Code Changes Required!**
- PHP automatically handles decimal from MySQL
- Calculation tetap work
- Type casting otomatis

---

## 🔄 Rollback Plan

### Jika Ada Masalah

```bash
# Rollback migration
php artisan migrate:rollback --step=1

# ⚠️ WARNING: Data desimal akan hilang!
# Contoh: 0.25 → 0 (rounded down)
```

### Safe Rollback Procedure

1. **Backup current data with decimals:**
   ```sql
   CREATE TABLE inv_line_decimal_backup AS
   SELECT * FROM inv_line
   WHERE actual_receipt_qty != FLOOR(actual_receipt_qty)
      OR approve_qty != FLOOR(approve_qty);
   ```

2. **Execute rollback:**
   ```bash
   php artisan migrate:rollback --step=1
   ```

3. **If need to restore:**
   ```bash
   php artisan migrate
   # Then restore from backup
   ```

---

## ✅ Testing Checklist

### Pre-Migration Tests

- [ ] Backup database completed
- [ ] Document current data state
- [ ] Notify stakeholders about downtime
- [ ] Test migration in development environment
- [ ] Test migration in staging environment

### Post-Migration Tests

- [ ] Verify column types changed to DECIMAL
- [ ] Check existing data integrity (no data loss)
- [ ] Re-sync data from ERP
- [ ] Verify decimal values stored correctly
- [ ] Test invoice creation with decimal qty
- [ ] Run tax_base_amount calculation tests
- [ ] Verify API responses return decimal values
- [ ] Check frontend displays decimal properly

### Validation Queries

```sql
-- 1. Check column types
SHOW COLUMNS FROM inv_line LIKE '%qty%';
SHOW COLUMNS FROM inv_line LIKE '%amount%';

-- 2. Check decimal data
SELECT * FROM inv_line 
WHERE actual_receipt_qty LIKE '%.%'
LIMIT 10;

-- 3. Verify calculation accuracy
SELECT 
    inv_line_id,
    actual_receipt_qty,
    receipt_unit_price,
    receipt_amount,
    (actual_receipt_qty * receipt_unit_price) as calculated,
    ABS(receipt_amount - (actual_receipt_qty * receipt_unit_price)) as diff
FROM inv_line
HAVING diff > 0.01  -- Should be 0 or very small
LIMIT 10;

-- 4. Check invoice 47/X/25-0945 specifically
SELECT 
    il.inv_line_id,
    il.item_no,
    il.actual_receipt_qty,
    il.approve_qty,
    il.receipt_amount,
    il.receipt_unit_price
FROM inv_line il
JOIN transaction_invoice ti ON il.inv_line_id = ti.inv_line_id
JOIN inv_header ih ON ti.inv_id = ih.inv_id
WHERE ih.inv_no = '47/X/25-0945';
```

---

## 📊 Expected Results

### Before Migration

```sql
mysql> SELECT actual_receipt_qty, receipt_amount FROM inv_line WHERE inv_line_id = 65726;
+---------------------+----------------+
| actual_receipt_qty  | receipt_amount |
+---------------------+----------------+
|                   0 |           6250 |  -- Data loss!
+---------------------+----------------+
```

### After Migration + Re-sync

```sql
mysql> SELECT actual_receipt_qty, receipt_amount FROM inv_line WHERE inv_line_id = 65726;
+---------------------+----------------+
| actual_receipt_qty  | receipt_amount |
+---------------------+----------------+
|              0.2500 |        6250.00 |  -- Correct!
+---------------------+----------------+
```

---

## 🎯 Benefits

### 1. **Data Accuracy**
- ✅ No more data loss from ERP sync
- ✅ Preserve fractional quantities
- ✅ Accurate calculations

### 2. **Business Impact**
- ✅ Correct invoice amounts
- ✅ Match manual calculations
- ✅ Eliminate discrepancies (Rp 5,250 on invoice 47/X/25-0945)

### 3. **System Reliability**
- ✅ Single source of truth (ERP)
- ✅ Consistent data across systems
- ✅ Audit trail integrity

---

## 📝 Related Changes

After this migration, ensure:

1. **Update Model Casts (Optional but Recommended)**
   ```php
   // app/Models/InvLine.php
   protected $casts = [
       'request_qty' => 'decimal:4',
       'actual_receipt_qty' => 'decimal:4',
       'approve_qty' => 'decimal:4',
       'receipt_amount' => 'decimal:2',
       'receipt_unit_price' => 'decimal:2',
       'inv_qty' => 'decimal:4',
       'inv_amount' => 'decimal:2',
   ];
   ```

2. **Frontend Display**
   - Format decimal dengan benar (0.25 box, bukan 0.2500000)
   - Currency format untuk amount (Rp 6,250.00)

3. **API Response**
   - JSON akan return decimal as number
   - Pastikan frontend handle decimal

---

## 👥 Stakeholders to Notify

- [ ] Finance Team - Impact on invoice calculations
- [ ] Procurement Team - Fractional qty now supported
- [ ] IT Team - Database schema change
- [ ] Warehouse Team - Data accuracy improvement
- [ ] Frontend Developers - Display format changes

---

## 📅 Deployment Schedule

**Recommended:**
1. **Development:** Test migration ✅
2. **Staging:** Week 1 - Deploy & validate
3. **Production:** Week 2 - Deploy during low traffic
4. **Monitoring:** Week 2-3 - Watch for issues
5. **Sign-off:** Week 4 - Finance approval

---

**Created:** 2025-11-11  
**Migration File:** `2025_11_11_071147_change_qty_columns_to_decimal_in_inv_line_table.php`  
**Status:** ⚠️ **READY FOR TESTING**  
**Next Action:** Run in development/staging environment
