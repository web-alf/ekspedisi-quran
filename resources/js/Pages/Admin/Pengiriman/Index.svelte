<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '@/Components/FlashMessage.svelte';
  import { onMount, onDestroy } from 'svelte';
  import { Html5QrcodeScanner } from "html5-qrcode";
  import { page } from '@inertiajs/svelte';
  import TabNavigation from '@/Components/TabNavigation.svelte';
  import StatusModal from '@/Components/StatusModal.svelte';
  import CameraCapture from '@/Components/CameraCapture.svelte';
  import HeroIcon from '@/Components/UI/HeroIcon.svelte';
  import { can } from '../../../utils/permissions.js';
  
  // Props from Inertia
  export let pengiriman = [];
  export let filters = {};
  export let statusList = [];
  export let jenisQuranList = [];
  export const donaturList = [];
  export let stats = {};
  export const errors = {};
  export const auth = {};
  export const flash = {};
  
  // Permission checks
  $: canUpdate = can.shipments.update();
  $: canBulkUpdate = can.shipments.bulkUpdate();

  // Initialize filter values to prevent undefined errors
  $: if (!filters.search) filters.search = '';
  $: if (!filters.status) filters.status = '';
  $: if (!filters.jenis_quran) filters.jenis_quran = '';
  $: if (!filters.tanggal_mulai) filters.tanggal_mulai = '';
  $: if (!filters.tanggal_akhir) filters.tanggal_akhir = '';
  $: if (!filters.alamat_status) filters.alamat_status = '';
  $: if (!filters.number_from) filters.number_from = '';
  $: if (!filters.number_to) filters.number_to = '';

  // Date filter variables
  let startDate = filters.tanggal_mulai || '';
  let endDate = filters.tanggal_akhir || '';

  // Number range filter variables
  let numberFrom = filters.number_from || '';
  let numberTo = filters.number_to || '';
  
  // Current date for max date attribute
  let currentDate = new Date().toISOString().split('T')[0];
  
  // Bulk operations state
  let selectedItems = [];
  let showBulkActions = false;
  let bulkStatus = '';
  let bulkMushafRequest = '';
  let bulkCatatan = '';
  let bulkDokumentasi = null;
  let bulkLokasi = '';
  let isBulkProcessing = false;
  let approvedMushafRequests = [];

  // Camera mode for bulk update
  let useBulkCameraMode = false;
  let bulkCapturedPhotos = [];

  // Toggle bulk camera mode
  function toggleBulkCameraMode() {
    useBulkCameraMode = !useBulkCameraMode;

    // Clear existing data when switching modes
    if (useBulkCameraMode) {
      bulkDokumentasi = null;
    } else {
      bulkCapturedPhotos = [];
    }
  }

  // Handle bulk camera capture
  function handleBulkCameraCapture(photos) {
    // Extract dataUrl from photo objects (CameraCapture sends objects with {dataUrl, timestamp, id})
    bulkCapturedPhotos = photos.map(photo => photo.dataUrl);
  }

  // Convert base64 to File object for bulk upload
  function base64ToFile(dataUrl, filename) {
    const arr = dataUrl.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);

    while (n--) {
      u8arr[n] = bstr.charCodeAt(n);
    }

    return new File([u8arr], filename, { type: mime });
  }

  // Toggle bulk actions visibility
  $: showBulkActions = selectedItems.length > 0;
  let showDetailModal = false;
  let selectedItem = null;
  let isLoading = false;
  let showQRModal = false;
  let qrImageUrl = '';
  let currentQRResi = '';
  let searchTimeout;
  
  // Modal states
  let showStatusModal = false;
  let statusModalType = 'info';
  let statusModalTitle = '';
  let statusModalMessage = '';
  let statusModalLoading = false;
  let statusModalCallback = null;
  
  // Modal helper functions
  function showSuccessModal(title, message, callback = null) {
    statusModalType = 'success';
    statusModalTitle = title;
    statusModalMessage = message;
    statusModalCallback = callback;
    showStatusModal = true;
  }
  
  function showErrorModal(title, message) {
    statusModalType = 'error';
    statusModalTitle = title;
    statusModalMessage = message;
    statusModalCallback = null;
    showStatusModal = true;
  }
  
  function showConfirmModal(title, message, callback) {
    statusModalType = 'confirm';
    statusModalTitle = title;
    statusModalMessage = message;
    statusModalCallback = callback;
    showStatusModal = true;
  }
  
  function handleStatusModalConfirm() {
    if (statusModalCallback) {
      if (statusModalType === 'confirm') {
        statusModalCallback();
      } else {
        statusModalCallback();
      }
    }
    showStatusModal = false;
  }
  
  function handleStatusModalClose() {
    showStatusModal = false;
    statusModalCallback = null;
  }
  // Load approved mushaf requests on mount
  onMount(async () => {
    if (currentMode === 'scan-status') {
      requestCameraPermission();
    }
    
    // Load approved mushaf requests
    try {
      const response = await fetch('/admin/api/donatur-list', {
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      
      if (response.ok) {
        const data = await response.json();
        approvedMushafRequests = data.approved_mushaf_requests || [];
      }
    } catch (error) {
      // Failed to load mushaf requests
    }
  });
  
  async function bulkUpdateStatus() {
    
    if (!bulkStatus || selectedItems.length === 0) {
      showErrorModal('Validasi Gagal', 'Pilih status yang akan diupdate terlebih dahulu');
      return;
    }
    
    // Show confirmation modal
    showConfirmModal(
      'Konfirmasi Update Status', 
      `Apakah Anda yakin ingin mengupdate status ${selectedItems.length} pengiriman?`,
      () => {
        // This callback will execute the actual update
        performBulkUpdate();
      }
    );
  }
  
  async function performBulkUpdate() {
    isBulkProcessing = true;

    // Check CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    try {
      // Check if we have file upload or camera photos
      const hasDocumentation = bulkDokumentasi || (bulkCapturedPhotos && bulkCapturedPhotos.length > 0);

      if (hasDocumentation) {
        // Use FormData for file upload
        const formData = new FormData();
        
        // Try different approaches for array handling
        // Approach 1: Array notation
        selectedItems.forEach((id, index) => {
          formData.append(`pengiriman_ids[${index}]`, id);
        });
        
        // Approach 2: Multiple same-name entries (fallback)
        // selectedItems.forEach(id => {
        //   formData.append('pengiriman_ids[]', id);
        // });
        
        formData.append('status_id', bulkStatus);
        formData.append('catatan', bulkCatatan || 'Bulk update status');

        // Handle documentation: either file upload or camera photos
        if (bulkDokumentasi) {
          formData.append('dokumentasi', bulkDokumentasi);
          console.log('[BULK] Appended file upload:', bulkDokumentasi);
        } else if (bulkCapturedPhotos && bulkCapturedPhotos.length > 0) {
          // Convert camera photos to files and append as array
          console.log('[BULK] Converting', bulkCapturedPhotos.length, 'base64 photos to File objects');
          bulkCapturedPhotos.forEach((photoDataUrl, index) => {
            console.log('[BULK] Photo', index, 'dataUrl length:', photoDataUrl?.length);
            const photoFile = base64ToFile(photoDataUrl, `bulk-photo-${Date.now()}-${index}.jpg`);
            console.log('[BULK] Created File object:', photoFile.name, 'size:', photoFile.size, 'type:', photoFile.type);
            formData.append('dokumentasi[]', photoFile);
          });
          console.log('[BULK] All photos appended to FormData with key "dokumentasi[]"');
        }

        if (bulkLokasi) {
          formData.append('lokasi', bulkLokasi);
        }
        
        // Sending FormData with file upload
        
        // Use fetch for FormData
        const response = await fetch('/admin/pengiriman/bulk-status', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
          },
          body: formData
        });
        
        // Check response
        
        if (response.ok) {
          
          // Check if response is JSON or HTML
          const contentType = response.headers.get('content-type');
          if (contentType && contentType.includes('application/json')) {
            const result = await response.json();
          } else {
            // Handle HTML response (likely a redirect or success page)
            const htmlResponse = await response.text();
          }
          
          selectedItems = [];
          bulkStatus = '';
          bulkCatatan = '';
          bulkDokumentasi = null;
          bulkCapturedPhotos = [];
          useBulkCameraMode = false;
          bulkLokasi = '';
          showSuccessModal(
            'Update Berhasil!', 
            'Status pengiriman berhasil diupdate. Data akan dimuat ulang secara otomatis.',
            () => {
              setTimeout(() => {
                router.reload();
              }, 500);
            }
          );
          // Auto close modal and reload after 2 seconds
          setTimeout(() => {
            showStatusModal = false;
            router.reload();
          }, 2000);
        } else if (response.status === 422) {
          
          // Try alternative approach with pengiriman_ids[]
          const formData2 = new FormData();
          selectedItems.forEach(id => {
            formData2.append('pengiriman_ids[]', id);
          });
          formData2.append('status_id', bulkStatus);
          formData2.append('catatan', bulkCatatan || 'Bulk update status');

          // Handle documentation for retry: either file upload or camera photos
          if (bulkDokumentasi) {
            formData2.append('dokumentasi', bulkDokumentasi);
          } else if (bulkCapturedPhotos && bulkCapturedPhotos.length > 0) {
            bulkCapturedPhotos.forEach((photoDataUrl, index) => {
              const photoFile = base64ToFile(photoDataUrl, `bulk-photo-${Date.now()}-${index}.jpg`);
              formData2.append('dokumentasi[]', photoFile);
            });
          }

          if (bulkLokasi) {
            formData2.append('lokasi', bulkLokasi);
          }
          
          // Retrying with alternative format
          const response2 = await fetch('/admin/pengiriman/bulk-status', {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
              'Accept': 'application/json'
            },
            body: formData2
          });
          
          if (response2.ok) {
            
            const contentType2 = response2.headers.get('content-type');
            if (contentType2 && contentType2.includes('application/json')) {
              const result = await response2.json();
            } else {
              const htmlResponse = await response2.text();
            }
            
            selectedItems = [];
            bulkStatus = '';
            bulkCatatan = '';
            bulkDokumentasi = null;
            bulkCapturedPhotos = [];
            useBulkCameraMode = false;
            bulkLokasi = '';
            showSuccessModal(
              'Update Berhasil!',
              'Status pengiriman berhasil diupdate. Data akan dimuat ulang secara otomatis.',
              () => {
                setTimeout(() => {
                  router.reload();
                }, 500);
              }
            );
            // Auto close modal and reload after 2 seconds
            setTimeout(() => {
              showStatusModal = false;
              router.reload();
            }, 2000);
          } else {
            let errorMessage = 'Unknown error';
            try {
              const errorData = await response2.json();
              errorMessage = errorData.message || errorData.error || JSON.stringify(errorData);
            } catch (e) {
              const errorText = await response2.text();
              errorMessage = errorText || `HTTP ${response2.status}`;
            }
            showErrorModal('Update Gagal', 'Gagal update status (retry): ' + errorMessage);
          }
        } else {
          let errorMessage = 'Unknown error';
          try {
            const errorData = await response.json();
            errorMessage = errorData.message || errorData.error || JSON.stringify(errorData);
          } catch (e) {
            const errorText = await response.text();
            errorMessage = errorText || `HTTP ${response.status}`;
          }
          showErrorModal('Update Gagal', 'Gagal update status: ' + errorMessage);
        }
      } else {
        // Sending regular POST without file upload
        
        const postData = {
          pengiriman_ids: selectedItems,
          status_id: bulkStatus,
          catatan: bulkCatatan || 'Bulk update status',
          lokasi: bulkLokasi || null
        };
        
        // Use regular POST for data without file
        await router.post('/admin/pengiriman/bulk-status', postData, {
          onSuccess: (page) => {
            selectedItems = [];
            bulkStatus = '';
            bulkCatatan = '';
            bulkDokumentasi = null;
            bulkLokasi = '';
            showSuccessModal(
              'Update Berhasil!', 
              'Status pengiriman berhasil diupdate.',
              () => {
                // Optional callback
              }
            );
          },
          onError: (errors) => {
            let errorMessage = 'Unknown error';
            if (errors.message) {
              errorMessage = errors.message;
            } else if (typeof errors === 'string') {
              errorMessage = errors;
            } else if (errors.errors) {
              errorMessage = Object.values(errors.errors).flat().join(', ');
            }
            showErrorModal('Update Gagal', 'Gagal update status: ' + errorMessage);
          },
          onFinish: () => {
            // Request finished
          }
        });
      }
    } catch (error) {
      showErrorModal('Terjadi Kesalahan', 'Terjadi kesalahan: ' + error.message);
    } finally {
      isBulkProcessing = false;
    }
  }
  
  async function bulkSetAlamat() {
    if (!bulkMushafRequest || selectedItems.length === 0) {
      showErrorModal('Validasi Gagal', 'Pilih permintaan mushaf yang akan digunakan terlebih dahulu');
      return;
    }
    
    const selectedRequest = approvedMushafRequests.find(r => r.id === parseInt(bulkMushafRequest));
    if (!selectedRequest) {
      showErrorModal('Data Tidak Ditemukan', 'Data permintaan mushaf tidak ditemukan');
      return;
    }
    
    showConfirmModal(
      'Konfirmasi Set Alamat',
      `Set alamat untuk ${selectedItems.length} pengiriman menggunakan data dari permintaan mushaf ${selectedRequest.no_request}?`,
      () => {
        performBulkSetAlamat(selectedRequest);
      }
    );
  }
  
  async function performBulkSetAlamat(selectedRequest) {
    
    isBulkProcessing = true;
    
    try {
      await router.post('/admin/pengiriman/bulk-alamat', {
        pengiriman_ids: selectedItems,
        alamat_tujuan: selectedRequest.alamat_lengkap,
        nama_penerima: selectedRequest.nama_pengurus_1,
        no_hp_penerima: selectedRequest.whatsapp_pengurus_1,
        nama_lembaga: selectedRequest.nama_lembaga
      }, {
        onSuccess: () => {
          selectedItems = [];
          bulkMushafRequest = '';
          showSuccessModal(
            'Set Alamat Berhasil!', 
            `Alamat berhasil di-set menggunakan data dari ${selectedRequest.no_request}!`
          );
        },
        onError: () => {
          showErrorModal('Set Alamat Gagal', 'Gagal melakukan set alamat. Silakan coba lagi.');
        }
      });
    } finally {
      isBulkProcessing = false;
    }
  }
  
  // Get mode from URL or default to 'list'
  let currentMode = 'list';
  $: {
    const urlParams = new URLSearchParams(window.location.search);
    currentMode = urlParams.get('mode') || 'list';
  }
  
  let scanner;
  let scanning = false;
  let loading = false;
  let error = null;
  let successData = null;
  let isInitializing = false;
  let permissionGranted = false;
  
  // Form data
  let searchQuery = '';
  let filteredPengiriman = [];
  
  // Filter pengiriman based on search query
  $: {
    if (!pengiriman) {
      filteredPengiriman = [];
    } else if (searchQuery) {
      filteredPengiriman = pengiriman.filter(p => 
        p.nomor_resi?.toLowerCase().includes(searchQuery.toLowerCase()) ||
        p.nama_penerima?.toLowerCase().includes(searchQuery.toLowerCase()) ||
        p.alamat_penerima?.toLowerCase().includes(searchQuery.toLowerCase())
      );
    } else {
      filteredPengiriman = pengiriman;
    }
  }
  
  // Search and filter handlers
  function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      applyFilters();
    }, 500); // Debounce 500ms
  }
  
  function handleFilter() {
    applyFilters();
  }
  
  function applyFilters() {
    const params = new URLSearchParams();
    
    if (filters.search && filters.search.trim()) {
      params.set('search', filters.search.trim());
    }
    if (filters.status) {
      params.set('status', filters.status);
    }
    if (filters.jenis_quran) {
      params.set('jenis_quran', filters.jenis_quran);
    }
    if (filters.alamat_status) {
      params.set('alamat_status', filters.alamat_status);
    }
    if (startDate) {
      params.set('tanggal_mulai', startDate);
    }
    if (endDate) {
      params.set('tanggal_akhir', endDate);
    }
    if (numberFrom) {
      params.set('number_from', numberFrom);
    }
    if (numberTo) {
      params.set('number_to', numberTo);
    }

    const url = params.toString() ? `/admin/pengiriman?${params.toString()}` : '/admin/pengiriman';
    router.visit(url, { preserveState: true, preserveScroll: true });
  }

  function clearFilters() {
    filters.search = '';
    numberFrom = '';
    numberTo = '';
    filters.status = '';
    filters.jenis_quran = '';
    filters.alamat_status = '';
    startDate = '';
    endDate = '';
    router.visit('/admin/pengiriman', { preserveState: true, preserveScroll: true });
  }
  
  // Toggle all checkbox
  function toggleAll(event) {
    if (event.target.checked) {
      selectedItems = pengiriman.data.map(item => item.id);
    } else {
      selectedItems = [];
    }
  }
  
  // Toggle individual checkbox
  function toggleItem(itemId) {
    if (selectedItems.includes(itemId)) {
      selectedItems = selectedItems.filter(id => id !== itemId);
    } else {
      selectedItems = [...selectedItems, itemId];
    }
  }
  
  // Handle View - Show detail modal
  function handleView(item) {
    selectedItem = item;
    showDetailModal = true;
  }
  
  // Handle Edit - Navigate to edit page
  function handleEdit(id) {
    router.visit(`/admin/pengiriman/${id}/edit`);
  }
  
  // QR Scanner functions
  function initializeScanner() {
    if (isInitializing || scanning) return;
    
    try {
      isInitializing = true;
      scanner = new Html5QrcodeScanner(
        "qr-reader",
        {
          fps: 10,
          qrbox: { width: 250, height: 250 },
          rememberLastUsedCamera: true,
          videoConstraints: {
            facingMode: "environment",
            width: { ideal: 1280, min: 640 },
            height: { ideal: 720, min: 480 }
          }
        },
        false
      );
      
      scanner.render(onScanSuccess, onScanFailure);
      scanning = true;
      error = null;
      isInitializing = false;
    } catch (err) {
      error = `Gagal menginisialisasi scanner: ${err.message}`;
      scanning = false;
      isInitializing = false;
    }
  }
  
  function onScanSuccess(decodedText, decodedResult) {
    scanning = false;
    cleanupScanner();
    
    try {
      const data = JSON.parse(decodedText);
      if (data.type === 'ekspedisi_quran' && data.no_resi) {
        router.visit(`/admin/pengiriman/${data.no_resi}/update-status`);
      } else {
        error = 'QR Code tidak valid';
      }
    } catch (err) {
      error = 'Format QR Code tidak valid';
    }
  }
  
  function onScanFailure(error) {
    // QR scan attempt
  }
  
  function cleanupScanner() {
    if (scanner) {
      scanner.clear();
      scanner = null;
    }
    scanning = false;
    isInitializing = false;
  }
  
  function requestCameraPermission() {
    navigator.mediaDevices.getUserMedia({ video: true })
      .then(() => {
        permissionGranted = true;
        if (currentMode === 'scan-status') {
          initializeScanner();
        }
      })
      .catch((err) => {
        error = 'Akses kamera ditolak: ' + err.message;
      });
  }
  
  // QR Code functions - Use dedicated Generate QR page
  
  // Lifecycle hooks
  // onMount sudah dipindah ke atas untuk load mushaf requests
  
  onDestroy(() => {
    cleanupScanner();
  });
  
  function getStatusBadgeClass(status) {
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
  
  function formatDateTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('id-ID');
  }
  
  // Helper function to build pagination URL
  function buildPaginationUrl(page) {
    const params = new URLSearchParams();
    params.set('page', page);
    
    // Preserve current filters
    if (filters.search) params.set('search', filters.search);
    if (filters.status) params.set('status', filters.status);
    if (filters.jenis_quran) params.set('jenis_quran', filters.jenis_quran);
    if (filters.alamat_status) params.set('alamat_status', filters.alamat_status);
    if (startDate) params.set('tanggal_mulai', startDate);
    if (endDate) params.set('tanggal_akhir', endDate);
    
    return `/admin/pengiriman?${params.toString()}`;
  }
  
  // Get QR status
  function getQRStatus(item) {
    if (item.qr_code_data) {
      return { hasQR: true, text: 'Ada QR', class: 'text-green-600' };
    }
    return { hasQR: false, text: 'Belum ada QR', class: 'text-red-600' };
  }
  
  // View QR Code
  function viewQR(item) {
    if (item.qr_code_data) {
      currentQRResi = item.no_resi;
      qrImageUrl = `/admin/qr/download/${item.id}`;
      showQRModal = true;
    } else {
      showErrorModal('QR Code Tidak Tersedia', 'QR Code belum di-generate untuk pengiriman ini. Silakan generate QR Code terlebih dahulu.');
    }
  }
  
