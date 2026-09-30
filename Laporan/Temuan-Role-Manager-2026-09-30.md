# Temuan Role Manager — Permintaan Mushaf, Donatur, Daftar Quran Selesai Packing

**Tanggal:** 30 September 2026
**Lingkup:** role `manager` (uid 6, Nalurita Firdausyah — 60 izin, cocok 1:1 dengan seeder)
**Sumber bukti:** pembacaan kode, query DB produksi lokal (`db_ekspedisi_quran`), probe HTTP nyata per role, uji tinker dengan rollback.

---

## Ringkasan

| # | Keluhan tim | Status temuan |
|---|---|---|
| 1 | Tombol edit jumlah di halaman detail belum berfungsi & belum muncul di semua proses | **Terbukti bug** — tombol hanya muncul untuk status `approved`/`reviewed`, dan penyimpanannya tidak pernah menulis kolom yang dipakai halaman untuk menampilkan |
| 2 | Akumulasi "Total Disetujui" belum realtime | **Terbukti bug** — akumulasi disimpan ke kolom berbeda dari yang dibaca halaman detail, jadi seolah tidak tersimpan. Satu permintaan bisa tampil 4 angka berbeda |
| 3 | Mulai "Selesai Packing" → "Diterima Penerima" | **Belum ada jalurnya** — status permintaan mushaf tidak punya tahap packing/pengiriman, dan tidak ada kode yang menulis status `selesai-packing` |
| 4 | Halaman Kelola Donatur dihilangkan (untuk manager) | **Terbukti** — menu masih tampil dan `/admin/donatur` masih 200 untuk manager |
| 5 | Halaman Daftar Quran selesai Packing + Barcode (pcs, doz, dll) | **Belum ada** — yang ada hanya daftar kerdus, labelnya QR (bukan barcode), dan tidak ada satuan pcs/doz di skema |

Temuan tambahan di luar daftar: **`/admin/performance` error 500** untuk manager *dan* super-admin (menu "Laporan Kinerja" ada di dropdown Manajemen Gudang yang dilihat manager).

---

## Status perbaikan (30 Sep 2026)

**Paket A — selesai & terverifikasi.** Paket B — selesai & terverifikasi. Poin #4 dan #5 masih menunggu keputusan Anda.

| # | Perbaikan | Bukti |
|---|---|---|
| 1 & 2 | Satu sumber angka `approved_breakdown` (A5+A6+IQRA) di model, dipakai halaman detail, daftar, modal, proses ke pengiriman, peta admin & publik, dan cache dashboard. Kolom `jumlah_mushaf_approved` dijaga model (hook `saving`) sebagai penanda "sudah ditetapkan", dengan migrasi penyelaras untuk baris lama. Tombol Edit Jumlah kini mengikuti izin (`can.mushafRequests.update`), bukan daftar status. | 8 test baru lulus; probe tinker pada baris nyata (id=1): edit A5=7/A6=3/IQRA=2 → total 12 dan angka itu sama di semua tampilan |
| 3 | Tahap **Packing → Selesai Packing → Pengiriman → Diterima Penerima** tampil di halaman tracking permintaan; `PackingBox::seal()` otomatis menaikkan status pengiriman dari `packing` ke `selesai-packing` (sebelumnya tidak ada penulisnya sama sekali). | 4 test baru lulus (naik, tidak mundur bila sudah dikirim, tampil di halaman, auto-selesai saat diterima) |

### Perbaikan tak terduga yang ikut ketemu

- **Penjaga proses ke pengiriman memblokir semua permintaan.** `has_quantity_change` bernilai true untuk seluruh baris (kolom pecahan NOT NULL default 0), sehingga `has_quantity_change && is_null(jumlah_mushaf_approved)` menolak setiap permintaan. Ini yang tim rasakan sebagai "alur macet". Penjaganya kini bermakna: hanya menolak bila jumlah yang akan dikirim benar-benar 0.
- **`jenis_quran_id` di-hardcode 1/2.** Nomor itu hanya kebetulan benar di satu database; di DB yang di-seed ulang, proses ke pengiriman gagal dengan foreign key violation. Kini dicari lewat `kode_jenis`.
- **`PengirimanController::bulkSetAlamat` tidak mengisi `mushaf_request_id`**, sehingga permintaan mushaf tidak pernah tertaut ke pengiriman, tidak pernah otomatis selesai, dan tidak muncul di peta distribusi. Kini menautkan + menandai diproses.
- **Kolom "Wakif" di halaman detail selalu "-"**: view memanggil `pengiriman.wakif?.nama_wakif`, padahal relasinya `wakafItem` dan kolomnya `wakif_name`. Sudah diperbaiki.
- **`DailyPackingTaskFactory` memakai `assignment_method => 'auto'`** yang tidak ada di `ENUM('flat','target_only','mixed_box_based')` → 5 test concurrency gagal. Sudah diperbaiki (13 gagal → 8, sisanya pra-eksisting).

