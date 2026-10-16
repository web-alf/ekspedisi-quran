# MANUAL STEPS - Update UI untuk Assign Target

## 🎯 Problem
UI masih menampilkan "Manual Assignment" padahal backend sudah siap dengan assign target system.

## ✅ Solusi Manual (5 menit)

### 1. Edit File UI
Buka file: `resources/js/Pages/Supervisor/WarehouseMonitor.svelte`

### 2. Perubahan Minimal yang Diperlukan

**A. Update Props (sekitar line 14-15):**
```javascript
// GANTI:
export let assignablePengiriman = [];
export let assignableCount = 0;

// MENJADI:
export let availablePengirimanCount = 0;
```

**B. Ganti Text Manual Assignment (sekitar line 275):**
```svelte
<!-- GANTI: -->
<div class="font-medium text-gray-900 group-hover:text-[#eb3434]">Manual Assignment</div>

<!-- MENJADI: -->
<div class="font-medium text-gray-900 group-hover:text-[#eb3434]">Assign Target</div>
```

**C. Tambah State Variables (setelah line dengan `let autoRefresh = true;`):**
```javascript
let showAssignTargetModal = false;
let isAssigningTarget = false;

// Assign target form
let assignTargetForm = {
  user_id: '',
  target: 80
};
```

**D. Tambah Functions (sebelum `function getStatusBadge`):**
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

**E. Ganti Link ke Button (sekitar line 267-277):**
```svelte
<!-- GANTI: -->
<a 
  href="/admin/supervisor/manual-assignment"
  class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-[#eb3434] hover:bg-red-50 transition-colors group"
>

<!-- MENJADI: -->
<button 
  on:click={openAssignTargetModal}
  class="flex items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-[#eb3434] hover:bg-red-50 transition-colors group"
>
```

Dan ganti penutup:
```svelte
</a> → </button>
```

**F. Tambah Modal HTML (di akhir file, sebelum `</AdminLayout>`):**

Copy seluruh konten dari file `ASSIGN_TARGET_MODAL.html` dan paste di akhir file.

### 3. Restart Dev Server
```bash
npm run dev
```

### 4. Test
1. Refresh browser
2. Akses `/admin/supervisor/warehouse-monitor`
3. Klik tombol "Assign Target"
4. Modal akan muncul dengan form assignment

## 🚀 Alternative: Copy-Paste Ready Code

Jika terlalu kompleks, saya sudah buat file lengkap yang siap pakai:

1. **`AssignTargetModal.svelte`** - Modal component
2. **`ASSIGN_TARGET_MODAL.html`** - HTML untuk modal
3. **`assignTarget.js`** - Utility functions

## ✅ Expected Result

Setelah update:
- ✅ Button berubah: "Manual Assignment" → "Assign Target"
- ✅ Klik button membuka modal assign target
- ✅ Modal punya dropdown user dan input target number
- ✅ Submit akan assign target ke warehouse staff
- ✅ Data akan update dan refresh otomatis

## 🎯 Current Backend Status

**Backend 100% Ready:**
- ✅ API: `POST /admin/supervisor/assign-target`
- ✅ Controller: `assignTargetToUser()` method
- ✅ Service: `assignDailyTaskToUserWithTarget()`
- ✅ Data: availablePengirimanCount = 1
- ✅ User: Staff Gudang (ID: 3) ready
- ✅ Tested: Target assignment berhasil (80 → 160 dengan carry-over)

Hanya perlu update UI, backend sudah sempurna! 🎉