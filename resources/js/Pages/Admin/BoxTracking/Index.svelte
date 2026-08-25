<script>
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { router } from '@inertiajs/svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import { can } from '../../../utils/permissions.js';

  // Props
  export let boxes = {};
  export let filters = {};
  export let jenisQuranList = [];
  export let warehouseUsers = [];
  export let stats = {};
  export const errors = {};
  export const flash = {};
  export const auth = {};

  // State
  let searchQuery = filters.search || '';
  let selectedStatus = filters.status || '';
  let selectedJenisQuran = filters.jenis_quran_id || '';
  let selectedUser = filters.user_id || '';
  let startDate = filters.start_date || '';
  let endDate = filters.end_date || '';
  let searchTimeout;

  // Current date for max date attribute
  let currentDate = new Date().toISOString().split('T')[0];

  // Permission checks
  $: canRead = can.warehouse?.boxes?.view() ?? true;
  $: canTrack = can.warehouse?.boxes?.view() ?? true;

  function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      applyFilters();
    }, 300);
  }

  function applyFilters() {
    const params = {};
    if (searchQuery) params.search = searchQuery;
    if (selectedStatus) params.status = selectedStatus;
    if (selectedJenisQuran) params.jenis_quran_id = selectedJenisQuran;
    if (selectedUser) params.user_id = selectedUser;
    if (startDate) params.start_date = startDate;
    if (endDate) params.end_date = endDate;
    
    router.get('/admin/box-tracking', params, {
      preserveState: true,
      preserveScroll: true
    });
  }

  function resetFilters() {
    searchQuery = '';
    selectedStatus = '';
    selectedJenisQuran = '';
    selectedUser = '';
    startDate = '';
    endDate = '';
    router.get('/admin/box-tracking');
  }

  function viewBoxDetail(boxId) {
    router.visit(`/admin/box-tracking/${boxId}`);
  }

  function getProgressBarColor(percentage) {
    if (percentage === 0) return 'bg-gray-200';
    if (percentage < 50) return 'bg-blue-500';
    if (percentage < 100) return 'bg-yellow-500';
    return 'bg-green-500';
  }

  function printBoxLabel(boxId) {
    window.open(`/admin/thermal-print/box/${boxId}`, '_blank');
  }
</script>

<svelte:head>
  <title>Tracking Kerdus - Admin</title>
</svelte:head>

