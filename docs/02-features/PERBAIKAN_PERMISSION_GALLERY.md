# Pembaruan Permission Gallery - Memperbaiki Masalah "Kosong"

## Masalah yang Ditemukan

Halaman Gallery menampilkan kosong karena:
1. **Permission Mismatch**: Route galleries memerlukan `settings.write` tapi menu menggunakan `settings.read`
2. **Data Gallery**: Kemungkinan belum ada data gallery di database

## Perbaikan yang Dilakukan

### 1. **Pemisahan Permission Route Gallery**
**File:** `routes/web.php`

**SEBELUM:** 
```php
// Semua route galleries butuh settings.write
Route::middleware(['permission:settings.write'])->group(function() {
    Route::resource('galleries', GalleryController::class);
    // dst...
});
```

**SESUDAH:**
```php
// Read access - hanya perlu settings.read
Route::middleware(['permission:settings.read'])->group(function() {
    Route::get('galleries', [GalleryController::class, 'index'])->name('galleries.index');
    Route::get('galleries/{gallery}', [GalleryController::class, 'show'])->name('galleries.show');
});

// Write access - perlu settings.write
Route::middleware(['permission:settings.write'])->group(function() {
    Route::get('galleries/create', [GalleryController::class, 'create'])->name('galleries.create');
    Route::post('galleries', [GalleryController::class, 'store'])->name('galleries.store');
    Route::get('galleries/{gallery}/edit', [GalleryController::class, 'edit'])->name('galleries.edit');
    Route::put('galleries/{gallery}', [GalleryController::class, 'update'])->name('galleries.update');
    Route::delete('galleries/{gallery}', [GalleryController::class, 'destroy'])->name('galleries.destroy');
    Route::post('galleries/{gallery}/toggle-status', [GalleryController::class, 'toggleStatus'])->name('galleries.toggle-status');
    Route::post('galleries/update-order', [GalleryController::class, 'updateOrder'])->name('galleries.update-order');
});
```

### 2. **Update Permission di Menu**
**File:** `AdminLayout.svelte`

```javascript
{ 
  label: 'Gallery', 
  route: '/admin/galleries',
  requiredPermissions: ['settings.read']  // ← Menggunakan settings.read
}
```

### 3. **Conditional UI berdasarkan Permission**
**File:** `Galleries/Index.svelte`

**Perubahan:**
- Tombol "Tambah Gallery" hanya muncul untuk user dengan `settings.write`
- Action buttons (Edit, Delete, Toggle Status) hanya muncul untuk user dengan `settings.write`
- User dengan `settings.read` bisa view tapi tidak bisa edit

```javascript
// Check permission
$: userPermissions = $page.props.auth?.user?.permissions || [];
$: canWrite = userPermissions.includes('settings.write');

// Conditional rendering
{#if canWrite}
  <a href="/admin/galleries/create">+ Tambah Gallery</a>
{/if}
```

### 4. **Script Seeding Data Gallery**
**File:** `run-gallery-seeder.sh`

```bash
#!/bin/bash
cd /Users/agus/Herd/ekspedisi-quran
php artisan db:seed --class=GallerySeeder
```

## Cara Menjalankan Perbaikan

### 1. **Jalankan Gallery Seeder** (jika belum ada data)
```bash
# Beri permission execute
chmod +x run-gallery-seeder.sh

# Jalankan seeder
./run-gallery-seeder.sh
```

### 2. **Clear Cache** (jika perlu)
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

### 3. **Test Akses**
- **User dengan `settings.read`**: Bisa view gallery, tidak bisa edit
- **User dengan `settings.write`**: Bisa view dan edit gallery

## Permission Matrix

| Action | Required Permission | User Role Access |
|--------|-------------------|------------------|
| View Gallery List | `settings.read` | Admin, CS, Supervisor |
| View Gallery Detail | `settings.read` | Admin, CS, Supervisor |
| Add Gallery | `settings.write` | Admin |
| Edit Gallery | `settings.write` | Admin |
| Delete Gallery | `settings.write` | Admin |
| Toggle Status | `settings.write` | Admin |

## Troubleshooting

### Jika masih kosong:

1. **Cek permission user:**
   ```php
   // Di console Laravel
   $user = auth()->user();
   dd($user->permissions->pluck('name')->toArray());
   ```

2. **Cek data gallery:**
   ```php
   // Di console Laravel
   dd(\App\Models\Gallery::count());
   ```

3. **Cek route:**
   ```bash
   php artisan route:list | grep galleries
   ```

### Expected Output Route List:
```
GET|HEAD   admin/galleries ...................... galleries.index › Admin\GalleryController@index
GET|HEAD   admin/galleries/create ............... galleries.create › Admin\GalleryController@create  
POST       admin/galleries ....................... galleries.store › Admin\GalleryController@store
GET|HEAD   admin/galleries/{gallery} ............ galleries.show › Admin\GalleryController@show
GET|HEAD   admin/galleries/{gallery}/edit ....... galleries.edit › Admin\GalleryController@edit
PUT|PATCH  admin/galleries/{gallery} ............ galleries.update › Admin\GalleryController@update
DELETE     admin/galleries/{gallery} ............ galleries.destroy › Admin\GalleryController@destroy
```

## Rollback

Jika ada masalah, rollback dengan:
1. Revert perubahan di `routes/web.php`
2. Ubah permission di `AdminLayout.svelte` kembali ke `settings.write`
3. Hapus conditional permission di `Galleries/Index.svelte`

## Testing Checklist

- [ ] User dengan `settings.read` bisa akses `/admin/galleries`
- [ ] User dengan `settings.read` tidak bisa akses `/admin/galleries/create`
- [ ] User dengan `settings.write` bisa akses semua fitur
- [ ] Data gallery muncul di halaman (jika sudah ada data)
- [ ] Menu "Gallery" muncul di sidebar
