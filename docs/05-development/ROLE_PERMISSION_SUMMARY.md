# Ringkasan Role & Permissions

Dokumentasi ini merangkum keterkaitan role, permission, dan modul UI pada aplikasi Ekspedisi Qur'an. Sumber data utama:

- `database/seeders/RolePermissionSeeder.php` – definisi default role dan permission.
- `resources/js/Layouts/AdminLayout.svelte` dan `resources/js/utils/permissions.js` – kontrol visibilitas menu dan komponen UI.
- `routes/web.php` – middleware `permission:` yang melindungi tiap rute.
- `app/Policies/WarehousePolicy.php` & `app/Providers/AppServiceProvider.php` – logika tambahan yang mengandalkan role/permission.

> Catatan: ringkasan ini menggambarkan kondisi default setelah menjalankan seeder bawaan. Perubahan manual pada role/permission di lingkungan tertentu perlu disesuaikan sendiri.

## Ikhtisar Role Bawaan

| Role             | Deskripsi Singkat                                     | Bundel Permission Utama                                                                           | Restriksi Penting |
|------------------|--------------------------------------------------------|----------------------------------------------------------------------------------------------------|-------------------|
| super-admin      | Kendali penuh sistem (strategis & konfigurasi)         | Seluruh permission **kecuali** operasi gudang langsung (`warehouse.packing.*`, `warehouse.qr.*`, `warehouse.tasks.*`, `warehouse.boxes.*`) | Tidak bisa menjalankan aksi operasional gudang seperti bulk QR/thermal print. |
| customer-service | Layanan pelanggan & permintaan mushaf                  | `donatur.*` (kecuali delete), `mushaf-requests.(read|update|approve|reject|process)`, `shipments.(read|track|update-status)`, `certificates.(generate|read|download)`, `status.track` | Tidak punya akses ke manajemen user, pengaturan sistem, ataupun operasi gudang. |
| warehouse        | Staf gudang (packing, QR, box)                         | Akses penuh modul gudang: `warehouse.*`, `warehouse.qr.*`, `warehouse.box.*`, `shipments.(create|read|update|export)`, `qr.*`, `status.update`, `wakaf-batch.read` | Tidak mendapat permission konfigurasi (templates, settings, dsb). |
| supervisor       | Monitoring gudang & redistribusi tugas                | `supervisor.*`, `warehouse.performance.view`, `warehouse.boxes.view`, `shipments.(read|update|track)`, `qr.verify`, `wakaf-batch.(read|update)`, `status.track` | Tidak boleh melakukan packing/QR generate langsung. |
| courier          | Operasional kurir                                     | `shipments.(read|update|track)`, `donatur.read`, `status.(update|track)`, `dashboard.view` | Akses terbatas pada modul pengiriman dan status. |

> Seeder bawaan *tidak* membuat role bernama `admin`, namun beberapa rate limiter (`AppServiceProvider.php:156`) masih memeriksa role tersebut. Bila role `admin` ingin digunakan, permission-nya perlu dikonfigurasi manual agar konsisten.

## Matriks Permission → Modul UI

Legenda kolom role: `Y` = permission diberikan oleh seeder, `N` = tidak diberikan default, `–` = tidak relevan / modul memakai logika role lain.

### Dashboard & Navigasi Inti

| Fitur / UI                                   | Permission yang Dicek                                 | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|----------------------------------------------|--------------------------------------------------------|------------|----|-----------|------------|---------|---------|
| Dashboard utama (`/admin/dashboard`)         | `dashboard.view` (menu: `AdminLayout.svelte:72`)       | Y          | Y  | Y         | Y          | Y       | Tanpa permission ini menu utama hilang. |
| Analytics widget                              | `dashboard.analytics`                                  | Y          | N  | N         | N          | N       | Komponen analitik hanya muncul untuk super-admin. |

### Manajemen User & Akses

| Fitur / UI                                      | Permission                                             | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|-------------------------------------------------|--------------------------------------------------------|------------|----|-----------|------------|---------|---------|
| CRUD User (`/admin/users`)                      | `users.read` (+ `users.create/update/delete/toggle`)   | Y (semua)  | N  | N         | N          | N       | Hanya super-admin; routes di `routes/web.php:234`. |
| Manajemen role (`/admin/roles`)                 | `roles.read` (+ varian CRUD)                           | Y          | N  | N         | N          | N       | |
| Manajemen permission (`/admin/permissions`)     | `permissions.read`                                     | Y          | N  | N         | N          | N       | Form permission menggunakan kategori berbasis prefix. |

