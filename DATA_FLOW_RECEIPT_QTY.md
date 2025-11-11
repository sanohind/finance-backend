# Data Flow: Actual Receipt Quantity

## 📊 Sumber Data `actual_receipt_qty`

### 1. **Origin: ERP System Sanoh (SQL Server)**

**Database:** SQL Server  
**Table:** `data_receipt_purchase`  
**Connection:** `sqlsrv` (configured in `config/database.php`)

```php
// Model: app/Models/ERP/InvReceipt.php
protected $connection = "sqlsrv";
protected $table = "data_receipt_purchase";
```

---

## 🔄 Proses Lengkap Data Flow

### Step 1: **Warehouse Receiving Process**
```
┌─────────────────────────────────────────────────────┐
│  PHYSICAL WAREHOUSE PROCESS                         │
└─────────────────────────────────────────────────────┘

1. Supplier mengirim barang berdasarkan PO
2. Warehouse staff menerima barang
3. Dilakukan physical count (penghitungan fisik)
4. Quality Control (QC) check
5. Staff input data ke sistem ERP

Input ke ERP:
├─ PO Number
├─ Item/Part Number  
├─ Packing Slip Number
├─ GR Number (Goods Receipt)
└─ ACTUAL RECEIPT QTY ← Qty yang benar-benar diterima
```

**Contoh:**
```
PO request: 100 unit
Supplier kirim: 105 unit (over-delivery 5 unit)
Warehouse terima & count: 105 unit
Input ke ERP: actual_receipt_qty = 105
```

---

### Step 2: **Data di ERP System**

**Tabel SQL Server:** `data_receipt_purchase`

Fields yang relevan:
```sql
po_no                  -- Nomor Purchase Order
receipt_no             -- Nomor penerimaan barang
item_no                -- Kode item/part
request_qty            -- Qty yang diminta di PO (planning)
actual_receipt_qty     -- Qty yang BENAR-BENAR diterima warehouse ★
approve_qty            -- Qty yang disetujui untuk pembayaran
receipt_amount         -- Nilai total penerimaan
receipt_unit_price     -- Harga per unit
actual_receipt_date    -- Tanggal penerimaan
```

**Workflow di ERP:**
```
request_qty (PO)
    ↓
actual_receipt_qty (Warehouse receives)
    ↓
approve_qty (QC/Finance approves)
    ↓
receipt_amount (Calculated for payment)
```

---

### Step 3: **Sync ke Laravel (MySQL)**

**Controller:** `InvoiceReceiptController.php`  
**Method:** `copyInvLines()`

```php
// Line 263
'actual_receipt_qty' => $data->actual_receipt_qty ?? 0,
```

**Proses Sync:**
```
SQL Server (ERP)                    MySQL (Laravel)
data_receipt_purchase          →    inv_line
├─ actual_receipt_qty          →    ├─ actual_receipt_qty
├─ approve_qty                 →    ├─ approve_qty
├─ receipt_amount              →    ├─ receipt_amount
└─ receipt_unit_price          →    └─ receipt_unit_price
```

**Endpoint untuk trigger sync:**
```
POST /api/local2/copy-inv-lines
```

**Frekuensi:** 
- Manual trigger atau scheduled job
- Biasanya daily/periodic sync

---

## 📋 Perbedaan 3 Qty Fields

### 1️⃣ `request_qty` (Planning Stage)
**Source:** Purchase Order (PO)  
**Input by:** Procurement Team  
**Timing:** Sebelum barang dikirim  
**Purpose:** Perencanaan pembelian

**Contoh:**
```
Procurement butuh: 100 unit
PO dibuat dengan request_qty = 100
```

---

### 2️⃣ `actual_receipt_qty` (Receiving Stage) ★
**Source:** Warehouse Physical Count  
**Input by:** Warehouse Staff  
**Timing:** Saat barang diterima  
**Purpose:** Record penerimaan fisik

**Contoh:**
```
Warehouse terima & hitung: 105 unit (over-delivery)
Input: actual_receipt_qty = 105

ATAU

Warehouse terima & hitung: 0.25 box PAKU
Input: actual_receipt_qty = 0.25 (bisa decimal!)
```

**Karakteristik:**
- ✅ Data FAKTUAL dari lapangan
- ✅ Bisa berbeda dari request (over/under delivery)
- ✅ Bisa dalam decimal (pecahan unit)
- ✅ Basis perhitungan `receipt_amount`

---

### 3️⃣ `approve_qty` (Approval Stage)
**Source:** QC/Finance Approval  
**Input by:** QC Team atau Finance  
**Timing:** Setelah inspeksi kualitas  
**Purpose:** Qty yang disetujui untuk dibayar

**Contoh:**
```
Actual receipt: 105 unit
QC check: 5 unit cacat/reject
Approve: 100 unit
Input: approve_qty = 100

ATAU

Actual receipt: 105 unit (over PO 5 unit)
Finance reject over-delivery: 5 unit
Approve: 100 unit (sesuai PO)
Input: approve_qty = 100
```

**Karakteristik:**
- ✅ Bisa lebih kecil dari actual (ada reject)
- ✅ Basis pembayaran ke supplier
- ✅ Biasanya rounded/integer (jarang decimal)

---

## 🔍 Hubungan dengan `receipt_amount`

