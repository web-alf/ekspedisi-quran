# Siapa yang Bertugas Generate QR dan Ubah Status Mushaf?

**Tanggal:** 2026-10-13
**Lingkup:** produksi `dash.ekspedisiquran.com` (`vps-187.77.113.89`, `/var/www/dash`) + kode di repo `main`
**Sifat:** temuan audit — belum ada perubahan kode maupun data.
**Lampiran gambar:** `Laporan/rantai-generate-qr-status-mushaf.png` (+ sumber `rantai-generate-qr-status-mushaf.html`)

## Jawaban singkat

| Tugas | Kode mengizinkan | Yang benar-benar mengerjakan |
|---|---|---|
| **Generate QR** | `qr.generate` → manager (4), warehouse (18), super-admin (2). Bulk (`warehouse.qr.bulk_generate`) → **hanya** warehouse + super-admin. | **Administrator System** (super-admin, id 1) dan **skrip backfill**, bukan staf gudang. Tombol manual pernah ditekan **Staff Gudang** (id 3, `gudang@ekspedisiquran.com`) 2× pada 2026-10-08. Dari tombol itu hanya **7 QR baru** yang tercipta; 26.106 berkas sisanya dibuat sekali jalan oleh perintah `qr:generate-missing` pada 2026-10-06 01:07–01:11. |
| **Ubah status mushaf** (`wakaf_items.status`) | **Tidak ada siapa pun.** Tidak ada rute, tombol, atau endpoint yang menulis kolom itu — di semua peran. | **Tidak ada.** 26.101 dari 26.101 item masih `pending`; `updated_at` terbaru 2026-10-01 18:19. |
| *(status yang benar-benar bergerak: `pengiriman.status_id`)* | `shipments.update-status` → warehouse, courier, manager, distribusi (0 user), super-admin. Batas pilihan: gudang hanya pemesanan/produksi/kedatangan/packing, kurir hanya pengiriman/batal. | **Tidak ada.** 26.116 dari 26.116 resi masih `pemesanan`. |
| *(interpretasi lain: status permintaan mushaf)* | `mushaf-requests.approve/reject/update` → customer-service (4), manager (4), super-admin (2). | **Tidak ada.** 50 dari 50 permintaan masih `pending`; `reviewed_by` dan `approved_at` terisi 0 baris. |

**Kesimpulan satu kalimat:** yang selama ini "mengerjakan" QR adalah **satu kali backfill oleh skrip dan tombol super-admin**, sedangkan **status mushaf tidak punya pemilik sama sekali** — kolomnya ada, tetapi tidak ada satu jalur pun di seluruh aplikasi yang menulisnya.

## Bagian 1 — apa yang KODE izinkan (peta izin)

Pemegang izin diambil dari **DB produksi**, bukan seeder (`role_has_permissions`).

Route QR (`routes/web.php:373–410`):

| Rute | Middleware | Pemegang di produksi |
|---|---|---|
| `POST qr/generate/{pengiriman}` | `permission:qr.generate\|warehouse.qr.generate` | super-admin, warehouse, manager |
| `GET qr/display`, `qr/download` | sama | super-admin, warehouse, manager |
| `POST qr/bulk-generate` | `permission:qr.bulk_operations\|warehouse.qr.bulk_generate` | super-admin, warehouse |
| `POST qr/verify` | `permission:qr.verify\|warehouse.qr.verify` | super-admin, warehouse, manager, supervisor |
| `thermal-print/*` | `qr.generate`/`warehouse.qr.generate` | super-admin, warehouse, manager |
| Perintah CLI `qr:generate-missing` | tanpa izin (CLI) | siapa pun yang punya akses shell |

`qr:generate-missing` **tidak** masuk `routes/console.php` — tidak ada jadwal untuknya, jadi QR hanya bertambah kalau ada orang menekan tombol atau menjalankan perintahnya.

Status mushaf / resi:

- `pengiriman/{id}/update-status` (form + POST), `pengiriman/scan-status/update`, bulk, dan form edit → `shipments.update-status` / `shipments.update` + batas pilihan di `config/pengiriman.php`.
- `wakaf_items` hanya bisa **diubah nama wakif & doa**-nya (`donatur.update` → customer-service + super-admin), dan itu pun hanya selagi `status = pending`. **Tidak ada jalur yang mengubah `status`.**

