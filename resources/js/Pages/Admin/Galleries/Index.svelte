<script>
  import { router, page } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { dialog } from '../../../utils/notifications.js';
  import { hasPermission } from '../../../utils/permissions.js';

  export let galleries = {};
  export let gallerySettings = [];

  // Permission checks
  $: canCreate = hasPermission('settings.write');
  $: canUpdate = hasPermission('settings.write');
  $: canDelete = hasPermission('settings.delete');
  
  // Active tab state
  let activeTab = 'galleries'; // 'galleries' or 'settings'
  
  // Settings editing state
  let editingSettings = {};
  let savingSettings = false;
  
  async function deleteGallery(id) {
    const confirmed = await dialog.confirmDelete('gambar gallery ini');
    if (confirmed) {
      router.delete(`/admin/galleries/${id}`);
    }
  }
  
  function toggleStatus(id) {
    router.post(`/admin/galleries/${id}/toggle-status`);
  }
  
  function editSetting(setting) {
    editingSettings[setting.id] = { ...setting };
  }
  
  function cancelEditSetting(settingId) {
    delete editingSettings[settingId];
    editingSettings = { ...editingSettings };
  }
  
  function saveSetting(settingId) {
    const setting = editingSettings[settingId];
    if (!setting) return;

    savingSettings = true;

    // Handle file uploads with FormData
    if (setting.type === 'image' || setting.type === 'file') {
      const formData = new FormData();
      formData.append('_method', 'POST');
      
      // Add setting data
      formData.append(`settings[0][id]`, setting.id);
      formData.append(`settings[0][value]`, setting.value || '');

      // Handle file upload
      const fileInput = document.querySelector(`#file-${settingId}`);
      if (fileInput && fileInput.files[0]) {
        formData.append(`settings.${setting.id}.file`, fileInput.files[0]);
      }

      router.post(`/admin/galleries/update-settings`, formData, {
        preserveScroll: true,
        onSuccess: () => {
          delete editingSettings[settingId];
          editingSettings = { ...editingSettings };
          savingSettings = false;
        },
        onError: () => {
          savingSettings = false;
        }
      });
    } else {
      // Regular form submission for non-file types
      router.post(`/admin/galleries/update-settings`, {
        settings: [{
          id: setting.id,
          value: setting.value
        }]
      }, {
        preserveScroll: true,
        onSuccess: () => {
          delete editingSettings[settingId];
          editingSettings = { ...editingSettings };
          savingSettings = false;
        },
        onError: () => {
          savingSettings = false;
        }
      });
    }
  }
  
  function getFileUrl(value) {
    if (!value) return '';
    if (value.startsWith('http')) return value;
    return `/storage/${value}`;
  }
  
  function renderSettingValue(setting) {
    const editing = editingSettings[setting.id];
    if (editing) return '';

    switch (setting.type) {
      case 'boolean':
        return setting.value ? 'Ya' : 'Tidak';
      case 'image':
        return setting.value ? `<img src="${getFileUrl(setting.value)}" alt="${setting.label}" class="h-16 w-auto rounded">` : 'Tidak ada gambar';
      case 'file':
        return setting.value ? `<a href="${getFileUrl(setting.value)}" target="_blank" class="text-blue-600 hover:text-blue-800">Lihat File</a>` : 'Tidak ada file';
      case 'textarea':
        return setting.value || '-';
      default:
        return setting.value || '-';
    }
  }
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Kelola Gallery</h1>
          <p class="mt-2 text-gray-600">Kelola koleksi gambar dan pengaturan gallery untuk halaman utama</p>
        </div>
        {#if canCreate && activeTab === 'galleries'}
          <a
            href="/admin/galleries/create"
            class="bg-[#eb3434] text-white px-6 py-3 rounded-lg hover:bg-red-600 transition-colors font-medium"
          >
            + Tambah Gallery
          </a>
        {/if}
      </div>
    </div>
    
    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
      <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex space-x-8">
          <button
            on:click={() => activeTab = 'galleries'}
            class="py-2 px-1 border-b-2 font-medium text-sm transition-colors {activeTab === 'galleries' ? 'border-[#eb3434] text-[#eb3434]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}"
          >
            📸 Gallery Items
          </button>
          <button
            on:click={() => activeTab = 'settings'}
            class="py-2 px-1 border-b-2 font-medium text-sm transition-colors {activeTab === 'settings' ? 'border-[#eb3434] text-[#eb3434]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}"
          >
            ⚙️ Pengaturan Gallery
          </button>
        </nav>
      </div>
    </div>

    <!-- Content -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="p-6">
        {#if activeTab === 'galleries'}
          <!-- Gallery Items Tab -->
          {#if galleries.data && galleries.data.length > 0}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {#each galleries.data as gallery}
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                  <!-- Image -->
                  <div class="aspect-w-16 aspect-h-10">
                    <img 
                      src={gallery.image_url}
                      alt={gallery.title}
                      class="w-full h-48 object-cover"
                    />
                  </div>
                  
                  <!-- Content -->
                  <div class="p-4">
                    <div class="flex items-center gap-2 mb-2">
                      <h3 class="text-lg font-medium text-gray-900 flex-1">{gallery.title}</h3>
                      <span class="px-2 py-1 text-xs font-medium rounded-full {gallery.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                        {gallery.is_active ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </div>
                    
                    {#if gallery.caption}
                      <p class="text-sm text-gray-600 mb-3">{gallery.caption}</p>
                    {/if}
                    
                    <div class="flex items-center justify-end">
                      <div class="flex items-center gap-2">
                        {#if canUpdate}
                          <button
                            on:click={() => toggleStatus(gallery.id)}
                            class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title={gallery.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                          >
                            <HeroIcon name="arrow-path" class="w-4 h-4" />
                          </button>
                        {/if}

                        {#if canUpdate}
                          <a
                            href="/admin/galleries/{gallery.id}/edit"
                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                            title="Edit"
                          >
                            <HeroIcon name="pencil-square" class="w-4 h-4" />
                          </a>
                        {/if}

                        {#if canDelete}
                          <button
                            on:click={() => deleteGallery(gallery.id)}
                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            title="Hapus"
                          >
                            <HeroIcon name="trash" class="w-4 h-4" />
                          </button>
                        {/if}

                        {#if !canUpdate && !canDelete}
                          <span class="text-sm text-gray-500 italic">View only</span>
                        {/if}
                      </div>
                    </div>
                  </div>
                </div>
              {/each}
            </div>
            
            <!-- Pagination -->
            {#if galleries.last_page > 1}
              <div class="mt-6 flex justify-center">
                <nav class="flex items-center space-x-2">
                  {#each Array.from({length: galleries.last_page}, (_, i) => i + 1) as page}
                    <a
                      href="?page={page}"
                      class="px-3 py-2 text-sm rounded-md {page === galleries.current_page ? 'bg-[#eb3434] text-white' : 'text-gray-700 hover:bg-gray-100'}"
                    >
                      {page}
                    </a>
                  {/each}
                </nav>
              </div>
            {/if}
          {:else}
            <div class="text-center py-12">
              <HeroIcon name="photo" class="mx-auto h-12 w-12 text-gray-400" />
              <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada gallery</h3>
              <p class="mt-1 text-sm text-gray-500">Mulai dengan menambahkan gambar gallery pertama.</p>
              <div class="mt-6">
                {#if canCreate}
                  <a
                    href="/admin/galleries/create"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-[#eb3434] hover:bg-red-600"
                  >
                    + Tambah Gallery
                  </a>
                {:else}
                  <p class="text-sm text-gray-500">Hubungi administrator untuk menambahkan gallery.</p>
                {/if}
              </div>
            </div>
          {/if}
        
        {:else if activeTab === 'settings'}
          <!-- Settings Tab -->
          {#if gallerySettings && gallerySettings.length > 0}
            <div class="space-y-6">
              {#each gallerySettings as setting (setting.id)}
                <div class="border border-gray-200 rounded-lg p-6">
                  <div class="flex items-start gap-4">
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
                          {:else if setting.type === 'select'}
                            <select
                              bind:value={editingSettings[setting.id].value}
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-transparent"
                            >
                              {#if setting.key === 'landing_gallery_layout'}
                                <option value="grid">Grid</option>
                                <option value="hero">Hero (dengan thumbnail)</option>
                                <option value="slider">Slider</option>
                                <option value="masonry">Masonry</option>
                              {:else}
                                <option value={setting.value}>{setting.value}</option>
                              {/if}
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
                              disabled={savingSettings}
                              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                            >
                              {savingSettings ? 'Menyimpan...' : 'Simpan'}
                            </button>
                            <button
                              on:click={() => cancelEditSetting(setting.id)}
                              disabled={savingSettings}
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
                            {@html renderSettingValue(setting)}
                          </div>
                        </div>
                      {/if}
                    </div>

                    {#if !editingSettings[setting.id] && canUpdate}
                      <div class="flex gap-2 ml-4">
                        <button
                          on:click={() => editSetting(setting)}
                          class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                          title="Edit"
                        >
                          <HeroIcon name="pencil-square" class="w-5 h-5" />
                        </button>
                      </div>
                    {/if}
                  </div>
                </div>
              {/each}
            </div>
          {:else}
            <div class="text-center py-12">
              <HeroIcon name="cog-6-tooth" class="mx-auto h-12 w-12 text-gray-400" />
              <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada pengaturan galeri</h3>
              <p class="mt-1 text-sm text-gray-500">Jalankan migrasi untuk menambahkan pengaturan galeri.</p>
            </div>
          {/if}
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>
