# 📦 Ekspedisi Quran

> Sistem Manajemen Distribusi Al-Qur'an Wakaf

Aplikasi web komprehensif untuk mengelola seluruh proses distribusi Al-Qur'an wakaf, dari donasi hingga pengiriman dengan pelacakan real-time.

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![Svelte](https://img.shields.io/badge/Svelte-4.x-FF3E00?style=flat&logo=svelte)](https://svelte.dev)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=flat)](https://inertiajs.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php)](https://php.net)

---

## ✨ Fitur Utama

### 🏢 Manajemen Multi-Role
- **Super Admin**: Strategic oversight & system management
- **Customer Service**: Donatur & request management
- **Warehouse Staff**: Operational packing & shipping
- **Supervisor**: Monitoring & task assignment
- **Courier**: Delivery & status updates

### 📊 Warehouse Packing System
- ✅ Target-based daily assignments
- ✅ Free-pick QR code scanning
- ✅ Jenis-based boxing with auto-creation
- ✅ Shared box collaboration
- ✅ Real-time performance tracking
- ✅ Race-condition safe operations

### 📸 Smart Documentation
- ✅ Camera capture with live preview
- ✅ Front/back camera switching
- ✅ Photo compression & optimization
- ✅ Multiple photos per shipment
- ✅ Gallery upload fallback

### 📜 Certificate Management
- ✅ Auto-generate PDF certificates
- ✅ Consolidated certificates (multiple items)
- ✅ Public certificate verification
- ✅ QR code embedded certificates

### 🗺️ Public Features
- ✅ Landing page with interactive map
- ✅ Real-time shipment tracking
- ✅ Gallery & testimonials
- ✅ FAQ & contact form
- ✅ SEO optimized

---

## 🛠️ Tech Stack

### Backend
- **Framework**: Laravel 12 (PHP 8.4)
- **Database**: MySQL
- **Queue**: Database driver
- **Testing**: Pest v3
- **Code Style**: Laravel Pint

### Frontend
- **Framework**: Svelte 4
- **Router**: Inertia.js v2
- **CSS**: Tailwind CSS v3
- **Build**: Vite
- **Testing**: Vitest + Testing Library

### Key Packages
- **QR Codes**: SimpleSoftwareIO/simple-qrcode
- **PDF**: DomPDF
- **Excel**: Maatwebsite Excel
- **Images**: Intervention Image
- **Permissions**: Spatie Laravel Permission v6
- **Maps**: Leaflet.js

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.4+
- Composer
- Node.js 18+
- MySQL 8+
- Laravel Herd (recommended)

### Installation

1. **Clone repository**
```bash
git clone https://github.com/your-org/ekspedisi-quran.git
cd ekspedisi-quran
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure database** in `.env`
```env
DB_DATABASE=ekspedisi_quran
DB_USERNAME=root
DB_PASSWORD=
```

5. **Run migrations & seeders**
```bash
php artisan migrate --seed
```

6. **Build assets**
```bash
npm run build
```

7. **Start development**
```bash
# Using Laravel Herd (recommended)
herd link ekspedisi-quran

# Or using Artisan
php artisan serve
```

8. **Default credentials**
```
Super Admin:
Email: admin@ekspedisi.com
Password: password

Warehouse:
Email: warehouse@ekspedisi.com
Password: password
```

---

## 📦 Project Structure

```
ekspedisi-quran/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Admin controllers
│   │   │   ├── Warehouse/      # Warehouse operations
│   │   │   ├── Supervisor/     # Supervisor features
│   │   │   └── Public/         # Public pages
│   │   └── Middleware/
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Business logic
│   └── Jobs/                   # Queue jobs
├── resources/
│   ├── js/
│   │   ├── Pages/             # Svelte pages (Inertia)
│   │   ├── Components/        # Reusable components
│   │   ├── Layouts/          # Layout components
│   │   └── utils/            # JS utilities
│   └── css/                   # Stylesheets
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── tests/
│   ├── Feature/              # Integration tests
│   ├── Unit/                 # Unit tests
│   └── setup.js             # Test setup
├── docs/                     # Documentation
└── public/                   # Public assets
```

---

## 🧪 Testing

### Run Tests

```bash
# Backend (Pest)
php artisan test

# Frontend (Vitest)
npm run test

# With coverage
php artisan test --coverage
npm run test:coverage

# Watch mode
npm run test
```

### Test Coverage
- **Backend**: 85%+ coverage
- **Frontend**: 85%+ coverage
- **Total**: 92 test cases

---

## 📚 Documentation

Comprehensive documentation available in `docs/`:

- **[CAMERA_TROUBLESHOOTING.md](docs/CAMERA_TROUBLESHOOTING.md)** - Camera feature troubleshooting guide
- **[TESTING_DOCUMENTATION.md](docs/TESTING_DOCUMENTATION.md)** - Complete testing guide
- **[TESTING_SUMMARY.md](docs/TESTING_SUMMARY.md)** - Test coverage overview
- **[TEST_README.md](docs/TEST_README.md)** - Quick test reference

For developer documentation, see **[CLAUDE.md](CLAUDE.md)** (comprehensive project guide).

---

## 🔑 Key Workflows

### 1. Donatur → Shipment Flow
```
Create Donatur
  ↓
Generate WakafItems
  ↓
Create WakafBatch
  ↓
Create Pengiriman + QR
  ↓
Warehouse Packing
  ↓
Generate Certificate
  ↓
Ship & Track
```

### 2. Mushaf Request Flow
```
Public Request
  ↓
CS Review/Approve
  ↓
Auto-create Shipments
  ↓
Generate QR Codes
  ↓
Warehouse Packing
  ↓
Delivery Tracking
```

### 3. Daily Packing Flow
```
Supervisor Assign Target
  ↓
Staff Scan QR (free-pick)
  ↓
System Assigns Item
  ↓
Get/Create Box for Jenis
  ↓
Add Item (race-safe)
  ↓
Auto-seal when Full
  ↓
Track Progress
```

---

## 🔐 Security

- ✅ CSRF protection on all forms
- ✅ Role-based access control (Spatie Permission)
- ✅ Input validation with Form Requests
- ✅ XSS prevention (escaped output)
- ✅ SQL injection prevention (Eloquent ORM)
- ✅ File upload validation
- ✅ Rate limiting on public endpoints

---

## ⚡ Performance

- ✅ Eager loading to prevent N+1 queries
- ✅ Database query caching
- ✅ Optimistic UI updates (Inertia)
- ✅ Lazy loading for components
- ✅ Image optimization & compression
- ✅ Route caching for production
- ✅ Asset minification

---

## 🛠️ Development

### ⚠️ Pre-Commit Workflow (IMPORTANT!)

**Build dan Test WAJIB dijalankan di lokal sebelum commit!**

```bash
# 1. Build assets (WAJIB!)
npm ci && npm run build

# 2. Run tests (WAJIB!)
php artisan test

# 3. Mark tests passed
touch .tests-passed

# 4. Commit (pre-commit hook akan memverifikasi)
git add .
git commit -m "feat: your changes"
git push origin main
```

📚 **Baca lengkap**: [Local Build Workflow](docs/03-deployment/LOCAL_BUILD_WORKFLOW.md)

💡 **Reminder**: Jalankan `./scripts/commit-reminder.sh` untuk melihat checklist

---

### Code Style

```bash
# Format PHP code
vendor/bin/pint

# Format with specific preset
vendor/bin/pint --preset laravel
```

### Queue Workers

```bash
# Development
php artisan queue:work --tries=3

# Production (use Supervisor)
php artisan queue:listen
```

### Cache Management

```bash
# Clear all cache
php artisan optimize:clear

# Cache routes (production)
php artisan route:cache

# Cache config (production)
php artisan config:cache
```

---

## 🌐 Deployment

### Production Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure proper database credentials
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Set up queue worker (Supervisor)
- [ ] Configure SSL certificate
- [ ] Set up automated backups
- [ ] Configure error monitoring

### Build for Production

```bash
npm run build
php artisan optimize
```

---

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feat/amazing-feature`)
3. Commit changes (`git commit -m 'feat: add amazing feature'`)
4. Run tests (`php artisan test && npm run test`)
5. Format code (`vendor/bin/pint`)
6. Push to branch (`git push origin feat/amazing-feature`)
7. Open Pull Request

---

## 📄 License

This project is proprietary software. All rights reserved.

---

## 📞 Support

- **Documentation**: See `docs/` directory
- **Issues**: [GitHub Issues](https://github.com/your-org/ekspedisi-quran/issues)
- **Email**: support@ekspedisi-quran.com

---

## 🙏 Acknowledgments

Built with:
- [Laravel](https://laravel.com) - The PHP Framework
- [Svelte](https://svelte.dev) - Cybernetically enhanced web apps
- [Inertia.js](https://inertiajs.com) - Modern monolith
- [Tailwind CSS](https://tailwindcss.com) - Utility-first CSS

---

**Version**: 1.0.0
**Last Updated**: 2025-10-11
