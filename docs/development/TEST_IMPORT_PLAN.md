# TEST IMPORT EXCEL - STEP BY STEP

## 🎯 TESTING PLAN:

### **Phase 1: Clear Cache & Restart**
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan serve
```

### **Phase 2: Create Test Excel File**
Buat file Excel dengan data berikut (nama file: `test_wakif.xlsx`):

| kode_wakif | nama_wakif | no_hp | tanggal_wakaf | jenis_quran | jumlah_quran |
|------------|------------|-------|---------------|-------------|--------------|
| W004 | Siti Aminah | 081234567890 | 2024-12-28 | Al-Quran Ukuran A5 | 3 |
| W005 | Budi Hartono | 087654321012 | 2024-12-29 | Al-Quran Ukuran A6 | 2 |
| W006 | Andi Wijaya | 085678901234 | 2024-12-30 | Buku Iqro Jilid 1-6 | 1 |

### **Phase 3: Test Import Process**

#### **Step 1: Access Import Page**
- Login sebagai super_admin
- Buka `/admin/wakif`
- Klik tombol "Import Excel" (hijau)

#### **Step 2: Test Preview**
- Upload file `test_wakif.xlsx`
- Klik "Preview Data" (biru)
- Verifikasi:
  - ✅ 3 baris total
  - ✅ 3 baris valid
  - ✅ 0 error
  - ✅ Jenis Qur'an semua ditemukan

#### **Step 3: Test Import**
- Kembali ke halaman import
- Upload file yang sama
- Klik "Import Langsung" (merah)
- Tunggu proses selesai

#### **Step 4: Verify Results**
- Kembali ke `/admin/wakif`
- Cek data muncul di tabel:
  - ✅ Siti Aminah (W004)
  - ✅ Budi Hartono (W005) 
  - ✅ Andi Wijaya (W006)
- Cek statistik di card atas terupdate

### **Phase 4: Database Verification**
```sql
-- Cek wakif baru
SELECT * FROM wakif WHERE kode_wakif IN ('W004', 'W005', 'W006');

-- Cek pengiriman yang dibuat
SELECT 
    w.kode_wakif,
    w.nama_wakif,
    p.no_resi,
    p.jumlah_quran,
    jq.nama_jenis
FROM wakif w
JOIN pengiriman p ON w.id = p.wakif_id
JOIN jenis_quran jq ON p.jenis_quran_id = jq.id
WHERE w.kode_wakif IN ('W004', 'W005', 'W006')
ORDER BY w.kode_wakif, p.created_at;
```

**Expected Result:**
- W004: 3 pengiriman (EQ-2025-00003, 00004, 00005)
- W005: 2 pengiriman (EQ-2025-00006, 00007)
- W006: 1 pengiriman (EQ-2025-00008)

---

## 🚨 TROUBLESHOOTING CHECKLIST:

### **Jika Preview Tidak Muncul:**
- [ ] Route `/admin/wakif-preview` accessible
- [ ] File `WakifImportPreview.php` ada
- [ ] File `Preview.svelte` ada
- [ ] Laravel Excel package terinstall

### **Jika Import Gagal:**
- [ ] File `WakifImport.php` diperbaiki
- [ ] Jenis Qur'an sesuai database
- [ ] Format tanggal benar (YYYY-MM-DD)
- [ ] User login sebagai super_admin

### **Jika Data Tidak Muncul di Index:**
- [ ] Clear browser cache (Ctrl+F5)
- [ ] Cek JavaScript console error
- [ ] Cek Network tab for API response
- [ ] Verify data in database manual

### **Jika Error Laravel:**
- [ ] Cek `storage/logs/laravel.log`
- [ ] Cek permission folder `storage/`
- [ ] Cek database connection
- [ ] Restart Laravel server

---

## 📝 SUCCESS CRITERIA:

- ✅ Template download berfungsi
- ✅ Preview validation berfungsi
- ✅ Import data berhasil
- ✅ Data masuk ke tabel wakif
- ✅ Data masuk ke tabel pengiriman (1 Al-Qur'an = 1 Resi)
- ✅ Data muncul di index wakif
- ✅ Statistik terupdate
- ✅ Flash message muncul

**Mari kita test bersama-sama!** 🎯
