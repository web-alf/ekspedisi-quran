# Audit Struktur Role & Izin — Ekspedisi Quran (dash)

**Tanggal:** 3 Oktober 2026
**Target:** `dash.ekspedisiquran.com` — database `db_ekspedisi_quran_dash` (produksi harian)
**Metode:** kueri langsung ke database produksi + `route:list` produksi + pembandingan dengan seeder di database bersih
**Sifat:** baca saja — tidak ada satu pun perubahan yang ditulis ke produksi

---

## 1. Ringkasan

| Angka | Nilai |
|---|---|
| Role | 6 |
| Izin | 82 |
| User | 40 |
| Route admin | 256 (130 GET) |
| Izin yatim (tak dipakai role mana pun) | **0** ✅ |
| Izin langsung ke user (di luar role) | **0** ✅ |
| Role yang **menyimpang** dari kode | **1** — `super-admin` ⚠ |

**Kabar baiknya:** struktur izinnya rapi. Tidak ada izin yatim, tidak ada izin yang ditempelkan langsung ke orang (semua lewat role, sehingga mudah diaudit), dan 5 dari 6 role **persis sama** dengan yang didefinisikan di kode. Produksi dan kode juga sinkron: 82 izin di dua-duanya.

**Masalahnya ada dua, dan keduanya soal hierarki** — bukan soal data yang hilang.

---

## 2. Enam role

| ID | `name` (kunci sistem) | Nama tampil | Izin | Orang | Peran sebenarnya |
|---|---|---|---|---|---|
| 1 | `super-admin` | Super Admin | 82 | 2 | Pengelola sistem |
| 2 | `customer-service` | Customer Service | 13 | 4 | Pengelola donatur |
| 3 | `warehouse` | Staff Gudang | 31 | 18 | Pelaksana pengemasan |
| 4 | `supervisor` | Supervisor Gudang | 20 | 3 | Pengatur tugas gudang |
| 5 | `courier` | Kurir | 7 | 9 | Pembaru status kiriman |
| 6 | `manager` | Manager | 55 | 4 | Pengelola alur distribusi |

---

## 3. Siapa bisa membuka berapa halaman

Dihitung dari 128 halaman admin yang dijaga izin:

```
super-admin        128 dari 128   (100%)   ← lolos lewat Gate::before
manager             86 dari 128   ( 67%)
warehouse           51 dari 128   ( 40%)
supervisor          43 dari 128   ( 34%)
customer-service    20 dari 128   ( 16%)
courier             17 dari 128   ( 13%)
```

---

## 4. TEMUAN

### ⚠️ TEMUAN 1 — `super-admin` memegang 10 izin yang kode secara tegas LARANG

**Ini temuan terpenting.** Produksi punya 82 izin untuk super-admin; seeder hanya memberi **72**.

Yang berlebih, dan semuanya ada di daftar larangan kode (`AppServiceProvider::WAREHOUSE_OPERATIONAL_PERMISSIONS`):

| Izin | Kenapa bermasalah |
|---|---|
| `warehouse.packing.scan` | Mengemas |
| `warehouse.packing.view` | Membuka halaman pengemasan |
| `warehouse.packing.seal` | Menyegel kerdus |
| `warehouse.boxes.view` | Melihat kerdus |
| `warehouse.boxes.seal` | Menyegel kerdus |
| `warehouse.tasks.update` | Mengubah tugas gudang |
| `warehouse.tasks.view` | Melihat tugas gudang |
| `warehouse.qr.generate` | Membuat QR |
| `warehouse.qr.scan` | Memindai QR |
| `warehouse.qr.verify` | Memverifikasi QR |

**Kenapa ini penting.** Seeder menuliskan niatnya dengan jelas:

```php
// Super Admin Role - strategic overview, no direct warehouse operations
// Give all permissions EXCEPT direct warehouse operational permissions.
```

Sementara nama izin di produksi menyimpang. Artinya:

- **Kode dan produksi tidak sepakat.** Kalau database ini di-reset dan seeder dijalankan, super-admin akan **kehilangan** 10 izin itu. Perilakunya berubah tanpa ada yang mengubah kode.
- **Satu tes mengklaim sebaliknya.** `ManagerRoleBoundaryTest` → *"super-admin tidak diberi izin operasional gudang oleh seeder"* — dan tes itu **LULUS**, karena menguji hasil seeder, bukan produksi. Jadi ada keyakinan yang salah bahwa super-admin tidak bisa mengemas, padahal di produksi dia bisa.

