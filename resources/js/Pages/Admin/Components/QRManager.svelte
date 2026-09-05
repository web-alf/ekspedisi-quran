<script>
  import { onMount } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { toast } from '../../../utils/notifications.js';
  
  export let pengiriman;
  
  let loading = false;
  let qrData = null;
  let showQRPreview = false;
  let error = null;
  
  onMount(() => {
    // Check if QR already exists
    if (pengiriman.qr_code_path) {
      loadExistingQR();
    }
  });
  
  async function loadExistingQR() {
    if (pengiriman.qr_code_data) {
      try {
        qrData = JSON.parse(pengiriman.qr_code_data);
      } catch {
        qrData = null;
      }
    }
  }
  
  async function generateQR() {
    loading = true;
    error = null;
    
    try {
      const response = await fetch(`/admin/qr/generate/${pengiriman.id}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      const result = await response.json();
      
      if (result.success) {
        // Update pengiriman data
        pengiriman.qr_code_path = result.data.qr_code_path;
        pengiriman.qr_code_data = JSON.stringify(result.data.qr_data);
        qrData = result.data.qr_data;
        
        // Show success message
        showNotification('QR Code berhasil dibuat!', 'success');
        
        // Reload page to refresh QR status
        setTimeout(() => {
          router.reload({
            preserveState: false,
            preserveScroll: true
          });
        }, 500);
      } else {
        throw new Error(result.message);
      }
    } catch (err) {
      error = 'Gagal membuat QR Code: ' + err.message;
      showNotification(error, 'error');
    } finally {
      loading = false;
    }
  }
  
  async function downloadQR() {
    try {
      const response = await fetch(`/admin/qr/download/${pengiriman.id}`);
      
      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `QR-${pengiriman.no_resi}.png`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showNotification('QR Code berhasil diunduh!', 'success');
      } else {
        throw new Error('Gagal mengunduh QR Code');
      }
    } catch (err) {
      showNotification('Gagal mengunduh: ' + err.message, 'error');
    }
  }
  
  function copyTrackingURL() {
    const url = `${window.location.origin}/tracking/${pengiriman.no_resi}`;
    navigator.clipboard.writeText(url).then(() => {
      showNotification('URL tracking berhasil disalin!', 'success');
    });
  }
  
  function showNotification(message, type) {
    if (type === 'error') {
      toast.error(message);
    } else if (type === 'success') {
      toast.success(message);
    } else {
      toast.info(message);
    }
  }
  
  function toggleQRPreview() {
    showQRPreview = !showQRPreview;
  }
</script>

<div class="bg-white rounded-lg border border-gray-200 p-4">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
      <HeroIcon name="qr-code" class="w-5 h-5 mr-2" />
      QR Code Management
    </h3>
    
    {#if pengiriman.qr_code_path}
      <div class="flex space-x-2">
        <button
          on:click={toggleQRPreview}
          class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors"
        >
          {showQRPreview ? 'Sembunyikan' : 'Lihat'} QR
        </button>
        <button
          on:click={downloadQR}
          class="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors inline-flex items-center gap-1"
        >
          <HeroIcon name="arrow-down-tray" class="w-4 h-4" />
          Download
        </button>
      </div>
    {/if}
  </div>
  
  {#if pengiriman.qr_code_path}
    <!-- QR Code exists -->
    <div class="space-y-4">
      
      {#if showQRPreview}
        <!-- QR Code Preview -->
        <div class="bg-gray-50 rounded-lg p-4 text-center">
          <img 
            src="/storage/{pengiriman.qr_code_path.replace('public/', '')}" 
            alt="QR Code {pengiriman.no_resi}"
            class="mx-auto max-w-48 max-h-48 rounded-lg shadow-md"
          />
          <p class="text-sm text-gray-600 mt-2">QR Code untuk {pengiriman.no_resi}</p>
        </div>
      {/if}
      
      <!-- QR Info -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
          <span class="font-medium text-gray-700">Status:</span>
          <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 rounded text-xs inline-flex items-center gap-1">
            <HeroIcon name="check-circle" class="w-3.5 h-3.5" />
            QR Code Tersedia
          </span>
        </div>
        <div>
          <span class="font-medium text-gray-700">Dibuat:</span>
          <span class="ml-2 text-gray-600">
            {qrData?.generated_at ? new Date(qrData.generated_at).toLocaleString('id-ID') : 'Unknown'}
          </span>
        </div>
      </div>
      
      <!-- Actions -->
      <div class="flex flex-wrap gap-2">
        <button
          on:click={copyTrackingURL}
          class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors text-sm inline-flex items-center gap-1.5"
        >
          <HeroIcon name="clipboard-document" class="w-4 h-4" />
          Copy URL
        </button>
        
        <a
          href="/tracking/{pengiriman.no_resi}"
          target="_blank"
          class="px-4 py-2 bg-indigo-500 text-white rounded hover:bg-indigo-600 transition-colors text-sm inline-flex items-center gap-1.5"
        >
          <HeroIcon name="arrow-top-right-on-square" class="w-4 h-4" />
          Open Tracking
        </a>
      </div>
    </div>
    
  {:else}
    <!-- No QR Code yet -->
    <div class="text-center py-6">
      <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <HeroIcon name="qr-code" class="w-8 h-8 text-gray-400" />
      </div>
      <h4 class="text-lg font-medium text-gray-900 mb-2">QR Code Belum Dibuat</h4>
      <p class="text-gray-600 mb-4">Buat QR Code untuk memudahkan tracking pengiriman ini</p>
      
      <button
        on:click={generateQR}
        disabled={loading}
        class="px-6 py-3 bg-[#eb3434] text-white font-semibold rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
      >
        {loading ? '🔄 Membuat QR Code...' : '📱 Buat QR Code'}
      </button>
    </div>
  {/if}
  
  {#if error}
    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
      <p class="text-red-800 text-sm">{error}</p>
    </div>
  {/if}
</div>
