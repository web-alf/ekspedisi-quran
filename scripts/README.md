# Scripts Folder

Folder ini berisi script-script temporary yang digunakan untuk maintenance dan debugging project.

## Struktur Folder

### `/debug/`
Berisi script-script untuk debugging:
- `debug_qr_test.html` - Halaman test untuk QR scanner
- `debug_route_test.php` - Script test untuk route debugging

### `/fixes/`
Berisi script-script untuk memperbaiki masalah:
- `fix-status-pengiriman.php` - Script untuk memperbaiki status pengiriman
- `fix-status-pengiriman.sh` - Shell script wrapper untuk fix status pengiriman
- `fix_qr_build.sh` - Script untuk build dan clear cache QR scanner

### `/temp/`
Folder untuk script-script temporary yang belum dikategorikan.

## Scripts Available

### Puppeteer Auto-Fill Scripts

#### `puppeteer-donatur-dummy.js`
Script untuk mengisi form donatur secara otomatis dengan data dummy.

**Setup:**
```bash
npm install
```

**Penggunaan:**
```bash
# Menggunakan npm script
npm run puppeteer:donatur

# Atau langsung dengan node
node scripts/puppeteer-donatur-dummy.js

# Dengan custom URL
node scripts/puppeteer-donatur-dummy.js --url http://localhost:8000
```

**Fitur:**
- ✅ Mengisi semua field yang diperlukan dengan data dummy realistis
- ✅ Random selection untuk jenis wakaf (A5, A6, IQRA)  
- ✅ Support untuk kedua mode wakif (semua donatur / customize individual)
- ✅ Browser tetap terbuka untuk review sebelum submit

## Penggunaan

Script-script ini hanya digunakan untuk maintenance dan debugging. Jangan dijalankan di production environment tanpa testing terlebih dahulu.

## Catatan

- Semua script di folder ini bersifat temporary
- Backup data sebelum menjalankan script fixes
- Test di environment development terlebih dahulu