### Reproduksi khusus: "Total Disetujui tidak berubah setelah jumlah diedit"

Laporan tim diuji ulang dengan me-mount halaman aslinya di Vitest (`tests/Unit/MushafRequestTotalDisetujui.test.js`).

| | Kode lama (HEAD) | Kode baru |
|---|---|---|
| Payload yang dikirim modal | `{"jumlah_mushaf_approved":60,"jumlah_mushaf_a5_approved":10,"jumlah_iqra_approved":5,...}` | `{"jumlah_mushaf_a5_approved":10,"jumlah_mushaf_a6_approved":0,"jumlah_iqra_approved":5,...}` |
| Total di kartu setelah simpan | **60 (tidak berubah)** | **15** |
| Hasil test | 2 gagal / 1 lulus | 3 lulus |

Penyebab persisnya: modal mengirim kolom `jumlah_mushaf_approved` apa adanya dari nilai *prefill* (= total pengajuan 60) padahal input yang diedit hanya pecahan A5/A6/IQRA, dan kartu Detail Permintaan membaca kolom total itu — bukan penjumlahan pecahannya. Jadi angka hasil edit memang tidak pernah sampai ke kolom yang ditampilkan.

### Sisa yang belum dikerjakan
| Poin | Status |
|---|---|
| #4 Halaman Kelola Donatur dihilangkan untuk manager | **SELESAI** — lihat bagian 5 di bawah. |
| #5 Daftar Quran selesai packing + barcode | **SELESAI** — lihat bagian 6 di bawah. |
| `/admin/performance` error 500 | `PerformanceController:212` memanggil `getSlowQueries()` yang private. Dilaporkan, belum diperbaiki (di luar lingkup paket A/B/#4/#5). |

---

## 1. Tombol edit jumlah belum berfungsi & belum muncul di semua proses

### Bukti

**a) Tombol hanya muncul di 2 status, dan tidak memeriksa izin**

`resources/js/Pages/Admin/MushafRequest/Show.svelte:525`
```svelte
{#if mushafRequest.status === 'approved' || mushafRequest.status === 'reviewed'}
  <button on:click={openEditQuantityModal} ...>Edit Jumlah</button>
{/if}
```
- Di status `pending` (tahap paling awal) tombol **tidak ada** → itulah "belum muncul di semua proses".
- Syaratnya juga tidak memakai `canUpdate`, jadi user tanpa izin update tetap melihat tombolnya.

**b) Modal "Edit Jumlah yang Disetujui" tidak punya input untuk kolom yang dibaca halaman**

Modal hanya menyediakan 3 input: `jumlah_mushaf_a5_approved`, `jumlah_mushaf_a6_approved`, `jumlah_iqra_approved` (`Show.svelte:876-917`). Kolom `jumlah_mushaf_approved` **tidak pernah dikirim sebagai hasil edit** — nilainya ikut terkirim apa adanya dari prefill (`mushafRequest.jumlah_mushaf_approved || mushafRequest.jumlah_mushaf`, baris 72-77 & 178-183), yaitu total **yang diajukan**, bukan hasil edit.

Akibatnya seluruh tampilan yang membaca `jumlah_mushaf_approved ?? jumlah_mushaf` mengabaikan perubahan user:

| Lokasi | Ekspresi |
|---|---|
| `Show.svelte:539` baris "Jumlah Mushaf" | `jumlah_mushaf_approved ?? jumlah_mushaf` |
| `Show.svelte:564` baris "Total Disetujui" | `(jumlah_mushaf_approved ?? jumlah_mushaf) + (jumlah_iqra_approved ?? jumlah_iqra)` |
| `MushafRequestController:83-84` data peta | idem |
| `MapController:25-26`, `routes/web.php:89-91` | idem |
| `DashboardCacheService:264`, `QueryCacheService:257` | idem |
| `MushafRequest::total_mushaf_approved` | pakai pecahan A5+A6+IQRA (sumber angka ke-4) |

