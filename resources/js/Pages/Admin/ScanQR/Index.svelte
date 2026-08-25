<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte';
  import TabNavigation from '@/Components/TabNavigation.svelte';
  import { router } from '@inertiajs/svelte';
  import { page } from '@inertiajs/svelte';
  import { fade, scale } from 'svelte/transition';
  import FlashMessage from '@/Components/FlashMessage.svelte';
  import LazyQRScanner from '@/Components/LazyQRScanner.svelte';

  // Props from backend
  export const auth = {};
  export let stats = {};
  export let recent_scans = [];
  
  // Get mode from URL or default to 'scan-status'
  let currentMode = 'scan-status';
  $: {
    const urlParams = new URLSearchParams(window.location.search);
    currentMode = urlParams.get('mode') || 'scan-status';
  }
  
  let isScanning = false;
  let showCameraModal = false;
  let manualCode = '';
  
  function handleManualScan() {
    if (!manualCode.trim()) return;
    
    router.post('/admin/scan-status', {
      code: manualCode.trim()
    }, {
      onSuccess: () => {
        manualCode = '';
      },
      onError: (errors) => {
        console.error('Scan error:', errors);
      }
    });
  }
  
  function openCamera() {
    showCameraModal = true;
    // In a real implementation, you would initialize camera here
  }
  
  function closeCamera() {
    showCameraModal = false;
  }
  
  function viewScanDetail(scanId) {
    router.visit(`/admin/scan-status/${scanId}`);
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
  
  function getStatusColor(status) {
    switch(status) {
      case 'shipped': return 'bg-blue-100 text-blue-800';
      case 'delivered': return 'bg-green-100 text-green-800';
      case 'pending': return 'bg-yellow-100 text-yellow-800';
      case 'cancelled': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  }
  
  function getStatusText(status) {
    switch(status) {
      case 'shipped': return 'Dikirim';
      case 'delivered': return 'Terkirim';
      case 'pending': return 'Pending';
      case 'cancelled': return 'Dibatalkan';
      default: return 'Unknown';
    }
  }
</script>

<svelte:head>
  <title>Scan QR Code - Ekspedisi Qur'an</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@300;400;600;700&display=swap" rel="stylesheet">
</svelte:head>

<AdminLayout>
  <div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Scan QR Code</h2>
    <p class="text-gray-600">Scan untuk melihat status tracking dan lokasi Al-Quran</p>
  </div>
  
  <TabNavigation {currentMode} />
  
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Scan Tools -->
    <div class="lg:col-span-1">
      
      <!-- Camera Scan -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Scan dengan Kamera</h3>
          <p class="text-sm text-gray-500">Gunakan kamera untuk scan QR</p>
        </div>
        
        <div class="p-6">
          <button
            on:click={openCamera}
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-8 rounded-lg font-medium transition-colors flex flex-col items-center"
          >
            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-lg">Buka Kamera</span>
            <span class="text-sm opacity-80">Klik untuk mulai scan</span>
          </button>
        </div>
      </div>

      <!-- Manual Input -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Input Manual</h3>
          <p class="text-sm text-gray-500">Masukkan kode QR secara manual</p>
        </div>
        
        <div class="p-6">
          <form on:submit|preventDefault={handleManualScan} class="space-y-4">
            <input
              type="text"
              bind:value={manualCode}
              placeholder="Masukkan kode QR..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              required
            />
            
            <button
              type="submit"
              class="w-full bg-[#eb3434] hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              Cek Status
            </button>
          </form>
        </div>
      </div>

      <!-- Statistics -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Statistik Scan</h3>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-2 gap-4">
            <div class="text-center">
              <p class="text-2xl font-bold text-blue-600">{stats.total_scans || 0}</p>
              <p class="text-sm text-gray-500">Total Scan</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold text-green-600">{stats.success_scans || 0}</p>
              <p class="text-sm text-gray-500">Berhasil</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold text-orange-600">{stats.today_scans || 0}</p>
              <p class="text-sm text-gray-500">Hari Ini</p>
            </div>
            <div class="text-center">
              <p class="text-2xl font-bold text-purple-600">{stats.unique_codes || 0}</p>
              <p class="text-sm text-gray-500">Kode Unik</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Recent Scans -->
    <div class="lg:col-span-2">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Riwayat Scan Terbaru</h3>
          <p class="text-sm text-gray-500">Aktivitas scan QR code terkini</p>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode QR</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu Scan</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              {#each recent_scans as scan}
                <tr class="hover:bg-gray-50">
                  <!-- QR Code -->
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                      </div>
                      <div>
                        <div class="text-sm font-medium text-gray-900">{scan.qr_code}</div>
                        <div class="text-sm text-gray-500">{scan.wakif_name || 'Belum terdaftar'}</div>
                      </div>
                    </div>
                  </td>
                  
                  <!-- Status -->
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {getStatusColor(scan.status)}">
                      {getStatusText(scan.status)}
                    </span>
                  </td>
                  
                  <!-- Location -->
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {scan.location || 'Tidak diketahui'}
                  </td>
                  
                  <!-- Scan Time -->
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {formatDate(scan.scanned_at)}
                  </td>
                  
                  <!-- Actions -->
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button
                      on:click={() => viewScanDetail(scan.id)}
                      class="text-indigo-600 hover:text-indigo-900 p-1 hover:bg-indigo-50 rounded transition-colors"
                      title="Lihat Detail"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </button>
                  </td>
                </tr>
              {:else}
                <tr>
                  <td colspan="5" class="px-6 py-12 text-center">
                    <div class="text-gray-500">
                      <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                      </svg>
                      <p class="text-lg font-medium">Belum ada aktivitas scan</p>
                      <p class="text-sm">Mulai scan QR code untuk melihat riwayat di sini</p>
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

  <!-- Camera Modal -->
  {#if showCameraModal}
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div 
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
          on:click={closeCamera}
          on:keydown={(e) => e.key === 'Escape' && closeCamera()}
          role="button"
          tabindex="0"
          aria-label="Close modal"
          transition:fade={{ duration: 200 }}
        ></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div 
          class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6"
          transition:scale={{ duration: 200, start: 0.95 }}
        >
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Scan QR Code</h3>
            <button
              on:click={closeCamera}
              class="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          
          <div class="bg-gray-100 rounded-lg overflow-hidden">
            <LazyQRScanner
              width={400}
              height={300}
              facingMode="environment"
              showToggleCamera={true}
              on:scanSuccess={(e) => {
                const qrText = e.detail.text || '';
                let noResi = null;
                const match = qrText.match(/EQ-\d{4}-\d{5}/);
                if (match) {
                  noResi = match[0];
                } else {
                  try {
                    const data = JSON.parse(qrText);
                    if (data.no_resi) {
                      noResi = data.no_resi;
                    }
                  } catch {}
                }
                if (noResi) {
                  closeCamera();
                  router.visit(`/admin/pengiriman/${noResi}/update-status`);
                }
              }}
              on:scanError={() => {}}
            />
          </div>

          <div class="mt-4 text-center text-sm text-gray-500">
            <p>Pastikan QR code terlihat jelas dalam frame kamera</p>
          </div>
        </div>
      </div>
    </div>
  {/if}
</AdminLayout>

<!-- Flash Messages -->
<FlashMessage />