</script>

<svelte:head>
  <title>Pengiriman - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <div class="mb-6 md:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-4 sm:space-y-0">
      <div>
        <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">Manajemen Pengiriman</h2>
        <p class="text-sm md:text-base text-gray-600">Kelola pengiriman Al-Quran wakaf</p>
      </div>
      
    </div>
  </div>
  
  <TabNavigation {currentMode} />
  
  <!-- Statistics Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600">
          <HeroIcon name="cube" class="w-5 h-5 md:w-6 md:h-6" />
        </div>
        <div class="ml-3 md:ml-4 min-w-0 flex-1">
          <p class="text-xs md:text-sm font-medium text-gray-600 truncate">Pemesanan</p>
          <p class="text-lg md:text-2xl font-bold text-gray-900">{stats.pemesanan || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 md:w-12 md:h-12 bg-yellow-100 rounded-lg flex items-center justify-center text-yellow-600">
          <HeroIcon name="inbox-stack" class="w-5 h-5 md:w-6 md:h-6" />
        </div>
        <div class="ml-3 md:ml-4 min-w-0 flex-1">
          <p class="text-xs md:text-sm font-medium text-gray-600 truncate">Proses Packing</p>
          <p class="text-lg md:text-2xl font-bold text-gray-900">{stats.packing || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 md:w-12 md:h-12 bg-orange-100 rounded-lg flex items-center justify-center text-orange-600">
          <HeroIcon name="truck" class="w-5 h-5 md:w-6 md:h-6" />
        </div>
        <div class="ml-3 md:ml-4 min-w-0 flex-1">
          <p class="text-xs md:text-sm font-medium text-gray-600 truncate">Dalam Pengiriman</p>
          <p class="text-lg md:text-2xl font-bold text-gray-900">{stats.pengiriman || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 md:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 md:w-12 md:h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-600">
          <HeroIcon name="check-circle" class="w-5 h-5 md:w-6 md:h-6" />
        </div>
        <div class="ml-3 md:ml-4 min-w-0 flex-1">
          <p class="text-xs md:text-sm font-medium text-gray-600 truncate">Diterima</p>
          <p class="text-lg md:text-2xl font-bold text-gray-900">{stats.diterima || 0}</p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Search and Filters -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
    <div class="px-6 py-4">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter & Pencarian</h3>
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
        <!-- Search - Takes 3/5 of width -->
        <div class="md:col-span-3">
          <label for="search_input" class="block text-sm font-medium text-gray-700 mb-1">Cari Pengiriman</label>
          <div class="relative">
            <input
              id="search_input"
              type="text"
              bind:value={filters.search}
              on:input={() => handleSearch()}
              placeholder="No resi, nama donatur, atau penerima..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
            <HeroIcon name="magnifying-glass" class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
          </div>
        </div>

        <!-- Number From Filter - Takes 1/5 of width -->
        <div class="md:col-span-1">
          <label for="number_from" class="block text-sm font-medium text-gray-700 mb-1">No. Dari</label>
          <input
            id="number_from"
            type="number"
            bind:value={numberFrom}
            on:change={handleFilter}
            min="1"
            placeholder="1"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <!-- Number To Filter - Takes 1/5 of width -->
        <div class="md:col-span-1">
          <label for="number_to" class="block text-sm font-medium text-gray-700 mb-1">No. Sampai</label>
          <input
            id="number_to"
            type="number"
            bind:value={numberTo}
            on:change={handleFilter}
            min="1"
            placeholder="100"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Status Filter -->
        <div>
          <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select
            id="status_filter"
            bind:value={filters.status}
            on:change={() => handleFilter()}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Semua Status</option>
            {#each statusList as status}
              <option value={status.id}>{status.nama}</option>
            {/each}
          </select>
        </div>
        
        <!-- Jenis Quran Filter -->
        <div>
          <label for="jenis_quran_filter" class="block text-sm font-medium text-gray-700 mb-1">Jenis Al-Quran</label>
          <select
            id="jenis_quran_filter"
            bind:value={filters.jenis_quran}
            on:change={() => handleFilter()}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Semua Jenis</option>
            {#each jenisQuranList as jenis}
              <option value={jenis.id}>{jenis.nama_jenis}</option>
            {/each}
          </select>
        </div>
        
        <!-- Alamat Status Filter -->
        <div>
          <label for="alamat_status_filter" class="block text-sm font-medium text-gray-700 mb-1">Status Alamat</label>
          <select
            id="alamat_status_filter"
            bind:value={filters.alamat_status}
            on:change={() => handleFilter()}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Semua Status</option>
            <option value="sudah_diset">Sudah Diset</option>
            <option value="belum_diset">Belum Diset</option>
          </select>
        </div>
        
        <!-- Date Range Filter -->
        <div>
          <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
          <input
            id="start_date"
            type="date"
            bind:value={startDate}
            on:change={handleFilter}
            max={currentDate}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
        
        <div>
          <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
          <input
            id="end_date"
            type="date"
            bind:value={endDate}
            on:change={handleFilter}
            max={currentDate}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <!-- Clear Filters -->
      {#if filters.search || filters.status || filters.jenis_quran || startDate || endDate || numberFrom || numberTo}
        <div class="mt-4">
          <button
            on:click={() => clearFilters()}
            class="px-4 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors flex items-center gap-1"
          >
            <HeroIcon name="x-mark" class="w-4 h-4" />
            Hapus Filter
          </button>
          
          <!-- Active Filters -->
          <div class="mt-3 flex flex-wrap gap-2">
            {#if filters.search}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                <span>Pencarian: {filters.search}</span>
                <button 
                  on:click={() => { filters.search = ''; handleFilter(); }}
                  class="ml-2 text-blue-600 hover:text-blue-800"
                >
                  <HeroIcon name="x-mark" class="w-3 h-3" />
                </button>
              </div>
            {/if}
            
            {#if filters.status}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800">
                <span>Status: {statusList.find(s => s.id == filters.status)?.nama}</span>
                <button 
                  on:click={() => { filters.status = ''; handleFilter(); }}
                  class="ml-2 text-green-600 hover:text-green-800"
                >
                  <HeroIcon name="x-mark" class="w-3 h-3" />
                </button>
              </div>
            {/if}
            
            {#if filters.jenis_quran}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
                <span>Jenis: {jenisQuranList.find(j => j.id == filters.jenis_quran)?.nama_jenis}</span>
                <button 
                  on:click={() => { filters.jenis_quran = ''; handleFilter(); }}
                  class="ml-2 text-purple-600 hover:text-purple-800"
                >
                  <HeroIcon name="x-mark" class="w-3 h-3" />
                </button>
              </div>
            {/if}
            
            {#if startDate}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-orange-100 text-orange-800">
                <span>Dari: {startDate}</span>
                <button 
                  on:click={() => { startDate = ''; handleFilter(); }}
                  class="ml-2 text-orange-600 hover:text-orange-800"
                >
                  <HeroIcon name="x-mark" class="w-3 h-3" />
                </button>
              </div>
            {/if}
            
            {#if endDate}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-red-100 text-red-800">
                <span>Sampai: {endDate}</span>
                <button 
                  on:click={() => { endDate = ''; handleFilter(); }}
                  class="ml-2 text-red-600 hover:text-red-800"
                >
                  <HeroIcon name="x-mark" class="w-3 h-3" />
                </button>
              </div>
            {/if}
          </div>
        </div>
      {/if}
    </div>
  </div>
  
  <!-- Bulk Actions Panel -->
  {#if showBulkActions}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <HeroIcon name="clipboard-document-check" class="w-5 h-5 text-blue-600" />
          <span class="font-semibold text-blue-900">{selectedItems.length} item terpilih</span>
        </div>
        <button
          on:click={() => selectedItems = []}
          class="text-blue-600 hover:text-blue-800 text-sm"
        >
          Batal Pilih
        </button>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Bulk Update Status -->
        <div class="bg-white rounded-lg p-4 border">
          <div class="flex items-center gap-2 font-medium text-gray-900 mb-3">
            <HeroIcon name="cube" class="w-5 h-5 text-blue-600" />
            <h4>Update Status Massal</h4>
          </div>
          <div class="space-y-3">
            <div>
              <label for="bulk_status_select" class="block text-sm font-medium text-gray-700 mb-1">Status Baru</label>
              <select
                id="bulk_status_select"
                bind:value={bulkStatus}
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
              >
                <option value="">Pilih Status...</option>
                {#each statusList as status}
                  <option value={status.id}>{status.nama}</option>
                {/each}
              </select>
            </div>
            <div>
              <label for="bulk_catatan_input" class="block text-sm font-medium text-gray-700 mb-1">Catatan (Opsional)</label>
              <input
                id="bulk_catatan_input"
                type="text"
                bind:value={bulkCatatan}
                placeholder="Catatan untuk perubahan status..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
              />
            </div>
            <div>
              <label for="bulk_dokumentasi_input" class="block text-sm font-medium text-gray-700 mb-1">Dokumentasi (Foto)</label>

              <!-- Camera mode toggle button -->
              <div class="mb-2">
                <button
                  type="button"
                  on:click={toggleBulkCameraMode}
                  class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md transition-colors gap-1 {useBulkCameraMode ? 'bg-blue-100 text-blue-700 hover:bg-blue-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}"
                >
                  <HeroIcon name="camera" class="w-4 h-4" />
                  <span>{useBulkCameraMode ? 'Gunakan Upload' : 'Gunakan Kamera'}</span>
                </button>
              </div>

              {#if useBulkCameraMode}
                <!-- Camera capture mode -->
                <CameraCapture
                  onCapture={handleBulkCameraCapture}
                  maxPhotos={10}
                />
                {#if bulkCapturedPhotos.length > 0}
                  <p class="text-xs text-green-600 mt-1 flex items-center gap-1">
                    <HeroIcon name="check-circle" class="w-3.5 h-3.5" />
                    <span>{bulkCapturedPhotos.length} foto tertangkap</span>
                  </p>
                {/if}
              {:else}
                <!-- File upload mode -->
                <input
                  id="bulk_dokumentasi_input"
                  type="file"
                  accept="image/*"
                  on:change={(e) => bulkDokumentasi = e.target.files[0]}
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                />
                <p class="text-xs text-gray-500 mt-1">Upload foto dokumentasi untuk update status</p>
              {/if}
            </div>
            <div>
              <label for="bulk_lokasi_input" class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
              <input
                id="bulk_lokasi_input"
                type="text"
                bind:value={bulkLokasi}
                placeholder="Masukkan lokasi saat ini..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
              />
              <p class="text-xs text-gray-500 mt-1">Lokasi di mana update status dilakukan</p>
            </div>
            {#if canBulkUpdate}
            <button
              on:click={bulkUpdateStatus}
              disabled={!bulkStatus || isBulkProcessing || selectedItems.length === 0}
              class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5"
            >
              {#if isBulkProcessing}
                <div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                Updating...
              {:else}
                <HeroIcon name="cube" class="w-4 h-4" />
                <span>Update Status ({selectedItems.length} item)</span>
              {/if}
            </button>
            {/if}
          </div>
        </div>
        
        <!-- Bulk Set Alamat dari Mushaf Request -->
        <div class="bg-white rounded-lg p-4 border">
          <div class="flex items-center gap-2 font-medium text-gray-900 mb-3">
            <HeroIcon name="map-pin" class="w-5 h-5 text-green-600" />
            <h4>Set Alamat dari Permintaan Mushaf</h4>
          </div>
          <div class="space-y-3">
            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
              <div class="flex">
                <HeroIcon name="information-circle" class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" />
                <div>
                  <p class="text-sm font-medium text-green-800">Alamat dari Mushaf Request yang Disetujui</p>
                  <p class="text-xs text-green-700 mt-1">Alamat, nama penerima, dan nomor HP akan diambil dari permintaan mushaf yang telah disetujui.</p>
                </div>
              </div>
            </div>
            <div>
              <label for="bulk_mushaf_request_select" class="block text-sm font-medium text-gray-700 mb-1">Permintaan Mushaf yang Disetujui</label>
              <select
                id="bulk_mushaf_request_select"
                bind:value={bulkMushafRequest}
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm"
              >
                <option value="">Pilih permintaan mushaf...</option>
                {#each approvedMushafRequests as request}
                  <option value={request.id}>{request.no_request} - {request.nama_lembaga}</option>
                {/each}
              </select>
              {#if approvedMushafRequests.length === 0}
                <p class="text-xs text-gray-500 mt-1">Tidak ada permintaan mushaf yang disetujui</p>
              {/if}
            </div>
            
            {#if bulkMushafRequest}
              {@const selectedRequest = approvedMushafRequests.find(r => r.id === parseInt(bulkMushafRequest))}
              {#if selectedRequest}
                <div class="bg-gray-50 rounded-lg p-3 text-xs">
                  <p class="font-medium text-gray-700 mb-1">Preview Data:</p>
                  <p class="text-gray-600"><strong>Alamat:</strong> {selectedRequest.alamat_lengkap}</p>
                  <p class="text-gray-600"><strong>Penerima:</strong> {selectedRequest.nama_pengurus_1}</p>
                  <p class="text-gray-600"><strong>No. HP:</strong> {selectedRequest.whatsapp_pengurus_1}</p>
                </div>
              {/if}
            {/if}
            
            {#if canUpdate}
            <button
              on:click={bulkSetAlamat}
              disabled={!bulkMushafRequest || isBulkProcessing}
              class="w-full px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1.5"
            >
              {#if isBulkProcessing}
                <div class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                Processing...
              {:else}
                <HeroIcon name="map-pin" class="w-4 h-4" />
                <span>Set Alamat ({selectedItems.length} item)</span>
              {/if}
            </button>
            {/if}
          </div>
        </div>
      </div>
    </div>
  {/if}
  
  <!-- Pengiriman Table -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900">Daftar Pengiriman</h3>
      <p class="text-sm text-gray-500">Kelola pengiriman Al-Quran ke seluruh Indonesia</p>
    </div>
    
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
              <input
                type="checkbox"
                on:change={toggleAll}
                checked={selectedItems.length === pengiriman?.data?.length && pengiriman?.data?.length > 0}
                class="rounded border-gray-300 text-red-600 focus:ring-red-500"
              />
            </th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">No.</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Resi</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wakif</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Al-Qur'an</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Wakaf</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alamat Penerima</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          {#if pengiriman && pengiriman.data && pengiriman.data.length > 0}
            {#each pengiriman.data as item, index}
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <input
                    type="checkbox"
                    checked={selectedItems.includes(item.id)}
                    on:change={() => toggleItem(item.id)}
                    class="rounded border-gray-300 text-red-600 focus:ring-red-500"
                  />
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-center">
                  <div class="text-sm font-medium text-gray-700">
                    {(pengiriman.from || 0) + index}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{item.no_resi || 'Belum ada resi'}</div>
                  <div class="text-sm {getQRStatus(item).class}">
                    {#if getQRStatus(item).hasQR}
                      <span class="flex items-center">
                        <HeroIcon name="check" class="w-3 h-3 mr-1" />
                        QR: Ada
                      </span>
                    {:else}
                      <span class="flex items-center">
                        <HeroIcon name="x-mark" class="w-3 h-3 mr-1" />
                        QR: Belum ada
                      </span>
                    {/if}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                      <span class="text-xs font-medium text-indigo-800">{(item.wakaf_item?.wakif_name || item.donatur?.nama_donatur)?.charAt(0) || 'W'}</span>
                    </div>
                    <div>
                      <div class="text-sm font-medium text-gray-900">{item.wakaf_item?.wakif_name || item.donatur?.nama_donatur || 'N/A'}</div>
                      <div class="text-sm text-gray-500">{item.wakaf_item?.relationship_to_donatur || 'Wakaf'}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                    {item.jenis_quran?.nama_jenis || 'Unknown'}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {getStatusBadgeClass(item.status)}">
                    {item.status?.nama || 'Unknown'}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{formatDate(item.tanggal_wakaf)}</div>
                  <div class="text-xs text-gray-500">Dibuat: {formatDate(item.created_at)}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  {#if item.alamat_tujuan && item.nama_penerima}
                    <div class="flex items-center" title="Alamat penerima sudah diatur">
                      <div class="flex-shrink-0 w-2 h-2 bg-green-400 rounded-full mr-2"></div>
                      <div>
                        <div class="text-sm font-medium text-green-900">Sudah Diset</div>
                        <div class="text-xs text-green-600">alamat lengkap</div>
                      </div>
                    </div>
                  {:else}
                    <div class="flex items-center" title="Alamat penerima belum diatur, perlu melengkapi data alamat dan nama penerima">
                      <div class="flex-shrink-0 w-2 h-2 bg-red-400 rounded-full mr-2"></div>
                      <div>
                        <div class="text-sm font-medium text-red-900">Belum Diset</div>
                        <div class="text-xs text-red-600">perlu diatur</div>
                      </div>
                    </div>
                  {/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-1">
                    <button
                      on:click={() => handleView(item)}
                      class="text-indigo-600 hover:text-indigo-900 p-1 hover:bg-indigo-50 rounded transition-colors"
                      title="Lihat Detail"
                    >
                      <HeroIcon name="eye" class="w-4 h-4" />
                    </button>
                    {#if canUpdate}
                    <button
                      on:click={() => handleEdit(item.id)}
                      class="text-yellow-600 hover:text-yellow-900 p-1 hover:bg-yellow-50 rounded transition-colors"
                      title="Edit"
                    >
                      <HeroIcon name="pencil-square" class="w-4 h-4" />
                    </button>
                    {/if}
                    
                    <a
                      href={`/admin/pengiriman/${item.id}/update-status`}
                      class="text-blue-600 hover:text-blue-900 p-1 hover:bg-blue-50 rounded transition-colors inline-flex items-center"
                      title="Update Status"
                    >
                      <HeroIcon name="arrow-path" class="w-4 h-4" />
                    </a>
                  </div>
                </td>
              </tr>
            {/each}
          {:else}
            <tr>
              <td colspan="7" class="px-6 py-12 text-center">
                <div class="text-gray-500">
                  <HeroIcon name="cube" class="w-12 h-12 mx-auto mb-4 text-gray-300" />
                  <p class="text-lg font-medium">Tidak ada pengiriman ditemukan</p>
                  <p class="text-sm">Belum ada data pengiriman yang tersedia</p>
                </div>
              </td>
            </tr>
          {/if}
        </tbody>
      </table>
    </div>
    
    <!-- Pagination -->
    {#if pengiriman && pengiriman.last_page > 1}
      <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div class="text-sm text-gray-500 text-center sm:text-left">
            Menampilkan {pengiriman.from} - {pengiriman.to} dari {pengiriman.total} hasil
          </div>
          <div class="flex justify-center sm:justify-end items-center space-x-1">
            <!-- Previous -->
            {#if pengiriman.prev_page_url}
              <button
                on:click={() => router.visit(pengiriman.prev_page_url, { preserveScroll: true })}
                class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center gap-1"
              >
                <HeroIcon name="chevron-left" class="w-4 h-4" />
                <span class="hidden sm:inline">Sebelumnya</span>
                <span class="sm:hidden">Prev</span>
              </button>
            {:else}
              <span class="px-3 py-2 text-sm text-gray-300 cursor-not-allowed rounded-md flex items-center gap-1">
                <HeroIcon name="chevron-left" class="w-4 h-4" />
                <span class="hidden sm:inline">Sebelumnya</span>
                <span class="sm:hidden">Prev</span>
              </span>
            {/if}
            
            <!-- Page Numbers -->
            {#if pengiriman.last_page <= 7}
              <!-- Show all pages if 7 or less -->
              {#each Array(pengiriman.last_page) as _, i}
                {@const pageNum = i + 1}
                <button
                  on:click={() => router.visit(buildPaginationUrl(pageNum), { preserveScroll: true })}
                  class="px-3 py-2 text-sm rounded-md {pageNum === pengiriman.current_page ? 'bg-red-600 text-white font-medium' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}"
                >
                  {pageNum}
                </button>
              {/each}
            {:else}
              <!-- Show smart pagination for more than 7 pages -->
              
              <!-- First page -->
              <button
                on:click={() => router.visit(buildPaginationUrl(1), { preserveScroll: true })}
                class="px-3 py-2 text-sm rounded-md {1 === pengiriman.current_page ? 'bg-red-600 text-white font-medium' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}"
              >
                1
              </button>
              
              <!-- Dots if current page is far from start -->
              {#if pengiriman.current_page > 4}
                <span class="px-2 py-2 text-sm text-gray-400">...</span>
              {/if}
              
              <!-- Pages around current page -->
              {@const startPage = Math.max(2, pengiriman.current_page - 1)}
              {@const endPage = Math.min(pengiriman.last_page - 1, pengiriman.current_page + 1)}
              {#each Array(endPage - startPage + 1).fill().map((_, i) => startPage + i) as pageNum}
                {#if pageNum !== 1 && pageNum !== pengiriman.last_page && pageNum >= 2 && pageNum <= pengiriman.last_page - 1}
                  <button
                    on:click={() => router.visit(buildPaginationUrl(pageNum), { preserveScroll: true })}
                    class="px-3 py-2 text-sm rounded-md {pageNum === pengiriman.current_page ? 'bg-red-600 text-white font-medium' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}"
                  >
                    {pageNum}
                  </button>
                {/if}
              {/each}
              
              <!-- Dots if current page is far from end -->
              {#if pengiriman.current_page < pengiriman.last_page - 3}
                <span class="px-2 py-2 text-sm text-gray-400">...</span>
              {/if}
              
              <!-- Last page -->
              {#if pengiriman.last_page > 1}
                <button
                  on:click={() => router.visit(buildPaginationUrl(pengiriman.last_page), { preserveScroll: true })}
                  class="px-3 py-2 text-sm rounded-md {pengiriman.last_page === pengiriman.current_page ? 'bg-red-600 text-white font-medium' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}"
                >
                  {pengiriman.last_page}
                </button>
              {/if}
            {/if}
            
            <!-- Next -->
            {#if pengiriman.next_page_url}
              <button
                on:click={() => router.visit(pengiriman.next_page_url, { preserveScroll: true })}
                class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center gap-1"
              >
                <span class="hidden sm:inline">Selanjutnya</span>
                <span class="sm:hidden">Next</span>
                <HeroIcon name="chevron-right" class="w-4 h-4" />
              </button>
            {:else}
              <span class="px-3 py-2 text-sm text-gray-300 cursor-not-allowed rounded-md flex items-center gap-1">
                <span class="hidden sm:inline">Selanjutnya</span>
                <span class="sm:hidden">Next</span>
                <HeroIcon name="chevron-right" class="w-4 h-4" />
              </span>
            {/if}
          </div>
        </div>
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

<!-- Flash Messages -->
<FlashMessage />

<!-- Detail Modal -->
{#if showDetailModal && selectedItem}
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" on:click|self={() => showDetailModal = false} on:keydown={(e) => e.key === 'Escape' && (showDetailModal = false)} role="button" tabindex="0" aria-label="Close modal">
    <div class="bg-white rounded-2xl p-4 sm:p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto" role="dialog">
      <div class="flex justify-between items-center mb-4 sm:mb-6">
        <h3 class="text-lg sm:text-xl font-bold text-gray-900">Detail Pengiriman</h3>
        <button on:click={() => showDetailModal = false} class="text-gray-400 hover:text-gray-600 p-1">
          <HeroIcon name="x-mark" class="w-5 h-5 sm:w-6 sm:h-6" />
        </button>
      </div>
      
      <div class="space-y-4 sm:space-y-6">
        <!-- Header Info -->
        <div class="bg-gradient-to-r from-[#eb3434] to-red-600 rounded-xl p-4 sm:p-6 text-white">
          <div class="flex items-center gap-2 mb-2">
            <HeroIcon name="cube" class="w-5 h-5 text-white" />
            <h4 class="text-base sm:text-lg font-semibold">{selectedItem.no_resi || 'Belum ada resi'}</h4>
          </div>
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-2 sm:space-y-0">
            <div>
              <p class="text-red-100 text-sm">Donatur: {selectedItem.donatur?.nama_donatur || 'N/A'}</p>
              <p class="text-red-100 text-xs sm:text-sm">{selectedItem.jenis_quran?.nama_jenis || 'Unknown'} • {selectedItem.jumlah_quran || 1} buah</p>
            </div>
            <div class="text-left sm:text-right">
              <span class="inline-flex px-3 py-1 rounded-full text-xs font-medium bg-white/20 border border-white/30">
                {selectedItem.status?.nama || 'Unknown'}
              </span>
            </div>
          </div>
        </div>
        
        <!-- Detail Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
          <!-- Wakif Info -->
          <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
            <h5 class="font-semibold text-gray-900 mb-3 text-sm sm:text-base">Informasi Wakif</h5>
            <div class="space-y-2 text-xs sm:text-sm">
              <div class="flex flex-col sm:flex-row sm:justify-between">
                <span class="text-gray-600 font-medium">Nama:</span>
                <span class="font-medium break-words">{selectedItem.donatur?.nama_donatur || 'N/A'}</span>
              </div>
              <div class="flex flex-col sm:flex-row sm:justify-between">
                <span class="text-gray-600 font-medium">Kode:</span>
                <span class="font-medium">{selectedItem.donatur?.kode_donatur || 'N/A'}</span>
              </div>
              <div class="flex flex-col sm:flex-row sm:justify-between">
                <span class="text-gray-600 font-medium">No. HP:</span>
                <span class="font-medium">{selectedItem.donatur?.no_hp || 'N/A'}</span>
              </div>
            </div>
          </div>
          
          <!-- QR Status -->
          <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
            <h5 class="font-semibold text-gray-900 mb-3 text-sm sm:text-base">QR Code Status</h5>
            <div class="space-y-2 text-xs sm:text-sm">
              <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                <span class="text-gray-600 font-medium">Status QR:</span>
                <span class="font-medium {getQRStatus(selectedItem).class}">
                  {getQRStatus(selectedItem).text}
                </span>
              </div>
              {#if selectedItem.qr_code_data}
                <div class="mt-3">
                  <p class="text-xs text-gray-500">QR Code sudah tersedia. Silakan gunakan halaman Generate QR untuk mengelola QR Code.</p>
                </div>
              {:else}
                <div class="mt-3">
                  <p class="text-xs text-gray-500">QR Code belum dibuat. Gunakan halaman Generate QR untuk membuat QR Code.</p>
                </div>
              {/if}
            </div>
          </div>
        </div>
        
        <!-- Delivery Info -->
        {#if selectedItem.alamat_tujuan}
          <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
            <h5 class="font-semibold text-gray-900 mb-3 text-sm sm:text-base">Informasi Pengiriman</h5>
            <div class="space-y-2 text-xs sm:text-sm">
              <div>
                <span class="text-gray-600 font-medium">Alamat Tujuan:</span>
                <p class="font-medium mt-1 break-words">{selectedItem.alamat_tujuan}</p>
              </div>
              {#if selectedItem.nama_penerima}
                <div class="flex flex-col sm:flex-row sm:justify-between">
                  <span class="text-gray-600 font-medium">Penerima:</span>
                  <span class="font-medium break-words">{selectedItem.nama_penerima}</span>
                </div>
              {/if}
              {#if selectedItem.no_hp_penerima}
                <div class="flex flex-col sm:flex-row sm:justify-between">
                  <span class="text-gray-600 font-medium">No. HP Penerima:</span>
                  <span class="font-medium">{selectedItem.no_hp_penerima}</span>
                </div>
              {/if}
            </div>
          </div>
        {/if}
        
        <!-- Timestamps -->
        <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
          <h5 class="font-semibold text-gray-900 mb-3 text-sm sm:text-base">Tanggal</h5>
          <div class="space-y-2 text-xs sm:text-sm">
            <div class="flex flex-col sm:flex-row sm:justify-between">
              <span class="text-gray-600 font-medium">Tanggal Wakaf:</span>
              <span class="font-medium">{formatDate(selectedItem.tanggal_wakaf)}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:justify-between">
              <span class="text-gray-600 font-medium">Dibuat:</span>
              <span class="font-medium">{formatDateTime(selectedItem.created_at)}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:justify-between">
              <span class="text-gray-600 font-medium">Diupdate:</span>
              <span class="font-medium">{formatDateTime(selectedItem.updated_at)}</span>
            </div>
          </div>
        </div>
        
        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t">
          <button
            on:click={() => handleEdit(selectedItem.id)}
            class="flex-1 px-4 py-2.5 bg-[#eb3434] text-white font-medium rounded-lg hover:bg-red-600 transition-colors text-sm"
          >
            Edit Pengiriman
          </button>
          <button
            on:click={() => showDetailModal = false}
            class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors text-sm"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- QR Modal -->
{#if showQRModal && qrImageUrl}
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" on:click|self={() => showQRModal = false} on:keydown={(e) => e.key === 'Escape' && (showQRModal = false)} role="button" tabindex="0" aria-label="Close modal">
    <div class="bg-white rounded-2xl p-4 sm:p-6 max-w-md w-full mx-4" role="dialog">
      <div class="text-center">
        <h3 class="text-lg sm:text-xl font-bold text-gray-900 mb-4">QR Code - {currentQRResi}</h3>
        
        <div class="mb-6">
          <img 
            src={qrImageUrl} 
            alt="QR Code" 
            class="mx-auto rounded-lg shadow-lg max-w-[200px] sm:max-w-[250px] w-full"
            on:error={() => {
              showErrorModal('Error Loading QR', 'QR Code image tidak dapat dimuat');
              showQRModal = false;
            }}
          />
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3">
          <a
            href={qrImageUrl}
            download="qr-{currentQRResi}.png"
            class="flex-1 px-4 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors text-center text-sm flex items-center justify-center gap-1.5"
          >
            <HeroIcon name="arrow-down-tray" class="w-4 h-4" />
            <span class="hidden sm:inline">Download</span>
            <span class="sm:hidden">Download</span>
          </a>
          <button
            on:click={() => window.print()}
            class="flex-1 px-4 py-2.5 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors text-sm flex items-center justify-center gap-1.5"
          >
            <HeroIcon name="printer" class="w-4 h-4" />
            <span>Print</span>
          </button>
        </div>
        
        <button
          on:click={() => showQRModal = false}
          class="w-full mt-3 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors text-sm"
        >
          Tutup
        </button>
        
        <div class="mt-4 text-xs text-gray-500">
          <p>Scan QR code ini untuk update status pengiriman</p>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Status Modal -->
<StatusModal 
  bind:show={showStatusModal}
  type={statusModalType}
  title={statusModalTitle}
  message={statusModalMessage}
  loading={statusModalLoading}
  showCancel={statusModalType === 'confirm'}
  confirmText={statusModalType === 'confirm' ? 'Ya, Lanjutkan' : 'OK'}
  on:confirm={handleStatusModalConfirm}
  on:cancel={handleStatusModalClose}
  on:close={handleStatusModalClose}
/>
