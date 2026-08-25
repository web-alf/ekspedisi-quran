<script>
  import { onMount } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import { showWarning, showError, showInfo } from '../../stores/toast.js';
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  
  // Props (unused build-time props removed)
  export let warehouseUsers = [];
  export let assignablePengiriman = {};
  export let todayTasks = [];
  export let stats = {};
  
  // State
  let selectedItems = [];
  let selectedUser = '';
  let isAssigning = false;
  let searchQuery = '';
  let showAssignedOnly = false;
  
  // Computed
  $: filteredPengiriman = assignablePengiriman.data?.filter(item => {
    const matchesSearch = !searchQuery || 
      item.no_resi.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (item.donatur?.nama_donatur || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
      (item.wakafItem?.wakif_name || item.donatur?.nama_donatur || '').toLowerCase().includes(searchQuery.toLowerCase());
    return matchesSearch;
  }) || [];
  
  function toggleSelectAll() {
    if (selectedItems.length === filteredPengiriman.length) {
      selectedItems = [];
    } else {
      selectedItems = filteredPengiriman.map(item => item.id);
    }
  }
  
  function toggleSelectItem(itemId) {
    if (selectedItems.includes(itemId)) {
      selectedItems = selectedItems.filter(id => id !== itemId);
    } else {
      selectedItems = [...selectedItems, itemId];
    }
  }
  
  async function assignSelectedItems() {
    if (!selectedUser || selectedItems.length === 0) {
      showWarning('Data Belum Lengkap', 'Pilih user dan minimal 1 item untuk di-assign');
      return;
    }
    
    isAssigning = true;
    
    try {
      const response = await fetch('/admin/supervisor/assign-items', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
          user_id: selectedUser,
          pengiriman_ids: selectedItems
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        showInfo('Informasi', data.message);
        selectedItems = [];
        selectedUser = '';
        router.reload();
      } else {
        showError('Gagal Assign Items', data.message || 'Gagal assign items');
      }
    } catch (err) {
      console.error('Error assigning items:', err);
      showError('Kesalahan', 'Terjadi kesalahan saat assign items');
    } finally {
      isAssigning = false;
    }
  }
  
  function getUserTaskInfo(userId) {
    const task = todayTasks.find(t => t.user_id == userId);
    if (!task) return 'Belum ada tugas';
    return `${task.total_selesai}/${task.total_target} (${task.status})`;
  }
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Manual Assignment</h1>
          <p class="text-gray-600">Assign pengiriman secara manual ke staff gudang</p>
        </div>
        <div class="flex items-center space-x-4">
          <a 
            href="/admin/supervisor/warehouse-monitor"
            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            ← Kembali ke Monitor
          </a>
        </div>
      </div>
    </div>
    
    <!-- Assignment Form (Aksi Cepat) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Aksi Cepat</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- User Selection -->
        <div>
          <label for="staff-gudang" class="block text-sm font-medium text-gray-700 mb-2">Pilih Staff Gudang</label>
          <select 
            id="staff-gudang"
            bind:value={selectedUser}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
          >
            <option value="">-- Pilih Staff --</option>
            {#each warehouseUsers as user}
              <option value={user.id}>
                {user.name} ({getUserTaskInfo(user.id)})
              </option>
            {/each}
          </select>
        </div>
        
        <!-- Search -->
        <div>
          <label for="search-pengiriman" class="block text-sm font-medium text-gray-700 mb-2">Cari Pengiriman</label>
          <input
            id="search-pengiriman"
            type="text"
            bind:value={searchQuery}
            placeholder="Cari no resi, donatur, wakif..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
          />
        </div>
        
        <!-- Actions -->
        <div class="flex items-end space-x-2">
          <button
            on:click={assignSelectedItems}
            disabled={!selectedUser || selectedItems.length === 0 || isAssigning}
            class="flex-1 px-4 py-2 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isAssigning ? 'Assigning...' : `Assign ${selectedItems.length} Item`}
          </button>
        </div>
      </div>
      
      <!-- Selection Info -->
      {#if selectedItems.length > 0}
        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
          <p class="text-sm text-blue-800">
            <span class="font-medium">{selectedItems.length} item dipilih</span>
            {#if selectedUser}
              - akan di-assign ke <span class="font-medium">{warehouseUsers.find(u => u.id == selectedUser)?.name}</span>
            {/if}
          </p>
        </div>
      {/if}
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-600">Total Tersedia</p>
        <p class="text-2xl font-bold text-blue-600">{stats.total_assignable}</p>
      </div>
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-600">Sudah Assigned</p>
        <p class="text-2xl font-bold text-green-600">{stats.total_assigned_today}</p>
      </div>
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-600">Dipilih</p>
        <p class="text-2xl font-bold text-orange-600">{selectedItems.length}</p>
      </div>
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-600">Staff Aktif</p>
        <p class="text-2xl font-bold text-gray-900">{warehouseUsers.length}</p>
      </div>
    </div>
    
    <!-- Today's Tasks Summary -->
    {#if todayTasks.length > 0}
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Tugas Hari Ini</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {#each todayTasks as task}
            <div class="p-4 border rounded-lg {task.status === 'completed' ? 'bg-green-50 border-green-200' : task.status === 'in_progress' ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'}">
              <h3 class="font-medium text-gray-900">{task.user_name}</h3>
              <p class="text-sm text-gray-600">
                Progress: {task.total_selesai}/{task.total_target}
              </p>
              <p class="text-xs capitalize {task.status === 'completed' ? 'text-green-600' : task.status === 'in_progress' ? 'text-blue-600' : 'text-gray-600'}">
                {task.status}
              </p>
            </div>
          {/each}
        </div>
      </div>
    {/if}
    
    <!-- Assignable Items Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Pengiriman Tersedia</h2>
        <div class="flex items-center space-x-4">
          <span class="text-sm text-gray-600">
            Menampilkan {filteredPengiriman.length} dari {assignablePengiriman.total || 0} item
          </span>
          {#if filteredPengiriman.length > 0}
            <button
              on:click={toggleSelectAll}
              class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors"
            >
              {selectedItems.length === filteredPengiriman.length ? 'Unselect All' : 'Select All'}
            </button>
          {/if}
        </div>
      </div>
      
      {#if filteredPengiriman.length > 0}
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left">
                  <input
                    type="checkbox"
                    checked={selectedItems.length === filteredPengiriman.length && filteredPengiriman.length > 0}
                    on:change={toggleSelectAll}
                    class="rounded border-gray-300"
                  />
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No Resi</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Donatur</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wakif</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              {#each filteredPengiriman as item}
                <tr class="hover:bg-gray-50 {selectedItems.includes(item.id) ? 'bg-blue-50' : ''}">
                  <td class="px-4 py-3 whitespace-nowrap">
                    <input
                      type="checkbox"
                      checked={selectedItems.includes(item.id)}
                      on:change={() => toggleSelectItem(item.id)}
                      class="rounded border-gray-300"
                    />
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm font-mono">{item.no_resi}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm">{item.donatur?.nama_donatur || 'N/A'}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm">{item.wakafItem?.wakif_name || item.donatur?.nama_donatur || 'N/A'}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm">{item.jenisQuran?.nama_jenis || 'N/A'}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-center">{item.jumlah_quran}</td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{item.created_at}</td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        {#if assignablePengiriman.links}
          <div class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-600">
              Menampilkan {assignablePengiriman.from || 0} - {assignablePengiriman.to || 0} dari {assignablePengiriman.total || 0} hasil
            </div>
            <div class="flex space-x-2">
              {#each assignablePengiriman.links as link}
                {#if link.url}
                  <a
                    href={link.url}
                    class="px-3 py-2 text-sm rounded-lg {link.active ? 'bg-[#eb3434] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'} transition-colors"
                  >
                    {@html link.label}
                  </a>
                {:else}
                  <span class="px-3 py-2 text-sm text-gray-400">
                    {@html link.label}
                  </span>
                {/if}
              {/each}
            </div>
          </div>
        {/if}
      {:else}
        <div class="text-center py-8 text-gray-500">
          <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
          </svg>
          <p class="font-medium mb-2">Tidak ada pengiriman yang tersedia</p>
          <p class="text-sm">
            {#if searchQuery}
              Tidak ada hasil untuk pencarian "{searchQuery}"
            {:else}
              Semua pengiriman sudah di-assign atau belum memenuhi syarat
            {/if}
          </p>
        </div>
      {/if}
    </div>
  </div>
</AdminLayout>