### Donatur & Wakaf Item

| Fitur / UI                                                | Permission                            | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|-----------------------------------------------------------|---------------------------------------|------------|----|-----------|------------|---------|---------|
| Listing donatur (`/admin/donatur`)                        | `donatur.read`                        | Y          | Y  | Y         | Y          | Y       | Menu di `AdminLayout.svelte:80`. |
| Create/update/import/export donatur                       | `donatur.create/update/import/export` | Y          | Y  | N         | N          | N       | Warehouse hanya dapat melihat. |
| Delete donatur                                            | `donatur.delete`                      | Y          | N  | N         | N          | N       | |
| CRUD Wakaf Items                                          | `donatur.update`                      | Y          | Y  | N         | N          | N       | Nested routes memakai middleware `permission:donatur.update`. |

### Pengiriman & Status

| Fitur / UI                                                    | Permission                                    | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|----------------------------------------------------------------|-----------------------------------------------|------------|----|-----------|------------|---------|---------|
| Listing/detail pengiriman (`/admin/pengiriman`)                | `shipments.read`                              | Y          | Y  | Y         | Y          | Y       | Menu tab di `TabNavigation.svelte`. |
| Buat pengiriman                                                | `shipments.create`                            | Y          | N  | Y         | N          | N       | |
| Update / hapus pengiriman                                      | `shipments.update` / `shipments.delete`       | Y/ Y        | N  | Y/ N      | Y/ N       | Y/ N    | Hanya super-admin yang boleh delete; update tersedia untuk warehouse, supervisor, courier. |
| Export pengiriman                                              | `shipments.export`                            | Y          | N  | Y         | N          | N       | |
| Bulk update alamat/status                                      | `shipments.bulk-update` (status), `donatur.update` (alamat) | Y (status) | N | N | N | N | Middleware `throttle:bulk-operations`. |
| Tracking / update status (form)                                | Tidak dilindungi permission secara eksplisit  | –          | –  | –         | –          | –       | `routes/web.php:276-285` tidak memanggil middleware; bergantung pada otentikasi & kebijakan di controller. |
| Status update antar modul (mis. mobile)                        | `status.update`                               | Y          | N  | Y         | N          | Y       | Dipakai oleh API & policy. |
| Status tracking                                                | `status.track`                                | Y          | Y  | N         | Y          | Y       | Digunakan untuk tampilan status publik/internal. |

### QR Code & Thermal Print

| Fitur / UI                                          | Permission (frontend/backend)                                 | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|-----------------------------------------------------|---------------------------------------------------------------|------------|----|-----------|------------|---------|---------|
| Generate QR single (`/admin/pengiriman?mode=...`)   | UI: `can.qr.generate()` (`resources/js/utils/permissions.js`) <br> API: `permission:qr.generate|warehouse.qr.generate` | Y (`qr.generate`) | N | Y (`qr.generate` & `warehouse.qr.generate`) | N | N | |
| Bulk generate QR                                     | `warehouse.qr.bulk_generate`                                  | N          | N  | Y         | N          | N       | Hanya role warehouse yang mendapat izin ini dari seeder. |
| Thermal print bulk (`/admin/thermal-print/bulk`)    | Grup akses: `qr.generate|warehouse.qr.generate|warehouse.qr.bulk_generate` <br> Endpoint: `warehouse.qr.bulk_generate` | N | N | Y | N | N | Super-admin tetap 403 kecuali izin ditambahkan manual. |
| QR verify                                            | UI: `can.qr.verify()`; API: `permission:qr.verify|warehouse.qr.verify` | Y (`qr.verify`) | N | Y (`warehouse.qr.verify`) | Y (`qr.verify`) | N | |

### Modul Gudang

