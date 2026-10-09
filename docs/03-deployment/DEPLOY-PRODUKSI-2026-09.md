# Prosedur Deploy Produksi (Ekspedisi Quran)

**Status:** jalur deploy yang benar-benar dipakai per 30 Sep 2026.
**Penting:** `Envoy.blade.php` di repo **USANG** — jangan dipakai. Isinya masih menunjuk
repo `git@github.com:agoesset/ekspedisi-quran.git` dan server `root@45.77.42.220`,
padahal produksi sekarang mengambil dari `https://github.com/anggiadiputra/EQ.git`
di host `ekspedisi-prod` (vps-187.77.113.89).

## Fakta produksi

| Hal | Nilai |
|---|---|
| Host | `ekspedisi-prod` (vps-187.77.113.89), user `hermes`, sudo NOPASSWD |
| Domain app | `https://app.ekspedisiquran.com` (`APP_URL`), root nginx `/var/www/app/current/public` |
| Domain marketing | `ekspedisiquran.com` — **bukan** Laravel ini (situs WordPress terpisah) |
| Struktur | symlink release: `/var/www/app/current -> /var/www/app/releases/<timestamp>` |
| PHP | 8.3.6 (fpm + cli). **Tidak ada paket php8.4 di Ubuntu 24.04 ini.** |
| Database | `db_ekspedisi_quran` (kredensial di `/var/www/app/.env`) |
| Queue worker | **tidak ada** supervisor; tidak perlu restart |
| `vendor/` | **tidak dilacak git** — harus `composer install` di setiap rilis |
| `public/build/` | **dilacak git** dan Envoy tidak punya tugas build aset → wajib `npm run build` + commit bundle sebelum push |
| Backup | `/var/www/app/backups/` |

## Jebakan yang sudah menelan korban

1. **`composer install` (sudah DIPERBAIKI 30 Sep 2026, jangan diakali lagi).**
   Dulu `composer.lock` ter-resolve ke Symfony v8 (`symfony/clock v8.1.0`,
   `symfony/string v8.1.2`, `symfony/translation`, `css-selector`,
   `event-dispatcher`) yang menuntut **PHP >= 8.4.1**, sedangkan server PHP 8.3.6
   dan Ubuntu 24.04 tidak menyediakan paket php8.4. Install hanya bisa jalan
   dengan `--ignore-platform-reqs` — yang **tidak** melewati
   `vendor/composer/platform_check.php`.

   Penyebabnya: `laravel/framework` hanya mendukung `symfony/* ^7.2.0`, tapi
   constraint paket turunan bergaya `^6.4|^7.0|^8.0` sehingga Composer memilih v8.

   Perbaikan permanen (sudah diterapkan, commit `f573138`):
   - `composer.json` → `config.platform.php = "8.3.6"`
   - `composer update symfony/clock symfony/css-selector symfony/event-dispatcher
     symfony/string symfony/translation --with-all-dependencies` → kelimanya turun v7.4
   - `laravel/framework` dinaikkan ke v12.69.3 (menutup CVE-2026-102279)

   Hasil: `composer install` **sukses tanpa flag apa pun** dan
   `php vendor/composer/platform_check.php` hanya menuntut `PHP_VERSION_ID >= 80300`.
   **Jangan hapus `config.platform.php`** — kalau dihapus, resolusi berikutnya
   bisa memilih paket PHP 8.4 lagi dan produksi pecah.
2. **Migrasi di produksi bisa tertinggal jauh.** Rilis April → status migrasi berhenti di
   `2026_04_15`. Artinya `2026_09_02_000001_create_no_request_sequences_table`
   (dibuat 2 September) belum pernah jalan sampai deploy 30 Sep. Ingat cek
   `php artisan migrate:status` sebelum menganggap "cuma migrasi saya yang pending".
3. **`php artisan tinker` gagal** kalau HOME tidak writable (`Writing to directory
   /var/www/.config/psysh is not allowed`). Jalankan dengan `sudo -u www-data HOME=/tmp`.
4. **QR PNG butuh ekstensi `imagick`.** `simplesoftwareio/simple-qrcode` mengunci
   format PNG ke `ImagickImageBackEnd` (tidak bisa di-config). Server ini dulu hanya
   punya `gd`, sehingga pembuatan QR melempar `You need to install the imagick
   extension`. `php8.3-imagick` sudah dipasang 30 Sep 2026 — **kalau server
   di-provisioning ulang, pasang ini lagi**, kalau tidak tombol cetak label dan
   kolom QR kerdus akan gagal.

