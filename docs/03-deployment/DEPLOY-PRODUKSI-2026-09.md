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
