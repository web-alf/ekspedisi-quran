# INSTALL LARAVEL EXCEL PACKAGE

Untuk mengatasi error "Class Maatwebsite\Excel\Facades\Excel not found", ikuti langkah-langkah berikut:

## Langkah 1: Install Package via Composer

Jalankan perintah berikut di terminal dari root direktori project:

```bash
composer require maatwebsite/excel
```

## Langkah 2: Publish Config File (Opsional)

Jika ingin mengkustomisasi konfigurasi Excel:

```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

## Langkah 3: Clear Cache (Jika Diperlukan)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Langkah 4: Restart Development Server

```bash
php artisan serve
```

## Verifikasi Instalasi

Setelah instalasi selesai, coba akses halaman import di `/admin/wakif` dan klik tombol "Import Excel". 

Tombol tersebut seharusnya sudah berfungsi dan mengarahkan ke halaman import yang baru dibuat.

## Troubleshooting

Jika masih ada error:

1. Pastikan `composer.json` sudah memiliki dependency `"maatwebsite/excel": "^3.1"`
2. Jalankan `composer dump-autoload`
3. Clear semua cache Laravel
4. Restart web server

## Files yang Sudah Dibuat/Diperbaiki:

- ✅ `resources/js/Pages/Admin/Wakif/Import.svelte` - Halaman import Excel
- ✅ `app/Exports/WakifTemplateExport.php` - Export class untuk template
- ✅ `app/Http/Controllers/Admin/WakifController.php` - Controller sudah diperbaiki
- ✅ `app/Imports/WakifImport.php` - Import class sudah ada
- ✅ `composer.json` - Dependency sudah ditambahkan

Setelah mengikuti langkah-langkah di atas, fitur import Excel akan berfungsi dengan sempurna! 🎉