> Catatan: `Gate::before` memang membuat super-admin tembus pemeriksaan `can()` untuk izin apa pun. Tapi izin yang benar-benar **terdaftar** di DB tetap berbeda dari yang seharusnya — dan itulah yang terlihat di halaman Kelola Peran serta ikut terbawa kalau ada ekspor/perbandingan.

---

### ⚠️ TEMUAN 2 — `manager` bisa mengerjakan SELURUH pekerjaan gudang, termasuk membongkar kerdus tersegel

`manager` memegang **15 dari 15** izin `warehouse.*` — persis sama dengan Staff Gudang. Yang paling perlu diperhatikan:

| Izin | Artinya |
|---|---|
| `warehouse.box.update_any` | Mengubah kerdus **siapa pun** — bukan cuma buatannya sendiri |
| `warehouse.box.update_sealed` | Mengubah kerdus yang **sudah tersegel** |
| `warehouse.packing.seal` | Menyegel kerdus |

Kombinasinya: **mengemas → menyegel → membuka lagi kerdus yang sudah tersegel.** Kalau peran ini dimaksudkan sebagai pengelola alur distribusi yang memantau, ini jauh melewati memantau.

Seeder sendiri menuliskan niatnya sebagai `// Warehouse (mengawasi operasi, termasuk lihat & kelola box)` — kata "mengawasi", tapi yang diberikan operasional penuh. **Ada jarak antara niat yang ditulis dan yang dijalankan.**

