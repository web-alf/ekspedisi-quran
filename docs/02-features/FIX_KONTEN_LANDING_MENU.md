# Fix: Menu Konten Landing Tidak Muncul di Production

## Masalah
Menu "Konten Landing" tidak muncul di production meskipun sudah login sebagai super-admin dan mencentang semua permissions.

## Penyebab
1. Cache permission/role yang belum di-clear di production
2. Permission super-admin yang perlu di-refresh setelah seeder update

## Solusi

Jalankan command berikut **di production server**:

### Step 1: Clear All Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Step 2: Clear Permission Cache (Spatie)
```bash
php artisan permission:cache-reset
```

### Step 3: Re-run Permission Seeder
```bash
php artisan db:seed --class=RolePermissionSeeder
```

### Step 4: Verify Permissions
```bash
php artisan tinker
```

Lalu jalankan di tinker:
```php
// Check super-admin role permissions
$superAdmin = \Spatie\Permission\Models\Role::where('name', 'super-admin')->first();
$permissions = $superAdmin->permissions()->pluck('name');
echo "Total permissions: " . $permissions->count() . "\n";
echo "Has settings.read: " . ($permissions->contains('settings.read') ? 'YES' : 'NO') . "\n";
echo "Has settings.write: " . ($permissions->contains('settings.write') ? 'YES' : 'NO') . "\n";

// Check specific user
$user = \App\Models\User::where('email', 'your-email@example.com')->first();
echo "\nUser: {$user->name}\n";
echo "Has settings.read: " . ($user->can('settings.read') ? 'YES' : 'NO') . "\n";
exit
```

### Step 5: Logout & Login Ulang
Setelah semua command di atas, **logout** dan **login ulang** di browser.

## Alternatif: Manual Permission Assignment

Jika masih tidak muncul, assign permission manual via tinker:

```bash
php artisan tinker
```

```php
$superAdmin = \Spatie\Permission\Models\Role::where('name', 'super-admin')->first();
$settingsRead = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'settings.read']);
$settingsWrite = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'settings.write']);

$superAdmin->givePermissionTo($settingsRead);
$superAdmin->givePermissionTo($settingsWrite);

echo "Permissions assigned!\n";

// Clear cache lagi
\Artisan::call('permission:cache-reset');
exit
```

## Verifikasi Akhir

1. Logout dari admin panel
2. Login ulang
3. Check menu sidebar - "Konten Landing" harus muncul
4. Klik menu tersebut - harus bisa akses semua submenu

## Notes

Menu "Konten Landing" membutuhkan permission `settings.read` untuk bisa tampil (lihat AdminLayout.svelte:183).

Submenu di dalamnya ada yang butuh `settings.write` juga (Testimonial dan FAQ).