## Bagian 2 — apa yang DATA PRODUKSI tunjukkan

Diambil 2026-10-13 via tinker read-only.

**QR**

| Ukuran | Nilai |
|---|---|
| Resi total | 26.116 |
| Resi dengan `qr_code_path` terisi | 26.116 (100%) |
| Berkas QR ada di disk | 26.115 `.svg` + 1 `.png` = seluruhnya ada |
| Waktu berkas dibuat (mtime) | **26.106 berkas pada 2026-10-06 01:07–01:11** (4 menit: 01:07 = 4.284, 01:08 = 6.999, 01:09 = 6.934, 01:10 = 6.710, 01:11 = 1.179); 5 berkas pada 2026-10-12 08:59; 5 berkas tunggal lain pada 2026-09-02/25, 2026-10-02, 2026-10-05 |
| 5 berkas 2026-10-12 08:59 | dari `bulk-generate` oleh **user_id 1** (Administrator System) — tercatat di `laravel.log` |

Satu-satunya jejak pelaku di jalur tombol adalah baris log `laravel.log` (`admin@ekspedisiquran.com` id 1: 9×, manager id 6: 2×, staff gudang id 3: 2×) — **dan hanya 7 QR benar-benar tercipta dari 13 pemanggilan itu**, sisanya "skipped" karena QR-nya sudah ada.

**Status**

| Kolom | Sebaran di produksi |
|---|---|
| `wakaf_items.status` | pending **26.101** dari 26.101 |
| `pengiriman.status_id` | pemesanan **26.116** dari 26.116 |
| `mushaf_requests.status` | pending **50** dari 50 |
| `status_histories` | 26 baris, **semuanya** oleh Administrator System: 22 baris bercatatan "Uji …" (uji internal 2026-10-09) dan 4 baris "Bulk update status" (2026-09-25) |
| `packing_items` / `packing_boxes` | 0 / 0 baris (packing belum pernah jalan) |

Tidak ada satu pun baris jejak status yang dibuat oleh staf gudang, CS, kurir, atau manager.

## Temuan

1. **QR tidak punya kolom pelaku.** `pengiriman` menyimpan `created_by` (pembuat resi — di produksi 26.022 resi dibuat akun impor `db2@alfatihah.com`), tetapi tidak ada `qr_generated_by`/`qr_generated_at`. Pertanyaan "siapa yang membuat QR ini" hanya bisa dijawab dari log aplikasi dan mtime berkas.
2. **Angka QR 100% itu hasil skrip, bukan hasil kerja.** 26.106 dari 26.116 berkas (99,96%) lahir dalam 4 menit pada satu dini hari — tanda satu kali backfill (`qr:generate-missing`, commit `609caf9`), bukan pemindaian/tombol oleh 18 staf gudang.
3. **`wakaf_items.status` adalah kolom mati.** Tiga helper model (`markAsProcessed`, `markAsShipped`, `markAsDelivered`) tidak dipanggil di mana pun di `app/`, `routes/`, maupun frontend; statusnya hanya dipakai untuk label di `resources/js/Pages/Admin/Donatur/WakafItems/Index.svelte`.
4. **`qr.bulk_operations` tidak terdaftar di tabel `permissions` produksi.** Middleware rutenya `permission:qr.bulk_operations|warehouse.qr.bulk_generate`, jadi penjaga sebenarnya hanya izin kedua. Diuji dengan akun gudang nyata (`gudang@ekspedisiquran.com`): `hasAnyPermission('qr.bulk_operations','warehouse.qr.bulk_generate')` → BOLEH, tanpa exception. Bukan bug hari ini, tetapi izin pertama tidak bisa diberikan ke peran lain sampai ia didaftarkan.
5. **Rantai berhenti di langkah sebelum QR dipakai.** Halaman packing hanya menawarkan resi berstatus `packing` dan `assignPengirimanOnScan()` menolak resi lain, sementara 26.116 resi masih `pemesanan` — lihat `Laporan/Siapa-Penanggung-Jawab-Packing-2026-10-06.md`. Jadi QR yang sudah lengkap pun belum bisa dipakai gudang.

## Rekomendasi (belum diterapkan)

