<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import Pagination from '../../../Components/Pagination.svelte';
  import DownloadButton from '../../../Components/Certificate/DownloadButton.svelte';
  import PreviewButton from '../../../Components/Certificate/PreviewButton.svelte';
  import TokenButton from '../../../Components/Certificate/TokenButton.svelte';
  import { router } from '@inertiajs/svelte';
  import { showSuccess, showError, showInfo } from '../../../stores/toast';
  import { can } from '../../../utils/permissions.js';

  export let certificates = {};
  export let stats = {};
  export let filters = {};

  let searchQuery = filters.search || '';
  let sentStatusFilter = filters.sent_status || '';
  let startDate = filters.start_date || '';
  let endDate = filters.end_date || '';
  let showBatchGenerateModal = false;
  let showMarkSentModal = false;
  let showDeleteModal = false;
  let selectedCertificateId = null;
  let selectedCertificateData = null;
  let readyBatches = [];
  let loadingBatches = false;

  $: canGenerate = can.certificates.generate();
  $: canDelete = can.certificates.delete();
  $: canUpdate = can.certificates.update();

  let searchTimeout;

  function handleAutoSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      router.get('/admin/certificates', {
        search: searchQuery || undefined,
        sent_status: sentStatusFilter || undefined,
        start_date: startDate || undefined,
        end_date: endDate || undefined
      }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
      });
    }, 300); // 300ms delay for better UX
  }

  function clearFilters() {
    searchQuery = '';
    sentStatusFilter = '';
    startDate = '';
    endDate = '';
    router.get('/admin/certificates', {}, {
      preserveState: true,
      preserveScroll: true,
      replace: true
    });
  }

  function handleTokenGenerated(data) {
    // Token generation success handled by component itself
  }

  function showMarkSentConfirm(certificate) {
    selectedCertificateId = certificate.id;
    selectedCertificateData = certificate;
    showMarkSentModal = true;
  }

  function handleMarkAsSent() {
    showMarkSentModal = false;
    router.patch(`/admin/certificates/${selectedCertificateId}/mark-sent`, {}, {
      onSuccess: () => {
        selectedCertificateId = null;
        selectedCertificateData = null;
        // Success message handled by server flash message
      },
      onError: () => {
        // Error message handled by server flash message
        selectedCertificateId = null;
        selectedCertificateData = null;
      }
    });
  }

  function cancelMarkSent() {
    showMarkSentModal = false;
    selectedCertificateId = null;
    selectedCertificateData = null;
  }

  function showDeleteConfirm(certificate) {
    selectedCertificateId = certificate.id;
    selectedCertificateData = certificate;
    showDeleteModal = true;
  }

  function handleDelete() {
    showDeleteModal = false;
    router.delete(`/admin/certificates/${selectedCertificateId}`, {
      onSuccess: () => {
        selectedCertificateId = null;
        selectedCertificateData = null;
        // Success message handled by server flash message
      },
      onError: () => {
        // Error message handled by server flash message
        selectedCertificateId = null;
        selectedCertificateData = null;
      }
    });
  }

  function cancelDelete() {
    showDeleteModal = false;
    selectedCertificateId = null;
    selectedCertificateData = null;
  }

  // Batch Certificate Generation Functions
  function showBatchGenerateConfirm() {
    showBatchGenerateModal = true;
    loadReadyBatches();
  }

  async function loadReadyBatches() {
    loadingBatches = true;
    
    try {
      const response = await fetch('/admin/certificates/batches-ready-for-certificate', {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        credentials: 'include', // Include session cookies
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const result = await response.json();
      
      if (result.success) {
        readyBatches = result.data || [];
      } else {
        throw new Error(result.message || 'Failed to load batches');
      }
    } catch (error) {
      console.error('Failed to load ready batches:', error);
      showError('Gagal Memuat Data', `Tidak dapat memuat data batch: ${error.message}`);
      readyBatches = [];
    } finally {
      loadingBatches = false;
    }
  }

  function generateBatchCertificate(batch) {
    // Show processing toast
    showInfo('Memproses...', `Sedang membuat sertifikat untuk batch ${batch.batch_code}`);
    
    router.post(`/admin/certificates/generate-batch-certificate/${batch.id}`, {}, {
      preserveScroll: true,
      onSuccess: (page) => {
        showBatchGenerateModal = false;
        
        // Check flash message
        if (page.props?.flash?.success) {
          showSuccess('Berhasil!', page.props.flash.success);
        } else {
          // Default success message for async processing
          showSuccess('Sertifikat Diproses', 'Sertifikat batch sedang diproses di background. Anda akan menerima notifikasi setelah selesai.');
        }
        
        // Refresh the page after a delay to show updated certificates
        setTimeout(() => {
          router.reload({ preserveScroll: true });
        }, 2000);
      },
      onError: (errors) => {
        console.error('Batch certificate generation failed:', errors);
        showError('Gagal Membuat Sertifikat', errors?.message || 'Terjadi kesalahan saat membuat sertifikat');
      },
      onFinish: () => {
        // Always refresh after request completes
        setTimeout(() => {
          router.reload({ preserveScroll: true });
        }, 3000);
      }
    });
  }

  function cancelBatchGenerate() {
    showBatchGenerateModal = false;
    readyBatches = [];
  }
</script>

<svelte:head>
  <title>Certificate Management - Admin</title>
</svelte:head>

<AdminLayout>
  <div class="px-6 py-8">
    <!-- Header -->
    <div class="mb-6 lg:mb-8">
      <div class="flex flex-col space-y-4 lg:flex-row lg:justify-between lg:items-center lg:space-y-0">
        <div>
          <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mb-2">Certificate Management</h2>
          <p class="text-gray-600">Manage on-demand certificate generation and sharing</p>
        </div>
        <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:gap-3">
          {#if canGenerate}
            <button
              on:click={showBatchGenerateConfirm}
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              Generate Batch Certificates
            </button>
          {/if}
        </div>
      </div>
    </div>

    <!-- Flash Messages -->
    <FlashMessage />

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center">
          <div class="p-2 bg-blue-100 rounded-lg">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Total Certificates</p>
            <p class="text-2xl font-bold text-gray-900">{stats.total || 0}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center">
          <div class="p-2 bg-green-100 rounded-lg">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Generated Today</p>
            <p class="text-2xl font-bold text-gray-900">{stats.today || 0}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center">
          <div class="p-2 bg-purple-100 rounded-lg">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">This Month</p>
            <p class="text-2xl font-bold text-gray-900">{stats.month || 0}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center">
          <div class="p-2 bg-orange-100 rounded-lg">
            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Not Sent</p>
            <p class="text-2xl font-bold text-gray-900">{stats.not_sent || 0}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Filter & Pencarian</h3>
      
      <!-- Search -->
      <div class="mb-4">
        <label for="search-query" class="block text-sm font-medium text-gray-700 mb-2">Cari Pengiriman</label>
        <div class="relative">
          <input
            type="text"
            id="search-query"
            bind:value={searchQuery}
            placeholder="No resi, nama donatur, atau penerima..."
            class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            on:input={handleAutoSearch}
          />
          <div class="absolute inset-y-0 left-0 flex items-center pl-3">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
          </div>
        </div>
      </div>

      <!-- Filters Row -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Status Filter -->
        <div>
          <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
          <select
            id="status-filter"
            bind:value={sentStatusFilter}
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            on:change={handleAutoSearch}
          >
            <option value="">Semua Status</option>
            <option value="sent">Terkirim</option>
            <option value="not_sent">Belum Terkirim</option>
          </select>
        </div>

        <!-- Date Range -->
        <div>
          <label for="start-date" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
          <input
            type="date"
            id="start-date"
            bind:value={startDate}
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            on:change={handleAutoSearch}
          />
        </div>

        <div>
          <label for="end-date" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
          <input
            type="date"
            id="end-date"
            bind:value={endDate}
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            on:change={handleAutoSearch}
          />
        </div>

        <!-- Clear Button -->
        <div class="flex items-end">
          <button
            on:click={clearFilters}
            class="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Certificate Table -->
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wakaf Batch</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wakif</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Generated</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            {#each certificates.data as certificate}
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900">{certificate.nomor_sertifikat}</div>
                    <div class="text-sm text-gray-500">On-Demand Generation</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  {#if certificate.wakaf_batch}
                    <div class="text-sm text-gray-900">{certificate.wakaf_batch.batch_code}</div>
                    <div class="text-sm text-gray-500">{certificate.wakaf_batch.total_quran || 0} Mushaf</div>
                  {:else}
                    <div class="text-sm text-gray-900">Konsolidasi</div>
                    <div class="text-sm text-gray-500">Individual Certificate</div>
                  {/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">
                    {#if certificate.wakif_names_string}
                      {certificate.wakif_names_string}
                    {:else}
                      {certificate.wakaf_batch?.donatur?.nama_donatur || certificate.donatur?.nama_donatur || 'N/A'}
                    {/if}
                  </div>
                  {#if certificate.wakaf_batch?.donatur?.nama_donatur}
                    <div class="text-xs text-gray-500">Donatur: {certificate.wakaf_batch.donatur.nama_donatur}</div>
                  {/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{certificate.formatted_generated_at}</div>
                  <div class="text-sm text-gray-500">by {certificate.generator?.name || 'System'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  {#if certificate.is_sent}
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      Sent
                    </span>
                  {:else}
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                      Not Sent
                    </span>
                  {/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex items-center space-x-2">
                    <!-- Preview Button -->
                    <PreviewButton 
                      certificateId={certificate.id}
                      iconOnly={true}
                      buttonClass="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded transition-colors"
                    />

                    <!-- Download Button -->
                    <DownloadButton 
                      certificateId={certificate.id}
                      iconOnly={true}
                      buttonClass="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded transition-colors"
                    />

                    <!-- Token/Share Button -->
                    <TokenButton 
                      certificateId={certificate.id}
                      iconOnly={true}
                      buttonClass="p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded transition-colors"
                      onTokenGenerated={handleTokenGenerated}
                    />

                    <!-- Mark as Sent Button -->
                    {#if canUpdate && !certificate.is_sent}
                      <button
                        on:click={() => showMarkSentConfirm(certificate)}
                        class="p-2 text-purple-600 hover:text-purple-800 hover:bg-purple-100 rounded transition-colors"
                        title="Mark as sent"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                      </button>
                    {/if}

                    <!-- Delete Button -->
                    {#if canDelete}
                      <button
                        on:click={() => showDeleteConfirm(certificate)}
                        class="p-2 text-red-600 hover:text-red-800 hover:bg-red-100 rounded transition-colors"
                        title="Delete certificate record"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                      </button>
                    {/if}
                  </div>
                </td>
              </tr>
            {:else}
              <tr>
                <td colspan="6" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center">
                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No certificates found</h3>
                    <p class="text-gray-500 mb-6">No certificate records match your search criteria.</p>
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      {#if certificates.data && certificates.data.length > 0}
        <div class="px-6 py-4 border-t border-gray-200">
          <Pagination data={certificates} />
        </div>
      {/if}
    </div>
  </div>

</AdminLayout>

<!-- Batch Certificate Generation Modal -->
{#if showBatchGenerateModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
           role="button"
           tabindex="0"
           aria-label="Tutup modal batch generate"
           on:click={cancelBatchGenerate}
           on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cancelBatchGenerate(); } }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl lg:max-w-6xl sm:w-full sm:p-6">
        <div class="sm:flex sm:items-start">
          <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
              Generate Sertifikat Batch
            </h3>
            <div class="mt-2">
              <p class="text-sm text-gray-500 mb-4">
                Pilih batch donasi yang akan dibuat sertifikatnya. Sertifikat dapat dibuat kapan saja setelah data donasi diinput.
              </p>
              
              {#if loadingBatches}
                <div class="flex justify-center items-center py-8">
                  <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                  <span class="ml-2 text-sm text-gray-600">Memuat data batch...</span>
                </div>
              {:else if readyBatches.length === 0}
                <div class="text-center py-8">
                  <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                  <p class="text-gray-500">Tidak ada batch yang tersedia untuk generate sertifikat</p>
                  <p class="text-sm text-gray-400 mt-1">Semua batch sudah memiliki sertifikat atau belum ada data donasi</p>
                </div>
              {:else}
                <div class="max-h-96 overflow-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                          Batch Code
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                          Wakif
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                          Total Mushaf
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                          Tanggal Wakaf
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                          Status
                        </th>
                        <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                          Aksi
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      {#each readyBatches as batch}
                        <tr>
                          <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                            {batch.batch_code}
                          </td>
                          <td class="px-3 py-3 text-sm text-gray-500">
                            <div class="max-w-xs" title="{batch.wakif_names_full}">
                              <span class="text-gray-900">{batch.wakif_name}</span>
                              {#if batch.wakif_count > 1}
                                <div class="text-xs text-gray-400 mt-1">
                                  {batch.wakif_count} wakif
                                </div>
                              {/if}
                            </div>
                          </td>
                          <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500">
                            {batch.total_quran} mushaf
                          </td>
                          <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500">
                            {batch.tanggal_wakaf}
                          </td>
                          <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                              {batch.status}
                            </span>
                          </td>
                          <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-center">
                            {#if canGenerate}
                              <button
                                on:click={() => generateBatchCertificate(batch)}
                                class="text-blue-600 hover:text-blue-800 font-medium px-3 py-1 hover:bg-blue-50 rounded transition-colors"
                              >
                                Generate
                              </button>
                            {/if}
                          </td>
                        </tr>
                      {/each}
                    </tbody>
                  </table>
                </div>
              {/if}
            </div>
          </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
          <button
            type="button"
            on:click={cancelBatchGenerate}
            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:ml-3 sm:w-auto sm:text-sm"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Mark as Sent Confirmation Modal -->
{#if showMarkSentModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
           role="button"
           tabindex="0"
           aria-label="Tutup modal tandai terkirim"
           on:click={cancelMarkSent}
           on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cancelMarkSent(); } }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
        <div class="sm:flex sm:items-start">
          <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 sm:mx-0 sm:h-10 sm:w-10">
            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </svg>
          </div>
          <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
              Tandai Sertifikat Sebagai Terkirim
            </h3>
            <div class="mt-2">
              <p class="text-sm text-gray-500">
                Apakah Anda yakin ingin menandai sertifikat ini sebagai terkirim? Sertifikat yang sudah ditandai terkirim tidak dapat dikembalikan ke status "belum terkirim".
              </p>
              {#if selectedCertificateData}
                <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                  <div class="text-sm">
                    <p><strong>Nomor Sertifikat:</strong> {selectedCertificateData.nomor_sertifikat}</p>
                    <p><strong>Batch:</strong> {selectedCertificateData.wakaf_batch?.batch_code || 'Konsolidasi'}</p>
                    <p><strong>Donatur:</strong> {selectedCertificateData.wakaf_batch?.donatur?.nama_donatur || selectedCertificateData.donatur?.nama_donatur || 'N/A'}</p>
                  </div>
                </div>
              {/if}
            </div>
          </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
          <button
            type="button"
            on:click={handleMarkAsSent}
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </svg>
            Tandai Terkirim
          </button>
          <button
            type="button"
            on:click={cancelMarkSent}
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto sm:text-sm"
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Delete Confirmation Modal -->
{#if showDeleteModal}
  <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
           role="button"
           tabindex="0"
           aria-label="Tutup modal hapus"
           on:click={cancelDelete}
           on:keydown={(e) => { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cancelDelete(); } }}
      ></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
        <div class="sm:flex sm:items-start">
          <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
          </div>
          <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
              Hapus Record Sertifikat
            </h3>
            <div class="mt-2">
              <p class="text-sm text-gray-500">
                Apakah Anda yakin ingin menghapus record sertifikat ini? Tindakan ini tidak dapat dibatalkan. 
                File PDF sertifikat masih dapat dibuat ulang karena menggunakan sistem on-demand generation.
              </p>
              {#if selectedCertificateData}
                <div class="mt-3 p-3 bg-red-50 rounded-lg border border-red-200">
                  <div class="text-sm">
                    <p><strong>Nomor Sertifikat:</strong> {selectedCertificateData.nomor_sertifikat}</p>
                    <p><strong>Batch:</strong> {selectedCertificateData.wakaf_batch?.batch_code || 'Konsolidasi'}</p>
                    <p><strong>Donatur:</strong> {selectedCertificateData.wakaf_batch?.donatur?.nama_donatur || selectedCertificateData.donatur?.nama_donatur || 'N/A'}</p>
                    <p><strong>Status:</strong> {selectedCertificateData.is_sent ? 'Sudah Terkirim' : 'Belum Terkirim'}</p>
                  </div>
                </div>
              {/if}
            </div>
          </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
          <button
            type="button"
            on:click={handleDelete}
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Hapus Record
          </button>
          <button
            type="button"
            on:click={cancelDelete}
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:w-auto sm:text-sm"
          >
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>
{/if}
