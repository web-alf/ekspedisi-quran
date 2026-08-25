<script>
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../../Layouts/AdminLayout.svelte';
  import Pagination from '../../../../Components/Pagination.svelte';
  import { slide } from 'svelte/transition';
  import { cubicOut } from 'svelte/easing';
  import { dialog } from '../../../../utils/notifications.js';
  import { showWarning, showInfo } from '../../../../stores/toast.js';

  export let settingsCollection = {};
  export let settingsData = {};
  export let group = 'landing';
  export const groupName = 'Landing Page';
  export let filters = {};

  // Initialize filters from backend
  let searchQuery = filters.search || '';
  let statusFilter = filters.status || 'all';
  let visibilityFilter = filters.visibility || 'all';
  
  // Use paginated data if available, fallback to grouped data
  $: currentSettings = settingsCollection?.data || settingsData || [];

  // Section order management
  $: sectionOrderSetting = currentSettings.find(s => s.key === 'landing_section_order');
  let localSectionOrder = [];
  let sectionOrderSaving = false;

  $: if (sectionOrderSetting) {
    try {
      localSectionOrder = JSON.parse(sectionOrderSetting.value || '[]');
    } catch (e) {
      localSectionOrder = [];
    }
  }

  function moveSection(index, direction) {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= localSectionOrder.length) return;
    const newOrder = [...localSectionOrder];
    const temp = newOrder[index];
    newOrder[index] = newOrder[newIndex];
    newOrder[newIndex] = temp;
    localSectionOrder = newOrder;
  }

  function toggleSectionEnabled(index) {
    const newOrder = [...localSectionOrder];
    newOrder[index] = { ...newOrder[index], enabled: !newOrder[index].enabled };
    localSectionOrder = newOrder;
  }

  function saveSectionOrder() {
    if (!sectionOrderSetting) return;
    sectionOrderSaving = true;
    router.post('/admin/settings/landing-content/landing', {
      settings: [{
        id: sectionOrderSetting.id,
        value: JSON.stringify(localSectionOrder)
      }]
    }, {
      preserveScroll: true,
      onSuccess: () => {
        sectionOrderSaving = false;
        showInfo('Berhasil', 'Urutan section landing page telah diperbarui');
      },
      onError: () => {
        sectionOrderSaving = false;
      }
    });
  }

  let editingSettings = {};
  let saving = false;

  // Bulk Actions
  let selectedSettings = [];
  let bulkAction = '';

  // Apply filters function
  function applyFilters() {
    const params = { group };
    if (searchQuery.trim()) params.search = searchQuery.trim();
    if (statusFilter !== 'all') params.status = statusFilter;
    if (visibilityFilter !== 'all') params.visibility = visibilityFilter;
    
    router.get('/admin/settings/landing-content/landing', params, {
      preserveState: true,
      preserveScroll: true
    });
  }

  // Reset filters function
  function resetFilters() {
    searchQuery = '';
    statusFilter = 'all';
    visibilityFilter = 'all';
    router.get('/admin/settings/landing-content/landing', { group }, {
      preserveState: true,
      preserveScroll: true
    });
  }

  // Debounce search
  let searchTimeout;
  function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      applyFilters();
    }, 300);
  }

  function editSetting(setting) {
    editingSettings[setting.id] = { ...setting };
  }

  function cancelEdit(settingId) {
    delete editingSettings[settingId];
    editingSettings = { ...editingSettings };
  }

  function saveSetting(settingId) {
    const setting = editingSettings[settingId];
    if (!setting) return;

    saving = true;

    // Handle file uploads with FormData
    if (setting.type === 'image' || setting.type === 'file') {
      const formData = new FormData();
      
      // Handle file upload - use the format expected by controller
      const fileInput = document.querySelector(`#file-${settingId}`);
      console.log('File input element:', fileInput);
      console.log('Files:', fileInput ? fileInput.files : 'No input found');
      
      if (fileInput && fileInput.files[0]) {
        // Send file directly with correct naming for Laravel
        formData.append(`file_${setting.id}`, fileInput.files[0]);
        formData.append(`settings[0][id]`, setting.id);
        formData.append(`settings[0][has_file]`, 'true');
        console.log('Appending file:', fileInput.files[0].name, 'for setting ID:', setting.id);
      } else {
        // No new file - keep existing value (for non-file fields or existing images)
        formData.append(`settings[0][id]`, setting.id);
        formData.append(`settings[0][value]`, setting.value || '');
        console.log('No file selected, keeping existing value:', setting.value);
      }

      router.post('/admin/settings/landing-content/landing', formData, {
        preserveScroll: true,
        onSuccess: (page) => {
          // Update local data with response
          if (page.props.settingsData) {
            settingsData = page.props.settingsData;
          }
          delete editingSettings[settingId];
          editingSettings = { ...editingSettings };
          saving = false;
        },
        onError: (errors) => {
          console.error('Upload error:', errors);
          saving = false;
        }
      });
    } else {
      // Use bulk update endpoint for inline editing
      router.post('/admin/settings/landing-content/landing', {
        settings: [{
          id: setting.id,
          value: setting.value
        }]
      }, {
        preserveScroll: true,
        onSuccess: (page) => {
          // Update local data with response
          if (page.props.settingsData) {
            settingsData = page.props.settingsData;
          }
          delete editingSettings[settingId];
          editingSettings = { ...editingSettings };
          saving = false;
        },
        onError: () => {
          saving = false;
        }
      });
    }
  }


  function getFileUrl(value) {
    if (!value) return '';
    if (value.startsWith('http')) return value;
    // Files in public/images/ should be accessed directly
    if (value.startsWith('/images/')) {
      return value;
    }
    // Files starting with /storage/ are already correct
    if (value.startsWith('/storage/')) {
      return value;
    }
    // For other paths starting with /, prepend /storage
    if (value.startsWith('/')) {
      return `/storage${value}`;
    }
    // For relative paths, prepend /storage/
    return `/storage/${value}`;
  }

  function renderValue(setting) {
    const editing = editingSettings[setting.id];
    if (editing) return '';

    switch (setting.type) {
      case 'boolean':
        return setting.value ? 'Ya' : 'Tidak';
      case 'image':
        return setting.value ? `<img src="${getFileUrl(setting.value)}" alt="${setting.label}" class="h-16 w-auto rounded">` : 'Tidak ada gambar';
      case 'file':
        return setting.value ? `<a href="${getFileUrl(setting.value)}" target="_blank" class="text-blue-600 hover:text-blue-800">Lihat File</a>` : 'Tidak ada file';
      case 'json':
        if (!setting.value) return '-';
        try {
          const parsed = JSON.parse(setting.value);
          const itemCount = Array.isArray(parsed) ? parsed.length : 1;
          const preview = Array.isArray(parsed) && parsed.length > 0 
            ? (parsed[0].name || parsed[0].question || parsed[0].src || 'Item 1')
            : 'JSON Object';
          return `<div class="space-y-2">
            <div class="flex items-center gap-2">
              <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">${itemCount} items</span>
              <span class="text-sm text-gray-600">Preview: ${preview}${itemCount > 1 ? ', ...' : ''}</span>
            </div>
            <details class="text-xs">
              <summary class="cursor-pointer text-blue-600 hover:text-blue-800">Lihat JSON</summary>
              <pre class="mt-2 p-3 bg-gray-100 rounded border text-xs overflow-x-auto max-h-40">${JSON.stringify(parsed, null, 2)}</pre>
            </details>
          </div>`;
        } catch (e) {
          return `<span class="text-red-600 text-sm">❌ Invalid JSON: ${e.message}</span>`;
        }
      case 'textarea':
        return setting.value || '-';
      default:
        return setting.value || '-';
    }
  }
  
  // Bulk actions
  function toggleSelectAll() {
    if (selectedSettings.length === currentSettings.length) {
      selectedSettings = [];
    } else {
      selectedSettings = currentSettings.map(s => s.id);
    }
  }
  
  function toggleSelect(settingId) {
    if (selectedSettings.includes(settingId)) {
      selectedSettings = selectedSettings.filter(id => id !== settingId);
    } else {
      selectedSettings = [...selectedSettings, settingId];
    }
  }
  
  function isSelected(settingId) {
    return selectedSettings.includes(settingId);
  }
  
  async function executeBulkAction() {
    if (!bulkAction || selectedSettings.length === 0) return;
    
    if (bulkAction === 'delete') {
      // Delete functionality not available in landing content settings
      showWarning('Tidak Dapat Dihapus', 'Pengaturan landing content tidak dapat dihapus secara individual atau bulk. Silakan edit nilai menjadi kosong jika diperlukan.');
      bulkAction = '';
      return;
    } else if (bulkAction === 'activate' || bulkAction === 'deactivate') {
      // Bulk status update not implemented for landing content settings yet
      showInfo('Fitur Belum Tersedia', 'Fitur bulk aktivasi/deaktivasi belum tersedia untuk pengaturan landing content.');
      bulkAction = '';
      return;
    }
  }
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-4">
            <a href="/admin/settings" class="hover:text-gray-700">Pengaturan</a>
            <span>/</span>
            <a href="/admin/settings" class="hover:text-gray-700">Landing Content</a>
            <span>/</span>
            <span class="text-gray-900">Landing Page</span>
          </nav>
          <h1 class="text-3xl font-bold text-gray-900">Pengaturan Landing Page</h1>
          <p class="mt-2 text-gray-600">Kelola konten halaman utama seperti hero section, statistik, dan call-to-action</p>
        </div>
      </div>
    </div>
    
    <!-- Search & Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Search -->
        <div>
          <label for="search-settings" class="block text-sm font-medium text-gray-700 mb-2">Cari Setting</label>
          <div class="relative">
            <input
              id="search-settings"
              type="text"
              bind:value={searchQuery}
              on:input={handleSearch}
              placeholder="Cari berdasarkan nama, key, atau deskripsi..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
            />
            <svg class="absolute left-3 top-3 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
          </div>
        </div>
        
        <!-- Status Filter -->
        <div>
          <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
          <select
            id="status-filter"
            bind:value={statusFilter}
            on:change={applyFilters}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
          >
            <option value="all">Semua Status</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>
        
        <!-- Visibility Filter -->
        <div>
          <label for="visibility-filter" class="block text-sm font-medium text-gray-700 mb-2">Visibility</label>
          <select
            id="visibility-filter"
            bind:value={visibilityFilter}
            on:change={applyFilters}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
          >
            <option value="all">Semua</option>
            <option value="public">Publik</option>
            <option value="private">Private</option>
          </select>
        </div>
      </div>
      
      <!-- Filter Actions -->
      <div class="mt-4 pt-4 border-t border-gray-200 flex items-center gap-4">
        <button
          on:click={applyFilters}
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
        >
          Terapkan Filter
        </button>
        <button
          on:click={resetFilters}
          class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium"
        >
          Reset Filter
        </button>
      </div>
      
      <!-- Bulk Actions -->
      {#if selectedSettings.length > 0}
        <div class="mt-4 pt-4 border-t border-gray-200 flex items-center gap-4">
          <span class="text-sm text-gray-600">{selectedSettings.length} setting dipilih</span>
          <select
            bind:value={bulkAction}
            class="px-3 py-1 border border-gray-300 rounded text-sm"
          >
            <option value="">Pilih Aksi</option>
            <option value="activate">Aktifkan</option>
            <option value="deactivate">Nonaktifkan</option>
            <option value="delete">Hapus</option>
          </select>
          <button
            on:click={executeBulkAction}
            disabled={!bulkAction}
            class="px-4 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 disabled:opacity-50"
          >
            Terapkan
          </button>
        </div>
      {/if}
    </div>

    <!-- Section Order Management -->
    {#if sectionOrderSetting}
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-xl font-semibold text-gray-900">Urutan Section Landing Page</h2>
            <p class="text-sm text-gray-600 mt-1">Atur urutan dan tampilkan/sembunyikan section pada halaman utama</p>
          </div>
          <button
            on:click={saveSectionOrder}
            disabled={sectionOrderSaving}
            class="px-4 py-2 bg-[#eb3434] text-white rounded-lg hover:bg-red-600 disabled:opacity-50 text-sm font-medium"
          >
            {sectionOrderSaving ? 'Menyimpan...' : 'Simpan Urutan'}
          </button>
        </div>

        <div class="space-y-2">
          {#each localSectionOrder as section, index}
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
              <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-500 w-6">{index + 1}.</span>
                <span class="text-sm font-medium text-gray-900">{section.label || section.id}</span>
                {#if !section.enabled}
                  <span class="px-2 py-0.5 text-xs bg-gray-200 text-gray-600 rounded">Disembunyikan</span>
                {/if}
              </div>
              <div class="flex items-center gap-2">
                <button
                  on:click={() => moveSection(index, -1)}
                  disabled={index === 0}
                  class="p-1.5 text-gray-600 hover:bg-gray-200 rounded disabled:opacity-30"
                  title="Naikkan"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                  </svg>
                </button>
                <button
                  on:click={() => moveSection(index, 1)}
                  disabled={index === localSectionOrder.length - 1}
                  class="p-1.5 text-gray-600 hover:bg-gray-200 rounded disabled:opacity-30"
                  title="Turunkan"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
                <button
                  on:click={() => toggleSectionEnabled(index)}
                  class="p-1.5 rounded {section.enabled ? 'text-green-600 hover:bg-green-50' : 'text-gray-400 hover:bg-gray-200'}"
                  title={section.enabled ? 'Sembunyikan section' : 'Tampilkan section'}
                >
                  {#if section.enabled}
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                  {:else}
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                  {/if}
                </button>
              </div>
            </div>
          {/each}
        </div>
      </div>
    {/if}

    <!-- Main Content -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-gray-900">
            Pengaturan Landing Page ({currentSettings.length})
          </h2>
        </div>
        
        {#if currentSettings.length > 0}
          <!-- Select All -->
          <div class="mb-4 pb-4 border-b border-gray-200">
            <label class="flex items-center">
              <input
                type="checkbox"
                checked={selectedSettings.length === currentSettings.length && selectedSettings.length > 0}
                on:change={toggleSelectAll}
                class="h-4 w-4 text-[#eb3434] focus:ring-[#eb3434] border-gray-300 rounded"
              />
              <span class="ml-2 text-sm text-gray-600">Pilih Semua</span>
            </label>
          </div>

          <div class="space-y-6">
            {#each currentSettings as setting (setting.id)}
              <div class="border border-gray-200 rounded-lg p-6" transition:slide="{{ duration: 200, easing: cubicOut }}">
                <div class="flex items-start gap-4">
                  <!-- Checkbox -->
                  <input
                    type="checkbox"
                    checked={isSelected(setting.id)}
                    on:change={() => toggleSelect(setting.id)}
                    class="mt-1 h-4 w-4 text-[#eb3434] focus:ring-[#eb3434] border-gray-300 rounded"
                  />
                  
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <h3 class="text-lg font-medium text-gray-900">{setting.label}</h3>
                      <span class="px-2 py-1 text-xs font-medium rounded-full {setting.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                        {setting.is_active ? 'Aktif' : 'Nonaktif'}
                      </span>
                      {#if setting.is_public}
                        <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                          Publik
                        </span>
                      {/if}
                    </div>
                    <p class="text-sm text-gray-600 mb-3">{setting.description || 'Tidak ada deskripsi'}</p>
                    <p class="text-xs text-gray-500 mb-4">Key: <code class="bg-gray-100 px-2 py-1 rounded">{setting.key}</code></p>

                    <!-- Value Display/Edit -->
                    {#if editingSettings[setting.id]}
                      <div class="space-y-4">
                        {#if setting.type === 'textarea'}
                          <textarea
                            bind:value={editingSettings[setting.id].value}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                            rows="4"
                            placeholder="Masukkan nilai..."
                          ></textarea>
                        {:else if setting.type === 'boolean'}
                          <select
                            bind:value={editingSettings[setting.id].value}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                          >
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                          </select>
                        {:else if setting.type === 'number'}
                          <input
                            type="number"
                            bind:value={editingSettings[setting.id].value}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                            placeholder="Masukkan angka..."
                          />
                        {:else if setting.type === 'image' || setting.type === 'file'}
                          <input
                            type="file"
                            id="file-{setting.id}"
                            accept={setting.type === 'image' ? 'image/*' : '*/*'}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                          />
                          <p class="mt-1 text-xs text-gray-500">Pilih file baru untuk mengganti</p>
                        {:else if setting.type === 'json'}
                          <div class="space-y-2">
                            <div class="flex justify-end gap-2">
                              <a
                                href="/admin/settings/{setting.id}/edit"
                                class="px-3 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
                              >
                                Edit JSON Detail
                              </a>
                            </div>
                            <textarea
                              bind:value={editingSettings[setting.id].value}
                              rows="6"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent font-mono text-sm"
                              placeholder="Masukkan data JSON..."
                            ></textarea>
                            <p class="text-xs text-gray-500">⚠️ Untuk JSON kompleks, gunakan halaman edit detail untuk tools formatting dan validasi yang lebih lengkap.</p>
                          </div>
                        {:else}
                          <input
                            type="text"
                            bind:value={editingSettings[setting.id].value}
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                            placeholder="Masukkan nilai..."
                          />
                        {/if}

                        <div class="flex gap-2">
                          <button
                            on:click={() => saveSetting(setting.id)}
                            disabled={saving}
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                          >
                            {saving ? 'Menyimpan...' : 'Simpan'}
                          </button>
                          <button
                            on:click={() => cancelEdit(setting.id)}
                            disabled={saving}
                            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 disabled:opacity-50"
                          >
                            Batal
                          </button>
                        </div>
                      </div>
                    {:else}
                      <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm font-medium text-gray-700">Nilai:</span>
                        <div class="mt-1">
                          {@html renderValue(setting)}
                        </div>
                      </div>
                    {/if}
                  </div>

                  {#if !editingSettings[setting.id]}
                    <div class="flex gap-2 ml-4">
                      <button
                        on:click={() => editSetting(setting)}
                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                        title="Edit"
                      >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                      </button>
                    </div>
                  {/if}
                </div>
              </div>
            {/each}
          </div>
          
          <!-- Pagination for paginated data -->
          {#if settingsCollection && settingsCollection.links}
            <div class="mt-6 pt-6 border-t border-gray-200">
              <Pagination 
                data={settingsCollection} 
                additionalParams={{
                  group,
                  search: searchQuery || undefined,
                  status: statusFilter !== 'all' ? statusFilter : undefined,
                  visibility: visibilityFilter !== 'all' ? visibilityFilter : undefined
                }} 
              />
            </div>
          {/if}
        {:else}
          <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada pengaturan landing page</h3>
            <p class="mt-1 text-sm text-gray-500">Mulai dengan menambahkan pengaturan seperti hero section, statistik, dan konten utama halaman.</p>
            <div class="mt-6">
              <a
                href="/admin/settings/create?group=landing"
                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-[#eb3434] hover:bg-red-600"
              >
                + Tambah Setting Landing
              </a>
            </div>
          </div>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>