## Prosedur deploy (terbukti 30 Sep 2026)

```bash
# 1. Backup database (wajib)
#    parse .env sendiri; jangan andalkan parse_ini_file (sintaks .env bikin warning)
REL=$(date +%Y%m%d%H%M%S)

# 2. Clone rilis baru
sudo mkdir -p /var/www/app/releases/$REL && sudo chown www-data:www-data /var/www/app/releases/$REL
cd /var/www/app/releases/$REL
sudo -u www-data HOME=/tmp git clone --depth 1 https://github.com/anggiadiputra/EQ.git .

# 3. Symlink (tiru pola rilis lama persis)
sudo -u www-data HOME=/tmp ln -sfn /var/www/app/.env      $PWD/.env
sudo -u www-data HOME=/tmp ln -sfn /var/www/app/storage   $PWD/storage
sudo -u www-data HOME=/tmp ln -sfn $PWD/storage/app/public $PWD/public/storage
sudo -u www-data HOME=/tmp mkdir -p bootstrap/cache storage/framework/{views,sessions,cache}

# 4. Dependensi — sudah aman di PHP 8.3 (config.platform.php terkunci).
#    Bila ada yang menuntut PHP 8.4 lagi, JANGAN pakai --ignore-platform-reqs:
#    turunkan paketnya, karena flag itu tidak melewati platform_check.php.
sudo -u www-data HOME=/tmp composer install --no-dev --no-interaction --prefer-dist \
    --optimize-autoloader

# 5. Cek & jalankan migrasi
sudo -u www-data HOME=/tmp php artisan migrate --pretend --force   # review dulu
sudo -u www-data HOME=/tmp php artisan migrate --force

# 6. Optimasi + izin aset
sudo -u www-data HOME=/tmp php artisan optimize:clear
sudo chown -R www-data:www-data $PWD/public/build && sudo chmod -R 755 $PWD/public/build

# 7. PERGANTIAN TRAFIK (atomik)
sudo ln -sfn $PWD /var/www/app/current.new && sudo mv -Tf /var/www/app/current.new /var/www/app/current
```

## Verifikasi setelah deploy

```bash
readlink -f /var/www/app/current                    # harus rilis baru
curl -s -o /dev/null -w '%{http_code}\n' -k -H 'Host: app.ekspedisiquran.com' https://127.0.0.1/login
# dari luar: pastikan halaman memuat hash bundle BARU, dan bundle lama 404
curl -s https://app.ekspedisiquran.com/login | grep -oE '/build/assets/app-[^"]+\.js'
sudo tail -60 /var/www/app/storage/logs/laravel.log | grep -cE 'ERROR|CRITICAL'
```

## Rollback

Symlink saja, tidak ada perubahan file di rilis lama:

```bash
sudo ln -sfn /var/www/app/releases/<rilis-lama> /var/www/app/current.new \
  && sudo mv -Tf /var/www/app/current.new /var/www/app/current
```

Migrasi **tidak** otomatis di-rollback. Bila perlu: restore
`/var/www/app/backups/eq-pre-deploy-*.sql`.

## ⚠️ KOREKSI PENTING (30 Sep 2026 malam): PRODUKSI SEHARI-HARI = DASH

Dokumen di atas keliru menyebut `app.ekspedisiquran.com` sebagai produksi utama.

**Fakta di host `ekspedisi-prod` (187.77.113.89) ada DUA aplikasi Laravel terpisah:**

| Domain | Root nginx | Struktur | Peran |
|---|---|---|---|
| `dash.ekspedisiquran.com` | `/var/www/dash` | direktori langsung (bukan symlink releases) | **PRODUKSI SEHARI-HARI TIM** — dipakai update rutin |
| `app.ekspedisiquran.com` | `/var/www/app/current` | symlink ke `releases/<timestamp>` | aplikasi terpisah/lama — **JANGAN disentuh** tanpa arahan eksplisit pemilik |

Database juga terpisah: `db_ekspedisi_quran_dash` (dash) vs `db_ekspedisi_quran` (app).

