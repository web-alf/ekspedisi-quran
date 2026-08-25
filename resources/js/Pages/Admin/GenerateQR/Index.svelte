<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import { fade, scale } from 'svelte/transition';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  
  // Props from backend
  export let stats = {};
  export let recent_codes = [];
  
  let isGenerating = false;
  let showPreview = false;
  let generatedCode = null;
  
  // Form data
  let formData = {
    batch_size: 1,
    prefix: 'EQ',
    description: ''
  };
  
  function handleGenerate() {
    if (isGenerating) return;
    
    isGenerating = true;
    
    router.post('/admin/generate-qr', formData, {
      onSuccess: (response) => {
        // Handle successful generation
        isGenerating = false;
        formData.description = '';
      },
      onError: (errors) => {
        isGenerating = false;
      }
    });
  }
  
  function downloadQR(code) {
    window.open(`/admin/qr-code/${code}/download`, '_blank');
  }
  
  function printQR(code) {
    window.open(`/admin/qr-code/${code}/print`, '_blank');
  }
  
  function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
</script>

<svelte:head>
  <title>Generate QR Code - Ekspedisi Qur'an</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<AdminLayout>
  <div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Generate QR Code</h2>
    <p class="text-gray-600">Buat kode QR untuk tracking Al-Quran ke seluruh Indonesia</p>
  </div>
    
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Generate Form -->
    <div class="lg:col-span-1">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Buat QR Code Baru</h3>
          <p class="text-sm text-gray-500">Generate kode QR untuk tracking</p>
        </div>
        
        <div class="p-6">
          <form on:submit|preventDefault={handleGenerate} class="space-y-6">
            
            <!-- Batch Size -->
            <div>
              <label for="qr-batch-size" class="block text-sm font-medium text-gray-700 mb-2">
                Jumlah QR Code
              </label>
              <input
                type="number"
                id="qr-batch-size"
                bind:value={formData.batch_size}
                min="1"
                max="100"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
                required
              />
              <p class="text-xs text-gray-500 mt-1">Maksimal 100 QR code per batch</p>
            </div>
            
            <!-- Prefix -->
            <div>
              <label for="qr-prefix" class="block text-sm font-medium text-gray-700 mb-2">
                Prefix Kode
              </label>
              <input
                type="text"
                id="qr-prefix"
                bind:value={formData.prefix}
                maxlength="5"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
                required
              />
              <p class="text-xs text-gray-500 mt-1">Contoh: EQ, QURAN, dll</p>
            </div>
            
            <!-- Description -->
            <div>
              <label for="qr-description" class="block text-sm font-medium text-gray-700 mb-2">
                Deskripsi (Opsional)
              </label>
              <textarea
                id="qr-description"
                bind:value={formData.description}
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
                placeholder="Catatan atau keterangan batch ini..."
              ></textarea>
            </div>
            
            <!-- Generate Button -->
            <button
              type="submit"
              disabled={isGenerating}
              class="w-full bg-[#eb3434] hover:bg-red-600 disabled:bg-gray-400 text-white px-4 py-3 rounded-lg font-medium transition-colors flex items-center justify-center"
            >
              {#if isGenerating}
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Generating...
              {:else}
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Generate QR Code
              {/if}
            </button>
          </form>
        </div>
      </div>

      <!-- Statistics -->
      <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Statistik</h3>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-2 gap-4">
            <div class="text-center">
              <p class="text-2xl font-bold text-blue-600">{stats.total_codes || 0}</p>
              <p class="text-sm text-gray-500">Total QR</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold text-green-600">{stats.used_codes || 0}</p>
              <p class="text-sm text-gray-500">Terpakai</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold text-orange-600">{stats.unused_codes || 0}</p>
              <p class="text-sm text-gray-500">Belum</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold text-purple-600">{stats.today_generated || 0}</p>
              <p class="text-sm text-gray-500">Hari Ini</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Recent QR Codes -->
    <div class="lg:col-span-2">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">QR Code Terbaru</h3>
          <p class="text-sm text-gray-500">Kode QR yang baru dibuat</p>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode QR</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dibuat</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              {#each recent_codes as code}
                <tr class="hover:bg-gray-50">
                  <!-- QR Code -->
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                      </div>
                      <div>
                        <div class="text-sm font-medium text-gray-900">{code.code}</div>
                        <div class="text-sm text-gray-500">{code.description || 'Tanpa deskripsi'}</div>
                      </div>
                    </div>
                  </td>
                  
                  <!-- Status -->
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {code.is_used ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                      {code.is_used ? 'Terpakai' : 'Belum Digunakan'}
                    </span>
                  </td>
                  
                  <!-- Created Date -->
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatDate(code.created_at)}
                  </td>
                  
                  <!-- Actions -->
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex justify-end space-x-2">
                      <button
                        on:click={() => downloadQR(code.code)}
                        class="text-blue-600 hover:text-blue-900 p-1 hover:bg-blue-50 rounded transition-colors"
                        title="Download QR"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                      </button>
                      <button
                        on:click={() => printQR(code.code)}
                        class="text-green-600 hover:text-green-900 p-1 hover:bg-green-50 rounded transition-colors"
                        title="Print QR"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              {:else}
                <tr>
                  <td colspan="4" class="px-6 py-12 text-center">
                    <div class="text-gray-500">
                      <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                      </svg>
                      <p class="text-lg font-medium">Belum ada QR code</p>
                      <p class="text-sm">Generate QR code pertama Anda untuk memulai tracking</p>
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</AdminLayout>

<!-- Flash Messages -->
<FlashMessage />
