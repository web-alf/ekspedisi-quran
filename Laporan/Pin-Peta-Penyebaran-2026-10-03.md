# Pin Lokasi pada Peta Penyebaran Distribusi Al-Qur'an

**Tanggal:** 2026-10-03
**Ruang lingkup:** halaman depan (`resources/js/Pages/Landing.svelte`) + muatan data peta (`routes/web.php`)
**Status:** diterapkan lokal, sudah diuji; **belum di-deploy**

---

## 1. Yang diminta

> "saya ingin di Peta Penyebaran Distribusi Al-Qur'an ada icon pin google maps gitu… memungkinkan gak?"
> "mark provinsinya tetap seperti sekarang tetap di pertahankan. bisa?"
> "icon pin nya kok kepotong ya?"
> "gabungkan opsi keduanya deh ya."
> "perbaiki dan saya cocok dan implementasikan ya"

Empat hal: (1) pin lokasi di peta, (2) pewarnaan provinsi **tetap** seperti sekarang, (3) pin tidak terpotong, (4) kedua pilihan digabung jadi satu perilaku, (5) koordinat rusak diperbaiki.

---

## 2. Hasil akhir

Peta sekarang punya **dua lapisan sekaligus**:

| Lapisan | Keadaan |
|---|---|
| Provinsi (choropleth) | **Tidak diubah.** Warna, ambang (10/20/50/100/200/500/1000), legenda, tooltip "Total Mushaf / Jumlah Lembaga", garis merah saat kursor, klik untuk memperbesar — semuanya seperti semula |
| Pin lokasi (baru) | Bentuk tetes air, warna per kategori lembaga, klik → detail sampai kelurahan/desa |

Ditambahkan juga **legenda kategori** di bawah peta.

---

## 3. "Gabungkan opsi keduanya" → satu perilaku otomatis

Tadinya dua tombol mode yang harus dipilih pengunjung. Sekarang tidak ada tombol mode: **pin terpisah saat peta besar, bergabung sendiri saat ruangnya sempit**, dan memisah lagi begitu diperbesar.

**Bukti pengukuran** (data uji lokal, 15 lembaga):

| Zoom | Pin | Angka pada pin |
|---|---|---|
| 5 (seluruh Indonesia) | 1 | `14` |
| 6 | 2 | `5`, `9` |
| 8 | 12 | `2`, `2` |
| 9–13 | 13 | `2` |
| 14–16 | 14 | — (semua sendiri) |

Keputusan penting: **penggabungan diukur dalam piksel layar, bukan derajat.** Kalau memakai derajat, titik-titik di Jawa tetap menumpuk saat peta diperkecil dan titik di pulau terpencil ikut tergabung tanpa alasan. Dengan ukuran piksel, aturannya satu: *pin yang tidak muat berdampingan tanpa saling menutup, digabung.*

---

## 4. Tiga cacat yang ditemukan dan diperbaiki

### a. Pin terpotong → `viewBox` hilang

Di versi yang bisa diperkecil, tag `<svg>` ditulis ulang untuk memberi ukuran **tanpa membawa `viewBox`**. Koordinat gambar lalu diperlakukan sebagai piksel apa adanya: gambar 26×34 dipaksa masuk kotak 20×30, ujung bawah pin terpotong.

| | Sebelum | Sesudah |
|---|---|---|
| `viewBox` | tidak ada | `0 0 26 34` |
| Kotak pin | 20 × 30 | 20 × 30 |
| Gambar pin | 24 × 33 (lebih besar dari kotak) | 18,7 × 25,5 |
| Terpotong | **ya** | **tidak** |

### b. Pin gabungan tanpa angka → menyesatkan

Sempat dihilangkan supaya semua pin seragam bentuknya. Ternyata pin berisi 14 lembaga terlihat **persis** seperti pin 1 lembaga. Angka dikembalikan; sekarang pin `14` vs pin berlubang putih jelas berbeda.

### c. `gambarPin()` bisa mengembalikan `undefined`

