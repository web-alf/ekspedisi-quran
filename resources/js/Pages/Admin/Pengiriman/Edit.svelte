<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router, page } from '@inertiajs/svelte';
  import { showError, showWarning, showInfo } from '../../../stores/toast.js';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { can } from '../../../utils/permissions.js';
  
  export let pengiriman;
  export let statusList = [];
  export const jenisQuranList = [];
  export let approvedMushafRequests = [];
  export let errors = {};
  
  let form = {
    status_id: pengiriman.status_id || '',
    alamat_tujuan: pengiriman.alamat_tujuan || '',
    nama_penerima: pengiriman.nama_penerima || '',
    no_hp_penerima: pengiriman.no_hp_penerima || '',
    catatan: pengiriman.catatan || ''
  };
  
  let isSubmitting = false;
  let showQRModal = false;
  let selectedMushafRequest = null;
  
  // Get user permissions
  $: userPermissions = $page.props.auth?.user?.permissions || [];
  
  // Check if user can generate QR codes
  $: canGenerateQR = can.qr.generate();
  
  // Handle mushaf request selection
  function onMushafRequestSelect() {
    if (selectedMushafRequest) {
      const request = approvedMushafRequests.find(r => r.id === parseInt(selectedMushafRequest));
      if (request) {
        form.alamat_tujuan = request.alamat_lengkap;
        form.nama_penerima = request.nama_pengurus_1;
        form.no_hp_penerima = request.whatsapp_pengurus_1;
      }
    }
  }
  
  // Clear selection and manual input
  function clearMushafRequestSelection() {
    selectedMushafRequest = null;
    // Don't clear the form fields, let user edit manually
  }
  
  function handleSubmit() {
    if (isSubmitting) return;
    
    isSubmitting = true;
    
    router.put(`/admin/pengiriman/${pengiriman.id}`, form, {
      onSuccess: () => {
      },
      onError: (errors) => {
      },
      onFinish: () => {
        isSubmitting = false;
      }
    });
  }
  
  function goBack() {
    router.visit('/admin/pengiriman');
  }
  
  function getStatusColor(status) {
    if (!status) return 'bg-gray-100 text-gray-800';
    
    const colorMap = {
      'gray': 'bg-gray-100 text-gray-800',
      'yellow': 'bg-yellow-100 text-yellow-800',
      'blue': 'bg-blue-100 text-blue-800',
      'green': 'bg-green-100 text-green-800',
      'red': 'bg-red-100 text-red-800',
      'indigo': 'bg-indigo-100 text-indigo-800',
    };
    
    return colorMap[status.warna] || 'bg-gray-100 text-gray-800';
  }
  
  function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID');
  }
  
  // Generate QR untuk pengiriman ini
  async function generateQR() {
    router.post(`/admin/qr/generate/${pengiriman.id}`, {}, {
      onSuccess: () => {
        // Add delay to ensure backend has updated data before reloading
        setTimeout(() => {
          router.reload({ 
            only: ['pengiriman'],
            preserveState: false,
            preserveScroll: true
          });
        }, 500);
      },
      onError: (errors) => {
        showError('Gagal Generate QR Code', 'Gagal generate QR Code: ' + (errors.message || 'Unknown error'));
      }
    });
  }
  
  // Show QR Code
  function showQR() {
    if (hasQRData()) {
      if (hasQRImageFile()) {
        // Show QR modal with image
        initQRModal();
        showQRModal = true;
      } else {
        // QR data exists but no image file, try to generate image first
        showInfo('Generating QR Image', 'QR Code data tersedia tapi file gambar belum dibuat. Generating QR image...');
        generateQR();
      }
    } else {
      showWarning('QR Code Belum Ada', 'QR Code belum di-generate untuk pengiriman ini.');
    }
  }
  
  // Check if QR has been properly generated as image file
  function hasQRImageFile() {
    return pengiriman.qr_code_path || pengiriman.qr_code_file;
  }
  
  // Check if QR data exists (JSON data)
  function hasQRData() {
    return pengiriman.qr_code_data;
  }
  
  // Get QR Image URL
  function getQRImageUrl() {
    if (hasQRImageFile()) {
      // Use the display route for inline viewing (better for images in modal)
      return `/admin/qr/display/${pengiriman.id}`;
    }
    return null;
  }
  
  // Alternative QR URLs to try if main fails
  function getAlternativeQRUrls() {
    if (!hasQRData()) return [];
    
    return [
      `/admin/qr/display/${pengiriman.id}`,
      `/admin/qr/download/${pengiriman.id}`,
      `/api/tracking/${pengiriman.no_resi}/qr`,
      `/storage/qr-codes/QR-${pengiriman.no_resi}.png`,
      // Try storage URL if path exists
      pengiriman.qr_code_path ? `/storage/${pengiriman.qr_code_path.replace('public/', '')}` : null,
      // Google Charts API fallback
      `https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=${encodeURIComponent(window.location.origin + '/tracking/' + pengiriman.no_resi)}`
    ].filter(Boolean);
  }
  
  // Generate QR as base64 image if no file exists
  async function generateQRBase64() {
    if (!hasQRData()) return null;
    
    try {
      const qrData = JSON.parse(pengiriman.qr_code_data);
      // This would need a client-side QR generator library
      // For now, just return null and use the file-based approach
      return null;
    } catch (e) {
      return null;
    }
  }
  
  let currentQRUrl = '';
  let qrUrlIndex = 0;
  
  function handleQRImageError() {
    const alternatives = getAlternativeQRUrls();
    
    if (qrUrlIndex < alternatives.length) {
      currentQRUrl = alternatives[qrUrlIndex];
      qrUrlIndex++;
    } else {
      showError('QR Image Error', 'QR Code image tidak dapat dimuat. Silakan regenerate QR Code.');
      showQRModal = false;
    }
  }
  
  function initQRModal() {
    currentQRUrl = getQRImageUrl();
    qrUrlIndex = 0;
    const alternatives = getAlternativeQRUrls();
    
    if (alternatives.length > 0 && !currentQRUrl) {
      currentQRUrl = alternatives[0];
      qrUrlIndex = 1;
    }
  }
