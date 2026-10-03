# Paginasi Seragam di Seluruh Tabel

**Tanggal:** 2026-10-03
**Commit:** `be85144`
**Status:** sudah di-push dan di-deploy ke `dash.ekspedisiquran.com`

## Ringkasan

Sebelumnya setiap controller mematok sendiri jumlah baris per halaman — ada yang 10, 12, 15,
20, 25, 100, bahkan 500. Akibatnya perilakunya berbeda-beda di tiap halaman dan pengguna tidak
punya cara memilih. Halaman Pengiriman dan Generate QR sudah diperbaiki lebih dulu; sekarang
seluruh tabel lain ikut memakai aturan yang sama.

## Akar Masalah

Angka paginasi ditulis langsung di dalam kode (`->paginate(100)`), tersebar di 20 controller
tanpa satu sumber kebenaran. Tidak ada penjagaan terhadap nilai dari permintaan, sehingga
`?per_page=999999` bisa menarik seluruh tabel (26 ribu baris) sekaligus.

## Perbaikan

**Fondasi yang bisa dipakai ulang** (bukan salin-tempel 20 kali):

| Berkas | Isi |
|---|---|
| `app/Support/PerPage.php` | daftar `[10,20,50,100,200]`, bawaan 20, dan penjagaan `in_array` — satu-satunya sumber kebenaran |
| `resources/js/Components/PerPageSelector.svelte` | satu-satunya tempat pemilih dirender (lebar tetap + `shrink-0` supaya angka tidak menumpuk panah bawaan browser) |

- **20 controller** memakai `PerPage::resolve()` dan mengirim `perPage`/`perPageOptions`.
- **18 halaman** memakai `PerPageSelector` — termasuk 7 halaman lewat
  `Components/Pagination.svelte` yang kini memuat pemilihnya sendiri.
- Pemilih **selalu tampil**, walau hasilnya cuma satu halaman (sebelumnya tersembunyi di balik
  `{#if last_page > 1}` — artinya pada tabel pendek justru tidak bisa diperlebar).
- 4 halaman berkartu (Testimoni, Video, Galeri, FAQ) ikut dapat pemilih walau tanpa `<table>`.
- `PengirimanController` kini merujuk `PerPage::OPTIONS`/`PerPage::DEFAULT`, tidak lagi
  menyimpan daftarnya sendiri.

## Verifikasi

**7 tes baru** (`tests/Feature/Admin/PerPageSeragamTest.php`, 289 assertion):

| Tes | Hasil |
|---|---|
| satu daftar + satu bawaan untuk seluruh aplikasi | ✅ |
| nilai asal ditolak, nilai sah diteruskan | ✅ |
| bawaan 20 saat tidak diminta | ✅ |
| **18 rute tabel** hormati `?per_page=50` | ✅ |
| nilai di luar daftar jatuh ke 20 | ✅ |
| daftar pilihan terkirim ke frontend | ✅ |
| tidak ada `paginate(<angka>)` tersisa di controller | ✅ |

Bukti tes ini menggigit: penjagaan dilepas + bawaan diubah jadi 10 → **4 dari 7 gagal**.
Ditambah 49 tes di 5 berkas terkait Pengiriman/GenerateQR → **semua lulus**; `npm run test` 87 lulus.

**Verifikasi produksi** (permintaan HTTP nyata sebagai super-admin, 15 halaman):

```
/admin/donatur      bawaan 20 · p=50→50 · p=200→200 · palsu→20 · pilihan ada
/admin/users        idem      /admin/roles      idem
/admin/permissions  /admin/mushaf-requests      /admin/certificates
/admin/certificate-templates  /admin/box-tracking  /admin/testimonials
/admin/videos  /admin/galleries  /admin/faqs  /admin/pengiriman
/admin/settings/landing-content/general  /admin/settings/landing-content/seo
lolos: 15/15
```

## Catatan / tidak diubah

- `Supervisor/ManualAssignment.svelte` **dikembalikan apa adanya**: tidak dirujuk dari rute
  maupun `Inertia::render()` mana pun — halaman mati.
- `Supervisor/PerformanceReport.svelte` dan `Warehouse/Performance.svelte`: tabelnya berisi
  agregat per-pengguna (maksimal 24 staff / 30 tugas terakhir milik sendiri), bukan daftar
  panjang — tidak diberi paginasi.
- `QueryCacheService` (`perPage = 15` internal) dan perintah performa CLI: bukan tabel UI.
- **Klaim lama saya bahwa `HandleInertiaRequests::version()` mengembalikan `null` di produksi
  ternyata SALAH.** Diukur langsung di dash: `f9196d06…` (manifest memang ada; yang khusus dev
  adalah berkas `public/hot`). Deteksi build baru oleh Inertia sudah bekerja normal, jadi tidak
  ada yang diubah di sana. Laporan sebelumnya yang menyebut sebab ini perlu dianggap batal.

## Rekomendasi lanjutan

1. Halaman mati (`ManualAssignment.svelte`, `PublicTracking/Index`, `ScanQR/Index`,
   `GenerateQR/Index`, `BulkOperations/Dashboard`) — hapus atau sambungkan ke rute; sekarang
   menyesatkan siapa pun yang membaca kode.
2. `Performance` memakai `->limit(30)` tetap; kalau nanti dianggap perlu, ubah jadi paginasi
   dengan pemilih yang sama.
