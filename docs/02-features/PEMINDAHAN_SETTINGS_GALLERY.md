# Pemindahan Settings Gallery ke Menu Gallery

## Ringkasan Perubahan

Settings yang berkaitan dengan galeri yang sebelumnya ada di `/admin/settings` dengan group `landing` telah dipindahkan ke menu Gallery dengan implementasi tab system.

## ✅ **Struktur Menu Baru**

### **Menu Gallery** (`/admin/galleries`)
Sekarang memiliki 2 tab:

#### **📸 Tab "Gallery Items"**
- CRUD gambar gallery (model Gallery)
- Upload, edit, delete gambar
- Toggle status aktif/nonaktif
- Sorting dan kategori

#### **⚙️ Tab "Pengaturan Gallery"** 
- Settings yang berkaitan dengan tampilan gallery di landing page
- Judul section, subtitle, layout, autoplay, dll
- Upload gambar galeri (1-6)
- Settings boolean, text, textarea

## 🔧 **Perubahan Teknis**

### 1. **Database Migration Script**
**File:** `move-gallery-settings.php`

```php
// Memindahkan settings dari group 'landing' ke 'gallery'
$gallerySettingKeys = [
    'landing_gallery_enabled',
    'landing_gallery_title', 
    'landing_gallery_subtitle',
    'landing_gallery_image_1',
    // dst... 19 settings total
];
```

### 2. **GalleryController Update**
**File:** `app/Http/Controllers/Admin/GalleryController.php`

**Perubahan:**
- `index()`: Mengembalikan galleries + gallerySettings
- `updateSettings()`: Method baru untuk update settings gallery

```php
public function index()
{
    $galleries = Gallery::ordered()->paginate(12);
    $gallerySettings = Setting::where('group', 'gallery')
        ->orderBy('sort_order')
        ->orderBy('label')
        ->get();
        
    return Inertia::render('Admin/Galleries/Index', [
        'galleries' => $galleries,
        'gallerySettings' => $gallerySettings
    ]);
}
```

### 3. **Route Update**
**File:** `routes/web.php`

```php
// Tambahan route untuk update settings
Route::post('galleries/update-settings', [GalleryController::class, 'updateSettings'])
    ->name('galleries.update-settings');
```

### 4. **Frontend dengan Tab System**
**File:** `resources/js/Pages/Admin/Galleries/Index.svelte`

**Fitur Baru:**
- Tab navigation (Gallery Items vs Pengaturan Gallery)
- Settings editor dengan conditional rendering
- File upload handling untuk image settings
- Permission-based UI (read vs write)

```javascript
// Tab state
let activeTab = 'galleries'; // 'galleries' or 'settings'

// Settings editing
let editingSettings = {};
function editSetting(setting) {
    editingSettings[setting.id] = { ...setting };
}
```

## 📋 **Settings yang Dipindahkan**

| Setting Key | Label | Type | Deskripsi |
|-------------|-------|------|-----------|
| `landing_gallery_enabled` | Aktifkan Section Galeri | boolean | Enable/disable gallery section |
| `landing_gallery_title` | Judul Section Galeri | text | Gallery section title |
| `landing_gallery_subtitle` | Subtitle Section Galeri | textarea | Gallery section description |
| `landing_gallery_image_1` - `landing_gallery_image_6` | Gambar Galeri 1-6 | image | Gallery images |
| `landing_gallery_image_X_caption` | Caption Gambar X | text | Image captions |
| `landing_gallery_layout` | Layout Galeri | select | Grid/Slider/Masonry |
| `landing_gallery_autoplay` | Auto-play Slider | boolean | Slider autoplay |
| `landing_gallery_show_captions` | Tampilkan Caption | boolean | Show/hide captions |
| `landing_gallery_lightbox` | Aktifkan Lightbox | boolean | Enable lightbox popup |

**Total:** 19 settings dipindahkan

## 🚀 **Cara Menjalankan Migration**

### **Otomatis (Recommended)**
```bash
chmod +x run-gallery-migration.sh
./run-gallery-migration.sh
```