| Fitur / UI                                    | Permission / Policy                                                | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|-----------------------------------------------|--------------------------------------------------------------------|------------|----|-----------|------------|---------|---------|
| Menu "Manajemen Gudang" (nav)                | `warehouse.dashboard` **atau** `supervisor.warehouse.monitor`     | Y          | N  | Y         | Y          | N       | `AdminLayout.svelte:105`. |
| Dashboard gudang (`/admin/warehouse`)         | `warehouse.dashboard`                                             | Y          | N  | Y         | N          | N       | |
| Proses packing (`/admin/warehouse/packing`)   | `warehouse.packing.view` + `WarehousePolicy::viewPacking`         | N (izin tidak ada) | N | Y | N | N | Super-admin diblokir karena izin ini dikecualikan. |
| Box scanner / seal                             | `warehouse.dashboard` (nav) + policy `accessBox`, `warehouse.boxes.*` | `warehouse.boxes.*` dieksklusi sehingga N | N | Y | Y (view saja) | N | Supervisor dapat melihat karena policy mengizinkan role. |
| Laporan kinerja (`/admin/warehouse/performance`)| `warehouse.performance.view`                                      | Y          | N  | Y         | Y          | N       | |
| Update task gudang                              | `warehouse.tasks.update`                                          | N          | N  | Y         | N          | N       | |
| Override box sealed                             | `warehouse.box.update_sealed` (warehouse) atau role supervisor/super-admin via policy | Role: Y / Permission: Y | Role: N | Permission Y | Role: Y (tanpa permission) | N | Policy mengizinkan supervisor & super-admin walau tidak punya permission eksplisit. |

### Modul Supervisor

| Fitur / UI                                         | Permission                                     | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|----------------------------------------------------|-----------------------------------------------|------------|----|-----------|------------|---------|---------|
| Monitor gudang (`/admin/supervisor/warehouse-monitor`)| `supervisor.warehouse.monitor`                | Y          | N  | N         | Y          | N       | |
| Redistribusi & assign tugas                       | `supervisor.warehouse.redistribute`, `supervisor.warehouse.assign` | Y          | N  | N         | Y          | N       | |
| Laporan kinerja supervisor                        | `supervisor.performance.view`/`reports`       | Y          | N  | N         | Y          | N       | |

### Sertifikat & Template

| Fitur / UI                                   | Permission                                    | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|----------------------------------------------|-----------------------------------------------|------------|----|-----------|------------|---------|---------|
| Manajemen sertifikat (`/admin/certificates`) | `certificates.read` (+ create/update/delete)   | Y (semua)  | CS: `generate/read/download` | Warehouse: `generate/read/download` | N | N | Penonaktifan/penyuntingan hanya super-admin. |
| Template sertifikat (`/admin/certificate-templates`)| `templates.read` (+ create/update/delete/set-default/toggle) | Y | N | N | N | N | |

### Mushaf Request

| Fitur / UI                               | Permission                                 | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|------------------------------------------|--------------------------------------------|------------|----|-----------|------------|---------|---------|
| Menu permintaan mushaf (`/admin/mushaf-requests`)| **Nav memakai `mushaf.requests.read`**       | N (mismatch) | N (mismatch) | N (mismatch) | N | N | `AdminLayout.svelte:111` menggunakan titik (`.`) sehingga tidak pernah cocok dengan permission nyata `mushaf-requests.read`. Needs fix. |
| Proses permintaan mushaf                 | `mushaf-requests.*` (lihat seeder)         | Y (seluruh aksi) | CS: read/update/approve/reject/process | Warehouse: read/process | Supervisor: read | Courier: N | UI lainnya menggunakan helper `can.mushafRequests.*` yang benar (pakai `-`). |

### Status & Wakaf Batch

| Fitur / UI                           | Permission                | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|--------------------------------------|---------------------------|------------|----|-----------|------------|---------|---------|
| Penugasan status (internal)          | `status.update`           | Y          | N  | Y         | N          | Y       | Digunakan di berbagai controller/API. |
| Tracking status                       | `status.track`            | Y          | Y  | N         | Y          | Y       | |
| Manajemen wakaf batch                 | `wakaf-batch.(create|read|update|delete)` | Y: semua | N | Y: read | Y: read/update | N | |

### Konten & Pengaturan Landing (Menu "Konten Landing")

| Fitur / UI                                    | Permission yang Dicari di UI            | Seeder Default | Catatan |
|-----------------------------------------------|-----------------------------------------|----------------|---------|
| Landing, General, Contact, Social, SEO, Legal | `settings.landing.read`, `settings.general.read`, dll | **Tidak dibuat oleh seeder** | Menu tidak pernah muncul sampai permission dibuat manual. |
| Testimonial (`/admin/testimonials`)           | `settings.landing.write` (nav) + `testimonials.*` (routes) | **Tidak dibuat** | Perlu membuat permission `testimonials.*` dan memberikan ke role. |
| Gallery (`/admin/galleries`)                  | `settings.landing.read` (nav) + `gallery.*` (routes)        | **Tidak dibuat** | |
| FAQ (`/admin/faqs`)                           | `settings.landing.write` (nav) + `faq.*` (routes)           | **Tidak dibuat** | |

