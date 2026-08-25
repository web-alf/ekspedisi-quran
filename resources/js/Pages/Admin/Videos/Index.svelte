<script>
  import { router, page } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import { dialog } from '../../../utils/notifications.js';
  import { hasPermission } from '../../../utils/permissions.js';

  export let videos = {};
  export let videoSettings = [];

  // Permission checks
  $: canCreate = hasPermission('settings.write');
  $: canUpdate = hasPermission('settings.write');
  $: canDelete = hasPermission('settings.delete');

  // Active tab state
  let activeTab = 'videos'; // 'videos' or 'settings'

  // Settings editing state
  let editingSettings = {};
  let savingSettings = false;

  async function deleteVideo(id) {
    const confirmed = await dialog.confirmDelete('video ini');
    if (confirmed) {
      router.delete(`/admin/videos/${id}`);
    }
  }

  function toggleStatus(id) {
    router.post(`/admin/videos/${id}/toggle-status`);
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

    router.post(`/admin/videos/update-settings`, {
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

  function renderSettingValue(setting) {
    const editing = editingSettings[setting.id];
    if (editing) return '';

    switch (setting.type) {
      case 'boolean':
        return setting.value ? 'Ya' : 'Tidak';
      case 'textarea':
        return setting.value || '-';
      default:
        return setting.value || '-';
    }
  }

  // Extract YouTube video ID from URL
  function getYoutubeVideoId(url) {
    if (!url) return null;
    const shortMatch = url.match(/youtu\.be\/([^?]+)/);
    if (shortMatch) return shortMatch[1];
    const watchMatch = url.match(/[?&]v=([^&]+)/);
    if (watchMatch) return watchMatch[1];
    const embedMatch = url.match(/embed\/([^?]+)/);
    if (embedMatch) return embedMatch[1];
    return null;
  }

  // Get thumbnail URL from YouTube
  function getThumbnailUrl(url) {
    const videoId = getYoutubeVideoId(url);
    return videoId ? `https://img.youtube.com/vi/${videoId}/hqdefault.jpg` : null;
  }
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Kelola Video</h1>
          <p class="mt-2 text-gray-600">Kelola koleksi video YouTube untuk halaman utama</p>
        </div>
        {#if canCreate && activeTab === 'videos'}
          <a
            href="/admin/videos/create"
            class="bg-[#eb3434] text-white px-6 py-3 rounded-lg hover:bg-red-600 transition-colors font-medium"
          >
            + Tambah Video
          </a>
        {/if}
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
      <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex space-x-8">
          <button
            on:click={() => activeTab = 'videos'}
            class="py-2 px-1 border-b-2 font-medium text-sm transition-colors {activeTab === 'videos' ? 'border-[#eb3434] text-[#eb3434]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}"
          >
            🎬 Video Items
          </button>
          <button
            on:click={() => activeTab = 'settings'}
            class="py-2 px-1 border-b-2 font-medium text-sm transition-colors {activeTab === 'settings' ? 'border-[#eb3434] text-[#eb3434]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}"
          >
            ⚙️ Pengaturan Video
          </button>
        </nav>
      </div>
    </div>

    <!-- Content -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="p-6">
        {#if activeTab === 'videos'}
          <!-- Video Items Tab -->
          {#if videos.data && videos.data.length > 0}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {#each videos.data as video}
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                  <!-- Video Thumbnail -->
                  <div class="aspect-w-16 aspect-h-9 bg-gray-900 relative">
                    <img
                      src={video.thumbnail_url || getThumbnailUrl(video.video_url)}
                      alt={video.title}
                      class="w-full h-48 object-cover"
                    />
                    <!-- Play Button Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                      <div class="w-12 h-12 bg-red-600 rounded-full flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M8 5v14l11-7z"/>
                        </svg>
                      </div>
                    </div>
                  </div>

                  <!-- Content -->
                  <div class="p-4">
                    <div class="flex items-center gap-2 mb-2">
                      <h3 class="text-lg font-medium text-gray-900 flex-1 line-clamp-1">{video.title}</h3>
                      <span class="px-2 py-1 text-xs font-medium rounded-full {video.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                        {video.is_active ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </div>

                    {#if video.caption}
                      <p class="text-sm text-gray-600 mb-3 line-clamp-2">{video.caption}</p>
                    {/if}

                    <div class="text-xs text-gray-500 mb-3">
                      Order: {video.sort_order}
                    </div>

                    <div class="flex items-center justify-end">
                      <div class="flex items-center gap-2">
                        {#if canUpdate}
                          <button
                            on:click={() => toggleStatus(video.id)}
                            class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title={video.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707"/>
                            </svg>
                          </button>
                        {/if}

                        {#if canUpdate}
                          <a
                            href="/admin/videos/{video.id}/edit"
                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                            title="Edit"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                          </a>
                        {/if}

                        {#if canDelete}
                          <button
                            on:click={() => deleteVideo(video.id)}
                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            title="Hapus"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
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
            {#if videos.last_page > 1}
              <div class="mt-6 flex justify-center">
                <nav class="flex items-center space-x-2">
                  {#each Array.from({length: videos.last_page}, (_, i) => i + 1) as page}
                    <a
                      href="?page={page}"
                      class="px-3 py-2 text-sm rounded-md {page === videos.current_page ? 'bg-[#eb3434] text-white' : 'text-gray-700 hover:bg-gray-100'}"
                    >
                      {page}
                    </a>
                  {/each}
                </nav>
              </div>
            {/if}
          {:else}
            <div class="text-center py-12">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada video</h3>
              <p class="mt-1 text-sm text-gray-500">Mulai dengan menambahkan video YouTube pertama.</p>
              <div class="mt-6">
                {#if canCreate}
                  <a
                    href="/admin/videos/create"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-[#eb3434] hover:bg-red-600"
                  >
                    + Tambah Video
                  </a>
                {:else}
                  <p class="text-sm text-gray-500">Hubungi administrator untuk menambahkan video.</p>
                {/if}
              </div>
            </div>
          {/if}

        {:else if activeTab === 'settings'}
          <!-- Settings Tab -->
          {#if videoSettings && videoSettings.length > 0}
            <div class="space-y-6">
              {#each videoSettings as setting (setting.id)}
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
                              {#if setting.key === 'landing_video_layout'}
                                <option value="grid">Grid</option>
                                <option value="slider">Slider</option>
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
                            {renderSettingValue(setting)}
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
          {:else}
            <div class="text-center py-12">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada pengaturan video</h3>
              <p class="mt-1 text-sm text-gray-500">Jalankan migrasi untuk menambahkan pengaturan video.</p>
            </div>
          {/if}
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>