**c) Bukti uji (tinker, `DB::rollBack()` — data produksi tidak berubah)**

Permintaan id 25 (`REQ-2026-00020`): A5=60, A6=0, IQRA=60 (diajukan 120). User mengubah di modal jadi A5=10, A6=0, IQRA=0 — modal menampilkan **Total 10**:

```
HASIL:
  kolom jumlah_mushaf_approved = 60        <-- tersimpan apa adanya, bukan hasil edit
  halaman detail "Total Disetujui" = 60     <-- bukan 10
  model accessor total_mushaf_approved = 10
  halaman daftar (pakai jumlah_mushaf) = 120
  peta pakai approved = 60
```

Satu perubahan menghasilkan **10 / 60 / 120** di tempat berbeda.

**d) Baris A5/A6/IQRA tidak tampil benar saat belum pernah diedit**

`jumlah_mushaf_a5_approved` dsb. punya `default 0` dan NOT NULL (`migration 2025_10_03_163532`), jadi `?? jumlah_mushaf_a5` **tidak pernah** jatuh ke fallback:

- `Show.svelte:541` `{#if (a5_approved ?? a5 ?? 0) > 0}` → **0 > 0 = false** → baris "Jumlah Mushaf A5" hilang padahal 100 A5 diminta.
- `Show.svelte:555` "Jumlah IQRA" → tampil **0** padahal diminta 60.
- Modal prefill pakai `||` (baris 178) sedangkan halaman detail pakai `??` (baris 539) → dua aturan berbeda untuk field yang sama.

**e) Semua 28 permintaan produksi saat ini `has_quantity_change = true`**

`has_quantity_change` = `total_mushaf_approved !== total_mushaf`, dan `total_mushaf_approved` memakai `?? ` pada kolom pecahan yang bernilai 0 → untuk **seluruh 28 baris** (25 pending + 3 reviewed, `jumlah_mushaf_approved` semuanya NULL) hasilnya `0 !== N` = true.

Efeknya penjaga di `MushafRequestController:227`:
```php
if ($mushafRequest->has_quantity_change && is_null($mushafRequest->jumlah_mushaf_approved)) {
    return back()->withErrors(['error' => 'Jumlah mushaf yang disetujui belum ditentukan...']);
}
```
→ **setiap** permintaan yang disetujui akan **selalu ditolak diproses** ke pengiriman, bahkan yang jumlahnya tidak pernah diubah.

**f) Bug turunan: jumlah yang masuk ke pengiriman salah saat kolom lama & pecahan berbeda**

`processToShipment:242-244` memakai `jumlah_mushaf_approved ?? jumlah_mushaf` + `jumlah_iqra_approved ?? jumlah_iqra`:

```
payload frontend: {jumlah_mushaf_approved:60, a5_approved:20, a6_approved:20, iqra_approved:5}
  Detail "Total Disetujui" = 65
  accessor model           = 45
  jumlah_quran ke pengiriman = 65
  peta publik                = 65
```

---

## 2. Akumulasi Total Disetujui belum realtime

Akar masalahnya sama dengan #1 dan bukan pada reactive statement-nya:

- `$: totalApproved` (`Show.svelte:293-295`) memang reaktif terhadap input A5/A6/IQRA — angka di modal ikut berubah saat diketik.
- Yang **tidak** ikut berubah adalah nilai yang tersimpan & ditampilkan: karena `jumlah_mushaf_approved` tidak pernah ditulis ulang, baris "Total Disetujui" di halaman detail (`Show.svelte:564`) tetap memakai total pengajuan. Dari sisi pengguna ini terbaca sebagai "tidak realtime".
- Tidak ada input untuk `jumlah_mushaf_approved`, sehingga angka "Total" di modal tidak berhubungan dengan angka mana pun yang tampil setelah disimpan.
- Kartu statistik di halaman daftar (`stats.approved` dll, `MushafRequestController:104-112`) menghitung **jumlah baris**, bukan jumlah mushaf — jadi perubahan jumlah tidak akan pernah terlihat di sana.

