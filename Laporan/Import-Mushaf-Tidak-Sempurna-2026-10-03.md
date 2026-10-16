# Import Permintaan Mushaf: mengapa berkas tidak masuk dengan sempurna

**Tanggal:** 2026-10-03
**Commit:** `446ec9e`, `729163f`
**Status:** sudah di-push dan di-deploy ke `dash.ekspedisiquran.com`; migrasi sudah dijalankan

## Ringkasan

Berkas import memang **bisa** masuk — template resminya pun berhasil 3 dari 3 baris.
Masalahnya, pengguna **tidak pernah tahu** apa yang sebenarnya terjadi, sehingga
kesannya "importnya tidak sempurna". Ada tiga sebab, dan ketiganya jenis yang sama:
kode berjalan tetapi tidak ada apa pun yang sampai ke layar.

## Penyebab 1 — Pesan error tidak pernah muncul (paling menentukan)

Di `resources/js/Pages/Admin/MushafRequest/Index.svelte`, fungsi `showError` dan
`showWarning` **dipanggil tetapi tidak pernah diimpor**:

| Baris | Pemakaian |
|---|---|
| 683 | `showError('Format File Tidak Valid', …)` |
| 690 | `showError('File Terlalu Besar', …)` |
| 701 | `showWarning('File Belum Dipilih', …)` |
| 727 | `showError('Error Import', …)` |

Akibatnya setiap penolakan berkas atau kegagalan import melempar
`ReferenceError: showError is not defined` **di dalam penangan kejadian**, sehingga
sisa penangannya tidak pernah jalan dan **tidak ada satu pun pesan yang tampil**.

Terbukti dari uji Vitest — muncul 3 kali:
```
ReferenceError: showError is not defined
  ❯ HTMLInputElement.handleFileSelect resources/js/Pages/Admin/MushafRequest/Index.svelte:683:9
```

Perbaikan: menambahkan `import { showError, showWarning } from '../../../stores/toast.js'`.

## Penyebab 2 — Laporan "berhasil" padahal ada baris yang hilang

`MushafRequestImport::hasErrors()` hanya melihat kegagalan **saat menyimpan**
(`$errors`), bukan kegagalan **validasi** (`$failures`). Baris yang tidak memenuhi
aturan tidak pernah sampai ke `collection()`, jadi jejaknya hanya ada di `$failures`.

Simulasi nyata: berkas 3 baris, 2 masuk, 1 gagal validasi →
controller menyimpulkan "tidak ada masalah" dan menampilkan **"Berhasil import 2 data"**.
Baris ketiga hilang tanpa disebut sama sekali.

Perbaikan:
```php
return count($this->errors) > 0 || $this->failures()->isNotEmpty();
```
Pesannya juga kini menyebut angka yang berhasil: *"Import selesai: 2 data berhasil,
1 data GAGAL dan tidak ikut masuk"*.

## Penyebab 3 — Detail baris gagal dibuang di middleware

Controller sudah mengirim daftar baris gagal sejak awal lewat `'import_errors'`, tetapi
`HandleInertiaRequests` hanya meneruskan `success/error/info/warning`. Datanya dibuang
di middleware, dan tidak ada satu pun baris di frontend yang merendernya
(`grep import_errors resources/js/` → kosong).

Perbaikan: `import_errors` diteruskan lewat flash, dan halaman kini menampilkan
tabelnya (baris ke berapa, penyebabnya apa).

## Perbaikan tambahan: kolom alamat terisi otomatis

Terpisah dari tiga hal di atas, berkas import hanya memuat satu kolom teks alamat,
sementara pengimpornya mengisi seluruh kolom wilayah dengan `null` dan komentar
"will be filled later" — dan tidak ada apa pun yang mengisinya kemudian. Akibatnya
halaman detail menampilkan alamat tidak lengkap, dan form "Informasi Lembaga" (yang
mewajibkan latitude/longitude) tidak bisa disimpan.

`App\Services\MushafAddressResolver` kini mengisinya:
memperluas tautan peta pendek → reverse geocoding → pencocokan nama ke sumber data
wilayah → kecamatan dari teks alamat → kelurahan hanya bila benar-benar disebut.

**Yang sengaja TIDAK ditebak**, karena sumber data wilayah yang dipakai aplikasi belum
lengkap: "Bence" dan "Tanggung" (dari alamat template) tidak ada di dalamnya, dan
"Slorok" ada di dua kecamatan. Nama jalan juga dibuang lebih dulu — tanpa itu
"Jl. Slorok" terbaca sebagai kelurahan.

## Verifikasi

| Uji | Hasil |
|---|---|
| `MushafAddressAutoFillTest` (13) | lulus · bukti-gagal 8/13 |
| `DianosaImportGagalSebagianTest` (4) | lulus · bukti-gagal 2/4 |
| `UmpanBalikImportTest` (3) | lulus · bukti-gagal 2/3 |
| `MushafRequestImportTest` (25, lama) | lulus setelah disesuaikan |
| Vitest (89) | lulus, `ReferenceError` hilang |
| Total tes PHP berkas Imports | **51 lulus** |

**Verifikasi produksi** (permintaan HTTP nyata sebagai super-admin):
```
hasErrors() menghitung kegagalan validasi?          YA
middleware meneruskan 'import_errors'?              YA
halaman memuat daftar baris gagal?                  YA (admin-users-DvKxT20-.js)
flash.warning : Import selesai: 5 data berhasil, 2 data GAGAL dan tidak ikut masuk
jumlah baris gagal diterima: 2
   baris 3: Validation: Nomor HP wajib diisi
   baris 7: Validation: Alamat lengkap wajib diisi
kolom link_gmaps: ADA
```

## Catatan / belum selesai

- **5 permintaan hasil import di produksi (REQ-2026-00025 … 00029) tetap kosong
  alamatnya dan tidak bisa dilengkapi otomatis.** Baris itu tidak menyimpan koordinat
  sama sekali, dan tautan petanya tidak pernah disimpan (kolomnya baru ada sekarang).
  Isiannya harus lewat halaman detail. Kolom `link_gmaps` ditambahkan supaya ini tidak
  terulang: kalau penguraian gagal sekali (jaringan mati), barisnya masih bisa
  dilengkapi kapan saja.
- Perintah `mushaf:backfill-address` sudah tersedia (dengan `--dry-run`), tetapi tidak
  berguna untuk 5 baris itu karena tidak punya tautan peta.
- Sumber data wilayah (`emsifa.com`) ternyata kurang lengkap — ini di luar kendali
  aplikasi dan kemungkinan perlu diganti sumber yang lebih baru bila ketepatan
  kelurahan penting.
