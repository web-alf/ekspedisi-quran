# Fix untuk Packing.svelte - Null CurrentBox Error

Terdapat error "Cannot read properties of null (reading 'kode_kerdus')" karena currentBox bisa null.

## Changes Required:

### 1. Fix Props Declaration (line 13)
```javascript
// FROM:
export let currentBox = {};

// TO:
export let currentBox = null;
```

### 2. Fix Current Box Section (around line 674-699)
Wrap dengan null check:
```svelte
<div class="space-y-4">
  {#if currentBox}
    <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
      <div class="flex items-center justify-between mb-3">
        <span class="font-mono text-lg font-semibold text-orange-900">{currentBox.kode_kerdus}</span>
        <span class="text-sm text-orange-700">{currentBox.jumlah_terisi}/{currentBox.kapasitas}</span>
      </div>
      
      <div class="w-full bg-orange-200 rounded-full h-2 mb-2">
        <div 
          class="bg-orange-500 h-2 rounded-full transition-all duration-300"
          style="width: {currentBox.progress_percentage}%"
        ></div>
      </div>
      
      <div class="flex items-center justify-between text-sm">
        <span class="text-orange-700">Sisa: {currentBox.remaining_space} mushaf</span>
        {#if currentBox.jumlah_terisi > 0}
          <button
            on:click={sealCurrentBox}
            class="text-orange-700 hover:text-orange-900 font-medium"
          >
            Segel Box
          </button>
        {/if}
      </div>
    </div>
  {:else}
    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200 text-center">
      <p class="text-blue-800 font-medium">Belum ada kerdus aktif</p>
      <p class="text-sm text-blue-600 mt-1">Kerdus akan dibuat otomatis saat QR pertama di-scan</p>
    </div>
  {/if}
```

### 3. Fix Box Full Modal (around line 801-805)
```svelte
{#if showBoxFullModal && currentBox}
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
      <h3 class="text-lg font-semibold mb-4">Kerdus Penuh!</h3>
      <p class="text-gray-600 mb-6">Kerdus {currentBox.kode_kerdus} sudah penuh. Silakan segel kerdus ini dan lanjutkan dengan kerdus baru.</p>
      <!-- rest of modal -->
    </div>
  </div>
{/if}
```

### 4. Check Other Functions
Pastikan semua function yang menggunakan currentBox juga cek null:
- sealCurrentBox()
- Anywhere else currentBox.property is accessed

## Quick Test:
Setelah perubahan ini, halaman packing akan load tanpa error dan menampilkan "Belum ada kerdus aktif" sampai QR pertama di-scan.