# Laporan Temuan Bug — Fitur Import Donatur

**Proyek:** Ekspedisi Quran (Laravel 12 + Svelte 4 + Inertia v2)
**Tanggal:** 23 September 2026
**Ruang lingkup:** Fitur import Donatur via Excel (`/admin/donatur-import`)
**Metode:** Uji end-to-end dengan file Excel nyata + verifikasi langsung ke database
**Status:** ✅ Diperbaiki & terverifikasi

---

## 1. Ringkasan

Fitur import donatur **berfungsi sesuai ekspektasi untuk kasus dasar**, namun ditemukan
**1 bug nyata** yang menyebabkan data berlipat ganda. Bug sudah diperbaiki dan ditutup
dengan test regresi.

| Aspek | Sebelum | Sesudah |
|---|---|---|
| Import donatur baru | ✅ Berfungsi | ✅ Berfungsi |
| Import ulang kode donatur sama | ❌ **Wakaf item & pengiriman berlipat ganda** | ✅ Akumulasi benar |
| Normalisasi nomor HP | ✅ `0812…` → `+62812…` | ✅ Sama |
| Validasi file & template | ✅ Berfungsi | ✅ Berfungsi |

---

## 2. Bug yang Ditemukan

### 2.1 Import ulang menggandakan wakaf item & pengiriman

**Severity:** Tinggi (integritas data — data bloat pada tabel `wakaf_items` dan `pengiriman`)

**Lokasi:**
- `app/Services/DonaturImportService.php:74`
- `app/Models/Donatur.php:262` (`generateWakafItems()`)

**Deskripsi:**
Saat mengimpor ulang donatur dengan `kode_donatur` yang sama, jumlah item wakaf dan
pengiriman yang tergenerate **berlipat ganda** setiap kali import.

**Akar masalah:**
`DonaturImportService` melakukan akumulasi total (`total_a5_count = total_a5_count + jumlah_a5`)
**terlebih dahulu**, lalu memanggil `generateWakafItems()` yang membaca nilai **total kumulatif**
(`$this->attributes['total_a5_count']`) — bukan jumlah dari baris yang baru diimport.

| Langkah | total_a5_count | Item yang digenerate | Item seharusnya |
|---|---|---|---|
| Import #1 (A5=2, A6=1) | 2 | 3 item | 3 item ✅ |
| Import #2 (kode sama) | 4 | **4 A5 + 2 A6 = 9 item total** | 6 item (3+3) ❌ |

**Bukti (output uji sebelum perbaikan):**
```
IMPORT #1 => success=1 error=0
  total_a5_count=2, a6=1, iqra=0, donation_count=1
  wakaf_items=3 (harap 3: 2x A5 + 1x A6)
  pengiriman=3 (harap 3, 1 per wakaf item)

IMPORT #2 (kode sama) => success=1 error=0
  SETELAH import#2: donation_count=2, a5=4, a6=2
  jumlah donatur dgn kode ini: 1 (harap 1 = update, bukan duplikat)
  wakaf_items total=9   <-- SEHARUSNYA 6
  pengiriman total=9    <-- SEHARUSNYA 6
```

**Dampak:**
- Tabel `wakaf_items` dan `pengiriman` membengkak setiap import ulang (data tidak valid).
- Laporan distribusi / stok menjadi tidak akurat.
- Jumlah mushaf per donatur terlihat lebih banyak dari yang sebenarnya.

**Mengapa test lama tidak menangkap:**
Test `it_can_import_donatur_data` hanya memverifikasi **redirect + donatur tersimpan**,
tidak memeriksa **jumlah** wakaf item / pengiriman yang tergenerate.

---

## 3. Perbaikan yang Dilakukan

### 3.1 `app/Models/Donatur.php` — `generateWakafItems()`

Menambahkan parameter opsional `$counts` sehingga method dapat digenerate sejumlah
**delta** (jumlah baris import), bukan selalu total kumulatif:

```php
public function generateWakafItems(?array $counts = null): array
{
    $countA5   = $counts !== null ? (int) ($counts['a5'] ?? 0)   : (int) ($this->attributes['total_a5_count'] ?? 0);
    $countA6   = $counts !== null ? (int) ($counts['a6'] ?? 0)   : (int) ($this->attributes['total_a6_count'] ?? 0);
    $countIqra = $counts !== null ? (int) ($counts['iqra'] ?? 0) : (int) ($this->attributes['total_iqra_count'] ?? 0);
    ...
}
```

> Perilaku lama (tanpa argumen) dipertahankan agar `DonaturController::create` — yang
> membuat donatur baru — tetap berjalan tanpa perubahan.

### 3.2 `app/Services/DonaturImportService.php`

- Mengirim delta baris import ke `generateWakafItems()`
- Menambahkan fallback `created_by` (mencegah crash bila tidak ada user auth)

```php
$wakafItemsData = $donatur->generateWakafItems([
    'a5'   => $currentA5,
    'a6'   => $currentA6,
    'iqra' => $currentIqra,
]);
```

---

## 4. Verifikasi Setelah Perbaikan

```
IMPORT #1 => success=1 error=0
  wakaf_items=3 (harap 3)          ✅
  pengiriman=3  (harap 3)          ✅

IMPORT #2 (kode sama) => success=1 error=0
  donation_count=2, a5=4, a6=2     ✅ akumulasi benar
  jumlah donatur dgn kode ini: 1   ✅ update, bukan duplikat
  wakaf_items total=6              ✅ (3+3, tidak berlipat)
  pengiriman total=6               ✅
```

### Hasil test

| Test | Hasil |
|---|---|
| `tests/Feature/Admin/DonaturImportTest.php` (baru, 3 test) | ✅ 3/3 (14 assertions) |
| `tests/Feature/Admin/DonaturManagementTest.php` | ✅ 27 passed |
| `vendor/bin/pint --dirty` | ✅ bersih |

---

## 5. Test Regresi yang Ditambahkan

File: **`tests/Feature/Admin/DonaturImportTest.php`**

1. `it import membuat donatur + wakaf items + pengiriman sesuai jumlah`
2. `it import ulang kode sama tidak menggandakan wakaf items & pengiriman` ← **test penangkap bug**
3. `it normalisasi nomor HP ke format +62`

---

## 6. Rekomendasi Lanjutan (opsional)

1. **Guardrail import** — pertimbangkan menambahkan opsi "mode import" (tambah vs ganti)
   agar perilaku akumulasi eksplisit dan disengaja pengguna.
2. **Kolom `created_by`** — saat ini `NOT NULL`; fallback sudah ditambahkan, namun sebaiknya
   dibuat `nullable` atau punya default agar lebih tahan terhadap jalur non-HTTP
   (CLI/queue).
3. **Data lama** — jika di produksi ada donatur yang pernah diimport berulang dengan bug ini,
   perlu audit/rekonsiliasi jumlah `wakaf_items` yang menggantung.

---

## 7. Catatan Teknis

- Uji dijalankan pada database `db_ekspedisi_quran` (dev) dengan MariaDB 10.11.
- Data uji yang dibuat **sudah dibersihkan** setelah verifikasi.
- Perubahan file yang terlibat:
  - `app/Models/Donatur.php`
  - `app/Services/DonaturImportService.php`
  - `tests/Feature/Admin/DonaturImportTest.php` (baru)
