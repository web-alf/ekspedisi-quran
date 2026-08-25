# Envoy Deployment Commands

Daftar lengkap perintah deployment menggunakan Laravel Envoy.

## Perintah Dasar

### Deploy Normal (Full)
```bash
envoy run deploy
```

Deploy lengkap dengan semua safety checks:
- Clone repository
- Composer install
- Database backup
- Run migrations
- Optimize cache
- Health check
- Notifikasi

**Durasi:** ~2-3 menit

### Deploy Cepat (Quick)
```bash
envoy run deploy:quick
```

Deploy cepat tanpa backup database:
- Cocok untuk hotfix minor
- Tidak menjalankan migrasi
- Lebih cepat (~1-2 menit)

**Gunakan ketika:**
- Hanya perubahan file (tidak ada migration)
- Hotfix urgent
- Tidak ada perubahan database

### Rollback
```bash
envoy run rollback
```

Rollback ke release sebelumnya:
- Switch symlink ke release sebelumnya
- Clear cache
- Restart PHP-FPM & queues
- Health check
- Hapus release yang gagal

## Opsi Deployment

### Deploy dengan Commit SHA
```bash
envoy run deploy --commit="abc123"
```

### Deploy ke Server Tertentu
```bash
envoy run deploy --server=production
```

### Deploy dengan Verbose Output
```bash
envoy run deploy -v
```

## Task Individual

Jika perlu menjalankan task tertentu saja:

```bash
# Backup database manual
envoy run backup_database

# Health check saja
envoy run health_check

# Restart services
envoy run restart_php
envoy run restart_queues

# Clean old releases
envoy run clean_old_releases
```

## Alur Deployment

```
┌─────────────────────────────────────────────────────────────┐
│ 1. LOCAL MACHINE                                             │
│    ✅ npm ci && npm run build                               │
│    ✅ php artisan test                                      │
│    ✅ git add . && git commit -m "..."                      │
│    ✅ git push origin main                                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. GITHUB ACTIONS                                            │
│    ✅ Verify build assets exist                             │
│    ✅ Run Envoy deploy                                      │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. SERVER (Envoy Tasks)                                      │
│    ┌──────────────────────────────────────────────┐         │
│    │ clone_repository                             │         │
│    │ run_composer                                 │         │
│    │ create_cache_directory                       │         │
│    │ link_env_file                                │         │
│    │ generate_app_key                             │         │
│    │ handle_storage_directory                     │         │
│    │ backup_database     ⚠️  Automatic backup     │         │
│    │ run_migrations                               │         │
│    │ run_optimize                                 │         │
│    │ update_symlinks    🔄 Zero-downtime switch   │         │
│    │ restart_queues                               │         │
│    │ delete_git_metadata                          │         │
│    │ clean_old_releases                           │         │
│    │ change_permission_owner                      │         │
│    │ restart_php                                  │         │
│    │ health_check          ✅ Verify success      │         │
│    │ notify_deployment                            │         │
│    └──────────────────────────────────────────────┘         │
└─────────────────────────────────────────────────────────────┘
```

## Direktori di Server

```
/var/www/app/
├── current/          → Symlink ke release aktif (document root)
├── .env              → Environment file (shared)
├── storage/          → Storage persistent (shared)
├── backups/          → Database backups (auto-created)
│   ├── backup_20240101120000.sql.gz
│   ├── backup_20240102120000.sql.gz
│   └── ... (max 10 backups)
├── releases/         → Semua releases
│   ├── 20240101120000/
│   ├── 20240102120000/
│   └── ... (max 2 releases)
└── deployment.log    → Log deployment history
```

## Troubleshooting

### Deployment Gagal

```bash
# Cek log deployment terakhir
ssh root@45.77.42.220 "tail -50 /var/www/app/deployment.log"

# Cek release yang ada
ssh root@45.77.42.220 "ls -la /var/www/app/releases/"

# Cek symlink current
ssh root@45.77.42.220 "ls -la /var/www/app/current"

# Rollback manual
envoy run rollback
```

### Health Check Gagal

```bash
# Cek status PHP-FPM
ssh root@45.77.42.220 "systemctl status php8.3-fpm"

# Cek Laravel logs
ssh root@45.77.42.220 "tail -50 /var/www/app/current/storage/logs/laravel.log"

# Test endpoint manual
curl -I http://45.77.42.220/up
```

### Database Backup Gagal

Jika backup gagal, deployment tetap lanjut dengan warning. Backup manual:

```bash
ssh root@45.77.42.220 "
  cd /var/www/app &&
  source .env &&
  mysqldump -h \$DB_HOST -u \$DB_USERNAME -p\$DB_PASSWORD \$DB_DATABASE > backup_manual.sql
"
```

## Konfigurasi

### Environment Variables

Tambahkan ke `.env` di server untuk notifikasi:

```env
# Slack (opsional)
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/xxx/yyy/zzz

# Custom health check endpoint
HEALTH_CHECK_URL=http://localhost/up
```

### Supervisor (Queue Workers)

Pastikan supervisor terinstall untuk restart queues otomatis:

```bash
# Cek status
ssh root@45.77.42.220 "supervisorctl status"

# Restart manual
ssh root@45.77.42.220 "supervisorctl restart all"
```

## Best Practices

### 1. Selalu Test di Lokal
```bash
npm ci && npm run build
php artisan test
```

### 2. Review Migration Sebelum Deploy
```bash
php artisan migrate:status
```

### 3. Backup Manual untuk Migrasi Besar
```bash
# Sebelum deploy dengan migrasi besar
ssh root@45.77.42.220 "mysqldump -u root db_name > pre_migration_backup.sql"
```

### 4. Pantau Deployment
```bash
# Terminal 1: Watch deployment
envoy run deploy

# Terminal 2: Monitor logs
ssh root@45.77.42.220 "tail -f /var/www/app/current/storage/logs/laravel.log"
```

### 5. Siap Rollback
```bash
# Selalu siap rollback jika ada masalah
envoy run rollback
```

## Perbandingan Deploy Types

| Fitur | `deploy` | `deploy:quick` |
|-------|----------|----------------|
| Clone & Composer | ✅ | ✅ |
| Database Backup | ✅ | ❌ |
| Migrations | ✅ | ❌ |
| Optimize | ✅ | ✅ |
| Health Check | ✅ | ✅ |
| Durasi | ~2-3 min | ~1-2 min |
| Use Case | Normal | Hotfix |

## Maintenance Mode

Jika perlu maintenance mode selama deploy (opsional):

```bash
# Enable maintenance
ssh root@45.77.42.220 "cd /var/www/app/current && php artisan down"

# Deploy
envoy run deploy

# Disable maintenance
ssh root@45.77.42.220 "cd /var/www/app/current && php artisan up"
```

---

**Catatan:** Semua perintah dijalankan dari local machine dengan Envoy terinstall.
