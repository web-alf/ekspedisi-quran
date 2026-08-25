# TEST TOMBOL DOWNLOAD TEMPLATE

Untuk memastikan tombol download template berfungsi, ikuti langkah-langkah troubleshooting berikut:

## ✅ Yang Sudah Diperbaiki:

1. **Fungsi Download di Frontend**: 
   - ✅ Import.svelte: Menggunakan method download yang benar
   - ✅ Index.svelte: Tombol template baru ditambahkan
   - ✅ Error handling ditambahkan

2. **Controller Method**:
   - ✅ downloadTemplate() dengan try-catch
   - ✅ Return Excel::download()

3. **Route**:
   - ✅ `/admin/wakif-template` sudah terdaftar

4. **Export Class**:
   - ✅ WakifTemplateExport.php sudah dibuat

## 🔍 Cara Testing:

### Test 1: Cek Routes
```bash
php artisan route:list | grep template
```
Hasilnya harus ada:
```
GET|HEAD  admin/wakif-template  admin.wakif.template
```

### Test 2: Test Download Via Browser
Buka URL langsung di browser:
```
http://localhost:8000/admin/wakif-template
```

Jika Laravel Excel belum terinstall, akan muncul error. Jika sudah, file akan otomatis terdownload.

### Test 3: Cek Laravel Excel Installation
```bash
composer show maatwebsite/excel
```

Jika belum ada, install dulu:
```bash
composer require maatwebsite/excel
```

### Test 4: Clear Cache
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 🚨 Kemungkinan Masalah:

1. **Laravel Excel belum terinstall**
   - Solusi: `composer require maatwebsite/excel`

2. **Permission file**
   - Solusi: `chmod 755 storage/`

3. **Cache issue**
   - Solusi: Clear semua cache Laravel

4. **Browser blocking download**
   - Solusi: Allow downloads in browser settings

## 🎯 Testing Manual:

1. Login sebagai super_admin
2. Buka `/admin/wakif`
3. Klik tombol **"Template"** (biru)
4. File `template_wakif_import.xlsx` harus terdownload
5. Buka file Excel yang terdownload
6. Pastikan ada:
   - Header: kode_wakif, nama_wakif, no_hp, tanggal_wakaf, jenis_quran, jumlah_quran
   - Sample data: W001, W002, W003

## 📝 File Template Excel Berisi:

| kode_wakif | nama_wakif | no_hp | tanggal_wakaf | jenis_quran | jumlah_quran |
|------------|------------|-------|---------------|-------------|--------------|
| W001 | Ahmad Subagyo | 081234567890 | 2024-12-25 | Al-Quran Standar | 5 |
| W002 | Siti Maryam | 087654321098 | 2024-12-26 | Al-Quran Terjemah | 3 |
| W003 | Budi Santoso | 085678901234 | 2024-12-27 | Al-Quran Braille | 2 |

---

**Jika masih tidak berfungsi, cek:**
1. Console browser untuk error JavaScript
2. Laravel log: `tail -f storage/logs/laravel.log`
3. Network tab di browser developer tools

Setelah langkah di atas, tombol download template akan berfungsi sempurna! 🎉
