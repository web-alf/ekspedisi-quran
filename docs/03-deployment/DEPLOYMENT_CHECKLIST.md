# Checklist Deployment Aplikasi Laravel ke VPS

## 1. Persiapan VPS

### Server Requirements
- [ ] VPS dengan Ubuntu 22.04 LTS atau lebih baru
- [ ] RAM minimal 2GB (disarankan 4GB)
- [ ] Storage minimal 20GB
- [ ] IP Public yang sudah terkonfigurasi
- [ ] Akses root/sudo ke server

### Software yang Harus Diinstall
- [ ] **Web Server**: Nginx atau Apache (disarankan Nginx)
- [ ] **PHP 8.3** dengan ekstensi:
  - [ ] php8.3-fpm
  - [ ] php8.3-cli
  - [ ] php8.3-common
  - [ ] php8.3-mysql
  - [ ] php8.3-mbstring
  - [ ] php8.3-xml
  - [ ] php8.3-curl
  - [ ] php8.3-zip
  - [ ] php8.3-bcmath
  - [ ] php8.3-gd
  - [ ] php8.3-intl
- [ ] **Database**: MySQL 8.0 atau MariaDB 10.6+
- [ ] **Node.js & NPM**: Versi 18.x atau lebih baru
- [ ] **Composer**: Versi 2.x
- [ ] **Git**
- [ ] **Supervisor** (untuk queue workers)
- [ ] **Redis** (opsional, untuk cache dan queue)
- [ ] **Certbot** (untuk SSL Let's Encrypt)

### Konfigurasi Server
- [ ] Setup firewall (UFW)
  ```bash
  ufw allow 22/tcp
  ufw allow 80/tcp
  ufw allow 443/tcp
  ufw enable
  ```
- [ ] Buat user non-root untuk deployment
- [ ] Setup SSH key authentication
- [ ] Disable password authentication SSH
- [ ] Konfigurasi swap memory (jika RAM terbatas)

## 2. Persiapan Aplikasi (Lokal)

### Environment Configuration
- [ ] Buat file `.env.production` dengan konfigurasi production
- [ ] Update konfigurasi:
  ```env
  APP_ENV=production
  APP_DEBUG=false
  APP_URL=https://yourdomain.com
  
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=your_database
  DB_USERNAME=your_username
  DB_PASSWORD=your_password
  
  SESSION_DRIVER=database
  CACHE_STORE=redis (atau database)
  QUEUE_CONNECTION=database
  ```

### Build Assets
- [ ] Install dependencies: `npm install`
- [ ] Build production assets: `npm run build`
- [ ] Commit hasil build ke repository (atau setup CI/CD)

### Testing
- [ ] Jalankan semua tests: `php artisan test`
- [ ] Test di environment staging jika ada
- [ ] Backup database development

## 3. Setup VPS (Pertama Kali)

### Database Setup
- [ ] Buat database baru
  ```sql
  CREATE DATABASE ekspedisi_quran CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```
- [ ] Buat user database dengan privileges terbatas
  ```sql
  CREATE USER 'app_user'@'localhost' IDENTIFIED BY 'secure_password';
  GRANT ALL PRIVILEGES ON ekspedisi_quran.* TO 'app_user'@'localhost';
  FLUSH PRIVILEGES;
  ```

### Direktori Aplikasi
- [ ] Buat struktur direktori
  ```bash
  mkdir -p /var/www/app
  mkdir -p /var/www/app/releases
  mkdir -p /var/www/app/storage
  ```
- [ ] Set ownership ke www-data
  ```bash
  chown -R www-data:www-data /var/www/app
  ```

### Nginx Configuration
- [ ] Buat file konfigurasi Nginx di `/etc/nginx/sites-available/ekspedisi-quran`
- [ ] Setup konfigurasi (contoh):
  ```nginx
  server {
      listen 80;
      server_name yourdomain.com;
      root /var/www/app/current/public;
      
      index index.php;
      
      location / {
          try_files $uri $uri/ /index.php?$query_string;
      }
      
      location ~ \.php$ {
          fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
          fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
          include fastcgi_params;
      }
      
      location ~ /\.(?!well-known).* {
          deny all;
      }
  }
  ```
- [ ] Enable site: `ln -s /etc/nginx/sites-available/ekspedisi-quran /etc/nginx/sites-enabled/`
- [ ] Test konfigurasi: `nginx -t`
- [ ] Reload Nginx: `systemctl reload nginx`

### SSL Certificate
- [ ] Install SSL dengan Certbot
  ```bash
  certbot --nginx -d yourdomain.com
  ```
- [ ] Setup auto-renewal

### Environment File
- [ ] Upload `.env` file ke `/var/www/app/.env`
- [ ] Set permission: `chmod 600 /var/www/app/.env`
- [ ] Set ownership: `chown www-data:www-data /var/www/app/.env`

## 4. Deployment dengan Envoy (Automated)

### Setup GitHub Secrets
- [ ] Generate SSH key pair untuk deployment
- [ ] Add public key ke server `~/.ssh/authorized_keys`
- [ ] Add private key ke GitHub Secrets sebagai `SSH_PRIVATE_KEY`

### Konfigurasi Envoy (Sudah Ada)
File `Envoy.blade.php` sudah dikonfigurasi dengan:
- Zero-downtime deployment
- Automatic rollback capability
- Storage persistence
- Cache clearing
- Migration running

### GitHub Actions (Sudah Ada)
File `.github/workflows/deploy.yml` sudah dikonfigurasi untuk:
- Auto deploy saat push ke branch `main`
- Install dependencies
- Run Envoy deployment

### Jalankan Deployment
- [ ] Push ke branch `main` untuk trigger deployment
- [ ] Monitor GitHub Actions untuk melihat progress
- [ ] Verifikasi deployment berhasil

## 5. Post-Deployment Tasks

### Queue Workers (Jika Digunakan)
- [ ] Setup Supervisor configuration di `/etc/supervisor/conf.d/laravel-worker.conf`:
  ```ini
  [program:laravel-worker]
  process_name=%(program_name)s_%(process_num)02d
  command=php /var/www/app/current/artisan queue:work --sleep=3 --tries=3
  autostart=true
  autorestart=true
  user=www-data
  numprocs=4
  redirect_stderr=true
  stdout_logfile=/var/www/app/storage/logs/worker.log
  ```
- [ ] Start workers: `supervisorctl reread && supervisorctl update && supervisorctl start all`

### Cron Jobs
- [ ] Setup Laravel scheduler di crontab:
  ```bash
  * * * * * cd /var/www/app/current && php artisan schedule:run >> /dev/null 2>&1
  ```

### Monitoring
- [ ] Setup log rotation untuk Laravel logs
- [ ] Install monitoring tools (optional):
  - [ ] New Relic atau Datadog
  - [ ] Laravel Telescope (dev only)
  - [ ] Sentry untuk error tracking
- [ ] Setup backup database otomatis

### Security Hardening
- [ ] Disable directory listing di Nginx
- [ ] Setup rate limiting
- [ ] Configure CORS jika ada API
- [ ] Review dan update security headers
- [ ] Setup fail2ban untuk SSH

## 6. Testing Production

### Functional Testing
- [ ] Test homepage loading
- [ ] Test login/logout functionality
- [ ] Test critical user flows:
  - [ ] Warehouse packing process
  - [ ] Donatur management
  - [ ] Wakaf management
  - [ ] Certificate generation
- [ ] Test file uploads
- [ ] Test email sending (jika ada)

### Performance Testing
- [ ] Check page load times
- [ ] Monitor memory usage
- [ ] Check database query performance
- [ ] Test under load (optional)

### Rollback Plan
- [ ] Dokumentasi cara rollback ke versi sebelumnya
- [ ] Test rollback procedure di staging
- [ ] Backup database sebelum deployment major

## 7. Maintenance

### Regular Tasks
- [ ] Monitor disk usage
- [ ] Check log files untuk errors
- [ ] Update dependencies secara berkala
- [ ] Backup database rutin
- [ ] Monitor uptime dan performance

### Emergency Contacts
- [ ] Dokumentasi kontak VPS provider
- [ ] Akses emergency ke server
- [ ] Backup admin credentials

## Notes Khusus untuk Project Ini

1. **Database**: Aplikasi menggunakan SQLite untuk development, pastikan sudah dimigrasi ke MySQL/MariaDB untuk production
2. **Storage**: Aplikasi menyimpan file PDF sertifikat, pastikan storage directory persistent
3. **Queue**: Aplikasi menggunakan database queue, pertimbangkan Redis untuk production
4. **Session**: Menggunakan database session, pastikan tabel sessions ada
5. **Inertia + Svelte**: Pastikan assets sudah di-build dengan `npm run build`

## Quick Deploy Commands

### Manual Deployment (tanpa CI/CD)
```bash
# Di lokal
npm run build
git add .
git commit -m "Build assets for production"
git push origin main

# GitHub Actions akan otomatis deploy
```

### Rollback jika Error
```bash
# SSH ke server
cd /var/www/app
ln -nfs releases/[previous_release] current
sudo systemctl restart php8.3-fpm
```

### Check Logs
```bash
# Laravel logs
tail -f /var/www/app/storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/error.log
tail -f /var/log/nginx/access.log

# PHP-FPM logs
tail -f /var/log/php8.3-fpm.log
```