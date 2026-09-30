# Telusur Produksi Dash — 30 Sep 2026

Audit `/var/www/dash` (produksi harian tim) setelah insiden symlink
`public/storage` hilang. Deploy rilis `e77b01a` + backfill **sudah dijalankan**.

## Ringkasan status

| Item | Status |
|---|---|
| Symlink `public/storage` | dipulihkan, semua gambar 200 |
| Deploy rilis `e77b01a` ke dash | **SELESAI & terverifikasi** |
| Backfill 96 tugas lama → `expired` | **SELESAI** (transaksional) |
| `packing:expire-tasks` | kini terdaftar & jalan (`exit 0`) |
| `performance:check-budgets` | frontend tidak lagi melanggar |
| Bug carry-over tak terbatas | **SUDAH DIPERBAIKI** — batas 20% diterapkan & ter-deploy |

## 1. Infrastruktur & deploy: sehat

| Diperiksa | Hasil |
|---|---|
| `composer install` (tanpa `--ignore-platform-reqs`) | 101 paket sinkron |
| `check-platform-reqs` | `php 8.3.6 success` |
| `platform_check.php` | `PHP_VERSION_ID >= 80300` |
| Framework | Laravel 12.69.3, `symfony/clock v7.4.8` |
| Migrasi | 0 pending |
| Cache | `config.php` (59 KB), `routes-v7.php` (426 KB), 39 view |
| Manifest aset | 30 entri, 0 berkas hilang |
| Queue worker | `dash-queue.service` aktif; 0 job menunggu / 0 gagal |
| Scheduler | crontab `www-data` tiap menit |
| Error setelah 22:20 | **0** |

Prosedur deploy dijalankan dengan `--exclude "public/storage"` +
pengecekan symlink di akhir; symlink **selamat** (terbukti setelah rsync).

Catatan pasca-deploy: `config:cache` sempat gagal (`Permission denied`) karena
dijalankan sebagai **root**, padahal `bootstrap/cache` milik `www-data`.
Jalankan `artisan config:cache|route:cache|view:cache` **sebagai `www-data`**,
lalu `optimize:clear` + rebuild. Sudah dibersihkan.

## 2. Bug A — `packing:expire-tasks` tidak pernah ada (SUDAH DIPERBAIKI)

```
Scheduled command [... 'artisan' packing:expire-tasks] failed  (12x)
ERROR  Command "packing:expire-tasks" is not defined.
```

`routes/console.php:16` menjadwalkannya tiap 23:59 sejak awal, tetapi kelas
perintahnya tidak pernah dibuat. Logikanya sudah ada di
`PackingAssignmentService::processDailyExpiration()` — **tidak dipanggil siapa pun**.

Dampak terukur: 96 tugas (24–29 Sep) masih `assigned`/`in_progress` padahal
sudah lewat; `expired` = 0.

**Perbaikan** (commit `e77b01a`): `App\Console\Commands\ExpirePackingTasks`.
Ter-deploy dan terbukti jalan di produksi (`exit 0`).

## 3. Bug B — `performance:check-budgets` exit 1 tiap malam (SUDAH DIPERBAIKI)

```
Scheduled command [... 'artisan' performance:check-budgets --output=json] failed  (12x)
```

`checkFrontendBudgets()` menjumlahkan **SELURUH berkas** manifest Vite
(JS + CSS + semua chunk dinamis) lalu membandingkannya dengan budget **satu
bundle** (1.000 KB) → terukur 3.419 KB. 19 dari 20 budget lulus; hanya metrik
yang salah ukur ini yang gagal (`severity: critical` → exit 1).

**Perbaikan:** `App\Support\BuildManifest` → chunk terbesar **721,6 KB** ✅,
bundle entry **146,4 KB** ✅. Terverifikasi di produksi.

## 4. Bug C — carry-over TIDAK TERBATAS (SUDAH DIPERBAIKI, batas 20%)

Akar inflasi target harian yang sesungguhnya. `config/packing.php` menyatakan:

```php
'task_expiration' => [
    'expire_after_days' => 1,
    'allow_carryover' => true,
    'max_carryover_percent' => 20,   // <- TIDAK PERNAH DIBACA
],
```

`grep` di seluruh `app/` membuktikan **tidak ada satu pun kode** yang membaca
`max_carryover_percent` maupun `allow_carryover`. `calculateCarryOver()` hanya
mengembalikan **seluruh sisa** tugas kemarin (`total_target - total_selesai`)
tanpa batas.