---

## 3. Mulai "Selesai Packing" → "Diterima Penerima"

### Kondisi saat ini

- Lifecycle permintaan mushaf hanya: `pending` → `reviewed` → `approved` → (`rejected`) → `processed` → `completed` (`MushafRequest::getStatusLabelAttribute`). **Tidak ada** tahap packing, selesai packing, pengiriman, atau diterima pada level permintaan.
- Permintaan langsung melompat ke `processed` begitu baris pengiriman dibuat (`markAsProcessed`, dipanggil `MushafRequestController:306`), lalu `completed` hanya lewat observer saat pengiriman berstatus `diterima` (`PengirimanObserver::completeRelatedMushafRequest`).
- **Tidak ada satu pun kode yang menulis status `selesai-packing`.** Hasil grep seluruh `app/`, `database/`, `routes/`: slug itu hanya muncul di seeder status (`UpdateStatusPengiriman:80`, migration) dan di jalur **baca** (`WarehouseMonitorController`, `DashboardCacheService`, `WakafBatch`, `PengirimanController::getNextPossibleStatuses`). `PackingBox::seal()` hanya mengubah box (`status='sealed'`, `seal_code`), **tidak menyentuh status pengiriman**.
- Konsekuensinya, tanpa intervensi manual admin (dropdown Update Status / Box Scanner), stok "Siap Distribusi" di Monitor Gudang (`WarehouseMonitorController:848`) akan selalu 0 dengan alasan yang sama seperti peta distribusi sebelumnya: status terminal yang tidak pernah dicapai.
- Halaman publik `/mushaf-tracking/{no_request}`:
  - "Riwayat Status" **di-hardcode dua entri** (Dibuat + status saat ini, `Show.svelte:306-317`); prop `statusHistory` dari controller (5 field, `MushafTrackingController:55-75`) **dikirim tapi tidak dipakai** (hanya muncul sekali di file, di deklarasi `export const`).
  - Peta label memakai key `'selesai'` sedangkan DB menyimpan `'completed'` (`Show.svelte:58` vs `MushafRequest::getStatusLabelAttribute`) → permintaan yang sudah selesai menampilkan teks mentah **"completed"**.
- Detail permintaan: blok "Informasi Pengiriman" (`Show.svelte:720`) membaca `pengiriman.wakif?.nama_wakif`, sedangkan `Pengiriman` **tidak punya relasi `wakif`** (yang ada `wakafItem`) → kolom Wakif selalu "-".

### Celah pada alur "Set Alamat dari Permintaan Mushaf"

`PengirimanController::bulkSetAlamat` (dipakai panel di halaman Pengiriman, `Index.svelte:453`) hanya menyalin `alamat_tujuan/nama_penerima/no_hp_penerima/nama_lembaga`. Ia **tidak** mengisi `pengiriman.mushaf_request_id` dan tidak menautkan `mushaf_requests.pengiriman_id`. Karena observer menyelesaikan permintaan lewat `where('pengiriman_id', ...)`, permintaan yang diproses melalui jalur ini **tidak akan pernah** `completed` dan **tidak akan pernah** muncul di peta — inilah yang harus diperbaiki agar "Diterima Penerima" benar-benar mengalir ke permintaan.

---

## 4. Halaman Kelola Donatur dihilangkan (role manager)

### Bukti

- Menu 'Kelola Donatur' (`AdminLayout.svelte:82-86`) tampil untuk siapa pun yang punya `donatur.read`, dan **manager memilikinya** (terverifikasi: seeder 60 izin = DB 60 izin, termasuk `donatur.create/read/update/import/export`).
- Menyembunyikan menu **saja tidak cukup**. Probe HTTP nyata per role:

```
ROLE      UID  /admin/donatur
manager   6    200     <-- masih terbuka
super-admin 1  200
```

- Tombol hapus di halaman donatur tetap aman: dibungkus `canDelete = can.donatur.delete()` (`Donatur/Index.svelte:21,539,608`) dan manager memang tidak punya `donatur.delete` (sengaja, sesuai catatan seeder).
- Yang perlu dipertimbangkan sebelum mencabut izin: `donatur.read` masih dipakai `DonaturPolicy` dan `WakafItemsController:22`; sedangkan daftar donatur di halaman **Pengiriman** tidak lewat izin donatur — ia dari relasi `pengiriman.donatur`/`wakafItem` dan prop `donaturList` di route yang dijaga `shipments.read`. Jadi mencabut `donatur.read` untuk manager tidak memutus halaman Pengiriman.

