# 🔒 Project Structure Lock Rules — EkspedisiQ

> **Tujuan:** Menjaga agar struktur proyek tidak berubah selama proses perbaikan fitur dan bug fix.
> Semua perbaikan HARUS dilakukan di dalam file dan direktori yang sudah ada.

---

## 🚫 DILARANG KERAS

### 1. Jangan Membuat Folder Baru
Tidak boleh membuat folder/direktori baru di level manapun kecuali mendapat persetujuan eksplisit dari user.

### 2. Jangan Menghapus File atau Folder yang Sudah Ada
Semua file dan folder yang ada saat ini WAJIB dipertahankan. Tidak boleh menghapus, memindahkan, atau me-rename file/folder manapun.

### 3. Jangan Mengubah Dependencies
Tidak boleh menambah, menghapus, atau mengubah versi package di `composer.json` atau `package.json` tanpa persetujuan user.

### 4. Jangan Mengubah Konfigurasi Build
File-file berikut TIDAK BOLEH dimodifikasi tanpa persetujuan:
- `vite.config.js`
- `vitest.config.js`
- `tailwind.config.js`
- `postcss.config.js`
- `phpunit.xml`
- `composer.json` / `package.json`

### 5. Jangan Membuat Migration Baru
Tidak boleh membuat migration baru tanpa persetujuan user. Perbaikan harus dilakukan di level kode (Model, Controller, Service), bukan di level database schema.

### 6. Jangan Mengubah Struktur Routes
File routes (`web.php`, `api.php`, `console.php`, `public-certificate.php`) tidak boleh ditambah route group baru atau diubah strukturnya. Hanya boleh memperbaiki logic di dalam route yang sudah ada.

---

## ✅ YANG DIPERBOLEHKAN

1. **Memperbaiki bug** di dalam file yang sudah ada
2. **Memperbaiki logic** di Controller, Service, Model, atau Component yang sudah ada
3. **Memperbaiki tampilan/UI** di file Svelte (.svelte) yang sudah ada
4. **Memperbaiki validasi** di Form Request yang sudah ada
5. **Menambah/memperbaiki test** di file test yang sudah ada atau membuat file test baru di folder `tests/` yang sudah ada
6. **Memperbaiki CSS** di file `resources/css/app.css` atau inline Tailwind classes
7. **Memperbaiki query/relasi** di Model yang sudah ada

---

## 📁 Struktur Direktori yang WAJIB Dipertahankan

### Root Project
```
ekspedisiQ/
├── app/
├── bootstrap/
├── config/
├── database/
├── deployment/
├── docs/
├── lang/
├── public/
├── resources/
├── routes/
├── scripts/
├── storage
├── tests/
├── vendor/
├── node_modules/
├── .git/
├── .githooks/
├── .github/
```

