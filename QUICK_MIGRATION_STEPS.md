# Quick Migration Steps - Decimal Conversion

## ⚡ Quick Reference

### 📋 Pre-Migration Checklist

```bash
# 1. Backup Database
mysqldump -u root -p finance_db > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Check current data
mysql -u root -p finance_db -e "
  SELECT COUNT(*) as total,
         MIN(actual_receipt_qty) as min_qty,
         MAX(actual_receipt_qty) as max_qty
  FROM inv_line;"

# 3. Test in development first!
php artisan migrate --env=local
```

---

## 🚀 Migration Commands

### Run Migration

```bash
# Production
php artisan migrate

# If asked: yes
```

### Verify Migration

```bash
# Check table structure
php artisan tinker
>>> DB::select("DESCRIBE inv_line");

# Expected output for qty columns:
# Type: decimal(15,4)
```

---

## 🔄 Re-sync Data from ERP

```bash
# Trigger data sync to get decimal values
curl -X POST http://localhost:8000/api/local2/copy-inv-lines \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## ✅ Quick Validation

```sql
-- 1. Check decimal data exists
SELECT 
    inv_line_id,
    item_no,
    actual_receipt_qty,
    approve_qty,
    receipt_amount
FROM inv_line
WHERE actual_receipt_qty <> FLOOR(actual_receipt_qty)
LIMIT 5;

-- 2. Check invoice 47/X/25-0945
SELECT 
    il.inv_line_id,
    il.actual_receipt_qty,
    il.approve_qty,
    il.receipt_amount,
    (il.actual_receipt_qty * il.receipt_unit_price) AS calculated
FROM inv_line il
JOIN transaction_invoice ti ON il.inv_line_id = ti.inv_line_id  
JOIN inv_header ih ON ti.inv_id = ih.inv_id
WHERE ih.inv_no = '47/X/25-0945';
```

---

## ⚠️ If Something Goes Wrong

### Rollback

```bash
# Rollback migration (⚠️ decimal data will be lost!)
php artisan migrate:rollback --step=1

# Restore from backup if needed
mysql -u root -p finance_db < backup_YYYYMMDD_HHMMSS.sql
```

---

## 📊 Expected Changes

### Before Migration

```
Column Type:        INT
Data Example:       0 (from 0.25)
Receipt Amount:     6250
Calculation:        0 × 25000 = 0 ❌
```

### After Migration

```
Column Type:        DECIMAL(15,4)
Data Example:       0.2500
Receipt Amount:     6250.00
Calculation:        0.25 × 25000 = 6250 ✅
```

---

## 🎯 Files Changed

1. ✅ **Migration:** `2025_11_11_071147_change_qty_columns_to_decimal_in_inv_line_table.php`
2. ✅ **Model:** `app/Models/InvLine.php` (added $casts)
3. ✅ **Controller:** `app/Http/Controllers/Api/Finance/FinanceInvHeaderController.php` (conditional logic)

---

## 📞 Support

If issues occur:
1. Check `storage/logs/laravel.log`
2. Verify database connection
3. Ensure MySQL supports DECIMAL type
4. Contact database admin if permission issues

---

**Status:** ⚠️ READY TO RUN  
**Estimated Time:** 5-10 minutes  
**Downtime:** Minimal (ALTER TABLE operation)