Saat pin disembunyikan, fungsi keluar lebih awal tanpa nilai, lalu pemanggil membaca `.length` dari `undefined` → `TypeError`. Akibatnya lapisan pin terhapus tetapi teks keterangan tidak ikut berubah. Sekarang selalu mengembalikan array.

---

## 5. Koordinat rusak di produksi

### Yang ditemukan

Audit seluruh 29 titik berkoodinat di produksi: **27 sah, 2 bermasalah.**

| id | Lembaga | Masalah |
|---|---|---|
| 19 | SD NEGERI DEMAAN (Demaan, Jepara) | `longitude = -6.59960511` — **sama dengan latitude**; titiknya jatuh di Afrika |
| 28 | TEST (data uji, dibuat 2026-08-07) | Provinsi "KEPULAUAN BANGKA BELITUNG" tetapi koordinatnya di Kudus |

**Akibat berantai titik rusak:** `fitBounds` menghitung skala untuk rentang sebesar dunia, lalu dijepit ke pembesaran minimum — **seluruh pin menumpuk jadi satu gumpalan** (dialami langsung pada percobaan pertama). Karena itu penjagaannya wajib, bukan pilihan.

### Yang dikerjakan

**id 19 diperbaiki** — bujur yang benar `110.65751`. Dasarnya: lintang yang ada (`-6.59958`) sudah cocok dengan Demaan, Jepara; hanya bujurnya yang salah. Nilai pembanding dari kelurahan Demaan menurut OpenStreetMap (`-6.5992266, 110.6575102`).

- Cadangan nilai sebelum: `/root/eq-titik-peta-backup-20261003.json`
- Verifikasi setelah perbaikan: audit ulang → **28 sah, 1 sisa**

**id 28 (data uji "TEST") tidak diubah** — bukan koordinat rusak (berada di Indonesia), hanya provinsinya tidak cocok dengan koordinatnya. Dibuat 7 Agustus 2026, sebelum pekerjaan ini. **Menunggu keputusan: dihapus atau dibiarkan.** Kalau dibiarkan, peta publik akan menampilkan kotak "TEST" di Kudus dengan label provinsi Bangka Belitung.

### Peringatan tidak ditampilkan ke publik

Peta publik **tidak** menampilkan kotak peringatan apa pun tentang titik yang dibuang. Pengunjung hanya melihat data bersih; jejaknya masuk `console.warn` untuk pengelola.

---

## 6. Perubahan berkas

| Berkas | Isi |
|---|---|
| `resources/js/utils/mapMarkers.js` **(baru)** | Logika pin: kategori & warna, batas Indonesia, pemisahan titik rusak, pengelompokan per piksel, penyusunan popup, pembuatan ikon pin |
| `resources/js/Pages/Landing.svelte` | Dua lapisan peta, `pasangPin()`, `gambarPin()`, legenda kategori, CSS pin & popup, keterangan diperbarui |
| `routes/web.php` | Muatan peta menambah `kecamatan`, `kelurahan_desa`, `kode_pos`, dan pecahan `a5/a6/iqra` |
| `tests/Unit/mapMarkers.test.js` **(baru)** | 32 tes |
| `tests/Feature/Landing/LandingMapDataTest.php` **(baru)** | 3 tes |

**Catatan keamanan:** setiap nilai dari isian pengguna (nama lembaga, kelurahan, kecamatan) diloloskan sebelum masuk popup. Nama lembaga berasal dari formulir publik permintaan mushaf — tanpa pelolosan, satu kiriman berisi tag skrip akan dijalankan di halaman depan. Ada 3 tes khusus untuk ini.

---

## 7. Pengujian

**JS:** `npx vitest run` → **121 lulus** (89 sebelumnya + 32 baru).

**Bukti-gagal** (penjagaan dilepas satu per satu → tes harus GAGAL):