<AdminLayout>
  <!-- Header -->
  <div class="mb-6">
    <div class="flex flex-col space-y-4 lg:flex-row lg:justify-between lg:items-center lg:space-y-0">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Tracking Kerdus</h2>
        <p class="text-gray-600">Monitor dan tracking semua kerdus packing</p>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
      <div class="text-sm font-medium text-gray-600">Total Kerdus</div>
      <div class="text-2xl font-bold text-gray-900">{stats.total_boxes || 0}</div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
      <div class="text-sm font-medium text-gray-600">Kosong</div>
      <div class="text-2xl font-bold text-gray-500">{stats.empty_boxes || 0}</div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
      <div class="text-sm font-medium text-gray-600">Sedang Diisi</div>
      <div class="text-2xl font-bold text-blue-600">{stats.filling_boxes || 0}</div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
      <div class="text-sm font-medium text-gray-600">Penuh</div>
      <div class="text-2xl font-bold text-yellow-600">{stats.full_boxes || 0}</div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
      <div class="text-sm font-medium text-gray-600">Tersegel</div>
      <div class="text-2xl font-bold text-green-600">{stats.sealed_boxes || 0}</div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
      <div class="text-sm font-medium text-gray-600">Total Items</div>
      <div class="text-2xl font-bold text-[#eb3434]">{stats.total_items_packed || 0}</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter & Pencarian</h3>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
      <!-- Search -->
      <div>
        <label for="search-input" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
        <input
          id="search-input"
          type="text"
          bind:value={searchQuery}
          on:input={handleSearch}
          placeholder="Kode kerdus, user, jenis..."
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
        />
      </div>

      <!-- Status Filter -->
      <div>
        <label for="status-select" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
        <select
          id="status-select"
          bind:value={selectedStatus}
          on:change={applyFilters}
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
        >
          <option value="">Semua Status</option>
          <option value="empty">Kosong</option>
          <option value="filling">Sedang Diisi</option>
          <option value="full">Penuh</option>
          <option value="sealed">Tersegel</option>
        </select>
      </div>

      <!-- Jenis Quran Filter -->
      <div>
        <label for="jenis-quran-select" class="block text-sm font-medium text-gray-700 mb-2">Jenis Quran</label>
        <select
          id="jenis-quran-select"
          bind:value={selectedJenisQuran}
          on:change={applyFilters}
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
        >
          <option value="">Semua Jenis</option>
          {#each jenisQuranList as jenis}
            <option value={jenis.id}>{jenis.nama_jenis}</option>
          {/each}
        </select>
      </div>

      <!-- User Filter -->
      <div>
        <label for="user-select" class="block text-sm font-medium text-gray-700 mb-2">User</label>
        <select
          id="user-select"
          bind:value={selectedUser}
          on:change={applyFilters}
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
        >
          <option value="">Semua User</option>
          {#each warehouseUsers as user}
            <option value={user.id}>{user.name}</option>
          {/each}
        </select>
      </div>
    </div>

    <!-- Date Range -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <label for="start-date" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
        <input
          id="start-date"
          type="date"
          bind:value={startDate}
          on:change={applyFilters}
          max={currentDate}
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
        />
      </div>
      <div>
        <label for="end-date" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
        <input
          id="end-date"
          type="date"
          bind:value={endDate}
          on:change={applyFilters}
          max={currentDate}
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
        />
      </div>
    </div>

    <!-- Reset Button -->
    <div class="flex justify-end">
      <button
        on:click={resetFilters}
        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors"
      >
        Reset Filter
      </button>
    </div>
  </div>

  <!-- Boxes Table -->
  <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900">Daftar Kerdus</h3>
    </div>

    {#if boxes.data.length > 0}
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Kerdus</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Quran</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seal Code</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            {#each boxes.data as box}
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{box.kode_kerdus}</div>
                  <div class="text-sm text-gray-500">{box.item_count} items</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {box.status_info.bg} {box.status_info.text}">
                    {box.status_info.label}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {box.jenis_quran}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                      <div class="h-2 rounded-full {getProgressBarColor(box.progress_percentage)}" style="width: {box.progress_percentage}%"></div>
                    </div>
                    <span class="text-sm text-gray-600">{box.terisi}/{box.kapasitas}</span>
                  </div>
                  <div class="text-xs text-gray-500">{box.progress_percentage}%</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {box.user_name}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {box.seal_code || '-'}
                  {#if box.sealed_at}
                    <div class="text-xs text-gray-500">{box.sealed_at}</div>
                  {/if}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {box.created_at}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                  <div class="flex space-x-3 justify-center">
                    {#if canRead}
                      <button
                        on:click={() => viewBoxDetail(box.id)}
                        class="text-[#eb3434] hover:text-red-700"
                        title="Detail"
                        aria-label="Lihat detail kerdus"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span class="sr-only">Detail</span>
                      </button>
                    {/if}
                    <button
                      on:click={() => printBoxLabel(box.id)}
                      class="text-blue-600 hover:text-blue-800"
                      title="Print Label Kerdus"
                      aria-label="Print label kerdus"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                      </svg>
                      <span class="sr-only">Print</span>
                    </button>
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      {#if boxes.links && boxes.links.length > 3}
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div class="mb-4 sm:mb-0">
              <p class="text-sm text-gray-700">
                Menampilkan 
                <span class="font-medium">{boxes.from || 0}</span>
                sampai 
                <span class="font-medium">{boxes.to || 0}</span>
                dari 
                <span class="font-medium">{boxes.total || 0}</span>
                kerdus
              </p>
            </div>
            <div class="flex items-center space-x-1">
              {#each boxes.links as link}
                {#if link.url}
                  <button
                    on:click={() => {
                      const url = new URL(link.url);
                      const params = {};
                      if (searchQuery) params.search = searchQuery;
                      if (selectedStatus) params.status = selectedStatus;
                      if (selectedJenisQuran) params.jenis_quran_id = selectedJenisQuran;
                      if (selectedUser) params.user_id = selectedUser;
                      if (startDate) params.start_date = startDate;
                      if (endDate) params.end_date = endDate;
                      if (url.searchParams.get('page')) params.page = url.searchParams.get('page');
                      
                      router.get('/admin/box-tracking', params, {
                        preserveState: true,
                        preserveScroll: true
                      });
                    }}
                    class="px-3 py-2 text-sm font-medium rounded-md {link.active ? 'bg-[#eb3434] text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'} border border-gray-300"
                    disabled={!link.url}
                  >
                    {@html link.label}
                  </button>
                {:else}
                  <span class="px-3 py-2 text-sm font-medium text-gray-400 border border-gray-300 rounded-md">
                    {@html link.label}
                  </span>
                {/if}
              {/each}
            </div>
          </div>
        </div>
      {/if}
    {:else}
      <div class="p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada kerdus ditemukan</h3>
        <p class="text-gray-600">Belum ada kerdus yang sesuai dengan filter yang dipilih</p>
      </div>
    {/if}
  </div>
</AdminLayout>

<FlashMessage />