### app/ — Backend Application
```
app/
├── Console/
│   └── Commands/
│       ├── Monitoring/
│       ├── Performance/
│       └── Storage/
├── Enums/
│   ├── PermissionEnum.php
│   └── RoleEnum.php
├── Exports/
│   ├── DonaturExport.php
│   ├── DonaturTemplateExport.php
│   ├── MushafRequestExport.php
│   ├── MushafRequestTemplateExport.php
│   └── SettingsExport.php
├── Helpers/
│   ├── HijriHelper.php
│   ├── MediaHelper.php
│   └── no_resi_helper.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── LandingContent/
│   │   │   │   ├── ContactSettingsController.php
│   │   │   │   ├── GeneralSettingsController.php
│   │   │   │   ├── LandingSettingsController.php
│   │   │   │   ├── LegalSettingsController.php
│   │   │   │   ├── SeoSettingsController.php
│   │   │   │   └── SocialSettingsController.php
│   │   │   ├── BoxBulkUpdateController.php
│   │   │   ├── BoxTrackingController.php
│   │   │   ├── BulkOperationsAnalyticsController.php
│   │   │   ├── CacheController.php
│   │   │   ├── CertificateController.php
│   │   │   ├── CertificateTemplateController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── DonaturController.php
│   │   │   ├── FaqController.php
│   │   │   ├── GalleryController.php
│   │   │   ├── MonitoringDashboardController.php
│   │   │   ├── MushafRequestController.php
│   │   │   ├── PengirimanController.php
│   │   │   ├── PengirimanTrackingController.php
│   │   │   ├── PerformanceController.php
│   │   │   ├── PermissionController.php
│   │   │   ├── QRCodeController.php
│   │   │   ├── QueryOptimizationController.php
│   │   │   ├── RoleController.php
│   │   │   ├── TestimonialController.php
│   │   │   ├── ThermalPrintController.php
│   │   │   ├── UserController.php
│   │   │   ├── VideoController.php
│   │   │   └── WakafItemsController.php
│   │   ├── Api/
│   │   │   ├── JobProgressController.php
│   │   │   └── WilayahController.php
│   │   ├── Auth/
│   │   │   └── AuthenticatedSessionController.php
│   │   ├── Public/
│   │   │   ├── CertificateDownloadController.php
│   │   │   ├── LegalController.php
│   │   │   ├── MapController.php
│   │   │   ├── MushafRequestController.php
│   │   │   ├── MushafTrackingController.php
│   │   │   └── TrackingController.php
│   │   ├── Supervisor/
│   │   │   └── WarehouseMonitorController.php
│   │   ├── Warehouse/
│   │   │   ├── BoxScannerController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── JobMonitorController.php
│   │   │   ├── PackingController.php
│   │   │   └── PerformanceController.php
│   │   ├── Controller.php
│   │   └── SitemapController.php
│   ├── Middleware/
│   │   ├── Authenticate.php
│   │   ├── CheckSessionTimeout.php
│   │   ├── EncryptCookies.php
│   │   ├── EnsureUserIsActive.php
│   │   ├── FileSizeHandler.php
│   │   ├── ForceJsonResponse.php
│   │   ├── HandleInertiaRequests.php
│   │   ├── PerformanceMonitoring.php
│   │   ├── PermissionMiddleware.php
│   │   ├── QueryPerformanceMiddleware.php
│   │   ├── RateLimitHandler.php
│   │   ├── RedirectIfAuthenticated.php
│   │   ├── RoleMiddleware.php
│   │   ├── VerifyCsrfToken.php
│   │   └── WarehouseAccess.php
│   ├── Requests/
│   │   ├── Admin/
│   │   │   └── ScanStatusRequest.php
│   │   ├── BulkDestroyWakafItemsRequest.php
│   │   ├── StoreDonaturRequest.php
│   │   ├── StoreWakafItemsRequest.php
│   │   ├── UpdateDonaturRequest.php
│   │   └── UpdateWakifNamesRequest.php
│   └── Kernel.php
├── Imports/
│   ├── DonaturImport.php
│   ├── MushafRequestImport.php
│   └── SettingsImport.php
├── Jobs/
│   ├── Certificate/
│   ├── Traits/
│   └── Warehouse/
├── Listeners/
│   ├── LogQueueJobFailure.php
│   └── SendCertificateJobNotification.php
├── Models/  (31 model files — jangan tambah/hapus)
├── Notifications/
│   ├── CertificateJobCompletedNotification.php
│   └── QueueJobFailedNotification.php
├── Observers/
│   ├── CacheInvalidationObserver.php
│   └── PengirimanObserver.php
├── Policies/
│   ├── CertificateTemplatePolicy.php
│   ├── DonaturPolicy.php
│   ├── FaqPolicy.php
│   ├── GalleryPolicy.php
│   ├── MushafRequestPolicy.php
│   ├── PengirimanPolicy.php
│   ├── SertifikatPolicy.php
│   ├── TestimonialPolicy.php
│   ├── VideoPolicy.php
│   └── WarehousePolicy.php
├── Providers/
│   ├── AppServiceProvider.php
│   ├── CacheServiceProvider.php
│   ├── EventServiceProvider.php
│   └── ImageOptimizationServiceProvider.php
├── Services/
│   ├── Cache/
│   │   ├── BaseCacheService.php
│   │   ├── CacheManager.php
│   │   ├── DashboardCacheService.php
│   │   ├── GeographicCacheService.php
│   │   ├── QueryCacheService.php
│   │   ├── ReferenceDataCacheService.php
│   │   ├── StatusPengirimanCache.php
│   │   └── UserCacheService.php
│   ├── Monitoring/
│   │   ├── AlertingService.php
│   │   ├── MetricsCollectionService.php
│   │   └── PerformanceBenchmarkService.php
│   ├── AnalyticsService.php
│   ├── BatchCertificateService.php
│   ├── BoxBasedAssignmentService.php
│   ├── CertificateService.php
│   ├── CertificateStorageService.php
│   ├── ConcurrencyMonitorService.php
│   ├── ConsolidatedCertificateService.php
│   ├── DonaturImportService.php
│   ├── FileStorageService.php
│   ├── ImageOptimizationService.php
│   ├── IndexOptimizer.php
│   ├── LandingSectionRegistry.php
│   ├── MediaUploadService.php
│   ├── NPlusOneDetector.php
│   ├── OnDemandCertificateService.php
│   ├── PackingAssignmentService.php
│   ├── PerformanceMonitoringService.php
│   ├── PostDeliveryService.php
│   ├── QueryAnalyzer.php
│   └── StorageMonitoringService.php
└── Traits/
    ├── ApiResponse.php
    └── HandlesImageUpload.php
```

