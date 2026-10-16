# Siapa yang Bertanggung Jawab Melakukan Packing?

**Tanggal:** 2026-10-06 (revisi kedua)
**Lingkup:** produksi `dash.ekspedisiquran.com` + kode di repo `main`
**Sifat:** temuan audit — belum ada perubahan kode.

## Jawaban singkat

| Peran | Tanggung jawab | Bukti |
|---|---|---|
| **Staf Gudang** (role `warehouse`, 18 orang) | **Mengerjakan** packing: pindai QR, isi kerdus, segel | `warehouse.packing.view/scan/seal` |
| **Supervisor Gudang** (id 4) | **Menugaskan** target harian + memantau — tidak mengerjakan | `supervisor.warehouse.assign`; 8 penugasan tercatat |
| **Manager Distribusi** | Memantau. **Tidak** menerima tugas packing | tanpa `warehouse.packing.scan` |
| **Kurir** | Tidak terlibat; mulai bekerja **setelah** packing selesai | tanpa `warehouse.*` |

Alur yang dimaksud kode: **Supervisor menugaskan → Staf Gudang mengerjakan (free-pick) → Supervisor memantau.**

## Temuan inti: alurnya belum pernah sampai ke tahap packing

`packing_items` = **0 baris** sepanjang riwayat sistem. Tabel inilah satu-satunya
yang mencatat pelaku (`packed_by`).

**Penyebabnya bukan kerusakan di modul packing, melainkan alur yang tidak pernah
sampai ke tahap itu.** Halaman packing hanya menawarkan resi berstatus `packing`:

```php
// PackingAssignmentService::getAvailablePengiriman()
Pengiriman::where('status_id', $packingStatusId)   // $packingStatusId = slug 'packing'
```

Dan pemindaian menolak resi yang belum berstatus itu:

```php
// PackingAssignmentService::assignPengirimanOnScan()
if ($pengiriman->status_id !== $packingStatusId) {
    throw new \Exception('Pengiriman ini tidak dalam status packing.');
}
```

**Diuji langsung di basis data lokal** (salinan produksi, nol risiko): penugasan dari
Supervisor **berhasil** (`assigned_by=4` terisi), gate `scanItems` **BOLEH**, tetapi
saat memindai resi `EQ-2025-00001` hasilnya:

```
respons: success=false | Pengiriman ini tidak dalam status packing. | type=assignment_error
```

**Sebabnya: 26.097 dari 26.099 resi masih berstatus `pemesanan`.** Alur statusnya
`pemesanan → produksi → kedatangan → packing`, jadi tiga langkah perpindahan harus
terjadi lebih dulu — dan tidak ada yang melakukannya.

`kedatangan` bahkan disebut "ready to pack" di kode (`getWarehouseStockInfo`:
`'Incoming stock (status: kedatangan - ready to pack)'`), **tetapi alur packing tidak
mengenalinya sama sekali** — nol kemunculan `kedatangan` di seluruh
`PackingAssignmentService`, `PackingController`, dan `BoxBasedAssignmentService`.

### Staf gudang tidak berwenang memindahkan statusnya sendiri

| | `shipments.update-status` |
|---|---|
| `warehouse` (18 orang) | **tidak punya** |
| super-admin, manager, CS, kurir | punya |

Jadi orang yang mengerjakan packing **tidak bisa menandai barangnya siap dikemas**,
sementara orang yang bisa menandai tidak mengerjakannya. Inilah simpul yang
membuat seluruh alur berhenti di langkah pertama.

## Bukti pendukung

### 10 resi pernah bergerak — lewat perubahan status massal

| Status | Jumlah resi |
|---|---|
| Proses Pemesanan | 26.097 |
| Selesai Packing | 7 |
| Proses Pengiriman | 4 |
| Proses Packing | 3 |

Sepuluh resi itu bergerak pada `2026-10-03 12:51:56` — **detik yang sama persis** —
dengan `tracking_history` berbunyi `keterangan: "Bulk update status"`, `lokasi: "Gudang A"`,
oleh user id 1 (Administrator). Itu perubahan status massal lewat skrip/admin,
bukan kerja pindai di lantai gudang. Kolom `pengiriman` juga **tidak menyimpan kerdus**.

