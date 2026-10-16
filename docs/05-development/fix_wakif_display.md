# Fix untuk masalah "Atas Nama" menampilkan nama donatur

## Masalah yang ditemukan:
1. Kolom "Atas Nama" di halaman `/admin/donatur/{id}` menampilkan nama donatur, bukan nama wakif
2. Frontend menggunakan fallback `{item.wakif_name || donatur.nama_donatur}`

## Perbaikan yang sudah dilakukan:

### 1. Model Donatur (✅ SUDAH DIPERBAIKI)
File: `app/Models/Donatur.php`
- Ubah dari `'' (empty string)` menjadi `null` di logic generateWakafItems
- Ini memastikan wakif_name yang kosong adalah null, bukan string kosong

### 2. Frontend Display (🔄 PERLU DIPERBAIKI)
File: `resources/js/Pages/Admin/Donatur/Show.svelte` - line 178

**Dari:**
```javascript
{item.wakif_name || donatur.nama_donatur}
```

**Ke:**
```javascript
{item.wakif_name || '-'}
```

### 3. Controller (✅ SUDAH BENAR)
File: `app/Http/Controllers/Admin/DonaturController.php`
- Logic untuk custom individual sudah benar
- Relationship loading sudah benar

## Langkah perbaikan yang harus dilakukan:

1. **Ubah file Show.svelte line 178:**
   ```javascript
   // Ganti ini:
   {item.wakif_name || donatur.nama_donatur}
   
   // Dengan ini:
   {item.wakif_name || '-'}
   ```

2. **Alternatif yang lebih baik:**
   ```javascript
   // Tampilkan nama wakif jika ada, jika tidak tampilkan "Belum diset"
   {item.wakif_name || 'Belum diset'}
   ```

## Cara menjalankan fix:

1. Buka file `/Users/agus/Herd/ekspedisi-quran/resources/js/Pages/Admin/Donatur/Show.svelte`
2. Cari line 178 yang berisi `{item.wakif_name || donatur.nama_donatur}`
3. Ganti dengan `{item.wakif_name || '-'}`
4. Simpan file
5. Refresh halaman untuk melihat perubahan

## Expected Result:
- Jika donatur memiliki wakif individual: akan menampilkan nama wakif
- Jika donatur "semua atas nama donatur": akan menampilkan nama donatur (karena wakif_name sudah terisi dengan nama donatur)
- Jika wakif_name kosong/null: akan menampilkan "-" atau "Belum diset"