### resources/js/ — Frontend (Svelte + Inertia)
```
resources/js/
├── Components/
│   ├── Certificate/
│   ├── Tracking/
│   ├── UI/
│   ├── Warehouse/
│   └── (32 component .svelte files)
├── Layouts/
│   ├── AdminLayout.svelte
│   └── PublicLayout.svelte
├── Pages/
│   ├── Admin/
│   │   ├── BoxTracking/
│   │   ├── BulkOperations/
│   │   ├── CertificateTemplates/
│   │   ├── Certificates/
│   │   ├── Components/
│   │   ├── Donatur/
│   │   ├── Faqs/
│   │   ├── Galleries/
│   │   ├── GenerateQR/
│   │   ├── MushafRequest/
│   │   ├── Pengiriman/
│   │   ├── Performance/
│   │   ├── Permissions/
│   │   ├── PublicTracking/
│   │   ├── Roles/
│   │   ├── ScanQR/
│   │   ├── Settings/
│   │   ├── Testimonials/
│   │   ├── Users/
│   │   ├── Videos/
│   │   └── Dashboard.svelte
│   ├── Auth/
│   ├── Errors/
│   ├── Public/
│   ├── Supervisor/
│   │   ├── ManualAssignment.svelte
│   │   ├── PerformanceReport.svelte
│   │   └── WarehouseMonitor.svelte
│   ├── Warehouse/
│   │   ├── BoxScanner.svelte
│   │   ├── Dashboard.svelte
│   │   ├── Packing.svelte
│   │   └── Performance.svelte
│   ├── Landing.svelte
│   └── Welcome.svelte
├── constants/
│   ├── permissions.js
│   └── roles.js
├── stores/
│   ├── dialog.js
│   └── toast.js
├── utils/
│   ├── assignTarget.js
│   ├── auth.js
│   ├── formHelpers.js
│   ├── lazyload.js
│   ├── logger.js
│   ├── mobileDetection.js
│   ├── notifications.js
│   ├── permissions.js
│   ├── qrScannerUtils.js
│   ├── scanner-core.js
│   ├── scanner-memory.js
│   ├── scanner-test.js
│   └── seo.js
├── app.js
└── bootstrap.js
```

### routes/
```
routes/
├── web.php
├── api.php
├── console.php
└── public-certificate.php
```

### config/ (20 files — jangan tambah/hapus)
```
config/
├── app.php
├── auth.php
├── cache.php
├── certificate.php
├── database.php
├── filesystems.php
├── image_optimization.php
├── logging.php
├── mail.php
├── monitoring.php
├── packing.php
├── performance_cache.php
├── permission.php
├── queue.php
├── rate-limiting.php
├── services.php
├── session.php
├── storage_optimization.php
├── upload.php
└── warehouse_security.php
```

### database/
```
database/
├── factories/   (31 factory files — jangan tambah/hapus)
├── migrations/  (106 migration files — JANGAN TAMBAH tanpa persetujuan)
└── seeders/     (18 seeder files + backup/)
```

### resources/views/ (Blade Templates)
```
resources/views/
├── admin/
├── certificates/
├── errors/
├── app.blade.php
└── welcome.blade.php
```

### resources/css/
```
resources/css/
├── app.css
└── leaflet.css
```

---

## 🔧 Panduan Perbaikan Fitur

### Urutan Prioritas Saat Memperbaiki Bug/Fitur:

1. **Identifikasi file yang bermasalah** — cari di struktur yang sudah ada
2. **Perbaiki di tempat** — edit file yang ada, jangan buat file baru
3. **Ikuti konvensi yang ada** — lihat file sejenis (sibling files) untuk referensi
4. **Tulis/perbarui test** — gunakan file test yang ada atau buat di folder test yang sudah ada
5. **Jalankan test terkait** — pastikan perbaikan tidak merusak fitur lain

### Lokasi Perbaikan Berdasarkan Jenis Masalah:

| Jenis Masalah | Lokasi Perbaikan |
|---|---|
| Bug tampilan/UI | `resources/js/Pages/` atau `resources/js/Components/` |
| Bug logic backend | `app/Http/Controllers/` atau `app/Services/` |
| Bug validasi | `app/Http/Requests/` |
| Bug model/relasi | `app/Models/` |
| Bug middleware/auth | `app/Http/Middleware/` atau `app/Policies/` |
| Bug packing system | `app/Services/PackingAssignmentService.php`, `app/Services/BoxBasedAssignmentService.php` |
| Bug sertifikat | `app/Services/CertificateService.php`, `BatchCertificateService.php`, `ConsolidatedCertificateService.php` |
| Bug QR scanner | `resources/js/utils/qrScannerUtils.js`, `scanner-core.js` |
| Bug routing | `routes/web.php`, `routes/api.php` (edit yang ada, jangan tambah group baru) |

---

## ⚠️ Checklist Sebelum Setiap Perubahan

- [ ] Apakah perubahan ini hanya memodifikasi file yang sudah ada? (WAJIB Ya)
- [ ] Apakah perubahan ini membuat folder baru? (WAJIB Tidak)
- [ ] Apakah perubahan ini mengubah dependencies? (WAJIB Tidak tanpa persetujuan)
- [ ] Apakah perubahan ini memerlukan migration baru? (WAJIB Tidak tanpa persetujuan)
- [ ] Apakah test terkait sudah dijalankan dan passed?
