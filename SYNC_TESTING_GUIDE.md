# Sync Testing Guide

## Konfigurasi Database yang Diperbarui

### Database 1: SQL Server (ERP)
- **Host**: 10.1.10.50
- **Port**: 1433
- **Database**: soi107
- **Username**: sanoh
- **Password**: San0h!nd

### Database 2: MySQL (Local)
- **Host**: localhost
- **Port**: 3306
- **Database**: sanoh-scm
- **Username**: root
- **Password**: (kosong)

## Status Connection

✅ **SQL Server Connection**: Berhasil terhubung ke 10.1.10.50:1433/soi107
✅ **MySQL Connection**: Berhasil terhubung ke localhost:3306/sanoh-scm
✅ **API Server**: Berjalan di http://127.0.0.1:8000

## Endpoint Testing

### 1. Test API Basic
```bash
curl http://127.0.0.1:8000/api/test-api
```

**Expected Response:**
```json
{
    "success": true,
    "message": "API is working correctly",
    "timestamp": "2025-08-05T07:21:07.792414Z",
    "environment": "local"
}
```

### 2. Test Sync Endpoint (LocalDataController)

**Endpoint**: `GET /api/local2/sync-inv-line`

**Headers Required:**
```
Authorization: Bearer {your_token}
Content-Type: application/json
```

**Test dengan Postman:**
1. Method: GET
2. URL: `http://127.0.0.1:8000/api/local2/sync-inv-line`
3. Headers:
   - Authorization: Bearer {token}
   - Content-Type: application/json

**Expected Response:**
```json
{
    "success": true,
    "message": "Data synchronized successfully",
    "stats": {
        "processed": 0,
        "skipped": 0,
        "errors": 0
    }
}
```

### 3. Test Sync Endpoint (InvoiceReceiptController)

**Endpoint**: `GET /api/sync`

**Test dengan Postman:**
1. Method: GET
2. URL: `http://127.0.0.1:8000/api/sync`

**Expected Response:**
```json
{
    "success": true,
    "message": "Data synchronized successfully",
    "stats": {
        "processed": 0,
        "skipped": 0,
        "errors": 0,
        "total_found": 0
    }
}
```

## Troubleshooting

### Jika mendapat error "Database connection failed":

1. **Periksa MySQL Server**:
   ```bash
   # Windows
   net start mysql
   
   # Linux/Mac
   sudo systemctl start mysql
   ```

2. **Periksa SQL Server**:
   ```bash
   # Windows
   net start mssqlserver
   ```

3. **Test Connection Manual**:
   ```bash
   php troubleshoot.php
   ```

### Jika mendapat error "Table not found":

1. **Periksa apakah tabel ada di database**:
   ```sql
   -- MySQL
   USE sanoh-scm;
   SHOW TABLES;
   
   -- SQL Server
   USE soi107;
   SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES;
   ```

2. **Periksa nama tabel yang benar**:
   - `po_detail`
   - `dn_header`
   - `dn_detail`
   - `inv_line`

### Jika mendapat error "Authentication failed":

1. **Periksa credentials di .env**
2. **Test connection manual dengan credentials yang sama**

## Log Files

Periksa log untuk debugging:
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Database logs
tail -f storage/logs/database.log
```

## Testing dengan Data Mock

Jika database tidak tersedia, gunakan test unit:
```bash
php artisan test tests/Feature/SyncManualTest.php
```

## Next Steps

1. **Test dengan Postman** menggunakan endpoint di atas
2. **Periksa log** jika ada error
3. **Verifikasi data** di database setelah sync
4. **Test dengan data real** dari ERP system

## Notes

- Sync endpoint memerlukan authentication (Bearer token)
- Data akan di-sync dari SQL Server ke MySQL
- Duplicate data akan di-update, bukan di-create ulang
- Error handling sudah diimplementasi dengan logging 