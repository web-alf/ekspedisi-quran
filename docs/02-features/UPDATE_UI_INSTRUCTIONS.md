# UPDATE UI INSTRUCTIONS - Mengganti Manual Assignment dengan Assign Target

## 🎯 Problem
Tombol masih menampilkan "Manual Assignment" padahal backend sudah support target-only assignment.

## ✅ Solution
Update file UI untuk mengganti interface manual assignment dengan assign target.

## 📁 File yang Perlu Diupdate

### 1. `/resources/js/Pages/Supervisor/WarehouseMonitor.svelte`

**Lokasi perubahan sekitar line 14-15:**
```javascript
// GANTI INI:
export let assignablePengiriman = [];
export let assignableCount = 0;

// MENJADI INI:
export let availablePengirimanCount = 0;
```

**Lokasi perubahan sekitar line 18-32 (State variables):**
```javascript
// TAMBAHKAN SETELAH LINE YANG ADA:
let showAssignTargetModal = false;
let isAssigningTarget = false;

// Assign target form
let assignTargetForm = {
  user_id: '',
  target: 80
};
```

**Lokasi perubahan sekitar line 267-277 (Manual Assignment Button):**
```svelte
<!-- GANTI BAGIAN INI: -->
<a 
  href="/admin/supervisor/manual-assignment"
  class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-[#eb3434] hover:bg-red-50 transition-colors group"
>
  <div class="text-center">
    <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 group-hover:text-[#eb3434]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
    </svg>
    <div class="font-medium text-gray-900 group-hover:text-[#eb3434]">Manual Assignment</div>
  </div>
</a>

<!-- DENGAN BAGIAN INI: -->
<button 
  on:click={openAssignTargetModal}
  class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-[#eb3434] hover:bg-red-50 transition-colors group"
>
  <div class="text-center">
    <svg class="w-8 h-8 mx-auto mb-2 text-gray-400 group-hover:text-[#eb3434]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"/>
    </svg>
    <div class="font-medium text-gray-900 group-hover:text-[#eb3434]">Assign Target</div>
  </div>
</button>
```

**Lokasi perubahan sekitar line 320-324 (Stats Cards - tambahkan Available Items):**
```svelte
<!-- SETELAH CARD "Overall Progress", TAMBAHKAN: -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
  <p class="text-sm font-medium text-gray-600">Available Items</p>
  <p class="text-2xl font-bold text-blue-600">{availablePengirimanCount}</p>
  <p class="text-xs text-gray-500 mt-1">Ready for pickup</p>
</div>
```

**Lokasi perubahan sekitar line 460-515 (Hapus section "Pengiriman Siap Assign"):**
```svelte
<!-- HAPUS SELURUH SECTION INI: -->
<!-- Pengiriman Siap Assign -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold text-gray-900">Pengiriman Siap Assign</h2>
    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
      Total: {assignableCount} item
    </span>
  </div>
  
  <!-- ... seluruh konten di dalam div ini sampai penutup </div> -->
</div>
```

**Tambahkan functions baru sebelum onMount():**
```javascript
// Assign target functions
function openAssignTargetModal() {
  assignTargetForm = { user_id: '', target: 80 };
  showAssignTargetModal = true;
}

async function handleAssignTarget() {
  if (!assignTargetForm.user_id || !assignTargetForm.target) {
    alert('Pilih user dan masukkan target');
    return;
  }
  
  isAssigningTarget = true;
  
  try {
    const response = await fetch('/admin/supervisor/assign-target', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(assignTargetForm)
    });
    
    const data = await response.json();
    
    if (data.success) {
      alert(data.message);
      showAssignTargetModal = false;
      router.reload({ preserveScroll: true });
    } else {
      alert('Error: ' + data.message);
    }
  } catch (error) {
    alert('Terjadi kesalahan: ' + error.message);
  } finally {
    isAssigningTarget = false;
  }
}
```

**Tambahkan modal assign target di akhir file sebelum </AdminLayout>:**
```svelte
<!-- Assign Target Modal -->
{#if showAssignTargetModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div 
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
        on:click={() => showAssignTargetModal = false}
      ></div>

      <!-- Modal panel -->
      <div class="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
        <div>
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
            </svg>
          </div>
          
          <div class="mt-3 text-center sm:mt-5">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
              Assign Target Harian
            </h3>
            <div class="mt-2">
              <p class="text-sm text-gray-500">
                Berikan target packing harian untuk warehouse staff. Sistem akan otomatis menambahkan carry-over.
              </p>
            </div>
          </div>
        </div>

        <form on:submit|preventDefault={handleAssignTarget} class="mt-6 space-y-4">
          <!-- User Selection -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Pilih Warehouse Staff
            </label>
            <select
              bind:value={assignTargetForm.user_id}
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
              disabled={isAssigningTarget}
              required
            >
              <option value="">-- Pilih Staff --</option>
              {#each warehouseUsers as user}
                <option value={user.id}>{user.name} ({user.email})</option>
              {/each}
            </select>
          </div>

          <!-- Target Input -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Target Mushaf
            </label>
            <input
              type="number"
              min="1"
              max="200"
              bind:value={assignTargetForm.target}
              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
              placeholder="80"
              disabled={isAssigningTarget}
              required
            />
            <p class="mt-1 text-xs text-gray-500">
              Sistem akan otomatis menambahkan carry-over dari hari sebelumnya.
            </p>
          </div>

          <!-- Action Buttons -->
          <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-3">
            <button
              type="button"
              on:click={() => showAssignTargetModal = false}
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm"
              disabled={isAssigningTarget}
            >
              Batal
            </button>
            <button
              type="submit"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:w-auto sm:text-sm disabled:opacity-50"
              disabled={isAssigningTarget}
            >
              {isAssigningTarget ? 'Assigning...' : 'Assign Target'}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
{/if}
```

## 🚀 Quick Steps

1. **Edit file**: `resources/js/Pages/Supervisor/WarehouseMonitor.svelte`
2. **Apply changes** sesuai instruksi di atas
3. **Restart Vite dev server**: `npm run dev`
4. **Refresh browser** dan test tombol "Assign Target"

## ✅ Expected Result

After update:
- ✅ Tombol berubah dari "Manual Assignment" → "Assign Target"
- ✅ Klik tombol membuka modal assign target
- ✅ Modal memiliki dropdown user dan input target
- ✅ Submit akan assign target ke user yang dipilih
- ✅ Section "Pengiriman Siap Assign" dihapus
- ✅ Stats menampilkan "Available Items" count

## 🔧 Alternative: Simple Replace

Jika terlalu kompleks, minimal ganti ini saja:

**Ganti line ~275:**
```svelte
<div class="font-medium text-gray-900 group-hover:text-[#eb3434]">Manual Assignment</div>
```

**Menjadi:**
```svelte
<div class="font-medium text-gray-900 group-hover:text-[#eb3434]">Assign Target</div>
```

Dan ganti href ke function:
```svelte
<button on:click={openAssignTargetModal} class="...">
```