</script>

<svelte:head>
  <title>Edit Pengiriman {pengiriman.no_resi} - Admin</title>
</svelte:head>

<AdminLayout>
  <div class="mb-8">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Edit Pengiriman</h2>
        <p class="text-gray-600">Edit data pengiriman {pengiriman.no_resi}</p>
      </div>
      <button
        on:click={goBack}
        class="px-4 py-2 text-gray-600 hover:text-gray-800 flex items-center gap-2"
      >
        <HeroIcon name="arrow-left" class="w-4 h-4" />
        Kembali
      </button>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Form Column -->
    <div class="lg:col-span-2">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">Informasi Pengiriman</h3>
        
        <form on:submit|preventDefault={handleSubmit}>
          <div class="space-y-6">
            <!-- Status -->
            <div>
              <label for="status-select" class="block text-sm font-medium text-gray-700 mb-2">
                Status Pengiriman *
              </label>
              <select 
                id="status-select"
                bind:value={form.status_id}
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                required
              >
                <option value="">Pilih Status...</option>
                {#each statusList as status}
                  <option value={status.id}>{status.nama}</option>
                {/each}
              </select>
              {#if errors.status_id}
                <p class="text-red-500 text-sm mt-1">{errors.status_id}</p>
              {/if}
            </div>

            <!-- Alamat dari Mushaf Request (Optional) -->
            {#if approvedMushafRequests && approvedMushafRequests.length > 0}
              <div>
                <label for="mushaf-request-select" class="block text-sm font-medium text-gray-700 mb-2">
                  Pilih dari Permintaan Mushaf yang Disetujui (Opsional)
                </label>
                <div class="flex gap-3">
                  <select 
                    id="mushaf-request-select"
                    bind:value={selectedMushafRequest}
                    on:change={onMushafRequestSelect}
                    class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                  >
                    <option value="">Pilih permintaan mushaf...</option>
                    {#each approvedMushafRequests as request}
                      <option value={request.id}>{request.label}</option>
                    {/each}
                  </select>
                  {#if selectedMushafRequest}
                    <button
                      type="button"
                      on:click={clearMushafRequestSelection}
                      class="px-4 py-3 text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
                      title="Clear Selection"
                    >
                      <HeroIcon name="x-mark" class="w-5 h-5" />
                    </button>
                  {/if}
                </div>
                <p class="text-xs text-gray-500 mt-1">
                  Pilih permintaan mushaf untuk mengisi alamat, nama, dan nomor HP secara otomatis
                </p>
              </div>
            {/if}

            <!-- Alamat Tujuan -->
            <div>
              <label for="alamat-tujuan" class="block text-sm font-medium text-gray-700 mb-2">
                Alamat Tujuan
                {#if selectedMushafRequest}
                  <span class="inline-flex items-center px-2 py-1 ml-2 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                    <HeroIcon name="check" class="w-3 h-3 mr-1" />
                    Dari Permintaan Mushaf
                  </span>
                {/if}
              </label>
              <textarea 
                id="alamat-tujuan"
                bind:value={form.alamat_tujuan}
                rows="3"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {selectedMushafRequest ? 'bg-green-50 border-green-300' : ''}"
                placeholder="Masukkan alamat lengkap tujuan..."
              ></textarea>
              {#if errors.alamat_tujuan}
                <p class="text-red-500 text-sm mt-1">{errors.alamat_tujuan}</p>
              {/if}
            </div>

            <!-- Nama Penerima -->
            <div>
              <label for="nama-penerima" class="block text-sm font-medium text-gray-700 mb-2">
                Nama Penerima
                {#if selectedMushafRequest}
                  <span class="inline-flex items-center px-2 py-1 ml-2 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                    <HeroIcon name="check" class="w-3 h-3 mr-1" />
                    Dari Permintaan Mushaf
                  </span>
                {/if}
              </label>
              <input 
                id="nama-penerima"
                type="text"
                bind:value={form.nama_penerima}
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {selectedMushafRequest ? 'bg-green-50 border-green-300' : ''}"
                placeholder="Nama penerima paket..."
              />
              {#if errors.nama_penerima}
                <p class="text-red-500 text-sm mt-1">{errors.nama_penerima}</p>
              {/if}
            </div>

            <!-- No HP Penerima -->
            <div>
              <label for="no-hp-penerima" class="block text-sm font-medium text-gray-700 mb-2">
                No. HP Penerima
                {#if selectedMushafRequest}
                  <span class="inline-flex items-center px-2 py-1 ml-2 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                    <HeroIcon name="check" class="w-3 h-3 mr-1" />
                    Dari Permintaan Mushaf
                  </span>
                {/if}
              </label>
              <input 
                id="no-hp-penerima"
                type="text"
                bind:value={form.no_hp_penerima}
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent {selectedMushafRequest ? 'bg-green-50 border-green-300' : ''}"
                placeholder="08xxxxxxxxxx"
              />
              {#if errors.no_hp_penerima}
                <p class="text-red-500 text-sm mt-1">{errors.no_hp_penerima}</p>
              {/if}
            </div>

            <!-- Catatan -->
            <div>
              <label for="catatan" class="block text-sm font-medium text-gray-700 mb-2">
                Catatan
              </label>
              <textarea 
                id="catatan"
                bind:value={form.catatan}
                rows="3"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                placeholder="Catatan tambahan untuk pengiriman..."
              ></textarea>
              {#if errors.catatan}
                <p class="text-red-500 text-sm mt-1">{errors.catatan}</p>
              {/if}
            </div>

            <!-- Submit Button -->
            <div class="flex gap-4 pt-6 border-t">
              <button
                type="submit"
                disabled={isSubmitting}
                class="flex-1 px-6 py-3 bg-[#eb3434] text-white font-semibold rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {#if isSubmitting}
                  <div class="flex items-center justify-center">
                    <div class="animate-spin w-5 h-5 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                    Menyimpan...
                  </div>
                {:else}
                  Simpan Perubahan
                {/if}
              </button>
              
              <button
                type="button"
                on:click={goBack}
                class="px-6 py-3 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition-colors"
              >
                Batal
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Info Sidebar -->
    <div class="space-y-6">
      <!-- Mushaf Request Info (if selected) -->
      {#if selectedMushafRequest}
        {@const selectedRequest = approvedMushafRequests.find(r => r.id === parseInt(selectedMushafRequest))}
        {#if selectedRequest}
          <div class="bg-green-50 rounded-xl border border-green-200 p-6">
            <h4 class="font-semibold text-green-900 mb-4 flex items-center">
              <HeroIcon name="check" class="w-5 h-5 mr-2" />
              Permintaan Mushaf Terpilih
            </h4>
            
            <div class="space-y-3 text-sm">
              <div>
                <span class="text-green-700 font-medium">No. Request:</span>
                <p class="text-green-900 font-mono">{selectedRequest.no_request}</p>
              </div>
              
              <div>
                <span class="text-green-700 font-medium">Lembaga:</span>
                <p class="text-green-900">{selectedRequest.nama_lembaga}</p>
              </div>
              
              <div>
                <span class="text-green-700 font-medium">Alamat Lengkap:</span>
                <p class="text-green-900 text-xs bg-white p-2 rounded border">{selectedRequest.alamat_lengkap}</p>
              </div>
              
              <div>
                <span class="text-green-700 font-medium">Pengurus:</span>
                <p class="text-green-900">{selectedRequest.nama_pengurus_1}</p>
              </div>
              
              <div>
                <span class="text-green-700 font-medium">WhatsApp:</span>
                <p class="text-green-900 font-mono">{selectedRequest.whatsapp_pengurus_1}</p>
              </div>
            </div>
            
            <button
              type="button"
              on:click={clearMushafRequestSelection}
              class="mt-4 w-full px-3 py-2 text-green-700 bg-white border border-green-300 rounded-lg hover:bg-green-50 transition-colors text-sm"
            >
              Hapus Pilihan & Edit Manual
            </button>
          </div>
        {/if}
      {/if}

      <!-- Pengiriman Info -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h4 class="font-semibold text-gray-900 mb-4">Detail Pengiriman</h4>
        
        <div class="space-y-3 text-sm">
          <div>
            <span class="text-gray-600">No. Resi:</span>
            <p class="font-medium font-mono">{pengiriman.no_resi}</p>
          </div>
          
          <div>
            <span class="text-gray-600">Donatur:</span>
            <p class="font-medium">{pengiriman.donatur?.nama_donatur || 'N/A'}</p>
          </div>
          
          <div>
            <span class="text-gray-600">Wakif:</span>
            <p class="font-medium">{pengiriman.wakaf_item?.wakif_name || pengiriman.donatur?.nama_donatur || 'N/A'}</p>
          </div>
          
          {#if pengiriman.wakaf_item?.doa_request}
          <div>
            <span class="text-gray-600">Doa:</span>
            <p class="text-sm italic text-gray-700">{pengiriman.wakaf_item.doa_request}</p>
          </div>
          {/if}
          
          <div>
            <span class="text-gray-600">Jenis Al-Qur'an:</span>
            <p class="font-medium">{pengiriman.jenisQuran?.nama_jenis || pengiriman.jenis_quran?.nama_jenis || 'N/A'}</p>
          </div>
          
          <div>
            <span class="text-gray-600">Jumlah:</span>
            <p class="font-medium">{pengiriman.jumlah_quran || 1} buah</p>
          </div>
          
          <div>
            <span class="text-gray-600">Tanggal Wakaf:</span>
            <p class="font-medium">{formatDate(pengiriman.tanggal_wakaf)}</p>
          </div>
          
          <div>
            <span class="text-gray-600">Status Saat Ini:</span>
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {getStatusColor(pengiriman.status)} mt-1">
              {pengiriman.status?.nama || 'Unknown'}
            </span>
          </div>
        </div>
      </div>

      <!-- QR Code Section -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h4 class="font-semibold text-gray-900 mb-4">QR Code</h4>
        
        {#if hasQRImageFile()}
          <div class="text-center space-y-3">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
              <HeroIcon name="check" class="w-8 h-8 text-green-600" />
            </div>
            <p class="text-sm text-green-600 font-medium">QR Code tersedia</p>
            
            <div class="flex gap-2">
              <button
                on:click={showQR}
                class="w-full px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors"
              >
                Lihat QR
              </button>
            </div>
          </div>
        {:else if hasQRData()}
          <div class="text-center space-y-3">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto">
              <HeroIcon name="exclamation-circle" class="w-8 h-8 text-yellow-600" />
            </div>
            <p class="text-sm text-yellow-600 font-medium">QR data tersedia, gambar belum dibuat</p>
            
            {#if canGenerateQR}
              <button
                on:click={generateQR}
                class="w-full px-3 py-2 bg-[#eb3434] text-white text-sm rounded-lg hover:bg-red-600 transition-colors"
              >
                Generate QR
              </button>
            {:else}
              <p class="text-xs text-gray-500">Tidak memiliki izin untuk generate QR</p>
            {/if}
          </div>
        {:else}
          <div class="text-center space-y-3">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto">
              <HeroIcon name="x-mark" class="w-8 h-8 text-red-600" />
            </div>
            <p class="text-sm text-red-600 font-medium">QR Code belum dibuat</p>
            
            {#if canGenerateQR}
              <button
                on:click={generateQR}
                class="w-full px-3 py-2 bg-[#eb3434] text-white text-sm rounded-lg hover:bg-red-600 transition-colors"
              >
                Generate QR
              </button>
            {:else}
              <p class="text-xs text-gray-500">Tidak memiliki izin untuk generate QR</p>
            {/if}
          </div>
        {/if}
      </div>

      <!-- Tracking Link -->
      <div class="bg-blue-50 rounded-xl border border-blue-200 p-4">
        <h5 class="font-medium text-blue-900 mb-2">Link Tracking</h5>
        <a 
          href="/tracking/{pengiriman.no_resi}" 
          target="_blank"
          class="text-sm text-blue-600 hover:text-blue-800 break-all"
        >
          {window.location.origin}/tracking/{pengiriman.no_resi}
        </a>
      </div>
    </div>
  </div>
</AdminLayout>

<!-- QR Modal -->
{#if showQRModal && hasQRData()}
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" on:click|self={() => showQRModal = false} on:keydown={(e) => e.key === 'Escape' && (showQRModal = false)} role="button" tabindex="0" aria-label="Close modal">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4" role="dialog">
      <div class="text-center">
        <h3 class="text-xl font-bold text-gray-900 mb-4">QR Code - {pengiriman.no_resi}</h3>
        
        <div class="mb-6">
          {#if currentQRUrl}
            <img 
              src={currentQRUrl} 
              alt="QR Code" 
              class="mx-auto rounded-lg shadow-lg max-w-[250px]"
              on:error={handleQRImageError}
              on:load={() => {}}
            />
          {:else}
            <div class="mx-auto w-[250px] h-[250px] bg-gray-100 rounded-lg flex items-center justify-center">
              <div class="text-center">
                <HeroIcon name="qr-code" class="w-16 h-16 text-gray-400 mx-auto mb-2" />
                <p class="text-gray-500 text-sm">QR Code tidak dapat dimuat</p>
              </div>
            </div>
          {/if}
        </div>
        
        <div class="flex gap-3">
          {#if currentQRUrl}
            <a
              href="/admin/qr/download/{pengiriman.id}"
              download="qr-{pengiriman.no_resi}.png"
              class="flex-1 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors text-center"
            >
              Download
            </a>
          {/if}
          <button
            on:click={() => window.print()}
            class="flex-1 px-4 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors"
          >
            Print
          </button>
          <button
            on:click={() => showQRModal = false}
            class="px-4 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Flash Messages -->
<FlashMessage />