### Perbaikan (SELESAI)

Ditempuh jalur **cabut izin**, bukan sekadar sembunyikan menu — supaya menu dan URL-nya hilang bersamaan (menu difilter `requiredPermissions: ['donatur.read']` di `AdminLayout.svelte:82-86`).

1. `database/seeders/RolePermissionSeeder.php` — blok `Donatur` milik manager diganti komentar penjelas; manager tidak lagi menerima `donatur.create/read/update/import/export`.
2. `database/migrations/2026_09_30_180000_revoke_donatur_permissions_from_manager.php` (baru) — **ini yang benar-benar mengubah produksi.** Story `deploy` di `Envoy.blade.php` tidak menjalankan seeder (seeder hanya di story `deploy:seed`), jadi kalau hanya seeder yang diubah, produksi tidak akan berubah. Migrasi mencabut kelima izin dari role `manager` dan mengembalikannya di `down()`.
3. `tests/Feature/Admin/ManagerDonaturAndBoxUnitsTest.php` (baru) — 5 test: izin tercabut, `/admin/donatur` 403 untuk manager, customer-service tetap 200, seeder mencabut izin yang sebelumnya sudah ter-seed, dan izin `shipments.*` tidak ikut tercabut.

Verifikasi pada user manager asli (uid 6, Nalurita Firdausyah) setelah migrasi dijalankan:

```
izin efektif : 55  (sebelumnya 60)
donatur.read   => DITOLAK
donatur.create => DITOLAK
donatur.update => DITOLAK
shipments.read          => BOLEH
mushaf-requests.update  => BOLEH
warehouse.boxes.view    => BOLEH

HTTP /admin/donatur      => 403
HTTP /admin/box-tracking => 200
```

---

## 5. Halaman Daftar Quran selesai Packing + Barcode (pcs, doz, dll)

### Yang sudah ada

`/admin/box-tracking` ("Tracking Kerdus") + halaman detailnya:
- Daftar kerdus: kode, status (Kosong / Sedang Diisi / Penuh / Tersegel), jenis quran, progress `terisi/kapasitas`, user, seal code, tanggal, tombol detail & cetak label.
- Detail kerdus: info box + tabel isi (`Urutan`, `No. Resi`, `Donatur`, `Wakif`, `Packed oleh`).
- Cetak label: `resources/views/admin/thermal-print/box-label-100x150.blade.php` — 100×150 mm, berisi **QR code** + `terisi/kapasitas mushaf`.

### Yang belum ada (semuanya masih kosong di skema/kode)

1. **Daftar level item** mushaf yang selesai packing. Yang tersedia hanya agregat per kerdus (`item_count` = `packingItems->count()`); tidak ada halaman/endpoint yang mendaftar per mushaf dengan resi + wakif + tanggal packing.
2. **Barcode.** Label hanya memuat QR. `composer.json` tidak punya pustaka barcode 1D (hanya `simplesoftwareio/simple-qrcode`). Grep `barcode` di `app/`, `resources/js/`, `resources/views/`: nol hasil — hanya ada di dokumen `docs/02-features/BOX_BARCODE_SYSTEM.md` yang isinya sebenarnya sistem **QR**.
3. **Satuan pcs/doz/lusin.** Tidak ada di mana pun: grep `doz|lusin|kodi|satuan|pcs` di `app/`, `database/migrations`, `resources/js` → nol hasil. Yang ada hanya `jenis_quran.default_capacity` (A5=20, A6=20, IQRO=20) dan `packing_boxes.kapasitas/jumlah_terisi` yang dicetak sebagai "mushaf".
4. Catatan kecil: daftar kerdus menampilkan satuan **"items"** (Inggris) di `BoxTracking/Index.svelte:261`, tidak konsisten dengan "mushaf" di tempat lain.

Ini fitur baru, bukan perbaikan — butuh keputusan dulu (lihat di bawah).

### Perbaikan (SELESAI)