Keempat pemiliknya: Nalurita Firdausyah (#6), Nelli Agustina Siregar (#37), Chozinatul Rohmah (#38), Zaen (#40).

---

### ⚠️ TEMUAN 3 — Hierarki izinnya bolong di satu titik

Dicek apakah role "atas" memuat seluruh izin role "bawah":

```
manager ⊇ supervisor?   TIDAK — kurang: donatur.read
manager ⊇ warehouse?    TIDAK — kurang: donatur.read
supervisor ⊇ warehouse? TIDAK — kurang: 20 izin
warehouse ⊇ courier?    TIDAK — kurang: shipments.track, status.track
```

Yang pertama menarik: **Manager tidak bisa membuka halaman donatur (403), tapi Supervisor Gudang dan Staff Gudang bisa melihatnya.** Jadi manajer punya akses paling luas di hampir semua hal — kecuali justru di data donatur. Ini konsekuensi dari pencabutan `donatur.read` yang dilakukan sengaja. Bukan salah, tapi perlu disadari: **staf bisa hal yang atasannya tidak bisa.**

---

### ℹ️ TEMUAN 4 — Supervisor Gudang justru tidak bisa mengemas

| | supervisor | warehouse |
|---|---|---|
| Izin gudang | **3 dari 15** | 15 dari 15 |
| Bisa mengemas (`packing.scan`) | ❌ | ✅ |
| Bisa menyegel | ❌ | ✅ |

Alfi Husnia Fitri (#18) dan Ria Refriatin Febria (#19) — dua supervisor gudang — hanya bisa **membagi dan memantau** tugas, tidak turun tangan mengemas. Wajar sebagai pembagian kerja (supervisor mengatur, staf mengerjakan), tapi kalau di lapangan mereka ikut mengemas, mereka akan terhalang.

---

### ℹ️ TEMUAN 5 — 14 izin tampak kembar, maksudnya sama, namanya dua

Pola izinnya tidak konsisten — ada dua keluarga penamaan untuk hal yang sama:

| Nama A | Nama B | Pemiliknya |
|---|---|---|
| `qr.generate` | `warehouse.qr.generate` | **sama persis** |
| `qr.scan` | `warehouse.qr.scan` | **sama persis** |
| `qr.verify` | `warehouse.qr.verify` | hampir sama |
| `warehouse.packing.seal` | `warehouse.boxes.seal` | **sama persis** |
| `status.update` | `shipments.update-status` | beda (yang kedua lebih sempit) |
| `warehouse.dashboard` | `dashboard.view` | beda (satu halaman, dua penjaga) |

Akibatnya: **satu halaman bisa dijaga dua izin berbeda**, dan mencabut satu saja tidak menutup akses (mis. mencabut `qr.scan` tidak menghalangi karena `warehouse.qr.scan` masih ada). Ini jebakan saat nanti merapikan izin.

---

### ℹ️ TEMUAN 6 — 22 izin hanya dimiliki super-admin

Termasuk semua yang berisiko: `donatur.delete`, `users.*`, `roles.*`, `permissions.*`, `settings.*`, `shipments.delete`, `certificates.delete`, `mushaf-requests.delete`, `wakaf-batch.delete`.

Artinya **tidak ada seorang pun selain 2 super-admin yang bisa menghapus apa pun**, dan hanya mereka yang bisa mengurus pengguna. Ini bagus dari sisi keamanan (kekuasaan terkonsentrasi), tapi juga berarti **penghapusan data bergantung pada 2 orang**. Kalau keduanya tidak tersedia, tidak ada yang bisa menghapus.

---

### ℹ️ TEMUAN 7 — 2 tes usang merah

`ManagerRoleBoundaryTest`:

```
✓ it menolak manager membuka halaman manajemen user dan role
✗ it mengizinkan manager membuka halaman donatur dan mushaf request
✗ it tetap memberi manager wewenang baca dan ubah
```

Kedua tes yang merah masih mengunci perilaku **lama** (saat manager masih boleh `donatur.read`). Izin itu sudah dicabut dengan sengaja, jadi **kodenya yang benar, tesnya yang ketinggalan.**

---

## 5. Yang sudah benar

Supaya gambaran tetap seimbang — ini semua terverifikasi:

- **0 izin yatim** — setiap izin di sistem dipakai minimal satu role
- **0 izin langsung ke user** — semuanya lewat role, jadi perubahan kebijakan cukup sekali di role
- **Produksi dan kode sinkron** — 82 izin di dua-duanya, tanpa izin yang "hanya ada di DB" (kebijakan tak tercatat)
- **5 dari 6 role persis sesuai seeder** — warehouse, supervisor, courier, customer-service, manager
- **2 route admin tanpa penjaga izin** (`admin/qr-generator`, `admin/qr-scanner`) — **bukan celah**, isinya cuma pengalih ke halaman lain
- **Pengaturan berisiko terkunci** — pengaturan sistem dan pengelolaan peran hanya di super-admin

---

## 6. Rekomendasi

Diurutkan dari yang paling mendesak. **Tidak satu pun saya kerjakan tanpa persetujuan Anda** — semuanya menyentuh produksi.

### 1. Putuskan dulu: bolehkah `manager` mengemas?

Ini pertanyaan produk, bukan teknis, dan menghambat rekomendasi lain. Dua jawaban yang mungkin:

- **Ikut mengemas** → biarkan seperti sekarang, tapi update komentar seeder supaya niatnya jujur
- **Hanya memantau** → cabut izin operasional dari manager, sisakan yang memantau saja

### 2. Tangani penyimpangan `super-admin`

Pilih satu:

- **Terima produksi sebagai kebenaran** → perbarui `WAREHOUSE_OPERATIONAL_PERMISSIONS` + seeder supaya cocok. Konsekuensinya niat "no direct warehouse operations" dibatalkan.
- **Kembalikan ke niat kode** → cabut 10 izin itu dari super-admin di produksi. Risikonya: super-admin tidak bisa lagi memakai halaman gudang kalau dibutuhkan untuk darurat.

### 3. Rapikan 14 izin kembar

Pilih satu keluarga penamaan (`qr.*` atau `warehouse.qr.*`), alihkan, lalu hapus yang tidak dipakai. Setelah itu baru pencabutan izin bisa dipercaya.

### 4. Perbaiki 2 tes usang

Tesnya menuntut `donatur.read` yang sudah dicabut — selaraskan dengan kebijakan yang berlaku.

### 5. Pertimbangkan Supervisor Gudang

Kalau mereka memang ikut mengemas di lapangan, beri `warehouse.packing.scan` + `warehouse.packing.seal`.

---

## 7. Cara memverifikasi sendiri

```bash
# Route produksi + izin penjaganya
ssh ekspedisi-prod 'cd /var/www/dash && sudo -u www-data HOME=/tmp \
  php artisan route:list --json' > routes.json

# Probe langsung: bisa tidak role tertentu membuka halaman tertentu
# (lihat skrip probe di lampiran — tidak mengubah data, hanya GET)
```

Prinsip yang dipakai: **jangan menjawab dari seeder.** Produksi bisa menyimpang, dan memang menyimpang (Temuan 1). Jawaban yang sahih adalah hasil memanggil route-nya sebagai user produksi yang sesungguhnya.
