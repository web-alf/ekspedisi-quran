# SIMPLE MANUAL FIX - Langsung ke Inti Masalah

## Masalah Utama
Lines 664-687 memiliki struktur HTML yang rusak. Ada `{#if currentBox}` di line 666 tapi tidak ada penutup yang benar.

## Langkah Fix:

### 1. Buka file: `resources/js/Pages/Warehouse/Packing.svelte`

### 2. Cari sekitar line 664-687, ganti SELURUH section ini:

**HAPUS ini (lines 664-687):**
```svelte
                <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                <div class="flex items-center justify-between mb-3">
                {#if currentBox}
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
```

**GANTI dengan ini:**
```svelte
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

### 3. Save file dan refresh browser

## Atau Jalankan Script:
```bash
python3 complete_fix.py
```

## Hasil yang Diharapkan:
- ✅ Tidak ada error console lagi
- ✅ Halaman load dengan normal
- ✅ Menampilkan "Belum ada kerdus aktif" saat currentBox null
- ✅ Scanner berfungsi untuk membuat kerdus pertama

**Ini adalah fix terakhir yang diperlukan untuk menyelesaikan masalah null currentBox!**