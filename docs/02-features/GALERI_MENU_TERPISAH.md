# Pemisahan Menu Gallery dari Settings

## Perubahan yang Dilakukan

### 1. AdminLayout.svelte
**File:** `resources/js/Layouts/AdminLayout.svelte`

**Perubahan:**
- Memisahkan menu "Gallery" dari dropdown "Konten Landing" 
- Menambahkan menu "Gallery" sebagai menu terpisah
- Menambahkan ikon 🖼️ untuk menu Gallery

**Detail Perubahan:**
```javascript
// SEBELUM: Gallery ada di dalam dropdown "Konten Landing"
{ 
  label: 'Konten Landing', 
  route: null,
  dropdown: true,
  children: [
    { label: 'Testimonial', route: '/admin/testimonials' },
    { label: 'Gallery', route: '/admin/galleries' },      // ← Dihapus dari sini
    { label: 'FAQ', route: '/admin/faqs' }
  ]
}

// SESUDAH: Gallery menjadi menu terpisah
{ 
  label: 'Gallery', 
  route: '/admin/galleries',
  requiredPermissions: ['settings.read']
},
{ 
  label: 'Konten Landing', 
  dropdown: true,
  children: [
    { label: 'Testimonial', route: '/admin/testimonials' },
    { label: 'FAQ', route: '/admin/faqs' }
  ]
}
```

### 2. SettingController.php  
**File:** `app/Http/Controllers/Admin/SettingController.php`

**Perubahan:**
- Menambahkan group 'gallery' ke dalam `getSettingGroups()`
- Ini memungkinkan settings dengan group 'gallery' untuk dikelompokkan dengan benar

```php
private function getSettingGroups()
{
    return [
        'general' => 'Umum',
        'landing' => 'Halaman Utama',
        'gallery' => 'Galeri',        // ← Ditambahkan
        'contact' => 'Kontak',
        // ... dst
    ];
}
```

### 3. Gallery Index Page
**File:** `resources/js/Pages/Admin/Galleries/Index.svelte`

**Perubahan:**
- Memperbarui deskripsi halaman untuk lebih sesuai dengan konteks menu terpisah

```javascript
// SEBELUM
<p class="mt-2 text-gray-600">Kelola gambar gallery yang ditampilkan di halaman utama</p>

// SESUDAH  
<p class="mt-2 text-gray-600">Kelola koleksi gambar dan foto untuk ditampilkan di halaman utama</p>
```

## Hasil Akhir

### Struktur Menu Baru:
```
📊 Dashboard
👥 Kelola Donatur  
📦 Pengiriman
📜 Sertifikat (dropdown)
📋 Permintaan Mushaf
💬 Notifikasi WhatsApp (dropdown)
🏭 Manajemen Gudang (dropdown)
⚙️ Manajemen Pengguna (dropdown)
🔧 Pengaturan Sistem
🖼️ Gallery                    ← Menu terpisah baru
🎨 Konten Landing (dropdown)
   ├── Testimonial
   └── FAQ
```

## Fitur Gallery yang Tersedia

### 1. Gallery Management (Model Gallery)
- **Route:** `/admin/galleries`
- **Fitur:** CRUD lengkap untuk gambar gallery
- **Database:** table `galleries`
- **Controller:** `GalleryController`

### 2. Gallery Settings (Model Setting)
- **Route:** `/admin/settings` (group: gallery)
- **Fitur:** Pengaturan gallery via sistem settings
- **Database:** table `settings` dengan group 'gallery' atau 'landing'
- **Controller:** `SettingController`

## Catatan Penting

1. **Dua Sistem Gallery:** 
   - Gallery management (model Gallery) untuk CRUD gambar
   - Gallery settings (model Setting) untuk konfigurasi tampilan

2. **Permissions:** 
   - Menu Gallery menggunakan permission `settings.read`
   - Sama dengan permission untuk Settings dan Konten Landing

3. **Backward Compatibility:**
   - Semua route dan functionality tetap sama
   - Hanya struktur menu yang berubah
   - Tidak ada breaking changes

## Testing

Untuk memastikan perubahan berfungsi dengan baik:

1. **Login sebagai admin**
2. **Cek menu Gallery terpisah** - harus muncul di sidebar
3. **Akses `/admin/galleries`** - harus berfungsi normal
4. **Cek menu Konten Landing** - harus hanya berisi Testimonial dan FAQ
5. **Test permissions** - pastikan user dengan `settings.read` bisa akses Gallery

## Rollback

Jika perlu mengembalikan ke struktur lama:

1. Revert perubahan di `AdminLayout.svelte`
2. Hapus 'gallery' => 'Galeri' dari `getSettingGroups()`
3. Revert perubahan deskripsi di Gallery Index

File backup tersimpan di Git history.