### Sistem Monitoring & Dev Tools

| Fitur / UI                                            | Permission                 | SuperAdmin | CS | Warehouse | Supervisor | Courier | Catatan |
|-------------------------------------------------------|----------------------------|------------|----|-----------|------------|---------|---------|
| Bulk operations analytics (`/admin/bulk-operations/*`)| `system.monitor`           | Y          | N  | N         | N          | N       | Termasuk Query Optimization, Performance, Monitoring, Cache management (Routes `routes/web.php:595-803`). |
| Cache tools (`/admin/cache/*`)                        | `system.monitor`           | Y          | N  | N         | N          | N       | |

### Rute Khusus CS & Courier

| Prefix `/admin/cs`  | `permission:donatur.read` | SuperAdmin, CS, Warehouse, Supervisor, Courier | Saat ini belum ada halaman khusus; permission sudah tercakup pada modul donatur. |
| Prefix `/admin/courier` | `permission:shipments.read` | SuperAdmin, CS, Warehouse, Supervisor, Courier | Belum ada implementasi UI khusus. |

## Temuan Ketidaksesuaian

1. **Nama permission berbeda antara UI dan backend**
   - Menu "Permintaan Mushaf" memakai `mushaf.requests.read` di `AdminLayout.svelte:111`, sedangkan permission sebenarnya `mushaf-requests.read`. Akibatnya menu tidak muncul walau user punya akses.
   - Dropdown "Konten Landing" menggunakan permission `settings.*.*`, sementara seeder hanya membuat `settings.read|write|delete`. Permission granular tersebut perlu dibuat/manual atau nav diperbaiki.

2. **Konten landing (Gallery/Testimonial/FAQ)**
   - Middleware rute (`routes/web.php:719-783`) mengharuskan permission `gallery.*`, `testimonials.*`, `faq.*`, namun seeder tidak pernah membuat ataupun memasukkannya ke role. Halaman akan selalu 403 sampai permission tersebut dibuat dan di-assign secara manual.

3. **Operasi Thermal Print/Bulk QR**
   - Rute `POST /admin/thermal-print/bulk` tetap meminta `warehouse.qr.bulk_generate`. Super-admin tidak memilikinya secara default sehingga 403 meski sudah bisa membuka halaman. Perlu menambahkan permission itu ke role terkait bila ingin akses.

4. **Rate limiter memeriksa role `admin`**
   - `AppServiceProvider.php:150` memakai `hasRole(['super-admin', 'admin'])`, padahal seeder tidak membuat role `admin`. Bila role ini diperlukan, perlu ditambah manual; bila tidak, kondisi tersebut bisa diubah.

5. **Kebijakan gudang dominan pada role**
   - `WarehousePolicy` mengizinkan beberapa aksi berdasarkan role langsung (mis. super-admin & supervisor bisa override box sealed tanpa permission eksplisit). Dokumentasi per role harus mempertimbangkan aturan policy, bukan hanya permission.

## Rekomendasi Sinkronisasi

1. Perbaiki nama permission di UI navigation (`AdminLayout.svelte`) agar selaras dengan nama sebenarnya (ganti `mushaf.requests.read` → `mushaf-requests.read`, pertimbangkan ulang penggunaan `settings.*.*`).
2. Tambahkan seeder/migrasi yang membuat permission granular untuk konten landing (`settings.landing.*`, `gallery.*`, `testimonials.*`, `faq.*`) **atau** sesuaikan middleware/nav agar memakai permission yang sudah ada (`settings.read`/`write`).
3. Evaluasi apakah super-admin perlu akses thermal print/warehouse bulk. Jika ya, tambahkan `warehouse.qr.bulk_generate` ke role terkait dan reset cache permission.
4. Pertimbangkan untuk mengganti pengecekan rate limiter `hasRole('admin')` dengan permission yang relevan atau tambahkan role `admin` secara resmi.
5. Dokumentasikan perubahan dari waktu ke waktu di file ini setiap kali role/permission mengalami perubahan, agar onboarding dan audit izin lebih mudah.

