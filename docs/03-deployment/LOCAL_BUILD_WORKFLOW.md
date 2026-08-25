# Local-First Build Workflow

## Overview

Workflow ini menggunakan **local build** approach untuk mengurangi beban server dan mempercepat deployment. Semua build assets (Vite/NPM) dibuat di lokal sebelum push.

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

### GitHub Actions Checks

| Check | Deskripsi | Jika Gagal |
|-------|-----------|------------|
| **Build Assets** | Verifikasi `public/build` ada di repo | ❌ Deployment STOP |
| **SSH Connection** | Koneksi ke server (45.77.42.220) | ❌ Deployment FAIL |

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
# Manual deploy (jika perlu)
envoy run deploy

# Rollback
envoy run rollback

# Check deployment status
ssh root@45.77.42.220 "ls -la /var/www/app/releases/ | head -10"
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
# Cek status di server
ssh root@45.77.42.220 "systemctl status php8.3-fpm"
ssh root@45.77.42.220 "tail -50 /var/www/app/current/storage/logs/laravel.log"

# Manual rollback jika perlu
envoy run rollback
```

## Important Notes

### ✅ DO

- Selalu build di lokal sebelum commit
- Stage `public/build/` dalam commit
- Jalankan tests sebelum push
- Perhatikan warning migration

### ❌ DON'T

- Commit tanpa build (akan gagal di CI)
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

1. Cek GitHub Actions logs
2. Verifikasi SSH key di secrets
3. Cek server status
4. Hubungi DevOps jika perlu

---

**Last Updated**: April 2026
**Version**: 2.0 (Local-First Build)