**Insiden 30 Sep 2026:** deploy keliru dilakukan ke `app` (3 rilis baru +
migrasi), menganggap itu produksi. Tindakan pemulihan yang sudah dilakukan:
`app` di-rollback ke rilis April `20260416072853`, dan fix hari ini
(`209990f`..`29e1d87`) di-deploy ke **`dash`** dengan backup penuh
(`/root/dash-full-backup-20260930.tar.gz` + `/root/dash-db-backup-20260930.sql`).

## Struktur dash sekarang: clone git (sejak 9 Okt 2026)

`/var/www/dash` **memiliki `.git`** dan melacak `origin/main`
(`https://github.com/anggiadiputra/EQ.git`). Jadi versi yang berjalan di server bisa
diperiksa langsung dari servernya:

```bash
cd /var/www/dash
sudo git log --oneline -3                 # riwayat yang sedang berjalan
sudo git rev-list --left-right --count HEAD...origin/main   # 0 0 = sinkron
```

**Cara deploy sekarang — git, bukan rsync.** `rsync -a --delete` akan menimpa isi
checkout dan membuat working tree kotor, jadi jangan dipakai lagi untuk kode:

```bash
cd /var/www/dash
sudo git fetch origin main
sudo git checkout -f origin/main          # menimpa berkas terlacak; storage/vendor/.env TIDAK terlacak
sudo chown -R www-data:www-data /var/www/dash/public/build   # WAJIB: checkout sbg root bikin berkas root
sudo -u www-data HOME=/tmp php artisan optimize:clear
sudo -u www-data HOME=/tmp php artisan migrate --force
sudo -u www-data HOME=/tmp php artisan config:cache && sudo -u www-data HOME=/tmp php artisan route:cache
sudo systemctl reload php8.3-fpm
```

Jebakan git di server ini:
- **`git checkout` dijalankan sebagai root → berkas jadi milik root.** Untuk `.php` tidak
  masalah (mode 644, dibaca siapa saja), tetapi `public/build` harus tetap bisa ditimpa
  build berikutnya, jadi kembalikan ke `www-data` seperti langkah di atas.
- **`git checkout -f` membuang perubahan terlacak di server tanpa bertanya.** Kalau ada
  yang pernah mengedit berkas langsung di server, edit itu hilang. Periksa dulu dengan
  `sudo git status --short` (kosong = bersih).
- `origin/main` sudah ada di server tetapi **push ke GitHub TIDAK otomatis men-deploy
  dash** — workflow `.github/workflows/deploy.yml` masih menunjuk server lama
  (`root@45.77.42.220`, repo `agoesset/ekspedisi-quran`) lewat `Envoy.blade.php` yang
  usang. Deploy ke dash tetap langkah manual di atas sampai workflow itu diperbaiki.
- `vendor/`, `.env`, `storage/`, dan `public/storage` tidak dilacak git — aman dari
  checkout, tapi juga berarti `composer install` tetap perlu dijalankan bila
  `composer.lock` berubah.

## Prosedur deploy lama (rsync) — JANGAN dipakai lagi

Disimpan hanya sebagai catatan sejarah; langkah ini digantikan blok git di atas.

```bash
# 1. Backup WAJIB (kode + DB)
sudo tar -czf /root/dash-full-backup-$(date +%Y%m%d-%H%M%S).tar.gz \
    -C /var/www --exclude=vendor dash
U=$(grep -m1 ^DB_USERNAME /var/www/dash/.env | cut -d= -f2)
P=$(grep -m1 ^DB_PASSWORD /var/www/dash/.env | cut -d= -f2-)
sudo sh -c "mysqldump --single-transaction --no-tablespaces -u\"$U\" -p\"$P\" \
    db_ekspedisi_quran_dash > /root/dash-db-$(date +%Y%m%d-%H%M%S).sql"
```

Catatan: `mysqldump` tanpa `--no-tablespaces` gagal dengan *"Access denied; you need
(at least one of) the PROCESS privilege(s)"* dan — karena stdout diarahkan ke berkas —
**berkas kosong tetap tertulis**, jadi backup terlihat ada padahal isinya nol. Selalu
periksa hasilnya (`grep -c "^CREATE TABLE"` harus 49, bukan 0).

