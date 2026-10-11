# Local-First Build Workflow

## Overview

Workflow ini menggunakan **local build** approach untuk mengurangi beban server dan mempercepat deployment. Semua build assets (Vite/NPM) dibuat di lokal sebelum push.

> ⚠️ **Bagian deploy di dokumen ini sudah usang.** Jalur GitHub Actions + Envoy
> (`Envoy.blade.php`, `.github/workflows/deploy.yml`) **dihapus 12 Okt 2026** karena
> menunjuk repo dan server yang sudah mati. Yang masih berlaku dari dokumen ini:
> kebiasaan **build di lokal + commit `public/build`** dan pre-commit hook-nya.
> Untuk cara deploy yang benar, lihat [DEPLOY-PRODUKSI-2026-09.md](DEPLOY-PRODUKSI-2026-09.md).

## Workflow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         LOCAL MACHINE                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Code Changes ──────┐                                         │
│                        ▼                                         │
│  2. npm ci && npm run build                                     │
│                        │                                         │
│  3. php artisan test  │  ✅ Tests Pass                            │
│                        │                                         │
│  4. git add .         │                                         │
│                        ▼                                         │
│  5. git commit        │  🚀 Pre-commit Hook Check               │
│                        │     • Build assets exist?               │
│                        │     • Tests passed?                     │
│                        │     • Code style OK?                    │
│                        ▼                                         │
│  6. git push ──────────┼───────────────────────────────────────┐ │
│                        │                                       │ │
└────────────────────────┼───────────────────────────────────────┘ │
                         │                                         │
                         ▼                                         │
┌────────────────────────────────────────────────────────────────┐│
│                      GITHUB ACTIONS                             ││
├────────────────────────────────────────────────────────────────┤│
│                                                                 ││
│  1. Trigger on push to main                                    ││
│                        │                                        ││
│  2. Verify build assets ────┐  ❌ Not found = FAIL             ││
│                        │     │  ✅ Found = Continue             ││
│                        ▼     │                                  ││
│  3. Run Envoy Deploy ─────────┘                                 ││
│                                                                 ││
└────────────────────────┼────────────────────────────────────────┘
                         │
                         ▼
┌────────────────────────────────────────────────────────────────┐
│                      PRODUCTION SERVER                          │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. Clone repository                                           │
│  2. Composer install (no-dev)                                  │
│  3. Run migrations (safe: migrate --force)                     │
│  4. Optimize                                                   │
│  5. Symlink switch (zero-downtime)                             │
│  6. Restart PHP-FPM                                            │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

## Pre-Deployment Checklist

Sebelum setiap commit dan push, jalankan:

```bash
# 1. Install dependencies (jika ada perubahan di package.json)
npm ci

# 2. Build assets
npm run build

# 3. Run tests
php artisan test

# 4. Code style check (optional tapi recommended)
./vendor/bin/pint

# 5. Stage semua perubahan (termasuk public/build!)
git add .

# 6. Commit
# Hook akan otomatis memverifikasi build & test
git commit -m "feat: your changes"

# 7. Push ke main (trigger deployment)
git push origin main
```

## Setup

### 1. Install Git Hooks

```bash
# Jalankan sekali untuk setup hooks
./scripts/setup-git-hooks.sh
```

Atau manual:

```bash
cp .githooks/pre-commit .git/hooks/
chmod +x .git/hooks/pre-commit
```

### 2. Verifikasi Setup

```bash
# Cek apakah hook terinstall
cat .git/hooks/pre-commit | head -5
```

## What Gets Verified

### Pre-Commit Hook Checks

| Check | Deskripsi | Jika Gagal |
|-------|-----------|------------|
| **Build Assets** | Verifikasi `public/build/manifest.json` ada | ❌ Commit dibatalkan |
| **Tests** | Cek apakah tests sudah dijalankan | ⚠️ Prompt konfirmasi |
| **Code Style** | Laravel Pint pada file yang berubah | 🔧 Auto-fix & restage |
| **Migration Safety** | Warning jika migration berubah | ⚠️ Warning only |

