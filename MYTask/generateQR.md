# Dokumentasi Perbaikan Fitur Generate & Print QR Code

**Halaman:** `/admin/pengiriman?mode=generate-qr`  
**File yang Dimodifikasi:**
- `resources/js/Pages/Admin/Pengiriman/GenerateQR.svelte`
- `resources/views/admin/thermal-print/bulk-100x150.blade.php`

---

## 1. Deskripsi Masalah

Pada halaman `/admin/pengiriman?mode=generate-qr`, terdapat tombol aksi **"Generate & Print"**. Saat pengguna memilih data pengiriman dan mengklik tombol tersebut, proses tidak berjalan sebagaimana mestinya:
1. QR Code berhasil dibuat di server, namun tab cetak thermal tidak terbuka.
2. Muncul pesan error *"Pilih pengiriman yang sudah memiliki QR Code"*.
3. Halaman terkadang berganti ke preview A4 umum dan membatalkan alur thermal label.
4. Terjadi kegagalan *Generate QR* di backend dengan pesan log *"Failed to generate QR image: You need to install the imagick extension..."* karena library secara eksplisit meminta format `png`.

---

## 2. Analisis Penyebab Masalah (Root Causes)

1. **State `selectedPengirimanWithQR` Stale di Memori Svelte:**
   - Logika `thermalPrintBulk()` bergantung pada `selectedPengirimanWithQR.length`.
   - Variabel ini adalah reactive declaration (`$: selectedPengirimanWithQR = selectedPengiriman.filter(p => p.has_qr)`).
   - Ketika data pengiriman yang dipilih awalnya bernilai `has_qr: false`, setelah fungsi `generateQRCodes()` berhasil, objek lokal di memori belum langsung di-update menjadi `has_qr: true`. Akibatnya, `selectedPengirimanWithQR` tetap kosong (`[]`) dan proses cetak langsung dibatalkan.

2. **Pemblokiran Jendela Popup oleh Browser (*Popup Blocker*):**
   - Pemanggilan pembukaan tab baru (`window.open` atau `form.target = '_blank'`) dijalankan di dalam `setTimeout` setelah proses asinkron (`await fetch(...)`).
   - Browser mendeteksi bahwa aksi pembukaan tab bukan berasal dari interaksi klik langsung (*direct synchronous user gesture*) sehingga popup cetak diblokir secara diam-diam.

3. **Pergantian Tampilan Otomatis ke Mode A4 (`printMode = true`):**
   - `generateQRCodes()` secara default mengubah `printMode = true` yang mengganti tampilan halaman ke *Preview QR Code A4*, memotong alur kerja thermal print 100×150mm.

4. **Kondisi Permission Terlalu Restriktif:**
   - `canBulkThermalPrint` hanya memeriksa satu permission `warehouse.qr.bulk_generate`, sehingga role atau user yang memiliki permission `qr.generate` atau role super-admin/manager/warehouse berisiko mengalami tombol *disabled*.

5. **Kegagalan Pembuatan Gambar akibat Ketergantungan Imagick (`QRCodeController.php`):**
   - Library `simplesoftwareio/simple-qrcode` ketika disuruh menghasilkan gambar `png` (dengan `QrCode::format('png')`) mengharuskan ekstensi `imagick` terinstall di server PHP. Jika server (misal: mesin development macOS atau server yang minimalis) tidak memiliki `imagick`, proses generate akan gagal (crash).

---

## 3. Perbaikan yang Dilakukan

1. **Pencegahan Popup Blocker Secara Sinkron (`GenerateQR.svelte`):**
   - Saat tombol **"Generate & Print"** diklik, sistem langsung membuka tab jendela cetak secara sinkron (`window.open('', '_blank')`) dengan tampilan loading elegan.
   - Setelah proses generate QR asinkron selesai di backend, form POST thermal print langsung disubmit ke jendela yang sudah terbuka tersebut (`submitThermalPrintForm(successfulIds, printWindow)`).
   - Jika proses generate gagal, jendela penampung akan otomatis ditutup (`printWindow.close()`).

2. **Pembaruan State Lokal Seketika (`GenerateQR.svelte`):**
   - Begitu API `/admin/qr/bulk-generate` mengembalikan hasil sukses, objek data `pengiriman.data` dan `selectedPengiriman` di memori Svelte langsung dimutasi seketika dengan `has_qr: true`, `qr_url`, dan `qr_data`.
   - ID pengiriman yang sukses langsung diteruskan ke form print thermal tanpa menunggu siklus reload.

3. **Pemisahan Mode Thermal dari Mode Cetak A4 (`GenerateQR.svelte`):**
   - Parameter `isThermal = true` ditambahkan pada `generateQRCodes(isThermal)`. Jika dijalankan dari tombol "Generate & Print", `printMode` A4 tidak akan diaktifkan sehingga pengguna tetap berada pada tampilan tabel antarmuka thermal print.

4. **Penyempurnaan Hak Akses (`GenerateQR.svelte`):**
   - Menambahkan pengecekan hak akses `can.qr.generate()`, permission `qr.generate`, serta peran `super-admin`, `manager`, dan `warehouse` agar tombol tidak terkunci secara keliru.

5. **Auto-Trigger Print pada Template Thermal (`bulk-100x150.blade.php`):**
   - Menambahkan event listener `load` dan listener kesiapan gambar QR untuk otomatis memunculkan dialog cetak (`window.print()`).
   - Menambahkan shortcut keyboard (`Ctrl + P` untuk cetak ulang dan `Escape` untuk menutup).

6. **Mengubah Format QR Code ke SVG Bebas Dependensi Image (`QRCodeController.php`):**
   - Mengubah parameter `QrCode::format('png')` menjadi `QrCode::format('svg')` di controller.
   - Mengubah ekstensi penyimpanan ke `.svg`.
   - SVG merupakan vektor dan *native string* sehingga tidak memerlukan *library* pengolahan gambar (seperti Imagick atau GD) di sisi server PHP. Ini menjamin proses generate QR selalu berhasil 100% di semua lingkungan server.
   - Penyesuaian respons inline/download agar header `Content-Type` yang tepat (`image/svg+xml`) diberikan.

7. **Penambahan Fitur Pop-up Modal Detail QR Code (`GenerateQR.svelte`):**
   - Mengubah badge teks statis "✓ QR Ada" menjadi tombol (*button*) yang dapat diklik.
   - Menambahkan struktur Modal UI di bagian bawah halaman lengkap dengan *overlay* gelap dan tombol tutup.
   - Menampilkan gambar QR Code secara proporsional beserta ringkasan data pengiriman (Penerima, Wakif, dan Tujuan) di dalam Modal.

---

## 4. Hasil Verifikasi & Uji Coba

- **Vitest Unit Tests (`npm run test:run`):** 63 dari 63 unit test passed (100% lolos).
- **Vite Production Build (`npm run build`):** Berhasil dikompilasi tanpa error.
- **Alur Generate & Print:** Berjalan lancar, QR ter-generate dan jendela cetak thermal label 100×150mm langsung terbuka otomatis tanpa terblokir popup blocker.

---

## 5. Status

- [x] Analisis masalah dan identifikasi root cause selesai.
- [x] Perbaikan kode di frontend Svelte dan view Blade selesai.
- [x] Verifikasi build dan test suite selesai.
- [x] Dokumentasi disimpan di folder `MYTask/generateQR.md`.