| Penjagaan dilepas | Hasil |
|---|---|
| batas wilayah Indonesia | 4 tes gagal |
| `viewBox` pada pin | 2 tes gagal |
| angka pada pin gabungan | 1 tes gagal |
| pelolosan HTML | 4 tes gagal |
| pengelompokan per piksel | 2 tes gagal |

**PHP:** 3 tes baru lulus (60 assertion); bukti-gagal untuk ketiganya memakai mutasi `routes/web.php` — setiap mutasi menjadikan tes gagal, berkas kembali utuh (dibuktikan `diff -q` → SAMA).

**Suite penuh `tests/Feature/`:** diukur ulang dengan benar (baseline dibuat dengan mengembalikan `Landing.svelte` + `routes/web.php` ke commit sebelumnya, berkas baru disingkirkan):

| Run | Hasil |
|---|---|
| A — baseline (tanpa perubahan ini) | 74 gagal, 402 lulus |
| B — sesudah (dengan perubahan ini) | 76 gagal, 403 lulus |
| Kode yang sama, diulang | **74, lalu 75** |

**Suite ini flaky.** Kode yang sama persis menghasilkan 74 dan 75 kegagalan di dua run berturut-turut. Perbedaan A/B (2 tes) **lebih kecil** daripada goyangan run-ulang (1 tes, bahkan pernah terukur 4), jadi tidak bisa disimpulkan sebagai regresi. Tes yang sempat muncul sebagai "gagal hanya di B" (`it menyelesaikan permintaan mushaf ketika pengiriman berstatus diterima`) terbukti **lulus** di kedua run-ulang.

**Yang benar-benar bisa disimpulkan:** 3 tes baru lulus; 32 tes JS baru lulus; tidak ada tes yang gagal **karena kode peta** — tes peta (`tests/Feature/Landing/`, `MushafRequestAutoCompleteTest`, `tests/Feature/Imports/`) **74 lulus tanpa satu pun gagal** saat dijalankan bersama. Sisanya kegagalan bawaan pada basis data tes yang rapuh.

**Cacat yang terbukti sudah ada sebelumnya:**

- `WarehouseMonitorTest` — `Unknown column 'assignment_method'`; penyebabnya basis data tes tertinggal separuh termigrasi saat run kehabisan memori. Kolomnya ada di skema (`SHOW COLUMNS` → ADA).
- `ThermalPrintBoxViewTest` — 2 tes gagal (302 bukan 200)
- Fatal Mockery di `Tests\Feature\Admin\QrBulkPermissionTest` (membatalkan seluruh run)
- `tests` kehabisan memori pada `memory_limit` 128M
- Error `localStorage` di `AdminMenuLabel.test.js` (4 tesnya sendiri lulus)

**Verifikasi di aplikasi sungguhan** (server PHP lokal + data uji sementara yang sudah dikembalikan):

- 32 poligon provinsi + pin di atasnya, 15 lembaga (1 titik rusak uji dibuang)
- Pin `8` + `3` + 4 tunggal; setelah diperbesar → `7`, `2`, `2` + tunggal
- Semua pin `viewBox="0 0 26 34"`, `terpotong: false`, tidak ada yang keluar batas peta
- Popup kelompok: "4 lembaga berdekatan" + daftar 4 lembaga dengan kecamatan/provinsi + Total 400 mushaf
- Popup tunggal: kategori, nama, kelurahan, kecamatan, kabupaten, provinsi, kode pos, jumlah, pecahan A5/A6
- Legenda kategori tampil; legenda provinsi tetap ada; tidak ada overflow horizontal
- **0 galat JS**

---

## 8. Yang belum dikerjakan

1. **Belum di-commit / di-push / di-deploy.** `public/build` sudah di-build (wajib ikut karena di-track git dan ikut ter-rsync ke `dash`).
2. **Data uji "TEST" (id 28)** di produksi belum diputuskan.
3. **Kategori "Masyarakat/Jamaah Alfatihah" dan "Lembaga Lainnya"** memakai warna bawaan abu-abu. Kalau perlu warna sendiri, tinggal tambah di `WARNA_KATEGORI`.
