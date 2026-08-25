<script>
  import { router, page } from '@inertiajs/svelte';
  import { fade, scale } from 'svelte/transition';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { can } from '../../../utils/permissions.js';
  import { toast, dialog } from '../../../utils/notifications.js';
  
  export let donatur;
  export let filters = {};
  export let stats = {};
  
  let search = filters.search || '';
  let kodeDonatur = filters.kode_donatur || '';
  let startDate = filters.start_date || '';
  let endDate = filters.end_date || '';
  
  // Check specific permissions
  $: canCreate = can.donatur.create();
  $: canEdit = can.donatur.update();
  $: canDelete = can.donatur.delete();
  $: canImport = can.donatur.import();
  $: canExport = can.donatur.export();

  // Import modal state
  let showImportModal = false;
  let importFile = null;
  let importFileInput;

  // Current date for max date attribute
  let currentDate = new Date().toISOString().split('T')[0];
  
  // Debounce search
  let searchTimeout;
  function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      applyFilters();
    }, 300);
  }
  
  // Debounce kode donatur search
  let kodeDonaturTimeout;
  function handleKodeDonaturSearch() {
    clearTimeout(kodeDonaturTimeout);
    kodeDonaturTimeout = setTimeout(() => {
      applyFilters();
    }, 300);
  }
  
  function applyFilters() {
    const params = {};
    if (search) params.search = search;
    if (kodeDonatur) params.kode_donatur = kodeDonatur;
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;
    
    router.get('/admin/donatur', params, {
      preserveState: true,
      preserveScroll: true
    });
  }
  
  function resetFilters() {
    search = '';
    kodeDonatur = '';
    startDate = '';
    endDate = '';
    router.get('/admin/donatur', {}, { preserveScroll: true });
  }
  
  function handleCreate() {
    router.visit('/admin/donatur/create');
  }
  
  function handleView(donationId) {
    router.visit(`/admin/donatur/${donationId}`);
  }
  
  function handleEdit(donationId) {
    router.visit(`/admin/donatur/${donationId}/edit`);
  }
  
  async function handleDelete(donation) {
    const confirmed = await dialog.confirmDelete(`donatur ${donation.nama_donatur}`);
    if (confirmed) {
      router.delete(`/admin/donatur/${donation.id}`, {
        onSuccess: () => {
          toast.success('Donatur berhasil dihapus');
        },
        onError: (errors) => {
          console.error('Delete error:', errors);

          // Display specific error message from backend
          let errorMessage = 'Gagal menghapus donatur';
          if (errors.error) {
            errorMessage = errors.error;
          }

          toast.error(errorMessage, 'Error', { duration: 8000 });
        }
      });
    }
  }

  function exportData() {
    const params = new URLSearchParams();

    if (search) params.append('search', search);
    if (kodeDonatur) params.append('kode_donatur', kodeDonatur);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);

    const exportUrl = `/admin/donatur-export?${params.toString()}`;
    window.location.href = exportUrl;
  }

  function downloadTemplate() {
    window.location.href = '/admin/donatur-template';
  }

  function openImportModal() {
    showImportModal = true;
  }

  function closeImportModal() {
    showImportModal = false;
    importFile = null;
    if (importFileInput) importFileInput.value = '';
  }

  function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
      const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
      if (!validTypes.includes(file.type)) {
        toast.error('Hanya file Excel (.xlsx, .xls) yang diperbolehkan.');
        event.target.value = '';
        return;
      }

      if (file.size > 10 * 1024 * 1024) {
        toast.error('Ukuran file maksimal 10MB.');
        event.target.value = '';
        return;
      }

      importFile = file;
    }
  }

  function submitImport() {
    if (!importFile) {
      toast.warning('File Belum Dipilih', 'Silakan pilih file Excel terlebih dahulu');
      return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const formData = new FormData();
    formData.append('file', importFile);
    formData.append('_token', csrfToken);

    router.post('/admin/donatur-import', formData, {
      forceFormData: true,
      preserveState: false,
      preserveScroll: false,
      onSuccess: () => {
        closeImportModal();
      },
      onError: (errors) => {
        console.error('Import error:', errors);
        toast.error('Error saat import: ' + (errors.file || errors.message || 'Unknown error'));
      }
    });
  }

  function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }
  
  function formatJenisWakaf(jenisArray, donation) {
    // Ensure donation object exists
    if (!donation) return '-';
    
    // Always display types based on actual counts, regardless of jenis_wakaf_dipilih
    // This ensures IQRA is shown even if it wasn't in the original selection
    const labels = [];
    
    if (donation.total_a5_count > 0) {
      labels.push(`A5 (${donation.total_a5_count})`);
    }
    if (donation.total_a6_count > 0) {
      labels.push(`A6 (${donation.total_a6_count})`);
    }
    if (donation.total_iqra_count > 0) {
      labels.push(`IQRA (${donation.total_iqra_count})`);
    }
    
    return labels.length > 0 ? labels.join(', ') : '-';
  }
  
  // Helper function to build pagination URL
  function buildPaginationUrl(page) {
    const params = new URLSearchParams();
    params.set('page', page);
    
    // Preserve current filters
    if (search) params.set('search', search);
    if (kodeDonatur) params.set('kode_donatur', kodeDonatur);
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);
    
    return `/admin/donatur?${params.toString()}`;
  }
