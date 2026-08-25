<script>
  import { onMount } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '@/Layouts/AdminLayout.svelte';
  import TabNavigation from '@/Components/TabNavigation.svelte';
  import { page } from '@inertiajs/svelte';
  import { can } from '@/utils/permissions';
  
  // Props
  export let pengiriman = [];
  export let filters = {};
  export const statusList = [];
  export const jenisQuranList = [];
  export const wakifList = [];
  export const donaturList = [];
  export const stats = {};
  export const errors = {};
  export const auth = {};
  export const flash = {};
  
  // State
  let selectedPengiriman = [];
  let qrCodes = [];
  let loading = false;
  let error = null;
  let success = null;
  let searchQuery = filters.search || '';
  let startDate = filters.start_date || '';
  let endDate = filters.end_date || '';
  let qrStatus = filters.qr_status || '';

  // ✅ ENHANCEMENT: Number range filter variables
  let numberFrom = filters.number_from || '';
  let numberTo = filters.number_to || '';

  let filteredPengiriman = [];
  let printMode = false;

  // Current date for max date attribute
  let currentDate = new Date().toISOString().split('T')[0];
  let generationProgress = {
    current: 0,
    total: 0,
    results: []
  };
  
  // Computed: Selected pengiriman that already have QR codes
  $: selectedPengirimanWithQR = selectedPengiriman.filter(p => p.has_qr);
  
  // Thermal Print State
  let thermalPrintMode = false;

  $: canBulkThermalPrint = can.qr.bulkOperations();
  
  // Get current mode from URL
  let currentMode = 'generate-qr';
  $: {
    const urlParams = new URLSearchParams(window.location.search);
    currentMode = urlParams.get('mode') || 'generate-qr';
  }
  
  // Filter functions
  let searchTimeout;
  function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      applyFilters();
    }, 300);
  }

  function applyFilters() {
    const params = { mode: 'generate-qr' };
    if (searchQuery) params.search = searchQuery;
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;
    if (qrStatus) params.qr_status = qrStatus;
    // ✅ ENHANCEMENT: Include number range filters
    if (numberFrom) params.number_from = numberFrom;
    if (numberTo) params.number_to = numberTo;

    router.get('/admin/pengiriman', params, {
      preserveState: true,
      preserveScroll: true
    });
  }

  function resetFilters() {
    searchQuery = '';
    startDate = '';
    endDate = '';
    qrStatus = '';
    // ✅ ENHANCEMENT: Reset number filters
    numberFrom = '';
    numberTo = '';
    router.get('/admin/pengiriman', { mode: 'generate-qr' });
  }

  // Use pengiriman directly since pagination is handled server-side
  $: filteredPengiriman = Array.isArray(pengiriman?.data) ? pengiriman.data : (Array.isArray(pengiriman) ? pengiriman : []);
  
  // Toggle selection
  function toggleSelection(pengiriman) {
    const index = selectedPengiriman.findIndex(p => p.id === pengiriman.id);
    if (index === -1) {
      selectedPengiriman = [...selectedPengiriman, pengiriman];
    } else {
      selectedPengiriman = selectedPengiriman.filter(p => p.id !== pengiriman.id);
    }
  }
  
  // Toggle all selection
  function toggleAll(event) {
    if (event.target.checked) {
      selectedPengiriman = [...filteredPengiriman];
    } else {
      selectedPengiriman = [];
    }
  }
  
  // FIXED: Bulk generate QR codes using proper bulk endpoint
  async function generateQRCodes() {
    if (selectedPengiriman.length === 0) {
      error = 'Pilih minimal satu pengiriman';
      return;
    }
    
    loading = true;
    error = null;
    success = null;
    qrCodes = [];
    
    // Reset progress tracking
    generationProgress = {
      current: 0,
      total: selectedPengiriman.length,
      results: []
    };
    
    try {
      // Get CSRF token
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
      if (!csrfToken) {
        throw new Error('CSRF token tidak ditemukan');
      }
      
      // Extract pengiriman IDs
      const pengirimanIds = selectedPengiriman.map(p => p.id);
      
      
      // Use the proper bulk endpoint
      const response = await fetch('/admin/qr/bulk-generate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          pengiriman_ids: pengirimanIds
        })
      });
      
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`Server error (${response.status}): ${response.statusText}`);
      }
      
      const result = await response.json();
      
      if (!result.success) {
        throw new Error(result.message || 'Bulk generation failed');
      }
      
      // Process results
      const { summary, results } = result;
      generationProgress.results = results;
      
      // Filter successful results for QR codes
      const successfulResults = results.filter(r => r.status === 'success');
      
      // Create qrCodes array for print mode
      qrCodes = successfulResults.map(result => {
        const pengirimanData = selectedPengiriman.find(p => p.id === result.id);
        return {
          pengiriman: pengirimanData,
          qrCode: result.qr_url,
          qrData: result.qr_data || null
        };
      });
      
      // Show success message
      success = result.message;
      
      // If all successful, switch to print mode
      if (summary.success === summary.total) {
        printMode = true;
        success = `Semua ${summary.success} QR Code berhasil dibuat!`;
      } else if (summary.success > 0) {
        success = `${summary.success} dari ${summary.total} QR Code berhasil dibuat. ${summary.errors} gagal.`;
        printMode = true; // Still allow printing successful ones
      } else {
        error = `Semua QR Code gagal dibuat. Periksa log error.`;
      }
      
      // Log detailed results for debugging
      if (summary.errors > 0) {
        const failedResults = results.filter(r => r.status === 'error');
      }
      
      // Reload pengiriman data to update QR status
      if (summary.success > 0) {
        // Store current selected IDs
        const currentSelectedIds = selectedPengiriman.map(p => p.id);
        
        setTimeout(() => {
          router.reload({
            only: ['pengiriman'],
            preserveState: true,  // Preserve pagination state
            preserveScroll: true,
            onSuccess: () => {
              // Get the updated pengiriman data - handle both array and paginated object
              const updatedData = Array.isArray(pengiriman?.data) ? pengiriman.data : (Array.isArray(pengiriman) ? pengiriman : []);
              // Re-select items after reload based on stored IDs
              selectedPengiriman = updatedData.filter(p => currentSelectedIds.includes(p.id));
              // Keep print mode and QR codes to show preview - DON'T clear them
              // printMode and qrCodes should remain active to show the generated QR preview
            }
          });
        }, 500); // Small delay to ensure backend has updated the data
      }
      
    } catch (err) {
      error = `Gagal generate QR codes: ${err.message}`;
      
      // Reset progress on error
      generationProgress = {
        current: 0,
        total: 0,
        results: []
      };
    } finally {
      loading = false;
    }
  }
  
  // Print existing QR codes
  function printExistingQRCodes() {
    if (selectedPengirimanWithQR.length === 0) {
      error = 'Pilih pengiriman yang sudah memiliki QR Code';
      return;
    }
    
    // Prepare qrCodes array from existing QR data
    const existingQRCodes = selectedPengirimanWithQR.map(pengiriman => {
      // Try multiple QR URL sources
      let qrUrl = pengiriman.qr_url;
      if (!qrUrl && pengiriman.id) {
        qrUrl = `/admin/qr/display/${pengiriman.id}`;
      }
      
      return {
        pengiriman,
        qrCode: qrUrl,
        qrData: pengiriman.qr_data || null
      };
    });
    
    
    // Print with existing QR codes
    printQRCodesInternal(existingQRCodes);
  }
  
  // Print QR codes (shared function)
  function printQRCodes() {
    printQRCodesInternal(qrCodes);
  }
  
  // Internal print function
  function printQRCodesInternal(qrCodesToPrint) {
    // Validate QR codes before printing
    const validQRCodes = qrCodesToPrint.filter(qr => {
      if (!qr.qrCode) {
        return false;
      }
      return true;
    });
    
    if (validQRCodes.length === 0) {
      error = 'Tidak ada QR code yang valid untuk dicetak';
      return;
    }
    
    // Build the HTML content
    const currentDate = new Date();
    const formattedDate = currentDate.toLocaleDateString('id-ID', { 
      weekday: 'long', 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric'
    });
    const formattedTime = currentDate.toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit'
    });
    const hostname = window.location.hostname;
    
    let qrItemsHTML = '';
    validQRCodes.forEach((qr, index) => {
      const noResi = qr.pengiriman.no_resi || 'Tidak tersedia';
      const wakifName = qr.pengiriman.wakaf_item?.wakif_name || qr.pengiriman.donatur?.nama_donatur || 'Tidak tersedia';
      const wakifCode = qr.pengiriman.donatur?.kode_donatur ? '(' + qr.pengiriman.donatur.kode_donatur + ')' : '';
      const penerima = qr.pengiriman.nama_penerima || 'Tidak tersedia';
      const namaLembaga = qr.pengiriman.nama_lembaga || qr.pengiriman.lembaga || '';
      const penerimaWithLembaga = namaLembaga ? `${penerima} (${namaLembaga})` : penerima;
      const noHp = qr.pengiriman.no_hp_penerima || 'Tidak tersedia';
      const alamat = qr.pengiriman.alamat_tujuan || 'Tidak tersedia';
      const jenisQuran = qr.pengiriman.jenisQuran?.nama_jenis || 'Al-Quran Standar';
      const jumlah = qr.pengiriman.jumlah_quran || 1;
      const tanggal = new Date(qr.pengiriman.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
      
      qrItemsHTML += `
        <div class="qr-container">
          <div class="qr-header">
            <h3>QR Code Pengiriman #${index + 1}</h3>
          </div>
          <div class="qr-content">
            <div class="qr-info">
              <div class="info-row">
                <span class="info-label">No. Resi:</span>
                <span class="info-value"><strong>${noResi}</strong></span>
              </div>
              <div class="info-row">
                <span class="info-label">Wakif:</span>
                <span class="info-value">${wakifName} ${wakifCode}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Penerima:</span>
                <span class="info-value">${penerimaWithLembaga}</span>
              </div>
              <div class="info-row">
                <span class="info-label">No. HP:</span>
                <span class="info-value">${noHp}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Alamat:</span>
                <span class="info-value">${alamat}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Jenis:</span>
                <span class="info-value">${jenisQuran}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Jumlah:</span>
                <span class="info-value"><strong>${jumlah} Eksemplar</strong></span>
              </div>
              <div class="info-row">
                <span class="info-label">Tanggal:</span>
                <span class="info-value">${tanggal}</span>
              </div>
            </div>
            <div class="qr-image">
              <img 
                src="${qr.qrCode}" 
                alt="QR Code ${noResi}" 
                style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; display: block;"
              />
            </div>
          </div>
        </div>
      `;
    });
    
    
    const printWindow = window.open('', '_blank');
    
    // Build the print content using safe string concatenation
    const printContent = [
      '<!DOCTYPE html>',
      '<html>',
      '  <head>',
      '    <title>Print QR Codes - Ekspedisi Qur\'an</title>',
      '    <style>',
      '      @page { size: A4; margin: 10mm; }',
      '      body { margin: 0; padding: 0; font-family: Arial, sans-serif; background: white; }',
      '      .print-header { text-align: center; margin-bottom: 25px; border-bottom: 3px solid #2c5530; padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; }',
      '      .print-header h1 { margin: 0 0 5px 0; font-size: 28px; color: #2c5530; font-weight: bold; letter-spacing: 1px; }',
      '      .print-header h2 { margin: 0 0 15px 0; font-size: 18px; color: #666; font-weight: normal; font-style: italic; }',
      '      .print-info { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; }',
      '      .print-info p { margin: 0; color: #555; font-size: 12px; background: white; padding: 5px 10px; border-radius: 4px; border: 1px solid #ddd; }',
      '      .qr-container { page-break-inside: avoid; margin-bottom: 20px; padding: 20px; border: 2px solid #2c5530; border-radius: 10px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }',
      '      .qr-header { margin-bottom: 15px; padding: 12px; border-bottom: 2px solid #2c5530; background: #f8f9fa; border-radius: 6px; margin: -20px -20px 15px -20px; }',
      '      .qr-header h3 { margin: 0; color: #2c5530; font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }',
      '      .qr-content { display: flex; gap: 25px; align-items: flex-start; }',
      '      .qr-info { flex: 1; min-width: 0; }',
      '      .qr-image { width: 200px; height: 200px; border: 2px solid #2c5530; border-radius: 8px; padding: 8px; background: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }',
      '      .qr-image img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; display: block; }',
      '      .info-row { margin-bottom: 8px; font-size: 14px; line-height: 1.5; display: flex; align-items: flex-start; }',
      '      .info-label { font-weight: bold; color: #2c5530; display: inline-block; min-width: 90px; flex-shrink: 0; }',
      '      .info-value { color: #333; margin-left: 10px; flex: 1; }',
      '      .print-footer { margin-top: 20px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }',
      '      @media print {',
      '        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }',
      '        .qr-container { border: 2px solid #000; background: white; margin-bottom: 15px; page-break-inside: avoid; }',
      '        .print-header { background: #f8f9fa !important; border-bottom: 3px solid #000 !important; }',
      '        .print-header h1 { color: #000 !important; }',
      '        .qr-header { background: #f0f0f0 !important; border-bottom: 2px solid #000 !important; }',
      '        .qr-header h3 { color: #000 !important; }',
      '        .info-label { color: #000 !important; }',
      '        .qr-image { border: 2px solid #000 !important; background: white !important; width: 200px !important; height: 200px !important; }',
      '        .qr-image img { max-width: 184px !important; max-height: 184px !important; }',
      '        .qr-content { gap: 20px !important; }',
      '      }',
      '    </style>',
      '  </head>',
      '  <body>',
      '    <div class="print-header">',
      '      <h1>LABEL QR CODE PENGIRIMAN AL-QUR\'AN</h1>',
      '      <h2>Yayasan Wakaf Al-Qur\'an</h2>',
      '      <div class="print-info">',
      '        <p><strong>Tanggal Cetak:</strong> ' + formattedDate + ' pukul ' + formattedTime + '</p>',
      '        <p><strong>Total QR Codes:</strong> ' + validQRCodes.length + ' pengiriman</p>',
      '        <p><strong>Status:</strong> Siap untuk Pengiriman</p>',
      '      </div>',
      '    </div>',
      qrItemsHTML,
      '    <div class="print-footer">',
      '      <div style="border-top: 2px solid #2c5530; padding-top: 15px; margin-top: 30px; text-align: center;">',
      '        <p style="margin: 0; font-size: 11px; color: #666;">Dicetak oleh: <strong>Sistem Ekspedisi Al-Qur\'an</strong></p>',
      '        <p style="margin: 5px 0 0 0; font-size: 10px; color: #999;">' + hostname + ' | Scan QR untuk tracking pengiriman</p>',
      '        <p style="margin: 5px 0 0 0; font-size: 10px; color: #999; font-style: italic;">"Dan Kami turunkan dari Al Qur\'an suatu yang menjadi penawar dan rahmat bagi orang-orang yang beriman" - QS. Al-Isra: 82</p>',
      '      </div>',
      '    </div>',
      '  </body>',
      '</html>'  
    ].join('\n');
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    
    // Tunggu gambar dimuat sebelum print dengan preload
    const images = printWindow.document.querySelectorAll('img');
    let loadedImages = 0;
    const totalImages = images.length;
    
    if (totalImages === 0) {
      // No images to load, print immediately
      setTimeout(() => {
        printWindow.print();
      }, 500);
      return;
    }
    
    const checkAllLoaded = () => {
      loadedImages++;
      
      if (loadedImages >= totalImages) {
        setTimeout(() => {
          printWindow.print();
        }, 500);
      }
    };
    
    // Add load listeners to all images
    images.forEach((img, index) => {
      if (img.complete) {
        checkAllLoaded();
      } else {
        img.addEventListener('load', checkAllLoaded);
        img.addEventListener('error', () => {
          checkAllLoaded(); // Still count as "loaded" to proceed
        });
      }
    });
    
    // Fallback timeout in case some images never trigger events
    setTimeout(() => {
      if (loadedImages < totalImages) {
        printWindow.print();
      }
    }, 3000);
  }
  
  // Reset selection
  function resetSelection() {
    selectedPengiriman = [];
    qrCodes = [];
    printMode = false;
    error = null;
    success = null;
    generationProgress = {
      current: 0,
      total: 0,
      results: []
    };
  }
  
  // Clear messages
  function clearMessages() {
    error = null;
    success = null;
  }
  
  // ========== THERMAL PRINT FUNCTIONS ==========
  
  function previewThermalLabel() {
    if (!canBulkThermalPrint) {
      error = 'Anda tidak memiliki izin untuk mengakses thermal print.';
      return;
    }

    const url = `/admin/thermal-print/preview`;
    window.open(url, '_blank', 'width=800,height=600,scrollbars=yes');
  }

  function thermalPrintBulk() {
    if (!canBulkThermalPrint) {
      error = 'Anda tidak memiliki izin untuk thermal print.';
      return;
    }

    if (selectedPengirimanWithQR.length === 0) {
      error = 'Pilih pengiriman yang sudah memiliki QR Code';
      return;
    }
    
    const pengirimanIds = selectedPengirimanWithQR.map(p => p.id);
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/admin/thermal-print/bulk';
    form.target = '_blank';
    
    // CSRF Token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
    form.appendChild(csrfInput);
    
    // Pengiriman IDs
    pengirimanIds.forEach(id => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'pengiriman_ids[]';
      input.value = id;
      form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    success = `Membuka thermal print untuk ${pengirimanIds.length} label...`;
  }
  
  async function generateAndThermalPrint() {
    if (selectedPengiriman.length === 0) {
      error = 'Pilih minimal satu pengiriman';
      return;
    }

    if (!canBulkThermalPrint) {
      error = 'Anda tidak memiliki izin untuk generate dan thermal print.';
      return;
    }

    try {
      // Generate QR codes terlebih dahulu
      await generateQRCodes();
      
      // Jika berhasil, tunggu sebentar lalu thermal print
      if (success && generationProgress.results) {
        const successfulResults = generationProgress.results.filter(r => r.status === 'success');
        if (successfulResults.length > 0) {
          setTimeout(() => {
            thermalPrintBulk();
          }, 1500);
        }
      }
    } catch (err) {
      error = `Gagal generate dan print: ${err.message}`;
    }
  }
  
  function thermalPrintSingle(pengiriman) {
    const url = `/admin/thermal-print/single/${pengiriman.id}`;
    window.open(url, '_blank', 'width=800,height=600,scrollbars=yes');
  }
</script>

<svelte:head>
  <title>Generate QR Code - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <div class="mb-6 md:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between space-y-4 sm:space-y-0">
      <div>
        <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">Generate QR Code & Print Thermal</h2>
        <p class="text-sm md:text-base text-gray-600">Generate QR Code untuk pengiriman Al-Quran dari <span class="font-semibold text-blue-600">semua status aktif</span> dan print label thermal 100×150mm</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <div class="inline-flex items-center px-3 py-1 rounded-full text-xs sm:text-sm bg-blue-100 text-blue-800">
            <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="hidden sm:inline">Menampilkan resi dari semua status aktif</span>
            <span class="sm:hidden">Semua status aktif</span>
          </div>
          <div class="inline-flex items-center px-3 py-1 rounded-full text-xs sm:text-sm bg-green-100 text-green-800">
            <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            <span class="hidden sm:inline">Thermal Print 100×150mm dengan data wakif</span>
            <span class="sm:hidden">Thermal Print</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <TabNavigation {currentMode} />
  
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
    <!-- Success/Error Messages -->
    {#if success}
      <div class="mb-4 p-3 sm:p-4 bg-green-50 text-green-600 rounded-lg flex items-start justify-between">
        <div class="flex items-start">
          <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          <span class="text-sm sm:text-base">{success}</span>
        </div>
        <button on:click={clearMessages} class="text-green-400 hover:text-green-600 flex-shrink-0 ml-2">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
          </svg>
        </button>
      </div>
    {/if}
    
    {#if error}
      <div class="mb-4 p-3 sm:p-4 bg-red-50 text-red-600 rounded-lg flex items-start justify-between">
        <div class="flex items-start">
          <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
          </svg>
          <span class="text-sm sm:text-base break-words">{error}</span>
        </div>
        <button on:click={clearMessages} class="text-red-400 hover:text-red-600 flex-shrink-0 ml-2">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
          </svg>
        </button>
      </div>
    {/if}

    <!-- Progress indicator during bulk generation -->
    {#if loading && generationProgress.total > 0}
      <div class="mb-4 p-3 sm:p-4 bg-blue-50 rounded-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-2 space-y-1 sm:space-y-0">
          <span class="text-sm font-medium text-blue-900">Generating QR Codes...</span>
          <span class="text-sm text-blue-700">{generationProgress.current}/{generationProgress.total}</span>
        </div>
        <div class="w-full bg-blue-200 rounded-full h-2">
          <div 
            class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
            style="width: {(generationProgress.current / generationProgress.total) * 100}%"
          ></div>
        </div>
      </div>
    {/if}

    <!-- Search & Filter -->
    <div class="bg-gray-50 rounded-lg p-4 mb-6">
      <div class="space-y-4">
        <!-- Search Row -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
          <!-- Search -->
          <div class="lg:col-span-2">
            <label for="search-pengiriman" class="block text-sm font-medium text-gray-700 mb-2">Cari Pengiriman</label>
            <input
              id="search-pengiriman"
              type="text"
              bind:value={searchQuery}
              on:input={handleSearch}
              placeholder="Nomor resi, nama penerima, atau alamat..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
            />
          </div>
          
          <!-- QR Status Filter -->
          <div>
            <label for="status-qr" class="block text-sm font-medium text-gray-700 mb-2">Status QR</label>
            <select
              id="status-qr"
              bind:value={qrStatus}
              on:change={applyFilters}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
            >
              <option value="">Semua</option>
              <option value="has_qr">Sudah Ada QR</option>
              <option value="no_qr">Belum Ada QR</option>
            </select>
          </div>
          
          <!-- Reset Button -->
          <div class="flex items-end">
            <button
              on:click={resetFilters}
              class="w-full px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors"
            >
              Reset Filter
            </button>
          </div>
        </div>
        
        <!-- Date Range & Number Range Filters -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
            <input
              id="start_date"
              type="date"
              bind:value={startDate}
              on:change={applyFilters}
              max={currentDate}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
            />
          </div>
          <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
            <input
              id="end_date"
              type="date"
              bind:value={endDate}
              on:change={applyFilters}
              max={currentDate}
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
            />
          </div>
          <!-- ✅ ENHANCEMENT: Number range filters -->
          <div>
            <label for="number_from" class="block text-sm font-medium text-gray-700 mb-2">No. Dari</label>
            <input
              id="number_from"
              type="number"
              bind:value={numberFrom}
              on:change={applyFilters}
              min="1"
              placeholder="1"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
            />
          </div>
          <div>
            <label for="number_to" class="block text-sm font-medium text-gray-700 mb-2">No. Sampai</label>
            <input
              id="number_to"
              type="number"
              bind:value={numberTo}
              on:change={applyFilters}
              min="1"
              placeholder="500"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
            />
          </div>
        </div>
        
        <!-- Active Filters -->
        {#if startDate || endDate || searchQuery || qrStatus || numberFrom || numberTo}
          <div class="flex flex-wrap gap-2">
            {#if searchQuery}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                <span>Pencarian: {searchQuery}</span>
                <button
                  on:click={() => { searchQuery = ''; applyFilters(); }}
                  class="ml-2 text-blue-600 hover:text-blue-800"
                >
                  &times;
                </button>
              </div>
            {/if}

            {#if qrStatus}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-orange-100 text-orange-800">
                <span>QR: {qrStatus === 'has_qr' ? 'Sudah Ada' : 'Belum Ada'}</span>
                <button
                  on:click={() => { qrStatus = ''; applyFilters(); }}
                  class="ml-2 text-orange-600 hover:text-orange-800"
                >
                  &times;
                </button>
              </div>
            {/if}

            {#if startDate}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-800">
                <span>Mulai: {startDate}</span>
                <button
                  on:click={() => { startDate = ''; applyFilters(); }}
                  class="ml-2 text-green-600 hover:text-green-800"
                >
                  &times;
                </button>
              </div>
            {/if}

            {#if endDate}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
                <span>Sampai: {endDate}</span>
                <button
                  on:click={() => { endDate = ''; applyFilters(); }}
                  class="ml-2 text-purple-600 hover:text-purple-800"
                >
                  &times;
                </button>
              </div>
            {/if}

            <!-- ✅ ENHANCEMENT: Number range filter badges -->
            {#if numberFrom}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-indigo-100 text-indigo-800">
                <span>No. Dari: {numberFrom}</span>
                <button
                  on:click={() => { numberFrom = ''; applyFilters(); }}
                  class="ml-2 text-indigo-600 hover:text-indigo-800"
                >
                  &times;
                </button>
              </div>
            {/if}

            {#if numberTo}
              <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-pink-100 text-pink-800">
                <span>No. Sampai: {numberTo}</span>
                <button
                  on:click={() => { numberTo = ''; applyFilters(); }}
                  class="ml-2 text-pink-600 hover:text-pink-800"
                >
                  &times;
                </button>
              </div>
            {/if}
          </div>
        {/if}
      </div>
    </div>
  
    {#if !printMode}
      <!-- Selection and Action Buttons -->
      <div class="mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-4 space-y-3 lg:space-y-0">
          <div class="flex-1 lg:mr-4">
            <p class="text-sm text-gray-600">
              {filteredPengiriman.length} pengiriman ditemukan. 
              {selectedPengiriman.length} dipilih
              {#if selectedPengirimanWithQR.length > 0}
                ({selectedPengirimanWithQR.length} sudah memiliki QR)
              {/if}
            </p>
          </div>
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <!-- Preview Thermal Label Button -->
            <button
              on:click={previewThermalLabel}
              class="px-4 py-2.5 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center justify-center gap-2 text-sm font-medium disabled:opacity-50"
              disabled={!canBulkThermalPrint}
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <span class="hidden sm:inline">Preview 100×150mm</span>
              <span class="sm:hidden">Preview</span>
            </button>
            
            <!-- Print Existing QR Codes Button (Thermal) -->
            <button
              on:click={thermalPrintBulk}
              disabled={loading || selectedPengirimanWithQR.length === 0 || !canBulkThermalPrint}
              class="px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2 text-sm font-medium"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
              </svg>
              <span class="hidden sm:inline">Print Thermal ({selectedPengirimanWithQR.length})</span>
              <span class="sm:hidden">Print ({selectedPengirimanWithQR.length})</span>
            </button>
            
            <!-- Generate New QR Codes Button (Thermal) -->
            <button
              on:click={generateAndThermalPrint}
              disabled={loading || selectedPengiriman.length === 0 || !canBulkThermalPrint}
              class="px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2 text-sm font-medium"
            >
              {#if loading}
                <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                <span class="hidden sm:inline">Generating...</span>
                <span class="sm:hidden">...</span>
              {:else}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h1M6 16H5m12-6h1M6 8H5M9 20h6a2 2 0 002-2V6a2 2 0 00-2-2H9a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="hidden sm:inline">Generate & Print ({selectedPengiriman.length})</span>
                <span class="sm:hidden">Generate ({selectedPengiriman.length})</span>
              {/if}
            </button>
          </div>
        </div>
        
        <!-- Pengiriman List -->
        <div class="border border-gray-200 rounded-lg overflow-hidden">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left">
                  <input
                    type="checkbox"
                    on:change={toggleAll}
                    checked={selectedPengiriman.length === filteredPengiriman.length && filteredPengiriman.length > 0}
                    class="rounded border-gray-300 text-red-600 focus:ring-red-500"
                  />
                </th>
                <!-- ✅ ENHANCEMENT: Add Number column header -->
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">No.</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Resi</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wakif</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Penerima</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">QR Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              {#if filteredPengiriman && filteredPengiriman.length > 0}
                {#each filteredPengiriman as item, index}
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                      <input
                        type="checkbox"
                        checked={selectedPengiriman.some(p => p.id === item.id)}
                        on:change={() => toggleSelection(item)}
                        class="rounded border-gray-300 text-red-600 focus:ring-red-500"
                      />
                    </td>
                    <!-- ✅ ENHANCEMENT: Add Number column cell -->
                    <td class="px-4 py-4 whitespace-nowrap text-center">
                      <div class="text-sm font-medium text-gray-700">
                        {(pengiriman.from || 0) + index}
                      </div>
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{item.no_resi || 'Data tidak tersedia'}</td>
                    <td class="px-4 py-3">
                      <div class="flex items-center">
                        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                          <span class="text-xs font-medium text-indigo-800">{(item.wakaf_item?.wakif_name || item.donatur?.nama_donatur)?.charAt(0) || 'W'}</span>
                        </div>
                        <div>
                          <div class="text-sm font-medium text-gray-900">{item.wakaf_item?.wakif_name || item.donatur?.nama_donatur || 'Data tidak tersedia'}</div>
                          <div class="text-sm text-gray-500">{item.wakaf_item?.relationship_to_donatur || 'Wakaf'}</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3">
                      <div>
                        <div class="text-sm font-medium text-gray-900">{item.nama_penerima || 'Data tidak tersedia'}</div>
                        <div class="text-sm text-gray-500">{item.nama_lembaga || '-'}</div>
                      </div>
                    </td>
                    <td class="px-4 py-3">
                      <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {item.status?.warna ? `bg-${item.status.warna}-100 text-${item.status.warna}-800` : 'bg-gray-100 text-gray-800'}">
                        {item.status?.nama || 'Unknown'}
                      </span>
                    </td>
                    <td class="px-4 py-3">
                      {#if item.has_qr}
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                          ✓ QR Ada
                        </span>
                      {:else}
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                          ⚪ Belum Ada
                        </span>
                      {/if}
                    </td>
                  </tr>
                {/each}
              {:else}
                <tr>
                  <!-- ✅ ENHANCEMENT: Update colspan from 6 to 7 (added Number column) -->
                  <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                    {#if searchQuery}
                      Tidak ada pengiriman yang cocok dengan pencarian "{searchQuery}"
                    {:else}
                      Tidak ada pengiriman dengan status aktif ditemukan
                    {/if}
                  </td>
                </tr>
              {/if}
            </tbody>
          </table>
        </div>
        
        <!-- Generation Results Summary (if available) -->
        {#if generationProgress.results.length > 0}
          <div class="mt-4 p-3 sm:p-4 bg-gray-50 rounded-lg">
            <h4 class="text-sm font-medium text-gray-900 mb-2">Generation Results:</h4>
            <div class="space-y-1 text-sm max-h-40 overflow-y-auto">
              {#each generationProgress.results as result}
                <div class="flex items-center justify-between">
                  <span class="text-gray-600 text-xs sm:text-sm truncate mr-2">{result.no_resi}</span>
                  {#if result.status === 'success'}
                    <span class="text-green-600 text-xs sm:text-sm flex-shrink-0">✓ Success</span>
                  {:else if result.status === 'error'}
                    <span class="text-red-600 text-xs sm:text-sm flex-shrink-0 truncate">✗ {result.message}</span>
                  {:else if result.status === 'skipped'}
                    <span class="text-yellow-600 text-xs sm:text-sm flex-shrink-0 truncate">⚪ {result.message}</span>
                  {/if}
                </div>
              {/each}
            </div>
          </div>
        {/if}

        <!-- Pagination -->
        {#if pengiriman && pengiriman.last_page > 1}
          <div class="mt-6 px-4 sm:px-6 py-4 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div class="text-sm text-gray-500 text-center sm:text-left">
                Menampilkan {pengiriman.from} - {pengiriman.to} dari {pengiriman.total} hasil
              </div>
              <div class="flex justify-center sm:justify-end space-x-1">
                <!-- Previous -->
                {#if pengiriman.prev_page_url}
                  <button
                    on:click={() => {
                      const params = { mode: 'generate-qr', page: pengiriman.current_page - 1 };
                      if (searchQuery) params.search = searchQuery;
                      if (startDate) params.start_date = startDate;
                      if (endDate) params.end_date = endDate;
                      if (qrStatus) params.qr_status = qrStatus;
                      // ✅ ENHANCEMENT: Include number range filters in pagination
                      if (numberFrom) params.number_from = numberFrom;
                      if (numberTo) params.number_to = numberTo;

                      router.get('/admin/pengiriman', params, {
                        preserveState: true,
                        preserveScroll: true
                      });
                    }}
                    class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center"
                  >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <span class="hidden sm:inline">Sebelumnya</span>
                    <span class="sm:hidden">Prev</span>
                  </button>
                {/if}

                <!-- Next -->
                {#if pengiriman.next_page_url}
                  <button
                    on:click={() => {
                      const params = { mode: 'generate-qr', page: pengiriman.current_page + 1 };
                      if (searchQuery) params.search = searchQuery;
                      if (startDate) params.start_date = startDate;
                      if (endDate) params.end_date = endDate;
                      if (qrStatus) params.qr_status = qrStatus;
                      // ✅ ENHANCEMENT: Include number range filters in pagination
                      if (numberFrom) params.number_from = numberFrom;
                      if (numberTo) params.number_to = numberTo;

                      router.get('/admin/pengiriman', params, {
                        preserveState: true,
                        preserveScroll: true
                      });
                    }}
                    class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center"
                  >
                    <span class="hidden sm:inline">Selanjutnya</span>
                    <span class="sm:hidden">Next</span>
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                  </button>
                {/if}
              </div>
            </div>
          </div>
        {/if}
      </div>
    {:else}
      <!-- Print Preview -->
      <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 space-y-3 sm:space-y-0">
          <h3 class="text-base sm:text-lg font-semibold text-gray-900">Preview QR Codes ({qrCodes.length})</h3>
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <button
              on:click={resetSelection}
              class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium"
            >
              Kembali
            </button>
            <button
              on:click={printQRCodes}
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center gap-2 text-sm font-medium"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
              </svg>
              <span class="hidden sm:inline">Print QR Codes</span>
              <span class="sm:hidden">Print</span>
            </button>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
          {#each qrCodes as qr}
            <div class="border border-gray-200 rounded-lg p-3 sm:p-4">
              <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1 space-y-2">
                  <div class="text-xs sm:text-sm">
                    <span class="font-medium text-gray-500">No. Resi:</span>
                    <span class="text-gray-900 ml-2 break-words">{qr.pengiriman.no_resi || 'Data tidak tersedia'}</span>
                  </div>
                  <div class="text-xs sm:text-sm">
                    <span class="font-medium text-gray-500">Wakif:</span>
                    <span class="text-gray-900 ml-2 break-words">{qr.pengiriman.wakaf_item?.wakif_name || qr.pengiriman.donatur?.nama_donatur || 'Data tidak tersedia'}</span>
                  </div>
                  <div class="text-xs sm:text-sm">
                    <span class="font-medium text-gray-500">Penerima:</span>
                    <span class="text-gray-900 ml-2 break-words">{qr.pengiriman.nama_penerima || 'Data tidak tersedia'}</span>
                  </div>
                  <div class="text-xs sm:text-sm">
                    <span class="font-medium text-gray-500">Alamat:</span>
                    <span class="text-gray-900 ml-2 break-words">{qr.pengiriman.alamat_tujuan || 'Data tidak tersedia'}</span>
                  </div>
                </div>
                <div class="w-24 h-24 sm:w-32 sm:h-32 flex-shrink-0 mx-auto sm:mx-0">
                  <img src={qr.qrCode} alt="QR Code" class="w-full h-full object-contain border border-gray-200 rounded" />
                </div>
              </div>
            </div>
          {/each}
        </div>
      </div>
    {/if}
  </div>
</AdminLayout>
