# Solusi untuk Masalah Delete Donatur

## 🔍 Analisis Masalah

1. **Tombol delete tidak memberikan feedback** - Frontend tidak menangani error response
2. **Donatur tidak bisa dihapus** - Karena memiliki pengiriman yang sudah diproses
3. **Method destroy pengiriman tidak ada** - Route ada tapi method tidak ada di controller

## 🛠️ Solusi yang Diimplementasikan

### 1. Fix Error Handling di Frontend

**File:** `resources/js/Pages/Admin/Donatur/Index.svelte`

```javascript
// Tambahkan variable untuk error
let deleteError = null;

// Update handleDelete function
function handleDelete() {
  if (donaturToDelete) {
    deleteError = null; // Reset error
    router.delete(`/admin/donatur/${donaturToDelete.id}`, {
      onSuccess: () => {
        showDeleteModal = false;
        donaturToDelete = null;
        deleteError = null;
      },
      onError: (errors) => {
        console.error('Delete failed:', errors);
        deleteError = errors.error || 'Terjadi kesalahan saat menghapus donatur';
        // Modal tetap terbuka untuk menampilkan error
      }
    });
  }
}

// Update cancelDelete function
function cancelDelete() {
  showDeleteModal = false;
  donaturToDelete = null;
  deleteError = null;
}
```

**Tambahkan di modal (setelah konfirmasi text):**
```svelte
<!-- Error Message -->
{#if deleteError}
  <div class="mt-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
    <div class="flex">
      <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
      </svg>
      <span class="text-sm">{deleteError}</span>
    </div>
  </div>
{/if}
```

### 2. Tambahkan Method Destroy untuk Pengiriman

**File:** `app/Http/Controllers/Admin/PengirimanController.php`

Tambahkan method ini di akhir file:

```php
/**
 * Remove the specified pengiriman from storage.
 */
public function destroy(Pengiriman $pengiriman)
{
    try {
        DB::beginTransaction();

        // Check if pengiriman has been shipped or processed
        if ($pengiriman->status->slug !== 'proses-pemesanan') {
            return back()->withErrors([
                'error' => 'Tidak dapat menghapus pengiriman yang sudah diproses. Status saat ini: ' . $pengiriman->status->nama
            ]);
        }

        // Delete related files
        if ($pengiriman->qr_code_path && \Storage::exists($pengiriman->qr_code_path)) {
            \Storage::delete($pengiriman->qr_code_path);
        }

        // Delete wakaf item if exists
        if ($pengiriman->wakafItem) {
            $pengiriman->wakafItem()->delete();
        }

        // Delete certificates
        $pengiriman->sertifikat()->delete();

        // Delete the pengiriman
        $pengiriman->delete();

        DB::commit();

        return redirect()->route('admin.pengiriman.index')
            ->with('success', "Pengiriman {$pengiriman->no_resi} berhasil dihapus!");

    } catch (\Exception $e) {
        DB::rollback();

        return back()->withErrors([
            'error' => 'Gagal menghapus pengiriman: ' . $e->getMessage()
        ]);
    }
}
```

### 3. Opsi untuk Menghapus Donatur

**Opsi A: Cascade Delete (Hapus Semua)**
Update method destroy di DonaturController:

```php
public function destroy(Donatur $donatur)
{
    try {
        DB::beginTransaction();

        // Check if user wants to force delete
        if (request()->has('force_delete') && request('force_delete') == 'true') {
            // Force delete - remove all related records
            
            // Delete all pengiriman and related records
            foreach ($donatur->pengiriman as $pengiriman) {
                // Delete QR codes
                if ($pengiriman->qr_code_path && \Storage::exists($pengiriman->qr_code_path)) {
                    \Storage::delete($pengiriman->qr_code_path);
                }
                
                // Delete certificates
                $pengiriman->sertifikat()->delete();
                
                // Delete pengiriman
                $pengiriman->delete();
            }
            
            // Delete wakaf items
            $donatur->wakafItems()->delete();
            
            // Delete wakaf batches
            $donatur->wakafBatches()->delete();
            
            // Delete donatur
            $donatur->delete();
            
            DB::commit();
            
            return redirect()->route('admin.donatur.index')
                ->with('success', 'Donatur dan semua data terkait berhasil dihapus!');
        }

        // Original logic - check if has processed items
        $processedItems = $donatur->pengiriman()
            ->whereHas('status', function ($q) {
                $q->where('slug', '!=', 'proses-pemesanan');
            })
            ->count();

        if ($processedItems > 0) {
            return back()->withErrors([
                'error' => 'Tidak dapat menghapus donatur yang memiliki pengiriman yang sudah diproses. ' .
                          'Untuk menghapus paksa, gunakan opsi "Force Delete" (akan menghapus semua data terkait).'
            ]);
        }

        // Safe delete - only if no processed items
        $donatur->pengiriman()->delete();
        $donatur->wakafItems()->delete();
        $donatur->wakafBatches()->delete();
        $donatur->delete();

        DB::commit();

        return redirect()->route('admin.donatur.index')
            ->with('success', 'Donatur berhasil dihapus!');

    } catch (\Exception $e) {
        DB::rollback();

        return back()->withErrors([
            'error' => 'Gagal menghapus donatur: ' . $e->getMessage()
        ]);
    }
}
```

**Opsi B: Prevent Delete (Rekomendasi)**
Tetap gunakan business logic yang ada, tapi perbaiki error handling di frontend.

## 📋 Langkah-langkah Implementasi

1. **Perbaiki Error Handling Frontend**
   - Edit file `resources/js/Pages/Admin/Donatur/Index.svelte`
   - Tambahkan error handling dan tampilan error

2. **Tambahkan Method Destroy Pengiriman**
   - Edit file `app/Http/Controllers/Admin/PengirimanController.php`
   - Tambahkan method destroy

3. **Pilih Strategi Delete**
   - **Opsi A:** Cascade delete (hapus semua)
   - **Opsi B:** Prevent delete (rekomendasi)

## 🔄 Cara Menghapus Donatur

### Jika Menggunakan Prevent Delete (Rekomendasi):
1. Pastikan semua pengiriman dalam status "Proses Pemesanan"
2. Jika ada pengiriman yang sudah diproses, tidak bisa dihapus
3. Error message akan muncul di modal

### Jika Menggunakan Cascade Delete:
1. Tambahkan parameter `force_delete=true` di request
2. Semua data terkait akan dihapus

## 🧪 Testing

```bash
# Test dengan donatur yang bisa dihapus
php artisan tinker
$donatur = App\Models\Donatur::find(3); // Test donatur
$donatur->delete(); // Should work

# Test dengan donatur yang tidak bisa dihapus
$donatur = App\Models\Donatur::find(1); // Has processed items
$donatur->delete(); // Should show error
```

## 📝 Recommendations

1. **Gunakan Prevent Delete** untuk data integrity
2. **Implementasikan Soft Delete** untuk audit trail
3. **Tambahkan confirmation step** untuk force delete
4. **Log semua delete operations** untuk audit