### Formula di ERP (Kemungkinan):
```
receipt_amount = actual_receipt_qty × receipt_unit_price

BUKAN

receipt_amount = approve_qty × receipt_unit_price
```

**Mengapa?**
- `receipt_amount` adalah **nilai penerimaan barang** (accounting entry)
- Mencatat transaksi yang terjadi, bukan yang disetujui
- `approve_qty` baru digunakan saat **pembayaran/invoice**

---

## 📊 Contoh Real Kasus Invoice 47/X/25-0945

### Case 1: Decimal Quantity
```
Item: GL8BA0PAKU3CM00 (PAKU 3CM)

┌─────────────────────┬──────────┐
│ request_qty         │ 1 box    │  ← Planning
├─────────────────────┼──────────┤
│ actual_receipt_qty  │ 0.25 box │  ← Warehouse count (decimal!)
├─────────────────────┼──────────┤
│ approve_qty         │ 0 box    │  ← Rounded di sistem (0.25 → 0)
├─────────────────────┼──────────┤
│ receipt_unit_price  │ 25,000   │
├─────────────────────┼──────────┤
│ receipt_amount      │ 6,250    │  ← 0.25 × 25,000 (dari actual!)
└─────────────────────┴──────────┘

Jika pakai approve_qty × price = 0 × 25,000 = 0 ❌
Jika pakai receipt_amount = 6,250 ✅
```

### Case 2: QC Rejection
```
Item: ITEM-ABC

┌─────────────────────┬──────────┐
│ request_qty         │ 100 pcs  │  ← Planning
├─────────────────────┼──────────┤
│ actual_receipt_qty  │ 100 pcs  │  ← Warehouse terima 100
├─────────────────────┼──────────┤
│ approve_qty         │ 95 pcs   │  ← QC reject 5 (cacat)
├─────────────────────┼──────────┤
│ receipt_unit_price  │ 1,000    │
├─────────────────────┼──────────┤
│ receipt_amount      │ 100,000  │  ← 100 × 1,000 (dari actual)
└─────────────────────┴──────────┘

Payment calculation:
- Jika pakai receipt_amount = 100,000 ❌ (overpay!)
- Jika pakai approve_qty × price = 95,000 ✅
```

---

## 💡 Kenapa Ada Perbedaan?

### Scenario A: Over/Under Delivery
```
PO: 100 unit
Supplier kirim: 105 unit
Warehouse input: actual_receipt_qty = 105
Finance approve: approve_qty = 100 (reject over 5)
```

### Scenario B: QC Rejection
```
Warehouse terima: 100 unit
QC check: 5 cacat
Finance approve: approve_qty = 95 (reject 5)
actual_receipt_qty tetap: 100
```

### Scenario C: Partial Shipment
```
PO: 100 unit
Supplier kirim sebagian: 50 unit
Warehouse input: actual_receipt_qty = 50
approve_qty = 50 (approve semua)
```

### Scenario D: Decimal/Fractional Unit
```
PO: 1 box
Supplier kirim: 0.25 box (partial box)
Warehouse input: actual_receipt_qty = 0.25
Sistem round: approve_qty = 0
receipt_amount = 0.25 × price (tetap ada nilai!)
```

---

## 🎯 Kesimpulan

### `actual_receipt_qty` berasal dari:

1. **Primary Source:**
   - 🏭 **Warehouse Physical Count** (manual input staff)
   - 📊 **ERP System Sanoh** (SQL Server)
   - 📋 **Tabel:** `data_receipt_purchase`

2. **Karakteristik:**
   - ✅ Data FAKTUAL dari lapangan
   - ✅ Input oleh warehouse staff saat penerimaan barang
   - ✅ Bisa decimal (0.25, 0.5, dst)
   - ✅ Basis perhitungan `receipt_amount`
   - ✅ Bisa berbeda dari `request_qty` (PO)
   - ✅ Bisa berbeda dari `approve_qty` (QC reject)

3. **Workflow:**
   ```
   Supplier → Warehouse → Physical Count → 
   Input ERP → actual_receipt_qty → 
   Sync to Laravel → inv_line table
   ```

---

## 📝 Important Notes

### ⚠️ Data Integrity
- `actual_receipt_qty` adalah **source of truth** untuk penerimaan fisik
- Tidak boleh diubah setelah input (audit trail)
- Perubahan hanya via adjustment/correction note

### ⚠️ Payment Calculation
- `actual_receipt_qty` → untuk accounting/record keeping
- `approve_qty` → untuk payment calculation
- `receipt_amount` → kombinasi keduanya (tergantung ERP logic)

### ⚠️ Decimal Handling
- ERP mungkin support decimal qty
- Laravel/MySQL support decimal
- Display/UI mungkin round untuk user friendly
- **Calculation harus pakai value asli**, bukan rounded!

---

## 🔗 Related Files

- **Model:** `app/Models/ERP/InvReceipt.php` (source)
- **Model:** `app/Models/InvLine.php` (target)
- **Controller:** `app/Http/Controllers/Api/Local2/InvoiceReceiptController.php` (sync)
- **Migration:** `database/migrations/2025_06_09_150909_create_inv_line_table.php`

---

**Created:** 2025-11-11  
**Purpose:** Documentation of data flow for actual_receipt_qty field