### Penugasan otomatis sudah diperbaiki untuk manager — panelnya belum

Perbaikan di `PackingAssignmentService::assignDailyAssignment` (`7522bb4`, sudah ada
di produksi) memakai izin memindai, bukan izin melihat:

```php
// Sebelumnya `warehouse.dashboard` — manager ikut dapat tugas packing tiap hari.
$warehouseUsers = User::permission('warehouse.packing.scan')->where('is_active', true)->get();
```

Efeknya terlihat: manager tidak lagi menerima tugas dari job 06:00.

**Namun tidak konsisten.** Panel penugasan manual di `WarehouseMonitorController`
masih memakai izin melihat (`getWarehouseUsers()` → `User::permission('warehouse.dashboard')`)
di **tiga tempat** (baris 120, 621, 740):

| Izin | Role yang memegangnya |
|---|---|
| `warehouse.dashboard` | super-admin (2), **warehouse (18)**, **manager (4)** = 24 |
| `warehouse.packing.scan` | super-admin (2), warehouse (18) = 20 |

Manager masih bisa dipilih sebagai penerima tugas dari panel supervisor, walau job
otomatis sudah mengabaikannya — dan endpoint `start-scanning` akan menolak manager
dengan 403. Tugas yang tidak bisa dikerjakan bukan tugas.

### 96% tugas tanpa penugas — dan itu wajar

- Penugasan manual lewat supervisor: **8 tugas**, `assigned_by = 4`
- Sisanya **182 dari 190 (96%)** `assigned_by = NULL`

182 itu tetap berstatus `assigned`, jadi bukan dari tombol "Mulai Memindai" (tombol
itu menolak staf tanpa tugas: `Tidak ada tugas untuk hari ini`, HTTP 404). Asalnya
adalah job terjadwal **`packing:daily-assignment` pukul 06:00** (`routes/console.php`):

```php
Schedule::command('packing:daily-assignment')->dailyAt('06:00')
```

Job otomatis memang **tidak punya penugas manusia** — jadi `assigned_by = NULL` di
situ bukan kelalaian, melainkan **tidak dibedakannya penugasan otomatis dari manual**.
Hanya job ini yang tidak mengisi `assigned_by`; jalur manual
(`PackingAssignmentService:191,205` dan `BoxBasedAssignmentService:113`) mengisinya.

### Kemacetan operasional

| Fakta | Angka |
|---|---|
| Tugas kedaluwarsa (`expired`) | **169 dari 190** |
| Tugas dengan `total_selesai > 0` | **0** |
| Masih `in_progress` | 1 (`Staff Gudang`) |

Tugas menumpuk tiap hari, tak satu pun mencatat kemajuan, lalu kedaluwarsa.
Sesuai dengan temuan inti: tidak ada yang bisa dikerjakan karena tidak ada resi
yang berstatus `packing`.

## Kesimpulan

1. **Pertanggungjawaban packing belum pernah terbentuk** — `packing_items` kosong.
2. **Sebabnya berantai, bukan satu bug:** resi tidak pernah mencapai status `packing`,
   dan **staf gudang tidak berwenang memindahkan status** untuk mencapai tahap itu.
3. **Penugasan otomatis sudah benar** (manager dikecualikan), tetapi **panel manual
   belum** — manager masih bisa ditugasi hal yang pasti ditolak.
4. **`assigned_by = NULL` bukan cacat** pada tugas otomatis; yang kurang adalah
   penanda otomatis-vs-manual.

## Hasil uji putaran packing nyata — 6 Okt 2026, 00:48 WIB

Dijalankan di **produksi**, atas persetujuan pemilik. Skrip menjalankan alur
sungguhan lewat endpoint HTTP (middleware & CSRF ikut jalan), bukan menulis
langsung ke tabel.

**Hasil: alur packing BERFUNGSI dan jejak pertanggungjawaban TERBENTUK.**

