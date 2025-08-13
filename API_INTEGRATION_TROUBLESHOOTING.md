# API Integration Troubleshooting Guide

## 🎯 Masalah
Pada tampilan sistem, untuk yang berakhiran `-2` masih belum terintegrasi. Perlu dicek apakah ada masalah pada API atau komponen lainnya.

## 🔍 Langkah-langkah Diagnose

### 1. Diagnose API Integration
```bash
# Diagnose semua suffix (-1, -2, -3)
php artisan api:diagnose-integration

# Diagnose bp_code tertentu dengan akhiran -2
php artisan api:diagnose-integration SLAPMTI-2

# Diagnose dengan testing API endpoints
php artisan api:diagnose-integration SLAPMTI-2 --test-api
```

### 2. Test API Response
```bash
# Test response untuk bp_code dengan akhiran -2
php artisan api:test-response SLAPMTI-2

# Test dan bandingkan dengan base bp_code
php artisan api:test-response SLAPMTI-2 --compare
```

### 3. Check Authentication & Access
```bash
# Check semua users dan bp_codes
php artisan api:check-auth

# Check bp_code tertentu
php artisan api:check-auth SLAPMTI-2

# Check user tertentu
php artisan api:check-auth --user-id=1
```

### 4. Fix Business Partner Integration
```bash
# Fix khusus untuk suffix -2
php artisan business-partner:fix-suffix2

# Fix komprehensif
php artisan business-partner:fix-integration --force

# Update parent-child relationships
php artisan business-partner:update-relations
```

## 🔧 Kemungkinan Masalah dan Solusi

### Masalah 1: Parent-Child Relationship Tidak Benar
**Gejala**: Data tidak terintegrasi antara bp_code lama dan baru
**Solusi**:
```bash
# Check relationship
php artisan business-partner:diagnose SLAPMTI-2

# Fix relationship
php artisan business-partner:fix-suffix2
```

### Masalah 2: User Authentication
**Gejala**: User tidak bisa mengakses data
**Solusi**:
```bash
# Check user authentication
php artisan api:check-auth SLAPMTI-2

# Check jika user ada dengan bp_code tersebut
php artisan tinker
>>> App\Models\User::where('bp_code', 'SLAPMTI-2')->first();
```

### Masalah 3: Database Connection
**Gejala**: Error database connection
**Solusi**:
```bash
# Check database connection
php artisan tinker
>>> DB::connection('mysql2')->getPdo();

# Check jika tabel business_partner ada
>>> DB::connection('mysql2')->table('business_partner')->count();
```

### Masalah 4: API Response Format
**Gejala**: Data tidak muncul di frontend
**Solusi**:
```bash
# Test API response
php artisan api:test-response SLAPMTI-2

# Check resource transformation
php artisan tinker
>>> $service = app(App\Services\BusinessPartnerUnifiedService::class);
>>> $invLines = $service->getUnifiedInvLines('SLAPMTI-2');
>>> App\Http\Resources\InvLineResource::collection($invLines->take(1));
```

### Masalah 5: Data Tidak Ada
**Gejala**: Tidak ada data untuk bp_code dengan akhiran -2
**Solusi**:
```bash
# Check data availability
php artisan tinker
>>> App\Models\InvLine::where('bp_id', 'SLAPMTI-2')->count();
>>> App\Models\InvHeader::where('bp_code', 'SLAPMTI-2')->count();

# Check unified data
>>> $service = app(App\Services\BusinessPartnerUnifiedService::class);
>>> $service->getUnifiedInvLines('SLAPMTI-2')->count();
```

## 🧪 Testing Manual

### Test 1: Check Database Data
```sql
-- Check business partner dengan akhiran -2
SELECT bp_code, parent_bp_code, bp_name 
FROM business_partner 
WHERE bp_code LIKE '%-2';

-- Check parent records
SELECT bp_code, parent_bp_code, bp_name 
FROM business_partner 
WHERE bp_code IN (
    SELECT DISTINCT REPLACE(bp_code, '-2', '') 
    FROM business_partner 
    WHERE bp_code LIKE '%-2'
);

-- Check InvLine data
SELECT bp_id, COUNT(*) as record_count
FROM inv_line 
WHERE bp_id LIKE 'SLAPMTI%'
GROUP BY bp_id
ORDER BY bp_id;

-- Check InvHeader data
SELECT bp_code, COUNT(*) as record_count
FROM inv_header 
WHERE bp_code LIKE 'SLAPMTI%'
GROUP BY bp_code
ORDER BY bp_code;
```

### Test 2: Check API Endpoints
```bash
# Test GR Tracking
curl -X GET "http://your-api/api/finance/inv-line/SLAPMTI-2" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test Invoice Creation
curl -X GET "http://your-api/api/finance/inv-line/invoice/SLAPMTI-2" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test Invoice Report
curl -X GET "http://your-api/api/finance/inv-header/bp-code/SLAPMTI-2" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test 3: Check Unified Service
```bash
php artisan tinker

# Test unified service
>>> $service = app(App\Services\BusinessPartnerUnifiedService::class);
>>> $service->getUnifiedBpCodes('SLAPMTI-2');
>>> $service->getUnifiedInvLines('SLAPMTI-2');
>>> $service->getUnifiedInvHeaders('SLAPMTI-2');
```

## 📊 Expected Results

Setelah perbaikan, Anda seharusnya melihat:

1. **Unified Data**: Mencari dengan `SLAPMTI-2` dan `SLAPMTI` mengembalikan data yang sama
2. **Complete Integration**: Semua data dari sistem lama dan baru terintegrasi
3. **Consistent Results**: Hasil yang konsisten di semua halaman (GR Tracking, Invoice Creation, Invoice Report)

### Verification Checklist
- [ ] Parent-child relationships ter-set dengan benar
- [ ] User authentication berfungsi
- [ ] Database connection stabil
- [ ] API response format benar
- [ ] Data tersedia untuk bp_code dengan akhiran -2
- [ ] Unified service berfungsi dengan baik
- [ ] Frontend menampilkan data dengan benar

## 🚨 Common Issues

### Issue 1: "No data found"
**Penyebab**: Data tidak ada atau parent-child relationship salah
**Solusi**: Jalankan `php artisan business-partner:fix-suffix2`

### Issue 2: "Unauthorized access"
**Penyebab**: User tidak memiliki bp_code yang benar
**Solusi**: Check user authentication dengan `php artisan api:check-auth`

### Issue 3: "Database connection failed"
**Penyebab**: Konfigurasi database salah
**Solusi**: Check file `.env` dan koneksi database

### Issue 4: "API returns empty response"
**Penyebab**: Resource transformation error atau data kosong
**Solusi**: Test dengan `php artisan api:test-response SLAPMTI-2`

## 📞 Support

Jika masalah masih berlanjut:

1. Jalankan semua diagnostic commands
2. Periksa log Laravel: `tail -f storage/logs/laravel.log`
3. Check browser developer tools untuk error
4. Verify database connections
5. Test dengan bp_code yang berbeda
6. Contact system administrator jika diperlukan

---

**Note**: Pastikan untuk menjalankan commands secara berurutan dan periksa setiap output untuk mengidentifikasi masalah spesifik.
