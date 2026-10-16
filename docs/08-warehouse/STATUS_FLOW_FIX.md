# Status Flow Fix - Update Status Implementation

## Masalah yang Diperbaiki

### 1. **Masalah Awal**
- Setelah update ke status "Proses Pengiriman" pada halaman `/admin/pengiriman/1/update-status`, hanya tersedia opsi **"Batal"**
- Status "Diterima Penerima" tidak muncul sebagai opsi meskipun seharusnya tersedia

### 2. **Root Cause Analysis**

#### Database Status Issue:
- Status "Diterima" memiliki `urutan = 4` padahal dalam flow baru seharusnya `urutan = 8`
- Ada duplikasi status dengan urutan yang sama akibat migration yang tidak bersih
- Status lama (pending, dikemas, dikirim) masih aktif dan konflik dengan status baru

#### Logic Issue:
- Method `getNextPossibleStatuses()` di `PengirimanTrackingController` mencari status dengan `urutan = currentUrutan + 1`
- Jika current status "Proses Pengiriman" (urutan 7), maka mencari status dengan urutan 8
- Namun status "Diterima" masih urutan 4, jadi tidak ditemukan

## Solusi yang Diterapkan

### 1. **Database Fixes**

#### a. Update Status "Diterima":
```sql
UPDATE status_pengiriman 
SET urutan = 8, nama = 'Diterima Penerima' 
WHERE slug = 'diterima';
```

#### b. Nonaktifkan Status Duplikat:
```sql
UPDATE status_pengiriman 
SET is_active = 0 
WHERE slug IN ('pending', 'dikemas', 'dikirim') 
AND urutan <= 3;
```

### 2. **Perbaikan Halaman Update Status (/admin/pengiriman/1/update-status)**
- ✅ Sudah menggunakan `getNextPossibleStatuses()` yang benar
- ✅ Validasi transisi status sudah ada di `PengirimanTrackingController`
- ✅ Sekarang menampilkan "Diterima Penerima" + "Batal" untuk status "Proses Pengiriman"

### 3. **Perbaikan Halaman Scan Status (/admin/pengiriman?mode=scan-status)**

#### Backend Changes (`PengirimanController.php`):
- ✅ **Added**: Method `getPengirimanStatusInfo($noResi)` - API untuk mendapatkan info pengiriman + status valid
- ✅ **Added**: Method `getNextPossibleStatuses($currentStatusId)` - Logic sama dengan PengirimanTrackingController
- ✅ **Updated**: Method `updateStatusByScan()` - Tambah validasi transisi status
- ✅ **Added**: Method `validateStatusTransition()` - Validasi perubahan status
- ✅ **Updated**: Method `scanStatus()` - Include `is_final` field untuk frontend

#### Frontend Changes (`ScanStatus.svelte`):
- ✅ **Added**: Variable `pengirimanInfo` dan `validStatuses` untuk menyimpan data dinamis
- ✅ **Updated**: Method `processQRResult()` - Fetch pengiriman info setelah scan QR
- ✅ **Added**: Method `fetchPengirimanInfo()` - Call API untuk mendapatkan status valid
- ✅ **Updated**: Status selection - Gunakan `validStatuses` bukan `statusList` statis
- ✅ **Added**: Display current status dan info pengiriman
- ✅ **Updated**: Manual input juga menggunakan dynamic status validation

#### Route Changes:
- ✅ **Added**: `GET admin/pengiriman/{no_resi}/status-info` - API endpoint baru

## Flow Status yang Benar Setelah Perbaikan

```
1. Proses Pembelian Quran (urutan 1)
   ↓ Next: Proses Pemesanan, Batal
   
2. Proses Pemesanan (urutan 2)  
   ↓ Next: Proses Produksi, Batal
   
3. Proses Produksi (urutan 3)
   ↓ Next: Proses Kedatangan/Penurunan, Batal
   
4. Proses Kedatangan/Penurunan (urutan 4)
   ↓ Next: Proses Packing, Batal
   
5. Proses Packing (urutan 5)
   ↓ Next: Pengiriman Dokumentasi, Batal
   
6. Pengiriman Dokumentasi (urutan 6)
   ↓ Next: Proses Pengiriman, Batal
   
7. Proses Pengiriman (urutan 7) ← SEBELUMNYA BERMASALAH
   ↓ Next: Diterima Penerima, Batal ← SEKARANG SUDAH BENAR
   
8. Diterima Penerima (urutan 8, FINAL)
   ↓ Next: - (tidak ada)
   
99. Batal (urutan 99, FINAL)
    ↓ Next: - (tidak ada)
```

## Validation Rules

### Status Transition Rules:
1. **Status "Batal"**: Selalu tersedia dari status mana pun (kecuali sudah batal)
2. **Status Final**: Tidak bisa diubah lagi (kecuali dari batal)
3. **Sequential Only**: Hanya boleh maju ke status selanjutnya (urutan + 1)
4. **Same Status**: Tidak boleh update ke status yang sama

### Example Validations:
- ✅ Proses Pengiriman → Diterima Penerima (urutan 7 → 8)
- ✅ Proses Pengiriman → Batal (urutan 7 → 99)
- ❌ Proses Pengiriman → Proses Pembelian (urutan 7 → 1, mundur)
- ❌ Diterima Penerima → Proses Pengiriman (status final)
- ❌ Proses Pengiriman → Proses Pengiriman (status sama)

## Files Yang Dimodifikasi

### Backend:
- ✅ `app/Http/Controllers/Admin/PengirimanController.php`
- ✅ `routes/web.php`
- ✅ `database/migrations/2025_06_13_120000_fix_status_pengiriman_flow.php` (created)

### Frontend:
- ✅ `resources/js/Pages/Admin/Pengiriman/ScanStatus.svelte`

### Scripts:
- ✅ `scripts/fix_status_flow.php` (created)
- ✅ `scripts/test_status_flow.php` (created)

## Testing Results

### Database Status Check:
```sql
SELECT * FROM status_pengiriman WHERE is_active = 1 ORDER BY urutan;
```
✅ **Status ordering benar**: 1,2,3,4,5,6,7,8,99
✅ **"Diterima Penerima" urutan 8**: Benar
✅ **Status duplikat dinonaktifkan**: Benar

### API Testing:
- ✅ `GET /admin/pengiriman/EQ-2025-00002/status-info` returns correct data
- ✅ Pengiriman dengan status "Proses Pengiriman" mengembalikan ["Diterima Penerima", "Batal"]
- ✅ Status transition validation works correctly

### User Experience:
- ✅ Halaman `/admin/pengiriman/1/update-status` menampilkan opsi yang benar
- ✅ Halaman `/admin/pengiriman?mode=scan-status` menampilkan status dinamis berdasarkan QR scan
- ✅ Validasi error message informatif untuk transisi tidak valid

## Kesimpulan

✅ **Masalah SOLVED**: Setelah update ke "Proses Pengiriman", kini tersedia opsi:
- **Diterima Penerima** (untuk menyelesaikan pengiriman)
- **Batal** (untuk membatalkan pengiriman)

✅ **Konsistensi**: Kedua halaman (update status & scan status) kini menggunakan logic yang sama

✅ **Robustness**: Tambahan validasi mencegah transisi status yang tidak valid

✅ **Future-proof**: Logic yang diterapkan akan bekerja untuk semua status dalam flow