### **Manual Step by Step**
```bash
# 1. Pindahkan settings
php artisan tinker --execute="include('move-gallery-settings.php');"

# 2. Seed gallery data (opsional)
php artisan db:seed --class=GallerySeeder

# 3. Clear cache
php artisan route:clear
php artisan config:clear
```

## 📊 **Before vs After**

### **SEBELUM:**
```
📱 Menu Struktur:
├── 🔧 Pengaturan Sistem
│   └── 📂 Group: "landing" 
│       ├── Judul Section Galeri
│       ├── Gambar Galeri 1-6
│       └── Settings galeri lainnya
└── 🖼️ Gallery (kosong - hanya CRUD gambar)
```

### **SESUDAH:**
```
📱 Menu Struktur:
├── 🔧 Pengaturan Sistem 
│   └── 📂 Groups lainnya (tidak ada gallery settings)
└── 🖼️ Gallery 
    ├── 📸 Tab: Gallery Items (CRUD gambar)
    └── ⚙️ Tab: Pengaturan Gallery (settings ex-landing)
```

## 🎯 **Hasil Akhir**

### **User Experience:**
- **Satu tempat** untuk semua hal yang berkaitan dengan gallery
- **Tab navigation** yang intuitif 
- **Permission-based UI** (read vs write access)
- **Consistent interface** dengan desain yang sama

### **Admin Benefits:**
- **Tidak perlu berpindah menu** untuk manage gallery
- **Grouped functionality** - semua gallery management di satu tempat
- **Easier maintenance** - settings dan items dalam satu controller

### **Technical Benefits:**
- **Cleaner separation** - gallery settings tidak lagi di "landing" group
- **Better organization** - related functionality grouped together
- **Extensible** - mudah menambah tab atau fitur gallery lainnya

## 🔍 **Testing Checklist**

### **Fungsional:**
- [ ] Tab Gallery Items menampilkan daftar gambar gallery
- [ ] Tab Pengaturan Gallery menampilkan 19 settings
- [ ] CRUD gambar gallery berfungsi normal
- [ ] Edit settings gallery berfungsi normal
- [ ] File upload untuk image settings bekerja
- [ ] Permission read vs write diterapkan dengan benar

### **UI/UX:**
- [ ] Tab navigation berfungsi
- [ ] Active tab highlighted dengan benar
- [ ] Tombol "Tambah Gallery" hanya muncul di tab Gallery Items
- [ ] Settings editor UI responsif dan user-friendly

### **Data Integrity:**
- [ ] Settings gallery group berubah dari 'landing' ke 'gallery'
- [ ] Nilai settings tidak berubah/hilang
- [ ] Settings masih bisa diakses dan diedit
- [ ] Landing page tetap berfungsi normal

## 🔄 **Rollback Plan**

Jika ada masalah:

### **1. Rollback Database**
```php
// Kembalikan settings ke group 'landing'
$settings = Setting::where('group', 'gallery')->get();
foreach ($settings as $setting) {
    $setting->update(['group' => 'landing']);
}
```

### **2. Rollback Code**
- Revert changes di `GalleryController.php`
- Revert changes di `routes/web.php` 
- Revert changes di `Galleries/Index.svelte`

### **3. Clear Cache**
```bash
php artisan route:clear
php artisan config:clear
```

## 📝 **Notes**

1. **Backward Compatibility**: Landing page tetap berfungsi karena settings masih ada, hanya berpindah group
2. **Permission Model**: Tetap menggunakan `settings.read` dan `settings.write`
3. **File Uploads**: Settings dengan type 'image' support file upload
4. **Extensibility**: Mudah menambah tab atau fitur baru di masa depan

## 🎉 **Kesimpulan**

Perubahan ini memberikan **pengalaman yang lebih baik** untuk admin dalam mengelola gallery dengan:
- **Sentralisasi** semua fitur gallery di satu tempat
- **Interface yang konsisten** dengan tab navigation
- **Flexibility** untuk pengembangan fitur gallery di masa depan
- **Maintainability** yang lebih baik dengan struktur yang terorganisir