```bash
# 2. Clone repo terbaru ke dir sementara
sudo rm -rf /tmp/eq-deploy && sudo git clone --depth 1 \
    https://github.com/anggiadiputra/EQ.git /tmp/eq-deploy

# 3. rsync kode ke dash (JANGAN sentuh .env, storage, vendor, public/storage)
#    --delete akan MENGHAPUS public/storage (symlink tidak ada di repo) sehingga
#    SELURUH gambar yang diunggah jadi 404. Wajib dikecualikan.
sudo rsync -a --delete --exclude ".env" --exclude "storage/" \
    --exclude "vendor/" --exclude ".git/" --exclude "public/storage" \
    /tmp/eq-deploy/ /var/www/dash/

# 4. Dependensi + migrasi + cache
cd /var/www/dash
sudo composer install --no-dev --optimize-autoloader --no-interaction
sudo composer check-platform-reqs --no-dev     # semua harus "success"
sudo -u www-data HOME=/tmp php artisan migrate --pretend --force   # review dulu
sudo -u www-data HOME=/tmp php artisan migrate --force

# PENTING (1): rsync dijalankan sebagai root, jadi bootstrap/cache jadi milik root.
# Kembalikan kepemilikan SEBELUM menjalankan cache, atau config:cache gagal dengan
# "file_put_contents(...): Failed to open stream: Permission denied".
sudo chown -R www-data:www-data /var/www/dash/storage /var/www/dash/bootstrap/cache

# PENTING (2): jalankan cache sebagai www-data, BUKAN root — kalau tidak, file
# cache jadi milik root dan request berikutnya gagal menulis.
sudo -u www-data HOME=/tmp php artisan optimize:clear
sudo -u www-data HOME=/tmp php artisan config:cache
sudo -u www-data HOME=/tmp php artisan route:cache
sudo -u www-data HOME=/tmp php artisan view:cache

# 4b. WAJIB: pastikan symlink storage ada (jaring pengaman bila exclude luput).
sudo test -L /var/www/dash/public/storage || sudo ln -sfn ../storage/app/public /var/www/dash/public/storage
sudo chown -h www-data:www-data /var/www/dash/public/storage

# 5. Reload PHP-FPM + verifikasi
sudo systemctl reload php8.3-fpm
curl -sk -o /dev/null -w '%{http_code}\n' https://dash.ekspedisiquran.com/login
# Verifikasi aset nyata lewat HTTP (bukan hanya halaman login)
F=$(sudo ls /var/www/dash/storage/app/public/settings | head -1)
curl -sk -o /dev/null -w "%{http_code}\n" "https://dash.ekspedisiquran.com/storage/settings/$F"
sudo tail -20 /var/www/dash/storage/logs/laravel.log | grep -cE 'ERROR|CRITICAL'

# 6. Bersihkan sisa deploy
sudo rm -rf /tmp/eq-deploy
```

## Insiden 30 Sep 2026: symlink `public/storage` hilang → semua gambar 404

Gejala: `GET /storage/settings/<hash>.webp` → **404**, padahal filenya ADA di
`/var/www/dash/storage/app/public/settings/`. Ikut terdampak: QR code (83 file),
foto lembaga (107), foto santri (107) — jadi bukan hanya logo.

Sebab: `rsync -a --delete` menghapus `public/storage`, dan prosedur deploy tidak
pernah menjalankan `php artisan storage:link`. Envoy dash sebenarnya punya
langkah itu (`update_symlinks`), tapi app ini dideploy dengan **rsync in-place**,
bukan layout `releases/current` yang diharapkan Envoy — jadi langkah tersebut
tidak pernah jalan.

Perbaikan: (a) tambah `--exclude "public/storage"` pada rsync, dan
(b) jalankan pengecekan symlink di akhir setiap deploy. Keduanya sudah masuk
prosedur di atas.

Catatan: `setting` bernama `app_logo` di tabel `settings` menyimpan nilai
`settings/<hash>.webp` (TANPA prefix `/storage/`), dan view menambahkan
`asset('storage/'.$value)`. Jadi 404 pada URL logo hampir selalu berarti symlink
hilang, bukan data setting yang rusak.

Rollback dash: ekstrak arsip `dash-full-backup-*.tar.gz` + restore SQL dump.

Sebelum deploy APA PUN: konfirmasi dulu ke pemilik bahwa target adalah `dash`, bukan `app`.