1. Tambahkan `qr_generated_by` + `qr_generated_at` pada `pengiriman` (atau tulis baris di `status_histories`) dan isi dari `QrCodeService`, supaya tiap QR punya pelaku yang bisa diaudit.
2. Jadwalkan `qr:generate-missing` (mis. harian 01:00) agar resi baru selalu ber-QR tanpa bergantung pada satu akun admin; hapus kebutuhan backfill manual.
3. Putuskan nasib `wakaf_items.status`: dipakai sungguhan (tulis di jalur packing/penutupan) atau dibuang dari UI supaya tidak terbaca sebagai pekerjaan yang belum selesai.
4. Daftarkan izin `qr.bulk_operations` (atau hapus dari middleware) agar peta izin tidak memuat nama yang tidak ada di DB.

## Catatan perubahan: QR backfill dihapus untuk simulasi (2026-10-13 07:53 WIB)

Sesuai permintaan, QR hasil backfill dihapus supaya alur generate QR bisa disimulasikan dari nol. **Diagram di lampiran menggambarkan kondisi SEBELUM perubahan ini.**

| Hal | Nilai |
|---|---|
| Berkas dihapus | 26.106 berkas ber-mtime 2026-10-06 01:07–01:11 (41,15 MB), 0 gagal |
| Kolom dinolkan | `qr_code_path` + `qr_code_data` pada **26.106 baris** (lewat query builder → `updated_at` tidak berubah, max tetap `2026-10-12 12:15:26`) |
| Tidak disentuh | 10 resi ber-QR di luar jendela (1 `.png` lama `EQ-2026-21532`, 2 resi 2026-09-25, 1 resi 2026-10-02, 1 resi 2026-10-05, 5 resi 2026-10-12) + 80 berkas yatim (`.png` bernama cap-waktu, mtime 2026-09-02 21:10, tidak dirujuk baris mana pun) |
| Status resi | tidak disentuh: 26.116 masih `pemesanan` |
| Backup | `/root/dash-pengiriman-qr-20261013-075123.sql.gz` — isi dump diverifikasi: 26.100 pemisah baris + 16 pernyataan INSERT = **26.116 baris** |
| Snapshot sasaran | `/root/qr-hapus-20261013-075341.csv` (26.106 baris: `id, no_resi, qr_code_path, qr_code_data, mtime`) |
| **Undo** | `sudo -u www-data HOME=/tmp php artisan tinker --execute="require '/var/www/dash/storage/app/qr-undo/undo-qr.php';"` (ada README di folder yang sama). Terbukti byte-identik: uji bolak-balik 1 resi mengembalikan md5 `914971d2…` yang sama, lalu dihapus lagi |
| Gelombang kedua | 13 resi sisa dihapus juga (`EQ-2025-00001…00003`, `EQ-2026-21530/21531/21532/21534/21540/21546…21550`), termasuk 1 berkas `.png` ber-cap-waktu. Kondisi sekarang: **0 resi ber-QR** |
| Snapshot gabungan | `/var/www/dash/storage/app/qr-undo/qr-hapus-lengkap-20261013-080133.csv` — **26.116 baris**, divalidasi cocok 1:1 dengan seluruh resi yang kini tanpa QR (0 kurang, 0 lebih) |
| Catatan `updated_at` | 4 resi punya `updated_at` baru (3 dari uji tombol 07:54:57, 1 dari uji bolak-balik 08:01:46). **Itu satu-satunya jejak yang ditinggalkan penekanan tombol** — tidak ada baris pelaku. Baris lain tetap `2026-10-12 12:15:26` |

Uji tombol lewat **endpoint asli** (bukan panggilan model) sebagai akun staf gudang nyata `gudang@ekspedisiquran.com` (id 3, role `warehouse`):

```
POST /admin/qr/generate/1  → HTTP 200 {"success":true,"message":"QR Code untuk resi EQ-2025-00001 berhasil dibuat."}
   kolom : qr_code_path=public/qr-codes/QR-EQ-2025-00001.svg | qr_code_data=EQ-2025-00001
   berkas: ADA | md5 914971d2793cbdc6542f30933e3c3403 | 1663 byte   ← sama dengan md5 sebelum penghapusan
```