</script>

<svelte:head>
  <title>Manajemen Donasi Wakaf - Admin Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <!-- Page Header -->
  <div class="mb-6 lg:mb-8">
    <div class="flex flex-col space-y-4 lg:flex-row lg:justify-between lg:items-center lg:space-y-0">
      <div>
        <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mb-2">Manajemen Donasi Wakaf</h2>
        <p class="text-gray-600">Sistem baru: Donatur dapat mewakafkan Al-Qur'an untuk diri sendiri atau orang lain</p>
      </div>
      
      <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:space-x-2">
        {#if canImport}
          <button
            on:click={downloadTemplate}
            class="inline-flex items-center justify-center px-3 py-2 lg:px-4 lg:py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors"
            title="Download template Excel untuk import data"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            <span class="hidden sm:inline">Template</span>
            <span class="sm:hidden">Template</span>
          </button>
          <button
            on:click={openImportModal}
            class="inline-flex items-center justify-center px-3 py-2 lg:px-4 lg:py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <span class="hidden sm:inline">Import Excel</span>
            <span class="sm:hidden">Import</span>
          </button>
        {/if}
        {#if canExport}
          <button
            on:click={exportData}
            class="inline-flex items-center justify-center px-3 py-2 lg:px-4 lg:py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="hidden sm:inline">Export Excel</span>
            <span class="sm:hidden">Export</span>
          </button>
        {/if}
        {#if canCreate}
          <button
            on:click={handleCreate}
            class="bg-[#eb3434] hover:bg-red-600 text-white px-3 py-2 lg:px-4 lg:py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            <span class="hidden sm:inline">Tambah Donasi</span>
            <span class="sm:hidden">Tambah</span>
          </button>
        {/if}
      </div>
    </div>
  </div>
    
  <!-- Statistics Cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-6 mb-6">
    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 lg:w-6 lg:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
        </div>
        <div class="ml-3 lg:ml-4 min-w-0 flex-1">
          <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">Total Donatur</p>
          <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.total_donatur || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 lg:w-6 lg:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
          </svg>
        </div>
        <div class="ml-3 lg:ml-4 min-w-0 flex-1">
          <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">Total Qur'an A5</p>
          <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.total_quran_a5 || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 lg:w-6 lg:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
          </svg>
        </div>
        <div class="ml-3 lg:ml-4 min-w-0 flex-1">
          <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">Total Qur'an A6</p>
          <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.total_quran_a6 || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 lg:w-6 lg:h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
          </svg>
        </div>
        <div class="ml-3 lg:ml-4 min-w-0 flex-1">
          <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">Total Iqra</p>
          <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.total_iqra || 0}</p>
        </div>
      </div>
    </div>
  </div>
    
  <!-- Search & Filter -->
  <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6 mb-6">
    <div class="space-y-4">
      <!-- Search Row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Search -->
        <div>
          <label for="search-donasi" class="block text-sm font-medium text-gray-700 mb-2">Cari Donasi</label>
          <input
            type="text"
            id="search-donasi"
            bind:value={search}
            on:input={handleSearch}
            placeholder="Nama donatur, nomor HP, atau email..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
          />
        </div>
        
        <!-- Kode Donatur Filter -->
        <div>
          <label for="filter-kode-donatur" class="block text-sm font-medium text-gray-700 mb-2">Filter Kode Donatur</label>
          <input
            type="text"
            id="filter-kode-donatur"
            bind:value={kodeDonatur}
            on:input={handleKodeDonaturSearch}
            placeholder="Masukkan kode donatur..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
          />
        </div>
      </div>
      
      <!-- Date Range Filter and Reset Button -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
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
      
      <!-- Active Filters -->
      {#if startDate || endDate || search || kodeDonatur}
        <div class="flex flex-wrap gap-2">
          {#if search}
            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
              <span>Pencarian: {search}</span>
              <button 
                on:click={() => { search = ''; applyFilters(); }}
                class="ml-2 text-blue-600 hover:text-blue-800"
              >
                &times;
              </button>
            </div>
          {/if}
          
          {#if kodeDonatur}
            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-indigo-100 text-indigo-800">
              <span>Kode: {kodeDonatur}</span>
              <button 
                on:click={() => { kodeDonatur = ''; applyFilters(); }}
                class="ml-2 text-indigo-600 hover:text-indigo-800"
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
        </div>
      {/if}
    </div>
  </div>

  <!-- Donations Table -->
  <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100">
    <div class="px-4 lg:px-6 py-4 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900">Daftar Donasi Wakaf</h3>
      <p class="text-sm text-gray-500">Total: {donatur.total || 0} donasi</p>
    </div>
    
    <!-- Desktop Table -->
    <div class="hidden lg:block overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Donatur</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wakaf</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Donasi Terakhir</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          {#each donatur.data as donation}
            <tr class="hover:bg-gray-50">
              <!-- Donatur Info -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                    <span class="text-sm font-medium text-indigo-800">{donation.nama_donatur ? donation.nama_donatur.charAt(0) : 'D'}</span>
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900">{donation.nama_donatur || 'Nama tidak tersedia'}</div>
                    <div class="text-sm text-gray-500">{donation.no_hp || 'No HP tidak tersedia'}</div>
                    {#if donation.kode_donatur}
                      <div class="text-xs text-gray-600 font-mono">Kode: {donation.kode_donatur}</div>
                    {/if}
                    {#if donation.email}
                      <div class="text-xs text-gray-400">{donation.email}</div>
                    {/if}
                  </div>
                </div>
              </td>
              
              <!-- Wakaf Info -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{formatJenisWakaf(donation.jenis_wakaf_dipilih, donation)}</div>
                <div class="text-sm text-gray-500">Total: {(donation.total_a5_count || 0) + (donation.total_a6_count || 0) + (donation.total_iqra_count || 0)} Al-Qur'an</div>
                <div class="text-xs text-gray-400">
                  {donation.prayer_mode === 'semua_donatur' ? 'Atas nama donatur' : 'Custom individual'}
                </div>
              </td>
              
              <!-- Date -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{formatDate(donation.donation_date)}</div>
                <div class="text-xs text-gray-400">Oleh: {donation.creator?.name || 'Administrator System'}</div>
              </td>
              
              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex flex-wrap gap-1">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {donation.donation_count || 1} donasi
                  </span>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    {(donation.total_a5_count || 0) + (donation.total_a6_count || 0) + (donation.total_iqra_count || 0)} mushaf
                  </span>
                </div>
              </td>
              
              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex justify-end space-x-2">
                  <button
                    on:click={() => handleView(donation.id)}
                    class="text-indigo-600 hover:text-indigo-900 p-1 hover:bg-indigo-50 rounded transition-colors"
                    title="Lihat Detail"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                  </button>
                  {#if canEdit}
                    <button
                      on:click={() => handleEdit(donation.id)}
                      class="text-yellow-600 hover:text-yellow-900 p-1 hover:bg-yellow-50 rounded transition-colors"
                      title="Edit"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </button>
                  {/if}
                  {#if canDelete}
                    <button
                      on:click={() => handleDelete(donation)}
                      class="text-red-600 hover:text-red-900 p-1 hover:bg-red-50 rounded transition-colors"
                      title="Hapus"
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
              <td colspan="5" class="px-6 py-12 text-center">
                <div class="text-gray-500">
                  <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                  </svg>
                  <p class="text-lg font-medium">Tidak ada donasi ditemukan</p>
                  <p class="text-sm">
                    {#if filters.search}Coba ubah kata kunci pencarian atau{/if}
                    {#if canCreate}
                      <button on:click={handleCreate} class="text-blue-600 hover:text-blue-800 underline">tambah donasi baru</button>
                    {/if}
                  </p>
                </div>
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
    </div>

    <!-- Mobile Cards -->
    <div class="lg:hidden">
      {#each donatur.data as donation}
        <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors">
          <div class="flex items-start space-x-3">
            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
              <span class="text-lg font-medium text-indigo-800">{donation.nama_donatur ? donation.nama_donatur.charAt(0) : 'D'}</span>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                  <h4 class="text-sm font-medium text-gray-900 truncate">{donation.nama_donatur || 'Nama tidak tersedia'}</h4>
                  <p class="text-xs text-gray-500">{donation.no_hp || 'No HP tidak tersedia'}</p>
                  {#if donation.kode_donatur}
                    <p class="text-xs text-gray-600 font-mono">Kode: {donation.kode_donatur}</p>
                  {/if}
                  {#if donation.email}
                    <p class="text-xs text-gray-400 truncate">{donation.email}</p>
                  {/if}
                </div>
                <div class="flex space-x-1 ml-2 flex-shrink-0">
                  <button
                    on:click={() => handleView(donation.id)}
                    class="text-indigo-600 hover:text-indigo-900 p-2 hover:bg-indigo-50 rounded-full transition-colors"
                    title="Lihat Detail"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                  </button>
                  {#if canEdit}
                    <button
                      on:click={() => handleEdit(donation.id)}
                      class="text-yellow-600 hover:text-yellow-900 p-2 hover:bg-yellow-50 rounded-full transition-colors"
                      title="Edit"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </button>
                  {/if}
                  {#if canDelete}
                    <button
                      on:click={() => handleDelete(donation)}
                      class="text-red-600 hover:text-red-900 p-2 hover:bg-red-50 rounded-full transition-colors"
                      title="Hapus"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  {/if}
                </div>
              </div>
              
              <div class="mt-2">
                <div class="text-sm text-gray-900">{formatJenisWakaf(donation.jenis_wakaf_dipilih, donation)}</div>
                <div class="text-sm text-gray-500">Total: {(donation.total_a5_count || 0) + (donation.total_a6_count || 0) + (donation.total_iqra_count || 0)} Al-Qur'an</div>
              </div>
              
              <div class="mt-2 flex flex-wrap gap-1">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  {donation.donation_count || 1} donasi
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  {(donation.total_a5_count || 0) + (donation.total_a6_count || 0) + (donation.total_iqra_count || 0)} mushaf
                </span>
              </div>
              
              <div class="mt-2 text-xs text-gray-500">
                <div>Tanggal: {formatDate(donation.donation_date)}</div>
                <div>Oleh: {donation.creator?.name || 'Administrator System'}</div>
              </div>
            </div>
          </div>
        </div>
      {:else}
        <div class="p-8 text-center">
          <div class="text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <p class="text-lg font-medium">Tidak ada donasi ditemukan</p>
            <p class="text-sm">
              {#if filters.search}Coba ubah kata kunci pencarian atau{/if}
              <button on:click={handleCreate} class="text-blue-600 hover:text-blue-800 underline">tambah donasi baru</button>
            </p>
          </div>
        </div>
      {/each}
    </div>
    
    <!-- Pagination -->
    {#if donatur.last_page > 1}
      <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div class="text-sm text-gray-500 text-center sm:text-left">
            Menampilkan {donatur.from} - {donatur.to} dari {donatur.total} hasil
          </div>
          <div class="flex justify-center sm:justify-end items-center space-x-1">
            <!-- Previous -->
            {#if donatur.prev_page_url}
              <button
                on:click={() => router.visit(donatur.prev_page_url, { preserveScroll: true })}
                class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center"
              >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span class="hidden sm:inline">Sebelumnya</span>
                <span class="sm:hidden">Prev</span>
              </button>
            {:else}
              <span class="px-3 py-2 text-sm text-gray-300 cursor-not-allowed rounded-md flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span class="hidden sm:inline">Sebelumnya</span>
                <span class="sm:hidden">Prev</span>
              </span>
            {/if}
            
            <!-- Page Numbers -->
            {#if donatur.last_page <= 7}
              <!-- Show all pages if 7 or less -->
              {#each Array(donatur.last_page) as _, i}
                {@const pageNum = i + 1}
                <button
                  on:click={() => router.visit(buildPaginationUrl(pageNum), { preserveScroll: true })}
                  class="px-3 py-2 text-sm rounded-md {pageNum === donatur.current_page ? 'bg-red-600 text-white font-medium' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}"
                >
                  {pageNum}
                </button>
              {/each}
            {:else}
              <!-- Show smart pagination for more than 7 pages -->
              
              <!-- First page -->
              <button
                on:click={() => router.visit(buildPaginationUrl(1), { preserveScroll: true })}
                class="px-3 py-2 text-sm rounded-md {1 === donatur.current_page ? 'bg-red-600 text-white font-medium' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}"
              >
                1
              </button>
              
              <!-- Dots if current page is far from start -->
              {#if donatur.current_page > 4}
                <span class="px-2 py-2 text-sm text-gray-400">...</span>
              {/if}
              
              <!-- Pages around current page -->
              {@const startPage = Math.max(2, donatur.current_page - 1)}
              {@const endPage = Math.min(donatur.last_page - 1, donatur.current_page + 1)}
              {#each Array(endPage - startPage + 1).fill().map((_, i) => startPage + i) as pageNum}
                {#if pageNum !== 1 && pageNum !== donatur.last_page && pageNum >= 2 && pageNum <= donatur.last_page - 1}
                  <button
                    on:click={() => router.visit(buildPaginationUrl(pageNum), { preserveScroll: true })}
                    class="px-3 py-2 text-sm rounded-md {pageNum === donatur.current_page ? 'bg-red-600 text-white font-medium' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}"
                  >
                    {pageNum}
                  </button>
                {/if}
              {/each}
              
              <!-- Dots if current page is far from end -->
              {#if donatur.current_page < donatur.last_page - 3}
                <span class="px-2 py-2 text-sm text-gray-400">...</span>
              {/if}
              
              <!-- Last page -->
              {#if donatur.last_page > 1}
                <button
                  on:click={() => router.visit(buildPaginationUrl(donatur.last_page), { preserveScroll: true })}
                  class="px-3 py-2 text-sm rounded-md {donatur.last_page === donatur.current_page ? 'bg-red-600 text-white font-medium' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}"
                >
                  {donatur.last_page}
                </button>
              {/if}
            {/if}
            
            <!-- Next -->
            {#if donatur.next_page_url}
              <button
                on:click={() => router.visit(donatur.next_page_url, { preserveScroll: true })}
                class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center"
              >
                <span class="hidden sm:inline">Selanjutnya</span>
                <span class="sm:hidden">Next</span>
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            {:else}
              <span class="px-3 py-2 text-sm text-gray-300 cursor-not-allowed rounded-md flex items-center">
                <span class="hidden sm:inline">Selanjutnya</span>
                <span class="sm:hidden">Next</span>
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </span>
            {/if}
          </div>
        </div>
      </div>
    {/if}
  </div>

  <!-- Import Modal -->
  {#if showImportModal}
    <div class="fixed inset-0 overflow-y-auto" style="z-index: 9999;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
          on:click={closeImportModal}
          on:keydown={(e) => e.key === 'Escape' && closeImportModal()}
          role="button"
          tabindex="0"
          aria-label="Close modal"
          transition:fade={{ duration: 200 }}
        ></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div
          class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
          transition:scale={{ duration: 200, start: 0.95 }}
        >
          <div class="sm:flex sm:items-start">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
              <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                Import Data Donatur
              </h3>
              <div class="mt-4">
                <p class="text-sm text-gray-500 mb-4">
                  Upload file Excel (.xlsx atau .xls) untuk import data donatur secara bulk.
                </p>

                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-indigo-500 transition-colors">
                  <input
                    type="file"
                    accept=".xlsx,.xls"
                    on:change={handleFileSelect}
                    bind:this={importFileInput}
                    class="hidden"
                    id="import-file-input"
                  />
                  <label for="import-file-input" class="cursor-pointer">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p class="mt-2 text-sm text-gray-600">
                      {importFile ? importFile.name : 'Klik untuk memilih file atau drag & drop'}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">
                      Excel files up to 10MB
                    </p>
                  </label>
                </div>

                <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-3">
                  <p class="text-xs text-blue-800">
                    <strong>Tips:</strong> Download template terlebih dahulu untuk format yang benar.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-2">
            <button
              type="button"
              on:click={submitImport}
              disabled={!importFile}
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Import
            </button>
            <button
              type="button"
              on:click={closeImportModal}
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm"
            >
              Batal
            </button>
          </div>
        </div>
      </div>
    </div>
  {/if}
</AdminLayout>