| Yang dibuktikan | Hasil |
|---|---|
| Penugasan dari service | tugas id 220, target 20, `assigned_by=3` terisi |
| Resi dimajukan `pemesanan` → `packing` | EQ-2026-21532, urutan 1 → 4 (maju, sesuai aturan) |
| Pindai resi (endpoint sungguhan) | **3 dari 3 berhasil** — HTTP 200, `success=true` |
| `packing_items` terbentuk | **0 → 3 baris**, semua `packed_by=3` (Staff Gudang) |
| Kerdus dibuat otomatis | `KB-20261006-003-A5-01`, isi 3/20, status `filling` |
| Kapasitas sesuai jenis | A5=20 · A6=40 · Iqro=160 ✓ |
| Halaman packing (UI) | HTTP 200, `currentBox` menampilkan kerdus & progres 15% |

| No. Resi | Kerdus | Pengerja | Urutan |
|---|---|---|---|
| EQ-2026-21530 | KB-20261006-003-A5-01 | Staff Gudang (id 3) | 1 |
| EQ-2026-21531 | KB-20261006-003-A5-01 | Staff Gudang (id 3) | 2 |
| EQ-2026-21532 | KB-20261006-003-A5-01 | Staff Gudang (id 3) | 3 |

Bagan alur: `Laporan/alur-packing.html` + `Laporan/alur-packing.png`.

### Yang masih perlu keputusan

Uji ini **membuktikan alurnya jalan, tetapi belum menyelesaikan penghambatnya.**
Saya memajukan status resi satu per satu khusus untuk uji. Untuk operasional
sehari-hari, **tidak ada yang menjembatani** `pemesanan → packing` secara massal
(lihat rekomendasi kedua di atas).

Satu temuan tambahan: **hanya 5 dari 26.111 resi yang punya QR.** Halaman packing
mensyaratkan `qr_code_path` terisi, jadi meski status resi sudah dimajukan, resi
tanpa QR tetap tidak akan muncul untuk dipindai. Dua hal ini harus beres bersamaan
sebelum packing bisa jalan massal.

### Membatalkan uji

Data uji **sengaja dibiarkan** agar bisa diperiksa di UI. Membatalkan:

```bash
ssh ekspedisi-prod 'sudo -u www-data php /tmp/eq-uji-batal.php'
```

Backup sebelum uji: `/root/eq-uji-packing-20261006-004836.sql` (34,9 MB, 49 tabel).
Skrip uji sudah dihapus dari server; skrip pembatal disimpan.

## Rekomendasi — urutan pengerjaan

**Pertama (membuka jalan): jalankan satu putaran packing nyata dari ujung ke ujung,
sekecil mungkin.** Majukan 5–20 resi `pemesanan → produksi → kedatangan → packing`,
lalu minta satu staf gudang memindainya. Ini sekaligus membuktikan tiga hal yang
belum terbukti:

- apakah alur packing benar-benar berfungsi di produksi (di lokal: ya, setelah status benar)
- apakah `packing_items` benar-benar terisi, artinya pertanggungjawaban berjalan
- apakah kapasitas kerdus sesuai jenis (A5 20 / A6 40 / Iqra 160)

**Kedua: putuskan siapa yang berwenang memajukan status ke `packing`.** Ini simpul
yang menghentikan seluruh alur. Tiga pilihan:

- beri `warehouse` izin `shipments.update-status`, atau
- majukan status otomatis saat packing dimulai, atau
- terima `kedatangan` sebagai "siap packing" seperti yang sudah tertulis di kode

**Ketiga: samakan panel supervisor dengan job otomatis** — ubah
`getWarehouseUsers()`/baris 120/740 dari `warehouse.dashboard` ke
`warehouse.packing.scan` agar manager tidak lagi bisa ditugasi pekerjaan yang
pasti ditolak.

**Keempat: bedakan penugasan otomatis dari manual.** Jangan wajibkan `assigned_by`
pada job terjadwal; cukup beri penanda sumber penugasan agar laporan tidak
membacanya sebagai "tanpa penanggung jawab".

Semua di atas **belum diubah di kode.**