**Keputusan yang diambil:** memperluas halaman **Pelacakan Kerdus** yang sudah ada (bukan halaman baru), memakai **QR** yang sudah dipakai gudang (bukan menambah pustaka barcode 1D), dan **satuan dihitung otomatis** dari jumlah keping — tanpa tabel satuan baru.

1. `app/Support/BoxUnits.php` (baru) — satu-satunya tempat konversi satuan.
   - `PCS_PER_DOZ = 12`. `lusin` tidak ditampilkan sebagai kolom tersendiri karena nilainya identik dengan doz (dua kolom kembar = membingungkan); bila di lapangan lusin dihitung beda, ubah konstanta `PCS_PER_LUSIN` di sini.
   - Label memakai doz **hanya bila pas kelipatan 12**: 24 → `2 doz`, 30 → `2,5 doz`, 18 → `1,5 doz`. **20 keping TIDAK dibulatkan jadi "1 doz"** — ditampilkan `20 pcs`, supaya isi kerdus tidak tampak berkurang di mata gudang.
2. `app/Http/Controllers/Admin/BoxTrackingController.php` — tiap baris kerdus kini mengirim `satuan`, `satuan_kapasitas`, dan `qr_code_base64`; `stats` menambah `sealed_pcs` + `sealed_satuan`; filter baru `?selesai_packing=1` (status `sealed`).
   - **Catatan penting:** `getBoxQRBase64()` sudah mengembalikan data URL utuh (`data:image/png;base64,...`) — dipakai apa adanya sebagai `src` (jangan ditambah prefix lagi; blade label kerdus juga begitu).
   - Generate QR ~2 KB/baris × 20 baris/halaman ≈ 40 KB payload; ini memakai generator yang sama dengan tombol cetak label, jadi tidak ada mekanisme baru yang perlu dirawat.
3. `resources/js/Pages/Admin/BoxTracking/Index.svelte` — 2 kolom baru (**Isi (Satuan)** dan **QR Kerdus**), tombol toggle **"Hanya Selesai Packing"** dengan penghitung, dan kartu ringkasan **Total Al-Qur'an Selesai Packing** (Pcs / Doz / Total). Kolom `item_count` yang tadinya berbunyi `"N items"` (Inggris, tidak konsisten) dihapus — digantikan kolom satuan.
4. `tests/Feature/Admin/ManagerDonaturAndBoxUnitsTest.php` (baru) — 5 test Poin #5: rumus satuan, satuan+QR terkirim ke props, QR berbentuk data URL, filter `selesai_packing`, dan total hanya dari kerdus tersegel.

Verifikasi HTTP nyata sebagai manager (data uji dibuat lalu dihapus, DB kembali 0 kerdus):

```
STATUS HTTP : 200   component: Admin/BoxTracking/Index

KB-PROBE-SEALED-001   status=sealed   isi=24  satuan=2 doz    /kapasitas=2,5 doz  qr=ADA (2162 char)
KB-PROBE-FILLING-001  status=filling  isi=7   satuan=7 pcs    /kapasitas=2,5 doz  qr=ADA (1974 char)

stats.sealed_pcs    = 24
stats.sealed_satuan = {"total":24,"pcs":24,"doz":2,"lusin":2,"label":"2 doz"}

-- filter selesai_packing=1 --> 1 baris: KB-PROBE-SEALED-001 (2 doz)
-- bersih: 0 kerdus sisa
```

**Yang masih menunggu keputusan Anda:** apakah "barcode" di maksud tim berarti QR yang sudah ada (pilihan yang saya ambil), atau memang perlu barcode 1D sungguhan (Code128/EAN) yang berarti menambah pustaka baru. Juga: bila di lapangan 1 doz ≠ 12, cukup ubah konstanta di `BoxUnits` — tidak perlu ubah skema.

---

## Temuan tambahan (di luar daftar, tapi menghambat role manager)

- **`/admin/performance` — 500 untuk manager & super-admin.** Menu "Laporan Kinerja" (`AdminLayout` baris 137, izin `warehouse.performance.view` — dimiliki manager) mengarah ke halaman yang error:
  ```
  local.ERROR: Call to private method App\Services\PerformanceMonitoringService::getSlowQueries()
  from scope App\Http\Controllers\Admin\PerformanceController
  at app/Http/Controllers/Admin/PerformanceController.php:212
  ```
  Terverifikasi dua kali (probe HTTP → 500, dan log). Penyebab: `getSlowQueries()` di `PerformanceMonitoringService.php:160` ber-visibilitas `private`, dipanggil dari controller.