- Halaman pelacakan publik (`/tracking/EQ-2025-00004`) tetap **HTTP 200**; blok QR hilang tanpa galat (bukan gambar rusak).
- **Kondisi akhir 2026-10-13 08:01 WIB: 0 dari 26.116 resi ber-QR**, 0 kolom terisi, 80 berkas yatim lama (`.png` 2026-09-02, tidak dirujuk baris mana pun) tetap di folder. Status resi tidak disentuh (26.116 `pemesanan`). Jalur keluar: tombol generate di `/admin/pengiriman?mode=generate-qr` atau `php artisan qr:generate-missing` (±2 menit untuk 26.116 resi).
- **Pelaku tidak terbentuk.** Menekan tombol generate **tidak** menulis baris pelaku: `pengiriman` tidak punya `qr_generated_by`/`qr_generated_at`, dan jalur per-resi tidak menulis log. Satu-satunya jejak dari uji tombol adalah `updated_at` 4 resi + mtime berkasnya. Hanya endpoint bulk yang mencatat `user_id` di `laravel.log` (13 pemanggilan historis). Jadi tujuan "membuktikan pelaku QR terbentuk" hanya tercapai setelah kolom pelaku ditambahkan (rekomendasi 1).

## Catatan perubahan 2: kolom Penerima di tab Generate QR dikosongkan (2026-10-13 08:31–08:36 WIB)

Keluhan: kolom "Penerima" di `/admin/pengiriman?mode=generate-qr` masih menampilkan nama lembaga
(TPQ/Masjid/Sekolah) padahal belum ada permintaan mushaf yang di-approve.

| Hal | Nilai |
|---|---|
| Sumber nilai itu | `PengirimanController.php:915` — `'nama_lembaga' => $item->mushafRequest?->nama_lembaga ?? $item->nama_lembaga` |
| Relasinya | `Pengiriman::mushafRequest()` = `hasOne(MushafRequest::class, 'pengiriman_id')` — **reverse lookup**, jadi tidak bergantung pada `pengiriman.mushaf_request_id` |
| Arah maju dibersihkan | 15 resi: `mushaf_request_id` + `nama_lembaga` dinolkan (snapshot `/var/www/dash/storage/app/qr-undo/penerima-hapus-20261013-083141.csv`) |
| Arah balik dibersihkan | 15 permintaan `pending`: `pengiriman_id` dinolkan (snapshot `link-balik-hapus-20261013-083555.csv`) |
| Penjaga | Skrip arah balik BATAL bila ada satu permintaan berstatus bukan `pending`; arah maju BATAL bila jumlah sasaran bukan 15 |
| Tidak disentuh | 50 permintaan mushaf (tetap `pending`, `updated_at` tetap), 26.116 resi (jumlah tetap), `nama_penerima` (memang sudah 0 terisi) |
| Backup | `/root/dash-pengiriman-penerima-20261013-083110.sql.gz` (26.101 pemisah + 15 INSERT = 26.116 baris) |
| Undo | `undo-penerima.php` (arah maju) + `undo-link-balik.php` (arah balik) di `storage/app/qr-undo/` |
| Tampilan | `GenerateQR.svelte`: sel Penerima & pratinjau memakai `'-'` (bukan lagi "Data tidak tersedia"), sehingga sel kosong terbaca kosong. Diuji 5 tes vitest; mutasi (kembalikan placeholder lama) membuat tes gagal |

Bukti akhir (dibaca dari DOM sebagai akun staf gudang): kolom Penerima = `- / -` di seluruh 20 baris,
tidak ada nama lembaga maupun teks "Data tidak tersedia".

**Catatan perilaku nyata:** pada 2026-10-13 08:23:13 dan 08:24:27 akun **Staff Gudang**
(`gudang@ekspedisiquran.com`) memakai tombol Bulk Update yang baru diperbaiki — 20 resi
`pemesanan → produksi` lalu 20 resi `produksi → kedatangan` (40 baris `status_histories`).
Ini pemindahan status organik PERTAMA oleh staf gudang; sebelumnya seluruh pergerakan berasal dari
skrip/uji administrator.

## Bagaimana ini diuji

- Probe read-only via tinker: `sudo -u www-data HOME=/tmp php artisan tinker --execute="require '/tmp/probe-qr-status*.php';"` (skrip di `/tmp` server; tidak ada penulisan data).
- Peta izin dari `roles`/`permissions`/`role_has_permissions` produksi, bukan dari seeder.
- Jejak QR dari `laravel.log` + mtime berkas `storage/app/public/qr-codes`.
