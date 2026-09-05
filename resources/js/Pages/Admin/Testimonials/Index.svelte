<script>
  import { router, page } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  import { dialog } from '../../../utils/notifications.js';
  import { hasPermission } from '../../../utils/permissions.js';

  export let testimonials = {};
  export let testimonialSettings = [];

  $: canCreate = hasPermission('settings.write');
  $: canUpdate = hasPermission('settings.write');
  $: canDelete = hasPermission('settings.delete');
  
  // Active tab state
  let activeTab = 'testimonials'; // 'testimonials' or 'settings'
  
  // Settings editing state
  let editingSettings = {};
  let savingSettings = false;
  
  async function deleteTestimonial(id) {
    const confirmed = await dialog.confirmDelete('testimonial ini');
    if (confirmed) {
      router.delete(`/admin/testimonials/${id}`);
    }
  }
  
  function toggleStatus(id) {
    router.post(`/admin/testimonials/${id}/toggle-status`);
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

    // Regular form submission for Testimonial settings
    router.post(`/admin/testimonials/update-settings`, {
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
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Kelola Testimonial</h1>
          <p class="mt-2 text-gray-600">Kelola testimonial yang ditampilkan di halaman utama dan pengaturannya</p>
        </div>
        {#if canCreate && activeTab === 'testimonials'}
          <a
            href="/admin/testimonials/create"
            class="bg-[#eb3434] text-white px-6 py-3 rounded-lg hover:bg-red-600 transition-colors font-medium inline-flex items-center gap-2"
          >
            <HeroIcon name="plus" class="w-5 h-5" />
            Tambah Testimonial
          </a>
        {/if}
      </div>
    </div>
    
    <!-- Tabs -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
      <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex space-x-8">
          <button
            on:click={() => activeTab = 'testimonials'}
            class="py-2 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {activeTab === 'testimonials' ? 'border-[#eb3434] text-[#eb3434]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}"
          >
            <HeroIcon name="chat-bubble-left-right" class="w-4 h-4" />
            Testimonial Items
          </button>
          <button
            on:click={() => activeTab = 'settings'}
            class="py-2 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 {activeTab === 'settings' ? 'border-[#eb3434] text-[#eb3434]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}"
          >
            <HeroIcon name="cog-6-tooth" class="w-4 h-4" />
            Pengaturan Testimonial
          </button>
        </nav>
      </div>
    </div>

    <!-- Content -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="p-6">
        {#if activeTab === 'testimonials'}
          <!-- Testimonial Items Tab -->
          {#if testimonials.data && testimonials.data.length > 0}
            <div class="space-y-6">
              {#each testimonials.data as testimonial}
                <div class="border border-gray-200 rounded-lg p-6">
                  <div class="flex items-start gap-4">
                    {#if testimonial.has_custom_image}
                      <div class="relative">
                        <img 
                          src={testimonial.avatar_url}
                          alt={testimonial.name}
                          class="w-16 h-16 rounded-full object-cover flex-shrink-0"
                        />
                        <div class="absolute -bottom-1 -right-1 bg-blue-500 text-white rounded-full p-1" title="Custom upload">
                          <HeroIcon name="photo" class="w-3 h-3" />
                        </div>
                      </div>
                    {:else}
                      <div class="relative">
                        <img 
                          src={testimonial.default_avatar_url}
                          alt={testimonial.name}
                          class="w-16 h-16 rounded-full object-cover flex-shrink-0 ring-2 ring-gray-300 ring-opacity-50"
                        />
                        <div class="absolute -bottom-1 -right-1 bg-gray-400 text-white rounded-full p-1" title="Default avatar">
                          <HeroIcon name="user" class="w-3 h-3" />
                        </div>
                      </div>
                    {/if}
                    
                    <div class="flex-1">
                      <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-lg font-medium text-gray-900">{testimonial.name}</h3>
                        <span class="px-2 py-1 text-xs font-medium rounded-full {testimonial.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                          {testimonial.is_active ? 'Aktif' : 'Nonaktif'}
                        </span>
                      </div>
                      <p class="text-sm text-gray-600 mb-3">{testimonial.location}</p>
                      <p class="text-sm text-gray-700 italic">"{testimonial.quote}"</p>
                    </div>
                    
                      <div class="flex items-center gap-2">
                        {#if canUpdate}
                          <button
                            on:click={() => toggleStatus(testimonial.id)}
                            class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            title={testimonial.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                          >
                            <HeroIcon name="sun" class="w-5 h-5" />
                          </button>
                        {/if}

                        {#if canUpdate}
                          <a
                            href="/admin/testimonials/{testimonial.id}/edit"
                            class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                            title="Edit"
                          >
                            <HeroIcon name="pencil-square" class="w-5 h-5" />
                          </a>
                        {/if}

                        {#if canDelete}
                          <button
                            on:click={() => deleteTestimonial(testimonial.id)}
                            class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            title="Hapus"
                          >
                            <HeroIcon name="trash" class="w-5 h-5" />
                          </button>
                        {/if}
                      </div>
                  </div>
                </div>
              {/each}
            </div>
            
            <!-- Pagination -->
            {#if testimonials.last_page > 1}
              <div class="mt-6 flex justify-center">
                <nav class="flex items-center space-x-2">
                  {#each Array.from({length: testimonials.last_page}, (_, i) => i + 1) as page}
                    <a
                      href="?page={page}"
                      class="px-3 py-2 text-sm rounded-md {page === testimonials.current_page ? 'bg-[#eb3434] text-white' : 'text-gray-700 hover:bg-gray-100'}"
                    >
                      {page}
                    </a>
                  {/each}
                </nav>
              </div>
            {/if}
          {:else}
            <div class="text-center py-12">
              <HeroIcon name="chat-bubble-left-right" class="mx-auto h-12 w-12 text-gray-400" />
              <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada testimonial</h3>
              <p class="mt-1 text-sm text-gray-500">Mulai dengan menambahkan testimonial pertama.</p>
              <div class="mt-6">
                {#if canCreate}
                  <a
                    href="/admin/testimonials/create"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-[#eb3434] hover:bg-red-600"
                  >
                    + Tambah Testimonial
                  </a>
                {:else}
                  <p class="text-sm text-gray-500">Hubungi administrator untuk menambahkan testimonial.</p>
                {/if}
              </div>
            </div>
          {/if}
        
        {:else if activeTab === 'settings'}
          <!-- Settings Tab -->
          {#if testimonialSettings && testimonialSettings.length > 0}
            <div class="space-y-6">
              {#each testimonialSettings as setting (setting.id)}
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
              <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada pengaturan testimonial</h3>
              <p class="mt-1 text-sm text-gray-500">Jalankan migrasi untuk menambahkan pengaturan testimonial.</p>
            </div>
          {/if}
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>