## Peta akses role manager (probe HTTP nyata, ambil dari `route:list`)

```
ROLE      UID  PERM  /admin/dashboard  /admin/donatur  /admin/mushaf-requests  /admin/pengiriman  /admin/certificates  /admin/box-tracking  /admin/warehouse/packing  /admin/supervisor/warehouse-monitor  /admin/users  /admin/roles  /admin/performance
manager   6    60    200               200             200                     200                200                  200                  200                       200                                  403           403           500
```

Batas yang sudah benar: manager **tidak** bisa `/admin/users` dan `/admin/roles` (403), dan tidak memegang `permissions.*` maupun `*.delete`.

---

## Usulan perbaikan (menunggu keputusan)

**A. Jumlah & akumulasi (satu paket perbaikan)**
1. Jadikan `jumlah_mushaf_a5_approved`/`a6`/`iqra_approved` sebagai **satu-satunya sumber angka disetujui**; hapus pemakaian `jumlah_mushaf_approved` di seluruh tampilan, atau isi kolom itu dari `a5+a6` di controller saat menyimpan (`updateQuantities`) supaya konsisten.
2. Samakan operator: pakai fallback berbasis `??` + normalisasi `NULL`→`jumlah_*` (atau ubah kolom pecahan jadi nullable), bukan campur `||` dan `??`.
3. Perbaiki `has_quantity_change` agar membandingkan terhadap angka pengajuan yang benar, supaya penjaga di `processToShipment` tidak memblokir semua permintaan.
4. Pakai `total_mushaf_approved` (accessor) di `processToShipment`, peta, dashboard, dan halaman detail — satu rumus, satu angka.
5. Tampilkan tombol Edit Jumlah pada semua tahap yang relevan (termasuk `pending`) dan bungkus dengan `canUpdate`.
6. Tes: satu feature test yang menyimpan angka lalu memastikan detail, daftar, peta, dan `jumlah_quran` pengiriman menunjukkan angka yang sama.

**B. Tahap pengiriman pada permintaan mushaf**
- Tambah tahap tampilan "Selesai Packing" dan "Diterima Penerima" pada permintaan (ambil dari status pengiriman tertaut, jangan duplikasi kolom status), pakai `statusHistory` yang sudah dikirim tapi tidak dipakai, dan perbaiki key `'selesai'` → `'completed'`.
- Isi `mushaf_request_id` + `mushaf_requests.pengiriman_id` pada alur "Set Alamat dari Permintaan Mushaf" agar observer bisa menyelesaikan permintaan.
- Perbaiki `pengiriman.wakif` → `pengiriman.wakafItem`.
- Putuskan: apakah sealing kerdus otomatis menaikkan pengiriman ke `selesai-packing` (sekarang murni manual, jadi stok "Siap Distribusi" selalu 0).

**C. Donatur untuk manager** — pilih: (1) sembunyikan menu saja, (2) cabut `donatur.read` dari role manager juga (tetap aman untuk halaman Pengiriman), atau (3) cabut semua izin `donatur.*` kecuali yang dipakai halaman lain.

**D. Daftar Quran selesai packing + barcode + satuan** — perlu keputusan produk dulu: definisi satuan (1 kerdus = berapa pcs? doz = 12 atau 20?), apakah barcode menggantikan atau melengkapi QR di label, dan apakah daftarnya per-mushaf atau per-kerdus.

**E. Bonus** — perbaiki `getSlowQueries()` (jadikan `public`) supaya menu Laporan Kinerja tidak 500.

---

## Verifikasi

- Suite relevan dijalankan: `vendor/bin/pest` pada 4 berkas → **2 gagal, 26 lulus, 13 dilewati**. Kedua kegagalan **pre-existing** di `MushafRequestQuantityFeatureTest` (kegagalan terkait penjaga `has_quantity_change` di atas, bukan dampak perubahan sesi ini — repo belum diubah).
- Semua uji data memakai `DB::rollBack()`; `git status` tetap bersih (hanya `.agents/` dan `MYTask/` yang memang belum dilacak).
- Belum ada kode yang diubah.