### Pemeriksaan di Sisi Server

GitHub Actions **sudah tidak dipakai** — workflow `deploy.yml` dihapus 12 Okt 2026 karena
menunjuk server yang sudah mati. Verifikasi setelah deploy dilakukan manual:
bundle baru termuat, bundle lama 404, dan log Laravel bersih. Lihat
[DEPLOY-PRODUKSI-2026-09.md](DEPLOY-PRODUKSI-2026-09.md).

## Commands Reference

### Local Development

```bash
# Install dependencies
npm ci

# Build untuk development
npm run dev

# Build untuk production (jangan lupa ini!)
npm run build

# Run tests
php artisan test

# Run specific test
php artisan test --filter=UserTest

# Code style check
./vendor/bin/pint

# Code style fix
./vendor/bin/pint --dirty
```

### Deployment

```bash
# Deploy produksi = rsync ke host ekspedisi-prod (vps-187.77.113.89).
# Prosedur lengkap (backup, migrasi, verifikasi, rollback):
#   docs/03-deployment/DEPLOY-PRODUKSI-2026-09.md
# JANGAN pakai `envoy` — Envoy.blade.php sudah dihapus dari repo.
```

## Directory Structure on Server

```
/var/www/app/
├── current/          → Symlink ke release aktif (document root)
├── .env              → Environment file (shared)
├── storage/          → Storage directory (shared, persistent)
└── releases/         → Semua releases
    ├── 20240101120000/
    ├── 20240102120000/
    └── ... (keep 2 latest)
```

## Troubleshooting

### "Build assets not found" Error

```bash
# Solusi: Build dan stage ulang
npm ci
npm run build
git add public/build/
git commit -m "your message"
```

### "Tests not run" Warning

```bash
# Solusi: Jalankan tests
php artisan test

# Buat marker file (jika test sudah passing)
touch .tests-passed
```

### Deployment Fail

```bash
# Dash (produksi sehari-hari) — cek langsung dari host ekspedisi-prod
ssh ekspedisi-prod "systemctl status php8.3-fpm"
ssh ekspedisi-prod "tail -50 /var/www/dash/storage/logs/laravel.log"

# Prosedur rollback ada di docs/03-deployment/DEPLOY-PRODUKSI-2026-09.md
```

## Important Notes

### ✅ DO

- Selalu build di lokal sebelum commit
- Stage `public/build/` dalam commit
- Jalankan tests sebelum push
- Perhatikan warning migration

### ❌ DON'T

- Commit tanpa build (aset basi ikut ter-deploy)
- Push langsung tanpa test
- Ubah migration yang sudah di-push
- Lupa restart PHP-FPM jika manual deploy

## CI/CD Changes

Perbedaan dari workflow sebelumnya:

| Aspek | Sebelum | Sekarang |
|-------|---------|----------|
| Build Location | GitHub Actions | Local Machine |
| PHP Setup | Di CI | Tidak perlu |
| Composer Install | Di CI | Di server (lebih cepat) |
| Build Verification | Build ulang | Cek file exist |
| Deployment Time | ~5-10 menit | ~1-2 menit |

## Migration Safety

⚠️ **CRITICAL**: Workflow ini sudah diperbaiki dari `migrate:fresh` menjadi `migrate`:

```php
// ✅ SAFE - Sekarang
php artisan migrate --force

// ❌ DANGEROUS - Dulu (sudah diperbaiki)
php artisan migrate:fresh --force
```

## Support

Jika ada masalah dengan deployment:

1. Baca `docs/03-deployment/DEPLOY-PRODUKSI-2026-09.md` — prosedur + jebakan yang sudah menelan korban
2. Cek log Laravel di host: `ssh ekspedisi-prod "tail -50 /var/www/dash/storage/logs/laravel.log"`
3. Cek status layanan: nginx, php8.3-fpm, mysql, redis, dash-queue

---

**Last Updated**: April 2026
**Version**: 2.0 (Local-First Build)
