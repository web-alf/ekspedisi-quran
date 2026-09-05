<script>
  import { onMount, onDestroy, tick } from 'svelte';
  import AdminLayout from '@/Layouts/AdminLayout.svelte';
  import { toast, dialog } from '@/utils/notifications.js';
  import FileQRScanner from '@/Components/FileQRScanner.svelte';
  import DokumentasiUpload from '@/Components/DokumentasiUpload.svelte';
  import HeroIcon from '@/Components/UI/HeroIcon.svelte';
  import axios from 'axios';
  import { createScanner } from '@/utils/scanner-core.js';

  // Props
  export let statusList = [];
  export let stats = {};
  export let auth = {};

  const scannerStore = createScanner('qr-reader', {
    onSuccess: onScanSuccess,
    onFailure: onScanFailure,
    onError: (msg) => { error = msg; },
  });

  // State
  let loading = false;
  let showBoxDetails = false;
  let showStatusForm = false;
  let showMushafForm = false;
  let error = null;

  // Box data
  let scannedBox = null;
  let boxSummary = null;
  let boxItems = [];

  // Form data
  let selectedStatusId = '';
  let selectedMushafRequestId = '';
  let showFileScanner = false;
  let previewImages = [];

  // Manual input
  let showManualInput = false;
  let manualBoxCode = '';

  // Mobile detection
  let isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

  // Update status form data
  let updateAddress = '';
  let updateNotes = '';
  let updateLocation = '';
  let uploadedFile = null;
  let dokumentasiUploadComponent;

  function onScanSuccess(decodedText, decodedResult) {
    if (loading || !decodedText?.trim()) return;
    handleScanSuccess(decodedText);
  }

  function onScanFailure(scanError) {
    // Silent fail for normal scan attempts
  }

  async function handleScanSuccess(decodedText) {
    loading = true;
    error = null;

    try {
      const response = await axios.post('/admin/warehouse/box-scanner/scan', {
        qr_code: decodedText.trim()
      });

      if (response.data.success) {
        scannedBox = response.data.data.box || null;
        boxSummary = response.data.data.summary || null;
        boxItems = Array.isArray(response.data.data.items) ? response.data.data.items : [];
        showBoxDetails = true;

        toast.success('Box berhasil di-scan!');
      } else {
        throw new Error(response.data.message || 'Scan gagal');
      }
    } catch (err) {
      console.error('Box scan error:', err);
      const message = err.response?.data?.message || err.message || 'Gagal scan box';
      toast.error(message);
    } finally {
      loading = false;
    }
  }

  function resumeScanning() {
    showBoxDetails = false;
    showStatusForm = false;
    showMushafForm = false;
    scannedBox = null;
    showManualInput = false;
    manualBoxCode = '';
    resetUpdateForm();

    scannerStore.restart();
  }

  function toggleFileScanner() {
    showFileScanner = !showFileScanner;
    if (showFileScanner && $scannerStore.scanning) {
      scannerStore.cleanup();
    } else if (!showFileScanner && !$scannerStore.scanning) {
      scannerStore.restart();
    }
  }

  onMount(async () => {
    await tick();
    setTimeout(() => {
      scannerStore.requestPermission();
    }, 100);
  });

  onDestroy(() => {
    scannerStore.cleanup();
  });

  // File scanner handling
  function handleFileScanned(event) {
    const qrCode = event.detail;
    if (qrCode) {
      handleScanSuccess(qrCode);
    }
  }

  // Manual input handling
  function toggleManualInput() {
    showManualInput = !showManualInput;
    if (showManualInput && $scannerStore.scanning) {
      scannerStore.cleanup();
    }
    manualBoxCode = '';
    error = null;
  }

  async function submitManualCode() {
    if (!manualBoxCode.trim()) {
      toast.error('Masukkan kode box');
      return;
    }

    const boxCode = manualBoxCode.trim().toUpperCase();

    // Validate box code format
    if (!boxCode.match(/^KB-\d{8}-\d{3}-[A-Z0-9]+-\d{2}$/)) {
      toast.error('Format kode box tidak valid. Format: KB-YYYYMMDD-XXX-JENIS-NN');
      return;
    }

    await handleScanSuccess(boxCode);
    manualBoxCode = '';
  }

  // Handle Enter key on manual input
  function handleManualKeydown(event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      submitManualCode();
    }
  }

  // Form functions (simplified)
  function resetUpdateForm() {
    selectedStatusId = '';
    selectedMushafRequestId = '';
    updateAddress = '';
    updateNotes = '';
    updateLocation = '';
    uploadedFile = null;
    previewImages = [];

    // Clear dokumentasi upload component
    if (dokumentasiUploadComponent) {
      dokumentasiUploadComponent.clearAll();
    }
  }

  // Handle photos changed from DokumentasiUpload component
  function handlePhotosChanged(event) {
    const { files } = event.detail;

    // Set the first file as uploadedFile (backend only accepts single file)
    if (files && files.length > 0) {
      uploadedFile = files[0];

      // Validation
      if (uploadedFile.size > 10 * 1024 * 1024) {
        toast.error('File terlalu besar. Maksimal 10MB');
        uploadedFile = null;
        return;
      }

      if (!uploadedFile.type.startsWith('image/')) {
        toast.error('Hanya file gambar yang diperbolehkan');
        uploadedFile = null;
        return;
      }
    } else {
      uploadedFile = null;
    }
  }

  async function updateStatus() {
    if (!selectedStatusId) {
      toast.error('Pilih status terlebih dahulu');
      return;
    }
    
    if (loading) return;
    
    const selectedStatus = statusList.find(s => s.id == selectedStatusId);
    if (!selectedStatus) {
      toast.error('Status tidak valid');
      return;
    }
    
    const confirmed = await dialog.confirm(
      `Update ${boxItems.length} pengiriman ke status "${selectedStatus.nama}"?`
    );
    
    if (!confirmed) return;
    
    loading = true;
    error = null;
    
    try {
      if (!scannedBox?.id) {
        throw new Error('Box ID tidak tersedia');
      }
      
      const formData = new FormData();
      formData.append('box_id', scannedBox.id);
      formData.append('new_status_id', selectedStatusId);
      
      if (updateAddress?.trim()) {
        formData.append('address', updateAddress.trim());
      }
      if (updateNotes?.trim()) {
        formData.append('notes', updateNotes.trim());
      }
      if (updateLocation?.trim()) {
        formData.append('location', updateLocation.trim());
      }
      if (uploadedFile) {
        if (uploadedFile.size > 10 * 1024 * 1024) {
          throw new Error('File terlalu besar (maksimal 10MB)');
        }
        if (!uploadedFile.type.startsWith('image/')) {
          throw new Error('Hanya file gambar yang diperbolehkan');
        }
        formData.append('documentation', uploadedFile);
      }
      
      const response = await axios.post('/admin/warehouse/box-scanner/update-status', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        timeout: 30000
      });
      
      if (response.data.success) {
        toast.success(response.data.message || 'Status berhasil diupdate');
        resetUpdateForm();
        resumeScanning();
      } else {
        throw new Error(response.data.message || 'Update status gagal');
      }
    } catch (err) {
      console.error('Update status error:', err);
      const message = err.response?.data?.message || err.message || 'Gagal update status';
      toast.error(message);
    } finally {
      loading = false;
    }
  }

  async function setMushafAddress() {
    if (!selectedMushafRequestId) {
      toast.error('Pilih permintaan mushaf terlebih dahulu');
      return;
    }
    
    if (loading) return;
    
    const selectedRequest = boxSummary?.mushaf_requests?.find(
      mr => mr.id == selectedMushafRequestId
    );
    
    if (!selectedRequest) {
      toast.error('Permintaan mushaf tidak ditemukan');
      return;
    }
    
    const confirmed = await dialog.confirm(
      `Set alamat ${boxItems.length} pengiriman ke "${selectedRequest.nama_lembaga}"?\n\nData yang akan diset:\n- Nama: ${selectedRequest.nama_lembaga}\n- Alamat: ${selectedRequest.alamat}\n- WhatsApp: ${selectedRequest.whatsapp_pengurus_1 || 'Tidak ada'}`
    );
    
    if (!confirmed) return;
    
    loading = true;
    error = null;
    
    try {
      if (!scannedBox?.id) {
        throw new Error('Box ID tidak tersedia');
      }
      
      const response = await axios.post('/admin/warehouse/box-scanner/set-mushaf-address', {
        box_id: scannedBox.id,
        mushaf_request_id: selectedMushafRequestId
      }, {
        timeout: 30000
      });
      
      if (response.data.success) {
        toast.success(response.data.message || 'Alamat berhasil diset');
        resumeScanning();
      } else {
        throw new Error(response.data.message || 'Set alamat gagal');
      }
    } catch (err) {
      console.error('Set mushaf address error:', err);
      const message = err.response?.data?.message || err.message || 'Gagal set alamat';
      toast.error(message);
    } finally {
      loading = false;
    }
  }
