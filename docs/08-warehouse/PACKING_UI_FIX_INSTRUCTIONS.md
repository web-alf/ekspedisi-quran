# Warehouse Packing UI Fix Instructions

## Problem
The frontend still uses the old "nextItems" system while the backend has switched to the new "free-pick" system with "availablePengiriman". This causes the "Item Berikutnya" section to always show "Tidak ada item tersisa".

## Required Changes

### 1. Update Props in Packing.svelte (Line ~17)

**Add missing props:**
```svelte
export let availablePengiriman = [];
export let availableCount = 0;
```

### 2. Replace "Item Berikutnya" Section (Lines ~719-739)

**Replace entire section with:**
```svelte
<!-- Available Items (Free-Pick System) -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
  <div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-900">Item Tersedia</h2>
    <span class="text-sm text-gray-600 bg-blue-100 px-2 py-1 rounded-full">
      {availableCount} total
    </span>
  </div>
  
  {#if availablePengiriman.length > 0}
    <div class="space-y-2 max-h-64 overflow-y-auto">
      {#each availablePengiriman.slice(0, 5) as item}
        <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
          <div class="flex justify-between items-start">
            <div>
              <p class="font-mono font-medium text-blue-900">{item.no_resi}</p>
              <p class="text-sm text-blue-700">{item.wakif}</p>
              <p class="text-xs text-blue-600">{item.jenis_quran} ({item.jumlah_quran} mushaf)</p>
            </div>
            <span class="text-xs text-blue-500">{item.created_at}</span>
          </div>
        </div>
      {/each}
      {#if availablePengiriman.length > 5}
        <p class="text-sm text-blue-600 text-center">+{availablePengiriman.length - 5} item lainnya</p>
      {/if}
    </div>
  {:else}
    <div class="text-center py-8">
      <svg class="w-12 h-12 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
      </svg>
      <p class="text-gray-500">Tidak ada item tersedia untuk free-pick</p>
      <p class="text-xs text-gray-400 mt-1">Scan QR code apa saja yang belum dipacking</p>
    </div>
  {/if}
</div>
```

### 3. Update updateLocalData Function (Line ~270)

**Remove this line:**
```javascript
// Remove scanned item from nextItems
nextItems = nextItems.filter(item => item.pengiriman.no_resi !== data.no_resi);
```

**Replace with:**
```javascript
// Remove scanned item from available pengiriman (optional - will be refreshed on reload)
availablePengiriman = availablePengiriman.filter(item => item.no_resi !== data.no_resi);
```

## What This Fixes

1. ✅ **Shows available items** instead of empty "Item Berikutnya"
2. ✅ **Displays total count** of available items for free-pick
3. ✅ **Better UI styling** with blue theme to distinguish from assigned items
4. ✅ **Free-pick explanation** when no items available
5. ✅ **Removes outdated logic** that references old nextItems system

## System Change Summary

**OLD SYSTEM (nextItems):**
- Pre-assigned specific items to each user
- Shows "what you must pack next"

**NEW SYSTEM (availablePengiriman):**
- Free-pick from available pool
- Shows "what you can scan" (any available QR code)
- Dynamic assignment when scanned

## File Permissions Issue

The file `/Users/agus/Herd/ekspedisi-quran/resources/js/Pages/Warehouse/Packing.svelte` has root ownership. To fix:

```bash
sudo chown agus:staff /Users/agus/Herd/ekspedisi-quran/resources/js/Pages/Warehouse/Packing.svelte
```

Then apply the changes above manually or use the patch file created.