**Carry-over tumbuh tanpa henti (terukur di DB):**

| Tanggal | Target/user | Sisa/user |
|---|---|---|
| 24 Sep | 80 | 0 |
| 25 Sep | 160 | 80 |
| 28 Sep | 240 | 160* |
| 29 Sep | 320 | 240 |
| 30 Sep (hari ini) | 320 | 240 |

\* 27 Sep Minggu dilewati (`working_days` = Sen–Jum).

Hari ini `sisa_kemarin` = **5.760** total (240/user). **Proyeksi 1 Okt =
320/user, lalu terus naik** karena 30 Sep juga 0% capaian.

**Backfill TIDAK memperbaiki ini.** Backfill hanya merapikan status 96 tugas
lama; mekanismenya tetap tanpa batas. Yang benar-benar menyetop inflasi adalah
**menerapkan `max_carryover_percent`** yang sudah tertulis di config.

### Perbaikan (commit `65aef2d`)

`calculateCarryOver()` kini menerima base target hari ini dan memotong sisa
kemarin lewat `capCarryOver()`: batas = `floor(base × 20 / 100)`.

| Skenario | Tanpa batas | Dengan batas |
|---|---|---|
| sisa kemarin 30, base 50 | carry 30 → target 80 | carry **10** → target **60** |
| sisa kemarin 240, base 80 | carry 240 → target 320 | carry **16** → target **96** |
| 3 hari berturut 0% | 80 → 160 → 240 → 320 | 96 → 96 → 96 |

Nilai `max_carryover_percent` ≥ 100 mengembalikan perilaku lama (tanpa batas)
bagi yang membutuhkannya, dan `allow_carryover => false` kini benar-benar
mematikan carry-over. Kedua jalur (auto assignment dan assign-by-target manual)
ikut berlaku; jalur update memakai `sisa_kemarin` tersimpan sehingga batas tidak
dihitung dua kali.

Konteks: modul packing di dash **belum dipakai** (0 kerdus, 0 item, 0 scan,
`total_selesai > 0` = 0). Jadi bug ini belum melukai operasional — tapi target
sudah ~4x lipat sebelum gudang memakai sistemnya.

## 5. Uji & verifikasi

- 17 test baru/terkait lulus: 5 satuan manifest, 7 kedaluwarsa tugas,
  5 batas carry-over (7 lulus penuh di `PackingAssignmentServiceTest`).
- **Bukti test menggigit:** kode metrik lama dipasang kembali → melaporkan
  `3419.400390625`, **persis sama** dengan produksi. Kode carry-over lama
  dipasang kembali → **5 test batas gagal**.
- Regresi suite penuh: baseline 76 gagal/386 lulus vs sesudah 77/390 —
  satu-satunya kegagalan tambahan (`PackingConcurrencyTest`) terbukti **flaky**,
  gagal juga saat perubahan saya distash.
- `vendor/bin/pint --dirty` passed.
- Commit `e77b01a`, `6a3e9ad`, `65aef2d` — di-push dan ter-deploy ke dash.

## 6. Verifikasi langsung di produksi (pasca-deploy)

Diuji di dalam transaksi (rollback), pada DB dash sungguhan:

```
sisa kemarin = 1000, base target = 80
carry_over   = 16   (tanpa batas: 1000)
total_target = 96   (tanpa batas: 1080)
data uji tersisa: 0
```

Data produksi utuh: 15.552 donatur, 26.099 pengiriman, 40 user, 120 tugas
(96 expired + 24 hari ini), 0 kerdus, 480 notifikasi. Nol error setelah deploy.

## 7. Sisa keputusan untuk pemilik

1. **`docs/CLAUDE.md:190`** menyebut `packing:daily-assignment` "DISABLED",
   padahal aktif dan dijadwalkan 06:00 (terbukti 24 tugas/hari). Patch diblokir
   pengaman berkas instruksi agen — butuh persetujuan.
2. **480 notifikasi** belum ada yang dibaca (`is_read` = 0). Perlu pembersihan
   sebelum modul dipakai.
3. **24 tugas 30 Sep** masih bertarget 240 (dibuat sebelum batas berlaku).
   Tugas 1 Okt ke atas otomatis 96. Bila ingin 30 Sep ikut dinormalkan,
   perlu penyesuaian manual.
