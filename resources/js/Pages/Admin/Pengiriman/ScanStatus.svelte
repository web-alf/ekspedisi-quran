<script>
  import { onMount, onDestroy, tick } from 'svelte';
  import AdminLayout from '@/Layouts/AdminLayout.svelte';
  import TabNavigation from '@/Components/TabNavigation.svelte';
  import DokumentasiUpload from '@/Components/DokumentasiUpload.svelte';
  import { page } from '@inertiajs/svelte';
  import { createScanner } from '@/utils/scanner-core.js';

  // Props dari Inertia
  export let statusList = [];
  export const user = {};
  export const errors = {};
  export const flash = {};
  export const auth = {};
  export const pengiriman = [];
  export const jenisQuranList = [];
  export const wakifList = [];
  export const donaturList = [];
  export const stats = {};
  export const filters = {};

  // Get mode from URL or default to 'scan-status'
  let currentMode = 'scan-status';
  $: {
    const urlParams = new URLSearchParams(window.location.search);
    currentMode = urlParams.get('mode') || 'scan-status';
  }

  const scannerStore = createScanner('qr-reader', {
    onSuccess: onScanSuccess,
    onFailure: onScanFailure,
    onError: (msg) => { error = msg; },
  });

  // State management
  let loading = false;
  let error = null;
  let successData = null;
  let validationErrors = {};

  // Form data
  let showStatusForm = false;
  let scannedResi = '';
  let selectedStatus = '';
  let catatan = '';
  let lokasi = '';
  let dokumentasi = [];
  let previewImages = [];
  let latitude = null;
  let longitude = null;
  let manualInput = '';
  let qrDataForUpdate = null;
  let pengirimanInfo = null;
  let validStatuses = [];

  // DokumentasiUpload component reference
  let dokumentasiUploadComponent;

  onMount(async () => {
    if (statusList.length > 0) {
      selectedStatus = statusList[0].id;
    }
    await tick();
    setTimeout(() => {
      scannerStore.requestPermission();
    }, 100);
  });

  onDestroy(() => {
    scannerStore.cleanup();
  });

  function onScanSuccess(decodedText, decodedResult) {
    if (loading) return;
    processQRResult(decodedText);
  }

  function onScanFailure(scanError) {
    // Silent fail for normal scan attempts
  }

  function processQRResult(qrText) {
    loading = true;
    error = null;
    
    try {
      const extractedData = extractResiNumber(qrText);
      
      if (extractedData) {
        scannedResi = extractedData.no_resi;
        qrDataForUpdate = extractedData.qr_data;
        
        // Get pengiriman info to determine valid next statuses
        fetchPengirimanInfo(extractedData.no_resi);
        
      } else {
        throw new Error('QR Code tidak mengandung nomor resi yang valid');
      }
    } catch (err) {
      error = err.message;
      loading = false;
    }
  }
  
  function extractResiNumber(qrText) {
    try {
      // Try JSON format first
      const data = JSON.parse(qrText);
      if (data.type === 'ekspedisi_quran' && data.no_resi) {
        return {
          no_resi: data.no_resi,
          qr_data: qrText,
          is_verified: true
        };
      }
      if (data.no_resi) {
        return {
          no_resi: data.no_resi,
          qr_data: qrText,
          is_verified: false
        };
      }
    } catch {
      // Not JSON, try patterns
    }
    
    // Direct resi pattern
    let match = qrText.match(/EQ-\d{4}-\d{5}/);
    if (match) {
      return {
        no_resi: match[0],
        qr_data: null,
        is_verified: false
      };
    }
    
    // URL pattern
    match = qrText.match(/tracking\/([A-Z]{2}-\d{4}-\d{5})/);
    if (match) {
      return {
        no_resi: match[1],
        qr_data: null,
        is_verified: false
      };
    }
    
    return null;
  }
  
  // Handle dokumentasi upload change
  function handleDokumentasiChange(event) {
    const detail = event.detail;

    if (detail.mode === 'camera') {
      // Convert camera photos to files
      dokumentasi = detail.files || [];
    } else {
      // File upload mode
      dokumentasi = detail.files || [];
    }

    console.log('Dokumentasi updated:', dokumentasi.length, 'files');
  }

  async function updateStatus() {
    try {
      loading = true;
      error = null;
      validationErrors = {}; // Clear previous validation errors

      // Validation
      if (!scannedResi || !selectedStatus) {
        error = 'Nomor resi dan status baru harus diisi';
        loading = false;
        return;
      }

      const formData = new FormData();
      formData.append('no_resi', scannedResi);
      formData.append('status_id', selectedStatus.toString()); // Ensure it's string for FormData
      formData.append('catatan', catatan || ''); // Never undefined/null
      formData.append('lokasi', lokasi || ''); // Never undefined/null

      // Add QR data if available
      if (qrDataForUpdate) {
        formData.append('qr_data', qrDataForUpdate);
      }

      if (latitude && longitude) {
        formData.append('latitude', latitude);
        formData.append('longitude', longitude);
      }

      // Get files from DokumentasiUpload component if available
      if (dokumentasiUploadComponent) {
        const files = dokumentasiUploadComponent.getFiles();
        if (files && files.length > 0) {
          for (let i = 0; i < files.length; i++) {
            formData.append('dokumentasi[]', files[i]);
          }
          console.log('Appending', files.length, 'files from DokumentasiUpload component');
        }
      }
      // Fallback: Append files from dokumentasi array (if not using component)
      else if (dokumentasi && dokumentasi.length > 0) {
        for (let i = 0; i < dokumentasi.length; i++) {
          formData.append('dokumentasi[]', dokumentasi[i]);
        }
        console.log('Appending', dokumentasi.length, 'files from dokumentasi array');
      }
      
      
      // Fixed: Use original route that has been repaired
      const response = await fetch('/admin/pengiriman/scan-status/update', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      });
      
      
      if (!response.ok) {
        const errorData = await response.json().catch(() => ({ message: 'Network error' }));
        
        // Show specific validation errors with format info
        if (errorData.field_errors) {
          
          // Display user-friendly error messages
          Object.keys(errorData.field_errors).forEach(field => {
            const fieldErrors = errorData.field_errors[field];
            fieldErrors.forEach(errorMsg => {
              
              // Add format hints for common validation errors
              if (field === 'no_resi' && errorMsg.includes('format')) {
              }
              if (field === 'catatan' && errorMsg.includes('minimal')) {
              }
              if (field === 'status_id' && errorMsg.includes('tidak valid')) {
              }
              if (field === 'latitude' && errorMsg.includes('between')) {
              }
              if (field === 'longitude' && errorMsg.includes('between')) {
              }
            });
          });
        }
        
        // Show debug info for common issues
        if (errorData.debug_info) {
          
          // Check for same status issue
          if (errorData.debug_info.is_same_status) {
          }
        }
        
        // Store validation errors for UI display
        if (errorData.field_errors) {
          validationErrors = errorData.field_errors;
        } else {
        }
        
        throw new Error(errorData.message || `Server error: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.success) {
        successData = data.data;
        validationErrors = {}; // Clear validation errors on success
        resetForm();
        
        // Auto restart scanner after 3 seconds
        setTimeout(() => {
          successData = null;
          restartScanner();
        }, 3000);
      } else {
        error = data.message || 'Update gagal';
      }
    } catch (err) {
      
      error = err.message || 'Terjadi kesalahan saat update status';
      
      // Show user-friendly error messages
      if (err.message.includes('422')) {
        error = 'Data yang dikirim tidak valid. Periksa kembali form Anda.';
      } else if (err.message.includes('404')) {
        error = 'Pengiriman tidak ditemukan. Periksa nomor resi.';
      } else if (err.message.includes('403')) {
        error = 'Anda tidak memiliki izin untuk melakukan update ini.';
      } else if (err.message.includes('500')) {
        error = 'Terjadi kesalahan server. Silakan coba lagi atau hubungi administrator.';
      }
    } finally {
      loading = false;
    }
  }
  
  function resetForm() {
    showStatusForm = false;
    scannedResi = '';
    catatan = '';
    lokasi = '';
    dokumentasi = [];
    previewImages = [];
    latitude = null;
    longitude = null;
    qrDataForUpdate = null;
    pengirimanInfo = null;
    validStatuses = [];
    validationErrors = {}; // Clear validation errors

    // Clear DokumentasiUpload component if available
    if (dokumentasiUploadComponent) {
      dokumentasiUploadComponent.clearAll();
    }
  }
  
  function getFormatHint(field, errorMessage) {
    
    const hints = {
      'no_resi': {
        'format': 'Format: EQ-YYYY-NNNNN (contoh: EQ-2025-00001)',
        'exists': 'Nomor resi tidak ditemukan di database',
        'regex': 'Format: EQ-YYYY-NNNNN (contoh: EQ-2025-00001)'
      },
      'status_id': {
        'exists': 'Pilih status dari dropdown yang tersedia',
        'tidak aktif': 'Status yang dipilih sudah tidak aktif',
        'sama': 'Pilih status yang berbeda dari status saat ini',
        'tidak valid': 'Pilih status dari dropdown yang tersedia'
      },
      'catatan': {
        'minimal': 'Catatan minimal 3 karakter atau kosongkan',
        'max': 'Catatan maksimal 500 karakter',
        'maksimal': 'Catatan maksimal 500 karakter'
      },
      'lokasi': {
        'max': 'Lokasi maksimal 255 karakter',
        'maksimal': 'Lokasi maksimal 255 karakter'
      },
      'latitude': {
        'between': 'Latitude: -90 hingga 90 (contoh: -6.2088)'
      },
      'longitude': {
        'between': 'Longitude: -180 hingga 180 (contoh: 106.8456)'
      },
      'dokumentasi': {
        'max': 'Maksimal 5 file dokumentasi',
        'image': 'File harus berupa gambar (JPG, PNG, GIF, WebP)',
        'mimes': 'Format file: JPEG, JPG, PNG, GIF, WebP saja',
        'size': 'Ukuran file maksimal 10MB'
      }
    };
    
    if (hints[field]) {
      const errorLower = errorMessage.toLowerCase();
      
      for (const [key, hint] of Object.entries(hints[field])) {
        if (errorLower.includes(key.toLowerCase())) {
          return hint;
        }
      }
      
    } else {
    }
    
    return null;
  }
  
  // Get user's location if available
  function getUserLocation() {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition((position) => {
        latitude = position.coords.latitude;
        longitude = position.coords.longitude;

        // Optional: Get address from coordinates
        if (latitude && longitude) {
          lokasi = `Lat: ${latitude.toFixed(6)}, Lng: ${longitude.toFixed(6)}`;
        }
      }, (error) => {
        // Fallback or show error message
      });
    } else {
    }
  }
  
  function restartScanner() {
    error = null;
    resetForm();
    scannerStore.restart();
  }
  
  // FIXED: Fetch pengiriman info dengan URL yang benar
  async function fetchPengirimanInfo(noResi) {
    try {
      
      // Fixed: Use original route that has been repaired
      const response = await fetch(`/admin/pengiriman-status/${noResi}`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      
      if (!response.ok) {
        if (response.status === 404) {
          throw new Error(`Pengiriman dengan resi ${noResi} tidak ditemukan`);
        } else {
          throw new Error(`Server error: ${response.status}`);
        }
      }
      
      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.message || 'Gagal mengambil data pengiriman');
      }
      
      pengirimanInfo = data.pengiriman;
      validStatuses = data.validStatuses || [];
      
      // Auto-select first valid status
      if (validStatuses.length > 0) {
        selectedStatus = validStatuses[0].id;
      }
      
      showStatusForm = true;
      
      
    } catch (err) {
      error = err.message;
      
      // Auto restart scanner after error
      setTimeout(() => {
        error = null;
        resetForm();
        restartScanner();
      }, 3000);
    } finally {
      loading = false;
    }
  }
  
  function processManualInput() {
    if (manualInput.trim()) {
      const extractedData = extractResiNumber(manualInput.trim());
      if (extractedData) {
        scannedResi = extractedData.no_resi;
        qrDataForUpdate = extractedData.qr_data;
        
        // Get pengiriman info for manual input too
        fetchPengirimanInfo(extractedData.no_resi);
        manualInput = '';
      } else {
        error = 'Format nomor resi tidak valid';
      }
    }
  }
  
  function getStatusColor(statusId) {
    const status = statusList.find(s => s.id == statusId);
    return status ? status.warna : 'gray';
  }
  
  function getStatusName(statusId) {
    const status = statusList.find(s => s.id == statusId);
    return status ? status.nama : 'Unknown';
  }
  
  function getStatusIcon(statusId) {
    const status = statusList.find(s => s.id == statusId);
    return status ? status.icon : '📦';
  }
  
  function getStatusDescription(statusId) {
    const status = statusList.find(s => s.id == statusId);
    return status ? status.deskripsi : 'Update status pengiriman';
  }
</script>

<svelte:head>
  <title>Scan QR Code - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <div class="mb-6 md:mb-8">
    <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">Scan QR Code</h2>
    <p class="text-sm md:text-base text-gray-600">Scan QR Code untuk update status pengiriman</p>
  </div>
      
  <TabNavigation {currentMode} />
  
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
      {#if !$scannerStore.permissionGranted}
        <!-- Permission Request -->
        <div class="p-6 sm:p-8 text-center">
          <div class="w-16 h-16 sm:w-20 sm:h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 002 2v8a2 2 0 002 2z"/>
            </svg>
          </div>
          <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2">Izin Kamera Diperlukan</h3>
          <p class="text-sm sm:text-base text-gray-600 mb-6">Untuk scan QR Code, kami memerlukan akses ke kamera perangkat Anda</p>
          <button
            on:click={() => scannerStore.requestPermission()}
            class="px-6 py-3 bg-[#eb3434] text-white font-semibold rounded-lg hover:bg-red-600 transition-colors text-sm sm:text-base"
          >
            Berikan Izin Kamera
          </button>
        </div>
    {:else}
        <!-- Scanner Active -->
        <div class="p-4 sm:p-6">
          {#if !showStatusForm && !successData}
            <!-- QR Scanner -->
            <div class="bg-gray-900 rounded-xl overflow-hidden mb-4">
              <div id="qr-reader" class="min-h-[250px] sm:min-h-[300px]"></div>
            </div>

            <div class="text-center">
              {#if $scannerStore.scanning}
                <p class="text-xs sm:text-sm text-green-600 mb-4">
                  ✅ Scanner aktif - Arahkan kamera ke QR Code pada paket
                </p>
              {:else if loading}
                <p class="text-xs sm:text-sm text-yellow-600 mb-4">
                  ⏳ Memproses QR Code...
                </p>
              {:else if error}
                <p class="text-xs sm:text-sm text-red-600 mb-4 break-words">
                  ❌ Error: {error}
                </p>
              {:else}
                <p class="text-xs sm:text-sm text-blue-600 mb-4">
                  🔄 Mempersiapkan scanner...
                </p>
              {/if}

              <div class="flex flex-col sm:flex-row justify-center space-y-2 sm:space-y-0 sm:space-x-4">
                <button
                  on:click={() => scannerStore.restart()}
                  class="px-4 py-2 text-red-600 border border-red-600 rounded hover:bg-red-600 hover:text-white transition-colors text-sm"
                >
                  🔄 Restart Scanner
                </button>
                
                <button
                  on:click={() => { resetForm(); getUserLocation(); }}
                  class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors text-sm"
                >
                  📍 Get Location
                </button>
              </div>
              
              <!-- Manual Input -->
              <div class="mt-6 p-3 sm:p-4 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Input Manual Nomor Resi</h4>
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                  <input
                    type="text"
                    bind:value={manualInput}
                    placeholder="EQ-2025-00001"
                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-red-500 focus:border-red-500 text-sm"
                  />
                  <button
                    on:click={processManualInput}
                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors text-sm font-medium"
                  >
                    Proses
                  </button>
                </div>
              </div>
            </div>
          {:else if showStatusForm}
            <!-- Status Update Form -->
            <div class="bg-white p-4 sm:p-6 rounded-lg border">
              <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Update Status Pengiriman</h3>
              
              {#if pengirimanInfo}
                <!-- Pengiriman Info -->
                <div class="bg-blue-50 p-3 sm:p-4 rounded-lg mb-6">
                  <h4 class="font-semibold text-blue-900 mb-2 text-sm sm:text-base">📦 {pengirimanInfo.no_resi}</h4>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs sm:text-sm">
                    <div><span class="font-medium">Wakif:</span> {pengirimanInfo.wakif}</div>
                    <div><span class="font-medium">Jenis:</span> {pengirimanInfo.jenis_quran}</div>
                    <div><span class="font-medium">Jumlah:</span> {pengirimanInfo.jumlah_quran} Qur'an</div>
                    <div class="col-span-1 sm:col-span-2"><span class="font-medium">Status Saat Ini:</span> 
                      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                            style="background-color: {pengirimanInfo.current_status.warna}20; color: {pengirimanInfo.current_status.warna};">
                        {pengirimanInfo.current_status.icon} {pengirimanInfo.current_status.nama}
                      </span>
                    </div>
                  </div>
                </div>
              {/if}
              
              <form on:submit|preventDefault={updateStatus} class="space-y-4">
              
              <!-- Test Validation Error Button (for debugging) -->
              {#if typeof window !== 'undefined' && window.location.hostname === 'localhost'}
                <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                  <h4 class="text-yellow-800 font-semibold text-sm mb-2">🧪 Debug Tools:</h4>
                  <div class="flex flex-col sm:flex-row gap-2">
                    <button 
                      type="button"
                      on:click={() => {
                        // Test validation error display
                        validationErrors = {
                          'no_resi': ['Format nomor resi tidak valid. Contoh: EQ-2025-00001'],
                          'status_id': ['Status yang dipilih sama dengan status saat ini.'],
                          'catatan': ['Catatan maksimal 500 karakter.']
                        };
                      }}
                      class="px-3 py-1 bg-yellow-600 text-white text-xs rounded"
                    >
                      Test Error Display
                    </button>
                    <button 
                      type="button"
                      on:click={() => {
                        validationErrors = {};
                      }}
                      class="px-3 py-1 bg-gray-600 text-white text-xs rounded"
                    >
                      Clear Errors
                    </button>
                  </div>
                </div>
              {/if}
                <!-- Validation Errors Display -->
                {#if Object.keys(validationErrors).length > 0}
                  <!-- Debug info -->
                  
                  <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <h4 class="text-red-800 font-semibold text-sm mb-2">❌ Validation Errors:</h4>
                    {#each Object.entries(validationErrors) as [field, errors]}
                      <div class="mb-3 last:mb-0">
                        <div class="text-red-700 font-medium text-sm capitalize">{field.replace('_', ' ')}:</div>
                        {#each errors as errorMsg}
                          {@const hint = getFormatHint(field, errorMsg)}
                          <div class="text-red-600 text-sm mt-1">
                            • {errorMsg}
                            {#if hint}
                              <div class="text-blue-600 text-xs mt-1 ml-3">
                                💡 {hint}
                              </div>
                            {:else}
                              <!-- Debug: no hint found -->
                              <div class="text-gray-500 text-xs mt-1 ml-3">
                                (No format hint for: {field} - {errorMsg})
                              </div>
                            {/if}
                          </div>
                        {/each}
                      </div>
                    {/each}
                  </div>
                {:else}
                  <!-- Debug: no validation errors -->
                {/if}
                <!-- Status Selection -->
                <div>
                  <label for="status_select" class="block text-sm font-medium text-gray-700 mb-2">Status Baru</label>
                  <select id="status_select" bind:value={selectedStatus} class="w-full px-3 py-2.5 border {validationErrors.status_id ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'} rounded-md focus:ring-red-500 focus:border-red-500 text-sm" required>
                    <option value="">Pilih Status Baru</option>
                    {#each validStatuses as status}
                      <option value={status.id}>
                        {status.icon} {status.nama}
                        {#if status.deskripsi} - {status.deskripsi}{/if}
                      </option>
                    {/each}
                  </select>
                  
                  {#if pengirimanInfo && selectedStatus}
                    {@const currentStatusId = pengirimanInfo.current_status.id}
                    {@const selectedStatusId = parseInt(selectedStatus)}
                    {#if currentStatusId === selectedStatusId}
                      <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                        ⚠️ Status yang dipilih sama dengan status saat ini. Pilih status yang berbeda.
                      </div>
                    {/if}
                  {/if}
                </div>
                
                <!-- Catatan -->
                <div>
                  <label for="catatan_input" class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                  <textarea 
                    id="catatan_input"
                    bind:value={catatan} 
                    placeholder="Masukkan catatan untuk update status..."
                    class="w-full px-3 py-2.5 border {validationErrors.catatan ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'} rounded-md focus:ring-red-500 focus:border-red-500 text-sm"
                    rows="3"
                  ></textarea>
                </div>
                
                <!-- Lokasi -->
                <div>
                  <label for="lokasi_input" class="block text-sm font-medium text-gray-700 mb-2">Lokasi</label>
                  <input 
                    id="lokasi_input"
                    type="text" 
                    bind:value={lokasi} 
                    placeholder="Jakarta, Indonesia"
                    class="w-full px-3 py-2.5 border {validationErrors.lokasi ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'} rounded-md focus:ring-red-500 focus:border-red-500 text-sm"
                  />
                </div>
                
                <!-- Dokumentasi Upload (Camera or File) -->
                <div>
                  <DokumentasiUpload
                    bind:this={dokumentasiUploadComponent}
                    maxPhotos={5}
                    label="Dokumentasi (Opsional)"
                    showToggle={true}
                    defaultMode="upload"
                    allowModeSwitch={true}
                    on:photosChanged={handleDokumentasiChange}
                  />
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3">
                  <button 
                    type="submit" 
                    disabled={loading || !selectedStatus}
                    class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 transition-colors text-sm font-medium"
                  >
                    {loading ? '⏳ Memproses...' : '✅ Update Status'}
                  </button>
                  
                  <button 
                    type="button" 
                    on:click={() => { resetForm(); restartScanner(); }}
                    class="px-4 py-2.5 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors text-sm font-medium"
                  >
                    🔄 Batal
                  </button>
                </div>
              </form>
            </div>
          {:else if successData}
            <!-- Success Message -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 sm:p-6 text-center">
              <div class="w-12 h-12 sm:w-16 sm:h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
              </div>
              
              <h3 class="text-base sm:text-lg font-semibold text-green-900 mb-2">Status Berhasil Diupdate! ✅</h3>
              
              <div class="text-xs sm:text-sm text-green-700 space-y-1">
                <p><strong>Resi:</strong> {successData.no_resi}</p>
                <p><strong>Status Baru:</strong> {getStatusName(successData.new_status)}</p>
                {#if successData.lokasi}
                  <p><strong>Lokasi:</strong> <span class="break-words">{successData.lokasi}</span></p>
                {/if}
                {#if successData.files_uploaded > 0}
                  <p><strong>File:</strong> {successData.files_uploaded} file terupload</p>
                {/if}
              </div>
              
              <p class="text-xs text-green-600 mt-4">Scanner akan restart otomatis dalam 3 detik...</p>
            </div>
          {/if}
        </div>
      {/if}
  </div>
</AdminLayout>

<style>
  :global(#qr-reader) {
    border-radius: 12px;
    overflow: hidden;
  }
  
  :global(#qr-reader video) {
    border-radius: 12px;
  }
  
  :global(#qr-reader__dashboard) {
    background: rgba(0, 0, 0, 0.8);
    border-radius: 0 0 12px 12px;
  }
  
  :global(#qr-reader__dashboard_section) {
    background: transparent;
  }
  
  :global(#qr-reader__dashboard_section_csr) {
    text-align: center;
    padding: 8px;
  }
  
  :global(#qr-reader__dashboard_section_csr > button) {
    background: #eb3434 !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 8px 16px !important;
    margin: 4px !important;
    font-weight: 600 !important;
  }
</style>