</script>

<svelte:head>
  <title>Box Scanner - Ekspedisi Quran</title>
</svelte:head>

<AdminLayout user={auth.user}>
  <div class="min-h-screen bg-gray-50">
    <div class="container mx-auto px-3 sm:px-4 lg:px-6 py-4 sm:py-6 lg:py-8">
      <!-- Header -->
      <div class="mb-6 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Box Scanner</h1>
        <p class="mt-2 text-sm sm:text-base text-gray-600">Scan QR box untuk update status dan alamat pengiriman</p>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
          <div class="text-xs sm:text-sm font-medium text-gray-500">Box Scanned Today</div>
          <div class="mt-1 sm:mt-2 text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{stats.boxes_scanned_today || 0}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
          <div class="text-xs sm:text-sm font-medium text-gray-500">Items Updated Today</div>
          <div class="mt-1 sm:mt-2 text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{stats.items_updated_today || 0}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
          <div class="text-xs sm:text-sm font-medium text-gray-500">Active Boxes</div>
          <div class="mt-1 sm:mt-2 text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{stats.active_boxes || 0}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
          <div class="text-xs sm:text-sm font-medium text-gray-500">Pending Shipment</div>
          <div class="mt-1 sm:mt-2 text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">{stats.pending_shipment || 0}</div>
        </div>
    </div>
    
      <!-- Scanner -->
      {#if !showBoxDetails}
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
          <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 space-y-2 sm:space-y-0">
            <h2 class="text-lg sm:text-xl font-semibold">
              {showManualInput ? '⌨️ Input Kode Manual' : '📷 Scan QR Code Box'}
            </h2>
            <div class="flex gap-2">
              <button
                on:click={toggleManualInput}
                class="self-start sm:self-auto px-3 py-2 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors font-medium"
              >
                {showManualInput ? '📷 Scan QR' : '⌨️ Input Manual'}
              </button>
              {#if isMobileDevice && !showManualInput}
                <button
                  on:click={toggleFileScanner}
                  class="self-start sm:self-auto px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-1.5"
                >
                  <HeroIcon name={showFileScanner ? 'camera' : 'folder'} class="w-4 h-4" />
                  <span>{showFileScanner ? 'Camera' : 'File'}</span>
                </button>
              {/if}
            </div>
          </div>

        {#if showManualInput}
          <!-- Manual Input Form -->
          <div class="max-w-xl mx-auto">
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="flex items-start">
                <HeroIcon name="information-circle" class="h-5 w-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" />
                <div class="flex-1">
                  <h4 class="text-sm font-medium text-blue-800 mb-1">Format Kode Box</h4>
                  <p class="text-xs text-blue-600">KB-YYYYMMDD-XXX-JENIS-NN</p>
                  <p class="text-xs text-blue-600 mt-1">Contoh: KB-20250113-001-A5-01</p>
                </div>
              </div>
            </div>

            <div class="space-y-4">
              <div>
                <label for="manual-box-code" class="block text-sm font-medium text-gray-700 mb-2">
                  Kode Box
                </label>
                <input
                  type="text"
                  id="manual-box-code"
                  bind:value={manualBoxCode}
                  on:keydown={handleManualKeydown}
                  placeholder="KB-20250113-001-A5-01"
                  class="w-full px-4 py-3 text-base font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 uppercase"
                  disabled={loading}
                  autocomplete="off"
                />
              </div>

              <button
                on:click={submitManualCode}
                disabled={loading || !manualBoxCode.trim()}
                class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5"
              >
                {#if loading}
                  <div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div>
                  <span>Memproses...</span>
                {:else}
                  <HeroIcon name="magnifying-glass" class="w-4 h-4" />
                  <span>Cari Box</span>
                {/if}
              </button>
            </div>

            {#if error}
              <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2">
                <HeroIcon name="x-circle" class="w-4 h-4 text-red-600 flex-shrink-0" />
                <p class="text-sm text-red-600">{error}</p>
              </div>
            {/if}
          </div>
        {:else if showFileScanner}
          <FileQRScanner on:scan={handleFileScanned} />
          {:else if !$scannerStore.permissionGranted}
            <!-- Permission Request -->
            <div class="text-center py-6 sm:py-8">
              <div class="mb-4 p-4 sm:p-6 bg-yellow-50 border border-yellow-200 rounded-lg">
                <HeroIcon name="video-camera" class="mx-auto h-10 w-10 sm:h-12 sm:w-12 text-yellow-600 mb-2" />
                <h3 class="text-base sm:text-lg font-medium text-yellow-800">Izin Kamera Diperlukan</h3>
                <p class="text-sm text-yellow-600 mt-1">Untuk scan QR Code, kami memerlukan akses ke kamera perangkat Anda</p>
              </div>
              <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <button
                  on:click={() => scannerStore.requestPermission()}
                  class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
                >
                  Berikan Izin Kamera
                </button>
                <button
                  on:click={() => showFileScanner = true}
                  class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 font-medium"
                >
                  Gunakan File Scanner
                </button>
              </div>
            </div>
          {:else}
            <div
              id="qr-reader"
              class="mx-auto w-full max-w-sm sm:max-w-md lg:max-w-lg rounded-lg overflow-hidden bg-gray-900"
              style="min-height: 300px;"
            ></div>
          {/if}

          <!-- Scanner Status -->
          <div class="mt-4 sm:mt-6 text-center px-2">
            {#if $scannerStore.scanning}
              <p class="text-sm sm:text-base text-green-600 leading-relaxed flex items-center justify-center gap-1.5">
                <HeroIcon name="check-circle" class="w-4 h-4 text-green-600" />
                <span>Scanner aktif - Arahkan kamera ke QR Code pada box</span>
              </p>
            {:else if loading}
              <div class="inline-flex items-center px-4 py-2 text-sm font-medium text-yellow-700 bg-yellow-50 rounded-lg">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-yellow-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Memproses...
              </div>
            {:else if error}
              <div class="p-3 sm:p-4 bg-red-50 border border-red-200 rounded-lg max-w-md mx-auto">
                <p class="text-sm text-red-600 mb-3 flex items-center justify-center gap-1.5">
                  <HeroIcon name="x-circle" class="w-4 h-4 text-red-600" />
                  <span>{error}</span>
                </p>
                <button
                  class="w-full sm:w-auto text-sm bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors font-medium"
                  on:click={() => scannerStore.restart()}
                >
                  Coba Lagi
                </button>
              </div>
            {:else if $scannerStore.permissionGranted}
              <p class="text-sm text-blue-600 flex items-center justify-center gap-1.5">
                <HeroIcon name="arrow-path" class="w-4 h-4 text-blue-600 animate-spin" />
                <span>Mempersiapkan scanner...</span>
              </p>
            {/if}

            <div class="mt-4 sm:mt-6 flex justify-center">
              <button
                on:click={() => scannerStore.restart()}
                class="px-4 py-3 sm:py-2 text-red-600 border border-red-600 rounded-lg hover:bg-red-600 hover:text-white transition-colors text-sm font-medium min-w-[140px] flex items-center justify-center gap-1.5"
              >
                <HeroIcon name="arrow-path" class="w-4 h-4" />
                <span>Restart Scanner</span>
              </button>
            </div>
        </div>
      </div>
    {/if}
      
      <!-- Box Details (simplified version) -->
      {#if showBoxDetails && scannedBox}
        <div class="bg-white rounded-lg shadow p-4 sm:p-6">
          <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-3 sm:space-y-0">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2 mb-1">
                <HeroIcon name="cube" class="w-5 h-5 text-gray-700" />
                <h2 class="text-lg sm:text-xl font-semibold truncate">Detail Box</h2>
              </div>
              <p class="text-sm sm:text-base text-gray-600 font-mono break-all">{scannedBox.kode_kerdus}</p>
            </div>
            <button
              on:click={resumeScanning}
              class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 font-medium"
            >
              Scan Box Lain
            </button>
          </div>
          
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            <!-- Box Info -->
            <div class="p-4 bg-gray-50 rounded-lg">
              <h3 class="font-semibold mb-3 text-gray-900">Informasi Box</h3>
              <div class="text-sm space-y-2">
                <div class="flex justify-between">
                  <span class="text-gray-600">QR Code:</span>
                  <span class="font-mono text-xs sm:text-sm break-all">{scannedBox.kode_kerdus}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Total Item:</span>
                  <span class="font-semibold">{boxItems.length}</span>
                </div>
                <div class="flex justify-between items-start">
                  <span class="text-gray-600">Status:</span>
                  <span class="font-medium text-right">{scannedBox.status_display || scannedBox.status || 'Tidak ada'}</span>
                </div>
              </div>
            </div>
            
            <!-- Actions -->
            <div class="space-y-3">
              <button
                on:click={() => showStatusForm = !showStatusForm}
                class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors flex items-center justify-center gap-1.5"
              >
                <HeroIcon name="document-text" class="w-4 h-4" />
                <span>Update Status</span>
              </button>
              
              {#if boxSummary?.mushaf_requests?.length > 0}
                <button
                  on:click={() => showMushafForm = !showMushafForm}
                  class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium transition-colors flex items-center justify-center gap-1.5"
                >
                  <HeroIcon name="map-pin" class="w-4 h-4" />
                  <span>Set Alamat Mushaf</span>
                </button>
              {/if}
            </div>
        </div>
        
          <!-- Status Form -->
          {#if showStatusForm}
            <div class="mt-4 sm:mt-6 p-4 sm:p-6 bg-gray-50 rounded-lg">
              <h4 class="font-semibold mb-4 text-gray-900">Update Status Pengiriman</h4>
              <div class="space-y-4">
                <div>
                  <label for="status-baru" class="block text-sm font-medium text-gray-700 mb-2">Status Baru</label>
                  <select
                    id="status-baru"
                    bind:value={selectedStatusId}
                    class="w-full px-3 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">Pilih Status</option>
                    {#each statusList as status}
                      <option value={status.id}>{status.nama}</option>
                    {/each}
                  </select>
                </div>
              
                
                <!-- Address -->
                <div>
                  <label for="update-address" class="block text-sm font-medium text-gray-700 mb-2">Alamat (Optional)</label>
                  <textarea
                    id="update-address"
                    bind:value={updateAddress}
                    rows="3"
                    class="w-full px-3 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Alamat tujuan untuk update status..."
                  ></textarea>
                </div>
                
                <!-- Notes -->
                <div>
                  <label for="update-notes" class="block text-sm font-medium text-gray-700 mb-2">Catatan (Optional)</label>
                  <textarea
                    id="update-notes"
                    bind:value={updateNotes}
                    rows="3"
                    class="w-full px-3 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Catatan untuk update status..."
                  ></textarea>
                </div>
                
                <!-- Location -->
                <div>
                  <label for="update-location" class="block text-sm font-medium text-gray-700 mb-2">Lokasi (Optional)</label>
                  <input
                    type="text"
                    id="update-location"
                    bind:value={updateLocation}
                    class="w-full px-3 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Lokasi update status..."
                  />
                </div>
              
                
                <!-- Documentation Upload with Camera -->
                <DokumentasiUpload
                  bind:this={dokumentasiUploadComponent}
                  maxPhotos={1}
                  label="Dokumentasi (Foto)"
                  showToggle={true}
                  defaultMode="upload"
                  allowModeSwitch={true}
                  on:photosChanged={handlePhotosChanged}
                />
                
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                  <button
                    on:click={updateStatus}
                    class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5"
                    disabled={loading || !selectedStatusId}
                  >
                    {#if loading}
                      <div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div>
                      <span>Processing...</span>
                    {:else}
                      <HeroIcon name="document-text" class="w-4 h-4" />
                      <span>Update ({boxItems.length})</span>
                    {/if}
                  </button>
                  <button
                    on:click={() => showStatusForm = false}
                    class="w-full sm:w-auto px-4 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 font-medium transition-colors disabled:opacity-50"
                    disabled={loading}
                  >
                    Batal
                  </button>
                </div>
            </div>
          </div>
        {/if}
          
          <!-- Mushaf Form -->
          {#if showMushafForm && boxSummary?.mushaf_requests?.length > 0}
            <div class="mt-4 sm:mt-6 p-4 sm:p-6 bg-gray-50 rounded-lg">
              <h4 class="font-semibold mb-4 text-gray-900">Set Alamat Mushaf</h4>
              <div class="space-y-4">
                <div>
                  <label for="mushaf-request-select" class="block text-sm font-medium text-gray-700 mb-2">Permintaan Mushaf</label>
                  <select
                    id="mushaf-request-select"
                    bind:value={selectedMushafRequestId}
                    class="w-full px-3 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                  >
                    <option value="">Pilih Permintaan Mushaf</option>
                    {#each boxSummary.mushaf_requests as request}
                      <option value={request.id}>
                        {request.nama_lembaga} - {request.alamat}
                      </option>
                    {/each}
                  </select>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3">
                  <button
                    on:click={setMushafAddress}
                    class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5"
                    disabled={loading || !selectedMushafRequestId}
                  >
                    {#if loading}
                      <div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div>
                      <span>Processing...</span>
                    {:else}
                      <HeroIcon name="map-pin" class="w-4 h-4" />
                      <span>Set ({boxItems.length})</span>
                    {/if}
                  </button>
                  <button
                    on:click={() => showMushafForm = false}
                    class="w-full sm:w-auto px-4 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 font-medium transition-colors disabled:opacity-50"
                    disabled={loading}
                  >
                    Batal
                  </button>
                </div>
              </div>
            </div>
          {/if}
        </div>
      {/if}
    </div>
  </div>
</AdminLayout>
